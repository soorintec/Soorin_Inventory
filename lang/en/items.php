<?php

/*
| Items — three-level structure: category ← item ← version.
| Stock and price always live on the version, not the item.
*/

return [
    'category_label'  => 'Item category',
    'category_plural' => 'Categories',
    'category_parent' => 'Parent category',
    'spec_template'   => 'Technical spec template',
    'spec_template_hint' => 'Keys that should be recorded for this category (e.g. CPU, RAM, disk). A dynamic form is built from this template for each version of the item.',
    'spec_key'   => 'Key',
    'spec_label' => 'Label',

    'label'       => 'Item',
    'plural'      => 'Items',
    'nav_group'   => 'Warehouse',
    // Menu label — not "Items", because this page shows items together with
    // their stock, and users think of it as the warehouse stock.
    'nav_label'   => 'Warehouse stock',
    'stock_intro' => 'List of warehouse items with their stock. Click an item to open its versions, location and notes. Recording stock in/out and editing items is done in the "Warehouse management" section.',
    'code'        => 'Item code',
    'name'        => 'Item name',
    'brand'       => 'Brand',
    'unit'        => 'Unit',
    'unit_default'=> 'pcs',
    'track_serial'      => 'Track serials',
    'track_serial_hint' => 'For expensive items. Turning this on and saving the item shows the "Serial numbers" table at the bottom of this page.',
    'description' => 'Description',
    'total_stock' => 'Total stock',

    'version_label'  => 'Version',
    'version_plural' => 'Versions',
    'version_code'   => 'Version code',
    'version_name'   => 'Display name',
    'location'       => 'Location',
    'location_hint'  => 'Physical shelf address — e.g. "D3/#04" or "middle right drawer".',
    'fx_price'       => 'Price',
    'fx_price_hint'  => 'Unit price of this version. Choose its currency in the adjacent field. Empty means no price recorded.',
    'fx_currency'    => 'Price currency',
    'notes'          => 'Warehouse note',
    'notes_hint'     => 'Something the warehouse keeper should know — e.g. "two units with the engineer for repair" or "running low".',
    'year'           => 'Year',
    'min_stock'      => 'Stock alert level',
    'min_stock_hint' => 'When total stock falls below this number, an alert is shown.',
    'specs'          => 'Technical specs',
    'current_stock'  => 'Current stock',
    'below_min'      => 'Below alert level',

    // serial numbers
    'serials'            => 'Serial numbers',
    'serial'             => 'Serial number',
    'serial_add'         => 'Add serial',
    'serial_duplicate'   => 'This serial has already been recorded for this item.',
    'serial_status'      => 'Status',
    'serial_statuses'    => [
        'in_stock'  => 'In stock',
        'installed' => 'Installed at customer',
        'defective' => 'Defective',
        'scrapped'  => 'Scrapped',
    ],
    'warranty_until'     => 'Supplier warranty until',
    'warranty_hint'      => 'The seller\'s warranty end date — not our warranty to the customer.',
    'serial_bulk_add'    => 'Bulk add',
    'serial_bulk_list'   => 'Serials list',
    'serial_bulk_hint'   => 'One serial per line (or comma-separated). Duplicate serials are skipped automatically.',
    'serial_bulk_done'   => ':added serials added, :skipped duplicates skipped.',
    'serials_empty'      => 'No serial numbers recorded yet.',
    'serials_empty_hint' => 'Use "Bulk add" to enter serials all at once from the packing list.',

    'status'   => 'Status',
    'statuses' => [
        'out' => 'Out of stock',
        'low' => 'Running low — at or below the alert level',
        'ok'  => 'In stock',
    ],
    'only_low_stock'         => 'Only out-of-stock and running-low',

    'only_in_stock'          => 'Only in-stock items',
    'only_imported'          => 'Only imported items',
    'empty_versions_inline'  => 'This item has no versions yet.',

    'empty_categories' => 'No categories recorded yet.',
    'empty_items'       => 'No items recorded yet.',
    'empty_versions'    => 'This item has no versions yet. Without a version, stock and price cannot be recorded.',
];
