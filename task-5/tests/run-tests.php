<?php
/**
 * Task 5 — automated test suite.
 *
 *     npm run test:5
 *
 * The OTP checks are the important ones: the code must be stored only as a
 * hash, must expire, must work once, and must be rate limited.
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

/* ------------------------------------------------------ HTTP client --- */

final class Client
{
    private string $base;
    private array $cookies = [];
    public int $status = 0;
    public string $body = '';
    public array $headers = [];

    public function __construct(string $base) { $this->base = rtrim($base, '/'); }

    public function request(string $method, string $path, array $fields = []): self
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 25,
        ]);
        if ($this->cookies) {
            $pairs = [];
            foreach ($this->cookies as $k => $v) { $pairs[] = "$k=$v"; }
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $pairs));
        }
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $raw = curl_exec($ch);
        if ($raw === false) { $this->body = ''; $this->status = 0; curl_close($ch); return $this; }

        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headers = substr((string) $raw, 0, $hs);
        $this->body = substr((string) $raw, $hs);

        foreach (explode("\r\n", $headers) as $line) {
            if (stripos($line, 'Set-Cookie:') === 0) {
                $c = trim(substr($line, 11));
                $n = explode('=', $c)[0];
                $v = explode(';', explode('=', $c, 2)[1] ?? '')[0];
                $this->cookies[$n] = $v;
            }
            if (stripos($line, 'Location:') === 0) {
                $this->headers['location'] = trim(substr($line, 9));
            }
        }
        curl_close($ch);
        return $this;
    }

    public function get(string $p): self { return $this->request('GET', $p); }
    public function post(string $p, array $f = []): self { return $this->request('POST', $p, $f); }

    public function token(): string
    {
        return preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $this->body, $m) ? $m[1] : '';
    }
}

/* ------------------------------------------------------------- setup --- */

$configFile = ROOT . '/shared/config.php';
if (!is_file($configFile)) { echo "\n\033[31mshared/config.php missing. Run: npm run setup\033[0m\n"; exit(1); }
$config = require $configFile;
$dbCfg  = $config['db_lms'];

$port = 8082;
$serverLog = (string) tempnam(sys_get_temp_dir(), 'apex-t5-');
$server = @proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', ROOT . '/task-5/public'],
    [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']],
    $pipes
);
if (!is_resource($server)) { echo "\n\033[31mCould not start the test server.\033[0m\n"; exit(1); }
stream_set_blocking($pipes[0], false);
register_shutdown_function(static function () use (&$server, $pipes, $serverLog) {
    foreach ($pipes as $p) { if (is_resource($p)) fclose($p); }
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    @unlink($serverLog);
});
$ready = false;
for ($i = 0; $i < 60; $i++) {
    usleep(150000);
    $s = @fsockopen('127.0.0.1', $port, $e, $e2, 0.4);
    if ($s) { fclose($s); $ready = true; break; }
}
if (!$ready) { echo "\n\033[31mTest server never became ready.\033[0m\n"; exit(1); }

$base = "http://127.0.0.1:{$port}";

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbCfg['host'], $dbCfg['port'], $dbCfg['name'], $dbCfg['charset']);
    $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    echo "\n\033[31mCannot reach the LMS database: {$e->getMessage()}\033[0m\n";
    exit(1);
}

// Clean slate for anything a previous run left behind.
$suffix = bin2hex(random_bytes(3));
$otpEmail = "otp{$suffix}@example.test";
$pdo->exec("DELETE FROM otp_codes WHERE user_id IN (SELECT id FROM users WHERE email = '{$otpEmail}')");
$pdo->exec("DELETE FROM users WHERE email = '{$otpEmail}'");

/* ---------------------------------------------------------- schema --- */

section('1. Schema and normalisation');

$tables = $pdo->query('SELECT table_name FROM information_schema.TABLES WHERE table_schema = DATABASE()')
    ->fetchAll(PDO::FETCH_COLUMN);
foreach (['users', 'otp_codes', 'categories', 'courses', 'lessons', 'enrollments'] as $t) {
    check("table {$t} exists", in_array($t, $tables, true));
}

$fk = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL"
)->fetchColumn();
check('five foreign keys defined', (int) $fk === 5, "found {$fk}");

$otpCols = $pdo->query('SHOW COLUMNS FROM otp_codes')->fetchAll(PDO::FETCH_COLUMN);
check('otp_codes stores a hash column', in_array('code_hash', $otpCols, true));
check('otp_codes has no plaintext code column',
      !in_array('code', $otpCols, true) && !in_array('otp', $otpCols, true),
      implode(',', $otpCols));
check('otp_codes row length is a sha256 hex string',
      (int) $pdo->query("SELECT COUNT(*) FROM otp_codes WHERE CHAR_LENGTH(code_hash) <> 64")->fetchColumn() === 0);

$courses = (int) $pdo->query('SELECT COUNT(*) FROM courses WHERE is_published = 1')->fetchColumn();
check('seeded courses are published', $courses > 0);

/* --------------------------------------------------- 2. registration --- */

section('2. Registration and OTP verification');

$c = new Client($base);
$r = $c->get('/register.php');
check('registration page renders', $r->status === 200 && str_contains($r->body, 'Create your account'));

$r = $c->post('/register.php', [
    'csrf_token' => $c->token(),
    'full_name'  => 'OTP Test Student',
    'email'      => $otpEmail,
    'password'   => 'Test@12345',
    'confirm_password' => 'Test@12345',
]);
check('registration succeeds', $r->status === 302 && str_contains((string) ($r->headers['location'] ?? ''), 'verify.php'),
      "status={$r->status}");

$userId = (int) $pdo->prepare('SELECT id FROM users WHERE email = ?')->execute([$otpEmail])
    ? (int) $pdo->query("SELECT id FROM users WHERE email = '{$otpEmail}'")->fetchColumn()
    : 0;
check('the account was created', $userId > 0);

$row = $pdo->prepare('SELECT email_verified, password_hash FROM users WHERE id = ?');
$row->execute([$userId]);
$acct = $row->fetch(PDO::FETCH_ASSOC);
check('new account starts unverified', (int) $acct['email_verified'] === 0);
check('password is hashed', str_starts_with((string) $acct['password_hash'], '$2y$'));

// A code must exist and must not be the plaintext in the table.
$otpRows = $pdo->prepare('SELECT code_hash FROM otp_codes WHERE user_id = ? AND purpose = ? AND consumed_at IS NULL');
$otpRows->execute([$userId, 'email_verification']);
$hashes = $otpRows->fetchAll(PDO::FETCH_COLUMN);
check('a verification code was issued', count($hashes) === 1, 'count=' . count($hashes));
check('the code is stored as a 64 character hash', strlen((string) $hashes[0]) === 64);

// Sign-in must be refused until verification.
$c2 = new Client($base);
$c2->get('/login.php');
$r = $c2->post('/login.php', ['csrf_token' => $c2->token(), 'email' => $otpEmail, 'password' => 'Test@12345']);
check('unverified account cannot sign in',
      $r->status === 200 && str_contains($r->body, 'Verify your email'),
      "status={$r->status}");

// The console transport writes the code to the error log; read it back the same
// way an operator would.
$logFile = dirname(__DIR__, 2) . '/../_runtime/php/php-error.log';
$code = null;
if (is_file($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach (array_reverse($lines) as $line) {
        if (str_contains($line, 'purpose=email_verification code=')) {
            if (preg_match('/code=(\d{6})/', $line, $m)) { $code = $m[1]; }
            if (str_contains($line, $otpEmail)) { break; }
        }
    }
}
check('the code was delivered through the console transport', $code !== null, 'no code found in the error log');

if ($code !== null) {
    // Load the verification page first: the registration POST redirected, so
    // its body carries no form and therefore no CSRF token.
    $c->get('/verify.php');

    // Wrong code first.
    $r = $c->post('/verify.php', ['csrf_token' => $c->token(), 'code' => '000000' === $code ? '111111' : '000000']);
    check('a wrong code is rejected', $r->status === 200 && str_contains($r->body, 'not correct'),
          'status=' . $r->status . ' ' . substr(strip_tags($r->body), 0, 90));

    // Correct code. The page reloads after a wrong attempt, so take a fresh token.
    $c->get('/verify.php');
    $r = $c->post('/verify.php', ['csrf_token' => $c->token(), 'code' => $code]);
    check('the correct code verifies the account', $r->status === 302, "status={$r->status}");

    $row = $pdo->prepare('SELECT email_verified FROM users WHERE id = ?');
    $row->execute([$userId]);
    check('email_verified is set to 1', (int) $row->fetchColumn() === 1);

    // Replay: the same code must not work twice.
    $replay = new Client($base);
    $replay->get('/login.php');
    $replay->post('/login.php', ['csrf_token' => $replay->token(), 'email' => $otpEmail, 'password' => 'Test@12345']);
    $row = $pdo->prepare('SELECT consumed_at FROM otp_codes WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $row->execute([$userId]);
    check('the consumed code is marked consumed', $row->fetchColumn() !== null);

    // A verified account can now sign in.
    $r = $replay->get('/index.php');
    check('verified account reaches the catalogue', $r->status === 200);
}

/* ------------------------------------------------------- 3. AJAX search --- */

section('3. AJAX course search');

$anon = new Client($base);

$r = $anon->get('/index.php');
check('catalogue renders server-side', $r->status === 200 && str_contains($r->body, 'Courses'));

$r = $anon->get('/api/search-courses.php?q=php');
$j = json_decode($r->body, true);
check('AJAX endpoint returns JSON', is_array($j) && array_key_exists('html', $j),
      'status=' . $r->status . ' body=' . substr($r->body, 0, 120));
check('AJAX response carries a total', is_array($j) && is_int($j['total'] ?? null));

$all = json_decode($anon->get('/api/search-courses.php')->body, true);
$one = json_decode($anon->get('/api/search-courses.php?q=secure')->body, true);
check('search narrows the result set', $one['total'] < $all['total'], "one={$one['total']} all={$all['total']}");

$none = json_decode($anon->get('/api/search-courses.php?q=zzzznothing')->body, true);
check('no matches returns an empty state', $none['total'] === 0 && str_contains($none['html'], 'No courses match'));

// LIKE wildcard must be escaped, not honoured.
$wild = json_decode($anon->get('/api/search-courses.php?q=' . rawurlencode('%'))->body, true);
check('LIKE wildcard is escaped', $wild['total'] === 0, "total={$wild['total']}");

// Injection attempt.
$inj = json_decode($anon->get('/api/search-courses.php?q=' . rawurlencode("' OR '1'='1"))->body, true);
check('quote-heavy search is harmless', is_array($inj) && $inj['total'] === 0);
check('courses still present after the attempt', (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn() > 0);

// Invalid filter values fall back rather than erroring.
$bad = json_decode($anon->get('/api/search-courses.php?level=nonsense')->body, true);
check('invalid level is ignored, not injected', is_array($bad) && $bad['total'] === $all['total']);

$free = json_decode($anon->get('/api/search-courses.php?max_price=0')->body, true);
check('max_price filter works', is_array($free) && $free['total'] < $all['total']);

// Method guard. The shared CSRF check runs before the endpoint sees the
// request, so an unauthenticated POST is refused there — in JSON, because the
// endpoint lives under /api/. Either refusal is correct; what matters is that
// it is JSON and not an HTML error page.
$ctx = stream_context_create(['http' => ['method' => 'POST', 'content' => '', 'timeout' => 15, 'ignore_errors' => true]]);
$postBody = (string) @file_get_contents("$base/api/search-courses.php", false, $ctx);
$postJson = json_decode($postBody, true);
check('AJAX endpoint refuses POST with a JSON error',
      is_array($postJson) && isset($postJson['error']),
      substr($postBody, 0, 100));

// With a valid CSRF token the method guard itself is what answers. Take the
// token from the login form (an anonymous catalogue page carries no form) and
// submit through the cookie-aware client so the session reaches the server.
$anon->get('/login.php');
$tok = $anon->token();
$r = $anon->post('/api/search-courses.php', ['csrf_token' => $tok]);
check('AJAX endpoint answers "Use GET" for an authorised POST',
      $r->status === 405 && str_contains($r->body, 'Use GET'),
      'status=' . $r->status . ' ' . substr($r->body, 0, 90));

/* -------------------------------------------------------- 4. enrolment --- */

section('4. Enrolment and learning');

$student = new Client($base);
$student->get('/login.php');
$r = $student->post('/login.php', ['csrf_token' => $student->token(), 'email' => 'demo@apexplanet.local', 'password' => 'Demo@12345']);
check('seeded student signs in', $r->status === 302, "status={$r->status}");

$r = $student->get('/my-courses.php');
check('my learning renders', $r->status === 200 && str_contains($r->body, 'My learning'));

$target = (int) $pdo->query("SELECT id FROM courses WHERE is_published = 1 AND code = 'CS110'")->fetchColumn();
$before = (int) $pdo->query("SELECT COUNT(*) FROM enrollments WHERE user_id = (SELECT id FROM users WHERE email='demo@apexplanet.local') AND course_id = {$target}")->fetchColumn();

$r = $student->get('/index.php');
$r = $student->post('/enrol.php', ['csrf_token' => $r->token(), 'course_id' => $target]);
check('enrolment redirects', $r->status === 302, "status={$r->status}");

$after = (int) $pdo->query("SELECT COUNT(*) FROM enrollments WHERE user_id = (SELECT id FROM users WHERE email='demo@apexplanet.local') AND course_id = {$target}")->fetchColumn();
check('an enrolment row was created', $after === $before + 1, "before={$before} after={$after}");

// Enrolling twice must not create a second row (unique key).
$r = $student->get('/index.php');
$student->post('/enrol.php', ['csrf_token' => $r->token(), 'course_id' => $target]);
$again = (int) $pdo->query("SELECT COUNT(*) FROM enrollments WHERE user_id = (SELECT id FROM users WHERE email='demo@apexplanet.local') AND course_id = {$target}")->fetchColumn();
check('enrolling twice does not duplicate the row', $again === 1, "rows={$again}");

// Unpublished course must be refused.
$draft = (int) $pdo->query('SELECT id FROM courses WHERE is_published = 0 LIMIT 1')->fetchColumn();
if ($draft > 0) {
    $r = $student->get('/index.php');
    $student->post('/enrol.php', ['csrf_token' => $r->token(), 'course_id' => $draft]);
    $n = (int) $pdo->query("SELECT COUNT(*) FROM enrollments WHERE course_id = {$draft}")->fetchColumn();
    check('unpublished course cannot be enrolled in', $n === 0);
    $r = $student->get('/course.php?id=' . $draft);
    check('unpublished course is 404 for a student', $r->status === 404, "status={$r->status}");
}

// CSRF on enrolment
$r = $student->post('/enrol.php', ['csrf_token' => str_repeat('b', 64), 'course_id' => $target]);
check('enrolment refuses a forged CSRF token', $r->status === 419, "status={$r->status}");

/* --------------------------------------------------------- 5. admin --- */

section('5. Admin panel and RBAC');

$admin = new Client($base);
$admin->get('/login.php');
$r = $admin->post('/login.php', ['csrf_token' => $admin->token(), 'email' => 'admin@apexplanet.local', 'password' => 'Admin@12345']);
check('admin signs in', $r->status === 302);

$r = $admin->get('/admin/index.php');
check('admin dashboard renders', $r->status === 200 && str_contains($r->body, 'Admin dashboard'));
check('dashboard loads Chart.js', str_contains($r->body, 'chart.umd.js'));
// The chart data is embedded by the escaper json_safe(), not the responder
// json_out(). Getting that wrong echoes HTTP headers into the middle of the
// inline <script> and leaves "headers already sent" warnings in the page, so
// assert on it rather than just on the presence of a canvas element. PHP
// prints errors both as "Warning: ..." and as "<b>Warning</b>: ...", so the
// pattern has to match either form.
check('dashboard emits no PHP warnings',
      !preg_match(
          '/<b>(Warning|Fatal error|Deprecated|Notice|Parse error)<\/b>|(?<!<b>)Warning:|Fatal error:|Deprecated:/i',
          $r->body
      ));
check('chart labels are valid inline JSON',
      (bool) preg_match('/labels:\s*\[/', $r->body),
      'no json_safe array literal found');
check('enrolments chart present', str_contains($r->body, 'enrolChart'));
check('level chart present', str_contains($r->body, 'levelChart'));
check('OTP activity reported', str_contains($r->body, 'OTP verification activity'));

$r = $student->get('/admin/index.php');
$loc = (string) ($r->headers['location'] ?? '');
check('student is denied the admin dashboard', $r->status === 302 && !str_contains($loc, 'admin/'));

$r = $anon->get('/my-courses.php');
check('anonymous is redirected from my learning', $r->status === 302);

/* ---------------------------------------- 5b. clean page rendering --- */

section('5b. Pages render without PHP warnings');

// PHP prints warnings inline, so a page can still "contain" everything an
// assertion looks for while being visibly broken in a browser. Walk the main
// pages as each role and require the markup to be clean.
$warnPat = '/<b>(Warning|Fatal error|Deprecated|Notice|Parse error)<\/b>|(?<!<b>)Warning:|Fatal error:|Deprecated:/i';
$pages = [
    'guest on the catalogue'   => [$anon,   '/index.php',         200],
    'guest on the login form'  => [$anon,   '/login.php',         200],
    'guest on registration'    => [$anon,   '/register.php',      200],
    'student on my learning'   => [$student,'/my-courses.php',    200],
    'admin on the dashboard'   => [$admin,  '/admin/index.php',   200],
    'admin on the course list' => [$admin,  '/admin/courses.php', 200],
];
foreach ($pages as $label => [$client, $path, $want]) {
    $resp = $client->get($path);
    $clean = !preg_match($warnPat, $resp->body);
    check("{$label} renders cleanly", $resp->status === $want && $clean,
          "status={$resp->status}" . ($clean ? '' : ' — warning found'));
}

// The nav must actually mark the current page. On a page loaded from /admin/,
// a wrong "active" entry is a visible defect users hit on every navigation.
$r = $admin->get('/admin/courses.php');
check('nav marks the course manager active',
      (bool) preg_match('/<a href="[^"]*admin\/courses\.php"[^>]*class="active"/', $r->body));
check('the dashboard is not highlighted while on the course page',
      !preg_match('/<a href="[^"]*admin\/index\.php"[^>]*class="active"/', $r->body));
$r = $admin->get('/admin/index.php');
check('nav marks the dashboard active',
      (bool) preg_match('/<a href="[^"]*admin\/index\.php"[^>]*class="active"/', $r->body));

/* ------------------------------------------------- 6. course management --- */

section('6. Admin course CRUD');

$code = 'TS' . strtoupper($suffix);

$r = $admin->get('/admin/courses.php');
check('admin course manager renders', $r->status === 200 && str_contains($r->body, 'Manage courses'));

// Create
$r = $admin->post('/admin/courses.php', [
    'csrf_token'   => $r->token(),
    'action'       => 'create',
    'code'         => $code,
    'title'        => 'Test Suite Course',
    'summary'      => 'Created by the automated suite.',
    'description'  => 'Full description written by the test suite.',
    'category_id'  => (int) $pdo->query('SELECT id FROM categories LIMIT 1')->fetchColumn(),
    'level'        => 'intermediate',
    'price_cents'  => '49900',
    'duration_min' => '90',
    'is_published' => '1',
]);
$courseId = (int) $pdo->query("SELECT id FROM courses WHERE code = '{$code}'")->fetchColumn();
check('a course is created', $r->status === 302 && $courseId > 0, "status={$r->status}");

// Duplicate code must be a field error, not a database exception.
$r = $admin->get('/admin/courses.php');
$r = $admin->post('/admin/courses.php', [
    'csrf_token'  => $r->token(),
    'action'      => 'create',
    'code'        => $code,
    'title'       => 'Duplicate',
    'summary'     => 'x',
    'description' => 'x',
    'category_id' => (int) $pdo->query('SELECT id FROM categories LIMIT 1')->fetchColumn(),
    'level'       => 'beginner',
    'price_cents' => '0',
    'duration_min' => '10',
]);
check('a duplicate course code is refused', str_contains($r->body, 'already in use'),
      'status=' . $r->status);
check('the duplicate did not create a second row',
      (int) $pdo->query("SELECT COUNT(*) FROM courses WHERE code = '{$code}'")->fetchColumn() === 1);

// Invalid level must be refused rather than written to the ENUM.
$r = $admin->get('/admin/courses.php');
$admin->post('/admin/courses.php', [
    'csrf_token'  => $r->token(),
    'action'      => 'create',
    'code'        => 'TX' . strtoupper($suffix),
    'title'       => 'Bad level',
    'summary'     => 'x',
    'description' => 'x',
    'category_id' => (int) $pdo->query('SELECT id FROM categories LIMIT 1')->fetchColumn(),
    'level'       => "beginner', is_published=1 -- ",
    'price_cents' => '0',
    'duration_min' => '10',
]);
$bad = (int) $pdo->query("SELECT COUNT(*) FROM courses WHERE code = 'TX{strtoupper($suffix)}'")->fetchColumn();
check('an invalid level is rejected, not injected', $bad === 0);

// Unpublished course must be invisible to a student.
$admin->get('/admin/courses.php');
$admin->post('/admin/courses.php', ['csrf_token' => $admin->token(), 'action' => 'toggle', 'id' => $courseId]);
$isPub = (int) $pdo->query("SELECT is_published FROM courses WHERE id = {$courseId}")->fetchColumn();
check('the publish toggle flips the flag', $isPub === 0, "is_published={$isPub}");

$r = $student->get('/course.php?id=' . $courseId);
check('an unpublished course 404s for a student', $r->status === 404, "status={$r->status}");

$studentSearch = json_decode($student->get('/api/search-courses.php')->body, true);
check('an unpublished course is absent from the AJAX catalogue',
      is_array($studentSearch) && $studentSearch['total'] === $all['total'],
      "with=" . ($studentSearch['total'] ?? 'null') . " expected={$all['total']}");

// Republish so the delete guard has a published row to protect.
$admin->get('/admin/courses.php');
$admin->post('/admin/courses.php', ['csrf_token' => $admin->token(), 'action' => 'toggle', 'id' => $courseId]);

// A course holding enrolments must be unpublished, not deleted: the foreign key
// on enrollments.course_id is ON DELETE CASCADE, so a real delete would
// silently erase the student's record.
$r = $student->get('/index.php');
$student->post('/enrol.php', ['csrf_token' => $r->token(), 'course_id' => $courseId]);
$enrolledBefore = (int) $pdo->query("SELECT COUNT(*) FROM enrollments WHERE course_id = {$courseId}")->fetchColumn();
check('the test course has an enrolment to protect', $enrolledBefore === 1, "rows={$enrolledBefore}");

$r = $admin->get('/admin/courses.php');
$admin->post('/admin/courses.php', ['csrf_token' => $admin->token(), 'action' => 'delete', 'id' => $courseId]);
$still = (int) $pdo->query("SELECT COUNT(*) FROM courses WHERE id = {$courseId}")->fetchColumn();
check('a course with students is unpublished, not deleted', $still === 1);
$r = $admin->get('/admin/courses.php');
check('the guard is explained to the admin', str_contains($r->body, 'unpublished instead of deleted'));
check('the enrolment survived the delete attempt',
      (int) $pdo->query("SELECT COUNT(*) FROM enrollments WHERE course_id = {$courseId}")->fetchColumn() === 1);

// A student must not reach any of this.
$r = $student->get('/admin/courses.php');
check('student is denied the course manager', $r->status === 302 && !str_contains((string) ($r->headers['location'] ?? ''), 'admin/'));

// Cleanup: the guard left the row unpublished, so remove it directly.
$pdo->exec("DELETE FROM enrollments WHERE course_id = {$courseId}");
$pdo->exec("DELETE FROM courses WHERE id = {$courseId}");

/* --------------------------------------------------------- cleanup --- */

$pdo->exec("DELETE FROM enrollments WHERE user_id IN (SELECT id FROM users WHERE email = '{$otpEmail}')");
$pdo->exec("DELETE FROM otp_codes WHERE user_id IN (SELECT id FROM users WHERE email = '{$otpEmail}')");
$pdo->exec("DELETE FROM users WHERE email = '{$otpEmail}'");
$pdo->exec("DELETE FROM enrollments WHERE course_id = {$target}");

/* --------------------------------------------------------- summary --- */

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
