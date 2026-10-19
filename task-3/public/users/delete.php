<?php
/**
 * Task 3 â€” delete user (the Delete side of CRUD).
 *
 * POST only, administrator only, CSRF protected. Reached from the list page,
 * where the button shows a confirmation popup before the request is sent.
 *
 * user_profiles and login_attempts rows are removed by ON DELETE CASCADE, so
 * no orphan records are left behind.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    // Refuse to act on a GET, so a crafted link cannot delete an account.
    flash('error', 'Deleting a user requires a POST request.');
    redirect('users/index.php');
}

$db = Database::instance($APEX_CONFIG, 'db_users');
$id = post_int('id');

if ($id < 1) {
    flash('error', 'No user selected.');
    redirect('users/index.php');
}

if ($id === current_user_id()) {
    flash('error', 'You cannot delete the account you are signed in with.');
    redirect('users/index.php');
}

$target = $db->one(
    'SELECT u.username, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
    [$id]
);

if (!$target) {
    flash('error', 'That user does not exist.');
    redirect('users/index.php');
}

// Same last-administrator guard as the edit page.
if ($target['role'] === 'admin') {
    $otherAdmins = (int) $db->value(
        "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.name = 'admin' AND u.is_active = 1 AND u.id <> ?",
        [$id]
    );
    if ($otherAdmins === 0) {
        flash('error', 'That is the last active administrator and cannot be deleted.');
        redirect('users/index.php');
    }
}

$db->run('DELETE FROM users WHERE id = ?', [$id]);
flash('success', 'User "' . $target['username'] . '" deleted.');
redirect('users/index.php');
