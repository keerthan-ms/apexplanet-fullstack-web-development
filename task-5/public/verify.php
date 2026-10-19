<?php
/**
 * Task 5 — email OTP verification endpoint.
 *
 * The code is checked against a SHA-256 hash; the plaintext is never read back
 * out of the database. A code can be used once, expires, and is rate limited.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

$db     = Database::instance($APEX_CONFIG, 'db_lms');
$otp    = otp_service();
$errors = [];

$userId = (int) ($_SESSION['pending_verification'] ?? 0);

if ($userId < 1) {
    flash('error', 'Start by creating an account.');
    redirect('register.php');
}

$account = $db->one('SELECT id, email, full_name, email_verified FROM users WHERE id = ?', [$userId]);

if (!$account) {
    unset($_SESSION['pending_verification']);
    flash('error', 'That account no longer exists.');
    redirect('register.php');
}

if ((int) $account['email_verified'] === 1) {
    unset($_SESSION['pending_verification']);
    flash('info', 'That address is already verified. Please sign in.');
    redirect('login.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('action') === 'resend') {
    $code = $otp->issue($userId, 'email_verification');
    if ($code === null) {
        flash('error', 'Too many codes requested. Try again in an hour.');
    } else {
        flash('success', 'A new code has been sent to ' . $account['email'] . '.');
    }
    redirect('verify.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $result = $otp->verify($userId, (string) ($_POST['code'] ?? ''), 'email_verification');

    if ($result['ok']) {
        $db->run('UPDATE users SET email_verified = 1 WHERE id = ?', [$userId]);
        unset($_SESSION['pending_verification']);

        session_regenerate_id(true);
        $_SESSION['user_id']    = $userId;
        $_SESSION['username']   = (string) $account['full_name'];
        $_SESSION['role']       = 'student';
        $_SESSION['csrf']       = bin2hex(random_bytes(32));

        flash('success', 'Email verified. Welcome to ApexPlanet Learn.');
        redirect('index.php');
    }

    $errors['code'] = $result['reason'];
}

$ttl = $otp->ttlRemaining($userId, 'email_verification');
$transport = (string) ($APEX_CONFIG['security']['mail_transport'] ?? 'console');

render_header('Verify your email', true, lms_nav());
?>
<div class="wrap narrow">
  <?php render_flashes(); ?>
  <h1>Confirm your email</h1>
  <p class="muted">
    We sent a six digit code to <strong><?= e((string) $account['email']) ?></strong>.
    <?php if ($ttl !== null): ?>
      It expires in <?= (int) ceil($ttl / 60) ?> minute<?= $ttl > 60 ? 's' : '' ?>.
    <?php endif; ?>
  </p>

  <?php if ($transport !== 'mail'): ?>
    <div class="alert alert-info" role="status">
      <strong>Local mode.</strong> No mail transport is configured, so the code was
      written to the PHP error log. Find it with:
      <code>grep otp ..\_runtime\php\php-error.log</code>
    </div>
  <?php endif; ?>

  <form method="post" action="verify.php" class="card form-grid" novalidate>
    <?= csrf_field() ?>

    <div class="field full">
      <label for="code">Six digit code</label>
      <input type="text" id="code" name="code" inputmode="numeric" pattern="\d{6}"
             maxlength="6" required autocomplete="one-time-code" autofocus
             class="otp-input" placeholder="000000"
             aria-describedby="code-hint">
      <p class="hint" id="code-hint">Digits only. The code works once.</p>
      <?php if (!empty($errors['code'])): ?>
        <p class="error-text"><?= e($errors['code']) ?></p>
      <?php endif; ?>
    </div>

    <div class="field full row">
      <button class="btn" type="submit">Verify</button>
    </div>
  </form>

  <form method="post" action="verify.php" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="resend">
    <button class="btn btn-ghost" type="submit">Send a new code</button>
    <p class="hint mt">Requesting a new code immediately invalidates the previous one.</p>
  </form>
</div>
<?php render_footer(); ?>
