# South Zone ERP — Replit Setup

## What this is
A multi-tenant Travel Agency SaaS ERP built in PHP 8.2. Agencies can manage CRM leads, passports, visas, air tickets, Umrah packages, tour packages, invoices, expenses, staff, customers, and subscriptions. A Super Admin layer manages all agencies, plans, and platform settings.

## How to run
The workflow **"Start application"** handles everything:
1. Starts a local MariaDB 10.11 server (data lives in `/home/runner/mysql-data`)
2. Loads the full schema from `database_schema.sql` (idempotent — safe to re-run)
3. Starts PHP 8.2's built-in dev server on **port 5000**

Just press **Run** (or restart the "Start application" workflow). The app is served at the Replit preview URL.

## Default login
- **Super Admin:** `admin@southzone.com` / `admin123`
  *(The password is force-reset to `admin123` on every startup by `includes/db.php` — intentional dev convenience.)*

## Database credentials (local Replit only)
Stored as Replit environment variables (shared):

| Variable    | Value              |
|-------------|--------------------|
| `DB_HOST`   | `127.0.0.1`        |
| `DB_NAME`   | `southzone_erp`    |
| `DB_USER`   | `southzone`        |
| `DB_PASS`   | `southzone_local`  |
| `DB_SOCKET` | `/home/runner/mysql.sock` |

`includes/db.php` reads these at runtime and prefers a Unix socket when it exists (Replit), falling back to TCP (Hostinger production).

## Project structure
```
index.php                  Entry point — bootstraps everything
database_schema.sql        Full schema; run once on a fresh DB
start.sh                   Replit startup script (MariaDB + PHP server)
includes/
  db.php                   DB connection + all auto-migrations
  helpers.php              Utility functions (CSRF, currency, TOTP, email)
  config.php               $modules, $statusOptions, $countryCurrencyMap
  actions*.php             All POST/GET action handlers
  router.php               HTML shell + route dispatch
pages/
  landing.php              Public marketing homepage
  auth.php                 Login / Register / 2FA
  superadmin.php           Super Admin dashboard
  agency_app.php           Agency ERP shell
  agency/                  Per-page agency views (dashboard, invoices, etc.)
```

## Deploying to Hostinger (production)
1. Upload the entire folder to `public_html` (or a subfolder).
2. In `includes/db.php` the app now reads `DB_*` env vars. On Hostinger, set those in the hosting panel **or** hardcode them directly in `db.php` (the original approach) — the socket fallback to TCP is automatic.
3. Import `database_schema.sql` once via phpMyAdmin into your production database.

## Architecture rules (per owner preference)
- **Do not rewrite or restructure** the project. All new features must integrate into the existing PHP/MySQL single-entry-point architecture.
- Every new feature must be additive — no breaking changes to existing functionality.
- New pages go in `pages/agency/` (or `pages/` for shared pages).
- New actions go in the appropriate `includes/actions_*.php` file.

## User preferences
- Keep the existing architecture and database intact.
- All new features must integrate into the existing system without breaking current functionality.
