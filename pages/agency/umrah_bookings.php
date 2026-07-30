<?php
// ── Hajj & Umrah — Bookings ──────────────────────────────────────────────────
require_once __DIR__ . '/../../includes/umrah_actions.php';
$agency_id = $_SESSION['agency_id'];

// Filters
$f_status = trim($_GET['status'] ?? '');
$f_from   = trim($_GET['from']   ?? '');
$f_to     = trim($_GET['to']     ?? '');
$f_pkg    = trim($_GET['pkg']    ?? '');

// Build query
$where = "b.agency_id = ?";
$params = [$agency_id];
if ($f_status) { $where .= " AND b.booking_status = ?"; $params[] = $f_status; }
if ($f_pkg)    { $where .= " AND b.package_id = ?";     $params[] = $f_pkg; }
if ($f_from)   { $where .= " AND b.travel_date >= ?";   $params[] = $f_from; }
if ($f_to)     { $where .= " AND b.travel_date <= ?";   $params[] = $f_to; }

$bookings = $conn->prepare("
    SELECT b.*, p.package_name, p.package_type,
           COALESCE(SUM(py.amount),0) AS paid_amount,
           s.full_name AS staff_name
    FROM umrah_bookings b
    LEFT JOIN umrah_packages  p  ON b.package_id = p.id
    LEFT JOIN umrah_payments  py ON py.booking_id = b.id AND py.agency_id = b.agency_id
    LEFT JOIN staff           s  ON b.reference_staff_id = s.id
    WHERE $where
    GROUP BY b.id
    ORDER BY b.created_at DESC
");
$bookings->execute($params);
$bookings = $bookings->fetchAll(PDO::FETCH_ASSOC);

// For modal: active packages + customers
$activePackages = $conn->prepare("SELECT id, package_type, package_name, price FROM umrah_packages WHERE agency_id=? AND status='Active' ORDER BY package_type, package_name");
$activePackages->execute([$agency_id]);
$activePackages = $activePackages->fetchAll(PDO::FETCH_ASSOC);

$customers = $conn->prepare("SELECT id, name, mobile FROM customers WHERE agency_id=? ORDER BY name");
$customers->execute([$agency_id]);
$customers = $customers->fetchAll(PDO::FETCH_ASSOC);

$statusColors = [
    'Inquiry'   => 'bg-amber-100 text-amber-700',
    'Confirmed' => 'bg-blue-100 text-blue-700',
    'Completed' => 'bg-emerald-100 text-emerald-700',
    'Cancelled' => 'bg-rose-100 text-rose-700',
];
?>
<div class="space-y-6">
    <!-- Header + filters -->
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="p-5 border-b flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50">
            <div>
                <h2 class="font-extrabold text-slate-800 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-calendar-check text-indigo-500"></i> Bookings
                </h2>
                <p class="text-xs text-slate-400 mt-0.5"><?= count($bookings) ?> booking(s) found</p>
            </div>
            <button onclick="openBkModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center gap-2 transition">
                <i class="fa-solid fa-plus"></i> New Booking
            </button>
        </div>

        <!-- Filters -->
        <form method="GET" class="flex flex-wrap gap-3 p-4 border-b bg-slate-50/30">
            <input type="hidden" name="route" value="app">
            <input type="hidden" name="page" value="umrah_bookings">
            <select name="status" class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
                <option value="">All Statuses</option>
                <?php foreach (['Inquiry','Confirmed','Completed','Cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $f_status===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <select name="pkg" class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
                <option value="">All Packages</option>
                <?php foreach ($activePackages as $ap): ?>
                <option value="<?= htmlspecialchars($ap['id']) ?>" <?= $f_pkg===$ap['id']?'selected':'' ?>><?= htmlspecialchars($ap['package_type'].' - '.$ap['package_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from" value="<?= htmlspecialchars($f_from) ?>" class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none" title="Travel Date From">
            <input type="date" name="to"   value="<?= htmlspecialchars($f_to) ?>"   class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none" title="Travel Date To">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition">Filter</button>
            <?php if ($f_status||$f_pkg||$f_from||$f_to): ?>
            <a href="?route=app&page=umrah_bookings" class="px-4 py-2 border border-slate-200 text-slate-500 rounded-xl text-sm hover:bg-slate-50 transition">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                    <tr>
                        <th class="px-6 py-4 font-bold">ID</th>
                        <th class="px-6 py-4 font-bold">Customer</th>
                        <th class="px-6 py-4 font-bold">Package</th>
                        <th class="px-6 py-4 font-bold">Travel Date</th>
                        <th class="px-6 py-4 font-bold">Pilgrims</th>
                        <th class="px-6 py-4 font-bold">Total</th>
                        <th class="px-6 py-4 font-bold">Paid</th>
                        <th class="px-6 py-4 font-bold">Due</th>
                        <th class="px-6 py-4 font-bold">Status</th>
                        <th class="px-6 py-4 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($bookings): foreach ($bookings as $b):
                        $due = $b['total_price'] - $b['paid_amount'];
                    ?>
                    <tr class="hover:bg-slate-50 transition text-slate-700">
                        <td class="px-6 py-4 font-extrabold text-indigo-600"><?= htmlspecialchars($b['id']) ?></td>
                        <td class="px-6 py-4">
                            <div class="font-semibold"><?= htmlspecialchars($b['customer_name'] ?: '—') ?></div>
                            <?php if ($b['customer_id']): ?>
                            <a href="?route=app&page=customer_profile&id=<?= htmlspecialchars($b['customer_id']) ?>" class="text-xs text-indigo-500 hover:underline">View Profile</a>
                            <?php elseif ($b['customer_mobile']): ?>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($b['customer_mobile']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($b['package_name']): ?>
                            <div class="font-medium"><?= htmlspecialchars($b['package_name']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($b['package_type'] ?? '') ?></div>
                            <?php else: ?>
                            <span class="text-slate-400 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4"><?= $b['travel_date'] ? date('d M Y', strtotime($b['travel_date'])) : '—' ?></td>
                        <td class="px-6 py-4 text-center font-bold"><?= (int)$b['num_pilgrims'] ?></td>
                        <td class="px-6 py-4 font-bold"><?= number_format($b['total_price'], 2) ?></td>
                        <td class="px-6 py-4 font-bold text-emerald-600"><?= number_format($b['paid_amount'], 2) ?></td>
                        <td class="px-6 py-4 font-bold <?= $due > 0 ? 'text-rose-600' : 'text-slate-400' ?>"><?= number_format($due, 2) ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $statusColors[$b['booking_status']] ?? 'bg-slate-100 text-slate-600' ?>">
                                <?= htmlspecialchars($b['booking_status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <a href="?route=app&page=umrah_payments&booking_id=<?= urlencode($b['id']) ?>" class="text-emerald-600 bg-emerald-50 w-8 h-8 inline-flex items-center justify-center rounded-lg hover:bg-emerald-100 transition" title="Payments"><i class="fa-solid fa-coins text-xs"></i></a>
                            <a href="?route=app&page=umrah_checklist&booking_id=<?= urlencode($b['id']) ?>" class="text-sky-600 bg-sky-50 w-8 h-8 inline-flex items-center justify-center rounded-lg hover:bg-sky-100 transition ml-1" title="Checklist"><i class="fa-solid fa-list-check text-xs"></i></a>
                            <?php if (!$_SESSION['is_staff']): ?>
                            <button onclick='openBkModal(<?= htmlspecialchars(json_encode($b)) ?>)' class="text-indigo-600 bg-indigo-50 w-8 h-8 rounded-lg hover:bg-indigo-100 ml-1 transition"><i class="fa-solid fa-pen text-xs"></i></button>
                            <form method="POST" action="?route=app" class="inline ml-1" onsubmit="return confirm('Delete this booking and all its payments?')">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="umrah_delete_booking">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($b['id']) ?>">
                                <button type="submit" class="text-rose-600 bg-rose-50 w-8 h-8 rounded-lg hover:bg-rose-100 transition"><i class="fa-solid fa-trash text-xs"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="10" class="px-6 py-12 text-center text-slate-400">No bookings found. Create your first booking.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Booking Modal -->
<div id="bkModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-y-auto max-h-[92vh] custom-scrollbar">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50 sticky top-0">
            <h3 class="font-extrabold text-slate-800 text-lg" id="bkModalTitle"><i class="fa-solid fa-calendar-check text-indigo-500 mr-2"></i> Booking</h3>
            <button onclick="closeBkModal()" class="w-8 h-8 rounded-full bg-slate-200/50 text-slate-400 hover:text-slate-700 flex items-center justify-center transition"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" action="?route=app" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="umrah_save_booking">
            <input type="hidden" name="id" id="bk_id" value="">

            <!-- Customer -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-bold text-slate-700 mb-1">Select Existing Customer</label>
                    <select name="customer_id" id="bk_customer_id" onchange="fillCustomer(this)" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="">— Walk-in / New Customer —</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= htmlspecialchars($c['id']) ?>" data-name="<?= htmlspecialchars($c['name']) ?>" data-mobile="<?= htmlspecialchars($c['mobile']) ?>">
                            <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['mobile']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Customer Name *</label>
                    <input type="text" name="customer_name" id="bk_cust_name" required class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Full name">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Mobile</label>
                    <input type="text" name="customer_mobile" id="bk_cust_mobile" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Mobile number">
                </div>
            </div>

            <!-- Package + Price -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-bold text-slate-700 mb-1">Package</label>
                    <select name="package_id" id="bk_package_id" onchange="updateTotal()" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="">— No Package Selected —</option>
                        <?php foreach ($activePackages as $ap): ?>
                        <option value="<?= htmlspecialchars($ap['id']) ?>" data-price="<?= $ap['price'] ?>">
                            <?= htmlspecialchars($ap['package_type'].' — '.$ap['package_name']) ?> (<?= htmlspecialchars($currencySymbol) ?><?= number_format($ap['price'], 2) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Travel Date</label>
                    <input type="date" name="travel_date" id="bk_travel_date" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">No. of Pilgrims *</label>
                    <input type="number" name="num_pilgrims" id="bk_pilgrims" required min="1" value="1" oninput="updateTotal()" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Total Price (<?= htmlspecialchars($currencySymbol) ?>) *</label>
                    <input type="number" name="total_price" id="bk_total_price" required min="0" step="0.01" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-indigo-50 font-bold text-indigo-700 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Auto-calculated or enter manually">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Booking Status *</label>
                    <select name="booking_status" id="bk_status" required class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="Inquiry">Inquiry</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <?php if (!$_SESSION['is_staff'] && $all_staff): ?>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Reference Staff</label>
                <select name="reference_staff_id" id="bk_ref_staff" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="">— None —</option>
                    <?php foreach ($all_staff as $st): ?>
                    <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['full_name']) ?> (<?= $st['role'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Notes</label>
                <textarea name="notes" id="bk_notes" rows="2" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none resize-none" placeholder="Optional notes..."></textarea>
            </div>

            <div class="flex gap-3 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeBkModal()" class="w-1/3 py-3 border border-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" class="w-2/3 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition">Save Booking</button>
            </div>
        </form>
    </div>
</div>

<script>
function openBkModal(data) {
    document.getElementById('bkModal').classList.remove('hidden');
    if (data) {
        document.getElementById('bkModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-indigo-500 mr-2"></i> Edit Booking';
        document.getElementById('bk_id').value          = data.id || '';
        document.getElementById('bk_customer_id').value = data.customer_id || '';
        document.getElementById('bk_cust_name').value   = data.customer_name || '';
        document.getElementById('bk_cust_mobile').value = data.customer_mobile || '';
        document.getElementById('bk_package_id').value  = data.package_id || '';
        document.getElementById('bk_travel_date').value = data.travel_date || '';
        document.getElementById('bk_pilgrims').value    = data.num_pilgrims || 1;
        document.getElementById('bk_total_price').value = data.total_price || '';
        document.getElementById('bk_status').value      = data.booking_status || 'Inquiry';
        document.getElementById('bk_notes').value       = data.notes || '';
        const ref = document.getElementById('bk_ref_staff');
        if (ref) ref.value = data.reference_staff_id || '';
    } else {
        document.getElementById('bkModalTitle').innerHTML = '<i class="fa-solid fa-plus text-indigo-500 mr-2"></i> New Booking';
        document.getElementById('bk_id').value = '';
        document.getElementById('bkModal').querySelector('form').reset();
        document.getElementById('bk_pilgrims').value = 1;
    }
}
function closeBkModal() { document.getElementById('bkModal').classList.add('hidden'); }
function fillCustomer(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (opt.value) {
        document.getElementById('bk_cust_name').value   = opt.dataset.name   || '';
        document.getElementById('bk_cust_mobile').value = opt.dataset.mobile || '';
    }
}
function updateTotal() {
    const pkgSel  = document.getElementById('bk_package_id');
    const pilgrims = parseInt(document.getElementById('bk_pilgrims').value) || 1;
    const opt     = pkgSel.options[pkgSel.selectedIndex];
    const price   = parseFloat(opt?.dataset?.price || 0);
    if (price > 0) {
        document.getElementById('bk_total_price').value = (price * pilgrims).toFixed(2);
    }
}
</script>
