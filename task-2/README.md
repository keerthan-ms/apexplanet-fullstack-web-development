# Task 2 — Interactive UI & Frontend Development

**ApexPlanet Software Pvt. Ltd. · Full Stack Web Development Internship (PHP & MySQL)**

Deliverable: a responsive, animated login and registration interface built with
Bootstrap 5, a custom brand theme and vanilla JavaScript, with an AJAX endpoint
for live availability checks.

---

## 1. What this task covers

| Requirement from the brief | Where it is |
|---|---|
| Bootstrap grid: rows, columns, breakpoints | `public/components.php` §Grid |
| Navbar | `public/partials/navbar.php` |
| Carousel | `public/components.php` §Carousel |
| Cards | `public/components.php` §Cards, `public/index.php` |
| Modals | `public/components.php`, `public/login.php` (forgot password) |
| Buttons | `public/components.php` §Buttons |
| Responsive utilities (`d-none`, `d-md-block`) | `public/partials/navbar.php`, `public/components.php` |
| Custom colour palette for branding | `public/assets/css/theme.css` (`--apex-*` variables) |
| Smooth scrolling and hover effects | `theme.css` (`scroll-behavior`, `:hover` transforms) |
| Google Fonts and Icons | `public/partials/header.php` |
| Validate login and registration forms | `public/assets/js/forms.js` |
| Password match check on registration | `forms.js` → `initPasswordMatch()` |
| Show / hide password toggle | `forms.js` → `initPasswordToggles()` |
| AJAX basics — fetch from PHP without reloading | `forms.js` → `initAjaxChecks()` + `public/api/check-availability.php` |
| Check username / email availability | `public/api/check-availability.php` |
| Reusable Navbar and Footer components | `public/partials/navbar.php`, `public/partials/footer.php` |
| Mobile-first UI | Every breakpoint stacks first, then widens |

---

## 2. Run it

```powershell
npm run setup      # once, installs the portable PHP + MariaDB
npm run task:2     # serves on http://127.0.0.1:8082
```

Or without npm:

```powershell
php -S 127.0.0.1:8082 -t task-2\public
```

Then open <http://127.0.0.1:8082/>.

| URL | Page |
|---|---|
| `/index.php` | Overview |
| `/login.php` | Sign in, plus the forgot-password modal |
| `/register.php` | Registration with live AJAX availability checks |
| `/components.php` | Bootstrap component gallery |
| `/api/check-availability.php?field=username&value=admin` | JSON endpoint |

Run the tests:

```powershell
npm run test:2
```

---

## 3. Expected output

The suite prints grouped results and a summary; it starts its own server on
port 8098 and exercises every route and the AJAX contract:

```
1. Required files        every deliverable present, incl. vendored Bootstrap
2. Bootstrap 5           navbar, carousel, cards, modals, buttons, utilities
3. Reusable components   all four pages include the shared partials
4. JS form behaviour     toggle, match check, strength, AJAX, debounce
5. Server validation     e() escaping, HttpOnly, CSRF, server-side rules
6. AJAX endpoint         JSON contract, 405 guard, injection attempt
7. Accessibility         lang, viewport, skip link, labels, ARIA
Summary                  passed / failed / skipped, then ALL TESTS PASSED
```

---

## 4. Verification checklist

- [ ] **Responsive** — narrow to 320 px; the navbar collapses to a toggler and
      every column stacks into a single column.
- [ ] **Password toggle** — the eye button switches the input type and swaps the
      icon between `bi-eye` and `bi-eye-slash`.
- [ ] **Password match** — type a password, then a different confirmation; the
      message turns red and the field is marked invalid. Make them match again.
- [ ] **Strength meter** — watch the bar fill and change colour; the hint lists
      exactly what is still missing.
- [ ] **AJAX** — on `/register.php`, type `admin` in the username field. The page
      does not reload and the message reports it as reserved or taken.
- [ ] **AJAX failure** — stop MySQL with `npm run db:stop`, then type in the
      username field; the error message degrades gracefully instead of breaking.
- [ ] **Carousel** — `/components.php` autoplays and the arrows work; captions
      stay readable.
- [ ] **Modal** — click any card's *Details* button and the *Open modal* button;
      the title updates and the backdrop traps attention.
- [ ] **Reduced motion** — enable "Reduce motion" in the OS and reload; the
      entrance animation is suppressed.

---

## 5. Notes and limitations

- **Bootstrap is vendored, not loaded from a CDN.** `vendor-assets/bootstrap-5.3.3/`
  holds the compiled CSS and JS so the task runs offline and on shared hosting
  without a third-party request. The Google Font and Bootstrap Icons links do use
  a CDN, as the brief requires; both have system fallbacks so the page still
  reads correctly without them.
- **Task 2 is the front end.** Login and registration stop at a validated form.
  Real accounts, hashed passwords and sessions arrive in Task 3.
- **Availability checks.** The endpoint queries the `users` table when it exists.
  Before Task 3 has been applied it falls back to a small reserved-name list and
  marks the response with `"mode":"demo-reserved-list"` so the difference is
  visible in the network tab rather than hidden.
- **User enumeration.** Real sign-up forms usually avoid telling an anonymous
  visitor that a specific address is already registered. The message is kept
  generic and is called out here deliberately, because demonstrating the check
  is the point of this task.

---

## 6. Project layout

```
task-2/
├── README.md
├── public/                       ← document root
│   ├── index.php                 overview
│   ├── login.php                 sign-in form + forgot-password modal
│   ├── register.php              registration form with AJAX checks
│   ├── components.php            Bootstrap component gallery
│   ├── inc.php                   helpers, session, CSRF
│   ├── api/
│   │   └── check-availability.php   AJAX endpoint (prepared statements)
│   ├── partials/                 reusable components
│   │   ├── header.php
│   │   ├── navbar.php
│   │   └── footer.php
│   └── assets/
│       ├── css/theme.css         custom palette and component styling
│       └── js/forms.js           validation, match, toggle, AJAX
└── tests/
    └── run-tests.php
```
