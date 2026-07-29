<?php
// =========================================================================
// OCR DOCUMENT SCANNER MODULE — ACTION HANDLERS
// Included at the bottom of actions_agency.php via require.
// All actions are additive and do not modify any existing tables.
// =========================================================================

if (!function_exists('ocr_get_api_key')) {
    function ocr_get_api_key($conn, $agency_id) {
        $k = getenv('OPENAI_API_KEY');
        if ($k) return $k;
        $stmt = $conn->prepare("SELECT setting_value FROM acc_settings WHERE agency_id=? AND setting_key='ocr_openai_key' LIMIT 1");
        $stmt->execute([$agency_id]);
        return $stmt->fetchColumn() ?: null;
    }
}

function ocr_guard($conn) {
    if (!isset($_SESSION['agency_id'])) { http_response_code(403); die("Unauthorised."); }
    if (isAgencySubscriptionExpired($conn, $_SESSION['agency_id'])) {
        flash("Subscription expired. Renew to use this feature.", "error");
        redirect("?route=app&page=dashboard");
    }
}

// Helper: call OpenAI Vision API and return structured data
function ocr_call_ai($filePath, $mimeType, $apiKey) {
    if (!in_array($mimeType, ['image/jpeg','image/jpg','image/png','image/webp','image/gif'])) {
        return ['success' => false, 'error' => 'unsupported_type', 'message' => 'AI OCR supports JPG, PNG, WEBP images only. Please enter data manually for PDF files.'];
    }
    $imageData = base64_encode(file_get_contents($filePath));
    if (!$imageData) return ['success' => false, 'error' => 'read_failed', 'message' => 'Could not read uploaded file.'];

    $prompt = 'Analyze this document image (passport, NID, visa, birth certificate, etc.) and extract all readable data. Return ONLY a valid JSON object with exactly these fields (use null for any field not found or not applicable):
{
  "document_type": "Passport or NID or Visa or Birth Certificate or Other",
  "document_number": "the primary document/serial number",
  "full_name": "full name of the document holder",
  "date_of_birth": "YYYY-MM-DD format or null",
  "gender": "Male or Female or Other or null",
  "nationality": "country name or null",
  "issue_date": "YYYY-MM-DD format or null",
  "expiry_date": "YYYY-MM-DD format or null",
  "issue_country": "country of issue or null",
  "father_name": "father\'s name or null",
  "mother_name": "mother\'s name or null",
  "address": "full address if shown or null",
  "nid_number": "national ID number if different from document_number or null",
  "confidence": 0-100 integer representing how confident you are in the extraction
}
Return ONLY the JSON object, no markdown, no explanation.';

    $payload = json_encode([
        'model' => 'gpt-4o-mini',
        'max_tokens' => 800,
        'messages' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageData}", 'detail' => 'high']]
            ]
        ]]
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$raw) return ['success' => false, 'error' => 'curl_failed', 'message' => 'Network error calling AI service.'];

    $resp = json_decode($raw, true);
    if ($httpCode !== 200 || empty($resp['choices'][0]['message']['content'])) {
        $errMsg = $resp['error']['message'] ?? 'Unknown AI error (HTTP '.$httpCode.')';
        return ['success' => false, 'error' => 'api_error', 'message' => $errMsg];
    }

    $content = trim($resp['choices'][0]['message']['content']);
    // Strip markdown code fences if present
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);
    $extracted = json_decode($content, true);
    if (!$extracted) return ['success' => false, 'error' => 'parse_failed', 'message' => 'AI returned unreadable data. Try a clearer image.'];

    return ['success' => true, 'data' => $extracted, 'raw' => $content];
}

// ── PROCESS FILE (AJAX — returns JSON) ────────────────────────────────────────
if ($action === 'ocr_process_file' && isset($_SESSION['agency_id'])) {
    header('Content-Type: application/json');
    ocr_guard($conn);
    $agency_id = $_SESSION['agency_id'];

    if (empty($_FILES['ocr_file']['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => 'No file received.']); exit;
    }

    $file    = $_FILES['ocr_file'];
    $mime    = mime_content_type($file['tmp_name']);
    $apiKey  = ocr_get_api_key($conn, $agency_id);

    if (!$apiKey) {
        // No API key — return empty template for manual entry
        echo json_encode(['success' => true, 'ai_used' => false, 'message' => 'No AI API key configured. Enter document data manually.', 'data' => []]);
        exit;
    }

    $result = ocr_call_ai($file['tmp_name'], $mime, $apiKey);
    if (!$result['success']) {
        echo json_encode(['success' => false, 'ai_used' => true, 'message' => $result['message']]); exit;
    }

    echo json_encode(['success' => true, 'ai_used' => true, 'data' => $result['data'], 'raw' => $result['raw'] ?? '']);
    exit;
}

// ── SAVE DOCUMENT ──────────────────────────────────────────────────────────────
if ($action === 'ocr_save_document' && isset($_SESSION['agency_id'])) {
    ocr_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $staffId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $id        = trim($_POST['id'] ?? '');

    // Save file to disk
    $filePath = null; $fileType = null; $fileSize = null;
    $uploadDir = __DIR__ . '/../uploads/ocr_docs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if (!empty($_FILES['ocr_file']['tmp_name'])) {
        $origName = basename($_FILES['ocr_file']['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','webp','gif','pdf','heic'];
        if (!in_array($ext, $allowed)) {
            flash("File type not allowed.", "error");
            redirect("?route=app&page=ocr_scanner&tab=upload");
        }
        $fileName = 'ocr_' . uniqid() . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['ocr_file']['tmp_name'], $uploadDir . $fileName)) {
            $filePath = 'uploads/ocr_docs/' . $fileName;
            $fileType = $_FILES['ocr_file']['type'];
            $fileSize = $_FILES['ocr_file']['size'];
        }
    }

    // Fields
    $docType      = trim($_POST['document_type']  ?? 'Other');
    $docNumber    = trim($_POST['document_number'] ?? '');
    $fullName     = trim($_POST['full_name']       ?? '');
    $mobile       = trim($_POST['mobile']          ?? '');
    $email        = trim($_POST['email']           ?? '');
    $dob          = trim($_POST['date_of_birth']   ?? '') ?: null;
    $age          = ($dob && strtotime($dob)) ? (int)date_diff(date_create($dob), date_create('today'))->y : null;
    $gender       = trim($_POST['gender']          ?? '');
    $nationality  = trim($_POST['nationality']     ?? '');
    $issueDate    = trim($_POST['issue_date']      ?? '') ?: null;
    $expiryDate   = trim($_POST['expiry_date']     ?? '') ?: null;
    $issueCountry = trim($_POST['issue_country']   ?? '');
    $fatherName   = trim($_POST['father_name']     ?? '');
    $motherName   = trim($_POST['mother_name']     ?? '');
    $address      = trim($_POST['address']         ?? '');
    $nidNumber    = trim($_POST['nid_number']      ?? '');
    $confidence   = is_numeric($_POST['ocr_confidence'] ?? '') ? (float)$_POST['ocr_confidence'] : null;
    $status       = trim($_POST['status'] ?? 'Active');
    $customerId   = trim($_POST['customer_id'] ?? '') ?: null;

    // Handle customer linking or auto-creation
    $createCustomer = !empty($_POST['create_customer']) && $_POST['create_customer'] === '1';
    if ($createCustomer && $fullName && !$customerId) {
        // Check for existing customer by document number
        $existing = null;
        if ($docNumber) {
            $cx = $conn->prepare("SELECT id FROM customers WHERE agency_id=? AND passport_number=? LIMIT 1");
            $cx->execute([$agency_id, $docNumber]);
            $existing = $cx->fetchColumn();
        }
        if (!$existing && $nidNumber) {
            $cx = $conn->prepare("SELECT id FROM customers WHERE agency_id=? AND nid_number=? LIMIT 1");
            $cx->execute([$agency_id, $nidNumber]);
            $existing = $cx->fetchColumn();
        }
        if ($existing) {
            $customerId = $existing;
        } else {
            // Create new customer
            $cuId = generateSerialId($conn, 'customers', 'CU', $agency_id);
            $conn->prepare("INSERT INTO customers (id, agency_id, name, mobile, email, passport_number, nid_number, date_of_birth, gender, nationality, address, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([$cuId, $agency_id, $fullName, $mobile ?: '000', $email, $docNumber ?: null, $nidNumber ?: null, $dob, $gender, $nationality, $address]);
            $customerId = $cuId;
        }
    }

    if (empty($id)) {
        // Insert
        $newId = generateSerialId($conn, 'ocr_documents', 'OD', $agency_id);
        $conn->prepare("INSERT INTO ocr_documents
            (id, agency_id, customer_id, document_type, document_number, full_name, mobile, email,
             date_of_birth, age, gender, nationality, issue_date, expiry_date, issue_country,
             father_name, mother_name, address, nid_number, ocr_confidence,
             file_path, file_type, file_size, status, uploaded_by_staff_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$newId, $agency_id, $customerId, $docType, $docNumber, $fullName, $mobile, $email,
                       $dob, $age, $gender, $nationality, $issueDate, $expiryDate, $issueCountry,
                       $fatherName, $motherName, $address, $nidNumber, $confidence,
                       $filePath, $fileType, $fileSize, $status, $staffId]);
        flash("Document saved successfully (ID: {$newId}).");
    } else {
        // Update
        $setClause = "customer_id=?, document_type=?, document_number=?, full_name=?, mobile=?, email=?,
                      date_of_birth=?, age=?, gender=?, nationality=?, issue_date=?, expiry_date=?, issue_country=?,
                      father_name=?, mother_name=?, address=?, nid_number=?, status=?";
        $vals = [$customerId, $docType, $docNumber, $fullName, $mobile, $email,
                 $dob, $age, $gender, $nationality, $issueDate, $expiryDate, $issueCountry,
                 $fatherName, $motherName, $address, $nidNumber, $status];
        if ($filePath) { $setClause .= ", file_path=?, file_type=?, file_size=?"; $vals = array_merge($vals, [$filePath, $fileType, $fileSize]); }
        if ($confidence !== null) { $setClause .= ", ocr_confidence=?"; $vals[] = $confidence; }
        $vals[] = $agency_id; $vals[] = $id;
        $conn->prepare("UPDATE ocr_documents SET $setClause WHERE agency_id=? AND id=?")->execute($vals);
        flash("Document updated successfully.");
    }
    redirect("?route=app&page=ocr_scanner");
}

// ── DELETE DOCUMENT ────────────────────────────────────────────────────────────
if ($action === 'ocr_delete_document' && isset($_SESSION['agency_id'])) {
    ocr_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $id = trim($_POST['id'] ?? '');
    if ($id) {
        // Delete physical file too
        $row = $conn->prepare("SELECT file_path FROM ocr_documents WHERE id=? AND agency_id=?")->execute([$id,$agency_id]);
        $doc = $conn->prepare("SELECT file_path FROM ocr_documents WHERE id=? AND agency_id=? LIMIT 1");
        $doc->execute([$id, $agency_id]);
        $row = $doc->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['file_path'] && file_exists(__DIR__.'/../'.$row['file_path'])) {
            @unlink(__DIR__.'/../'.$row['file_path']);
        }
        $conn->prepare("DELETE FROM ocr_documents WHERE id=? AND agency_id=?")->execute([$id, $agency_id]);
        flash("Document deleted.");
    }
    redirect("?route=app&page=ocr_scanner");
}

// ── SAVE OCR SETTINGS (admin only) ────────────────────────────────────────────
if ($action === 'ocr_save_settings' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    ocr_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $apiKey = trim($_POST['ocr_api_key'] ?? '');
    // Upsert into acc_settings
    $conn->prepare("INSERT INTO acc_settings (agency_id, setting_key, setting_value) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
        ->execute([$agency_id, 'ocr_openai_key', $apiKey]);
    flash($apiKey ? "AI OCR API key saved." : "API key cleared.");
    redirect("?route=app&page=ocr_scanner&tab=settings");
}
