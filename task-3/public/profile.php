<?php
/**
 * Task 3 â€” profile management.
 *
 * Uploads are validated three ways: size, real MIME type (read from the file
 * contents, never from the client-supplied Content-Type), and extension.
 * The generated filename is random, and the file is stored outside the web
 * root and served through download.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$db      = Database::instance($APEX_CONFIG, 'db_users');
$userId  = current_user_id();
$errors  = [];
$notice  = '';

$profile = $db->one(
    'SELECT p.full_name, p.headline, p.bio, p.avatar_path
     FROM user_profiles p WHERE p.user_id = ?',
    [$userId]
) ?? ['full_name' => '', 'headline' => '', 'bio' => '', 'avatar_path' => ''];

$account = $db->one(
    'SELECT u.username, u.email, r.name AS role, u.created_at, u.last_login_at
     FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
    [$userId]
);

$uploadDir = $APEX_CONFIG['upload']['dir'];
$maxBytes  = (int) $APEX_CONFIG['upload']['max_bytes'];
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0o777, true);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = post('action');
    $fullName  = post('full_name');
    $headline  = post('headline');
    $bio       = post('bio');
    $newEmail  = post('email');

    if ($action === 'remove_avatar') {
        if (!empty($profile['avatar_path'])) {
            $old = $uploadDir . '/' . basename((string) $profile['avatar_path']);
            if (is_file($old)) {
                @unlink($old);
            }
        }
        $db->run("UPDATE user_profiles SET avatar_path = '' WHERE user_id = ?", [$userId]);
        flash('success', 'Profile picture removed.');
        redirect('profile.php');
    }

    if (mb_strlen($fullName) > 60) {
        $errors['full_name'] = 'Full name must be 60 characters or fewer.';
    }
    if (mb_strlen($headline) > 120) {
        $errors['headline'] = 'Headline must be 120 characters or fewer.';
    }
    if (mb_strlen($bio) > 400) {
        $errors['bio'] = 'Bio must be 400 characters or fewer.';
    }
    if (!valid_email($newEmail)) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $clash = $db->value('SELECT id FROM users WHERE email = ? AND id <> ?', [strtolower($newEmail), $userId]);
        if ($clash) {
            $errors['email'] = 'Another account already uses that email address.';
        }
    }

    // ---------------------------------------------------------- upload ---
    $newAvatar = '';
    if (!empty($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['avatar'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['avatar'] = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That image is larger than the server allows.',
                UPLOAD_ERR_PARTIAL    => 'The upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary directory for uploads.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
                default               => 'The upload failed. Please try again.',
            };
        } elseif ($file['size'] <= 0) {
            $errors['avatar'] = 'The uploaded file is empty.';
        } elseif ($file['size'] > $maxBytes) {
            $errors['avatar'] = sprintf(
                'Image is %s; the limit is %s.',
                number_format($file['size'] / 1024) . ' KB',
                number_format($maxBytes / 1024) . ' KB'
            );
        } else {
            // Trust the file contents, never the name or the client's MIME type.
            $info = @getimagesize($file['tmp_name']);
            $allowedMimes = $APEX_CONFIG['upload']['allowed_mime'];
            $extensions   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

            // allowed_mime is a list of strings, so membership is tested with
            // in_array rather than isset(), which only works for a keyed map.
            if ($info === false || !in_array((string) ($info['mime'] ?? ''), $allowedMimes, true)) {
                $errors['avatar'] = 'Only JPEG, PNG, GIF and WebP images are accepted.';
            } elseif ((int) $info[0] > 4000 || (int) $info[1] > 4000) {
                $errors['avatar'] = 'Image dimensions must be 4000x4000 pixels or smaller.';
            } else {
                $storedName = bin2hex(random_bytes(16)) . '.' . $extensions[$info['mime']];
                $target     = $uploadDir . '/' . $storedName;

                if (move_uploaded_file($file['tmp_name'], $target)) {
                    @chmod($target, 0o644);
                    $newAvatar = $storedName;
                } else {
                    $errors['avatar'] = 'Could not save the uploaded image.';
                }
            }
        }
    }

    if (!$errors) {
        $db->run(
            'INSERT INTO user_profiles (user_id, full_name, headline, bio, avatar_path)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                full_name   = VALUES(full_name),
                headline    = VALUES(headline),
                bio         = VALUES(bio),
                avatar_path = IF(VALUES(avatar_path) = "", avatar_path, VALUES(avatar_path))',
            [$userId, $fullName, $headline, $bio, $newAvatar]
        );
        $db->run('UPDATE users SET email = ? WHERE id = ?', [strtolower($newEmail), $userId]);

        if ($newAvatar !== '' && !empty($profile['avatar_path'])) {
            $old = $uploadDir . '/' . basename((string) $profile['avatar_path']);
            if (is_file($old)) {
                @unlink($old);
            }
        }

        flash('success', 'Profile updated.');
        redirect('profile.php');
    }

    // Keep what the user typed so the form does not reset on a validation error.
    $profile = [
        'full_name'   => $fullName,
        'headline'    => $headline,
        'bio'         => $bio,
        'avatar_path' => $newAvatar !== '' ? $newAvatar : (string) $profile['avatar_path'],
    ];
    $account['email'] = $newEmail;
}

if ($errors) {
    http_response_code(422);
}

$nav = ['index.php' => 'Dashboard', 'users/index.php' => 'Manage users', 'profile.php' => 'Profile'];

render_header('Profile', true, $nav);
?>
<div class="wrap narrow">
  <h1>My profile</h1>

  <?php render_flashes(); ?>
  <?php if (!empty($errors['form'])): ?><div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>

  <div class="card profile-head">
    <img class="avatar-sm" width="96" height="96"
         src="<?= !empty($profile['avatar_path']) ? 'download.php?file=' . e((string) $profile['avatar_path']) : 'assets/img/placeholder.svg' ?>"
         alt="" width="96" height="96">
    <div>
      <h2><?= e((string) ($profile['full_name'] ?: $account['username'])) ?></h2>
      <p class="muted">
        @<?= e((string) $account['username']) ?> Â·
        <span class="badge <?= $account['role'] === 'admin' ? 'badge-blue' : '' ?>"><?= e((string) $account['role']) ?></span>
      </p>
      <p class="small muted">
        Member since <?= e((string) $account['created_at']) ?> Â·
        Last sign-in <?= $account['last_login_at'] ? e((string) $account['last_login_at']) : 'this session' ?>
      </p>
    </div>
  </div>

  <form method="post" action="profile.php" enctype="multipart/form-data" class="card form-grid" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">

    <div class="field full">
      <label for="full_name">Full name</label>
      <input type="text" id="full_name" name="full_name" value="<?= e((string) $profile['full_name']) ?>" maxlength="60">
      <?php if (!empty($errors['full_name'])): ?><p class="error-text"><?= e($errors['full_name']) ?></p><?php endif; ?>
    </div>

    <div class="field full">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e((string) $account['email']) ?>" required maxlength="254">
      <?php if (!empty($errors['email'])): ?><p class="error-text"><?= e($errors['email']) ?></p><?php endif; ?>
    </div>

    <div class="field full">
      <label for="headline">Headline</label>
      <input type="text" id="headline" name="headline" value="<?= e((string) $profile['headline']) ?>" maxlength="120">
      <?php if (!empty($errors['headline'])): ?><p class="error-text"><?= e($errors['headline']) ?></p><?php endif; ?>
    </div>

    <div class="field full">
      <label for="bio">Bio</label>
      <textarea id="bio" name="bio" maxlength="400" rows="4"><?= e((string) $profile['bio']) ?></textarea>
      <?php if (!empty($errors['bio'])): ?><p class="error-text"><?= e($errors['bio']) ?></p><?php endif; ?>
    </div>

    <div class="field full">
      <label for="avatar">Profile picture</label>
      <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp"
             aria-describedby="avatar-hint">
      <p class="hint" id="avatar-hint">
        JPEG, PNG, GIF or WebP, up to <?= number_format($maxBytes / 1024) ?> KB.
        The real image type is checked from the file contents, not the filename.
      </p>
      <?php if (!empty($errors['avatar'])): ?><p class="error-text"><?= e($errors['avatar']) ?></p><?php endif; ?>
    </div>

    <div class="field full row">
      <button class="btn" type="submit">Save profile</button>
      <a class="btn btn-ghost" href="index.php">Back to dashboard</a>
    </div>
  </form>

  <?php if (!empty($profile['avatar_path'])): ?>
    <form method="post" action="profile.php" class="card"
          data-confirm="Remove your profile picture?">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="remove_avatar">
      <button class="btn btn-danger" type="submit">Remove profile picture</button>
    </form>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
