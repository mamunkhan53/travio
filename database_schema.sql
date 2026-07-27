-- =============================================================================
-- SOUTH ZONE - TRAVEL AGENCY SAAS ERP
-- COMPLETE / FRESH DATABASE SCHEMA
-- =============================================================================
-- This file reflects the FULL current schema needed by index.php, including
-- everything the app used to build up on its own via runtime auto-migration
-- (ALTER TABLE / CREATE TABLE IF NOT EXISTS). Use this single file when setting
-- up a brand new hosting environment instead of relying on the app to migrate
-- itself — the app only ALTERS the 10 core tables below, it does not CREATE
-- them, so they must already exist before the app is first opened.
--
-- HOSTINGER / SHARED HOSTING NOTE: this file does NOT create its own database
-- on purpose. Create your database first in hPanel (or use your existing one),
-- then open it in phpMyAdmin and use Import to run this file INTO it. Do not
-- run this against a blank MySQL session with no database selected.
--
-- Whatever database name you import into, make sure $db_name in index.php
-- (top of the file) matches it exactly. As of your current index.php that is:
--   $db_name = "u873990700_ERP2";
--
-- Run this once on a fresh, empty database. Safe to re-run (uses IF NOT EXISTS
-- / ON DUPLICATE KEY UPDATE throughout).
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- 1. SAAS CORE TABLES (Agencies & Users)
-- =============================================================================

CREATE TABLE IF NOT EXISTS agencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(100) NOT NULL,
    company_email VARCHAR(100) NOT NULL,
    company_phone VARCHAR(20) NOT NULL,
    address TEXT,
    logo LONGTEXT,
    status ENUM('Pending Approval', 'Active', 'Suspended') DEFAULT 'Pending Approval',

    -- Subscription / SaaS billing (added by the runtime migration; baked in here)
    subscription_plan VARCHAR(20) DEFAULT 'Trial',
    subscription_amount DECIMAL(10,2) DEFAULT 0,
    subscription_started_at DATETIME NULL,
    subscription_expires_at DATETIME NULL,
    trial_ends_at DATETIME NULL,
    subscription_notes TEXT NULL,
    last_payment_at DATETIME NULL,
    last_payment_amount DECIMAL(10,2) NULL,
    last_payment_method VARCHAR(50) NULL,
    last_payment_reference VARCHAR(100) NULL,

    -- Multi-currency (added by the runtime migration; baked in here)
    country VARCHAR(100) DEFAULT 'Bangladesh',
    currency_code VARCHAR(10) NOT NULL DEFAULT 'BDT',
    currency_symbol VARCHAR(10) NOT NULL DEFAULT '৳',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_id INT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Super Admin', 'Agency Admin', 'Staff') NOT NULL,
    status ENUM('Pending Approval', 'Active', 'Suspended') DEFAULT 'Pending Approval',

    -- Email verification (added by the runtime migration; baked in here). Defaults to verified=1
    -- so this never locks out an account created before the feature existed.
    email_verified TINYINT(1) NOT NULL DEFAULT 1,
    email_verification_token VARCHAR(64) NULL,
    email_verification_sent_at DATETIME NULL,

    -- Two-Factor Authentication (TOTP)
    totp_secret VARCHAR(32) NULL,
    totp_enabled TINYINT(1) NOT NULL DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
);

-- Default Super Admin login: admin@southzone.com / admin123
-- (index.php also force-resets this password to admin123 on every request,
-- so this hash is a correct starting point either way.)
INSERT INTO users (full_name, email, password_hash, role, status)
VALUES ('Super Admin', 'admin@southzone.com', '$2y$10$siVeDRoYGcYyrX3qg7gbf.04Qp0wSrKBLCOO27ld//jg778se36Yq', 'Super Admin', 'Active')
ON DUPLICATE KEY UPDATE id = id;

-- =============================================================================
-- 2. STAFF MODULE (agency employees, permissions, follow-ups, notifications)
-- =============================================================================

CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    profile_photo LONGTEXT,
    commission_rate DECIMAL(5,2) DEFAULT 20.00,

    -- Two-Factor Authentication (TOTP) - same mechanism as Agency Admin / Super Admin
    totp_secret VARCHAR(32) NULL,
    totp_enabled TINYINT(1) NOT NULL DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS staff_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    can_add_enquiry TINYINT(1) DEFAULT 0,
    can_edit_enquiry TINYINT(1) DEFAULT 0,
    can_delete_enquiry TINYINT(1) DEFAULT 0,
    can_add_sale TINYINT(1) DEFAULT 0,
    can_edit_sale TINYINT(1) DEFAULT 0,
    can_delete_sale TINYINT(1) DEFAULT 0,
    can_add_expense TINYINT(1) DEFAULT 0,
    can_edit_expense TINYINT(1) DEFAULT 0,
    can_delete_expense TINYINT(1) DEFAULT 0,
    can_view_reports TINYINT(1) DEFAULT 0,
    can_manage_customers TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- =============================================================================
-- 3. CRM & CUSTOMERS TABLES
-- =============================================================================

CREATE TABLE IF NOT EXISTS customers (
    id VARCHAR(50) PRIMARY KEY,
    agency_id INT NOT NULL,
    name VARCHAR(100),
    mobile VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS customer_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_id INT NOT NULL,
    customer_id VARCHAR(50) NOT NULL,
    staff_id INT NULL,
    note TEXT NOT NULL,
    follow_up_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS record_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_id INT NOT NULL,
    module_name VARCHAR(50) NOT NULL,
    record_id VARCHAR(50) NOT NULL,
    staff_id INT NULL,
    note TEXT NOT NULL,
    follow_up_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_record_followups_lookup (agency_id, module_name, record_id)
);

CREATE TABLE IF NOT EXISTS enquiries (
    id VARCHAR(50) PRIMARY KEY,
    agency_id INT NOT NULL,
    date DATE,
    customer VARCHAR(100),
    mobile VARCHAR(20) NOT NULL,
    category ENUM('Passport Processing', 'Visa Processing', 'Air Ticket', 'Umrah Package', 'Tour Package', 'Hotel Booking', 'Manpower', 'Other'),
    service VARCHAR(100),
    status VARCHAR(50),
    notes TEXT,
    reference_staff_id INT NULL,
    created_by_staff_id INT NULL,
    updated_by_staff_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
    FOREIGN KEY (reference_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_enquiries_ref (reference_staff_id),
    INDEX idx_enquiries_agency_date (agency_id, date)
);

-- =============================================================================
-- 4. ERP SERVICE MODULE TABLES (Sales)
-- =============================================================================

CREATE TABLE IF NOT EXISTS passports (
    id VARCHAR(50) PRIMARY KEY,
    agency_id INT NOT NULL,
    name VARCHAR(100),
    mobile VARCHAR(20) NOT NULL,
    type VARCHAR(50),
    appDate DATE,
    transaction_date DATE NULL,
    status VARCHAR(50),
    service_cost DECIMAL(10, 2) DEFAULT 0,
    selling_price DECIMAL(10, 2) DEFAULT 0,
    reference_staff_id INT NULL,
    created_by_staff_id INT NULL,
    updated_by_staff_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
    FOREIGN KEY (reference_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_passports_ref (reference_staff_id),
    INDEX idx_passports_agency_date (agency_id, created_at),
    INDEX idx_passports_transaction_date (agency_id, transaction_date)
);

CREATE TABLE IF NOT EXISTS visas (
    id VARCHAR(50) PRIMARY KEY,
    agency_id INT NOT NULL,
    name VARCHAR(100),
    mobile VARCHAR(20) NOT NULL,
    country VARCHAR(100),
    type VARCHAR(50),
    transaction_date DATE NULL,
    status VARCHAR(50),
    service_cost DECIMAL(10, 2) DEFAULT 0,
    selling_price DECIMAL(10, 2) DEFAULT 0,
    reference_staff_id INT NULL,
    created_by_staff_id INT NULL,
    updated_by_staff_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
    FOREIGN KEY (reference_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_visas_ref (reference_staff_id),
    INDEX idx_visas_agency_date (agency_id, created_at),
    INDEX idx_visas_transaction_date (agency_id, transaction_date)
);

CREATE TABLE IF NOT EXISTS tickets (
    id VARCHAR(50) PRIMARY KEY,
    agency_id INT NOT NULL,
    name VARCHAR(100),
    mobile VARCHAR(20) NOT NULL,
    airline VARCHAR(100),
    route VARCHAR(100),
    date DATE,
    transaction_date DATE NULL,
    status VARCHAR(50),
    service_cost DECIMAL(10, 2) DEFAULT 0,
    selling_price DECIMAL(10, 2) DEFAULT 0,
    reference_staff_id INT NULL,
    created_by_staff_id INT NULL,
    updated_by_staff_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
    FOREIGN KEY (reference_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_tickets_ref (reference_staff_id),
    INDEX idx_tickets_agency_date (agency_id, created_at),
    INDEX idx_tickets_transaction_date (agency_id, transaction_date)
);

CREATE TABLE IF NOT EXISTS umrah (
    id VARCHAR(50) PRIMARY KEY,
    agency_id INT NOT NULL,
    name VARCHAR(100),
    mobile VARCHAR(20) NOT NULL,
    package VARCHAR(100),
    depDate DATE,
    transaction_date DATE NULL,
    status VARCHAR(50),
    service_cost DECIMAL(10, 2) DEFAULT 0,
    selling_price DECIMAL(10, 2) DEFAULT 0,
    reference_staff_id INT NULL,
    created_by_staff_id INT NULL,
    updated_by_staff_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
    FOREIGN KEY (reference_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_umrah_ref (reference_staff_id),
    INDEX idx_umrah_agency_date (agency_id, created_at),
    INDEX idx_umrah_transaction_date (agency_id, transaction_date)
);

CREATE TABLE IF NOT EXISTS tours (
    id VARCHAR(50) PRIMARY KEY,
    agency_id INT NOT NULL,
    name VARCHAR(100),
    mobile VARCHAR(20) NOT NULL,
    package VARCHAR(100),
    date DATE,
    transaction_date DATE NULL,
    status VARCHAR(50),
    service_cost DECIMAL(10, 2) DEFAULT 0,
    selling_price DECIMAL(10, 2) DEFAULT 0,
    reference_staff_id INT NULL,
    created_by_staff_id INT NULL,
    updated_by_staff_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
    FOREIGN KEY (reference_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_tours_ref (reference_staff_id),
    INDEX idx_tours_agency_date (agency_id, created_at),
    INDEX idx_tours_transaction_date (agency_id, transaction_date)
);

-- =============================================================================
-- 5. INVOICING MODULE
-- =============================================================================

CREATE TABLE IF NOT EXISTS invoices (
    id VARCHAR(50) PRIMARY KEY,
    agency_id INT NOT NULL,
    invoice_number VARCHAR(50),
    issue_date DATE,
    customer_name VARCHAR(100),
    mobile VARCHAR(20),
    email VARCHAR(100),
    service_desc TEXT,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10, 2) DEFAULT 0,
    subtotal DECIMAL(10, 2) DEFAULT 0,
    discount DECIMAL(10, 2) DEFAULT 0,
    tax DECIMAL(10, 2) DEFAULT 0,
    grand_total DECIMAL(10, 2) DEFAULT 0,
    paid_amount DECIMAL(10, 2) DEFAULT 0,
    due_amount DECIMAL(10, 2) DEFAULT 0,
    reference_staff_id INT NULL,
    created_by_staff_id INT NULL,
    updated_by_staff_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
    FOREIGN KEY (reference_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_invoices_ref (reference_staff_id),
    INDEX idx_invoices_agency_date (agency_id, created_at)
);

-- =============================================================================
-- 6. DEADLINE NOTIFICATIONS
-- =============================================================================

CREATE TABLE IF NOT EXISTS service_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_id INT NOT NULL,
    sale_id VARCHAR(50) NOT NULL,
    module_name VARCHAR(50) NOT NULL,
    customer_name VARCHAR(100),
    staff_id INT NULL,
    notification_type VARCHAR(50),
    deadline_date DATE NOT NULL,
    notify_date DATE NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_by INT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
);

-- =============================================================================
-- 7. SUBSCRIPTION / SAAS BILLING
-- =============================================================================

CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_key VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_usd DECIMAL(10,2) NOT NULL DEFAULT 0,
    duration_days INT NOT NULL DEFAULT 30,
    is_active TINYINT(1) DEFAULT 1,
    terms TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO subscription_plans (plan_key, name, price, price_usd, duration_days, is_active, terms) VALUES
    ('trial', 'Free Trial', 0.00, 0.00, 30, 1, 'Full access to every feature for 30 days after registration. No payment required.'),
    ('monthly', 'Monthly Plan', 500.00, 5.00, 30, 1, 'Billed every 30 days at 500 BDT. Cancel or switch plans anytime from the Super Admin panel.'),
    ('yearly', 'Yearly Plan', 3500.00, 35.00, 365, 1, 'Billed once a year at 3500 BDT. Best value for agencies that plan to stay long term.')
ON DUPLICATE KEY UPDATE plan_key = plan_key;

CREATE TABLE IF NOT EXISTS subscription_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agency_id INT NOT NULL,
    plan_key VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    method VARCHAR(50) NULL,
    reference VARCHAR(100) NULL,
    note VARCHAR(255) NULL,
    screenshot LONGTEXT NULL,
    status VARCHAR(20) DEFAULT 'Approved',
    decline_reason VARCHAR(255) NULL,
    new_expiry_at DATETIME NULL,
    recorded_by VARCHAR(100) NULL,
    reviewed_by VARCHAR(100) NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
);

-- Manual payment methods (bKash / Nagad / Bank) shown on the agency's Renew page
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_key VARCHAR(20) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    account_details TEXT NULL,
    instructions TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO payment_methods (method_key, display_name, account_details, instructions, is_active) VALUES
    ('bkash', 'bKash', '01XXXXXXXXX (Personal)', 'Send Money to the number above, then enter the Transaction ID below.', 1),
    ('nagad', 'Nagad', '01XXXXXXXXX (Personal)', 'Send Money to the number above, then enter the Transaction ID below.', 1),
    ('bank', 'Bank Transfer', 'Bank: Your Bank Name | Account Name: Your Company | Account No: 0000000000 | Routing: 000000', 'Transfer to the account above, then enter the transaction / slip reference below.', 1)
ON DUPLICATE KEY UPDATE method_key = method_key;

-- Manual expense entries for the Accounting module (Income side is read-only,
-- pulled live from the existing Sales Net Profit calculation - not stored here)
CREATE TABLE IF NOT EXISTS accounting_expenses (
    id VARCHAR(50) PRIMARY KEY,
    agency_id INT NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(100),
    title VARCHAR(150) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50),
    remarks TEXT,
    created_by_staff_id INT NULL,
    updated_by_staff_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_accounting_expenses_agency_date (agency_id, expense_date)
);

-- Platform-wide feature flags controlled by the Super Admin (Settings tab)
CREATE TABLE IF NOT EXISTS platform_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT INTO platform_settings (setting_key, setting_value) VALUES
    ('email_verification_required', '0'),
    ('agency_2fa_enabled', '0')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- NOTES FOR DEPLOYMENT ON NEW HOSTING
-- =============================================================================
-- 1. Update ONLY the bKash / Nagad / Bank account_details and instructions
--    above (or edit them later from Super Admin -> Payment Methods) with your
--    real account numbers - the placeholders above are NOT real.
-- 2. Create the database in hPanel first (or reuse your existing one), import
--    this file INTO it via phpMyAdmin, then make sure $host, $db_name,
--    $username, $password at the top of index.php match that exact database
--    and its MySQL user.
-- 3. Default Super Admin login is admin@southzone.com / admin123 - change
--    this password immediately after first login.
-- 4. Everything in sections 2-7 above is also auto-created/auto-migrated by
--    index.php itself if missing, EXCEPT section 1 (agencies, users) and the
--    base columns of enquiries/customers/passports/visas/tickets/umrah/tours/
--    invoices in sections 3-5 - those must exist before the app is opened for
--    the first time, which is exactly what this file provides.
-- =============================================================================
