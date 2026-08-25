<?php

return [
    'label' => 'Registro', 'recent' => 'Ultime attività di magazzino', 'empty' => 'Nessuna attività registrata.',
    'user' => 'Utente', 'action' => 'Azione', 'subject' => 'Oggetto', 'when' => 'Quando', 'system' => 'Sistema', 'view_all' => 'Tutte le attività',
    'actions' => [
        'stock_in' => 'Carico', 'stock_out' => 'Scarico',
        'item_created' => 'Articolo creato', 'item_updated' => 'Articolo modificato', 'item_deleted' => 'Articolo eliminato',
        'version_created' => 'Versione creata', 'version_updated' => 'Versione modificata', 'version_deleted' => 'Versione eliminata',
        'category_created' => 'Categoria creata', 'category_updated' => 'Categoria modificata', 'category_deleted' => 'Categoria eliminata',
        'warehouse_created' => 'Magazzino creato', 'warehouse_updated' => 'Magazzino modificato',
        'purchase_received' => 'Acquisto ricevuto',
        'stocktake_started' => 'Inventario avviato', 'stocktake_closed' => 'Inventario finalizzato',
        'backup_created' => 'Backup creato', 'backup_restored' => 'Backup ripristinato', 'backup_deleted' => 'Backup eliminato',
    ],
    'subjects' => [
        'Item' => 'Articolo (eliminato)', 'ItemVersion' => 'Versione (eliminata)', 'ItemCategory' => 'Categoria (eliminata)',
        'StockMovement' => 'Movimento di magazzino', 'Warehouse' => 'Magazzino', 'Stocktake' => 'Inventario',
    ],
];
