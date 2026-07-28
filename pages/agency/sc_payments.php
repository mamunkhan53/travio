<?php
// Payments — all student payments
$sc_filter_student = trim($_GET['student_id'] ?? '');
$sc_search = trim($_GET['q'] ?? '');

$where = "p.agency_id=?"; $params = [$agency_id];
if ($sc_filter_student) { $where .= " AND p.student_id=?"; $params[] = $sc_filter_student; }
if ($sc_search) { $where .= " AND (s.student_name LIKE ? OR p.payment_type LIKE ?)"; $params=array_merge($params,["%$sc_search%","%$sc_search%"]); }

$pmts = $conn->prepare("SELECT p.*, s.student_name, s.mobile FROM sc_payments p JOIN sc_students s ON p.student_id=s.id WHERE $where ORDER BY p.created_at DESC");
$pmts->execute($params); $pmts = $pmts->fetchAll(PDO::FETCH_ASSOC);

$students_r = $conn->prepare("SELECT id, student_name FROM sc_students WHERE agency_id=? ORDER BY student_name"); $students_r->execute([$agency_id]); $students_list = $students_r->fetchAll(PDO::FETCH_ASSOC);
$sc_pcats_r = $conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='payment_categories' ORDER BY value"); $sc_pcats_r->execute([$agency_id]); $sc_pcats = $sc_pcats_r->fetchAll(PDO::FETCH_COLUMN);

$totalRevenue = $conn->query("SELECT SUM(total_amount) FROM sc_payments p JOIN sc_students s ON p.student_id=s.id WHERE p.agency_id=$agency_id")->fetchColumn() ?: 0;
$totalPaid    = $conn->query("SELECT SUM(paid_amount) FROM sc_payments p JOIN sc_students s ON p.student_id=s.id WHERE p.agency_id=$agency_id")->fetchColumn() ?: 0;
$totalDue     = $conn->query("SELECT SUM(due_amount) FROM sc_payments p JOIN sc_students s ON p.student_id=s.id WHERE p.agency_id=$agency_id")->fetchColumn() ?: 0;
$paymentTypes = array_merge($sc_pcats, ['Consultancy Fee','Application Fee','Tuition Deposit','Visa Fee','Medical Fee','Service Charge','Other']);
?>
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-coins text-indigo-500"></i> Payments</h2><p class="text-sm text-slate-500 mt-1">Track all student-related payments and generate invoices.</p></div>
        <?php if (has_permission('can_manage_sc_payments')): ?>
        <button onclick="document.getElementById('scPmtListModal').classList.remove('hidden');document.getElementById('scPmtListModal').classList.add('flex')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow transition flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Payment</button>
        <?php endif; ?>
    </div>
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total Billed</p><p class="text-2xl font-black text-indigo-600"><?= number_format($totalRevenue,2) ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Collected</p><p class="text-2xl font-black text-emerald-600"><?= number_format($totalPaid,2) ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Outstanding</p><p class="text-2xl font-black text-rose-500"><?= number_format($totalDue,2) ?></p></div>
    </div>
    <form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-center">
        <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="sc_payments">
        <input type="text" name="q" value="<?= xss_clean($sc_search) ?>" placeholder="Search student, type…" class="border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none w-48">
        <select name="student_id" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">All Students</option>
            <?php foreach($students_list as $sl): ?><option value="<?= $sl['id'] ?>" <?= $sc_filter_student===$sl['id']?'selected':'' ?>><?= xss_clean($sl['student_name']) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold">Filter</button>
        <a href="?route=app&page=sc_payments" class="text-sm text-slate-500 hover:text-slate-700 font-bold">Reset</a>
    </form>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left">
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Student</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Payment Type</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Total</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Paid</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Due</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php if (empty($pmts)): ?>
                    <tr><td colspan="7" class="text-center py-12 text-slate-400"><i class="fa-solid fa-coins text-3xl mb-2 block"></i>No payment records.</td></tr>
                <?php else: foreach ($pmts as $pm): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3"><a href="?route=app&page=sc_students&id=<?= $pm['student_id'] ?>&tab=payments" class="font-bold text-indigo-700 hover:underline"><?= xss_clean($pm['student_name']) ?></a></td>
                        <td class="px-4 py-3 font-bold text-slate-700"><?= xss_clean($pm['payment_type']??'—') ?></td>
                        <td class="px-4 py-3 font-bold text-slate-800"><?= number_format($pm['total_amount'],2) ?></td>
                        <td class="px-4 py-3 font-bold text-emerald-600"><?= number_format($pm['paid_amount'],2) ?></td>
                        <td class="px-4 py-3 font-bold <?= $pm['due_amount']>0?'text-rose-500':'text-slate-400' ?>"><?= number_format($pm['due_amount'],2) ?></td>
                        <td class="px-4 py-3 text-xs text-slate-500"><?= $pm['payment_date'] ? date('d M Y',strtotime($pm['payment_date'])) : '—' ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <a href="?route=app&page=invoices" onclick="sessionStorage.setItem('sc_inv',JSON.stringify({customer_name:'<?= addslashes($pm['student_name']) ?>',mobile:'<?= $pm['mobile'] ?>',service_desc:'<?= addslashes($pm['payment_type']??'') ?>',grand_total:'<?= $pm['total_amount'] ?>',paid_amount:'<?= $pm['paid_amount'] ?>',due_amount:'<?= $pm['due_amount'] ?>'}));return true;"
                                   class="text-xs font-bold text-teal-600 bg-teal-50 hover:bg-teal-100 px-2.5 py-1.5 rounded-lg transition flex items-center gap-1"><i class="fa-solid fa-file-invoice text-xs"></i> Invoice</a>
                                <?php if (has_permission('can_manage_sc_payments') && !$_SESSION['is_staff']): ?>
                                <form method="POST" action="?route=app" class="inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="sc_delete_payment"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" value="<?= $pm['id'] ?>"><button class="text-xs font-bold text-rose-500 bg-rose-50 hover:bg-rose-100 px-2.5 py-1.5 rounded-lg transition">Del</button></form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Add Payment Modal -->
<div id="scPmtListModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800">Add Payment Record</h3><button onclick="document.getElementById('scPmtListModal').classList.add('hidden');document.getElementById('scPmtListModal').classList.remove('flex')" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="sc_save_payment"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Student *</label><select name="student_id" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select Student —</option><?php foreach($students_list as $sl): ?><option value="<?= $sl['id'] ?>"><?= xss_clean($sl['student_name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Payment Type</label><select name="payment_type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select —</option><?php foreach($paymentTypes as $pt): ?><option><?= xss_clean($pt) ?></option><?php endforeach; ?></select></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Total Amount</label><input type="number" step="0.01" name="total_amount" value="0" oninput="scPmtCalc()" id="scPmtTotal" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Paid Amount</label><input type="number" step="0.01" name="paid_amount" value="0" oninput="scPmtCalc()" id="scPmtPaid" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Due Amount (auto)</label><input type="number" step="0.01" name="due_amount" id="scPmtDue" value="0" readonly class="w-full border border-emerald-200 bg-emerald-50 rounded-xl px-3 py-2.5 text-sm font-bold text-emerald-700"></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Payment Date</label><input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Notes</label><textarea name="notes" rows="2" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"></textarea></div>
        <div class="flex gap-3"><button type="button" onclick="document.getElementById('scPmtListModal').classList.add('hidden');document.getElementById('scPmtListModal').classList.remove('flex')" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save</button></div>
    </form>
  </div>
</div>
<script>function scPmtCalc(){const t=parseFloat(document.getElementById('scPmtTotal').value)||0,p=parseFloat(document.getElementById('scPmtPaid').value)||0;document.getElementById('scPmtDue').value=Math.max(0,t-p).toFixed(2);}</script>
