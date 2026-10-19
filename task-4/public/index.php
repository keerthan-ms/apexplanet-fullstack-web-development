<?php
/**
 * Task 4 — catalogue with search, filters and pagination.
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

$db = Database::instance($APEX_CONFIG, 'db_shop');

$search = mb_substr(get('q'), 0, 80);
$cat    = get('cat');
$sort   = get('sort');
$page   = max(1, (int) (get('page') ?: 1));
$perPage = 9;

$sortOptions = [
    'title'  => 'Title (A–Z)',
    'price'  => 'Price (low to high)',
    'priced' => 'Price (high to low)',
    'new'    => 'Newest first',
];
$orderBy = match ($sort) {
    'price'  => 'p.price_cents ASC',
    'priced' => 'p.price_cents DESC',
    'new'    => 'p.created_at DESC',
    default  => 'p.title ASC',
};

// Whitelist the category against the database so the filter cannot be abused
// to smuggle anything into SQL.
$categories = $db->all('SELECT id, name, slug FROM categories ORDER BY name');
$catIds = array_map(static fn ($c) => (int) $c['id'], $categories);
$catId  = ($cat !== '' && ctype_digit($cat) && in_array((int) $cat, $catIds, true)) ? (int) $cat : null;

$where  = ['p.is_active = 1'];
$args   = [];

if ($search !== '') {
    // Escape LIKE metacharacters so % cannot widen the match.
    $pattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    $where[] = '(p.title LIKE ? OR p.author LIKE ? OR p.description LIKE ?)';
    array_push($args, $pattern, $pattern, $pattern);
}
if ($catId !== null) {
    $where[] = 'p.category_id = ?';
    $args[] = $catId;
}

$whereSql = implode(' AND ', $where);

$total = (int) $db->value("SELECT COUNT(*) FROM products p WHERE {$whereSql}", $args);
$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

$products = $db->all(
    "SELECT p.id, p.title, p.author, p.description, p.price_cents, p.stock, c.name AS category
     FROM products p JOIN categories c ON c.id = p.category_id
     WHERE {$whereSql}
     ORDER BY {$orderBy}
     LIMIT {$perPage} OFFSET {$offset}",
    $args
);

$buildUrl = static function (array $overrides = []) use ($search, $cat, $sort, $page): string {
    $q = array_filter([
        'q'    => $search,
        'cat'  => $cat,
        'sort' => $sort,
        'page' => $page > 1 ? (string) $page : '',
    ], static fn ($v) => $v !== '');
    foreach ($overrides as $k => $v) {
        if ($v === '' || $v === null) { unset($q[$k]); } else { $q[$k] = (string) $v; }
    }
    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
};

render_header('ApexPlanet Bookstore', true, shop_nav());
?>
<div class="wrap">
  <?php render_flashes(); ?>

  <div class="section-head">
    <div>
      <h1>The catalogue</h1>
      <p class="muted"><?= $total ?> title<?= $total === 1 ? '' : 's' ?> available</p>
    </div>
    <form method="get" action="index.php" role="search" class="shop-search">
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search title, author or description" maxlength="80" aria-label="Search the catalogue">
      <select name="cat" aria-label="Filter by category">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e((string) $c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="sort" aria-label="Sort results">
        <?php foreach ($sortOptions as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Apply</button>
      <?php if ($search !== '' || $catId !== null || $sort !== ''): ?>
        <a class="btn btn-ghost" href="index.php">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$products): ?>
    <div class="card">
      <p class="muted">Nothing matches that search. Try a different term or clear the filters.</p>
    </div>
  <?php endif; ?>

  <div class="product-grid">
    <?php foreach ($products as $p): ?>
      <article class="card product-card">
        <span class="badge"><?= e((string) $p['category']) ?></span>
        <h2 class="h5"><?= e((string) $p['title']) ?></h2>
        <p class="muted small"><?= e((string) $p['author']) ?></p>
        <p class="small clamp"><?= e((string) $p['description']) ?></p>
        <p class="price">&#8377;<?= money((int) $p['price_cents']) ?></p>
        <?php if ((int) $p['stock'] === 0): ?>
          <span class="badge badge-amber">Out of stock</span>
        <?php else: ?>
          <span class="badge badge-green"><?= (int) $p['stock'] ?> in stock</span>
        <?php endif; ?>
        <div class="row mt-auto">
          <a class="btn btn-sm btn-ghost" href="product.php?id=<?= (int) $p['id'] ?>">Details</a>
          <?php if ((int) $p['stock'] > 0): ?>
            <form method="post" action="cart.php" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="qty" value="1">
              <button class="btn btn-sm" type="submit">Add to cart</button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="pagination-nav" aria-label="Pagination">
      <?php if ($page > 1): ?>
        <a class="btn btn-sm btn-ghost" href="<?= e($buildUrl(['page' => $page - 1])) ?>">Previous</a>
      <?php endif; ?>
      <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?>
        <a class="btn btn-sm btn-ghost" href="<?= e($buildUrl(['page' => $page + 1])) ?>">Next</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
