<?php

return [
    'nav_group' => 'المشتريات والاستيراد',

    'supplier_label'  => 'مورّد',
    'supplier_plural' => 'الموردون',
    'country'         => 'الدولة',

    'currency_label'  => 'العملة',
    'currency_plural' => 'العملات',
    'currency_code'   => 'رمز العملة',
    'currency_name'   => 'اسم العملة',

    'label'      => 'مستند شراء',
    'plural'     => 'المشتريات',
    'number'     => 'رقم المستند',
    'type'       => 'النوع',
    'types' => ['import' => 'استيراد', 'local' => 'محلي'],
    'order_date'    => 'تاريخ الطلب',
    'received_date' => 'تاريخ الاستلام',
    'warehouse'     => 'المستودع الوجهة',

    'currency_section' => 'العملة وسعر الصرف',
    'fx_amount'      => 'المبلغ الإجمالي بالعملة',
    'transfer_date'  => 'تاريخ الحوالة',
    'rate_to_irr'    => 'سعر صرف العملة إلى الريال',
    'usd_rate_irr'   => 'سعر الدولار في اليوم نفسه (مرجعي)',

    'costs_section' => 'التكاليف الإضافية (تُوزّع)',
    'shipping_cost'  => 'الشحن',
    'customs_cost'   => 'الجمارك',
    'clearance_cost' => 'التخليص',
    'insurance_cost' => 'التأمين',
    'other_cost'     => 'أخرى',
    'allocation_method' => 'طريقة التوزيع',
    'allocation_methods' => [
        'value'    => 'حسب قيمة البضاعة',
        'weight'   => 'حسب الوزن',
        'quantity' => 'حسب الكمية',
    ],

    'status'  => 'الحالة',
    'statuses' => [
        'draft'     => 'مسودة',
        'ordered'   => 'مطلوب',
        'received'  => 'مُستلم',
        'cancelled' => 'ملغى',
    ],

    'goods_value' => 'قيمة البضاعة (ريال)',
    'total_cost'  => 'التكلفة النهائية الإجمالية (ريال)',

    'items'            => 'أسطر الشراء',
    'item_version'     => 'نسخة الصنف',
    'quantity'         => 'الكمية',
    'fx_unit_price'    => 'سعر الوحدة (عملة)',
    'weight_kg'        => 'وزن الوحدة (كجم)',
    'unit_price_irr'   => 'سعر الوحدة (ريال، دون تكاليف إضافية)',
    'allocated_cost'   => 'نصيب التكلفة الإضافية',
    'landed_unit_cost' => 'التكلفة النهائية للوحدة',

    'receive'         => 'استلام البضاعة وإدخالها للمستودع',
    'receive_confirm' => 'بالتأكيد، تُحسب التكلفة النهائية لكل سطر وتُدخل للمستودع الوجهة. هذا الإجراء غير قابل للتراجع.',
    'already_received' => 'سبق استلام هذا المستند.',
    'no_items'          => 'أضف سطراً واحداً على الأقل أولاً.',

    'empty' => 'لم تُسجّل مستندات شراء بعد.',
];
