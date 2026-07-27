# South Zone ERP — File Structure

Your app is now split into 26 files instead of one. Nothing about how it *works* has
changed — this is a pure reorganization, not a rewrite. Every function, query, and
HTML block is byte-for-byte the same code, just moved into a logically-named file.

## How to deploy

Upload the **entire folder structure below** to your Hostinger `public_html` (or
subfolder), keeping the folders exactly as they are. `index.php` is the only file
you ever open in a browser — all the others are loaded automatically by it.

```
index.php                          <- entry point, only this loads directly in a browser
includes/
  db.php                           <- DB connection + all auto-migrations
  helpers.php                      <- utility functions (CSRF, currency, TOTP, email, etc.)
  config.php                       <- $modules, $statusOptions, $countryCurrencyMap, etc.
  actions.php                      <- wires all the action files below together
  actions_auth.php                 <- login, 2FA login step, resend verification, register
  actions_superadmin.php           <- Super Admin: agencies, plans, payments, settings
  actions_2fa.php                  <- 2FA setup/disable (Super Admin, Agency Admin, Staff)
  actions_agency.php               <- profile, follow-ups, staff, invoices, expenses
  actions_generic_crud.php         <- add/edit for enquiries, passports, visas, tickets, umrah, tours, customers
  actions_get.php                  <- delete, delete_expense, begin_2fa_setup (GET-based)
  router.php                       <- HTML shell (head/body) + route switch + closing tags
pages/
  landing.php                      <- public homepage
  auth.php                         <- login / register / 2FA challenge page
  superadmin.php                   <- Super Admin dashboard (all tabs)
  agency_app.php                   <- agency shell: sidebar, header, and page dispatch
  agency/
    dashboard.php
    accounting.php
    invoices.php
    crmhistory.php                 <- the "query_history" follow-up/timeline page
    customers.php                  <- the customer detail/profile page
    profile.php
    staff.php                      <- staff list + staff performance history
    reports.php                    <- the "download reports" page
    subscription.php               <- subscription renewal payment page
    generic_crud.php               <- shared table+form for enquiries/passports/visas/tickets/umrah/tours/customers-list
```

## A few things worth knowing

- **`pages/agency/customers.php`** is the individual **customer profile** page (a
  specific customer's order history). The **customers list/table** itself is part
  of `generic_crud.php`, same as it was in the original file — that table is shared
  machinery used by six different modules, so splitting just "customers" out of it
  would mean duplicating that whole table/form template. I kept it as-is rather than
  fork it.
- **`staff.php`** contains both the staff list and the staff performance-history
  view — they share data-prep and are really one feature.
- A couple of tiny section-divider comments (e.g. `// LANDING PAGE TEMPLATE`) were
  dropped since the filename now serves that purpose. Nothing else was removed —
  I ran an automated line-by-line diff between the original file and every new file
  combined, and confirmed the only line differences are that handful of now-redundant
  comments plus the `if/elseif` page-selector lines that got replaced by clean
  `include` statements.

## Testing note

I split this file using precise, scripted extraction (not manual retyping) and
verified it extensively: brace balance checked file-by-file and in total, every one
of the 33 functions confirmed to exist exactly once and match the original exactly,
and every `include`/`require` path confirmed to resolve to a real file.

That said, **I was not able to run a live PHP syntax check or exercise the app this
session** — the sandbox's package/network access was down the entire time I worked
on this. Please test this on a staging copy (or a local server) before replacing
your production `index.php`, the same way you'd want to test any large refactor.
If anything doesn't load right, tell me the exact error and I'll fix it immediately.

## Changelog

**Transaction Date (backdating support)** — Passports, Visas, Tickets, Umrah, and
Tours now each have a new **Transaction Date** field on their Add/Edit forms,
defaulting to today but fully editable. This field - not the system's entry
timestamp - now drives the Dashboard's Net Profit chart and Monthly Performance,
the Accounting module's Total Income, the Sales/Profit Download Report and its
date filters, Staff performance history, and Customer order history. Enquiries
already had its own `date` field and now feeds reports from that the same way.
Existing scheduling fields (Application Date, Travel Date, Departure Date) are
untouched and still serve their original purpose. The Dashboard's "Recent
Queries / Recent Sales" widget and all follow-up timestamps intentionally still
run on system-entry time, per your instruction - that widget is about *what needs
attention right now*, not the transaction date. Existing records were backfilled
so their Transaction Date starts out equal to their original entry date; correct
any of them anytime by editing the record.

**Homepage Redesign** — `pages/landing.php` got a visual refresh inspired by a
design-system reference the user supplied (a dark-mode "Vireo/Aurora" style
spec). Since that reference was a pure design-token document (colors, type
scale, spacing, shadows) with no actual page content, and its color scheme was
dark navy + teal/cyan/purple, the *structure* of that system was translated
onto the existing White & Blue brand rather than importing its literal dark
palette: a Space Grotesk display font paired with the existing Plus Jakarta
Sans body font (headings only), pill-shaped badges, a consistent card
hover-lift + soft blue-glow shadow treatment, a refined shadow-depth hierarchy,
and softer floating-blob background accents. All CSS for this lives in a
`#sz-home`-scoped `<style>` block inside `landing.php` itself, so it cannot
affect the shared dashboard/admin styling in `router.php`. Every section,
heading, paragraph, button label, link, route, feature, pricing tier, and the
footer are 100% unchanged — verified with an automated text-node and PHP-logic
diff against the previous version, not just eyeballed. No FAQ, testimonials,
or separate Contact section were added, because none existed on the original
homepage and inventing that copy would violate the no-placeholder-content rule.
