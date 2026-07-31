<?php
// ── Staff Management — Attendance ─────────────────────────────────────────────
require_once __DIR__ . '/../../includes/staff_actions.php';
$agency_id = $_SESSION['agency_id'];

// Active staff list
$staffList = $conn->prepare("SELECT id, full_name, role FROM staff WHERE agency_id=? AND status='Active' ORDER BY full_name");
$staffList->execute([$agency_id]);
$staffList = $staffList->fetchAll(PDO::FETCH_ASSOC);

// Filters
$viewMode   = trim($_GET['view']   ?? 'list');   // list | summary
$f_staff    = trim($_GET['staff']  ?? '');
$f_month    = trim($_GET['month']  ?? date('Y-m'));
$f_from     = trim($_GET['from']   ?? '');
$f_to       = trim($_GET['to']     ?? '');
$f_status   = trim($_GET['status'] ?? '');

$statusColors = [
    'Present' => 'bg-emerald-100 text-emerald-700',
    'Absent'  => 'bg-rose-100 text-rose-700',
    'Late'    => 'bg-amber-100 text-amber-700',
    'Leave'   => 'bg-sky-100 text-sky-700',
];

// ── List view query ───────────────────────────────────────────────────────────
$records = [];
if ($viewMode === 'list') {
    $where  = "a.agency_id = ?";
    $params = [$agency_id];
    if ($f_staff)  { $where .= " AND a.staff_id = ?";        $params[] = $f_staff; }
    if ($f_status) { $where .= " AND a.status = ?";           $params[] = $f_status; }
    if ($f_from)   { $where .= " AND a.attendance_date >= ?"; $params[] = $f_from; }
    if ($f_to)     { $where .= " AND a.attendance_date <= ?"; $params[] = $f_to; }
    if (!$f_from && !$f_to) {
        // Default: current month
        $where .= " AND DATE_FORMAT(a.attendance_date,'%Y-%m') = ?";
        $params[] = $f_month;
    }
    $q = $conn->prepare("
        SELECT a.*, s.full_name, s.role
        FROM staff_attendance a
        JOIN staff s ON a.staff_id = s.id
        WHERE $where
        ORDER BY a.attendance_date DESC, s.full_name ASC
    ");
    $q->execute($params);
    $records = $q->fetchAll(PDO::FETCH_ASSOC);
}

// ── Monthly summary query ─────────────────────────────────────────────────────
$summary = [];
if ($viewMode === 'summary') {
    $sumWhere  = "a.agency_id = ? AND DATE_FORMAT(a.attendance_date,'%Y-%m') = ?";
    $sumParams = [$agency_id, $f_month];
    if ($f_staff) { $sumWhere .= " AND a.staff_id = ?"; $sumParams[] = $f_staff; }
    $q = $conn->prepare("
        SELECT s.full_name, s.role,
               SUM(a.status='Present') AS present,
               SUM(a.status='Absent')  AS absent,
               SUM(a.status='Late')    AS late,
               SUM(a.status='Leave')   AS `leave`,
               COUNT(a.id)             AS total_days
        FROM staff_attendance a
        JOIN staff s ON a.staff_id = s.id
        WHERE $sumWhere
        GROUP BY a.staff_id
        ORDER BY s.full_name
    ");
    $q->execute($sumParams);
    $summary = $q->fetchAll(PDO::FETCH_ASSOC);
}
?>
<style>
@media print {
    body * { visibility: hidden; }
    #attPrintArea, #attPrintArea * { visibility: visible; }
    #attPrintArea { position: fixed; top: 0; left: 0; width: 100%; padding: 24px; }
    .no-print { display: none !important; }
    .att-action-col { display: none !important; }
    .att-print-header { display: block !important; }
}
</style>
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden" id="attPrintArea">
        <div class="p-5 border-b flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50 no-print">
            <div>
                <h2 class="font-extrabold text-slate-800 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-calendar-day text-indigo-500"></i> Attendance
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Track daily staff check-in and check-out</p>
            </div>
            <div class="flex gap-2">
                <a href="/app/staff_attendance?view=list&month=<?= urlencode($f_month) ?><?= $f_staff ? '&staff='.urlencode($f_staff) : '' ?>"
                   class="px-4 py-2 rounded-xl text-sm font-bold transition <?= $viewMode==='list' ? 'bg-indigo-600 text-white shadow-md' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-list mr-1"></i> Records
                </a>
                <a href="/app/staff_attendance?view=summary&month=<?= urlencode($f_month) ?><?= $f_staff ? '&staff='.urlencode($f_staff) : '' ?>"
                   class="px-4 py-2 rounded-xl text-sm font-bold transition <?= $viewMode==='summary' ? 'bg-indigo-600 text-white shadow-md' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                    <i class="fa-solid fa-chart-bar mr-1"></i> Monthly Summary
                </a>
                <button onclick="window.print()" class="px-4 py-2 rounded-xl text-sm font-bold border border-slate-200 text-slate-600 hover:bg-slate-50 transition flex items-center gap-2 no-print">
                    <i class="fa-solid fa-file-pdf text-rose-500"></i> Export PDF
                </button>
                <button onclick="openAttModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-sm font-bold shadow-md flex items-center gap-2 transition">
                    <i class="fa-solid fa-plus"></i> Add Record
                </button>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="flex flex-wrap gap-3 p-4 border-b bg-slate-50/30 no-print">
            <input type="hidden" name="route" value="app">
            <input type="hidden" name="page"  value="staff_attendance">
            <input type="hidden" name="view"  value="<?= htmlspecialchars($viewMode) ?>">
            <select name="staff" class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
                <option value="">All Staff</option>
                <?php foreach ($staffList as $st): ?>
                <option value="<?= $st['id'] ?>" <?= $f_staff == $st['id'] ? 'selected' : '' ?>><?= htmlspecialchars($st['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="month" name="month" value="<?= htmlspecialchars($f_month) ?>" class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none" title="Month">
            <?php if ($viewMode === 'list'): ?>
            <select name="status" class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
                <option value="">All Statuses</option>
                <?php foreach (['Present','Absent','Late','Leave'] as $s): ?>
                <option value="<?= $s ?>" <?= $f_status===$s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition">Filter</button>
        </form>

        <!-- Print header (hidden on screen, visible on print) -->
        <div class="hidden px-6 pt-4 pb-2 att-print-header">
            <h2 class="text-xl font-extrabold text-slate-800 mb-1">
                <?= $viewMode === 'summary' ? 'Monthly Attendance Summary' : 'Attendance Records' ?>
                — <?= htmlspecialchars(date('F Y', strtotime($f_month.'-01'))) ?>
            </h2>
            <p class="text-xs text-slate-400 mb-4">Generated: <?= date('d M Y H:i') ?></p>
        </div>

        <?php if ($viewMode === 'list'): ?>
        <!-- List View -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                    <tr>
                        <th class="px-6 py-4 font-bold">Date</th>
                        <th class="px-6 py-4 font-bold">Staff</th>
                        <th class="px-6 py-4 font-bold">Role</th>
                        <th class="px-6 py-4 font-bold">Status</th>
                        <th class="px-6 py-4 font-bold">Check In</th>
                        <th class="px-6 py-4 font-bold">Check Out</th>
                        <th class="px-6 py-4 font-bold">Notes</th>
                        <th class="px-6 py-4 font-bold text-right att-action-col">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($records): foreach ($records as $r): ?>
                    <tr class="hover:bg-slate-50 transition text-slate-700">
                        <td class="px-6 py-4 font-semibold whitespace-nowrap"><?= date('d M Y', strtotime($r['attendance_date'])) ?></td>
                        <td class="px-6 py-4 font-semibold"><?= htmlspecialchars($r['full_name']) ?></td>
                        <td class="px-6 py-4 text-slate-400 text-xs"><?= htmlspecialchars($r['role']) ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $statusColors[$r['status']] ?? 'bg-slate-100 text-slate-600' ?>">
                                <?= htmlspecialchars($r['status']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4"><?= $r['check_in'] ? date('h:i A', strtotime($r['check_in'])) : '—' ?></td>
                        <td class="px-6 py-4"><?= $r['check_out'] ? date('h:i A', strtotime($r['check_out'])) : '—' ?></td>
                        <td class="px-6 py-4 text-slate-400 text-xs max-w-[160px] truncate"><?= htmlspecialchars($r['notes'] ?: '—') ?></td>
                        <td class="px-6 py-4 text-right whitespace-nowrap att-action-col">
                            <button onclick='openAttModal(<?= htmlspecialchars(json_encode($r)) ?>)' class="text-indigo-600 bg-indigo-50 w-8 h-8 rounded-lg hover:bg-indigo-100 transition"><i class="fa-solid fa-pen text-xs"></i></button>
                            <form method="POST" action="" class="inline ml-1" onsubmit="return confirm('Delete this attendance record?')">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="staff_delete_attendance">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="text-rose-600 bg-rose-50 w-8 h-8 rounded-lg hover:bg-rose-100 transition"><i class="fa-solid fa-trash text-xs"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="8" class="px-6 py-12 text-center text-slate-400">No attendance records found for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <!-- Monthly Summary View -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                    <tr>
                        <th class="px-6 py-4 font-bold">Staff</th>
                        <th class="px-6 py-4 font-bold">Role</th>
                        <th class="px-6 py-4 font-bold text-center text-emerald-600">Present</th>
                        <th class="px-6 py-4 font-bold text-center text-rose-600">Absent</th>
                        <th class="px-6 py-4 font-bold text-center text-amber-600">Late</th>
                        <th class="px-6 py-4 font-bold text-center text-sky-600">Leave</th>
                        <th class="px-6 py-4 font-bold text-center">Total Days</th>
                        <th class="px-6 py-4 font-bold text-center">Attendance %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($summary): foreach ($summary as $r):
                        $pct = $r['total_days'] > 0 ? round(($r['present'] + $r['late']) / $r['total_days'] * 100) : 0;
                    ?>
                    <tr class="hover:bg-slate-50 transition text-slate-700">
                        <td class="px-6 py-4 font-semibold"><?= htmlspecialchars($r['full_name']) ?></td>
                        <td class="px-6 py-4 text-slate-400 text-xs"><?= htmlspecialchars($r['role']) ?></td>
                        <td class="px-6 py-4 text-center font-extrabold text-emerald-600"><?= (int)$r['present'] ?></td>
                        <td class="px-6 py-4 text-center font-extrabold text-rose-600"><?= (int)$r['absent'] ?></td>
                        <td class="px-6 py-4 text-center font-extrabold text-amber-600"><?= (int)$r['late'] ?></td>
                        <td class="px-6 py-4 text-center font-extrabold text-sky-600"><?= (int)$r['leave'] ?></td>
                        <td class="px-6 py-4 text-center font-bold"><?= (int)$r['total_days'] ?></td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center gap-2 justify-center">
                                <div class="w-20 bg-slate-100 rounded-full h-2">
                                    <div class="h-2 rounded-full <?= $pct >= 80 ? 'bg-emerald-500' : ($pct >= 60 ? 'bg-amber-500' : 'bg-rose-500') ?>"
                                         style="width:<?= $pct ?>%"></div>
                                </div>
                                <span class="text-xs font-bold <?= $pct >= 80 ? 'text-emerald-600' : ($pct >= 60 ? 'text-amber-600' : 'text-rose-600') ?>"><?= $pct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="8" class="px-6 py-12 text-center text-slate-400">No attendance data for <?= htmlspecialchars(date('F Y', strtotime($f_month.'-01'))) ?>.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Attendance Modal -->
<div id="attModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-y-auto max-h-[92vh] custom-scrollbar">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50 sticky top-0">
            <h3 class="font-extrabold text-slate-800 text-lg" id="attModalTitle">
                <i class="fa-solid fa-calendar-day text-indigo-500 mr-2"></i> Attendance
            </h3>
            <button onclick="closeAttModal()" class="w-8 h-8 rounded-full bg-slate-200/50 text-slate-400 hover:text-slate-700 flex items-center justify-center transition"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" action="" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="staff_save_attendance">
            <input type="hidden" name="id" id="att_id" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-bold text-slate-700 mb-1">Staff Member *</label>
                    <select name="staff_id" id="att_staff_id" required class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="">— Select Staff —</option>
                        <?php foreach ($staffList as $st): ?>
                        <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['full_name']) ?> (<?= htmlspecialchars($st['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Date *</label>
                    <input type="date" name="attendance_date" id="att_date" required class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Status *</label>
                    <select name="status" id="att_status" required class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Late">Late</option>
                        <option value="Leave">Leave</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Check In</label>
                    <input type="time" name="check_in" id="att_check_in" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Check Out</label>
                    <input type="time" name="check_out" id="att_check_out" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Notes</label>
                <textarea name="notes" id="att_notes" rows="2" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none resize-none" placeholder="Optional notes..."></textarea>
            </div>
            <div class="flex gap-3 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeAttModal()" class="w-1/3 py-3 border border-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" class="w-2/3 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition">Save Attendance</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAttModal(data) {
    document.getElementById('attModal').classList.remove('hidden');
    if (data) {
        document.getElementById('attModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-indigo-500 mr-2"></i> Edit Attendance';
        document.getElementById('att_id').value        = data.id || '';
        document.getElementById('att_staff_id').value  = data.staff_id || '';
        document.getElementById('att_date').value       = data.attendance_date || '';
        document.getElementById('att_status').value     = data.status || 'Present';
        document.getElementById('att_check_in').value  = data.check_in || '';
        document.getElementById('att_check_out').value = data.check_out || '';
        document.getElementById('att_notes').value     = data.notes || '';
    } else {
        document.getElementById('attModalTitle').innerHTML = '<i class="fa-solid fa-plus text-indigo-500 mr-2"></i> Add Attendance';
        document.getElementById('att_id').value = '';
        document.getElementById('attModal').querySelector('form').reset();
        document.getElementById('att_date').value = new Date().toISOString().split('T')[0];
    }
}
function closeAttModal() { document.getElementById('attModal').classList.add('hidden'); }
</script>
