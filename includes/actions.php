<?php
// =========================================================================
// 4. FRONT CONTROLLER & ROUTER
// =========================================================================
// This file only wires things together - the actual action logic lives in the
// small included files below, split by feature area for easier maintenance.
// ── Path-based routing (clean URLs) ──────────────────────────────────────────
// Works on PHP built-in server (all requests pass through index.php) and
// Apache/Hostinger (via .htaccess RewriteRule → index.php).
$_uriPath  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$_segments = array_values(array_filter(explode('/', trim($_uriPath, '/'))));
$_seg0     = $_segments[0] ?? '';
$_seg1     = $_segments[1] ?? '';

// 301: redirect legacy ?route= query-string URLs to their clean-URL equivalents.
// GET requests only — POST forms must never be 301-redirected (form data would be lost).
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$_seg0 && !empty($_GET['route'])) {
    $_lr  = $_GET['route'];
    $_lp  = $_GET['page'] ?? '';
    $_map = [
        'login'           => '/login',
        'register'        => '/register',
        'logout'          => '/logout',
        'home'            => '/',
        'forgot_password' => '/forgot-password',
        'reset_password'  => '/reset-password',
        'verify_email'    => '/verify-email',
        'admin_dashboard' => '/admin',
        'travio-bangla'   => '/travio-bangla',
    ];
    if ($_lr === 'app' && $_lp) {
        // strip route= and page= from QS; keep everything else (?id=, &tab=, etc.)
        $_qs = preg_replace('/(?:^|&)(?:route|page)=[^&]*/', '', $_SERVER['QUERY_STRING'] ?? '');
        $_qs = ltrim($_qs, '&');
        header('Location: /app/' . rawurlencode($_lp) . ($_qs ? '?' . $_qs : ''), true, 301);
        exit;
    } elseif (isset($_map[$_lr])) {
        $_target = $_map[$_lr];
        $_qs = preg_replace('/(?:^|&)route=[^&]*/', '', $_SERVER['QUERY_STRING'] ?? '');
        $_qs = ltrim($_qs, '&');
        if ($_qs) $_target .= (str_contains($_target, '?') ? '&' : '?') . $_qs;
        header('Location: ' . $_target, true, 301);
        exit;
    }
}

// Derive $route (and optionally set $_GET['page']) from the URL path.
if (!$_seg0 || $_seg0 === 'index.php') {
    $route = 'home';
} else {
    switch ($_seg0) {
        case 'login':           $route = 'login';           break;
        case 'register':        $route = 'register';        break;
        case 'logout':          $route = 'logout';          break;
        case 'forgot-password': $route = 'forgot_password'; break;
        case 'reset-password':  $route = 'reset_password';  break;
        case 'verify-email':    $route = 'verify_email';    break;
        case 'admin':           $route = 'admin_dashboard'; break;
        case 'travio-bangla':   $route = 'travio-bangla';   break;
        case 'app':
            $route = 'app';
            if ($_seg1) $_GET['page'] = $_seg1;
            break;
        default:
            // Fall back to query param (keeps ?route= working for any edge case)
            $route = $_GET['route'] ?? 'home';
    }
}

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
    redirect("/");
}
