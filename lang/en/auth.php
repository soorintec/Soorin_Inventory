<?php

/*
| Login, logout and authentication messages — this system has internal users only.
*/

return [
    'failed'    => 'These credentials do not match any account.',
    'password'  => 'The provided password is incorrect.',
    'throttle'  => 'Too many login attempts. Please try again in :seconds seconds.',
    'inactive'  => 'Your account is inactive.',

    'login'           => 'Sign in',
    'login_action'    => 'Sign in',
    'logout'          => 'Log out',
    'identifier'      => 'Email or mobile number',
    'password_field'  => 'Password',
    'remember_me'     => 'Remember me',

    'panel_title' => 'Warehouse Admin Panel',

    'role'        => 'Role',
    'user_type'   => 'User type',
    'types'       => [
        'admin' => 'Administrator',
        'staff' => 'Warehouse staff',
    ],

    'last_login_at' => 'Last login',
    'last_login_ip' => 'Last login IP',
];
