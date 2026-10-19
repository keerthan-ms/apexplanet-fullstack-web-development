<?php
/**
 * Task 2 — Login page (front end only).
 *
 * Client-side validation lives in assets/js/forms.js. Server-side validation
 * and real session authentication are Task 3; this page deliberately stops at
 * a validated, accessible form and reports the outcome without navigating.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc.php';

$pageTitle = 'Sign in';
$activeNav = 'login';

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email    = post('email');
    $password = (string) ($_POST['password'] ?? '');

    // The server validates too. Client-side checks are a convenience, never
    // a security control.
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please reload the page and try again.';
    }
    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if ($errors) {
        http_response_code(422);
    }
}

$emailValue = post('email');
?>
<?php require __DIR__ . '/partials/header.php'; ?>

<div class="auth-shell">
  <div class="row w-100 g-0" style="max-width: 980px;">

    <!-- Marketing panel: hidden on small screens, shown from lg upward. -->
    <div class="col-lg-6 d-none d-lg-block">
      <div class="auth-visual h-100 d-flex flex-column justify-content-between">
        <div>
          <span class="section-label text-white-50">Welcome back</span>
          <h1 class="mt-2 mb-3 display-6 fw-bold">Sign in to your ApexPlanet workspace</h1>
          <p class="mb-4">One account for the storefront, the admin panel and your learning progress.</p>
        </div>
        <ul class="list-unstyled small mb-0">
          <li class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-shield-check text-white" aria-hidden="true"></i>
            Passwords stored hashed, never in plain text
          </li>
          <li class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-lightning-charge text-white" aria-hidden="true"></i>
            Availability checked with AJAX, no page reload
          </li>
          <li class="d-flex align-items-center gap-2">
            <i class="bi bi-phone text-white" aria-hidden="true"></i>
            Built mobile-first, usable from 320px up
          </li>
        </ul>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="auth-card">
        <div class="p-4 p-md-5">

          <span class="section-label d-md-none">Sign in</span>
          <h1 class="h3 fw-bold mb-1">Sign in</h1>
          <p class="text-body-secondary mb-4">
            New here? <a href="register.php">Create an account</a>
          </p>

          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
              <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
              <div><?= e($errors['form'] ?? 'Please correct the errors below.') ?></div>
            </div>
          <?php endif; ?>

          <form method="post" action="login.php" novalidate data-validate="login">
            <?= csrf_field() ?>

            <div class="mb-3">
              <label class="form-label fw-semibold" for="email">Email address</label>
              <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= e($emailValue) ?>" autocomplete="username"
                       required maxlength="254" placeholder="you@example.com"
                       aria-describedby="email-msg">
                <div class="invalid-feedback" id="email-msg"></div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold" for="password">Password</label>
              <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                <input type="password" class="form-control" id="password" name="password"
                       autocomplete="current-password" required minlength="8"
                       placeholder="At least 8 characters" aria-describedby="password-msg">
                <button class="btn btn-ghost" type="button" data-toggle-password="password"
                        aria-label="Show password" title="Show password">
                  <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
                <div class="invalid-feedback" id="password-msg"></div>
              </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                <label class="form-check-label small" for="remember">Remember me</label>
              </div>
              <a class="small" href="#" data-bs-toggle="modal" data-bs-target="#forgotModal">Forgot password?</a>
            </div>

            <button class="btn btn-apex w-100 py-2" type="submit">
              <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Sign in
            </button>

            <p class="small text-body-secondary text-center mt-3 mb-0">
              <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
              Task 2 is the front end only. Live authentication arrives in Task 3.
            </p>
          </form>

        </div>
      </div>
    </div>

  </div>
</div>

<!-- Bootstrap 5 modal component -->
<div class="modal fade" id="forgotModal" tabindex="-1" aria-labelledby="forgotModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content apex-card">
      <div class="modal-header">
        <h2 class="modal-title h5" id="forgotModalLabel">Reset your password</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form data-validate="login" novalidate>
        <div class="modal-body">
          <p class="small text-body-secondary">
            Enter your email address and we will send a reset link. In this task the
            form is a demonstration and no message is sent.
          </p>
          <label class="form-label fw-semibold" for="reset-email">Email address</label>
          <input type="email" class="form-control" id="reset-email" required placeholder="you@example.com">
          <p class="field-message" data-message-for="reset-email"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-apex">Send reset link</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
