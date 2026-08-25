<?php

/*
|--------------------------------------------------------------------------
| زبان‌های سامانه
|--------------------------------------------------------------------------
| فهرست زبان‌های در دسترس. برای افزودن زبان تازه، اینجا یک ردیف اضافه کن و
| پوشهٔ lang/<code> را با ترجمه‌ها بساز. راهنمای کامل: docs/TRANSLATIONS.md
|
| هر ردیف:
|   name  → نامِ زبان به خطِ خودش (در سوییچ زبان نشان داده می‌شود)
|   dir   → جهت نوشتار: rtl یا ltr
|   jalali→ آیا تاریخ به تقویم شمسی نمایش داده شود؟ (فارسی: بله، انگلیسی: میلادی)
|   digits→ آیا ارقام به‌صورت فارسی نمایش داده شوند؟
*/

return [
    'available' => [
        'fa' => ['name' => 'فارسی',    'dir' => 'rtl', 'jalali' => true,  'digits' => 'fa'],
        'en' => ['name' => 'English',  'dir' => 'ltr', 'jalali' => false, 'digits' => 'en'],
        'ar' => ['name' => 'العربية',  'dir' => 'rtl', 'jalali' => false, 'digits' => 'ar'],
        'ru' => ['name' => 'Русский',  'dir' => 'ltr', 'jalali' => false, 'digits' => 'en'],
        'zh' => ['name' => '中文',      'dir' => 'ltr', 'jalali' => false, 'digits' => 'en'],
        'de' => ['name' => 'Deutsch',  'dir' => 'ltr', 'jalali' => false, 'digits' => 'en'],
        'fr' => ['name' => 'Français', 'dir' => 'ltr', 'jalali' => false, 'digits' => 'en'],
        'it' => ['name' => 'Italiano', 'dir' => 'ltr', 'jalali' => false, 'digits' => 'en'],
    ],

    'default' => 'fa',
];
