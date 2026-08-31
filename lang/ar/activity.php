<?php

return [
    'label'      => 'السجل',
    'recent'     => 'آخر أنشطة المستودع',
    'empty'      => 'لم يُسجّل أي نشاط بعد.',
    'user'       => 'المستخدم',
    'action'     => 'الإجراء',
    'subject'    => 'الموضوع',
    'when'       => 'الوقت',
    'system'     => 'النظام',
    'view_all'   => 'كل الأنشطة',

    // أنواع الإجراءات — المفاتيح مطابقة لما يسجّله ActivityLog::record
    'actions' => [
        'stock_in'          => 'إدخال مخزون',
        'stock_out'         => 'إخراج مخزون',
        'stocktake_cancelled' => 'إلغاء الجرد',
        'stocktake_applied' => 'تطبيق الجرد',
        'item_created'      => 'إنشاء صنف',
        'item_updated'      => 'تعديل صنف',
        'item_deleted'      => 'حذف صنف',
        'version_created'   => 'إنشاء نسخة',
        'version_updated'   => 'تعديل نسخة',
        'version_deleted'   => 'حذف نسخة',
        'category_created'  => 'إنشاء فئة',
        'category_updated'  => 'تعديل فئة',
        'category_deleted'  => 'حذف فئة',
        'warehouse_created' => 'إنشاء مستودع',
        'warehouse_updated' => 'تعديل مستودع',
        'purchase_received' => 'استلام مشتريات',
        'stocktake_started' => 'بدء جرد',
        'stocktake_closed'  => 'إنهاء جرد',
        'backup_created'    => 'إنشاء نسخة احتياطية',
        'backup_restored'   => 'استعادة نسخة احتياطية',
        'backup_deleted'    => 'حذف نسخة احتياطية',
    ],

    'subjects' => [
        'Item'          => 'صنف (محذوف)',
        'ItemVersion'   => 'نسخة (محذوفة)',
        'ItemCategory'  => 'فئة (محذوفة)',
        'StockMovement' => 'حركة مخزون',
        'Warehouse'     => 'مستودع',
        'Stocktake'     => 'جرد',
    ],
];
