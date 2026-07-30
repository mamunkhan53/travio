<?php
// =========================================================================
// HAJJ & UMRAH MODULE — ACTION HANDLERS
// Included at the bottom of actions_agency.php via require_once.
// =========================================================================

// Safe to require_once on GET pages — $action resolves to '' so all dispatch blocks are skipped
$action = $action ?? ($_POST['action'] ?? '');

function umrah_guard($conn) {
    if (!isset($_SESSION['agency_id'])) { http_response_code(403); die("Unauthorised."); }
    if (isAgencySubscriptionExpired($conn, $_SESSION['agency_id'])) {
        flash("Subscription expired. Renew to use this feature.", "error");
        redirect("?route=app&page=dashboard");
    }
}

// =============================================================================
// ── PACKAGES ─────────────────────────────────────────────────────────────────
// =============================================================================
if ($action === 'umrah_save_package' && isset($_SESSION['agency_id'])) {
    umrah_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $id        = trim($_POST['id'] ?? '');
    $type      = trim($_POST['package_type']  ?? 'Umrah');
    $name      = trim($_POST['package_name']  ?? '');
    $duration  = trim($_POST['duration']      ?? '');
    $price     = is_numeric($_POST['price'] ?? '') ? (float)$_POST['price'] : 0;
    $desc      = trim($_POST['description']   ?? '');
    $status    = trim($_POST['status']        ?? 'Active');

    if (!in_array($type,   ['Hajj', 'Umrah'])) $type = 'Umrah';
    if (!in_array($status, ['Active', 'Inactive'])) $status = 'Active';
    if (!$name) { flash("Package name is required.", "error"); redirect("?route=app&page=umrah_packages"); }

    if (empty($id)) {
        $newId = generateSerialId($conn, 'umrah_packages', 'UP', $agency_id);
        $conn->prepare("INSERT INTO umrah_packages (id,agency_id,package_type,package_name,duration,price,description,status)
                        VALUES (?,?,?,?,?,?,?,?)")
             ->execute([$newId, $agency_id, $type, $name, $duration, $price, $desc, $status]);
        flash("Package added successfully.");
    } else {
        $conn->prepare("UPDATE umrah_packages SET package_type=?,package_name=?,duration=?,price=?,description=?,status=?
                        WHERE id=? AND agency_id=?")
             ->execute([$type, $name, $duration, $price, $desc, $status, $id, $agency_id]);
        flash("Package updated.");
    }
    redirect("?route=app&page=umrah_packages");
}

if ($action === 'umrah_delete_package' && isset($_SESSION['agency_id'])) {
    umrah_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $id = trim($_POST['id'] ?? '');
    if ($id) {
        $conn->prepare("DELETE FROM umrah_packages WHERE id=? AND agency_id=?")->execute([$id, $agency_id]);
        flash("Package deleted.");
    }
    redirect("?route=app&page=umrah_packages");
}

// =============================================================================
// ── BOOKINGS ─────────────────────────────────────────────────────────────────
// =============================================================================
if ($action === 'umrah_save_booking' && isset($_SESSION['agency_id'])) {
    umrah_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $id        = trim($_POST['id'] ?? '');
    $staffId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $refId     = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : (!empty($_POST['reference_staff_id']) ? (int)$_POST['reference_staff_id'] : null);

    $custId     = trim($_POST['customer_id']   ?? '') ?: null;
    $custName   = trim($_POST['customer_name'] ?? '');
    $custMobile = trim($_POST['customer_mobile'] ?? '');
    $pkgId      = trim($_POST['package_id']    ?? '') ?: null;
    $travelDate  = trim($_POST['travel_date']    ?? '') ?: null;
    $bookingDate = trim($_POST['booking_date']   ?? '') ?: date('Y-m-d');
    $pilgrims    = max(1, (int)($_POST['num_pilgrims'] ?? 1));
    $totalPrice = is_numeric($_POST['total_price'] ?? '') ? (float)$_POST['total_price'] : 0;
    $status     = trim($_POST['booking_status'] ?? 'Inquiry');
    $notes      = trim($_POST['notes'] ?? '');

    $validStatuses = ['Inquiry','Confirmed','Completed','Cancelled'];
    if (!in_array($status, $validStatuses)) $status = 'Inquiry';

    // If customer_id given but no name, look it up
    if ($custId && !$custName) {
        $row = $conn->prepare("SELECT name, mobile FROM customers WHERE id=? AND agency_id=?");
        $row->execute([$custId, $agency_id]);
        $cx = $row->fetch(PDO::FETCH_ASSOC);
        if ($cx) { $custName = $cx['name']; $custMobile = $cx['mobile']; }
    }

    if (empty($id)) {
        $newId = generateSerialId($conn, 'umrah_bookings', 'UB', $agency_id);
        $conn->prepare("INSERT INTO umrah_bookings
            (id,agency_id,customer_id,customer_name,customer_mobile,package_id,booking_date,travel_date,num_pilgrims,
             total_price,booking_status,notes,reference_staff_id,created_by_staff_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
             ->execute([$newId,$agency_id,$custId,$custName,$custMobile,$pkgId,$bookingDate,$travelDate,$pilgrims,
                        $totalPrice,$status,$notes,$refId,$staffId]);
        flash("Booking created (ID: $newId).");
    } else {
        $conn->prepare("UPDATE umrah_bookings SET
            customer_id=?,customer_name=?,customer_mobile=?,package_id=?,booking_date=?,travel_date=?,num_pilgrims=?,
            total_price=?,booking_status=?,notes=?,reference_staff_id=?
            WHERE id=? AND agency_id=?")
             ->execute([$custId,$custName,$custMobile,$pkgId,$bookingDate,$travelDate,$pilgrims,
                        $totalPrice,$status,$notes,$refId,$id,$agency_id]);
        flash("Booking updated.");
    }
    redirect("?route=app&page=umrah_bookings");
}

if ($action === 'umrah_delete_booking' && isset($_SESSION['agency_id'])) {
    umrah_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $id = trim($_POST['id'] ?? '');
    if ($id) {
        $conn->prepare("DELETE FROM umrah_payments WHERE booking_id=? AND agency_id=?")->execute([$id, $agency_id]);
        $conn->prepare("DELETE FROM umrah_bookings WHERE id=? AND agency_id=?")->execute([$id, $agency_id]);
        flash("Booking and its payments deleted.");
    }
    redirect("?route=app&page=umrah_bookings");
}

// =============================================================================
// ── PAYMENTS ─────────────────────────────────────────────────────────────────
// =============================================================================
if ($action === 'umrah_save_payment' && isset($_SESSION['agency_id'])) {
    umrah_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $staffId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;

    $bookingId = trim($_POST['booking_id'] ?? '');
    $amount    = is_numeric($_POST['amount'] ?? '') ? abs((float)$_POST['amount']) : 0;
    $date      = trim($_POST['payment_date'] ?? '') ?: date('Y-m-d');
    $method    = trim($_POST['payment_method'] ?? 'Cash');
    $notes     = trim($_POST['notes'] ?? '');

    if (!$bookingId || $amount <= 0) {
        flash("Invalid payment — booking and amount required.", "error");
        redirect("?route=app&page=umrah_payments");
    }

    // Verify booking belongs to agency
    $bk = $conn->prepare("SELECT id FROM umrah_bookings WHERE id=? AND agency_id=?");
    $bk->execute([$bookingId, $agency_id]);
    if (!$bk->fetch()) { flash("Booking not found.", "error"); redirect("?route=app&page=umrah_payments"); }

    $newId = generateSerialId($conn, 'umrah_payments', 'PY', $agency_id);
    $conn->prepare("INSERT INTO umrah_payments (id,agency_id,booking_id,amount,payment_date,payment_method,notes,created_by_staff_id)
                    VALUES (?,?,?,?,?,?,?,?)")
         ->execute([$newId, $agency_id, $bookingId, $amount, $date, $method, $notes, $staffId]);
    flash("Payment of " . number_format($amount, 2) . " recorded.");
    redirect("?route=app&page=umrah_payments&booking_id=" . urlencode($bookingId));
}

if ($action === 'umrah_delete_payment' && isset($_SESSION['agency_id'])) {
    umrah_guard($conn);
    $agency_id  = $_SESSION['agency_id'];
    $id         = trim($_POST['id'] ?? '');
    $booking_id = trim($_POST['booking_id'] ?? '');
    if ($id) {
        $conn->prepare("DELETE FROM umrah_payments WHERE id=? AND agency_id=?")->execute([$id, $agency_id]);
        flash("Payment removed.");
    }
    redirect("?route=app&page=umrah_payments" . ($booking_id ? "&booking_id=" . urlencode($booking_id) : ''));
}

// =============================================================================
// ── CHECKLIST (AJAX JSON or POST) ────────────────────────────────────────────
// =============================================================================
if ($action === 'umrah_update_checklist' && isset($_SESSION['agency_id'])) {
    umrah_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $id        = trim($_POST['id'] ?? '');
    $field     = trim($_POST['field'] ?? '');
    $val       = !empty($_POST['value']) ? 1 : 0;

    $allowed = ['passport_received','visa_completed','ticket_issued','hotel_confirmed'];
    if (!$id || !in_array($field, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit;
    }
    $conn->prepare("UPDATE umrah_bookings SET $field=? WHERE id=? AND agency_id=?")
         ->execute([$val, $id, $agency_id]);
    echo json_encode(['success' => true]); exit;
}
