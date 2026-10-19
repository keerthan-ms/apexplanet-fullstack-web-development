<?php
/**
 * Shared configuration for ApexPlanet Full Stack Web Development tasks 2-5.
 *
 * Copy to `config.php` and edit for your machine. `config.php` is gitignored
 * because it holds local database credentials.
 *
 * Connection details are also read from environment variables when present so
 * the same code runs on 000webhost / InfinityFree without code changes:
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 */

declare(strict_types=1);

return [
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => (int) (getenv('DB_PORT') ?: 3306),
        'name'    => getenv('DB_NAME') ?: 'apexplanet_fullstack',
        'user'    => getenv('DB_USER') ?: 'apexplanet',
        // No default password on purpose. tools/setup-runtime.ps1 generates a
        // random one and writes it into the gitignored shared/config.php.
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],

    // Application settings
    'app' => [
        'name'     => 'ApexPlanet Full Stack',
        'timezone' => 'Asia/Kolkata',
        // Leave empty to auto-detect from the request.
        'base_url' => getenv('APP_BASE_URL') ?: '',
        'debug'    => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOL),
    ],

    'upload' => [
        // Stored OUTSIDE the public web root; served through download.php.
        'dir'          => dirname(__DIR__) . '/task-3/storage/uploads',
        'public_route' => 'task-3/public/download.php',
        'max_bytes'    => 2 * 1024 * 1024, // 2 MB
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    ],

    'security' => [
        // Used by Task 5 email OTP. Leave empty to run in "console" mode,
        // where the OTP is written to the server log instead of sent by email.
        'mail_transport' => getenv('MAIL_TRANSPORT') ?: 'console',
        'mail_from'      => getenv('MAIL_FROM') ?: 'no-reply@apexplanet.local',
        'otp_ttl'        => 600, // seconds
    ],
];
