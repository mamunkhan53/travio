<?php
// Enhanced Expenses — wraps existing accounting_expenses with new fields
$accFrom = normalizeReportDate($_GET['from_date']??null, date('Y-m-01'));
$accTo   = normalizeReportDate($_GET['to_date']??null, date('Y-m-d'));
if($accFrom>$accTo) [$accFrom,$accTo]=[$accTo,$accFrom];
$filterCat = trim($_GET['category'] ?? '');

// Load expense categories
$expCatSetting = (function() use($conn,$agency_id){ $s=$conn->prepare("SELECT setting_value FROM acc_settings WHERE agency_id=? AND setting_key='expense_categories'"); $s->execute([$agency_id]); $v=$s->fetchColumn(); return $v ? array_map('trim',explode("\n",$v)) : []; })();
$defaultExpCats = ['Office Rent','Staff Salary','Utility Bill','Internet','Electricity','Marketing','Office Supplies','Bank Charges','Transport','Maintenance','Printing & Stationery','Miscellaneous'];
$expCats = !empty($expCatSetting) ? $expCatSetting : $defaultExpCats;
$pmMethods = ['Cash','Bank Transfer','bKash','Nagad','Card','Cheque','Other'];

$whereClause = "e.agency_id=? AND e.expense_date BETWEEN ? AND ?";
$params = [$agency_id,$accFrom,$accTo];
if($filterCat){ $whereClause.=" AND e.category=?"; $params[]=$filterCat; }

$exps=$conn->prepare("SELECT e.*,s.full_name as staff_name FROM accounting_expenses e LEFT JOIN staff s ON e.created_by_staff_id=s.id WHERE $whereClause ORDER BY e.expense_date DESC, e.id DESC");
$exps->execute($params); $exps=$exps->fetchAll(PDO::FETCH_ASSOC);
$totalExp = array_sum(array_column($exps,'amount'));

// Category breakdown
$catBreakdown = $conn->query("SELECT category, SUM(amount) as total, COUNT(*) as cnt FROM accounting_expenses WHERE agency_id=$agency_id AND expense_date BETWEEN '$accFrom' AND '$accTo' GROUP BY category ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);

$accCanAdd = has_permission('can_add_expense');
$accCanEdit= has_permission('can_edit_expense');
$accCanDel = has_permission('can_delete_expense');
$accRedirectQs = "from_date=$accFrom&to_date=$accTo";
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-receipt text-indigo-500"></i> Expenses</h2><p class="text-sm text-slate-500 mt-1">Track all agency expenses by category and vendor.</p></div>
    <?php if($accCanAdd): ?>
    <button onclick="openExpModal('add')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Expense</button>
    <?php endif; ?>
</div>

<form method="GET" action="/app/acc_expenses" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-end">
    <div><label class="block text-xs font-bold text-slate-500 mb-1">From</label><input type="date" name="from_date" value="<?= $accFrom ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <div><label class="block text-xs font-bold text-slate-500 mb-1">To</label><input type="date" name="to_date" value="<?= $accTo ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <div><label class="block text-xs font-bold text-slate-500 mb-1">Category</label>
        <select name="category" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">All Categories</option>
            <?php foreach($expCats as $c): ?><option value="<?= xss_clean($c) ?>" <?= $filterCat===$c?'selected':'' ?>><?= xss_clean($c) ?></option><?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold self-end">Apply</button>
</form>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-rose-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total Expenses</p><p class="text-2xl font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($totalExp,2) ?></p><p class="text-xs text-slate-400"><?= count($exps) ?> records</p></div>
    <?php if(!empty($catBreakdown[0])): ?>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Top Category</p><p class="text-lg font-black text-slate-800"><?= xss_clean($catBreakdown[0]['category']??'—') ?></p><p class="text-sm font-bold text-rose-500"><?= $currencySymbol ?> <?= number_format($catBreakdown[0]['total']??0,2) ?></p></div>
    <?php endif; ?>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Categories Used</p><p class="text-2xl font-black text-indigo-600"><?= count($catBreakdown) ?></p></div>
</div>

<?php if(!empty($catBreakdown) && !$filterCat): ?>
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
    <h3 class="font-extrabold text-slate-700 mb-3">Breakdown by Category</h3>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    <?php foreach(array_slice($catBreakdown,0,8) as $cb): ?>
    <div class="bg-slate-50 rounded-xl p-3"><p class="text-xs font-bold text-slate-500 mb-1 truncate"><?= xss_clean($cb['category']) ?></p><p class="font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($cb['total'],2) ?></p><p class="text-xs text-slate-400"><?= $cb['cnt'] ?> records</p></div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
<div class="overflow-x-auto"><table class="w-full text-sm">
<thead class="bg-slate-50 border-b border-slate-100"><tr class="text-left">
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Date</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Category</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Title</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Vendor</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Method</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-rose-500">Amount</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Staff</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Actions</th>
</tr></thead>
<tbody class="divide-y divide-slate-50">
<?php if(empty($exps)): ?>
<tr><td colspan="8" class="text-center py-12 text-slate-400"><i class="fa-solid fa-receipt text-3xl block mb-2"></i>No expenses in this period.</td></tr>
<?php else: foreach($exps as $ex): ?>
<tr class="hover:bg-slate-50 transition">
    <td class="px-4 py-3 font-bold text-slate-700 whitespace-nowrap"><?= date('d M Y',strtotime($ex['expense_date'])) ?></td>
    <td class="px-4 py-3"><span class="text-xs font-bold px-2.5 py-1 rounded-full bg-rose-100 text-rose-700"><?= xss_clean($ex['category']??'—') ?></span></td>
    <td class="px-4 py-3 font-bold text-slate-800"><?= xss_clean($ex['title']??'') ?></td>
    <td class="px-4 py-3 text-sm text-slate-600"><?= xss_clean($ex['vendor']??'—') ?></td>
    <td class="px-4 py-3 text-sm text-slate-500"><?= xss_clean($ex['payment_method']??'—') ?></td>
    <td class="px-4 py-3 font-extrabold text-rose-600"><?= $currencySymbol ?> <?= number_format($ex['amount'],2) ?></td>
    <td class="px-4 py-3 text-xs text-slate-500"><?= xss_clean($ex['staff_name']??'—') ?></td>
    <td class="px-4 py-3">
        <?php if($accCanEdit): ?><button onclick="openExpModal('edit','<?= rawurlencode(json_encode($ex)) ?>')" class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1.5 rounded-lg hover:bg-indigo-100 transition">Edit</button><?php endif; ?>
        <?php if($accCanDel): ?><a href="/app?action=delete_expense&id=<?= urlencode($ex['id']) ?>&redirect_qs=<?= urlencode('from_date='.$accFrom.'&to_date='.$accTo) ?>" onclick="return confirm('Delete?')" class="ml-1 text-xs font-bold text-rose-500 bg-rose-50 px-2.5 py-1.5 rounded-lg hover:bg-rose-100 transition inline-block">Del</a><?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
<tfoot class="bg-slate-50 border-t-2 border-slate-200">
<tr><td colspan="5" class="px-4 py-3 font-extrabold text-right text-slate-800">TOTAL</td><td class="px-4 py-3 font-extrabold text-rose-600"><?= $currencySymbol ?> <?= number_format($totalExp,2) ?></td><td colspan="2"></td></tr>
</tfoot>
</table></div>
</div>
</div>

<!-- Reuse existing expense modal (from accounting.php) — same action=save_expense -->
<div id="expModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-y-auto max-h-[90vh]">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50 sticky top-0 z-10">
            <h3 class="font-extrabold text-slate-800 text-lg flex items-center gap-2" id="expModalTitle"><i class="fa-solid fa-receipt text-rose-500"></i> Add Expense</h3>
            <button onclick="closeExpModal()" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center transition"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" action="" class="p-6 space-y-4" id="expForm">
            <input type="hidden" name="action" value="save_expense">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="expense_id" id="exp_id" value="">
            <input type="hidden" name="redirect_qs" value="from_date=<?= $accFrom ?>&to_date=<?= $accTo ?>">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-bold text-slate-700 mb-2">Date</label><input type="date" name="expense_date" id="exp_date" required value="<?= date('Y-m-d') ?>" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                <div><label class="block text-sm font-bold text-slate-700 mb-2">Category</label><input type="text" list="expCategoryList" name="category" id="exp_category" required placeholder="e.g. Office Rent" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none"><datalist id="expCategoryList"><?php foreach($expCats as $cat): ?><option value="<?= xss_clean($cat) ?>"><?php endforeach; ?></datalist></div>
            </div>
            <div><label class="block text-sm font-bold text-slate-700 mb-2">Title</label><input type="text" name="title" id="exp_title" required placeholder="e.g. July Office Rent" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-bold text-slate-700 mb-2">Vendor / Payee</label><input type="text" name="vendor" id="exp_vendor" placeholder="e.g. ABC Suppliers" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none"></div>
                <div><label class="block text-sm font-bold text-slate-700 mb-2">Amount</label><input type="number" step="0.01" min="0" name="amount" id="exp_amount" required placeholder="0.00" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none"></div>
            </div>
            <div><label class="block text-sm font-bold text-slate-700 mb-2">Payment Method</label>
                <select name="payment_method" id="exp_method" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
                    <?php foreach($pmMethods as $pm): ?><option><?= $pm ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-sm font-bold text-slate-700 mb-2">Remarks / Notes</label><textarea name="remarks" id="exp_remarks" rows="2" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none resize-none"></textarea></div>
            <div class="flex gap-3"><button type="button" onclick="closeExpModal()" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-rose-600 hover:bg-rose-700 text-white py-3 rounded-xl text-sm font-bold shadow">Save Expense</button></div>
        </form>
    </div>
</div>
<script>
function openExpModal(mode,dataRaw){
    const m=document.getElementById('expModal');
    document.getElementById('expModalTitle').innerHTML=(mode==='add'?'<i class="fa-solid fa-receipt text-rose-500"></i> Add Expense':'<i class="fa-solid fa-pen text-indigo-500"></i> Edit Expense');
    document.getElementById('exp_id').value='';['exp_date','exp_category','exp_title','exp_vendor','exp_amount','exp_remarks'].forEach(i=>{const el=document.getElementById(i);if(el){if(i==='exp_date')el.value='<?= date('Y-m-d') ?>';else el.value='';}});
    document.getElementById('exp_method').value='Cash';
    if(mode==='edit'&&dataRaw){
        const d=JSON.parse(decodeURIComponent(dataRaw));
        document.getElementById('exp_id').value=d.id||'';
        document.getElementById('exp_date').value=d.expense_date||'';
        document.getElementById('exp_category').value=d.category||'';
        document.getElementById('exp_title').value=d.title||'';
        document.getElementById('exp_vendor').value=d.vendor||'';
        document.getElementById('exp_amount').value=d.amount||'';
        document.getElementById('exp_method').value=d.payment_method||'Cash';
        document.getElementById('exp_remarks').value=d.remarks||'';
    }
    m.classList.remove('hidden');m.classList.add('flex');
}
function closeExpModal(){document.getElementById('expModal').classList.add('hidden');document.getElementById('expModal').classList.remove('flex');}
</script>
