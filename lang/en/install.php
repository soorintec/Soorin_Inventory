<?php

return [
    'title'       => 'Install the Warehouse System',
    'heading'     => 'Install the Warehouse System',
    'subheading'  => 'For the first run, enter the database details and the admin user.',

    'db_section'  => '1) Database connection',
    'db_hint'     => 'Get these from your hosting control panel (an empty database and its user).',
    'db_host'     => 'Database host',
    'db_port'     => 'Port',
    'db_name'     => 'Database name',
    'db_user'     => 'Database username',
    'db_pass'     => 'Database password',
    'app_url'     => 'System address (APP URL)',
    'app_url_hint'=> 'Usually the same address you have open right now is correct.',

    'admin_section'  => '2) Admin user (first login)',
    'admin_name'     => 'Admin name',
    'admin_name_default' => 'System Administrator',
    'admin_email'    => 'Login email',
    'admin_password' => 'Password (at least 8 characters)',

    'submit'  => 'Install',
    'note'    => 'Clicking this button: tests the database connection, creates the tables, creates the default warehouse and admin user, then locks this install page; the next refresh shows the app itself.',
];
