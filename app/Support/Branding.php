<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * منبع واحدِ هویت برند.
 *
 * هر مقدار اول از جدول settings (گروه branding) خوانده می‌شود و اگر مدیر آن را
 * تنظیم نکرده باشد، به پیش‌فرضِ config('branding') برمی‌گردد. همهٔ جاهایی که نام
 * شرکت، عنوان سامانه یا لوگو نمایش داده می‌شود — فوتر، نوار بالا، فاکتور PDF،
 * صفحهٔ خطا و فاوآیکون — باید از همین کلاس بخوانند، نه مستقیم از config، تا تغییرِ
 * مدیر همه‌جا اعمال شود.
 */
class Branding
{
    /** گروه ردیف‌های settings و پیشوند کلیدها. */
    public const GROUP = 'branding';

    /** دیسکِ نگه‌داری لوگوهای آپلودی (public/branding). */
    public const DISK = 'branding';

    /** واریانت‌های لوگو که مدیر می‌تواند جایگزین کند. */
    public const LOGO_VARIANTS = ['light', 'dark', 'mark', 'favicon'];

    public static function companyName(): string
    {
        return self::text('company_name', 'branding.company.name');
    }

    public static function companyNameEn(): string
    {
        return self::text('company_name_en', 'branding.company.name_en');
    }

    public static function appTitle(): string
    {
        return self::text('app_title', 'branding.app.title');
    }

    public static function website(): string
    {
        return self::text('website', 'branding.company.website');
    }

    public static function websiteLabel(): string
    {
        return self::text('website_label', 'branding.company.website_label');
    }

    public static function foundedYear(): int
    {
        $value = Setting::get(self::key('founded_year'));

        return filled($value) ? (int) $value : (int) config('branding.company.founded_year');
    }

    public static function phone(): ?string
    {
        $value = Setting::get(self::key('phone'));

        return filled($value) ? (string) $value : config('branding.company.phone');
    }

    public static function address(): ?string
    {
        $value = Setting::get(self::key('address'));

        return filled($value) ? (string) $value : config('branding.company.address');
    }

    /**
     * آدرس URL یک واریانت لوگو (light | dark | mark | favicon).
     *
     * اگر مدیر فایلی آپلود کرده و هنوز روی دیسک هست، همان؛ وگرنه فایل پیش‌فرضِ
     * داخل public/images.
     */
    public static function logo(string $variant): string
    {
        $path = Setting::get(self::key('logo_' . $variant));

        if (filled($path) && Storage::disk(self::DISK)->exists($path)) {
            // نسخه‌دهی با زمانِ تغییر فایل تا مرورگر نسخهٔ قدیمی را کش نکند.
            $version = Storage::disk(self::DISK)->lastModified($path);

            return asset(ltrim(Storage::disk(self::DISK)->url($path), '/')) . '?v=' . $version;
        }

        return asset(self::defaultLogo($variant));
    }

    /**
     * مسیرِ فایلِ واقعیِ لوگو روی دیسک (سفارشیِ مدیر یا پیش‌فرضِ public/images).
     * برای مصرف‌کننده‌هایی که به فایل نیاز دارند نه URL — مثل PDF (mPDF).
     */
    public static function logoPath(string $variant): ?string
    {
        $path = Setting::get(self::key('logo_' . $variant));

        if (filled($path) && Storage::disk(self::DISK)->exists($path)) {
            return Storage::disk(self::DISK)->path($path);
        }

        $default = public_path(self::defaultLogo($variant));

        return is_file($default) ? $default : null;
    }

    /**
     * لوگو به‌صورت data: URI (base64) — برای چاپ/PDF که با mPDF ساخته می‌شود و
     * به اینترنت یا وب‌سرور دسترسی ندارد، پس URL کار نمی‌کند و باید خودِ بایت‌ها
     * جاسازی شوند. null یعنی فایلی نیست.
     */
    public static function logoData(string $variant): ?string
    {
        $file = self::logoPath($variant);

        if ($file === null) {
            return null;
        }

        $mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'svg'         => 'image/svg+xml',
            'png'         => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp'        => 'image/webp',
            'ico'         => 'image/x-icon',
            default       => 'application/octet-stream',
        };

        $bytes = @file_get_contents($file);

        return $bytes === false ? null : 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /** آیا مدیر برای این واریانت لوگوی سفارشی گذاشته؟ */
    public static function hasCustomLogo(string $variant): bool
    {
        $path = Setting::get(self::key('logo_' . $variant));

        return filled($path) && Storage::disk(self::DISK)->exists($path);
    }

    /**
     * شکل آرایه‌ای هم‌ریخت با config('branding.company') تا نماها و سرویس‌های
     * PDF که تا حالا آن آرایه را می‌گرفتند، بدون تغییرِ ساختار کار کنند.
     *
     * @return array<string, mixed>
     */
    public static function company(): array
    {
        return [
            'name'          => self::companyName(),
            'name_en'       => self::companyNameEn(),
            'website'       => self::website(),
            'website_label' => self::websiteLabel(),
            'founded_year'  => self::foundedYear(),
            'phone'         => self::phone(),
            'address'       => self::address(),
        ];
    }

    /**
     * مقادیر متنیِ فعلی برای پر کردن فرمِ صفحهٔ شخصی‌سازی.
     *
     * @return array<string, mixed>
     */
    public static function formState(): array
    {
        return [
            'company_name'    => self::companyName(),
            'company_name_en' => self::companyNameEn(),
            'app_title'       => self::appTitle(),
            'website'         => self::website(),
            'website_label'   => self::websiteLabel(),
            'founded_year'    => self::foundedYear(),
            'phone'           => self::phone(),
            'address'         => self::address(),
        ];
    }

    private static function text(string $key, string $configKey): string
    {
        $value = Setting::get(self::key($key));

        return filled($value) ? (string) $value : (string) config($configKey);
    }

    private static function key(string $suffix): string
    {
        return self::GROUP . '.' . $suffix;
    }

    private static function defaultLogo(string $variant): string
    {
        return match ($variant) {
            'favicon' => 'images/favicon-32.png',
            'mark'    => config('branding.logo.mark', 'images/logo-mark.png'),
            'dark'    => config('branding.logo.dark', 'images/logo-dark.png'),
            default   => config('branding.logo.light', 'images/logo-light.png'),
        };
    }
}
