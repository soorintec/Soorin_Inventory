<?php

return [
    'label' => 'Journal', 'recent' => 'Dernières activités de l\'entrepôt', 'empty' => 'Aucune activité enregistrée.',
    'user' => 'Utilisateur', 'action' => 'Action', 'subject' => 'Objet', 'when' => 'Quand', 'system' => 'Système', 'view_all' => 'Toutes les activités',
    'actions' => [
        'stock_in' => 'Entrée', 'stock_out' => 'Sortie',
        'stocktake_cancelled' => 'Inventaire annulé',
        'stocktake_applied' => 'Inventaire appliqué',
        'item_created' => 'Article créé', 'item_updated' => 'Article modifié', 'item_deleted' => 'Article supprimé',
        'version_created' => 'Version créée', 'version_updated' => 'Version modifiée', 'version_deleted' => 'Version supprimée',
        'category_created' => 'Catégorie créée', 'category_updated' => 'Catégorie modifiée', 'category_deleted' => 'Catégorie supprimée',
        'warehouse_created' => 'Entrepôt créé', 'warehouse_updated' => 'Entrepôt modifié',
        'purchase_received' => 'Achat reçu',
        'stocktake_started' => 'Inventaire démarré', 'stocktake_closed' => 'Inventaire finalisé',
        'backup_created' => 'Sauvegarde créée', 'backup_restored' => 'Sauvegarde restaurée', 'backup_deleted' => 'Sauvegarde supprimée',
    ],
    'subjects' => [
        'Item' => 'Article (supprimé)', 'ItemVersion' => 'Version (supprimée)', 'ItemCategory' => 'Catégorie (supprimée)',
        'StockMovement' => 'Mouvement de stock', 'Warehouse' => 'Entrepôt', 'Stocktake' => 'Inventaire',
    ],
];
