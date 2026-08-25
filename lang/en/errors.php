<?php

/*
| Error pages — branded, not Laravel's default text.
*/

return [
    'back_home'  => 'Back to home',
    'back'       => 'Back to previous page',
    'code'       => 'Error code',

    '403' => [
        'title' => 'Access denied',
        'body'  => 'You are not allowed to view this page. If you think this is a mistake, contact the system administrator.',
    ],
    '404' => [
        'title' => 'Page not found',
        'body'  => 'The address you were looking for does not exist or has moved.',
    ],
    '419' => [
        'title' => 'Your session expired',
        'body'  => 'Your session was closed due to inactivity. Please sign in again.',
    ],
    '429' => [
        'title' => 'Too many requests',
        'body'  => 'Too many requests were sent in a short time. Please wait and try again.',
    ],
    '500' => [
        'title' => 'Internal server error',
        'body'  => 'Something went wrong on the server. The issue has been logged and will be reviewed. Please try again later.',
    ],
    '503' => [
        'title' => 'Under maintenance',
        'body'  => 'An update is in progress. Please check back in a few minutes.',
    ],
];
