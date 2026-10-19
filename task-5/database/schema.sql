-- ---------------------------------------------------------------------
-- Task 5 — Capstone: "ApexPlanet Learn" e-learning portal
-- ApexPlanet Software Pvt. Ltd. · Full Stack Web Development Internship
--
-- Target database: apexplanet_lms
--
-- Run with:
--   php task-5/database/migrate.php
--   php task-5/database/migrate.php --fresh
--
--   users ──1───< enrollments >──1─── courses >──1─── categories
--   users ──1───< otp_codes          courses ──1───< lessons
--
-- Nothing here stores a password in plain text, and no OTP is stored in a form
-- that would let a database read recover it.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(80)   NOT NULL,
    email         VARCHAR(254)  NOT NULL,
    password_hash VARCHAR(255)  NOT NULL,
    role          ENUM('student','instructor','admin') NOT NULL DEFAULT 'student',
    email_verified TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lms_users_email (email),
    KEY idx_lms_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OTPs are stored as a SHA-256 hash, never as the code itself. A leaked table
-- therefore cannot be replayed to take over an account, and the plaintext only
-- ever exists in the message that was sent.
CREATE TABLE IF NOT EXISTS otp_codes (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED    NOT NULL,
    code_hash    CHAR(64)        NOT NULL COMMENT 'sha256 of the code, never the code',
    purpose      ENUM('email_verification','password_reset') NOT NULL DEFAULT 'email_verification',
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    consumed_at  DATETIME        NULL DEFAULT NULL,
    expires_at   DATETIME        NOT NULL,
    created_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_otp_user_purpose (user_id, purpose, consumed_at),
    CONSTRAINT fk_otp_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_otp_attempts CHECK (attempts <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(60)  NOT NULL,
    slug        VARCHAR(60)  NOT NULL,
    description VARCHAR(200) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_lms_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    category_id   INT UNSIGNED  NOT NULL,
    code          VARCHAR(20)   NOT NULL,
    title         VARCHAR(140)  NOT NULL,
    summary       VARCHAR(300)  NOT NULL,
    description   TEXT          NOT NULL,
    level         ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
    price_cents   INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT '0 means free',
    duration_min  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_published  TINYINT(1)    NOT NULL DEFAULT 0,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lms_courses_code (code),
    KEY idx_courses_category (category_id),
    KEY idx_courses_published_level (is_published, level),
    FULLTEXT KEY ft_courses_search (title, summary, description),
    CONSTRAINT fk_courses_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lessons (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_id   INT UNSIGNED NOT NULL,
    position    SMALLINT UNSIGNED NOT NULL,
    title       VARCHAR(140) NOT NULL,
    body        TEXT         NOT NULL,
    duration_min SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lessons_course_position (course_id, position),
    CONSTRAINT fk_lessons_course
        FOREIGN KEY (course_id) REFERENCES courses (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_lessons_position CHECK (position > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- progress_percent is denormalised onto the enrolment so the student
-- dashboard can render without aggregating on every request. course_id is
-- carried on this row as well so a progress update needs one statement.
CREATE TABLE IF NOT EXISTS enrollments (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL,
    course_id        INT UNSIGNED NOT NULL,
    status           ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    enrolled_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at     DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_enrollments_user_course (user_id, course_id),
    KEY idx_enrollments_course (course_id),
    KEY idx_enrollments_status (status),
    CONSTRAINT fk_enrollments_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_enrollments_course
        FOREIGN KEY (course_id) REFERENCES courses (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_enrollments_progress CHECK (progress_percent <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Seed data. Password hashes are substituted at migration time so this file
-- never contains one.
-- ---------------------------------------------------------------------
INSERT INTO users (full_name, email, password_hash, role, email_verified) VALUES
  ('ApexPlanet Admin', 'admin@apexplanet.local', '__HASH_ADMIN__', 'admin', 1),
  ('Demo Student',     'demo@apexplanet.local',  '__HASH_DEMO__',  'student', 1)
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO categories (name, slug, description) VALUES
  ('Web Development', 'web-dev',     'HTML, CSS, JavaScript, PHP and databases'),
  ('Cybersecurity',   'cybersecurity','Defensive security and secure coding'),
  ('Data',            'data',        'SQL, modelling and analysis'),
  ('Career',          'career',      'Interviewing, CVs and workplace practice')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO courses (category_id, code, title, summary, description, level, price_cents, duration_min, is_published)
SELECT c.id, x.code, x.title, x.summary, x.description, x.level, x.price_cents, x.duration_min, x.is_published
FROM (
    SELECT 'web-dev' AS cat, 'WD101' AS code, 'Secure PHP from the Ground Up' AS title,
           'Build PHP applications that do not fall over, or get injected.' AS summary,
           'A hands-on introduction to PHP: syntax, arrays, functions, forms, sessions, and the security decisions that matter at every layer.' AS description,
           'beginner' AS level, 0 AS price_cents, 240 AS duration_min, 1 AS is_published
    UNION ALL SELECT 'web-dev', 'WD204', 'Modern CSS Layouts',
           'Flexbox, Grid and responsive design without the guesswork.',
           'Layout techniques that hold up from a 320px phone to a wide desktop, plus accessibility and reduced-motion support.',
           'intermediate', 0, 180, 1
    UNION ALL SELECT 'cybersecurity', 'CS110', 'OWASP Top 10 in Practice',
           'Find and fix the ten most common web vulnerabilities.',
           'Injection, XSS, CSRF, broken access control and the rest, each with a working reproduction and its mitigation.',
           'beginner', 0, 300, 1
    UNION ALL SELECT 'cybersecurity', 'CS220', 'Network Defence Essentials',
           'Scanning, packet analysis and hardening a host.',
           'How to run an authorised assessment, interpret the results, and decide what actually needs fixing.',
           'intermediate', 149900, 210, 1
    UNION ALL SELECT 'data', 'DB150', 'SQL and Normalisation',
           'From SELECT to third normal form, with worked examples.',
           'Query fundamentals, join design, indexing strategy, and why a denormalised table sometimes is the right call.',
           'beginner', 0, 195, 1
    UNION ALL SELECT 'data', 'DB260', 'Analytics with SQL',
           'Turn raw rows into a number someone can act on.',
           'Aggregation, window functions and cohort analysis, applied to realistic reporting questions.',
           'advanced', 129900, 240, 0
    UNION ALL SELECT 'career', 'CR100', 'Technical Interviews That Work',
           'Prepare without burning out.',
           'A repeatable approach to coding rounds, system design and the questions people actually get asked.',
           'beginner', 0, 150, 1
) AS x
JOIN categories c ON c.slug = x.cat
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO lessons (course_id, position, title, body, duration_min)
SELECT co.id, l.pos, l.title, l.body, l.dur
FROM (
    SELECT 'WD101' AS code, 1 AS pos, 'Why PHP still matters' AS title,
           'Where PHP fits today, what it is good at, and what to reach for instead.' AS body, 25 AS dur
    UNION ALL SELECT 'WD101', 2, 'Syntax, arrays and control flow',
           'Variables, the three array shapes PHP gives you, and choosing between if, switch and match.', 45
    UNION ALL SELECT 'WD101', 3, 'Forms and input validation',
           'Handling GET and POST safely, and why the browser is never the one enforcing the rules.', 40
    UNION ALL SELECT 'WD101', 4, 'Sessions and authentication',
           'Password hashing, session fixation, and the small number of decisions that keep accounts safe.', 55
    UNION ALL SELECT 'WD101', 5, 'Prepared statements and SQL injection',
           'Reproducing an injection, then removing it, and understanding why binding beats escaping.', 75
    UNION ALL SELECT 'CS110', 1, 'Why the Top 10 still matters',
           'How the list has stayed relevant, and how to use it as a checklist rather than a checklist to memorise.', 30
    UNION ALL SELECT 'CS110', 2, 'Injection in practice',
           'Reproducing and fixing SQL injection on an intentionally vulnerable target.', 70
    UNION ALL SELECT 'CS110', 3, 'Cross-site scripting',
           'Stored and reflected XSS, why output encoding is the real fix, and where CSP fits in.', 65
    UNION ALL SELECT 'CS110', 4, 'CSRF and access control',
           'Forged cross-site requests, token defences, and the checks that belong on every page.', 60
    UNION ALL SELECT 'DB150', 1, 'SELECT to the first join',
           'Filtering, ordering and joining without guessing at the syntax.', 50
    UNION ALL SELECT 'DB150', 2, 'Normalisation explained',
           '1NF, 2NF and 3NF with examples of where each one is violated in real schemas.', 60
    UNION ALL SELECT 'DB150', 3, 'Indexes and query plans',
           'Reading EXPLAIN and deciding which index is worth its write cost.', 85
) AS l
JOIN courses co ON co.code = l.code
WHERE NOT EXISTS (SELECT 1 FROM lessons x WHERE x.course_id = co.id AND x.position = l.pos);

INSERT INTO enrollments (user_id, course_id, status, progress_percent)
SELECT u.id, c.id, 'active', 45
FROM users u, courses c
WHERE u.email = 'demo@apexplanet.local' AND c.code = 'WD101'
ON DUPLICATE KEY UPDATE progress_percent = VALUES(progress_percent);

INSERT INTO enrollments (user_id, course_id, status, progress_percent)
SELECT u.id, c.id, 'completed', 100
FROM users u, courses c
WHERE u.email = 'demo@apexplanet.local' AND c.code = 'DB150'
ON DUPLICATE KEY UPDATE progress_percent = VALUES(progress_percent);
