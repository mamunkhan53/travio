<?php
    if ($page === 'dashboard') {
        $ref_filter = $_SESSION['is_staff'] ? " AND reference_staff_id = {$_SESSION['staff_id']}" : "";
        $totalLeads = $conn->query("SELECT COUNT(*) FROM enquiries WHERE agency_id=$agency_id $ref_filter")->fetchColumn();
        
        $totalSales = 0; $totalTurnover = 0; $netProfit = 0; $commissionEarned = 0;
        $completedStatuses = "'Completed', 'Paid', 'Confirmed'";
        
        foreach(['passports', 'visas', 'tickets', 'umrah', 'tours'] as $tbl) {
            $row = $conn->query("SELECT COUNT(*) as cnt, SUM(selling_price) as turnover, SUM(selling_price - service_cost) as profit FROM $tbl WHERE agency_id=$agency_id AND status IN ($completedStatuses) $ref_filter")->fetch(PDO::FETCH_ASSOC);
            $totalSales += $row['cnt'] ?? 0;
            $totalTurnover += $row['turnover'] ?? 0;
            $netProfit += $row['profit'] ?? 0;
        }

        if ($_SESSION['is_staff']) {
            $commissionEarned = $netProfit * ($_SESSION['commission_rate'] / 100);
        }

        // Fetch Deadline Notifications
        $notif_filter = $_SESSION['is_staff'] ? " AND staff_id = {$_SESSION['staff_id']}" : "";
        $notifications = $conn->query("SELECT * FROM service_notifications WHERE agency_id=$agency_id AND is_read=0 AND notify_date <= CURRENT_DATE() AND deadline_date >= CURRENT_DATE() $notif_filter ORDER BY deadline_date ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Real Data Generation (Last 6 Months)
        $chartLabels = []; $salesData = []; $turnoverData = [];
        for ($i = 5; $i >= 0; $i--) {
            $mNum = date('n', strtotime("-$i months"));
            $yNum = date('Y', strtotime("-$i months"));
            $chartLabels[] = date('M', strtotime("-$i months"));
            
            $mTurnover = 0; $sCount = 0;
            foreach(['passports', 'visas', 'tickets', 'umrah', 'tours'] as $tbl) {
                $stmtTurn = $conn->query("SELECT COUNT(*) as c, SUM(selling_price) as t FROM $tbl WHERE agency_id=$agency_id AND MONTH(transaction_date)=$mNum AND YEAR(transaction_date)=$yNum AND status IN ($completedStatuses) $ref_filter")->fetch(PDO::FETCH_ASSOC);
                $mTurnover += (float)$stmtTurn['t'];
                $sCount += (int)$stmtTurn['c'];
            }
            $turnoverData[] = $mTurnover;
            $salesData[] = $sCount;
        }

        // ---- Calendar Reminders (wide date window for navigation) ----
        $cal_from = date('Y-m-d', strtotime('-3 months'));
        $cal_to   = date('Y-m-d', strtotime('+6 months'));
        $cal_sf_rf = $_SESSION['is_staff'] ? " AND rf.staff_id = "    . (int)$_SESSION['staff_id'] : "";
        $cal_sf_sn = $_SESSION['is_staff'] ? " AND sn.staff_id = "    . (int)$_SESSION['staff_id'] : "";
        $cal_sf_tb = $_SESSION['is_staff'] ? " AND reference_staff_id = " . (int)$_SESSION['staff_id'] : "";

        $cal_events = [];

        // 1. Record follow-ups (any module)
        $rfStmt = $conn->prepare(
            "SELECT rf.follow_up_date as event_date, rf.module_name, rf.record_id, rf.note
             FROM record_followups rf
             WHERE rf.agency_id = ? AND rf.follow_up_date BETWEEN ? AND ? $cal_sf_rf"
        );
        $rfStmt->execute([$agency_id, $cal_from, $cal_to]);
        foreach ($rfStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $moduleLabel = ['enquiries'=>'Lead','passports'=>'Passport','visas'=>'Visa','tickets'=>'Ticket','umrah'=>'Umrah','tours'=>'Tour'][$r['module_name']] ?? $r['module_name'];
            $cal_events[] = [
                'date'  => $r['event_date'],
                'type'  => $r['module_name'] === 'enquiries' ? 'followup_lead' : 'followup_sale',
                'title' => 'Follow-up — ' . $moduleLabel . ' #' . $r['record_id'],
                'note'  => $r['note'] ?? '',
                'url'   => '?route=app&page=query_history&table=' . $r['module_name'] . '&id=' . rawurlencode($r['record_id']),
            ];
        }

        // 2. Service deadline notifications
        $snStmt = $conn->prepare(
            "SELECT sn.deadline_date as event_date, sn.module_name, sn.sale_id, sn.customer_name, sn.notification_type
             FROM service_notifications sn
             WHERE sn.agency_id = ? AND sn.deadline_date BETWEEN ? AND ? $cal_sf_sn"
        );
        $snStmt->execute([$agency_id, $cal_from, $cal_to]);
        foreach ($snStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cal_events[] = [
                'date'  => $r['event_date'],
                'type'  => 'delivery',
                'title' => $r['notification_type'] . ' — ' . ($r['customer_name'] ?? $r['sale_id']),
                'note'  => $r['module_name'],
                'url'   => '?route=app&page=query_history&table=' . $r['module_name'] . '&id=' . rawurlencode($r['sale_id']),
            ];
        }

        // 3. Travel / departure dates from service modules
        $travelSources = [
            'tickets' => ["SELECT `date` AS ed, id, name, TRIM(CONCAT_WS(' ', airline, route)) AS extra FROM tickets WHERE agency_id={$agency_id} AND `date` BETWEEN '{$cal_from}' AND '{$cal_to}' AND `date` IS NOT NULL {$cal_sf_tb}", 'Flight'],
            'umrah'   => ["SELECT depDate AS ed, id, name, package AS extra FROM umrah WHERE agency_id={$agency_id} AND depDate BETWEEN '{$cal_from}' AND '{$cal_to}' AND depDate IS NOT NULL {$cal_sf_tb}", 'Umrah Dep.'],
            'tours'   => ["SELECT `date` AS ed, id, name, package AS extra FROM tours WHERE agency_id={$agency_id} AND `date` BETWEEN '{$cal_from}' AND '{$cal_to}' AND `date` IS NOT NULL {$cal_sf_tb}", 'Tour Dep.'],
        ];
        foreach ($travelSources as $tbl => [$sql, $label]) {
            foreach ($conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $cal_events[] = [
                    'date'  => $r['ed'],
                    'type'  => 'travel',
                    'title' => $label . ' — ' . ($r['name'] ?? ''),
                    'note'  => $r['extra'] ?? '',
                    'url'   => '?route=app&page=query_history&table=' . $tbl . '&id=' . rawurlencode($r['id']),
                ];
            }
        }

        $calendarJson = json_encode($cal_events, JSON_HEX_TAG | JSON_HEX_QUOT);

        // ---- Recent Queries & Recent Sales (unified activity feed) ----
        // A record lives in "Queries" while its status is still in-progress, and moves to "Sales"
        // the moment its status reaches Completed/Paid/Confirmed - the same definition already used
        // for Net Profit above. A follow-up note bumps a query's recency even if it was created earlier.
        $moduleLabels = ['enquiries' => 'Query', 'passports' => 'Passport', 'visas' => 'Visa', 'tickets' => 'Air Ticket', 'umrah' => 'Umrah', 'tours' => 'Tour'];
        $moduleIcons = ['enquiries' => 'fa-solid fa-user-clock', 'passports' => 'fa-solid fa-passport', 'visas' => 'fa-solid fa-file-signature', 'tickets' => 'fa-solid fa-plane', 'umrah' => 'fa-solid fa-kaaba', 'tours' => 'fa-solid fa-map-location-dot'];

        $recentFeedSql = "
            SELECT * FROM (
                SELECT id, 'enquiries' AS module_name, customer AS display_name, category AS detail, mobile, status, created_at,
                    (SELECT MAX(created_at) FROM record_followups rf WHERE rf.agency_id = $agency_id AND rf.module_name = 'enquiries' AND rf.record_id = enquiries.id) AS last_followup
                FROM enquiries WHERE agency_id = $agency_id $ref_filter
                UNION ALL
                SELECT id, 'passports', name, type, mobile, status, created_at,
                    (SELECT MAX(created_at) FROM record_followups rf WHERE rf.agency_id = $agency_id AND rf.module_name = 'passports' AND rf.record_id = passports.id)
                FROM passports WHERE agency_id = $agency_id $ref_filter
                UNION ALL
                SELECT id, 'visas', name, CONCAT_WS(' - ', country, type), mobile, status, created_at,
                    (SELECT MAX(created_at) FROM record_followups rf WHERE rf.agency_id = $agency_id AND rf.module_name = 'visas' AND rf.record_id = visas.id)
                FROM visas WHERE agency_id = $agency_id $ref_filter
                UNION ALL
                SELECT id, 'tickets', name, CONCAT_WS(' - ', airline, route), mobile, status, created_at,
                    (SELECT MAX(created_at) FROM record_followups rf WHERE rf.agency_id = $agency_id AND rf.module_name = 'tickets' AND rf.record_id = tickets.id)
                FROM tickets WHERE agency_id = $agency_id $ref_filter
                UNION ALL
                SELECT id, 'umrah', name, package, mobile, status, created_at,
                    (SELECT MAX(created_at) FROM record_followups rf WHERE rf.agency_id = $agency_id AND rf.module_name = 'umrah' AND rf.record_id = umrah.id)
                FROM umrah WHERE agency_id = $agency_id $ref_filter
                UNION ALL
                SELECT id, 'tours', name, package, mobile, status, created_at,
                    (SELECT MAX(created_at) FROM record_followups rf WHERE rf.agency_id = $agency_id AND rf.module_name = 'tours' AND rf.record_id = tours.id)
                FROM tours WHERE agency_id = $agency_id $ref_filter
            ) AS feed
            ORDER BY GREATEST(created_at, COALESCE(last_followup, created_at)) DESC
            LIMIT 150
        ";
        $recentFeed = $conn->query($recentFeedSql)->fetchAll(PDO::FETCH_ASSOC);

        $recentQueries = [];
        $recentSales = [];
        foreach ($recentFeed as $rec) {
            if (in_array($rec['status'], ['Completed', 'Paid', 'Confirmed'])) {
                if (count($recentSales) < 8) $recentSales[] = $rec;
            } else {
                if (count($recentQueries) < 8) $recentQueries[] = $rec;
            }
            if (count($recentSales) >= 8 && count($recentQueries) >= 8) break;
        }
    }
?>

                <!-- SUBSCRIPTION STATUS BANNER -->
                <?php if ($subscription['expired']): ?>
                    <div class="mb-8 bg-rose-50 border border-rose-200 rounded-2xl p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div>
                                <h3 class="font-extrabold text-rose-700 text-lg">Your subscription has expired</h3>
                                <p class="text-sm text-rose-600 mt-1">Your plan expired on <?= date('d M Y', strtotime($subscription['expires_at'])) ?>. Adding, editing, and deleting records is disabled until you renew. Only the Dashboard and Profile pages remain visible.</p>
                                <?php if (!$_SESSION['is_staff']): ?>
                                <p class="text-xs text-rose-500 mt-3 font-bold">Monthly: ৳<?= number_format($subscriptionPlans['monthly']['price'] ?? 500, 0) ?>/month or Yearly: ৳<?= number_format($subscriptionPlans['yearly']['price'] ?? 3500, 0) ?>/year.</p>
                                <?php else: ?>
                                <p class="text-xs text-rose-500 mt-3 font-bold">Please ask your agency admin to renew the subscription.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!$_SESSION['is_staff']): ?>
                        <a href="?route=app&page=subscription_payment" class="shrink-0 bg-rose-600 text-white font-bold px-6 py-3 rounded-xl shadow-lg shadow-rose-200 hover:bg-rose-700 transition text-center"><i class="fa-solid fa-arrow-rotate-right mr-2"></i>Renew Now</a>
                        <?php endif; ?>
                    </div>
                <?php elseif ($subscription['plan'] === 'Trial' && $subscription['days_left'] !== null && $subscription['days_left'] <= 7): ?>
                    <div class="mb-8 bg-amber-50 border border-amber-200 rounded-2xl p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-clock"></i></div>
                            <div>
                                <h3 class="font-extrabold text-amber-700 text-lg">Your free trial ends in <?= $subscription['days_left'] ?> day<?= $subscription['days_left'] == 1 ? '' : 's' ?></h3>
                                <p class="text-sm text-amber-600 mt-1">Trial ends on <?= date('d M Y', strtotime($subscription['expires_at'])) ?>. Renew after your trial to keep uninterrupted access to all features.</p>
                            </div>
                        </div>
                        <?php if (!$_SESSION['is_staff']): ?>
                        <a href="?route=app&page=subscription_payment" class="shrink-0 bg-amber-500 text-white font-bold px-6 py-3 rounded-xl shadow-lg shadow-amber-200 hover:bg-amber-600 transition text-center"><i class="fa-solid fa-arrow-rotate-right mr-2"></i>Renew Now</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- NOTIFICATION WIDGET -->
                <?php if (!empty($notifications)): ?>
                <div class="mb-8">
                    <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="fa-solid fa-bell text-rose-500 animate-pulse"></i> Upcoming Service Deadlines</h3>
                    <div class="bg-white rounded-2xl soft-shadow border border-rose-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-rose-50/50 text-rose-700 uppercase tracking-wider text-xs border-b border-rose-100">
                                    <tr><th class="px-6 py-4 font-bold">Customer</th><th class="px-6 py-4 font-bold">Service</th><th class="px-6 py-4 font-bold">Deadline</th><th class="px-6 py-4 font-bold">Time Left</th><th class="px-6 py-4 font-bold text-right">Action</th></tr>
                                </thead>
                                <tbody class="divide-y divide-rose-50">
                                    <?php foreach($notifications as $n): 
                                        $diff = strtotime($n['deadline_date']) - strtotime(date('Y-m-d'));
                                        $days = round($diff / (60 * 60 * 24));
                                        if ($days == 0) { $daysStr = "Today"; $dClass="bg-rose-100 text-rose-700"; }
                                        elseif ($days == 1) { $daysStr = "Tomorrow"; $dClass="bg-amber-100 text-amber-700"; }
                                        elseif ($days < 0) { $daysStr = abs($days) . " days ago"; $dClass="bg-slate-100 text-slate-500"; }
                                        else { $daysStr = "$days days"; $dClass="bg-indigo-100 text-indigo-700"; }
                                    ?>
                                    <tr class="hover:bg-rose-50/30">
                                        <td class="px-6 py-4 font-bold text-slate-800"><?= xss_clean($n['customer_name']) ?></td>
                                        <td class="px-6 py-4 font-medium text-slate-600"><?= $n['notification_type'] ?> <span class="text-xs text-slate-400 block"><?= $n['module_name'] ?> (#<?= $n['sale_id'] ?>)</span></td>
                                        <td class="px-6 py-4 font-bold"><?= date('d M Y', strtotime($n['deadline_date'])) ?></td>
                                        <td class="px-6 py-4"><span class="px-3 py-1 rounded-lg text-xs font-bold <?= $dClass ?>"><?= $daysStr ?></span></td>
                                        <td class="px-6 py-4 text-right">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="read_notification">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
                                                <button type="submit" class="text-xs bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg hover:bg-slate-50 hover:text-indigo-600 font-bold transition shadow-sm">Mark as Read</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- DASHBOARD LAYOUT -->
                <div class="flex flex-col lg:flex-row gap-8">
                    <div class="flex-1 space-y-8">
                        <!-- Stats Cards -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-white p-5 rounded-2xl soft-shadow border border-slate-100 relative overflow-hidden">
                                <div class="absolute -right-4 -top-4 w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center text-blue-500 text-2xl"><i class="fa-solid fa-users-viewfinder"></i></div>
                                <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Total Leads</p>
                                <p class="text-3xl font-extrabold text-slate-800 mt-2"><?= $totalLeads ?></p>
                            </div>
                            <div class="bg-white p-5 rounded-2xl soft-shadow border border-slate-100 relative overflow-hidden">
                                <div class="absolute -right-4 -top-4 w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center text-indigo-500 text-2xl"><i class="fa-solid fa-suitcase-rolling"></i></div>
                                <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Total Sales</p>
                                <p class="text-3xl font-extrabold text-slate-800 mt-2"><?= $totalSales ?></p>
                            </div>
                            
                            <?php if($_SESSION['is_staff']): ?>
                                <div class="bg-white p-5 rounded-2xl soft-shadow border border-slate-100 relative overflow-hidden">
                                    <div class="absolute -right-4 -top-4 w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-500 text-2xl"><i class="fa-solid fa-chart-line"></i></div>
                                    <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Profit Generated</p>
                                    <p class="text-2xl font-extrabold text-slate-800 mt-2 truncate"><?= $currencySymbol ?> <?= number_format($netProfit) ?></p>
                                </div>
                                <div class="bg-white p-5 rounded-2xl soft-shadow border border-slate-100 relative overflow-hidden">
                                    <div class="absolute -right-4 -top-4 w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center text-amber-500 text-2xl"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                                    <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Commission Earned</p>
                                    <p class="text-2xl font-extrabold text-emerald-500 mt-2 truncate"><?= $currencySymbol ?> <?= number_format($commissionEarned) ?></p>
                                </div>
                            <?php else: ?>
                                <div class="bg-white p-5 rounded-2xl soft-shadow border border-slate-100 relative overflow-hidden">
                                    <div class="absolute -right-4 -top-4 w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-500 text-2xl"><i class="fa-solid fa-wallet"></i></div>
                                    <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Total Turnover</p>
                                    <p class="text-2xl font-extrabold text-slate-800 mt-2 truncate"><?= $currencySymbol ?> <?= number_format($totalTurnover) ?></p>
                                </div>
                                <div class="bg-white p-5 rounded-2xl soft-shadow border border-slate-100 relative overflow-hidden">
                                    <div class="absolute -right-4 -top-4 w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center text-amber-500 text-2xl"><i class="fa-solid fa-chart-line"></i></div>
                                    <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Net Profit</p>
                                    <p class="text-2xl font-extrabold <?= $netProfit >= 0 ? 'text-emerald-500' : 'text-rose-500' ?> mt-2 truncate"><?= $currencySymbol ?> <?= number_format($netProfit) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Chart + Calendar: 50/50 grid -->
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                            <!-- LEFT: Monthly Performance Trend -->
                            <div class="bg-white p-6 rounded-2xl soft-shadow border border-slate-100">
                                <h3 class="font-extrabold text-slate-800 mb-6 text-lg"><i class="fa-solid fa-chart-area text-indigo-500 mr-2"></i> Monthly Performance Trend</h3>
                                <canvas id="mainChart" height="140"></canvas>
                            </div>
                            <script>
                                new Chart(document.getElementById('mainChart'), {
                                    type: 'bar',
                                    data: {
                                        labels: <?= json_encode($chartLabels) ?>,
                                        datasets: [
                                            { label: 'Turnover (<?= $currencySymbol ?>)', data: <?= json_encode($turnoverData) ?>, backgroundColor: '#4f46e5', borderRadius: 4, order: 2 },
                                            { label: 'Sales (Count)', data: <?= json_encode($salesData) ?>, type: 'line', borderColor: '#10b981', backgroundColor: '#10b981', borderWidth: 3, tension: 0.3, order: 1 }
                                        ]
                                    },
                                    options: { responsive: true, interaction: { mode: 'index', intersect: false } }
                                });
                            </script>

                            <!-- RIGHT: Calendar Widget -->
                            <div class="bg-white rounded-2xl soft-shadow border border-slate-100 flex flex-col overflow-hidden">

                                <!-- Calendar header -->
                                <div class="px-5 pt-5 pb-3 border-b border-slate-100">
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="font-extrabold text-slate-800 text-base flex items-center gap-2">
                                            <i class="fa-solid fa-calendar-days text-indigo-500"></i> Reminders
                                        </h3>
                                        <div class="flex items-center gap-1.5">
                                            <button id="calPrev" onclick="calNav(-1)"
                                                class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-indigo-100 hover:text-indigo-600 text-slate-600 flex items-center justify-center transition text-sm font-bold">
                                                <i class="fa-solid fa-chevron-left"></i>
                                            </button>
                                            <select id="calMonthSel" onchange="calJump()"
                                                class="border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none bg-white">
                                                <?php foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $mi => $mn): ?>
                                                <option value="<?= $mi ?>"><?= $mn ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select id="calYearSel" onchange="calJump()"
                                                class="border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none bg-white">
                                                <?php for($y = date('Y')-2; $y <= date('Y')+3; $y++): ?>
                                                <option value="<?= $y ?>"><?= $y ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <button id="calNext" onclick="calNav(1)"
                                                class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-indigo-100 hover:text-indigo-600 text-slate-600 flex items-center justify-center transition text-sm font-bold">
                                                <i class="fa-solid fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Filter pills -->
                                    <div class="flex flex-wrap gap-1.5 mt-3" id="calFilters">
                                        <button onclick="calSetFilter('all')"       data-f="all"           class="cal-pill active">All</button>
                                        <button onclick="calSetFilter('followup')"  data-f="followup"      class="cal-pill">Follow-ups</button>
                                        <button onclick="calSetFilter('delivery')"  data-f="delivery"      class="cal-pill">Deliveries</button>
                                        <button onclick="calSetFilter('lead')"      data-f="lead"          class="cal-pill">Leads</button>
                                        <button onclick="calSetFilter('travel')"    data-f="travel"        class="cal-pill">Travel</button>
                                    </div>
                                </div>

                                <!-- Day-of-week headers -->
                                <div class="grid grid-cols-7 text-center text-[10px] font-extrabold text-slate-400 uppercase tracking-wider px-3 pt-3 pb-1">
                                    <?php foreach(['Su','Mo','Tu','We','Th','Fr','Sa'] as $d): ?>
                                    <div class="py-1"><?= $d ?></div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Calendar grid (rendered by JS) -->
                                <div id="calGrid" class="grid grid-cols-7 gap-px px-3 pb-3 flex-1"></div>

                                <!-- Selected-day event list -->
                                <div id="calDayPanel" class="hidden border-t border-slate-100 bg-slate-50/60 px-4 py-3 text-sm" style="max-height:160px; overflow-y:auto;">
                                    <p id="calDayTitle" class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2"></p>
                                    <div id="calDayList" class="space-y-1.5"></div>
                                </div>
                            </div>
                        </div><!-- end Chart+Calendar grid -->

                        <!-- Tooltip (shared, positioned by JS) -->
                        <div id="calTooltip"
                             class="fixed z-50 hidden pointer-events-none bg-slate-900 text-white text-xs rounded-xl shadow-2xl px-3 py-2.5 max-w-xs leading-relaxed"
                             style="transform: translate(-50%, calc(-100% - 10px));"></div>

                        <style>
                            .cal-pill {
                                padding: 3px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700;
                                border: 1.5px solid #e2e8f0; color: #64748b; background: #fff;
                                cursor: pointer; transition: all .15s;
                            }
                            .cal-pill:hover  { border-color: #6366f1; color: #6366f1; }
                            .cal-pill.active { background: #6366f1; border-color: #6366f1; color: #fff; }
                            .cal-day {
                                min-height: 44px; border-radius: 10px; padding: 4px 3px 3px;
                                cursor: pointer; position: relative; transition: background .12s;
                                display: flex; flex-direction: column; align-items: center;
                            }
                            .cal-day:hover   { background: #f1f5f9; }
                            .cal-day.today   { background: #eef2ff; }
                            .cal-day.today .cal-day-num { background: #6366f1; color: #fff; }
                            .cal-day.selected { background: #e0e7ff; }
                            .cal-day.other-month .cal-day-num { color: #cbd5e1; }
                            .cal-day-num {
                                width: 24px; height: 24px; border-radius: 50%;
                                display: flex; align-items: center; justify-content: center;
                                font-size: 11px; font-weight: 700; color: #475569; line-height: 1;
                            }
                            .cal-dots { display: flex; gap: 2px; flex-wrap: wrap; justify-content: center; margin-top: 2px; }
                            .cal-dot  { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
                            .cal-badge {
                                position: absolute; top: 2px; right: 3px;
                                background: #6366f1; color: #fff;
                                font-size: 9px; font-weight: 800; min-width: 14px; height: 14px;
                                border-radius: 7px; padding: 0 3px;
                                display: flex; align-items: center; justify-content: center;
                                line-height: 1;
                            }
                        </style>

                        <script>
                        (function() {
                            const EVENTS     = <?= $calendarJson ?>;
                            const TYPE_COLOR = {
                                followup_lead: '#6366f1',  // indigo  – Leads
                                followup_sale: '#f59e0b',  // amber   – Follow-ups
                                delivery:      '#f43f5e',  // rose    – Deliveries
                                travel:        '#10b981',  // emerald – Travel
                            };
                            const TYPE_LABEL = {
                                followup_lead: 'Lead Follow-up',
                                followup_sale: 'Follow-up',
                                delivery:      'Delivery',
                                travel:        'Travel',
                            };

                            let curYear  = <?= date('Y') ?>;
                            let curMonth = <?= date('n') - 1 ?>;   // 0-based
                            let activeFilter = 'all';
                            let selectedDate = null;

                            const tooltip = document.getElementById('calTooltip');

                            // ---- Filter helpers ----
                            function typeMatchesFilter(type) {
                                if (activeFilter === 'all')      return true;
                                if (activeFilter === 'followup') return type === 'followup_lead' || type === 'followup_sale';
                                if (activeFilter === 'delivery') return type === 'delivery';
                                if (activeFilter === 'lead')     return type === 'followup_lead';
                                if (activeFilter === 'travel')   return type === 'travel';
                                return true;
                            }

                            // Events keyed by YYYY-MM-DD for current filter
                            function buildIndex() {
                                const idx = {};
                                EVENTS.forEach(e => {
                                    if (!typeMatchesFilter(e.type)) return;
                                    if (!idx[e.date]) idx[e.date] = [];
                                    idx[e.date].push(e);
                                });
                                return idx;
                            }

                            // ---- Render calendar ----
                            function renderCalendar() {
                                const idx   = buildIndex();
                                const grid  = document.getElementById('calGrid');
                                const mSel  = document.getElementById('calMonthSel');
                                const ySel  = document.getElementById('calYearSel');
                                mSel.value  = curMonth;
                                ySel.value  = curYear;

                                const today = new Date(); today.setHours(0,0,0,0);
                                const firstDay  = new Date(curYear, curMonth, 1);
                                const lastDay   = new Date(curYear, curMonth + 1, 0);
                                const startDow  = firstDay.getDay();   // 0=Sun
                                const totalDays = lastDay.getDate();

                                let html = '';

                                // Leading blanks (previous month days)
                                const prevLast = new Date(curYear, curMonth, 0).getDate();
                                for (let i = startDow - 1; i >= 0; i--) {
                                    const d = prevLast - i;
                                    const m = curMonth === 0 ? 11 : curMonth - 1;
                                    const y = curMonth === 0 ? curYear - 1 : curYear;
                                    const ds = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                                    html += dayCell(d, ds, idx[ds] || [], true, false);
                                }

                                // Current month days
                                for (let d = 1; d <= totalDays; d++) {
                                    const ds = `${curYear}-${String(curMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                                    const dt = new Date(curYear, curMonth, d);
                                    const isToday = dt.getTime() === today.getTime();
                                    const isSel   = ds === selectedDate;
                                    html += dayCell(d, ds, idx[ds] || [], false, isToday, isSel);
                                }

                                // Trailing blanks (next month)
                                const trailCount = 42 - startDow - totalDays;
                                for (let d = 1; d <= trailCount; d++) {
                                    const m = curMonth === 11 ? 0 : curMonth + 1;
                                    const y = curMonth === 11 ? curYear + 1 : curYear;
                                    const ds = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                                    html += dayCell(d, ds, idx[ds] || [], true, false);
                                }

                                grid.innerHTML = html;

                                // Attach events after render
                                grid.querySelectorAll('.cal-day').forEach(cell => {
                                    const ds     = cell.dataset.date;
                                    const events = idx[ds] || [];

                                    cell.addEventListener('click', () => onDayClick(ds, events));

                                    if (events.length > 0) {
                                        cell.addEventListener('mouseenter', e => showTooltip(e, events));
                                        cell.addEventListener('mousemove',  e => moveTooltip(e));
                                        cell.addEventListener('mouseleave',    hideTooltip);
                                    }
                                });

                                // Restore selected day panel
                                if (selectedDate) {
                                    const events = idx[selectedDate] || [];
                                    renderDayPanel(selectedDate, events);
                                }
                            }

                            function dayCell(dayNum, ds, events, otherMonth, isToday, isSel) {
                                const classes = [
                                    'cal-day',
                                    otherMonth ? 'other-month' : '',
                                    isToday    ? 'today'       : '',
                                    isSel      ? 'selected'    : '',
                                ].filter(Boolean).join(' ');

                                // Collect unique dot colors (max 3 types)
                                const seenTypes = [];
                                events.forEach(e => { if (!seenTypes.includes(e.type)) seenTypes.push(e.type); });
                                const dots = seenTypes.slice(0, 3).map(t =>
                                    `<span class="cal-dot" style="background:${TYPE_COLOR[t]}"></span>`
                                ).join('');
                                const badge = events.length > 3
                                    ? `<span class="cal-badge">${events.length}</span>`
                                    : (events.length > 0 ? `<span class="cal-badge" style="background:${TYPE_COLOR[events[0].type]}">${events.length}</span>` : '');

                                return `<div class="${classes}" data-date="${ds}">
                                    ${badge}
                                    <span class="cal-day-num">${dayNum}</span>
                                    ${dots ? `<div class="cal-dots">${dots}</div>` : ''}
                                </div>`;
                            }

                            function onDayClick(ds, events) {
                                selectedDate = (selectedDate === ds) ? null : ds;
                                // Re-render to update selected state
                                renderCalendar();
                            }

                            function renderDayPanel(ds, events) {
                                const panel = document.getElementById('calDayPanel');
                                const title = document.getElementById('calDayTitle');
                                const list  = document.getElementById('calDayList');

                                if (!selectedDate || events.length === 0) {
                                    panel.classList.add('hidden');
                                    return;
                                }

                                const [y, m, d] = ds.split('-');
                                const dateObj = new Date(+y, +m - 1, +d);
                                const fmt = dateObj.toLocaleDateString('en-US', { weekday:'long', month:'long', day:'numeric', year:'numeric' });

                                title.textContent = fmt;
                                list.innerHTML = events.map(e => {
                                    const col = TYPE_COLOR[e.type];
                                    const lbl = TYPE_LABEL[e.type];
                                    return `<a href="${e.url}"
                                        class="flex items-start gap-2 px-3 py-2 rounded-xl hover:bg-white border border-transparent hover:border-slate-200 transition group">
                                        <span class="w-2 h-2 rounded-full mt-1 flex-shrink-0" style="background:${col}"></span>
                                        <div class="flex-1 min-w-0">
                                            <span class="text-xs font-extrabold text-slate-700 group-hover:text-indigo-600 leading-tight block truncate">${escHtml(e.title)}</span>
                                            ${e.note ? `<span class="text-[10px] text-slate-400 block truncate">${escHtml(e.note)}</span>` : ''}
                                        </div>
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 mt-0.5"
                                              style="background:${col}22; color:${col}">${lbl}</span>
                                    </a>`;
                                }).join('');

                                panel.classList.remove('hidden');
                            }

                            // ---- Tooltip ----
                            function showTooltip(e, events) {
                                const lines = events.slice(0, 6).map(ev => {
                                    const col = TYPE_COLOR[ev.type];
                                    return `<span style="display:flex;align-items:center;gap:5px"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:${col};flex-shrink:0"></span>${escHtml(ev.title)}</span>`;
                                });
                                if (events.length > 6) lines.push(`<span style="color:#94a3b8">+ ${events.length - 6} more</span>`);
                                tooltip.innerHTML = lines.join('');
                                tooltip.classList.remove('hidden');
                                moveTooltip(e);
                            }
                            function moveTooltip(e) {
                                tooltip.style.left = e.clientX + 'px';
                                tooltip.style.top  = e.clientY + window.scrollY + 'px';
                            }
                            function hideTooltip() { tooltip.classList.add('hidden'); }

                            // ---- Public functions (called by inline handlers) ----
                            window.calNav = function(dir) {
                                curMonth += dir;
                                if (curMonth > 11) { curMonth = 0;  curYear++; }
                                if (curMonth < 0)  { curMonth = 11; curYear--; }
                                selectedDate = null;
                                renderCalendar();
                            };
                            window.calJump = function() {
                                curMonth = +document.getElementById('calMonthSel').value;
                                curYear  = +document.getElementById('calYearSel').value;
                                selectedDate = null;
                                renderCalendar();
                            };
                            window.calSetFilter = function(f) {
                                activeFilter = f;
                                document.querySelectorAll('.cal-pill').forEach(b => {
                                    b.classList.toggle('active', b.dataset.f === f);
                                });
                                renderCalendar();
                            };

                            function escHtml(s) {
                                return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                            }

                            // Boot
                            renderCalendar();
                        })();
                        </script>
                    </div>

                    <!-- Right Summary Panel -->
                    <div class="w-full lg:w-80 space-y-6">
                        <?php if(!$_SESSION['is_staff']): ?>
                        <!-- Profile Card (Admin) -->
                        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6 text-center">
                            <div class="w-20 h-20 mx-auto rounded-full bg-indigo-100 border-4 border-white shadow flex items-center justify-center text-3xl text-indigo-500 mb-4 overflow-hidden">
                                <img src="<?= $logoSrc ?>" class="w-full h-full object-cover">
                            </div>
                            <h3 class="font-bold text-lg text-slate-800"><?= xss_clean($agency['company_name']) ?></h3>
                            <p class="text-sm text-slate-500 mb-4"><?= xss_clean($agency['company_email']) ?></p>
                            <a href="?route=app&page=profile" class="inline-block text-xs font-bold text-indigo-600 bg-indigo-50 px-4 py-2 rounded-full hover:bg-indigo-100 transition">Manage Profile</a>
                        </div>
                        <?php else: ?>
                        <!-- Staff Details Card -->
                        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6 text-center">
                            <div class="w-20 h-20 mx-auto rounded-full bg-indigo-100 border-4 border-white shadow flex items-center justify-center text-3xl text-indigo-500 mb-4">
                                <i class="fa-solid fa-user-tie"></i>
                            </div>
                            <h3 class="font-bold text-lg text-slate-800"><?php $cu = $conn->query("SELECT full_name FROM staff WHERE id=".$_SESSION['staff_id'])->fetch(); echo xss_clean($cu['full_name']); ?></h3>
                            <p class="text-sm font-bold text-indigo-500 mb-4"><?= $_SESSION['staff_role'] ?></p>
                            <a href="?route=app&page=profile" class="inline-block text-xs font-bold text-indigo-600 bg-indigo-50 px-4 py-2 rounded-full hover:bg-indigo-100 transition">My Profile</a>
                        </div>
                        <?php endif; ?>

                        <!-- Weekly Stats -->
                        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6 bg-gradient-to-br from-indigo-900 to-indigo-700 text-white relative overflow-hidden">
                            <i class="fa-solid fa-chart-line absolute -right-6 -bottom-6 text-indigo-500/30 text-8xl"></i>
                            <h4 class="font-bold mb-4 text-sm uppercase tracking-wider relative z-10">This Month</h4>
                            <div class="space-y-4 relative z-10">
                                <div>
                                    <p class="text-indigo-200 text-xs">New Leads</p>
                                    <p class="text-2xl font-bold"><?= $leadsData[5] ?? 0 ?></p>
                                </div>
                                <div>
                                    <p class="text-indigo-200 text-xs">Turnover</p>
                                    <p class="text-2xl font-bold"><?= $currencySymbol ?> <?= number_format($turnoverData[5] ?? 0) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RECENT QUERIES & RECENT SALES -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
                    <!-- Recent Queries -->
                    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="font-extrabold text-slate-800 text-lg flex items-center gap-2"><i class="fa-solid fa-users-viewfinder text-amber-500"></i> Recent Queries</h3>
                            <a href="?route=app&page=enquiries" class="text-xs font-bold text-indigo-600 hover:underline shrink-0">View All</a>
                        </div>
                        <div class="divide-y divide-slate-100 max-h-[420px] overflow-y-auto custom-scrollbar">
                            <?php if (empty($recentQueries)): ?>
                                <p class="p-8 text-center text-slate-400 text-sm font-medium">No active queries right now.</p>
                            <?php endif; ?>
                            <?php foreach ($recentQueries as $rq):
                                $rqActivity = max(strtotime($rq['created_at']), strtotime($rq['last_followup'] ?: $rq['created_at']));
                            ?>
                            <a href="?route=app&page=query_history&table=<?= $rq['module_name'] ?>&id=<?= urlencode($rq['id']) ?>" class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50 transition">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-500 flex items-center justify-center shrink-0 text-sm"><i class="<?= $moduleIcons[$rq['module_name']] ?>"></i></div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-slate-800 truncate"><?= xss_clean($rq['display_name'] ?: 'Unnamed') ?></p>
                                    <p class="text-xs text-slate-500 truncate"><?= xss_clean($moduleLabels[$rq['module_name']]) ?><?= $rq['detail'] ? ' &middot; ' . xss_clean($rq['detail']) : '' ?></p>
                                    <?php if (!empty($rq['last_followup'])): ?>
                                        <p class="text-[11px] text-indigo-400 font-bold mt-0.5"><i class="fa-solid fa-comment-dots mr-1"></i>Followed up <?= timeAgo($rq['last_followup']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-amber-100 text-amber-700"><?= xss_clean($rq['status'] ?: 'Pending') ?></span>
                                    <p class="text-[11px] text-slate-400 mt-1"><?= timeAgo($rqActivity) ?></p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent Sales -->
                    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                            <h3 class="font-extrabold text-slate-800 text-lg flex items-center gap-2"><i class="fa-solid fa-money-check-dollar text-emerald-500"></i> Recent Sales</h3>
                        </div>
                        <div class="divide-y divide-slate-100 max-h-[420px] overflow-y-auto custom-scrollbar">
                            <?php if (empty($recentSales)): ?>
                                <p class="p-8 text-center text-slate-400 text-sm font-medium">No completed sales yet.</p>
                            <?php endif; ?>
                            <?php foreach ($recentSales as $rs): ?>
                            <a href="?route=app&page=query_history&table=<?= $rs['module_name'] ?>&id=<?= urlencode($rs['id']) ?>" class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50 transition">
                                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center shrink-0 text-sm"><i class="<?= $moduleIcons[$rs['module_name']] ?>"></i></div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-slate-800 truncate"><?= xss_clean($rs['display_name'] ?: 'Unnamed') ?></p>
                                    <p class="text-xs text-slate-500 truncate"><?= xss_clean($moduleLabels[$rs['module_name']]) ?><?= $rs['detail'] ? ' &middot; ' . xss_clean($rs['detail']) : '' ?></p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-100 text-emerald-700"><?= xss_clean($rs['status']) ?></span>
                                    <p class="text-[11px] text-slate-400 mt-1"><?= timeAgo($rs['created_at']) ?></p>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
