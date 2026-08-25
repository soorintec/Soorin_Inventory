<?php

return [
    'label' => 'Protokoll', 'recent' => 'Letzte Lageraktivitäten', 'empty' => 'Noch keine Aktivität erfasst.',
    'user' => 'Benutzer', 'action' => 'Aktion', 'subject' => 'Objekt', 'when' => 'Wann', 'system' => 'System', 'view_all' => 'Alle Aktivitäten',
    'actions' => [
        'stock_in' => 'Zugang', 'stock_out' => 'Abgang',
        'item_created' => 'Artikel erstellt', 'item_updated' => 'Artikel geändert', 'item_deleted' => 'Artikel gelöscht',
        'version_created' => 'Version erstellt', 'version_updated' => 'Version geändert', 'version_deleted' => 'Version gelöscht',
        'category_created' => 'Kategorie erstellt', 'category_updated' => 'Kategorie geändert', 'category_deleted' => 'Kategorie gelöscht',
        'warehouse_created' => 'Lager erstellt', 'warehouse_updated' => 'Lager geändert',
        'purchase_received' => 'Einkauf erhalten',
        'stocktake_started' => 'Inventur gestartet', 'stocktake_closed' => 'Inventur abgeschlossen',
        'backup_created' => 'Sicherung erstellt', 'backup_restored' => 'Sicherung wiederhergestellt', 'backup_deleted' => 'Sicherung gelöscht',
    ],
    'subjects' => [
        'Item' => 'Artikel (gelöscht)', 'ItemVersion' => 'Version (gelöscht)', 'ItemCategory' => 'Kategorie (gelöscht)',
        'StockMovement' => 'Lagerbewegung', 'Warehouse' => 'Lager', 'Stocktake' => 'Inventur',
    ],
];
