<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * ارز — جدولِ واحدِ پولی سامانه.
 *
 * مدیر از بخش «ارز» می‌تواند هر واحد پولی را اضافه کند (مثلاً روبل روسیه) و از
 * آن پس همان ارز در ورود کالا، قیمت قطعه، داشبورد، گزارش‌ها و ماشین‌حساب پروژه
 * قابل انتخاب و نمایش است. ریال (ارز پایه) همیشه در فهرست هست، حتی اگر در جدول
 * نباشد.
 */
class Currency extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name'];

    /** کد ارز پایه (ریال). */
    public const BASE = 'IRR';

    /**
     * ارزهای همیشه‌موجود. ریال (پایه)، دلار و یوان از ابتدای سامانه بوده‌اند و
     * همیشه در دسترس‌اند حتی اگر در جدول نباشند؛ ارزهای تازه (مثلاً روبل) از جدول
     * روی این‌ها اضافه می‌شوند و اگر مدیر نامِ همین‌ها را در جدول عوض کند، نامِ
     * جدول برنده است.
     */
    public const DEFAULTS = [
        'IRR' => 'ریال',
        'USD' => 'دلار',
        'CNY' => 'یوان',
    ];

    /**
     * فهرست ارزها به‌صورت [کد => نام]: پیش‌فرض‌ها + هرچه مدیر در بخش «ارز» افزوده.
     *
     * برای گزینه‌های فرم (ورود کالا، قیمت قطعه) و برچسب‌ها همه‌جا (داشبورد،
     * گزارش، ماشین‌حساب) استفاده می‌شود. کش می‌شود چون زیاد صدا زده می‌شود و با
     * تغییر ارز پاک می‌شود (هوک booted).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return Cache::rememberForever('currencies.options', function () {
            $db = static::query()->orderBy('name')->pluck('name', 'code')->all();

            // array_merge: نامِ جدول روی پیش‌فرض غالب می‌شود، ترتیب پیش‌فرض‌ها
            // (ریال اول) حفظ می‌شود و ارزهای تازه به انتها اضافه می‌شوند.
            return array_merge(self::DEFAULTS, $db);
        });
    }

    /** نام فارسی یک ارز از روی کدش؛ اگر نبود، خودِ کد. */
    public static function label(?string $code): string
    {
        $code = $code ?: self::BASE;

        return self::options()[$code] ?? $code;
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('currencies.options'));
        static::deleted(fn () => Cache::forget('currencies.options'));
    }
}
