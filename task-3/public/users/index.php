<?php
/**
 * Task 3 â€” user list (the Read and Delete side of the CRUD set).
 *
 * Create and Edit have their own pages. Delete is a POST-only endpoint with a
 * CSRF token, so a crafted link cannot delete an account.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_admin();

$db = Database::instance($APEX_CONFIG, 'db_users');

// Search term is trimmed and bound; the LIKE wildcards are escaped so a user
// cannot turn "%" into a full table scan of everything.
$search = get('q');
$search = mb_substr($search, 0, 60);

if ($search !== '' && !str_contains($search, '***')) {
    $pattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    $where   = 'WHERE u.username LIKE ? OR u.email LIKE ? OR p.full_name LIKE ?';
    $args    = [$pattern, $pattern, $pattern];
} else {
    $where = '';
    $args  = [];
}

$rows = $db->all(
    "SELECT u.id, u.username, u.email, u.is_active, u.last_login_at, u.created_at,
            r.name AS role, p.full_name, p.avatar_path
     FROM users u
     JOIN roles r ON r.id = u.role_id
     LEFT JOIN user_profiles p ON p.user_id = u.id
     {$where}
     ORDER BY u.id ASC",
    $args
);

$total = (int) $db->value('SELECT COUNT(*) FROM users');
$active = (int) $db->value('SELECT COUNT(*) FROM users WHERE is_active = 1');

$nav = ['index.php' => 'Dashboard', 'users/index.php' => 'Manage users', 'profile.php' => 'Profile'];

render_header('Manage users', true, $nav);
?>
<div class="wrap">
  <div class="section-head">
    <h1>Manage users</h1>
    <a class="btn" href="users/create.php">Add user</a>
  </div>

  <?php render_flashes(); ?>

  <div class="grid-3">
    <div class="card stat"><b><?= $total ?></b><span>Total accounts</span></div>
    <div class="card stat"><b><?= $active ?></b><span>Active</span></div>
    <div class="card stat"><b><?= $total - $active ?></b><span>Deactivated</span></div>
  </div>

  <form method="get" action="index.php" class="card form-grid" role="search">
    <div class="field">
      <label for="q">Search</label>
      <input type="search" id="q" name="q" value="<?= e($search) ?>"
             placeholder="username, email or name" maxlength="60">
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <button class="btn" type="submit">Search</button>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <a class="btn btn-ghost" href="index.php">Reset</a>
    </div>
  </form>

  <div class="table-wrap">
    <table>
      <caption><?= count($rows) ?> account(s)<?= $search !== '' ? ' matching "' . e($search) . '"' : '' ?></caption>
      <thead>
        <tr>
          <th scope="col">ID</th>
          <th scope="col">User</th>
          <th scope="col">Email</th>
          <th scope="col">Role</th>
          <th scope="col">Status</th>
          <th scope="col">Last login</th>
          <th scope="col">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7">No accounts found.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $u): ?>
        <tr>
          <td><?= (int) $u['id'] ?></td>
          <th scope="row">
            <?= e((string) $u['username']) ?>
            <?php if (!empty($u['full_name'])): ?>
              <br><span class="muted small"><?= e((string) $u['full_name']) ?></span>
            <?php endif; ?>
          </th>
          <td><?= e((string) $u['email']) ?></td>
          <td>
            <span class="badge <?= $u['role'] === 'admin' ? 'badge-blue' : '' ?>"><?= e((string) $u['role']) ?></span>
          </td>
          <td>
            <span class="badge <?= (int) $u['is_active'] === 1 ? 'badge-green' : 'badge-amber' ?>">
              <?= (int) $u['is_active'] === 1 ? 'Active' : 'Inactive' ?>
            </span>
          </td>
          <td class="small"><?= $u['last_login_at'] ? e((string) $u['last_login_at']) : '<span class="muted">never</span>' ?></td>
          <td class="nowrap">
            <a class="btn btn-sm" href="users/edit.php?id=<?= (int) $u['id'] ?>">Edit</a>
            <form method="post" action="users/delete.php" class="inline-form"
                  data-confirm="Delete &quot;<?= e((string) $u['username']) ?>&quot;? This cannot be undone.">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="7">Showing <?= count($rows) ?> of <?= $total ?> accounts</td></tr>
      </tfoot>
    </table>
  </div>
</div>
<?php render_footer(); ?>
