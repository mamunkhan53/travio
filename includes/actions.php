<?php
// =========================================================================
// 4. FRONT CONTROLLER & ROUTER
// =========================================================================
// This file only wires things together - the actual action logic lives in the
// small included files below, split by feature area for easier maintenance.
$route = $_GET['route'] ?? 'home';

// Process Actions First
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        require __DIR__ . '/actions_auth.php';         // login, 2FA login step, resend verification, register
        require __DIR__ . '/actions_superadmin.php';   // Super Admin: agencies, plans, payment methods/requests, platform settings
        require __DIR__ . '/actions_2fa.php';          // Two-Factor Authentication setup/disable (Super Admin, Agency Admin, Staff)
        require __DIR__ . '/actions_agency.php';       // Agency-side: subscription payment, profile, follow-ups, staff, invoices, expenses
        require __DIR__ . '/actions_generic_crud.php'; // Generic add/edit for enquiries/passports/visas/tickets/umrah/tours/customers
    }
}

// GET-based actions (delete, delete_expense, begin_2fa_setup) - each guards its own REQUEST_METHOD/action check
require __DIR__ . '/actions_get.php';

// Logout
if ($route === 'logout') {
    session_destroy();
    redirect("?route=home");
}
