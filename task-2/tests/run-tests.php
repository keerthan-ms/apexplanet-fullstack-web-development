<?php
/**
 * Task 2 — automated test suite.
 *
 * Run from the repository root:
 *     npm run test:2
 *   or directly:
 *     php task-2/tests/run-tests.php
 *
 * Exit code 0 = all checks passed.
 */

declare(strict_types=1);

define('ROOT', dirname(__DIR__, 2));

$passed = 0; $failed = 0; $skipped = 0; $failures = [];

function section(string $t): void { echo "\n\033[1m{$t}\033[0m\n"; }

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed, $failures;
    if ($ok) {
        $passed++;
        echo "  \033[32mPASS\033[0m  {$label}\n";
    } else {
        $failed++;
        $failures[] = $label . ($detail !== '' ? " — {$detail}" : '');
        echo "  \033[31mFAIL\033[0m  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function skipTest(string $label, string $why): void
{
    global $skipped;
    $skipped++;
    echo "  \033[33mSKIP\033[0m  {$label} — {$why}\n";
}

$read = static fn (string $rel): string => is_file(ROOT . '/' . $rel)
    ? (string) file_get_contents(ROOT . '/' . $rel) : '';

/* --------------------------------------------------------------- 1 --- */
section('1. Required files');

foreach ([
    'task-2/public/index.php',
    'task-2/public/login.php',
    'task-2/public/register.php',
    'task-2/public/components.php',
    'task-2/public/inc.php',
    'task-2/public/api/check-availability.php',
    'task-2/public/partials/header.php',
    'task-2/public/partials/navbar.php',
    'task-2/public/partials/footer.php',
    'task-2/public/assets/css/theme.css',
    'task-2/public/assets/js/forms.js',
    'task-2/README.md',
    'vendor-assets/bootstrap-5.3.3/css/bootstrap.min.css',
    'vendor-assets/bootstrap-5.3.3/js/bootstrap.bundle.min.js',
] as $rel) {
    check("exists {$rel}", is_file(ROOT . '/' . $rel));
}

/* --------------------------------------------------------------- 2 --- */
section('2. Bootstrap 5 requirements');

$nav = $read('task-2/public/partials/navbar.php');
$cmp = $read('task-2/public/components.php');
$css = $read('task-2/public/assets/css/theme.css');

check('navbar uses navbar-expand-md collapse', str_contains($nav, 'navbar-expand-md') && str_contains($nav, 'navbar-collapse'));
check('navbar uses the responsive toggler', str_contains($nav, 'navbar-toggler'));
check('navbar uses d-none d-md-block utility', str_contains($nav, 'd-none d-md-block'));
check('navbar uses d-md-none utility', str_contains($nav, 'd-md-none'));
check('components page renders a carousel', str_contains($cmp, 'carousel') && str_contains($cmp, 'carousel-control-prev'));
check('components page renders cards', str_contains($cmp, 'apex-card'));
check('components page renders a modal', str_contains($cmp, 'modal fade') && str_contains($cmp, 'data-bs-toggle="modal"'));
check('components page renders buttons', str_contains($cmp, 'btn-apex') && str_contains($cmp, 'btn-ghost'));
check('components page shows the grid breakpoints', str_contains($cmp, 'col-12 col-md-6 col-lg-4'));
check('theme defines a custom palette', str_contains($css, '--apex-indigo') && str_contains($css, '--apex-violet'));
check('theme overrides Bootstrap variables', str_contains($css, '--bs-primary'));
check('theme enables smooth scrolling', str_contains($css, 'scroll-behavior: smooth'));
check('theme defines hover effects', str_contains($css, ':hover'));
check('theme honours prefers-reduced-motion', str_contains($css, 'prefers-reduced-motion'));

// Fonts, icons and the stylesheet are declared once in the shared header
// partial, so that is where they are asserted. Rendering is verified
// separately in section 6.
$header = $read('task-2/public/partials/header.php');
check('header uses Google Fonts', str_contains($header, 'fonts.googleapis.com'));
check('header uses Bootstrap Icons', str_contains($header, 'bootstrap-icons'));
check('header loads the vendored Bootstrap CSS', str_contains($header, 'bootstrap.min.css'));
check('header loads the ApexPlanet theme', str_contains($header, 'assets/css/theme.css'));
check('footer loads the vendored Bootstrap JS', str_contains($read('task-2/public/partials/footer.php'), 'bootstrap.bundle.min.js'));

foreach (['login.php', 'register.php', 'components.php', 'index.php'] as $page) {
    check("{$page} includes the shared header", str_contains($read('task-2/public/' . $page), 'partials/header.php'));
}

/* --------------------------------------------------------------- 3 --- */
section('3. Reusable components');

$header = $read('task-2/public/partials/header.php');
$footer = $read('task-2/public/partials/footer.php');
check('header pulls in the navbar partial', str_contains($header, "partials/navbar.php") || str_contains($header, "navbar.php"));
check('footer is a separate reusable partial', str_contains($footer, 'apex-footer'));
foreach (['index.php', 'login.php', 'register.php', 'components.php'] as $page) {
    check("{$page} includes the shared header", str_contains($read('task-2/public/' . $page), 'partials/header.php'));
    check("{$page} includes the shared footer", str_contains($read('task-2/public/' . $page), 'partials/footer.php'));
}

/* --------------------------------------------------------------- 4 --- */
section('4. JavaScript form behaviour');

$js = $read('task-2/public/assets/js/forms.js');
check('implements show/hide password toggle', str_contains($js, 'data-toggle-password') && str_contains($js, 'bi-eye-slash'));
check('implements password match check', str_contains($js, 'Passwords match') && str_contains($js, 'confirm_password'));
check('implements password strength meter', str_contains($js, 'passwordStrength') && str_contains($js, 'strength-fill'));
check('implements the AJAX availability check', str_contains($js, 'fetch(') && str_contains($js, 'check-availability.php'));
check('debounces the AJAX request', str_contains($js, 'DEBOUNCE_MS'));
check('validates login and register forms', str_contains($js, 'data-validate'));
check('uses DOM APIs required by the brief', str_contains($js, 'querySelector') && str_contains($js, 'addEventListener'));
check('handles click, keyup-equivalent and change events', str_contains($js, "'input'") && str_contains($js, "'change'"));

$reg = $read('task-2/public/register.php');
check('register page has username field', str_contains($reg, 'name="username"'));
check('register page has password and confirm fields', str_contains($reg, 'name="password"') && str_contains($reg, 'name="confirm_password"'));
check('register page has a select element', str_contains($reg, '<select'));
check('register page has checkbox inputs', substr_count($reg, 'type="checkbox"') >= 2);
check('register page wires the AJAX check', str_contains($reg, 'data-ajax-check="username"') && str_contains($reg, 'data-ajax-check="email"'));

$login = $read('task-2/public/login.php');
check('login page has email and password', str_contains($login, 'name="email"') && str_contains($login, 'name="password"'));

/* --------------------------------------------------------------- 5 --- */
section('5. Server-side validation and CSRF');

$inc  = $read('task-2/public/inc.php');
$lg   = $read('task-2/public/login.php');
$rg   = $read('task-2/public/register.php');
$api  = $read('task-2/public/api/check-availability.php');

check('helpers escape output (e())', str_contains($inc, 'htmlspecialchars'));
check('session cookie is HttpOnly', str_contains($inc, "'httponly' => true"));
check('session cookie is SameSite', str_contains($inc, "'samesite' => 'Lax'"));
check('login validates the email server-side', str_contains($lg, 'FILTER_VALIDATE_EMAIL'));
check('login validates the password length server-side', str_contains($lg, 'strlen($password) < 8'));
check('login enforces a CSRF token', str_contains($lg, 'csrf_check'));
check('register validates the username server-side', str_contains($rg, 'preg_match'));
check('register enforces a CSRF token', str_contains($rg, 'csrf_check'));
check('register requires the terms checkbox', str_contains($rg, "\$_POST['terms']"));
check('API rejects non-GET methods', str_contains($api, "'GET'") && str_contains($api, '405'));
check('API allow-lists the field name', str_contains($api, "in_array(\$field, ['username', 'email']"));
check('API uses a prepared statement', str_contains($api, 'prepare(') && str_contains($api, 'execute(['));
check('API sends Cache-Control: no-store', str_contains($api, 'no-store'));
check('API escapes identifiers allow-list style', str_contains($api, "\$column = \$field === 'username' ? 'username' : 'email'"));

/* --------------------------------------------------------------- 6 --- */
section('6. AJAX endpoint behaviour');

$php = defined('APEX_PHP') ? APEX_PHP : PHP_BINARY;
$port = 8098;
$docroot = ROOT . '/task-2/public';

$server = @proc_open(
    [$php, '-S', "127.0.0.1:{$port}", '-t', $docroot],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);

if (!is_resource($server)) {
    skipTest('AJAX endpoint', 'could not start php -S');
} else {
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $ready = false;
    for ($i = 0; $i < 60; $i++) {
        usleep(150000);
        $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.4);
        if ($probe) { fclose($probe); $ready = true; break; }
    }

    if (!$ready) {
        skipTest('AJAX endpoint', "server not ready on port {$port}");
    } else {
        $get = static function (string $url) {
            $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
            $body = @file_get_contents($url, false, $ctx);
            return ['body' => (string) $body, 'json' => json_decode((string) $body, true)];
        };

        // Pages render, and the shared header really injects the assets
        foreach (['/', '/index.php', '/login.php', '/register.php', '/components.php'] as $route) {
            $r = $get("http://127.0.0.1:{$port}{$route}");
            $ok = $r['body'] !== '' && str_contains($r['body'], '</html>');
            check("GET {$route}", $ok, $ok ? '' : 'empty or unterminated output');
            if ($ok) {
                check("{$route} renders Google Fonts", str_contains($r['body'], 'fonts.googleapis.com'));
                check("{$route} renders the theme", str_contains($r['body'], 'theme.css'));
                check("{$route} declares lang", str_contains($r['body'], '<html lang="en"'));
                check("{$route} sets a viewport", str_contains($r['body'], 'name="viewport"'));
                check("{$route} provides a skip link", str_contains($r['body'], 'Skip to main content'));
            }
        }

        // Assets
        foreach (['/assets/css/theme.css', '/assets/js/forms.js'] as $route) {
            $r = $get("http://127.0.0.1:{$port}{$route}");
            check("GET {$route}", $r['body'] !== '');
        }

        // The vendored Bootstrap path is relative to the page, so verify the
        // file it points at exists on disk.
        check('vendored bootstrap CSS exists on disk',
              is_file(ROOT . '/vendor-assets/bootstrap-5.3.3/css/bootstrap.min.css'));
        check('vendored bootstrap JS exists on disk',
              is_file(ROOT . '/vendor-assets/bootstrap-5.3.3/js/bootstrap.bundle.min.js'));

        // API contract
        $r = $get("http://127.0.0.1:{$port}/api/check-availability.php?field=username&value=keerthan.m");
        check('API returns JSON for a free username',
              is_array($r['json']) && array_key_exists('available', $r['json']));

        // "demo" is seeded into the users table by the Task 1 schema, so this
        // exercises the real prepared-statement path rather than the fallback.
        $r = $get("http://127.0.0.1:{$port}/api/check-availability.php?field=username&value=demo");
        check('API reports a registered username as unavailable',
              is_array($r['json']) && ($r['json']['available'] === false),
              json_encode($r['json']));

        $r = $get("http://127.0.0.1:{$port}/api/check-availability.php?field=username&value=admin");
        check('API responds for an unused username',
              is_array($r['json']) && array_key_exists('available', $r['json']));

        $r = $get("http://127.0.0.1:{$port}/api/check-availability.php?field=email&value=demo@example.com");
        check('API reports a registered email as unavailable',
              is_array($r['json']) && ($r['json']['available'] === false));

        $r = $get("http://127.0.0.1:{$port}/api/check-availability.php?field=email&value=not-an-email");
        check('API rejects a malformed email',
              is_array($r['json']) && ($r['json']['available'] === false));

        $r = $get("http://127.0.0.1:{$port}/api/check-availability.php?field=role&value=x");
        check('API rejects an unknown field',
              is_array($r['json']) && isset($r['json']['error']));

        // SQL injection attempt through the query string must be harmless
        $r = $get("http://127.0.0.1:{$port}/api/check-availability.php?field=username&value=" . rawurlencode("' OR '1'='1"));
        check('API survives a quote-heavy value',
              is_array($r['json']) && !isset($r['json']['error']));

        // Method guard
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'content' => '', 'timeout' => 15, 'ignore_errors' => true,
        ]]);
        $body = (string) @file_get_contents("http://127.0.0.1:{$port}/api/check-availability.php", false, $ctx);
        $json = json_decode($body, true);
        check('API rejects POST with 405', is_array($json) && isset($json['error']));
    }

    foreach ($pipes as $p) { if (is_resource($p)) fclose($p); }
    proc_terminate($server);
    proc_close($server);
}

/* --------------------------------------------------------------- 7 --- */
section('7. Accessibility and responsiveness');

$reg2 = $read('task-2/public/register.php');
$lg2  = $read('task-2/public/login.php');

check('header declares lang', str_contains($header, 'lang="en"'));
check('header sets a viewport', str_contains($header, 'name="viewport"'));
check('header provides a skip link', str_contains($header, 'Skip to main content'));
check('register page labels its inputs', substr_count($reg2, '<label') >= 7);
check('login page labels its inputs', substr_count($lg2, '<label') >= 3);
check('toggle buttons carry aria-label', str_contains($reg2, 'aria-label="Show password"'));
check('modal has aria-labelledby', str_contains($lg2, 'aria-labelledby'));
check('carousel controls are labelled', str_contains($read('task-2/public/components.php'), 'visually-hidden'));
check('alerts use role attributes', str_contains($lg2, 'role="alert"') || str_contains($lg2, 'role="status"'));
check('spinner is hidden by default with d-none', str_contains($reg2, 'spinner-border-sm') && str_contains($reg2, 'd-none'));

/* ---------------------------------------------------------- summary --- */
section('Summary');
echo "  passed : {$passed}\n  failed : {$failed}\n  skipped: {$skipped}\n";
if ($failures) {
    echo "\n  Failures:\n";
    foreach ($failures as $f) { echo "   - {$f}\n"; }
}
echo "\n" . ($failed === 0
    ? "\033[32mALL TESTS PASSED\033[0m\n"
    : "\033[31m{$failed} TEST(S) FAILED\033[0m\n");

exit($failed === 0 ? 0 : 1);
