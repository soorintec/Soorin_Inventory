<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ItemVersion;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * تک نقطه ورودی برای هر تغییر موجودی. هیچ کد دیگری نباید مستقیم روی
 * stock_movements یا stock_balances بنویسد — وگرنه FIFO و موجودی از هم
 * جدا می‌افتند.
 *
 * قاعده ثابت پروژه: هیچ رکورد stock_movements حذف یا ویرایش نمی‌شود؛
 * اصلاح فقط با سند معکوس (recordIn برای جبران recordOut اشتباه یا برعکس).
 */
class StockMovementService
{
    /**
     * ورود به انبار — یک لات جدید با قیمت تمام‌شده خودش می‌سازد.
     * منبع لات: خرید (فاز ۲) یا ثبت موجودی اولیه/اصلاحی (همین‌جا).
     */
    public function recordIn(
        ItemVersion $itemVersion,
        Warehouse $warehouse,
        float $quantity,
        int $unitCost,
        string $reason,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $purchaseItemId = null,
        ?string $lotCode = null,
        ?\DateTimeInterface $receivedAt = null,
        ?string $notes = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('تعداد ورودی باید بزرگ‌تر از صفر باشد.');
        }

        return DB::transaction(function () use (
            $itemVersion, $warehouse, $quantity, $unitCost, $reason,
            $referenceType, $referenceId, $purchaseItemId, $lotCode, $receivedAt, $notes,
        ) {
            $lot = StockLot::create([
                'item_version_id'     => $itemVersion->id,
                'warehouse_id'        => $warehouse->id,
                'purchase_item_id'    => $purchaseItemId,
                'lot_code'            => $lotCode,
                'received_at'         => $receivedAt ?? now(),
                'quantity_in'         => $quantity,
                'quantity_remaining'  => $quantity,
                'unit_cost'           => $unitCost,
            ]);

            $movement = StockMovement::create([
                'item_version_id' => $itemVersion->id,
                'warehouse_id'    => $warehouse->id,
                'stock_lot_id'    => $lot->id,
                'direction'       => StockMovement::DIRECTION_IN,
                'reason'          => $reason,
                'quantity'        => $quantity,
                'unit_cost'       => $unitCost,
                'reference_type'  => $referenceType,
                'reference_id'    => $referenceId,
                'user_id'         => auth()->id(),
                'notes'           => $notes,
            ]);

            $this->adjustBalance($itemVersion, $warehouse, $quantity);

            ActivityLog::record('stock_in', $movement, [
                'item_version_id' => $itemVersion->id, 'quantity' => $quantity, 'reason' => $reason,
            ]);

            return $movement;
        });
    }

    /**
     * خروج از انبار — به روش FIFO از قدیمی‌ترین لات‌های با موجودی باقیمانده
     * مصرف می‌شود. اگر خروجی چند لات را پوشش دهد، به همان تعداد سند
     * stock_movements جدا ساخته می‌شود (هر سند فقط به یک لات وصل است).
     *
     * @return array<StockMovement>
     */
    public function recordOut(
        ItemVersion $itemVersion,
        Warehouse $warehouse,
        float $quantity,
        string $reason,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
    ): array {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('تعداد خروجی باید بزرگ‌تر از صفر باشد.');
        }

        return DB::transaction(function () use (
            $itemVersion, $warehouse, $quantity, $reason, $referenceType, $referenceId, $notes,
        ) {
            $balance = StockBalance::firstOrCreate(
                ['item_version_id' => $itemVersion->id, 'warehouse_id' => $warehouse->id],
            );

            if ($balance->available() < $quantity) {
                throw new RuntimeException(
                    "موجودی کافی نیست: موجود {$balance->available()}، درخواست {$quantity} — {$itemVersion->displayName()} در انبار {$warehouse->name}",
                );
            }

            $lots = StockLot::where('item_version_id', $itemVersion->id)
                ->where('warehouse_id', $warehouse->id)
                ->where('quantity_remaining', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remaining = $quantity;
            $movements = [];

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (float) $lot->quantity_remaining);
                $lot->decrement('quantity_remaining', $take);

                $movements[] = StockMovement::create([
                    'item_version_id' => $itemVersion->id,
                    'warehouse_id'    => $warehouse->id,
                    'stock_lot_id'    => $lot->id,
                    'direction'       => StockMovement::DIRECTION_OUT,
                    'reason'          => $reason,
                    'quantity'        => $take,
                    'unit_cost'       => $lot->unit_cost,
                    'reference_type'  => $referenceType,
                    'reference_id'    => $referenceId,
                    'user_id'         => auth()->id(),
                    'notes'           => $notes,
                ]);

                $remaining -= $take;
            }

            if ($remaining > 0) {
                // نباید برسد — چون available() بالاتر چک شد؛ فقط برای اطمینان از سازگاری داده
                throw new RuntimeException('موجودی لات‌ها با موجودی خلاصه هم‌خوانی ندارد؛ نیاز به بررسی دستی.');
            }

            $this->adjustBalance($itemVersion, $warehouse, -$quantity);

            ActivityLog::record('stock_out', $movements[0], [
                'item_version_id' => $itemVersion->id, 'quantity' => $quantity, 'reason' => $reason,
                'lots_used' => count($movements),
            ]);

            return $movements;
        });
    }

    /**
     * انتقال بین دو انبار — یک خروج از مبدأ + یک یا چند ورود به مقصد با
     * همان قیمت تمام‌شده لات مبدأ (نه لات جدید با قیمت دلخواه).
     *
     * @return array{out: array<StockMovement>, in: array<StockMovement>}
     */
    public function transfer(ItemVersion $itemVersion, Warehouse $from, Warehouse $to, float $quantity, ?string $notes = null): array
    {
        if ($from->is($to)) {
            throw new InvalidArgumentException('انبار مبدأ و مقصد نمی‌تواند یکی باشد.');
        }

        return DB::transaction(function () use ($itemVersion, $from, $to, $quantity, $notes) {
            $outMovements = $this->recordOut($itemVersion, $from, $quantity, StockMovement::REASON_TRANSFER, notes: $notes);

            $inMovements = [];
            foreach ($outMovements as $out) {
                $inMovements[] = $this->recordIn(
                    $itemVersion, $to, (float) $out->quantity, $out->unit_cost,
                    StockMovement::REASON_TRANSFER,
                    referenceType: 'transfer_pair', referenceId: $out->id,
                    lotCode: $out->lot->lot_code, receivedAt: $out->lot->received_at, notes: $notes,
                );
            }

            return ['out' => $outMovements, 'in' => $inMovements];
        });
    }

    /**
     * برداشتنِ حضورِ یک ورژن از یک انبار — ردیفِ ماندهٔ همان (ورژن، انبار) پاک
     * می‌شود، بی‌آنکه انبارهای دیگر یا خودِ کالا دست بخورند.
     *
     * اگر موجودیِ آزادی مانده باشد، اول به‌صورت «اصلاح» خارج می‌شود تا کاردکس با
     * مانده هم‌خوان بماند (قاعده: مانده = جمعِ حرکت‌ها). ردیفِ دارای رزرو دست‌نخورده
     * می‌ماند چون رزرو تعهد به پروژه است و نباید بی‌سروصدا برود.
     *
     * ردیفِ stock_balances یک کَش است (منبعِ حقیقت، حرکت‌هاست)؛ پاک‌شدنش سابقه را
     * از بین نمی‌برد و در صورت نیاز دوباره ساخته می‌شود.
     */
    public function removeFromWarehouse(ItemVersion $itemVersion, Warehouse $warehouse, ?string $notes = null): void
    {
        DB::transaction(function () use ($itemVersion, $warehouse, $notes) {
            $balance = StockBalance::where('item_version_id', $itemVersion->id)
                ->where('warehouse_id', $warehouse->id)
                ->lockForUpdate()
                ->first();

            if ($balance === null) {
                return;
            }

            $available = $balance->available();

            if ($available > 0) {
                $this->recordOut($itemVersion, $warehouse, $available, StockMovement::REASON_ADJUSTMENT, notes: $notes);
                $balance->refresh();
            }

            if ((float) $balance->reserved > 0) {
                return;
            }

            $balance->delete();
        });
    }

    private function adjustBalance(ItemVersion $itemVersion, Warehouse $warehouse, float $delta): void
    {
        $balance = StockBalance::firstOrCreate(
            ['item_version_id' => $itemVersion->id, 'warehouse_id' => $warehouse->id],
        );

        $balance->increment('quantity', $delta);
    }
}
