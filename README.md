# BEST Books — Books-Extraordinary-Supply-Town

A database-driven e-commerce web application for **Books-Extraordinary-Supply-Town (BEST)**, a book retailer with several physical stores. It has two parts that share one database:

- **Main website (customers)** — browse and search books, see live stock at every store, add to cart, check out for delivery or click & collect, and follow orders.
- **Admin website (staff portal)** — manage books, categories, stock per store, stores, orders, customers, messages and staff accounts, with role-based access.

ITECA3-34 Project · Deliverable 2 · Prepared by Tshepo Mokoena

## Built with

| Layer | Technology |
| --- | --- |
| Structure | HTML5 (PHP templates, no CMS, no framework) |
| Presentation | CSS3 + Bootstrap 5.3 + Bootstrap Icons, responsive for phones, tablets and desktops |
| Interactivity | JavaScript (vanilla) and jQuery 3.7 |
| Server | PHP 8.2 on Apache (Docker) |
| Database | MySQL-compatible **TiDB Cloud**, connected over TLS with `mysqli` prepared statements |
| Hosting | **Render** (Docker web service) |

## What it does

**Main website**

| Page | Purpose |
| --- | --- |
| `index.php` | Home: search, categories, staff picks, new arrivals, stores |
| `catalogue.php` | Product catalogue with keyword search, category, price, store and in-stock filters, sorting and pages |
| `book.php?id=` | Product details with **stock at each store** and related books |
| `cart.php` | Shopping cart (stored in the session, prices always re-read from the database) |
| `checkout.php` | Delivery or click & collect, address, payment method. Stock is taken from the right store in one transaction |
| `order-confirmation.php` / `order.php` | Confirmation, EFT details, order tracker, cancel while pending |
| `register.php` / `login.php` / `account.php` | Customer accounts, saved address, password change, POPIA privacy choices and deletion request |
| `stores.php`, `about.php`, `contact.php`, `privacy.php` | Store details, about, contact form, POPIA privacy notice |

**Admin website** (`staffLogin.php` → `staff/`)

| Page | Admin | Staff |
| --- | :---: | :---: |
| Dashboard (sales, low stock, best sellers, charts) | ✓ | ✓ |
| Orders and order details (status workflow, payment, notes, cancel + restock, print pick list) | ✓ | ✓ |
| Books (add, edit, hide, staff picks) and Categories | ✓ | ✓ |
| Inventory grid (stock per book per store, restock/adjust with an audit trail) | all stores | own store only |
| Customers | ✓ (can suspend) | view |
| Messages (contact form and POPIA requests) | ✓ | ✓ |
| Stores (add, edit, close/reopen) | ✓ | — |
| Staff & Roles (create accounts, temporary passwords, roles, suspend) | ✓ | — |
| Activity Log | ✓ | — |

## Security and POPIA

- Passwords hashed with `password_hash()`; staff accounts start with a temporary password that must be changed.
- Every query uses prepared statements (SQL injection), every form has a CSRF token, all output is escaped (XSS).
- Sessions use `HttpOnly`, `SameSite=Lax` and `Secure` cookies and are regenerated on login.
- Role checks run on every staff page (`requireRole()`), not only in the menu.
- Security headers and a Content Security Policy are sent on every page (`includes/security.php`).
- Database traffic to TiDB Cloud is encrypted (TLS).
- Customers accept the privacy notice when registering, marketing is a separate opt-in, and they can request deletion.
- Staff actions are recorded in the Activity Log.

## Project structure

```
/                    customer pages (index.php, catalogue.php, book.php, cart.php, checkout.php ...)
assets/              css, js and images for the customer site
config/              .env loader and database connection
includes/            shared components (navBar, footer, bookCard) and helpers (books, cart, orders ...)
staff/               admin website: pages, components, helpers, assets
database/            dbSetup.sql — tables, keys and seed data
docs/                documentation for the report (diagrams to be added)
Dockerfile           image Render runs
render.yaml          Render blueprint
```

Like the Likhwezi Tech project it is modelled on: **pages own content, helpers own data, components own reusable structure.**

## Database

Tables: `UserAccount` (supertype) → `Customer`, `Staff`, `Admin` (subtypes) · `Store` · `Category` · `Book` · `Inventory` (Book ↔ Store, one row per book per store) · `CustomerOrder` · `OrderItem` (records which store each copy came from) · `OrderStatusHistory` · `StockMovement` · `ContactMessage` · `ActivityLog`.

TiDB does not support triggers or stored procedures, so order placement, cancellation and stock changes run as PHP transactions with `SELECT … FOR UPDATE` row locks.

## 1. Set up the TiDB Cloud database

1. Sign in at <https://tidbcloud.com> and create a free **Starter (Serverless)** cluster. Pick a region close to your Render region (e.g. AWS Frankfurt `eu-central-1` if Render is in Frankfurt).
2. Open the cluster → **Connect**. Choose *General* / *MySQL CLI*, click **Generate password**, and copy:
   - Host — like `gateway01.eu-central-1.prod.aws.tidbcloud.com`
   - Port — `4000`
   - User — like `2abcDEF.root`
   - Password
3. Open **SQL Editor** in the TiDB console, paste the whole of `database/dbSetup.sql` and run it. (Or with the MySQL client: `mysql --comments -u '<user>' -h <host> -P 4000 -p --ssl-mode=VERIFY_IDENTITY < database/dbSetup.sql`.)
4. Check: `SELECT COUNT(*) FROM best_books.Book;` should return 24.

## 2. Deploy to Render

1. Push this folder to a GitHub repository (`config/.env` is ignored by git).
2. In Render: **New → Web Service** → connect the repository → Render detects the `Dockerfile` (Runtime: Docker). Or use **New → Blueprint** to read `render.yaml`.
3. Add these **Environment Variables**:

   | Key | Value |
   | --- | --- |
   | `DB_HOST` | your TiDB host |
   | `DB_PORT` | `4000` |
   | `DB_USER` | your TiDB user (with the prefix, e.g. `2abcDEF.root`) |
   | `DB_PASSWORD` | your TiDB password |
   | `DB_NAME` | `best_books` |
   | `DB_SSL` | `true` |

4. Deploy. The site is live at `https://<service-name>.onrender.com/`, the admin website at `/staffLogin.php`.

> Render's free plan sleeps after 15 minutes without visits; the first visit afterwards takes about a minute. Open the site a few minutes before a demo.

## Running locally (XAMPP)

1. Copy the folder into `htdocs` and start Apache and MySQL.
2. Import `database/dbSetup.sql` in phpMyAdmin.
3. Create `config/.env`:
   ```
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_USER=root
   DB_PASSWORD=
   DB_NAME=best_books
   DB_SSL=false
   ```
4. Open `http://localhost/<folder>/`. To use TiDB from Windows instead, set `DB_SSL=true` and download the ISRG Root X1 certificate to `config/isrgrootx1.pem`.

## Demo accounts

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@best.co.za | `BestAdmin#2026` |
| Staff (BEST Midrand) | staff@best.co.za | `BestStaff#2026` |
| Customer | reader@example.com | `BestReader#2026` |

Change these passwords after the lecturer has marked the project. Book covers load from the Open Library covers service using each book's ISBN; if a cover is missing a styled placeholder is drawn.

## Code samples for the report (section 2.4)

| Report section | Suggested file |
| --- | --- |
| Sample PHP | `includes/helpers/orders.php` — `placeOrder()` and `allocateStock()` (transaction, row locks, multi-store stock) |
| Sample HTML | `book.php` (product page with the availability-by-store list) or `checkout.php` |
| Sample JavaScript | `assets/js/main.js` (AJAX add to cart) and `assets/js/checkout.js` (jQuery) |
| Sample CSS | `assets/css/style.css` (book cards, responsive breakpoints) and `staff/assets/css/dashboard.css` |
| MySQL tables | `database/dbSetup.sql`; screenshot the tables in the TiDB SQL Editor |
