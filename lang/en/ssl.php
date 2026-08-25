<?php

return [
    'nav_group' => 'Management & Reports',
    'label'     => 'SSL',
    'title'     => 'Security certificate (SSL / HTTPS)',

    'intro' => 'With SSL, the connection between the browser and the system is encrypted and the address opens with "https" and a green lock. There are two modes; choose the one that fits your server.',

    // mode guide
    'guide_title'   => 'Which mode for me?',
    'guide_local'   => 'Internal server (local network): if the system is only opened inside the company network (e.g. 192.168.x.x), get a "self-signed" certificate. HTTPS is enabled, but because the server signed the certificate itself, the browser shows a "connection is not secure" warning the first time; click "Continue / Advanced → Proceed". This warning is normal and, since the network is internal, it is safe.',
    'guide_public'  => 'Public server (with a domain): if the system is reachable on the internet with a domain (e.g. anbar.yoursite.com), get a free "Let\'s Encrypt" certificate. This certificate is valid and the browser shows no warning. Requirement: the domain must point to this server\'s IP and ports 80 and 443 must reach the server from the internet.',

    // status
    'status_title'    => 'Current status',
    'status_mode'     => 'Mode',
    'mode_none'       => 'No SSL (http only)',
    'mode_self'       => 'self-signed (internal server)',
    'mode_le'         => 'Let\'s Encrypt (valid)',
    'status_domain'   => 'Domain/name',
    'status_expiry'   => 'Valid until',
    'status_force'    => 'Force HTTPS',
    'status_renew'    => 'Auto-renewal',
    'on'              => 'On',
    'off'             => 'Off',
    'yes'             => 'Enabled',
    'no'              => 'Disabled',

    // helper not installed
    'helper_missing_title' => 'The SSL helper is not installed on the server',
    'helper_missing_body'  => 'To manage certificates automatically, run this command once on the server (as root); then this page becomes active:',
    'helper_missing_note'  => 'This feature only works on the installed Linux server (not on a Windows development environment).',

    // self-signed action
    'self_action'   => 'Issue self-signed certificate',
    'self_heading'  => 'Self-signed certificate for an internal server',
    'self_cn'       => 'Server name or IP',
    'self_cn_hint'  => 'What you type in the browser; e.g. 192.168.1.36 or anbar.local',
    'self_done'     => 'Self-signed certificate issued and applied. The system now opens with https (the browser warns the first time; accept it).',

    // Let's Encrypt action
    'le_action'   => 'Issue Let\'s Encrypt certificate',
    'le_heading'  => 'Valid Let\'s Encrypt certificate (public server)',
    'le_domain'   => 'Domain',
    'le_domain_hint' => 'The domain that points to this server; e.g. anbar.yoursite.com (no http, no /)',
    'le_email'    => 'Email',
    'le_email_hint' => 'For expiry alerts from Let\'s Encrypt; provide a valid email.',
    'le_done'     => 'A valid certificate was issued and applied. Automatic renewal every 90 days is also enabled.',
    'le_warning'  => 'The domain must already point to this server\'s IP and port 80 must be open from the internet, otherwise issuance fails.',

    // force https
    'force_on'   => 'Turn on Force HTTPS',
    'force_off'  => 'Turn off Force HTTPS',
    'force_hint' => 'When on, anyone arriving via http is automatically redirected to https.',
    'force_done' => 'Force HTTPS setting saved.',
    'force_need_cert' => 'Issue a certificate first, then turn on Force HTTPS.',

    // disable
    'disable_action'  => 'Remove SSL',
    'disable_heading' => 'Return to no-SSL mode',
    'disable_warning' => 'The system will open with http only again and the security lock is removed. Are you sure?',
    'disable_done'    => 'SSL removed; the system now opens with http only.',

    'failed' => 'SSL operation failed.',

    'renew_note' => 'Auto-renewal: for Let\'s Encrypt, the certbot service checks daily and renews the certificate before it expires (no manual action needed). The self-signed certificate is created for ten years and needs no renewal.',
];
