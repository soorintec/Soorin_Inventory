<?php

namespace App\Support;

use App\Models\Business;
use Illuminate\Support\Facades\DB;

/**
 * مدیریتِ کسب‌وکارِ فعال (tenant) در چند-کسب‌وکاری.
 *
 * اتصالِ «tenant» را از روی کسب‌وکارِ فعال هدف‌گیری می‌کند:
 *   - حالتِ database (VPS): نامِ دیتابیسِ اتصالِ tenant عوض می‌شود.
 *   - حالتِ prefix (هاستِ اشتراکی/تک‌دیتابیس): پیشوندِ جدول‌ها عوض می‌شود.
 *
 * کدِ برنامه در هر دو حالت یکی است؛ فقط اتصالِ زیرین جابه‌جا می‌شود (الگوی
 * InstallController: config([...]) + DB::purge()). اگر هدف با پیکربندیِ فعلیِ
 * اتصالِ tenant یکی باشد (مثلِ تنها-کسب‌وکارِ پیش‌فرض)، هیچ purgeای زده نمی‌شود تا
 * رفتار و ایزوله‌سازیِ تست‌ها دست‌نخورده بماند.
 */
class Tenancy
{
    public const MODE_KEY = 'tenancy.mode';

    /**
     * حالتِ نصب: prefix (پیش‌فرض) یا database.
     *
     * اول از تنظیماتِ دیتابیس خوانده می‌شود (کاربر از UI انتخاب می‌کند)، بعد از
     * env، و در نهایت prefix. پس دیگر نیازی به ویرایشِ دستیِ .env نیست.
     */
    public static function mode(): string
    {
        try {
            $stored = \App\Models\Setting::get(self::MODE_KEY);

            if (in_array($stored, ['prefix', 'database'], true)) {
                return $stored;
            }
        } catch (\Throwable) {
            // پیش از آماده‌بودنِ دیتابیس/جدولِ settings — به env برمی‌گردیم.
        }

        $mode = (string) env('TENANCY_MODE', 'prefix');

        return in_array($mode, ['prefix', 'database'], true) ? $mode : 'prefix';
    }

    /** ذخیرهٔ حالتِ نصب از UI (به‌جای ویرایشِ دستیِ .env). */
    public static function setMode(string $mode): void
    {
        $mode = in_array($mode, ['prefix', 'database'], true) ? $mode : 'prefix';

        \App\Models\Setting::set(self::MODE_KEY, $mode, 'tenancy');
    }

    private static ?Business $active = null;

    public static function active(): ?Business
    {
        return self::$active;
    }

    /**
     * کسب‌وکارِ فعال را روی اتصالِ tenant سوار می‌کند.
     */
    public static function use(Business $business): void
    {
        self::$active = $business;

        // مقصد از روی کسب‌وکار؛ در نبودِ مقدار، همان دیتابیس/پیشوندِ پیش‌فرضِ اتصالِ
        // central (که خودش از env می‌آید) استفاده می‌شود = رفتارِ تک‌کسب‌وکاری.
        $centralDatabase = config('database.connections.'.config('database.default').'.database');
        $database = filled($business->database) ? $business->database : $centralDatabase;
        $prefix = (string) ($business->table_prefix ?? '');

        $current = config('database.connections.tenant');

        // اگر اتصالِ tenant همین حالا هم درست هدف‌گیری شده، دست نزن (no-op) — این حالت
        // برای تنها-کسب‌وکارِ پیش‌فرض همیشه برقرار است و purge نمی‌خواهد.
        if (($current['database'] ?? null) === $database && (string) ($current['prefix'] ?? '') === $prefix) {
            return;
        }

        // اعتبارِ اتصال را از central می‌گیریم تا اگر در زمانِ اجرا عوض شده باشد
        // (مثلِ ویزارد نصب) هماهنگ بماند.
        $central = config('database.connections.'.config('database.default'));

        config([
            'database.connections.tenant.host'     => $central['host'] ?? env('DB_HOST', '127.0.0.1'),
            'database.connections.tenant.port'     => $central['port'] ?? env('DB_PORT', '3306'),
            'database.connections.tenant.username' => $central['username'] ?? env('DB_USERNAME', 'root'),
            'database.connections.tenant.password' => $central['password'] ?? env('DB_PASSWORD', ''),
            'database.connections.tenant.database' => $database,
            'database.connections.tenant.prefix'   => $prefix,
        ]);

        DB::purge('tenant');
    }
}
