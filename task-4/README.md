# Task 4 — Real-World Full Stack Project

**Project: ApexPlanet Bookstore**
ApexPlanet Software Pvt. Ltd. · Full Stack Web Development Internship (PHP & MySQL)

---

## 1. Project idea

The brief offered *Online Bookstore* or *Food Ordering*. **Online Bookstore** was
chosen because it exercises the same mechanics a food-ordering app does —
catalogue, search, stock, cart, checkout, order lifecycle — without needing
payment integration, kitchen scheduling or delivery zones.

---

## 2. Requirement document

### 2.1 Features

| Area | Feature |
|---|---|
| Catalogue | Browse active products, paginated 9 per page |
| Catalogue | Full-text-ish search across title, author and description |
| Catalogue | Filter by category, sort by title or price, combine all three |
| Cart | Add, change quantity, remove a line, empty the cart |
| Cart | Stock is re-checked on every view, not just on add |
| Checkout | Places an order in a transaction; stock decrements atomically |
| Orders | A customer sees their own orders with line items |
| Orders | Status moves `pending → paid → shipped`, or `cancelled` |
| Admin | Dashboard with six live statistics |
| Admin | Dashboard with two charts: orders per day, orders by status |
| Admin | Full product CRUD with server-side validation |
| Admin | Order management and status workflow |
| Admin | User management: role changes, deletion with guards |
| Account | Register, sign in, sign out, forgotten-password request |
| Account | Password reset tokens with a one-hour expiry |

### 2.2 User roles

| Role | May do |
|---|---|
| **Guest** | Browse the catalogue, search, filter, view product pages, add to the cart |
| **Customer (user)** | Everything a guest can do, plus register, sign in, check out, view own orders, request a password reset |
| **Administrator** | Everything a customer can do, plus the admin dashboard, product CRUD, order workflow and user management |

### 2.3 Use cases

**UC-01 — Browse and find a product**
*Actor:* guest · *Precondition:* none
A visitor opens the catalogue, narrows by category or keyword, sorts by price,
and pages through the results. The page stays usable at 320 px.

**UC-02 — Register**
*Actor:* guest · *Precondition:* the username and email are unused
The visitor supplies a username, email and password. The password is hashed with
`password_hash()` before storage. Duplicate identifiers are rejected by the
server, not only in the browser.

**UC-03 — Sign in**
*Actor:* guest · *Precondition:* an active account
Credentials are verified with `password_verify()`. On success the session id is
regenerated and the visitor lands on the catalogue. Failure messages are
identical whether or not the account exists, so the form cannot be used to
enumerate accounts.

**UC-04 — Add to cart and check out**
*Actor:* customer · *Precondition:* signed in, cart not empty
Checkout re-reads prices and stock from the database, writes the order header and
its line items, and decrements stock — all inside one transaction. If any line
fails, the whole order is rolled back and the cart is left untouched.

**UC-05 — Track my order**
*Actor:* customer · *Precondition:* has placed at least one order
The customer opens *My orders* and sees each order with its lines, total and
current status.

**UC-06 — Manage the catalogue**
*Actor:* administrator
Create, edit and deactivate or delete products. A product that appears in a past
order is deactivated rather than deleted, so order history stays intact.

**UC-07 — Fulfil an order**
*Actor:* administrator
Moves an order through its lifecycle and sees which orders need attention.

**UC-08 — Manage users**
*Actor:* administrator
Changes a role or deletes an account. Guards prevent an administrator from
deleting or demoting themselves, from removing the last administrator, and from
deleting an account that has order history.

### 2.4 Non-functional requirements

- Every query is a prepared statement; no user input reaches SQL as text.
- Every POST is CSRF-protected; destructive endpoints refuse GET.
- Output is escaped with `ENT_QUOTES | ENT_SUBSTITUTE`.
- Money is stored as integer paise, never as a float.
- Stock is decremented with a guarded `UPDATE … WHERE stock >= ?` so concurrent
  checkouts cannot oversell.
- Redirects are resolved against the document root, so pages in `/admin/` link
  correctly.
- The interface works from 320 px upward and honours `prefers-reduced-motion`.

---

## 3. Wireframes

### 3.1 Catalogue (`/`)

```
┌──────────────────────────────────────────────────────────────────────────┐
│ ApexPlanet   Catalogue  My orders  Cart  [user ▾]  Log out              │
├──────────────────────────────────────────────────────────────────────────┤
│ The catalogue                                    ┌───────┬─────┬───────┐ │
│ 11 titles available                             │Search │ ▾Cat │ ▾Sort │ │
│                                                  └───────┴─────┴───────┘ │
│ ┌────────────┐ ┌────────────┐ ┌────────────┐                            │
│ │ [Fiction]  │ │ [Technology]│ │ [Business] │                            │
│ │ The Silent │ │ Building   │ │ Pricing for│                            │
│ │ Meridian   │ │ Secure PHP │ │ Small Teams│                            │
│ │ A. R. Cal..│ │ R. Almeida │ │ T. Brennan │                            │
│ │ ₹1,299.00  │ │ ₹2,450.00  │ │ ₹1,750.00  │                            │
│ │ 14 in stock│ │ 22 in stock│ │ 11 in stock│                            │
│ │ [Details]  │ │ [Details]  │ │ [Details]  │                            │
│ │ [+ Cart]   │ │ [+ Cart]   │ │ [+ Cart]   │                            │
│ └────────────┘ └────────────┘ └────────────┘                            │
│ ┌────────────┐ ┌────────────┐ ┌────────────┐                            │
│ │   … 3 more cards per row …                                      │      │
│ └────────────┘ └────────────┘ └────────────┘                            │
│                                                                          │
│                     ‹ Previous    Page 1 of 2    Next ›                  │
└──────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Cart (`/cart.php`)

```
┌──────────────────────────────────────────────────────────────┐
│ Your cart                                                     │
│ ┌──────────────────────────────────────────────────────────┐ │
│ │ Title            │ Price     │ Qty │ Line total │ Remove │ │
│ ├──────────────────────────────────────────────────────────┤ │
│ │ The Silent       │ ₹1,299.00 │ [ 2]│ ₹2,598.00  │ Remove │ │
│ │ Meridian         │           │     │             │        │ │
│ │ A. R. Calloway   │           │     │             │        │ │
│ ├──────────────────────────────────────────────────────────┤ │
│ │ Order total                       │ ₹2,598.00          │ │
│ └──────────────────────────────────────────────────────────┘ │
│ [Continue shopping]  [Proceed to checkout]        [Empty cart]│
└──────────────────────────────────────────────────────────────┘
```

### 3.3 Admin dashboard (`/admin/index.php`)

```
┌──────────────────────────────────────────────────────────────────────────┐
│ ApexPlanet  Dashboard  Catalogue  Manage products  Manage orders  …      │
├──────────────────────────────────────────────────────────────────────────┤
│ Admin dashboard                                                           │
│ ┌─────────┐┌─────────┐┌─────────┐┌─────────┐┌─────────┐┌─────────┐        │
│ │Active   ││Out of   ││ Orders  ││Awaiting ││Customers││ Revenue │        │
│ │products ││stock    ││         ││action   ││         ││         │        │
│ │  11     ││  2      ││   3     ││  1      ││   2     ││ ₹3,759  │        │
│ └─────────┘└─────────┘└─────────┘└─────────┘└─────────┘└─────────┘        │
│ ┌────────────────────────────────┐ ┌──────────────────────────────────┐  │
│ │ Orders per day (last 14 days) │ │ Orders by status                │  │
│ │      ╱╲    ╱╲                  │ │        ◕  shipped  1            │  │
│ │  ╱╲╱    ╲╱   ╲╱╲__            │ │       ◕   pending  2            │  │
│ │ ───────────────────────────    │ │                                  │  │
│ │  (line chart, Chart.js)        │ │     (doughnut, Chart.js)        │  │
│ └────────────────────────────────┘ └──────────────────────────────────┘  │
│ Most active customers            Recent orders                           │
│ ┌──────────────────────────┐     ┌──────────────────────────────────┐  │
│ │ demo   2 orders  ₹3,759  │     │ #3 demo ₹2,749  pending Manage  │  │
│ └──────────────────────────┘     └──────────────────────────────────┘  │
│ Low stock: Algorithms Cheat Sheet — 40 left                             │
└──────────────────────────────────────────────────────────────────────────┘
```

### 3.4 Mobile

```
320 px                          768 px
┌──────────────┐               ┌────────────────────────┐
│ A  ☰ Menu    │               │ A │ Nav │ Orders │ Cart│
├──────────────┤               ├────────────────────────┤
│ Title        │               │ ┌──────┐ ┌──────┐     │
│ 11 titles    │               │ │ card │ │ card │     │
│ ┌──────────┐ │               │ └──────┘ └──────┘     │
│ │ product  │ │               │ ┌──────┐ ┌──────┐     │
│ │ card     │ │               │ │ card │ │ card │     │
│ └──────────┘ │               │ └──────┘ └──────┘     │
│ ┌──────────┐ │               │ Search ▾ ▾  [Apply]    │
│ │ product  │ │               └────────────────────────┘
│ │ card     │ │
│ └──────────┘ │        One column, stacked; the navigation
│      ⋮       │        collapses behind the toggler.
└──────────────┘
```

---

## 4. Run it

```powershell
npm run setup                     # once
npm run db:start                  # if MariaDB is not running
php task-4\database\migrate.php   # schema + seed (add --fresh to reset)
npm run task:4                    # http://127.0.0.1:8084
```

| URL | Page |
|---|---|
| `/index.php` | Catalogue with search, filter, sort, pagination |
| `/product.php?id=N` | Product detail |
| `/cart.php` | Cart |
| `/checkout.php` | Checkout |
| `/orders.php` | My orders |
| `/login.php` · `/register.php` · `/forgot.php` | Authentication |
| `/admin/index.php` | Dashboard with Chart.js analytics |
| `/admin/products.php` | Product CRUD |
| `/admin/orders.php` | Order workflow |
| `/admin/users.php` | User management |

Run the tests:

```powershell
npm run test:4
```

### Demo accounts

| Username | Password | Role |
|---|---|---|
| `admin` | `Admin@12345` | administrator |
| `demo` | `Demo@12345` | customer |

---

## 5. Expected output

69 checks against a live server and database:

```
1. Schema            5 tables, 4 foreign keys, indexes, integer money, FK rejects orphans
2. Catalogue         search, empty state, filters, sort, inactive hidden, 404 handling
                     quote-heavy search is harmless, LIKE wildcard is escaped
3. Authentication    wrong password refused, both roles sign in, customer denied /admin
4. Cart and checkout order created, stock decremented, lines written, cart emptied,
                     out-of-stock blocked, forged CSRF rejected
5. Admin panel       dashboard + charts, product CRUD, validation, deactivation instead of
                     deletion for ordered products, order workflow, self-delete and
                     self-demote guards
Summary              passed : 69  failed : 0  skipped : 0
```

---

## 6. Design notes

### Money

`price_cents` is an `INT UNSIGNED`. `19.99` cannot be represented exactly in a
binary float, so summing floats produces `59.97000000000001`. Storing whole
paise and formatting only for display avoids the problem entirely.

### Checkout is transactional

The order header, its line items and the stock decrements all happen inside one
`BEGIN` / `COMMIT`. Stock uses a guarded update —
`UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?` — and the
code treats a zero row-count as a failure, so two people checking out the last
copy cannot both succeed. The suite verifies the decrement actually lands.

### Deliberate denormalisation

`order_items` copies both `title` and `unit_price_cents` from the product at the
moment of ordering. This is intentional: a later price rise or a retitled book
must not silently rewrite what somebody already paid. The same reasoning
produces the "deactivate instead of delete" rule for products.

### Redirects

`task_url()` builds every internal link and redirect against the document root.
A relative `login.php` from `/admin/index.php` would resolve to
`/admin/login.php` and 404 — a bug the suite now guards by checking the
`Location` header rather than just the status code.

### Charts

Aggregation happens in SQL and is handed to Chart.js as JSON, so the browser
never builds a query. The 14-day series is generated server-side so days with no
orders show as zero rather than collapsing the axis. Each chart has a
`visually-hidden` table carrying the same numbers, so the data is available to
screen readers and to anyone the canvas does not render for.

---

## 7. Limitations

- **Payment is simulated.** Placing an order marks it `pending`; there is no
  gateway.
- **Password reset writes to the log** instead of sending mail, since no
  transport is configured. The reset token is a 32-byte random value, stored
  hashed, expiring after an hour.
- **Product images are not implemented.** Only text is stored, which keeps the
  catalogue honest about what it actually does.
- **Orders are limited to 100 rows** in the admin list with no pagination
  beyond that.
