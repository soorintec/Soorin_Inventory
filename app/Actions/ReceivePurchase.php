<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * دریافت خرید: محاسبه قیمت تمام‌شده هر ردیف (سرشکن هزینه‌های جانبی طبق
 * ارزش یا وزن یا تعداد) و ورود واقعی به انبار — هر ردیف یک لات FIFO
 * می‌سازد. تا این مرحله انجام نشود، سند خرید هیچ اثری روی موجودی ندارد.
 *
 * خروجی مطلوب پروژه: «این کالا با قیمت خرید همان روز، فلان‌قدر تمام شد.»
 */
class ReceivePurchase
{
    public function __construct(private readonly StockMovementService $stock) {}

    public function __invoke(Purchase $purchase): Purchase
    {
        if ($purchase->status === Purchase::STATUS_RECEIVED) {
            throw new RuntimeException('این سند خرید قبلاً دریافت شده است.');
        }

        $items = $purchase->items()->get();

        if ($items->isEmpty()) {
            throw new RuntimeException('سند خرید هیچ ردیفی ندارد.');
        }

        return DB::transaction(function () use ($purchase, $items) {
            // ۱. قیمت ریالی هر واحد (بدون هزینه جانبی) از نرخ ارز روز حواله
            foreach ($items as $item) {
                $unitPriceIrr = (int) round((float) $item->fx_unit_price * $purchase->rate_to_irr);
                $item->forceFill(['unit_price_irr' => $unitPriceIrr])->save();
            }

            $goodsValue = (int) $items->sum(fn ($i) => (float) $i->quantity * $i->unit_price_irr);
            $peripheral = $purchase->peripheralCosts();

            // ۲. سرشکن هزینه‌های جانبی طبق روش انتخاب‌شده
            $itemsArray = $items->all();
            $shares = $this->allocationShares($purchase->allocation_method, $itemsArray);

            $allocatedSum = 0;

            foreach ($itemsArray as $index => $item) {
                $share = $shares[$index];

                // آخرین ردیف باقیمانده تقسیم را می‌گیرد تا مجموع دقیقاً برابر peripheral شود
                $isLast = $index === count($itemsArray) - 1;
                $allocated = $isLast ? ($peripheral - $allocatedSum) : (int) round($peripheral * $share);
                $allocatedSum += $allocated;

                $landedUnitCost = $item->unit_price_irr + (int) round($allocated / max(1, (float) $item->quantity));

                $item->forceFill([
                    'allocated_cost'   => $allocated,
                    'landed_unit_cost' => $landedUnitCost,
                ])->save();
            }

            // ۳. ورود واقعی به انبار — هر ردیف یک لات FIFO با قیمت تمام‌شده خودش
            foreach ($items as $item) {
                $this->stock->recordIn(
                    itemVersion: $item->itemVersion,
                    warehouse: $purchase->warehouse,
                    quantity: (float) $item->quantity,
                    unitCost: $item->landed_unit_cost,
                    reason: StockMovement::REASON_PURCHASE,
                    referenceType: Purchase::class,
                    referenceId: $purchase->id,
                    purchaseItemId: $item->id,
                    lotCode: $purchase->number,
                    receivedAt: $purchase->received_date ?? now(),
                );
            }

            $purchase->forceFill([
                'goods_value_irr' => $goodsValue,
                'total_cost_irr'  => $goodsValue + $peripheral,
                'status'          => Purchase::STATUS_RECEIVED,
                'received_date'   => $purchase->received_date ?? now(),
            ])->save();

            ActivityLog::record('purchase_received', $purchase, [
                'total_cost_irr' => $goodsValue + $peripheral, 'items' => $items->count(),
            ]);

            return $purchase->fresh();
        });
    }

    /**
     * سهم نسبی هر ردیف از هزینه‌های جانبی.
     *
     * اگر مبنای انتخاب‌شده صفر باشد — مثلاً کاربر روش وزنی را انتخاب کرده
     * ولی وزنی وارد نکرده — به‌جای صفر برگرداندن (که کل هزینه را روی ردیف
     * آخر می‌انداخت و قیمت تمام‌شده را بی‌سروصدا خراب می‌کرد) به مبنای
     * بعدی برمی‌گردیم: وزن ← ارزش ← تعداد ← تقسیم مساوی.
     *
     * @param  array<\App\Models\PurchaseItem>  $items
     * @return array<float>
     */
    private function allocationShares(string $method, array $items): array
    {
        $bases = [];

        $bases[Purchase::ALLOCATION_WEIGHT]   = array_map(fn ($i) => (float) $i->weight_kg * (float) $i->quantity, $items);
        $bases[Purchase::ALLOCATION_VALUE]    = array_map(fn ($i) => (float) $i->quantity * $i->unit_price_irr, $items);
        $bases[Purchase::ALLOCATION_QUANTITY] = array_map(fn ($i) => (float) $i->quantity, $items);

        // ترتیب تلاش: روش انتخابی، بعد ارزش، بعد تعداد
        $order = array_values(array_unique([$method, Purchase::ALLOCATION_VALUE, Purchase::ALLOCATION_QUANTITY]));

        foreach ($order as $candidate) {
            $values = $bases[$candidate] ?? [];
            $sum = array_sum($values);

            if ($sum > 0) {
                return array_map(fn ($v) => $v / $sum, $values);
            }
        }

        // هیچ مبنای معناداری نبود — تقسیم مساوی
        $count = max(1, count($items));

        return array_fill(0, $count, 1 / $count);
    }
}
