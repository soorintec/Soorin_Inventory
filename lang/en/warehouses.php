<?php

return [
    'label'      => 'Warehouse',
    'plural'     => 'Warehouses',
    'nav_group'  => 'Warehouse',
    'name'       => 'Warehouse name',
    'code'       => 'Warehouse code',
    'type'       => 'Warehouse type',
    'types' => [
        'main'        => 'Main',
        'consignment' => 'Consignment at customer',
        'defective'   => 'Returns and defective',
        'transit'     => 'In transit',
    ],
    'customer'   => 'Customer',
    'customer_hint' => 'Only for consignment warehouses — specifies which customer this consignment stock is with.',

    'empty' => 'No warehouses recorded yet.',
];
