<?php
/**
 * Task 4 — authentication.
 *
 * Register, sign in, sign out and request a password reset. Every query is a
 * prepared statement and every password is hashed before storage.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

$db     = Database::instance($APEX_CONFIG, 'db_shop');
$errors = [];

$action = post('action');

/* ------------------------------------------------------------ register -- */

if ($action === 'register') {
    $username = post('username');
    $email    = post('email');
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.]{3,32}$/', $username)) {
        $errors['username'] = 'Use 3 to 32 characters: letters, digits, dot or underscore.';
    } elseif ($db->value('SELECT id FROM users WHERE username = ?', [$username])) {
        $errors['username'] = 'That username is taken.';
    }

    if (!valid_email($email)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif ($db->value('SELECT id FROM users WHERE email = ?', [strtolower($email)])) {
        $errors['email'] = 'That email address is already registered.';
    }

    $problems = password_problems($password);
    if ($problems) {
        $errors['password'] = 'Password ' . implode(', ', $problems) . '.';
    } elseif (!hash_equals($password, $confirm)) {
        $errors['confirm_password'] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $db->run(
            'INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)',
            [$username, strtolower($email), password_hash($password, PASSWORD_DEFAULT), 'user']
        );
        flash('success', 'Account created. You can sign in now.');
        redirect('login.php');
    }

    $_POST['register'] = '1';
}

/* --------------------------------------------------------------- login -- */

if ($action === 'login') {
    $identifier = post('identifier');
    $password   = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $errors['form'] = 'Enter your username or email, and your password.';
    } else {
        $user = $db->one(
            'SELECT id, username, password_hash, role FROM users
             WHERE username = ? OR email = ? LIMIT 1',
            [$identifier, strtolower($identifier)]
        );

        // Compare against a dummy hash when the account is unknown so timing
        // does not disclose whether the identifier exists.
        $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinva';
        $ok   = password_verify($password, $hash);

        if (!$user || !$ok) {
            $errors['form'] = 'Those credentials do not match our records.';
        } else {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $db->run('UPDATE users SET password_hash = ? WHERE id = ?', [
                    password_hash($password, PASSWORD_DEFAULT), $user['id'],
                ]);
            }
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['csrf']     = bin2hex(random_bytes(32));

            flash('success', 'Welcome back, ' . $user['username'] . '.');
            redirect('index.php');
        }
    }

    $_POST['login'] = '1';
}

/* ----------------------------------------------------- forgot password -- */

if ($action === 'forgot') {
    $email = post('email');

    if (!valid_email($email)) {
        $errors['forgot'] = 'Enter a valid email address.';
    } else {
        $user = $db->one('SELECT id, email FROM users WHERE email = ?', [strtolower($email)]);

        // Always report the same outcome, whether or not the address is known.
        $note = 'If that address has an account, a reset link has been sent.';

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + 3600;
            $_SESSION['reset_tokens'][(hash('sha256', $token))] = [
                'user_id' => (int) $user['id'],
                'expires' => $expires,
            ];

            $link = ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8084')
                . '/reset.php?token=' . $token . '&email=' . rawurlencode((string) $user['email']);

            // No mail transport is configured in this task, so the link is
            // written to the PHP error log. A real deployment would call mail()
            // or a provider here instead.
            error_log('[task-4] password reset link: ' . $link);
        }

        flash('success', $note);
        redirect('login.php');
    }

    $_POST['forgot'] = '1';
}

// Which form to show. A POST sets the mode from the action it came from; a
// direct visit to register.php or forgot.php must render that form too.
$mode = 'login';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register'])) { $mode = 'register'; }
    elseif (isset($_POST['forgot'])) { $mode = 'forgot'; }
} else {
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === 'register.php') { $mode = 'register'; }
    elseif ($script === 'forgot.php') { $mode = 'forgot'; }
}

render_header('Sign in', true, shop_nav());
?>
<div class="wrap narrow">
  <?php render_flashes(); ?>
  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
  <?php endif; ?>

  <?php if ($mode === 'register'): ?>
    <h1>Create an account</h1>
    <form method="post" action="register.php" class="card form-grid" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="register">

      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required pattern="[A-Za-z0-9_.]{3,32}" maxlength="32" autocomplete="username" autofocus>
        <?php if (!empty($errors['username'])): ?><p class="error-text"><?= e($errors['username']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required maxlength="254" autocomplete="email">
        <?php if (!empty($errors['email'])): ?><p class="error-text"><?= e($errors['email']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="input-group">
          <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
          <button class="btn btn-ghost" type="button" data-toggle-password="password" aria-label="Show password">Show</button>
        </div>
        <p class="hint">At least 8 characters, with a letter and a digit.</p>
        <?php if (!empty($errors['password'])): ?><p class="error-text"><?= e($errors['password']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="confirm_password">Confirm password</label>
        <div class="input-group">
          <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
          <button class="btn btn-ghost" type="button" data-toggle-password="confirm_password" aria-label="Show password">Show</button>
        </div>
        <?php if (!empty($errors['confirm_password'])): ?><p class="error-text"><?= e($errors['confirm_password']) ?></p><?php endif; ?>
      </div>

      <button class="btn" type="submit">Create account</button>
      <p class="muted small">Already have one? <a href="login.php">Sign in</a></p>
    </form>

  <?php elseif ($mode === 'forgot'): ?>
    <h1>Reset your password</h1>
    <form method="post" action="forgot.php" class="card form-grid" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="forgot">

      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" required maxlength="254" autocomplete="email" autofocus>
        <?php if (!empty($errors['forgot'])): ?><p class="error-text"><?= e($errors['forgot']) ?></p><?php endif; ?>
      </div>

      <button class="btn" type="submit">Send reset link</button>
      <p class="muted small"><a href="login.php">Back to sign in</a></p>
      <p class="hint">
        No mail transport is configured in this task, so the generated link is
        written to <code>_runtime/php/php-error.log</code>.
      </p>
    </form>

  <?php else: ?>
    <h1>Sign in</h1>
    <form method="post" action="login.php" class="card form-grid" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="login">

      <div class="field">
        <label for="identifier">Username or email</label>
        <input type="text" id="identifier" name="identifier" required maxlength="254" autocomplete="username" autofocus>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="input-group">
          <input type="password" id="password" name="password" required autocomplete="current-password">
          <button class="btn btn-ghost" type="button" data-toggle-password="password" aria-label="Show password">Show</button>
        </div>
      </div>

      <button class="btn" type="submit">Sign in</button>
      <p class="muted small"><a href="forgot.php">Forgot your password?</a></p>
      <p class="muted small">No account? <a href="register.php">Create one</a></p>
    </form>

    <div class="card">
      <p class="muted small">
        Demo accounts — <code>admin / Admin@12345</code> (administrator),
        <code>demo / Demo@12345</code> (customer). See the README.
      </p>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
