<?php
session_start();

// =========================================================================
// SOUTH ZONE - TRAVEL AGENCY SAAS ERP
// =========================================================================
// This file only bootstraps the application. All actual logic lives in the
// included files below, organized by feature area:
//
//   includes/db.php                 Database connection + auto-migrations
//   includes/helpers.php            Utility functions (CSRF, currency, TOTP, email, etc.)
//   includes/config.php             Global config: $modules, $statusOptions, $countryCurrencyMap...
//   pages/landing.php               Public marketing homepage
//   pages/auth.php                  Login / Register / 2FA challenge page
//   pages/superadmin.php            Super Admin dashboard (agencies, plans, settings)
//   pages/agency_app.php            Agency ERP shell (sidebar, header) + page dispatch
//   pages/agency/*.php              One file per agency page (dashboard, accounting, invoices...)
//   includes/actions.php            Routes all POST/GET actions to the files below
//   includes/actions_auth.php       Login, 2FA login step, resend verification, register
//   includes/actions_superadmin.php Super Admin: agencies, plans, payments, platform settings
//   includes/actions_2fa.php        2FA setup/disable (Super Admin, Agency Admin, Staff)
//   includes/actions_agency.php     Agency-side: profile, follow-ups, staff, invoices, expenses
//   includes/actions_generic_crud.php  Generic add/edit for enquiries/passports/visas/etc.
//   includes/actions_get.php        GET-based actions: delete, delete_expense, begin_2fa_setup
//   includes/router.php             HTML shell (head/body) + route switch + closing tags
//
// Every file assumes it is included from here (or from another already-included file), so it
// can freely use $conn, $modules, and everything else set up below. None of these files should
// be opened directly in a browser - only index.php is a valid entry point.
// =========================================================================

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/config.php';

// Define every page-rendering function BEFORE the router tries to call one of them
require_once __DIR__ . '/pages/landing.php';
require_once __DIR__ . '/pages/landing_bangla.php';
require_once __DIR__ . '/pages/auth.php';
require_once __DIR__ . '/pages/superadmin.php';
require_once __DIR__ . '/pages/agency_app.php';

// Handle POST/GET actions (login, CRUD, 2FA, etc.) - may redirect and exit before reaching the router
require __DIR__ . '/includes/actions.php';

// Render the HTML shell and dispatch to the matching page function above
require __DIR__ . '/includes/router.php';
