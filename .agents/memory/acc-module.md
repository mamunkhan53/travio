---
name: Accounting module architecture
description: How the 17-page Finance & Accounting module is wired into the app
---

## File locations
- Page files: `pages/agency/acc/acc_*.php` (17 files in subdirectory)
- Action handlers: `includes/acc_actions.php` (required at end of `actions_agency.php`)
- Sidebar JS toggle: `toggleAccMenu()` in `includes/router.php`
- Sidebar group: `$key === 'acc'` block in `pages/agency_app.php`

## Config.php sentinel pattern
- Sentinel key `'acc'` triggers collapsible group render in sidebar
- Sub-pages use `'hidden'=>true, 'acc_module'=>true`
- Legacy `'accounting'` key kept as `hidden, acc_module:true` — redirects to `acc_dashboard` so old URLs don't break

## Dispatch in agency_app.php
- Data-loader exclusion: `empty($modules[$page]['acc_module'])`
- Dispatch: `elseif (isset($modules[$page]) && !empty($modules[$page]['acc_module']))` → `include pages/agency/acc/$page.php`
- `acc_settings` blocked for staff users

## Staff permissions (8 new columns)
`can_view_acc_reports`, `can_manage_acc_income`, `can_manage_acc_cash`, `can_manage_acc_bank`, `can_manage_acc_payable`, `can_manage_acc_journals`, `can_manage_acc_vouchers`, `can_manage_acc_receivable`

## Key data sources
- Auto-income from: `passports`, `visas`, `tickets`, `umrah`, `tours` tables (selling_price - service_cost where status IN Completed/Paid/Confirmed)
- Manual income: `acc_income` table
- Expenses: existing `accounting_expenses` table (+ vendor, attachment_path, approval_status columns added)
- Cash: `acc_cash_transactions`; Bank: `acc_bank_transactions`
- Opening balances stored in `acc_settings` (keys: `opening_cash_balance`, `opening_bank_balance`)
- Voucher numbering prefix from `acc_settings` (keys: `voucher_prefix_pv`, `voucher_prefix_rv`)

**Why subdirectory:** 17 files would clutter pages/agency/; grouping under acc/ keeps it organized and mirrors sc/ pattern intention.
**How to apply:** Any new accounting sub-page goes in pages/agency/acc/, gets a config.php entry with `acc_module:true`, and an acc_sub entry in the sidebar block.
