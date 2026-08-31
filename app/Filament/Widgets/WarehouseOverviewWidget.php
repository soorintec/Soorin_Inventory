<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Models\Item;
use App\Models\ItemVersion;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Services\DatabaseBackupService;
use App\Services\InventoryReportService;
use App\Support\Jalali;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * چهار عدد بالای داشبورد — جواب سؤال‌هایی که هر روز صبح پرسیده می‌شود.
 *
 * «کالای کسری‌دار» عمداً برجسته است: تنها عددی است که ممکن است نیاز به
 * اقدام فوری داشته باشد.
 */
class WarehouseOverviewWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ViewStock->value) ?? false;
    }

    protected function getStats(): array
    {
        $itemCount = Item::count();

        [$outOfStock, $lowStock] = $this->stockAlerts();

        $today = StockMovement::whereDate('created_at', '>=', Carbon::now()->subDay())->count();

        return [
            Stat::make(__('dashboard.items'), Jalali::quantity($itemCount))
                ->description(__('dashboard.items_hint', ['count' => Jalali::quantity(ItemVersion::count())]))
                ->descriptionIcon('heroicon-o-cube')
                ->color('primary'),

            ...$this->stockValueStats(),

            Stat::make(__('dashboard.out_of_stock'), Jalali::quantity($outOfStock))
                ->description(__('dashboard.low_stock_hint', ['count' => Jalali::quantity($lowStock)]))
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($outOfStock > 0 ? 'danger' : 'success')
                // کلیک روی این عدد → فهرستِ همان کالاهای تمام‌شده در «موجودی انبار».
                ->url(\App\Filament\Resources\Items\ItemResource::getUrl('index', ['stock' => 'out'])),

            Stat::make(__('dashboard.movements_today'), Jalali::quantity($today))
                ->description(__('dashboard.movements_today_hint'))
                ->descriptionIcon('heroicon-o-arrows-up-down')
                ->color('info'),

            $this->lastBackupStat(),
            $this->lastStocktakeStat(),
        ];
    }

    /**
     * ارزش موجودی به تفکیک ارز — یک کاشی برای هر ارز که موجودیِ قیمت‌دار دارد.
     *
     * عمداً به تفکیک است و هیچ تبدیلی بین ریال، دلار و یوان انجام نمی‌شود؛
     * جمع کردن دلار و ریال در یک عدد بی‌معنی است.
     *
     * @return array<int, Stat>
     */
    private function stockValueStats(): array
    {
        $values = app(InventoryReportService::class)->stockValueByCurrency();

        if ($values === []) {
            return [
                Stat::make(__('dashboard.stock_value_generic'), '—')
                    ->description(__('dashboard.stock_value_none'))
                    ->descriptionIcon('heroicon-o-banknotes')
                    ->color('gray'),
            ];
        }

        $stats = [];

        foreach ($values as $currency) {
            $stats[] = Stat::make(
                __('dashboard.stock_value', ['currency' => $currency['label']]),
                Jalali::quantity($currency['value']) . ' ' . $currency['label'],
            )
                ->description(__('dashboard.stock_value_hint'))
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success');
        }

        return $stats;
    }

    private function lastBackupStat(): Stat
    {
        $backups = app(DatabaseBackupService::class)->list();
        $last = $backups[0]['created_at'] ?? null;

        return Stat::make(
            __('dashboard.last_backup'),
            $last ? Jalali::format($last) : __('dashboard.never'),
        )
            ->description($last ? Jalali::diffForHumans($last) : __('dashboard.last_backup_hint'))
            ->descriptionIcon('heroicon-o-circle-stack')
            ->color($last ? 'gray' : 'warning');
    }

    private function lastStocktakeStat(): Stat
    {
        $last = Stocktake::max('started_at');

        return Stat::make(
            __('dashboard.last_stocktake'),
            $last ? Jalali::format($last) : __('dashboard.never'),
        )
            ->description($last ? Jalali::diffForHumans($last) : __('dashboard.last_stocktake_hint'))
            ->descriptionIcon('heroicon-o-clipboard-document-check')
            ->color('gray');
    }

    /**
     * شمارش کالاهای صفر و کم‌موجود.
     *
     * حد هشدار روی ورژن است، پس شمارش هم روی ورژن انجام می‌شود؛ یک کالا
     * ممکن است یک ورژنش تمام شده باشد و ورژن دیگرش پر.
     *
     * @return array{0: int, 1: int}
     */
    private function stockAlerts(): array
    {
        $out = 0;
        $low = 0;

        ItemVersion::query()
            ->withSum('balances as stock', 'quantity')
            ->whereHas('item')
            ->chunk(500, function ($versions) use (&$out, &$low) {
                foreach ($versions as $version) {
                    $stock = (float) ($version->stock ?? 0);

                    if ($stock <= 0) {
                        $out++;
                    } elseif ($version->min_stock > 0 && $stock <= $version->min_stock) {
                        $low++;
                    }
                }
            });

        return [$out, $low];
    }
}
