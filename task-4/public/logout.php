<?php
/**
 * Task 4 — logout. Destroys the session server-side, then starts a fresh one
 * purely so the confirmation message can be shown.
 */
declare(strict_types=1);

require_once __DIR__ . '/init.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'] ?? 'Lax',
    ]);
}

session_destroy();
session_start();
session_regenerate_id(true);
flash('success', 'You have been signed out.');
redirect('login.php');