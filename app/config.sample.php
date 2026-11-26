<?php
/*
 * Copy this file to app/config.php and fill in your values.
 * On Hostinger: hPanel → Databases → MySQL Databases gives you the name, user and password
 * (the host is usually "localhost").
 */
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'u000000000_morphora',
        'user'    => 'u000000000_morphora',
        'pass'    => 'change-me',
        'charset' => 'utf8mb4',
    ],
    // The site's public address, no trailing slash. Used for canonical URLs, the sitemap and emails.
    'base_url'    => 'https://morphoraspaces.com',
    // Redirect http:// and www. to the canonical address (keep on in production).
    'force_canonical_host' => true,
    // Sender for notification emails. Must be an address on your own domain (create it in hPanel → Emails).
    'mail_from'   => 'no-reply@morphoraspaces.com',
    'timezone'    => 'Asia/Kolkata',
    // Show PHP errors in the browser. Keep false in production.
    'debug'       => false,
];
