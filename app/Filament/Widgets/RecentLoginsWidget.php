<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * آخرین ورودهای کاربران به سامانه، با تاریخ و ساعت شمسی به وقت تهران.
 *
 * فقط برای کسی که مجوز مشاهده کاربران دارد — «چه کسی کِی وارد شد» اطلاعات
 * مدیریتی است، نه عمومی.
 */
class RecentLoginsWidget extends Widget
{
    protected string $view = 'filament.widgets.recent-logins';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 1,
    ];

    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    private const LIMIT = 6;

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ViewUsers->value) ?? false;
    }

    public function getUsers(): Collection
    {
        return User::query()
            ->whereNotNull('last_login_at')
            ->orderByDesc('last_login_at')
            ->limit(self::LIMIT)
            ->get();
    }

    /** کاربرانی که هرگز وارد نشده‌اند — نشانه حساب بلااستفاده. */
    public function neverLoggedIn(): int
    {
        return User::whereNull('last_login_at')->count();
    }
}
