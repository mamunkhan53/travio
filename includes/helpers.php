<?php
// 2. SECURITY, UTILITIES & SERIAL GENERATOR
// =========================================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verifyCsrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die("CSRF token validation failed.");
        }
    }
}

function xss_clean($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function flash($message, $type = 'success') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function has_permission($key) {
    if (!isset($_SESSION['is_staff']) || $_SESSION['is_staff'] === false) return true; // Admin/Super Admin
    if ($_SESSION['is_staff'] === true) {
        return isset($_SESSION['permissions'][$key]) && $_SESSION['permissions'][$key] == 1;
    }
    return false;
}

// =========================================================================
// SUBSCRIPTION / SAAS BILLING HELPERS
// =========================================================================
function isAgencySubscriptionExpired($conn, $agency_id) {
    $stmt = $conn->prepare("SELECT subscription_expires_at FROM agencies WHERE id = ?");
    $stmt->execute([$agency_id]);
    $exp = $stmt->fetchColumn();
    if (empty($exp)) return false; // No expiry set = not gated (safety default)
    return strtotime($exp) < time();
}

function subscriptionStatusInfo($agency) {
    $expires = $agency['subscription_expires_at'] ?? null;
    $expired = $expires ? (strtotime($expires) < time()) : false;
    $daysLeft = $expires ? (int)ceil((strtotime($expires) - time()) / 86400) : null;
    return [
        'plan' => $agency['subscription_plan'] ?? 'Trial',
        'amount' => $agency['subscription_amount'] ?? 0,
        'expires_at' => $expires,
        'expired' => $expired,
        'days_left' => $daysLeft,
    ];
}

function getSubscriptionPlans($conn) {
    $rows = $conn->query("SELECT * FROM subscription_plans ORDER BY price ASC")->fetchAll(PDO::FETCH_ASSOC);
    $plans = [];
    foreach ($rows as $r) { $plans[$r['plan_key']] = $r; }
    return $plans;
}

function getPaymentMethods($conn) {
    $rows = $conn->query("SELECT * FROM payment_methods ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $methods = [];
    foreach ($rows as $r) { $methods[$r['method_key']] = $r; }
    return $methods;
}

// Generate Sequential ID (e.g. CU-001)
function generateSerialId($conn, $table, $prefix, $agency_id, $pad = 3) {
    $stmt = $conn->prepare("SELECT id FROM $table WHERE agency_id = ? AND id LIKE ? ORDER BY CAST(SUBSTRING(id, LENGTH(?)+2) AS UNSIGNED) DESC LIMIT 1");
    $stmt->execute([$agency_id, $prefix.'-%', $prefix]);
    $lastId = $stmt->fetchColumn();
    if ($lastId) {
        $num = intval(substr($lastId, strlen($prefix) + 1));
        return $prefix . '-' . str_pad($num + 1, $pad, '0', STR_PAD_LEFT);
    }
    return $prefix . '-' . str_pad(1, $pad, '0', STR_PAD_LEFT);
}

// Generate Invoice Number (e.g. INV-240612001)
function generateInvoiceId($conn, $agency_id) {
    $prefix = 'INV-' . date('ymd');
    $stmt = $conn->prepare("SELECT invoice_number FROM invoices WHERE agency_id = ? AND invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1");
    $stmt->execute([$agency_id, $prefix.'%']);
    $last = $stmt->fetchColumn();
    if ($last) {
        $num = intval(substr($last, strlen($prefix)));
        return $prefix . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
    }
    return $prefix . '001';
}

function normalizeReportDate($date, $fallback) {
    $ts = strtotime($date ?? '');
    return $ts ? date('Y-m-d', $ts) : $fallback;
}

// =========================================================================
// WHATSAPP AUTOMATION ENGINE
// Additive layer on top of the manual WhatsApp sending system.
// Call triggerWhatsAppAutomation() from any action handler — it is a
// complete no-op when the automation is disabled or not yet configured.
// =========================================================================

/**
 * Central trigger. Safe to call from anywhere; silently returns if the
 * automation is disabled or there is no phone number to send to.
 *
 * $data keys accepted:
 *   phone, customer_name, company_name, office_phone, service_name,
 *   invoice_no, invoice_amount, due_amount, due_date,
 *   flight_date, flight_time, visa_country, visa_status, passport_number,
 *   record_table, record_id, event_date (Y-m-d, used for scheduling)
 */
function triggerWhatsAppAutomation($conn, $agency_id, $automation_type, $data) {
    try {
        $stmt = $conn->prepare(
            "SELECT * FROM whatsapp_automations WHERE agency_id = ? AND automation_type = ? AND is_enabled = 1"
        );
        $stmt->execute([$agency_id, $automation_type]);
        $auto = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$auto) return;

        $phone = preg_replace('/[^\d+]/', '', $data['phone'] ?? '');
        if (empty($phone)) return;

        // Enrich $data with agency details for variable replacement
        if (empty($data['company_name']) || empty($data['office_phone'])) {
            $ag = $conn->prepare("SELECT company_name, company_phone FROM agencies WHERE id = ?");
            $ag->execute([$agency_id]);
            $ag = $ag->fetch(PDO::FETCH_ASSOC) ?: [];
            $data['company_name'] = $data['company_name'] ?? ($ag['company_name'] ?? '');
            $data['office_phone'] = $data['office_phone'] ?? ($ag['company_phone'] ?? '');
        }

        $message = replaceWAVariables($auto['message_template'], $data);

        if ($auto['send_timing'] === 'immediately') {
            _dispatchWAAutomation($conn, $agency_id, $automation_type, $data, $phone, $message);
        } else {
            // Calculate scheduled send time relative to event_date
            $eventDate  = $data['event_date'] ?? date('Y-m-d');
            $unit       = $auto['timing_unit']  === 'hours' ? 'hours' : 'days';
            $val        = max(0, (int)$auto['timing_value']);
            $scheduledAt = date('Y-m-d H:i:s', strtotime("{$eventDate} - {$val} {$unit}"));

            if (strtotime($scheduledAt) <= time()) {
                // Already past — send now
                _dispatchWAAutomation($conn, $agency_id, $automation_type, $data, $phone, $message);
            } else {
                $conn->prepare(
                    "INSERT INTO whatsapp_automation_queue
                     (agency_id, automation_type, record_table, record_id, customer_name, phone, message_body, scheduled_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $agency_id, $automation_type,
                    $data['record_table'] ?? '', $data['record_id'] ?? '',
                    $data['customer_name'] ?? '', $phone,
                    $message, $scheduledAt,
                ]);
            }
        }
    } catch (Exception $e) {
        // Automation failures must never break the primary action flow
        error_log("WA Automation error ({$automation_type}): " . $e->getMessage());
    }
}

/** Internal: send one message and log it in whatsapp_message_logs. */
function _dispatchWAAutomation($conn, $agency_id, $automation_type, $data, $phone, $message) {
    $prov = $conn->prepare(
        "SELECT * FROM whatsapp_providers WHERE agency_id = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1"
    );
    $prov->execute([$agency_id]);
    $provider = $prov->fetch(PDO::FETCH_ASSOC);

    $logId   = generateSerialId($conn, 'whatsapp_message_logs', 'WA', $agency_id);
    $initStatus = $provider ? 'Processing' : 'No Provider';

    $conn->prepare(
        "INSERT INTO whatsapp_message_logs
         (id, agency_id, provider_id, message_body, recipient_count, status, sent_by_type)
         VALUES (?, ?, ?, ?, 1, ?, 'automation')"
    )->execute([$logId, $agency_id, $provider['id'] ?? null, $message, $initStatus]);

    $sentCount = 0; $failedCount = 0;
    $recStatus = 'No Provider'; $recError = null; $recSentAt = null;

    if ($provider) {
        $result = sendWhatsAppViaProvider($provider, $phone, $message);
        if ($result['success']) {
            $sentCount  = 1;
            $recStatus  = 'Sent';
            $recSentAt  = date('Y-m-d H:i:s');
        } else {
            $failedCount = 1;
            $recStatus   = 'Failed';
            $recError    = $result['error'];
        }
    }

    $conn->prepare(
        "INSERT INTO whatsapp_message_recipients
         (log_id, agency_id, customer_name, phone, status, error_message, sent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    )->execute([$logId, $agency_id, $data['customer_name'] ?? '', $phone, $recStatus, $recError, $recSentAt]);

    $finalStatus = $provider ? ($sentCount ? 'Sent' : 'Failed') : 'No Provider';
    $conn->prepare("UPDATE whatsapp_message_logs SET sent_count=?, failed_count=?, status=? WHERE id=?")
         ->execute([$sentCount, $failedCount, $finalStatus, $logId]);
}

/** Replace {Variable} placeholders in a template string. */
function replaceWAVariables($template, $data) {
    return str_replace(
        [
            '{CustomerName}','{CompanyName}','{ServiceName}',
            '{InvoiceNo}','{InvoiceAmount}','{DueAmount}','{DueDate}',
            '{FlightDate}','{FlightTime}','{VisaCountry}','{VisaStatus}',
            '{PassportNumber}','{OfficePhone}',
        ],
        [
            $data['customer_name']   ?? '',
            $data['company_name']    ?? '',
            $data['service_name']    ?? '',
            $data['invoice_no']      ?? '',
            $data['invoice_amount']  ?? '',
            $data['due_amount']      ?? '',
            $data['due_date']        ?? '',
            $data['flight_date']     ?? '',
            $data['flight_time']     ?? '',
            $data['visa_country']    ?? '',
            $data['visa_status']     ?? '',
            $data['passport_number'] ?? '',
            $data['office_phone']    ?? '',
        ],
        $template
    );
}

/**
 * Process scheduled queue items whose scheduled_at has passed.
 * Call this from the dashboard (or any high-traffic page) to act as a
 * lightweight cron substitute. Processes up to 20 items per call.
 */
function processWAAutomationQueue($conn, $agency_id) {
    try {
        $rows = $conn->prepare(
            "SELECT * FROM whatsapp_automation_queue
             WHERE agency_id = ? AND status = 'Pending' AND scheduled_at <= NOW()
             ORDER BY scheduled_at ASC LIMIT 20"
        );
        $rows->execute([$agency_id]);
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $item) {
            _dispatchWAAutomation(
                $conn, $agency_id, $item['automation_type'],
                ['customer_name' => $item['customer_name']],
                $item['phone'],
                $item['message_body']
            );
            // Mark queue item regardless — log entry in message_logs is the source of truth
            $conn->prepare("UPDATE whatsapp_automation_queue SET status='Sent' WHERE id=?")
                 ->execute([$item['id']]);
        }
    } catch (Exception $e) {
        error_log("WA Queue processing error: " . $e->getMessage());
    }
}

// =========================================================================
// WHATSAPP API DISPATCHER
// Dispatches a single message to one recipient via the configured provider.
// Returns ['success' => bool, 'error' => string|null].
// To add a new provider type: add a new `if ($apiType === '...')` block below
// and handle its unique authentication / payload format — nothing else changes.
// =========================================================================
function sendWhatsAppViaProvider($provider, $phone, $messageBody) {
    $apiType = $provider['api_type'] ?? 'custom_webhook';

    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'cURL is not available on this server.'];
    }

    // ---- Meta Cloud API (official WhatsApp Business API) ----
    if ($apiType === 'meta_cloud') {
        $phoneNumberId = $provider['from_number'] ?? '';
        $token = $provider['api_key'] ?? '';
        if (empty($phoneNumberId) || empty($token)) {
            return ['success' => false, 'error' => 'Meta Cloud: phone_number_id and access token are required.'];
        }
        $base = rtrim($provider['api_endpoint'] ?: 'https://graph.facebook.com/v18.0', '/');
        $url = "{$base}/{$phoneNumberId}/messages";
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $messageBody],
        ]);
        return _waHttpPost($url, $payload, ["Authorization: Bearer {$token}", "Content-Type: application/json"]);
    }

    // ---- Twilio WhatsApp ----
    if ($apiType === 'twilio') {
        $accountSid = $provider['api_key'] ?? '';
        $authToken  = $provider['api_secret'] ?? '';
        $from = 'whatsapp:+' . ltrim($provider['from_number'] ?? '', '+');
        $to   = 'whatsapp:+' . ltrim($phone, '+');
        if (empty($accountSid) || empty($authToken)) {
            return ['success' => false, 'error' => 'Twilio: Account SID and Auth Token are required.'];
        }
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
        $payload = http_build_query(['From' => $from, 'To' => $to, 'Body' => $messageBody]);
        return _waHttpPost($url, $payload, ["Content-Type: application/x-www-form-urlencoded"], "{$accountSid}:{$authToken}");
    }

    // ---- Vonage (Nexmo) ----
    if ($apiType === 'vonage') {
        $apiKey    = $provider['api_key'] ?? '';
        $apiSecret = $provider['api_secret'] ?? '';
        $from = $provider['from_number'] ?? '';
        if (empty($apiKey) || empty($apiSecret)) {
            return ['success' => false, 'error' => 'Vonage: API Key and API Secret are required.'];
        }
        $base = rtrim($provider['api_endpoint'] ?: 'https://messages-sandbox.nexmo.com', '/');
        $url  = "{$base}/v1/messages";
        $payload = json_encode([
            'message_type' => 'text',
            'text' => $messageBody,
            'to' => $phone,
            'from' => $from,
            'channel' => 'whatsapp',
        ]);
        $credentials = base64_encode("{$apiKey}:{$apiSecret}");
        return _waHttpPost($url, $payload, ["Authorization: Basic {$credentials}", "Content-Type: application/json"]);
    }

    // ---- WATI (WhatsApp Team Inbox) ----
    if ($apiType === 'wati') {
        $endpoint = rtrim($provider['api_endpoint'] ?? '', '/');
        $token = $provider['api_key'] ?? '';
        if (empty($endpoint) || empty($token)) {
            return ['success' => false, 'error' => 'WATI: Endpoint URL and API Token are required.'];
        }
        $url = "{$endpoint}/api/v1/sendSessionMessage/{$phone}";
        $payload = json_encode(['messageText' => $messageBody]);
        return _waHttpPost($url, $payload, ["Authorization: Bearer {$token}", "Content-Type: application/json"]);
    }

    // ---- Custom Webhook (generic HTTP POST — user controls the payload format) ----
    if ($apiType === 'custom_webhook') {
        $url = $provider['api_endpoint'] ?? '';
        if (empty($url)) {
            return ['success' => false, 'error' => 'Custom Webhook: Endpoint URL is required.'];
        }
        $extras = json_decode($provider['extra_params'] ?? '{}', true) ?: [];
        $payload = json_encode(array_merge($extras, [
            'phone'   => $phone,
            'message' => $messageBody,
        ]));
        $headers = ["Content-Type: application/json"];
        if (!empty($provider['api_key'])) {
            $headers[] = "Authorization: Bearer {$provider['api_key']}";
        }
        return _waHttpPost($url, $payload, $headers);
    }

    return ['success' => false, 'error' => "Unknown API type: {$apiType}"];
}

// Internal cURL helper — not called directly outside this file.
function _waHttpPost($url, $payload, $headers = [], $basicAuth = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($basicAuth) {
        curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
    }
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => "Network error: {$curlError}"];
    }
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'error' => null];
    }
    $decoded = json_decode($response, true);
    $errMsg  = $decoded['error']['message'] ?? $decoded['message'] ?? $decoded['errorMessage'] ?? "HTTP {$httpCode}";
    return ['success' => false, 'error' => $errMsg];
}

function timeAgo($datetime) {
    if (empty($datetime)) return '';
    $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) { $m = floor($diff / 60); return $m . ' min' . ($m > 1 ? 's' : '') . ' ago'; }
    if ($diff < 86400) { $h = floor($diff / 3600); return $h . ' hour' . ($h > 1 ? 's' : '') . ' ago'; }
    if ($diff < 604800) { $d = floor($diff / 86400); return $d . ' day' . ($d > 1 ? 's' : '') . ' ago'; }
    return date('d M Y', $ts);
}

// Multi-currency: detect the visitor's country from their IP (cached per-session so we only call the
// lookup service once per visitor). Returns the raw country name reported by the lookup, or null if it
// could not be determined (private/local IP, lookup unreachable, etc).
function getVisitorCountryName() {
    if (array_key_exists('detected_country_name', $_SESSION)) return $_SESSION['detected_country_name'];

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);

    $countryName = null;
    if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $url = "http://ip-api.com/json/{$ip}?fields=status,country";
        $resp = false;
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 2]);
                $resp = curl_exec($ch);
                curl_close($ch);
            } elseif (ini_get('allow_url_fopen')) {
                $ctx = stream_context_create(['http' => ['timeout' => 2]]);
                $resp = @file_get_contents($url, false, $ctx);
            }
        } catch (Exception $e) {
            $resp = false;
        }

        if ($resp) {
            $data = json_decode($resp, true);
            if (!empty($data['status']) && $data['status'] === 'success' && !empty($data['country'])) {
                $countryName = $data['country'];
            }
        }
    }

    $_SESSION['detected_country_name'] = $countryName;
    return $countryName;
}

// Matches a free-text detected country name against our supported registration list.
function matchSupportedCountry($countryName, $countryCurrencyMap) {
    if (!$countryName) return null;
    foreach ($countryCurrencyMap as $name => $info) {
        if (strcasecmp($name, $countryName) === 0) return $name;
    }
    if (stripos($countryName, 'emirates') !== false || strcasecmp($countryName, 'UAE') === 0) return 'United Arab Emirates';
    return null;
}

// ---------------------------------------------------------------------------
// PLATFORM SETTINGS (Super Admin controlled feature flags)
// ---------------------------------------------------------------------------
function getPlatformSetting($conn, $key, $default = null) {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $conn->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    $cache[$key] = ($val !== false) ? $val : $default;
    return $cache[$key];
}

function setPlatformSetting($conn, $key, $value) {
    $conn->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
         ->execute([$key, $value]);
}

// ---------------------------------------------------------------------------
// EMAIL VERIFICATION
// ---------------------------------------------------------------------------
function generateVerificationToken() {
    return bin2hex(random_bytes(32));
}

// Uses PHP's native mail(); on hosts where outbound mail isn't configured this will simply return
// false and the caller degrades gracefully (the account still exists, just unverified until resend
// succeeds or a Super Admin manually verifies them from the Agencies tab).
function sendAppEmail($to, $subject, $htmlBody) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Travio <no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'travioerp.com') . ">\r\n";
    try {
        return @mail($to, $subject, $htmlBody, $headers);
    } catch (Exception $e) {
        return false;
    }
}

// ---------------------------------------------------------------------------
// SHARED TRAVIO EMAIL TEMPLATE
// Wraps any inner content block in the standard Travio header/footer shell
// so every outgoing email (password reset, verification, and any future
// ones) shares consistent, on-brand styling. Table-based layout with inline
// styles for maximum compatibility across email clients (incl. Outlook).
// ---------------------------------------------------------------------------
function buildTravioEmailShell($innerHtml) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'travioerp.com');
    $year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body, table, td { font-family: 'Segoe UI', Helvetica, Arial, sans-serif; }
  body { margin:0; padding:0; background-color:#F4F6F8; }
  img { border:0; display:block; }
  a { text-decoration:none; }
  @media only screen and (max-width:600px) {
    .email-container { width:100% !important; }
    .email-padding { padding-left:22px !important; padding-right:22px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:#F4F6F8;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F4F6F8;padding:32px 16px;">
    <tr>
      <td align="center">

        <!-- Card -->
        <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background-color:#FFFFFF;border:1px solid #E4E7EC;border-radius:12px;overflow:hidden;">
          <!-- Header -->
          <tr>
            <td class="email-padding" style="padding:26px 36px;border-bottom:1px solid #EEF0F3;">
              <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="width:38px;height:38px;">
                    <img src="{$baseUrl}/assets/favicon.png" width="38" height="38" alt="Travio" style="display:block;border-radius:10px;">
                  </td>
                  <td style="padding-left:12px;">
                    <div style="font-size:19px;font-weight:700;color:#1A202C;line-height:1.3;">Travio</div>
                    <div style="font-size:10.5px;font-weight:600;color:#8A94A3;letter-spacing:0.4px;text-transform:uppercase;">Travel Agency Management</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <!-- Content -->
          <tr>
            <td class="email-padding" style="padding:36px;">
              {$innerHtml}
            </td>
          </tr>
        </table>

        <!-- Footer -->
        <table role="presentation" class="email-container" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;margin-top:24px;">
          <tr>
            <td align="center" class="email-padding" style="padding:0 36px;">
              <div style="font-size:13px;font-weight:700;color:#4A5568;margin:0 0 6px;">Travio - Travel Agency Management</div>
              <div style="font-size:12px;color:#8A94A3;line-height:1.6;margin:0 0 18px;">
                Diganta Khawja Shopping Centre (2nd Floor), Shop No-32,<br>Bahaddarhat, Chittagong, Bangladesh, 4212
              </div>
              <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 18px;">
                <tr>
                  <td style="padding:0 6px;"><a href="https://facebook.com/travioerp"><img src="{$baseUrl}/assets/icon-facebook.png" width="32" height="32" alt="Facebook"></a></td>
                  <td style="padding:0 6px;"><a href="https://youtube.com/@travioerp"><img src="{$baseUrl}/assets/icon-youtube.png" width="32" height="32" alt="YouTube"></a></td>
                  <td style="padding:0 6px;"><a href="https://instagram.com/travioerp"><img src="{$baseUrl}/assets/icon-instagram.png" width="32" height="32" alt="Instagram"></a></td>
                </tr>
              </table>
              <div style="font-size:11px;color:#B0B7C3;margin:0 0 24px;">&copy; {$year} Travio. All rights reserved.</div>
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

function sendPasswordResetEmail($email, $name, $token) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $link = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/reset-password?token=' . $token;
    $subject = "Reset Your Password - Travio";
    $content = "<h2 style='margin:0 0 14px;font-size:20px;font-weight:700;color:#1A202C;'>Password Reset Request</h2>"
          . "<p style='margin:0 0 24px;font-size:14px;line-height:1.7;color:#5A6472;'>Hi " . htmlspecialchars($name) . ", we received a request to reset your Travio password. Click the button below to set a new password. This link expires in <strong style='color:#1A202C;'>1 hour</strong>.</p>"
          . "<table role='presentation' cellpadding='0' cellspacing='0' style='margin:0 0 28px;'><tr><td style='background-color:#2BC4B0;border-radius:8px;'>"
          . "<a href=\"$link\" style='display:inline-block;padding:13px 28px;font-size:14px;font-weight:700;color:#ffffff;'>Reset My Password</a>"
          . "</td></tr></table>"
          . "<p style='margin:0 0 6px;font-size:12px;color:#8A94A3;'>Or copy this link into your browser:</p>"
          . "<p style='margin:0 0 24px;font-size:12px;color:#8A94A3;word-break:break-all;'>$link</p>"
          . "<p style='margin:0;padding-top:20px;border-top:1px solid #EEF0F3;font-size:12px;color:#8A94A3;'>If you didn't request this, you can safely ignore this email. Your password will not change.</p>";
    return sendAppEmail($email, $subject, buildTravioEmailShell($content));
}

function sendVerificationEmail($email, $name, $token) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $link = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/verify-email?token=' . $token;
    $subject = "Verify Your Email - Travio";
    $content = "<h2 style='margin:0 0 14px;font-size:20px;font-weight:700;color:#1A202C;'>Verify Your Email Address</h2>"
          . "<p style='margin:0 0 24px;font-size:14px;line-height:1.7;color:#5A6472;'>Hi " . htmlspecialchars($name) . ", thanks for registering with Travio. Please confirm your email address to activate your account.</p>"
          . "<table role='presentation' cellpadding='0' cellspacing='0' style='margin:0 0 28px;'><tr><td style='background-color:#2BC4B0;border-radius:8px;'>"
          . "<a href=\"$link\" style='display:inline-block;padding:13px 28px;font-size:14px;font-weight:700;color:#ffffff;'>Verify Email</a>"
          . "</td></tr></table>"
          . "<p style='margin:0 0 6px;font-size:12px;color:#8A94A3;'>Or copy this link into your browser:</p>"
          . "<p style='margin:0;font-size:12px;color:#8A94A3;word-break:break-all;'>$link</p>";
    return sendAppEmail($email, $subject, buildTravioEmailShell($content));
}

// ---------------------------------------------------------------------------
// TWO-FACTOR AUTHENTICATION (TOTP, RFC 6238 - Google Authenticator / Authy compatible)
// ---------------------------------------------------------------------------
function generateTotpSecret($length = 16) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, 31)];
    }
    return $secret;
}

function totpBase32Decode($secret) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
    $bits = '';
    for ($i = 0; $i < strlen($secret); $i++) {
        $bits .= str_pad(decbin(strpos($alphabet, $secret[$i])), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $bytes .= chr(bindec(substr($bits, $i, 8)));
    }
    return $bytes;
}

function totpGenerateCode($secret, $timeSlice = null) {
    if ($timeSlice === null) $timeSlice = floor(time() / 30);
    $key = totpBase32Decode($secret);
    $time = str_pad(pack('N', $timeSlice), 8, chr(0), STR_PAD_LEFT);
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord($hash[19]) & 0xf;
    $code = ((ord($hash[$offset]) & 0x7f) << 24)
          | ((ord($hash[$offset + 1]) & 0xff) << 16)
          | ((ord($hash[$offset + 2]) & 0xff) << 8)
          | (ord($hash[$offset + 3]) & 0xff);
    return str_pad($code % 1000000, 6, '0', STR_PAD_LEFT);
}

// Allows a +/-1 time-step tolerance (90 seconds total) to absorb minor clock drift between the
// server and the user's phone.
function verifyTotpCode($secret, $code) {
    $code = preg_replace('/\s+/', '', (string)$code);
    if (!preg_match('/^\d{6}$/', $code)) return false;
    $currentSlice = floor(time() / 30);
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(totpGenerateCode($secret, $currentSlice + $i), $code)) return true;
    }
    return false;
}

function getTotpQrUrl($secret, $accountLabel, $issuer = 'South Zone ERP') {
    $otpauth = 'otpauth://totp/' . rawurlencode($issuer . ':' . $accountLabel) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($otpauth);
}

function getDownloadReportTypes($modules) {
    $types = [
        'sales' => ['label' => 'Sales Report', 'kind' => 'sales'],
        'queries_all' => ['label' => 'All Queries Report', 'kind' => 'queries']
    ];

    $categories = $modules['enquiries']['fields']['category']['options'] ?? [];
    foreach ($categories as $category) {
        $types['query:' . $category] = [
            'label' => $category . ' Query Report',
            'kind' => 'queries',
            'category' => $category
        ];
    }
    return $types;
}

function reportMoney($value, $currencySymbol = '') {
    if ($value === '' || $value === null) return '-';
    $formatted = number_format((float)$value, 2);
    return $currencySymbol ? $currencySymbol . ' ' . $formatted : $formatted;
}

function reportServiceDescription($module, $row) {
    $parts = [];
    foreach ($module['fields'] as $field => $config) {
        if (in_array($field, ['name', 'mobile', 'service_cost', 'selling_price', 'status'])) continue;
        if (isset($row[$field]) && $row[$field] !== '') {
            $parts[] = $config['label'] . ': ' . $row[$field];
        }
    }
    return $parts ? implode(', ', array_slice($parts, 0, 3)) : '-';
}

function buildDownloadReport($conn, $modules, $agency_id, $report_type, $from_date, $to_date, $currencySymbol = '') {
    $types = getDownloadReportTypes($modules);
    if (!isset($types[$report_type])) $report_type = 'sales';
    $type = $types[$report_type];
    $is_staff = !empty($_SESSION['is_staff']);
    $staff_id = $_SESSION['staff_id'] ?? null;

    if ($type['kind'] === 'sales') {
        $columns = ['Date', 'Module', 'Record ID', 'Customer', 'Mobile', 'Description', 'Status', 'Sale', 'Cost', 'Profit', 'Reference Staff'];
        $rows = [];
        $totals = ['sale' => 0, 'cost' => 0, 'profit' => 0];

        foreach ($modules as $table => $module) {
            if (empty($module['is_service'])) continue;
            $staffFilter = $is_staff ? " AND t.reference_staff_id = ?" : "";
            $params = [$agency_id, $from_date, $to_date];
            if ($is_staff) $params[] = $staff_id;

            $stmt = $conn->prepare("SELECT t.*, s.full_name as reference_name FROM $table t LEFT JOIN staff s ON t.reference_staff_id = s.id WHERE t.agency_id = ? AND t.transaction_date BETWEEN ? AND ? $staffFilter ORDER BY t.transaction_date DESC");
            $stmt->execute($params);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $sale = (float)($row['selling_price'] ?? 0);
                $cost = (float)($row['service_cost'] ?? 0);
                $profit = $sale - $cost;
                $totals['sale'] += $sale;
                $totals['cost'] += $cost;
                $totals['profit'] += $profit;

                $rows[] = [
                    'Date' => date('Y-m-d', strtotime($row['transaction_date'])),
                    'Module' => $module['title'],
                    'Record ID' => $row['id'],
                    'Customer' => $row['name'] ?? '-',
                    'Mobile' => $row['mobile'] ?? '-',
                    'Description' => reportServiceDescription($module, $row),
                    'Status' => $row['status'] ?? '-',
                    'Sale' => reportMoney($sale, $currencySymbol),
                    'Cost' => reportMoney($cost, $currencySymbol),
                    'Profit' => reportMoney($profit, $currencySymbol),
                    'Reference Staff' => $row['reference_name'] ?: 'System'
                ];
            }
        }

        usort($rows, function($a, $b) { return strcmp($b['Date'], $a['Date']); });
        return [
            'title' => $type['label'],
            'subtitle' => 'Completed and in-progress sales from all service modules',
            'columns' => $columns,
            'rows' => $rows,
            'totals' => [
                'Total Sale' => reportMoney($totals['sale'], $currencySymbol),
                'Total Cost' => reportMoney($totals['cost'], $currencySymbol),
                'Net Profit' => reportMoney($totals['profit'], $currencySymbol)
            ]
        ];
    }

    $columns = ['Date', 'Category', 'Query ID', 'Customer', 'Mobile', 'Service Details', 'Status', 'Notes', 'Reference Staff'];
    $rows = [];
    $categoryFilter = isset($type['category']) ? " AND t.category = ?" : "";
    $staffFilter = $is_staff ? " AND t.reference_staff_id = ?" : "";
    $params = [$agency_id, $from_date, $to_date];
    if (isset($type['category'])) $params[] = $type['category'];
    if ($is_staff) $params[] = $staff_id;

    $stmt = $conn->prepare("SELECT t.*, s.full_name as reference_name FROM enquiries t LEFT JOIN staff s ON t.reference_staff_id = s.id WHERE t.agency_id = ? AND t.date BETWEEN ? AND ? $categoryFilter $staffFilter ORDER BY t.date DESC, t.id DESC");
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rows[] = [
            'Date' => $row['date'],
            'Category' => $row['category'],
            'Query ID' => $row['id'],
            'Customer' => $row['customer'],
            'Mobile' => $row['mobile'],
            'Service Details' => $row['service'],
            'Status' => $row['status'],
            'Notes' => $row['notes'] ?: '-',
            'Reference Staff' => $row['reference_name'] ?: 'System'
        ];
    }

    return [
        'title' => $type['label'],
        'subtitle' => isset($type['category']) ? 'Filtered query history for ' . $type['category'] : 'All enquiry and lead records',
        'columns' => $columns,
        'rows' => $rows,
        'totals' => ['Total Queries' => count($rows)]
    ];
}

