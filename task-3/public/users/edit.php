<?php
/**
 * Task 3 â€” edit user (the Update side of CRUD).
 *
 * Password is only rewritten when a new value is supplied, so saving a profile
 * change never silently resets someone's password.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_admin();

$db  = Database::instance($APEX_CONFIG, 'db_users');
$id  = (int) get('id');
$errors = [];

if ($id < 1) {
    flash('error', 'No user selected.');
    redirect('users/index.php');
}

$user = $db->one(
    'SELECT u.id, u.username, u.email, u.role_id, u.is_active,
            p.full_name, p.headline
     FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id
     WHERE u.id = ?',
    [$id]
);

if (!$user) {
    flash('error', 'That user does not exist.');
    redirect('users/index.php');
}

$roles = $db->all('SELECT id, name, description FROM roles ORDER BY id');
$old   = [
    'username'  => (string) $user['username'],
    'email'     => (string) $user['email'],
    'full_name' => (string) ($user['full_name'] ?? ''),
    'headline'  => (string) ($user['headline'] ?? ''),
    'role_id'   => (string) $user['role_id'],
    'is_active' => (string) $user['is_active'],
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $old['username']  = post('username');
    $old['email']     = post('email');
    $old['full_name'] = post('full_name');
    $old['headline']  = post('headline');
    $old['role_id']   = (string) post('role_id', '1');
    $old['is_active'] = post('is_active') === '1' ? '1' : '0';
    $newPassword      = (string) ($_POST['password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.]{3,32}$/', $old['username'])) {
        $errors['username'] = 'Use 3 to 32 characters: letters, digits, dot or underscore.';
    } else {
        $clash = $db->value('SELECT id FROM users WHERE username = ? AND id <> ?', [$old['username'], $id]);
        if ($clash) {
            $errors['username'] = 'Another account already uses that username.';
        }
    }

    if (!valid_email($old['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $clash = $db->value('SELECT id FROM users WHERE email = ? AND id <> ?', [strtolower($old['email']), $id]);
        if ($clash) {
            $errors['email'] = 'Another account already uses that email address.';
        }
    }

    if (mb_strlen($old['full_name']) > 60) {
        $errors['full_name'] = 'Full name must be 60 characters or fewer.';
    }
    if (mb_strlen($old['headline']) > 120) {
        $errors['headline'] = 'Headline must be 120 characters or fewer.';
    }
    if (!$db->value('SELECT id FROM roles WHERE id = ?', [(int) $old['role_id']])) {
        $errors['role_id'] = 'Choose a valid role.';
    }

    if ($newPassword !== '') {
        $problems = password_problems($newPassword);
        if ($problems) {
            $errors['password'] = 'Password ' . implode(', ', $problems) . '.';
        } elseif (!hash_equals($newPassword, (string) ($_POST['confirm_password'] ?? ''))) {
            $errors['confirm_password'] = 'The two passwords do not match.';
        }
    }

    // An administrator must not be able to lock every administrator out of the
    // system. Block the change if this edit would remove the last active admin.
    $currentRoleName = (string) $db->value('SELECT name FROM roles WHERE id = ?', [(int) $user['role_id']]);
    $stillAdmin      = (string) $db->value('SELECT name FROM roles WHERE id = ?', [(int) $old['role_id']]) === 'admin';

    if ($currentRoleName === 'admin' && (int) $user['is_active'] === 1) {
        $losingAccess = ($old['is_active'] === '0') || !$stillAdmin;
        if ($losingAccess) {
            $otherAdmins = (int) $db->value(
                "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE r.name = 'admin' AND u.is_active = 1 AND u.id <> ?",
                [$id]
            );
            if ($otherAdmins === 0) {
                $errors['role_id'] = 'This is the last active administrator. Promote another account first.';
            }
        }
    }

    if (!$errors) {
        $db->run('BEGIN');
        try {
            if ($newPassword !== '') {
                $db->run(
                    'UPDATE users SET username = ?, email = ?, password_hash = ?, role_id = ?, is_active = ?
                     WHERE id = ?',
                    [
                        $old['username'],
                        strtolower($old['email']),
                        password_hash($newPassword, PASSWORD_DEFAULT),
                        (int) $old['role_id'],
                        (int) $old['is_active'],
                        $id,
                    ]
                );
            } else {
                $db->run(
                    'UPDATE users SET username = ?, email = ?, role_id = ?, is_active = ? WHERE id = ?',
                    [$old['username'], strtolower($old['email']), (int) $old['role_id'], (int) $old['is_active'], $id]
                );
            }

            // Upsert so a profile row is created even if it was never present.
            $db->run(
                'INSERT INTO user_profiles (user_id, full_name, headline) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), headline = VALUES(headline)',
                [$id, $old['full_name'], $old['headline']]
            );
            $db->run('COMMIT');

            flash('success', 'User "' . $old['username'] . '" updated.');
            redirect('users/index.php');
        } catch (Throwable $e) {
            $db->run('ROLLBACK');
            error_log('edit user failed: ' . $e->getMessage());
            $errors['form'] = 'Could not save the changes. Please try again.';
        }
    }
}

$nav = ['index.php' => 'Dashboard', 'users/index.php' => 'Manage users', 'profile.php' => 'Profile'];

render_header('Edit user', true, $nav);
?>
<div class="wrap narrow">
  <h1>Edit user</h1>
  <p class="muted">Account #<?= (int) $user['id'] ?> Â· created <?= e((string) $user['created_at']) ?></p>

  <?php render_flashes(); ?>
  <?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
  <?php endif; ?>

  <form method="post" action="edit.php?id=<?= $id ?>" class="card form-grid" novalidate>
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
      <input type="text" id="headline" name="headline" value="<?= e($old['headline']) ?>" maxlength="120">
      <?php if (!empty($errors['headline'])): ?><p class="error-text"><?= e($errors['headline']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="role_id">Role</label>
      <select id="role_id" name="role_id" required>
        <?php foreach ($roles as $r): ?>
          <option value="<?= (int) $r['id'] ?>" <?= (string) $r['id'] === $old['role_id'] ? 'selected' : '' ?>>
            <?= e((string) $r['name']) ?> â€” <?= e((string) $r['description']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (!empty($errors['role_id'])): ?><p class="error-text"><?= e($errors['role_id']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="is_active">Status</label>
      <select id="is_active" name="is_active" required>
        <option value="1" <?= $old['is_active'] === '1' ? 'selected' : '' ?>>Active</option>
        <option value="0" <?= $old['is_active'] === '0' ? 'selected' : '' ?>>Inactive (cannot sign in)</option>
      </select>
    </div>

    <div class="field full">
      <label for="password">New password</label>
      <div class="input-group">
        <input type="password" id="password" name="password" autocomplete="new-password"
               placeholder="Leave blank to keep the current password">
        <button class="btn btn-ghost" type="button" data-toggle-password="password" aria-label="Show password">Show</button>
      </div>
      <p class="hint">Only set a new password if you intend to change it.</p>
      <?php if (!empty($errors['password'])): ?><p class="error-text"><?= e($errors['password']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="confirm_password">Confirm new password</label>
      <div class="input-group">
        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
        <button class="btn btn-ghost" type="button" data-toggle-password="confirm_password" aria-label="Show password">Show</button>
      </div>
      <?php if (!empty($errors['confirm_password'])): ?><p class="error-text"><?= e($errors['confirm_password']) ?></p><?php endif; ?>
    </div>

    <div class="field full row">
      <button class="btn" type="submit">Save changes</button>
      <a class="btn btn-ghost" href="index.php">Cancel</a>
    </div>
  </form>
</div>
<?php render_footer(); ?>
