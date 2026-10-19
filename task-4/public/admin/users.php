<?php
/**
 * Task 4 — admin user management.
 *
 * Role changes and activation are guarded so the last active administrator
 * cannot lock everyone out, and no administrator can delete their own account.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_admin();

$db = Database::instance($APEX_CONFIG, 'db_shop');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = post('action');
    $id     = post_int('id');

    if ($action === 'role') {
        $role = post('role');
        if (!in_array($role, ['user', 'admin'], true)) {
            flash('error', 'Unknown role.');
        } elseif ($id === current_user_id()) {
            flash('error', 'You cannot change your own role.');
        } else {
            $otherAdmins = (int) $db->value(
                "SELECT COUNT(*) FROM users WHERE role = 'admin' AND id <> ?", [$id]
            );
            $wasAdmin = (string) $db->value('SELECT role FROM users WHERE id = ?', [$id]);
            if ($wasAdmin === 'admin' && $role !== 'admin' && $otherAdmins === 0) {
                flash('error', 'That is the last administrator and cannot be demoted.');
            } else {
                $db->run('UPDATE users SET role = ? WHERE id = ?', [$role, $id]);
                flash('success', 'Role updated.');
            }
        }
    }

    if ($action === 'delete') {
        if ($id === current_user_id()) {
            flash('error', 'You cannot delete the account you are signed in with.');
        } else {
            $orders = (int) $db->value('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$id]);
            if ($orders > 0) {
                flash('error', 'That account has ' . $orders . ' order(s). Deleting it would remove real history.');
            } else {
                $db->run('DELETE FROM users WHERE id = ?', [$id]);
                flash('success', 'Account deleted.');
            }
        }
    }

    redirect('users.php');
}

$users = $db->all(
    'SELECT u.id, u.username, u.email, u.role, u.created_at,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders,
            (SELECT COALESCE(SUM(o.total_cents),0) FROM orders o WHERE o.user_id = u.id AND o.status <> "cancelled") AS spend
     FROM users u ORDER BY u.username ASC'
);

render_header('Manage users', true, shop_nav());
?>
<div class="wrap">
  <?php render_flashes(); ?>
  <h1>Manage users</h1>

  <div class="table-wrap">
    <table>
      <caption><?= count($users) ?> account(s)</caption>
      <thead>
        <tr><th scope="col">ID</th><th scope="col">User</th><th scope="col">Role</th>
            <th scope="col">Orders</th><th scope="col">Spend</th><th scope="col">Joined</th><th scope="col">Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <?php $self = (int) $u['id'] === current_user_id(); ?>
        <tr>
          <td><?= (int) $u['id'] ?><?= $self ? ' <span class="badge">(you)</span>' : '' ?></td>
          <th scope="row"><?= e((string) $u['username']) ?><br><span class="muted small"><?= e((string) $u['email']) ?></span></th>
          <td>
            <span class="badge <?= $u['role'] === 'admin' ? 'badge-blue' : '' ?>"><?= e((string) $u['role']) ?></span>
          </td>
          <td><?= (int) $u['orders'] ?></td>
          <td>&#8377;<?= money((int) $u['spend']) ?></td>
          <td class="small"><?= e((string) $u['created_at']) ?></td>
          <td class="nowrap">
            <?php if (!$self): ?>
              <form method="post" action="users.php" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="role">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <label class="visually-hidden" for="role-<?= (int) $u['id'] ?>">Role for <?= e((string) $u['username']) ?></label>
                <select id="role-<?= (int) $u['id'] ?>" name="role">
                  <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>user</option>
                  <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                </select>
                <button class="btn btn-sm" type="submit">Set</button>
              </form>
              <form method="post" action="users.php" class="inline-form mt-sm"
                    data-confirm="Delete &quot;<?= e((string) $u['username']) ?>&quot;?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
              </form>
            <?php else: ?>
              <span class="muted small">Current account</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php render_footer(); ?>
