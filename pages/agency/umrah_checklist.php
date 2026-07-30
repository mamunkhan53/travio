<?php
// ── Hajj & Umrah — Document Checklist ────────────────────────────────────────
require_once __DIR__ . '/../../includes/umrah_actions.php';
$agency_id  = $_SESSION['agency_id'];
$f_status   = trim($_GET['status'] ?? '');
$f_bk       = trim($_GET['booking_id'] ?? '');

$where  = "b.agency_id = ?";
$params = [$agency_id];
if ($f_status) { $where .= " AND b.booking_status = ?"; $params[] = $f_status; }
if ($f_bk)     { $where .= " AND b.id = ?";             $params[] = $f_bk; }

$bookings = $conn->prepare("
    SELECT b.*, p.package_name, p.package_type
    FROM umrah_bookings b
    LEFT JOIN umrah_packages p ON b.package_id = p.id
    WHERE $where
    ORDER BY b.booking_status, b.travel_date
");
$bookings->execute($params);
$bookings = $bookings->fetchAll(PDO::FETCH_ASSOC);

$statusColors = [
    'Inquiry'   => 'bg-amber-100 text-amber-700',
    'Confirmed' => 'bg-blue-100 text-blue-700',
    'Completed' => 'bg-emerald-100 text-emerald-700',
    'Cancelled' => 'bg-rose-100 text-rose-700',
];
$checks = [
    'passport_received' => ['Passport Received',  'fa-passport',         'indigo'],
    'visa_completed'    => ['Visa Completed',      'fa-file-signature',   'violet'],
    'ticket_issued'     => ['Ticket Issued',       'fa-plane',            'sky'],
    'hotel_confirmed'   => ['Hotel Confirmed',     'fa-hotel',            'amber'],
];
?>
<div class="space-y-6">
    <!-- Header + filters -->
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="p-5 border-b flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-slate-50/50">
            <div>
                <h2 class="font-extrabold text-slate-800 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-indigo-500"></i> Document Checklist
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Track document completion per booking</p>
            </div>
        </div>

        <!-- Legend -->
        <div class="px-5 py-3 border-b bg-slate-50/20 flex flex-wrap gap-4">
            <?php foreach ($checks as $key => [$lbl, $icon, $color]): ?>
            <span class="flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                <i class="fa-solid <?= $icon ?> text-<?= $color ?>-500"></i> <?= $lbl ?>
            </span>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <form method="GET" class="flex flex-wrap gap-3 p-4 border-b bg-slate-50/20">
            <input type="hidden" name="route" value="app">
            <input type="hidden" name="page"  value="umrah_checklist">
            <select name="status" class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
                <option value="">All Statuses</option>
                <?php foreach (['Inquiry','Confirmed','Completed','Cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $f_status===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition">Filter</button>
            <?php if ($f_status || $f_bk): ?>
            <a href="?route=app&page=umrah_checklist" class="px-4 py-2 border border-slate-200 text-slate-500 rounded-xl text-sm hover:bg-slate-50 transition">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                    <tr>
                        <th class="px-5 py-4 font-bold text-left">Booking</th>
                        <th class="px-5 py-4 font-bold text-left">Customer</th>
                        <th class="px-5 py-4 font-bold text-left">Package</th>
                        <th class="px-5 py-4 font-bold text-left">Travel Date</th>
                        <?php foreach ($checks as $key => [$lbl, $icon, $color]): ?>
                        <th class="px-4 py-4 font-bold text-center whitespace-nowrap">
                            <i class="fa-solid <?= $icon ?> text-<?= $color ?>-400 mr-1"></i><?= $lbl ?>
                        </th>
                        <?php endforeach; ?>
                        <th class="px-5 py-4 font-bold text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($bookings): foreach ($bookings as $b): 
                        $done = (int)$b['passport_received'] + (int)$b['visa_completed'] + (int)$b['ticket_issued'] + (int)$b['hotel_confirmed'];
                    ?>
                    <tr class="hover:bg-slate-50 transition text-slate-700" id="row_<?= $b['id'] ?>">
                        <td class="px-5 py-4">
                            <div class="font-extrabold text-indigo-600"><?= htmlspecialchars($b['id']) ?></div>
                            <div class="text-xs text-slate-400 mt-0.5">
                                <span class="font-bold text-<?= $done==4?'emerald':'amber' ?>-600"><?= $done ?>/4</span> complete
                            </div>
                        </td>
                        <td class="px-5 py-4 font-semibold"><?= htmlspecialchars($b['customer_name'] ?: '—') ?></td>
                        <td class="px-5 py-4 text-slate-500"><?= htmlspecialchars($b['package_name'] ?: '—') ?></td>
                        <td class="px-5 py-4"><?= $b['travel_date'] ? date('d M Y', strtotime($b['travel_date'])) : '—' ?></td>
                        <?php foreach ($checks as $field => [$lbl, $icon, $color]): ?>
                        <td class="px-4 py-4 text-center">
                            <button type="button"
                                onclick="toggleCheck('<?= $b['id'] ?>', '<?= $field ?>', this)"
                                data-val="<?= (int)$b[$field] ?>"
                                title="<?= $lbl ?>"
                                class="w-8 h-8 rounded-xl flex items-center justify-center mx-auto transition-all
                                       <?= $b[$field] ? "bg-{$color}-100 text-{$color}-600 hover:bg-{$color}-200" : 'bg-slate-100 text-slate-300 hover:bg-slate-200' ?>">
                                <i class="fa-solid <?= $b[$field] ? 'fa-check' : 'fa-times' ?> text-sm"></i>
                            </button>
                        </td>
                        <?php endforeach; ?>
                        <td class="px-5 py-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $statusColors[$b['booking_status']] ?? 'bg-slate-100 text-slate-600' ?>">
                                <?= htmlspecialchars($b['booking_status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="10" class="px-5 py-12 text-center text-slate-400">No bookings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleCheck(bookingId, field, btn) {
    const cur  = parseInt(btn.dataset.val);
    const next = cur ? 0 : 1;
    const col  = { passport_received:'indigo', visa_completed:'violet', ticket_issued:'sky', hotel_confirmed:'amber' }[field] || 'indigo';

    fetch('?route=app', {
        method : 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body   : new URLSearchParams({
            csrf_token : '<?= $_SESSION['csrf_token'] ?>',
            action     : 'umrah_update_checklist',
            id         : bookingId,
            field      : field,
            value      : next
        })
    })
    .then(r => r.json())
    .then(function(res) {
        if (!res.success) return;
        btn.dataset.val = next;
        if (next) {
            btn.className = `w-8 h-8 rounded-xl flex items-center justify-center mx-auto transition-all bg-${col}-100 text-${col}-600 hover:bg-${col}-200`;
            btn.innerHTML = '<i class="fa-solid fa-check text-sm"></i>';
        } else {
            btn.className = 'w-8 h-8 rounded-xl flex items-center justify-center mx-auto transition-all bg-slate-100 text-slate-300 hover:bg-slate-200';
            btn.innerHTML = '<i class="fa-solid fa-times text-sm"></i>';
        }
        // Update progress counter
        const row = document.getElementById('row_' + bookingId);
        if (row) {
            let done = 0;
            row.querySelectorAll('button[data-val]').forEach(b => { if (parseInt(b.dataset.val)) done++; });
            const prog = row.querySelector('.font-bold.text-emerald-600, .font-bold.text-amber-600');
            if (prog) {
                prog.textContent = done + '/4';
                prog.className = `font-bold text-${done===4?'emerald':'amber'}-600`;
            }
        }
    })
    .catch(() => {});
}
</script>
