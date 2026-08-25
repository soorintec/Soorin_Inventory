<?php

return [
    'label'     => 'Backups',
    'nav_group' => 'Management & Reports',

    'how_it_works'   => 'What this page does',
    'hint_create'    => 'The "Create backup" button makes an SQL file of the whole database and keeps it on the server. Download the file and store it somewhere safe.',
    'hint_keep'      => 'Only the last 20 backups are kept; older ones are removed automatically.',
    'hint_restore'   => 'Restoring replaces the current data with the file\'s data. Before every restore, the system takes a backup of the current state so there is a way back.',
    'hint_portable'  => 'The output SQL file is standard, so it can also be restored with phpMyAdmin or the mysql command.',

    'create'    => 'Create backup',
    'created'   => 'Backup created: :file',

    'list'       => 'Backup files',
    'empty'      => 'No backup has been taken yet.',
    'file'       => 'File name',
    'created_at' => 'Date',
    'size'       => 'Size',
    'download'   => 'Download',
    'delete'     => 'Delete',
    'deleted'    => 'Backup file deleted.',
    'delete_confirm' => 'Delete this backup file?',
    'megabyte'   => 'MB',
    'kilobyte'   => 'KB',

    'restore'                 => 'Restore from file',
    'restore_heading'         => 'Restore database from a backup file',
    'restore_warning'         => 'Warning: restoring erases all current data and replaces it with the file\'s data. This is irreversible. Before running, the system takes a backup of the current state.',
    'restore_existing'            => 'Choose from backups already on the server',
    'restore_existing_hint'       => 'If the backup you want is already here on the server, select it; no need to download and re-upload.',
    'restore_existing_placeholder' => 'Select one, or upload a file below',
    'restore_no_source'           => 'Select an existing backup or upload a file.',
    'restore_file'            => 'Or upload a backup file (SQL or TXT)',
    'restore_file_hint'       => 'A file you previously downloaded (.sql), or a mysqldump / phpMyAdmin export. .txt is also accepted.',
    'restore_bad_type'        => 'Only .sql or .txt files are accepted.',
    'restore_understood'      => 'I understand the current data will be erased and this is irreversible.',
    'restore_confirm_button'  => 'Yes, restore',
    'restored'                => 'Restore completed successfully.',
    'restored_safety'         => 'A backup of the pre-restore state was saved as ":file".',
    'restored_no_safety'      => 'The database was empty, so a pre-restore backup was not needed.',
    'restore_failed'          => 'Restore failed.',
];
