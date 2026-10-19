# Task 1 — Foundation & Environment Setup

**ApexPlanet Software Pvt. Ltd. · Full Stack Web Development Internship (PHP & MySQL)**

Deliverable: a personal portfolio website built with HTML, CSS and JavaScript,
with live PHP and MySQL demonstrations and an automated test suite.

---

## 1. What this task covers

| Requirement from the brief | Where it is demonstrated |
|---|---|
| Install PHP & MySQL, configure the servers | `tools/setup-runtime.ps1` — portable, no admin needed |
| Test PHP with a `hello.php` style file | `public/php/index.php` and the test suite |
| phpMyAdmin for database management | `public/mysql/schema.sql` (importable) + `public/mysql/index.php` |
| Git & GitHub setup, first repo and push | Repository history; see the root `README.md` |
| Semantic HTML5 (`header`/`nav`/`section`/`article`/`footer`) | `public/index.html` |
| Forms: `input`, `select`, `textarea`, `radio`, `checkbox` | `public/contact.html` |
| Multimedia: `audio`, `video`, `iframe` | `public/fundamentals.html` |
| Tables with `thead`, `tbody`, `tfoot` | `public/fundamentals.html` |
| Inline, internal and external CSS | inline + internal in `fundamentals.html`, external in `assets/css/style.css` |
| Flexbox & Grid | `assets/css/style.css` (`.nav`, `.hero`, `.grid`) |
| Colours, backgrounds, shadows, borders, gradients | `assets/css/style.css` |
| CSS animations & transitions | `assets/css/style.css` (`@keyframes float`, `transition:`) |
| Media queries for responsive design | `assets/css/style.css` (`@media (max-width: 860px)`, `680px`) |
| JS: variables, loops, functions, arrays | `assets/js/main.js` |
| JS: DOM (`getElementById`, `querySelector`) | `assets/js/main.js` |
| JS: events (`click`, `keyup`, `change`) | `assets/js/main.js`, `assets/js/validation.js` |
| JS: form validation (required, email, password length) | `assets/js/validation.js` |
| PHP syntax, variables, indexed/associative/multidimensional arrays | `public/php/index.php` §1–2 |
| PHP functions & control structures (`if`/`else`/`switch`/loops) | `public/php/index.php` §3 |
| PHP forms with `$_GET` and `$_POST` | `public/php/index.php` §4 |
| PHP `include()` and `require()` | `public/php/index.php`, `public/php/inc/` |
| MySQL: create database & tables | `public/mysql/schema.sql` |
| MySQL: Insert, Select, Update, Delete | `public/mysql/index.php` |
| MySQL: primary keys, foreign keys | `schema.sql` — `posts.user_id → users.id` |
| MySQL: connect with `mysqli_connect()` | `public/mysql/index.php` §1 |

---

## 2. Run it

### One-time setup

```powershell
# Installs a portable PHP + MariaDB into ..\_runtime (no admin rights required)
powershell -ExecutionPolicy Bypass -File tools\setup-runtime.ps1
```

### Start the application

```powershell
php -S 127.0.0.1:8081 -t task-1\public
```

Then open:

| URL | Page |
|---|---|
| <http://127.0.0.1:8081/> | Portfolio home |
| <http://127.0.0.1:8081/about.html> | About |
| <http://127.0.0.1:8081/projects.html> | Projects |
| <http://127.0.0.1:8081/fundamentals.html> | Tables, multimedia, CSS techniques |
| <http://127.0.0.1:8081/contact.html> | Form with live validation |
| <http://127.0.0.1:8081/php/index.php> | PHP fundamentals demo |
| <http://127.0.0.1:8081/mysql/index.php> | MySQL CRUD demo |

Press `Ctrl+C` to stop the server.

### Run the tests

```powershell
php task-1\tests\run-tests.php
```

Exit code `0` means every check passed. The suite starts its own temporary web
server, so **stop the server above first** (it uses port 8099, so they can
coexist, but stopping is cleaner).

---

## 3. Expected output

`run-tests.php` prints grouped results and a summary. A healthy run looks like:

```
1. Environment          PHP version, mysqli, pdo_mysql, mbstring, json
2. Required files       all deliverable files present
3. HTML5 semantics      landmarks, forms, tables, multimedia
4. CSS3 features        flexbox, grid, gradients, @keyframes, @media
5. PHP helpers          e() escaping, badge_class()
6. MySQL connectivity   connect, schema, foreign key, prepared statements
7. HTTP smoke test      every route returns 200 with expected content
Summary                 passed / failed / skipped, then ALL TESTS PASSED
```

The MySQL section performs a live prepared-statement round trip and then
inserts `Robert'); DROP TABLE posts;--` as a record body, reads it back
byte-for-byte, and asserts the `posts` table still exists. That is the SQL
injection check required by Task 3's security brief, verified early.

---

## 4. Verification checklist

Run through these manually after starting the server:

- [ ] **Responsive** — narrow the window below 680 px; the header collapses to a
      "Menu" button and the layout becomes a single column.
- [ ] **Forms** — submit `contact.html` empty; every required field reports an
      error. Enter `abc` as the email; the format rule fires. Enter a 6-character
      password; the length rule fires.
- [ ] **Live character count** — typing in the message box updates the counter.
- [ ] **Show password** — the checkbox switches the password input type.
- [ ] **Tables** — `fundamentals.html` shows a header row, body rows and a
      bolded footer row.
- [ ] **Multimedia** — the audio player and video player both load; the iframe
      renders the About page.
- [ ] **PHP demo** — search `ch` in the fruits box; "1 match" is reported. Click
      *Descending*; the order reverses.
- [ ] **MySQL demo** — insert a post, edit its body and save, then delete it.
      Confirm the confirmation prompt appears before deleting.
- [ ] **Reduced motion** — enable "Reduce motion" in your OS settings and
      reload; the floating avatar stops animating.

---

## 5. Notes and limitations

- **Apache vs the built-in server.** The brief asks for XAMPP/WAMP/LAMP with
  Apache. This environment has no administrator rights, so Apache could not be
  installed. The PHP built-in server (`php -S`) is used instead — it executes the
  same PHP and is sufficient for development. Deployment notes for real hosting
  are in `docs/DEPLOYMENT.md`.
- **phpMyAdmin.** Also unavailable without Apache. `schema.sql` is written to be
  imported directly through phpMyAdmin on a normal XAMPP install, and
  `mysql/index.php` creates the same tables without it.
- **Database engine.** MariaDB 11.4 is used instead of Oracle MySQL. It is wire
  compatible and every feature used here (`AUTO_INCREMENT`, `ENUM`, foreign keys,
  prepared statements, `utf8mb4`) behaves identically. Swap the server and no
  code changes are needed.
- **Demo credentials.** The seeded accounts are placeholders. `schema.sql` uses
  `INSERT IGNORE`, so re-running it never duplicates rows.
- **Contact form.** `contact.html` validates in the browser and does not transmit
  anything; it is a front-end demonstration as required by Task 1. Server-side
  handling is implemented in Tasks 2 and 3.

---

## 6. Project layout

```
task-1/
├── README.md
├── public/                       ← document root
│   ├── index.html                portfolio home
│   ├── about.html
│   ├── projects.html
│   ├── fundamentals.html         tables, multimedia, CSS techniques
│   ├── contact.html              full form + validation
│   ├── assets/
│   │   ├── css/style.css         external stylesheet
│   │   ├── js/main.js            navigation, reveal, counters
│   │   ├── js/validation.js      form validation rules
│   │   ├── img/                  avatar, favicon, video poster (SVG)
│   │   └── media/                generated tone.wav and demo.mp4
│   ├── php/
│   │   ├── index.php             PHP fundamentals demo
│   │   └── inc/                  include()/require() partials
│   └── mysql/
│       ├── index.php             mysqli CRUD demo
│       ├── schema.sql            importable schema
│       └── inc/                  shared partials
└── tests/
    └── run-tests.php             automated suite (97 checks)
```
