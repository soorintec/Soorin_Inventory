<?php

return [
    'label' => 'Sicherungen', 'nav_group' => 'Verwaltung & Berichte',
    'how_it_works' => 'Was diese Seite tut',
    'hint_create' => 'Die Schaltfläche „Sicherung erstellen“ erzeugt eine SQL-Datei der gesamten Datenbank und speichert sie auf dem Server. Laden Sie die Datei herunter und bewahren Sie sie sicher auf.',
    'hint_keep' => 'Es werden nur die letzten 20 Sicherungen aufbewahrt; ältere werden automatisch gelöscht.',
    'hint_restore' => 'Die Wiederherstellung ersetzt die aktuellen Daten durch die Dateidaten. Vor jeder Wiederherstellung sichert das System den aktuellen Stand.',
    'hint_portable' => 'Die SQL-Ausgabedatei ist Standard und kann auch mit phpMyAdmin oder dem mysql-Befehl wiederhergestellt werden.',
    'create' => 'Sicherung erstellen', 'created' => 'Sicherung erstellt: :file',
    'list' => 'Sicherungsdateien', 'empty' => 'Noch keine Sicherung.', 'file' => 'Dateiname', 'created_at' => 'Datum', 'size' => 'Größe',
    'download' => 'Herunterladen', 'delete' => 'Löschen', 'deleted' => 'Sicherungsdatei gelöscht.', 'delete_confirm' => 'Diese Sicherungsdatei löschen?',
    'megabyte' => 'MB', 'kilobyte' => 'KB',
    'restore' => 'Aus Datei wiederherstellen', 'restore_heading' => 'Datenbank aus Sicherungsdatei wiederherstellen',
    'restore_warning' => 'Achtung: Die Wiederherstellung löscht alle aktuellen Daten und ersetzt sie durch die Dateidaten. Unumkehrbar. Vor dem Start sichert das System den aktuellen Stand.',
    'restore_existing' => 'Aus Sicherungen auf dem Server wählen', 'restore_existing_hint' => 'Liegt die gewünschte Sicherung bereits auf dem Server, wählen Sie sie; kein erneutes Herunter-/Hochladen nötig.',
    'restore_existing_placeholder' => 'Eine wählen oder unten eine Datei hochladen', 'restore_no_source' => 'Wählen Sie eine vorhandene Sicherung oder laden Sie eine Datei hoch.',
    'restore_file' => 'Oder Sicherungsdatei hochladen (SQL oder TXT)', 'restore_file_hint' => 'Eine zuvor heruntergeladene Datei (.sql) oder ein mysqldump/phpMyAdmin-Export. .txt wird ebenfalls akzeptiert.',
    'restore_bad_type' => 'Nur .sql- oder .txt-Dateien werden akzeptiert.', 'restore_understood' => 'Ich verstehe, dass die aktuellen Daten gelöscht werden und dies unumkehrbar ist.',
    'restore_confirm_button' => 'Ja, wiederherstellen', 'restored' => 'Wiederherstellung erfolgreich abgeschlossen.',
    'restored_safety' => 'Eine Sicherung des Zustands vor der Wiederherstellung wurde als „:file“ gespeichert.', 'restored_no_safety' => 'Die Datenbank war leer, daher war keine Vor-Wiederherstellungssicherung nötig.',
    'restore_failed' => 'Wiederherstellung fehlgeschlagen.',
];
