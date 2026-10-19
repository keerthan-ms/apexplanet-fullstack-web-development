<?php
/**
 * Task 4 bootstrap.
 *
 * Loads the shared library and declares this task's extra stylesheets.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/bootstrap.php';
apex_register_base(__DIR__);

$GLOBALS['APEX_EXTRA_CSS'] = ['assets/css/shop.css'];

/**
 * Task 4 navigation, shared by the storefront and the admin panel.
 *
 * @return array<string,string>
 */
function shop_nav(): array
{
    if (is_admin()) {
        return [
            'admin/index.php'   => 'Dashboard',
            'products.php'      => 'Catalogue',
            'orders.php'        => 'My orders',
            'cart.php'          => 'Cart',
            'admin/products.php'=> 'Manage products',
            'admin/orders.php'  => 'Manage orders',
            'admin/users.php'   => 'Manage users',
            'profile.php'       => 'Profile',
        ];
    }
    return [
        'index.php'   => 'Catalogue',
        'orders.php'  => 'My orders',
        'cart.php'    => 'Cart',
        'profile.php' => 'Profile',
    ];
}

/** Format an integer number of cents as a currency string. */
function money(int $cents): string
{
    return number_format($cents / 100, 2);
}

/**
 * The cart lives in the session. Keeping it server-side means the contents
 * cannot be forged from the browser.
 */
function cart_items(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_add(int $productId, int $qty = 1): void
{
    $qty = max(1, min($qty, 99));
    $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + $qty;
}

function cart_set(int $productId, int $qty): void
{
    if ($qty <= 0) {
        unset($_SESSION['cart'][$productId]);
        return;
    }
    $_SESSION['cart'][$productId] = max(1, min($qty, 99));
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

function cart_count(): int
{
    return array_sum(cart_items());
}

/**
 * Load the cart rows joined to the live product data.
 *
 * Prices come from the database, never from the session, so a tampered
 * session cannot change what an order costs.
 */
function cart_details(): array
{
    $ids = array_keys(cart_items());
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = Database::instance($GLOBALS['APEX_CONFIG'] ?? null, 'db_shop')->all(
        "SELECT p.id, p.title, p.author, p.price_cents, p.stock, p.is_active, c.name AS category
         FROM products p JOIN categories c ON c.id = p.category_id
         WHERE p.id IN ({$placeholders})",
        $ids
    );

    $out = [];
    foreach ($rows as $r) {
        $qty = cart_items()[(int) $r['id']] ?? 0;
        $out[] = [
            'id'          => (int) $r['id'],
            'title'       => (string) $r['title'],
            'author'      => (string) $r['author'],
            'category'    => (string) $r['category'],
            'price_cents' => (int) $r['price_cents'],
            'stock'       => (int) $r['stock'],
            'active'      => (int) $r['is_active'] === 1,
            'qty'         => $qty,
            'line_cents'  => (int) $r['price_cents'] * $qty,
        ];
    }
    return $out;
}

function cart_total(array $details): int
{
    return array_sum(array_column($details, 'line_cents'));
}
