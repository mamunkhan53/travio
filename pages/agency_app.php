<?php
function renderAgencyApp($conn, $modules) {
    $agency_id = $_SESSION['agency_id'];
    $page = $_GET['page'] ?? 'dashboard';
    if ($page === 'customer_profile') {
        $active_page = 'customers';
    } elseif ($page === 'query_history') {
        $historyMenuTable = $_GET['table'] ?? 'enquiries';
        $active_page = array_key_exists($historyMenuTable, $modules) ? $historyMenuTable : 'enquiries';
    } else {
        $active_page = $page;
    }
    
    $stmt = $conn->prepare("SELECT * FROM agencies WHERE id = ?");
    $stmt->execute([$agency_id]);
    $agency = $stmt->fetch(PDO::FETCH_ASSOC);
    $logoSrc = $agency['logo'] ?: 'https://ui-avatars.com/api/?name='.urlencode($agency['company_name']).'&background=random';

    // Multi-currency: the agency's own operating currency (set at registration from their Country),
    // used throughout Sales/Accounting/Invoices/Reports. Falls back to BDT for older agencies.
    $currencySymbol = $agency['currency_symbol'] ?: '৳';
    $currencyCode = $agency['currency_code'] ?: 'BDT';

    // Two-Factor Authentication: available to Agency Admin and Staff alike, but only offered at all
    // when a Super Admin has switched this capability on platform-wide.
    $agency2faEnabled = getPlatformSetting($conn, 'agency_2fa_enabled', '0') === '1';
    $my2faAccount = null;
    if ($_SESSION['is_staff'] && !empty($_SESSION['staff_id'])) {
        $stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
        $stmt->execute([$_SESSION['staff_id']]);
        $my2faAccount = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (!$_SESSION['is_staff'] && !empty($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $my2faAccount = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Subscription Gate: once expired, only Dashboard & Profile remain reachable.
    // All Add/Edit/Delete actions are additionally blocked server-side (see action handlers above).
    $subscription = subscriptionStatusInfo($agency);
    if ($subscription['expired'] && !in_array($page, ['dashboard', 'profile', 'subscription_payment'])) {
        flash("Your subscription expired on " . date('d M Y', strtotime($subscription['expires_at'])) . ". Renew your plan to access this feature.", "error");
        redirect('?route=app&page=dashboard');
    }
    $subscriptionPlans = getSubscriptionPlans($conn);
    $paymentMethods = getPaymentMethods($conn);

    // Accounting Module Access Gate: staff need the "View Analytics & Reports" permission
    // (mirrors how the rest of the app already gates financial/reporting screens for staff).
    if ($page === 'accounting' && $_SESSION['is_staff'] && !has_permission('can_view_reports')) {
        flash("You do not have permission to view Accounting.", "error");
        redirect('?route=app&page=dashboard');
    }

    // Map Staff IDs to Names
    $all_staff = [];
    if (!$_SESSION['is_staff']) {
        $st_stmt = $conn->prepare("SELECT id, full_name, role FROM staff WHERE agency_id = ? AND status = 'Active'");
        $st_stmt->execute([$agency_id]);
        $all_staff = $st_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $st_map_stmt = $conn->query("SELECT id, full_name FROM staff WHERE agency_id=$agency_id");
    $staffMap = []; while($sm = $st_map_stmt->fetch()) { $staffMap[$sm['id']] = $sm['full_name']; }

    // Analytics Math

    // Fetch Standard Records
    $records = [];
    if (!in_array($page, ['dashboard', 'profile', 'staff', 'staff_history', 'customer_profile', 'query_history', 'download', 'subscription_payment', 'accounting', 'whatsapp'])) {
        if ($page === 'customers') {
            $stmt = $conn->prepare("SELECT * FROM customers WHERE agency_id = ? ORDER BY id DESC");
            $stmt->execute([$agency_id]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $ref_filter = $_SESSION['is_staff'] ? " AND t.reference_staff_id = {$_SESSION['staff_id']}" : "";
            $stmt = $conn->prepare("SELECT t.*, s.full_name as reference_name FROM $page t LEFT JOIN staff s ON t.reference_staff_id = s.id WHERE t.agency_id = ? $ref_filter ORDER BY t.id DESC");
            $stmt->execute([$agency_id]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
?>
<div class="flex h-screen overflow-hidden bg-slate-50">
    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out w-64 bg-white border-r border-slate-200 flex flex-col h-full z-50">
        <div class="p-6 flex items-center gap-3 border-b border-slate-100">
            <img src="<?= $logoSrc ?>" class="w-10 h-10 rounded-lg shadow-sm object-cover border border-slate-200">
            <div>
                <h1 class="text-sm font-bold text-slate-800 truncate w-40"><?= xss_clean($agency['company_name']) ?></h1>
                <?php if ($subscription['expired']): ?>
                    <p class="text-xs text-rose-500 font-bold">● Subscription Expired</p>
                <?php elseif ($subscription['plan'] === 'Trial'): ?>
                    <p class="text-xs text-amber-500 font-bold">● Trial · <?= $subscription['days_left'] ?> day<?= $subscription['days_left'] == 1 ? '' : 's' ?> left</p>
                <?php else: ?>
                    <p class="text-xs text-emerald-500 font-medium">● Active Agency</p>
                <?php endif; ?>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto py-6 space-y-1 px-3 custom-scrollbar">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 px-2">Main Menu</p>
            <?php foreach ($modules as $key => $module): 
                if (isset($module['admin_only']) && $_SESSION['is_staff']) continue;
                if (isset($module['hidden'])) continue;
                if ($key === 'accounting' && $_SESSION['is_staff'] && !has_permission('can_view_reports')) continue;
                $locked = $subscription['expired'] && !in_array($key, ['dashboard', 'profile']);
            ?>
                <?php if ($locked): ?>
                    <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 cursor-not-allowed" title="Locked - renew your subscription to unlock">
                        <i class="<?= $module['icon'] ?> w-5 text-center"></i>
                        <span class="flex-1"><?= $module['title'] ?></span>
                        <i class="fa-solid fa-lock text-xs"></i>
                    </div>
                <?php else: ?>
                    <a href="?route=app&page=<?= $key ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $active_page === $key ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                        <i class="<?= $module['icon'] ?> w-5 text-center"></i>
                        <?= $module['title'] ?>
                    </a>
                    <?php if ($key === 'whatsapp' && !$_SESSION['is_staff'] && !$locked): ?>
                    <a href="?route=app&page=whatsapp_automation"
                       class="flex items-center gap-2 pl-8 pr-3 py-2 rounded-xl text-xs font-bold transition-all
                              <?= $active_page === 'whatsapp_automation' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-400 hover:bg-slate-50 hover:text-indigo-500' ?>">
                        <i class="fa-solid fa-robot w-4 text-center text-xs"></i>
                        Automation
                        <span class="ml-auto text-[9px] bg-indigo-500 text-white px-1.5 py-0.5 rounded-full">AUTO</span>
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="p-4 border-t border-slate-100 bg-white">
            <?php if ($_SESSION['is_staff']): ?>
                <a href="?route=app&page=profile" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 mb-2 transition-colors"><i class="fa-solid fa-user w-5 text-center"></i> My Profile</a>
            <?php endif; ?>
            <a href="?route=logout" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-bold text-rose-600 hover:bg-rose-50 transition-colors">
                <i class="fa-solid fa-arrow-right-from-bracket w-5 text-center"></i> Logout
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-4 flex justify-between items-center z-10 sticky top-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-500 hover:text-indigo-600 focus:outline-none">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <h2 class="text-xl font-bold text-slate-800 tracking-tight flex items-center gap-2">
                    <i class="<?= $modules[$active_page]['icon'] ?> text-indigo-500 hidden sm:inline-block"></i> <?= $modules[$active_page]['title'] ?>
                </h2>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <div class="hidden sm:block text-right">
                    <p class="text-slate-500 text-xs font-medium">Logged in as</p>
                    <p class="font-bold text-slate-800"><?= xss_clean($_SESSION['role']) ?></p>
                </div>
                <a href="<?= $_SESSION['is_staff'] ? '?' : '?route=app&page=profile' ?>" class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold border border-indigo-100 hover:bg-indigo-100 transition">
                    <i class="fa-solid fa-user"></i>
                </a>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-8 relative custom-scrollbar">
            

            <?php if ($page === 'dashboard'): 
                include __DIR__ . '/agency/dashboard.php';
            elseif ($page === 'download'):
                include __DIR__ . '/agency/reports.php';
            elseif ($page === 'query_history'):
                include __DIR__ . '/agency/crmhistory.php';
            elseif ($page === 'customer_profile'):
                include __DIR__ . '/agency/customers.php';
            elseif ($page === 'staff' && !$_SESSION['is_staff']):
                include __DIR__ . '/agency/staff.php';
            elseif ($page === 'staff_history' && !$_SESSION['is_staff']):
                include __DIR__ . '/agency/staff.php';
            elseif ($page === 'profile'):
                include __DIR__ . '/agency/profile.php';
            elseif ($page === 'subscription_payment' && !$_SESSION['is_staff']):
                include __DIR__ . '/agency/subscription.php';
            elseif ($page === 'invoices'):
                include __DIR__ . '/agency/invoices.php';
            elseif ($page === 'whatsapp'):
                include __DIR__ . '/agency/whatsapp.php';
            elseif ($page === 'whatsapp_automation' && !$_SESSION['is_staff']):
                include __DIR__ . '/agency/whatsapp_automation.php';
            elseif ($page === 'accounting'):
                include __DIR__ . '/agency/accounting.php';
            elseif (array_key_exists($page, $modules) && $page !== 'dashboard'):
                include __DIR__ . '/agency/generic_crud.php';
            endif; ?>
        </div>
    </main>
</div>
<?php } ?>
