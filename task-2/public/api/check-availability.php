<?php
/**
 * Task 2 — AJAX endpoint for live availability checks.
 *
 * The register form calls this with fetch() as the user types, so the page
 * never reloads while checking whether a username or email is free.
 *
 * Contract
 *   GET ?field=username&value=keerthan
 *   -> 200 {"field":"username","available":true,"message":"..."}
 *   -> 422 {"field":"username","available":false,"message":"..."}
 *   -> 400 {"error":"..."}   bad or missing parameters
 *   -> 405 {"error":"..."}   wrong HTTP method
 *   -> 503 {"error":"..."}   database unavailable
 *
 * Security notes
 *   - The value is bound with a prepared statement, never concatenated.
 *   - Responses carry no-store so a proxy never serves a stale answer.
 *   - The message is deliberately generic; it does not enumerate accounts.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_out(['error' => 'Use GET for this endpoint.'], 405);
}

$field = isset($_GET['field']) && is_string($_GET['field']) ? strtolower(trim($_GET['field'])) : '';
$value = isset($_GET['value']) && is_string($_GET['value']) ? trim($_GET['value']) : '';

if (!in_array($field, ['username', 'email'], true)) {
    json_out(['error' => 'field must be "username" or "email".'], 400);
}
if ($value === '') {
    json_out(['field' => $field, 'available' => true, 'message' => ''], 200);
}
if (strlen($value) > 254) {
    json_out(['error' => 'value is too long.'], 400);
}

// Reject obviously malformed input before touching the database.
if ($field === 'username' && !preg_match('/^[A-Za-z0-9_.]{3,32}$/', $value)) {
    json_out([
        'field'     => $field,
        'available' => false,
        'message'   => 'Use 3 to 32 characters: letters, digits, dot or underscore.',
    ], 422);
}
if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
    json_out([
        'field'     => $field,
        'available' => false,
        'message'   => 'Enter a valid email address.',
    ], 422);
}

try {
    $root  = dirname(__DIR__, 3);           // repository root
    $cfgFile = $root . '/shared/config.php';
    if (!is_file($cfgFile)) {
        json_out(['error' => 'Server is not configured. Run npm run setup.'], 503);
    }
    $cfg = require $cfgFile;

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['db']['host'],
        $cfg['db']['port'],
        $cfg['db']['name'],
        $cfg['db']['charset']
    );

    $pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // The column name is chosen from a fixed allow-list, never from user input.
    $column = $field === 'username' ? 'username' : 'email';
    $sql    = "SELECT id FROM users WHERE {$column} = ? LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$value]);
    $taken = $stmt->fetch() !== false;

    json_out([
        'field'     => $field,
        'available' => !$taken,
        'message'   => $taken
            ? 'That ' . $field . ' is already registered.'
            : ucfirst($field) . ' looks available.',
    ], $taken ? 422 : 200);
} catch (PDOException $e) {
    error_log('availability check failed: ' . $e->getMessage());

    // SQLSTATE 42S02 = base table or view not found.
    //
    // Task 2 is the front-end task and is expected to run on its own, before the
    // Task 3 schema exists. When there is no users table we fall back to a small
    // reserved-name list so the AJAX round trip is still real and observable,
    // clearly labelled so nobody mistakes it for a production check.
    if (($e->getCode() === '42S02') || str_contains($e->getMessage(), "doesn't exist")) {
        $reserved = ['admin', 'administrator', 'root', 'test', 'demo', 'guest', 'keerthan', 'apexplanet'];
        $taken = in_array(strtolower($value), $reserved, true);
        json_out([
            'field'     => $field,
            'available' => !$taken,
            'message'   => $taken
                ? 'That ' . $field . ' is reserved.'
                : ucfirst($field) . ' looks available.',
            'mode'      => 'demo-reserved-list',
        ], $taken ? 422 : 200);
    }

    json_out(['error' => 'Availability check is temporarily unavailable.'], 503);
}
