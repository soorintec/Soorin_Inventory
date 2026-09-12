<?php

return [
    'badge_tooltip'   => 'A new version is available — click to update.',
    'label'           => 'Updates',
    'current_version' => 'Current version',
    'offline_mode'    => 'File update only',

    'how_it_works'    => 'What this page does',
    'hint_check'      => '"Check for updates" compares the current version with the latest on GitHub.',
    'hint_git'        => 'If the app was installed with git and a new version exists, "Update from GitHub" fetches it.',
    'hint_zip'        => 'If you have no internet, upload the new version zip file with "Update from file".',
    'hint_backup'     => 'Before every update, the system takes a database backup itself.',

    'check'           => 'Check for updates',
    'check_failed'    => 'Could not check for updates.',
    'up_to_date'      => 'The app is up to date; no newer version exists.',
    'available'       => 'A new version is available: :version',

    'update_git'      => 'Update from GitHub',
    'update_zip'      => 'Update from file',
    'update_warning'  => 'The app will be updated to the new version. A database backup is taken first. It may take a moment; do not close the page mid-way.',
    'updated'         => 'The app was updated to version :version.',
    'updated_backup'  => 'Pre-update backup: :file',
    'update_failed'   => 'Update failed.',

    'zip_file'        => 'New version zip file',
    'zip_hint'        => 'The app package file (containing artisan). Only .zip files.',
    'zip_bad_type'    => 'Only .zip files are accepted.',

    'link_git'          => 'Connect to GitHub',
    'link_git_warning'  => 'This install was done with a zip file and is not connected to GitHub. This will create the git folder and connect it to the repository so that "Update from GitHub" works from now on. The app files are aligned with the repository version; your settings and data (.env and database) stay untouched and a backup is taken beforehand.',
    'link_git_url'      => 'GitHub repository URL',
    'link_git_url_hint' => 'If the repository is private, put the token in the URL: https://<TOKEN>@github.com/…',
    'link_git_done'     => 'Connected to GitHub. "Update from GitHub" is now available.',
    'link_git_failed'   => 'Connecting to GitHub failed.',

    'rollback'                => 'Roll back to previous version',
    'rollback_heading'        => 'Roll back to the previous version',
    'rollback_warning'        => 'The app code will return to version :version (before the last update). A fresh backup of the current state is taken first, so this rollback is itself reversible. Choose what happens to the database:',
    'rollback_db_label'       => 'What about the database?',
    'rollback_db_restore'     => 'Full rollback — also restore the database to before the update',
    'rollback_db_restore_hint' => 'Fully consistent and safe. Data entered after the update is lost (but kept in the pre-rollback backup).',
    'rollback_db_keep'        => 'Code only — leave the database untouched',
    'rollback_db_keep_hint'   => 'New data is kept. Only safe if the newer update had no destructive schema changes.',
    'rollback_confirm_button' => 'Yes, roll back',
    'rollback_done'           => 'Rolled back to version :version.',
    'rollback_failed'         => 'Rollback failed.',
    'rollback_none'           => 'No rollback point recorded (you have not updated from this panel yet).',
    'rollback_backup_note'    => 'Pre-rollback backup: :file',
];
