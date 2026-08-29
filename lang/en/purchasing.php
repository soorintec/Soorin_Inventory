<?php

return [
    'add_item' => 'Add item',
    'nav_group' => 'Purchasing & Imports',

    'supplier_label'  => 'Supplier',
    'supplier_plural' => 'Suppliers',
    'country'         => 'Country',

    'currency_label'  => 'Currency',
    'currency_plural' => 'Currencies',
    'currency_code'   => 'Currency code',
    'currency_name'   => 'Currency name',

    'label'      => 'Purchase document',
    'plural'     => 'Purchases',
    'number'     => 'Document number',
    'type'       => 'Type',
    'types' => ['import' => 'Import', 'local' => 'Local'],
    'order_date'    => 'Order date',
    'received_date' => 'Received date',
    'warehouse'     => 'Destination warehouse',

    'currency_section' => 'Currency and exchange rate',
    'fx_amount'      => 'Total amount in currency',
    'transfer_date'  => 'Transfer date',
    'rate_to_irr'    => 'Exchange rate to Rial',
    'usd_rate_irr'   => 'USD rate same day (reference)',

    'costs_section' => 'Additional costs (allocated)',
    'shipping_cost'  => 'Shipping',
    'customs_cost'   => 'Customs',
    'clearance_cost' => 'Clearance',
    'insurance_cost' => 'Insurance',
    'other_cost'     => 'Other',
    'allocation_method' => 'Allocation method',
    'allocation_methods' => [
        'value'    => 'By goods value',
        'weight'   => 'By weight',
        'quantity' => 'By quantity',
    ],

    'status'  => 'Status',
    'statuses' => [
        'draft'     => 'Draft',
        'ordered'   => 'Ordered',
        'received'  => 'Received',
        'cancelled' => 'Cancelled',
    ],

    'goods_value' => 'Goods value (Rial)',
    'total_cost'  => 'Total landed cost (Rial)',

    'items'            => 'Purchase rows',
    'item_version'     => 'Item version',
    'quantity'         => 'Quantity',
    'fx_unit_price'    => 'Unit price (currency)',
    'weight_kg'        => 'Unit weight (kg)',
    'unit_price_irr'   => 'Unit price (Rial, excl. extra costs)',
    'allocated_cost'   => 'Allocated extra cost',
    'landed_unit_cost' => 'Final landed unit cost',

    'receive'         => 'Receive goods into warehouse',
    'receive_confirm' => 'On confirmation, the landed cost of each row is calculated and added to the destination warehouse. This action is irreversible.',
    'already_received' => 'This document has already been received.',
    'no_items'          => 'Add at least one row first.',

    'empty' => 'No purchase documents recorded yet.',
];
