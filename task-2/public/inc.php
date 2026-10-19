<?php
/**
 * Task 2 bootstrap.
 *
 * Task 2 is a front-end task: pages render HTML only, and the single AJAX
 * endpoint loads the database on its own. That keeps page loads fast and means
 * the UI still renders if MySQL is unavailable.
 */

declare(strict_types=1);

define('TASK2_START', microtime(true));

/** Escape a value for safe HTML output. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Read a trimmed POST field. */
function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** Return a JSON response and stop. */
function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

/** Session-bound CSRF token, accepted from a header or the form. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $sent): bool
{
    return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// A hardened session is needed for the CSRF token to persist across AJAX calls.
//
// The configured session.save_path is only honoured when it actually exists.
// php.ini may carry a stale or relative path, so pick the first usable
// candidate and fall back to the system temp directory. Without this, a
// session_start() warning is printed before any JSON output and corrupts the
// API response.
$sessionCandidates = [];
if (ini_get('session.save_path')) {
    $sessionCandidates[] = ini_get('session.save_path');
}
// Walk up looking for the portable runtime created by tools/setup-runtime.ps1.
$sessionCandidates[] = dirname(__DIR__) . '/_runtime/php/sessions';
$sessionCandidates[] = dirname(__DIR__, 2) . '/_runtime/php/sessions';
$sessionCandidates[] = dirname(__DIR__, 3) . '/_runtime/php/sessions';

$sessionDir = '';
foreach ($sessionCandidates as $candidate) {
    if (!is_string($candidate) || $candidate === '') {
        continue;
    }
    if (is_dir($candidate)) { $sessionDir = $candidate; break; }
    if (@mkdir($candidate, 0o777, true) && is_dir($candidate)) { $sessionDir = $candidate; break; }
}
if ($sessionDir !== '') {
    session_save_path($sessionDir);
}

if (session_status() === PHP_SESSION_NONE) {
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

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
