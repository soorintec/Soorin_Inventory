<?php

/*
| Permission labels — keys match the App\Enums\Permission enum values.
*/

return [
    'labels' => [
        'items.view'          => 'View items',
        'items.manage'        => 'Create and edit items and categories',
        'stock.view'          => 'View warehouse stock',
        'stock.manage'        => 'Record stock in and out',
        'warehouses.manage'   => 'Create and manage warehouses',
        'stocktakes.manage'   => 'Stocktake',
        'purchases.view'      => 'View purchases',
        'purchases.manage'    => 'Create and manage purchases',
        'projects.view'       => 'View projects and systems',
        'projects.manage'     => 'Manage projects',
        'system_models.manage'=> 'Define system models and their parts',
        'customers.view'      => 'View customers',
        'customers.manage'    => 'Create and edit customers',
        'users.view'          => 'View users',
        'users.manage'        => 'Create and edit users',
        'settings.manage'     => 'System settings',
        'activity.view'       => 'View activity log',
        'reports.view'        => 'View and print reports',
        'backups.view'        => 'View and download backup files',
        'backups.create'      => 'Create backups',
        'backups.delete'      => 'Delete backup files',
        'backups.restore'     => 'Restore database from backup',
    ],

    'hints' => [
        'backups.restore' => 'Dangerous: replaces the current data with the file\'s data.',
        'stock.manage'    => 'Without this, the user can only view stock and cannot change it.',
        'stock.view'      => 'The most basic warehouse access; without it the user sees no warehouse page.',
    ],

    'groups' => [
        'warehouse'  => 'Warehouse & items',
        'purchasing' => 'Purchasing & imports',
        'projects'   => 'Systems & projects',
        'customers'  => 'Customers',
        'reports'    => 'Reports',
        'backups'    => 'Backups',
        'system'     => 'System management',
    ],
];
