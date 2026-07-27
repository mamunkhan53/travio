---
name: WhatsApp module architecture
description: Key decisions and gotchas for the WhatsApp messaging module added to South Zone ERP
---

# WhatsApp Module

## Files added/changed
- `includes/db.php` — 3 new tables (whatsapp_providers, whatsapp_message_logs, whatsapp_message_recipients) + can_send_whatsapp on staff_permissions
- `includes/config.php` — 'whatsapp' added to $modules before 'invoices'
- `includes/helpers.php` — sendWhatsAppViaProvider() and _waHttpPost() helpers
- `includes/actions_agency.php` — send_whatsapp and save_whatsapp_provider POST handlers
- `includes/actions_get.php` — delete_whatsapp_log GET handler + wa_recipients_ajax JSON endpoint
- `pages/agency_app.php` — whatsapp in exclusion list + page dispatch
- `pages/agency/staff.php` — can_send_whatsapp in $permsList and JS forEach array
- `pages/agency/whatsapp.php` — full module UI (Compose/History/Settings tabs)

## Critical: AJAX handlers before HTML output
The AJAX JSON endpoint (wa_recipients_ajax) MUST live in actions_get.php, not at the bottom of whatsapp.php. By the time whatsapp.php executes, router.php has already started outputting HTML — any header() call would throw "headers already sent" and corrupt the JSON response.

**Why:** router.php opens `<!DOCTYPE html>` immediately before the route switch, so all page files run after HTML has started.

**How to apply:** Any future AJAX/JSON endpoints that return early with a Content-Type header must be placed in actions_get.php (runs before router.php) or actions_agency.php (same, for POST).

## Provider abstraction
Five supported api_type values: meta_cloud, twilio, vonage, wati, custom_webhook. Adding a new provider = new `if ($apiType === '...')` block in sendWhatsAppViaProvider() in helpers.php. Nothing else needs changing.

## Permission
can_send_whatsapp column on staff_permissions. Agency Admins always have it (has_permission returns true for non-staff). Staff need it granted explicitly.

## Log ID format
generateSerialId($conn, 'whatsapp_message_logs', 'WA', $agency_id) → WA-XXXXX prefix, consistent with all other module IDs.
