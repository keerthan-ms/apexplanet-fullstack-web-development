<?php
/**
 * Task 3 â€” dashboard.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$db = Database::instance($APEX_CONFIG, 'db_users');

$stats = [
    'users'    => (int) $db->value('SELECT COUNT(*) FROM users'),
    'active'   => (int) $db->value('SELECT COUNT(*) FROM users WHERE is_active = 1'),
    'admins'   => (int) $db->value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'admin'"),
    'attempts' => (int) $db->value('SELECT COUNT(*) FROM login_attempts'),
    'failed'   => (int) $db->value('SELECT COUNT(*) FROM login_attempts WHERE was_successful = 0'),
];

$recentAttempts = $db->all(
    'SELECT username_tried, ip_address, was_successful, attempted_at
     FROM login_attempts ORDER BY attempted_at DESC, id DESC LIMIT 8'
);

$recentUsers = $db->all(
    'SELECT u.id, u.username, r.name AS role, u.created_at
     FROM users u JOIN roles r ON r.id = u.role_id
     ORDER BY u.created_at DESC, u.id DESC LIMIT 5'
);

$nav = ['index.php' => 'Dashboard', 'users/index.php' => 'Manage users', 'profile.php' => 'Profile'];

render_header('Dashboard', true, $nav);
?>
<div class="wrap">
  <h1>Welcome, <?= e((string) ($_SESSION['username'] ?? 'user')) ?></h1>
  <p class="muted">Signed in with the <strong><?= e(current_user_role()) ?></strong> role.</p>

  <?php render_flashes(); ?>

  <div class="grid-3">
    <div class="card stat"><b><?= $stats['users'] ?></b><span>Total users</span></div>
    <div class="card stat"><b><?= $stats['active'] ?></b><span>Active</span></div>
    <div class="card stat"><b><?= $stats['admins'] ?></b><span>Administrators</span></div>
    <div class="card stat"><b><?= $stats['attempts'] ?></b><span>Login attempts</span></div>
    <div class="card stat"><b><?= $stats['failed'] ?></b><span>Failed attempts</span></div>
  </div>

  <div class="section-head">
    <h2>Recent sign-in attempts</h2>
    <span class="muted">Written by the login form, successful or not</span>
  </div>
  <div class="table-wrap">
    <table>
      <caption>Latest <?= count($recentAttempts) ?> attempt(s)</caption>
      <thead>
        <tr><th scope="col">Identifier</th><th scope="col">Source IP</th><th scope="col">Result</th><th scope="col">When</th></tr>
      </thead>
      <tbody>
      <?php if (!$recentAttempts): ?>
        <tr><td colspan="4">No attempts recorded yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($recentAttempts as $a): ?>
        <tr>
          <th scope="row"><?= e((string) $a['username_tried']) ?></th>
          <td><code><?= e((string) $a['ip_address']) ?></code></td>
          <td>
            <span class="badge <?= (int) $a['was_successful'] === 1 ? 'badge-green' : 'badge-amber' ?>">
              <?= (int) $a['was_successful'] === 1 ? 'Success' : 'Failed' ?>
            </span>
          </td>
          <td class="small"><?= e((string) $a['attempted_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (is_admin()): ?>
    <div class="section-head"><h2>Newest accounts</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th scope="col">Username</th><th scope="col">Role</th><th scope="col">Created</th><th scope="col"></th></tr></thead>
        <tbody>
        <?php foreach ($recentUsers as $u): ?>
          <tr>
            <th scope="row"><?= e((string) $u['username']) ?></th>
            <td><?= e((string) $u['role']) ?></td>
            <td class="small"><?= e((string) $u['created_at']) ?></td>
            <td><a class="btn btn-sm" href="users/edit.php?id=<?= (int) $u['id'] ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
