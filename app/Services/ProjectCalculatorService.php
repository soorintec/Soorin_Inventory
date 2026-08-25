<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemVersion;
use App\Models\SystemVersion;

/**
 * ماشین‌حساب پروژه.
 *
 * چند مدل/نسخهٔ سامانه با تعدادشان انتخاب می‌شود؛ این سرویس قطعات همهٔ آن‌ها
 * را (قطعهٔ هر نسخه × تعداد آن نسخه) جمع می‌زند و برای هر قطعه گزارش می‌دهد:
 *   - تعداد کل موردنیاز پروژه
 *   - قیمت واحد و قیمت کل (تعداد × قیمت) — به تفکیک ارز
 *   - وضعیت موجودی: «موجود» یا «n عدد کسری»
 *
 * نکتهٔ مهم: قیمت بر اساس تعداد موردنیازِ پروژه حساب می‌شود، نه موجودی انبار.
 * اگر ۱۰ مانیتور لازم است و انبار ۸ تا دارد، باز هم ۱۰ × قیمت حساب می‌شود.
 * جمعِ قیمت‌ها به تفکیک ارز است (ریال، دلار، یوان با هم جمع نمی‌شوند).
 */
class ProjectCalculatorService
{
    /**
     * @param  array<int, array{system_version_id: mixed, quantity: mixed}>  $selections
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, array{label: string, value: float}>, has_selection: bool}
     */
    public function calculate(array $selections): array
    {
        // جمع تعداد موردنیاز هر کالا در کل پروژه
        $required = [];   // item_id => ['item' => Item, 'qty' => float]

        foreach ($selections as $selection) {
            $versionId = $selection['system_version_id'] ?? null;
            $count = (float) ($selection['quantity'] ?? 0);

            if (! $versionId || $count <= 0) {
                continue;
            }

            $version = SystemVersion::with(['bomLines.item.versions'])->find($versionId);

            if (! $version) {
                continue;
            }

            foreach ($version->bomLines as $line) {
                if (! $line->item) {
                    continue;
                }

                $id = $line->item_id;
                $required[$id]['item'] ??= $line->item;
                $required[$id]['qty'] = ($required[$id]['qty'] ?? 0) + (float) $line->quantity * $count;
            }
        }

        $rows = [];
        $totals = [];

        foreach ($required as $entry) {
            /** @var Item $item */
            $item = $entry['item'];
            $need = (float) $entry['qty'];

            [$price, $currency] = $this->itemPrice($item);
            $lineTotal = $price !== null ? $price * $need : null;

            if ($lineTotal !== null) {
                $totals[$currency] = ($totals[$currency] ?? 0) + $lineTotal;
            }

            $stock = (float) $item->totalStock();
            $shortage = max(0.0, $need - $stock);

            $rows[] = [
                'item'           => $item->name,
                'code'           => $item->code,
                'unit'           => $item->unit,
                'required'       => $need,
                'unit_price'     => $price,
                'currency'       => $currency,
                'currency_label' => $currency ? \App\Models\Currency::label($currency) : null,
                'line_total'     => $lineTotal,
                'stock'          => $stock,
                'shortage'       => $shortage,
            ];
        }

        // مرتب‌سازی: قطعاتِ کسری‌دار بالاتر، بعد بر اساس نام
        usort($rows, fn ($a, $b) => [$b['shortage'] > 0, $a['item']] <=> [$a['shortage'] > 0, $b['item']]);

        return [
            'rows'          => $rows,
            'totals'        => $this->orderTotals($totals),
            'has_selection' => $rows !== [],
        ];
    }

    /**
     * قیمت نمایندهٔ یک کالا: قیمت اولین ورژنی که قیمت دارد.
     *
     * قیمت روی ورژن است نه کالا؛ در عمل بیشتر کالاها یک ورژن دارند، پس همان
     * قیمت است. اگر هیچ ورژنی قیمت نداشته باشد، null برمی‌گردد.
     *
     * @return array{0: ?float, 1: ?string}
     */
    private function itemPrice(Item $item): array
    {
        $priced = $item->versions
            ->sortBy('version_code')
            ->first(fn (ItemVersion $v) => $v->fx_price !== null && (float) $v->fx_price > 0);

        if (! $priced) {
            return [null, null];
        }

        return [(float) $priced->fx_price, $priced->fx_currency ?: 'IRR'];
    }

    /**
     * جمع‌های ارزی به ترتیب ریال، دلار، یوان.
     *
     * @param  array<string, float>  $totals
     * @return array<string, array{label: string, value: float}>
     */
    private function orderTotals(array $totals): array
    {
        $ordered = [];

        foreach (\App\Models\Currency::options() as $code => $label) {
            if (isset($totals[$code])) {
                $ordered[$code] = ['label' => $label, 'value' => $totals[$code]];
            }
        }

        return $ordered;
    }
}
