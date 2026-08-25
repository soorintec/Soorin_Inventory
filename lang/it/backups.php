<?php

return [
    'label' => 'Backup', 'nav_group' => 'Gestione e report',
    'how_it_works' => 'Cosa fa questa pagina',
    'hint_create' => 'Il pulsante «Crea backup» genera un file SQL dell\'intero database e lo conserva sul server. Scarica il file e conservalo in un luogo sicuro.',
    'hint_keep' => 'Vengono conservati solo gli ultimi 20 backup; i più vecchi vengono eliminati automaticamente.',
    'hint_restore' => 'Il ripristino sostituisce i dati attuali con quelli del file. Prima di ogni ripristino, il sistema salva lo stato attuale.',
    'hint_portable' => 'Il file SQL in uscita è standard e può essere ripristinato anche con phpMyAdmin o il comando mysql.',
    'create' => 'Crea backup', 'created' => 'Backup creato: :file',
    'list' => 'File di backup', 'empty' => 'Nessun backup.', 'file' => 'Nome file', 'created_at' => 'Data', 'size' => 'Dimensione',
    'download' => 'Scarica', 'delete' => 'Elimina', 'deleted' => 'File di backup eliminato.', 'delete_confirm' => 'Eliminare questo file di backup?',
    'megabyte' => 'MB', 'kilobyte' => 'KB',
    'restore' => 'Ripristina da file', 'restore_heading' => 'Ripristina il database da un file di backup',
    'restore_warning' => 'Attenzione: il ripristino cancella tutti i dati attuali e li sostituisce con quelli del file. Irreversibile. Prima dell\'esecuzione il sistema salva lo stato attuale.',
    'restore_existing' => 'Scegli dai backup sul server', 'restore_existing_hint' => 'Se il backup desiderato è già sul server, selezionalo; non serve scaricarlo e ricaricarlo.',
    'restore_existing_placeholder' => 'Selezionane uno o carica un file qui sotto', 'restore_no_source' => 'Seleziona un backup esistente o carica un file.',
    'restore_file' => 'Oppure carica un file di backup (SQL o TXT)', 'restore_file_hint' => 'Un file scaricato in precedenza (.sql) o un export mysqldump / phpMyAdmin. Accettato anche .txt.',
    'restore_bad_type' => 'Sono accettati solo file .sql o .txt.', 'restore_understood' => 'Capisco che i dati attuali verranno cancellati e che è irreversibile.',
    'restore_confirm_button' => 'Sì, ripristina', 'restored' => 'Ripristino completato con successo.',
    'restored_safety' => 'Un backup dello stato precedente al ripristino è stato salvato come «:file».', 'restored_no_safety' => 'Il database era vuoto, quindi non è servito un backup preliminare.',
    'restore_failed' => 'Ripristino non riuscito.',
];
