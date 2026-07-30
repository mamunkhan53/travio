<?php
// ── Staff Management — Salary ──────────────────────────────────────────────────
require_once __DIR__ . '/../../includes/staff_actions.php';
$agency_id = $_SESSION['agency_id'];

// Active staff list
$staffList = $conn->prepare("SELECT id, full_name, role FROM staff WHERE agency_id=? AND status='Active' ORDER BY full_name");
$staffList->execute([$agency_id]);
$staffList = $staffList->fetchAll(PDO::FETCH_ASSOC);
$staffMap  = array_column($staffList, null, 'id');

// Agency info (for salary slip)
$agency = $conn->prepare("SELECT company_name, company_email, company_phone FROM agencies WHERE id=?");
$agency->execute([$agency_id]);
$agency = $agency->fetch(PDO::FETCH_ASSOC);

// Filters
$f_staff  = trim($_GET['staff']  ?? '');
$f_year   = trim($_GET['year']   ?? date('Y'));
$f_status = trim($_GET['status'] ?? '');

$where  = "s.agency_id = ?";
$params = [$agency_id];
if ($f_staff)  { $where .= " AND s.staff_id = ?";        $params[] = $f_staff; }
if ($f_status) { $where .= " AND s.payment_status = ?";  $params[] = $f_status; }
if ($f_year)   { $where .= " AND YEAR(s.salary_month) = ?"; $params[] = $f_year; }

$records = $conn->prepare("
    SELECT s.*, st.full_name, st.role
    FROM staff_salary s
    JOIN staff st ON s.staff_id = st.id
    WHERE $where
    ORDER BY s.salary_month DESC, st.full_name ASC
");
$records->execute($params);
$records = $records->fetchAll(PDO::FETCH_ASSOC);

// Totals
$totBasic  = array_sum(array_column($records, 'basic_salary'));
$totNet    = array_sum(array_column($records, 'net_salary'));
$totPaid   = array_sum(array_map(fn($r) => $r['payment_status']==='Paid' ? $r['net_salary'] : 0, $records));
$totUnpaid = $totNet - $totPaid;

// Year list for filter
$yearList = range(date('Y'), max(2020, (int)date('Y') - 5));

// Print record (for salary slip)
$printId = (int)($_GET['print'] ?? 0);
$printRow = null;
if ($printId) {
    $pr = $conn->prepare("SELECT s.*, st.full_name, st.role FROM staff_salary s JOIN staff st ON s.staff_id = st.id WHERE s.id=? AND s.agency_id=?");
    $pr->execute([$printId, $agency_id]);
    $printRow = $pr->fetch(PDO::FETCH_ASSOC);
}
?>
<style>
@media print {
    body * { visibility: hidden; }
    #salListPrintArea, #salListPrintArea * { visibility: visible; }
    #salListPrintArea { position: fixed; top: 0; left: 0; width: 100%; padding: 24px; }
    .no-print { display: none !important; }
    .sal-action-col { display: none !important; }
    .sal-print-header { display: block !important; }
}
</style>
<?php if ($printRow): ?>
<!-- ── Salary Slip Print View ─────────────────────────────────────────────── -->
<style>
@media print {
    body * { visibility: hidden; }
    #salarySlipPrint, #salarySlipPrint * { visibility: visible; }
    #salarySlipPrint { position: fixed; top: 0; left: 0; width: 100%; }
    .no-print { display: none !important; }
}
</style>
<div class="no-print mb-4 flex gap-3">
    <button onclick="window.print()" class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-indigo-700 shadow flex items-center gap-2">
        <i class="fa-solid fa-print"></i> Print / Download
    </button>
    <a href="?route=app&page=staff_salary" class="border border-slate-200 text-slate-600 px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-slate-50">
        <i class="fa-solid fa-arrow-left mr-1"></i> Back
    </a>
</div>
<div id="salarySlipPrint" class="bg-white rounded-2xl border border-slate-200 p-8 max-w-2xl mx-auto shadow-lg">
    <!-- Header -->
    <div class="flex justify-between items-start border-b border-slate-200 pb-5 mb-5">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-800"><?= htmlspecialchars($agency['company_name'] ?? '') ?></h1>
            <p class="text-sm text-slate-400 mt-1"><?= htmlspecialchars($agency['company_email'] ?? '') ?> · <?= htmlspecialchars($agency['company_phone'] ?? '') ?></p>
        </div>
        <div class="text-right">
            <div class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-extrabold tracking-wide">SALARY SLIP</div>
            <p class="text-xs text-slate-400 mt-1">Slip #<?= $printRow['id'] ?></p>
        </div>
    </div>

    <!-- Staff + Month Info -->
    <div class="grid grid-cols-2 gap-6 mb-6">
        <div>
            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-2">Employee</p>
            <p class="font-extrabold text-slate-800 text-lg"><?= htmlspecialchars($printRow['full_name']) ?></p>
            <p class="text-sm text-slate-500"><?= htmlspecialchars($printRow['role']) ?></p>
        </div>
        <div class="text-right">
            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-2">Salary Month</p>
            <p class="font-extrabold text-slate-800 text-lg"><?= date('F Y', strtotime($printRow['salary_month'])) ?></p>
            <?php if ($printRow['payment_status'] === 'Paid'): ?>
            <p class="text-sm text-emerald-600 font-bold">Paid on <?= $printRow['payment_date'] ? date('d M Y', strtotime($printRow['payment_date'])) : '—' ?></p>
            <?php else: ?>
            <p class="text-sm text-rose-500 font-bold">Unpaid</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Earnings / Deductions table -->
    <table class="w-full text-sm mb-6">
        <thead>
            <tr class="bg-slate-50">
                <th class="px-4 py-3 text-left font-bold text-slate-600 rounded-tl-lg">Description</th>
                <th class="px-4 py-3 text-right font-bold text-slate-600 rounded-tr-lg">Amount (<?= htmlspecialchars($currencySymbol) ?>)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <tr><td class="px-4 py-3 text-slate-700">Basic Salary</td><td class="px-4 py-3 text-right font-semibold"><?= number_format($printRow['basic_salary'], 2) ?></td></tr>
            <?php if ($printRow['bonus'] > 0): ?>
            <tr><td class="px-4 py-3 text-emerald-700">Bonus</td><td class="px-4 py-3 text-right font-semibold text-emerald-700">+ <?= number_format($printRow['bonus'], 2) ?></td></tr>
            <?php endif; ?>
            <?php if ($printRow['commission'] > 0): ?>
            <tr><td class="px-4 py-3 text-emerald-700">Commission</td><td class="px-4 py-3 text-right font-semibold text-emerald-700">+ <?= number_format($printRow['commission'], 2) ?></td></tr>
            <?php endif; ?>
            <?php if ($printRow['deduction'] > 0): ?>
            <tr><td class="px-4 py-3 text-rose-700">Deduction</td><td class="px-4 py-3 text-right font-semibold text-rose-700">− <?= number_format($printRow['deduction'], 2) ?></td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="bg-indigo-50">
                <th class="px-4 py-4 text-left font-extrabold text-indigo-800 rounded-bl-lg">Net Salary</th>
                <th class="px-4 py-4 text-right font-extrabold text-indigo-800 text-base rounded-br-lg"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($printRow['net_salary'], 2) ?></th>
            </tr>
        </tfoot>
    </table>

    <?php if ($printRow['notes']): ?>
    <div class="bg-slate-50 rounded-xl px-4 py-3 mb-6 text-sm text-slate-500">
        <span class="font-bold text-slate-600">Notes: </span><?= htmlspecialchars($printRow['notes']) ?>
    </div>
    <?php endif; ?>

    <div class="border-t border-dashed border-slate-200 pt-5 flex justify-between text-xs text-slate-400">
        <span>Generated: <?= date('d M Y H:i') ?></span>
        <span>This is a computer-generated salary slip.</span>
    </div>
</div>
<?php return; endif; ?>

<!-- ── Main Salary List ────────────────────────────────────────────────────── -->
<div class="space-y-6">
    <!-- Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 text-center">
            <div class="text-xl font-extrabold text-slate-800"><?= count($records) ?></div>
            <div class="text-xs text-slate-400 font-bold mt-0.5">Records</div>
        </div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 text-center">
            <div class="text-xl font-extrabold text-indigo-600"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($totNet, 0) ?></div>
            <div class="text-xs text-slate-400 font-bold mt-0.5">Total Net</div>
        </div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 text-center">
            <div class="text-xl font-extrabold text-emerald-600"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($totPaid, 0) ?></div>
            <div class="text-xs text-slate-400 font-bold mt-0.5">Paid</div>
        </div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 text-center">
            <div class="text-xl font-extrabold text-rose-600"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($totUnpaid, 0) ?></div>
            <div class="text-xs text-slate-400 font-bold mt-0.5">Unpaid</div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden" id="salListPrintArea">
        <!-- Print header (hidden on screen) -->
        <div class="sal-print-header hidden px-6 pt-4 pb-2">
            <h2 class="text-xl font-extrabold text-slate-800 mb-1">Salary Records — <?= htmlspecialchars($f_year) ?><?= $f_staff && isset($staffMap[$f_staff]) ? ' · '.htmlspecialchars($staffMap[$f_staff]['full_name']) : '' ?><?= $f_status ? ' · '.$f_status : '' ?></h2>
            <p class="text-xs text-slate-400 mb-4">Generated: <?= date('d M Y H:i') ?></p>
        </div>
        <div class="p-5 border-b flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50 no-print">
            <div>
                <h2 class="font-extrabold text-slate-800 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-money-bill-wave text-indigo-500"></i> Salary Records
                </h2>
                <p class="text-xs text-slate-400 mt-0.5"><?= count($records) ?> record(s) found</p>
            </div>
            <div class="flex gap-2">
                <button onclick="window.print()" class="px-4 py-2.5 rounded-xl text-sm font-bold border border-slate-200 text-slate-600 hover:bg-slate-50 transition flex items-center gap-2 no-print">
                    <i class="fa-solid fa-file-pdf text-rose-500"></i> Export PDF
                </button>
                <button onclick="openSalModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center gap-2 transition">
                    <i class="fa-solid fa-plus"></i> Add Salary
                </button>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="flex flex-wrap gap-3 p-4 border-b bg-slate-50/30">
            <input type="hidden" name="route" value="app">
            <input type="hidden" name="page"  value="staff_salary">
            <select name="staff" class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
                <option value="">All Staff</option>
                <?php foreach ($staffList as $st): ?>
                <option value="<?= $st['id'] ?>" <?= $f_staff == $st['id'] ? 'selected' : '' ?>><?= htmlspecialchars($st['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="year" class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
                <?php foreach ($yearList as $y): ?>
                <option value="<?= $y ?>" <?= $f_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-indigo-400 outline-none">
                <option value="">All Statuses</option>
                <option value="Paid"   <?= $f_status==='Paid'   ? 'selected' : '' ?>>Paid</option>
                <option value="Unpaid" <?= $f_status==='Unpaid' ? 'selected' : '' ?>>Unpaid</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition">Filter</button>
        </form>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                    <tr>
                        <th class="px-6 py-4 font-bold">Staff</th>
                        <th class="px-6 py-4 font-bold">Month</th>
                        <th class="px-6 py-4 font-bold text-right">Basic</th>
                        <th class="px-6 py-4 font-bold text-right">Bonus</th>
                        <th class="px-6 py-4 font-bold text-right">Commission</th>
                        <th class="px-6 py-4 font-bold text-right">Deduction</th>
                        <th class="px-6 py-4 font-bold text-right">Net Salary</th>
                        <th class="px-6 py-4 font-bold">Status</th>
                        <th class="px-6 py-4 font-bold text-right sal-action-col">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($records): foreach ($records as $r): ?>
                    <tr class="hover:bg-slate-50 transition text-slate-700">
                        <td class="px-6 py-4">
                            <div class="font-semibold"><?= htmlspecialchars($r['full_name']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($r['role']) ?></div>
                        </td>
                        <td class="px-6 py-4 font-semibold whitespace-nowrap"><?= date('M Y', strtotime($r['salary_month'])) ?></td>
                        <td class="px-6 py-4 text-right"><?= number_format($r['basic_salary'], 2) ?></td>
                        <td class="px-6 py-4 text-right text-emerald-600"><?= $r['bonus'] > 0 ? number_format($r['bonus'], 2) : '—' ?></td>
                        <td class="px-6 py-4 text-right text-emerald-600"><?= $r['commission'] > 0 ? number_format($r['commission'], 2) : '—' ?></td>
                        <td class="px-6 py-4 text-right text-rose-600"><?= $r['deduction'] > 0 ? number_format($r['deduction'], 2) : '—' ?></td>
                        <td class="px-6 py-4 text-right font-extrabold text-indigo-600"><?= htmlspecialchars($currencySymbol) ?> <?= number_format($r['net_salary'], 2) ?></td>
                        <td class="px-6 py-4">
                            <?php if ($r['payment_status'] === 'Paid'): ?>
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-100 text-emerald-700">Paid</span>
                            <?php if ($r['payment_date']): ?>
                            <div class="text-xs text-slate-400 mt-0.5"><?= date('d M Y', strtotime($r['payment_date'])) ?></div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-100 text-rose-700">Unpaid</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap sal-action-col">
                            <!-- Print Slip -->
                            <a href="?route=app&page=staff_salary&print=<?= $r['id'] ?>" class="text-slate-600 bg-slate-100 w-8 h-8 inline-flex items-center justify-center rounded-lg hover:bg-slate-200 transition" title="Print Salary Slip"><i class="fa-solid fa-print text-xs"></i></a>
                            <!-- Mark Paid -->
                            <?php if ($r['payment_status'] === 'Unpaid'): ?>
                            <button onclick="markPaid(<?= $r['id'] ?>)" class="text-emerald-600 bg-emerald-50 w-8 h-8 inline-flex items-center justify-center rounded-lg hover:bg-emerald-100 ml-1 transition" title="Mark as Paid"><i class="fa-solid fa-check text-xs"></i></button>
                            <?php endif; ?>
                            <!-- Edit -->
                            <button onclick='openSalModal(<?= htmlspecialchars(json_encode($r)) ?>)' class="text-indigo-600 bg-indigo-50 w-8 h-8 rounded-lg hover:bg-indigo-100 ml-1 transition"><i class="fa-solid fa-pen text-xs"></i></button>
                            <!-- Delete -->
                            <form method="POST" action="?route=app" class="inline ml-1" onsubmit="return confirm('Delete this salary record?')">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="staff_delete_salary">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="text-rose-600 bg-rose-50 w-8 h-8 rounded-lg hover:bg-rose-100 transition"><i class="fa-solid fa-trash text-xs"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="9" class="px-6 py-12 text-center text-slate-400">No salary records found. Click "Add Salary" to create one.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Mark Paid Form (hidden) -->
<form id="markPaidForm" method="POST" action="?route=app" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="action" value="staff_mark_salary_paid">
    <input type="hidden" name="id" id="markPaidId">
    <input type="hidden" name="payment_date" id="markPaidDate">
</form>

<!-- Salary Modal -->
<div id="salModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-y-auto max-h-[92vh] custom-scrollbar">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50 sticky top-0">
            <h3 class="font-extrabold text-slate-800 text-lg" id="salModalTitle">
                <i class="fa-solid fa-money-bill-wave text-indigo-500 mr-2"></i> Salary Record
            </h3>
            <button onclick="closeSalModal()" class="w-8 h-8 rounded-full bg-slate-200/50 text-slate-400 hover:text-slate-700 flex items-center justify-center transition"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" action="?route=app" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="staff_save_salary">
            <input type="hidden" name="id" id="sal_id" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Staff Member *</label>
                    <select name="staff_id" id="sal_staff_id" required class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="">— Select Staff —</option>
                        <?php foreach ($staffList as $st): ?>
                        <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['full_name']) ?> (<?= htmlspecialchars($st['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Salary Month *</label>
                    <input type="month" name="salary_month" id="sal_month" required class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Basic Salary (<?= htmlspecialchars($currencySymbol) ?>) *</label>
                    <input type="number" name="basic_salary" id="sal_basic" required min="0" step="0.01" oninput="calcNet()" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Bonus (<?= htmlspecialchars($currencySymbol) ?>)</label>
                    <input type="number" name="bonus" id="sal_bonus" min="0" step="0.01" value="0" oninput="calcNet()" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Commission (<?= htmlspecialchars($currencySymbol) ?>)</label>
                    <input type="number" name="commission" id="sal_commission" min="0" step="0.01" value="0" oninput="calcNet()" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Deduction (<?= htmlspecialchars($currencySymbol) ?>)</label>
                    <input type="number" name="deduction" id="sal_deduction" min="0" step="0.01" value="0" oninput="calcNet()" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="0.00">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-bold text-slate-700 mb-1">Net Salary (<?= htmlspecialchars($currencySymbol) ?>)</label>
                    <input type="number" name="net_salary_display" id="sal_net" readonly class="w-full px-4 py-3 border border-indigo-200 rounded-xl bg-indigo-50 font-extrabold text-indigo-700 outline-none cursor-not-allowed" placeholder="Auto-calculated">
                    <p class="text-xs text-slate-400 mt-1">= Basic + Bonus + Commission − Deduction</p>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Payment Status *</label>
                    <select name="payment_status" id="sal_pay_status" required onchange="togglePayDate()" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="Unpaid">Unpaid</option>
                        <option value="Paid">Paid</option>
                    </select>
                </div>
                <div id="salPayDateWrap" class="hidden">
                    <label class="block text-sm font-bold text-slate-700 mb-1">Payment Date</label>
                    <input type="date" name="payment_date" id="sal_pay_date" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Notes</label>
                <textarea name="notes" id="sal_notes" rows="2" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none resize-none" placeholder="Optional notes..."></textarea>
            </div>
            <div class="flex gap-3 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeSalModal()" class="w-1/3 py-3 border border-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" class="w-2/3 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition">Save Record</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSalModal(data) {
    document.getElementById('salModal').classList.remove('hidden');
    if (data) {
        document.getElementById('salModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-indigo-500 mr-2"></i> Edit Salary';
        document.getElementById('sal_id').value          = data.id || '';
        document.getElementById('sal_staff_id').value    = data.staff_id || '';
        // salary_month stored as YYYY-MM-DD, input[type=month] needs YYYY-MM
        document.getElementById('sal_month').value       = (data.salary_month || '').slice(0,7);
        document.getElementById('sal_basic').value       = data.basic_salary || '';
        document.getElementById('sal_bonus').value       = data.bonus || 0;
        document.getElementById('sal_commission').value  = data.commission || 0;
        document.getElementById('sal_deduction').value   = data.deduction || 0;
        document.getElementById('sal_net').value         = data.net_salary || '';
        document.getElementById('sal_pay_status').value  = data.payment_status || 'Unpaid';
        document.getElementById('sal_pay_date').value    = data.payment_date || '';
        document.getElementById('sal_notes').value       = data.notes || '';
        togglePayDate();
    } else {
        document.getElementById('salModalTitle').innerHTML = '<i class="fa-solid fa-plus text-indigo-500 mr-2"></i> New Salary Record';
        document.getElementById('sal_id').value = '';
        document.getElementById('salModal').querySelector('form').reset();
        document.getElementById('sal_month').value = new Date().toISOString().slice(0,7);
        document.getElementById('salPayDateWrap').classList.add('hidden');
    }
}
function closeSalModal() { document.getElementById('salModal').classList.add('hidden'); }
function calcNet() {
    const basic      = parseFloat(document.getElementById('sal_basic').value)      || 0;
    const bonus      = parseFloat(document.getElementById('sal_bonus').value)      || 0;
    const commission = parseFloat(document.getElementById('sal_commission').value) || 0;
    const deduction  = parseFloat(document.getElementById('sal_deduction').value)  || 0;
    document.getElementById('sal_net').value = (basic + bonus + commission - deduction).toFixed(2);
}
function togglePayDate() {
    const paid = document.getElementById('sal_pay_status').value === 'Paid';
    const wrap = document.getElementById('salPayDateWrap');
    wrap.classList.toggle('hidden', !paid);
    if (paid && !document.getElementById('sal_pay_date').value)
        document.getElementById('sal_pay_date').value = new Date().toISOString().split('T')[0];
}
function markPaid(id) {
    if (!confirm('Mark this salary as paid today?')) return;
    document.getElementById('markPaidId').value   = id;
    document.getElementById('markPaidDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('markPaidForm').submit();
}
</script>
