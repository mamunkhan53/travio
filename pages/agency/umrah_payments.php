<?php
// ── Hajj & Umrah — Payments ──────────────────────────────────────────────────
require_once __DIR__ . '/../../includes/umrah_actions.php';
$agency_id  = $_SESSION['agency_id'];
$sel_bk_id  = trim($_GET['booking_id'] ?? '');

// All bookings for selector
$allBookings = $conn->prepare("
    SELECT b.id, b.customer_name, b.travel_date, b.total_price, b.booking_status,
           p.package_name, p.package_type
    FROM umrah_bookings b
    LEFT JOIN umrah_packages p ON b.package_id = p.id
    WHERE b.agency_id = ?
    ORDER BY b.created_at DESC
");
$allBookings->execute([$agency_id]);
$allBookings = $allBookings->fetchAll(PDO::FETCH_ASSOC);

// Selected booking detail
$selBooking  = null;
$payments    = [];
$totalPaid   = 0;

if ($sel_bk_id) {
    $bkSt = $conn->prepare("
        SELECT b.*, p.package_name, p.package_type
        FROM umrah_bookings b
        LEFT JOIN umrah_packages p ON b.package_id = p.id
        WHERE b.id = ? AND b.agency_id = ?
    ");
    $bkSt->execute([$sel_bk_id, $agency_id]);
    $selBooking = $bkSt->fetch(PDO::FETCH_ASSOC);

    if ($selBooking) {
        $pySt = $conn->prepare("SELECT py.*, s.full_name AS staff_name FROM umrah_payments py LEFT JOIN staff s ON py.created_by_staff_id = s.id WHERE py.booking_id = ? AND py.agency_id = ? ORDER BY py.payment_date DESC, py.created_at DESC");
        $pySt->execute([$sel_bk_id, $agency_id]);
        $payments  = $pySt->fetchAll(PDO::FETCH_ASSOC);
        $totalPaid = array_sum(array_column($payments, 'amount'));
    }
}

$statusColors = [
    'Inquiry'   => 'bg-amber-100 text-amber-700',
    'Confirmed' => 'bg-blue-100 text-blue-700',
    'Completed' => 'bg-emerald-100 text-emerald-700',
    'Cancelled' => 'bg-rose-100 text-rose-700',
];
?>
<div class="space-y-6">
    <!-- Booking Selector -->
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
        <h2 class="font-extrabold text-slate-800 text-lg flex items-center gap-2 mb-4">
            <i class="fa-solid fa-coins text-indigo-500"></i> Payments
        </h2>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="route" value="app">
            <input type="hidden" name="page"  value="umrah_payments">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Select Booking</label>
                <select name="booking_id" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    <option value="">— Choose a booking —</option>
                    <?php foreach ($allBookings as $bk): ?>
                    <option value="<?= htmlspecialchars($bk['id']) ?>" <?= $sel_bk_id===$bk['id']?'selected':'' ?>>
                        <?= htmlspecialchars($bk['id']) ?> — <?= htmlspecialchars($bk['customer_name'] ?: 'Unknown') ?>
                        <?= $bk['package_name'] ? ' | '.htmlspecialchars($bk['package_name']) : '' ?>
                        <?= $bk['travel_date'] ? ' | '.date('d M Y', strtotime($bk['travel_date'])) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="px-5 py-3 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition">View</button>
        </form>
    </div>

    <?php if ($selBooking): ?>
    <!-- Booking Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <?php
        $due = $selBooking['total_price'] - $totalPaid;
        $cards = [
            ['Total Price', number_format($selBooking['total_price'], 2), 'fa-file-invoice-dollar', 'from-indigo-500 to-indigo-600'],
            ['Total Paid',  number_format($totalPaid, 2),                'fa-circle-check',         'from-emerald-500 to-emerald-600'],
            ['Due Amount',  number_format(max(0, $due), 2),              'fa-hourglass-half',        $due > 0 ? 'from-rose-500 to-rose-600' : 'from-slate-400 to-slate-500'],
        ];
        foreach ($cards as [$lbl,$val,$icon,$grad]):
        ?>
        <div class="bg-gradient-to-br <?= $grad ?> text-white rounded-2xl p-5 shadow-md">
            <p class="text-xs font-bold opacity-75 uppercase tracking-wide mb-1"><?= $lbl ?></p>
            <p class="text-2xl font-extrabold"><?= htmlspecialchars($currencySymbol) ?> <?= $val ?></p>
            <i class="fa-solid <?= $icon ?> text-white/20 text-4xl absolute right-4 bottom-3 pointer-events-none"></i>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Booking Info + Add Payment -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Payment History -->
        <div class="lg:col-span-2 bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
            <div class="p-5 border-b flex items-center justify-between bg-slate-50/50">
                <h3 class="font-extrabold text-slate-800">Payment History</h3>
                <span class="text-xs text-slate-400"><?= count($payments) ?> payment(s)</span>
            </div>
            <?php if ($payments): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                        <tr>
                            <th class="px-5 py-3 font-bold text-left">Date</th>
                            <th class="px-5 py-3 font-bold text-left">Amount</th>
                            <th class="px-5 py-3 font-bold text-left">Method</th>
                            <th class="px-5 py-3 font-bold text-left">Notes</th>
                            <th class="px-5 py-3 font-bold text-left">By</th>
                            <?php if (!$_SESSION['is_staff']): ?><th class="px-5 py-3"></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($payments as $py): ?>
                        <tr class="hover:bg-slate-50 text-slate-700">
                            <td class="px-5 py-3 font-medium"><?= date('d M Y', strtotime($py['payment_date'])) ?></td>
                            <td class="px-5 py-3 font-bold text-emerald-600"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($py['amount'], 2) ?></td>
                            <td class="px-5 py-3"><?= htmlspecialchars($py['payment_method']) ?></td>
                            <td class="px-5 py-3 text-slate-500"><?= htmlspecialchars($py['notes'] ?: '—') ?></td>
                            <td class="px-5 py-3 text-xs text-slate-400"><?= htmlspecialchars($py['staff_name'] ?: 'Admin') ?></td>
                            <?php if (!$_SESSION['is_staff']): ?>
                            <td class="px-5 py-3 text-right">
                                <form method="POST" action="?route=app" class="inline" onsubmit="return confirm('Remove this payment?')">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="umrah_delete_payment">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($py['id']) ?>">
                                    <input type="hidden" name="booking_id" value="<?= htmlspecialchars($sel_bk_id) ?>">
                                    <button type="submit" class="text-rose-500 hover:text-rose-700 text-xs"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="py-12 text-center text-slate-400">No payments recorded yet.</div>
            <?php endif; ?>
        </div>

        <!-- Add Payment + Booking Info -->
        <div class="space-y-5">
            <!-- Booking detail card -->
            <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 space-y-2 text-sm">
                <h4 class="font-extrabold text-slate-800 mb-3">Booking Details</h4>
                <div class="flex justify-between"><span class="text-slate-500">Booking ID</span><span class="font-bold text-indigo-600"><?= htmlspecialchars($selBooking['id']) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Customer</span><span class="font-semibold"><?= htmlspecialchars($selBooking['customer_name'] ?: '—') ?></span></div>
                <?php if ($selBooking['package_name']): ?>
                <div class="flex justify-between"><span class="text-slate-500">Package</span><span class="font-semibold"><?= htmlspecialchars($selBooking['package_name']) ?></span></div>
                <?php endif; ?>
                <?php if ($selBooking['travel_date']): ?>
                <div class="flex justify-between"><span class="text-slate-500">Travel Date</span><span><?= date('d M Y', strtotime($selBooking['travel_date'])) ?></span></div>
                <?php endif; ?>
                <div class="flex justify-between"><span class="text-slate-500">Pilgrims</span><span class="font-bold"><?= (int)$selBooking['num_pilgrims'] ?></span></div>
                <div class="flex justify-between items-center pt-1 border-t border-slate-100">
                    <span class="text-slate-500">Status</span>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $statusColors[$selBooking['booking_status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= htmlspecialchars($selBooking['booking_status']) ?></span>
                </div>
                <?php if ($selBooking['customer_id']): ?>
                <a href="?route=app&page=customer_profile&id=<?= htmlspecialchars($selBooking['customer_id']) ?>" class="block text-center text-xs text-indigo-500 hover:underline pt-1">View Customer Profile →</a>
                <?php endif; ?>
            </div>

            <!-- Add Payment form -->
            <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
                <h4 class="font-extrabold text-slate-800 mb-4">Record Payment</h4>
                <form method="POST" action="?route=app" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="umrah_save_payment">
                    <input type="hidden" name="booking_id" value="<?= htmlspecialchars($sel_bk_id) ?>">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Amount (<?= htmlspecialchars($currencySymbol) ?>) *</label>
                        <input type="number" name="amount" required min="0.01" step="0.01" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-bold" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Payment Date *</label>
                        <input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Method</label>
                        <select name="payment_method" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                            <?php foreach (['Cash','Bank Transfer','Card','Mobile Banking','Cheque'] as $m): ?>
                            <option value="<?= $m ?>"><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Notes</label>
                        <input type="text" name="notes" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none text-sm" placeholder="Optional note">
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-md shadow-indigo-200 transition">
                        <i class="fa-solid fa-plus mr-1"></i> Add Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 py-16 text-center text-slate-400">
        <i class="fa-solid fa-coins text-4xl mb-3 opacity-30"></i>
        <p class="font-semibold">Select a booking above to view and record payments.</p>
    </div>
    <?php endif; ?>
</div>
