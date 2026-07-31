<?php
// =========================================================================
// FINANCE & ACCOUNTING MODULE — ACTION HANDLERS
// Included at the bottom of actions_agency.php via require_once.
// All actions are additive and do not modify any existing tables/logic.
// =========================================================================

function acc_guard($conn) {
    if (!isset($_SESSION['agency_id'])) { http_response_code(403); die("Unauthorised."); }
    if (isAgencySubscriptionExpired($conn, $_SESSION['agency_id'])) {
        flash("Subscription expired.", "error"); redirect("/app/dashboard");
    }
}

// ── CHART OF ACCOUNTS ─────────────────────────────────────────────────────
if ($action === 'save_acc_account' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    acc_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $id        = (int)($_POST['id'] ?? 0);
    $code      = strtoupper(trim($_POST['account_code'] ?? ''));
    $name      = trim($_POST['account_name'] ?? '');
    $type      = trim($_POST['account_type'] ?? '');
    $group     = trim($_POST['account_group'] ?? '');
    $opening   = (float)($_POST['opening_balance'] ?? 0);
    $allowed_types = ['Asset','Liability','Income','Expense','Equity'];
    if (empty($code) || empty($name) || !in_array($type, $allowed_types)) {
        flash("Invalid account data.", "error"); redirect("/app/acc_chart_of_accounts");
    }
    if ($id) {
        $conn->prepare("UPDATE acc_chart_of_accounts SET account_code=?,account_name=?,account_type=?,account_group=?,opening_balance=? WHERE id=? AND agency_id=?")
             ->execute([$code,$name,$type,$group,$opening,$id,$agency_id]);
        flash("Account updated.");
    } else {
        $conn->prepare("INSERT INTO acc_chart_of_accounts (agency_id,account_code,account_name,account_type,account_group,opening_balance) VALUES (?,?,?,?,?,?)")
             ->execute([$agency_id,$code,$name,$type,$group,$opening]);
        flash("Account added.");
    }
    redirect("/app/acc_chart_of_accounts");
}

if ($action === 'toggle_acc_account' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    acc_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $id = (int)($_POST['id'] ?? 0);
    $conn->prepare("UPDATE acc_chart_of_accounts SET is_active = 1 - is_active WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Account status toggled.");
    redirect("/app/acc_chart_of_accounts");
}

// ── MANUAL INCOME ─────────────────────────────────────────────────────────
if ($action === 'save_acc_income' && isset($_SESSION['agency_id'])) {
    acc_guard($conn);
    if (!has_permission('can_manage_acc_income') && $_SESSION['is_staff']) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $id        = trim($_POST['income_id'] ?? '');
    $staffId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $refId     = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : (!empty($_POST['reference_staff_id']) ? (int)$_POST['reference_staff_id'] : null);
    $fields    = ['income_date','category','description','amount','payment_method','customer_name','notes'];
    $data      = array_map(fn($f)=>trim($_POST[$f]??'')?:null, array_combine($fields,$fields));
    $data['amount'] = (float)($_POST['amount'] ?? 0);
    $attach    = null;
    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/acc_docs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $fname = 'inc_'.time().'_'.uniqid().'.'.$ext;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir.$fname)) $attach = 'uploads/acc_docs/'.$fname;
    }
    if (empty($id)) {
        $newId = generateSerialId($conn, 'acc_income', 'INC', $agency_id);
        $conn->prepare("INSERT INTO acc_income (id,agency_id,income_date,category,description,amount,payment_method,customer_name,reference_staff_id,attachment_path,notes,created_by_staff_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
             ->execute([$newId,$agency_id,$data['income_date'],$data['category'],$data['description'],$data['amount'],$data['payment_method'],$data['customer_name'],$refId,$attach,$data['notes'],$staffId]);
        flash("Income record added.");
    } else {
        $setAttach = $attach !== null ? ", attachment_path=?" : "";
        $params = [$data['income_date'],$data['category'],$data['description'],$data['amount'],$data['payment_method'],$data['customer_name'],$data['notes'],$refId];
        if ($attach !== null) $params[] = $attach;
        $params[] = $agency_id; $params[] = $id;
        $conn->prepare("UPDATE acc_income SET income_date=?,category=?,description=?,amount=?,payment_method=?,customer_name=?,notes=?,reference_staff_id=?$setAttach WHERE agency_id=? AND id=?")->execute($params);
        flash("Income updated.");
    }
    redirect("/app/acc_income");
}

if ($action === 'delete_acc_income' && isset($_SESSION['agency_id'])) {
    acc_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $id = trim($_POST['id'] ?? '');
    $conn->prepare("DELETE FROM acc_income WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Income record deleted.");
    redirect("/app/acc_income");
}

// ── ACCOUNTS PAYABLE ──────────────────────────────────────────────────────
if ($action === 'save_acc_payable' && isset($_SESSION['agency_id'])) {
    acc_guard($conn);
    if (!has_permission('can_manage_acc_payable') && $_SESSION['is_staff']) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $id        = trim($_POST['payable_id'] ?? '');
    $staffId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $total     = (float)($_POST['total_amount'] ?? 0);
    $paid      = (float)($_POST['paid_amount'] ?? 0);
    $due       = round($total - $paid, 2);
    $d = [
        'vendor_name'  => trim($_POST['vendor_name'] ?? ''),
        'vendor_type'  => trim($_POST['vendor_type'] ?? ''),
        'description'  => trim($_POST['description'] ?? ''),
        'invoice_ref'  => trim($_POST['invoice_ref'] ?? ''),
        'due_date'     => trim($_POST['due_date'] ?? '') ?: null,
        'status'       => trim($_POST['status'] ?? 'Unpaid'),
        'notes'        => trim($_POST['notes'] ?? ''),
    ];
    if (empty($id)) {
        $newId = generateSerialId($conn, 'acc_payables', 'PAY', $agency_id);
        $conn->prepare("INSERT INTO acc_payables (id,agency_id,vendor_name,vendor_type,description,invoice_ref,total_amount,paid_amount,due_amount,due_date,status,notes,created_by_staff_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
             ->execute([$newId,$agency_id,$d['vendor_name'],$d['vendor_type'],$d['description'],$d['invoice_ref'],$total,$paid,$due,$d['due_date'],$d['status'],$d['notes'],$staffId]);
        flash("Payable record added.");
    } else {
        $conn->prepare("UPDATE acc_payables SET vendor_name=?,vendor_type=?,description=?,invoice_ref=?,total_amount=?,paid_amount=?,due_amount=?,due_date=?,status=?,notes=? WHERE id=? AND agency_id=?")
             ->execute([$d['vendor_name'],$d['vendor_type'],$d['description'],$d['invoice_ref'],$total,$paid,$due,$d['due_date'],$d['status'],$d['notes'],$id,$agency_id]);
        flash("Payable updated.");
    }
    redirect("/app/acc_payable");
}

if ($action === 'delete_acc_payable' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    acc_guard($conn);
    $id = trim($_POST['id'] ?? ''); $agency_id = $_SESSION['agency_id'];
    $conn->prepare("DELETE FROM acc_payables WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Payable deleted.");
    redirect("/app/acc_payable");
}

// ── JOURNAL ENTRIES ───────────────────────────────────────────────────────
if ($action === 'save_acc_journal' && isset($_SESSION['agency_id'])) {
    acc_guard($conn);
    if (!has_permission('can_manage_acc_journals') && $_SESSION['is_staff']) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $staffId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $jDate     = trim($_POST['journal_date'] ?? date('Y-m-d'));
    $jRef      = trim($_POST['reference'] ?? '');
    $jDesc     = trim($_POST['description'] ?? '');
    $attach    = null;
    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/acc_docs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $fname = 'je_'.time().'_'.uniqid().'.'.$ext;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir.$fname)) $attach = 'uploads/acc_docs/'.$fname;
    }
    // Lines
    $lineAcc   = $_POST['line_account'] ?? [];
    $lineDebit = $_POST['line_debit'] ?? [];
    $lineCredit= $_POST['line_credit'] ?? [];
    $lineDesc  = $_POST['line_desc'] ?? [];
    if (count($lineAcc) < 2) { flash("Journal requires at least 2 lines.", "error"); redirect("/app/acc_journals"); }
    $totalDebit = array_sum(array_map('floatval', $lineDebit));
    $totalCredit= array_sum(array_map('floatval', $lineCredit));
    if (round($totalDebit,2) !== round($totalCredit,2)) {
        flash("Journal entry does not balance. Debits: ".number_format($totalDebit,2)." Credits: ".number_format($totalCredit,2), "error");
        redirect("/app/acc_journals");
    }
    $jId = generateSerialId($conn, 'acc_journals', 'JE', $agency_id);
    $conn->prepare("INSERT INTO acc_journals (id,agency_id,journal_date,reference,description,attachment_path,status,created_by_staff_id) VALUES (?,?,?,?,?,?,?,?)")
         ->execute([$jId,$agency_id,$jDate,$jRef,$jDesc,$attach,'Posted',$staffId]);
    foreach ($lineAcc as $i => $accName) {
        $debit  = (float)($lineDebit[$i] ?? 0);
        $credit = (float)($lineCredit[$i] ?? 0);
        if ($debit == 0 && $credit == 0) continue;
        $parts  = explode('|', $accName, 2);
        $aCode  = trim($parts[0] ?? '');
        $aName  = trim($parts[1] ?? $accName);
        $conn->prepare("INSERT INTO acc_journal_lines (journal_id,agency_id,account_code,account_name,debit,credit,description) VALUES (?,?,?,?,?,?,?)")
             ->execute([$jId,$agency_id,$aCode,$aName,$debit,$credit,trim($lineDesc[$i]??'')]);
    }
    flash("Journal entry posted: $jId");
    redirect("/app/acc_journals");
}

if ($action === 'delete_acc_journal' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    acc_guard($conn);
    $id = trim($_POST['id'] ?? ''); $agency_id = $_SESSION['agency_id'];
    $conn->prepare("DELETE FROM acc_journal_lines WHERE journal_id=? AND agency_id=?")->execute([$id,$agency_id]);
    $conn->prepare("DELETE FROM acc_journals WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Journal entry deleted.");
    redirect("/app/acc_journals");
}

// ── VOUCHERS (Payment / Receipt) ──────────────────────────────────────────
if ($action === 'save_acc_voucher' && isset($_SESSION['agency_id'])) {
    acc_guard($conn);
    if (!has_permission('can_manage_acc_vouchers') && $_SESSION['is_staff']) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $staffId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $vtype     = ($_POST['voucher_type'] ?? 'payment') === 'receipt' ? 'receipt' : 'payment';
    $prefix    = $vtype === 'payment' ? 'PV' : 'RV';
    $newId     = generateSerialId($conn, 'acc_vouchers', $prefix, $agency_id);
    // auto-generate voucher number
    $vNum = $newId;
    $conn->prepare("INSERT INTO acc_vouchers (id,agency_id,voucher_type,voucher_number,voucher_date,party_name,amount,payment_method,invoice_ref,description,notes,created_by_staff_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
         ->execute([$newId,$agency_id,$vtype,$vNum,
            trim($_POST['voucher_date']??date('Y-m-d')),trim($_POST['party_name']??''),
            (float)($_POST['amount']??0),trim($_POST['payment_method']??''),
            trim($_POST['invoice_ref']??''),trim($_POST['description']??''),
            trim($_POST['notes']??''),$staffId]);
    flash(ucfirst($vtype)." voucher created: $newId");
    $page = $vtype === 'payment' ? 'acc_payment_vouchers' : 'acc_receipt_vouchers';
    redirect("/app/$page");
}

if ($action === 'delete_acc_voucher' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    acc_guard($conn);
    $id = trim($_POST['id'] ?? ''); $agency_id = $_SESSION['agency_id'];
    $row = $conn->prepare("SELECT voucher_type FROM acc_vouchers WHERE id=? AND agency_id=?");
    $row->execute([$id,$agency_id]); $vt = $row->fetchColumn();
    $conn->prepare("DELETE FROM acc_vouchers WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Voucher deleted.");
    redirect("/app/".($vt==='payment'?'acc_payment_vouchers':'acc_receipt_vouchers'));
}

// ── CASH BOOK ─────────────────────────────────────────────────────────────
if ($action === 'save_cash_transaction' && isset($_SESSION['agency_id'])) {
    acc_guard($conn);
    if (!has_permission('can_manage_acc_cash') && $_SESSION['is_staff']) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $staffId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $id        = trim($_POST['tx_id'] ?? '');
    $type      = ($_POST['transaction_type'] ?? 'in') === 'out' ? 'out' : 'in';
    if (empty($id)) {
        $newId = generateSerialId($conn, 'acc_cash_transactions', 'CT', $agency_id);
        $conn->prepare("INSERT INTO acc_cash_transactions (id,agency_id,transaction_date,transaction_type,description,amount,reference,created_by_staff_id) VALUES (?,?,?,?,?,?,?,?)")
             ->execute([$newId,$agency_id,trim($_POST['transaction_date']??date('Y-m-d')),$type,trim($_POST['description']??''),(float)$_POST['amount'],trim($_POST['reference']??''),$staffId]);
        flash("Cash transaction added.");
    } else {
        $conn->prepare("UPDATE acc_cash_transactions SET transaction_date=?,transaction_type=?,description=?,amount=?,reference=? WHERE id=? AND agency_id=?")
             ->execute([trim($_POST['transaction_date']??date('Y-m-d')),$type,trim($_POST['description']??''),(float)$_POST['amount'],trim($_POST['reference']??''),$id,$agency_id]);
        flash("Cash transaction updated.");
    }
    redirect("/app/acc_cash_book");
}

if ($action === 'delete_cash_transaction' && isset($_SESSION['agency_id'])) {
    acc_guard($conn);
    $id = trim($_POST['id'] ?? ''); $agency_id = $_SESSION['agency_id'];
    $conn->prepare("DELETE FROM acc_cash_transactions WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Transaction deleted.");
    redirect("/app/acc_cash_book");
}

// ── BANK BOOK ─────────────────────────────────────────────────────────────
if ($action === 'save_bank_transaction' && isset($_SESSION['agency_id'])) {
    acc_guard($conn);
    if (!has_permission('can_manage_acc_bank') && $_SESSION['is_staff']) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $staffId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $id        = trim($_POST['tx_id'] ?? '');
    $type_raw  = $_POST['transaction_type'] ?? 'deposit';
    $type      = in_array($type_raw,['deposit','withdrawal','transfer']) ? $type_raw : 'deposit';
    if (empty($id)) {
        $newId = generateSerialId($conn, 'acc_bank_transactions', 'BT', $agency_id);
        $conn->prepare("INSERT INTO acc_bank_transactions (id,agency_id,bank_account_name,transaction_date,transaction_type,description,amount,reference,created_by_staff_id) VALUES (?,?,?,?,?,?,?,?,?)")
             ->execute([$newId,$agency_id,trim($_POST['bank_account_name']??'Main Account'),trim($_POST['transaction_date']??date('Y-m-d')),$type,trim($_POST['description']??''),(float)$_POST['amount'],trim($_POST['reference']??''),$staffId]);
        flash("Bank transaction added.");
    } else {
        $conn->prepare("UPDATE acc_bank_transactions SET bank_account_name=?,transaction_date=?,transaction_type=?,description=?,amount=?,reference=? WHERE id=? AND agency_id=?")
             ->execute([trim($_POST['bank_account_name']??'Main Account'),trim($_POST['transaction_date']??date('Y-m-d')),$type,trim($_POST['description']??''),(float)$_POST['amount'],trim($_POST['reference']??''),$id,$agency_id]);
        flash("Bank transaction updated.");
    }
    redirect("/app/acc_bank_book");
}

if ($action === 'delete_bank_transaction' && isset($_SESSION['agency_id'])) {
    acc_guard($conn);
    $id = trim($_POST['id'] ?? ''); $agency_id = $_SESSION['agency_id'];
    $conn->prepare("DELETE FROM acc_bank_transactions WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Transaction deleted.");
    redirect("/app/acc_bank_book");
}

// ── SETTINGS ─────────────────────────────────────────────────────────────
if ($action === 'save_acc_setting' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    acc_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $key = trim($_POST['setting_key'] ?? '');
    $val = trim($_POST['setting_value'] ?? '');
    if (!empty($key)) {
        $conn->prepare("INSERT INTO acc_settings (agency_id,setting_key,setting_value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=?")
             ->execute([$agency_id,$key,$val,$val]);
        flash("Setting saved.");
    }
    redirect("/app/acc_settings");
}

if ($action === 'save_acc_settings_bulk' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    acc_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $keys = ['income_categories','expense_categories','payment_methods','vat_rate','vat_label','voucher_prefix_payment','voucher_prefix_receipt','fiscal_year_start','opening_cash_balance','opening_bank_balance'];
    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            $v = trim($_POST[$k]);
            $conn->prepare("INSERT INTO acc_settings (agency_id,setting_key,setting_value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$agency_id,$k,$v,$v]);
        }
    }
    flash("Settings saved.");
    redirect("/app/acc_settings");
}
