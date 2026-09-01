<?php

/*
| برچسب دسترسی‌ها — کلیدها همان مقدار enum App\Enums\Permission هستند.
*/

return [
    'labels' => [
        'backups.settings' => 'تنظیماتِ بکاپِ خودکار و شبکه',
        'items.view'          => 'مشاهده کالا',
        'items.manage'        => 'تعریف و ویرایش کالا و دسته‌بندی',
        'stock.view'          => 'مشاهده موجودی انبار',
        'stock.manage'        => 'ثبت ورود و خروج کالا',
        'warehouses.manage'   => 'تعریف و مدیریت انبارها',
        'stocktakes.manage'   => 'انبارگردانی',
        'purchases.view'      => 'مشاهده خریدها',
        'purchases.manage'    => 'ثبت و مدیریت خرید',
        'projects.view'       => 'مشاهده پروژه‌ها و سامانه‌ها',
        'projects.manage'     => 'مدیریت پروژه‌ها',
        'system_models.manage'=> 'تعریف مدل سامانه و قطعاتش',
        'customers.view'      => 'مشاهده مشتریان',
        'customers.manage'    => 'تعریف و ویرایش مشتریان',
        'users.view'          => 'مشاهده کاربران',
        'users.manage'        => 'ساخت و ویرایش کاربران',
        'settings.manage'     => 'تنظیمات سامانه',
        'activity.view'       => 'مشاهده سیاهه تغییرات',
        'reports.view'        => 'مشاهده و پرینت گزارش‌ها',
        'backups.view'        => 'دیدن و دانلود فایل‌های پشتیبان',
        'backups.create'      => 'تهیه پشتیبان',
        'backups.delete'      => 'حذف فایل پشتیبان',
        'backups.restore'     => 'بازیابی دیتابیس از پشتیبان',
    ],

    'hints' => [
        'backups.settings' => 'تعریفِ پوشهٔ شبکه و زمان‌بندیِ خودکارِ پشتیبان‌گیری.',
        'backups.restore' => 'خطرناک: داده فعلی را با داده فایل جایگزین می‌کند.',
        'stock.manage'    => 'بدون این تیک، کاربر فقط موجودی را می‌بیند و نمی‌تواند تغییرش دهد.',
        'stock.view'      => 'پایه‌ای‌ترین دسترسی انبار؛ بدون آن کاربر هیچ صفحه انباری نمی‌بیند.',
    ],

    'groups' => [
        'warehouse'  => 'انبار و کالا',
        'purchasing' => 'خرید و واردات',
        'projects'   => 'سامانه و پروژه',
        'customers'  => 'مشتریان',
        'reports'    => 'گزارش',
        'backups'    => 'پشتیبان‌گیری',
        'system'     => 'مدیریت سامانه',
    ],
];
