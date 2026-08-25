<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ItemVersion;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * انبارگردانی — ساخت فهرست شمارش، ثبت شمارش، و اعمال مغایرت‌ها.
 *
 * روال طبق خواسته مالک پروژه:
 *   ۱. فهرست کالاها با همه جزئیات **به‌جز موجودی** به مسئول شمارش داده می‌شود.
 *   ۲. شمارش وارد می‌شود.
 *   ۳. سامانه مغایرت‌ها را نشان می‌دهد.
 *   ۴. با ثبت نهایی، موجودی سامانه با سند اصلاحی به شمارش واقعی می‌رسد.
 */
class StocktakeService
{
    /**
     * ساخت یک دوره انبارگردانی و فهرست شمارشش.
     *
     * موجودی لحظه شروع روی هر سطر منجمد می‌شود؛ شمارش ممکن است ساعت‌ها طول
     * بکشد و بدون انجماد، مغایرت‌ها با ورود و خروج همان روز قاطی می‌شوند.
     */
    public function start(Warehouse $warehouse, ?string $notes = null): Stocktake
    {
        return DB::transaction(function () use ($warehouse, $notes) {
            $stocktake = Stocktake::create([
                'code'         => $this->nextCode(),
                'warehouse_id' => $warehouse->id,
                'status'       => Stocktake::STATUS_COUNTING,
                'started_by'   => auth()->id(),
                'started_at'   => now(),
                'notes'        => $notes,
            ]);

            // هر ورژنی که در این انبار سابقه موجودی دارد شمرده می‌شود — حتی
            // آن‌هایی که الان صفرند، چون ممکن است در قفسه چیزی پیدا شود.
            $balances = StockBalance::query()
                ->where('warehouse_id', $warehouse->id)
                ->whereHas('itemVersion.item')
                ->get();

            foreach ($balances as $balance) {
                StocktakeLine::create([
                    'stocktake_id'    => $stocktake->id,
                    'item_version_id' => $balance->item_version_id,
                    'system_quantity' => $balance->quantity,
                ]);
            }

            ActivityLog::record('stocktake_started', $stocktake, [
                'warehouse' => $warehouse->name,
                'lines'     => $balances->count(),
            ]);

            return $stocktake->load('lines');
        });
    }

    /**
     * پایان انبارگردانی: فقط شمارش را می‌بندد.
     *
     * موجودی انبار اینجا دست نمی‌خورد — این کار عمداً از «به‌روزرسانی انبار»
     * جدا شد، چون گاهی انبارگردانی فقط برای گزارش و چک‌کردن است و نمی‌خواهیم
     * موجودی سامانه عوض شود. اعمالِ مغایرت‌ها با applyToStock انجام می‌شود.
     */
    public function finish(Stocktake $stocktake): void
    {
        if (! $stocktake->isEditable()) {
            throw new RuntimeException('این انبارگردانی قبلاً بسته شده است.');
        }

        $stocktake->update([
            'status'    => Stocktake::STATUS_CLOSED,
            'closed_by' => auth()->id(),
            'closed_at' => now(),
        ]);

        ActivityLog::record('stocktake_closed', $stocktake, [
            'discrepancies' => $stocktake->discrepancies()->count(),
            'uncounted'     => $stocktake->lines->count() - $stocktake->countedLines()->count(),
        ]);
    }

    /**
     * به‌روزرسانی انبار: موجودی سامانه با سند اصلاحی به شمارش واقعی می‌رسد.
     *
     * موجودی مستقیم دستکاری نمی‌شود — برای هر مغایرت یک سند ورود یا خروج با
     * دلیل «اصلاح موجودی» ثبت می‌شود تا تاریخچه و FIFO دست‌نخورده بمانند
     * (قاعده ۳ و ۴ پروژه). فقط یک بار قابل انجام است.
     *
     * @return array{adjusted: int, in: float, out: float}
     */
    public function applyToStock(Stocktake $stocktake): array
    {
        if (! $stocktake->canApplyToStock()) {
            throw new RuntimeException('این انبارگردانی یا هنوز بسته نشده یا موجودی قبلاً با آن به‌روز شده است.');
        }

        return DB::transaction(function () use ($stocktake) {
            $stock = app(StockMovementService::class);
            $warehouse = $stocktake->warehouse;

            $adjusted = 0;
            $totalIn = 0.0;
            $totalOut = 0.0;

            foreach ($stocktake->lines()->with('itemVersion')->get() as $line) {
                if (! $line->hasDiscrepancy() || ! $line->itemVersion) {
                    continue;
                }

                $difference = $line->difference();
                $note = __('stocktake.adjustment_note', ['code' => $stocktake->code]);

                if ($difference > 0) {
                    // شمارش بیشتر از دفتر: ورود اصلاحی با قیمت صفر، چون این
                    // کالا خریدی نداشته و قیمت واقعی‌اش معلوم نیست
                    $stock->recordIn(
                        $line->itemVersion, $warehouse, $difference, 0,
                        StockMovement::REASON_ADJUSTMENT,
                        referenceType: Stocktake::class, referenceId: $stocktake->id,
                        notes: $note,
                    );
                    $totalIn += $difference;
                } else {
                    $stock->recordOut(
                        $line->itemVersion, $warehouse, abs($difference),
                        StockMovement::REASON_ADJUSTMENT,
                        referenceType: Stocktake::class, referenceId: $stocktake->id,
                        notes: $note,
                    );
                    $totalOut += abs($difference);
                }

                $adjusted++;
            }

            $stocktake->update([
                'applied_by' => auth()->id(),
                'applied_at' => now(),
            ]);

            ActivityLog::record('stocktake_applied', $stocktake, [
                'adjusted' => $adjusted, 'in' => $totalIn, 'out' => $totalOut,
            ]);

            return ['adjusted' => $adjusted, 'in' => $totalIn, 'out' => $totalOut];
        });
    }

    /** لغو انبارگردانی — فقط تا وقتی هنوز بسته نشده است. */
    public function cancel(Stocktake $stocktake): void
    {
        if (! $stocktake->isEditable()) {
            throw new RuntimeException('انبارگردانی بسته‌شده را نمی‌توان لغو کرد.');
        }

        $stocktake->update([
            'status'    => Stocktake::STATUS_CANCELLED,
            'closed_by' => auth()->id(),
            'closed_at' => now(),
        ]);

        ActivityLog::record('stocktake_cancelled', $stocktake, []);
    }

    /**
     * فهرست شمارش برای مسئول انبارگردانی — عمداً **بدون ستون موجودی**.
     *
     * اگر عدد سامانه را ببیند، ناخودآگاه همان را می‌نویسد و انبارگردانی
     * بی‌معنی می‌شود.
     *
     * @return array<int, array<string, string>>
     */
    public function countingSheet(Stocktake $stocktake): array
    {
        $rows = [];

        $lines = $stocktake->lines()
            ->with(['itemVersion.item.category'])
            ->get()
            ->sortBy(fn (StocktakeLine $line) => [
                $line->itemVersion?->item?->category?->name ?? '',
                $line->itemVersion?->item?->name ?? '',
            ]);

        foreach ($lines as $line) {
            $version = $line->itemVersion;

            if (! $version || ! $version->item) {
                continue;
            }

            $rows[] = [
                'code'     => $version->item->code,
                'name'     => $version->item->name,
                'category' => $version->item->category?->name ?? '—',
                'version'  => $version->version_code,
                'location' => $version->location ?: '—',
                'unit'     => $version->item->unit,
                'notes'    => $version->notes ?: '',
                // ستون خالی برای نوشتن دستی شمارش
                'counted'  => '',
            ];
        }

        return $rows;
    }

    /** کد بعدی: ANB-۱۴۰۵-۰۱ */
    private function nextCode(): string
    {
        $year = (int) \Hekmatinasser\Verta\Verta::instance(Carbon::now())->format('Y');
        $prefix = "ANB-{$year}-";

        $last = Stocktake::withTrashed()
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('code')
            ->value('code');

        $number = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return sprintf('%s%02d', $prefix, $number);
    }
}
