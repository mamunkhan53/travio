<?php
        // ------------- 2FA: CONFIRM SETUP CODE AND ACTIVATE (Super Admin or Agency Admin) -------------
        if ($action === 'confirm_2fa' && !empty($_SESSION['user_id'])) {
            $pendingSecret = $_SESSION['pending_totp_secret'] ?? '';
            $redirectTarget = $_SESSION['is_staff'] ? '/app/profile' : (($_SESSION['role'] === 'Super Admin') ? '/admin?tab=settings' : '/app/profile');
            $table = $_SESSION['is_staff'] ? 'staff' : 'users';
            $idToUpdate = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : $_SESSION['user_id'];

            if ($pendingSecret && verifyTotpCode($pendingSecret, $_POST['code'] ?? '')) {
                $conn->prepare("UPDATE $table SET totp_secret = ?, totp_enabled = 1 WHERE id = ?")->execute([$pendingSecret, $idToUpdate]);
                unset($_SESSION['pending_totp_secret']);
                flash("Two-Factor Authentication is now enabled.");
            } else {
                flash("That code didn't match. Please try again.", "error");
            }
            redirect($redirectTarget);
        }

        // ------------- 2FA: DISABLE (requires current password, Super Admin, Agency Admin, or Staff) -------------
        if ($action === 'disable_2fa' && !empty($_SESSION['user_id'])) {
            $redirectTarget = $_SESSION['is_staff'] ? '/app/profile' : (($_SESSION['role'] === 'Super Admin') ? '/admin?tab=settings' : '/app/profile');
            $table = $_SESSION['is_staff'] ? 'staff' : 'users';
            $idToUpdate = $_SESSION['is_staff'] ? $_SESSION['staff_id'] : $_SESSION['user_id'];

            $stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
            $stmt->execute([$idToUpdate]);
            $me = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($me && password_verify($_POST['password'] ?? '', $me['password_hash'])) {
                $conn->prepare("UPDATE $table SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?")->execute([$idToUpdate]);
                flash("Two-Factor Authentication has been disabled.");
            } else {
                flash("Incorrect password.", "error");
            }
            redirect($redirectTarget);
        }

