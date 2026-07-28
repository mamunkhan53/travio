---
name: Student Consultancy Module
description: Architecture and key constraints for the SC module (leads, students, applications, documents, visa, payments, follow-ups, reports, settings).
---

## Structure
- **Actions**: `includes/sc_actions.php` — required at the bottom of `includes/actions_agency.php` via `require_once`.
- **Pages**: `pages/agency/sc_*.php` — dispatched in `pages/agency_app.php` via the `sc_module` flag on the module entry.
- **Config**: `includes/config.php` — all SC entries have `'hidden'=>true,'sc_module'=>true`; a `'sc'` sentinel key triggers the sidebar collapsible group.
- **Sidebar**: rendered in `pages/agency_app.php` inside the `$key === 'sc'` branch; `toggleScMenu()` in `includes/router.php`.
- **File uploads**: stored under `uploads/sc_docs/`; directory created at runtime if missing.

## Follow-ups
- Reuse `record_followups` table with `module_name='sc_leads'` or `'sc_students'`.
- `add_record_followup` in `actions_agency.php` was patched to allow these two module names and redirects to `?route=app&page=sc_followups` for SC tables instead of query_history.
- `sc_leads` and `sc_students` are in `$modules` (config.php), so the `array_key_exists` guard passes.

## Permission columns
Five additive columns on `staff` table: `can_manage_sc_leads`, `can_manage_sc_students`, `can_manage_sc_applications`, `can_manage_sc_payments`, `can_view_sc_reports`.

**Why:** Kept additive to avoid breaking existing staff permission logic.

## Key constraint
SC action handlers must stay in `sc_actions.php`, not inlined into `actions_agency.php`, to keep the file manageable and allow easy extension.
