<?php

return [
    'labels' => [
        'items.view' => 'Voir les articles', 'items.manage' => 'Créer et modifier articles et catégories',
        'stock.view' => 'Voir le stock', 'stock.manage' => 'Enregistrer entrées et sorties',
        'warehouses.manage' => 'Créer et gérer les entrepôts', 'stocktakes.manage' => 'Inventaire',
        'purchases.view' => 'Voir les achats', 'purchases.manage' => 'Créer et gérer les achats',
        'projects.view' => 'Voir projets et systèmes', 'projects.manage' => 'Gérer les projets',
        'system_models.manage' => 'Définir les modèles de système et leurs pièces',
        'customers.view' => 'Voir les clients', 'customers.manage' => 'Créer et modifier les clients',
        'users.view' => 'Voir les utilisateurs', 'users.manage' => 'Créer et modifier les utilisateurs',
        'settings.manage' => 'Paramètres du système', 'activity.view' => 'Voir le journal d\'activité',
        'reports.view' => 'Voir et imprimer les rapports', 'backups.view' => 'Voir et télécharger les sauvegardes',
        'backups.create' => 'Créer des sauvegardes', 'backups.delete' => 'Supprimer des sauvegardes', 'backups.restore' => 'Restaurer la base depuis une sauvegarde',
    ],
    'hints' => [
        'backups.restore' => 'Dangereux : remplace les données actuelles par celles du fichier.',
        'stock.manage' => 'Sans cela, l\'utilisateur ne fait que voir le stock sans pouvoir le modifier.',
        'stock.view' => 'Accès entrepôt le plus basique ; sans lui, l\'utilisateur ne voit aucune page d\'entrepôt.',
    ],
    'groups' => [
        'warehouse' => 'Entrepôt et articles', 'purchasing' => 'Achats et import', 'projects' => 'Systèmes et projets',
        'customers' => 'Clients', 'reports' => 'Rapports', 'backups' => 'Sauvegardes', 'system' => 'Administration du système',
    ],
];
