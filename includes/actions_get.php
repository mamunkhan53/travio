<?php
// Handle GET Actions (Delete)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_SESSION['agency_id'])) {
    $table = $_GET['table'];
    $id = $_GET['id'];

    if (isAgencySubscriptionExpired($conn, $_SESSION['agency_id'])) {
        flash("Your subscription has expired. Please renew your plan to delete records.", "error");
        redirect("?route=app&page=dashboard");
    }
    
    // Strict Delete Permission Backend Checks
    if ($table === 'customers' && !has_permission('can_manage_customers')) { http_response_code(403); die("403 Access Denied: Missing permissions to delete customers."); }
    if ($table === 'enquiries' && !has_permission('can_delete_enquiry')) { http_response_code(403); die("403 Access Denied."); }
    if (in_array($table, ['passports', 'visas', 'tickets', 'umrah', 'tours', 'invoices']) && !has_permission('can_delete_sale')) { http_response_code(403); die("403 Access Denied."); }
    if ($table === 'staff' && $_SESSION['is_staff']) { http_response_code(403); die("403 Access Denied."); }

    if (array_key_exists($table, $modules)) {
        $conn->prepare("DELETE FROM $table WHERE id = ? AND agency_id = ?")->execute([$id, $_SESSION['agency_id']]);
        
        if (!in_array($table, ['customers', 'invoices', 'staff', 'enquiries'])) {
            $conn->prepare("DELETE FROM service_notifications WHERE sale_id=? AND agency_id=?")->execute([$id, $_SESSION['agency_id']]);
        }
        flash("Record deleted.");
    }
    redirect("?route=app&page=" . $table);
}

// Handle GET Action: Delete Accounting Expense (separate from the generic module CRUD delete above,
// since accounting_expenses is not a sidebar module/table in $modules)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_expense' && isset($_SESSION['agency_id'])) {
    if (isAgencySubscriptionExpired($conn, $_SESSION['agency_id'])) {
        flash("Your subscription has expired. Please renew your plan to delete expenses.", "error");
        redirect("?route=app&page=dashboard");
    }
    if (!has_permission('can_delete_expense')) { http_response_code(403); die("403 Access Denied: You do not have permission to delete expenses."); }

    $conn->prepare("DELETE FROM accounting_expenses WHERE id = ? AND agency_id = ?")->execute([$_GET['id'], $_SESSION['agency_id']]);
    flash("Expense deleted.");

    $redirectQs = !empty($_GET['redirect_qs']) ? '&' . ltrim($_GET['redirect_qs'], '&') : '';
    redirect("?route=app&page=accounting" . $redirectQs);
}

// ------------- WHATSAPP: AJAX — FETCH RECIPIENTS FOR A LOG ENTRY -------------
// Must run before any HTML output so header() works.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['wa_recipients_ajax']) && !empty($_GET['log_id']) && !empty($_SESSION['agency_id'])) {
    $logIdReq  = $_GET['log_id'];
    $agencyReq = (int)$_SESSION['agency_id'];
    $recipRows = $conn->prepare(
        "SELECT customer_name, phone, status, error_message, sent_at
         FROM whatsapp_message_recipients
         WHERE log_id = ? AND agency_id = ?
         ORDER BY id ASC"
    );
    $recipRows->execute([$logIdReq, $agencyReq]);
    $recipData = $recipRows->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($recipData);
    exit;
}

// ------------- WHATSAPP: DELETE A MESSAGE LOG (Agency Admin only) -------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_whatsapp_log' && isset($_SESSION['agency_id'])) {
    if ($_SESSION['is_staff']) { http_response_code(403); die("403 Access Denied."); }
    $logId = $_GET['id'] ?? '';
    if (!empty($logId)) {
        $conn->prepare("DELETE FROM whatsapp_message_recipients WHERE log_id = ? AND agency_id = ?")->execute([$logId, $_SESSION['agency_id']]);
        $conn->prepare("DELETE FROM whatsapp_message_logs WHERE id = ? AND agency_id = ?")->execute([$logId, $_SESSION['agency_id']]);
        flash("Message log deleted.");
    }
    redirect("?route=app&page=whatsapp&tab=history");
}

// ------------- 2FA SETUP: GENERATE A PENDING SECRET + QR CODE (JSON) -------------
// Works for Super Admin, Agency Admin, and Staff.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'begin_2fa_setup' && !empty($_SESSION['user_id'])) {
    if ($_SESSION['is_staff']) {
        $stmt = $conn->prepare("SELECT email, username FROM staff WHERE id = ?");
        $stmt->execute([$_SESSION['staff_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $accountEmail = $row ? ($row['email'] ?: $row['username']) : 'account';
    } else {
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $accountEmail = $stmt->fetchColumn() ?: 'account';
    }

    $secret = generateTotpSecret();
    $_SESSION['pending_totp_secret'] = $secret;

    header('Content-Type: application/json');
    echo json_encode([
        'secret' => $secret,
        'qr_url' => getTotpQrUrl($secret, $accountEmail),
    ]);
    exit;
}


