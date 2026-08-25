<?php

return [
    'badge_tooltip'   => 'Une nouvelle version est disponible — cliquez pour mettre à jour.',
    'label' => 'Mises à jour', 'current_version' => 'Version actuelle', 'offline_mode' => 'Mise à jour par fichier uniquement',
    'how_it_works' => 'Ce que fait cette page',
    'hint_check' => '« Vérifier les mises à jour » compare la version actuelle à la dernière sur GitHub.',
    'hint_git' => 'Si l\'application est installée via git et qu\'une nouvelle version existe, « Mettre à jour depuis GitHub » la récupère.',
    'hint_zip' => 'Sans Internet, téléversez le fichier zip de la nouvelle version via « Mettre à jour depuis un fichier ».',
    'hint_backup' => 'Avant chaque mise à jour, le système sauvegarde lui-même la base.',
    'check' => 'Vérifier les mises à jour', 'check_failed' => 'Impossible de vérifier les mises à jour.', 'up_to_date' => 'L\'application est à jour ; pas de version plus récente.', 'available' => 'Nouvelle version disponible : :version',
    'update_git' => 'Mettre à jour depuis GitHub', 'update_zip' => 'Mettre à jour depuis un fichier',
    'update_warning' => 'L\'application sera mise à jour. Une sauvegarde de la base est d\'abord effectuée. Cela peut prendre un moment ; ne fermez pas la page.',
    'updated' => 'L\'application a été mise à jour vers la version :version.', 'updated_backup' => 'Sauvegarde avant mise à jour : :file', 'update_failed' => 'Échec de la mise à jour.',
    'zip_file' => 'Fichier zip de la nouvelle version', 'zip_hint' => 'Le fichier du paquet de l\'application (avec artisan). Fichiers .zip uniquement.', 'zip_bad_type' => 'Seuls les fichiers .zip sont acceptés.',
    'link_git' => 'Connecter à GitHub',
    'link_git_warning' => 'Cette installation provient d\'un zip et n\'est pas connectée à GitHub. Ceci créera le dossier git et le connectera au dépôt afin que « Mettre à jour depuis GitHub » fonctionne. Les fichiers de l\'application sont alignés sur la version du dépôt ; les réglages et données (.env et base) restent intacts et une sauvegarde est faite au préalable.',
    'link_git_url' => 'URL du dépôt GitHub', 'link_git_url_hint' => 'Si le dépôt est privé, placez le jeton dans l\'URL : https://<TOKEN>@github.com/…',
    'link_git_done' => 'Connecté à GitHub. « Mettre à jour depuis GitHub » est maintenant disponible.', 'link_git_failed' => 'Échec de la connexion à GitHub.',
];
