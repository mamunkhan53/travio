<?php
    if ($page === 'dashboard') {
        $ref_filter = $_SESSION['is_staff'] ? " AND reference_staff_id = {$_SESSION['staff_id']}" : "";
        $totalLeads = $conn->query("SELECT COUNT(*) FROM enquiries WHERE agency_id=$agency_id $ref_filter")->fetchColumn();

        $totalSales = 0; $totalTurnover = 0; $netProfit = 0; $commissionEarned = 0;
        $completedStatuses = "'Completed', 'Paid', 'Confirmed'";

        foreach(['passports', 'visas', 'tickets', 'umrah', 'tours'] as $tbl) {
            $row = $conn->query("SELECT COUNT(*) as cnt, SUM(selling_price) as turnover, SUM(selling_price - service_cost) as profit FROM $tbl WHERE agency_id=$agency_id AND status IN ($completedStatuses) $ref_filter")->fetch(PDO::FETCH_ASSOC);
            $totalSales    += $row['cnt']     ?? 0;
            $totalTurnover += $row['turnover'] ?? 0;
            $netProfit     += $row['profit']   ?? 0;
        }

        if ($_SESSION['is_staff']) {
            $commissionEarned = $netProfit * ($_SESSION['commission_rate'] / 100);
        }

        // Deadline Notifications
        $notif_filter  = $_SESSION['is_staff'] ? " AND staff_id = {$_SESSION['staff_id']}" : "";
        $notifications = $conn->query("SELECT * FROM service_notifications WHERE agency_id=$agency_id AND is_read=0 AND notify_date <= CURRENT_DATE() AND deadline_date >= CURRENT_DATE() $notif_filter ORDER BY deadline_date ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Legacy 6-month chart data (kept for compat, not rendered in new UI)
        $chartLabels = []; $salesData = []; $turnoverData = [];
        for ($i = 5; $i >= 0; $i--) {
            $mNum = date('n', strtotime("-$i months"));
            $yNum = date('Y', strtotime("-$i months"));
            $chartLabels[] = date('M', strtotime("-$i months"));
            $mTurnover = 0; $sCount = 0;
            foreach(['passports', 'visas', 'tickets', 'umrah', 'tours'] as $tbl) {
                $stmtTurn = $conn->query("SELECT COUNT(*) as c, SUM(selling_price) as t FROM $tbl WHERE agency_id=$agency_id AND MONTH(transaction_date)=$mNum AND YEAR(transaction_date)=$yNum AND status IN ($completedStatuses) $ref_filter")->fetch(PDO::FETCH_ASSOC);
                $mTurnover += (float)$stmtTurn['t'];
                $sCount    += (int)$stmtTurn['c'];
            }
            $turnoverData[] = $mTurnover;
            $salesData[]    = $sCount;
        }

        // ---- Calendar Reminders ----
        $cal_from  = date('Y-m-d', strtotime('-3 months'));
        $cal_to    = date('Y-m-d', strtotime('+6 months'));
        $cal_sf_rf = $_SESSION['is_staff'] ? " AND rf.staff_id = "           . (int)$_SESSION['staff_id'] : "";
        $cal_sf_sn = $_SESSION['is_staff'] ? " AND sn.staff_id = "           . (int)$_SESSION['staff_id'] : "";
        $cal_sf_tb = $_SESSION['is_staff'] ? " AND reference_staff_id = "    . (int)$_SESSION['staff_id'] : "";
        $cal_events = [];

        $rfStmt = $conn->prepare("SELECT rf.follow_up_date as event_date, rf.module_name, rf.record_id, rf.note FROM record_followups rf WHERE rf.agency_id = ? AND rf.follow_up_date BETWEEN ? AND ? $cal_sf_rf");
        $rfStmt->execute([$agency_id, $cal_from, $cal_to]);
        foreach ($rfStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $moduleLabel = ['enquiries'=>'Lead','passports'=>'Passport','visas'=>'Visa','tickets'=>'Ticket','umrah'=>'Umrah','tours'=>'Tour'][$r['module_name']] ?? $r['module_name'];
            $cal_events[] = ['date'=>$r['event_date'],'type'=>$r['module_name']==='enquiries'?'followup_lead':'followup_sale','title'=>'Follow-up — '.$moduleLabel.' #'.$r['record_id'],'note'=>$r['note']??'','url'=>'/app/query_history?table='.$r['module_name'].'&id='.rawurlencode($r['record_id'])];
        }

        $snStmt = $conn->prepare("SELECT sn.deadline_date as event_date, sn.module_name, sn.sale_id, sn.customer_name, sn.notification_type FROM service_notifications sn WHERE sn.agency_id = ? AND sn.deadline_date BETWEEN ? AND ? $cal_sf_sn");
        $snStmt->execute([$agency_id, $cal_from, $cal_to]);
        foreach ($snStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cal_events[] = ['date'=>$r['event_date'],'type'=>'delivery','title'=>$r['notification_type'].' — '.($r['customer_name']??$r['sale_id']),'note'=>$r['module_name'],'url'=>'/app/query_history?table='.$r['module_name'].'&id='.rawurlencode($r['sale_id'])];
        }

        $travelSources = [
            'tickets' => ["SELECT `date` AS ed, id, name, TRIM(CONCAT_WS(' ', airline, route)) AS extra FROM tickets WHERE agency_id={$agency_id} AND `date` BETWEEN '{$cal_from}' AND '{$cal_to}' AND `date` IS NOT NULL {$cal_sf_tb}", 'Flight'],
            'umrah'   => ["SELECT depDate AS ed, id, name, package AS extra FROM umrah WHERE agency_id={$agency_id} AND depDate BETWEEN '{$cal_from}' AND '{$cal_to}' AND depDate IS NOT NULL {$cal_sf_tb}", 'Umrah Dep.'],
            'tours'   => ["SELECT `date` AS ed, id, name, package AS extra FROM tours WHERE agency_id={$agency_id} AND `date` BETWEEN '{$cal_from}' AND '{$cal_to}' AND `date` IS NOT NULL {$cal_sf_tb}", 'Tour Dep.'],
        ];
        foreach ($travelSources as $tbl => [$sql, $label]) {
            foreach ($conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $cal_events[] = ['date'=>$r['ed'],'type'=>'travel','title'=>$label.' — '.($r['name']??''),'note'=>$r['extra']??'','url'=>'/app/query_history?table='.$tbl.'&id='.rawurlencode($r['id'])];
            }
        }

        $scVisaStmt = $conn->prepare("SELECT v.id, v.destination_country, v.biometrics_date, v.medical_date, v.interview_date, v.decision_date, s.student_name FROM sc_visa v JOIN sc_students s ON v.student_id = s.id WHERE v.agency_id = ? AND ((v.biometrics_date BETWEEN ? AND ?) OR (v.medical_date BETWEEN ? AND ?) OR (v.interview_date BETWEEN ? AND ?) OR (v.decision_date BETWEEN ? AND ?))");
        $scVisaStmt->execute([$agency_id, $cal_from,$cal_to, $cal_from,$cal_to, $cal_from,$cal_to, $cal_from,$cal_to]);
        foreach ($scVisaStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            foreach (['biometrics_date'=>'Biometrics','medical_date'=>'Medical','interview_date'=>'Visa Interview','decision_date'=>'Visa Decision'] as $col => $lbl) {
                if (!empty($r[$col]) && $r[$col] >= $cal_from && $r[$col] <= $cal_to) {
                    $cal_events[] = ['date'=>$r[$col],'type'=>'travel','title'=>$lbl.' — '.$r['student_name'],'note'=>$r['destination_country']??'','url'=>'/app/sc_visa'];
                }
            }
        }
        $calendarJson = json_encode($cal_events, JSON_HEX_TAG | JSON_HEX_QUOT);

        // ---- Leads Generated Chart (Weekly / Monthly / Yearly) ----
        // Weekly: last 12 weeks
        $leadsWeekLabels = []; $leadsWeekData = [];
        for ($i = 11; $i >= 0; $i--) {
            $ts    = strtotime("-$i weeks");
            $wFrom = date('Y-m-d', strtotime('monday this week', $ts));
            $wTo   = ($i === 0) ? date('Y-m-d') : date('Y-m-d', strtotime('sunday this week', $ts));
            $leadsWeekLabels[] = 'Wk ' . date('W', strtotime($wFrom));
            $cnt = $conn->prepare("SELECT COUNT(*) FROM enquiries WHERE agency_id=? AND DATE(created_at) BETWEEN ? AND ? $ref_filter");
            $cnt->execute([$agency_id, $wFrom, $wTo]);
            $leadsWeekData[] = (int)$cnt->fetchColumn();
        }
        // Monthly: last 12 months
        $leadsMonthLabels = []; $leadsMonthData = [];
        for ($i = 11; $i >= 0; $i--) {
            $mFrom = date('Y-m-01', strtotime("-$i months"));
            $mTo   = date('Y-m-t',  strtotime("-$i months"));
            $leadsMonthLabels[] = date('M y', strtotime($mFrom));
            $cnt = $conn->prepare("SELECT COUNT(*) FROM enquiries WHERE agency_id=? AND DATE(created_at) BETWEEN ? AND ? $ref_filter");
            $cnt->execute([$agency_id, $mFrom, $mTo]);
            $leadsMonthData[] = (int)$cnt->fetchColumn();
        }
        // Yearly: last 5 years
        $leadsYearLabels = []; $leadsYearData = [];
        for ($i = 4; $i >= 0; $i--) {
            $y = (int)date('Y') - $i;
            $leadsYearLabels[] = (string)$y;
            $cnt = $conn->prepare("SELECT COUNT(*) FROM enquiries WHERE agency_id=? AND YEAR(created_at)=? $ref_filter");
            $cnt->execute([$agency_id, $y]);
            $leadsYearData[] = (int)$cnt->fetchColumn();
        }

        // ---- Recent Feed (queries + sales) ----
        $moduleLabels = ['enquiries'=>'Query','passports'=>'Passport','visas'=>'Visa','tickets'=>'Air Ticket','umrah'=>'Umrah','tours'=>'Tour'];
        $moduleIcons  = ['enquiries'=>'fa-solid fa-user-clock','passports'=>'fa-solid fa-passport','visas'=>'fa-solid fa-file-signature','tickets'=>'fa-solid fa-plane','umrah'=>'fa-solid fa-kaaba','tours'=>'fa-solid fa-map-location-dot'];
        $moduleBadgeColors = ['enquiries'=>'bg-purple-100 text-purple-700','passports'=>'bg-blue-100 text-blue-700','visas'=>'bg-teal-100 text-teal-700','tickets'=>'bg-sky-100 text-sky-700','umrah'=>'bg-green-100 text-green-700','tours'=>'bg-orange-100 text-orange-700'];

        $recentFeedSql = "
            SELECT * FROM (
                SELECT id, 'enquiries' AS module_name, customer AS display_name, category AS detail, mobile, status, created_at,
                    (SELECT MAX(created_at) FROM record_followups rf WHERE rf.agency_id=$agency_id AND rf.module_name='enquiries' AND rf.record_id=enquiries.id) AS last_followup,
                    NULL as selling_price, reference_staff_id
                FROM enquiries WHERE agency_id=$agency_id $ref_filter
                UNION ALL
                SELECT id,'passports',name,type,mobile,status,created_at,
                    (SELECT MAX(created_at) FROM record_followups rf WHERE rf.agency_id=$agency_id AND rf.module_name='passports' AND rf.record_id=passports.id),
                    selling_price, reference_staff_id
                FROM passports WHERE agency_id=$agency_id $ref_filter
                UNION ALL
                SELECT id,'visas',name,CONCAT_WS(' - ',country,type),mobile,status,created_at,
                    (SELECT MAX(created_at) FROM record_followups rf WHERE rf.agency_id=$agency_id AND rf.module_name='visas' AND rf.record_id=visas.id),
                    selling_price, reference_staff_id
                FROM visas WHERE agency_id=$agency_id $ref_filter
                UNION ALL
                SELECT id,'tickets',name,CONCAT_WS(' - ',airline,route),mobile,status,created_at,
                    (SELECT MAX(created_at) FROM record_followups rf WHERE rf.agency_id=$agency_id AND rf.module_name='tickets' AND rf.record_id=tickets.id),
                    selling_price, reference_staff_id
                FROM tickets WHERE agency_id=$agency_id $ref_filter
                UNION ALL
                SELECT id,'umrah',name,package,mobile,status,created_at,
                    (SELECT MAX(created_at) FROM record_followups rf WHERE rf.agency_id=$agency_id AND rf.module_name='umrah' AND rf.record_id=umrah.id),
                    selling_price, reference_staff_id
                FROM umrah WHERE agency_id=$agency_id $ref_filter
                UNION ALL
                SELECT id,'tours',name,package,mobile,status,created_at,
                    (SELECT MAX(created_at) FROM record_followups rf WHERE rf.agency_id=$agency_id AND rf.module_name='tours' AND rf.record_id=tours.id),
                    selling_price, reference_staff_id
                FROM tours WHERE agency_id=$agency_id $ref_filter
            ) AS feed
            ORDER BY GREATEST(created_at, COALESCE(last_followup, created_at)) DESC
            LIMIT 150
        ";
        $recentFeed = $conn->query($recentFeedSql)->fetchAll(PDO::FETCH_ASSOC);

        $recentQueries = []; $recentSales = [];
        foreach ($recentFeed as $rec) {
            if (in_array($rec['status'], ['Completed','Paid','Confirmed'])) {
                if (count($recentSales)   < 8) $recentSales[]   = $rec;
            } else {
                if (count($recentQueries) < 8) $recentQueries[] = $rec;
            }
            if (count($recentSales) >= 8 && count($recentQueries) >= 8) break;
        }
    }
?>

<style>
/* ── Dashboard wrapper dark mode ── */
[data-dark="1"] #dashWrapper { background:#0b1120; }
[data-dark="1"] #dashWrapper .dash-card {
    background:#1e2537 !important; border-color:#2d3748 !important; color:#f1f5f9 !important;
}
[data-dark="1"] #dashWrapper .dash-card p,
[data-dark="1"] #dashWrapper .dash-card span,
[data-dark="1"] #dashWrapper .dash-card td,
[data-dark="1"] #dashWrapper .dash-card th,
[data-dark="1"] #dashWrapper .dash-card div { color:inherit; }
[data-dark="1"] #dashWrapper .dash-label  { color:#94a3b8 !important; }
[data-dark="1"] #dashWrapper .dash-value  { color:#f8fafc !important; }
[data-dark="1"] #dashWrapper .dash-sub    { color:#64748b !important; }
[data-dark="1"] #dashWrapper .dash-divider { border-color:#2d3748 !important; }
[data-dark="1"] #dashWrapper .dash-row-hover:hover { background:#273249 !important; }
[data-dark="1"] #dashWrapper .dash-thead { background:#151f35 !important; color:#94a3b8 !important; }
[data-dark="1"] #dashWrapper .dash-scrollable { background:#1e2537 !important; }
[data-dark="1"] #dashWrapper .cal-pill { background:#1e2537 !important; border-color:#334155 !important; color:#94a3b8 !important; }
[data-dark="1"] #dashWrapper .cal-pill.active { background:#6366f1 !important; border-color:#6366f1 !important; color:#fff !important; }
[data-dark="1"] #dashWrapper .cal-day:hover { background:#273249 !important; }
[data-dark="1"] #dashWrapper .cal-day.today  { background:#1e2f50 !important; }
[data-dark="1"] #dashWrapper .cal-day-num    { color:#cbd5e1 !important; }
[data-dark="1"] #dashWrapper .cal-day.other-month .cal-day-num { color:#3d4f6b !important; }
[data-dark="1"] #dashWrapper select,
[data-dark="1"] #dashWrapper input[type=date] { background:#1e2537 !important; border-color:#334155 !important; color:#f1f5f9 !important; }
[data-dark="1"] #dashWrapper #calDayPanel { background:#151f35 !important; border-color:#2d3748 !important; }
[data-dark="1"] #dashWrapper #calDayTitle { color:#94a3b8 !important; }

/* Calendar styles (unchanged) */
.cal-pill { padding:3px 10px; border-radius:9999px; font-size:11px; font-weight:700; border:1.5px solid #e2e8f0; color:#64748b; background:#fff; cursor:pointer; transition:all .15s; }
.cal-pill:hover  { border-color:#6366f1; color:#6366f1; }
.cal-pill.active { background:#6366f1; border-color:#6366f1; color:#fff; }
.cal-day { min-height:44px; border-radius:10px; padding:4px 3px 3px; cursor:pointer; position:relative; transition:background .12s; display:flex; flex-direction:column; align-items:center; }
.cal-day:hover   { background:#f1f5f9; }
.cal-day.today   { background:#eef2ff; }
.cal-day.today .cal-day-num { background:#6366f1; color:#fff; }
.cal-day.selected { background:#e0e7ff; }
.cal-day.other-month .cal-day-num { color:#cbd5e1; }
.cal-day-num { width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#475569; line-height:1; }
.cal-dots { display:flex; gap:2px; flex-wrap:wrap; justify-content:center; margin-top:2px; }
.cal-dot  { width:5px; height:5px; border-radius:50%; flex-shrink:0; }
.cal-badge { position:absolute; top:2px; right:3px; background:#6366f1; color:#fff; font-size:9px; font-weight:800; min-width:14px; height:14px; border-radius:7px; padding:0 3px; display:flex; align-items:center; justify-content:center; line-height:1; }

/* Stat card gradient icon shadows */
.dash-icon-indigo  { box-shadow: 0 8px 20px -4px rgba(99,102,241,.4); }
.dash-icon-blue    { box-shadow: 0 8px 20px -4px rgba(59,130,246,.4); }
.dash-icon-amber   { box-shadow: 0 8px 20px -4px rgba(245,158,11,.4); }
.dash-icon-emerald { box-shadow: 0 8px 20px -4px rgba(16,185,129,.4); }
.dash-icon-rose    { box-shadow: 0 8px 20px -4px rgba(244,63,94,.4); }

/* Leads period button */
.leads-period-btn { padding:4px 14px; border-radius:8px; font-size:12px; font-weight:700; border:1.5px solid #e2e8f0; color:#64748b; background:#fff; cursor:pointer; transition:all .15s; }
.leads-period-btn.active { background:#6366f1; border-color:#6366f1; color:#fff; }
[data-dark="1"] #dashWrapper .leads-period-btn { background:#1e2537; border-color:#334155; color:#94a3b8; }
[data-dark="1"] #dashWrapper .leads-period-btn.active { background:#6366f1; border-color:#6366f1; color:#fff; }
</style>

<!-- ═══════════════════════════════════════════════════════════════
     DASHBOARD WRAPPER
═══════════════════════════════════════════════════════════════ -->
<div id="dashWrapper" class="space-y-5 -mx-4 sm:-mx-8 -mt-4 sm:-mt-8 p-4 sm:p-8 min-h-full">

<!-- ── Subscription banners ─────────────────────────────────────── -->
<?php if ($subscription['expired']): ?>
<div class="mb-2 bg-rose-50 border border-rose-200 rounded-2xl p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4 dash-card">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
            <h3 class="font-extrabold text-rose-700">Your subscription has expired</h3>
            <p class="text-sm text-rose-600 mt-1">Expired on <?= date('d M Y', strtotime($subscription['expires_at'])) ?>. Adding, editing, and deleting records is disabled until you renew.</p>
            <?php if (!$_SESSION['is_staff']): ?>
            <p class="text-xs text-rose-500 mt-2 font-bold">Monthly: <?= $currencySymbol ?><?= number_format($subscriptionPlans['monthly']['price']??500,0) ?>/month · Yearly: <?= $currencySymbol ?><?= number_format($subscriptionPlans['yearly']['price']??3500,0) ?>/year</p>
            <?php else: ?>
            <p class="text-xs text-rose-500 mt-2 font-bold">Please ask your agency admin to renew.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$_SESSION['is_staff']): ?>
    <a href="/app/subscription_payment" class="shrink-0 bg-rose-600 text-white font-bold px-5 py-2.5 rounded-xl hover:bg-rose-700 transition text-center text-sm"><i class="fa-solid fa-arrow-rotate-right mr-2"></i>Renew Now</a>
    <?php endif; ?>
</div>
<?php elseif ($subscription['plan']==='Trial' && $subscription['days_left']!==null && $subscription['days_left']<=7): ?>
<div class="mb-2 bg-amber-50 border border-amber-200 rounded-2xl p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4 dash-card">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg shrink-0"><i class="fa-solid fa-clock"></i></div>
        <div>
            <h3 class="font-extrabold text-amber-700">Trial ends in <?= $subscription['days_left'] ?> day<?= $subscription['days_left']==1?'':'s' ?></h3>
            <p class="text-sm text-amber-600 mt-1">Expires on <?= date('d M Y', strtotime($subscription['expires_at'])) ?>. Renew to keep uninterrupted access.</p>
        </div>
    </div>
    <?php if (!$_SESSION['is_staff']): ?>
    <a href="/app/subscription_payment" class="shrink-0 bg-amber-500 text-white font-bold px-5 py-2.5 rounded-xl hover:bg-amber-600 transition text-center text-sm"><i class="fa-solid fa-arrow-rotate-right mr-2"></i>Renew Now</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Notification widget ───────────────────────────────────────── -->
<?php if (!empty($notifications)): ?>
<div id="dashNotifications" class="dash-card bg-white rounded-2xl border border-slate-100 overflow-hidden" style="box-shadow:0 2px 12px rgba(0,0,0,.06)">
    <div class="px-5 py-4 border-b dash-divider flex items-center gap-2">
        <i class="fa-solid fa-bell text-rose-500 animate-pulse"></i>
        <h3 class="font-extrabold dash-value text-slate-800">Upcoming Service Deadlines</h3>
        <span class="ml-auto text-xs font-bold bg-rose-100 text-rose-700 px-2.5 py-1 rounded-full"><?= count($notifications) ?></span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="dash-thead bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b dash-divider">
                <tr><th class="px-5 py-3 font-bold">Customer</th><th class="px-5 py-3 font-bold">Service</th><th class="px-5 py-3 font-bold">Deadline</th><th class="px-5 py-3 font-bold">Time Left</th><th class="px-5 py-3 font-bold text-right">Action</th></tr>
            </thead>
            <tbody>
                <?php foreach($notifications as $n):
                    $diff = strtotime($n['deadline_date']) - strtotime(date('Y-m-d'));
                    $days = round($diff/(60*60*24));
                    if ($days==0)       { $daysStr='Today';            $dClass='bg-rose-100 text-rose-700'; }
                    elseif ($days==1)   { $daysStr='Tomorrow';         $dClass='bg-amber-100 text-amber-700'; }
                    elseif ($days<0)    { $daysStr=abs($days).' days ago'; $dClass='bg-slate-100 text-slate-500'; }
                    else                { $daysStr="$days days";       $dClass='bg-indigo-100 text-indigo-700'; }
                ?>
                <tr class="dash-row-hover border-b dash-divider last:border-0">
                    <td class="px-5 py-3 font-bold dash-value text-slate-800"><?= xss_clean($n['customer_name']) ?></td>
                    <td class="px-5 py-3 dash-label text-slate-600"><?= $n['notification_type'] ?> <span class="text-xs text-slate-400 block"><?= $n['module_name'] ?> (#<?= $n['sale_id'] ?>)</span></td>
                    <td class="px-5 py-3 font-bold dash-value text-slate-800"><?= date('d M Y', strtotime($n['deadline_date'])) ?></td>
                    <td class="px-5 py-3"><span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $dClass ?>"><?= $daysStr ?></span></td>
                    <td class="px-5 py-3 text-right">
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="read_notification">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
                            <button type="submit" class="text-xs bg-white border border-slate-200 text-slate-600 px-3 py-1.5 rounded-lg hover:bg-slate-50 hover:text-indigo-600 font-bold transition">Mark Read</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── Stat Cards ────────────────────────────────────────────────── -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

    <!-- Total Leads -->
    <div class="dash-card bg-white rounded-2xl p-5 relative overflow-hidden border border-slate-100" style="box-shadow:0 2px 12px rgba(0,0,0,.06)">
        <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl bg-gradient-to-r from-violet-500 to-indigo-500"></div>
        <div class="flex items-start justify-between mt-1">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold dash-label text-slate-500 uppercase tracking-wider mb-2">Total Leads</p>
                <p class="text-3xl font-black dash-value text-slate-900"><?= number_format($totalLeads) ?></p>
            </div>
            <div class="w-13 h-13 rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center flex-shrink-0 ml-3 dash-icon-indigo" style="width:52px;height:52px">
                <i class="fa-solid fa-users-viewfinder text-white text-lg"></i>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-lg"><i class="fa-solid fa-circle-dot text-[8px]"></i>All time</span>
            <a href="/app/enquiries" class="text-xs font-bold text-slate-400 hover:text-indigo-600 transition ml-auto">View →</a>
        </div>
    </div>

    <!-- Total Sales -->
    <div class="dash-card bg-white rounded-2xl p-5 relative overflow-hidden border border-slate-100" style="box-shadow:0 2px 12px rgba(0,0,0,.06)">
        <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl bg-gradient-to-r from-sky-400 to-blue-600"></div>
        <div class="flex items-start justify-between mt-1">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold dash-label text-slate-500 uppercase tracking-wider mb-2">Total Sales</p>
                <p class="text-3xl font-black dash-value text-slate-900"><?= number_format($totalSales) ?></p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 flex items-center justify-center flex-shrink-0 ml-3 dash-icon-blue" style="width:52px;height:52px">
                <i class="fa-solid fa-suitcase-rolling text-white text-lg"></i>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-lg"><i class="fa-solid fa-circle-dot text-[8px]"></i>Completed</span>
        </div>
    </div>

    <!-- Turnover or Profit Generated (admin vs staff) -->
    <?php if ($_SESSION['is_staff']): ?>
    <div class="dash-card bg-white rounded-2xl p-5 relative overflow-hidden border border-slate-100" style="box-shadow:0 2px 12px rgba(0,0,0,.06)">
        <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl bg-gradient-to-r from-emerald-400 to-teal-500"></div>
        <div class="flex items-start justify-between mt-1">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold dash-label text-slate-500 uppercase tracking-wider mb-2">Profit Generated</p>
                <p class="text-2xl font-black dash-value text-slate-900 truncate"><?= $currencySymbol ?> <?= number_format($netProfit) ?></p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center flex-shrink-0 ml-3 dash-icon-emerald" style="width:52px;height:52px">
                <i class="fa-solid fa-chart-line text-white text-lg"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg"><i class="fa-solid fa-circle-dot text-[8px]"></i>All sales</span>
        </div>
    </div>
    <div class="dash-card bg-white rounded-2xl p-5 relative overflow-hidden border border-slate-100" style="box-shadow:0 2px 12px rgba(0,0,0,.06)">
        <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl bg-gradient-to-r from-amber-400 to-orange-500"></div>
        <div class="flex items-start justify-between mt-1">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold dash-label text-slate-500 uppercase tracking-wider mb-2">Commission Earned</p>
                <p class="text-2xl font-black text-emerald-500 truncate"><?= $currencySymbol ?> <?= number_format($commissionEarned) ?></p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center flex-shrink-0 ml-3 dash-icon-amber" style="width:52px;height:52px">
                <i class="fa-solid fa-hand-holding-dollar text-white text-lg"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-600 bg-amber-50 px-2 py-1 rounded-lg"><i class="fa-solid fa-circle-dot text-[8px]"></i><?= $_SESSION['commission_rate'] ?>% rate</span>
        </div>
    </div>
    <?php else: ?>
    <div class="dash-card bg-white rounded-2xl p-5 relative overflow-hidden border border-slate-100" style="box-shadow:0 2px 12px rgba(0,0,0,.06)">
        <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl bg-gradient-to-r from-amber-400 to-orange-500"></div>
        <div class="flex items-start justify-between mt-1">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold dash-label text-slate-500 uppercase tracking-wider mb-2">Total Turnover</p>
                <p class="text-2xl font-black dash-value text-slate-900 truncate"><?= $currencySymbol ?> <?= number_format($totalTurnover) ?></p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center flex-shrink-0 ml-3 dash-icon-amber" style="width:52px;height:52px">
                <i class="fa-solid fa-wallet text-white text-lg"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-600 bg-amber-50 px-2 py-1 rounded-lg"><i class="fa-solid fa-circle-dot text-[8px]"></i>Revenue</span>
        </div>
    </div>
    <div class="dash-card bg-white rounded-2xl p-5 relative overflow-hidden border border-slate-100" style="box-shadow:0 2px 12px rgba(0,0,0,.06)">
        <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl <?= $netProfit>=0 ? 'bg-gradient-to-r from-emerald-400 to-teal-500' : 'bg-gradient-to-r from-rose-400 to-red-500' ?>"></div>
        <div class="flex items-start justify-between mt-1">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold dash-label text-slate-500 uppercase tracking-wider mb-2">Net Profit</p>
                <p class="text-2xl font-black <?= $netProfit>=0?'text-emerald-500':'text-rose-500' ?> truncate"><?= $currencySymbol ?> <?= number_format($netProfit) ?></p>
            </div>
            <div class="rounded-2xl <?= $netProfit>=0 ? 'bg-gradient-to-br from-emerald-400 to-teal-500 dash-icon-emerald' : 'bg-gradient-to-br from-rose-400 to-red-500 dash-icon-rose' ?> flex items-center justify-center flex-shrink-0 ml-3" style="width:52px;height:52px">
                <i class="fa-solid fa-chart-line text-white text-lg"></i>
            </div>
        </div>
        <div class="mt-3">
            <span class="inline-flex items-center gap-1 text-xs font-bold <?= $netProfit>=0?'text-emerald-600 bg-emerald-50':'text-rose-600 bg-rose-50' ?> px-2 py-1 rounded-lg"><i class="fa-solid fa-circle-dot text-[8px]"></i>Income − Expenses</span>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /stat cards -->

<!-- ── Row 2: Reminder Calendar (left) + Recent Queries (right) ──── -->
<div class="grid grid-cols-1 xl:grid-cols-5 gap-5">

    <!-- Reminder Calendar -->
    <div class="xl:col-span-2 dash-card bg-white rounded-2xl border border-slate-100 flex flex-col overflow-hidden" style="box-shadow:0 2px 12px rgba(0,0,0,.06)">
        <!-- Calendar header -->
        <div class="px-4 pt-4 pb-3 border-b dash-divider">
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-extrabold dash-value text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-calendar-days text-indigo-500"></i> Reminders
                </h3>
                <div class="flex items-center gap-1">
                    <button id="calPrev" onclick="calNav(-1)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-indigo-100 hover:text-indigo-600 text-slate-600 flex items-center justify-center transition text-xs font-bold"><i class="fa-solid fa-chevron-left"></i></button>
                    <select id="calMonthSel" onchange="calJump()" class="border border-slate-200 rounded-lg px-1.5 py-1 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none bg-white">
                        <?php foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $mi=>$mn): ?>
                        <option value="<?= $mi ?>"><?= $mn ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="calYearSel" onchange="calJump()" class="border border-slate-200 rounded-lg px-1.5 py-1 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none bg-white">
                        <?php for($y=date('Y')-2;$y<=date('Y')+3;$y++): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <button id="calNext" onclick="calNav(1)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-indigo-100 hover:text-indigo-600 text-slate-600 flex items-center justify-center transition text-xs font-bold"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
            <div class="flex flex-wrap gap-1 mt-2.5" id="calFilters">
                <button onclick="calSetFilter('all')"      data-f="all"      class="cal-pill active">All</button>
                <button onclick="calSetFilter('followup')" data-f="followup" class="cal-pill">Follow-ups</button>
                <button onclick="calSetFilter('delivery')" data-f="delivery" class="cal-pill">Deliveries</button>
                <button onclick="calSetFilter('lead')"     data-f="lead"     class="cal-pill">Leads</button>
                <button onclick="calSetFilter('travel')"   data-f="travel"   class="cal-pill">Travel</button>
            </div>
        </div>
        <!-- Day headers -->
        <div class="grid grid-cols-7 text-center text-[10px] font-extrabold text-slate-400 uppercase tracking-wider px-2 pt-2.5 pb-1">
            <?php foreach(['Su','Mo','Tu','We','Th','Fr','Sa'] as $d): ?><div class="py-0.5"><?= $d ?></div><?php endforeach; ?>
        </div>
        <!-- Grid -->
        <div id="calGrid" class="grid grid-cols-7 gap-px px-2 pb-2 flex-1"></div>
        <!-- Day panel -->
        <div id="calDayPanel" class="hidden border-t dash-divider bg-slate-50/60 px-3 py-2.5 text-sm" style="max-height:150px;overflow-y:auto">
            <p id="calDayTitle" class="text-xs font-extrabold text-slate-500 uppercase tracking-wider mb-2"></p>
            <div id="calDayList" class="space-y-1"></div>
        </div>
    </div>

    <!-- Recent Queries -->
    <div class="xl:col-span-3 dash-card bg-white rounded-2xl border border-slate-100 overflow-hidden flex flex-col" style="box-shadow:0 2px 12px rgba(0,0,0,.06)">
        <div class="px-5 py-4 border-b dash-divider flex items-center justify-between">
            <h3 class="font-extrabold dash-value text-slate-800 flex items-center gap-2 text-sm">
                <i class="fa-solid fa-users-viewfinder text-violet-500"></i> Recent Queries
            </h3>
            <a href="/app/enquiries" class="text-xs font-bold text-indigo-600 hover:underline flex items-center gap-1">View All <i class="fa-solid fa-arrow-right text-[10px]"></i></a>
        </div>
        <div class="overflow-x-auto dash-scrollable flex-1" style="max-height:380px">
            <?php if (empty($recentQueries)): ?>
            <p class="p-8 text-center dash-label text-slate-400 text-sm">No active queries right now.</p>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead class="dash-thead bg-slate-50 border-b dash-divider sticky top-0">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold dash-label text-slate-500 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-bold dash-label text-slate-500 uppercase tracking-wider">Category</th>
                        <th class="px-4 py-3 text-center text-xs font-bold dash-label text-slate-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y dash-divider">
                <?php foreach ($recentQueries as $rq):
                    $rqActivity  = max(strtotime($rq['created_at']), strtotime($rq['last_followup'] ?: $rq['created_at']));
                    $staffName   = $rq['reference_staff_id'] ? ($staffMap[$rq['reference_staff_id']] ?? '—') : '—';
                    $initials    = strtoupper(substr($rq['display_name'] ?: 'U', 0, 2));
                    $statusColors= ['New'=>'bg-blue-100 text-blue-700','Contacted'=>'bg-purple-100 text-purple-700','Pending'=>'bg-amber-100 text-amber-700','Follow Up'=>'bg-orange-100 text-orange-700','Closed'=>'bg-slate-100 text-slate-600','Not Interested'=>'bg-rose-100 text-rose-700'];
                    $sBadge      = $statusColors[$rq['status'] ?? ''] ?? 'bg-slate-100 text-slate-600';
                ?>
                <tr class="dash-row-hover hover:bg-slate-50 transition cursor-pointer" onclick="location.href='/app/query_history?table=<?= $rq['module_name'] ?>&id=<?= urlencode($rq['id']) ?>'">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center text-white text-xs font-black flex-shrink-0"><?= $initials ?></div>
                            <div class="min-w-0">
                                <p class="font-bold dash-value text-slate-800 text-sm truncate max-w-[130px]"><?= xss_clean($rq['display_name'] ?: 'Unnamed') ?></p>
                                <p class="text-[11px] dash-sub text-slate-400 truncate"><i class="fa-solid fa-user-tie mr-1"></i><?= xss_clean($staffName) ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="<?= $moduleBadgeColors[$rq['module_name']] ?? 'bg-slate-100 text-slate-600' ?> px-2 py-1 rounded-lg text-xs font-bold whitespace-nowrap"><?= $moduleLabels[$rq['module_name']] ?></span>
                        <?php if ($rq['detail']): ?>
                        <p class="text-xs dash-label text-slate-400 mt-0.5 truncate max-w-[110px]"><?= xss_clean($rq['detail']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 rounded-lg text-[10px] font-bold whitespace-nowrap <?= $sBadge ?>"><?= xss_clean($rq['status'] ?: 'Pending') ?></span>
                        <p class="text-[10px] dash-sub text-slate-400 mt-1"><?= timeAgo($rqActivity) ?></p>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>


</div><!-- /row 2 -->

<!-- ── Row 3: Leads Generated Chart (left) + Recent Sales (right) ── -->
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

    <!-- Leads Generated Chart -->
    <div class="dash-card bg-white rounded-2xl border border-slate-100 p-5 flex flex-col" style="box-shadow:0 2px 12px rgba(0,0,0,.06)">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <h3 class="font-extrabold dash-value text-slate-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-chart-column text-indigo-500"></i> Leads Generated
            </h3>
            <div class="flex gap-1.5">
                <button onclick="setLeadsPeriod('weekly')"  id="lpWeekly"  class="leads-period-btn active">Weekly</button>
                <button onclick="setLeadsPeriod('monthly')" id="lpMonthly" class="leads-period-btn">Monthly</button>
                <button onclick="setLeadsPeriod('yearly')"  id="lpYearly"  class="leads-period-btn">Yearly</button>
            </div>
        </div>
        <div class="flex-1" style="min-height:260px">
            <canvas id="leadsChart"></canvas>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="dash-card bg-white rounded-2xl border border-slate-100 overflow-hidden flex flex-col" style="box-shadow:0 2px 12px rgba(0,0,0,.06)">
        <div class="px-5 py-4 border-b dash-divider flex items-center justify-between">
            <h3 class="font-extrabold dash-value text-slate-800 flex items-center gap-2 text-sm">
                <i class="fa-solid fa-money-check-dollar text-emerald-500"></i> Recent Sales
            </h3>
            <a href="/app/passports" class="text-xs font-bold text-indigo-600 hover:underline flex items-center gap-1">View All <i class="fa-solid fa-arrow-right text-[10px]"></i></a>
        </div>
        <div class="overflow-x-auto dash-scrollable flex-1">
            <?php if (empty($recentSales)): ?>
            <p class="p-8 text-center dash-label text-slate-400 text-sm">No completed sales yet.</p>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead class="dash-thead bg-slate-50 border-b dash-divider">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold dash-label text-slate-500 uppercase tracking-wider">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-bold dash-label text-slate-500 uppercase tracking-wider hidden sm:table-cell">Service</th>
                        <th class="px-4 py-3 text-right text-xs font-bold dash-label text-slate-500 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-center text-xs font-bold dash-label text-slate-500 uppercase tracking-wider hidden md:table-cell">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-bold dash-label text-slate-500 uppercase tracking-wider hidden lg:table-cell">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y dash-divider">
                <?php foreach ($recentSales as $rs):
                    $statusSaleColors = ['Completed'=>'bg-emerald-100 text-emerald-700','Paid'=>'bg-teal-100 text-teal-700','Confirmed'=>'bg-blue-100 text-blue-700'];
                    $sColor = $statusSaleColors[$rs['status']] ?? 'bg-slate-100 text-slate-600';
                    $initials2 = strtoupper(substr($rs['display_name'] ?: 'U', 0, 2));
                ?>
                <tr class="dash-row-hover hover:bg-slate-50 transition">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white text-xs font-black flex-shrink-0"><?= $initials2 ?></div>
                            <div class="min-w-0">
                                <p class="font-bold dash-value text-slate-800 truncate text-sm"><?= xss_clean($rs['display_name'] ?: 'Unnamed') ?></p>
                                <p class="text-xs dash-label text-slate-400 sm:hidden truncate"><?= $moduleLabels[$rs['module_name']] ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 hidden sm:table-cell">
                        <span class="<?= $moduleBadgeColors[$rs['module_name']] ?? 'bg-slate-100 text-slate-600' ?> px-2 py-1 rounded-lg text-xs font-bold"><?= $moduleLabels[$rs['module_name']] ?></span>
                        <?php if ($rs['detail']): ?><p class="text-xs dash-label text-slate-400 mt-0.5 truncate max-w-[140px]"><?= xss_clean($rs['detail']) ?></p><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php if ($rs['selling_price']): ?>
                        <p class="font-black dash-value text-slate-800 text-sm"><?= $currencySymbol ?> <?= number_format($rs['selling_price']) ?></p>
                        <?php else: ?>
                        <p class="dash-label text-slate-400 text-xs">—</p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center hidden md:table-cell">
                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $sColor ?>"><?= xss_clean($rs['status']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-right hidden lg:table-cell">
                        <p class="text-xs dash-label text-slate-500"><?= date('d M Y', strtotime($rs['created_at'])) ?></p>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>


</div><!-- /row 3 -->

<!-- Shared calendar tooltip -->
<div id="calTooltip" class="fixed z-50 hidden pointer-events-none bg-slate-900 text-white text-xs rounded-xl shadow-2xl px-3 py-2.5 max-w-xs leading-relaxed" style="transform:translate(-50%,calc(-100% - 10px))"></div>

</div><!-- /#dashWrapper -->

<!-- ═══════════════════════════════════════════════════════════════
     DASHBOARD SCRIPTS
═══════════════════════════════════════════════════════════════ -->
<script>
/* ---- Leads Generated Chart ---- */
(function() {
    const datasets = {
        weekly:  { labels: <?= json_encode($leadsWeekLabels) ?>,  data: <?= json_encode($leadsWeekData) ?> },
        monthly: { labels: <?= json_encode($leadsMonthLabels) ?>, data: <?= json_encode($leadsMonthData) ?> },
        yearly:  { labels: <?= json_encode($leadsYearLabels) ?>,  data: <?= json_encode($leadsYearData) ?> },
    };

    const isDark = () => document.documentElement.getAttribute('data-dark') === '1';

    const gradient = (ctx) => {
        const g = ctx.createLinearGradient(0, 0, 0, 260);
        g.addColorStop(0, 'rgba(99,102,241,0.55)');
        g.addColorStop(1, 'rgba(139,92,246,0.05)');
        return g;
    };

    const leadsChart = new Chart(document.getElementById('leadsChart'), {
        type: 'bar',
        data: {
            labels: datasets.weekly.labels,
            datasets: [
                {
                    label: 'Leads Generated',
                    data: datasets.weekly.data,
                    backgroundColor: function(ctx) { return gradient(ctx.chart.ctx); },
                    borderColor: '#6366f1',
                    borderWidth: 0,
                    borderRadius: 6,
                    borderSkipped: false,
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Trend',
                    data: datasets.weekly.data,
                    borderColor: '#f59e0b',
                    backgroundColor: 'transparent',
                    borderWidth: 2.5,
                    tension: 0.4,
                    pointBackgroundColor: '#f59e0b',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    order: 1,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 16,
                        color: () => isDark() ? '#94a3b8' : '#64748b', font: { size: 11, weight: '700' } }
                },
                tooltip: {
                    backgroundColor: isDark() ? '#1e2537' : '#fff',
                    titleColor: isDark() ? '#f1f5f9' : '#1e293b',
                    bodyColor: isDark() ? '#94a3b8' : '#64748b',
                    borderColor: isDark() ? '#334155' : '#e2e8f0',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 12,
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: () => isDark() ? '#64748b' : '#94a3b8', font: { size: 11 } } },
                y: { grid: { color: () => isDark() ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)' }, ticks: { color: () => isDark() ? '#64748b' : '#94a3b8', font: { size: 11 }, precision: 0 }, beginAtZero: true }
            }
        }
    });

    window.setLeadsPeriod = function(p) {
        ['weekly','monthly','yearly'].forEach(k => {
            document.getElementById('lp'+k.charAt(0).toUpperCase()+k.slice(1)).classList.toggle('active', k===p);
        });
        const d = datasets[p];
        leadsChart.data.labels = d.labels;
        leadsChart.data.datasets[0].data = d.data;
        leadsChart.data.datasets[1].data = d.data;
        leadsChart.update();
    };
})();

/* ---- Calendar ---- */
(function() {
    const EVENTS     = <?= $calendarJson ?>;
    const TYPE_COLOR = { followup_lead:'#6366f1', followup_sale:'#f59e0b', delivery:'#f43f5e', travel:'#10b981' };
    const TYPE_LABEL = { followup_lead:'Lead Follow-up', followup_sale:'Follow-up', delivery:'Delivery', travel:'Travel' };

    let curYear = <?= date('Y') ?>, curMonth = <?= date('n')-1 ?>;
    let activeFilter = 'all', selectedDate = null;
    const tooltip = document.getElementById('calTooltip');

    function typeMatchesFilter(type) {
        if (activeFilter==='all')      return true;
        if (activeFilter==='followup') return type==='followup_lead'||type==='followup_sale';
        if (activeFilter==='delivery') return type==='delivery';
        if (activeFilter==='lead')     return type==='followup_lead';
        if (activeFilter==='travel')   return type==='travel';
        return true;
    }
    function buildIndex() {
        const idx={};
        EVENTS.forEach(e => {
            if (!typeMatchesFilter(e.type)) return;
            if (!idx[e.date]) idx[e.date]=[];
            idx[e.date].push(e);
        });
        return idx;
    }
    function renderCalendar() {
        const idx=buildIndex();
        const grid=document.getElementById('calGrid');
        const mSel=document.getElementById('calMonthSel');
        const ySel=document.getElementById('calYearSel');
        mSel.value=curMonth; ySel.value=curYear;
        const today=new Date(); today.setHours(0,0,0,0);
        const firstDay=new Date(curYear,curMonth,1);
        const lastDay=new Date(curYear,curMonth+1,0);
        const startDow=firstDay.getDay();
        const totalDays=lastDay.getDate();
        let html='';
        const prevLast=new Date(curYear,curMonth,0).getDate();
        for(let i=startDow-1;i>=0;i--){
            const d=prevLast-i,m=curMonth===0?11:curMonth-1,y=curMonth===0?curYear-1:curYear;
            const ds=`${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            html+=dayCell(d,ds,idx[ds]||[],true,false,false);
        }
        for(let d=1;d<=totalDays;d++){
            const ds=`${curYear}-${String(curMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            const dt=new Date(curYear,curMonth,d);
            html+=dayCell(d,ds,idx[ds]||[],false,dt.getTime()===today.getTime(),ds===selectedDate);
        }
        const trailCount=42-startDow-totalDays;
        for(let d=1;d<=trailCount;d++){
            const m=curMonth===11?0:curMonth+1,y=curMonth===11?curYear+1:curYear;
            const ds=`${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            html+=dayCell(d,ds,idx[ds]||[],true,false,false);
        }
        grid.innerHTML=html;
        grid.querySelectorAll('.cal-day').forEach(cell => {
            const ds=cell.dataset.date, events=idx[ds]||[];
            cell.addEventListener('click',()=>onDayClick(ds,events));
            if(events.length>0){
                cell.addEventListener('mouseenter',e=>showTooltip(e,events));
                cell.addEventListener('mousemove',e=>moveTooltip(e));
                cell.addEventListener('mouseleave',hideTooltip);
            }
        });
        if(selectedDate) renderDayPanel(selectedDate,idx[selectedDate]||[]);
    }
    function dayCell(dayNum,ds,events,otherMonth,isToday,isSel){
        const classes=['cal-day',otherMonth?'other-month':'',isToday?'today':'',isSel?'selected':''].filter(Boolean).join(' ');
        const seenTypes=[];
        events.forEach(e=>{if(!seenTypes.includes(e.type))seenTypes.push(e.type);});
        const dots=seenTypes.slice(0,3).map(t=>`<span class="cal-dot" style="background:${TYPE_COLOR[t]}"></span>`).join('');
        const badge=events.length>3?`<span class="cal-badge">${events.length}</span>`:(events.length>0?`<span class="cal-badge" style="background:${TYPE_COLOR[events[0].type]}">${events.length}</span>`:'');
        return `<div class="${classes}" data-date="${ds}">${badge}<span class="cal-day-num">${dayNum}</span>${dots?`<div class="cal-dots">${dots}</div>`:''}</div>`;
    }
    function onDayClick(ds,events){
        selectedDate=(selectedDate===ds)?null:ds;
        renderCalendar();
    }
    function renderDayPanel(ds,events){
        const panel=document.getElementById('calDayPanel'),title=document.getElementById('calDayTitle'),list=document.getElementById('calDayList');
        if(!selectedDate||events.length===0){panel.classList.add('hidden');return;}
        const [y,m,d]=ds.split('-');
        title.textContent=new Date(+y,+m-1,+d).toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'});
        list.innerHTML=events.map(e=>{
            const col=TYPE_COLOR[e.type],lbl=TYPE_LABEL[e.type];
            return `<a href="${e.url}" class="flex items-start gap-2 px-2.5 py-2 rounded-xl hover:bg-white border border-transparent hover:border-slate-200 transition group">
                <span class="w-2 h-2 rounded-full mt-1 flex-shrink-0" style="background:${col}"></span>
                <div class="flex-1 min-w-0">
                    <span class="text-xs font-extrabold text-slate-700 group-hover:text-indigo-600 leading-tight block truncate">${escHtml(e.title)}</span>
                    ${e.note?`<span class="text-[10px] text-slate-400 block truncate">${escHtml(e.note)}</span>`:''}
                </div>
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 mt-0.5" style="background:${col}22;color:${col}">${lbl}</span>
            </a>`;
        }).join('');
        panel.classList.remove('hidden');
    }
    function showTooltip(e,events){
        const lines=events.slice(0,5).map(ev=>`<span style="display:flex;align-items:center;gap:5px"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:${TYPE_COLOR[ev.type]};flex-shrink:0"></span>${escHtml(ev.title)}</span>`);
        if(events.length>5) lines.push(`<span style="color:#94a3b8">+ ${events.length-5} more</span>`);
        tooltip.innerHTML=lines.join('');
        tooltip.classList.remove('hidden');
        moveTooltip(e);
    }
    function moveTooltip(e){tooltip.style.left=e.clientX+'px';tooltip.style.top=(e.clientY+window.scrollY)+'px';}
    function hideTooltip(){tooltip.classList.add('hidden');}
    window.calNav=function(dir){curMonth+=dir;if(curMonth>11){curMonth=0;curYear++;}if(curMonth<0){curMonth=11;curYear--;}selectedDate=null;renderCalendar();};
    window.calJump=function(){curMonth=+document.getElementById('calMonthSel').value;curYear=+document.getElementById('calYearSel').value;selectedDate=null;renderCalendar();};
    window.calSetFilter=function(f){activeFilter=f;document.querySelectorAll('.cal-pill').forEach(b=>b.classList.toggle('active',b.dataset.f===f));renderCalendar();};
    function escHtml(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
    renderCalendar();
})();
</script>
