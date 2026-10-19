# Task 3 — Backend Development, Authentication & User Management

**ApexPlanet Software Pvt. Ltd. · Full Stack Web Development Internship (PHP & MySQL)**

Deliverable: a user management system with full CRUD, hashed-password
authentication, role-based access and validated profile picture upload.

---

## 1. What this task covers

| Requirement from the brief | Where it is |
|---|---|
| ER diagram (`users`, `roles`) | [`database/ER-DIAGRAM.md`](database/ER-DIAGRAM.md) |
| Normalise to 1NF, 2NF, 3NF | `database/schema.sql` header + the `roles` lookup table |
| PHP form to add users | `public/users/create.php` |
| Fetch data in an HTML table | `public/users/index.php` |
| Update existing records | `public/users/edit.php` |
| Delete with a confirmation popup | `public/users/index.php` → `public/users/delete.php` |
| Registration with `password_hash` | `public/register.php` |
| Login and logout using sessions | `public/login.php`, `public/logout.php` |
| Role-based login (User / Admin) | `shared/helpers.php` (`is_admin`, `require_admin`) |
| Prepared statements (`mysqli_prepare`) | Everywhere — see [§5](#5-sql-injection) |
| Validate all input server-side | Every POST handler re-validates |
| Store passwords encrypted | `password_hash()` / `password_verify()` |
| Edit profile page | `public/profile.php` |
| Profile picture upload with size and type validation | `public/profile.php` |
| Display profile information dynamically | `public/profile.php`, `public/index.php` |

---

## 2. Run it

```powershell
npm run setup                # once
npm run db:start             # if MariaDB is not already running
php task-3\database\migrate.php   # create the schema and seed demo accounts
npm run task:3               # serves on http://127.0.0.1:8083
```

Open <http://127.0.0.1:8083/login.php>.

| URL | Page |
|---|---|
| `/login.php` | Sign in |
| `/register.php` | Create an account |
| `/index.php` | Dashboard (statistics, recent sign-in attempts) |
| `/users/index.php` | User list with search, edit and delete |
| `/users/create.php` | Add a user (admin only) |
| `/users/edit.php?id=N` | Edit a user (admin only) |
| `/profile.php` | Own profile and avatar upload |
| `/download.php?file=…` | Serves a stored avatar |

Run the tests:

```powershell
npm run test:3
```

---

## 3. Demo accounts

Seeded by `database/schema.sql`. These are throwaway local credentials.

| Username | Password | Role | Status |
|---|---|---|---|
| `admin` | `Admin@12345` | admin | active |
| `demo` | `Demo@12345` | user | active |
| `locked` | `Locked@12345` | user | **inactive** — sign-in is refused |

The inactive account exists so the `is_active` guard can be demonstrated, not
just described.

---

## 4. Expected output

`npm run test:3` runs 80 checks against a live server and a live database:

```
1. Database schema       4 tables, PKs, FKs, 3NF, seeded accounts, hashing
2. Pages and access      anonymous requests are redirected, delete refuses GET
3. CSRF protection       missing and forged tokens both rejected with 419
4. Authentication        wrong password refused, correct one signs in,
                         inactive account refused, attempts logged
5. CRUD                  create, list, search, update, delete, cascade,
                         blank password field does not reset the password
6. Role-based access     standard user reaches the dashboard but not the
                         user manager, and is told why
7. Injection / XSS       quote payload stored verbatim, table intact, output
                         HTML-escaped
8. Upload                valid image accepted and renamed, traversal blocked,
                         oversized rejected, PHP-as-.png rejected
9. Throttling            repeated failures are blocked
Summary                  passed : 80  failed : 0  skipped : 0
```

---

## 5. Security notes

### SQL injection

Every query runs through `PDO::prepare()` with bound parameters
(`shared/Database.php`). No query in this task concatenates user input.

`allowed_mime` is worth calling out: it is a **list**, so membership is checked
with `in_array($mime, $allowed, true)`. An earlier draft used `isset()` on it,
which silently rejected every upload because a list has keys `0,1,2,3`.

The suite proves this rather than asserting it: it creates a user whose name is
`Bobby'); DROP TABLE users;--`, reads it back byte-for-byte, and then confirms
the `users` table still has its rows.

### Passwords

`password_hash($pw, PASSWORD_DEFAULT)` on write, `password_verify()` on read.
Plaintext is never stored or logged. On successful login the hash is re-written
with `password_needs_rehash()`, so raising the cost later upgrades accounts as
they sign in. Every seeded account has a distinct hash, which the suite checks —
identical hashes would mean identical passwords.

### Session handling

`session_regenerate_id(true)` runs on login, registration and logout, which
defeats session fixation. Cookies are `HttpOnly` and `SameSite=Lax`, and the
session path falls back to the system temp directory when `session.save_path`
in `php.ini` is unusable — otherwise a warning is printed before any output and
corrupts JSON responses.

### CSRF

`csrf_token()` issues a per-session token and `csrf_require()` rejects any POST
without a matching one (HTTP 419). The token is rotated on login. The delete
endpoint additionally refuses GET, so a crafted link cannot delete an account.

### Output encoding

`e()` escapes on output with `ENT_QUOTES | ENT_SUBSTITUTE`. Nothing is echoed
raw.

### Login throttling

Five failures for the same identifier **and** source address within 15 minutes
blocks further attempts. Both are required, so one attacker cannot lock every
account out from a single host. A hash comparison runs even when the account
does not exist, so response time does not reveal whether an account is real.

### File upload

Four independent checks: upload error code, non-zero size, a 2 MB ceiling, and
`getimagesize()` on the **file contents** — never the client-supplied
`Content-Type` and never the extension. The suite uploads PHP source named
`evil.png` and confirms it is rejected and that no `.php` file appears in the
upload directory.

Stored names are 32 random hex characters plus a known extension. Files live in
`task-3/storage/uploads`, **outside every document root**, and are only reachable
through `download.php`, which reduces the name with `basename()`, matches it
against a strict pattern, and confirms with `realpath()` that the result is still
inside the upload directory.

### Last-administrator guard

The edit and delete pages both refuse to remove or demote the final active
administrator, and the delete page refuses to delete the account you are signed
in with. Otherwise one careless click locks everyone out.

---

## 6. Verification checklist

- [ ] **Register** — create an account with a weak password; the policy message
      appears. Register the same username again; the duplicate is rejected
      server-side even though the browser check can be bypassed.
- [ ] **Login** — sign in as `demo`, then try to open `/users/index.php`. You are
      redirected and told administrator access is required.
- [ ] **Locked account** — sign in as `locked` with the right password. The
      refusal is specifically about deactivation.
- [ ] **Throttling** — fail a sign-in six times for the same username. The sixth
      attempt is blocked for 15 minutes.
- [ ] **CRUD** — add a user, edit the name and role, then delete. Confirm the
      dialog appears first and that the profile row goes with it.
- [ ] **Password field** — edit a user, change only the name, save. The original
      password still works.
- [ ] **Search** — search for a username; matching accounts appear. Search for
      nonsense; the empty state shows.
- [ ] **XSS** — create a user whose full name is `<script>alert(1)</script>`.
      The list renders it as visible text and the script does not execute.
- [ ] **Upload** — upload a real PNG; the avatar appears and the stored filename
      is random hex. Upload a renamed `.php`; it is refused.
- [ ] **Direct access** — try to browse to `task-3/storage/uploads`. The
      directory is outside the web root and is not served.

---

## 7. Notes and limitations

- **Separate database.** Task 3 uses `apexplanet_users` rather than sharing the
  Task 1 demonstration database, so each task stays independently runnable.
- **Forgot password is not implemented.** It needs an email transport, which
  arrives with Task 5's OTP work. Resetting from the command line is the honest
  alternative today: `php -r "echo password_hash('NewPass1', PASSWORD_DEFAULT);"`
  then update the row.
- **No CSRF token rotation on every request**, only on privilege change. For a
  site of this size that is proportionate.
- **Session storage is the default file handler**, so sessions do not survive a
  move to a multi-node deployment. Moving to a shared handler would be the first
  change for real scale.

---

## 8. Project layout

```
task-3/
├── README.md
├── database/
│   ├── schema.sql          tables, keys, normalisation notes, seed data
│   ├── migrate.php         applies the schema; --fresh drops first
│   └── ER-DIAGRAM.md       relationships and design notes
├── public/                 ← document root
│   ├── init.php            loads the shared library, declares the theme
│   ├── index.php           dashboard
│   ├── login.php           sign in, with throttling and attempt logging
│   ├── logout.php          destroys the session server-side
│   ├── register.php        create an account
│   ├── profile.php         own profile and avatar upload
│   ├── download.php        serves avatars, with path-traversal defence
│   ├── users/
│   │   ├── index.php       list, search, delete trigger
│   │   ├── create.php      Create
│   │   ├── edit.php        Update
│   │   └── delete.php      Delete (POST only)
│   ├── assets/
│   │   ├── css/            style.css (shared base) + task3.css
│   │   ├── js/app.js       nav, confirm dialogs, avatar preview
│   │   └── img/placeholder.svg
│   └── uploads/.gitkeep
├── storage/uploads/        avatar files, gitignored, outside the web root
└── tests/
    └── run-tests.php       80 checks against a live server
```
