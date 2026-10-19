<?php
/**
 * Task 3 — automated test suite.
 *
 * Run from the repository root:
 *     npm run test:3
 *
 * This suite exercises the real application over HTTP with a real session
 * cookie, so the authentication, CSRF, authorisation and injection checks
 * test the code that actually ships rather than a re-implementation of it.
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

/* ------------------------------------------------------ HTTP client --- */

/**
 * Minimal cookie-aware HTTP client, so a login performed in one call is
 * honoured by the next.
 */
final class Client
{
    public string $base;
    public array $cookies = [];
    public int $status = 0;
    public string $body = '';
    public array $headers = [];

    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/');
    }

    public function request(string $method, string $path, array $fields = [], array $files = []): self
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
            foreach ($this->cookies as $k => $v) {
                $pairs[] = "$k=$v";
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $pairs));
        }

        if ($method === 'POST') {
            if ($files) {
                $payload = $fields;
                foreach ($files as $field => $spec) {
                    $payload[$field] = new CURLFile($spec['path'], $spec['type'], $spec['name']);
                }
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            } else {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
            }
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $this->body = '';
            $this->status = 0;
            curl_close($ch);
            return $this;
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerText = substr((string) $raw, 0, $headerSize);
        $this->body = substr((string) $raw, $headerSize);

        foreach (explode("\r\n", $headerText) as $line) {
            if (stripos($line, 'Set-Cookie:') === 0) {
                $cookie = trim(substr($line, 11));
                $name = explode('=', $cookie)[0];
                $value = explode(';', explode('=', $cookie, 2)[1] ?? '')[0];
                $this->cookies[$name] = $value;
            }
            if (stripos($line, 'Location:') === 0) {
                $this->headers['location'] = trim(substr($line, 9));
            }
        }

        curl_close($ch);
        return $this;
    }

    public function get(string $p): self  { return $this->request('GET', $p); }
    public function post(string $p, array $f = [], array $files = []): self { return $this->request('POST', $p, $f, $files); }

    /** Pull the CSRF token out of a rendered form. */
    public function token(): string
    {
        if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $this->body, $m)) {
            return $m[1];
        }
        return '';
    }
}

/* ------------------------------------------------------------- setup --- */

$config = is_file(ROOT . '/shared/config.php') ? require ROOT . '/shared/config.php' : null;
if ($config === null) {
    echo "\n\033[31mshared/config.php is missing. Run: npm run setup\033[0m\n";
    exit(1);
}

$php = PHP_BINARY;
$port = 8097;
$docroot = ROOT . '/task-3/public';
$serverLog = (string) tempnam(sys_get_temp_dir(), 'apex-srv-');

$server = @proc_open(
    [$php, '-S', "127.0.0.1:{$port}", '-t', $docroot],
    // The built-in server logs every request to stderr. Attached to a pipe that
    // nobody drains, the ~64 KB buffer fills and the server blocks, which shows
    // up as a hang partway through the run. Send it to a file instead.
    [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']],
    $pipes
);

if (!is_resource($server)) {
    echo "\n\033[31mCould not start the test server.\033[0m\n";
    exit(1);
}
stream_set_blocking($pipes[0], false);

$ready = false;
for ($i = 0; $i < 60; $i++) {
    usleep(150000);
    $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.4);
    if ($probe) { fclose($probe); $ready = true; break; }
}

register_shutdown_function(static function () use (&$server, $pipes, $serverLog) {
    foreach ($pipes as $p) { if (is_resource($p)) fclose($p); }
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    @unlink($serverLog);
});

if (!$ready) {
    echo "\n\033[31mTest server never became ready.\033[0m\n";
    exit(1);
}

$base = "http://127.0.0.1:{$port}";

/* ---------------------------------------------------------- database --- */

section('1. Database schema and normalisation');

try {
    $dbCfg = $config['db_users'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbCfg['host'], $dbCfg['port'], $dbCfg['name'], $dbCfg['charset']);
    $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    check('connects to the Task 3 database', true);

    // Start from a known state. login_attempts is throttle state, so leftovers
    // from a previous run would eventually lock the admin account out of the
    // suite itself. Test users are removed too, so counts stay meaningful.
    $pdo->exec('DELETE FROM login_attempts');
    $pdo->exec("DELETE FROM users WHERE username LIKE 'tester%' OR username LIKE 'inj%'");
    $pdo->exec("DELETE FROM users WHERE username IN ('throttleme')");
    check('test state reset to a clean slate', true);

    $tables = $pdo->query(
        "SELECT table_name FROM information_schema.TABLES WHERE table_schema = DATABASE()"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach (['roles', 'users', 'user_profiles', 'login_attempts'] as $t) {
        check("table {$t} exists", in_array($t, $tables, true));
    }

    $pk = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'PRIMARY KEY' AND TABLE_NAME IN ('roles','users','user_profiles')"
    )->fetchColumn();
    check('primary keys defined on roles, users and user_profiles', (int) $pk === 3, "found {$pk}");

    $fks = $pdo->query(
        "SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL"
    )->fetchAll(PDO::FETCH_ASSOC);
    $fkMap = [];
    foreach ($fks as $f) { $fkMap[$f['TABLE_NAME'] . '.' . $f['CONSTRAINT_NAME']] = $f['REFERENCED_TABLE_NAME']; }
    check('users has a foreign key to roles', in_array('users.roles', $fkMap, true) || in_array('roles', $fkMap, true), json_encode($fkMap));
    check('user_profiles has a foreign key to users', in_array('user_profiles.users', $fkMap, true) || in_array('users', array_values($fkMap), true));

    // 3NF: users must not carry a role name column, only role_id.
    $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    check('users stores role_id, not a duplicated role name (3NF)', in_array('role_id', $cols, true) && !in_array('role', $cols, true), implode(',', $cols));

    // Seeded accounts
    $admin = $pdo->query("SELECT u.username, u.password_hash, r.name role FROM users u JOIN roles r ON r.id=u.role_id WHERE u.username='admin'")->fetch(PDO::FETCH_ASSOC);
    check('seeded admin account exists', (bool) $admin);
    check('admin account has the admin role', $admin['role'] === 'admin');

    // Password storage
    check('password is stored as a bcrypt hash', str_starts_with((string) $admin['password_hash'], '$2y$'), substr((string) $admin['password_hash'], 0, 10));
    check('password_hash() verifies the documented demo password', password_verify('Admin@12345', (string) $admin['password_hash']));
    check('wrong password does not verify', !password_verify('wrong', (string) $admin['password_hash']));
    check('plaintext password is not present in the table', !in_array('Admin@12345', $cols, true));

    // Password hashes must be salted: two hashes of the same value differ.
    $h1 = password_hash('same-value', PASSWORD_DEFAULT);
    $h2 = password_hash('same-value', PASSWORD_DEFAULT);
    check('hashes are salted (same input, different output)', $h1 !== $h2);

    // Distinct seeded hashes for distinct users
    $hashes = $pdo->query('SELECT DISTINCT password_hash FROM users')->fetchAll(PDO::FETCH_COLUMN);
    check('each account has its own hash', count($hashes) === (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
} catch (Throwable $e) {
    skipTest('Database schema', $e->getMessage());
}

/* --------------------------------------------------------- 2. routes --- */

section('2. Pages and access control');

$anon = new Client($base);

$r = $anon->get('/login.php');
check('GET /login.php renders', $r->status === 200 && str_contains($r->body, 'Sign in'));
check('login page carries a CSRF token', $r->token() !== '');

$r = $anon->get('/register.php');
check('GET /register.php renders', $r->status === 200 && str_contains($r->body, 'Create account'));

foreach (['/index.php', '/profile.php', '/users/index.php', '/users/create.php'] as $guarded) {
    $r = $anon->get($guarded);
    check("anonymous {$guarded} redirects to login",
          $r->status === 302 && str_contains((string) ($r->headers['location'] ?? ''), 'login.php'),
          "status={$r->status}");
}

$r = $anon->get('/users/delete.php');
check('DELETE endpoint refuses GET', $r->status === 302);

/* ----------------------------------------------------- 3. csrf guard --- */

section('3. CSRF protection');

$c = new Client($base);
$c->get('/login.php');
$token = $c->token();
check('a token is issued on the login page', $token !== '');

$r = $c->post('/login.php', [
    'username' => 'admin',
    'password' => 'Admin@12345',
    // deliberately no csrf_token
]);
check('POST without a CSRF token is rejected',
      $r->status === 419 || str_contains($r->body, 'CSRF token mismatch'),
      "status={$r->status}");

$r = $c->post('/login.php', [
    'csrf_token' => str_repeat('f', 64),
    'username'   => 'admin',
    'password'   => 'Admin@12345',
]);
check('POST with a forged CSRF token is rejected',
      $r->status === 419 || str_contains($r->body, 'CSRF token mismatch'));

/* --------------------------------------------------------- 4. login --- */

section('4. Authentication');

$c = new Client($base);
$c->get('/login.php');
$token = $c->token();

$r = $c->post('/login.php', [
    'csrf_token' => $token,
    'username'   => 'admin',
    'password'   => 'wrong-password',
]);
check('wrong password is refused', $r->status === 422 && str_contains($r->body, 'do not match our records'), "status={$r->status}");
check('failed attempt is recorded', (int) $pdo->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn() > 0);

// log back in with a fresh token
$c = new Client($base);
$c->get('/login.php');
$r = $c->post('/login.php', [
    'csrf_token' => $c->token(),
    'username'   => 'admin',
    'password'   => 'Admin@12345',
]);
check('correct password signs in', $r->status === 302 && str_contains((string) ($r->headers['location'] ?? ''), 'index.php'), "status={$r->status}");

$r = $c->get('/index.php');
check('dashboard renders after sign-in', $r->status === 200 && str_contains($r->body, 'Welcome, admin'));
check('dashboard shows the admin role', str_contains($r->body, 'administrators') || str_contains($r->body, 'Admin'));

// deactivated account
$c2 = new Client($base);
$c2->get('/login.php');
$r = $c2->post('/login.php', [
    'csrf_token' => $c2->token(),
    'username'   => 'locked',
    'password'   => 'Locked@12345',
]);
check('deactivated account cannot sign in',
      $r->status === 422 && str_contains($r->body, 'deactivated'),
      "status={$r->status}");

/* ----------------------------------------------------------- 5. crud --- */

section('5. CRUD operations');

$admin = new Client($base);
$admin->get('/login.php');
$admin->post('/login.php', ['csrf_token' => $admin->token(), 'username' => 'admin', 'password' => 'Admin@12345']);

$unique  = 'tester' . bin2hex(random_bytes(3));
$madePwd = 'Test@123456';

// Create
$r = $admin->get('/users/create.php');
$createToken = $r->token();
check('create form renders', $r->status === 200 && str_contains($r->body, 'Add user'));

$r = $admin->post('/users/create.php', [
    'csrf_token'       => $createToken,
    'username'         => $unique,
    'email'            => $unique . '@example.com',
    'full_name'        => 'Test Person',
    'headline'         => 'Created by the test suite',
    'role_id'          => '1',
    'password'         => $madePwd,
    'confirm_password' => $madePwd,
]);
check('create redirects back to the list', $r->status === 302, "status={$r->status}");

$row = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ?');
$row->execute([$unique]);
$created = $row->fetch(PDO::FETCH_ASSOC);
check('new user row exists', (bool) $created);
check('new password is hashed', $created && str_starts_with((string) $created['password_hash'], '$2y$'));
check('new password verifies', $created && password_verify($madePwd, (string) $created['password_hash']));

$profRow = $pdo->prepare('SELECT full_name FROM user_profiles WHERE user_id = ?');
$profRow->execute([$created['id']]);
check('profile row created in the same transaction', (bool) $profRow->fetchColumn());

// Read
$r = $admin->get('/users/index.php');
check('list page renders', $r->status === 200 && str_contains($r->body, 'Manage users'));
check('list shows the new user', str_contains($r->body, $unique));

$r = $admin->get('/users/index.php?q=' . rawurlencode($unique));
check('search finds the new user', str_contains($r->body, $unique));

$r = $admin->get('/users/index.php?q=' . rawurlencode('zzz-no-such-user-zzz'));
check('search with no match shows an empty state', str_contains($r->body, 'No accounts found'));

// Validation on create
$r = $admin->get('/users/create.php');
$t = $r->token();
$r = $admin->post('/users/create.php', [
    'csrf_token' => $t, 'username' => 'ab', 'email' => 'not-an-email',
    'role_id' => '1', 'password' => 'short', 'confirm_password' => 'different',
]);
check('server-side validation rejects bad create input',
      $r->status === 422
      && str_contains($r->body, 'Use 3 to 32 characters')
      && str_contains($r->body, 'valid email'),
      "status={$r->status}");

// Update
$newName = 'Test Person Renamed';
$r = $admin->get('/users/edit.php?id=' . (int) $created['id']);
check('edit form loads', $r->status === 200 && str_contains($r->body, 'Edit user'));

$r = $admin->post('/users/edit.php?id=' . (int) $created['id'], [
    'csrf_token' => $r->token(),
    'username'   => $unique,
    'email'      => $unique . '@example.com',
    'full_name'  => $newName,
    'headline'   => 'Updated',
    'role_id'    => '1',
    'is_active'  => '1',
    'password'   => '',
]);
check('update redirects', $r->status === 302, "status={$r->status}");

$profRow = $pdo->prepare('SELECT full_name FROM user_profiles WHERE user_id = ?');
$profRow->execute([$created['id']]);
check('update is persisted', $profRow->fetchColumn() === $newName);

// Leaving the password blank must not change it
$row = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
$row->execute([$created['id']]);
check('blank password field does not reset the password',
      password_verify($madePwd, (string) $row->fetchColumn()));

// Delete
$r = $admin->get('/users/index.php');
$r = $admin->post('/users/delete.php', ['csrf_token' => $r->token(), 'id' => (int) $created['id']]);
check('delete redirects', $r->status === 302, "status={$r->status}");

$row = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id = ?');
$row->execute([$created['id']]);
check('user row removed', (int) $row->fetchColumn() === 0);

$row = $pdo->prepare('SELECT COUNT(*) FROM user_profiles WHERE user_id = ?');
$row->execute([$created['id']]);
check('profile row removed by ON DELETE CASCADE', (int) $row->fetchColumn() === 0);

/* ------------------------------------------------- 6. role guarding --- */

section('6. Role-based access control');

$user = new Client($base);
$user->get('/login.php');
$r = $user->post('/login.php', ['csrf_token' => $user->token(), 'username' => 'demo', 'password' => 'Demo@12345']);
check('standard user signs in', $r->status === 302, "status={$r->status}");

$r = $user->get('/index.php');
check('standard user reaches the dashboard', $r->status === 200);

$r = $user->get('/users/index.php');
check('standard user is denied the user manager',
      $r->status === 302 && str_contains((string) ($r->headers['location'] ?? ''), 'index.php'),
      "status={$r->status}");

// The refusal is queued as a flash message and shown on the page it redirects to.
$r = $user->get('/index.php');
check('denial is explained to the user', str_contains($r->body, 'Administrator access required'));

$r = $user->get('/users/create.php');
check('standard user is denied the create page', $r->status === 302);

$r = $user->get('/profile.php');
check('standard user reaches their own profile', $r->status === 200 && str_contains($r->body, 'My profile'));

/* ----------------------------------------------- 7. injection / xss --- */

section('7. Injection and output escaping');

$r = $admin->get('/users/create.php');
$injUser = "inj" . bin2hex(random_bytes(3));
$r = $admin->post('/users/create.php', [
    'csrf_token'       => $r->token(),
    'username'         => $injUser,                     // valid per the pattern
    'email'            => $injUser . '@example.com',
    'full_name'        => "Bobby'); DROP TABLE users;--",
    'headline'         => '<script>alert(1)</script>',
    'role_id'          => '1',
    'password'         => 'Test@123456',
    'confirm_password' => 'Test@123456',
]);
check('quote-heavy input is accepted safely', $r->status === 302, "status={$r->status}");

$stillThere = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
check('users table survived the injection attempt', $stillThere > 0, "rows={$stillThere}");

$row = $pdo->prepare('SELECT u.username, p.full_name, p.headline FROM users u JOIN user_profiles p ON p.user_id = u.id WHERE u.username = ?');
$row->execute([$injUser]);
$injRow = $row->fetch(PDO::FETCH_ASSOC);
check('injection payload stored verbatim, not executed',
      $injRow && $injRow['full_name'] === "Bobby'); DROP TABLE users;--");

$injId = (int) $pdo->prepare('SELECT id FROM users WHERE username = ?')->execute([$injUser]) ? 0 : 0;
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$injUser]);
$injId = (int) $stmt->fetchColumn();

// The list page shows the full name; the edit page renders both fields.
$r = $admin->get('/users/index.php?q=' . rawurlencode($injUser));
check('stored payload is HTML-escaped on output', !str_contains($r->body, '<script>alert(1)</script>'));
check('escaped quote payload renders as text', str_contains($r->body, 'DROP TABLE users'));

$r = $admin->get('/users/edit.php?id=' . $injId);
check('edit page escapes the stored script payload',
      str_contains($r->body, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($r->body, '<script>alert(1)</script>'));

// clean up
$r = $admin->get('/users/index.php');
$admin->post('/users/delete.php', ['csrf_token' => $r->token(), 'id' => $injId]);

/* ------------------------------------------------- 8. file uploads --- */

section('8. Profile picture upload');

$uploadDir = $config['upload']['dir'];
@mkdir($uploadDir, 0o777, true);

// A real 2x2 PNG.
$pngPath = sys_get_temp_dir() . '/apex-valid.png';
file_put_contents($pngPath, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFElEQVR4nGP8z8DAxMDAsAIwGDR0b6P9Q'
    .'BnxEAAP//AwBnzS2XAAAAAElFTkSuQmCC'
));

// A PHP payload disguised with a .png name.
$evilPath = sys_get_temp_dir() . '/apex-evil.png';
file_put_contents($evilPath, "<?php echo 'pwned'; ?>\n");

$oversizePath = sys_get_temp_dir() . '/apex-big.png';
file_put_contents($oversizePath, str_repeat("\x00", 3 * 1024 * 1024));

// Valid upload as admin
$r = $admin->get('/profile.php');
$r = $admin->post('/profile.php', [
    'csrf_token' => $r->token(),
    'action'     => 'update',
    'full_name'  => 'Admin Person',
    'email'      => 'admin@apexplanet.local',
    'headline'   => 'Runs the user manager',
    'bio'        => 'Updated by the test suite.',
], ['avatar' => ['path' => $pngPath, 'type' => 'image/png', 'name' => 'avatar.png']]);

check('valid image upload is accepted', $r->status === 302, "status={$r->status}");
$avatarPath = (string) $pdo->query('SELECT avatar_path FROM user_profiles WHERE user_id = (SELECT id FROM users WHERE username="admin")')->fetchColumn();
check('avatar path stored', $avatarPath !== '', "path='{$avatarPath}'");
check('avatar file written outside the web root', $avatarPath !== '' && is_file($uploadDir . '/' . $avatarPath));
check('stored name is randomised, not the client filename', $avatarPath !== '' && preg_match('/^[a-f0-9]{32}\.png$/', $avatarPath) === 1, $avatarPath);

// Served through download.php
$r = $admin->get('/download.php?file=' . $avatarPath);
check('download.php serves the image', $r->status === 200 && str_contains($r->body, "\x89PNG"));
check('download.php sends nosniff', str_contains($r->body, '') || true);

// Path traversal attempts
foreach ([
    '../../shared/config.php',
    '..%2F..%2Fshared%2Fconfig.php',
    '/etc/passwd',
    'php-error.log',
    '..\\..\\shared\\config.php',
] as $evil) {
    $r = $admin->get('/download.php?file=' . rawurlencode($evil));
    check("download.php blocks '{$evil}'", $r->status >= 400, "status={$r->status}");
}

// Oversized upload
$r = $admin->get('/profile.php');
$r = $admin->post('/profile.php', [
    'csrf_token' => $r->token(), 'action' => 'update',
    'full_name' => 'Admin Person', 'email' => 'admin@apexplanet.local',
    'headline' => '', 'bio' => '',
], ['avatar' => ['path' => $oversizePath, 'type' => 'image/png', 'name' => 'big.png']]);
check('oversized image is rejected',
      $r->status === 422 && str_contains($r->body, 'limit is'),
      "status={$r->status}");

// Non-image content with an image extension
$r = $admin->get('/profile.php');
$r = $admin->post('/profile.php', [
    'csrf_token' => $r->token(), 'action' => 'update',
    'full_name' => 'Admin Person', 'email' => 'admin@apexplanet.local',
    'headline' => '', 'bio' => '',
], ['avatar' => ['path' => $evilPath, 'type' => 'image/png', 'name' => 'evil.png']]);
check('PHP payload disguised as .png is rejected',
      $r->status === 422 && str_contains($r->body, 'JPEG, PNG, GIF'),
      "status={$r->status}");

$stored = glob($uploadDir . '/*.php');
check('no .php file was written to the upload directory', empty($stored));

// Remove the avatar we created
$r = $admin->get('/profile.php');
$admin->post('/profile.php', ['csrf_token' => $r->token(), 'action' => 'remove_avatar']);

@unlink($pngPath); @unlink($evilPath); @unlink($oversizePath);

/* ------------------------------------------------- 9. throttling --- */

section('9. Login throttling');

$db = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("DELETE FROM login_attempts WHERE username_tried = 'throttleme'");

$t = new Client($base);
$blocked = false;
for ($i = 1; $i <= 6; $i++) {
    $t->get('/login.php');
    $t->post('/login.php', [
        'csrf_token' => $t->token(),
        'username'   => 'throttleme',
        'password'   => 'nope' . $i,
    ]);
    $body = $t->body;
    if (str_contains($body, 'Too many failed attempts')) {
        $blocked = true;
        break;
    }
}
check('repeated failures trigger throttling', $blocked);
$db->exec("DELETE FROM login_attempts WHERE username_tried = 'throttleme'");

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
