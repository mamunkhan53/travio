---
name: OCR Document Scanner module
description: Architecture and gotchas for the OCR Document Scanner module added to the ERP.
---

## Module key
`ocr_scanner` — registered in `$modules` in `includes/config.php`.

## Routing
Dispatched with a dedicated `elseif ($page === 'ocr_scanner')` in `pages/agency_app.php` (before the generic catch-all). Added to the exclusion list so it doesn't trigger the generic $records fetch.

## Files
- `pages/agency/ocr_scanner.php` — main UI (Documents / Upload & Scan / Settings tabs)
- `includes/ocr_actions.php` — POST handlers: `ocr_process_file` (returns JSON), `ocr_save_document`, `ocr_update_document`, `ocr_delete_document`, `ocr_save_settings`
- `includes/actions_agency.php` — `require_once` for ocr_actions.php added at the bottom

## Critical: function_exists guard on ocr_get_api_key()
`ocr_get_api_key($conn, $agency_id)` must be wrapped in `if (!function_exists(...))` in BOTH:
1. `includes/ocr_actions.php` — loaded only on POST
2. `pages/agency/ocr_scanner.php` — loaded on GET

**Why:** actions_agency.php (which includes ocr_actions.php) only runs during POST requests. The page file (loaded on GET) also needs the function, so it re-defines it with the guard to avoid fatal "function already declared" errors on POST.

## CSRF pattern
Use `<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">` — not `csrfInput()` (that function doesn't exist).

## Flash messages
Use `flash()` to store; router.php displays globally. Do NOT call `getFlash()` in page files — it doesn't exist.

## DB
- New table: `ocr_documents` (migrated in db.php)
- Extended: `customers` table — added `passport_number`, `nid_number`, `date_of_birth`, `gender`, `nationality`, `address` via additive ALTER migrations in db.php
- API key stored in `acc_settings` table with `setting_key='ocr_openai_key'` per agency
- Uploads stored in `uploads/ocr_docs/`

## AI OCR
- Uses OpenAI `gpt-4o-mini` vision model
- Env var `OPENAI_API_KEY` takes priority over per-agency DB key
- Only supports image MIME types (JPG/PNG/WEBP/GIF); PDFs are stored but not AI-processed

## Global OCR Import Modal
Added to `pages/agency_app.php` (HTML + JS) — `openOcrImport(callback)` is available on every page. AJAX endpoint: `?ocr_import_ajax=1&q=` (in actions_get.php).
