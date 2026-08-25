<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectChecklistLine;
use App\Models\StockBalance;
use Illuminate\Support\Facades\DB;

/**
 * تولید و به‌روزرسانی چک‌لیست پروژه از روی BOM نسخه سامانه، و تطبیق با
 * موجودی آزاد انبار: چقدر موجود است (و **واقعاً رزرو می‌شود**) و چقدر
 * کسری دارد (باید خریداری شود).
 *
 * رزرو روی ستون stock_balances.reserved نوشته می‌شود تا پروژه دوم همان
 * موجودی را دوباره حساب نکند — خواسته صریح «رزرو موجودی» در سند پروژه.
 */
class ProjectChecklistService
{
    /**
     * ساخت چک‌لیست از BOM. رزرو قبلی همین پروژه ابتدا آزاد و سپس رزرو
     * تازه اعمال می‌شود، تا اجرای دوباره موجودی را دوبار قفل نکند.
     */
    public function generateFromBom(Project $project): void
    {
        $version = $project->systemVersion;

        if (! $version) {
            return;
        }

        DB::transaction(function () use ($project, $version) {
            $this->releaseReservations($project);
            $project->checklistLines()->delete();

            foreach ($version->bomLines()->with('item')->get() as $bom) {
                $line = $project->checklistLines()->create([
                    'item_id'           => $bom->item_id,
                    'item_version_id'   => $bom->item_version_id,
                    'quantity_required' => $bom->quantity,
                ]);

                $this->reserveForLine($line);
            }
        });
    }

    /**
     * آزادسازی رزروهای این پروژه از موجودی انبار.
     * هنگام تولید دوباره چک‌لیست، لغو یا حذف پروژه فراخوانی می‌شود.
     */
    public function releaseReservations(Project $project): void
    {
        DB::transaction(function () use ($project) {
            foreach ($project->checklistLines()->get() as $line) {
                $remaining = (float) $line->quantity_reserved;

                if ($remaining <= 0) {
                    continue;
                }

                foreach ($this->balancesFor($line) as $balance) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $release = min($remaining, (float) $balance->reserved);

                    if ($release > 0) {
                        $balance->decrement('reserved', $release);
                        $remaining -= $release;
                    }
                }

                $line->forceFill(['quantity_reserved' => 0])->save();
            }
        });
    }

    /**
     * تطبیق یک ردیف چک‌لیست با موجودی آزاد و قفل کردن آن.
     * از انبارهایی که موجودی آزاد دارند به ترتیب برداشته می‌شود.
     */
    public function reserveForLine(ProjectChecklistLine $line): void
    {
        $required = (float) $line->quantity_required;
        $reserved = 0.0;

        foreach ($this->balancesFor($line) as $balance) {
            if ($reserved >= $required) {
                break;
            }

            $take = min($required - $reserved, $balance->available());

            if ($take > 0) {
                $balance->increment('reserved', $take);
                $reserved += $take;
            }
        }

        $shortage = max(0, $required - $reserved);

        $line->forceFill([
            'quantity_reserved' => $reserved,
            'quantity_shortage' => $shortage,
            'status'            => $shortage > 0
                ? ProjectChecklistLine::STATUS_PURCHASE_NEEDED
                : ProjectChecklistLine::STATUS_RESERVED,
        ])->save();
    }

    /**
     * ردیف‌های موجودی مرتبط با یک خط چک‌لیست.
     * اگر ورژن مشخص باشد فقط همان؛ وگرنه همه ورژن‌های آن کالا.
     *
     * @return \Illuminate\Support\Collection<int, StockBalance>
     */
    private function balancesFor(ProjectChecklistLine $line): \Illuminate\Support\Collection
    {
        $query = StockBalance::query()->lockForUpdate()->orderBy('warehouse_id');

        return $line->item_version_id
            ? $query->where('item_version_id', $line->item_version_id)->get()
            : $query->whereIn('item_version_id', $line->item->versions()->pluck('id'))->get();
    }
}
