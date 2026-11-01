# M-A-E-Commerce prototype

A server-rendered PHP/MySQL portfolio application with customer and administrator accounts, a searchable catalog, invoice orders, and order history. The original logo, Pokémon imagery, gold/charcoal colors, Montserrat/Roboto Slab typography, and playful voice are retained. Landing highlights now reflect available products and current catalog prices. Administrators record payment status manually; the prototype does not process real payments.

## Docker Compose setup

1. Copy `.env.example` to `.env` and replace both database password placeholders.
2. Run `docker compose up --build -d`.
3. For a **new, empty database**, run `docker compose exec web php scripts/setup.php --seed`. Omit `--seed` to start without sample products.
4. Export `ADMIN_EMAIL` and `ADMIN_PASSWORD` in your terminal. Use a password of 8–72 bytes. Run `docker compose exec -e ADMIN_EMAIL -e ADMIN_PASSWORD web php scripts/create-admin.php`, then unset the password variable. No default administrator or shared login is installed.
5. Open `http://localhost:8080`. The main page has customer and administrator login forms. Register using fictional details to try shopping.

The `expiry` service cancels expired invoices every minute. Database data and uploaded images use named volumes. After source changes, rebuild with `docker compose up --build -d`.

## Native PHP setup

Requirements: PHP 8.2+ with `mysqli`/mysqlnd, `mbstring`, and `fileinfo`; MySQL 8.0+ or MariaDB 10.11+; a database and database account. Python 3 is needed for the regression suite.

Create an empty `NealleDB` database using `utf8mb4`, and grant your application account access to it. Copy `config.example.php` to `config.local.php` and set connection details. Environment variables override local configuration:

| Setting | Purpose |
| --- | --- |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Database connection; original localhost/root defaults remain for compatibility |
| `DB_SOCKET` | Optional MySQL socket |
| `APP_BASE_PATH` | Empty at the domain root; `/mae` when installed under that subdirectory |
| `COOKIE_SECURE` | Set to `1` on HTTPS, including behind a trusted TLS proxy |

Run `php scripts/setup.php --seed`. Create an administrator as above using `php scripts/create-admin.php`. Start the development server with:

```sh
php -S 127.0.0.1:8080 -t . scripts/dev-router.php
```

`npm run dev` is an optional preview command; no Node packages or frontend build are required. `PORT` chooses its port.

For Apache, enable `mod_rewrite` and `AllowOverride All` for this directory so `.htaccess` protects internal code and configuration. For other web servers, deny dotfiles, `config.*`, `app/`, `database/`, `scripts/`, and `tests/`, and disable PHP execution under `assets/uploads/`. Give the PHP account write access to `assets/uploads/`. The local development router implements corresponding path restrictions.

For native deployments, schedule `php /absolute/path/to/e-com-website/scripts/expire-orders.php` every minute. Shop/order pages also apply expiry before displaying an invoice. Order times are stored and displayed in UTC.

## Existing database upgrade

Back up the database and test the upgrade on a copy first. Use a maintenance window: MySQL table changes are not transactional.

1. Point this checkout's configuration at the database being upgraded.
2. Run `php scripts/migrate.php`, or `docker compose exec web php scripts/migrate.php` for an imported legacy database.
3. Verify existing accounts and orders in the upgraded copy before applying the same process to the live installation.

The migration preserves IDs and records, widens password columns, hashes existing plaintext passwords, normalizes email addresses, adds unique account/order-line indexes, converts tables to InnoDB/utf8mb4, and adds product/customer availability and invoice expiry. Version 1 repair databases upgrade directly to version 2 without repeating the legacy data transforms. Already-hashed passwords are not hashed again. Original passwords continue to work after migration; newly set passwords must meet current limits. The application refuses to use a legacy schema until migration completes.

Duplicate normalized emails or duplicate `(OrderID, pcode)` lines stop migration before table changes; resolve them explicitly in the backup copy instead of silently choosing an account or merging historical prices. An interrupted migration can be rerun. Column additions use explicit table-copy rebuilds for compatibility with older MariaDB instant-column behavior, so allow time and disk space for the maintenance operation. Previously orphaned detail rows are counted and retained for manual review. Fresh databases include foreign keys; upgraded databases retain their existing key types/relationships, with application transactions performing cleanup. The old unused `Cart` table, if present, is retained and unused.

Cancelled orders retain their linked items and appear in history. Product deletion archives a product, preserving order names/prices; Edit can restore availability. Customers with order history are deactivated, preserving every order and item. Deactivated customers cannot log in or be selected in Shop As; Edit can reactivate them. Permanent customer deletion requires confirmation and is restricted to accounts with no orders. Administrator deletion cannot remove the current account or the last administrator.

## Architecture

Existing PHP URLs remain the entry points. Each handles requests before rendering and delegates to shared helpers:

| File | Responsibility |
| --- | --- |
| `app/bootstrap.php` | Configuration, sessions, role checks, CSRF, validation, prepared SQL, error handling |
| `app/orders.php` | Per-customer invoices, transactional additions, expiry, payment status, cancellation |
| `app/accounts.php` | Customer/admin validation, passwords, profile edits, account forms |
| `app/catalog.php` | Customer/admin shopping, search/category context, add-item handlers |
| `app/products.php` | Product validation and forms |
| `app/views.php` | Shared layout, navigation, escaped output, forms |
| `database/schema.sql`, `scripts/` | Setup, migration, admin creation, expiry |

Customer and admin logins establish separate session identities. Admin “Shop As” selects a customer without changing administrator identity. Active invoices are recovered from the session/database when URL context is lost. Adding items locks the customer row, reads catalog prices on the server, uses the connection's inserted ID, and commits the whole change together. Paid and cancelled orders cannot be edited. The existing invoice-as-cart behavior is preserved: the first item starts a 24-hour invoice window, shown in the catalog and order pages. There is no separate checkout or payment gateway. Reopening a paid invoice starts a new 24-hour payment window without changing the original order date, and is refused while that customer has another open invoice.

Order transactions retry a bounded number of deadlocks or lock timeouts. File-upload transactions do not replay filesystem operations.

Mutations require POST and CSRF tokens and enforce role/ownership independently of navigation. Password fields never contain stored passwords; blank edits keep the existing hash. Validation errors retain non-password fields and leave passwords blank.

Image uploads validate content, dimensions, and size, use randomized filenames with trusted extensions, and update the product reference.

## Regression suite

Use a test-server account with permission to create/drop databases. The suite creates uniquely named `mae_test_*` databases and drops them afterward. It never reuses the configured application database.

```sh
DB_HOST=127.0.0.1 DB_USER=test_runner DB_PASSWORD='your-test-password' python3 tests/integration.py
```

Optional settings: `DB_PORT`, `DB_SOCKET`, `PHP_BIN`. Tests start temporary PHP HTTP workers and verify requests plus database state:

- Account creation/login/logout, roles, hashed passwords, blank-password edits, duplicate emails, and SQL/HTML punctuation.
- Direct endpoint access, CSRF, order ownership, setup protection, non-mutating GETs, navigation, and unique HTML IDs.
- Authoritative prices, quantity validation, cart continuity, concurrent sessions, immutable paid orders, reopening, cancellation history, and expiry.
- Product/admin/customer management, safe uploads, customer deactivation/history retention, and restricted deletion.
- Subdirectory installation and upgrades from the original schema, including repeated migration and duplicate-identity preflight.

GitHub Actions runs the same suite with MariaDB on pushes and pull requests.

## Interface checks

All pages use compatible, pinned Bootstrap/Bootswatch 5.3.3 assets. The catalog uses a responsive grid, complete product images, visible filter context, and an open-invoice summary. Product previews, forms, order tables, and management pages share the MAE visual treatment.

For manual verification, check the landing page, catalog, profile, active orders/history, and admin management pages at 320px, 390px, 768px, and desktop widths. Confirm that mobile navigation exposes search, descriptions and preview dialogs work with a keyboard, and wide management tables scroll within their own container. Use the HTTP/database suite for actual form submissions and permissions; rendered page snapshots validate layout only.

## Display currency

The display-currency selector supports **IDR, USD, EUR, GBP, CAD, and JPY**. It appears above shared app pages and beside the landing-page highlights. The selected currency is remembered in the browser and synchronized between tabs.

Reference rates come from [Frankfurter v2](https://frankfurter.dev/). The feed publishes daily observations; this is an instantly updating price display using the latest published reference rates, not a tick-by-tick trading feed. Each conversion is marked as approximate and shows the relevant publication date. JPY and IDR use whole units; USD, EUR, GBP, and CAD use two decimals.

Product prices, price-entry fields, historical order prices, and server-side totals remain **whole IDR**. Changing the display currency never modifies an order or submits a converted price. Every conversion starts from its original IDR value, including when switching currencies repeatedly. Without JavaScript or usable rates, prices remain in rupiah.

`api/exchange-rates.php` retrieves a fixed set of IDR-based rates server-side, without API keys or customer data. It caches validated responses for one hour and the browser rechecks every 15 minutes while the page is visible. During a provider outage, recent saved rates are explicitly marked; quotes older than seven days are refused. A cold outage leaves the original IDR prices visible. Invalid rates are never applied.

PHP must have HTTPS stream support (OpenSSL and `allow_url_fopen`) and outbound HTTPS access to `api.frankfurter.dev`. The Docker image already includes HTTPS support. The cache uses PHP's temporary directory by default; an optional `FX_CACHE_DIR` process environment variable can select an existing, writable private directory. No database migration is needed for currency conversion. After pulling updates in Docker, rebuild with `docker compose up --build -d`.

Currency-specific checks run without an external API dependency:

```sh
php tests/currency.php
node --test tests/currency.test.cjs
```

Node is needed only for the JavaScript tests, not to serve the PHP application. The HTTP suite also checks the rates endpoint, outage behavior, exact IDR price metadata, and subdirectory URLs.
