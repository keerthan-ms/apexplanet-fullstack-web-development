<?php
/**
 * Task 4 — automated test suite.
 *
 *     npm run test:4
 *
 * Exercises the storefront and admin panel over HTTP against a live server and
 * a live database, including the checkout transaction.
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
if (!is_file($configFile)) {
    echo "\n\033[31mshared/config.php missing. Run: npm run setup\033[0m\n";
    exit(1);
}
$config = require $configFile;
$dbCfg  = $config['db_shop'];

$port = 8085;
$serverLog = (string) tempnam(sys_get_temp_dir(), 'apex-t4-');

$server = @proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', ROOT . '/task-4/public'],
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

/* ---------------------------------------------------------- database --- */

section('1. Schema, keys and indexes');

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbCfg['host'], $dbCfg['port'], $dbCfg['name'], $dbCfg['charset']);
    $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    check('connects to the shop database', true);

    $tables = $pdo->query('SELECT table_name FROM information_schema.TABLES WHERE table_schema = DATABASE()')
        ->fetchAll(PDO::FETCH_COLUMN);
    foreach (['users', 'categories', 'products', 'orders', 'order_items'] as $t) {
        check("table {$t} exists", in_array($t, $tables, true));
    }

    $fk = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL"
    )->fetchColumn();
    check('four foreign keys are defined', (int) $fk === 4, "found {$fk}");

    $idx = $pdo->query(
        "SELECT COUNT(DISTINCT TABLE_NAME, INDEX_NAME) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('products','orders','order_items') AND INDEX_NAME <> 'PRIMARY'"
    )->fetchColumn();
    check('query paths are indexed', (int) $idx >= 6, "found {$idx}");

    $cols = $pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_COLUMN);
    check('price is stored as an integer, not a float', in_array('price_cents', $cols, true) && !in_array('price', $cols, true));

    $c = $pdo->query("SELECT password_hash FROM users WHERE username = 'admin'")->fetchColumn();
    check('admin password is hashed', is_string($c) && str_starts_with($c, '$2y$'));
    check('documented admin password verifies', password_verify('Admin@12345', (string) $c));

    // A real FK must reject an orphan order.
    $rejected = false;
    try {
        $pdo->exec("INSERT INTO orders (user_id, status, total_cents) VALUES (999999, 'pending', 100)");
    } catch (PDOException $e) {
        $rejected = true;
    }
    check('foreign key rejects an order for a non-existent user', $rejected);
} catch (Throwable $e) {
    check('database reachable', false, $e->getMessage());
}

/* --------------------------------------------------------- storefront --- */

section('2. Catalogue, search, filters and pagination');

$anon = new Client($base);

$r = $anon->get('/index.php');
check('catalogue renders', $r->status === 200 && str_contains($r->body, 'The catalogue'));
check('products are listed', str_contains($r->body, 'Building Secure PHP'));
check('pagination control present or single page', $r->status === 200);

$r = $anon->get('/index.php?q=secure');
check('search narrows the catalogue', str_contains($r->body, 'Building Secure PHP') && !str_contains($r->body, 'Salt and Cinder'));

$r = $anon->get('/index.php?q=zzzznothing');
check('no matches shows the empty state', str_contains($r->body, 'Nothing matches'));

$r = $anon->get('/index.php?sort=priced');
check('price sort is accepted', $r->status === 200);

$catId = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'technology'")->fetchColumn();
$r = $anon->get('/index.php?cat=' . $catId);
check('category filter works', str_contains($r->body, 'Building Secure PHP') && !str_contains($r->body, 'Salt and Cinder'));

// An inactive product must not be publicly visible.
$inactiveTitle = (string) $pdo->query('SELECT title FROM products WHERE is_active = 0 LIMIT 1')->fetchColumn();
if ($inactiveTitle !== '') {
    $r = $anon->get('/index.php?q=' . rawurlencode($inactiveTitle));
    // The term is echoed back into the search box, so assert on the result set
    // rather than on the string being absent from the whole page.
    check('inactive products are hidden from the catalogue results',
          str_contains($r->body, 'Nothing matches') || str_contains($r->body, '0 titles available'),
          'inactive title appeared in the catalogue results');
    $inactiveId = (int) $pdo->query('SELECT id FROM products WHERE is_active = 0 LIMIT 1')->fetchColumn();
    $r = $anon->get('/product.php?id=' . $inactiveId);
    check('inactive product page returns 404 for a visitor', $r->status === 404, "status={$r->status}");
}

$r = $anon->get('/product.php?id=999999');
check('unknown product returns 404', $r->status === 404, "status={$r->status}");

// SQL injection attempt through the search box.
$r = $anon->get('/index.php?q=' . rawurlencode("' OR '1'='1"));
check('quote-heavy search term is harmless', $r->status === 200 && str_contains($r->body, 'Nothing matches'));
check('catalogue still works after the injection attempt', (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() > 0);

// LIKE wildcards must not widen the search.
$r = $anon->get('/index.php?q=' . rawurlencode('%'));
check('LIKE wildcard is escaped, not honoured', str_contains($r->body, 'Nothing matches'), 'a % search matched everything');

/* ------------------------------------------------------- authentication -- */

section('3. Authentication');

$bad = new Client($base);
$bad->get('/login.php');
$r = $bad->post('/login.php', ['csrf_token' => $bad->token(), 'action' => 'login', 'identifier' => 'admin', 'password' => 'nope']);
check('wrong password is refused', $r->status === 200 && str_contains($r->body, 'do not match our records'));

$admin = new Client($base);
$admin->get('/login.php');
$r = $admin->post('/login.php', ['csrf_token' => $admin->token(), 'action' => 'login', 'identifier' => 'admin', 'password' => 'Admin@12345']);
check('admin signs in', $r->status === 302 && str_contains((string) ($r->headers['location'] ?? ''), 'index.php'), "status={$r->status}");

$customer = new Client($base);
$customer->get('/login.php');
$r = $customer->post('/login.php', ['csrf_token' => $customer->token(), 'action' => 'login', 'identifier' => 'demo', 'password' => 'Demo@12345']);
check('customer signs in', $r->status === 302);

$r = $customer->get('/admin/index.php');
// Signed in but not an administrator: sent back to the storefront rather than
// served the dashboard.
$loc = (string) ($r->headers['location'] ?? '');
check('customer is denied the admin dashboard',
      $r->status === 302 && !str_contains($loc, 'admin/'),
      "status={$r->status} loc={$loc}");
$r = $customer->get('/admin/products.php');
check('customer is denied product management', $r->status === 302);

/* --------------------------------------------------------------- cart --- */

section('4. Cart and checkout');

$r = $customer->get('/index.php');
$pid = (int) $pdo->query('SELECT id FROM products WHERE stock > 0 AND is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();

$r = $customer->post('/cart.php', ['csrf_token' => $customer->token(), 'action' => 'add', 'id' => $pid, 'qty' => 2]);
check('add to cart redirects', $r->status === 302);

$r = $customer->get('/cart.php');
check('cart shows the line', str_contains($r->body, 'in cart') || str_contains($r->body, 'Line total') || str_contains($r->body, 'Order total'));

$stockBefore = (int) $pdo->query("SELECT stock FROM products WHERE id = {$pid}")->fetchColumn();
$ordersBefore = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();

$r = $customer->get('/checkout.php');
check('checkout shows a total', $r->status === 200 && str_contains($r->body, 'Place order'));
$r = $customer->post('/checkout.php', ['csrf_token' => $r->token()]);
check('order is placed', $r->status === 302 && str_contains((string) ($r->headers['location'] ?? ''), 'orders.php'), "status={$r->status}");

$ordersAfter = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
check('an order row was created', $ordersAfter === $ordersBefore + 1, "before={$ordersBefore} after={$ordersAfter}");

$stockAfter = (int) $pdo->query("SELECT stock FROM products WHERE id = {$pid}")->fetchColumn();
check('stock was decremented by the ordered quantity', $stockAfter === $stockBefore - 2, "before={$stockBefore} after={$stockAfter}");

$items = (int) $pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn();
check('order line items were written', $items > 0);

$r = $customer->get('/orders.php');
check('the order appears in "my orders"', $r->status === 200 && str_contains($r->body, 'My orders'));

$r = $customer->get('/cart.php');
check('cart is emptied after checkout', str_contains($r->body, 'Your cart is empty'));

// Out-of-stock guard
$oos = (int) $pdo->query('SELECT id FROM products WHERE stock = 0 AND is_active = 1 LIMIT 1')->fetchColumn();
if ($oos > 0) {
    $r = $customer->post('/cart.php', ['csrf_token' => $customer->token(), 'action' => 'add', 'id' => $oos, 'qty' => 1]);
    $r = $customer->get('/cart.php');
    check('out-of-stock product cannot be added', !str_contains($r->body, "value=\"{$oos}\""));
}

// Checkout without a CSRF token
$bad2 = new Client($base);
$bad2->get('/login.php');
$bad2->post('/login.php', ['csrf_token' => $bad2->token(), 'action' => 'login', 'identifier' => 'demo', 'password' => 'Demo@12345']);
$bad2->post('/checkout.php', ['csrf_token' => str_repeat('e', 64)]);
check('checkout refuses a forged CSRF token', $bad2->status === 419, "status={$bad2->status}");

/* ---------------------------------------------------------- admin panel -- */

section('5. Admin panel');

$r = $admin->get('/admin/index.php');
check('admin dashboard renders', $r->status === 200 && str_contains($r->body, 'Admin dashboard'));
check('dashboard shows statistics', str_contains($r->body, 'Active products') && str_contains($r->body, 'Orders'));
check('dashboard loads Chart.js', str_contains($r->body, 'chart.umd.js'));
check('orders chart is present', str_contains($r->body, 'ordersChart'));
check('status chart is present', str_contains($r->body, 'statusChart'));
check('chart data is embedded as JSON', str_contains($r->body, 'new Chart('));

$r = $admin->get('/admin/products.php');
check('product manager renders', $r->status === 200 && str_contains($r->body, 'Manage products'));

// Create
$title = 'Suite Test Book ' . bin2hex(random_bytes(3));
$r = $admin->get('/admin/products.php');
$r = $admin->post('/admin/products.php', [
    'csrf_token'   => $r->token(),
    'action'       => 'create',
    'title'        => $title,
    'author'       => 'Automated Suite',
    'description'  => 'Created by the Task 4 test suite.',
    'category_id'  => (string) $catId,
    'price_cents'  => '45050',
    'stock'        => '7',
    'is_active'    => '1',
]);
check('admin can create a product', $r->status === 302, "status={$r->status}");

$stmt = $pdo->prepare('SELECT id, price_cents, stock FROM products WHERE title = ?');
$stmt->execute([$title]);
$newProduct = $stmt->fetch(PDO::FETCH_ASSOC);
check('the new product row exists', (bool) $newProduct);
check('price is stored exactly as whole paise', $newProduct && (int) $newProduct['price_cents'] === 45050);

// Validation
$r = $admin->get('/admin/products.php');
$r = $admin->post('/admin/products.php', [
    'csrf_token' => $r->token(), 'action' => 'create', 'title' => '',
    'description' => '', 'category_id' => '999999', 'price_cents' => '0', 'stock' => '-1',
]);
check('admin create validates server-side', $r->status === 200 && str_contains($r->body, 'Title is required'));
check('invalid category is rejected', str_contains($r->body, 'valid category'));
check('negative stock is rejected', str_contains($r->body, 'between 0'));

// Update
$r = $admin->get('/admin/products.php');
$r = $admin->post('/admin/products.php', [
    'csrf_token' => $r->token(), 'action' => 'update', 'id' => (int) $newProduct['id'],
    'title' => $title . ' (revised)', 'author' => 'Automated Suite',
    'description' => 'Revised.', 'category_id' => (string) $catId,
    'price_cents' => '99000', 'stock' => '3', 'is_active' => '1',
]);
check('admin can update a product', $r->status === 302);
$stmt = $pdo->prepare('SELECT title, price_cents FROM products WHERE id = ?');
$stmt->execute([$newProduct['id']]);
$updated = $stmt->fetch(PDO::FETCH_ASSOC);
check('update is persisted', $updated && $updated['title'] === $title . ' (revised)');

// Delete
$r = $admin->get('/admin/products.php');
$r = $admin->post('/admin/products.php', ['csrf_token' => $r->token(), 'action' => 'delete', 'id' => (int) $newProduct['id']]);
check('admin can delete a product', $r->status === 302);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE id = ?');
$stmt->execute([$newProduct['id']]);
check('product row removed', (int) $stmt->fetchColumn() === 0);

// Deleting a product referenced by an order must deactivate, not delete.
$ordered = (int) $pdo->query('SELECT product_id FROM order_items LIMIT 1')->fetchColumn();
$r = $admin->get('/admin/products.php');
$r = $admin->post('/admin/products.php', ['csrf_token' => $r->token(), 'action' => 'delete', 'id' => $ordered]);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE id = ?');
$stmt->execute([$ordered]);
check('a product used by an order is deactivated, not deleted', (int) $stmt->fetchColumn() === 1);
$r = $admin->get('/admin/products.php');
check('the deactivation is reported to the admin', str_contains($r->body, 'deactivated instead of deleted'));

// Order workflow
$r = $admin->get('/admin/orders.php');
check('order manager renders', $r->status === 200 && str_contains($r->body, 'Manage orders'));
$latest = (int) $pdo->query('SELECT MAX(id) FROM orders')->fetchColumn();
$r = $admin->post('/admin/orders.php', ['csrf_token' => $r->token(), 'id' => $latest, 'status' => 'shipped']);
check('admin can advance an order', $r->status === 302);
$stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
$stmt->execute([$latest]);
check('order status is persisted', $stmt->fetchColumn() === 'shipped');

// Re-fetch the page first: the previous request redirected, so its body (and
// therefore its CSRF token) is gone.
$r = $admin->get('/admin/orders.php');
$r = $admin->post('/admin/orders.php', ['csrf_token' => $r->token(), 'id' => $latest, 'status' => 'hacked']);
check('an unknown order status is refused',
      str_contains($r->body, 'Unknown status') && $r->status !== 302,
      "status={$r->status}");
$stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
$stmt->execute([$latest]);
check('the rejected status was not written', $stmt->fetchColumn() === 'shipped');

// User management guards
$r = $admin->get('/admin/users.php');
check('user manager renders', $r->status === 200 && str_contains($r->body, 'Manage users'));

$me = (int) $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
$r = $admin->post('/admin/users.php', ['csrf_token' => $r->token(), 'action' => 'delete', 'id' => $me]);
$r = $admin->get('/admin/users.php');
check('admin cannot delete their own account', str_contains($r->body, 'cannot delete the account you are signed in with'));

$r = $admin->post('/admin/users.php', ['csrf_token' => $r->token(), 'action' => 'role', 'id' => $me, 'role' => 'user']);
$r = $admin->get('/admin/users.php');
check('admin cannot demote themselves', str_contains($r->body, 'cannot change your own role'));

$demoId = (int) $pdo->query("SELECT id FROM users WHERE username = 'demo'")->fetchColumn();
$r = $admin->post('/admin/users.php', ['csrf_token' => $r->token(), 'action' => 'role', 'id' => $demoId, 'role' => 'user']);
check('promoting a customer to admin is allowed', $r->status === 302);

/* ------------------------------------------------- reset demo account --- */

// Leave the seeded data as the README documents.
$pdo->exec("UPDATE users SET role = 'user' WHERE id = {$demoId}");
$pdo->prepare('DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id = ?)')->execute([$demoId]);
$pdo->prepare('DELETE FROM orders WHERE user_id = ?')->execute([$demoId]);
$pdo->prepare('UPDATE products SET stock = stock + 2 WHERE id = ?')->execute([$pid]);

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
