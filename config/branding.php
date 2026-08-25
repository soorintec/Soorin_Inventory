<?php

/*
|--------------------------------------------------------------------------
| هویت برند — سورین
|--------------------------------------------------------------------------
| این مقادیر در فوتر، سربرگ فاکتور PDF، عنوان مرورگر و ایمیل‌ها استفاده
| می‌شوند. مقادیر قابل تغییر توسط مدیر، از جدول settings خوانده می‌شوند و
| این فایل فقط مقدار پیش‌فرض را تعیین می‌کند. (خریدار این‌ها را از صفحهٔ
| «شخصی‌سازی» به نام کسب‌وکار خودش تغییر می‌دهد.)
*/

return [
    'company' => [
        'name'         => env('COMPANY_NAME', 'سورین'),
        'name_en'      => env('COMPANY_NAME_EN', 'Soorin'),
        'website'      => env('COMPANY_WEBSITE', 'https://yoursite.com'),
        'website_label'=> env('COMPANY_WEBSITE_LABEL', 'yoursite.com'),
        'founded_year' => (int) env('COMPANY_FOUNDED_YEAR', 1400),
        'phone'        => env('COMPANY_PHONE'),
        'address'      => env('COMPANY_ADDRESS'),
    ],

    'app' => [
        // نام کامل کنار لوگو در نوار بالا و در فوتر نمایش داده می‌شود
        'title'   => 'سامانه انبارداری سورین',
        // نسخه سامانه از فایل VERSION در ریشهٔ پروژه خوانده می‌شود (App\Support\AppVersion)
        // و فوتر و صفحهٔ «به‌روزرسانی» هر دو از همان می‌خوانند. این مقدار فقط سازگاری
        // عقب‌رو است و در نمایش استفاده نمی‌شود.
        'version' => '1.0.0',
    ],

    'logo' => [
        'light'  => 'images/logo-light.png',  // روی پس‌زمینه روشن و فاکتور
        'dark'   => 'images/logo-dark.png',   // روی منوی سرمه‌ای و تم شب
        'mark'   => 'images/logo-mark.png',   // نشان مربعی — favicon و صفحهٔ خطا
    ],

    /*
    | پالت رنگ — تم اصلی «آبی نفتی و فیروزه‌ای» و تم «شب»
    | سرمه‌ای منو با سرمه‌ای لوگو یکسان است تا لوگو بدون حاشیه بنشیند.
    */
    'themes' => [
        'ocean' => [
            'label'      => 'آبی نفتی',
            'nav'        => '#0f2d4d',
            'nav_text'   => '#93b4c9',
            'nav_active' => '#14b8a6',
            'background' => '#eef4f6',
            'card'       => '#ffffff',
            'border'     => '#dde8ec',
            'text'       => '#0b2b3f',
            'muted'      => '#5f7d8c',
            'accent'     => '#14b8a6',
            'accent_soft'=> '#ccfbf1',
            'accent_text'=> '#0f766e',
        ],
        'night' => [
            'label'      => 'شب',
            'nav'        => '#0b1220',
            'nav_text'   => '#7b8ca3',
            'nav_active' => '#34d399',
            'background' => '#111a2b',
            'card'       => '#182338',
            'border'     => '#25314a',
            'text'       => '#e6edf7',
            'muted'      => '#8ea0b8',
            'accent'     => '#34d399',
            'accent_soft'=> '#12372c',
            'accent_text'=> '#6ee7b7',
        ],
    ],

    'default_theme' => 'ocean',

    /*
    | آدرس مخزن گیت‌هاب — برای «اتصال به گیت‌هاب» در نصب‌های زیپ، تا بعد از آن
    | «به‌روزرسانی از گیت‌هاب» هم کار کند. اگر مخزن private باشد، توکن را در URL
    | بگذار: https://<TOKEN>@github.com/…
    */
    'github' => [
        'repo' => env('APP_GITHUB_REPO', 'https://github.com/soorintec/Soorin_Inventory.git'),
    ],
];
