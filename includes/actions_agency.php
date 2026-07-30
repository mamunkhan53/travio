<?php
        // ------------- AGENCY: SUBMIT A RENEWAL PAYMENT FOR SUPER ADMIN REVIEW -------------
        if ($action === 'submit_subscription_payment' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
            $agency_id = $_SESSION['agency_id'];
            $plan_key = $_POST['plan_key'];
            $method = trim($_POST['method'] ?? '');
            $reference = trim($_POST['reference'] ?? '');
            $note = trim($_POST['note'] ?? '');

            if (!in_array($plan_key, ['monthly', 'yearly']) || $method === '' || $reference === '') {
                flash("Please select a package, a payment method, and enter your transaction details.", "error");
                redirect("?route=app&page=subscription_payment");
            }

            $plans = getSubscriptionPlans($conn);
            $amount = isset($plans[$plan_key]) ? (float)$plans[$plan_key]['price'] : 0;

            $screenshot = null;
            if (!empty($_FILES['screenshot']['tmp_name'])) {
                $shotData = base64_encode(file_get_contents($_FILES['screenshot']['tmp_name']));
                $screenshot = 'data:' . $_FILES['screenshot']['type'] . ';base64,' . $shotData;
            }

            $conn->prepare("INSERT INTO subscription_payments (agency_id, plan_key, amount, method, reference, note, screenshot, status, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'agency')")
                 ->execute([$agency_id, $plan_key, $amount, $method, $reference, $note, $screenshot]);

            flash("Payment submitted! Our team will verify your transaction and activate your subscription shortly.");
            redirect("?route=app&page=subscription_payment");
        }

        // ------------- PROFILE & APP SETTINGS -------------
        if ($action === 'update_profile' && isset($_SESSION['agency_id'])) {
            if ($_SESSION['is_staff']) {
                $conn->prepare("UPDATE staff SET full_name = ?, phone = ?, email = ? WHERE id = ?")->execute([$_POST['full_name'], $_POST['phone'], $_POST['email'], $_SESSION['staff_id']]);
                if (!empty($_POST['new_password'])) {
                    $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $conn->prepare("UPDATE staff SET password_hash = ? WHERE id = ?")->execute([$hash, $_SESSION['staff_id']]);
                }
                flash("Profile updated successfully.");
                redirect("?route=app&page=dashboard");
            } else {
                $company = $_POST['company_name'];
                $address = $_POST['address'];
                $agency_id = $_SESSION['agency_id'];
                
                $logoQuery = ""; $params = [$company, $address];
                if (!empty($_FILES['logo']['tmp_name'])) {
                    $logoData = base64_encode(file_get_contents($_FILES['logo']['tmp_name']));
                    $logoSrc = 'data:' . $_FILES['logo']['type'] . ';base64,' . $logoData;
                    $logoQuery = ", logo = ?"; $params[] = $logoSrc;
                }
                $params[] = $agency_id;
                
                $conn->prepare("UPDATE agencies SET company_name = ?, address = ? $logoQuery WHERE id = ?")->execute($params);
                $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?")->execute([$_POST['full_name'], $_POST['phone'], $_SESSION['user_id']]);
                
                if (!empty($_POST['new_password'])) {
                    $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $_SESSION['user_id']]);
                }
                flash("Agency Profile updated successfully.");
                redirect("?route=app&page=profile");
            }
        }

        // ------------- READ NOTIFICATION -------------
        if ($action === 'read_notification' && isset($_SESSION['agency_id'])) {
            $n_id = $_POST['notif_id'];
            $r_by = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
            $conn->prepare("UPDATE service_notifications SET is_read=1, read_by=?, read_at=NOW() WHERE id=? AND agency_id=?")->execute([$r_by, $n_id, $_SESSION['agency_id']]);
            flash("Notification marked as read.");
            redirect("?route=app&page=dashboard");
        }

        // ------------- ADD CUSTOMER FOLLOW UP -------------
        if ($action === 'add_followup' && isset($_SESSION['agency_id'])) {
            if (!has_permission('can_manage_customers')) {
                http_response_code(403); die("403 Access Denied: You do not have permission to modify customers.");
            }
            if (isAgencySubscriptionExpired($conn, $_SESSION['agency_id'])) {
                flash("Your subscription has expired. Please renew your plan to add follow-ups.", "error");
                redirect("?route=app&page=dashboard");
            }
            $c_id = $_POST['customer_id'];
            $st_id = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
            $f_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
            
            $conn->prepare("INSERT INTO customer_followups (agency_id, customer_id, staff_id, note, follow_up_date) VALUES (?, ?, ?, ?, ?)")->execute([$_SESSION['agency_id'], $c_id, $st_id, $_POST['note'], $f_date]);
            flash("Follow-up note added.");
            redirect("?route=app&page=customer_profile&id=$c_id");
        }

        // ------------- ADD QUERY / SALE FOLLOW UP -------------
        if ($action === 'add_record_followup' && isset($_SESSION['agency_id'])) {
            $allowedHistoryTables = ['enquiries', 'passports', 'visas', 'tickets', 'umrah', 'tours', 'invoices', 'sc_leads', 'sc_students'];
            $table = $_POST['table'] ?? '';
            $record_id = $_POST['record_id'] ?? '';

            if (!in_array($table, $allowedHistoryTables) || !array_key_exists($table, $modules) || empty($record_id)) {
                http_response_code(400); die("Invalid follow-up record.");
            }

            if (in_array($table, ['sc_leads','sc_students']) && !has_permission('can_manage_sc_leads')) {
                http_response_code(403); die("403 Access Denied");
            }
            if ($table === 'enquiries' && !has_permission('can_edit_enquiry')) {
                http_response_code(403); die("403 Access Denied");
            }
            if (!in_array($table, ['enquiries','sc_leads','sc_students']) && !has_permission('can_edit_sale')) {
                http_response_code(403); die("403 Access Denied");
            }
            if (isAgencySubscriptionExpired($conn, $_SESSION['agency_id'])) {
                flash("Your subscription has expired. Please renew your plan to add follow-ups.", "error");
                redirect("?route=app&page=dashboard");
            }

            $agency_id = $_SESSION['agency_id'];
            $check = $conn->prepare("SELECT reference_staff_id FROM $table WHERE id = ? AND agency_id = ?");
            $check->execute([$record_id, $agency_id]);
            $recordRef = $check->fetchColumn();
            if ($recordRef === false) {
                flash("Record not found.", "error");
                redirect("?route=app&page=$table");
            }
            if ($_SESSION['is_staff'] && (int)$recordRef !== (int)$_SESSION['staff_id']) {
                http_response_code(403); die("403 Access Denied");
            }

            $st_id = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
            $f_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
            $conn->prepare("INSERT INTO record_followups (agency_id, module_name, record_id, staff_id, note, follow_up_date) VALUES (?, ?, ?, ?, ?, ?)")
                 ->execute([$agency_id, $table, $record_id, $st_id, $_POST['note'], $f_date]);

            // Hook: WhatsApp follow-up reminder automation (only when a future date is set)
            if (!empty($f_date) && $f_date >= date('Y-m-d') && function_exists('triggerWhatsAppAutomation')) {
                $nameCol  = $table === 'enquiries' ? 'customer' : 'name';
                $recRow   = $conn->prepare("SELECT mobile, $nameCol AS cust_name FROM $table WHERE id=? AND agency_id=?");
                $recRow->execute([$record_id, $agency_id]);
                $recRow = $recRow->fetch(PDO::FETCH_ASSOC);
                if ($recRow && !empty($recRow['mobile'])) {
                    triggerWhatsAppAutomation($conn, $agency_id, 'followup_reminder', [
                        'phone'        => $recRow['mobile'],
                        'customer_name'=> $recRow['cust_name'] ?? '',
                        'service_name' => $modules[$table]['title'] ?? $table,
                        'record_table' => $table,
                        'record_id'    => $record_id,
                        'event_date'   => $f_date,
                    ]);
                }
            }

            flash("Follow-up update added.");
            if (in_array($table, ['sc_leads','sc_students'])) {
                redirect("?route=app&page=sc_followups");
            }
            redirect("?route=app&page=query_history&table=$table&id=$record_id");
        }

        // SAVE STAFF (Admin Only)
        if ($action === 'save_staff' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
            if (isAgencySubscriptionExpired($conn, $_SESSION['agency_id'])) {
                flash("Your subscription has expired. Please renew your plan to manage staff.", "error");
                redirect("?route=app&page=dashboard");
            }
            $agency_id = $_SESSION['agency_id'];
            $id = $_POST['id'] ?? '';
            $full_name = $_POST['full_name'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $username = $_POST['username'];
            $role = $_POST['role'];
            $commission_rate = $_POST['commission_rate'] ?: 20.00;
            $status = $_POST['status'];
            
            // Permissions mapping
            $perms = [
                'can_add_enquiry' => isset($_POST['can_add_enquiry']) ? 1 : 0,
                'can_edit_enquiry' => isset($_POST['can_edit_enquiry']) ? 1 : 0,
                'can_delete_enquiry' => isset($_POST['can_delete_enquiry']) ? 1 : 0,
                'can_add_sale' => isset($_POST['can_add_sale']) ? 1 : 0,
                'can_edit_sale' => isset($_POST['can_edit_sale']) ? 1 : 0,
                'can_delete_sale' => isset($_POST['can_delete_sale']) ? 1 : 0,
                'can_add_expense' => isset($_POST['can_add_expense']) ? 1 : 0,
                'can_edit_expense' => isset($_POST['can_edit_expense']) ? 1 : 0,
                'can_delete_expense' => isset($_POST['can_delete_expense']) ? 1 : 0,
                'can_view_reports' => isset($_POST['can_view_reports']) ? 1 : 0,
                'can_manage_customers' => isset($_POST['can_manage_customers']) ? 1 : 0,
            ];

            try {
                $conn->beginTransaction();
                if (empty($id)) {
                    // Add
                    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO staff (agency_id, full_name, email, phone, username, password_hash, role, status, commission_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$agency_id, $full_name, $email, $phone, $username, $pass, $role, $status, $commission_rate]);
                    $staff_id = $conn->lastInsertId();
                    
                    $pSql = "INSERT INTO staff_permissions (staff_id, " . implode(',', array_keys($perms)) . ") VALUES (?, " . implode(',', array_fill(0, count($perms), '?')) . ")";
                    $pVals = array_merge([$staff_id], array_values($perms));
                    $conn->prepare($pSql)->execute($pVals);
                    flash("Staff added successfully.");
                } else {
                    // Edit
                    $updSql = "UPDATE staff SET full_name=?, email=?, phone=?, username=?, role=?, status=?, commission_rate=? WHERE id=? AND agency_id=?";
                    $updVals = [$full_name, $email, $phone, $username, $role, $status, $commission_rate, $id, $agency_id];
                    $conn->prepare($updSql)->execute($updVals);
                    
                    if (!empty($_POST['password'])) {
                        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $conn->prepare("UPDATE staff SET password_hash=? WHERE id=?")->execute([$pass, $id]);
                    }
                    
                    $pSql = "UPDATE staff_permissions SET " . implode('=?, ', array_keys($perms)) . "=? WHERE staff_id=?";
                    $pVals = array_merge(array_values($perms), [$id]);
                    $conn->prepare($pSql)->execute($pVals);
                    flash("Staff updated successfully.");
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollBack();
                flash("Error: Username or Email might exist.", "error");
            }
            redirect("?route=app&page=staff");
        }

        // SAVE INVOICE (Exact Original Restoration)
        if ($action === 'save_invoice' && isset($_SESSION['agency_id'])) {
            if (!has_permission('can_add_sale')) die("403 Access Denied");
            if (isAgencySubscriptionExpired($conn, $_SESSION['agency_id'])) {
                flash("Your subscription has expired. Please renew your plan to create invoices.", "error");
                redirect("?route=app&page=dashboard");
            }
            
            $agency_id = $_SESSION['agency_id'];
            $inv_no = generateInvoiceId($conn, $agency_id);
            
            // Safely inherit Reference Staff from matching sale via mobile (without altering UI workflow)
            $ref_id = null;
            if ($_SESSION['is_staff']) {
                $ref_id = $_SESSION['staff_id'];
            } else {
                $mobile = trim($_POST['mobile'] ?? '');
                if (!empty($mobile)) {
                    $tbls = ['passports', 'visas', 'tickets', 'umrah', 'tours'];
                    foreach ($tbls as $tbl) {
                        $stmtMatch = $conn->prepare("SELECT reference_staff_id FROM $tbl WHERE agency_id=? AND mobile=? AND reference_staff_id IS NOT NULL ORDER BY created_at DESC LIMIT 1");
                        $stmtMatch->execute([$agency_id, $mobile]);
                        $fRef = $stmtMatch->fetchColumn();
                        if ($fRef) {
                            $ref_id = $fRef;
                            break;
                        }
                    }
                }
            }

            $creator_id = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
            $invoice_id = uniqid('ID-');
            
            $sql = "INSERT INTO invoices (id, agency_id, invoice_number, issue_date, customer_name, mobile, email, service_desc, quantity, unit_price, subtotal, discount, tax, grand_total, paid_amount, due_amount, reference_staff_id, created_by_staff_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $invoice_id, $agency_id, $inv_no, $_POST['issue_date'], $_POST['customer_name'], 
                $_POST['mobile'], $_POST['email'], $_POST['service_desc'], $_POST['quantity'], $_POST['unit_price'], 
                $_POST['subtotal'], $_POST['discount'], $_POST['tax'], $_POST['grand_total'], $_POST['paid_amount'], $_POST['due_amount'],
                $ref_id, $creator_id
            ]);
            $conn->prepare("INSERT INTO record_followups (agency_id, module_name, record_id, staff_id, note) VALUES (?, 'invoices', ?, ?, ?)")
                 ->execute([$agency_id, $invoice_id, $creator_id, "Invoice $inv_no created."]);
            // Hook: WhatsApp invoice notification automation
            if (function_exists('triggerWhatsAppAutomation')) {
                triggerWhatsAppAutomation($conn, $agency_id, 'invoice_notification', [
                    'phone'          => trim($_POST['mobile'] ?? ''),
                    'customer_name'  => trim($_POST['customer_name'] ?? ''),
                    'service_name'   => trim($_POST['service_desc'] ?? ''),
                    'invoice_no'     => $inv_no,
                    'invoice_amount' => number_format((float)($_POST['grand_total'] ?? 0), 2),
                    'due_amount'     => number_format((float)($_POST['due_amount'] ?? 0), 2),
                    'due_date'       => !empty($_POST['issue_date']) ? date('d M Y', strtotime($_POST['issue_date'])) : '',
                    'record_table'   => 'invoices',
                    'record_id'      => $invoice_id,
                ]);
            }

            flash("Invoice $inv_no generated successfully.");
            redirect("?route=app&page=invoices");
        }

        // ------------- ACCOUNTING: SAVE (ADD/EDIT) MANUAL EXPENSE -------------
        // Additive-only module. Does NOT touch the existing Sales Net Profit logic anywhere else in the app;
        // this only inserts/updates rows in the new accounting_expenses table.
        if ($action === 'save_expense' && isset($_SESSION['agency_id'])) {
            $agency_id = $_SESSION['agency_id'];
            if (isAgencySubscriptionExpired($conn, $agency_id)) {
                flash("Your subscription has expired. Please renew your plan to manage expenses.", "error");
                redirect("?route=app&page=dashboard");
            }

            $isEdit = !empty($_POST['expense_id']);
            if ($isEdit && !has_permission('can_edit_expense')) { http_response_code(403); die("403 Access Denied: You do not have permission to edit expenses."); }
            if (!$isEdit && !has_permission('can_add_expense')) { http_response_code(403); die("403 Access Denied: You do not have permission to add expenses."); }

            $expDate = !empty($_POST['expense_date']) ? $_POST['expense_date'] : date('Y-m-d');
            $category = trim($_POST['category'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $method = trim($_POST['payment_method'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');

            if ($isEdit) {
                $updater_id = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
                $conn->prepare("UPDATE accounting_expenses SET expense_date=?, category=?, title=?, amount=?, payment_method=?, remarks=?, updated_by_staff_id=? WHERE id=? AND agency_id=?")
                     ->execute([$expDate, $category, $title, $amount, $method, $remarks, $updater_id, $_POST['expense_id'], $agency_id]);
                flash("Expense updated successfully.");
            } else {
                $newExpId = generateSerialId($conn, 'accounting_expenses', 'EX', $agency_id);
                $creator_id = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
                $conn->prepare("INSERT INTO accounting_expenses (id, agency_id, expense_date, category, title, amount, payment_method, remarks, created_by_staff_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                     ->execute([$newExpId, $agency_id, $expDate, $category, $title, $amount, $method, $remarks, $creator_id]);
                flash("Expense recorded successfully.");
            }

            $redirectQs = !empty($_POST['redirect_qs']) ? '&' . ltrim($_POST['redirect_qs'], '&') : '';
            redirect("?route=app&page=accounting" . $redirectQs);
        }

        // ------------- WHATSAPP AUTOMATION: SAVE SETTINGS (Agency Admin only) -------------
        if ($action === 'save_automation' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
            $agency_id = $_SESSION['agency_id'];
            if (isAgencySubscriptionExpired($conn, $agency_id)) {
                flash("Your subscription has expired.", "error");
                redirect("?route=app&page=whatsapp_automation");
            }

            $allowedTypes = ['booking_confirmation','payment_reminder','followup_reminder',
                             'flight_reminder','visa_status_update','passport_ready',
                             'invoice_notification','customer_feedback'];
            $autoType = $_POST['automation_type'] ?? '';
            if (!in_array($autoType, $allowedTypes)) {
                flash("Invalid automation type.", "error");
                redirect("?route=app&page=whatsapp_automation");
            }

            $isEnabled   = isset($_POST['is_enabled']) ? 1 : 0;
            $template    = trim($_POST['message_template'] ?? '');
            $sendTiming  = in_array($_POST['send_timing'] ?? '', ['immediately','before']) ? $_POST['send_timing'] : 'immediately';
            $timingValue = max(0, (int)($_POST['timing_value'] ?? 1));
            $timingUnit  = in_array($_POST['timing_unit'] ?? '', ['hours','days']) ? $_POST['timing_unit'] : 'days';

            if (empty($template)) {
                flash("Message template cannot be empty.", "error");
                redirect("?route=app&page=whatsapp_automation");
            }

            $conn->prepare(
                "INSERT INTO whatsapp_automations (agency_id, automation_type, is_enabled, message_template, send_timing, timing_value, timing_unit)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   is_enabled=VALUES(is_enabled), message_template=VALUES(message_template),
                   send_timing=VALUES(send_timing), timing_value=VALUES(timing_value), timing_unit=VALUES(timing_unit)"
            )->execute([$agency_id, $autoType, $isEnabled, $template, $sendTiming, $timingValue, $timingUnit]);

            flash("Automation settings saved for " . ucwords(str_replace('_', ' ', $autoType)) . ".");
            redirect("?route=app&page=whatsapp_automation");
        }

        // ------------- WHATSAPP AUTOMATION: TEST SEND (Agency Admin only) -------------
        if ($action === 'test_automation' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
            $agency_id = $_SESSION['agency_id'];
            $autoType  = $_POST['automation_type'] ?? '';
            $testPhone = preg_replace('/[^\d+]/', '', trim($_POST['test_phone'] ?? ''));
            $template  = trim($_POST['message_template'] ?? '');

            if (empty($testPhone) || empty($template)) {
                flash("A test phone number and a template are required.", "error");
                redirect("?route=app&page=whatsapp_automation");
            }

            // Build sample variable data
            $ag = $conn->prepare("SELECT company_name, company_phone FROM agencies WHERE id=?");
            $ag->execute([$agency_id]);
            $ag = $ag->fetch(PDO::FETCH_ASSOC) ?: [];
            $sampleData = [
                'customer_name'  => 'John Doe',
                'company_name'   => $ag['company_name'] ?? 'Your Company',
                'service_name'   => 'Air Ticket',
                'invoice_no'     => 'INV-0001',
                'invoice_amount' => '25,000',
                'due_amount'     => '5,000',
                'due_date'       => date('d M Y', strtotime('+7 days')),
                'flight_date'    => date('d M Y', strtotime('+3 days')),
                'flight_time'    => '10:30 AM',
                'visa_country'   => 'Saudi Arabia',
                'visa_status'    => 'Approved',
                'passport_number'=> 'P-123456',
                'office_phone'   => $ag['company_phone'] ?? '',
            ];
            $message = replaceWAVariables($template, $sampleData);

            $prov = $conn->prepare("SELECT * FROM whatsapp_providers WHERE agency_id=? AND is_active=1 ORDER BY updated_at DESC LIMIT 1");
            $prov->execute([$agency_id]);
            $provider = $prov->fetch(PDO::FETCH_ASSOC);

            if (!$provider) {
                flash("No active provider configured. Configure a provider in WhatsApp → Provider Settings first.", "error");
                redirect("?route=app&page=whatsapp_automation");
            }

            $result = sendWhatsAppViaProvider($provider, $testPhone, $message);
            if ($result['success']) {
                flash("Test message sent successfully to {$testPhone}.");
            } else {
                flash("Test failed: " . ($result['error'] ?? 'Unknown error'), "error");
            }
            redirect("?route=app&page=whatsapp_automation");
        }

        // ------------- WHATSAPP: SAVE PROVIDER SETTINGS (Agency Admin only) -------------
        if ($action === 'save_whatsapp_provider' && isset($_SESSION['agency_id']) && !$_SESSION['is_staff']) {
            $agency_id = $_SESSION['agency_id'];
            if (isAgencySubscriptionExpired($conn, $agency_id)) {
                flash("Your subscription has expired.", "error");
                redirect("?route=app&page=whatsapp&tab=settings");
            }

            $allowedTypes = ['meta_cloud', 'twilio', 'vonage', 'wati', 'custom_webhook'];
            $providerId  = (int)($_POST['provider_id'] ?? 0);
            $name        = trim($_POST['provider_name'] ?? 'My WhatsApp Provider');
            $apiType     = in_array($_POST['api_type'] ?? '', $allowedTypes) ? $_POST['api_type'] : 'custom_webhook';
            $endpoint    = trim($_POST['api_endpoint'] ?? '');
            $apiKey      = trim($_POST['api_key'] ?? '');
            $apiSecret   = trim($_POST['api_secret'] ?? '');
            $fromNumber  = trim($_POST['from_number'] ?? '');
            $extraParams = trim($_POST['extra_params'] ?? '');
            $isActive    = isset($_POST['is_active']) ? 1 : 0;

            // Validate extra_params JSON if supplied
            if (!empty($extraParams) && json_decode($extraParams) === null) {
                flash("Extra Parameters must be valid JSON (or leave blank).", "error");
                redirect("?route=app&page=whatsapp&tab=settings");
            }

            if ($providerId > 0) {
                $conn->prepare("UPDATE whatsapp_providers SET provider_name=?, api_type=?, api_endpoint=?, api_key=?, api_secret=?, from_number=?, extra_params=?, is_active=?, updated_at=NOW() WHERE id=? AND agency_id=?")
                     ->execute([$name, $apiType, $endpoint, $apiKey, $apiSecret, $fromNumber, $extraParams, $isActive, $providerId, $agency_id]);
            } else {
                $conn->prepare("INSERT INTO whatsapp_providers (agency_id, provider_name, api_type, api_endpoint, api_key, api_secret, from_number, extra_params, is_active) VALUES (?,?,?,?,?,?,?,?,?)")
                     ->execute([$agency_id, $name, $apiType, $endpoint, $apiKey, $apiSecret, $fromNumber, $extraParams, $isActive]);
            }

            flash("WhatsApp provider settings saved successfully.");
            redirect("?route=app&page=whatsapp&tab=settings");
        }

        // ------------- WHATSAPP: SEND MESSAGE -------------
        if ($action === 'send_whatsapp' && isset($_SESSION['agency_id'])) {
            $agency_id = $_SESSION['agency_id'];

            if (isAgencySubscriptionExpired($conn, $agency_id)) {
                flash("Your subscription has expired. Please renew your plan to send messages.", "error");
                redirect("?route=app&page=whatsapp");
            }
            if (!has_permission('can_send_whatsapp')) {
                http_response_code(403); die("403 Access Denied: You do not have permission to send WhatsApp messages.");
            }

            $messageBody = trim($_POST['message_body'] ?? '');
            $recipients  = $_POST['recipients'] ?? [];       // Array of customer IDs
            $sendToAll   = !empty($_POST['send_to_all']);

            if (empty($messageBody)) {
                flash("Message body cannot be empty.", "error");
                redirect("?route=app&page=whatsapp");
            }

            // ---- Resolve recipient list ----
            if ($sendToAll) {
                $stmt = $conn->prepare("SELECT id, name, mobile FROM customers WHERE agency_id = ? AND mobile IS NOT NULL AND mobile != '' ORDER BY name ASC");
                $stmt->execute([$agency_id]);
                $recipientRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                if (empty($recipients)) {
                    flash("Please select at least one recipient.", "error");
                    redirect("?route=app&page=whatsapp");
                }
                $placeholders = implode(',', array_fill(0, count($recipients), '?'));
                $stmt = $conn->prepare("SELECT id, name, mobile FROM customers WHERE agency_id = ? AND id IN ($placeholders)");
                $stmt->execute(array_merge([$agency_id], $recipients));
                $recipientRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if (empty($recipientRows)) {
                flash("No recipients found with valid phone numbers.", "error");
                redirect("?route=app&page=whatsapp");
            }

            // ---- Get active provider (if any) ----
            $provStmt = $conn->prepare("SELECT * FROM whatsapp_providers WHERE agency_id = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1");
            $provStmt->execute([$agency_id]);
            $activeProvider = $provStmt->fetch(PDO::FETCH_ASSOC);

            // ---- Create campaign log entry ----
            $logId          = generateSerialId($conn, 'whatsapp_message_logs', 'WA', $agency_id);
            $sentByType     = $_SESSION['is_staff'] ? 'staff' : 'admin';
            $sentByStaffId  = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
            $providerId     = $activeProvider ? $activeProvider['id'] : null;
            $overallStatus  = $activeProvider ? 'Processing' : 'No Provider';

            $conn->prepare("INSERT INTO whatsapp_message_logs (id, agency_id, provider_id, message_body, recipient_count, status, sent_by_type, sent_by_staff_id) VALUES (?,?,?,?,?,?,?,?)")
                 ->execute([$logId, $agency_id, $providerId, $messageBody, count($recipientRows), $overallStatus, $sentByType, $sentByStaffId]);

            // ---- Dispatch to each recipient ----
            $sentCount = 0; $failedCount = 0;
            foreach ($recipientRows as $r) {
                $phone     = preg_replace('/[^\d+]/', '', $r['mobile']); // keep digits and leading +
                $recStatus = 'Pending'; $recError = null; $recSentAt = null;

                if (!$activeProvider) {
                    $recStatus = 'No Provider';
                } elseif (empty($phone)) {
                    $recStatus = 'Failed'; $recError = 'Invalid/empty phone number'; $failedCount++;
                } else {
                    $result = sendWhatsAppViaProvider($activeProvider, $phone, $messageBody);
                    if ($result['success']) {
                        $recStatus = 'Sent'; $recSentAt = date('Y-m-d H:i:s'); $sentCount++;
                    } else {
                        $recStatus = 'Failed'; $recError = $result['error']; $failedCount++;
                    }
                }

                $conn->prepare("INSERT INTO whatsapp_message_recipients (log_id, agency_id, customer_id, customer_name, phone, status, error_message, sent_at) VALUES (?,?,?,?,?,?,?,?)")
                     ->execute([$logId, $agency_id, $r['id'], $r['name'], $r['mobile'], $recStatus, $recError, $recSentAt]);
            }

            // ---- Finalise log ----
            if (!$activeProvider) {
                $finalStatus = 'No Provider';
                $sentCount   = count($recipientRows); // all queued, none dispatched
            } else {
                $finalStatus = ($failedCount === 0) ? 'Sent' : ($sentCount === 0 ? 'Failed' : 'Partial');
            }

            $conn->prepare("UPDATE whatsapp_message_logs SET sent_count=?, failed_count=?, status=? WHERE id=?")
                 ->execute([$sentCount, $failedCount, $finalStatus, $logId]);

            if (!$activeProvider) {
                flash("Message logged ({$logId}) — no active provider configured. Set up a provider in WhatsApp → Settings to enable actual delivery.");
            } elseif ($failedCount > 0 && $sentCount === 0) {
                flash("All {$failedCount} message(s) failed to send. Check provider settings.", "error");
            } else {
                $msg = "Message sent to {$sentCount} recipient(s).";
                if ($failedCount > 0) $msg .= " {$failedCount} failed.";
                flash($msg);
            }

            redirect("?route=app&page=whatsapp&tab=history");
        }

        // ------------- STUDENT CONSULTANCY MODULE ACTIONS -------------
        require_once __DIR__ . '/sc_actions.php';

        // ------------- FINANCE & ACCOUNTING MODULE ACTIONS -------------
        require_once __DIR__ . '/acc_actions.php';

        // ------------- OCR DOCUMENT SCANNER MODULE ACTIONS -------------
        require_once __DIR__ . '/ocr_actions.php';

        // ------------- HAJJ & UMRAH MODULE ACTIONS -------------------
        require_once __DIR__ . '/umrah_actions.php';

        // ------------- STAFF MANAGEMENT MODULE ACTIONS ---------------
        require_once __DIR__ . '/staff_actions.php';

