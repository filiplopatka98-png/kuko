<?php
/**
 * Template config. Copy to config/config.php (gitignored) and fill in real values.
 * config.php is loaded by public/index.php at boot via Kuko\App::bootstrap().
 */

return [
    'app' => [
        'env'   => 'production',       // production | dev
        'debug' => false,              // set true only for local dev
        'url'   => 'https://kukodetskysvet.sk',
        'tz'    => 'Europe/Bratislava',
        // Maintenance mode: when true, public visitors see a branded "we're updating" page
        // until they enter the staff password. Admin remains accessible.
        'maintenance'          => false,
        'maintenance_password' => '',  // plaintext password for the staff bypass form
        // Indexing flag: when true, robots.txt allows all + meta robots is index,follow.
        // When false (default during pre-launch), robots.txt disallows everything and
        // every page emits noindex,nofollow. Flip to true ONLY after final go-live.
        'public_indexing'      => false,
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
        'user'       => 'info@kukodetskysvet.sk',
        'pass'       => '',
        'from_email' => 'info@kukodetskysvet.sk',
        'from_name'  => 'KUKO detský svet',
        'admin_to'   => 'info@kukodetskysvet.sk',
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
        // Admin session timeouts (seconds). idle = drop after inactivity;
        // absolute = hard cap regardless of activity. Remember-me cookie is
        // 30 days and re-establishes a session past these limits.
        'idle_timeout'     => 8 * 3600,
        'absolute_timeout' => 24 * 3600,
    ],

    'security' => [
        // Generate with: openssl rand -hex 32
        'ip_hash_secret'      => '',
        'rate_limit_per_hour' => 3,
        'csrf_lifetime'       => 3600,
        // Only enable if the app sits behind a reverse proxy that rewrites
        // REMOTE_ADDR to its own IP. When true, the client IP is taken from the
        // first hop of X-Forwarded-For. Leave false unless you control the proxy
        // (the header is attacker-spoofable otherwise).
        'trust_proxy'         => false,
    ],

    'social' => [
        'facebook'  => 'https://www.facebook.com/profile.php?id=61587744202735',
        'instagram' => 'https://www.instagram.com/kuko.detskysvet',
    ],
];
