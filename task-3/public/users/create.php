<?php
/**
 * Task 3 â€” create user (the Create side of CRUD).
 *
 * Server-side validation only. Nothing here trusts the browser: every field is
 * re-checked, the password is hashed before storage, and the insert runs inside
 * a transaction so the users and user_profiles rows stay in step.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_admin();

$db     = Database::instance($APEX_CONFIG, 'db_users');
$errors = [];
$old    = ['username' => '', 'email' => '', 'full_name' => '', 'headline' => ''];
$roleId = '1';

$roles = $db->all('SELECT id, name, description FROM roles ORDER BY id');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $old['username']  = post('username');
    $old['email']     = post('email');
    $old['full_name'] = post('full_name');
    $old['headline']  = post('headline');
    $roleId           = (string) post('role_id', '1');
    $password         = (string) ($_POST['password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.]{3,32}$/', $old['username'])) {
        $errors['username'] = 'Use 3 to 32 characters: letters, digits, dot or underscore.';
    } else {
        $taken = $db->value('SELECT id FROM users WHERE username = ?', [$old['username']]);
        if ($taken) {
            $errors['username'] = 'That username is already taken.';
        }
    }

    if (!valid_email($old['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $taken = $db->value('SELECT id FROM users WHERE email = ?', [strtolower($old['email'])]);
        if ($taken) {
            $errors['email'] = 'That email address is already registered.';
        }
    }

    $problems = password_problems($password);
    if ($problems) {
        $errors['password'] = 'Password ' . implode(', ', $problems) . '.';
    }
    if (!hash_equals($password, (string) ($_POST['confirm_password'] ?? ''))) {
        $errors['confirm_password'] = 'The two passwords do not match.';
    }
    if (mb_strlen($old['full_name']) > 60) {
        $errors['full_name'] = 'Full name must be 60 characters or fewer.';
    }
    if (mb_strlen($old['headline']) > 120) {
        $errors['headline'] = 'Headline must be 120 characters or fewer.';
    }

    $validRole = $db->value('SELECT id FROM roles WHERE id = ?', [(int) $roleId]);
    if (!$validRole) {
        $errors['role_id'] = 'Choose a valid role.';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $db->run('BEGIN');
        try {
            $db->run(
                'INSERT INTO users (username, email, password_hash, role_id, is_active)
                 VALUES (?, ?, ?, ?, 1)',
                [$old['username'], strtolower($old['email']), $hash, (int) $roleId]
            );
            $newId = $db->lastInsertId();

            $db->run(
                'INSERT INTO user_profiles (user_id, full_name, headline) VALUES (?, ?, ?)',
                [$newId, $old['full_name'], $old['headline']]
            );
            $db->run('COMMIT');

            flash('success', 'User "' . $old['username'] . '" created.');
            redirect('users/index.php');
        } catch (Throwable $e) {
            $db->run('ROLLBACK');
            error_log('create user failed: ' . $e->getMessage());
            $errors['form'] = 'Could not create the user. Please try again.';
        }
    }
}

$nav = ['index.php' => 'Dashboard', 'users/index.php' => 'Manage users', 'profile.php' => 'Profile'];

if ($errors) {
    http_response_code(422);
}

render_header('Add user', true, $nav);
?>
<div class="wrap narrow">
  <h1>Add user</h1>
  <p class="muted">The password is hashed with <code>password_hash()</code> before it is stored.</p>

  <?php render_flashes(); ?>
  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
  <?php endif; ?>

  <form method="post" action="create.php" class="card form-grid" novalidate>
    <?= csrf_field() ?>

    <div class="field">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="<?= e($old['username']) ?>"
             required pattern="[A-Za-z0-9_.]{3,32}" maxlength="32" autocomplete="off" autofocus>
      <?php if (!empty($errors['username'])): ?><p class="error-text"><?= e($errors['username']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required maxlength="254">
      <?php if (!empty($errors['email'])): ?><p class="error-text"><?= e($errors['email']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="full_name">Full name</label>
      <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" maxlength="60">
      <?php if (!empty($errors['full_name'])): ?><p class="error-text"><?= e($errors['full_name']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="headline">Headline</label>
      <input type="text" id="headline" name="headline" value="<?= e($old['headline']) ?>"
             maxlength="120" placeholder="Optional, shown on the profile">
      <?php if (!empty($errors['headline'])): ?><p class="error-text"><?= e($errors['headline']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="role_id">Role</label>
      <select id="role_id" name="role_id" required>
        <?php foreach ($roles as $r): ?>
          <option value="<?= (int) $r['id'] ?>" <?= (string) $r['id'] === $roleId ? 'selected' : '' ?>>
            <?= e((string) $r['name']) ?> â€” <?= e((string) $r['description']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (!empty($errors['role_id'])): ?><p class="error-text"><?= e($errors['role_id']) ?></p><?php endif; ?>
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

    <div class="field full row">
      <button class="btn" type="submit">Create user</button>
      <a class="btn btn-ghost" href="index.php">Cancel</a>
    </div>
  </form>
</div>
<?php render_footer(); ?>
