# ApexPlanet — Full Stack Web Development Internship (PHP & MySQL)

Sixty-day internship programme, **five tasks**, each independently runnable and
each with an automated test suite.

ApexPlanet Software Pvt. Ltd. · `www.apexplanet.in`

---

## Repository

**Public:** `https://github.com/keerthan-ms/apexplanet-fullstack-web-development`
Branch: `main`

| Task | Title | Timeline | Suite |
|---|---|---|---|
| [Task 1](task-1/README.md) | Foundation & Environment Setup | Days 1–12 | **97 passing** |
| [Task 2](task-2/README.md) | Frontend Development with Responsive Design | Days 13–24 | **127 passing** |
| [Task 3](task-3/README.md) | Backend Integration & CRUD | Days 25–36 | **80 passing** |
| [Task 4](task-4/README.md) | Real-World Project Development | Days 37–48 | **68 passing** |
| [Task 5](task-5/README.md) | Capstone Project + Deployment | Days 49–60 | **77 passing** |
| | | | **449 passing, 0 failing** |

---

## Quick start

```powershell
npm run setup          # once: portable PHP 8.3 + MariaDB 11.4 into ..\_runtime
npm run db:start       # start the database
npm run task:1         # serve a task: http://127.0.0.1:8081
npm run test:1         # run a task's test suite
```

Ports are `8080 + task number` — Task 5 serves on `:8085`.

### No XAMPP required

The machine this was built on has **no Apache, no MySQL and no PHP on PATH**, and
the installer is not running with administrator rights. `tools/setup-runtime.ps1`
therefore downloads a **portable** PHP and MariaDB into `_runtime/` (gitignored),
generates a random database password into the gitignored `shared/config.php`, and
uses PHP's built-in web server in place of Apache. Nothing touches the system
`PATH` and nothing needs elevation.

If a machine *does* have PHP on PATH, `tools/task.ps1` prefers it automatically.

### Demo accounts

| Task | Role | Email | Password |
|---|---|---|---|
| 3 | Admin | `admin` | `Admin@12345` |
| 3 | User | `demo` | `Demo@12345` |
| 4 | Admin | `admin@apexplanet.local` | `Admin@12345` |
| 4 | Customer | `demo@apexplanet.local` | `Demo@12345` |
| 5 | Admin | `admin@apexplanet.local` | `Admin@12345` |
| 5 | Student | `demo@apexplanet.local` | `Demo@12345` |

Full per-task instructions, URL maps and expected output are in each task's
README.

---

## Layout

```
fullstack-web-development/
├── package.json              npm run setup | task:1-5 | test:1-5 | db:*
├── tools/
│   ├── setup-runtime.ps1     portable PHP + MariaDB installer
│   └── task.ps1              single entry point: serve, test, db control
├── shared/                   used by every task
│   ├── bootstrap.php         session, security headers, CSRF
│   ├── Database.php          PDO wrapper with named connections
│   ├── helpers.php           escaping, output, redirects, render_header
│   ├── config.example.php    committed template
│   └── config.php            gitignored — real credentials live here
├── vendor-assets/            vendored so pages work offline
│   ├── bootstrap-5.3.3/
│   └── chartjs-4.4.1/
├── task-1/ … task-5/
│   ├── public/               the document root for that task
│   ├── database/             schema, migration, ER diagram
│   ├── tests/run-tests.php   that task's suite
│   └── README.md
└── _runtime/                 gitignored: portable PHP, MariaDB, logs
```

Each task is self-contained: it can be served, tested and read on its own, and
shares only `shared/` and `vendor-assets/`.

---

## Verification

### Test results

```
task-1 :   passed : 97    failed : 0   ALL TESTS PASSED
task-2 :   passed : 127   failed : 0   ALL TESTS PASSED
task-3 :   passed : 80    failed : 0   ALL TESTS PASSED
task-4 :   passed : 68    failed : 0   ALL TESTS PASSED
task-5 :   passed : 77    failed : 0   ALL TESTS PASSED
```

Task 4 was re-run three times consecutively to prove the suite restores the
seeded data it disturbs rather than degrading on each run.

Every PHP file in the repository parses cleanly:

```powershell
npm run lint
```

### What the suites actually assert

The suites drive real HTTP requests against a real server backed by a real
database — there are no mocks. Among them:

- **Task 1** — semantic HTML structure, responsive CSS, JavaScript behaviour,
  PHP fundamentals, and a `mysqli` CRUD round trip.
- **Task 2** — Bootstrap grid and components, client-side validation, password
  match, show/hide toggle, and the AJAX availability check.
- **Task 3** — hashed passwords, session auth, role gating, avatar upload type
  and size validation, SQL injection resistance, CSRF, and the deletion rules.
- **Task 4** — transactional checkout under a stock race, order lifecycle,
  product CRUD, the "in use ⇒ deactivate, do not delete" guard, and admin RBAC.
- **Task 5** — OTP hashed-at-rest, expiry, single-use, rate limits; registration
  gated on verification; AJAX contract including LIKE-wildcard escaping;
  enrolment uniqueness; course CRUD and its delete guard; RBAC; and a sweep
  asserting no page renders with a PHP warning.

---

## Security decisions

| Decision | Where |
|---|---|
| Passwords via `password_hash()` / `password_verify()` (bcrypt) | Tasks 3, 4, 5 |
| Every write is a prepared statement | all tasks |
| Per-session CSRF token on every POST | `shared/bootstrap.php` |
| Output escaped through `e()`; embedded JSON through `json_safe()` | `shared/helpers.php` |
| **OTP stored as SHA-256 only** — a database dump cannot be replayed | Task 5 `lib/Otp.php` |
| OTP: 15-min expiry, single-use, 10 attempts, 10 codes / 10 hours | Task 5 `lib/Otp.php` |
| Avatar upload validated by **actual MIME type**, not extension | Task 3 |
| Uploads stored outside the web root where possible | Task 3 |
| No credentials in the repository | `shared/config.php` is gitignored |
| `app.debug` off in production; display_errors on only locally | `shared/config.php` |

### Two helpers that must not be confused

`shared/helpers.php` exposes `json_safe($data): string` (an **escaping helper**
that returns a string for use inside HTML) and `json_out(array $payload, int
$status): void` (a **responder** that sets HTTP headers and echoes a body).
Mixing them up puts `headers already sent` warnings inside an inline `<script>`
and silently breaks the charts — Task 5's suite asserts against exactly that.

---

## Known gaps

Stated plainly rather than glossed over:

- **Task 1 portfolio is not published to GitHub Pages.** The repository is
  public and the site runs locally; Pages needs to be enabled in the repo
  settings, which requires a manual click.
- **Task 5 is not deployed.** 000webhost / InfinityFree both require an account
  I do not have. `task-5/README.md` §7 is a complete runbook for it.
- **OTP delivery defaults to the server log** (`console` transport), not a real
  mailbox, because no outbound mail is configured on this machine. The `mail`
  transport is implemented and the switch is one config value.
- **Demo videos and the LinkedIn/portal submission are manual** and cannot be
  done from here: 5-min (Task 1), 3–5-min (Task 2), 8-min (Task 3), 10-min
  (Task 4), 12-min (Task 5), plus a Task 5 project report PDF.
