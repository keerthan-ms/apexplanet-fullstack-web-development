<?php
/**
 * Task 3 â€” registration.
 *
 * Hashes the password with password_hash() before it is ever stored, and never
 * writes the plain value anywhere.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

$db     = Database::instance($APEX_CONFIG, 'db_users');
$errors = [];
$old    = ['username' => '', 'email' => '', 'full_name' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $old['username']  = post('username');
    $old['email']     = post('email');
    $old['full_name'] = post('full_name');
    $password         = (string) ($_POST['password'] ?? '');
    $confirm          = (string) ($_POST['confirm_password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.]{3,32}$/', $old['username'])) {
        $errors['username'] = 'Use 3 to 32 characters: letters, digits, dot or underscore.';
    }
    if (!valid_email($old['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    }
    $passwordProblems = password_problems($password);
    if ($passwordProblems) {
        $errors['password'] = 'Password ' . implode(', ', $passwordProblems) . '.';
    }
    if (!hash_equals($password, $confirm)) {
        $errors['confirm_password'] = 'The two passwords do not match.';
    }
    if (!preg_match('/^.{0,60}$/u', $old['full_name'])) {
        $errors['full_name'] = 'Full name must be 60 characters or fewer.';
    }

    if (!$errors) {
        // Uniqueness is re-checked server-side; the client-side check in Task 2
        // is a convenience only.
        $clash = $db->one(
            'SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$old['username'], $old['email']]
        );

        if ($clash) {
            $errors[$clash['username'] === $old['username'] ? 'username' : 'email']
                = 'That ' . ($clash['username'] === $old['username'] ? 'username' : 'email') . ' is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $db->run('BEGIN');
            try {
                $db->run(
                    'INSERT INTO users (username, email, password_hash, role_id, is_active)
                     VALUES (?, ?, ?, 1, 1)',
                    [$old['username'], strtolower($old['email']), $hash]
                );
                $newId = $db->lastInsertId();

                $db->run(
                    'INSERT INTO user_profiles (user_id, full_name) VALUES (?, ?)',
                    [$newId, $old['full_name']]
                );
                $db->run('COMMIT');

                session_regenerate_id(true);
                $_SESSION['user_id']  = $newId;
                $_SESSION['username'] = $old['username'];
                $_SESSION['role']     = 'user';
                $_SESSION['csrf']     = bin2hex(random_bytes(32));

                flash('success', 'Account created. Welcome, ' . $old['username'] . '.');
                redirect('profile.php');
            } catch (Throwable $e) {
                $db->run('ROLLBACK');
                error_log('registration failed: ' . $e->getMessage());
                $errors['form'] = 'Could not create the account. Please try again.';
            }
        }
    }
}

$nav = ['index.php' => 'Dashboard', 'users/index.php' => 'Manage users', 'profile.php' => 'Profile'];

render_header('Create account', true, $nav);
?>
<div class="wrap narrow">
  <h1>Create account</h1>
  <p class="muted">Your password is hashed before it is stored. It is never saved in plain text.</p>

  <?php render_flashes(); ?>
  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
  <?php endif; ?>

  <form method="post" action="register.php" class="card form-grid" novalidate>
    <?= csrf_field() ?>

    <div class="field">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="<?= e($old['username']) ?>"
             required pattern="[A-Za-z0-9_.]{3,32}" maxlength="32" autocomplete="username" autofocus>
      <?php if (!empty($errors['username'])): ?><p class="error-text"><?= e($errors['username']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required maxlength="254" autocomplete="email">
      <?php if (!empty($errors['email'])): ?><p class="error-text"><?= e($errors['email']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="full_name">Full name</label>
      <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" maxlength="60" autocomplete="name">
      <?php if (!empty($errors['full_name'])): ?><p class="error-text"><?= e($errors['full_name']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <div class="input-group">
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <button class="btn btn-ghost" type="button" data-toggle-password="password" aria-label="Show password">Show</button>
      </div>
      <p class="hint">At least 8 characters, including a letter and a digit.</p>
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
    <p class="muted small">Already registered? <a href="login.php">Sign in</a>.</p>
  </form>
</div>
<?php render_footer(); ?>
