<?php
        if ($action === 'admin_action' && $_SESSION['role'] === 'Super Admin') {
            $agency_id = $_POST['agency_id'];
            $status = $_POST['status'];
            $conn->prepare("UPDATE agencies SET status = ? WHERE id = ?")->execute([$status, $agency_id]);
            $conn->prepare("UPDATE users SET status = ? WHERE agency_id = ?")->execute([$status, $agency_id]);
            flash("Agency status updated to $status.");
            redirect("?route=admin_dashboard");
        }

        // ------------- SUPER ADMIN: CHANGE AN AGENCY'S LOGIN EMAIL / PASSWORD / VERIFIED STATE -------------
        if ($action === 'update_agency_login' && $_SESSION['role'] === 'Super Admin') {
            $targetUserId = $_POST['user_id'];
            $newEmail = trim($_POST['email'] ?? '');
            $emailVerifiedFlag = isset($_POST['email_verified']) ? 1 : 0;

            try {
                $sql = "UPDATE users SET email = ?, email_verified = ?";
                $params = [$newEmail, $emailVerifiedFlag];
                if (!empty($_POST['new_password'])) {
                    $sql .= ", password_hash = ?";
                    $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ? AND role = 'Agency Admin'";
                $params[] = $targetUserId;
                $conn->prepare($sql)->execute($params);

                // Keep the agency's own contact email in sync, same as at registration time
                $conn->prepare("UPDATE agencies SET company_email = ? WHERE id = (SELECT agency_id FROM users WHERE id = ?)")->execute([$newEmail, $targetUserId]);

                flash("Login details updated.");
            } catch (Exception $e) {
                flash("Could not update login - that email may already be in use.", "error");
            }
            redirect("?route=admin_dashboard");
        }

        // ------------- SUPER ADMIN: MANAGE AN AGENCY'S SUBSCRIPTION -------------
        if ($action === 'manage_subscription' && $_SESSION['role'] === 'Super Admin') {
            $agency_id = $_POST['agency_id'];
            $plan_key = $_POST['plan_key'];
            $plans = getSubscriptionPlans($conn);
            $planLabel = ucfirst($plan_key);
            $customAmount = $_POST['custom_amount'] !== '' ? (float)$_POST['custom_amount'] : (isset($plans[$plan_key]) ? (float)$plans[$plan_key]['price'] : 0);
            $expiresAt = !empty($_POST['expires_at']) ? date('Y-m-d 23:59:59', strtotime($_POST['expires_at'])) : null;
            $notes = trim($_POST['admin_notes'] ?? '');

            $conn->prepare("UPDATE agencies SET subscription_plan = ?, subscription_amount = ?, subscription_expires_at = ?, subscription_started_at = NOW(), subscription_notes = ? WHERE id = ?")
                 ->execute([$planLabel, $customAmount, $expiresAt, $notes, $agency_id]);

            // Optionally log a payment against this change (already confirmed by Super Admin, so mark it Approved directly)
            $payAmount = (float)($_POST['payment_amount'] ?? 0);
            if ($payAmount > 0) {
                $conn->prepare("INSERT INTO subscription_payments (agency_id, plan_key, amount, method, reference, note, new_expiry_at, recorded_by, status, reviewed_by, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Approved', ?, NOW())")
                     ->execute([$agency_id, $plan_key, $payAmount, $_POST['payment_method'] ?? null, $_POST['payment_reference'] ?? null, $_POST['payment_note'] ?? null, $expiresAt, $_SESSION['role'], $_SESSION['role']]);
                $conn->prepare("UPDATE agencies SET last_payment_at = NOW(), last_payment_amount = ?, last_payment_method = ?, last_payment_reference = ? WHERE id = ?")
                     ->execute([$payAmount, $_POST['payment_method'] ?? null, $_POST['payment_reference'] ?? null, $agency_id]);
            }

            flash("Subscription updated successfully.");
            redirect("?route=admin_dashboard&tab=agencies");
        }

        // ------------- SUPER ADMIN: EDIT A SUBSCRIPTION PACKAGE (Trial / Monthly / Yearly) -------------
        if ($action === 'save_plan' && $_SESSION['role'] === 'Super Admin') {
            $conn->prepare("UPDATE subscription_plans SET name = ?, price = ?, price_usd = ?, duration_days = ?, is_active = ?, terms = ? WHERE plan_key = ?")
                 ->execute([
                     $_POST['name'],
                     (float)$_POST['price'],
                     (float)($_POST['price_usd'] ?? 0),
                     (int)$_POST['duration_days'],
                     isset($_POST['is_active']) ? 1 : 0,
                     $_POST['terms'] ?? '',
                     $_POST['plan_key']
                 ]);
            flash("Subscription package updated.");
            redirect("?route=admin_dashboard&tab=plans");
        }

        // ------------- SUPER ADMIN: EDIT A MANUAL PAYMENT METHOD (bKash / Nagad / Bank) -------------
        if ($action === 'save_payment_method' && $_SESSION['role'] === 'Super Admin') {
            $conn->prepare("UPDATE payment_methods SET display_name = ?, account_details = ?, instructions = ?, is_active = ? WHERE method_key = ?")
                 ->execute([
                     $_POST['display_name'],
                     $_POST['account_details'] ?? '',
                     $_POST['instructions'] ?? '',
                     isset($_POST['is_active']) ? 1 : 0,
                     $_POST['method_key']
                 ]);
            flash("Payment method updated.");
            redirect("?route=admin_dashboard&tab=payment_methods");
        }

        // ------------- SUPER ADMIN: APPROVE AN AGENCY-SUBMITTED RENEWAL PAYMENT -------------
        if ($action === 'approve_payment' && $_SESSION['role'] === 'Super Admin') {
            $payment_id = $_POST['payment_id'];
            $stmt = $conn->prepare("SELECT * FROM subscription_payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($payment && $payment['status'] === 'Pending') {
                $plans = getSubscriptionPlans($conn);
                $durationDays = isset($plans[$payment['plan_key']]) ? (int)$plans[$payment['plan_key']]['duration_days'] : 30;

                $stmt2 = $conn->prepare("SELECT subscription_expires_at FROM agencies WHERE id = ?");
                $stmt2->execute([$payment['agency_id']]);
                $currentExpiry = $stmt2->fetchColumn();
                // Extend from current expiry if still active, otherwise from today
                $base = ($currentExpiry && strtotime($currentExpiry) > time()) ? strtotime($currentExpiry) : time();
                $newExpiry = date('Y-m-d 23:59:59', strtotime("+$durationDays days", $base));

                $conn->prepare("UPDATE agencies SET subscription_plan = ?, subscription_amount = ?, subscription_expires_at = ?, last_payment_at = NOW(), last_payment_amount = ?, last_payment_method = ?, last_payment_reference = ? WHERE id = ?")
                     ->execute([ucfirst($payment['plan_key']), $payment['amount'], $newExpiry, $payment['amount'], $payment['method'], $payment['reference'], $payment['agency_id']]);

                $conn->prepare("UPDATE subscription_payments SET status = 'Approved', new_expiry_at = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                     ->execute([$newExpiry, $_SESSION['role'], $payment_id]);

                flash("Payment approved. Subscription extended to " . date('d M Y', strtotime($newExpiry)) . ".");
            } else {
                flash("This payment has already been reviewed.", "error");
            }
            redirect("?route=admin_dashboard&tab=payment_requests");
        }

        // ------------- SUPER ADMIN: DECLINE AN AGENCY-SUBMITTED RENEWAL PAYMENT -------------
        if ($action === 'decline_payment' && $_SESSION['role'] === 'Super Admin') {
            $payment_id = $_POST['payment_id'];
            $conn->prepare("UPDATE subscription_payments SET status = 'Declined', decline_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'Pending'")
                 ->execute([trim($_POST['decline_reason'] ?? ''), $_SESSION['role'], $payment_id]);
            flash("Payment request declined.");
            redirect("?route=admin_dashboard&tab=payment_requests");
        }

        // ------------- SUPER ADMIN: TOGGLE A PLATFORM-WIDE FEATURE FLAG -------------
        if ($action === 'save_platform_settings' && $_SESSION['role'] === 'Super Admin') {
            $allowedKeys = ['email_verification_required', 'agency_2fa_enabled'];
            $key = $_POST['setting_key'] ?? '';
            if (in_array($key, $allowedKeys)) {
                $value = ($_POST['setting_value'] ?? '0') === '1' ? '1' : '0';
                setPlatformSetting($conn, $key, $value);
                flash("Setting updated.");
            }
            redirect("?route=admin_dashboard&tab=settings");
        }

