<?php
/**
 * Task 3 — schema migration runner.
 *
 * Applies database/schema.sql to the Task 3 database. Safe to run repeatedly:
 * every statement is CREATE ... IF NOT EXISTS or an idempotent upsert.
 *
 *   php task-3/database/migrate.php
 *   php task-3/database/migrate.php --fresh     (drops first, losing all data)
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/shared/Database.php';
require_once $root . '/shared/helpers.php';

$fresh = in_array('--fresh', $argv ?? [], true);

if (!is_file($root . '/shared/config.php')) {
    fwrite(STDERR, "Missing shared/config.php. Run: npm run setup\n");
    exit(1);
}
$config = require $root . '/shared/config.php';
$db     = $config['db_users'];

$dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Cannot connect to MySQL: {$e->getMessage()}\n");
    exit(1);
}

$dbName = $db['name'];
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$dbName}`");
echo "Using database {$dbName}\n";

if ($fresh) {
    echo "--fresh requested, dropping existing tables\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['login_attempts', 'user_profiles', 'users', 'roles'] as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// Strip line comments before splitting, otherwise a comment block that shares
// a chunk with a CREATE TABLE would cause the DDL to be skipped.
$sql = (string) file_get_contents(__DIR__ . '/schema.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);

$statements = array_values(array_filter(
    array_map('trim', explode(';', (string) $sql)),
    static fn (string $s): bool => $s !== ''
));

$applied = 0;
foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
        $applied++;
    } catch (PDOException $e) {
        fwrite(STDERR, "Failed statement: " . substr(preg_replace('/\s+/', ' ', $statement), 0, 90) . "...\n");
        fwrite(STDERR, "  {$e->getMessage()}\n");
        exit(1);
    }
}

echo "Applied {$applied} statements.\n";

$tables = $pdo->query(
    'SELECT table_name FROM information_schema.TABLES WHERE table_schema = DATABASE() ORDER BY table_name'
)->fetchAll(PDO::FETCH_COLUMN);

echo 'Tables: ' . implode(', ', $tables) . "\n";

$counts = [];
foreach ($tables as $t) {
    $counts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
}
foreach ($counts as $t => $n) {
    echo "  {$t}: {$n} row(s)\n";
}

echo "\nDemo accounts (see task-3/README.md for the passwords):\n";
foreach (['admin', 'demo', 'locked'] as $u) {
    $row = $pdo->prepare(
        'SELECT u.username, u.email, r.name AS role, u.is_active
         FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ?'
    );
    $row->execute([$u]);
    $r = $row->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        echo sprintf(
            "  %-7s %-28s %-6s active=%d\n",
            $r['username'],
            $r['email'],
            $r['role'],
            (int) $r['is_active']
        );
    }
}

echo "\nMigration complete.\n";
