<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * افزودن ریال (ارز پایه) به جدول ارز برای نصب‌های موجود.
 *
 * قیمت‌گذاری قطعه‌ها حالا واحد پول را از جدول ارز می‌خواند (نه فهرست ثابت)؛ پس
 * ریال هم باید در همان جدول باشد تا در ورود کالا و همه‌جا قابل انتخاب بماند.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('currencies')->updateOrInsert(
            ['code' => 'IRR'],
            ['name' => 'ریال', 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('currencies')->where('code', 'IRR')->delete();
    }
};
