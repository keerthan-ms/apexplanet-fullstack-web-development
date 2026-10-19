<?php
/**
 * Task 4 — admin product management (Create, Read, Update, Delete).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_admin();

$db = Database::instance($APEX_CONFIG, 'db_shop');
$errors = [];

$categories = $db->all('SELECT id, name FROM categories ORDER BY name');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = post('action');
    $id     = post_int('id');

    if ($action === 'delete') {
        $inUse = (int) $db->value('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$id]);
        if ($inUse > 0) {
            // Deactivate instead of deleting, so past orders keep their history.
            $db->run('UPDATE products SET is_active = 0 WHERE id = ?', [$id]);
            flash('success', 'That title appears in past orders, so it was deactivated instead of deleted.');
        } else {
            $db->run('DELETE FROM products WHERE id = ?', [$id]);
            flash('success', 'Product deleted.');
        }
        redirect('products.php');
    }

    $title       = post('title');
    $author      = post('author');
    $description = post('description');
    $categoryId  = post_int('category_id');
    $priceCents  = post_int('price_cents');
    $stock       = post_int('stock');
    $isActive    = post('is_active') === '1' ? 1 : 0;

    if ($title === '' || mb_strlen($title) > 140) {
        $errors['title'] = 'Title is required and must be 140 characters or fewer.';
    }
    if ($description === '') {
        $errors['description'] = 'A description is required.';
    }
    if ($priceCents < 1 || $priceCents > 100000000) {
        $errors['price_cents'] = 'Enter a price in whole paise between 1 and 100000000.';
    }
    if ($stock < 0 || $stock > 100000) {
        $errors['stock'] = 'Stock must be between 0 and 100000.';
    }
    if (!$db->value('SELECT id FROM categories WHERE id = ?', [$categoryId])) {
        $errors['category_id'] = 'Choose a valid category.';
    }

    if (!$errors) {
        if ($action === 'update') {
            $db->run(
                'UPDATE products SET category_id = ?, title = ?, author = ?, description = ?,
                        price_cents = ?, stock = ?, is_active = ?
                 WHERE id = ?',
                [$categoryId, $title, $author, $description, $priceCents, $stock, $isActive, $id]
            );
            flash('success', 'Product updated.');
        } else {
            $db->run(
                'INSERT INTO products (category_id, title, author, description, price_cents, stock, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$categoryId, $title, $author, $description, $priceCents, $stock, $isActive]
            );
            flash('success', 'Product created.');
        }
        redirect('products.php');
    }

    $editId = $id;
}

$editing = null;
if (isset($_GET['id']) && ctype_digit((string) $_GET['id'])) {
    $editing = $db->one(
        'SELECT id, category_id, title, author, description, price_cents, stock, is_active
         FROM products WHERE id = ?',
        [(int) $_GET['id']]
    );
}

$q = mb_substr(get('q'), 0, 80);
$pattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
$products = $db->all(
    "SELECT p.id, p.title, p.author, p.price_cents, p.stock, p.is_active, c.name AS category
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE (? = '%%' OR p.title LIKE ? OR p.author LIKE ?)
     ORDER BY p.title ASC",
    [$q, $pattern, $pattern]
);

render_header('Manage products', true, shop_nav());
?>
<div class="wrap">
  <?php render_flashes(); ?>
  <div class="section-head">
    <h1>Manage products</h1>
    <form method="get" action="products.php" role="search" class="shop-search">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search products" aria-label="Search products">
      <button class="btn btn-sm" type="submit">Search</button>
    </form>
  </div>

  <form method="post" action="products.php" class="card form-grid" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

    <h2 class="h5 field full"><?= $editing ? 'Edit product #' . (int) $editing['id'] : 'Add a product' ?></h2>

    <div class="field">
      <label for="title">Title</label>
      <input type="text" id="title" name="title" maxlength="140" required
             value="<?= e($editing['title'] ?? post('title')) ?>">
      <?php if (!empty($errors['title'])): ?><p class="error-text"><?= e($errors['title']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="author">Author</label>
      <input type="text" id="author" name="author" maxlength="80" value="<?= e($editing['author'] ?? post('author')) ?>">
    </div>

    <div class="field">
      <label for="category_id">Category</label>
      <select id="category_id" name="category_id" required>
        <?php
        $sel = (int) ($editing['category_id'] ?? post('category_id'));
        foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $sel === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e((string) $c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (!empty($errors['category_id'])): ?><p class="error-text"><?= e($errors['category_id']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="price_cents">Price (whole paise)</label>
      <input type="number" id="price_cents" name="price_cents" min="1" step="1" required
             value="<?= e((string) ($editing['price_cents'] ?? post('price_cents'))) ?>">
      <p class="hint">Stored as an integer: 129900 means &#8377;1,299.00.</p>
      <?php if (!empty($errors['price_cents'])): ?><p class="error-text"><?= e($errors['price_cents']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="stock">Stock</label>
      <input type="number" id="stock" name="stock" min="0" step="1" required
             value="<?= e((string) ($editing['stock'] ?? post('stock', '0'))) ?>">
      <?php if (!empty($errors['stock'])): ?><p class="error-text"><?= e($errors['stock']) ?></p><?php endif; ?>
    </div>

    <div class="field full">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="3" required><?= e($editing['description'] ?? post('description')) ?></textarea>
      <?php if (!empty($errors['description'])): ?><p class="error-text"><?= e($errors['description']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label class="check"><input type="checkbox" name="is_active" value="1"
        <?= (int) ($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> Visible in the catalogue</label>
    </div>

    <div class="field full row">
      <button class="btn" type="submit"><?= $editing ? 'Save changes' : 'Create product' ?></button>
      <?php if ($editing): ?>
        <a class="btn btn-ghost" href="products.php">Cancel</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="table-wrap">
    <table>
      <caption><?= count($products) ?> product(s)</caption>
      <thead><tr><th scope="col">ID</th><th scope="col">Title</th><th scope="col">Category</th><th scope="col">Price</th><th scope="col">Stock</th><th scope="col">Visible</th><th scope="col">Actions</th></tr></thead>
      <tbody>
      <?php if (!$products): ?><tr><td colspan="7">No products match.</td></tr><?php endif; ?>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><?= (int) $p['id'] ?></td>
          <th scope="row"><?= e((string) $p['title']) ?><br><span class="muted small"><?= e((string) $p['author']) ?></span></th>
          <td><?= e((string) $p['category']) ?></td>
          <td>&#8377;<?= money((int) $p['price_cents']) ?></td>
          <td><?= (int) $p['stock'] ?></td>
          <td>
            <span class="badge <?= (int) $p['is_active'] === 1 ? 'badge-green' : 'badge-amber' ?>">
              <?= (int) $p['is_active'] === 1 ? 'Yes' : 'No' ?>
            </span>
          </td>
          <td class="nowrap">
            <a class="btn btn-sm" href="products.php?id=<?= (int) $p['id'] ?>">Edit</a>
            <form method="post" action="products.php" class="inline-form"
                  data-confirm="Delete &quot;<?= e((string) $p['title']) ?>&quot;?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php render_footer(); ?>
