<?php
/**
 * Task 4 — admin order management.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_admin();

$db = Database::instance($APEX_CONFIG, 'db_shop');
$errors = [];

$allowedStatuses = ['pending', 'paid', 'shipped', 'cancelled'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $id     = post_int('id');
    $status = post('status');

    if (!in_array($status, $allowedStatuses, true)) {
        // Report the problem and stay on the page. Redirecting here would drop
        // the message, leaving the administrator with no feedback at all.
        $errors[] = 'Unknown status "' . $status . '". Allowed: ' . implode(', ', $allowedStatuses) . '.';
    } else {
        $db->run('UPDATE orders SET status = ? WHERE id = ?', [$status, $id]);
        flash('success', 'Order #' . $id . ' marked as ' . $status . '.');
        redirect('orders.php');
    }
}

$filter = get('status');
$filter = in_array($filter, $allowedStatuses, true) ? $filter : '';

$where  = $filter !== '' ? 'WHERE o.status = ?' : '';
$args   = $filter !== '' ? [$filter] : [];

$orders = $db->all(
    "SELECT o.id, o.status, o.total_cents, o.placed_at, u.username,
            (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS line_count
     FROM orders o JOIN users u ON u.id = o.user_id
     {$where}
     ORDER BY o.placed_at DESC, o.id DESC
     LIMIT 100",
    $args
);

$itemsFor = static fn (int $orderId) => $db->all(
    'SELECT title, quantity, unit_price_cents FROM order_items WHERE order_id = ? ORDER BY id',
    [$orderId]
);

render_header('Manage orders', true, shop_nav());
?>
<div class="wrap">
  <?php render_flashes(); ?>
  <?php if ($errors): ?><div class="alert alert-error" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

  <div class="section-head">
    <h1>Manage orders</h1>
    <nav aria-label="Filter orders by status">
      <a class="btn btn-sm <?= $filter === '' ? '' : 'btn-ghost' ?>" href="orders.php">All</a>
      <?php foreach ($allowedStatuses as $s): ?>
        <a class="btn btn-sm <?= $filter === $s ? '' : 'btn-ghost' ?>" href="orders.php?status=<?= e($s) ?>">
          <?= e(ucfirst($s)) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <?php if (!$orders): ?>
    <div class="card"><p class="muted">No orders<?= $filter !== '' ? ' with that status' : '' ?>.</p></div>
  <?php endif; ?>

  <?php foreach ($orders as $o): ?>
    <div class="card">
      <div class="section-head">
        <h2 class="h5">Order #<?= (int) $o['id'] ?> — <?= e((string) $o['username']) ?></h2>
        <span class="badge"><?= e((string) $o['status']) ?></span>
      </div>
      <p class="muted small">
        Placed <?= e((string) $o['placed_at']) ?> · <?= (int) $o['line_count'] ?> line(s) ·
        <strong>&#8377;<?= money((int) $o['total_cents']) ?></strong>
      </p>

      <table>
        <thead><tr><th scope="col">Title</th><th scope="col">Qty</th><th scope="col">Unit</th><th scope="col">Total</th></tr></thead>
        <tbody>
        <?php foreach ($itemsFor((int) $o['id']) as $i): ?>
          <tr>
            <th scope="row"><?= e((string) $i['title']) ?></th>
            <td><?= (int) $i['quantity'] ?></td>
            <td>&#8377;<?= money((int) $i['unit_price_cents']) ?></td>
            <td>&#8377;<?= money((int) $i['unit_price_cents'] * (int) $i['quantity']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <form method="post" action="orders.php" class="row mt">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
        <label class="visually-hidden" for="status-<?= (int) $o['id'] ?>">Status for order <?= (int) $o['id'] ?></label>
        <select id="status-<?= (int) $o['id'] ?>" name="status">
          <?php foreach ($allowedStatuses as $s): ?>
            <option value="<?= e($s) ?>" <?= $s === $o['status'] ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-sm" type="submit">Update status</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php render_footer(); ?>
