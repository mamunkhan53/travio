<?php
// =========================================================================
// 3. SAAS MODULE CONFIGURATION
// =========================================================================
$statusOptions = ['Pending', 'Processing', 'Submitted', 'Confirmed', 'Completed', 'Paid', 'Unpaid', 'Lost'];

// Multi-currency: supported registration countries and their default currency
$countryCurrencyMap = [
    'Bangladesh'           => ['flag' => '🇧🇩', 'code' => 'BDT', 'symbol' => '৳'],
    'India'                => ['flag' => '🇮🇳', 'code' => 'INR', 'symbol' => '₹'],
    'Pakistan'             => ['flag' => '🇵🇰', 'code' => 'PKR', 'symbol' => '₨'],
    'Nepal'                => ['flag' => '🇳🇵', 'code' => 'NPR', 'symbol' => 'रू'],
    'United Arab Emirates' => ['flag' => '🇦🇪', 'code' => 'AED', 'symbol' => 'د.إ'],
    'Saudi Arabia'         => ['flag' => '🇸🇦', 'code' => 'SAR', 'symbol' => '﷼'],
    'Qatar'                => ['flag' => '🇶🇦', 'code' => 'QAR', 'symbol' => 'ر.ق'],
    'Oman'                 => ['flag' => '🇴🇲', 'code' => 'OMR', 'symbol' => 'ر.ع.'],
    'Kuwait'               => ['flag' => '🇰🇼', 'code' => 'KWD', 'symbol' => 'د.ك'],
    'Sri Lanka'            => ['flag' => '🇱🇰', 'code' => 'LKR', 'symbol' => 'Rs'],
];

$leadCategories = ['Passport Processing', 'Visa Processing', 'Air Ticket', 'Umrah Package', 'Tour Package', 'Hotel Booking', 'Manpower', 'Other'];

// Shared Fields 
$financialFields = [
    'service_cost' => ['label' => 'Service Cost', 'type' => 'number'],
    'selling_price' => ['label' => 'Selling Price', 'type' => 'number'],
    'status' => ['label' => 'Status', 'type' => 'select', 'options' => $statusOptions]
];

$modules = [
    'dashboard' => ['title' => 'Dashboard', 'icon' => 'fa-solid fa-border-all'],
    'subscription_payment' => ['title' => 'Renew Subscription', 'icon' => 'fa-solid fa-credit-card', 'hidden' => true],
    'enquiries' => [
        'title' => 'CRM Leads & History', 'icon' => 'fa-solid fa-users-viewfinder', 'prefix' => 'LD',
        'fields' => [
            'date' => ['label' => 'Date', 'type' => 'date'],
            'customer' => ['label' => 'Customer Name', 'type' => 'text'],
            'mobile' => ['label' => 'Mobile Number', 'type' => 'text'], 
            'category' => ['label' => 'Category', 'type' => 'select', 'options' => $leadCategories],
            'service' => ['label' => 'Service Details', 'type' => 'text'],
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => $statusOptions],
            'notes' => ['label' => 'Notes', 'type' => 'text']
        ]
    ],
    'customers' => [
        'title' => 'Customers', 'icon' => 'fa-solid fa-address-book', 'prefix' => 'CU',
        'fields' => [
            'name' => ['label' => 'Full Name', 'type' => 'text'],
            'mobile' => ['label' => 'Mobile Number', 'type' => 'text'],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'category' => ['label' => 'Category', 'type' => 'text']
        ]
    ],
    // Travel Services group sentinel — sub-items carry 'travel_module' => true
    'travel_services' => ['title' => 'Travel Services', 'icon' => 'fa-solid fa-globe'],
    'passports' => [
        'title' => 'Passports', 'icon' => 'fa-solid fa-passport', 'is_service' => true, 'cat' => 'Passport Processing', 'prefix' => 'PA', 'hidden' => true, 'travel_module' => true,
        'fields' => array_merge([
            'transaction_date' => ['label' => 'Transaction Date', 'type' => 'date'],
            'name' => ['label' => 'Customer Name', 'type' => 'text'],
            'mobile' => ['label' => 'Mobile Number', 'type' => 'text'],
            'type' => ['label' => 'Passport Type', 'type' => 'text'],
            'appDate' => ['label' => 'Application Date', 'type' => 'date'],
        ], $financialFields)
    ],
    'visas' => [
        'title' => 'Visas', 'icon' => 'fa-solid fa-file-signature', 'is_service' => true, 'cat' => 'Visa Processing', 'prefix' => 'VI', 'hidden' => true, 'travel_module' => true,
        'fields' => array_merge([
            'transaction_date' => ['label' => 'Transaction Date', 'type' => 'date'],
            'name' => ['label' => 'Customer Name', 'type' => 'text'],
            'mobile' => ['label' => 'Mobile Number', 'type' => 'text'],
            'country' => ['label' => 'Country', 'type' => 'text'],
            'type' => ['label' => 'Visa Type', 'type' => 'text'],
        ], $financialFields)
    ],
    'tickets' => [
        'title' => 'Air Tickets', 'icon' => 'fa-solid fa-plane', 'is_service' => true, 'cat' => 'Air Ticket', 'prefix' => 'TK', 'hidden' => true, 'travel_module' => true,
        'fields' => array_merge([
            'transaction_date' => ['label' => 'Transaction Date', 'type' => 'date'],
            'name' => ['label' => 'Passenger Name', 'type' => 'text'],
            'mobile' => ['label' => 'Mobile Number', 'type' => 'text'],
            'airline' => ['label' => 'Airline', 'type' => 'text'],
            'route' => ['label' => 'Route', 'type' => 'text'],
            'date' => ['label' => 'Travel Date', 'type' => 'date'],
        ], $financialFields)
    ],
    'umrah' => [
        'title' => 'Umrah', 'icon' => 'fa-solid fa-kaaba', 'is_service' => true, 'cat' => 'Umrah Package', 'prefix' => 'UM', 'hidden' => true,
        'fields' => array_merge([
            'transaction_date' => ['label' => 'Transaction Date', 'type' => 'date'],
            'name' => ['label' => 'Pilgrim Name', 'type' => 'text'],
            'mobile' => ['label' => 'Mobile Number', 'type' => 'text'],
            'package' => ['label' => 'Package Name', 'type' => 'text'],
            'depDate' => ['label' => 'Departure Date', 'type' => 'date'],
        ], $financialFields)
    ],
    // Hajj & Umrah group sentinel — sub-pages carry 'umrah_module' => true
    'hajj_umrah'      => ['title' => 'Hajj & Umrah',      'icon' => 'fa-solid fa-kaaba'],
    'umrah_packages'  => ['title' => 'Packages',           'icon' => 'fa-solid fa-box-open',       'hidden' => true, 'umrah_module' => true],
    'umrah_bookings'  => ['title' => 'Bookings',           'icon' => 'fa-solid fa-calendar-check', 'hidden' => true, 'umrah_module' => true],
    'umrah_payments'  => ['title' => 'Payments',           'icon' => 'fa-solid fa-coins',          'hidden' => true, 'umrah_module' => true],
    'umrah_checklist' => ['title' => 'Document Checklist', 'icon' => 'fa-solid fa-list-check',     'hidden' => true, 'umrah_module' => true],
    'umrah_reports'   => ['title' => 'Reports',            'icon' => 'fa-solid fa-chart-bar',      'hidden' => true, 'umrah_module' => true],
    'tours' => [
        'title' => 'Tours', 'icon' => 'fa-solid fa-map-location-dot', 'is_service' => true, 'cat' => 'Tour Package', 'prefix' => 'TO', 'hidden' => true, 'travel_module' => true,
        'fields' => array_merge([
            'transaction_date' => ['label' => 'Transaction Date', 'type' => 'date'],
            'name' => ['label' => 'Customer Name', 'type' => 'text'],
            'mobile' => ['label' => 'Mobile Number', 'type' => 'text'],
            'package' => ['label' => 'Package Name', 'type' => 'text'],
            'date' => ['label' => 'Travel Date', 'type' => 'date'],
        ], $financialFields)
    ],
    // Student Consultancy — 'sc' renders the sidebar group; sc_* are hidden module markers
    'sc'              => ['title' => 'Student Consultancy', 'icon' => 'fa-solid fa-graduation-cap'],
    'sc_leads'        => ['title' => 'Student Leads',            'icon' => 'fa-solid fa-user-graduate',     'hidden' => true, 'sc_module' => true],
    'sc_students'     => ['title' => 'Students',                 'icon' => 'fa-solid fa-id-card',           'hidden' => true, 'sc_module' => true],
    'sc_applications' => ['title' => 'University Applications',  'icon' => 'fa-solid fa-building-columns',  'hidden' => true, 'sc_module' => true],
    'sc_documents'    => ['title' => 'Documents',                'icon' => 'fa-solid fa-folder-open',       'hidden' => true, 'sc_module' => true],
    'sc_visa'         => ['title' => 'Visa Processing',          'icon' => 'fa-solid fa-stamp',             'hidden' => true, 'sc_module' => true],
    'sc_payments'     => ['title' => 'Payments',                 'icon' => 'fa-solid fa-coins',             'hidden' => true, 'sc_module' => true],
    'sc_followups'    => ['title' => 'Follow-ups',               'icon' => 'fa-solid fa-calendar-check',    'hidden' => true, 'sc_module' => true],
    'sc_reports'      => ['title' => 'Reports',                  'icon' => 'fa-solid fa-chart-bar',         'hidden' => true, 'sc_module' => true],
    'sc_settings'     => ['title' => 'Settings',                 'icon' => 'fa-solid fa-gear',              'hidden' => true, 'sc_module' => true, 'admin_only' => true],
    'whatsapp' => ['title' => 'WhatsApp', 'icon' => 'fa-brands fa-whatsapp'],
    'invoices' => ['title' => 'Invoices', 'icon' => 'fa-solid fa-file-invoice-dollar'],
    // 'ocr_scanner' => ['title' => 'Document Scanner', 'icon' => 'fa-solid fa-id-card-clip'],
    // Accounting group sentinel — sub-pages are acc_* with hidden+acc_module flags
    'acc'                    => ['title' => 'Accounting', 'icon' => 'fa-solid fa-calculator'],
    'accounting'             => ['title' => 'Accounting (legacy redirect)', 'icon' => 'fa-solid fa-calculator', 'hidden' => true, 'acc_module' => true],
    'acc_dashboard'          => ['title' => 'Dashboard',           'icon' => 'fa-solid fa-border-all',          'hidden' => true, 'acc_module' => true],
    'acc_chart_of_accounts'  => ['title' => 'Chart of Accounts',   'icon' => 'fa-solid fa-list-tree',           'hidden' => true, 'acc_module' => true],
    'acc_general_ledger'     => ['title' => 'General Ledger',      'icon' => 'fa-solid fa-book',                'hidden' => true, 'acc_module' => true],
    'acc_cash_book'          => ['title' => 'Cash Book',           'icon' => 'fa-solid fa-money-bill-wave',     'hidden' => true, 'acc_module' => true],
    'acc_bank_book'          => ['title' => 'Bank Book',           'icon' => 'fa-solid fa-building-columns',    'hidden' => true, 'acc_module' => true],
    'acc_income'             => ['title' => 'Income',              'icon' => 'fa-solid fa-circle-dollar-to-slot','hidden' => true, 'acc_module' => true],
    'acc_expenses'           => ['title' => 'Expenses',            'icon' => 'fa-solid fa-receipt',             'hidden' => true, 'acc_module' => true],
    'acc_receivable'         => ['title' => 'Accounts Receivable', 'icon' => 'fa-solid fa-hand-holding-dollar', 'hidden' => true, 'acc_module' => true],
    'acc_payable'            => ['title' => 'Accounts Payable',    'icon' => 'fa-solid fa-file-invoice',        'hidden' => true, 'acc_module' => true],
    'acc_journals'           => ['title' => 'Journal Entries',     'icon' => 'fa-solid fa-journal-whills',      'hidden' => true, 'acc_module' => true],
    'acc_payment_vouchers'   => ['title' => 'Payment Vouchers',    'icon' => 'fa-solid fa-money-check-dollar',  'hidden' => true, 'acc_module' => true],
    'acc_receipt_vouchers'   => ['title' => 'Receipt Vouchers',    'icon' => 'fa-solid fa-file-lines',          'hidden' => true, 'acc_module' => true],
    'acc_pl'                 => ['title' => 'Profit & Loss',       'icon' => 'fa-solid fa-chart-line',          'hidden' => true, 'acc_module' => true],
    'acc_balance_sheet'      => ['title' => 'Balance Sheet',       'icon' => 'fa-solid fa-scale-balanced',      'hidden' => true, 'acc_module' => true],
    'acc_vat'                => ['title' => 'VAT / Tax Reports',   'icon' => 'fa-solid fa-percent',             'hidden' => true, 'acc_module' => true],
    'acc_financial_reports'  => ['title' => 'Financial Reports',   'icon' => 'fa-solid fa-chart-pie',           'hidden' => true, 'acc_module' => true],
    'acc_settings'           => ['title' => 'Settings',            'icon' => 'fa-solid fa-gear',                'hidden' => true, 'acc_module' => true, 'admin_only' => true],
    'download' => ['title' => 'Download Reports', 'icon' => 'fa-solid fa-download'],
    // Staff Management group sentinel — sub-pages carry 'staff_module' => true
    'staff_mgmt'       => ['title' => 'Staff Management', 'icon' => 'fa-solid fa-user-tie',            'admin_only' => true],
    'staff'            => ['title' => 'Staff',             'icon' => 'fa-solid fa-users',               'admin_only' => true, 'hidden' => true, 'staff_module' => true],
    'staff_history'    => ['title' => 'Working History',   'icon' => 'fa-solid fa-clock-rotate-left',   'admin_only' => true, 'hidden' => true, 'staff_module' => true],
    'staff_attendance' => ['title' => 'Attendance',        'icon' => 'fa-solid fa-calendar-day',        'admin_only' => true, 'hidden' => true, 'staff_module' => true],
    'staff_salary'     => ['title' => 'Salary',            'icon' => 'fa-solid fa-money-bill-wave',     'admin_only' => true, 'hidden' => true, 'staff_module' => true],
    'profile' => ['title' => 'Agency Settings', 'icon' => 'fa-solid fa-sliders', 'admin_only' => true, 'hidden' => true]
];

