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

    // Dashboard top-nav: notification badge count (needed before header renders)
    $dashNotifCount = 0;
    if ($page === 'dashboard') {
        $dn_sf = $_SESSION['is_staff'] ? " AND staff_id = {$_SESSION['staff_id']}" : "";
        $dashNotifCount = (int)$conn->query("SELECT COUNT(*) FROM service_notifications WHERE agency_id=$agency_id AND is_read=0 AND notify_date <= CURRENT_DATE() AND deadline_date >= CURRENT_DATE() $dn_sf")->fetchColumn();
    }

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
    if (!in_array($page, ['dashboard', 'profile', 'staff', 'staff_history', 'customer_profile', 'query_history', 'download', 'subscription_payment', 'accounting', 'whatsapp', 'whatsapp_automation'])
        && empty($modules[$page]['sc_module'])
        && empty($modules[$page]['acc_module'])) {
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
                if ($key === 'acc' && $_SESSION['is_staff'] && !has_permission('can_view_reports') && !has_permission('can_view_acc_reports')) continue;
                $locked = $subscription['expired'] && !in_array($key, ['dashboard', 'profile']);
            ?>
                <?php if ($key === 'acc'): ?>
                    <?php
                    $acc_pages = ['acc_dashboard','acc_chart_of_accounts','acc_general_ledger','acc_cash_book','acc_bank_book','acc_income','acc_expenses','acc_receivable','acc_payable','acc_journals','acc_payment_vouchers','acc_receipt_vouchers','acc_pl','acc_balance_sheet','acc_vat','acc_financial_reports','acc_settings','accounting'];
                    $acc_open  = in_array($active_page, $acc_pages);
                    $acc_locked= $locked;
                    $acc_sub   = [
                        'acc_dashboard'         => ['Dashboard',            'fa-solid fa-border-all'],
                        'acc_chart_of_accounts' => ['Chart of Accounts',    'fa-solid fa-list'],
                        'acc_general_ledger'    => ['General Ledger',       'fa-solid fa-book'],
                        'acc_cash_book'         => ['Cash Book',            'fa-solid fa-money-bill-wave'],
                        'acc_bank_book'         => ['Bank Book',            'fa-solid fa-building-columns'],
                        'acc_income'            => ['Income',               'fa-solid fa-circle-dollar-to-slot'],
                        'acc_expenses'          => ['Expenses',             'fa-solid fa-receipt'],
                        'acc_receivable'        => ['Accounts Receivable',  'fa-solid fa-hand-holding-dollar'],
                        'acc_payable'           => ['Accounts Payable',     'fa-solid fa-file-invoice'],
                        'acc_journals'          => ['Journal Entries',      'fa-solid fa-journal-whills'],
                        'acc_payment_vouchers'  => ['Payment Vouchers',     'fa-solid fa-money-check-dollar'],
                        'acc_receipt_vouchers'  => ['Receipt Vouchers',     'fa-solid fa-file-lines'],
                        'acc_pl'                => ['Profit & Loss',        'fa-solid fa-chart-line'],
                        'acc_balance_sheet'     => ['Balance Sheet',        'fa-solid fa-scale-balanced'],
                        'acc_vat'               => ['VAT / Tax Reports',    'fa-solid fa-percent'],
                        'acc_financial_reports' => ['Financial Reports',    'fa-solid fa-chart-pie'],
                    ];
                    if (!$_SESSION['is_staff']) $acc_sub['acc_settings'] = ['Settings', 'fa-solid fa-gear'];
                    ?>
                    <?php if ($acc_locked): ?>
                        <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 cursor-not-allowed">
                            <i class="fa-solid fa-calculator w-5 text-center"></i>
                            <span class="flex-1">Accounting</span>
                            <i class="fa-solid fa-lock text-xs"></i>
                        </div>
                    <?php else: ?>
                        <button type="button" onclick="toggleAccMenu()"
                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all
                                       <?= $acc_open ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                            <i class="fa-solid fa-calculator w-5 text-center"></i>
                            <span class="flex-1 text-left">Accounting</span>
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200" id="accMenuChevron"
                               style="<?= $acc_open ? 'transform:rotate(180deg)' : '' ?>"></i>
                        </button>
                        <div id="accSubMenu" class="<?= $acc_open ? '' : 'hidden' ?> pl-3 mt-0.5 space-y-0.5">
                            <?php foreach ($acc_sub as $ak => [$alabel, $aicon]): ?>
                            <a href="?route=app&page=<?= $ak ?>"
                               class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-semibold transition-all
                                      <?= $active_page === $ak ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                                <i class="<?= $aicon ?> w-4 text-center text-xs"></i>
                                <?= $alabel ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php elseif ($key === 'sc'): ?>
                    <?php
                    $sc_pages  = ['sc_leads','sc_students','sc_applications','sc_documents','sc_visa','sc_payments','sc_followups','sc_reports','sc_settings'];
                    $sc_open   = in_array($active_page, $sc_pages);
                    $sc_locked = $locked;
                    $sc_sub    = [
                        'sc_leads'        => ['Student Leads',           'fa-solid fa-user-graduate'],
                        'sc_students'     => ['Students',                'fa-solid fa-id-card'],
                        'sc_applications' => ['University Applications', 'fa-solid fa-building-columns'],
                        'sc_documents'    => ['Documents',               'fa-solid fa-folder-open'],
                        'sc_visa'         => ['Visa Processing',         'fa-solid fa-stamp'],
                        'sc_payments'     => ['Payments',                'fa-solid fa-coins'],
                        'sc_followups'    => ['Follow-ups',              'fa-solid fa-calendar-check'],
                        'sc_reports'      => ['Reports',                 'fa-solid fa-chart-bar'],
                    ];
                    if (!$_SESSION['is_staff']) $sc_sub['sc_settings'] = ['Settings', 'fa-solid fa-gear'];
                    ?>
                    <?php if ($sc_locked): ?>
                        <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 cursor-not-allowed">
                            <i class="fa-solid fa-graduation-cap w-5 text-center"></i>
                            <span class="flex-1">Student Consultancy</span>
                            <i class="fa-solid fa-lock text-xs"></i>
                        </div>
                    <?php else: ?>
                        <button type="button" onclick="toggleScMenu()"
                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all
                                       <?= $sc_open ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                            <i class="fa-solid fa-graduation-cap w-5 text-center"></i>
                            <span class="flex-1 text-left">Student Consultancy</span>
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200" id="scMenuChevron"
                               style="<?= $sc_open ? 'transform:rotate(180deg)' : '' ?>"></i>
                        </button>
                        <div id="scSubMenu" class="<?= $sc_open ? '' : 'hidden' ?> pl-3 mt-0.5 space-y-0.5">
                            <?php foreach ($sc_sub as $sk => [$slabel, $sicon]): ?>
                            <a href="?route=app&page=<?= $sk ?>"
                               class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-semibold transition-all
                                      <?= $active_page === $sk ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                                <i class="<?= $sicon ?> w-4 text-center text-xs"></i>
                                <?= $slabel ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php elseif ($key === 'whatsapp' && !$_SESSION['is_staff']): ?>
                    <?php
                    $wa_open   = in_array($active_page, ['whatsapp', 'whatsapp_automation']);
                    $wa_locked = $locked;
                    ?>
                    <?php if ($wa_locked): ?>
                        <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 cursor-not-allowed" title="Locked - renew your subscription to unlock">
                            <i class="<?= $module['icon'] ?> w-5 text-center"></i>
                            <span class="flex-1"><?= $module['title'] ?></span>
                            <i class="fa-solid fa-lock text-xs"></i>
                        </div>
                    <?php else: ?>
                        <!-- WhatsApp collapsible group -->
                        <button type="button" onclick="toggleWaMenu()"
                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all
                                       <?= $wa_open ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                            <i class="<?= $module['icon'] ?> w-5 text-center"></i>
                            <span class="flex-1 text-left">WhatsApp</span>
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200" id="waMenuChevron"
                               style="<?= $wa_open ? 'transform:rotate(180deg)' : '' ?>"></i>
                        </button>
                        <div id="waSubMenu" class="<?= $wa_open ? '' : 'hidden' ?> pl-3 mt-0.5 space-y-0.5">
                            <a href="?route=app&page=whatsapp"
                               class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-semibold transition-all
                                      <?= $active_page === 'whatsapp' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                                <i class="fa-solid fa-paper-plane w-4 text-center text-xs"></i>
                                Manual
                            </a>
                            <a href="?route=app&page=whatsapp_automation"
                               class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-semibold transition-all
                                      <?= $active_page === 'whatsapp_automation' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                                <i class="fa-solid fa-robot w-4 text-center text-xs"></i>
                                Automation
                            </a>
                        </div>
                    <?php endif; ?>
                <?php elseif ($locked): ?>
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
        <?php
        $pageTitle = $modules[$active_page]['title'] ?? ucwords(str_replace('_', ' ', $active_page));
        $pageIcon  = $modules[$active_page]['icon']  ?? 'fa-solid fa-circle-nodes';
        if ($active_page === 'whatsapp_automation') { $pageTitle = 'WhatsApp Automation'; $pageIcon = 'fa-solid fa-robot'; }
        ?>
        <?php if ($page === 'dashboard'): ?>
        <!-- ── Dashboard top nav ──────────────────────────────────────── -->
        <header id="dashHeader" class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3 flex justify-between items-center z-10 sticky top-0 transition-colors duration-200">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-500 hover:text-indigo-600 focus:outline-none">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div>
                    <h2 class="text-base font-extrabold text-slate-800 dash-nav-text leading-tight">CRM Dashboard</h2>
                    <p class="text-xs text-slate-400 hidden sm:block"><?= xss_clean($agency['company_name']) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-1 sm:gap-1.5">
                <!-- Search -->
                <button class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-500 hover:bg-slate-100 transition dash-nav-icon" title="Search">
                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                </button>
                <!-- Dark / Light mode toggle -->
                <button id="dashDarkToggle" onclick="toggleDashDark()" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-500 hover:bg-slate-100 transition dash-nav-icon" title="Toggle dark mode">
                    <i class="fa-solid fa-moon text-sm" id="dashDarkIcon"></i>
                </button>
                <!-- Language -->
                <button class="w-9 h-9 rounded-xl hidden sm:flex items-center justify-center text-slate-500 hover:bg-slate-100 transition dash-nav-icon" title="Language">
                    <i class="fa-solid fa-language text-base"></i>
                </button>
                <!-- Notifications -->
                <a href="#dashNotifications" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-500 hover:bg-slate-100 transition relative dash-nav-icon" title="Notifications">
                    <i class="fa-solid fa-bell text-sm"></i>
                    <?php if ($dashNotifCount > 0): ?>
                    <span class="absolute -top-0.5 -right-0.5 min-w-[17px] h-[17px] bg-rose-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center px-1 leading-none"><?= $dashNotifCount ?></span>
                    <?php endif; ?>
                </a>
                <!-- Email / Inbox -->
                <a href="?route=app&page=dashboard" class="w-9 h-9 rounded-xl hidden sm:flex items-center justify-center text-slate-500 hover:bg-slate-100 transition dash-nav-icon" title="Inbox">
                    <i class="fa-solid fa-envelope text-sm"></i>
                </a>
                <!-- Profile dropdown -->
                <div class="relative ml-1" id="dashProfileMenuWrap">
                    <button onclick="document.getElementById('dashProfileDropdown').classList.toggle('hidden')"
                            class="flex items-center gap-2 pl-1.5 pr-2.5 py-1.5 rounded-xl hover:bg-slate-100 transition">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white text-xs font-black flex-shrink-0">
                            <?= strtoupper(substr($_SESSION['role'] ?? 'U', 0, 2)) ?>
                        </div>
                        <div class="hidden sm:block text-left">
                            <p class="text-xs font-bold text-slate-800 dash-nav-text leading-tight"><?= xss_clean($_SESSION['role']) ?></p>
                            <p class="text-[10px] text-slate-400"><?= $_SESSION['is_staff'] ? 'Staff' : 'Admin' ?></p>
                        </div>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 hidden sm:block ml-1"></i>
                    </button>
                    <div id="dashProfileDropdown" class="hidden absolute right-0 top-full mt-2 w-48 bg-white rounded-2xl shadow-xl border border-slate-100 py-2 z-50">
                        <div class="px-4 py-2 border-b border-slate-100 mb-1">
                            <p class="text-xs font-bold text-slate-800"><?= xss_clean($_SESSION['role']) ?></p>
                            <p class="text-[11px] text-slate-400"><?= $_SESSION['is_staff'] ? 'Staff Account' : 'Agency Admin' ?></p>
                        </div>
                        <a href="?route=app&page=profile" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                            <i class="fa-solid fa-user w-4 text-center text-slate-400"></i> My Profile
                        </a>
                        <a href="?route=app&page=dashboard" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                            <i class="fa-solid fa-gauge w-4 text-center text-slate-400"></i> Dashboard
                        </a>
                        <hr class="my-1 border-slate-100">
                        <a href="?route=logout" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50 transition">
                            <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>
        <script>
        // Close profile dropdown on outside click
        document.addEventListener('click', function(e) {
            const wrap = document.getElementById('dashProfileMenuWrap');
            const dd   = document.getElementById('dashProfileDropdown');
            if (wrap && dd && !wrap.contains(e.target)) dd.classList.add('hidden');
        });
        // Dark mode toggle (scoped: only affects #dashWrapper & #dashHeader via CSS attr selector)
        window.toggleDashDark = function() {
            const isDark = document.documentElement.getAttribute('data-dash-dark') === '1';
            const next   = isDark ? '0' : '1';
            document.documentElement.setAttribute('data-dash-dark', next);
            document.getElementById('dashDarkIcon').className = next === '1' ? 'fa-solid fa-sun text-sm' : 'fa-solid fa-moon text-sm';
            localStorage.setItem('dashDark', next);
        };
        // Apply saved preference immediately
        (function() {
            if (localStorage.getItem('dashDark') === '1') {
                document.documentElement.setAttribute('data-dash-dark', '1');
                const icon = document.getElementById('dashDarkIcon');
                if (icon) icon.className = 'fa-solid fa-sun text-sm';
            }
        })();
        </script>
        <?php else: ?>
        <!-- ── Standard top nav (all other pages) ────────────────────── -->
        <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-4 flex justify-between items-center z-10 sticky top-0">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-500 hover:text-indigo-600 focus:outline-none">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <h2 class="text-xl font-bold text-slate-800 tracking-tight flex items-center gap-2">
                    <i class="<?= $pageIcon ?> text-indigo-500 hidden sm:inline-block"></i> <?= xss_clean($pageTitle) ?>
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
        <?php endif; ?>

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
            elseif (isset($modules[$page]) && !empty($modules[$page]['sc_module'])):
                if ($page === 'sc_settings' && $_SESSION['is_staff']) { flash("Access denied.", "error"); redirect("?route=app&page=dashboard"); }
                include __DIR__ . '/agency/' . $page . '.php';
            elseif ($page === 'accounting'):
                redirect("?route=app&page=acc_dashboard");
            elseif (isset($modules[$page]) && !empty($modules[$page]['acc_module'])):
                if ($page === 'acc_settings' && $_SESSION['is_staff']) { flash("Access denied.", "error"); redirect("?route=app&page=acc_dashboard"); }
                include __DIR__ . '/agency/acc/' . $page . '.php';
            elseif (array_key_exists($page, $modules) && $page !== 'dashboard'):
                include __DIR__ . '/agency/generic_crud.php';
            endif; ?>
        </div>
    </main>
</div>
<?php } ?>
