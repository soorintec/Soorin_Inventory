<?php

return [
    'label'      => 'تغییرات',
    'recent'     => 'آخرین تغییرات انبار',
    'empty'      => 'هنوز تغییری ثبت نشده است.',
    'user'       => 'کاربر',
    'action'     => 'نوع تغییر',
    'subject'    => 'مورد',
    'when'       => 'زمان',
    'system'     => 'سامانه',
    'view_all'   => 'همه تغییرات',

    // نوع تغییرها — کلیدها همان مقداری‌اند که ActivityLog::record ثبت می‌کند
    'actions' => [
        'stock_in'          => 'ورود کالا',
        'stock_out'         => 'خروج کالا',
        'item_created'      => 'ساخت کالا',
        'item_updated'      => 'ویرایش کالا',
        'item_deleted'      => 'حذف کالا',
        'version_created'   => 'ساخت ورژن',
        'version_updated'   => 'ویرایش ورژن',
        'version_deleted'   => 'حذف ورژن',
        'category_created'  => 'ساخت دسته‌بندی',
        'category_updated'  => 'ویرایش دسته‌بندی',
        'category_deleted'  => 'حذف دسته‌بندی',
        'warehouse_created' => 'ساخت انبار',
        'warehouse_updated' => 'ویرایش انبار',
        'purchase_received' => 'دریافت خرید',
        'stocktake_started' => 'شروع انبارگردانی',
        'stocktake_closed'  => 'ثبت نهایی انبارگردانی',
        'backup_created'    => 'تهیه پشتیبان',
        'backup_restored'   => 'بازیابی پشتیبان',
        'backup_deleted'    => 'حذف فایل پشتیبان',
    ],

    'subjects' => [
        'Item'          => 'کالا (حذف‌شده)',
        'ItemVersion'   => 'ورژن (حذف‌شده)',
        'ItemCategory'  => 'دسته‌بندی (حذف‌شده)',
        'StockMovement' => 'تراکنش انبار',
        'Warehouse'     => 'انبار',
        'Stocktake'     => 'انبارگردانی',
    ],
];
