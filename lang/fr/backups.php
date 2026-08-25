<?php

return [
    'label' => 'Sauvegardes', 'nav_group' => 'Gestion et rapports',
    'how_it_works' => 'Ce que fait cette page',
    'hint_create' => 'Le bouton « Créer une sauvegarde » génère un fichier SQL de toute la base et le conserve sur le serveur. Téléchargez le fichier et rangez-le en lieu sûr.',
    'hint_keep' => 'Seules les 20 dernières sauvegardes sont conservées ; les plus anciennes sont supprimées automatiquement.',
    'hint_restore' => 'La restauration remplace les données actuelles par celles du fichier. Avant chaque restauration, le système sauvegarde l\'état actuel.',
    'hint_portable' => 'Le fichier SQL de sortie est standard et peut aussi être restauré via phpMyAdmin ou la commande mysql.',
    'create' => 'Créer une sauvegarde', 'created' => 'Sauvegarde créée : :file',
    'list' => 'Fichiers de sauvegarde', 'empty' => 'Aucune sauvegarde.', 'file' => 'Nom du fichier', 'created_at' => 'Date', 'size' => 'Taille',
    'download' => 'Télécharger', 'delete' => 'Supprimer', 'deleted' => 'Fichier de sauvegarde supprimé.', 'delete_confirm' => 'Supprimer ce fichier de sauvegarde ?',
    'megabyte' => 'Mo', 'kilobyte' => 'Ko',
    'restore' => 'Restaurer depuis un fichier', 'restore_heading' => 'Restaurer la base depuis un fichier de sauvegarde',
    'restore_warning' => 'Attention : la restauration efface toutes les données actuelles et les remplace par celles du fichier. Irréversible. Avant l\'exécution, le système sauvegarde l\'état actuel.',
    'restore_existing' => 'Choisir parmi les sauvegardes sur le serveur', 'restore_existing_hint' => 'Si la sauvegarde voulue est déjà sur le serveur, sélectionnez-la ; pas besoin de la télécharger puis de la re-téléverser.',
    'restore_existing_placeholder' => 'Sélectionnez-en une ou téléversez un fichier ci-dessous', 'restore_no_source' => 'Sélectionnez une sauvegarde existante ou téléversez un fichier.',
    'restore_file' => 'Ou téléversez un fichier de sauvegarde (SQL ou TXT)', 'restore_file_hint' => 'Un fichier déjà téléchargé (.sql) ou un export mysqldump / phpMyAdmin. .txt aussi accepté.',
    'restore_bad_type' => 'Seuls les fichiers .sql ou .txt sont acceptés.', 'restore_understood' => 'Je comprends que les données actuelles seront effacées et que c\'est irréversible.',
    'restore_confirm_button' => 'Oui, restaurer', 'restored' => 'Restauration réussie.',
    'restored_safety' => 'Une sauvegarde de l\'état avant restauration a été enregistrée sous « :file ».', 'restored_no_safety' => 'La base était vide, aucune sauvegarde préalable n\'était nécessaire.',
    'restore_failed' => 'Échec de la restauration.',
];
