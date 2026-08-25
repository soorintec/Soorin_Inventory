<?php

return [
    'label'      => 'User',
    'plural'     => 'Users',
    'nav_group'  => 'Management & Reports',

    'name'      => 'Name',
    'email'     => 'Email',
    'mobile'    => 'Mobile',
    'password'  => 'Password',
    'password_hint' => 'Leave empty to keep the current password.',
    'user_type' => 'Account type',
    'account'          => 'Account details',
    'permissions'      => 'Permissions',
    'permissions_hint' => 'Each checkbox is one permission. Unchecking removes that permission from this user — even an administrator. Changing the account type resets the checkboxes to that type\'s defaults, then you can adjust each one.',
    'user_type_hint'   => 'This only sets which boxes are pre-checked; the real access is the checkboxes below.',
    'active_hint'      => 'An inactive user cannot sign in at all.',

    'theme'      => 'Theme',
    'theme_hint' => 'Same sun/moon switch in the user menu; wherever you change it, it is saved here too.',
    'active'    => 'Active',

    'empty' => 'No users recorded yet.',
];
