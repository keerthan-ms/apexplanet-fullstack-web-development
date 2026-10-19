<?php
/**
 * Task 3 â€” logout.
 *
 * Clears the session server-side rather than only deleting the cookie, then
 * destroys the session and regenerates a CSRF token for the next page.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();

// Start a fresh session purely so the confirmation message can be shown.
session_start();
session_regenerate_id(true);
flash('success', 'You have been signed out.');
redirect('login.php');
