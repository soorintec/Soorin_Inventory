<?php

namespace App\Support;

/**
 * نسخهٔ فعلی برنامه — از فایل VERSION در ریشهٔ پروژه خوانده می‌شود.
 *
 * با هر تغییر برنامه این فایل یک پله بالا می‌رود (۱٫۰٫۰ ← ۱٫۰٫۱ …)؛ نصب‌های
 * زنده با مقایسهٔ همین عدد می‌فهمند نسخهٔ جدید آمده یا نه.
 */
class AppVersion
{
    public static function current(): string
    {
        $file = base_path('VERSION');

        if (is_file($file)) {
            $value = trim((string) file_get_contents($file));

            if ($value !== '') {
                return $value;
            }
        }

        return '0.0.0';
    }

    /** آیا این استقرار یک مخزن گیت است (پس به‌روزرسانی از گیت‌هاب ممکن است)؟ */
    public static function isGitRepo(): bool
    {
        return is_dir(base_path('.git'));
    }
}
