<?php
/**
 * Task 4 — product detail.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

$db = Database::instance($APEX_CONFIG, 'db_shop');
$id = (int) get('id');

$product = $id > 0 ? $db->one(
    'SELECT p.id, p.title, p.author, p.description, p.price_cents, p.stock, p.is_active, c.name AS category
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE p.id = ?',
    [$id]
) : null;

// An administrator may preview an inactive title; a visitor may not.
if ($product && (int) $product['is_active'] !== 1 && !is_admin()) {
    $product = null;
}

$related = $product ? $db->all(
    'SELECT p.id, p.title, p.price_cents FROM products p
     WHERE p.category_id = (SELECT category_id FROM products WHERE id = ?) AND p.id <> ? AND p.is_active = 1
     LIMIT 4',
    [$id, $id]
) : [];

if (!$product) {
    http_response_code(404);
    render_header('Not found', true, shop_nav());
    echo '<div class="wrap"><div class="card"><h1 class="h4">That title is not available</h1>'
       . '<p class="muted">It may have been removed or deactivated.</p>'
       . '<a class="btn" href="index.php">Back to the catalogue</a></div></div>';
    render_footer();
    exit;
}

render_header((string) $product['title'], true, shop_nav());
?>
<div class="wrap">
  <?php render_flashes(); ?>
  <nav aria-label="Breadcrumb" class="muted small mb">
    <a href="index.php">Catalogue</a> / <span><?= e((string) $product['category']) ?></span>
  </nav>

  <div class="product-detail">
    <div class="card">
      <span class="badge"><?= e((string) $product['category']) ?></span>
      <h1 class="h3"><?= e((string) $product['title']) ?></h1>
      <p class="muted">by <?= e((string) $product['author']) ?></p>
      <p class="price">&#8377;<?= money((int) $product['price_cents']) ?></p>
      <p><?= e((string) $product['description']) ?></p>

      <?php if ((int) $product['stock'] === 0): ?>
        <span class="badge badge-amber">Out of stock</span>
      <?php else: ?>
        <p class="muted small"><?= (int) $product['stock'] ?> in stock</p>
        <form method="post" action="cart.php" class="row">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
          <div class="field">
            <label for="qty">Quantity</label>
            <input type="number" id="qty" name="qty" value="1" min="1" max="<?= min(10, (int) $product['stock']) ?>">
          </div>
          <button class="btn" type="submit">Add to cart</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if ($related): ?>
      <div class="card">
        <h2 class="h5">More in <?= e((string) $product['category']) ?></h2>
        <ul class="plain-list">
          <?php foreach ($related as $r): ?>
            <li>
              <a href="product.php?id=<?= (int) $r['id'] ?>"><?= e((string) $r['title']) ?></a>
              <span class="muted">&#8377;<?= money((int) $r['price_cents']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php render_footer(); ?>
