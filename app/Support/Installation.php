<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * وضعیت نصب سامانه.
 *
 * مثل وردپرس: تا وقتی نصب نشده، هر آدرسی به ویزارد نصب می‌رود؛ بعد از نصب
 * ویزارد قفل می‌شود و رفرش بعدی خود برنامه را نشان می‌دهد.
 *
 * ملاک «نصب‌شده»:
 *   ۱. فایل قفل نصب موجود باشد (بعد از ویزارد ساخته می‌شود)، یا
 *   ۲. نصب دستی: جدول کاربران باشد و دست‌کم یک مدیر داشته باشد.
 */
class Installation
{
    public static function lockPath(): string
    {
        return storage_path('installed');
    }

    public static function isInstalled(): bool
    {
        if (is_file(self::lockPath())) {
            return true;
        }

        try {
            // نصب دستی (بدون ویزارد): جدول کاربران باشد و یک مدیر داشته باشد.
            return Schema::hasTable('users')
                && DB::table('users')->where('user_type', 'admin')->exists();
        } catch (\Throwable) {
            // دیتابیس هنوز تنظیم/در دسترس نیست → یعنی هنوز نصب نشده
            return false;
        }
    }

    public static function markInstalled(): void
    {
        @file_put_contents(self::lockPath(), 'installed at ' . date('c') . "\n");
    }
}
