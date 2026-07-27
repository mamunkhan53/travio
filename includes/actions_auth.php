<?php
        // ------------- AUTH ACTIONS -------------
        if ($action === 'login') {
            $email = $_POST['email'];
            $password = $_POST['password'];
            
            // Check Admin first
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'Active') {
                    flash("Your account is {$user['status']}. Please contact the Super Admin.", "error");
                    redirect("?route=login");
                }

                // Email verification gate (Super Admin's own account is exempt - it's a system account)
                $verificationRequired = getPlatformSetting($conn, 'email_verification_required', '0') === '1';
                if ($verificationRequired && $user['role'] !== 'Super Admin' && !$user['email_verified']) {
                    $_SESSION['unverified_email'] = $user['email'];
                    flash("Please verify your email address before logging in. Check your inbox, or use the resend link below.", "error");
                    redirect("?route=login");
                }

                // Two-Factor Authentication challenge (Super Admin always eligible; Agency Admin only
                // when a Super Admin has switched the "Allow 2FA for Agency Users" setting on)
                $agency2faOn = getPlatformSetting($conn, 'agency_2fa_enabled', '0') === '1';
                if ($user['totp_enabled'] && ($user['role'] === 'Super Admin' || $agency2faOn)) {
                    $_SESSION['pending_2fa_user_id'] = $user['id'];
                    redirect("?route=login&step=2fa");
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['agency_id'] = $user['agency_id'];
                $_SESSION['is_staff'] = false;
                
                if ($user['role'] === 'Super Admin') redirect("?route=admin_dashboard");
                else redirect("?route=app&page=dashboard");
            } else {
                // Check Staff table
                $stmt = $conn->prepare("SELECT * FROM staff WHERE (email = ? OR username = ?)");
                $stmt->execute([$email, $email]);
                $staff = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($staff && password_verify($password, $staff['password_hash'])) {
                    if ($staff['status'] !== 'Active') {
                        flash("Your staff account is inactive.", "error"); redirect("?route=login");
                    }

                    // Two-Factor Authentication challenge (same platform-wide switch that gates Agency Admin 2FA)
                    $agency2faOn = getPlatformSetting($conn, 'agency_2fa_enabled', '0') === '1';
                    if ($staff['totp_enabled'] && $agency2faOn) {
                        $_SESSION['pending_2fa_staff_id'] = $staff['id'];
                        redirect("?route=login&step=2fa");
                    }

                    $_SESSION['user_id'] = $staff['id'];
                    $_SESSION['staff_id'] = $staff['id'];
                    $_SESSION['role'] = 'Staff';
                    $_SESSION['staff_role'] = $staff['role'];
                    $_SESSION['agency_id'] = $staff['agency_id'];
                    $_SESSION['is_staff'] = true;
                    $_SESSION['commission_rate'] = $staff['commission_rate'];
                    
                    $pStmt = $conn->prepare("SELECT * FROM staff_permissions WHERE staff_id = ?");
                    $pStmt->execute([$staff['id']]);
                    $_SESSION['permissions'] = $pStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $conn->prepare("UPDATE staff SET last_login = NOW() WHERE id = ?")->execute([$staff['id']]);
                    
                    redirect("?route=app&page=dashboard");
                } else {
                    flash("Invalid credentials.", "error");
                    redirect("?route=login");
                }
            }
        }

        // ------------- COMPLETE LOGIN AFTER 2FA CODE ENTRY -------------
        if ($action === 'verify_2fa_login') {
            if (!empty($_SESSION['pending_2fa_staff_id'])) {
                // ---- Staff account completing 2FA ----
                $stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
                $stmt->execute([$_SESSION['pending_2fa_staff_id']]);
                $staff = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($staff && verifyTotpCode($staff['totp_secret'], $_POST['code'] ?? '')) {
                    unset($_SESSION['pending_2fa_staff_id']);
                    $_SESSION['user_id'] = $staff['id'];
                    $_SESSION['staff_id'] = $staff['id'];
                    $_SESSION['role'] = 'Staff';
                    $_SESSION['staff_role'] = $staff['role'];
                    $_SESSION['agency_id'] = $staff['agency_id'];
                    $_SESSION['is_staff'] = true;
                    $_SESSION['commission_rate'] = $staff['commission_rate'];

                    $pStmt = $conn->prepare("SELECT * FROM staff_permissions WHERE staff_id = ?");
                    $pStmt->execute([$staff['id']]);
                    $_SESSION['permissions'] = $pStmt->fetch(PDO::FETCH_ASSOC);

                    $conn->prepare("UPDATE staff SET last_login = NOW() WHERE id = ?")->execute([$staff['id']]);

                    redirect("?route=app&page=dashboard");
                } else {
                    flash("Incorrect authentication code. Please try again.", "error");
                    redirect("?route=login&step=2fa");
                }
            }

            if (empty($_SESSION['pending_2fa_user_id'])) {
                flash("Your session expired. Please log in again.", "error");
                redirect("?route=login");
            }
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['pending_2fa_user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && verifyTotpCode($user['totp_secret'], $_POST['code'] ?? '')) {
                unset($_SESSION['pending_2fa_user_id']);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['agency_id'] = $user['agency_id'];
                $_SESSION['is_staff'] = false;

                if ($user['role'] === 'Super Admin') redirect("?route=admin_dashboard");
                else redirect("?route=app&page=dashboard");
            } else {
                flash("Incorrect authentication code. Please try again.", "error");
                redirect("?route=login&step=2fa");
            }
        }

        // ------------- RESEND VERIFICATION EMAIL -------------
        if ($action === 'resend_verification') {
            $email = $_POST['email'] ?? '';
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = 'Agency Admin'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Always show the same message whether or not the account exists, so this can't be used
            // to probe which emails are registered.
            if ($user && !$user['email_verified']) {
                $newToken = generateVerificationToken();
                $conn->prepare("UPDATE users SET email_verification_token = ?, email_verification_sent_at = NOW() WHERE id = ?")
                     ->execute([$newToken, $user['id']]);
                sendVerificationEmail($user['email'], $user['full_name'], $newToken);
            }
            flash("If that email needs verifying, a new link has just been sent.");
            redirect("?route=login");
        }

        if ($action === 'register') {
            $company = $_POST['company_name'];
            $name = $_POST['full_name'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

            // Multi-currency: validate the submitted country against our supported list (never trust
            // raw POST data), then derive the agency's operating currency from it.
            $submittedCountry = $_POST['country'] ?? '';
            $country = array_key_exists($submittedCountry, $countryCurrencyMap) ? $submittedCountry : 'Bangladesh';
            $currencyInfo = $countryCurrencyMap[$country];

            // Admin approval is no longer required - new agencies are Active immediately. The only
            // remaining gate is email verification, and only when a Super Admin has turned it on.
            $verificationRequired = getPlatformSetting($conn, 'email_verification_required', '0') === '1';
            $emailVerified = $verificationRequired ? 0 : 1;
            $verificationToken = $verificationRequired ? generateVerificationToken() : null;

            try {
                $conn->beginTransaction();

                // Start every new agency on a free trial (duration controlled by Super Admin)
                $trialPlan = $conn->query("SELECT * FROM subscription_plans WHERE plan_key='trial'")->fetch(PDO::FETCH_ASSOC);
                $trialDays = $trialPlan ? (int)$trialPlan['duration_days'] : 30;
                $trialExpires = date('Y-m-d H:i:s', strtotime("+$trialDays days"));

                $stmt = $conn->prepare("INSERT INTO agencies (company_name, company_email, company_phone, status, subscription_plan, subscription_amount, subscription_started_at, subscription_expires_at, trial_ends_at, country, currency_code, currency_symbol) VALUES (?, ?, ?, 'Active', 'Trial', 0, NOW(), ?, ?, ?, ?, ?)");
                $stmt->execute([$company, $email, $phone, $trialExpires, $trialExpires, $country, $currencyInfo['code'], $currencyInfo['symbol']]);
                $agency_id = $conn->lastInsertId();
                
                $stmt = $conn->prepare("INSERT INTO users (agency_id, full_name, email, phone, password_hash, role, status, email_verified, email_verification_token, email_verification_sent_at) VALUES (?, ?, ?, ?, ?, 'Agency Admin', 'Active', ?, ?, NOW())");
                $stmt->execute([$agency_id, $name, $email, $phone, $pass, $emailVerified, $verificationToken]);
                $conn->commit();

                if ($verificationRequired) {
                    sendVerificationEmail($email, $name, $verificationToken);
                    flash("Registration successful! Please check your email ($email) to verify your account before logging in.");
                } else {
                    flash("Registration successful! You can now log in.");
                }
                redirect("?route=login");
            } catch (Exception $e) {
                $conn->rollBack();
                flash("Error: Email might already exist.", "error");
                redirect("?route=register");
            }
        }

