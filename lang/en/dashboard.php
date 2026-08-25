<?php

return [
    'title' => 'Dashboard',

    // top stats
    'items'                 => 'Items',
    'items_hint'            => ':count versions',
    'total_quantity'        => 'Total stock',
    'total_quantity_hint'   => 'Sum of all items across all warehouses',
    'stock_value'           => 'Stock value (:currency)',
    'stock_value_hint'      => 'Each version price × quantity',
    'stock_value_generic'   => 'Stock value',
    'stock_value_none'      => 'No prices have been set for items yet.',
    'last_backup'           => 'Last backup',
    'last_backup_hint'      => 'No backup has been taken yet.',
    'last_stocktake'        => 'Last stocktake',
    'last_stocktake_hint'   => 'No stocktake has been done yet.',
    'never'                 => 'Never',
    'out_of_stock'          => 'Out-of-stock items',
    'low_stock_hint'        => ':count items below the alert level',
    'movements_today'       => 'Transactions (last 24h)',
    'movements_today_hint'  => 'In, out and transfers',

    // low-stock box
    'low_stock'       => 'Out-of-stock and running-low items',
    'stock_all_good'  => 'No item is out of stock or below the alert level.',

    // logins box
    'recent_logins'    => 'Recent sign-ins',
    'no_logins'        => 'Nobody has signed in yet.',
    'never_logged_in'  => ':count users have never signed in.',
    'inactive'         => 'Inactive',
];
