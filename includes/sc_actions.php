<?php
// =========================================================================
// STUDENT CONSULTANCY MODULE — ACTION HANDLERS
// Included at the bottom of actions_agency.php via require.
// All actions are additive and do not modify any existing tables.
// =========================================================================

function sc_guard($conn) {
    if (!isset($_SESSION['agency_id'])) { http_response_code(403); die("Unauthorised."); }
    if (isAgencySubscriptionExpired($conn, $_SESSION['agency_id'])) {
        flash("Subscription expired. Renew to use this feature.", "error");
        redirect("?route=app&page=dashboard");
    }
}
function sc_id($conn, $table, $prefix, $agency_id) {
    return generateSerialId($conn, $table, $prefix, $agency_id);
}

// ── STUDENT LEADS ──────────────────────────────────────────────────────────
if ($action === 'sc_save_lead' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    if (!has_permission('can_manage_sc_leads')) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $id = trim($_POST['id'] ?? '');
    $staffId = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $refId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : (!empty($_POST['reference_staff_id']) ? (int)$_POST['reference_staff_id'] : null);
    $fields  = ['created_date','student_name','mobile','email','passport_no','education_background','ielts_score','preferred_country','preferred_university','preferred_intake','budget','lead_source','status','notes'];
    $data    = array_map(fn($f) => trim($_POST[$f] ?? ''), array_combine($fields, $fields));
    if (empty($id)) {
        $newId = sc_id($conn, 'sc_leads', 'SL', $agency_id);
        $cols = array_merge(['id','agency_id','reference_staff_id'], $fields);
        if ($staffId) { $cols[] = 'created_by_staff_id'; }
        $vals = array_merge([$newId, $agency_id, $refId], array_values($data));
        if ($staffId) $vals[] = $staffId;
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $conn->prepare("INSERT INTO sc_leads (".implode(',',$cols).") VALUES ($ph)")->execute($vals);
        $conn->prepare("INSERT INTO record_followups (agency_id,module_name,record_id,staff_id,note) VALUES (?,?,?,?,?)")
             ->execute([$agency_id,'sc_leads',$newId,$staffId,'Lead created.']);
        flash("Student lead added successfully.");
    } else {
        $set = []; $vals = [];
        foreach ($fields as $f) { $set[] = "$f=?"; $vals[] = $data[$f]; }
        $set[] = "reference_staff_id=?"; $vals[] = $refId;
        if ($staffId) { $set[] = "updated_by_staff_id=?"; $vals[] = $staffId; }
        $vals[] = $agency_id; $vals[] = $id;
        $conn->prepare("UPDATE sc_leads SET ".implode(',',$set)." WHERE agency_id=? AND id=?")->execute($vals);
        flash("Lead updated.");
    }
    redirect("?route=app&page=sc_leads");
}

if ($action === 'sc_delete_lead' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    if (!has_permission('can_manage_sc_leads')) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $id = trim($_POST['id'] ?? '');
    $conn->prepare("DELETE FROM record_followups WHERE agency_id=? AND module_name='sc_leads' AND record_id=?")->execute([$agency_id,$id]);
    $conn->prepare("DELETE FROM sc_leads WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Lead deleted.");
    redirect("?route=app&page=sc_leads");
}

if ($action === 'sc_convert_lead' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    if (!has_permission('can_manage_sc_students')) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $leadId = trim($_POST['lead_id'] ?? '');
    $stmt = $conn->prepare("SELECT * FROM sc_leads WHERE id=? AND agency_id=?");
    $stmt->execute([$leadId,$agency_id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lead) { flash("Lead not found.", "error"); redirect("?route=app&page=sc_leads"); }
    $newId = sc_id($conn, 'sc_students', 'ST', $agency_id);
    $staffId = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $conn->prepare("INSERT INTO sc_students (id,agency_id,lead_id,student_name,mobile,email,passport_no,education_background,ielts_score,current_status,reference_staff_id,created_by_staff_id) VALUES (?,?,?,?,?,?,?,?,?,'Active',?,?)")
         ->execute([$newId,$agency_id,$leadId,$lead['student_name'],$lead['mobile'],$lead['email'],$lead['passport_no'],$lead['education_background'],$lead['ielts_score'],$lead['reference_staff_id'],$staffId]);
    $conn->prepare("UPDATE sc_leads SET status='Converted' WHERE id=? AND agency_id=?")->execute([$leadId,$agency_id]);
    $conn->prepare("INSERT INTO record_followups (agency_id,module_name,record_id,staff_id,note) VALUES (?,?,?,?,?)")
         ->execute([$agency_id,'sc_students',$newId,$staffId,"Student profile created from Lead $leadId."]);
    flash("Lead converted to Student profile. Student ID: $newId");
    redirect("?route=app&page=sc_students&id=$newId");
}

// ── STUDENTS ───────────────────────────────────────────────────────────────
if ($action === 'sc_save_student' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    if (!has_permission('can_manage_sc_students')) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $id = trim($_POST['id'] ?? '');
    $staffId = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $refId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : (!empty($_POST['reference_staff_id']) ? (int)$_POST['reference_staff_id'] : null);
    $fields  = ['student_name','mobile','email','date_of_birth','passport_no','passport_expiry','nationality','guardian_name','guardian_mobile','guardian_relation','education_background','ielts_score','current_status','notes'];
    $data    = array_map(fn($f) => trim($_POST[$f] ?? '') ?: null, array_combine($fields, $fields));
    if (empty($id)) {
        $newId = sc_id($conn, 'sc_students', 'ST', $agency_id);
        $cols  = array_merge(['id','agency_id','reference_staff_id'], $fields);
        if ($staffId) $cols[] = 'created_by_staff_id';
        $vals  = array_merge([$newId, $agency_id, $refId], array_values($data));
        if ($staffId) $vals[] = $staffId;
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $conn->prepare("INSERT INTO sc_students (".implode(',',$cols).") VALUES ($ph)")->execute($vals);
        flash("Student profile created.");
        redirect("?route=app&page=sc_students&id=$newId");
    } else {
        $set = []; $vals = [];
        foreach ($fields as $f) { $set[] = "$f=?"; $vals[] = $data[$f]; }
        $set[] = "reference_staff_id=?"; $vals[] = $refId;
        $vals[] = $agency_id; $vals[] = $id;
        $conn->prepare("UPDATE sc_students SET ".implode(',',$set)." WHERE agency_id=? AND id=?")->execute($vals);
        flash("Student profile updated.");
        redirect("?route=app&page=sc_students&id=$id");
    }
}

if ($action === 'sc_delete_student' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    if (!has_permission('can_manage_sc_students') || $_SESSION['is_staff']) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $id = trim($_POST['id'] ?? '');
    foreach (['sc_applications','sc_documents','sc_visa','sc_payments'] as $tbl) {
        $conn->prepare("DELETE FROM $tbl WHERE student_id=? AND agency_id=?")->execute([$id,$agency_id]);
    }
    $conn->prepare("DELETE FROM record_followups WHERE agency_id=? AND module_name='sc_students' AND record_id=?")->execute([$agency_id,$id]);
    $conn->prepare("DELETE FROM sc_students WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Student deleted along with all related records.");
    redirect("?route=app&page=sc_students");
}

// ── APPLICATIONS ───────────────────────────────────────────────────────────
if ($action === 'sc_save_application' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    if (!has_permission('can_manage_sc_applications')) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $id = trim($_POST['id'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $staffId = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $f = ['university_name','course','intake','tuition_fee','scholarship','application_date','offer_status','notes'];
    $d = array_map(fn($k) => trim($_POST[$k] ?? '') ?: null, array_combine($f,$f));
    if (empty($id)) {
        $newId = sc_id($conn, 'sc_applications', 'AP', $agency_id);
        $conn->prepare("INSERT INTO sc_applications (id,agency_id,student_id,university_name,course,intake,tuition_fee,scholarship,application_date,offer_status,notes,reference_staff_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
             ->execute([$newId,$agency_id,$studentId,$d['university_name'],$d['course'],$d['intake'],$d['tuition_fee']??0,$d['scholarship']??0,$d['application_date'],$d['offer_status']??'Draft',$d['notes'],$staffId]);
        flash("Application added.");
    } else {
        $conn->prepare("UPDATE sc_applications SET university_name=?,course=?,intake=?,tuition_fee=?,scholarship=?,application_date=?,offer_status=?,notes=? WHERE id=? AND agency_id=?")
             ->execute([$d['university_name'],$d['course'],$d['intake'],$d['tuition_fee']??0,$d['scholarship']??0,$d['application_date'],$d['offer_status'],$d['notes'],$id,$agency_id]);
        flash("Application updated.");
    }
    $redirect = !empty($_POST['student_id']) ? "?route=app&page=sc_students&id=$studentId&tab=applications" : "?route=app&page=sc_applications";
    redirect($redirect);
}

if ($action === 'sc_delete_application' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    if (!has_permission('can_manage_sc_applications')) { http_response_code(403); die("403"); }
    $id = trim($_POST['id'] ?? ''); $agency_id = $_SESSION['agency_id'];
    $conn->prepare("DELETE FROM sc_applications WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Application deleted.");
    redirect("?route=app&page=sc_applications");
}

// ── DOCUMENTS ──────────────────────────────────────────────────────────────
if ($action === 'sc_upload_document' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    if (!has_permission('can_manage_sc_students')) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $studentId = trim($_POST['student_id'] ?? '');
    $docType   = trim($_POST['doc_type'] ?? 'Other');
    $docStatus = trim($_POST['doc_status'] ?? 'Uploaded');
    $expiryDate= trim($_POST['expiry_date'] ?? '') ?: null;
    $notes     = trim($_POST['doc_notes'] ?? '');
    $staffId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $filePath  = null; $fileName = null;
    if (!empty($_FILES['doc_file']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/sc_docs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext  = strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION));
        $safe = preg_replace('/[^a-z0-9_\-]/', '_', strtolower(pathinfo($_FILES['doc_file']['name'], PATHINFO_FILENAME)));
        $fileName  = $safe . '_' . time() . '.' . $ext;
        $filePath  = 'uploads/sc_docs/' . $fileName;
        if (!move_uploaded_file($_FILES['doc_file']['tmp_name'], $uploadDir . $fileName)) {
            flash("File upload failed.", "error");
            redirect("?route=app&page=sc_students&id=$studentId&tab=documents");
        }
    }
    $conn->prepare("INSERT INTO sc_documents (agency_id,student_id,doc_type,file_name,file_path,doc_status,expiry_date,notes,uploaded_by_staff_id) VALUES (?,?,?,?,?,?,?,?,?)")
         ->execute([$agency_id,$studentId,$docType,$fileName,$filePath,$docStatus,$expiryDate,$notes,$staffId]);
    flash("Document uploaded.");
    redirect("?route=app&page=sc_students&id=$studentId&tab=documents");
}

if ($action === 'sc_update_document' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $id = (int)($_POST['id'] ?? 0);
    $conn->prepare("UPDATE sc_documents SET doc_status=?,expiry_date=?,notes=? WHERE id=? AND agency_id=?")
         ->execute([trim($_POST['doc_status']??'Pending'), trim($_POST['expiry_date']??'')?:null, trim($_POST['doc_notes']??''), $id, $agency_id]);
    flash("Document updated.");
    redirect("?route=app&page=sc_documents");
}

if ($action === 'sc_delete_document' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $id = (int)($_POST['id'] ?? 0);
    $row = $conn->prepare("SELECT file_path, student_id FROM sc_documents WHERE id=? AND agency_id=?");
    $row->execute([$id,$agency_id]); $row = $row->fetch();
    if ($row) {
        if ($row['file_path'] && file_exists(__DIR__ . '/../' . $row['file_path'])) unlink(__DIR__ . '/../' . $row['file_path']);
        $conn->prepare("DELETE FROM sc_documents WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
        flash("Document deleted.");
        redirect("?route=app&page=sc_students&id=".$row['student_id']."&tab=documents");
    }
    redirect("?route=app&page=sc_documents");
}

// ── VISA ───────────────────────────────────────────────────────────────────
if ($action === 'sc_save_visa' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    if (!has_permission('can_manage_sc_applications')) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $id = trim($_POST['id'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $staffId = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $f = ['destination_country','visa_type','embassy','application_date','biometrics_date','medical_date','interview_date','decision_date','visa_number','status','notes'];
    $d = array_map(fn($k) => trim($_POST[$k]??'')?:null, array_combine($f,$f));
    if (empty($id)) {
        $newId = sc_id($conn, 'sc_visa', 'SV', $agency_id);
        $conn->prepare("INSERT INTO sc_visa (id,agency_id,student_id,destination_country,visa_type,embassy,application_date,biometrics_date,medical_date,interview_date,decision_date,visa_number,status,notes,reference_staff_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
             ->execute([$newId,$agency_id,$studentId,$d['destination_country'],$d['visa_type'],$d['embassy'],$d['application_date'],$d['biometrics_date'],$d['medical_date'],$d['interview_date'],$d['decision_date'],$d['visa_number'],$d['status']??'Not Started',$d['notes'],$staffId]);
        flash("Visa record added.");
    } else {
        $conn->prepare("UPDATE sc_visa SET destination_country=?,visa_type=?,embassy=?,application_date=?,biometrics_date=?,medical_date=?,interview_date=?,decision_date=?,visa_number=?,status=?,notes=? WHERE id=? AND agency_id=?")
             ->execute([$d['destination_country'],$d['visa_type'],$d['embassy'],$d['application_date'],$d['biometrics_date'],$d['medical_date'],$d['interview_date'],$d['decision_date'],$d['visa_number'],$d['status'],$d['notes'],$id,$agency_id]);
        flash("Visa updated.");
    }
    redirect(!empty($studentId) ? "?route=app&page=sc_students&id=$studentId&tab=visa" : "?route=app&page=sc_visa");
}

if ($action === 'sc_delete_visa' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    $id = trim($_POST['id'] ?? ''); $agency_id = $_SESSION['agency_id'];
    $conn->prepare("DELETE FROM sc_visa WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Visa record deleted.");
    redirect("?route=app&page=sc_visa");
}

// ── PAYMENTS ───────────────────────────────────────────────────────────────
if ($action === 'sc_save_payment' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    if (!has_permission('can_manage_sc_payments')) { http_response_code(403); die("403"); }
    $agency_id = $_SESSION['agency_id'];
    $id = trim($_POST['id'] ?? '');
    $studentId = trim($_POST['student_id'] ?? '');
    $staffId = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $total  = (float)($_POST['total_amount']  ?? 0);
    $paid   = (float)($_POST['paid_amount']   ?? 0);
    $due    = round($total - $paid, 2);
    $ptype  = trim($_POST['payment_type'] ?? '');
    $pdate  = trim($_POST['payment_date'] ?? '') ?: null;
    $notes  = trim($_POST['notes'] ?? '');
    if (empty($id)) {
        $newId = sc_id($conn, 'sc_payments', 'SP', $agency_id);
        $conn->prepare("INSERT INTO sc_payments (id,agency_id,student_id,payment_type,total_amount,paid_amount,due_amount,payment_date,notes,reference_staff_id) VALUES (?,?,?,?,?,?,?,?,?,?)")
             ->execute([$newId,$agency_id,$studentId,$ptype,$total,$paid,$due,$pdate,$notes,$staffId]);
        flash("Payment record added.");
    } else {
        $conn->prepare("UPDATE sc_payments SET payment_type=?,total_amount=?,paid_amount=?,due_amount=?,payment_date=?,notes=? WHERE id=? AND agency_id=?")
             ->execute([$ptype,$total,$paid,$due,$pdate,$notes,$id,$agency_id]);
        flash("Payment updated.");
    }
    redirect(!empty($studentId) ? "?route=app&page=sc_students&id=$studentId&tab=payments" : "?route=app&page=sc_payments");
}

if ($action === 'sc_delete_payment' && isset($_SESSION['agency_id'])) {
    sc_guard($conn);
    if (!has_permission('can_manage_sc_payments')) { http_response_code(403); die("403"); }
    $id = trim($_POST['id'] ?? ''); $agency_id = $_SESSION['agency_id'];
    $conn->prepare("DELETE FROM sc_payments WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Payment deleted.");
    redirect("?route=app&page=sc_payments");
}

// ── SETTINGS ───────────────────────────────────────────────────────────────
if ($action === 'sc_save_setting' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    sc_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $allowed_cats = ['countries','universities','courses','intakes','visa_types','document_types','lead_sources','payment_categories'];
    $cat   = trim($_POST['category'] ?? '');
    $value = trim($_POST['value'] ?? '');
    if (!in_array($cat, $allowed_cats) || empty($value)) {
        flash("Invalid input.", "error");
        redirect("?route=app&page=sc_settings&tab=$cat");
    }
    $exists = $conn->prepare("SELECT id FROM sc_setting_items WHERE agency_id=? AND category=? AND value=?");
    $exists->execute([$agency_id,$cat,$value]);
    if ($exists->fetchColumn()) {
        flash("Item already exists.", "error");
    } else {
        $conn->prepare("INSERT INTO sc_setting_items (agency_id,category,value) VALUES (?,?,?)")->execute([$agency_id,$cat,$value]);
        flash("Added: $value");
    }
    redirect("?route=app&page=sc_settings&tab=$cat");
}

if ($action === 'sc_delete_setting' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    sc_guard($conn);
    $id = (int)($_POST['id'] ?? 0); $agency_id = $_SESSION['agency_id'];
    $cat = $conn->prepare("SELECT category FROM sc_setting_items WHERE id=? AND agency_id=?");
    $cat->execute([$id,$agency_id]); $cat = $cat->fetchColumn();
    $conn->prepare("DELETE FROM sc_setting_items WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
    flash("Deleted.");
    redirect("?route=app&page=sc_settings&tab=$cat");
}
