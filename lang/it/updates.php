<?php

return [
    'badge_tooltip'   => 'È disponibile una nuova versione — clicca per aggiornare.',
    'label' => 'Aggiornamenti', 'current_version' => 'Versione attuale', 'offline_mode' => 'Solo aggiornamento da file',
    'how_it_works' => 'Cosa fa questa pagina',
    'hint_check' => '«Controlla aggiornamenti» confronta la versione attuale con l\'ultima su GitHub.',
    'hint_git' => 'Se l\'app è installata via git e c\'è una nuova versione, «Aggiorna da GitHub» la scarica.',
    'hint_zip' => 'Senza Internet, carica il file zip della nuova versione con «Aggiorna da file».',
    'hint_backup' => 'Prima di ogni aggiornamento, il sistema esegue da sé un backup del database.',
    'check' => 'Controlla aggiornamenti', 'check_failed' => 'Impossibile controllare gli aggiornamenti.', 'up_to_date' => 'L\'app è aggiornata; nessuna versione più recente.', 'available' => 'Nuova versione disponibile: :version',
    'update_git' => 'Aggiorna da GitHub', 'update_zip' => 'Aggiorna da file',
    'update_warning' => 'L\'app verrà aggiornata. Prima viene eseguito un backup del database. Può richiedere un momento; non chiudere la pagina.',
    'updated' => 'L\'app è stata aggiornata alla versione :version.', 'updated_backup' => 'Backup prima dell\'aggiornamento: :file', 'update_failed' => 'Aggiornamento non riuscito.',
    'zip_file' => 'File zip della nuova versione', 'zip_hint' => 'Il file del pacchetto dell\'app (con artisan). Solo file .zip.', 'zip_bad_type' => 'Sono accettati solo file .zip.',
    'link_git' => 'Collega a GitHub',
    'link_git_warning' => 'Questa installazione proviene da uno zip e non è collegata a GitHub. Questa operazione crea la cartella git e la collega al repository affinché «Aggiorna da GitHub» funzioni. I file dell\'app vengono allineati alla versione del repository; impostazioni e dati (.env e database) restano intatti e viene eseguito un backup preventivo.',
    'link_git_url' => 'URL del repository GitHub', 'link_git_url_hint' => 'Se il repository è privato, inserisci il token nell\'URL: https://<TOKEN>@github.com/…',
    'link_git_done' => 'Collegato a GitHub. «Aggiorna da GitHub» è ora disponibile.', 'link_git_failed' => 'Collegamento a GitHub non riuscito.',
];
