# Product Requirements Document
## Smart Grocery ERP + POS + Online Store — Single-File PHP Architecture

**Version:** 1.0
**Stack:** PHP + MySQL | XAMPP (local) → cPanel (production)

---

## Single-Paragraph PRD Description

The Smart Grocery ERP + POS + Online Store is a full-stack, single-file-per-page PHP and MySQL web application that unifies a physical grocery shop's operations — including point-of-sale billing, advanced dual-location inventory (display shelf vs. warehouse), supplier and purchase management, expiry tracking, credit customer ledger (Baki system), barcode-driven product management, role-based access control (Owner and Staff), thermal receipt printing, and a public-facing online grocery storefront — into one cohesive system built on a flat, framework-free PHP architecture where every page or route is a single `.php` file that contains all of its own backend logic (database queries, form processing, authentication checks, redirects) and its own frontend rendering (HTML output, inline dynamic data), so that the codebase remains completely transparent, portable, and maintainable without build tools, MVC frameworks, or CLI setup; the entire system shares one central configuration file (`config.php`) at the project root that stores the database host, database name, username, password, shop name, base URL, timezone, receipt header content, WhatsApp number, and any other global constants, which every page file loads via `require_once` as its very first line, meaning the sole action required to move the project from a local XAMPP environment (`htdocs/grocery-erp/`) to a live cPanel hosting account is to upload the project directory to `public_html/`, open `config.php` in the cPanel File Manager, update the four database credential values and the base URL to match the hosting environment, and save — after which the entire system, all modules, all pages, and all APIs are immediately live with zero code changes anywhere else; all HTML structures, navigation shells, dashboard layouts, form wrappers, alert components, table headers, and shared UI sections that appear on more than one page are extracted into named partial files stored under an `/includes/` directory (`header.php`, `footer.php`, `sidebar.php`, `db.php`, `auth.php`, `flash.php`, `pos-header.php`, `store-header.php`) and pulled into page files at the exact point they are needed using `require_once`, ensuring that every reusable pattern — from the admin navigation bar to the POS receipt template to the online store category grid — is defined exactly once and never duplicated; global CSS (Tailwind CDN or a compiled `main.css`), global JavaScript (Alpine.js CDN and `main.js`), and all static assets live under `/assets/css/`, `/assets/js/`, and `/assets/img/` respectively and are linked once inside `header.php`; page-specific styles or scripts that only a single route requires are inlined directly inside that page's file below the shared header include; the directory hierarchy mirrors the system's module structure, with top-level admin pages (`dashboard.php`, `products.php`, `inventory.php`, `pos.php`, `suppliers.php`, `customers.php`, `purchases.php`, `expiry.php`, `reports.php`, `settings.php`) sitting at root level, online store pages grouped under `/store/` (`index.php`, `category.php`, `product.php`, `cart.php`, `checkout.php`), REST API endpoints for the future Android application grouped under `/api/` (`products.php`, `orders.php`, `customers.php`, `inventory.php`, `sales.php`) and returning JSON responses, and all include partials isolated in `/includes/` so no partial is ever web-accessible directly; every page file follows a strict top-to-bottom execution order — (1) `require_once 'config.php'`, (2) `require_once 'includes/db.php'`, (3) `require_once 'includes/auth.php'` (enforces session and role checks, redirects unauthorized users before any output), (4) all backend logic for that page including POST handling, prepared-statement database queries using PDO with bound parameters, session flash message writes, and header-based redirects, (5) `require_once 'includes/header.php'` to open the HTML document, (6) the page's own HTML and echoed PHP content, and (7) `require_once 'includes/footer.php'` to close the document — guaranteeing that all redirects and `header()` calls execute before any output is sent, preventing headers-already-sent errors across all environments; the database layer uses PDO with prepared statements throughout to prevent SQL injection, all output is passed through `htmlspecialchars()` before rendering to prevent XSS, passwords are hashed with `password_hash()` / `password_verify()`, sessions are regenerated on login with `session_regenerate_id(true)`, and the `config.php` file is protected by an `.htaccess` `deny from all` rule to block direct HTTP access; the system's sixteen core database tables (`users`, `products`, `categories`, `companies`, `dealers`, `inventory`, `product_batches`, `customers`, `sales`, `sale_items`, `customer_ledger`, `customer_payments`, `stock_transfers`, `purchase_orders`, `price_history`, `stock_movements`) are each created by a standalone SQL migration script in `/database/migrations/` and seeded with sample data from `/database/seeders/`, both of which are run once via a simple `setup.php` installer page that is deleted after first use; this flat, zero-dependency architecture ensures that any developer who understands basic PHP and MySQL can read, modify, and maintain any part of the system by opening a single file, and that any shop owner with cPanel access can deploy or redeploy the entire ERP without a developer present.

---

## File & Directory Structure

```
/project-root/
│
├── config.php                        ← DB credentials, shop settings, global content (edit on deploy)
│
├── dashboard.php                     ← Admin home: sales summary, alerts, expiry warnings
├── products.php                      ← Product CRUD, barcode, price, batch, expiry fields
├── categories.php                    ← Category management
├── companies.php                     ← Brand/company management
├── suppliers.php                     ← Dealer profiles + WhatsApp/call buttons
├── inventory.php                     ← Display stock vs. warehouse stock view + transfer
├── purchases.php                     ← Supplier purchase entry, order generation
├── expiry.php                        ← Expiry tracker: expired / 7d / 15d / 30d views
├── customers.php                     ← Credit customer management (Baki)
├── customer-ledger.php               ← Individual customer ledger: Debit / Credit / Balance
├── customer-payment.php              ← Record customer payment, auto-update balance
├── pos.php                           ← POS interface: barcode scan, cart, payment, receipt
├── reports.php                       ← Sales, inventory, credit reports
├── price-history.php                 ← Log of all price changes
├── stock-movements.php               ← Full movement history: sales, returns, transfers
├── settings.php                      ← Shop settings, user management
├── login.php                         ← Login form + POST handler
├── logout.php                        ← Session destroy + redirect
├── setup.php                         ← One-time DB installer (delete after use)
│
├── store/
│   ├── index.php                     ← Online store homepage
│   ├── category.php                  ← Products by category
│   ├── product.php                   ← Product detail page
│   ├── cart.php                      ← Shopping cart
│   └── checkout.php                  ← Checkout + order placement (reduces inventory)
│
├── api/
│   ├── products.php                  ← REST: GET products, search, barcode lookup
│   ├── orders.php                    ← REST: POST order, GET order history
│   ├── customers.php                 ← REST: GET/POST customers, ledger
│   ├── inventory.php                 ← REST: GET stock levels
│   └── sales.php                     ← REST: GET sales summary
│
├── includes/
│   ├── config-loader.php             ← Safe config validator (called by setup.php)
│   ├── db.php                        ← PDO connection (reads config.php)
│   ├── auth.php                      ← Session check + role guard (Owner / Staff)
│   ├── header.php                    ← HTML <head>, Tailwind CDN, Alpine.js, admin nav
│   ├── footer.php                    ← Closing scripts, footer HTML
│   ├── sidebar.php                   ← Admin sidebar navigation
│   ├── flash.php                     ← One-time success/error message renderer
│   ├── pos-header.php                ← Minimal header for POS interface
│   ├── store-header.php              ← Online store navigation header
│   └── store-footer.php              ← Online store footer
│
├── assets/
│   ├── css/
│   │   ├── main.css                  ← Global styles (compiled Tailwind or utility classes)
│   │   └── pos.css                   ← POS-specific layout overrides
│   ├── js/
│   │   ├── main.js                   ← Global JS (flash auto-dismiss, nav toggles)
│   │   ├── pos.js                    ← Barcode scanner listener, cart logic, receipt print
│   │   └── store.js                  ← Cart quantity controls, search filter
│   └── img/
│       └── logo.png
│
├── uploads/
│   └── products/                     ← Product images (writable)
│
└── database/
    ├── migrations/
    │   └── create_tables.sql         ← All 16 table CREATE statements
    └── seeders/
        └── seed_data.sql             ← Sample categories, products, one owner user
```

---

## Page File Anatomy (Applied to Every `.php` Page)

```
1. require_once 'config.php'
2. require_once 'includes/db.php'
3. require_once 'includes/auth.php'          ← Redirects if not logged in / wrong role
4. [Backend logic block]
   - Handle $_POST: validate, query DB via PDO prepared statements
   - Set $_SESSION['flash'] messages
   - header('Location: ...') redirect on success
5. require_once 'includes/header.php'        ← Opens HTML document
6. require_once 'includes/sidebar.php'
7. [Page-specific HTML + echoed PHP data]
8. require_once 'includes/footer.php'        ← Closes HTML document
```

---

## XAMPP → cPanel Deployment Workflow

| Step | XAMPP (Local) | cPanel (Production) |
|------|--------------|---------------------|
| Project location | `htdocs/grocery-erp/` | `public_html/grocery-erp/` |
| `config.php` DB host | `localhost` | `localhost` (cPanel standard) |
| `config.php` DB name | `grocery_dev` | `cpanelusername_grocerydb` |
| `config.php` base URL | `http://localhost/grocery-erp/` | `https://yourdomain.com/` |
| Code changes needed | None | **None** |
| Files changed on deploy | — | `config.php` only (4 values) |

---

## Constraints & Non-Goals

- No Laravel, CodeIgniter, or any PHP framework — pure PHP only
- No Composer, npm, Webpack, or any build pipeline
- No `.env` files — all configuration lives in `config.php`
- No front-end framework — Alpine.js via CDN only; no React or Vue build step
- No CLI commands required at any stage of development or deployment
- No separate API server — REST endpoints are plain PHP files in `/api/` returning JSON
