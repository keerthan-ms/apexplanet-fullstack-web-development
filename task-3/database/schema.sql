-- ---------------------------------------------------------------------
-- Task 3 — User Management System
-- ApexPlanet Software Pvt. Ltd. · Full Stack Web Development Internship
--
-- Target database: apexplanet_users (separate from Task 1's demo tables so
-- every task stays independently runnable).
--
-- How to run
--   php task-3/database/migrate.php     (creates the schema and seeds it)
--   or through phpMyAdmin: select apexplanet_users, Import, choose this file.
--
-- ER summary
--   roles 1───< users 1───1 user_profiles
--   users  1───< login_attempts
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS roles (
    id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(20)     NOT NULL,
    description VARCHAR(120)    NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1NF: every column holds a single value.
-- 2NF: all non-key columns depend on the whole primary key.
-- 3NF: no non-key column depends on another non-key column. Role text lives in
--      `roles`, so users carries only role_id.
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username      VARCHAR(32)     NOT NULL,
    email         VARCHAR(254)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL COMMENT 'password_hash(), never a plain password',
    role_id       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    last_login_at DATETIME        NULL DEFAULT NULL,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email    (email),
    KEY idx_users_role (role_id),
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_users_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-to-one with users, split out so users stays narrow and optional
-- presentation data does not force a row for every account.
CREATE TABLE IF NOT EXISTS user_profiles (
    user_id     INT UNSIGNED NOT NULL,
    full_name   VARCHAR(60)  NOT NULL DEFAULT '',
    headline    VARCHAR(120) NOT NULL DEFAULT '',
    bio         VARCHAR(400) NOT NULL DEFAULT '',
    avatar_path VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'relative to task-3/storage/uploads',
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_profiles_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security evidence for the login form: proves rate limiting and gives the
-- report something real to show.
CREATE TABLE IF NOT EXISTS login_attempts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username_tried  VARCHAR(64)     NOT NULL,
    ip_address      VARCHAR(45)     NOT NULL DEFAULT '',
    was_successful  TINYINT(1)      NOT NULL DEFAULT 0,
    attempted_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_user_time (username_tried, attempted_at),
    KEY idx_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------------------

INSERT INTO roles (name, description) VALUES
  ('user',  'Standard account'),
  ('admin', 'Full access to the user manager')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Passwords below are password_hash() output for the demo passwords given in
-- the README. They are placeholders for a local lab database only.
--
--   admin  / Admin@12345
--   demo   / Demo@12345
--   locked / Locked@12345   (inactive, to demonstrate the is_active guard)
--
-- Generate fresh hashes at any time with:
--   php -r "echo password_hash('YourPassword1', PASSWORD_DEFAULT), PHP_EOL;"
--
INSERT INTO users (username, email, password_hash, role_id, is_active) VALUES
  ('admin', 'admin@apexplanet.local',
   '$2y$10$6/d3m.cpgvCKZKnWk8cwNOVQdUojIc4C/DGzsSnMjmMOms3c2ktPG',
   2, 1),
  ('demo', 'demo@apexplanet.local',
   '$2y$10$RYyLxppUkJoUnCkd70XXbuiQr6LrHL1xHNvAUctj7WrxNI/cP7kiu',
   1, 1),
  ('locked', 'locked@apexplanet.local',
   '$2y$10$WmXToSAoudqco2hITgrjWuplGQjsWMsMs2RDiNyICCgYiPVgbYgLO',
   1, 0)
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO user_profiles (user_id, full_name, headline)
SELECT id, 'ApexPlanet Administrator', 'Runs the user manager'
FROM users WHERE username = 'admin'
ON DUPLICATE KEY UPDATE headline = VALUES(headline);

INSERT INTO user_profiles (user_id, full_name, headline)
SELECT id, 'Demo User', 'Standard account'
FROM users WHERE username = 'demo'
ON DUPLICATE KEY UPDATE headline = VALUES(headline);

INSERT INTO user_profiles (user_id, full_name, headline)
SELECT id, 'Locked Account', 'Deactivated for the is_active test'
FROM users WHERE username = 'locked'
ON DUPLICATE KEY UPDATE headline = VALUES(headline);
