<?php
/**
 * Task 4 — checkout.
 *
 * Places an order in a transaction: the order header, its line items and the
 * stock decrement either all happen or none do. Totals are recalculated from
 * the database inside the transaction rather than trusted from the session.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_login();

$db = Database::instance($APEX_CONFIG, 'db_shop');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $details = cart_details();

    if (!$details) {
        flash('error', 'Your cart is empty.');
        redirect('cart.php');
    }

    // Re-check availability and stock inside the transaction.
    $invalid = [];
    foreach ($details as $l) {
        if (!$l['active'] || $l['stock'] < $l['qty']) {
            $invalid[] = $l['title'];
        }
    }

    if ($invalid) {
        flash('error', 'Not enough stock for: ' . implode(', ', $invalid) . '.');
        redirect('cart.php');
    }

    $total = cart_total($details);

    try {
        $db->run('BEGIN');

        $db->run('INSERT INTO orders (user_id, status, total_cents) VALUES (?, ?, ?)', [
            current_user_id(), 'pending', $total,
        ]);
        $orderId = $db->lastInsertId();

        foreach ($details as $l) {
            $db->run(
                'INSERT INTO order_items (order_id, product_id, title, unit_price_cents, quantity)
                 VALUES (?, ?, ?, ?, ?)',
                [$orderId, $l['id'], $l['title'], $l['price_cents'], $l['qty']]
            );

            // Guard against overselling: the UPDATE only succeeds while stock
            // is still sufficient, so two concurrent checkouts cannot both win.
            $stmt = $db->run(
                'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?',
                [$l['qty'], $l['id'], $l['qty']]
            );
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Stock changed while checking out: ' . $l['title']);
            }
        }

        $db->run('COMMIT');
        cart_clear();
        flash('success', 'Order #' . $orderId . ' placed.');
        redirect('orders.php');
    } catch (Throwable $e) {
        $db->run('ROLLBACK');
        error_log('checkout failed: ' . $e->getMessage());
        flash('error', 'Could not place the order. Nothing was charged and your cart is unchanged.');
        redirect('cart.php');
    }
}

$details = cart_details();
$total   = cart_total($details);

render_header('Checkout', true, shop_nav());
?>
<div class="wrap narrow">
  <?php render_flashes(); ?>
  <h1>Checkout</h1>

  <?php if (!$details): ?>
    <div class="card">
      <p class="muted">Your cart is empty.</p>
      <a class="btn" href="index.php">Browse the catalogue</a>
    </div>
  <?php else: ?>
    <div class="card">
      <table>
        <caption>Review before placing your order</caption>
        <thead><tr><th scope="col">Title</th><th scope="col">Qty</th><th scope="col">Total</th></tr></thead>
        <tbody>
        <?php foreach ($details as $l): ?>
          <tr>
            <th scope="row"><?= e($l['title']) ?><br><span class="muted small"><?= e($l['author']) ?></span></th>
            <td><?= $l['qty'] ?></td>
            <td>&#8377;<?= money($l['line_cents']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="2">Total</td><td>&#8377;<?= money($total) ?></td></tr></tfoot>
      </table>
    </div>

    <form method="post" action="checkout.php" data-confirm="Place this order?">
      <?= csrf_field() ?>
      <button class="btn" type="submit">Place order</button>
      <a class="btn btn-ghost" href="cart.php">Back to cart</a>
    </form>

    <p class="hint mt">
      Payment is simulated in this task. Placing the order marks it
      <code>pending</code>; an administrator moves it through the workflow.
    </p>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
