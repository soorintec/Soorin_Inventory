<?php

return [
    'nav_group' => 'خرید و واردات',

    'supplier_label'  => 'تأمین‌کننده',
    'supplier_plural' => 'تأمین‌کنندگان',
    'country'         => 'کشور',

    'currency_label'  => 'ارز',
    'currency_plural' => 'ارزها',
    'currency_code'   => 'کد ارز',
    'currency_name'   => 'نام ارز',

    'label'      => 'سند خرید',
    'plural'     => 'خریدها',
    'number'     => 'شماره سند',
    'type'       => 'نوع',
    'types' => ['import' => 'وارداتی', 'local' => 'داخلی'],
    'order_date'    => 'تاریخ سفارش',
    'received_date' => 'تاریخ دریافت',
    'warehouse'     => 'انبار مقصد',

    'currency_section' => 'ارز و نرخ روز',
    'fx_amount'      => 'مبلغ کل به ارز',
    'transfer_date'  => 'تاریخ حواله',
    'rate_to_irr'    => 'نرخ ارز به ریال',
    'usd_rate_irr'   => 'نرخ دلار همان روز (مرجع)',

    'costs_section' => 'هزینه‌های جانبی (سرشکن‌شونده)',
    'shipping_cost'  => 'کرایه حمل',
    'customs_cost'   => 'گمرک',
    'clearance_cost' => 'ترخیص',
    'insurance_cost' => 'بیمه',
    'other_cost'     => 'سایر',
    'allocation_method' => 'روش سرشکن',
    'allocation_methods' => [
        'value'    => 'بر مبنای ارزش کالا',
        'weight'   => 'بر مبنای وزن',
        'quantity' => 'بر مبنای تعداد',
    ],

    'status'  => 'وضعیت',
    'statuses' => [
        'draft'     => 'پیش‌نویس',
        'ordered'   => 'سفارش‌داده‌شده',
        'received'  => 'دریافت‌شده',
        'cancelled' => 'لغوشده',
    ],

    'goods_value' => 'ارزش کالا (ریال)',
    'total_cost'  => 'قیمت تمام‌شده کل (ریال)',

    'items'            => 'ردیف‌های خرید',
    'item_version'     => 'ورژن کالا',
    'quantity'         => 'تعداد',
    'fx_unit_price'    => 'قیمت واحد (ارز)',
    'weight_kg'        => 'وزن واحد (کیلوگرم)',
    'unit_price_irr'   => 'قیمت واحد (ریال، بدون هزینه جانبی)',
    'allocated_cost'   => 'سهم هزینه جانبی',
    'landed_unit_cost' => 'قیمت تمام‌شده نهایی هر واحد',

    'receive'         => 'دریافت کالا و ورود به انبار',
    'receive_confirm' => 'با تأیید، قیمت تمام‌شده هر ردیف محاسبه و به انبار مقصد وارد می‌شود. این عمل قابل بازگشت نیست.',
    'already_received' => 'این سند قبلاً دریافت شده است.',
    'no_items'          => 'ابتدا حداقل یک ردیف اضافه کنید.',

    'empty' => 'هنوز سند خریدی ثبت نشده است.',
];
