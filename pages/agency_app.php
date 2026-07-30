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

    // Global nav: notification badge count + items (service deadlines + follow-up reminders)
    $dn_sf  = $_SESSION['is_staff'] ? " AND staff_id = {$_SESSION['staff_id']}" : "";
    $rf_sf  = $_SESSION['is_staff'] ? " AND rf.agency_id=$agency_id AND rf.staff_id = {$_SESSION['staff_id']}" : " AND rf.agency_id=$agency_id";
    $navNotifCount = 0;
    $navNotifItems = [];
    // 1. Service deadline notifications (unread, due within range)
    $notifRows = $conn->query("SELECT customer_name, module_name, notification_type, deadline_date FROM service_notifications WHERE agency_id=$agency_id AND is_read=0 AND notify_date <= CURRENT_DATE() AND deadline_date >= CURRENT_DATE() $dn_sf ORDER BY deadline_date ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($notifRows as $nr) {
        $navNotifCount++;
        $navNotifItems[] = ['icon'=>'fa-clock','color'=>'text-rose-500','title'=>$nr['notification_type'].' — '.$nr['customer_name'],'sub'=>date('d M Y', strtotime($nr['deadline_date'])).' deadline','url'=>'?route=app&page=dashboard#dashNotifications'];
    }
    // 2. Follow-up reminders (today and overdue, unhandled)
    $fuRows = $conn->query("SELECT rf.follow_up_date, rf.module_name, rf.record_id, rf.note FROM record_followups rf WHERE 1=1 $rf_sf AND rf.follow_up_date <= CURRENT_DATE() ORDER BY rf.follow_up_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fuRows as $fr) {
        $navNotifCount++;
        $mlabel = ['enquiries'=>'Lead','passports'=>'Passport','visas'=>'Visa','tickets'=>'Ticket','umrah'=>'Umrah','tours'=>'Tour','sc_leads'=>'SC Lead','sc_students'=>'Student'][$fr['module_name']] ?? ucfirst($fr['module_name']);
        $navNotifItems[] = ['icon'=>'fa-comment-dots','color'=>'text-indigo-500','title'=>'Follow-up: '.$mlabel.' #'.$fr['record_id'],'sub'=>date('d M Y', strtotime($fr['follow_up_date'])).($fr['note'] ? ' — '.substr($fr['note'],0,35) : ''),'url'=>'?route=app&page=query_history&table='.$fr['module_name'].'&id='.urlencode($fr['record_id'])];
    }
    // Language switch (handled before HTML output)
    if (!empty($_GET['set_lang']) && in_array($_GET['set_lang'], ['en','bn','ar','hi','ur'])) {
        $_SESSION['lang'] = $_GET['set_lang'];
        redirect('?route=app&page='.urlencode($page));
    }
    $currentLang = $_SESSION['lang'] ?? 'en';
    $langLabels  = ['en'=>'EN','bn'=>'বাং','ar'=>'عر','hi'=>'हि','ur'=>'اُر'];
    $langNames   = ['en'=>'English','bn'=>'বাংলা','ar'=>'العربية','hi'=>'हिन्दी','ur'=>'اردو'];

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
    if (!in_array($page, ['dashboard', 'profile', 'staff', 'staff_history', 'customer_profile', 'query_history', 'download', 'subscription_payment', 'accounting', 'whatsapp', 'whatsapp_automation', 'ocr_scanner'])
        && empty($modules[$page]['sc_module'])
        && empty($modules[$page]['acc_module'])
        && empty($modules[$page]['umrah_module'])) {
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
<style>
/* ═══ Global Dark Mode ═══ */
[data-dark="1"] { color-scheme: dark; }
[data-dark="1"] #appRoot   { background: #0b1120 !important; }
[data-dark="1"] #pageContent { background: #0b1120 !important; }
/* ─ Header ─ */
[data-dark="1"] #appHeader { background: #0f172a !important; border-color: #1e293b !important; }
[data-dark="1"] #appHeader .header-title { color: #f1f5f9 !important; }
[data-dark="1"] #appHeader .header-sub   { color: #64748b !important; }
[data-dark="1"] #appHeader .nav-btn { color: #94a3b8 !important; }
[data-dark="1"] #appHeader .nav-btn:hover { background: #1e2537 !important; color: #f1f5f9 !important; }
/* ─ Profile dropdown ─ */
[data-dark="1"] #profileDropdown { background: #1e293b !important; border-color: #334155 !important; }
[data-dark="1"] #profileDropdown a, [data-dark="1"] #profileDropdown p { color: #cbd5e1 !important; }
[data-dark="1"] #profileDropdown a:hover { background: #0f172a !important; }
[data-dark="1"] #profileDropdown .border-b, [data-dark="1"] #profileDropdown hr { border-color: #334155 !important; }
/* ─ Language dropdown ─ */
[data-dark="1"] #langDropdown { background: #1e293b !important; border-color: #334155 !important; }
[data-dark="1"] #langDropdown a { color: #cbd5e1 !important; }
[data-dark="1"] #langDropdown a:hover { background: #0f172a !important; color: #f1f5f9 !important; }
[data-dark="1"] #langDropdown .border-b { border-color: #334155 !important; }
/* ─ Notification dropdown ─ */
[data-dark="1"] #notifDropdown { background: #1e293b !important; border-color: #334155 !important; }
[data-dark="1"] #notifDropdown .notif-hdr { border-color: #334155 !important; }
[data-dark="1"] #notifDropdown .notif-hdr p  { color: #f1f5f9 !important; }
[data-dark="1"] #notifDropdown a   { color: #cbd5e1 !important; }
[data-dark="1"] #notifDropdown a:hover { background: #0f172a !important; }
[data-dark="1"] #notifDropdown .divide-y > a { border-color: #334155 !important; }
[data-dark="1"] #notifDropdown .notif-footer { border-color: #334155 !important; }
[data-dark="1"] #notifDropdown p.text-slate-400 { color: #64748b !important; }
[data-dark="1"] #notifDropdown p.text-xs.font-bold { color: #f1f5f9 !important; }
/* ─ Sidebar ─ */
[data-dark="1"] #sidebar { background: #0f172a !important; border-color: #1e293b !important; }
[data-dark="1"] #sidebar .border-b, [data-dark="1"] #sidebar .border-r { border-color: #1e293b !important; }
[data-dark="1"] #sidebar h1 { color: #f1f5f9 !important; }
[data-dark="1"] #sidebar p.text-xs { color: #64748b !important; }
[data-dark="1"] #sidebar .text-emerald-500 { color: #34d399 !important; }
[data-dark="1"] #sidebar .text-amber-500   { color: #fbbf24 !important; }
[data-dark="1"] #sidebar .text-rose-500    { color: #fb7185 !important; }
[data-dark="1"] #sidebar nav a, [data-dark="1"] #sidebar nav button { color: #94a3b8 !important; }
[data-dark="1"] #sidebar nav a:hover, [data-dark="1"] #sidebar nav button:hover { background: #1e2537 !important; color: #f1f5f9 !important; }
[data-dark="1"] #sidebar nav a.bg-indigo-600, [data-dark="1"] #sidebar nav button.bg-indigo-600 { background: #4338ca !important; color: #fff !important; box-shadow: none !important; }
[data-dark="1"] #sidebar nav a.bg-indigo-50 { background: #312e81 !important; color: #a5b4fc !important; }
[data-dark="1"] #sidebar .p-4, [data-dark="1"] #sidebar .p-6 { background: #0f172a !important; }
[data-dark="1"] #sidebar .border-t { border-color: #1e293b !important; }
/* ─ Search modal ─ */
[data-dark="1"] #searchModal .search-box { background: #1e2537 !important; border-color: #334155 !important; }
[data-dark="1"] #searchModal .search-box .border-b { border-color: #334155 !important; }
[data-dark="1"] #searchModal input { background: transparent !important; color: #f1f5f9 !important; }
[data-dark="1"] #searchModal input::placeholder { color: #64748b !important; }
[data-dark="1"] #searchModal .search-result-item p.text-sm { color: #f1f5f9 !important; }
[data-dark="1"] #searchModal .search-result-item p.text-xs { color: #94a3b8 !important; }
[data-dark="1"] #searchModal .search-result-item:hover { background: #273249 !important; }
[data-dark="1"] #searchModal .search-empty { color: #64748b !important; }
[data-dark="1"] #searchModal i.fa-magnifying-glass { color: #64748b !important; }
[data-dark="1"] #searchModal kbd { color: #64748b !important; border-color: #334155 !important; }
</style>
<div id="appRoot" class="flex h-screen overflow-hidden bg-slate-50">
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
                <?php if ($key === 'travel_services'): ?>
                    <?php
                    $ts_pages = ['tickets', 'passports', 'visas', 'tours', 'download'];
                    $ts_open  = in_array($active_page, $ts_pages);
                    $ts_locked = $subscription['expired'];
                    ?>
                    <?php if ($ts_locked): ?>
                        <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 cursor-not-allowed" title="Locked - renew your subscription to unlock">
                            <i class="fa-solid fa-globe w-5 text-center"></i>
                            <span class="flex-1">Travel Services</span>
                            <i class="fa-solid fa-lock text-xs"></i>
                        </div>
                    <?php else: ?>
                        <button type="button" onclick="toggleTsMenu()"
                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all
                                       <?= $ts_open ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                            <i class="fa-solid fa-globe w-5 text-center"></i>
                            <span class="flex-1 text-left">Travel Services</span>
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200" id="tsMenuChevron"
                               style="<?= $ts_open ? 'transform:rotate(180deg)' : '' ?>"></i>
                        </button>
                        <div id="tsSubMenu" class="<?= $ts_open ? '' : 'hidden' ?> pl-3 mt-0.5 space-y-0.5">
                            <?php
                            $ts_sub = [
                                'tickets'   => ['Air Tickets',      'fa-solid fa-plane'],
                                'passports' => ['Passports',        'fa-solid fa-passport'],
                                'visas'     => ['Visa',             'fa-solid fa-file-signature'],
                                'tours'     => ['Tours',            'fa-solid fa-map-location-dot'],
                                'download'  => ['Download Reports', 'fa-solid fa-download'],
                            ];
                            foreach ($ts_sub as $tk => [$tlabel, $ticon]):
                            ?>
                            <a href="?route=app&page=<?= $tk ?>"
                               class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-semibold transition-all
                                      <?= $active_page === $tk ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                                <i class="<?= $ticon ?> w-4 text-center text-xs"></i>
                                <?= $tlabel ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php elseif ($key === 'acc'): ?>
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
                <?php elseif ($key === 'hajj_umrah'): ?>
                    <?php
                    $hu_pages = ['umrah_packages','umrah_bookings','umrah_payments','umrah_checklist','umrah_reports'];
                    $hu_open  = in_array($active_page, $hu_pages);
                    ?>
                    <?php if ($locked): ?>
                        <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-300 cursor-not-allowed">
                            <i class="fa-solid fa-kaaba w-5 text-center"></i>
                            <span class="flex-1">Hajj &amp; Umrah</span>
                            <i class="fa-solid fa-lock text-xs"></i>
                        </div>
                    <?php else: ?>
                        <button type="button" onclick="toggleHuMenu()"
                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all
                                       <?= $hu_open ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                            <i class="fa-solid fa-kaaba w-5 text-center"></i>
                            <span class="flex-1 text-left">Hajj &amp; Umrah</span>
                            <i class="fa-solid fa-chevron-down text-xs transition-transform duration-200" id="huMenuChevron"
                               style="<?= $hu_open ? 'transform:rotate(180deg)' : '' ?>"></i>
                        </button>
                        <div id="huSubMenu" class="<?= $hu_open ? '' : 'hidden' ?> pl-3 mt-0.5 space-y-0.5">
                            <?php
                            $hu_sub = [
                                'umrah_packages'  => ['Packages',           'fa-solid fa-box-open'],
                                'umrah_bookings'  => ['Bookings',           'fa-solid fa-calendar-check'],
                                'umrah_payments'  => ['Payments',           'fa-solid fa-coins'],
                                'umrah_checklist' => ['Document Checklist', 'fa-solid fa-list-check'],
                                'umrah_reports'   => ['Reports',            'fa-solid fa-chart-bar'],
                            ];
                            foreach ($hu_sub as $hk => [$hlabel, $hicon]):
                            ?>
                            <a href="?route=app&page=<?= $hk ?>"
                               class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-semibold transition-all
                                      <?= $active_page === $hk ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                                <i class="<?= $hicon ?> w-4 text-center text-xs"></i>
                                <?= $hlabel ?>
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
        <!-- ── Global top nav (all pages) ────────────────────────────── -->
        <header id="appHeader" class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3 flex justify-between items-center z-30 sticky top-0 transition-colors duration-200">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-500 hover:text-indigo-600 focus:outline-none nav-btn">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <div>
                    <?php if ($page === 'dashboard'): ?>
                    <h2 class="text-base font-extrabold header-title text-slate-800 leading-tight">CRM Dashboard</h2>
                    <p class="text-xs header-sub text-slate-400 hidden sm:block"><?= xss_clean($agency['company_name']) ?></p>
                    <?php else: ?>
                    <h2 class="text-base font-extrabold header-title text-slate-800 leading-tight flex items-center gap-2">
                        <i class="<?= $pageIcon ?> text-indigo-500 text-sm"></i> <?= xss_clean($pageTitle) ?>
                    </h2>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-1 sm:gap-1.5">
                <!-- Search -->
                <button onclick="openSearch()" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-500 hover:bg-slate-100 transition nav-btn" title="Search (Ctrl+K)">
                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                </button>
                <!-- Dark mode toggle -->
                <button id="darkToggle" onclick="toggleDark()" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-500 hover:bg-slate-100 transition nav-btn" title="Toggle dark mode">
                    <i class="fa-solid fa-moon text-sm" id="darkIcon"></i>
                </button>
                <!-- Language -->
                <div class="relative hidden sm:block" id="langMenuWrap">
                    <button onclick="document.getElementById('langDropdown').classList.toggle('hidden')" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-500 hover:bg-slate-100 transition nav-btn" title="Language: <?= $langNames[$currentLang] ?>">
                        <span class="text-[11px] font-black leading-none"><?= $langLabels[$currentLang] ?></span>
                    </button>
                    <div id="langDropdown" class="hidden absolute right-0 top-full mt-2 w-44 bg-white rounded-2xl shadow-xl border border-slate-100 py-2 z-50">
                        <p class="px-4 py-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100 mb-1">Language</p>
                        <?php foreach ($langNames as $lk => $lname): ?>
                        <a href="?route=app&page=<?= $page ?>&set_lang=<?= $lk ?>"
                           class="flex items-center justify-between px-4 py-2.5 text-sm font-semibold hover:bg-slate-50 transition <?= $currentLang===$lk ? 'text-indigo-600' : 'text-slate-600' ?>">
                            <?= $lname ?>
                            <?php if ($currentLang===$lk): ?><i class="fa-solid fa-check text-indigo-500 text-xs"></i><?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Notifications -->
                <div class="relative" id="notifMenuWrap">
                    <button onclick="document.getElementById('notifDropdown').classList.toggle('hidden')" class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-500 hover:bg-slate-100 transition relative nav-btn" title="Notifications">
                        <i class="fa-solid fa-bell text-sm"></i>
                        <?php if ($navNotifCount > 0): ?>
                        <span class="absolute -top-0.5 -right-0.5 min-w-[17px] h-[17px] bg-rose-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center px-1 leading-none"><?= min($navNotifCount,99) ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="notifDropdown" class="hidden absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-xl border border-slate-100 z-50">
                        <div class="notif-hdr px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                            <p class="text-sm font-bold text-slate-800">Notifications</p>
                            <?php if ($navNotifCount > 0): ?><span class="text-xs font-bold bg-rose-100 text-rose-700 px-2 py-0.5 rounded-full"><?= $navNotifCount ?></span><?php endif; ?>
                        </div>
                        <?php if (empty($navNotifItems)): ?>
                        <p class="px-4 py-6 text-center text-slate-400 text-sm">All clear — no pending reminders.</p>
                        <?php else: ?>
                        <div class="max-h-72 overflow-y-auto divide-y divide-slate-100">
                            <?php foreach ($navNotifItems as $ni): ?>
                            <a href="<?= $ni['url'] ?>" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 transition">
                                <span class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center flex-shrink-0 mt-0.5 <?= $ni['color'] ?>">
                                    <i class="fa-solid <?= $ni['icon'] ?> text-xs"></i>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-slate-800 leading-tight"><?= xss_clean($ni['title']) ?></p>
                                    <p class="text-[11px] text-slate-400 mt-0.5 truncate"><?= xss_clean($ni['sub']) ?></p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="notif-footer border-t border-slate-100 px-4 py-2.5">
                            <a href="?route=app&page=dashboard#dashNotifications" class="text-xs font-bold text-indigo-600 hover:underline">View all on Dashboard →</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Profile dropdown -->
                <div class="relative ml-1" id="profileMenuWrap">
                    <button onclick="document.getElementById('profileDropdown').classList.toggle('hidden')"
                            class="flex items-center gap-2 pl-1.5 pr-2.5 py-1.5 rounded-xl hover:bg-slate-100 transition nav-btn">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white text-xs font-black flex-shrink-0">
                            <?= strtoupper(substr($_SESSION['role'] ?? 'U', 0, 2)) ?>
                        </div>
                        <div class="hidden sm:block text-left">
                            <p class="text-xs font-bold header-title text-slate-800 leading-tight"><?= xss_clean($_SESSION['role']) ?></p>
                            <p class="text-[10px] text-slate-400"><?= $_SESSION['is_staff'] ? 'Staff' : 'Admin' ?></p>
                        </div>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 hidden sm:block ml-1"></i>
                    </button>
                    <div id="profileDropdown" class="hidden absolute right-0 top-full mt-2 w-48 bg-white rounded-2xl shadow-xl border border-slate-100 py-2 z-50">
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
        <!-- Search Modal -->
        <div id="searchModal" class="fixed inset-0 z-[200] hidden">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeSearch()"></div>
            <div class="absolute top-16 left-1/2 -translate-x-1/2 w-full max-w-xl px-4">
                <div class="search-box bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="flex items-center gap-3 px-4 py-3.5 border-b border-slate-100">
                        <i class="fa-solid fa-magnifying-glass text-slate-400 text-sm flex-shrink-0"></i>
                        <input id="searchInput" type="text" placeholder="Search customers, leads, passports, visas…"
                               class="flex-1 outline-none text-sm text-slate-800 placeholder-slate-400 bg-transparent"
                               autocomplete="off" oninput="doSearch(this.value)" onkeydown="if(event.key==='Escape')closeSearch()">
                        <kbd class="text-[10px] text-slate-400 border border-slate-200 rounded px-1.5 py-0.5 flex-shrink-0">Esc</kbd>
                    </div>
                    <div id="searchResults" class="max-h-80 overflow-y-auto p-2">
                        <p class="search-empty px-3 py-6 text-center text-slate-400 text-sm">Type at least 2 characters to search…</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- OCR Import from Scanner Modal (global — available on every page) -->
        <div id="ocrImportModal" class="fixed inset-0 z-[210] hidden">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeOcrImport()"></div>
            <div class="absolute top-16 left-1/2 -translate-x-1/2 w-full max-w-2xl px-4">
                <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-xl bg-indigo-100 flex items-center justify-center">
                                <i class="fa-solid fa-id-card-clip text-indigo-600 text-sm"></i>
                            </span>
                            <div>
                                <p class="text-sm font-bold text-slate-800">Import from Document Scanner</p>
                                <p class="text-xs text-slate-400">Search by name, passport no., or NID</p>
                            </div>
                        </div>
                        <button onclick="closeOcrImport()" class="w-8 h-8 rounded-xl hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-600 transition">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>
                    <div class="px-4 pt-3 pb-2">
                        <input id="ocrImportSearch" type="text" placeholder="Type name, passport number, NID…"
                               class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                               oninput="doOcrImportSearch(this.value)" autocomplete="off">
                    </div>
                    <div id="ocrImportResults" class="max-h-80 overflow-y-auto px-4 pb-4">
                        <p class="py-6 text-center text-slate-400 text-sm">Type to search existing scanned documents…</p>
                    </div>
                </div>
            </div>
        </div>

        <script>
        // ── Collapsible sidebar menus ──
        function _toggleMenu(subId, chevronId) {
            var sub = document.getElementById(subId);
            var chev = document.getElementById(chevronId);
            if (!sub) return;
            var hidden = sub.classList.toggle('hidden');
            if (chev) chev.style.transform = hidden ? '' : 'rotate(180deg)';
        }
        window.toggleTsMenu  = function() { _toggleMenu('tsSubMenu',  'tsMenuChevron');  };
        window.toggleHuMenu  = function() { _toggleMenu('huSubMenu',  'huMenuChevron');  };
        window.toggleAccMenu = function() { _toggleMenu('accSubMenu', 'accMenuChevron'); };
        window.toggleScMenu  = function() { _toggleMenu('scSubMenu',  'scMenuChevron');  };
        window.toggleWaMenu  = function() { _toggleMenu('waSubMenu',  'waMenuChevron');  };
        // ── Global dark mode ──
        window.toggleDark = function() {
            const isDark = document.documentElement.getAttribute('data-dark') === '1';
            const next   = isDark ? '0' : '1';
            document.documentElement.setAttribute('data-dark', next);
            document.getElementById('darkIcon').className = next==='1' ? 'fa-solid fa-sun text-sm' : 'fa-solid fa-moon text-sm';
            localStorage.setItem('appDark', next);
        };
        (function() {
            if (localStorage.getItem('appDark') === '1') {
                document.documentElement.setAttribute('data-dark', '1');
                const icon = document.getElementById('darkIcon');
                if (icon) icon.className = 'fa-solid fa-sun text-sm';
            }
        })();
        // ── Close dropdowns on outside click ──
        document.addEventListener('click', function(e) {
            [['profileMenuWrap','profileDropdown'],['langMenuWrap','langDropdown'],['notifMenuWrap','notifDropdown']].forEach(function([wid,did]) {
                const w=document.getElementById(wid), d=document.getElementById(did);
                if (w && d && !w.contains(e.target)) d.classList.add('hidden');
            });
        });
        // ── Search ──
        let _st;
        window.openSearch = function() {
            document.getElementById('searchModal').classList.remove('hidden');
            setTimeout(function(){document.getElementById('searchInput').focus();},50);
        };
        window.closeSearch = function() {
            document.getElementById('searchModal').classList.add('hidden');
            document.getElementById('searchInput').value='';
            document.getElementById('searchResults').innerHTML='<p class="search-empty px-3 py-6 text-center text-slate-400 text-sm">Type at least 2 characters to search…</p>';
        };
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey||e.metaKey) && e.key==='k') { e.preventDefault(); openSearch(); }
            if (e.key==='Escape') closeSearch();
        });
        // ── OCR Import Modal ──
        let _ocrImportCb = null, _ocrSt;
        window.openOcrImport = function(callback) {
            _ocrImportCb = callback;
            document.getElementById('ocrImportModal').classList.remove('hidden');
            document.getElementById('ocrImportResults').innerHTML = '<p class="py-6 text-center text-slate-400 text-sm">Type to search existing scanned documents…</p>';
            document.getElementById('ocrImportSearch').value = '';
            setTimeout(function(){ document.getElementById('ocrImportSearch').focus(); }, 50);
        };
        window.closeOcrImport = function() {
            document.getElementById('ocrImportModal').classList.add('hidden');
            _ocrImportCb = null;
        };
        window.selectOcrDoc = function(data) {
            if (_ocrImportCb) _ocrImportCb(data);
            closeOcrImport();
        };
        window.doOcrImportSearch = function(q) {
            clearTimeout(_ocrSt);
            const el = document.getElementById('ocrImportResults');
            if (!q || q.trim().length < 2) { el.innerHTML = '<p class="py-6 text-center text-slate-400 text-sm">Type to search existing scanned documents…</p>'; return; }
            el.innerHTML = '<p class="py-6 text-center text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Searching…</p>';
            _ocrSt = setTimeout(function() {
                fetch('?ocr_import_ajax=1&q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(function(rows) {
                        if (!rows.length) { el.innerHTML = '<p class="py-6 text-center text-slate-400 text-sm">No documents found for "' + q + '"</p>'; return; }
                        const typeIcons = { Passport: 'fa-passport', NID: 'fa-id-card', Visa: 'fa-file-signature', 'Birth Certificate': 'fa-baby', Other: 'fa-file-alt' };
                        const typeColors = { Passport: 'bg-blue-100 text-blue-600', NID: 'bg-indigo-100 text-indigo-600', Visa: 'bg-purple-100 text-purple-600', 'Birth Certificate': 'bg-green-100 text-green-600', Other: 'bg-slate-100 text-slate-600' };
                        el.innerHTML = rows.map(function(r) {
                            const icon  = typeIcons[r.document_type]  || 'fa-file-alt';
                            const color = typeColors[r.document_type] || 'bg-slate-100 text-slate-600';
                            const exp   = r.expiry_date ? (' · Exp: ' + r.expiry_date) : '';
                            return `<button type="button" onclick='selectOcrDoc(${JSON.stringify(r)})'
                                class="w-full flex items-center gap-3 px-3 py-3 rounded-xl hover:bg-indigo-50 transition text-left border border-transparent hover:border-indigo-100 mb-1">
                                <span class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 ${color}">
                                    <i class="fa-solid ${icon} text-sm"></i>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">${r.full_name || '—'}</p>
                                    <p class="text-xs text-slate-400 truncate">${r.document_type} · ${r.document_number || '—'}${exp}</p>
                                </div>
                                <span class="text-xs font-bold text-indigo-600 flex-shrink-0">Use →</span>
                            </button>`;
                        }).join('');
                    })
                    .catch(function() { el.innerHTML = '<p class="py-6 text-center text-slate-400 text-sm">Search unavailable.</p>'; });
            }, 300);
        };
        // ── Search ──
        window.doSearch = function(q) {
            clearTimeout(_st);
            const el=document.getElementById('searchResults');
            if (!q||q.trim().length<2) { el.innerHTML='<p class="search-empty px-3 py-6 text-center text-slate-400 text-sm">Type at least 2 characters to search…</p>'; return; }
            el.innerHTML='<p class="search-empty px-3 py-6 text-center text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Searching…</p>';
            _st=setTimeout(function(){
                fetch('?search_ajax=1&q='+encodeURIComponent(q))
                    .then(r=>r.json())
                    .then(function(data){
                        if(!data.length){el.innerHTML='<p class="search-empty px-3 py-6 text-center text-slate-400 text-sm">No results for "'+q+'"</p>';return;}
                        el.innerHTML=data.map(function(item){
                            const sb=item.status?`<span class="ml-auto text-[10px] font-bold bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded flex-shrink-0">${item.status}</span>`:'';
                            return `<a href="${item.url}" class="search-result-item flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 transition" onclick="closeSearch()">
                                <span class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 text-white text-xs font-black" style="background:${item.color}">
                                    <i class="fa-solid ${item.icon} text-xs"></i>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-slate-800 truncate">${item.name}</p>
                                    <p class="text-xs text-slate-400 truncate">${item.type}${item.sub?' · '+item.sub:''}</p>
                                </div>${sb}</a>`;
                        }).join('');
                    })
                    .catch(function(){el.innerHTML='<p class="search-empty px-3 py-6 text-center text-slate-400 text-sm">Search unavailable.</p>';});
            },300);
        };
        </script>

        <div id="pageContent" class="flex-1 overflow-y-auto p-4 sm:p-8 relative custom-scrollbar">
            

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
            elseif (isset($modules[$page]) && !empty($modules[$page]['umrah_module'])):
                include __DIR__ . '/agency/' . $page . '.php';
            elseif ($page === 'ocr_scanner'):
                include __DIR__ . '/agency/ocr_scanner.php';
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
