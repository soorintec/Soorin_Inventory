<?php

namespace Tests\Unit;

use App\Support\Jalali;
use PHPUnit\Framework\TestCase;

/**
 * نمایش تعداد کالا. ستون تعداد در دیتابیس decimal است چون بعضی کالاها متر یا
 * کیلوگرم‌اند، ولی «۳۷۳٫۰۰» برای کالای شمردنی فقط شلوغی است.
 */
class JalaliQuantityTest extends TestCase
{
    public function test_a_whole_number_loses_its_decimals(): void
    {
        $this->assertSame('۳۷۳', Jalali::quantity(373));
        $this->assertSame('۳۷۳', Jalali::quantity('373.00'));
        $this->assertSame('۰', Jalali::quantity(0));
    }

    public function test_a_real_decimal_is_kept(): void
    {
        $this->assertSame('۱٫۵', Jalali::quantity(1.5));
        $this->assertSame('۶۳٫۲۵', Jalali::quantity('63.25'));
    }

    /** صفرهای انتهایی بی‌معنی‌اند: ۱٫۵۰ همان ۱٫۵ است. */
    public function test_trailing_zeros_are_trimmed(): void
    {
        $this->assertSame('۱٫۵', Jalali::quantity('1.50'));
    }

    public function test_thousands_get_a_separator(): void
    {
        $this->assertSame('۲٬۵۰۶', Jalali::quantity(2506));
        $this->assertSame('۱۲٬۳۴۵٫۵', Jalali::quantity(12345.5));
    }

    public function test_null_reads_as_zero_not_empty(): void
    {
        $this->assertSame('۰', Jalali::quantity(null));
        $this->assertSame('۰', Jalali::quantity(''));
    }

    /** اعداد منفی در سند اصلاحی پیش می‌آیند و نباید بد نمایش داده شوند. */
    public function test_negative_numbers_survive(): void
    {
        $this->assertSame('-۵', Jalali::quantity(-5));
    }
}
