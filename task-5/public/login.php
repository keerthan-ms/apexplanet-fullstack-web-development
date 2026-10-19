<?php
/**
 * Task 5 — sign in.
 *
 * An unverified account is refused with a specific message, which is what makes
 * the OTP step meaningful rather than decorative.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

$db     = Database::instance($APEX_CONFIG, 'db_lms');
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email    = valid_email(post('email'));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === null) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($password === '') {
        $errors['password'] = 'Enter your password.';
    }

    if (!$errors) {
        $user = $db->one(
            'SELECT id, email, full_name, password_hash, role, email_verified FROM users WHERE email = ?',
            [$email]
        );

        // Compare against a dummy hash when unknown, so timing does not reveal
        // whether an address exists.
        $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
        $ok   = password_verify($password, $hash);

        if (!$user || !$ok) {
            $errors['form'] = 'Those credentials do not match our records.';
        } elseif ((int) $user['email_verified'] !== 1) {
            $_SESSION['pending_verification'] = (int) $user['id'];
            $errors['form'] = 'Verify your email address before signing in. We have resent the page where you can enter the code.';
        } else {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $db->run('UPDATE users SET password_hash = ? WHERE id = ?', [
                    password_hash($password, PASSWORD_DEFAULT), $user['id'],
                ]);
            }
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $user['id'];
            $_SESSION['username'] = (string) $user['full_name'];
            $_SESSION['role']     = (string) $user['role'];
            $_SESSION['csrf']     = bin2hex(random_bytes(32));

            flash('success', 'Welcome back, ' . $user['full_name'] . '.');
            redirect('index.php');
        }
    }
}

render_header('Sign in', true, lms_nav());
?>
<div class="wrap narrow">
  <?php render_flashes(); ?>
  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
  <?php endif; ?>

  <h1>Sign in</h1>

  <form method="post" action="login.php" class="card form-grid" novalidate>
    <?= csrf_field() ?>

    <div class="field full">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" value="<?= e(post('email')) ?>"
             required maxlength="254" autocomplete="username" autofocus>
      <?php if (!empty($errors['email'])): ?><p class="error-text"><?= e($errors['email']) ?></p><?php endif; ?>
    </div>

    <div class="field full">
      <label for="password">Password</label>
      <div class="input-group">
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <button class="btn btn-ghost" type="button" data-toggle-password="password" aria-label="Show password">Show</button>
      </div>
      <?php if (!empty($errors['password'])): ?><p class="error-text"><?= e($errors['password']) ?></p><?php endif; ?>
    </div>

    <div class="field full row">
      <button class="btn" type="submit">Sign in</button>
      <a class="btn btn-ghost" href="register.php">Create an account</a>
    </div>
  </form>

  <div class="card">
    <p class="muted small">
      Demo accounts — <code>admin@apexplanet.local / Admin@12345</code>,
      <code>demo@apexplanet.local / Demo@12345</code>.
    </p>
  </div>
</div>
<?php render_footer(); ?>
