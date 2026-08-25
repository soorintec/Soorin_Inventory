<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Hekmatinasser\Verta\Verta;
use InvalidArgumentException;

/**
 * نمایش تاریخ و عدد — آگاه از زبان فعال.
 *
 * قاعده پروژه: تاریخ در دیتابیس **میلادی** ذخیره می‌شود و فقط در نمایش تبدیل
 * می‌شود. در زبان فارسی به تقویم شمسی و ارقام فارسی، و در زبان‌های دیگر (مثل
 * انگلیسی) به تقویم میلادی و ارقام لاتین نمایش داده می‌شود. تنظیمِ هر زبان در
 * config/locales.php است، پس افزودن زبان تازه اینجا تغییری لازم ندارد.
 */
class Jalali
{
    public const TIMEZONE = 'Asia/Tehran';

    /** آیا زبان فعال از تقویم شمسی استفاده می‌کند؟ */
    public static function usesJalali(): bool
    {
        return (bool) (self::localeConfig()['jalali'] ?? true);
    }

    /** آیا ارقام محلی (غیرلاتین) نمایش داده شوند؟ (فارسی یا عربی) */
    public static function usesPersianDigits(): bool
    {
        return (self::localeConfig()['digits'] ?? 'fa') !== 'en';
    }

    /**
     * تنظیمات زبانِ فعال از config/locales.php.
     *
     * دفاعی است: در تست‌های واحد که کانتینر لاراول بالا نیست، به‌جای خطا یک آرایهٔ
     * خالی برمی‌گرداند و پیش‌فرض‌ها (فارسی/شمسی) اعمال می‌شوند — همان رفتار قبلی.
     *
     * @return array<string, mixed>
     */
    private static function localeConfig(): array
    {
        try {
            return (array) config('locales.available.' . app()->getLocale(), []);
        } catch (\Throwable) {
            return [];
        }
    }

    /** نمایش تاریخ: شمسی «۱۴۰۵/۰۵/۲۲» یا میلادی «2026-08-13». */
    public static function format(mixed $date, string $format = 'Y/m/d'): ?string
    {
        if (blank($date)) {
            return null;
        }

        $carbon = self::toTehran($date);

        if (! self::usesJalali()) {
            // قالبِ میلادی معادل: در انگلیسی خط تیره خواناتر از اسلش است.
            $gregorian = $format === 'Y/m/d' ? 'Y-m-d' : ($format === 'Y/m/d H:i' ? 'Y-m-d H:i' : $format);

            return self::digits($carbon->translatedFormat($gregorian));
        }

        return self::digits(Verta::instance($carbon)->format($format));
    }

    public static function toTehran(mixed $date): CarbonInterface
    {
        return Carbon::parse($date)->setTimezone(self::TIMEZONE);
    }

    /** نمایش تاریخ و ساعت. */
    public static function formatDateTime(mixed $date): ?string
    {
        return self::format($date, 'Y/m/d H:i');
    }

    /** نمایش با نام ماه: «۲۲ مرداد ۱۴۰۵» یا «13 August 2026». */
    public static function formatLong(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        if (! self::usesJalali()) {
            return self::digits(self::toTehran($date)->locale(app()->getLocale())->translatedFormat('d F Y'));
        }

        return self::format($date, 'd F Y');
    }

    /** سال جاری — شمسی یا میلادی بسته به زبان. */
    public static function currentYear(): int
    {
        return self::usesJalali()
            ? (int) Verta::now()->format('Y')
            : (int) now(self::TIMEZONE)->format('Y');
    }

    /** فاصله زمانی خوانا: «۳ روز پیش» / «3 days ago». */
    public static function diffForHumans(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        if (! self::usesJalali()) {
            return self::digits(self::toTehran($date)->locale(app()->getLocale())->diffForHumans());
        }

        return self::digits(Verta::instance(self::toTehran($date))->formatDifference());
    }

    /**
     * تبدیل تاریخ واردشده توسط کاربر به میلادی.
     *
     * در زبان فارسی ورودی شمسی است و تبدیل می‌شود؛ در زبان‌های میلادی، ورودی
     * همان میلادی است و مستقیم خوانده می‌شود.
     */
    public static function toGregorian(?string $inputDate): ?CarbonInterface
    {
        if (blank($inputDate)) {
            return null;
        }

        $normalized = str_replace(['-', '.'], '/', self::englishDigits($inputDate));

        if (! self::usesJalali()) {
            try {
                return Carbon::parse(trim($normalized));
            } catch (\Throwable) {
                return null;
            }
        }

        $parts = array_map('intval', explode('/', trim($normalized)));

        if (count($parts) !== 3) {
            return null;
        }

        [$year, $month, $day] = $parts;

        try {
            $datetime = Verta::createJalali($year, $month, $day, 0, 0, 0)->datetime();
        } catch (InvalidArgumentException) {
            return null;
        }

        return Carbon::instance($datetime);
    }

    /** ارقام لاتین → محلی (فارسی ۰-۹ یا عربی ٠-٩)، بسته به زبان فعال. */
    public static function digits(string $value): string
    {
        return match (self::localeConfig()['digits'] ?? 'fa') {
            'fa'    => strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
                                     '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']),
            'ar'    => strtr($value, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
                                     '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']),
            default => $value,
        };
    }

    /** ارقام فارسی/عربی → لاتین — پیش از ذخیره در دیتابیس (مستقل از زبان). */
    public static function englishDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /** مبلغ با جداکنندهٔ هزارگان — فارسی «۵٬۰۰۰٬۰۰۰» یا لاتین «5,000,000». */
    public static function money(?int $amount): string
    {
        if (self::usesPersianDigits()) {
            return self::digits(number_format((int) $amount, 0, '.', '٬'));
        }

        return number_format((int) $amount, 0, '.', ',');
    }

    /** تعداد کالا برای نمایش: «۳۷۳» نه «۳۷۳٫۰۰». اعشار واقعی حفظ می‌شود. */
    public static function quantity(mixed $value): string
    {
        if (blank($value)) {
            return self::digits('0');
        }

        $number = (float) $value;
        $decimals = fmod($number, 1.0) === 0.0 ? 0 : 2;

        [$decSep, $thousandsSep] = self::usesPersianDigits() ? ['٫', '٬'] : ['.', ','];

        $formatted = number_format($number, $decimals, $decSep, $thousandsSep);

        if ($decimals > 0) {
            $formatted = rtrim($formatted, '0');
            $formatted = rtrim($formatted, $decSep);
        }

        return self::digits($formatted);
    }
}
