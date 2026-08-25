<?php

namespace App\Models;

use App\Enums\Permission as PermissionEnum;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * کاربر داخلی شرکت — این سامانه پرتال مشتری ندارد، فقط مدیر و کارشناس انبار.
 */
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    public const TYPE_ADMIN = 'admin';
    public const TYPE_STAFF = 'staff';

    protected $fillable = ['name', 'email', 'mobile', 'password', 'user_type', 'theme', 'locale', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'is_active' => true,
        'theme'     => 'ocean',
        'locale'    => 'fa',
        'user_type' => self::TYPE_STAFF,
    ];

    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'is_active'     => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    /**
     * تم کاربر به زبان فیلامنت. فیلامنت با light/dark/system کار می‌کند و
     * کلاس dark را روی <html> می‌گذارد؛ ما همان انتخاب را با نام خودمان
     * (ocean/night) در دیتابیس نگه می‌داریم تا بین دستگاه‌ها هم بماند.
     */
    public function filamentThemeMode(): string
    {
        return match ($this->theme) {
            'night'  => 'dark',
            'system' => 'system',
            default  => 'light',
        };
    }

    /** ذخیره تم به زبان فیلامنت، با نگاشت به نام‌های خودمان. */
    public function saveFilamentThemeMode(string $mode): void
    {
        $this->update([
            'theme' => match ($mode) {
                'dark'   => 'night',
                'system' => 'system',
                default  => 'ocean',
            },
        ]);
    }

    public function isAdmin(): bool
    {
        return $this->user_type === self::TYPE_ADMIN;
    }

    protected static function booted(): void
    {
        /*
        | نقش Spatie همیشه با user_type هم‌راستا نگه داشته می‌شود.
        |
        | بدون این، کاربری که مدیر از پنل می‌سازد هیچ نقشی نمی‌گیرد و چون
        | تمام بررسی‌های دسترسی مجوزمحورند، بعد از ورود پنل کاملاً خالی
        | می‌بیند. تغییر نوع حساب هم باید نقش را جابه‌جا کند.
        */
        static::saved(function (User $user) {
            if (blank($user->user_type) || ! $user->wasChanged('user_type') && ! $user->wasRecentlyCreated) {
                return;
            }

            if (! Role::where('name', $user->user_type)->where('guard_name', 'web')->exists()) {
                return;
            }

            $user->syncRoles([$user->user_type]);

            /*
            | مجوزها مستقیم روی کاربر می‌نشینند (نقش هیچ مجوزی نمی‌دهد) تا
            | برداشتن تیک واقعاً دسترسی را بگیرد.
            |
            | ‑ کاربر تازه: اگر تیکی زده نشده، پیش‌فرض نوعش را می‌گیرد.
            | ‑ تغییر نوع حساب: مجوزها روی پیش‌فرض نوع تازه بازنشانی می‌شوند،
            |   وگرنه ارتقای کارشناس به مدیر نقش را عوض می‌کرد ولی دسترسی‌ها
            |   دست‌نخورده می‌ماند و کاربر مدیرِ بی‌اختیار می‌شد.
            |
            | فرم پنل بعد از این، انتخاب صریح مدیر را جایگزین می‌کند.
            */
            $defaults = PermissionEnum::defaultsByRole()[$user->user_type] ?? [];

            if ($user->wasChanged('user_type') || $user->permissions()->count() === 0) {
                $user->syncPermissions($defaults);
            }
        });
    }

    /**
     * مجوزهای مستقیم این کاربر — همان تیک‌های فرم.
     *
     * @return array<int, string>
     */
    public function directPermissionNames(): array
    {
        return $this->permissions()->pluck('name')->all();
    }
}
