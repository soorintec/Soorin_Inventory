<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Models\ItemVersion;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * کالاهایی که تمام شده‌اند یا نزدیک اتمام‌اند.
 *
 * تنها باکس داشبورد که ممکن است نیاز به اقدام فوری داشته باشد، پس بالاتر از
 * سیاهه تغییرات می‌نشیند و وقتی چیزی برای هشدار نباشد خودش را پنهان نمی‌کند
 * (خالی بودنش هم خبر خوبی است و باید دیده شود).
 */
class LowStockWidget extends Widget
{
    protected string $view = 'filament.widgets.low-stock';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 1,
    ];

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    private const LIMIT = 8;

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ViewStock->value) ?? false;
    }

    /**
     * ورژن‌های صفر یا زیر حد هشدار، صفرها اول.
     *
     * حد هشدار روی ورژن است، پس فیلتر هم روی ورژن انجام می‌شود؛ یک کالا
     * ممکن است یک ورژنش تمام شده باشد و ورژن دیگرش پر.
     */
    public function getVersions(): Collection
    {
        return ItemVersion::query()
            ->with('item')
            ->whereHas('item')
            ->where('is_active', true)
            ->withSum('balances as stock', 'quantity')
            ->get()
            ->map(function (ItemVersion $version) {
                $version->stock = (float) ($version->stock ?? 0);

                return $version;
            })
            ->filter(fn (ItemVersion $v) => $v->stock <= 0
                || ($v->min_stock > 0 && $v->stock <= $v->min_stock))
            // صفرها اول، بعد کم‌موجودها
            ->sortBy(fn (ItemVersion $v) => [$v->stock > 0 ? 1 : 0, $v->stock])
            ->take(self::LIMIT)
            ->values();
    }
}
