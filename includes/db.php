<?php
// =========================================================================
// 1. DATABASE CONFIGURATION
// Credentials come from environment variables so the same code works on
// Replit (local MariaDB via Unix socket) and on Hostinger (TCP).
// =========================================================================
$host     = getenv('DB_HOST')   ?: '127.0.0.1';
$db_name  = getenv('DB_NAME')   ?: 'southzone_erp';
$username = getenv('DB_USER')   ?: 'southzone';
$password = getenv('DB_PASS')   ?: 'southzone_local';
$socket   = getenv('DB_SOCKET') ?: '/home/runner/mysql.sock';

// On Replit connect via Unix socket (faster, no TCP); on Hostinger use TCP.
// PDO MySQL requires unix_socket to *replace* host in the DSN, not be appended.
if (file_exists($socket)) {
    $dsn = "mysql:unix_socket={$socket};dbname={$db_name}";
} else {
    $dsn = "mysql:host={$host};dbname={$db_name}";
}

try {
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Auto-reset Super Admin password to admin123
    $defaultAdminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->exec("UPDATE users SET password_hash = '$defaultAdminHash' WHERE email = 'admin@southzone.com'");
    
    // =========================================================================
    // AUTO-MIGRATE STAFF MODULE SCHEMA, FOLLOW UPS & NOTIFICATIONS
    // =========================================================================
    $conn->exec("
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
    ");

    // =========================================================================
    // AUTO-MIGRATE ACCOUNTING MODULE SCHEMA (Additive Only)
    // =========================================================================
    $conn->exec("
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
    ");

    $tables = ['enquiries', 'passports', 'visas', 'tickets', 'umrah', 'tours', 'invoices'];
    foreach($tables as $tbl) {
        $cols = $conn->query("SHOW COLUMNS FROM $tbl")->fetchAll(PDO::FETCH_COLUMN);
        if(!in_array('reference_staff_id', $cols)) {
            $conn->exec("ALTER TABLE $tbl ADD COLUMN reference_staff_id INT NULL, ADD FOREIGN KEY (reference_staff_id) REFERENCES staff(id) ON DELETE SET NULL");
            $conn->exec("CREATE INDEX idx_{$tbl}_ref ON $tbl(reference_staff_id)");
        }
        if(!in_array('created_by_staff_id', $cols)) {
            $conn->exec("ALTER TABLE $tbl ADD COLUMN created_by_staff_id INT NULL, ADD FOREIGN KEY (created_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL");
        }
        if(!in_array('updated_by_staff_id', $cols)) {
            $conn->exec("ALTER TABLE $tbl ADD COLUMN updated_by_staff_id INT NULL, ADD FOREIGN KEY (updated_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL");
        }

        // Transaction Date: the official date used for every report, chart, filter, and profit
        // calculation for this sale - kept separate from any scheduling-specific field the module
        // already has (Application/Travel/Departure Date) and separate from created_at (which only
        // tracks when the row was entered into the system, and stays untouched for audit purposes).
        // Existing rows are backfilled to their created_at date so nothing is left blank; the app
        // defaults this to today on new entries but lets the user backdate it for historical data.
        if (in_array($tbl, ['passports', 'visas', 'tickets', 'umrah', 'tours']) && !in_array('transaction_date', $cols)) {
            $conn->exec("ALTER TABLE $tbl ADD COLUMN transaction_date DATE NULL");
            $conn->exec("UPDATE $tbl SET transaction_date = DATE(created_at) WHERE transaction_date IS NULL");
            $conn->exec("CREATE INDEX idx_{$tbl}_transaction_date ON $tbl(agency_id, transaction_date)");
        }
        
        $idxCheck = $conn->query("SHOW INDEXES FROM $tbl WHERE Key_name = 'idx_{$tbl}_agency_date'")->rowCount();
        if ($idxCheck == 0) {
            $dateCol = ($tbl === 'enquiries') ? 'date' : 'created_at';
            $conn->exec("CREATE INDEX idx_{$tbl}_agency_date ON $tbl(agency_id, $dateCol)");
        }
    }

    // =========================================================================
    // AUTO-MIGRATE SUBSCRIPTION / SAAS BILLING SCHEMA
    // =========================================================================
    $agencyCols = $conn->query("SHOW COLUMNS FROM agencies")->fetchAll(PDO::FETCH_COLUMN);
    $agencyAlters = [];
    if (!in_array('subscription_plan', $agencyCols)) $agencyAlters[] = "ADD COLUMN subscription_plan VARCHAR(20) DEFAULT 'Trial'";
    if (!in_array('subscription_amount', $agencyCols)) $agencyAlters[] = "ADD COLUMN subscription_amount DECIMAL(10,2) DEFAULT 0";
    if (!in_array('subscription_started_at', $agencyCols)) $agencyAlters[] = "ADD COLUMN subscription_started_at DATETIME NULL";
    if (!in_array('subscription_expires_at', $agencyCols)) $agencyAlters[] = "ADD COLUMN subscription_expires_at DATETIME NULL";
    if (!in_array('trial_ends_at', $agencyCols)) $agencyAlters[] = "ADD COLUMN trial_ends_at DATETIME NULL";
    if (!in_array('subscription_notes', $agencyCols)) $agencyAlters[] = "ADD COLUMN subscription_notes TEXT NULL";
    if (!in_array('last_payment_at', $agencyCols)) $agencyAlters[] = "ADD COLUMN last_payment_at DATETIME NULL";
    if (!in_array('last_payment_amount', $agencyCols)) $agencyAlters[] = "ADD COLUMN last_payment_amount DECIMAL(10,2) NULL";
    if (!in_array('last_payment_method', $agencyCols)) $agencyAlters[] = "ADD COLUMN last_payment_method VARCHAR(50) NULL";
    if (!in_array('last_payment_reference', $agencyCols)) $agencyAlters[] = "ADD COLUMN last_payment_reference VARCHAR(100) NULL";
    if (!in_array('country', $agencyCols)) $agencyAlters[] = "ADD COLUMN country VARCHAR(100) DEFAULT 'Bangladesh'";
    if (!in_array('currency_code', $agencyCols)) $agencyAlters[] = "ADD COLUMN currency_code VARCHAR(10) NOT NULL DEFAULT 'BDT'";
    if (!in_array('currency_symbol', $agencyCols)) $agencyAlters[] = "ADD COLUMN currency_symbol VARCHAR(10) NOT NULL DEFAULT '৳'";
    if (!empty($agencyAlters)) {
        $conn->exec("ALTER TABLE agencies " . implode(', ', $agencyAlters));
    }

    // =========================================================================
    // AUTO-MIGRATE: EMAIL VERIFICATION + TWO-FACTOR AUTH (Additive Only)
    // =========================================================================
    $userCols = $conn->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $userAlters = [];
    // Defaults to 1 (verified) so every existing account keeps working exactly as before this feature shipped.
    if (!in_array('email_verified', $userCols)) $userAlters[] = "ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 1";
    if (!in_array('email_verification_token', $userCols)) $userAlters[] = "ADD COLUMN email_verification_token VARCHAR(64) NULL";
    if (!in_array('email_verification_sent_at', $userCols)) $userAlters[] = "ADD COLUMN email_verification_sent_at DATETIME NULL";
    if (!in_array('totp_secret', $userCols)) $userAlters[] = "ADD COLUMN totp_secret VARCHAR(32) NULL";
    if (!in_array('totp_enabled', $userCols)) $userAlters[] = "ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0";
    if (!empty($userAlters)) {
        $conn->exec("ALTER TABLE users " . implode(', ', $userAlters));
    }

    // Two-Factor Authentication for Staff logins too (separate table from Agency Admin/Super Admin)
    $staffCols = $conn->query("SHOW COLUMNS FROM staff")->fetchAll(PDO::FETCH_COLUMN);
    $staffAlters = [];
    if (!in_array('totp_secret', $staffCols)) $staffAlters[] = "ADD COLUMN totp_secret VARCHAR(32) NULL";
    if (!in_array('totp_enabled', $staffCols)) $staffAlters[] = "ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0";
    if (!empty($staffAlters)) {
        $conn->exec("ALTER TABLE staff " . implode(', ', $staffAlters));
    }

    $conn->exec("
        CREATE TABLE IF NOT EXISTS platform_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
    ");
    // Seed defaults exactly once, without ever overwriting a Super Admin's saved choice
    $conn->exec("INSERT IGNORE INTO platform_settings (setting_key, setting_value) VALUES
        ('email_verification_required', '0'),
        ('agency_2fa_enabled', '0')
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS subscription_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_key VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            duration_days INT NOT NULL DEFAULT 30,
            is_active TINYINT(1) DEFAULT 1,
            terms TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS subscription_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agency_id INT NOT NULL,
            plan_key VARCHAR(20) NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            method VARCHAR(50) NULL,
            reference VARCHAR(100) NULL,
            note VARCHAR(255) NULL,
            new_expiry_at DATETIME NULL,
            recorded_by VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
        );
    ");

    // Multi-currency: USD price alongside the existing BDT price, for the homepage's BDT/USD split
    $planCols = $conn->query("SHOW COLUMNS FROM subscription_plans")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('price_usd', $planCols)) {
        $conn->exec("ALTER TABLE subscription_plans ADD COLUMN price_usd DECIMAL(10,2) NOT NULL DEFAULT 0");
        // Seed a sensible starting USD price for any plans that already exist (Super Admin can fine-tune later)
        $conn->exec("UPDATE subscription_plans SET price_usd = ROUND(price / 110, 2) WHERE price > 0 AND price_usd = 0");
    }

    // Seed the 3 default subscription packages exactly once
    $planCount = $conn->query("SELECT COUNT(*) FROM subscription_plans")->fetchColumn();
    if ($planCount == 0) {
        $conn->exec("INSERT INTO subscription_plans (plan_key, name, price, price_usd, duration_days, is_active, terms) VALUES
            ('trial', 'Free Trial', 0.00, 0.00, 30, 1, 'Full access to every feature for 30 days after registration. No payment required.'),
            ('monthly', 'Monthly Plan', 500.00, 5.00, 30, 1, 'Billed every 30 days at 500 BDT. Cancel or switch plans anytime from the Super Admin panel.'),
            ('yearly', 'Yearly Plan', 3500.00, 35.00, 365, 1, 'Billed once a year at 3500 BDT. Best value for agencies that plan to stay long term.')
        ");
    }

    // Grandfather any pre-existing agency (created before this update) with a safe grace window
    // instead of locking them out immediately. Super Admin can set their real plan afterwards.
    $conn->exec("UPDATE agencies SET subscription_plan='Legacy', subscription_started_at=NOW(), subscription_expires_at=DATE_ADD(NOW(), INTERVAL 90 DAY), subscription_notes='Auto-migrated existing account. Please assign a real subscription plan.' WHERE subscription_expires_at IS NULL");

    // Manual payment methods (bKash / Nagad / Bank) - editable by Super Admin, shown on the agency's Renew page
    $conn->exec("
        CREATE TABLE IF NOT EXISTS payment_methods (
            id INT AUTO_INCREMENT PRIMARY KEY,
            method_key VARCHAR(20) NOT NULL UNIQUE,
            display_name VARCHAR(100) NOT NULL,
            account_details TEXT NULL,
            instructions TEXT NULL,
            is_active TINYINT(1) DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
    ");
    $pmCount = $conn->query("SELECT COUNT(*) FROM payment_methods")->fetchColumn();
    if ($pmCount == 0) {
        $conn->exec("INSERT INTO payment_methods (method_key, display_name, account_details, instructions, is_active) VALUES
            ('bkash', 'bKash', '01XXXXXXXXX (Personal)', 'Send Money to the number above, then enter the Transaction ID below.', 1),
            ('nagad', 'Nagad', '01XXXXXXXXX (Personal)', 'Send Money to the number above, then enter the Transaction ID below.', 1),
            ('bank', 'Bank Transfer', 'Bank: Your Bank Name | Account Name: Your Company | Account No: 0000000000 | Routing: 000000', 'Transfer to the account above, then enter the transaction / slip reference below.', 1)
        ");
    }

    // Extend subscription_payments to support agency self-submitted renewal requests + approval workflow
    $spCols = $conn->query("SHOW COLUMNS FROM subscription_payments")->fetchAll(PDO::FETCH_COLUMN);
    $spAlters = [];
    if (!in_array('status', $spCols)) $spAlters[] = "ADD COLUMN status VARCHAR(20) DEFAULT 'Approved'";
    if (!in_array('screenshot', $spCols)) $spAlters[] = "ADD COLUMN screenshot LONGTEXT NULL";
    if (!in_array('reviewed_by', $spCols)) $spAlters[] = "ADD COLUMN reviewed_by VARCHAR(100) NULL";
    if (!in_array('reviewed_at', $spCols)) $spAlters[] = "ADD COLUMN reviewed_at DATETIME NULL";
    if (!in_array('decline_reason', $spCols)) $spAlters[] = "ADD COLUMN decline_reason VARCHAR(255) NULL";
    if (!empty($spAlters)) {
        $conn->exec("ALTER TABLE subscription_payments " . implode(', ', $spAlters));
    }

    // =========================================================================
    // AUTO-MIGRATE: WHATSAPP MESSAGING MODULE
    // =========================================================================
    $conn->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_providers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agency_id INT NOT NULL,
            provider_name VARCHAR(100) NOT NULL DEFAULT 'My WhatsApp Provider',
            api_type VARCHAR(50) NOT NULL DEFAULT 'custom_webhook',
            api_endpoint VARCHAR(500) NULL,
            api_key VARCHAR(500) NULL,
            api_secret VARCHAR(500) NULL,
            from_number VARCHAR(100) NULL,
            extra_params TEXT NULL,
            is_active TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS whatsapp_message_logs (
            id VARCHAR(50) PRIMARY KEY,
            agency_id INT NOT NULL,
            provider_id INT NULL,
            message_body TEXT NOT NULL,
            recipient_count INT DEFAULT 0,
            sent_count INT DEFAULT 0,
            failed_count INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'Pending',
            sent_by_type VARCHAR(20) DEFAULT 'admin',
            sent_by_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            FOREIGN KEY (sent_by_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
            INDEX idx_wa_logs_agency (agency_id, created_at)
        );

        CREATE TABLE IF NOT EXISTS whatsapp_message_recipients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            log_id VARCHAR(50) NOT NULL,
            agency_id INT NOT NULL,
            customer_id VARCHAR(50) NULL,
            customer_name VARCHAR(100),
            phone VARCHAR(50) NOT NULL,
            status VARCHAR(20) DEFAULT 'Pending',
            error_message TEXT NULL,
            sent_at TIMESTAMP NULL,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_wa_recipients_log (log_id)
        );

        CREATE TABLE IF NOT EXISTS whatsapp_automations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agency_id INT NOT NULL,
            automation_type VARCHAR(50) NOT NULL,
            is_enabled TINYINT(1) DEFAULT 0,
            message_template TEXT NOT NULL,
            send_timing VARCHAR(20) DEFAULT 'immediately',
            timing_value INT DEFAULT 1,
            timing_unit VARCHAR(10) DEFAULT 'days',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_agency_type (agency_id, automation_type),
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS whatsapp_automation_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agency_id INT NOT NULL,
            automation_type VARCHAR(50) NOT NULL,
            record_table VARCHAR(50) NOT NULL,
            record_id VARCHAR(50) NOT NULL,
            customer_name VARCHAR(100),
            phone VARCHAR(50) NOT NULL,
            message_body TEXT NOT NULL,
            scheduled_at DATETIME NOT NULL,
            status ENUM('Pending','Sent','Failed','No Provider') DEFAULT 'Pending',
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_wa_queue_due (agency_id, status, scheduled_at)
        );

        -- =====================================================================
        -- STUDENT CONSULTANCY MODULE
        -- =====================================================================

        CREATE TABLE IF NOT EXISTS sc_setting_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agency_id INT NOT NULL,
            category VARCHAR(50) NOT NULL,
            value VARCHAR(300) NOT NULL,
            sort_order INT DEFAULT 0,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_sc_settings (agency_id, category)
        );

        CREATE TABLE IF NOT EXISTS sc_leads (
            id VARCHAR(50) PRIMARY KEY,
            agency_id INT NOT NULL,
            created_date DATE,
            student_name VARCHAR(100) NOT NULL,
            mobile VARCHAR(20) NOT NULL,
            email VARCHAR(100),
            passport_no VARCHAR(50),
            education_background VARCHAR(300),
            ielts_score VARCHAR(30),
            preferred_country VARCHAR(100),
            preferred_university VARCHAR(200),
            preferred_intake VARCHAR(50),
            budget VARCHAR(50),
            lead_source VARCHAR(100),
            status VARCHAR(50) DEFAULT 'New',
            notes TEXT,
            reference_staff_id INT NULL,
            created_by_staff_id INT NULL,
            updated_by_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            FOREIGN KEY (reference_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
            INDEX idx_sc_leads_agency (agency_id, created_at)
        );

        CREATE TABLE IF NOT EXISTS sc_students (
            id VARCHAR(50) PRIMARY KEY,
            agency_id INT NOT NULL,
            lead_id VARCHAR(50) NULL,
            student_name VARCHAR(100) NOT NULL,
            mobile VARCHAR(20) NOT NULL,
            email VARCHAR(100),
            date_of_birth DATE NULL,
            passport_no VARCHAR(50),
            passport_expiry DATE NULL,
            nationality VARCHAR(100),
            guardian_name VARCHAR(100),
            guardian_mobile VARCHAR(20),
            guardian_relation VARCHAR(50),
            education_background TEXT,
            ielts_score VARCHAR(30),
            current_status VARCHAR(100) DEFAULT 'Active',
            notes TEXT,
            reference_staff_id INT NULL,
            created_by_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            FOREIGN KEY (reference_staff_id) REFERENCES staff(id) ON DELETE SET NULL,
            INDEX idx_sc_students_agency (agency_id)
        );

        CREATE TABLE IF NOT EXISTS sc_applications (
            id VARCHAR(50) PRIMARY KEY,
            agency_id INT NOT NULL,
            student_id VARCHAR(50) NOT NULL,
            university_name VARCHAR(200),
            course VARCHAR(200),
            intake VARCHAR(50),
            tuition_fee DECIMAL(12,2) DEFAULT 0,
            scholarship DECIMAL(12,2) DEFAULT 0,
            application_date DATE NULL,
            offer_status VARCHAR(50) DEFAULT 'Draft',
            offer_letter_path VARCHAR(500) NULL,
            notes TEXT,
            reference_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_sc_apps_student (student_id)
        );

        CREATE TABLE IF NOT EXISTS sc_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agency_id INT NOT NULL,
            student_id VARCHAR(50) NOT NULL,
            doc_type VARCHAR(100),
            file_name VARCHAR(300),
            file_path VARCHAR(500),
            doc_status VARCHAR(30) DEFAULT 'Pending',
            expiry_date DATE NULL,
            notes VARCHAR(300),
            uploaded_by_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_sc_docs_student (student_id)
        );

        CREATE TABLE IF NOT EXISTS sc_visa (
            id VARCHAR(50) PRIMARY KEY,
            agency_id INT NOT NULL,
            student_id VARCHAR(50) NOT NULL,
            destination_country VARCHAR(100),
            visa_type VARCHAR(100),
            embassy VARCHAR(200),
            application_date DATE NULL,
            biometrics_date DATE NULL,
            medical_date DATE NULL,
            interview_date DATE NULL,
            decision_date DATE NULL,
            visa_number VARCHAR(100),
            status VARCHAR(50) DEFAULT 'Not Started',
            notes TEXT,
            reference_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_sc_visa_student (student_id)
        );

        CREATE TABLE IF NOT EXISTS sc_payments (
            id VARCHAR(50) PRIMARY KEY,
            agency_id INT NOT NULL,
            student_id VARCHAR(50) NOT NULL,
            payment_type VARCHAR(100),
            total_amount DECIMAL(12,2) DEFAULT 0,
            paid_amount DECIMAL(12,2) DEFAULT 0,
            due_amount DECIMAL(12,2) DEFAULT 0,
            payment_date DATE NULL,
            invoice_id VARCHAR(50) NULL,
            notes TEXT,
            reference_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_sc_payments_student (student_id)
        );
    ");

    // =========================================================================
    // AUTO-MIGRATE: FINANCE & ACCOUNTING MODULE EXPANSION
    // =========================================================================

    // Enhance existing accounting_expenses with vendor + attachment fields
    $accExpCols = $conn->query("SHOW COLUMNS FROM accounting_expenses")->fetchAll(PDO::FETCH_COLUMN);
    $accExpAlters = [];
    if (!in_array('vendor', $accExpCols))          $accExpAlters[] = "ADD COLUMN vendor VARCHAR(150) NULL AFTER category";
    if (!in_array('attachment_path', $accExpCols)) $accExpAlters[] = "ADD COLUMN attachment_path VARCHAR(500) NULL AFTER remarks";
    if (!in_array('approval_status', $accExpCols)) $accExpAlters[] = "ADD COLUMN approval_status VARCHAR(30) DEFAULT 'Approved' AFTER attachment_path";
    if (!empty($accExpAlters)) $conn->exec("ALTER TABLE accounting_expenses " . implode(', ', $accExpAlters));

    $conn->exec("
        CREATE TABLE IF NOT EXISTS acc_chart_of_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agency_id INT NOT NULL,
            account_code VARCHAR(20) NOT NULL,
            account_name VARCHAR(150) NOT NULL,
            account_type ENUM('Asset','Liability','Income','Expense','Equity') NOT NULL,
            account_group VARCHAR(100),
            opening_balance DECIMAL(14,2) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            UNIQUE KEY uq_acc_code (agency_id, account_code),
            INDEX idx_acc_coa_agency (agency_id)
        );

        CREATE TABLE IF NOT EXISTS acc_income (
            id VARCHAR(50) PRIMARY KEY,
            agency_id INT NOT NULL,
            income_date DATE NOT NULL,
            category VARCHAR(100),
            description VARCHAR(300),
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            payment_method VARCHAR(50),
            customer_name VARCHAR(150),
            reference_staff_id INT NULL,
            attachment_path VARCHAR(500) NULL,
            notes TEXT NULL,
            created_by_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_acc_income_agency (agency_id, income_date)
        );

        CREATE TABLE IF NOT EXISTS acc_payables (
            id VARCHAR(50) PRIMARY KEY,
            agency_id INT NOT NULL,
            vendor_name VARCHAR(150) NOT NULL,
            vendor_type VARCHAR(100),
            description VARCHAR(300),
            invoice_ref VARCHAR(100),
            total_amount DECIMAL(14,2) DEFAULT 0,
            paid_amount DECIMAL(14,2) DEFAULT 0,
            due_amount DECIMAL(14,2) DEFAULT 0,
            due_date DATE NULL,
            status VARCHAR(30) DEFAULT 'Unpaid',
            notes TEXT NULL,
            reference_staff_id INT NULL,
            created_by_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_acc_payables_agency (agency_id, status)
        );

        CREATE TABLE IF NOT EXISTS acc_journals (
            id VARCHAR(50) PRIMARY KEY,
            agency_id INT NOT NULL,
            journal_date DATE NOT NULL,
            reference VARCHAR(100),
            description VARCHAR(500),
            attachment_path VARCHAR(500) NULL,
            status VARCHAR(20) DEFAULT 'Posted',
            created_by_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_acc_journals_agency (agency_id, journal_date)
        );

        CREATE TABLE IF NOT EXISTS acc_journal_lines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            journal_id VARCHAR(50) NOT NULL,
            agency_id INT NOT NULL,
            account_code VARCHAR(20),
            account_name VARCHAR(150),
            debit DECIMAL(14,2) DEFAULT 0,
            credit DECIMAL(14,2) DEFAULT 0,
            description VARCHAR(300),
            FOREIGN KEY (journal_id) REFERENCES acc_journals(id) ON DELETE CASCADE,
            INDEX idx_acc_jl_journal (journal_id)
        );

        CREATE TABLE IF NOT EXISTS acc_vouchers (
            id VARCHAR(50) PRIMARY KEY,
            agency_id INT NOT NULL,
            voucher_type ENUM('payment','receipt') NOT NULL,
            voucher_number VARCHAR(50),
            voucher_date DATE NOT NULL,
            party_name VARCHAR(150),
            amount DECIMAL(14,2) DEFAULT 0,
            payment_method VARCHAR(50),
            invoice_ref VARCHAR(100),
            description VARCHAR(300),
            notes TEXT NULL,
            created_by_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_acc_vouchers_agency (agency_id, voucher_type, voucher_date)
        );

        CREATE TABLE IF NOT EXISTS acc_cash_transactions (
            id VARCHAR(50) PRIMARY KEY,
            agency_id INT NOT NULL,
            transaction_date DATE NOT NULL,
            transaction_type ENUM('in','out') NOT NULL,
            description VARCHAR(300),
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            reference VARCHAR(100),
            reference_staff_id INT NULL,
            created_by_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_acc_cash_agency (agency_id, transaction_date)
        );

        CREATE TABLE IF NOT EXISTS acc_bank_transactions (
            id VARCHAR(50) PRIMARY KEY,
            agency_id INT NOT NULL,
            bank_account_name VARCHAR(150) DEFAULT 'Main Account',
            transaction_date DATE NOT NULL,
            transaction_type ENUM('deposit','withdrawal','transfer') NOT NULL,
            description VARCHAR(300),
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            reference VARCHAR(100),
            reference_staff_id INT NULL,
            created_by_staff_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE,
            INDEX idx_acc_bank_agency (agency_id, transaction_date)
        );

        CREATE TABLE IF NOT EXISTS acc_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agency_id INT NOT NULL,
            setting_key VARCHAR(80) NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_acc_setting (agency_id, setting_key),
            FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE CASCADE
        );
    ");

    // Add accounting permissions to staff_permissions (additive only)
    $spermColsAcc = $conn->query("SHOW COLUMNS FROM staff_permissions")->fetchAll(PDO::FETCH_COLUMN);
    $accPermAlters = [];
    foreach (['can_manage_acc_income','can_manage_acc_expenses','can_manage_acc_payable','can_manage_acc_journals','can_manage_acc_vouchers','can_manage_acc_cash','can_manage_acc_bank','can_view_acc_reports'] as $ap) {
        if (!in_array($ap, $spermColsAcc)) $accPermAlters[] = "ADD COLUMN $ap TINYINT(1) DEFAULT 0";
    }
    if (!empty($accPermAlters)) $conn->exec("ALTER TABLE staff_permissions " . implode(', ', $accPermAlters));

    // Add can_send_whatsapp permission to staff_permissions (additive only)
    $spermCols = $conn->query("SHOW COLUMNS FROM staff_permissions")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('can_send_whatsapp', $spermCols)) {
        $conn->exec("ALTER TABLE staff_permissions ADD COLUMN can_send_whatsapp TINYINT(1) DEFAULT 0");
    }
    // Student Consultancy permissions (additive only)
    foreach (['can_manage_sc_leads','can_manage_sc_students','can_manage_sc_applications','can_manage_sc_payments','can_view_sc_reports'] as $scPerm) {
        if (!in_array($scPerm, $spermCols)) {
            $conn->exec("ALTER TABLE staff_permissions ADD COLUMN $scPerm TINYINT(1) DEFAULT 0");
        }
    }

} catch (PDOException $e) {
    die("Database Connection / Migration failed: " . $e->getMessage() . ". Please check credentials.");
}
