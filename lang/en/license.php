<?php

return [
    'nav_group' => 'Management & Reports',
    'label'     => 'License',
    'title'     => 'Product license',
    'intro'     => 'This software is provided under license. Enter your license key for full activation.',

    // status
    'status_title'    => 'License status',
    'status_licensed' => 'Active',
    'status_unlicensed' => 'Unlicensed',
    'licensed_to'     => 'Issued to',
    'hwid'            => 'Hardware ID',
    'transferable'    => 'Transferable',
    'expires'         => 'Expiry',
    'perpetual'       => 'Perpetual',
    'this_hwid'       => 'This server\'s Hardware ID',
    'copy_hint'       => 'Copy this ID and send it to the seller.',

    // grace and lock
    'grace_title' => 'Trial period',
    'grace_left'  => ':days days left in the trial. After that, you must enter a license to continue.',
    'locked_title' => 'The trial period has ended',
    'locked_body'  => 'Enter a valid license key to continue using the system. Creating and editing are blocked until then.',
    'clock_title'  => 'System clock tampering detected',
    'clock_body'   => 'The server date has been moved back compared to the last run. To continue, set the server clock to the correct date or enter a valid license.',

    // activation
    'enter_title' => 'Enter license key',
    'key_label'   => 'License key',
    'key_hint'    => 'Paste the key you received from the seller after purchase.',
    'activate'    => 'Activate',
    'activated'   => 'License activated successfully.',
    'invalid'     => 'The license key is not valid.',
    'reasons' => [
        'no_key'         => 'No key was entered.',
        'no_public_key'  => 'This build is not configured to verify licenses (public key not set).',
        'malformed'      => 'The key format is invalid.',
        'bad_signature'  => 'The key signature is invalid (forged or tampered key).',
        'expired'        => 'This key has expired.',
        'hwid_mismatch'  => 'This key was issued for a different server (hardware).',
        'clock_tampered' => 'The server clock has been moved back.',
    ],

    // purchase
    'purchase_title' => 'Buy a license',
    'purchase_intro' => 'To get a key, send the amount to the USDT wallet below, then send the transaction receipt to the seller to have your license key issued.',
    'price'          => 'Price',
    'usdt_address'   => 'USDT wallet address',
    'usdt_network'   => 'Network',
    'contact'        => 'Contact the seller',
    'no_payment_info'=> 'Payment information has not been set by the seller yet.',
    'steps_title'    => 'Purchase steps',
    'step_pay'       => 'Send the amount to the wallet address above (on the stated network).',
    'step_send'      => 'Send the transaction hash/receipt plus this server\'s Hardware ID (shown above) to the seller.',
    'step_receive'   => 'Receive your license key and enter it on this page.',

    // about & support
    'about_title'  => 'About & Support',
    'about_body'   => 'This system was built with a lot of effort by an independent developer to make warehouse management simple, fast and hassle-free. We hope you find it useful.',
    'pricing_note' => 'The first six months are completely free with no limits. After that, a one-time payment of just 5 USDT activates it permanently — pay once, keep forever.',
    'donate_note'  => 'This price is intentionally small. If the app is valuable to you and you would like to support its continued development, feel free to send any amount to the same address. Thank you for your kindness. 🙏',
];
