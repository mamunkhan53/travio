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

                        <div class="bg-white p-6 rounded-2xl soft-shadow border border-slate-100">
                            <h3 class="font-extrabold text-slate-800 mb-6 text-lg"><i class="fa-solid fa-chart-area text-indigo-500 mr-2"></i> Monthly Performance Trend</h3>
                            <canvas id="mainChart" height="100"></canvas>
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
