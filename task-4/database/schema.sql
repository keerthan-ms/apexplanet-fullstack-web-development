-- ---------------------------------------------------------------------
-- Task 4 — Real-World Full Stack Project: "ApexPlanet Bookstore"
-- ApexPlanet Software Pvt. Ltd. · Full Stack Web Development Internship
--
-- Target database: apexplanet_shop
--
-- Run with:
--   php task-4/database/migrate.php
--   php task-4/database/migrate.php --fresh
-- or import this file through phpMyAdmin.
--
-- Tables
--   users        accounts and their role
--   categories   normalised product classification
--   products     the catalogue
--   orders       one row per placed order
--   order_items  line items, FK to both orders and products
--
-- Every relationship is enforced by a real FOREIGN KEY, and every column a
-- query filters or joins on is indexed.
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    username      VARCHAR(32)   NOT NULL,
    email         VARCHAR(254)  NOT NULL,
    password_hash VARCHAR(255)  NOT NULL,
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shop_users_username (username),
    UNIQUE KEY uq_shop_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- categories — separate table so a product references a category by id
-- instead of repeating its name on every row.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(60)  NOT NULL,
    slug        VARCHAR(60)  NOT NULL,
    description VARCHAR(160) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- products
--   price_cents stores an integer, never a float. Money in floating point
--   accumulates rounding errors such as 19.999999999.
--   stock is unsigned so a negative count is impossible at the storage layer.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    category_id  INT UNSIGNED  NOT NULL,
    title        VARCHAR(140)  NOT NULL,
    author       VARCHAR(80)   NOT NULL DEFAULT '',
    description  TEXT          NOT NULL,
    price_cents  INT UNSIGNED  NOT NULL,
    stock        INT UNSIGNED  NOT NULL DEFAULT 0,
    is_active    TINYINT(1)    NOT NULL DEFAULT 1,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_products_category (category_id),
    KEY idx_products_active_price (is_active, price_cents),
    FULLTEXT KEY ft_products_search (title, author, description),
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_products_price CHECK (price_cents > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- orders — the header. status drives the admin workflow.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    status       ENUM('pending','paid','shipped','cancelled') NOT NULL DEFAULT 'pending',
    total_cents  INT UNSIGNED NOT NULL DEFAULT 0,
    placed_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_user (user_id),
    KEY idx_orders_status (status),
    KEY idx_orders_placed (placed_at),
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- order_items — the detail.
--   unit_price_cents is copied at the time of ordering so a later price
--   change does not rewrite the history of what someone actually paid.
--   title is denormalised for the same reason: a renamed book must not
--   rewrite past receipts. Both are deliberate and documented in the
--   README rather than treated as accidental.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id         INT UNSIGNED NOT NULL,
    product_id       INT UNSIGNED NOT NULL,
    title            VARCHAR(140) NOT NULL,
    unit_price_cents INT UNSIGNED NOT NULL,
    quantity         INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_items_order (order_id),
    KEY idx_items_product (product_id),
    CONSTRAINT fk_items_order
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_items_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_items_qty CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Seed data
-- Passwords: admin / Admin@12345   demo / Demo@12345
-- (hashes are generated by tools; see task-4/README.md)
-- ---------------------------------------------------------------------
INSERT INTO users (username, email, password_hash, role) VALUES
  ('admin', 'admin@apexplanet.local', '__HASH_ADMIN__', 'admin'),
  ('demo',  'demo@apexplanet.local',  '__HASH_DEMO__',  'user')
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO categories (name, slug, description) VALUES
  ('Fiction',    'fiction',    'Novels and short stories'),
  ('Technology', 'technology', 'Programming and computing'),
  ('Business',   'business',   'Economics, management and strategy'),
  ('Study',      'study',      'Textbooks and exam preparation')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO products (category_id, title, author, description, price_cents, stock, is_active)
SELECT c.id, p.title, p.author, p.description, p.price_cents, p.stock, p.is_active
FROM (
    SELECT 'Fiction'    AS cat, 'The Silent Meridian' AS title, 'A. R. Calloway' AS author,
           'A cartographer discovers her maps have begun redrawing themselves overnight.' AS description,
           129900 AS price_cents, 14 AS stock, 1 AS is_active
    UNION ALL SELECT 'Fiction', 'Salt and Cinder', 'M. Okonkwo',
           'Two rival lighthouse keepers, one island, and a winter that will not end.', 99900, 6, 1
    UNION ALL SELECT 'Fiction', 'The Last Tram to Vasa', 'L. Bergqvist',
           'A commuter novel about the people who ride the same route every night for years.', 115000, 0, 1
    UNION ALL SELECT 'Technology', 'Building Secure PHP', 'R. Almeida',
           'Practical input validation, prepared statements, session handling and file upload hardening.', 245000, 22, 1
    UNION ALL SELECT 'Technology', 'Relational Design in Practice', 'D. Ferreira',
           'Normalisation, indexing and foreign keys explained through worked examples.', 219000, 17, 1
    UNION ALL SELECT 'Technology', 'Frontend Performance Handbook', 'S. Nakamura',
           'Measuring and fixing what users actually perceive as slow.', 199500, 9, 1
    UNION ALL SELECT 'Business', 'Pricing for Small Teams', 'T. Brennan',
           'A practical approach to pricing services without a finance department.', 175000, 11, 1
    UNION ALL SELECT 'Business', 'The Quarterly Close', 'P. Iyer',
           'What happens in the first ninety days of a new leadership role.', 158000, 4, 1
    UNION ALL SELECT 'Study', 'Algorithms Cheat Sheet', 'G. Mbeki',
           'Every algorithm you are expected to know, one page each.', 89000, 40, 1
    UNION ALL SELECT 'Study', 'Database Internals', 'H. Kowalski',
           'How indexes, query planners and storage engines actually behave.', 265000, 8, 1
    UNION ALL SELECT 'Study', 'System Design Notes', 'V. Anand',
           'Interviews and real systems, annotated.', 210000, 0, 0
) AS p
JOIN categories c ON c.slug = p.cat
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- A couple of historical orders so the admin analytics have something to
-- chart on a fresh install.
INSERT INTO orders (user_id, status, total_cents, placed_at)
SELECT u.id, 'shipped', 375900, NOW() - INTERVAL 6 DAY FROM users u WHERE u.username = 'demo';

INSERT INTO order_items (order_id, product_id, title, unit_price_cents, quantity)
SELECT o.id, p.id, p.title, p.price_cents, 1
FROM orders o, products p
WHERE o.user_id = (SELECT id FROM users WHERE username = 'demo')
  AND o.total_cents = 375900
  AND p.price_cents = 129900
LIMIT 1;

INSERT INTO order_items (order_id, product_id, title, unit_price_cents, quantity)
SELECT o.id, p.id, p.title, p.price_cents, 1
FROM orders o, products p
WHERE o.user_id = (SELECT id FROM users WHERE username = 'demo')
  AND o.total_cents = 375900
  AND p.price_cents = 245000
LIMIT 1;
