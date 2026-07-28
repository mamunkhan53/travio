<?php
        // Generic CRUD for SaaS Modules
        if (in_array($action, ['add', 'edit']) && isset($_POST['table']) && isset($_SESSION['agency_id'])) {
            $table = $_POST['table'];
            $agency_id = $_SESSION['agency_id'];

            if (isAgencySubscriptionExpired($conn, $agency_id)) {
                flash("Your subscription has expired. Please renew your plan to add or edit records.", "error");
                redirect("?route=app&page=dashboard");
            }
            
            // Backend Permission Checking
            if ($table === 'customers') {
                if (!has_permission('can_manage_customers')) { http_response_code(403); die("403 Access Denied: You do not have permission to modify customers."); }
            } elseif ($table === 'enquiries') {
                if ($action === 'add' && !has_permission('can_add_enquiry')) { http_response_code(403); die("403 Access Denied"); }
                if ($action === 'edit' && !has_permission('can_edit_enquiry')) { http_response_code(403); die("403 Access Denied"); }
            } elseif (in_array($table, ['passports', 'visas', 'tickets', 'umrah', 'tours'])) {
                if ($action === 'add' && !has_permission('can_add_sale')) { http_response_code(403); die("403 Access Denied"); }
                if ($action === 'edit' && !has_permission('can_edit_sale')) { http_response_code(403); die("403 Access Denied"); }
            }
            
            if (array_key_exists($table, $modules)) {
                $fieldsConfig = $modules[$table]['fields'];
                $postData = $_POST;
                $status = $_POST['status'] ?? 'Pending';
                $historyTables = ['enquiries', 'passports', 'visas', 'tickets', 'umrah', 'tours'];
                
                $ref_staff_id = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : (!empty($_POST['reference_staff_id']) ? $_POST['reference_staff_id'] : null);
                $id_to_use = null;
                
                if ($action === 'add') {
                    $prefix = $modules[$table]['prefix'] ?? 'ID';
                    $newId = generateSerialId($conn, $table, $prefix, $agency_id);
                    $id_to_use = $newId;
                    
                    $columns = ['id', 'agency_id'];
                    $placeholders = ['?', '?'];
                    $values = [$newId, $agency_id];
                    
                    if ($table !== 'customers') {
                        $columns[] = 'reference_staff_id';
                        $placeholders[] = '?';
                        $values[] = $ref_staff_id;
                        
                        if ($_SESSION['is_staff']) {
                            $columns[] = 'created_by_staff_id';
                            $placeholders[] = '?';
                            $values[] = $_SESSION['staff_id'];
                        }
                    }
                    
                    foreach ($fieldsConfig as $col => $config) {
                        if (isset($postData[$col])) {
                            $columns[] = $col;
                            $placeholders[] = '?';
                            $values[] = $postData[$col];
                        }
                    }
                    
                    $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $conn->prepare($sql)->execute($values);

                    if (in_array($table, $historyTables)) {
                        $creatorFollowup = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
                        $recordTypeLabel = $table === 'enquiries' ? 'Query' : 'Sale';
                        $conn->prepare("INSERT INTO record_followups (agency_id, module_name, record_id, staff_id, note) VALUES (?, ?, ?, ?, ?)")
                             ->execute([$agency_id, $table, $newId, $creatorFollowup, "$recordTypeLabel record created."]);
                    }
                    
                    // Hook: Auto-Create Lead in CRM if it's a Service
                    if (isset($modules[$table]['is_service'])) {
                        $leadId = generateSerialId($conn, 'enquiries', 'LD', $agency_id);
                        $leadCat = $modules[$table]['cat'];
                        $leadName = $postData['name'] ?? 'Unknown';
                        $leadMob = $postData['mobile'] ?? '';
                        $leadService = "Auto-generated from $table: $newId";
                        
                        $sqlLd = "INSERT INTO enquiries (id, agency_id, date, customer, mobile, category, service, status, notes, reference_staff_id";
                        $sqlLd .= $_SESSION['is_staff'] ? ", created_by_staff_id) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)" : ") VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)";
                        
                        $ldVals = [$leadId, $agency_id, $leadName, $leadMob, $leadCat, $leadService, $status, 'System Generated', $ref_staff_id];
                        if ($_SESSION['is_staff']) $ldVals[] = $_SESSION['staff_id'];
                        
                        $conn->prepare($sqlLd)->execute($ldVals);
                        $creatorFollowup = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
                        $conn->prepare("INSERT INTO record_followups (agency_id, module_name, record_id, staff_id, note) VALUES (?, 'enquiries', ?, ?, ?)")
                             ->execute([$agency_id, $leadId, $creatorFollowup, "Query auto-created from {$modules[$table]['title']} sale: $newId."]);
                    }
                    flash("Record added successfully!");
                } 
                elseif ($action === 'edit') {
                    $id = $_POST['id'];
                    $id_to_use = $id;
                    $oldRecord = null;
                    if (in_array($table, $historyTables)) {
                        $stmtOld = $conn->prepare("SELECT * FROM $table WHERE id = ? AND agency_id = ?");
                        $stmtOld->execute([$id, $agency_id]);
                        $oldRecord = $stmtOld->fetch(PDO::FETCH_ASSOC);
                    }
                    $setClause = [];
                    $values = [];
                    
                    foreach ($fieldsConfig as $col => $config) {
                        if (isset($postData[$col])) {
                            $setClause[] = "$col = ?";
                            $values[] = $postData[$col];
                        }
                    }
                    
                    if ($table !== 'customers') {
                        if (!$_SESSION['is_staff']) {
                            $setClause[] = "reference_staff_id = ?";
                            $values[] = $ref_staff_id;
                        } else {
                            $setClause[] = "updated_by_staff_id = ?";
                            $values[] = $_SESSION['staff_id'];
                        }
                    }
                    
                    $values[] = $agency_id; 
                    $values[] = $id;
                    
                    $sql = "UPDATE $table SET " . implode(', ', $setClause) . " WHERE agency_id = ? AND id = ?";
                    $conn->prepare($sql)->execute($values);

                    if ($oldRecord) {
                        $changeLines = [];
                        foreach ($fieldsConfig as $col => $config) {
                            if (isset($postData[$col])) {
                                $oldVal = (string)($oldRecord[$col] ?? '');
                                $newVal = (string)$postData[$col];
                                if ($oldVal !== $newVal) {
                                    $oldText = $oldVal === '' ? 'blank' : $oldVal;
                                    $newText = $newVal === '' ? 'blank' : $newVal;
                                    $changeLines[] = $config['label'] . ": $oldText -> $newText";
                                }
                            }
                        }
                        if (!$_SESSION['is_staff'] && (string)($oldRecord['reference_staff_id'] ?? '') !== (string)($ref_staff_id ?? '')) {
                            $oldRef = !empty($oldRecord['reference_staff_id']) ? 'Staff ID ' . $oldRecord['reference_staff_id'] : 'System / None';
                            $newRef = !empty($ref_staff_id) ? 'Staff ID ' . $ref_staff_id : 'System / None';
                            $changeLines[] = "Reference Staff: $oldRef -> $newRef";
                        }
                        $editorFollowup = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : null;
                        $changeNote = $changeLines ? "Record updated:\n- " . implode("\n- ", $changeLines) : "Record updated.";
                        $conn->prepare("INSERT INTO record_followups (agency_id, module_name, record_id, staff_id, note) VALUES (?, ?, ?, ?, ?)")
                             ->execute([$agency_id, $table, $id, $editorFollowup, $changeNote]);
                    }
                    flash("Record updated successfully!");
                }

                // ----------------------------------------------------
                // HOOK: Smart Deadline Notification Generator
                // ----------------------------------------------------
                if (in_array($table, ['passports', 'visas', 'tickets', 'umrah', 'tours'])) {
                    $deadlineField = null; $notifType = null;
                    if ($table === 'tickets') { $deadlineField = 'date'; $notifType = 'Flight Date'; }
                    elseif ($table === 'umrah') { $deadlineField = 'depDate'; $notifType = 'Umrah Departure'; }
                    elseif ($table === 'tours') { $deadlineField = 'date'; $notifType = 'Tour Departure'; }
                    elseif ($table === 'passports') { $deadlineField = 'appDate'; $notifType = 'Passport App/Delivery'; } 
                    
                    if ($deadlineField && !empty($postData[$deadlineField])) {
                        $deadline_date = $postData[$deadlineField];
                        // Notify exactly 1 day before
                        $notify_date = date('Y-m-d', strtotime($deadline_date . ' - 1 day'));
                        $cust_name = $postData['name'] ?? $postData['customer'] ?? 'Unknown';
                        
                        if (in_array($status, ['Cancelled', 'Lost'])) {
                            $conn->prepare("DELETE FROM service_notifications WHERE agency_id=? AND sale_id=?")->execute([$agency_id, $id_to_use]);
                        } else {
                            $exId = $conn->query("SELECT id FROM service_notifications WHERE agency_id=$agency_id AND sale_id='$id_to_use'")->fetchColumn();
                            if ($exId) {
                                $conn->prepare("UPDATE service_notifications SET deadline_date=?, notify_date=?, notification_type=?, customer_name=?, staff_id=?, is_read=0 WHERE id=?")->execute([$deadline_date, $notify_date, $notifType, $cust_name, $ref_staff_id, $exId]);
                            } else {
                                $conn->prepare("INSERT INTO service_notifications (agency_id, sale_id, module_name, customer_name, staff_id, notification_type, deadline_date, notify_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([$agency_id, $id_to_use, $table, $cust_name, $ref_staff_id, $notifType, $deadline_date, $notify_date]);
                            }
                        }
                    }
                }

                // Hook: Auto Create Customer
                if ($status === 'Completed' || $status === 'Paid' || $status === 'Confirmed') {
                    $mob = $postData['mobile'] ?? '';
                    $name = $postData['customer'] ?? $postData['name'] ?? 'Unknown';
                    
                    if (!empty($mob)) {
                        $chk = $conn->prepare("SELECT id FROM customers WHERE agency_id=? AND mobile=?");
                        $chk->execute([$agency_id, $mob]);
                        if (!$chk->fetch()) {
                            $newCustId = generateSerialId($conn, 'customers', 'CU', $agency_id);
                            $cat = $modules[$table]['cat'] ?? 'Lead Conversion';
                            $conn->prepare("INSERT INTO customers (id, agency_id, name, mobile, category) VALUES (?, ?, ?, ?, ?)")
                                 ->execute([$newCustId, $agency_id, $name, $mob, $cat]);
                        }
                    }
                }

                // -------------------------------------------------------
                // HOOK: WhatsApp Automation Triggers
                // Completely additive — no existing code paths are changed.
                // Each trigger checks independently; a failure in one does
                // not affect the others or the main save flow.
                // -------------------------------------------------------
                if (function_exists('triggerWhatsAppAutomation') && in_array($table, ['passports','visas','tickets','umrah','tours'])) {
                    $waBase = [
                        'phone'        => $postData['mobile'] ?? '',
                        'customer_name'=> $postData['name']   ?? $postData['customer'] ?? '',
                        'service_name' => $modules[$table]['title'] ?? $table,
                        'record_table' => $table,
                        'record_id'    => $id_to_use,
                    ];
                    $oldStatus = $oldRecord['status'] ?? null;
                    $statusChanged = ($action === 'add') || ($oldStatus !== $status);

                    // 1. Booking Confirmation
                    if ($status === 'Confirmed' && $statusChanged) {
                        triggerWhatsAppAutomation($conn, $agency_id, 'booking_confirmation', $waBase);
                    }

                    // 2. Customer Feedback (on completion, with configurable delay)
                    if (in_array($status, ['Completed','Paid']) && $statusChanged) {
                        triggerWhatsAppAutomation($conn, $agency_id, 'customer_feedback', $waBase);
                    }

                    // 3. Visa Status Update (any visa status change on edit)
                    if ($table === 'visas' && $action === 'edit' && $statusChanged) {
                        triggerWhatsAppAutomation($conn, $agency_id, 'visa_status_update', array_merge($waBase, [
                            'visa_country' => $postData['country'] ?? '',
                            'visa_status'  => $status,
                        ]));
                    }

                    // 4. Passport Ready
                    if ($table === 'passports' && in_array($status, ['Ready','Collected']) && $statusChanged) {
                        triggerWhatsAppAutomation($conn, $agency_id, 'passport_ready', array_merge($waBase, [
                            'passport_number' => $id_to_use,
                        ]));
                    }

                    // 5. Flight Reminder (queued: sends X days/hours before departure)
                    if ($table === 'tickets' && !empty($postData['date'])) {
                        $oldDate = $oldRecord['date'] ?? null;
                        if ($action === 'add' || $oldDate !== $postData['date']) {
                            triggerWhatsAppAutomation($conn, $agency_id, 'flight_reminder', array_merge($waBase, [
                                'flight_date' => date('d M Y', strtotime($postData['date'])),
                                'event_date'  => $postData['date'],
                            ]));
                        }
                    }
                }
            }
            redirect("?route=app&page=" . $table);
        }
