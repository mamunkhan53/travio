<?php
// ── Hajj & Umrah — Reports ───────────────────────────────────────────────────
require_once __DIR__ . '/../../includes/umrah_actions.php';
$agency_id = $_SESSION['agency_id'];
$tab       = trim($_GET['rtab'] ?? 'bookings');
$f_from    = trim($_GET['from'] ?? date('Y-m-01'));
$f_to      = trim($_GET['to']   ?? date('Y-m-d'));
$f_status  = trim($_GET['status'] ?? '');

$statusColors = [
    'Inquiry'   => 'bg-amber-100 text-amber-700',
    'Confirmed' => 'bg-blue-100 text-blue-700',
    'Completed' => 'bg-emerald-100 text-emerald-700',
    'Cancelled' => 'bg-rose-100 text-rose-700',
];

// ── Tab 1: Booking Report ──────────────────────────────────────────────────
if ($tab === 'bookings') {
    $where  = "b.agency_id = ?";
    $params = [$agency_id];
    if ($f_from)   { $where .= " AND b.booking_date >= ?";      $params[] = $f_from; }
    if ($f_to)     { $where .= " AND b.booking_date <= ?";      $params[] = $f_to; }
    if ($f_status) { $where .= " AND b.booking_status = ?";    $params[] = $f_status; }

    $rows = $conn->prepare("
        SELECT b.*, p.package_name, p.package_type,
               COALESCE(SUM(py.amount),0) AS paid,
               s.full_name AS staff_name
        FROM umrah_bookings b
        LEFT JOIN umrah_packages p  ON b.package_id = p.id
        LEFT JOIN umrah_payments py ON py.booking_id = b.id AND py.agency_id = b.agency_id
        LEFT JOIN staff s           ON b.reference_staff_id = s.id
        WHERE $where
        GROUP BY b.id
        ORDER BY b.created_at DESC
    ");
    $rows->execute($params);
    $rows = $rows->fetchAll(PDO::FETCH_ASSOC);
    $totBookings = count($rows);
    $totRevenue  = array_sum(array_column($rows, 'total_price'));
    $totPaid     = array_sum(array_column($rows, 'paid'));
}

// ── Tab 2: Payment & Due Report ────────────────────────────────────────────
if ($tab === 'payments') {
    $rows = $conn->prepare("
        SELECT b.id, b.customer_name, b.total_price, b.booking_status,
               p.package_name, b.travel_date,
               COALESCE(SUM(py.amount),0) AS paid
        FROM umrah_bookings b
        LEFT JOIN umrah_packages p  ON b.package_id = p.id
        LEFT JOIN umrah_payments py ON py.booking_id = b.id AND py.agency_id = b.agency_id
        WHERE b.agency_id = ? AND b.booking_status != 'Cancelled'
        GROUP BY b.id
        HAVING (b.total_price - COALESCE(SUM(py.amount),0)) > 0
        ORDER BY (b.total_price - COALESCE(SUM(py.amount),0)) DESC
    ");
    $rows->execute([$agency_id]);
    $rows = $rows->fetchAll(PDO::FETCH_ASSOC);
    $totDue = array_sum(array_map(fn($r) => $r['total_price'] - $r['paid'], $rows));
}

// ── Tab 3: Package-wise Report ─────────────────────────────────────────────
if ($tab === 'packages') {
    $rows = $conn->prepare("
        SELECT p.package_type, p.package_name, p.price AS pkg_price,
               COUNT(b.id)                           AS total_bookings,
               SUM(b.num_pilgrims)                   AS total_pilgrims,
               SUM(b.total_price)                    AS total_revenue,
               SUM(COALESCE(py.paid,0))              AS total_paid,
               SUM(b.booking_status = 'Completed')   AS completed,
               SUM(b.booking_status = 'Confirmed')   AS confirmed,
               SUM(b.booking_status = 'Cancelled')   AS cancelled
        FROM umrah_packages p
        LEFT JOIN umrah_bookings b  ON b.package_id = p.id AND b.agency_id = p.agency_id
        LEFT JOIN (SELECT booking_id, SUM(amount) AS paid FROM umrah_payments WHERE agency_id = ? GROUP BY booking_id) py
                  ON py.booking_id = b.id
        WHERE p.agency_id = ?
        GROUP BY p.id
        ORDER BY total_bookings DESC, p.package_type, p.package_name
    ");
    $rows->execute([$agency_id, $agency_id]);
    $rows = $rows->fetchAll(PDO::FETCH_ASSOC);
}
?>
<div class="space-y-6">
    <!-- Tab Bar -->
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="p-5 border-b bg-slate-50/50 flex items-center gap-2">
            <i class="fa-solid fa-chart-bar text-indigo-500"></i>
            <h2 class="font-extrabold text-slate-800 text-lg">Reports</h2>
        </div>
        <div class="flex gap-1 p-3 border-b bg-white">
            <?php
            $tabs = ['bookings'=>['Booking Report','fa-calendar-check'], 'payments'=>['Payment & Due','fa-coins'], 'packages'=>['Package-wise','fa-box-open']];
            foreach ($tabs as $t => [$tlbl, $ticon]):
            ?>
            <a href="?route=app&page=umrah_reports&rtab=<?= $t ?>&from=<?= urlencode($f_from) ?>&to=<?= urlencode($f_to) ?>"
               class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all
                      <?= $tab===$t ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                <i class="fa-solid <?= $ticon ?>"></i> <?= $tlbl ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Filters (Booking tab only) -->
        <?php if ($tab === 'bookings'): ?>
        <form method="GET" class="flex flex-wrap gap-3 p-4 border-b bg-slate-50/20">
            <input type="hidden" name="route" value="app">
            <input type="hidden" name="page"  value="umrah_reports">
            <input type="hidden" name="rtab"  value="bookings">
            <input type="date" name="from" value="<?= htmlspecialchars($f_from) ?>" class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
            <input type="date" name="to"   value="<?= htmlspecialchars($f_to) ?>"   class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
            <select name="status" class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
                <option value="">All Statuses</option>
                <?php foreach (['Inquiry','Confirmed','Completed','Cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $f_status===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition">Apply</button>
        </form>
        <?php endif; ?>

        <!-- ── Booking Report ─────────────────────────────────────────────── -->
        <?php if ($tab === 'bookings'): ?>
        <div class="grid grid-cols-3 gap-4 p-5 border-b">
            <div class="bg-indigo-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-extrabold text-indigo-600"><?= $totBookings ?></div>
                <div class="text-xs text-slate-500 font-bold mt-0.5">Total Bookings</div>
            </div>
            <div class="bg-emerald-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-extrabold text-emerald-600"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($totPaid, 0) ?></div>
                <div class="text-xs text-slate-500 font-bold mt-0.5">Total Paid</div>
            </div>
            <div class="bg-rose-50 rounded-xl p-4 text-center">
                <div class="text-2xl font-extrabold text-rose-600"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($totRevenue - $totPaid, 0) ?></div>
                <div class="text-xs text-slate-500 font-bold mt-0.5">Total Due</div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                    <tr>
                        <th class="px-5 py-3 font-bold text-left">Booking ID</th>
                        <th class="px-5 py-3 font-bold text-left">Booking Date</th>
                        <th class="px-5 py-3 font-bold text-left">Customer</th>
                        <th class="px-5 py-3 font-bold text-left">Package</th>
                        <th class="px-5 py-3 font-bold text-left">Travel Date</th>
                        <th class="px-5 py-3 font-bold text-center">Pilgrims</th>
                        <th class="px-5 py-3 font-bold text-right">Total</th>
                        <th class="px-5 py-3 font-bold text-right">Paid</th>
                        <th class="px-5 py-3 font-bold text-right">Due</th>
                        <th class="px-5 py-3 font-bold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($rows): foreach ($rows as $r): $due = $r['total_price'] - $r['paid']; ?>
                    <tr class="hover:bg-slate-50 text-slate-700">
                        <td class="px-5 py-3 font-bold text-indigo-600"><?= htmlspecialchars($r['id']) ?></td>
                        <td class="px-5 py-3 whitespace-nowrap"><?= $r['booking_date'] ? date('d M Y', strtotime($r['booking_date'])) : '—' ?></td>
                        <td class="px-5 py-3 font-medium"><?= htmlspecialchars($r['customer_name'] ?: '—') ?></td>
                        <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($r['package_name'] ?: '—') ?></td>
                        <td class="px-5 py-3"><?= $r['travel_date'] ? date('d M Y', strtotime($r['travel_date'])) : '—' ?></td>
                        <td class="px-5 py-3 text-center font-bold"><?= (int)$r['num_pilgrims'] ?></td>
                        <td class="px-5 py-3 font-bold text-right"><?= number_format($r['total_price'], 2) ?></td>
                        <td class="px-5 py-3 font-bold text-right text-emerald-600"><?= number_format($r['paid'], 2) ?></td>
                        <td class="px-5 py-3 font-bold text-right <?= $due > 0 ? 'text-rose-600' : 'text-slate-400' ?>"><?= number_format($due, 2) ?></td>
                        <td class="px-5 py-3"><span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $statusColors[$r['booking_status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= htmlspecialchars($r['booking_status']) ?></span></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="10" class="px-5 py-12 text-center text-slate-400">No bookings in this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Payment & Due Report ───────────────────────────────────────── -->
        <?php elseif ($tab === 'payments'): ?>
        <div class="p-5 border-b flex items-center gap-4">
            <div class="bg-rose-50 rounded-xl px-5 py-3">
                <span class="text-xs text-slate-500 font-bold block">Total Outstanding Due</span>
                <span class="text-xl font-extrabold text-rose-600"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($totDue, 2) ?></span>
            </div>
            <p class="text-xs text-slate-400">Showing all active bookings with outstanding balance.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                    <tr>
                        <th class="px-5 py-3 font-bold text-left">Booking ID</th>
                        <th class="px-5 py-3 font-bold text-left">Booking Date</th>
                        <th class="px-5 py-3 font-bold text-left">Customer</th>
                        <th class="px-5 py-3 font-bold text-left">Package</th>
                        <th class="px-5 py-3 font-bold text-left">Travel Date</th>
                        <th class="px-5 py-3 font-bold text-right">Total Price</th>
                        <th class="px-5 py-3 font-bold text-right">Paid</th>
                        <th class="px-5 py-3 font-bold text-right">Due</th>
                        <th class="px-5 py-3 font-bold">Status</th>
                        <th class="px-5 py-3 font-bold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($rows): foreach ($rows as $r): $due = $r['total_price'] - $r['paid']; ?>
                    <tr class="hover:bg-slate-50 text-slate-700">
                        <td class="px-5 py-3 font-bold text-indigo-600"><?= htmlspecialchars($r['id']) ?></td>
                        <td class="px-5 py-3 whitespace-nowrap"><?= $r['booking_date'] ? date('d M Y', strtotime($r['booking_date'])) : '—' ?></td>
                        <td class="px-5 py-3 font-medium"><?= htmlspecialchars($r['customer_name'] ?: '—') ?></td>
                        <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($r['package_name'] ?: '—') ?></td>
                        <td class="px-5 py-3"><?= $r['travel_date'] ? date('d M Y', strtotime($r['travel_date'])) : '—' ?></td>
                        <td class="px-5 py-3 font-bold text-right"><?= number_format($r['total_price'], 2) ?></td>
                        <td class="px-5 py-3 font-bold text-right text-emerald-600"><?= number_format($r['paid'], 2) ?></td>
                        <td class="px-5 py-3 font-bold text-right text-rose-600"><?= number_format($due, 2) ?></td>
                        <td class="px-5 py-3"><span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $statusColors[$r['booking_status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= htmlspecialchars($r['booking_status']) ?></span></td>
                        <td class="px-5 py-3">
                            <a href="?route=app&page=umrah_payments&booking_id=<?= urlencode($r['id']) ?>" class="text-indigo-500 hover:underline text-xs font-bold">Pay →</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="10" class="px-5 py-12 text-center text-slate-400 font-semibold">🎉 No outstanding dues — all bookings are fully paid!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── Package-wise Report ────────────────────────────────────────── -->
        <?php elseif ($tab === 'packages'): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                    <tr>
                        <th class="px-5 py-3 font-bold text-left">Package</th>
                        <th class="px-5 py-3 font-bold text-left">Type</th>
                        <th class="px-5 py-3 font-bold text-right">Unit Price</th>
                        <th class="px-5 py-3 font-bold text-center">Bookings</th>
                        <th class="px-5 py-3 font-bold text-center">Pilgrims</th>
                        <th class="px-5 py-3 font-bold text-center">Confirmed</th>
                        <th class="px-5 py-3 font-bold text-center">Completed</th>
                        <th class="px-5 py-3 font-bold text-center">Cancelled</th>
                        <th class="px-5 py-3 font-bold text-right">Revenue</th>
                        <th class="px-5 py-3 font-bold text-right">Collected</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($rows): foreach ($rows as $r): ?>
                    <tr class="hover:bg-slate-50 text-slate-700">
                        <td class="px-5 py-3 font-semibold"><?= htmlspecialchars($r['package_name']) ?></td>
                        <td class="px-5 py-3">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $r['package_type']==='Hajj' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' ?>"><?= htmlspecialchars($r['package_type']) ?></span>
                        </td>
                        <td class="px-5 py-3 font-bold text-right"><?= number_format($r['pkg_price'], 2) ?></td>
                        <td class="px-5 py-3 font-bold text-center text-indigo-600"><?= (int)$r['total_bookings'] ?></td>
                        <td class="px-5 py-3 text-center"><?= (int)$r['total_pilgrims'] ?></td>
                        <td class="px-5 py-3 text-center font-bold text-blue-600"><?= (int)$r['confirmed'] ?></td>
                        <td class="px-5 py-3 text-center font-bold text-emerald-600"><?= (int)$r['completed'] ?></td>
                        <td class="px-5 py-3 text-center font-bold text-rose-500"><?= (int)$r['cancelled'] ?></td>
                        <td class="px-5 py-3 font-bold text-right"><?= number_format($r['total_revenue'] ?? 0, 2) ?></td>
                        <td class="px-5 py-3 font-bold text-right text-emerald-600"><?= number_format($r['total_paid'] ?? 0, 2) ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="10" class="px-5 py-12 text-center text-slate-400">No packages found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
