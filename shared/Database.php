<?php
/**
 * Thin PDO wrapper used across tasks 2-5.
 *
 * Every query goes through prepared statements with bound parameters, so user
 * input can never be concatenated into SQL. That is the primary SQL injection
 * mitigation required by Task 3, and it is enforced here rather than left to
 * each page.
 */

declare(strict_types=1);

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct(array $cfg)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Never leak credentials or host details to the browser.
            error_log('DB connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Check shared/config.php.');
        }
    }

    public static function instance(?array $cfg = null): Database
    {
        if (self::$instance === null) {
            $cfg ??= require dirname(__DIR__) . '/shared/config.php';
            self::$instance = new self($cfg['db']);
        }
        return self::$instance;
    }

    /** @return PDO */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function value(string $sql, array $params = [])
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /** Escape output for safe rendering in HTML. */
    public static function e(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
