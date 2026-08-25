<?php

namespace App\Services;

use App\Models\CustomerSystem;
use App\Models\Purchase;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * گزارش‌های فاز ۴. یک منبع واحد برای صفحه، اکسل و PDF.
 *
 * سه گزارش اصلی خواسته‌شده در سند:
 *   ۱. گردش انبار به تفکیک کاربر
 *   ۲. قیمت تمام‌شده هر سامانه اجراشده
 *   ۳. موجودی جاری (کل + تفکیک ورژن)
 * به‌علاوه خلاصه اسناد واردات دوره.
 */
class InventoryReportService
{
    public function generate(CarbonInterface $from, CarbonInterface $to): array
    {
        return [
            'from'          => $from,
            'to'            => $to,
            'by_user'       => $this->movementsByUser($from, $to),
            'system_costs'  => $this->systemCosts(),
            'stock_levels'  => $this->stockLevels(),
            'stock_value'   => $this->stockValueByCurrency(),
            'purchases'     => $this->purchaseSummary($from, $to),
        ];
    }

    /** گردش انبار به تفکیک کاربر ثبت‌کننده — تعداد سند ورود/خروج و مجموع تعداد. */
    private function movementsByUser(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return StockMovement::whereBetween('created_at', [$from, $to])
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->map(function (Collection $group) {
                return [
                    'user'      => $group->first()->user?->name ?? '—',
                    'in_count'  => $group->where('direction', 'in')->count(),
                    'out_count' => $group->where('direction', 'out')->count(),
                    'in_qty'    => (float) $group->where('direction', 'in')->sum('quantity'),
                    'out_qty'   => (float) $group->where('direction', 'out')->sum('quantity'),
                ];
            })
            ->sortByDesc(fn ($r) => $r['in_count'] + $r['out_count'])
            ->values();
    }

    /** قیمت تمام‌شده هر سامانه اجراشده. */
    private function systemCosts(): Collection
    {
        return CustomerSystem::with('customer')
            ->get()
            ->map(fn (CustomerSystem $s) => [
                'code'     => $s->code,
                'name'     => $s->name,
                'customer' => $s->customer?->name ?? '—',
                'cost'     => (int) $s->total_cost,
            ])
            ->sortByDesc('cost')
            ->values();
    }

    /**
     * موجودی جاری هر ورژن کالا (کل در همه انبارها) + ارزش با قیمت خود ورژن.
     *
     * ارزش هر ردیف = قیمت ورژن × موجودی، به ارز خود همان ورژن (ریال/دلار/یوان).
     * هیچ تبدیلی بین ارزها انجام نمی‌شود؛ پیش از این ارزش از قیمت لات به ریال
     * حساب می‌شد و کالای ارزی را هم ریالی جمع می‌زد که نادرست بود.
     */
    private function stockLevels(): Collection
    {
        return StockBalance::with(['itemVersion.item'])
            ->get()
            ->groupBy('item_version_id')
            ->map(function (Collection $group) {
                $version = $group->first()->itemVersion;
                $qty = (float) $group->sum('quantity');
                $price = $version && $version->hasPrice() ? (float) $version->fx_price : null;
                $value = $price !== null ? $price * $qty : null;

                return [
                    'item'           => $version?->item->name ?? '—',
                    'version'        => $version?->version_code ?? '—',
                    'qty'            => $qty,
                    'currency_label' => $version?->currencyLabel() ?? \App\Models\Currency::label(null),
                    'value'          => $value,
                    'value_label'    => $value !== null
                        ? \App\Support\Jalali::quantity($value) . ' ' . $version->currencyLabel()
                        : null,
                ];
            })
            ->filter(fn ($r) => $r['qty'] > 0)
            ->sortByDesc(fn ($r) => $r['value'] ?? 0)
            ->values();
    }

    /**
     * ارزش کل موجودی به تفکیک ارز.
     *
     * خروجی به ترتیب ریال، دلار، یوان: ['IRR' => ['label' => 'ریال', 'value' => …], …].
     * فقط ارزهایی که موجودی ارزش‌دار دارند برمی‌گردند. داشبورد هم از همین
     * استفاده می‌کند تا یک منبع حقیقت باشد.
     *
     * @return array<string, array{label: string, value: float}>
     */
    public function stockValueByCurrency(): array
    {
        $totals = [];

        \App\Models\ItemVersion::query()
            ->whereNotNull('fx_price')
            ->where('fx_price', '>', 0)
            ->withSum('balances as stock', 'quantity')
            ->chunk(500, function ($versions) use (&$totals) {
                foreach ($versions as $version) {
                    $qty = (float) ($version->stock ?? 0);

                    if ($qty <= 0) {
                        continue;
                    }

                    $code = $version->fx_currency ?: 'IRR';
                    $totals[$code] = ($totals[$code] ?? 0) + (float) $version->fx_price * $qty;
                }
            });

        $ordered = [];

        foreach (\App\Models\Currency::options() as $code => $label) {
            if (isset($totals[$code])) {
                $ordered[$code] = ['label' => $label, 'value' => $totals[$code]];
            }
        }

        return $ordered;
    }

    /** خلاصه اسناد خرید دریافت‌شده در بازه. */
    private function purchaseSummary(CarbonInterface $from, CarbonInterface $to): array
    {
        $purchases = Purchase::where('status', Purchase::STATUS_RECEIVED)
            ->whereBetween('received_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        return [
            'count'      => $purchases->count(),
            'total_cost' => (int) $purchases->sum('total_cost_irr'),
            'goods'      => (int) $purchases->sum('goods_value_irr'),
        ];
    }
}
