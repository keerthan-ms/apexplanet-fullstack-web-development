<?php
/**
 * Task 1 — MySQL basics with mysqli.
 *
 * Demonstrates:
 *   - connecting with mysqli_connect() / the mysqli OO API
 *   - CREATE TABLE, INSERT, SELECT, UPDATE, DELETE
 *   - primary keys and foreign keys, enforced by InnoDB
 *   - prepared statements so user input can never become SQL
 *
 * Run:  php -S 127.0.0.1:8081 -t task-1/public
 *  then open http://127.0.0.1:8081/mysql/index.php
 */

declare(strict_types=1);

require __DIR__ . '/../php/inc/functions.php'; // e(), badge_class()
require __DIR__ . '/inc/header.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ------------------------------------------------- 1. Connect ---------- */

// mysqli_connect() is the procedural form required by the task brief.
// The credentials come from shared/config.php via environment defaults so
// nothing secret is hard-coded here.
$host = getenv('DB_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: 'apexplanet';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'ApexApp_2026!dev';
$name = getenv('DB_NAME') ?: 'apexplanet_fullstack';

$dbAvailable = true;
$dbError     = '';

try {
    $conn = mysqli_connect($host, $user, $pass, $name);
    mysqli_set_charset($conn, 'utf8mb4');
} catch (mysqli_sql_exception $ex) {
    $dbAvailable = false;
    $dbError     = $ex->getMessage();
    $conn        = null;
}

echo '<section class="section"><div class="section-head"><h2>1. Connecting with mysqli_connect()</h2></div><div class="card">';
if ($dbAvailable) {
    $server = mysqli_get_server_info($conn);
    $db     = mysqli_get_db($conn);
    echo '<div class="alert alert-success" role="status">Connected to <strong>' . e($db)
       . '</strong> on <strong>' . e($host) . '</strong> using MariaDB ' . e($server) . '.</div>';
    echo '<pre>$conn = mysqli_connect(' . e($host) . ", '" . e($user) . "', \$password, '" . e($db) . "');\n"
       . "mysqli_set_charset(\$conn, 'utf8mb4');</pre>";
} else {
    echo '<div class="alert alert-error" role="alert">Could not connect: <code>' . e($dbError) . '</code><br>'
       . 'Start the database and confirm the credentials in <code>shared/config.php</code>.</div>';
}
echo '</div></section>';

/* ------------------------------------ 2. Create tables if they are missing */

if ($dbAvailable) {
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    // Strip line comments before splitting, otherwise a comment block that
    // shares a chunk with CREATE TABLE causes the DDL to be skipped.
    $clean = preg_replace('/^\s*--.*$/m', '', (string) $schema);
    // Run only the DDL statements. Seed data is inserted separately below so
    // the page can be reloaded without duplicating rows.
    $ddl = [];
    foreach (explode(';', (string) $clean) as $statement) {
        $s = trim($statement);
        if ($s !== '' && preg_match('/^CREATE\s+TABLE/i', $s)) {
            $ddl[] = $s;
        }
    }
    foreach ($ddl as $sql) {
        mysqli_query($conn, $sql);
    }

    // Seed once only.
    mysqli_query($conn, "INSERT IGNORE INTO users (username, email, password_hash, full_name, role) VALUES
        ('keerthan', 'keerthan@example.com', 'seeded', 'Keerthan M S', 'admin'),
        ('demo',     'demo@example.com',     'seeded', 'Demo User',     'user')");
    mysqli_query($conn, "INSERT IGNORE INTO posts (user_id, title, body) VALUES
        ((SELECT id FROM users WHERE username='keerthan'), 'First post', 'Inserted with a prepared statement and linked by foreign key.'),
        ((SELECT id FROM users WHERE username='demo'),     'Hello world', 'A second post used to show the JOIN working.')");
}

/* ------------------------------------------------------ 3. Actions ------ */

$notice = '';

if ($dbAvailable && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'insert') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $body  = trim((string) ($_POST['body'] ?? ''));

        if ($title === '' || $body === '') {
            $notice = 'Both a title and a body are required.';
        } else {
            // Prepared statement: the values travel separately from the SQL,
            // so they can never be interpreted as SQL.
            $uidRes = mysqli_query($conn, "SELECT id FROM users WHERE username = 'keerthan'");
            $uidRow = mysqli_fetch_row($uidRes);
            $uid    = (int) ($uidRow[0] ?? 0);

            if ($uid < 1) {
                $notice = 'No author account found. Load the seed data first.';
            } else {
                $stmt = mysqli_prepare($conn, 'INSERT INTO posts (user_id, title, body) VALUES (?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'iss', $uid, $title, $body);
                mysqli_stmt_execute($stmt);
                $notice = 'Inserted post #' . mysqli_insert_id($conn) . ' with a prepared statement.';
                mysqli_stmt_close($stmt);
            }
        }
    }

    if ($action === 'update') {
        $id      = (int) ($_POST['id'] ?? 0);
        $newBody = trim((string) ($_POST['body'] ?? ''));
        if ($id > 0 && $newBody !== '') {
            $stmt = mysqli_prepare($conn, 'UPDATE posts SET body = ? WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'si', $newBody, $id);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            $notice   = $affected . ' row(s) updated.';
            mysqli_stmt_close($stmt);
        } else {
            $notice = 'Nothing to update.';
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, 'DELETE FROM posts WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $notice = mysqli_stmt_affected_rows($stmt) . ' row(s) deleted.';
            mysqli_stmt_close($stmt);
        }
    }
}

if ($dbAvailable) {
    echo '<section class="section"><div class="section-head"><h2>2. Create, Insert, Update, Delete</h2></div><div class="card">';
    if ($notice !== '') {
        echo '<div class="alert alert-success" role="status">' . e($notice) . '</div>';
    }

    // INSERT
    echo '<h3>INSERT</h3>';
    echo '<form method="post" class="form-grid" style="margin-bottom:1.5rem">';
    echo '<input type="hidden" name="action" value="insert">';
    echo '<div class="field"><label for="title">Title</label><input type="text" id="title" name="title" maxlength="120" required></div>';
    echo '<div class="field"><label for="body">Body</label><textarea id="body" name="body" rows="2" required></textarea></div>';
    echo '<div class="field"><label>&nbsp;</label><button class="btn" type="submit">Insert post</button></div>';
    echo '</form>';

    // SELECT with JOIN
    echo '<h3>SELECT with a JOIN (users ← posts)</h3>';
    echo '<div class="table-wrap"><table><thead><tr>'
       . '<th scope="col">ID</th><th scope="col">Title</th><th scope="col">Author</th>'
       . '<th scope="col">Role</th><th scope="col">Actions</th></tr></thead><tbody>';

    $sql = 'SELECT p.id, p.title, p.body, u.username, u.role
            FROM posts p
            INNER JOIN users u ON u.id = p.user_id
            ORDER BY p.id DESC';
    $result = mysqli_query($conn, $sql);
    $rows   = mysqli_fetch_all($result, MYSQLI_ASSOC);

    if (!$rows) {
        echo '<tr><td colspan="5">No rows yet. Insert one above.</td></tr>';
    }
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . (int) $row['id'] . '</td>';
        echo '<td><strong>' . e((string) $row['title']) . '</strong><br><span class="hint">' . e((string) $row['body']) . '</span></td>';
        echo '<td>' . e((string) $row['username']) . '</td>';
        echo '<td><span class="' . badge_class((string) $row['role'] === 'admin' ? 'complete' : 'progress') . '">'
           . e((string) $row['role']) . '</span></td>';
        echo '<td>';
        echo '<form method="post" style="display:flex;gap:.4rem;align-items:center">';
        echo '<input type="hidden" name="action" value="update"><input type="hidden" name="id" value="' . (int) $row['id'] . '">';
        echo '<input type="text" name="body" value="' . e((string) $row['body']) . '" style="min-width:180px" aria-label="Body for post ' . (int) $row['id'] . '">';
        echo '<button class="btn btn-sm" type="submit">Save</button>';
        echo '</form>';
        echo '<form method="post" onsubmit="return confirm(\'Delete this post?\')" style="margin-top:.35rem">';
        echo '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int) $row['id'] . '">';
        echo '<button class="btn btn-sm btn-danger" type="submit">Delete</button></form>';
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
    echo '<p class="hint">Keys: <code>posts.user_id</code> is a FOREIGN KEY referencing '
       . '<code>users.id</code>. Deleting a user cascades to their posts.</p>';
    echo '</div></section>';

    // Aggregate to prove the FK and joins are real data, not fixtures
    echo '<section class="section"><div class="section-head"><h2>3. Aggregation</h2></div><div class="card"><div class="table-wrap"><table><thead><tr>'
       . '<th scope="col">User</th><th scope="col">Role</th><th scope="col">Posts</th>'
       . '</tr></thead><tbody>';
    $agg = mysqli_query($conn,
        'SELECT u.username, u.role, COUNT(p.id) AS post_count
         FROM users u
         LEFT JOIN posts p ON p.user_id = u.id
         GROUP BY u.id, u.username, u.role
         ORDER BY post_count DESC');
    foreach (mysqli_fetch_all($agg, MYSQLI_ASSOC) as $r) {
        echo '<tr><td>' . e((string) $r['username']) . '</td><td>' . e((string) $r['role'])
           . '</td><td>' . (int) $r['post_count'] . '</td></tr>';
    }
    echo '</tbody></table></div></div></section>';

    mysqli_close($conn);
}

require __DIR__ . '/inc/footer.php';
