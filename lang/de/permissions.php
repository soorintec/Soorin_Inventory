<?php

return [
    'labels' => [
        'items.view' => 'Artikel ansehen', 'items.manage' => 'Artikel und Kategorien erstellen und bearbeiten',
        'stock.view' => 'Lagerbestand ansehen', 'stock.manage' => 'Zu- und Abgänge erfassen',
        'warehouses.manage' => 'Lager erstellen und verwalten', 'stocktakes.manage' => 'Inventur',
        'purchases.view' => 'Einkäufe ansehen', 'purchases.manage' => 'Einkäufe erstellen und verwalten',
        'projects.view' => 'Projekte und Systeme ansehen', 'projects.manage' => 'Projekte verwalten',
        'system_models.manage' => 'Systemmodelle und deren Teile definieren',
        'customers.view' => 'Kunden ansehen', 'customers.manage' => 'Kunden erstellen und bearbeiten',
        'users.view' => 'Benutzer ansehen', 'users.manage' => 'Benutzer erstellen und bearbeiten',
        'settings.manage' => 'Systemeinstellungen', 'activity.view' => 'Aktivitätsprotokoll ansehen',
        'reports.view' => 'Berichte ansehen und drucken', 'backups.view' => 'Sicherungsdateien ansehen und herunterladen',
        'backups.create' => 'Sicherungen erstellen', 'backups.delete' => 'Sicherungsdateien löschen', 'backups.restore' => 'Datenbank aus Sicherung wiederherstellen',
    ],
    'hints' => [
        'backups.restore' => 'Gefährlich: ersetzt die aktuellen Daten durch die Dateidaten.',
        'stock.manage' => 'Ohne dies kann der Benutzer den Bestand nur ansehen, aber nicht ändern.',
        'stock.view' => 'Grundlegendster Lagerzugriff; ohne ihn sieht der Benutzer keine Lagerseite.',
    ],
    'groups' => [
        'warehouse' => 'Lager & Artikel', 'purchasing' => 'Einkauf & Import', 'projects' => 'Systeme & Projekte',
        'customers' => 'Kunden', 'reports' => 'Berichte', 'backups' => 'Sicherungen', 'system' => 'Systemverwaltung',
    ],
];
