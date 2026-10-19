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

/** True when the supplied token matches the session token. */
function csrf_ok(?string $sent): bool
{
    return is_string($sent)
        && $sent !== ''
        && !empty($_SESSION['csrf'])
        && hash_equals((string) $_SESSION['csrf'], $sent);
}

/** Abort the request unless the submitted token matches the session token. */
function csrf_require(): void
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!csrf_ok(is_string($sent) ? $sent : null)) {
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
/**
 * Encode a value for safe embedding inside a <script> block.
 *
 * Named json_safe (not json_out) deliberately: json_out() is the responder
 * that writes a JSON response and stops. Having one name mean both made a real
 * endpoint silently emit nothing.
 */
function json_safe($data): string
{
    return (string) json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
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

/**
 * Write a JSON response and stop.
 *
 * JSON_INVALID_UTF8_SUBSTITUTE guarantees a body is always produced: without
 * it, one bad byte anywhere in the payload makes json_encode() return false and
 * the response is silently empty with a 200 status.
 */
function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $body = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($body === false) {
        $body = (string) json_encode(
            ['error' => 'Response could not be encoded.'],
            JSON_UNESCAPED_SLASHES
        );
        http_response_code(500);
    }

    echo $body;
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

/**
 * URL prefix for the current task's document root.
 *
 * A page in a subdirectory (for example admin/index.php) must redirect to
 * /login.php, not to /admin/login.php. A bare relative path breaks, so each
 * task registers its own document root and redirects are built from that.
 */
function apex_register_base(string $docroot): void
{
    $GLOBALS['APEX_BASE_URL'] = '';
    $GLOBALS['APEX_DOCROOT']   = $docroot;

    $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($script === '') {
        return;
    }

    $normalise = static fn (string $p): string => str_replace('\\', '/', $p);

    $root   = $normalise(realpath($docroot) ?: $docroot);
    $target = $normalise($script);

    if ($root !== '' && str_starts_with($target, $root)) {
        $relative = substr($target, strlen($root));          // '/admin/index.php'
        $dir      = str_replace('\\', '/', dirname($relative)); // '/admin' or '/'
        $GLOBALS['APEX_BASE_URL'] = rtrim($dir, '/');         // '' or '/admin'
    }
}

function apex_base(): string
{
    return (string) ($GLOBALS['APEX_BASE_URL'] ?? '');
}

/**
 * URL for a file in the current task's document root.
 *
 * Always docroot-relative, never relative to the current script's directory:
 * from /admin/index.php, "login.php" must resolve to /login.php, not
 * /admin/login.php, which would 404.
 */
function task_url(string $path = ''): string
{
    if ($path === '') {
        return '/';
    }
    if (str_starts_with($path, '/')) {
        return $path;
    }
    return '/' . ltrim($path, '/');
}

/** Redirect and stop. Paths are resolved against the task root, not the cwd. */
function redirect(string $to): void
{
    // An absolute URL or a protocol-relative one is already final.
    $isExternal = str_starts_with($to, 'http://')
        || str_starts_with($to, 'https://')
        || str_starts_with($to, '//');

    header('Location: ' . ($isExternal ? $to : task_url($to)));
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
    $css = task_url('assets/css/style.css');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeTitle} · ApexPlanet</title>
<link rel="stylesheet" href="{$css}">
HTML;
    foreach ($extraCss as $href) {
        // task_url keeps the href correct from subdirectories such as /admin/.
        $href = (string) (str_starts_with((string) $href, 'http') ? $href : task_url((string) $href));
        echo '<link rel="stylesheet" href="' . e($href) . '">' . "\n";
    }
    echo "</head>\n<body>\n";
    echo '<a class="skip-link" href="#main">Skip to main content</a>' . "\n";

    if ($withNav) {
        // The path of the running script relative to this task's document root,
        // so nav keys such as 'admin/index.php' match exactly. Deriving it from
        // SCRIPT_FILENAME and the docroot keeps it correct even when the app is
        // mounted under a subdirectory; SCRIPT_NAME is the fallback when the two
        // cannot be compared (realpath mismatch, symlinks).
        $docroot    = rtrim(str_replace('\\', '/', (string) ($GLOBALS['APEX_DOCROOT'] ?? '')), '/');
        $scriptFile = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if ($docroot !== '' && $scriptFile !== '' && str_starts_with($scriptFile, $docroot . '/')) {
            $here = substr($scriptFile, strlen($docroot) + 1);
        } else {
            $here = (string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php');
        }
        $here = ltrim(str_replace('\\', '/', $here), '/');
        echo '<header class="site-header"><nav class="nav wrap" aria-label="Primary">';
        echo '<a class="brand" href="' . e(task_url('index.php')) . '">ApexPlanet</a>';
        echo '<button class="nav-toggle" aria-expanded="false" aria-controls="navlist">Menu</button>';
        echo '<ul id="navlist" class="nav-list">';
        foreach ($nav as $href => $label) {
            $active = $here === ltrim($href, '/') ? ' class="active" aria-current="page"' : '';
            echo '<li><a href="' . e(task_url($href)) . '"' . $active . '>' . e($label) . '</a></li>';
        }
        if (is_logged_in()) {
            echo '<li class="who">' . e($_SESSION['username'] ?? 'User');
            echo ' <span class="role role-' . e(current_user_role()) . '">' . e(current_user_role()) . '</span></li>';
            echo '<li><a href="' . e(task_url('logout.php')) . '">Log out</a></li>';
        } else {
            echo '<li><a href="' . e(task_url('login.php')) . '">Log in</a></li>';
            echo '<li><a class="btn btn-sm" href="' . e(task_url('register.php')) . '">Register</a></li>';
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
    echo '<script src="' . e(task_url('assets/js/app.js')) . '" defer></script>';
    echo '</body></html>';
}

/** Render and clear flash messages. */
function render_flashes(): void
{
    foreach (flash_take() as $f) {
        echo '<div class="alert alert-' . e($f['type']) . '" role="status">' . e($f['message']) . '</div>';
    }
}
