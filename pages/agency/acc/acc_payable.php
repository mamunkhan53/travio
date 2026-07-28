<?php
$filterStatus = $_GET['status'] ?? 'all';
$filterSearch = trim($_GET['q'] ?? '');
$whereClause  = "p.agency_id=?"; $params = [$agency_id];
if($filterStatus==='unpaid') { $whereClause.=" AND p.status!='Paid'"; }
if($filterStatus==='paid')   { $whereClause.=" AND p.status='Paid'"; }
if($filterStatus==='overdue'){ $whereClause.=" AND p.status!='Paid' AND p.due_date < CURDATE()"; }
if($filterSearch){ $whereClause.=" AND (p.vendor_name LIKE ? OR p.description LIKE ?)"; $params=array_merge($params,["%$filterSearch%","%$filterSearch%"]); }

$payables=$conn->prepare("SELECT p.*,s.full_name as staff_name FROM acc_payables p LEFT JOIN staff s ON p.created_by_staff_id=s.id WHERE $whereClause ORDER BY p.due_date ASC, p.created_at DESC");
$payables->execute($params); $payables=$payables->fetchAll(PDO::FETCH_ASSOC);

$totalDue  = $conn->query("SELECT SUM(due_amount) FROM acc_payables WHERE agency_id=$agency_id AND status!='Paid'")->fetchColumn()?:0;
$totalPaid = $conn->query("SELECT SUM(paid_amount) FROM acc_payables WHERE agency_id=$agency_id")->fetchColumn()?:0;
$overdueAP = $conn->query("SELECT COUNT(*) FROM acc_payables WHERE agency_id=$agency_id AND status!='Paid' AND due_date < CURDATE()")->fetchColumn();
$totalAP   = $conn->query("SELECT SUM(total_amount) FROM acc_payables WHERE agency_id=$agency_id")->fetchColumn()?:0;
$statusColors=['Unpaid'=>'bg-rose-100 text-rose-700','Partial'=>'bg-amber-100 text-amber-700','Paid'=>'bg-emerald-100 text-emerald-700'];
$vendorTypes=['Airline','Visa Partner','Hotel','Tour Operator','Office Vendor','Supplier','Service Provider','Other'];
$pmMethods=['Cash','Bank Transfer','bKash','Nagad','Card','Cheque','Other'];
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-file-invoice text-indigo-500"></i> Accounts Payable</h2><p class="text-sm text-slate-500 mt-1">Supplier and vendor payments tracking.</p></div>
    <?php if(has_permission('can_manage_acc_payable') || !$_SESSION['is_staff']): ?>
    <button onclick="document.getElementById('accPayableModal').classList.remove('hidden');document.getElementById('accPayableModal').classList.add('flex')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Payable</button>
    <?php endif; ?>
</div>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total Payable</p><p class="text-2xl font-black text-slate-700"><?= $currencySymbol ?> <?= number_format($totalAP,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-emerald-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Paid</p><p class="text-2xl font-black text-emerald-600"><?= $currencySymbol ?> <?= number_format($totalPaid,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-rose-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Outstanding</p><p class="text-2xl font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($totalDue,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-amber-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Overdue</p><p class="text-2xl font-black text-amber-600"><?= $overdueAP ?></p></div>
</div>

<form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-center">
    <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_payable">
    <input type="text" name="q" value="<?= xss_clean($filterSearch) ?>" placeholder="Search vendor…" class="border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none w-48">
    <div class="flex gap-2">
        <?php foreach(['all'=>'All','unpaid'=>'Unpaid','overdue'=>'Overdue','paid'=>'Paid'] as $k=>$l): ?>
        <a href="?route=app&page=acc_payable&status=<?= $k ?>&q=<?= urlencode($filterSearch) ?>" class="px-3 py-2 rounded-xl text-sm font-bold transition <?= $filterStatus===$k?'bg-indigo-600 text-white':'bg-slate-100 text-slate-600 hover:bg-indigo-50' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
</form>

<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
<div class="overflow-x-auto"><table class="w-full text-sm">
<thead class="bg-slate-50 border-b border-slate-100"><tr class="text-left">
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Vendor</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Type</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Description</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Due Date</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Total</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-emerald-500">Paid</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-rose-500">Due</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Status</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Actions</th>
</tr></thead>
<tbody class="divide-y divide-slate-50">
<?php if(empty($payables)): ?>
<tr><td colspan="9" class="text-center py-12 text-slate-400"><i class="fa-solid fa-file-invoice text-3xl block mb-2"></i>No payable records found.</td></tr>
<?php else: foreach($payables as $p):
    $isOverdue = ($p['status']!=='Paid' && !empty($p['due_date']) && $p['due_date'] < date('Y-m-d'));
?>
<tr class="hover:bg-slate-50 transition <?= $isOverdue?'bg-rose-50/30':'' ?>">
    <td class="px-4 py-3"><p class="font-bold text-slate-800"><?= xss_clean($p['vendor_name']) ?></p><p class="text-xs text-slate-400"><?= xss_clean($p['invoice_ref']??'') ?></p></td>
    <td class="px-4 py-3 text-sm text-slate-600"><?= xss_clean($p['vendor_type']??'—') ?></td>
    <td class="px-4 py-3 text-sm text-slate-600 max-w-xs truncate"><?= xss_clean($p['description']??'') ?></td>
    <td class="px-4 py-3 whitespace-nowrap text-sm <?= $isOverdue?'text-rose-600 font-bold':'text-slate-600' ?>"><?= $p['due_date'] ? date('d M Y',strtotime($p['due_date'])) : '—' ?><?php if($isOverdue): ?> <span class="text-xs bg-rose-100 text-rose-600 px-1 py-0.5 rounded font-bold">Overdue</span><?php endif; ?></td>
    <td class="px-4 py-3 font-bold text-slate-700"><?= $currencySymbol ?> <?= number_format($p['total_amount'],2) ?></td>
    <td class="px-4 py-3 font-bold text-emerald-600"><?= $currencySymbol ?> <?= number_format($p['paid_amount'],2) ?></td>
    <td class="px-4 py-3 font-bold <?= $p['due_amount']>0?'text-rose-600':'text-slate-400' ?>"><?= $currencySymbol ?> <?= number_format($p['due_amount'],2) ?></td>
    <td class="px-4 py-3"><span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $statusColors[$p['status']]??'bg-slate-100 text-slate-600' ?>"><?= xss_clean($p['status']) ?></span></td>
    <td class="px-4 py-3">
        <button onclick='editPayable(<?= htmlspecialchars(json_encode($p),ENT_QUOTES) ?>)' class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1.5 rounded-lg hover:bg-indigo-100 transition">Edit</button>
        <?php if(!$_SESSION['is_staff']): ?>
        <form method="POST" action="?route=app" class="inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_acc_payable"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="ml-1 text-xs font-bold text-rose-500 bg-rose-50 px-2.5 py-1.5 rounded-lg hover:bg-rose-100 transition">Del</button></form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table></div>
</div>
</div>

<div id="accPayableModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800" id="apModalTitle">Add Payable</h3><button onclick="closePayableModal()" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="save_acc_payable"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="payable_id" id="ap_id">
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">Vendor Name *</label><input type="text" name="vendor_name" id="ap_vendor" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Vendor Type</label><select name="vendor_type" id="ap_vtype" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select —</option><?php foreach($vendorTypes as $vt): ?><option><?= $vt ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Invoice Ref</label><input type="text" name="invoice_ref" id="ap_ref" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Description</label><input type="text" name="description" id="ap_desc" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div class="grid grid-cols-3 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Total Amount</label><input type="number" step="0.01" name="total_amount" id="ap_total" value="0" oninput="calcAPDue()" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Paid Amount</label><input type="number" step="0.01" name="paid_amount" id="ap_paid" value="0" oninput="calcAPDue()" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Due (auto)</label><input type="number" step="0.01" name="due_amount" id="ap_due" value="0" readonly class="w-full border border-emerald-200 bg-emerald-50 rounded-xl px-3 py-2.5 text-sm font-bold text-emerald-700"></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Due Date</label><input type="date" name="due_date" id="ap_due_date" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Status</label><select name="status" id="ap_status" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option>Unpaid</option><option>Partial</option><option>Paid</option></select></div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Notes</label><textarea name="notes" id="ap_notes" rows="2" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"></textarea></div>
        <div class="flex gap-3"><button type="button" onclick="closePayableModal()" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save</button></div>
    </form>
  </div>
</div>
<script>
function calcAPDue(){const t=parseFloat(document.getElementById('ap_total').value)||0,p=parseFloat(document.getElementById('ap_paid').value)||0;document.getElementById('ap_due').value=Math.max(0,t-p).toFixed(2);}
function editPayable(d){document.getElementById('apModalTitle').textContent='Edit Payable';document.getElementById('ap_id').value=d.id;document.getElementById('ap_vendor').value=d.vendor_name||'';document.getElementById('ap_vtype').value=d.vendor_type||'';document.getElementById('ap_ref').value=d.invoice_ref||'';document.getElementById('ap_desc').value=d.description||'';document.getElementById('ap_total').value=d.total_amount||0;document.getElementById('ap_paid').value=d.paid_amount||0;document.getElementById('ap_due').value=d.due_amount||0;document.getElementById('ap_due_date').value=d.due_date||'';document.getElementById('ap_status').value=d.status||'Unpaid';document.getElementById('ap_notes').value=d.notes||'';document.getElementById('accPayableModal').classList.remove('hidden');document.getElementById('accPayableModal').classList.add('flex');}
function closePayableModal(){document.getElementById('accPayableModal').classList.add('hidden');document.getElementById('accPayableModal').classList.remove('flex');document.getElementById('apModalTitle').textContent='Add Payable';document.getElementById('ap_id').value='';}
</script>
