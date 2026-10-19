# Task 5 — Capstone Project & Deployment

**Project: ApexPlanet Learn** — an e-learning portal
ApexPlanet Software Pvt. Ltd. · Full Stack Web Development Internship (PHP & MySQL)

---

## 1. Project selection

The brief offered four options — *E-learning Portal, Job Board, Blogging CMS,
Web Application Pentest Report* — and asked for a **unique idea**. **E-learning
Portal** was chosen because it is the only one of the four that forces every
capability the capstone is scored on into a single coherent product:

| Capstone requirement | Where it lands here |
|---|---|
| Authentication with **email OTP verification** | `register.php` → `verify.php` → `login.php`, enforced by `lib/Otp.php` |
| Core module (**Courses & Enrollment**) | catalogue, course pages, enrolment, My learning |
| **AJAX** real-time search and filtering | `api/search-courses.php` + `assets/js/search.js` |
| **Analytics dashboard** (Chart.js) | `admin/index.php` |
| **ER diagram** | [`database/ER-DIAGRAM.md`](database/ER-DIAGRAM.md) |
| **Wireframes** for Home, Login, Dashboard, Admin | §3 below |
| **Deployment** on free hosting | §7 below |

A job board would have needed an application module without a content model; a
blogging CMS has no enrolment concept at all. The portal has a natural place for
each requirement.

---

## 2. Requirement document

### 2.1 Features

| Area | Feature |
|---|---|
| Auth | Register with name, email, password |
| Auth | **Six-digit email OTP**, required before the first sign-in |
| Auth | OTP stored only as a SHA-256 hash, 15-minute expiry, single-use |
| Auth | Rate limited: 10 codes per 10 hours, 10 wrong attempts per code |
| Auth | Sign in, sign out, role-based session |
| Catalogue | Server-rendered list with category, level and price filters |
| Catalogue | **AJAX search** — updates results, count and URL without a reload |
| Catalogue | Pagination (9 per page) with filters preserved across pages |
| Catalogue | LIKE wildcards and quotes escaped; unknown filter values ignored |
| Courses | Course detail page with ordered lesson list |
| Courses | Enrolment with a unique constraint per user and course |
| Learning | "My learning" dashboard with per-course progress |
| Admin | Analytics dashboard: six statistics and two Chart.js charts |
| Admin | OTP activity: issued, consumed, still valid |
| Admin | Course CRUD with server-side validation |
| Admin | Publish / unpublish toggle |
| Admin | Delete guarded: a course with students is unpublished, not deleted |

### 2.2 User roles

| Role | May do |
|---|---|
| **Guest** | Browse and search the catalogue, read course pages, register, sign in |
| **Student** | Everything a guest can do, plus enrol, view My learning, read lessons |
| **Admin** | Everything a student can do, plus the analytics dashboard and course CRUD |

Roles are enforced in PHP (`require_admin()`), not only by hiding a link: the
suite asserts that a student following `/admin/index.php` directly is redirected
away.

### 2.3 Use cases

**UC-01 — Register and verify**
*Actor:* guest · *Precondition:* the email is unused
The visitor registers, is redirected to the verification page, and enters the
six-digit code. Until it is accepted, `email_verified` stays `0` and sign-in is
refused with an instruction to verify rather than a generic failure.

**UC-02 — Find a course without waiting for a page reload**
*Actor:* guest or student · *Precondition:* none
Typing in the search box issues `GET /api/search-courses.php?q=…`. The server
returns `{ total, pages, page, html }`; the script replaces the grid and the
result count and pushes the query into the address bar with `history.replaceState`.
With JavaScript disabled the same page still works — the form submits normally.

**UC-03 — Enrol**
*Actor:* student · *Precondition:* signed in, course published
The student opens a course and enrols. The unique key
`uq_enrollments_user_course` makes a second attempt a no-op rather than a
duplicate row.

**UC-04 — Review the platform**
*Actor:* admin · *Precondition:* signed in as admin
The dashboard shows enrolments per day over the last 14 days (bar) and
enrolments by level (doughnut), plus OTP verification activity. Every chart has
a visually hidden data table so the numbers are readable without the graphic.

**UC-05 — Retire a course that still has students**
*Actor:* admin · *Precondition:* the course has enrolments
Deleting is refused in favour of unpublishing. `enrollments.course_id` is
`ON DELETE CASCADE`, so a literal delete would silently erase every student's
record; the guard says so in the message it returns.

### 2.4 Non-functional requirements

- Every query against user-supplied input is a prepared statement.
- Passwords use `password_hash()` / `password_verify()` (bcrypt).
- All POST routes require a per-session CSRF token.
- Output is escaped through `e()`; AJAX-embedded JSON goes through `json_safe()`.
- Usable down to 320 px; charts have accessible text alternatives.

---

## 3. Design

### 3.1 Entity relationship diagram

Six tables, five foreign keys, full normalisation argument and index rationale:
[`database/ER-DIAGRAM.md`](database/ER-DIAGRAM.md).

```
users ──1───< enrollments >──1─── courses >──1─── categories
users ──1───< otp_codes          courses ──1───< lessons
```

### 3.2 Wireframes

#### Home (`/index.php`)

```
+------------------------------------------------------------------+
| ApexPlanet   Courses   My learning   [student ▾]  Log out        |
+------------------------------------------------------------------+
| Courses                                              (count: 6)  |
| [ search ........................ ] [Category▾][Level▾][Price▾] |
| [ Apply ]                                                       |
|                                                                  |
| +------------------+ +------------------+ +------------------+   |
| | WD101            | | WD204            | | CS110            |   |
| | Secure PHP from  | | Modern CSS       | | OWASP Top 10     |   |
| | the Ground Up    | | Layouts          | | in Practice      |   |
| | [Web Dev]        | | [Web Dev]        | | [Cybersecurity]  |   |
| | Beginner         | | Intermediate     | | Beginner         |   |
| | Free             | | Free             | | Free             |   |
| | [Open course]    | | [Open course]    | | [Open course]    |   |
| +------------------+ +------------------+ +------------------+   |
|           (3 more cards per row, 9 per page)                    |
|                                                                  |
|            ‹ 1 Previous    Page 1 of 1    Next ›                |
+------------------------------------------------------------------+

search box -> fetch /api/search-courses.php -> replace grid in place
```

#### Login (`/login.php`) and OTP verification (`/verify.php`)

```
+------------------------------+   +------------------------------+
| Sign in                      |   | Check your email             |
|                              |   |                              |
| Email    [ .............. ]  |   | We sent a six-digit code to  |
| Password [ .............. ]  |   | demo@example.test            |
|                              |   |                              |
| [ show ]                     |   | Code  [ _ _ _ _ _ _ ]        |
|                              |   |                              |
| [ Sign in ]                  |   | [ Verify ]                   |
|                              |   |                              |
| No account? Register         |   | Send a new code   08:41 left |
| Forgot password?             |   | Wrong code? Send a new one.  |
+------------------------------+   +------------------------------+

unverified account  ->  "Verify your email before signing in."
wrong or expired    ->  "That code is not correct..."
10 wrong attempts   ->  code invalidated, new one required
```

#### Student dashboard (`/my-courses.php`)

```
+------------------------------------------------------------------+
| ApexPlanet   Courses   My learning   [student ▾]  Log out        |
+------------------------------------------------------------------+
| My learning                                                       |
|                                                                  |
| +------------------+ +------------------+                        |
| | WD101            | | DB150            |                        |
| | Secure PHP from  | | SQL and          |                        |
| | the Ground Up    | | Normalisation    |                        |
| | ▓▓▓▓▓▓░░░░ 45%   | | ▓▓▓▓▓▓▓▓▓▓ 100% |                        |
| | [ Continue ]     | | Completed        |                        |
| +------------------+ +------------------+                        |
|                                                                  |
| Nothing here yet?  Browse the catalogue.                         |
+------------------------------------------------------------------+
```

#### Admin (`/admin/index.php` and `/admin/courses.php`)

```
+------------------------------------------------------------------+
| ApexPlanet  Dashboard  Courses  My learning  [admin ▾] Log out   |
+------------------------------------------------------------------+
| Admin dashboard                                                   |
| +------+ +------+ +------+ +------+ +------+ +------+            |
| |  2   | |  2   | |  0   | |  6   | |  1   | |  3   |            |
| |Studnt| |Verif | |Await | |Publsh| |Drafts| |Enrol |            |
| +------+ +------+ +------+ +------+ +------+ +------+            |
| +------------------------------+ +------------------------+      |
| | Enrolments per day (14 days) | | Enrolments by level    |      |
| |  ▂▅▂▅▂▅▂ ▅▂▅ (bar chart)     | |   ◔ beginner           |      |
| |  [data table for screen      | |   ◑ intermediate       |      |
| |   readers below the chart]   | |   ○ advanced           |      |
| +------------------------------+ +------------------------+      |
| OTP verification activity                                        |
| +----------+ +----------+ +----------+                           |
| |  4 issued| | 3 used   | | 1 valid  |                           |
| +----------+ +----------+ +----------+                           |
+------------------------------------------------------------------+

/admin/courses.php
| [ code ] [ title ] [ category ] [ level ] [ price ] [ enrolled ] |
| WD101   Secure PHP  Web Dev     beginner  Free       1           |
|         [Edit][Unpublish][Delete]                                |
| + Add a course / Edit form with validation above the table +     |
| delete with enrolments  ->  "unpublished instead of deleted"     |
```

### 3.3 Mobile (320 px)

```
+--------------------------+
| ApexPlanet  [ Menu ]     |
+--------------------------+
| Courses            (6)  |
| [ search ............. ] |
| [Category▾][Level▾]      |
| +----------------------+ |
| | WD101                | |
| | Secure PHP from the  | |
| | Ground Up            | |
| | Free  [Open course]  | |
| +----------------------+ |
|   (cards stack, one      |
|    per row)              |
+--------------------------+
```

---

## 4. Run it

```powershell
npm run setup                        # once: portable PHP + MariaDB
npm run db:start                     # if MariaDB is not running
php task-5\database\migrate.php      # schema + seed (--fresh to reset)
npm run task:5                       # http://127.0.0.1:8085
```

### Demo accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@apexplanet.local` | `Admin@12345` |
| Student | `demo@apexplanet.local` | `Demo@12345` |

### URL map

| URL | Page |
|---|---|
| `/index.php` | Course catalogue with AJAX search and filters |
| `/course.php?id=N` | Course detail with lessons |
| `/register.php` → `/verify.php` | Registration and OTP verification |
| `/login.php` · `/logout.php` | Authentication |
| `/my-courses.php` | Student dashboard |
| `/enrol.php` | Enrolment (POST, CSRF protected) |
| `/api/search-courses.php` | AJAX JSON endpoint |
| `/admin/index.php` | Analytics dashboard (Chart.js) |
| `/admin/courses.php` | Course CRUD |

### Tests

```powershell
npm run test:5
```

**77 assertions, 0 failures.** The suite covers schema and foreign keys, OTP
hashing / expiry / single-use / rate limits, registration gating sign-in until
verified, the AJAX contract including LIKE-escaping and injection attempts,
enrolment uniqueness and CSRF, admin RBAC, course CRUD with its delete guard,
and a sweep asserting that no page renders with a PHP warning.

---

## 5. Expected output

- A guest can search the catalogue and see results update without a reload.
- A new registration cannot sign in until the six-digit code is entered.
- The OTP appears in the server log (console transport), never in the database.
- The student can enrol and see the course move into *My learning*.
- The admin dashboard shows live statistics and two charts.
- Deleting a course with students unpublishes it and says why.
- `npm run test:5` prints `ALL TESTS PASSED`.

---

## 6. Design notes

### The OTP is never stored

`otp_codes.code_hash` is a `CHAR(64)` SHA-256 digest of the code. The plaintext
exists only in the message being sent, so a database dump cannot be replayed to
take over an account. Verification re-hashes the submitted code and compares
with `hash_equals()`.

Four independent controls sit behind that: a 15-minute `expires_at`, a
`consumed_at` marker making each code single-use, an `attempts` column capped at
10 (mirrored by `ck_otp_attempts`), and a 10-codes-per-10-hour limit in
`Otp::issue()`.

### Delivery transport

`Otp::deliver()` supports `console` and `mail`. The shipped configuration is
**`console`**, which writes the code to the server error log — deliberately not
`mail`, because there is no verified outbound mail configured on this machine and
an SMTP failure would leave a registration permanently stuck. The console
transport is what lets the automated suite read the code the same way an operator
would, and the test asserts that path rather than fabricating a code.

Switching to real delivery means setting `security.otp_transport = 'mail'` in
`shared/config.php`.

### The AJAX contract

`GET /api/search-courses.php` returns

```json
{ "total": 1, "pages": 1, "page": 1, "html": "<div …>…</div>" }
```

`html` is a pre-rendered fragment from `partials/course-cards.php` — the same
partial the server-rendered page uses, so the two paths cannot drift apart.

Three failure modes are closed:

- `$_GET` values are validated against an allow-list, so `level=hacked` is
  ignored rather than interpolated.
- `%` and `_` are backslash-escaped in the `LIKE` pattern, so a wildcard in the
  search box is a literal.
- An unknown filter or a bad `page` degrades to "all published courses" instead
  of an error.

The endpoint is `GET`-only, and an unauthorised `POST` is answered with JSON
(`{ "error": … }`, 419) rather than an HTML page a `fetch()` caller cannot parse.

### Two helpers that must not be confused

`shared/helpers.php` exposes:

- `json_safe($data): string` — an **escaping helper** that returns a string, for
  embedding JSON inside an HTML page (chart data, `<script>` literals).
- `json_out(array $payload, int $status): void` — a **responder** that sets HTTP
  headers and echoes a body, for API endpoints.

Using the responder inside an HTML page emits headers after output has begun and
leaves `headers already sent` warnings in the middle of the `<script>` block —
which silently breaks every chart. The suite asserts both that the dashboard
emits no PHP warnings and that chart labels are a valid inline JSON array.

### Chart data is rendered twice

Each chart is accompanied by a `visually-hidden` table holding the same numbers,
so the dashboard is readable without colour perception or the graphic loading.

### Redirects are document-root relative

`task_url()` resolves against the task root, so a redirect issued while the
browser is on `/admin/…` still lands on `/login.php` rather than `/admin/login.php`
(which would 404).

---

## 7. Deployment

The brief asks for free hosting (000webhost / InfinityFree) linked to this
GitHub repository. **This has not been done yet** — it needs an account I do not
have, and the runbook is written so it can be completed in one sitting.

### What the host must provide

| Requirement | Why |
|---|---|
| PHP 8.1+ | `declare(strict_types=1)`, `enum`-backed `PDO::FETCH_ASSOC` behaviour |
| MySQL 5.7 / MariaDB 10.4+ | `CHECK` constraints, `FULLTEXT` index, `utf8mb4` |
| `pdo_mysql` | `shared/Database.php` |
| HTTPS | Cookies are issued with `Secure` outside local development |

Both 000webhost and InfinityFree meet this.

### Steps

1. **Push the repository.** `git push origin main` — already done; the public
   repo is linked below.
2. **Create the database** in the host's control panel and import
   `task-5/database/schema.sql` through phpMyAdmin, **or** run
   `php task-5/database/migrate.php` over SSH if the host offers it. The
   migration is idempotent, so it is safe to re-run.
3. **Upload `task-5/public/`** to the document root (via the host's file manager,
   FTP, or `git pull` if the host supports it). Do **not** upload
   `task-5/database/`, `task-5/tests/`, or `shared/config.php`.
4. **Create `shared/config.php` on the host** from `shared/config.example.php`,
   filling in the database name, user and password the host gave you. This file
   is gitignored on purpose — never commit real credentials.
5. **Set the base directory** so `apex_register_base()` points at the deployed
   document root.
6. **Point the OTP transport at `mail`.** The host's PHP `mail()` will work
   without any SMTP configuration on most free hosts.
7. **Smoke-test:** register, receive the code, verify, sign in, enrol, then sign
   in as admin and open the dashboard.
8. **Connect the repo.** 000webhost and InfinityFree both offer GitHub
   integration under the site's *Deployment* / *Git* tab, so future pushes update
   the live site.

### Before going live

- `APP_DEBUG = false` in `shared/config.php` — display_errors is on for local
  development and must be off in production.
- Confirm `shared/config.php` is not web-reachable (it lives outside `public/`).
- Change the seeded demo passwords.

---

## 8. Limitations

- **Not yet deployed.** §7 is the runbook; it needs a hosting account.
- **OTP delivery defaults to the log**, not a real mailbox, for the reason
  described in §6.
- Payment for paid courses is out of scope: `price_cents` is displayed but no
  checkout exists. The brief asked for courses and enrolment, not billing.
- Lessons have no per-student completion tracking; `progress_percent` is a stored
  value the seed data sets, and there is no "mark lesson complete" action yet.
- There is no "forgot password" flow. The `password_reset` OTP purpose is in the
  schema and `Otp` supports it, but no page issues a reset code yet.

---

## 9. Submission checklist

| Deliverable | State |
|---|---|
| Live deployment (000webhost / InfinityFree) | **Not done — needs a hosting account** (§7) |
| GitHub repository, public | `https://github.com/keerthan-ms/apexplanet-fullstack-web-development` |
| ER diagram | [`database/ER-DIAGRAM.md`](database/ER-DIAGRAM.md) |
| Wireframes | §3.2 |
| Automated tests | `npm run test:5` — 77 passing |
| Project report (PDF) | **Pending** |
| 12-minute presentation video | **Pending — manual** |
| LinkedIn upload + ApexPlanet portal submission | **Pending — manual** |
