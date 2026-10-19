<?php
/**
 * Single entry point for every PHP page in tasks 2-5.
 *
 * Requiring this file gives a page: config, Database, helpers, a hardened
 * session, CSRF tokens, and consistent error handling.
 */

declare(strict_types=1);

define('APEX_START', microtime(true));

if (!is_file(__DIR__ . '/config.php')) {
    http_response_code(500);
    exit("Missing shared/config.php. Copy shared/config.example.php to shared/config.php first.");
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

$APEX_CONFIG = require __DIR__ . '/config.php';

date_default_timezone_set($APEX_CONFIG['app']['timezone']);

ini_set('display_errors', $APEX_CONFIG['app']['debug'] ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/**
 * Resolve a usable session directory.
 *
 * A relative or stale session.save_path in php.ini makes session_start() emit
 * a warning, which corrupts any JSON response a page goes on to emit. Pick the
 * first candidate that exists (creating it if needed) and otherwise defer to
 * the system temp directory.
 */
function apex_session_path(string $scriptDir): string
{
    $candidates = [];
    $configured = ini_get('session.save_path');
    if (is_string($configured) && $configured !== '') {
        $candidates[] = $configured;
    }
    $candidates[] = $scriptDir . '/_runtime/php/sessions';
    $candidates[] = dirname($scriptDir) . '/_runtime/php/sessions';
    $candidates[] = dirname($scriptDir, 2) . '/_runtime/php/sessions';

    foreach ($candidates as $candidate) {
        if ($candidate === '' || str_contains($candidate, "\0")) {
            continue;
        }
        if (is_dir($candidate)) {
            return $candidate;
        }
        if (@mkdir($candidate, 0o777, true) && is_dir($candidate)) {
            return $candidate;
        }
    }
    return sys_get_temp_dir();
}

/**
 * Hardened session. HttpOnly + SameSite guard against the most common session
 * attacks; the CSRF token in helpers.php covers cross-site request forgery.
 */
if (session_status() === PHP_SESSION_NONE) {
    $sessionDir = apex_session_path(dirname(__DIR__));
    session_save_path($sessionDir);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_name('APEXSID');
    session_start();
}

/**
 * Enforce HTTPS when the deployment provides it, so credentials and session
 * cookies are never sent in clear text.
 */
if (!empty($_SERVER['HTTPS']) && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) !== 'https') {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

if (!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
