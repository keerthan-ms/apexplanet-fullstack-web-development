<?php
/**
 * Task 4 — schema migration runner.
 *
 *   php task-4/database/migrate.php
 *   php task-4/database/migrate.php --fresh
 *
 * Generates real password hashes for the seed accounts at run time rather than
 * storing them in the SQL file, so the committed schema never contains a hash
 * that could be mistaken for a real credential.
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
$db     = $config['db_shop'];

$dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
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
    foreach (['order_items', 'orders', 'products', 'categories', 'users'] as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `{$t}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// Real hashes, generated now.
$hashes = [
    '__HASH_ADMIN__' => password_hash('Admin@12345', PASSWORD_DEFAULT),
    '__HASH_DEMO__'  => password_hash('Demo@12345', PASSWORD_DEFAULT),
];

$sql = (string) file_get_contents(__DIR__ . '/schema.sql');
foreach ($hashes as $placeholder => $real) {
    $sql = str_replace($placeholder, $real, $sql);
}

if (str_contains($sql, '__HASH_')) {
    fwrite(STDERR, "A hash placeholder was not substituted. Aborting.\n");
    exit(1);
}

// Strip line comments before splitting: a comment block shares a chunk with
// the statement that follows it.
$sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);

$statements = array_values(array_filter(
    array_map('trim', explode(';', $sql)),
    static fn (string $s): bool => $s !== ''
));

$applied = 0;
foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
        $applied++;
    } catch (PDOException $e) {
        fwrite(STDERR, 'Failed statement: ' . substr(preg_replace('/\s+/', ' ', $statement), 0, 100) . "...\n");
        fwrite(STDERR, "  {$e->getMessage()}\n");
        exit(1);
    }
}

echo "Applied {$applied} statements.\n";

$tables = $pdo->query(
    'SELECT table_name FROM information_schema.TABLES WHERE table_schema = DATABASE() ORDER BY table_name'
)->fetchAll(PDO::FETCH_COLUMN);

echo 'Tables: ' . implode(', ', $tables) . "\n";
foreach ($tables as $t) {
    echo "  {$t}: " . $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn() . " row(s)\n";
}

$fks = $pdo->query(
    'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
     ORDER BY TABLE_NAME'
)->fetchAll(PDO::FETCH_ASSOC);

echo "\nForeign keys:\n";
foreach ($fks as $f) {
    echo sprintf("  %-12s %-14s -> %s\n", $f['TABLE_NAME'], $f['COLUMN_NAME'], $f['REFERENCED_TABLE_NAME']);
}

echo "\nDemo accounts:\n";
foreach (['admin' => 'Admin@12345', 'demo' => 'Demo@12345'] as $u => $pw) {
    $row = $pdo->prepare('SELECT password_hash, role FROM users WHERE username = ?');
    $row->execute([$u]);
    $r = $row->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        printf(
            "  %-6s %-6s password verifies: %s\n",
            $u,
            $r['role'],
            password_verify($pw, (string) $r['password_hash']) ? 'yes' : 'NO'
        );
    }
}

echo "\nMigration complete.\n";
