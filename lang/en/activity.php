<?php

return [
    'label'      => 'Activity',
    'recent'     => 'Recent warehouse activity',
    'empty'      => 'No activity has been recorded yet.',
    'user'       => 'User',
    'action'     => 'Action',
    'subject'    => 'Subject',
    'when'       => 'When',
    'system'     => 'System',
    'view_all'   => 'All activity',

    // action types — keys match what ActivityLog::record stores
    'actions' => [
        'stock_in'          => 'Stock in',
        'stock_out'         => 'Stock out',
        'item_created'      => 'Item created',
        'item_updated'      => 'Item updated',
        'item_deleted'      => 'Item deleted',
        'version_created'   => 'Version created',
        'version_updated'   => 'Version updated',
        'version_deleted'   => 'Version deleted',
        'category_created'  => 'Category created',
        'category_updated'  => 'Category updated',
        'category_deleted'  => 'Category deleted',
        'warehouse_created' => 'Warehouse created',
        'warehouse_updated' => 'Warehouse updated',
        'purchase_received' => 'Purchase received',
        'stocktake_started' => 'Stocktake started',
        'stocktake_closed'  => 'Stocktake finalized',
        'backup_created'    => 'Backup created',
        'backup_restored'   => 'Backup restored',
        'backup_deleted'    => 'Backup deleted',
    ],

    'subjects' => [
        'Item'          => 'Item (deleted)',
        'ItemVersion'   => 'Version (deleted)',
        'ItemCategory'  => 'Category (deleted)',
        'StockMovement' => 'Stock transaction',
        'Warehouse'     => 'Warehouse',
        'Stocktake'     => 'Stocktake',
    ],
];
