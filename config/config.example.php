<?php
/**
 * Template config. Copy to config/config.php (gitignored) and fill in real values.
 * config.php is loaded by public/index.php at boot via Kuko\App::bootstrap().
 */

return [
    'app' => [
        'env'   => 'production',       // production | dev
        'debug' => false,              // set true only for local dev
        'url'   => 'https://kuko-detskysvet.sk',
        'tz'    => 'Europe/Bratislava',
    ],

    'db' => [
        'host'    => 'localhost',
        'name'    => 'kuko',
        'user'    => '',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'mail' => [
        // WebSupport SMTP — fill from mailbox settings
        'host'       => 'smtp.websupport.sk',
        'port'       => 465,
        'encryption' => 'ssl',          // ssl | tls
        'user'       => 'info@kuko-detskysvet.sk',
        'pass'       => '',
        'from_email' => 'info@kuko-detskysvet.sk',
        'from_name'  => 'KUKO detský svet',
        'admin_to'   => 'info@kuko-detskysvet.sk',
    ],

    'auth' => [
        // Used for hashing session tokens, CSRF, etc. Generate with: openssl rand -hex 32
        'secret' => '',
    ],

    'recaptcha' => [
        // Google reCAPTCHA v3 — create at https://www.google.com/recaptcha/admin/create
        'site_key'   => '',
        'secret_key' => '',
        'min_score'  => 0.5,
    ],

    'admin' => [
        'session_lifetime' => 3600,
    ],

    'security' => [
        // Generate with: openssl rand -hex 32
        'ip_hash_secret'      => '',
        'rate_limit_per_hour' => 3,
        'csrf_lifetime'       => 3600,
    ],

    'social' => [
        'facebook'  => 'https://www.facebook.com/profile.php?id=61587744202735',
        'instagram' => 'https://www.instagram.com/kuko.detskysvet',
    ],
];
