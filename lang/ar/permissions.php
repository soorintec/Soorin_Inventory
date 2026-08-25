<?php

/*
| تسميات الصلاحيات — المفاتيح مطابقة لقيم App\Enums\Permission.
*/

return [
    'labels' => [
        'items.view'          => 'عرض الأصناف',
        'items.manage'        => 'تعريف وتعديل الأصناف والفئات',
        'stock.view'          => 'عرض مخزون المستودع',
        'stock.manage'        => 'تسجيل إدخال وإخراج المخزون',
        'warehouses.manage'   => 'تعريف وإدارة المستودعات',
        'stocktakes.manage'   => 'الجرد',
        'purchases.view'      => 'عرض المشتريات',
        'purchases.manage'    => 'تسجيل وإدارة المشتريات',
        'projects.view'       => 'عرض المشاريع والأنظمة',
        'projects.manage'     => 'إدارة المشاريع',
        'system_models.manage'=> 'تعريف نماذج الأنظمة وقطعها',
        'customers.view'      => 'عرض العملاء',
        'customers.manage'    => 'تعريف وتعديل العملاء',
        'users.view'          => 'عرض المستخدمين',
        'users.manage'        => 'إنشاء وتعديل المستخدمين',
        'settings.manage'     => 'إعدادات النظام',
        'activity.view'       => 'عرض سجل الأنشطة',
        'reports.view'        => 'عرض وطباعة التقارير',
        'backups.view'        => 'عرض وتنزيل ملفات النسخ الاحتياطي',
        'backups.create'      => 'إنشاء نسخ احتياطية',
        'backups.delete'      => 'حذف ملفات النسخ الاحتياطي',
        'backups.restore'     => 'استعادة قاعدة البيانات من نسخة احتياطية',
    ],

    'hints' => [
        'backups.restore' => 'خطير: يستبدل البيانات الحالية ببيانات الملف.',
        'stock.manage'    => 'بدون هذا، يرى المستخدم المخزون فقط ولا يستطيع تغييره.',
        'stock.view'      => 'أبسط صلاحيات المستودع؛ بدونها لا يرى المستخدم أي صفحة مستودع.',
    ],

    'groups' => [
        'warehouse'  => 'المستودع والأصناف',
        'purchasing' => 'المشتريات والاستيراد',
        'projects'   => 'الأنظمة والمشاريع',
        'customers'  => 'العملاء',
        'reports'    => 'التقارير',
        'backups'    => 'النسخ الاحتياطي',
        'system'     => 'إدارة النظام',
    ],
];
