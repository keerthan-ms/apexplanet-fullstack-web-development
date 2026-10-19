<?php
/**
 * Task 4 — cart. Add, update quantity and remove.
 *
 * The cart is a session map of product id to quantity. Prices are always read
 * back from the database when the cart is displayed or checked out, so a
 * tampered session cannot change what an order costs.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = post('action');
    $id     = post_int('id');
    $qty    = post_int('qty', 1);

    if ($action === 'add') {
        $p = Database::instance($APEX_CONFIG, 'db_shop')->one(
            'SELECT id, stock FROM products WHERE id = ? AND is_active = 1',
            [$id]
        );
        if (!$p) {
            flash('error', 'That product is not available.');
        } elseif ((int) $p['stock'] === 0) {
            flash('error', 'That title is out of stock.');
        } else {
            $current = cart_items()[$id] ?? 0;
            if ($current + $qty > (int) $p['stock']) {
                flash('error', 'Only ' . (int) $p['stock'] . ' in stock for that title.');
            } else {
                cart_add($id, $qty);
                flash('success', 'Added to your cart.');
            }
        }
        redirect('index.php');
    }

    if ($action === 'update') {
        // Quantities arrive as qty[<product id>]; each key is cast to int
        // before it reaches cart_set(), so nothing from the browser is trusted.
        $quantities = $_POST['qty'] ?? [];
        if (is_array($quantities)) {
            foreach ($quantities as $pid => $q) {
                cart_set((int) $pid, (int) $q);
            }
        }
        flash('success', 'Cart updated.');
        redirect('cart.php');
    }

    if ($action === 'remove' || isset($_POST['remove_id'])) {
        // The remove control is a submit button carrying its own id, so the
        // whole table can stay inside one form (HTML forbids nesting forms).
        $removeId = $action === 'remove' ? $id : post_int('remove_id');
        cart_set($removeId, 0);
        flash('success', 'Removed from your cart.');
        redirect('cart.php');
    }

    if ($action === 'clear') {
        cart_clear();
        flash('success', 'Cart emptied.');
        redirect('cart.php');
    }
}

$details = cart_details();
$total   = cart_total($details);
$inStock = !array_filter($details, static fn ($l) => !$l['active'] || $l['stock'] < $l['qty']);

render_header('Your cart', true, shop_nav());
?>
<div class="wrap narrow">
  <?php render_flashes(); ?>
  <h1>Your cart</h1>

  <?php if (!$details): ?>
    <div class="card">
      <p class="muted">Your cart is empty.</p>
      <a class="btn" href="index.php">Browse the catalogue</a>
    </div>
  <?php else: ?>
    <?php if ($inStock): ?>
      <div class="alert alert-error" role="alert">
        One or more titles are no longer available in the quantity you asked for. Adjust them to continue.
      </div>
    <?php endif; ?>

    <form method="post" action="cart.php" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <table>
        <caption><?= count($details) ?> line(s)</caption>
        <thead>
          <tr>
            <th scope="col">Title</th><th scope="col">Price</th>
            <th scope="col">Qty</th><th scope="col">Line total</th><th scope="col"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($details as $l): ?>
          <tr>
            <th scope="row">
              <?= e($l['title']) ?>
              <br><span class="muted small"><?= e($l['author']) ?> · <?= e($l['category']) ?></span>
              <?php if ($l['qty'] > $l['stock']): ?>
                <br><span class="error-text">Only <?= $l['stock'] ?> left</span>
              <?php endif; ?>
            </th>
            <td>&#8377;<?= money($l['price_cents']) ?></td>
            <td>
              <label class="visually-hidden" for="qty-<?= $l['id'] ?>">Quantity for <?= e($l['title']) ?></label>
              <input type="number" id="qty-<?= $l['id'] ?>" name="qty[<?= $l['id'] ?>]"
                     value="<?= $l['qty'] ?>" min="0" max="<?= max(1, $l['stock']) ?>">
            </td>
            <td>&#8377;<?= money($l['line_cents']) ?></td>
            <td>
              <button class="btn btn-sm btn-danger" type="submit"
                      name="remove_id" value="<?= $l['id'] ?>">Remove</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="3">Order total</td><td colspan="2">&#8377;<?= money($total) ?></td></tr>
        </tfoot>
      </table>
    </form>

    <div class="row">
      <a class="btn btn-ghost" href="index.php">Continue shopping</a>
      <?php if (!$inStock): ?>
        <a class="btn" href="checkout.php">Proceed to checkout</a>
      <?php endif; ?>
    </div>

    <form method="post" action="cart.php" class="mt"
          data-confirm="Empty your entire cart?">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clear">
      <button class="btn btn-ghost btn-sm" type="submit">Empty cart</button>
    </form>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
