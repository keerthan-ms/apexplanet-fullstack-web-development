<?php
/**
 * Task 5 — registration with email OTP verification.
 *
 * Flow
 *   1. POST /register.php with name, email and password.
 *   2. The account is created with email_verified = 0 and cannot sign in.
 *   3. A six digit code is issued and delivered (see lib/Otp.php).
 *   4. POST /verify.php with the code sets email_verified = 1.
 *   5. Only then does /login.php accept the account.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

$db     = Database::instance($APEX_CONFIG, 'db_lms');
$otp    = otp_service();
$errors = [];
$stage  = 'details';

$old = ['full_name' => '', 'email' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $old['full_name'] = post('full_name');
    $old['email']     = post('email');
    $password         = (string) ($_POST['password'] ?? '');
    $confirm          = (string) ($_POST['confirm_password'] ?? '');

    if (mb_strlen($old['full_name']) < 2 || mb_strlen($old['full_name']) > 80) {
        $errors['full_name'] = 'Enter your name (2 to 80 characters).';
    }
    $email = valid_email($old['email']);
    if ($email === null) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif ($db->value('SELECT id FROM users WHERE email = ?', [$email])) {
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
            'INSERT INTO users (full_name, email, password_hash, role, email_verified)
             VALUES (?, ?, ?, ?, 0)',
            [$old['full_name'], $email, password_hash($password, PASSWORD_DEFAULT), 'student']
        );
        $userId = $db->lastInsertId();

        $code = $otp->issue($userId, 'email_verification');
        if ($code === null) {
            flash('error', 'Too many codes requested. Try again in an hour.');
            redirect('login.php');
        }

        // Pending verification is held in the session, keyed to the user id so a
        // second registration in another tab cannot verify the wrong account.
        $_SESSION['pending_verification'] = $userId;

        flash('success', 'Account created. Enter the six digit code we sent to ' . $email . '.');
        redirect('verify.php');
    }
}

render_header('Create account', true, lms_nav());
?>
<div class="wrap narrow">
  <?php render_flashes(); ?>
  <h1>Create your account</h1>
  <p class="muted">
    We email a six digit code to confirm the address. Until it is verified,
    the account cannot sign in.
  </p>

  <?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
      <?= e(implode(' ', $errors)) ?>
    </div>
  <?php endif; ?>

  <form method="post" action="register.php" class="card form-grid" novalidate>
    <?= csrf_field() ?>

    <div class="field full">
      <label for="full_name">Full name</label>
      <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>"
             required maxlength="80" autocomplete="name" autofocus>
    </div>

    <div class="field full">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" value="<?= e($old['email']) ?>"
             required maxlength="254" autocomplete="email">
    </div>

    <div class="field">
      <label for="password">Password</label>
      <div class="input-group">
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <button class="btn btn-ghost" type="button" data-toggle-password="password" aria-label="Show password">Show</button>
      </div>
      <p class="hint">At least 8 characters, with a letter and a digit.</p>
    </div>

    <div class="field">
      <label for="confirm_password">Confirm password</label>
      <div class="input-group">
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
        <button class="btn btn-ghost" type="button" data-toggle-password="confirm_password" aria-label="Show password">Show</button>
      </div>
    </div>

    <div class="field full row">
      <button class="btn" type="submit">Create account</button>
      <a class="btn btn-ghost" href="login.php">I already have one</a>
    </div>
  </form>
</div>
<?php render_footer(); ?>
