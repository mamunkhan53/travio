<?php
// =========================================================================
// OCR DOCUMENT SCANNER MODULE — ACTION HANDLERS
// OCR engine: OCR.Space REST API (no server binaries required).
// Provider pattern: add new OCR engines by creating a new class that
// extends OcrProvider and registering it in ocr_get_provider().
// =========================================================================

// ── Guard ─────────────────────────────────────────────────────────────────────
function ocr_guard($conn) {
    if (!isset($_SESSION['agency_id'])) { http_response_code(403); die("Unauthorised."); }
    if (isAgencySubscriptionExpired($conn, $_SESSION['agency_id'])) {
        flash("Subscription expired. Renew to use this feature.", "error");
        redirect("?route=app&page=dashboard");
    }
}

// ── Settings helpers (function_exists guards: called from both GET & POST) ────

if (!function_exists('ocr_get_api_key')) {
    function ocr_get_api_key($conn, $agency_id) {
        $stmt = $conn->prepare(
            "SELECT setting_value FROM acc_settings
              WHERE agency_id = ? AND setting_key = 'ocr_api_key' LIMIT 1"
        );
        $stmt->execute([$agency_id]);
        return (string)($stmt->fetchColumn() ?: '');
    }
}

if (!function_exists('ocr_get_provider_name')) {
    function ocr_get_provider_name($conn, $agency_id) {
        $stmt = $conn->prepare(
            "SELECT setting_value FROM acc_settings
              WHERE agency_id = ? AND setting_key = 'ocr_provider' LIMIT 1"
        );
        $stmt->execute([$agency_id]);
        return (string)($stmt->fetchColumn() ?: 'ocr_space');
    }
}

// ── Provider factory ──────────────────────────────────────────────────────────
if (!function_exists('ocr_get_provider')) {
    function ocr_get_provider($conn, $agency_id, $apiKey) {
        $providerName = ocr_get_provider_name($conn, $agency_id);
        $base = __DIR__ . '/ocr_providers/';
        switch ($providerName) {
            // Add future providers here:
            // case 'google_vision': require_once $base.'GoogleVisionProvider.php'; return new GoogleVisionProvider($apiKey);
            // case 'openai':        require_once $base.'OpenAiVisionProvider.php'; return new OpenAiVisionProvider($apiKey);
            case 'ocr_space':
            default:
                require_once $base . 'OcrSpaceProvider.php';
                return new OcrSpaceProvider($apiKey);
        }
    }
}

// ── All available provider options (for Settings dropdown) ───────────────────
if (!function_exists('ocr_available_providers')) {
    function ocr_available_providers() {
        return [
            'ocr_space' => 'OCR.Space (Default — free tier available)',
            // Uncomment when implemented:
            // 'google_vision' => 'Google Cloud Vision (Future)',
            // 'openai'        => 'OpenAI Vision (Future)',
            // 'gemini'        => 'Google Gemini Vision (Future)',
        ];
    }
}

// ── Date normalisation ────────────────────────────────────────────────────────
function ocr_normalize_date($raw) {
    if (!$raw) return null;
    $raw = trim($raw);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $raw, $m))
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $raw, $m))
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : null;
}

// ── MRZ date (YYMMDD) → YYYY-MM-DD ───────────────────────────────────────────
function ocr_mrz_date($raw, $isExpiry = false) {
    if (strlen($raw) !== 6 || !ctype_digit($raw)) return null;
    $yy = (int)substr($raw, 0, 2);
    $mm = substr($raw, 2, 2);
    $dd = substr($raw, 4, 2);
    $currentYY = (int)date('y');
    if ($isExpiry) {
        $year = ($yy < $currentYY - 10) ? 2000 + $yy : ($yy > 50 ? 1900 + $yy : 2000 + $yy);
    } else {
        $year = ($yy > $currentYY + 5) ? 1900 + $yy : 2000 + $yy;
    }
    return "{$year}-{$mm}-{$dd}";
}

// ── Auto-detect document type ─────────────────────────────────────────────────
function ocr_detect_doc_type($text) {
    $up = strtoupper($text);
    if (preg_match('/^P<[A-Z]{3}/m', $text) || strpos($up, 'PASSPORT') !== false) return 'Passport';
    if (strpos($up, 'NATIONAL IDENTITY') !== false || strpos($up, 'VOTER ID') !== false
        || preg_match('/\bNID\b/', $up) || strpos($up, 'NATIONAL ID') !== false
        || strpos($up, 'BANGLADESH ELECTION') !== false) return 'NID';
    if (strpos($up, 'VISA') !== false && strpos($up, 'PASSPORT') === false) return 'Visa';
    if (strpos($up, 'BIRTH CERT') !== false || strpos($up, 'BIRTH REG') !== false) return 'Birth Certificate';
    if (strpos($up, 'DRIVING LICEN') !== false || strpos($up, 'DRIVER LICEN') !== false) return 'Driving License';
    return 'Other';
}

// ── Passport MRZ parser ───────────────────────────────────────────────────────
function ocr_parse_passport_mrz($text) {
    $lines = preg_split('/\r?\n/', $text);
    $mrzLines = [];
    foreach ($lines as $l) {
        $l = str_replace([' ', "\t"], '', $l);
        $l = str_replace('O', '0', $l); // common OCR mistake O→0
        if (preg_match('/^[A-Z0-9<]{30,44}$/', $l)) $mrzLines[] = $l;
    }
    $line1 = null; $line2 = null;
    foreach ($mrzLines as $i => $l) {
        if (substr($l, 0, 2) === 'P<' && strlen($l) >= 44) {
            $line1 = substr($l, 0, 44);
            for ($j = $i + 1; $j < count($mrzLines); $j++) {
                if (strlen($mrzLines[$j]) >= 44) {
                    $line2 = substr($mrzLines[$j], 0, 44); break;
                }
            }
            break;
        }
    }
    if (!$line1 || !$line2) return [];

    $country   = substr($line1, 2, 3);
    $namePart  = substr($line1, 5);
    $nameSplit = explode('<<', $namePart, 2);
    $surname   = trim(str_replace('<', ' ', $nameSplit[0]));
    $given     = isset($nameSplit[1]) ? trim(str_replace('<', ' ', $nameSplit[1])) : '';
    $fullName  = trim($surname . ($given ? ' ' . $given : ''));

    $passNo      = str_replace('<', '', substr($line2, 0, 9));
    $nationality = str_replace('<', '', substr($line2, 10, 3));
    $dobRaw      = substr($line2, 13, 6);
    $sexChar     = substr($line2, 20, 1);
    $expiryRaw   = substr($line2, 21, 6);

    return [
        'document_type'   => 'Passport',
        'document_number' => $passNo,
        'full_name'       => $fullName,
        'nationality'     => $nationality ?: $country,
        'date_of_birth'   => ocr_mrz_date($dobRaw, false),
        'expiry_date'     => ocr_mrz_date($expiryRaw, true),
        'gender'          => $sexChar === 'M' ? 'Male' : ($sexChar === 'F' ? 'Female' : ''),
        'issue_country'   => $country,
    ];
}

// ── Passport free-text parser (fallback when no MRZ) ─────────────────────────
function ocr_parse_passport_text($text) {
    $r = ['document_type' => 'Passport'];
    if (preg_match('/\b([A-Z]{1,2}\d{6,8})\b/', $text, $m)) $r['document_number'] = $m[1];
    if (preg_match('/(?:Surname|Last\s*Name)[:\s]+([A-Za-z\s\-]+)/i', $text, $m))
        $r['full_name'] = trim($m[1]);
    if (preg_match('/(?:Given\s*Names?|First\s*Name)[:\s]+([A-Za-z\s\-]+)/i', $text, $m))
        $r['full_name'] = trim(($r['full_name'] ?? '') . ' ' . $m[1]);
    if (empty($r['full_name']) && preg_match('/(?:Name)[:\s]+([A-Za-z\s\-]{3,50})/i', $text, $m))
        $r['full_name'] = trim($m[1]);
    if (preg_match('/(?:Nationality|Country)[:\s]+([A-Za-z\s]{3,30})/i', $text, $m))
        $r['nationality'] = trim($m[1]);
    if (preg_match('/(?:Date of Birth|D\.O\.B|DOB|Birth\s*Date)[:\s]+([0-9A-Za-z\s\/\-\.]{6,20})/i', $text, $m))
        $r['date_of_birth'] = ocr_normalize_date(trim($m[1]));
    if (preg_match('/(?:Date of Issue|Issued)[:\s]+([0-9A-Za-z\s\/\-\.]{6,20})/i', $text, $m))
        $r['issue_date'] = ocr_normalize_date(trim($m[1]));
    if (preg_match('/(?:Date of Expir(?:y|ation)|Valid Until|Expiry)[:\s]+([0-9A-Za-z\s\/\-\.]{6,20})/i', $text, $m))
        $r['expiry_date'] = ocr_normalize_date(trim($m[1]));
    if (preg_match('/\b(Male|Female|M|F)\b/i', $text, $m)) {
        $g = strtoupper($m[1]);
        $r['gender'] = ($g === 'M' || $g === 'MALE') ? 'Male' : 'Female';
    }
    if (preg_match('/(?:Place of Issue|Issuing Authority)[:\s]+([A-Za-z\s]{3,40})/i', $text, $m))
        $r['issue_country'] = trim($m[1]);
    if (preg_match('/(?:Father(?:\'?s?)?\s*Name|Father)[:\s]+([A-Za-z\s\-]{3,50})/i', $text, $m))
        $r['father_name'] = trim($m[1]);
    return $r;
}

// ── Bangladesh NID parser ─────────────────────────────────────────────────────
function ocr_parse_nid($text) {
    $r = ['document_type' => 'NID'];
    if (preg_match('/\b(\d{17}|\d{13}|\d{10})\b/', $text, $m)) $r['nid_number'] = $m[1];
    if (preg_match('/(?:Name|Voter\s*Name|Full\s*Name)[:\s]+([A-Za-z][A-Za-z\s\-\.]{2,60})/i', $text, $m))
        $r['full_name'] = trim($m[1]);
    if (preg_match('/(?:Father(?:\'?s?)?\s*Name|Father)[:\s]+([A-Za-z][A-Za-z\s\-\.]{2,60})/i', $text, $m))
        $r['father_name'] = trim($m[1]);
    if (preg_match('/(?:Mother(?:\'?s?)?\s*Name|Mother)[:\s]+([A-Za-z][A-Za-z\s\-\.]{2,60})/i', $text, $m))
        $r['mother_name'] = trim($m[1]);
    if (preg_match('/(?:Date of Birth|DOB|Birth\s*Date)[:\s]+([0-9A-Za-z\s\/\-\.]{6,20})/i', $text, $m))
        $r['date_of_birth'] = ocr_normalize_date(trim($m[1]));
    if (empty($r['date_of_birth']) && preg_match('/\b(\d{2}[\/-]\d{2}[\/-]\d{4})\b/', $text, $m))
        $r['date_of_birth'] = ocr_normalize_date($m[1]);
    if (preg_match('/\b(Male|Female)\b/i', $text, $m))
        $r['gender'] = ucfirst(strtolower($m[1]));
    if (preg_match('/(?:Address|Present\s*Address)[:\s]+(.{10,120})/i', $text, $m))
        $r['address'] = trim($m[1]);
    return $r;
}

// ── Visa parser ───────────────────────────────────────────────────────────────
function ocr_parse_visa($text) {
    $r = ['document_type' => 'Visa'];
    if (preg_match('/(?:Visa\s*(?:No|Number|#))[:\s]*([A-Z0-9\-]{4,20})/i', $text, $m))
        $r['document_number'] = trim($m[1]);
    if (preg_match('/(?:Surname|Name of Holder|Full\s*Name|Name)[:\s]+([A-Za-z\s\-]{3,60})/i', $text, $m))
        $r['full_name'] = trim($m[1]);
    if (preg_match('/(?:Nationality|Country of Birth)[:\s]+([A-Za-z\s]{3,40})/i', $text, $m))
        $r['nationality'] = trim($m[1]);
    if (preg_match('/(?:Date of Issue|Issued\s*(?:on)?)[:\s]+([0-9A-Za-z\s\/\-\.]{6,20})/i', $text, $m))
        $r['issue_date'] = ocr_normalize_date(trim($m[1]));
    if (preg_match('/(?:Date of Expir(?:y|ation)|Valid Until|Expiry\s*Date)[:\s]+([0-9A-Za-z\s\/\-\.]{6,20})/i', $text, $m))
        $r['expiry_date'] = ocr_normalize_date(trim($m[1]));
    if (preg_match('/(?:Valid for|Territory|Country)[:\s]+([A-Za-z\s]{3,40})/i', $text, $m))
        $r['issue_country'] = trim($m[1]);
    return $r;
}

// ── Generic parser (other document types) ────────────────────────────────────
function ocr_parse_generic($text) {
    $r = [];
    if (preg_match('/(?:Name)[:\s]+([A-Za-z][A-Za-z\s\-\.]{2,60})/i', $text, $m))
        $r['full_name'] = trim($m[1]);
    if (preg_match('/(?:Date of Birth|DOB)[:\s]+([0-9A-Za-z\s\/\-\.]{6,20})/i', $text, $m))
        $r['date_of_birth'] = ocr_normalize_date(trim($m[1]));
    if (preg_match('/\b(Male|Female)\b/i', $text, $m))
        $r['gender'] = ucfirst(strtolower($m[1]));
    return $r;
}

// ── Main parse dispatcher ─────────────────────────────────────────────────────
function ocr_parse_document($text) {
    $docType = ocr_detect_doc_type($text);
    switch ($docType) {
        case 'Passport':
            $parsed = ocr_parse_passport_mrz($text);
            if (empty($parsed)) $parsed = ocr_parse_passport_text($text);
            break;
        case 'NID':   $parsed = ocr_parse_nid($text);     break;
        case 'Visa':  $parsed = ocr_parse_visa($text);    break;
        default:      $parsed = ocr_parse_generic($text); break;
    }
    $parsed['document_type'] = $docType;
    return $parsed;
}

// ── Confidence heuristic (0–100) ──────────────────────────────────────────────
function ocr_estimate_confidence($text, $parsed) {
    $score = 0;
    $len = strlen(trim($text));
    if ($len > 200) $score += 20; elseif ($len > 80) $score += 10;
    if (preg_match('/^P<[A-Z]{3}/m', $text)) $score += 40; // MRZ detected
    $fields = ['full_name','document_number','date_of_birth','expiry_date','nationality','gender','nid_number'];
    $populated = 0;
    foreach ($fields as $f) { if (!empty($parsed[$f])) $populated++; }
    $score += min(40, $populated * 8);
    return min(100, $score);
}

// =============================================================================
// ── ACTION: PROCESS FILE (AJAX → JSON) ───────────────────────────────────────
// =============================================================================
if ($action === 'ocr_process_file' && isset($_SESSION['agency_id'])) {
    header('Content-Type: application/json');
    ocr_guard($conn);
    $agency_id = $_SESSION['agency_id'];

    if (empty($_FILES['ocr_file']['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => 'No file received.']); exit;
    }

    $apiKey = ocr_get_api_key($conn, $agency_id);
    if (!$apiKey) {
        echo json_encode([
            'success' => false,
            'message' => 'No OCR API key configured. Please add your OCR.Space API key in Settings → OCR Settings.'
        ]); exit;
    }

    $file     = $_FILES['ocr_file'];
    $mime     = mime_content_type($file['tmp_name']);
    $provider = ocr_get_provider($conn, $agency_id, $apiKey);
    $result   = $provider->extractText($file['tmp_name'], $mime);

    if (!$result['success']) {
        echo json_encode(['success' => false, 'message' => $result['message']]); exit;
    }

    $rawText    = $result['text'];
    $parsed     = ocr_parse_document($rawText);
    $confidence = ocr_estimate_confidence($rawText, $parsed);
    $lowConf    = $confidence < 50;

    echo json_encode([
        'success'        => true,
        'ocr_done'       => true,
        'low_confidence' => $lowConf,
        'confidence'     => $confidence,
        'data'           => $parsed,
        'raw_text'       => $rawText,
        'message'        => $lowConf
            ? "Confidence low ({$confidence}%). Please review all fields carefully before saving."
            : "Extraction complete ({$confidence}% confidence). Review the fields below.",
    ]);
    exit;
}

// =============================================================================
// ── ACTION: SAVE DOCUMENT ────────────────────────────────────────────────────
// =============================================================================
if ($action === 'ocr_save_document' && isset($_SESSION['agency_id'])) {
    ocr_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $staffId   = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
    $id        = trim($_POST['id'] ?? '');

    // Save file
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
    $rawText      = trim($_POST['ocr_raw_text']    ?? '');
    $status       = trim($_POST['status']          ?? 'Active');
    $customerId   = trim($_POST['customer_id']     ?? '') ?: null;

    // Customer linking / auto-create
    $createCustomer = !empty($_POST['create_customer']) && $_POST['create_customer'] === '1';
    if ($createCustomer && $fullName && !$customerId) {
        $existing = null;
        if ($docNumber) {
            $cx = $conn->prepare("SELECT id FROM customers WHERE agency_id=? AND passport_number=? LIMIT 1");
            $cx->execute([$agency_id, $docNumber]); $existing = $cx->fetchColumn();
        }
        if (!$existing && $nidNumber) {
            $cx = $conn->prepare("SELECT id FROM customers WHERE agency_id=? AND nid_number=? LIMIT 1");
            $cx->execute([$agency_id, $nidNumber]); $existing = $cx->fetchColumn();
        }
        if ($existing) {
            $customerId = $existing;
        } else {
            $cuId = generateSerialId($conn, 'customers', 'CU', $agency_id);
            $conn->prepare("INSERT INTO customers
                (id, agency_id, name, mobile, email, passport_number, nid_number,
                 date_of_birth, gender, nationality, address, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([$cuId, $agency_id, $fullName, $mobile ?: '000', $email,
                           $docNumber ?: null, $nidNumber ?: null,
                           $dob, $gender, $nationality, $address]);
            $customerId = $cuId;
        }
    }

    if (empty($id)) {
        $newId = generateSerialId($conn, 'ocr_documents', 'OD', $agency_id);
        $conn->prepare("INSERT INTO ocr_documents
            (id, agency_id, customer_id, document_type, document_number, full_name, mobile, email,
             date_of_birth, age, gender, nationality, issue_date, expiry_date, issue_country,
             father_name, mother_name, address, nid_number, ocr_confidence, ocr_raw_text,
             file_path, file_type, file_size, status, uploaded_by_staff_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$newId, $agency_id, $customerId, $docType, $docNumber, $fullName, $mobile, $email,
                       $dob, $age, $gender, $nationality, $issueDate, $expiryDate, $issueCountry,
                       $fatherName, $motherName, $address, $nidNumber, $confidence, $rawText ?: null,
                       $filePath, $fileType, $fileSize, $status, $staffId]);
        flash("Document saved successfully (ID: {$newId}).");
    } else {
        $setClause = "customer_id=?, document_type=?, document_number=?, full_name=?, mobile=?, email=?,
                      date_of_birth=?, age=?, gender=?, nationality=?, issue_date=?, expiry_date=?,
                      issue_country=?, father_name=?, mother_name=?, address=?, nid_number=?, status=?";
        $vals = [$customerId, $docType, $docNumber, $fullName, $mobile, $email,
                 $dob, $age, $gender, $nationality, $issueDate, $expiryDate,
                 $issueCountry, $fatherName, $motherName, $address, $nidNumber, $status];
        if ($filePath)          { $setClause .= ", file_path=?, file_type=?, file_size=?"; $vals = array_merge($vals, [$filePath, $fileType, $fileSize]); }
        if ($confidence !== null) { $setClause .= ", ocr_confidence=?"; $vals[] = $confidence; }
        if ($rawText)           { $setClause .= ", ocr_raw_text=?";   $vals[] = $rawText; }
        $vals[] = $agency_id; $vals[] = $id;
        $conn->prepare("UPDATE ocr_documents SET $setClause WHERE agency_id=? AND id=?")->execute($vals);
        flash("Document updated successfully.");
    }
    redirect("?route=app&page=ocr_scanner");
}

// =============================================================================
// ── ACTION: DELETE DOCUMENT ──────────────────────────────────────────────────
// =============================================================================
if ($action === 'ocr_delete_document' && isset($_SESSION['agency_id'])) {
    ocr_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $id        = trim($_POST['id'] ?? '');
    if ($id) {
        $doc = $conn->prepare("SELECT file_path FROM ocr_documents WHERE id=? AND agency_id=? LIMIT 1");
        $doc->execute([$id, $agency_id]);
        $row = $doc->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['file_path'] && file_exists(__DIR__ . '/../' . $row['file_path']))
            @unlink(__DIR__ . '/../' . $row['file_path']);
        $conn->prepare("DELETE FROM ocr_documents WHERE id=? AND agency_id=?")->execute([$id, $agency_id]);
        flash("Document deleted.");
    }
    redirect("?route=app&page=ocr_scanner");
}

// =============================================================================
// ── ACTION: SAVE OCR SETTINGS (admin / agency owner only) ───────────────────
// =============================================================================
if ($action === 'ocr_save_settings' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
    ocr_guard($conn);
    $agency_id = $_SESSION['agency_id'];
    $apiKey    = trim($_POST['ocr_api_key']  ?? '');
    $provider  = trim($_POST['ocr_provider'] ?? 'ocr_space');

    // Validate provider choice
    $validProviders = array_keys(ocr_available_providers());
    if (!in_array($provider, $validProviders)) $provider = 'ocr_space';

    $upsert = "INSERT INTO acc_settings (agency_id, setting_key, setting_value) VALUES (?,?,?)
               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    $conn->prepare($upsert)->execute([$agency_id, 'ocr_provider', $provider]);
    $conn->prepare($upsert)->execute([$agency_id, 'ocr_api_key',  $apiKey]);

    flash($apiKey ? "OCR settings saved." : "OCR settings saved (API key cleared — OCR extraction disabled).");
    redirect("?route=app&page=ocr_scanner&tab=settings");
}
