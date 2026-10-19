<?php
/**
 * Task 2 — Registration page (front end only).
 *
 * Shows the three JavaScript behaviours the brief asks for:
 *   - validation of every field
 *   - a password confirmation match check
 *   - a show/hide password toggle
 * and the AJAX username/email availability check.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc.php';

$pageTitle = 'Create account';
$activeNav = 'register';

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = post('username');
    $email    = post('email');
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please reload the page and try again.';
    }
    if ($username === '') {
        $errors['username'] = 'Choose a username.';
    } elseif (!preg_match('/^[A-Za-z0-9_.]{3,32}$/', $username)) {
        $errors['username'] = 'Use 3 to 32 characters: letters, digits, dot or underscore.';
    }
    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Include at least one letter and one digit.';
    }
    if ($confirm !== $password) {
        $errors['confirm_password'] = 'The two passwords do not match.';
    }
    if (!isset($_POST['terms'])) {
        $errors['terms'] = 'You must accept the terms to continue.';
    }

    if ($errors) {
        http_response_code(422);
    }
}
?>
<?php require __DIR__ . '/partials/header.php'; ?>

<div class="auth-shell">
  <div class="row w-100 g-0" style="max-width: 1040px;">

    <div class="col-lg-6">
      <div class="auth-card">
        <div class="p-4 p-md-5">

          <span class="section-label d-lg-none">Get started</span>
          <h1 class="h3 fw-bold mb-1">Create your account</h1>
          <p class="text-body-secondary mb-4">
            Already registered? <a href="login.php">Sign in</a>
          </p>

          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
              <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
              <div><?= e($errors['form'] ?? 'Please correct the errors below.') ?></div>
            </div>
          <?php endif; ?>

          <form method="post" action="register.php" novalidate data-validate="register">
            <?= csrf_field() ?>

            <!-- AJAX availability check attaches to these fields. -->
            <div class="mb-3">
              <label class="form-label fw-semibold" for="username">Username</label>
              <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?= e(post('username')) ?>" required minlength="3" maxlength="32"
                       autocomplete="username" pattern="[A-Za-z0-9_.]{3,32}"
                       data-ajax-check="username"
                       placeholder="keerthan.m" aria-describedby="username-msg">
                <div class="spinner-border spinner-border-sm text-primary align-self-center me-2 d-none"
                     id="username-spinner" role="status" aria-hidden="true"></div>
                <div class="invalid-feedback" id="username-msg"></div>
              </div>
              <p class="field-message" id="username-ajax" data-message-for="username"></p>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold" for="email">Email address</label>
              <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= e(post('email')) ?>" required autocomplete="email"
                       data-ajax-check="email" placeholder="you@example.com"
                       aria-describedby="email-msg">
                <div class="spinner-border spinner-border-sm text-primary align-self-center me-2 d-none"
                     id="email-spinner" role="status" aria-hidden="true"></div>
                <div class="invalid-feedback" id="email-msg"></div>
              </div>
              <p class="field-message" id="email-ajax" data-message-for="email"></p>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold" for="password">Password</label>
              <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                <input type="password" class="form-control" id="password" name="password"
                       required minlength="8" autocomplete="new-password"
                       placeholder="At least 8 characters" aria-describedby="password-msg password-hint">
                <button class="btn btn-ghost" type="button" data-toggle-password="password"
                        aria-label="Show password" title="Show password">
                  <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
                <div class="invalid-feedback" id="password-msg"></div>
              </div>
              <div class="strength-bar" aria-hidden="true"><i id="strength-fill"></i></div>
              <p class="field-message" id="password-hint">Use at least 8 characters with a letter and a digit.</p>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold" for="confirm_password">Confirm password</label>
              <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-lock-fill" aria-hidden="true"></i></span>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                       required autocomplete="new-password" placeholder="Repeat your password"
                       aria-describedby="confirm-msg">
                <button class="btn btn-ghost" type="button" data-toggle-password="confirm_password"
                        aria-label="Show password" title="Show password">
                  <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
                <div class="invalid-feedback" id="confirm-msg"></div>
              </div>
              <p class="field-message" id="match-msg" data-message-for="confirm_password"></p>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold" for="role">I am joining as</label>
              <select class="form-select" id="role" name="role" required>
                <option value="user" selected>User — browse and manage my account</option>
                <option value="admin">Administrator — manage the platform</option>
              </select>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" value="1" id="newsletter" name="newsletter">
              <label class="form-check-label small" for="newsletter">Email me occasional product updates</label>
            </div>

            <div class="form-check mb-4">
              <input class="form-check-input" type="checkbox" value="1" id="terms" name="terms" required>
              <label class="form-check-label small" for="terms">
                I accept the terms of service and the privacy policy
              </label>
              <p class="field-message" data-message-for="terms"></p>
            </div>

            <button class="btn btn-apex w-100 py-2" type="submit">
              <i class="bi bi-person-check me-1" aria-hidden="true"></i>Create account
            </button>

            <p class="small text-body-secondary text-center mt-3 mb-0">
              <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
              Task 2 is the front end only. Account creation is implemented in Task 3.
            </p>
          </form>

        </div>
      </div>
    </div>

    <div class="col-lg-6 d-none d-lg-block">
      <div class="auth-visual h-100 d-flex flex-column justify-content-between">
        <div>
          <span class="section-label text-white-50">Why ApexPlanet</span>
          <h2 class="mt-2 mb-3 fw-bold">Everything you need, in one place</h2>
        </div>
        <div class="row g-3">
          <div class="col-6">
            <div class="apex-card p-3 h-100" style="background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.2)">
              <i class="bi bi-shield-lock fs-4" aria-hidden="true"></i>
              <p class="fw-semibold mt-2 mb-0">Secure by default</p>
              <p class="small mb-0 text-white-50">Hashed passwords, prepared statements</p>
            </div>
          </div>
          <div class="col-6">
            <div class="apex-card p-3 h-100" style="background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.2)">
              <i class="bi bi-phone fs-4" aria-hidden="true"></i>
              <p class="fw-semibold mt-2 mb-0">Mobile first</p>
              <p class="small mb-0 text-white-50">Works from 320px upward</p>
            </div>
          </div>
          <div class="col-6">
            <div class="apex-card p-3 h-100" style="background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.2)">
              <i class="bi bi-lightning-charge fs-4" aria-hidden="true"></i>
              <p class="fw-semibold mt-2 mb-0">Instant feedback</p>
              <p class="small mb-0 text-white-50">AJAX checks, no reload</p>
            </div>
          </div>
          <div class="col-6">
            <div class="apex-card p-3 h-100" style="background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.2)">
              <i class="bi bi-universal-access fs-4" aria-hidden="true"></i>
              <p class="fw-semibold mt-2 mb-0">Accessible</p>
              <p class="small mb-0 text-white-50">Labels, focus and ARIA</p>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
