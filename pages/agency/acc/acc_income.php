<?php
$accFrom = normalizeReportDate($_GET['from_date']??null, date('Y-m-01'));
$accTo   = normalizeReportDate($_GET['to_date']??null, date('Y-m-d'));
if($accFrom>$accTo) [$accFrom,$accTo]=[$accTo,$accFrom];
$filterCat = trim($_GET['category'] ?? '');

// Load income categories from settings
$incCatSetting = (function() use($conn,$agency_id){ $s=$conn->prepare("SELECT setting_value FROM acc_settings WHERE agency_id=? AND setting_key='income_categories'"); $s->execute([$agency_id]); $v=$s->fetchColumn(); return $v ? array_map('trim',explode("\n",$v)) : []; })();
$defaultIncCats = ['Air Ticket','Visa Processing','Student Consultancy','Hotel Booking','Umrah Package','Tour Package','Service Charge','Consultancy Fee','Other Income'];
$incCats = !empty($incCatSetting) ? $incCatSetting : $defaultIncCats;

$completedStatuses = "'Completed','Paid','Confirmed'";

// Sales income (auto)
$salesIncome = [];
$salesTotal  = 0;
foreach(['passports'=>'Passport Processing','visas'=>'Visa Processing','tickets'=>'Air Ticket','umrah'=>'Umrah Package','tours'=>'Tour Package'] as $tbl=>$cat) {
    $s=$conn->prepare("SELECT SUM(selling_price-service_cost) FROM $tbl WHERE agency_id=? AND status IN ($completedStatuses) AND transaction_date BETWEEN ? AND ?");
    $s->execute([$agency_id,$accFrom,$accTo]); $amt=(float)$s->fetchColumn();
    $salesIncome[$cat]=$amt; $salesTotal+=$amt;
}

// Manual income
$whereClause = "agency_id=? AND income_date BETWEEN ? AND ?";
$params = [$agency_id,$accFrom,$accTo];
if($filterCat){ $whereClause.=" AND category=?"; $params[]=$filterCat; }
$manualIncome=$conn->prepare("SELECT * FROM acc_income WHERE $whereClause ORDER BY income_date DESC, created_at DESC");
$manualIncome->execute($params); $manualIncome=$manualIncome->fetchAll(PDO::FETCH_ASSOC);
$manualTotal = array_sum(array_column($manualIncome,'amount'));

$grandTotal = $salesTotal + $manualTotal;
$pmMethods  = ['Cash','Bank Transfer','bKash','Nagad','Card','Cheque','Other'];
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-circle-dollar-to-slot text-indigo-500"></i> Income</h2><p class="text-sm text-slate-500 mt-1">Sales income (auto) + manual income records.</p></div>
    <?php if(has_permission('can_manage_acc_income') || !$_SESSION['is_staff']): ?>
    <button onclick="document.getElementById('accIncomeModal').classList.remove('hidden');document.getElementById('accIncomeModal').classList.add('flex')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Income</button>
    <?php endif; ?>
</div>

<form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_income">
    <div><label class="block text-xs font-bold text-slate-500 mb-1">From</label><input type="date" name="from_date" value="<?= $accFrom ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <div><label class="block text-xs font-bold text-slate-500 mb-1">To</label><input type="date" name="to_date" value="<?= $accTo ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <div><label class="block text-xs font-bold text-slate-500 mb-1">Category</label>
        <select name="category" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">All Categories</option>
            <?php foreach($incCats as $c): ?><option value="<?= xss_clean($c) ?>" <?= $filterCat===$c?'selected':'' ?>><?= xss_clean($c) ?></option><?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold self-end">Apply</button>
</form>

<!-- Summary -->
<div class="grid grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-emerald-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Sales Income</p><p class="text-2xl font-black text-emerald-600"><?= $currencySymbol ?> <?= number_format($salesTotal,2) ?></p><p class="text-xs text-slate-400">Auto from completed sales</p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-blue-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Manual Income</p><p class="text-2xl font-black text-blue-600"><?= $currencySymbol ?> <?= number_format($manualTotal,2) ?></p><p class="text-xs text-slate-400"><?= count($manualIncome) ?> records</p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-indigo-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total Income</p><p class="text-2xl font-black text-indigo-600"><?= $currencySymbol ?> <?= number_format($grandTotal,2) ?></p></div>
</div>

<!-- Sales breakdown -->
<?php if(!$filterCat): ?>
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
    <h3 class="font-extrabold text-slate-700 mb-3 text-sm uppercase tracking-wider">Auto Sales Breakdown</h3>
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
    <?php foreach($salesIncome as $cat=>$amt): ?>
    <div class="bg-slate-50 rounded-xl p-3 text-center"><p class="text-xs font-bold text-slate-500 mb-1"><?= $cat ?></p><p class="text-lg font-black text-emerald-600"><?= $currencySymbol ?> <?= number_format($amt,2) ?></p></div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Manual income table -->
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
<div class="p-4 border-b border-slate-100"><h3 class="font-extrabold text-slate-800">Manual Income Records</h3></div>
<div class="overflow-x-auto"><table class="w-full text-sm">
<thead class="bg-slate-50 border-b border-slate-100"><tr class="text-left">
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Date</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Category</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Description</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Customer</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Method</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-emerald-500">Amount</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Actions</th>
</tr></thead>
<tbody class="divide-y divide-slate-50">
<?php if(empty($manualIncome)): ?>
<tr><td colspan="7" class="text-center py-10 text-slate-400"><i class="fa-solid fa-circle-dollar-to-slot text-3xl block mb-2"></i>No manual income records for this period.</td></tr>
<?php else: foreach($manualIncome as $inc): ?>
<tr class="hover:bg-slate-50 transition">
    <td class="px-4 py-3 font-bold text-slate-700 whitespace-nowrap"><?= date('d M Y',strtotime($inc['income_date'])) ?></td>
    <td class="px-4 py-3"><span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700"><?= xss_clean($inc['category']??'Income') ?></span></td>
    <td class="px-4 py-3 text-slate-600"><?= xss_clean($inc['description']??'') ?></td>
    <td class="px-4 py-3 text-sm text-slate-600"><?= xss_clean($inc['customer_name']??'—') ?></td>
    <td class="px-4 py-3 text-sm text-slate-500"><?= xss_clean($inc['payment_method']??'—') ?></td>
    <td class="px-4 py-3 font-extrabold text-emerald-600"><?= $currencySymbol ?> <?= number_format($inc['amount'],2) ?></td>
    <td class="px-4 py-3">
        <button onclick='editIncomeRecord(<?= htmlspecialchars(json_encode($inc),ENT_QUOTES) ?>)' class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1.5 rounded-lg hover:bg-indigo-100 transition">Edit</button>
        <form method="POST" action="" class="inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_acc_income"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" value="<?= $inc['id'] ?>"><button class="ml-1 text-xs font-bold text-rose-500 bg-rose-50 px-2.5 py-1.5 rounded-lg hover:bg-rose-100 transition">Del</button></form>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table></div>
</div>
</div>

<div id="accIncomeModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800" id="accIncomeTitle">Add Income</h3><button onclick="closeIncomeModal()" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="" enctype="multipart/form-data" class="p-6 space-y-4">
        <input type="hidden" name="action" value="save_acc_income"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="income_id" id="inc_id">
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Date *</label><input type="date" name="income_date" id="inc_date" value="<?= date('Y-m-d') ?>" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Category</label><input list="incCatList" name="category" id="inc_cat" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><datalist id="incCatList"><?php foreach($incCats as $c): ?><option><?= xss_clean($c) ?></option><?php endforeach; ?></datalist></div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Description</label><input type="text" name="description" id="inc_desc" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Amount *</label><input type="number" step="0.01" name="amount" id="inc_amount" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Payment Method</label><select name="payment_method" id="inc_method" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select —</option><?php foreach($pmMethods as $pm): ?><option><?= $pm ?></option><?php endforeach; ?></select></div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Customer Name</label><input type="text" name="customer_name" id="inc_customer" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Attachment</label><input type="file" name="attachment" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm"></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Notes</label><textarea name="notes" id="inc_notes" rows="2" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"></textarea></div>
        <div class="flex gap-3"><button type="button" onclick="closeIncomeModal()" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save Income</button></div>
    </form>
  </div>
</div>
<script>
function editIncomeRecord(d){
    document.getElementById('accIncomeTitle').textContent='Edit Income';
    document.getElementById('inc_id').value=d.id;document.getElementById('inc_date').value=d.income_date;document.getElementById('inc_cat').value=d.category||'';document.getElementById('inc_desc').value=d.description||'';document.getElementById('inc_amount').value=d.amount;document.getElementById('inc_method').value=d.payment_method||'';document.getElementById('inc_customer').value=d.customer_name||'';document.getElementById('inc_notes').value=d.notes||'';
    document.getElementById('accIncomeModal').classList.remove('hidden');document.getElementById('accIncomeModal').classList.add('flex');
}
function closeIncomeModal(){document.getElementById('accIncomeModal').classList.add('hidden');document.getElementById('accIncomeModal').classList.remove('flex');document.getElementById('accIncomeTitle').textContent='Add Income';document.getElementById('inc_id').value='';}
</script>
