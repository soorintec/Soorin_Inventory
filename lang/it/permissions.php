<?php

return [
    'labels' => [
        'backups.settings' => 'Impostazioni di backup automatico e di rete',
        'items.view' => 'Visualizza articoli', 'items.manage' => 'Crea e modifica articoli e categorie',
        'stock.view' => 'Visualizza giacenza', 'stock.manage' => 'Registra carichi e scarichi',
        'warehouses.manage' => 'Crea e gestisci magazzini', 'stocktakes.manage' => 'Inventario',
        'purchases.view' => 'Visualizza acquisti', 'purchases.manage' => 'Crea e gestisci acquisti',
        'projects.view' => 'Visualizza progetti e sistemi', 'projects.manage' => 'Gestisci progetti',
        'system_models.manage' => 'Definisci modelli di sistema e relative parti',
        'customers.view' => 'Visualizza clienti', 'customers.manage' => 'Crea e modifica clienti',
        'users.view' => 'Visualizza utenti', 'users.manage' => 'Crea e modifica utenti',
        'settings.manage' => 'Impostazioni di sistema', 'activity.view' => 'Visualizza registro attività',
        'reports.view' => 'Visualizza e stampa report', 'backups.view' => 'Visualizza e scarica i backup',
        'backups.create' => 'Crea backup', 'backups.delete' => 'Elimina file di backup', 'backups.restore' => 'Ripristina il database da un backup',
    ],
    'hints' => [
        'backups.settings' => 'Configura la cartella di rete e la pianificazione dei backup automatici.',
        'backups.restore' => 'Pericoloso: sostituisce i dati attuali con quelli del file.',
        'stock.manage' => 'Senza questo, l\'utente può solo vedere la giacenza ma non modificarla.',
        'stock.view' => 'Accesso di magazzino più basilare; senza di esso l\'utente non vede alcuna pagina di magazzino.',
    ],
    'groups' => [
        'warehouse' => 'Magazzino e articoli', 'purchasing' => 'Acquisti e import', 'projects' => 'Sistemi e progetti',
        'customers' => 'Clienti', 'reports' => 'Report', 'backups' => 'Backup', 'system' => 'Amministrazione sistema',
    ],
];
