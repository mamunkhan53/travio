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
- `includes/ocr_actions.php` — POST handlers + helper functions (with function_exists guards)
- `includes/ocr_providers/OcrProvider.php` — abstract base class for OCR providers
- `includes/ocr_providers/OcrSpaceProvider.php` — OCR.Space REST API implementation
- `includes/actions_agency.php` — `require_once 'ocr_actions.php'` added at the bottom

## Provider architecture (future-proof)
- All OCR calls go through `ocr_get_provider($conn, $agency_id, $apiKey)` factory in `ocr_actions.php`
- Factory reads `ocr_provider` from `acc_settings`; defaults to `ocr_space`
- New providers: create class extending `OcrProvider`, place in `includes/ocr_providers/`, add a `case` in the factory and entry in `ocr_available_providers()`
- `OcrSpaceProvider` sends file via cURL multipart to `api.ocr.space/parse/image`, uses Engine 2 (better for printed docs)

## Critical: function_exists guards
`ocr_get_api_key()`, `ocr_get_provider_name()`, `ocr_get_provider()`, `ocr_available_providers()` all have `if (!function_exists(...))` guards in `ocr_actions.php`.

**Why:** actions_agency.php (which includes ocr_actions.php) only runs during POST. The page file calls these same helpers on GET — it relies on them being loaded from ocr_actions.php via require_once called at the top of the page's own PHP block (or they're inlined in the page). Currently `ocr_scanner.php` calls them directly; they must remain guarded.

## Settings storage
- `ocr_provider` → provider key string (default `ocr_space`)
- `ocr_api_key` → API key string
- Both stored in `acc_settings` table, scoped per `agency_id`
- Saved by `ocr_save_settings` action (agency owner / non-staff only)

## CSRF pattern
Use `<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">` — not `csrfInput()` (that function doesn't exist).

## Flash messages
Use `flash()` to store; router.php displays globally. Do NOT call `getFlash()` in page files — it doesn't exist.

## DB
- Table: `ocr_documents` (migrated in db.php); includes `ocr_raw_text MEDIUMTEXT` column
- Extended: `customers` table — `passport_number`, `nid_number`, `date_of_birth`, `gender`, `nationality`, `address` via additive ALTER migrations in db.php
- Uploads stored in `uploads/ocr_docs/`

## OCR engine (Hostinger-compatible)
- **No binary dependencies** — OCR.Space REST API via cURL only
- Supports JPG, PNG, WEBP, GIF, PDF, HEIC (PDF handled natively by OCR.Space; no pdftoppm needed)
- Free tier: register at ocr.space/ocrapi for a key; limit ~1 MB/file, 1000 req/month
- Tesseract is installed on Replit dev environment but NOT used in production — OCR.Space is the active engine

## Parser stack (rule-based, runs locally after API returns text)
- `ocr_detect_doc_type()` — keyword + MRZ pattern detection
- `ocr_parse_passport_mrz()` — full TD3 MRZ parser (P< line + data line)
- `ocr_parse_passport_text()` — regex fallback when no MRZ
- `ocr_parse_nid()` — Bangladesh NID (10/13/17-digit numbers)
- `ocr_parse_visa()` — visa documents
- `ocr_estimate_confidence()` — heuristic 0–100 score

## Global OCR Import Modal
Added to `pages/agency_app.php` (HTML + JS) — `openOcrImport(callback)` available on every page. AJAX endpoint: `?ocr_import_ajax=1&q=` (in actions_get.php).
