<?php
// Payment Vouchers
$accFrom = normalizeReportDate($_GET['from_date']??null, date('Y-m-01'));
$accTo   = normalizeReportDate($_GET['to_date']??null, date('Y-m-d'));
if($accFrom>$accTo) [$accFrom,$accTo]=[$accTo,$accFrom];
$printId = trim($_GET['print'] ?? '');

$vouchers=$conn->prepare("SELECT v.*,s.full_name as staff_name FROM acc_vouchers v LEFT JOIN staff s ON v.created_by_staff_id=s.id WHERE v.agency_id=? AND v.voucher_type='payment' AND v.voucher_date BETWEEN ? AND ? ORDER BY v.voucher_date DESC, v.created_at DESC");
$vouchers->execute([$agency_id,$accFrom,$accTo]); $vouchers=$vouchers->fetchAll(PDO::FETCH_ASSOC);
$totalPV = array_sum(array_column($vouchers,'amount'));

// Print view
if($printId) {
    $pv=$conn->prepare("SELECT v.*,s.full_name as staff_name FROM acc_vouchers v LEFT JOIN staff s ON v.created_by_staff_id=s.id WHERE v.id=? AND v.agency_id=? AND v.voucher_type='payment'");
    $pv->execute([$printId,$agency_id]); $pv=$pv->fetch(PDO::FETCH_ASSOC);
}

$payees=$conn->query("SELECT DISTINCT party_name FROM acc_vouchers WHERE agency_id=$agency_id AND voucher_type='payment' ORDER BY party_name")->fetchAll(PDO::FETCH_COLUMN);
$pmMethods=['Cash','Bank Transfer','bKash','Nagad','Card','Cheque','Other'];
$vendorTypes=['Airline','Visa Partner','Hotel','Tour Operator','Office Vendor','Supplier','Staff','Other'];

// Agency info
$agencyInfo=$conn->query("SELECT * FROM agencies WHERE id=$agency_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>
<?php if($printId && $pv): ?>
<!-- ─── PRINT VIEW ─────────────────────────────────────────── -->
<div class="max-w-2xl mx-auto print-container">
<style>@media print{.no-print{display:none!important}body{background:white}}</style>
<div class="no-print mb-4 flex gap-2">
    <button onclick="window.print()" class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold"><i class="fa-solid fa-print mr-2"></i>Print Voucher</button>
    <a href="?route=app&page=acc_payment_vouchers&from_date=<?= $accFrom ?>&to_date=<?= $accTo ?>" class="bg-slate-100 text-slate-700 px-5 py-2.5 rounded-xl text-sm font-bold">← Back</a>
</div>
<div class="bg-white border-2 border-slate-200 rounded-2xl overflow-hidden">
    <div class="bg-indigo-700 text-white px-8 py-5 flex justify-between items-start">
        <div><p class="text-xs font-bold uppercase tracking-wider text-indigo-200 mb-1">Payment Voucher</p><h2 class="text-2xl font-black"><?= xss_clean($agencyInfo['agency_name']??'Agency') ?></h2><p class="text-indigo-200 text-sm mt-1"><?= xss_clean($agencyInfo['address']??'') ?></p></div>
        <div class="text-right"><p class="text-indigo-200 text-xs font-bold uppercase mb-1">Voucher No.</p><p class="text-xl font-black"><?= xss_clean($pv['voucher_number']) ?></p></div>
    </div>
    <div class="p-8 space-y-5">
        <div class="grid grid-cols-2 gap-6">
            <div><p class="text-xs font-bold text-slate-400 uppercase mb-1">Pay To</p><p class="text-lg font-extrabold text-slate-800"><?= xss_clean($pv['party_name']) ?></p><p class="text-sm text-slate-500"><?= xss_clean($pv['invoice_ref']??'') ?></p></div>
            <div class="text-right"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Date</p><p class="text-lg font-bold text-slate-800"><?= date('d F Y',strtotime($pv['voucher_date'])) ?></p><p class="text-sm text-slate-500">Method: <?= xss_clean($pv['payment_method']??'Cash') ?></p></div>
        </div>
        <div class="border border-slate-100 rounded-xl p-4 bg-slate-50"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Purpose / Description</p><p class="text-slate-700 font-semibold"><?= xss_clean($pv['description']??'') ?></p></div>
        <div class="border-2 border-indigo-100 rounded-xl p-5 bg-indigo-50 text-center"><p class="text-xs font-bold text-indigo-400 uppercase mb-1">Amount Paid</p><p class="text-4xl font-black text-indigo-700"><?= $currencySymbol ?> <?= number_format($pv['amount'],2) ?></p></div>
        <?php if($pv['notes']): ?><div><p class="text-xs font-bold text-slate-400 uppercase mb-1">Remarks</p><p class="text-slate-600 text-sm"><?= xss_clean($pv['notes']) ?></p></div><?php endif; ?>
        <div class="grid grid-cols-2 gap-6 pt-8 border-t border-slate-100">
            <div class="text-center"><div class="border-t-2 border-slate-300 pt-2 mt-8"><p class="text-xs font-bold text-slate-400">Prepared By</p><p class="font-bold text-slate-700"><?= xss_clean($pv['staff_name']??'—') ?></p></div></div>
            <div class="text-center"><div class="border-t-2 border-slate-300 pt-2 mt-8"><p class="text-xs font-bold text-slate-400">Authorized By</p><p class="font-bold text-slate-700"><?= xss_clean($agencyInfo['agency_name']??'') ?></p></div></div>
        </div>
    </div>
</div>
</div>

<?php else: ?>
<!-- ─── LIST VIEW ─────────────────────────────────────────── -->
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-money-check-dollar text-indigo-500"></i> Payment Vouchers</h2><p class="text-sm text-slate-500 mt-1">Generate and manage payment vouchers.</p></div>
    <?php if(has_permission('can_manage_acc_vouchers') || !$_SESSION['is_staff']): ?>
    <button onclick="document.getElementById('pvModal').classList.remove('hidden');document.getElementById('pvModal').classList.add('flex')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow flex items-center gap-2"><i class="fa-solid fa-plus"></i> New Voucher</button>
    <?php endif; ?>
</div>

<form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_payment_vouchers">
    <div><label class="block text-xs font-bold text-slate-500 mb-1">From</label><input type="date" name="from_date" value="<?= $accFrom ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <div><label class="block text-xs font-bold text-slate-500 mb-1">To</label><input type="date" name="to_date" value="<?= $accTo ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold self-end">Apply</button>
</form>

<div class="grid grid-cols-2 gap-4">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-rose-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total Paid Out</p><p class="text-2xl font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($totalPV,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Vouchers Count</p><p class="text-2xl font-black text-slate-700"><?= count($vouchers) ?></p></div>
</div>

<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
<div class="overflow-x-auto"><table class="w-full text-sm">
<thead class="bg-slate-50 border-b border-slate-100"><tr class="text-left">
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Voucher No.</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Date</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Pay To</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Description</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Method</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-rose-500">Amount</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Actions</th>
</tr></thead>
<tbody class="divide-y divide-slate-50">
<?php if(empty($vouchers)): ?>
<tr><td colspan="7" class="text-center py-12 text-slate-400"><i class="fa-solid fa-money-check-dollar text-3xl block mb-2"></i>No payment vouchers found.</td></tr>
<?php else: foreach($vouchers as $v): ?>
<tr class="hover:bg-slate-50 transition">
    <td class="px-4 py-3 font-mono font-bold text-indigo-700"><?= xss_clean($v['voucher_number']) ?></td>
    <td class="px-4 py-3 font-bold text-slate-700 whitespace-nowrap"><?= date('d M Y',strtotime($v['voucher_date'])) ?></td>
    <td class="px-4 py-3 font-bold text-slate-800"><?= xss_clean($v['party_name']) ?></td>
    <td class="px-4 py-3 text-slate-600 max-w-xs truncate"><?= xss_clean($v['description']??'') ?></td>
    <td class="px-4 py-3 text-sm text-slate-500"><?= xss_clean($v['payment_method']??'') ?></td>
    <td class="px-4 py-3 font-extrabold text-rose-600"><?= $currencySymbol ?> <?= number_format($v['amount'],2) ?></td>
    <td class="px-4 py-3">
        <a href="?route=app&page=acc_payment_vouchers&print=<?= $v['id'] ?>" class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1.5 rounded-lg hover:bg-indigo-100 transition">Print</a>
        <?php if(!$_SESSION['is_staff']): ?>
        <form method="POST" action="?route=app" class="inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_acc_voucher"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="ml-1 text-xs font-bold text-rose-500 bg-rose-50 px-2.5 py-1.5 rounded-lg hover:bg-rose-100 transition">Del</button></form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table></div>
</div>
</div>

<!-- New PV Modal -->
<div id="pvModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800">New Payment Voucher</h3><button onclick="document.getElementById('pvModal').classList.add('hidden');document.getElementById('pvModal').classList.remove('flex')" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="save_acc_voucher"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="voucher_type" value="payment">
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Date *</label><input type="date" name="voucher_date" value="<?= date('Y-m-d') ?>" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Payment Method</label><select name="payment_method" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><?php foreach($pmMethods as $pm): ?><option><?= $pm ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Pay To (Payee) *</label><input list="payeeList" type="text" name="party_name" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><datalist id="payeeList"><?php foreach($payees as $py): ?><option><?= xss_clean($py) ?></option><?php endforeach; ?></datalist></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Payee Type</label><select name="invoice_ref" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select —</option><?php foreach($vendorTypes as $vt): ?><option><?= $vt ?></option><?php endforeach; ?></select></div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Description / Purpose *</label><textarea name="description" rows="2" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"></textarea></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Amount *</label><input type="number" step="0.01" name="amount" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Remarks</label><input type="text" name="notes" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div class="flex gap-3"><button type="button" onclick="document.getElementById('pvModal').classList.add('hidden');document.getElementById('pvModal').classList.remove('flex')" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Create Voucher</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
