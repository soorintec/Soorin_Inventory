<?php

namespace App\Models;

use App\Models\Concerns\UsesTenantConnection;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * تنظیماتِ per-business — روی اتصالِ tenant می‌نشیند (جدولِ settingsِ هر کسب‌وکار).
 *
 * مثلِ Setting مرکزی است، ولی هر کسب‌وکار مقدارِ خودش را دارد (مثلِ برندینگ: نام،
 * لوگو، اطلاعات تماس). کشِ هر کلید با شناسهٔ کسب‌وکارِ فعال namespace می‌شود تا
 * مقدارِ یک کسب‌وکار برای کسب‌وکارِ دیگر سرو نشود.
 *
 * تنظیماتِ مرکزی (لایسنس، به‌روزرسانی، SSL، زمان‌بندیِ بکاپ) در App\Models\Setting
 * می‌مانند، نه اینجا.
 */
class TenantSetting extends Model
{
    use UsesTenantConnection;

    protected $table = 'settings';

    protected $fillable = ['key', 'value', 'group', 'type'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::cacheKey($key), function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            return match ($setting->type) {
                'bool'  => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'int'   => (int) $setting->value,
                default => $setting->value,
            };
        });
    }

    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value, 'group' => $group, 'type' => $type],
        );

        Cache::forget(self::cacheKey($key));
    }

    /** کلیدِ کش، namespace‌شده با کسب‌وکارِ فعال. */
    private static function cacheKey(string $key): string
    {
        $businessId = Tenancy::active()?->id ?? 'default';

        return "tsetting.{$businessId}.{$key}";
    }

    protected static function booted(): void
    {
        static::saved(fn (TenantSetting $s) => Cache::forget(self::cacheKey($s->key)));
        static::deleted(fn (TenantSetting $s) => Cache::forget(self::cacheKey($s->key)));
    }
}
