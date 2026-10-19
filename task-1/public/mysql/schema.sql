-- ---------------------------------------------------------------------
-- Task 1 — MySQL basics for the ApexPlanet Full Stack internship.
--
-- How to run this file
--   1. phpMyAdmin:  select the `apexplanet_fullstack` database, open Import,
--      choose this file and press Go.
--   2. Command line:
--        mariadb -u apexplanet -p apexplanet_fullstack < schema.sql
--      (or: mysql -u apexplanet -p apexplanet_fullstack < schema.sql)
--   3. From PHP: see ../mysql/index.php which also creates the tables.
--
-- Demonstrates: CREATE DATABASE/TABLE, primary keys, foreign keys,
-- INSERT / SELECT / UPDATE / DELETE, and normalisation to 1NF/2NF/3NF.
-- ---------------------------------------------------------------------

-- The database is created by the setup script. Uncomment if you prefer:
-- CREATE DATABASE IF NOT EXISTS apexplanet_fullstack
--   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE apexplanet_fullstack;

-- ---------------------------------------------------------------------
-- users — one row per person. Already in 3NF: every non-key column
-- depends on the key, nothing depends on a non-key column.
-- role is a lookup rather than free text so that reports stay consistent.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    username      VARCHAR(32)      NOT NULL,
    email         VARCHAR(254)     NOT NULL,
    password_hash VARCHAR(255)     NOT NULL,
    full_name     VARCHAR(60)      NOT NULL DEFAULT '',
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email    (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- posts — belongs to exactly one user.
-- The FOREIGN KEY is what makes this relational rather than a flat file,
-- and ON DELETE CASCADE keeps referential integrity when a user is removed.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS posts (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(120) NOT NULL,
    body       TEXT         NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_posts_user (user_id),
    CONSTRAINT fk_posts_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------------------

INSERT INTO users (username, email, password_hash, full_name, role) VALUES
  ('keerthan', 'keerthan@example.com',
   '$2y$10$4vJ1lP3Z0nQ8mT6yX9wUeAqS0R7bK2cD5fH1jL3nP6qS8tU0vW2y',
   'Keerthan M S', 'admin'),
  ('demo', 'demo@example.com',
   '$2y$10$9xQ2mN5pR7sT1vW3yA6bC8dE0fG4hJ6kL8mN0pQ2rS4tU6vW8xY0',
   'Demo User', 'user')
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO posts (user_id, title, body) VALUES
  ((SELECT id FROM users WHERE username = 'keerthan'),
   'First post',
   'This row was inserted with a prepared statement and linked to a user through a foreign key.'),
  ((SELECT id FROM users WHERE username = 'demo'),
   'Hello world',
   'A second post belonging to a different user, used to show JOIN working in both directions.');

-- ---------------------------------------------------------------------
-- Example read-only queries
-- ---------------------------------------------------------------------

-- SELECT with a JOIN:
--   SELECT u.username, u.role, COUNT(p.id) AS post_count
--   FROM users u
--   LEFT JOIN posts p ON p.user_id = u.id
--   GROUP BY u.id, u.username, u.role;

-- UPDATE a single row (always scope the WHERE clause):
--   UPDATE posts SET title = 'Updated title' WHERE id = 1;

-- DELETE a single row:
--   DELETE FROM posts WHERE id = 2;
