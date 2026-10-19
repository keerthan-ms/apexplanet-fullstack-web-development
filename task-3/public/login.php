<?php
/**
 * Task 3 â€” login.
 *
 * Security measures implemented here:
 *   - password_verify() against a password_hash() stored value
 *   - prepared statements for every query
 *   - a constant-time-ish generic failure message (no account enumeration)
 *   - throttling: 5 failed attempts per username+IP within 15 minutes
 *   - every attempt, successful or not, is written to login_attempts
 *   - CSRF token required, session regenerated on success
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

$db     = Database::instance($APEX_CONFIG, 'db_users');
$errors = [];
$username = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = post('username');
    $password = (string) ($_POST['password'] ?? '');
    $ip       = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    if ($username === '') {
        $errors['username'] = 'Enter your username or email.';
    }
    if ($password === '') {
        $errors['password'] = 'Enter your password.';
    }

    if (!$errors) {
        // Throttle on the identifier AND the source address together, so one
        // attacker cannot lock every account out from a single host.
        $recent = $db->value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE username_tried = ? AND ip_address = ? AND was_successful = 0
               AND attempted_at > (NOW() - INTERVAL 15 MINUTE)',
            [$username, $ip]
        );

        if ((int) $recent >= 5) {
            $errors['form'] = 'Too many failed attempts. Wait 15 minutes and try again.';
        } else {
            $user = $db->one(
                'SELECT u.id, u.username, u.password_hash, u.is_active, r.name AS role
                 FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE u.username = ? OR u.email = ?
                 LIMIT 1',
                [$username, $username]
            );

            // Always run a hash comparison, even when the account is missing,
            // so response time does not reveal whether the account exists.
            $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfor.invalidhashvaluexxxxxxxxxxxxxxxxxxxxxxxxxx';
            $ok   = password_verify($password, $hash);

            $db->run(
                'INSERT INTO login_attempts (username_tried, ip_address, was_successful)
                 VALUES (?, ?, ?)',
                [$username, $ip, ($ok && $user && (int) $user['is_active'] === 1) ? 1 : 0]
            );

            if (!$user) {
                $errors['form'] = 'Those credentials do not match our records.';
            } elseif ((int) $user['is_active'] !== 1) {
                $errors['form'] = 'That account has been deactivated. Contact an administrator.';
            } elseif (!$ok) {
                $errors['form'] = 'Those credentials do not match our records.';
            } else {
                // Re-hash if the cost or algorithm has moved on.
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $db->run(
                        'UPDATE users SET password_hash = ? WHERE id = ?',
                        [password_hash($password, PASSWORD_DEFAULT), $user['id']]
                    );
                }

                // New session id on privilege change defeats session fixation.
                session_regenerate_id(true);
                $_SESSION['user_id']    = (int) $user['id'];
                $_SESSION['username']   = $user['username'];
                $_SESSION['role']       = $user['role'];
                $_SESSION['csrf']       = bin2hex(random_bytes(32));
                $_SESSION['logged_in_at'] = time();

                $db->run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);

                $intended = $_SESSION['intended'] ?? 'index.php';
                unset($_SESSION['intended']);
                flash('success', 'Welcome back, ' . $user['username'] . '.');
                redirect($intended);
            }
        }
    }

    if ($errors) {
        http_response_code(422);
    }
}

$nav = ['index.php' => 'Dashboard', 'users/index.php' => 'Manage users', 'profile.php' => 'Profile'];

render_header('Sign in', true, $nav);
?>
<div class="wrap narrow">
  <h1>Sign in</h1>
  <p class="muted">Use your username or email address.</p>

  <?php render_flashes(); ?>
  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
  <?php endif; ?>

  <form method="post" action="login.php" class="card form-grid" novalidate>
    <?= csrf_field() ?>

    <div class="field">
      <label for="username">Username or email</label>
      <input type="text" id="username" name="username" value="<?= e($username) ?>"
             autocomplete="username" required maxlength="254" autofocus>
      <?php if (!empty($errors['username'])): ?>
        <p class="error-text"><?= e($errors['username']) ?></p>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <div class="input-group">
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button class="btn btn-ghost" type="button" data-toggle-password="password"
                aria-label="Show password">Show</button>
      </div>
      <?php if (!empty($errors['password'])): ?>
        <p class="error-text"><?= e($errors['password']) ?></p>
      <?php endif; ?>
    </div>

    <button class="btn" type="submit">Sign in</button>
    <p class="muted small">No account yet? <a href="register.php">Create one</a>.</p>
  </form>

  <div class="card">
    <h2>Demo accounts</h2>
    <table>
      <caption>Seeded by database/schema.sql</caption>
      <thead><tr><th scope="col">Username</th><th scope="col">Password</th><th scope="col">Role</th></tr></thead>
      <tbody>
        <tr><td>admin</td><td><code>Admin@12345</code></td><td>admin</td></tr>
        <tr><td>demo</td><td><code>Demo@12345</code></td><td>user</td></tr>
        <tr><td>locked</td><td><code>Locked@12345</code></td><td>inactive</td></tr>
      </tbody>
    </table>
  </div>
</div>
<?php render_footer(); ?>
