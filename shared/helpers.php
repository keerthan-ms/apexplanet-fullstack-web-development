<?php
/**
 * Shared helpers: validation, CSRF, escaping, flash messages, auth guards.
 */

declare(strict_types=1);

/* ---------------------------------------------------------------- CSRF --- */

/** Session-bound CSRF token (per-session, rotated on login). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Abort the request unless the submitted token matches the session token. */
function csrf_require(): void
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('CSRF token mismatch. Reload the page and try again.');
    }
}

/* ----------------------------------------------------------- Escaping ---- */

/** Escape for safe HTML output. Use on every interpolated value. */
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape for a JavaScript string / JSON context. */
function json_out($data): string
{
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/* ------------------------------------------------------------- Input ----- */

/** Trimmed POST value as string. */
function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function post_int(string $key, int $default = 0): int
{
    return isset($_POST[$key]) && is_scalar($_POST[$key]) ? (int) $_POST[$key] : $default;
}

/** JSON response and stop. */
function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

/* ------------------------------------------------------- Validation ----- */

/**
 * Validate an email address.
 * @return string|null Normalised email, or null when invalid.
 */
function valid_email(string $email): ?string
{
    $email = trim($email);
    if ($email === '' || strlen($email) > 254) {
        return null;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return strtolower($email);
}

/**
 * Validate a username: 3-32 chars, letters/digits/underscore/dot.
 */
function valid_username(string $u): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_.]{3,32}$/', $u);
}

/**
 * Enforce a password policy and return the list of failures.
 * Minimum 8 chars with at least one letter and one digit.
 * @return string[] Human readable problems; empty means valid.
 */
function password_problems(string $p): array
{
    $problems = [];
    if (strlen($p) < 8)          { $problems[] = 'must be at least 8 characters'; }
    if (strlen($p) > 200)        { $problems[] = 'must be at most 200 characters'; }
    if (!preg_match('/[A-Za-z]/', $p)) { $problems[] = 'must contain a letter'; }
    if (!preg_match('/\d/', $p))       { $problems[] = 'must contain a digit'; }
    return $problems;
}

/* ------------------------------------------------------- Flash / auth ---- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return array<int,array{type:string,message:string}> */
function flash_take(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_user_role(): string
{
    return (string) ($_SESSION['role'] ?? 'user');
}

function is_admin(): bool
{
    return current_user_role() === 'admin';
}

/** Redirect and stop. */
function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/** Send the visitor to login, remembering where they wanted to go. */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '/';
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
}

/** Send the visitor away unless they hold the admin role. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        flash('error', 'Administrator access required.');
        redirect('index.php');
    }
}

/* ------------------------------------------------------- View helpers --- */

/** Build a URL for a page inside a task's public dir. */
function url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

/**
 * Render the opening document, header and navigation.
 *
 * @param array<int,array{0:string,1:string}>|null $nav [href => label] pairs.
 *                                             Defaults to the Task 4/5 set.
 */
function render_header(string $title, bool $withNav = true, ?array $nav = null): void
{
    $nav ??= [
        'index.php'       => 'Home',
        'products.php'    => 'Catalogue',
        'orders.php'      => 'My Orders',
        'admin/index.php' => 'Admin',
        'profile.php'     => 'Profile',
    ];
    // A page may declare additional stylesheets before rendering the header.
    $extraCss = $GLOBALS['APEX_EXTRA_CSS'] ?? [];
    $safeTitle = e($title);
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeTitle} · ApexPlanet</title>
<link rel="stylesheet" href="assets/css/style.css">
HTML;
    foreach ($extraCss as $href) {
        echo '<link rel="stylesheet" href="' . e((string) $href) . '">' . "\n";
    }
    echo "</head>\n<body>\n";
    echo '<a class="skip-link" href="#main">Skip to main content</a>' . "\n";

    if ($withNav) {
        $here = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
        echo '<header class="site-header"><nav class="nav wrap" aria-label="Primary">';
        echo '<a class="brand" href="index.php">ApexPlanet</a>';
        echo '<button class="nav-toggle" aria-expanded="false" aria-controls="navlist">Menu</button>';
        echo '<ul id="navlist" class="nav-list">';
        foreach ($nav as $href => $label) {
            $active = $here === basename($href) ? ' class="active" aria-current="page"' : '';
            echo '<li><a href="' . e($href) . '"' . $active . '>' . e($label) . '</a></li>';
        }
        if (is_logged_in()) {
            echo '<li class="who">' . e($_SESSION['username'] ?? 'User');
            echo ' <span class="role role-' . e(current_user_role()) . '">' . e(current_user_role()) . '</span></li>';
            echo '<li><a href="logout.php">Log out</a></li>';
        } else {
            echo '<li><a href="login.php">Log in</a></li>';
            echo '<li><a class="btn btn-sm" href="register.php">Register</a></li>';
        }
        echo '</ul></nav></header>';
    }
    echo '<main id="main" class="wrap">';
}

function render_footer(): void
{
    echo '</main>';
    echo '<footer class="site-footer"><div class="wrap">';
    echo '<p>ApexPlanet Software Pvt. Ltd. — Full Stack Web Development Internship.</p>';
    echo '</div></footer>';
    echo '<script src="assets/js/app.js" defer></script>';
    echo '</body></html>';
}

/** Render and clear flash messages. */
function render_flashes(): void
{
    foreach (flash_take() as $f) {
        echo '<div class="alert alert-' . e($f['type']) . '" role="status">' . e($f['message']) . '</div>';
    }
}
