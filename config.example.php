<?php
// Copy to config.local.php for a native PHP installation. Environment wins.
return [
    'APP_ENV' => 'development', // The website remains a fictional-data portfolio in both environments.
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'NealleDB',
    'DB_USER' => 'mae',
    'DB_PASSWORD' => '',
    'APP_BASE_PATH' => '', // Example: /mae if installed in a subdirectory.
    'COOKIE_SECURE' => '0', // Production requires 1, an HTTPS APP_ORIGIN, and APP_KEY.
    'APP_ORIGIN' => '',
    'TRUSTED_PROXIES' => '', // Exact edge IPs/CIDRs only.
    'HSTS_ENABLED' => '0', // Enable after verifying HTTPS/domain ownership.
    // APP_KEY and DB_PASSWORD also support _FILE environment variables.
    // Development creates a private key outside the checkout; production requires a configured key.
];
