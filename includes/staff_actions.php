<?php
// Safe to require_once on GET pages — $action resolves to '' so all dispatch blocks are skipped
$action = $action ?? ($_POST['action'] ?? '');

// ── Attendance: Save (Insert or Update) ──────────────────────────────────────
if ($action === 'staff_save_attendance' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    $agency_id = $_SESSION['agency_id'];
    $id        = (int)($_POST['id']              ?? 0);
    $staff_id  = (int)($_POST['staff_id']        ?? 0);
    $att_date  = trim($_POST['attendance_date']  ?? '');
    $status    = trim($_POST['status']           ?? 'Present');
    $check_in  = trim($_POST['check_in']         ?? '') ?: null;
    $check_out = trim($_POST['check_out']        ?? '') ?: null;
    $notes     = trim($_POST['notes']            ?? '');

    $validStatuses = ['Present','Absent','Late','Leave'];
    if (!in_array($status, $validStatuses)) $status = 'Present';

    if (!$staff_id || !$att_date) {
        flash("Staff and date are required.", "error");
        redirect("?route=app&page=staff_attendance");
    }

    if ($id) {
        $conn->prepare("UPDATE staff_attendance
            SET staff_id=?, attendance_date=?, status=?, check_in=?, check_out=?, notes=?
            WHERE id=? AND agency_id=?")
             ->execute([$staff_id, $att_date, $status, $check_in, $check_out, $notes, $id, $agency_id]);
        flash("Attendance record updated.");
    } else {
        // UPSERT — one record per staff per day (unique key enforced in DB)
        $conn->prepare("INSERT INTO staff_attendance
                (agency_id, staff_id, attendance_date, status, check_in, check_out, notes)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                status=VALUES(status), check_in=VALUES(check_in),
                check_out=VALUES(check_out), notes=VALUES(notes)")
             ->execute([$agency_id, $staff_id, $att_date, $status, $check_in, $check_out, $notes]);
        flash("Attendance saved.");
    }
    redirect("?route=app&page=staff_attendance");
}

// ── Attendance: Delete ───────────────────────────────────────────────────────
if ($action === 'staff_delete_attendance' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    $agency_id = $_SESSION['agency_id'];
    $id = (int)($_POST['id'] ?? 0);
    if ($id) $conn->prepare("DELETE FROM staff_attendance WHERE id=? AND agency_id=?")->execute([$id, $agency_id]);
    flash("Attendance record deleted.");
    redirect("?route=app&page=staff_attendance");
}

// ── Salary: Save (Insert or Update) ─────────────────────────────────────────
if ($action === 'staff_save_salary' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    $agency_id  = $_SESSION['agency_id'];
    $id         = (int)($_POST['id']             ?? 0);
    $staff_id   = (int)($_POST['staff_id']       ?? 0);
    $sal_month  = trim($_POST['salary_month']    ?? '');  // format: YYYY-MM
    $basic      = abs((float)($_POST['basic_salary'] ?? 0));
    $bonus      = abs((float)($_POST['bonus']        ?? 0));
    $deduction  = abs((float)($_POST['deduction']    ?? 0));
    $commission = abs((float)($_POST['commission']   ?? 0));
    $net        = $basic + $bonus + $commission - $deduction;
    $pay_status = trim($_POST['payment_status']  ?? 'Unpaid');
    $pay_date   = trim($_POST['payment_date']    ?? '') ?: null;
    $notes      = trim($_POST['notes']           ?? '');

    if (!in_array($pay_status, ['Paid','Unpaid'])) $pay_status = 'Unpaid';
    if ($pay_status === 'Paid' && !$pay_date) $pay_date = date('Y-m-d');

    if (!$staff_id || !$sal_month) {
        flash("Staff and month are required.", "error");
        redirect("?route=app&page=staff_salary");
    }

    // Store as first-of-month date
    $sal_month_date = $sal_month . '-01';

    if ($id) {
        $conn->prepare("UPDATE staff_salary
            SET staff_id=?, salary_month=?, basic_salary=?, bonus=?, deduction=?, commission=?,
                net_salary=?, payment_status=?, payment_date=?, notes=?
            WHERE id=? AND agency_id=?")
             ->execute([$staff_id, $sal_month_date, $basic, $bonus, $deduction, $commission,
                        $net, $pay_status, $pay_date, $notes, $id, $agency_id]);
        flash("Salary record updated.");
    } else {
        $conn->prepare("INSERT INTO staff_salary
                (agency_id, staff_id, salary_month, basic_salary, bonus, deduction, commission,
                 net_salary, payment_status, payment_date, notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)")
             ->execute([$agency_id, $staff_id, $sal_month_date, $basic, $bonus, $deduction,
                        $commission, $net, $pay_status, $pay_date, $notes]);
        flash("Salary record created.");
    }
    redirect("?route=app&page=staff_salary");
}

// ── Salary: Delete ───────────────────────────────────────────────────────────
if ($action === 'staff_delete_salary' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    $agency_id = $_SESSION['agency_id'];
    $id = (int)($_POST['id'] ?? 0);
    if ($id) $conn->prepare("DELETE FROM staff_salary WHERE id=? AND agency_id=?")->execute([$id, $agency_id]);
    flash("Salary record deleted.");
    redirect("?route=app&page=staff_salary");
}

// ── Salary: Mark as Paid ─────────────────────────────────────────────────────
if ($action === 'staff_mark_salary_paid' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    $agency_id = $_SESSION['agency_id'];
    $id        = (int)($_POST['id']           ?? 0);
    $pay_date  = trim($_POST['payment_date']  ?? date('Y-m-d')) ?: date('Y-m-d');
    if ($id) {
        $conn->prepare("UPDATE staff_salary SET payment_status='Paid', payment_date=? WHERE id=? AND agency_id=?")
             ->execute([$pay_date, $id, $agency_id]);
        flash("Salary marked as paid.");
    }
    redirect("?route=app&page=staff_salary");
}
