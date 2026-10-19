<?php
/**
 * Task 1 — automated test suite.
 *
 * Run from the repository root:
 *     php task-1/tests/run-tests.php
 *
 * Or let the runner pick up the portable runtime:
 *     _runtime\php\php.exe task-1\tests\run-tests.php
 *
 * Exit code 0 = all tests passed, 1 = at least one failure.
 */

declare(strict_types=1);

define('ROOT', dirname(__DIR__, 2));

$passed = 0;
$failed = 0;
$skip   = 0;
$failures = [];

function section(string $title): void
{
    echo "\n\033[1m{$title}\033[0m\n";
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed, $skip, $failures;
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
    global $skip;
    $skip++;
    echo "  \033[33mSKIP\033[0m  {$label} — {$why}\n";
}

/* --------------------------------------------------- 1. Environment ----- */

section('1. Environment');

check(
    'PHP version is 8.0 or newer',
    PHP_VERSION_ID >= 80000,
    'found ' . PHP_VERSION
);

foreach (['mysqli', 'pdo_mysql', 'mbstring', 'json'] as $ext) {
    check("extension {$ext} is loaded", extension_loaded($ext));
}

check('mb_internal_encoding is UTF-8', mb_internal_encoding() === 'UTF-8', (string) mb_internal_encoding());

/* ------------------------------------------------- 2. Required files ---- */

section('2. Required files');

$required = [
    'task-1/public/index.html',
    'task-1/public/about.html',
    'task-1/public/projects.html',
    'task-1/public/contact.html',
    'task-1/public/fundamentals.html',
    'task-1/public/assets/css/style.css',
    'task-1/public/assets/js/main.js',
    'task-1/public/assets/js/validation.js',
    'task-1/public/assets/img/avatar.svg',
    'task-1/public/assets/media/tone.wav',
    'task-1/public/assets/media/demo.mp4',
    'task-1/public/php/index.php',
    'task-1/public/php/inc/functions.php',
    'task-1/public/mysql/index.php',
    'task-1/public/mysql/schema.sql',
    'task-1/README.md',
];
foreach ($required as $rel) {
    check("exists {$rel}", is_file(ROOT . '/' . $rel));
}

/* ---------------------------------------------------- 3. HTML content --- */

section('3. HTML5 semantics and required features');

$read = static function (string $rel): string {
    $p = ROOT . '/' . $rel;
    return is_file($p) ? (string) file_get_contents($p) : '';
};

$index  = $read('task-1/public/index.html');
$about  = $read('task-1/public/about.html');
$fund   = $read('task-1/public/fundamentals.html');
$contact = $read('task-1/public/contact.html');

foreach (['header', 'nav', 'main', 'section', 'article', 'footer'] as $tag) {
    check("index.html uses <{$tag}>", (bool) preg_match('/<' . $tag . '[\s>]/i', $index));
}
check('index.html has a viewport meta tag', str_contains($index, 'name="viewport"'));
check('index.html has a skip link', str_contains($index, 'skip-link'));
check('index.html declares lang="en"', str_contains($index, '<html lang="en"'));
check('index.html uses CSS Grid (class="hero")', str_contains($index, 'hero'));
check('index.html links external stylesheet', str_contains($index, 'assets/css/style.css'));
check('about.html uses external stylesheet', str_contains($about, 'assets/css/style.css'));

check('fundamentals.html has an internal <style> block', (bool) preg_match('/<style>/i', $fund));
check('fundamentals.html demonstrates inline style', (bool) preg_match('/style="/i', $fund));
check('fundamentals.html has <thead>', str_contains($fund, '<thead>'));
check('fundamentals.html has <tbody>', str_contains($fund, '<tbody>'));
check('fundamentals.html has <tfoot>', str_contains($fund, '<tfoot>'));
check('fundamentals.html has <audio>', str_contains($fund, '<audio'));
check('fundamentals.html has <video>', str_contains($fund, '<video'));
check('fundamentals.html has <iframe>', str_contains($fund, '<iframe'));

foreach (['type="text"', 'type="email"', 'type="password"', 'type="url"', 'type="tel"'] as $t) {
    check("contact.html has an input {$t}", str_contains($contact, $t));
}
check('contact.html has a <select>', str_contains($contact, '<select'));
check('contact.html has a <textarea>', str_contains($contact, '<textarea'));
check('contact.html has radio inputs', substr_count($contact, 'type="radio"') >= 2);
check('contact.html has checkbox inputs', substr_count($contact, 'type="checkbox"') >= 2);
check('contact.html loads validation.js', str_contains($contact, 'validation.js'));

/* ------------------------------------------------------ 4. CSS features -- */

section('4. CSS3 features');

$css = $read('task-1/public/assets/css/style.css');
check('uses CSS custom properties', str_contains($css, '--'));
check('uses flexbox', str_contains($css, 'display: flex'));
check('uses CSS Grid', str_contains($css, 'display: grid'));
check('uses gradients', str_contains($css, 'gradient('));
check('uses box-shadow', str_contains($css, 'box-shadow:'));
check('uses @keyframes animation', str_contains($css, '@keyframes'));
check('uses transitions', str_contains($css, 'transition:'));
check('has media queries', (bool) preg_match('/@media/', $css));
check('has a mobile breakpoint', (bool) preg_match('/@media\s*\(max-width:\s*(6|7|8)\d\dpx\)/', $css));
check('respects prefers-reduced-motion', str_contains($css, 'prefers-reduced-motion'));
check('declares :focus-visible style', str_contains($css, ':focus-visible'));

/* ------------------------------------------------ 5. PHP helper logic --- */

section('5. PHP helper functions');

require_once ROOT . '/task-1/public/php/inc/functions.php';

check('e() escapes <',  e('<b>x</b>') === '&lt;b&gt;x&lt;/b&gt;', e('<b>x</b>'));
check('e() escapes double quotes', e('a"b') === 'a&quot;b', e('a"b'));
check('e() escapes single quotes', e("a'b") === 'a&#039;b', e("a'b"));
check('e() handles an empty string', e('') === '');
check('badge_class() maps complete to green', str_contains(badge_class('complete'), 'badge-green'));
check('badge_class() defaults to amber', str_contains(badge_class('unknown'), 'badge-amber'));

/* ----------------------------------------------------------- 6. MySQL --- */

section('6. MySQL connectivity');

// Credentials live in the gitignored shared/config.php. Fall back to the
// DB_* environment variables so CI can inject them instead.
$dbCfg = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'user' => getenv('DB_USER') ?: 'apexplanet',
    'pass' => getenv('DB_PASS') ?: '',
    'name' => getenv('DB_NAME') ?: 'apexplanet_fullstack',
];

if (is_file(ROOT . '/shared/config.php')) {
    $cfg = require ROOT . '/shared/config.php';
    $dbCfg = array_merge($dbCfg, [
        'host' => $cfg['db']['host'],
        'user' => $cfg['db']['user'],
        'pass' => $cfg['db']['pass'],
        'name' => $cfg['db']['name'],
    ]);
}

$host = $dbCfg['host'];
$user = $dbCfg['user'];
$pass = $dbCfg['pass'];
$name = $dbCfg['name'];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = mysqli_connect($host, $user, $pass, $name);
    check('connects to MySQL', true);
    check('server is MariaDB or MySQL', (bool) preg_match('/(MariaDB|MySQL)/i', mysqli_get_server_info($conn)));

    // Apply schema.sql so this suite is self-contained and also proves the
    // schema file itself is valid, idempotent SQL.
    $schemaPath = ROOT . '/task-1/public/mysql/schema.sql';
    if (is_file($schemaPath)) {
        // Strip line comments first: a comment block sits in the same
        // semicolon-delimited chunk as the CREATE TABLE that follows it,
        // so filtering on the raw chunk would discard the DDL.
        $clean = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($schemaPath));
        $ddl = [];
        foreach (explode(';', (string) $clean) as $statement) {
            $s = trim($statement);
            if ($s !== '' && preg_match('/^CREATE\s+TABLE/i', $s)) {
                $ddl[] = $s;
            }
        }
        $ok = true;
        foreach ($ddl as $sql) {
            try { mysqli_query($conn, $sql); } catch (Throwable $e) { $ok = false; break; }
        }
        check('schema.sql applies cleanly', $ok);

        mysqli_query($conn, "INSERT IGNORE INTO users (username, email, password_hash, full_name, role) VALUES
            ('keerthan', 'keerthan@example.com', 'seeded', 'Keerthan M S', 'admin'),
            ('demo',     'demo@example.com',     'seeded', 'Demo User',     'user')");
        mysqli_query($conn, "INSERT IGNORE INTO posts (user_id, title, body) VALUES
            ((SELECT id FROM users WHERE username='keerthan'), 'First post', 'Inserted with a prepared statement and linked by foreign key.'),
            ((SELECT id FROM users WHERE username='demo'),     'Hello world', 'A second post used to show the JOIN working.')");
    }

    $res = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    check('users table exists', mysqli_num_rows($res) > 0);

    $res = mysqli_query($conn, "SHOW TABLES LIKE 'posts'");
    check('posts table exists', mysqli_num_rows($res) > 0);

    $res = mysqli_query($conn,
        "SELECT COUNT(*) AS n FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts'
           AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'users'");
    $row = mysqli_fetch_assoc($res);
    check('posts.user_id has a foreign key to users.id', (int) ($row['n'] ?? 0) > 0);

    // Live prepared-statement round trip.
    $uid = (int) (mysqli_fetch_row(mysqli_query($conn, "SELECT id FROM users WHERE username='keerthan'"))[0] ?? 0);
    check('seed user exists', $uid > 0);

    if ($uid > 0) {
        $marker = 'test-marker-' . bin2hex(random_bytes(4));
        $title  = 'Test row';
        $stmt = mysqli_prepare($conn, 'INSERT INTO posts (user_id, title, body) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'iss', $uid, $title, $marker);
        mysqli_stmt_execute($stmt);
        $newId = mysqli_insert_id($conn);
        check('prepared INSERT works', $newId > 0);
        mysqli_stmt_close($stmt);

        $got = null;
        $stmt = mysqli_prepare($conn, 'SELECT body FROM posts WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $newId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $got);
        mysqli_stmt_fetch($stmt);
        check('prepared SELECT returns the inserted value', $got === $marker, (string) $got);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, 'DELETE FROM posts WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $newId);
        mysqli_stmt_execute($stmt);
        check('prepared DELETE works', mysqli_stmt_affected_rows($stmt) === 1);
        mysqli_stmt_close($stmt);
    }

    // A quote-heavy value must survive intact — proves the value is bound,
    // not interpolated into SQL.
    if ($uid > 0) {
        $nasty = "Robert'); DROP TABLE posts;--";
        // bind_param needs variables, not literals, so the title goes in a var.
        $probeTitle = 'Injection probe';
        $stmt = mysqli_prepare($conn, 'INSERT INTO posts (user_id, title, body) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'iss', $uid, $probeTitle, $nasty);
        mysqli_stmt_execute($stmt);
        $probeId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $got = null;
        $stmt = mysqli_prepare($conn, 'SELECT body FROM posts WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $probeId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $got);
        mysqli_stmt_fetch($stmt);
        check('quote-heavy input is stored verbatim (no SQL injection)', $got === $nasty, (string) $got);
        mysqli_stmt_close($stmt);

        $still = mysqli_query($conn, 'SELECT COUNT(*) FROM posts');
        $count = (int) mysqli_fetch_row($still)[0];
        check('posts table survived the injection attempt', $count > 0, "rows={$count}");

        $stmt = mysqli_prepare($conn, 'DELETE FROM posts WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $probeId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    mysqli_close($conn);
} catch (Throwable $e) {
    skipTest('MySQL checks', 'cannot connect: ' . $e->getMessage());
}

/* --------------------------------------------------- 7. HTTP smoke test - */

section('7. HTTP smoke test (PHP built-in server)');

$php = defined('APEX_PHP') ? APEX_PHP : (PHP_BINARY ?: 'php');
$port = 8099;
$docroot = ROOT . '/task-1/public';

$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$server = @proc_open(
    [$php, '-S', "127.0.0.1:{$port}", '-t', $docroot],
    $descriptors,
    $pipes
);

if (!is_resource($server)) {
    skipTest('HTTP smoke test', 'could not start php -S');
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
        skipTest('HTTP smoke test', 'server did not become ready on port ' . $port);
    } else {
        $routes = [
            '/'                       => 'Keerthan',
            '/index.html'             => '<header',
            '/about.html'             => 'About me',
            '/projects.html'          => 'Task 1',
            '/fundamentals.html'      => '<tfoot>',
            '/contact.html'           => 'contact-form',
            '/assets/css/style.css'   => '--maxw',
            '/assets/js/main.js'      => 'initNav',
            '/assets/js/validation.js'=> 'RULES',
            '/assets/img/avatar.svg'  => '<svg',
            '/assets/media/demo.mp4'  => null,
            '/assets/media/tone.wav'  => null,
            '/php/index.php'          => 'Variables and types',
            '/mysql/index.php'        => 'mysqli_connect',
        ];

        foreach ($routes as $route => $needle) {
            $ctx  = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
            $body = @file_get_contents("http://127.0.0.1:{$port}{$route}", false, $ctx);
            $ok   = $body !== false;
            if ($ok && $needle !== null) {
                $ok = str_contains($body, $needle);
            }
            $status = $ok ? '200 + content' : 'failed';
            check("GET {$route}", $ok, $status);
        }

        // POST to the PHP demo must run without a fatal error.
        $post = @file_get_contents(
            "http://127.0.0.1:{$port}/php/index.php",
            false,
            stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content'       => http_build_query(['fruit' => 'ch']),
                    'timeout'       => 10,
                    'ignore_errors' => true,
                ],
            ])
        );
        check('POST /php/index.php returns a result', $post !== false && str_contains($post, 'match'));

        $post = @file_get_contents(
            "http://127.0.0.1:{$port}/php/index.php?sort=desc",
            false,
            stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]])
        );
        check('GET /php/index.php?sort=desc works', $post !== false && str_contains($post, 'Descending'));
    }

    foreach ($pipes as $p) { if (is_resource($p)) fclose($p); }
    proc_terminate($server);
    proc_close($server);
}

/* --------------------------------------------------------- 8. Summary --- */

section('Summary');
echo "  passed : {$passed}\n";
echo "  failed : {$failed}\n";
echo "  skipped: {$skip}\n";

if ($failures) {
    echo "\n  Failures:\n";
    foreach ($failures as $f) {
        echo "   - {$f}\n";
    }
}

echo "\n" . ($failed === 0
    ? "\033[32mALL TESTS PASSED\033[0m\n"
    : "\033[31m{$failed} TEST(S) FAILED\033[0m\n");

exit($failed === 0 ? 0 : 1);
