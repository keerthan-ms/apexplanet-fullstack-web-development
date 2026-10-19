<?php
/**
 * Task 4 — the signed-in user's orders.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$db = Database::instance($APEX_CONFIG, 'db_shop');

$orders = $db->all(
    'SELECT o.id, o.status, o.total_cents, o.placed_at, COUNT(i.id) AS line_count
     FROM orders o LEFT JOIN order_items i ON i.order_id = o.id
     WHERE o.user_id = ?
     GROUP BY o.id, o.status, o.total_cents, o.placed_at
     ORDER BY o.placed_at DESC, o.id DESC',
    [current_user_id()]
);

$itemsFor = static function (int $orderId) use ($db): array {
    return $db->all(
        'SELECT title, quantity, unit_price_cents FROM order_items WHERE order_id = ? ORDER BY id',
        [$orderId]
    );
};

render_header('My orders', true, shop_nav());
?>
<div class="wrap">
  <?php render_flashes(); ?>
  <h1>My orders</h1>

  <?php if (!$orders): ?>
    <div class="card">
      <p class="muted">You have not placed any orders yet.</p>
      <a class="btn" href="index.php">Browse the catalogue</a>
    </div>
  <?php endif; ?>

  <?php foreach ($orders as $o): ?>
    <div class="card">
      <div class="section-head">
        <h2 class="h5">Order #<?= (int) $o['id'] ?></h2>
        <span class="badge <?= match ((string) $o['status']) {
            'shipped'   => 'badge-green',
            'cancelled' => 'badge-amber',
            'paid'      => 'badge-blue',
            default     => '',
        } ?>"><?= e((string) $o['status']) ?></span>
      </div>
      <p class="muted small">
        Placed <?= e((string) $o['placed_at']) ?> · <?= (int) $o['line_count'] ?> line(s) ·
        <strong>&#8377;<?= money((int) $o['total_cents']) ?></strong>
      </p>
      <table>
        <thead><tr><th scope="col">Title</th><th scope="col">Qty</th><th scope="col">Unit price</th><th scope="col">Line total</th></tr></thead>
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
    </div>
  <?php endforeach; ?>
</div>
<?php render_footer(); ?>
