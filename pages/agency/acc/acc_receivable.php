<?php
// Accounts Receivable — from invoices
$filterStatus = $_GET['status'] ?? 'due'; // due | all | paid
$filterSearch = trim($_GET['q'] ?? '');

$whereClause = "agency_id=?"; $params = [$agency_id];
if($filterStatus === 'due')  { $whereClause .= " AND due_amount > 0"; }
if($filterStatus === 'paid') { $whereClause .= " AND due_amount = 0"; }
if($filterSearch) { $whereClause .= " AND (customer_name LIKE ? OR invoice_number LIKE ? OR mobile LIKE ?)"; $params=array_merge($params,["%$filterSearch%","%$filterSearch%","%$filterSearch%"]); }

$invoices=$conn->prepare("SELECT * FROM invoices WHERE $whereClause ORDER BY issue_date DESC, created_at DESC");
$invoices->execute($params); $invoices=$invoices->fetchAll(PDO::FETCH_ASSOC);

$totalAR       = $conn->query("SELECT SUM(grand_total) FROM invoices WHERE agency_id=$agency_id")->fetchColumn() ?: 0;
$totalReceived = $conn->query("SELECT SUM(paid_amount) FROM invoices WHERE agency_id=$agency_id")->fetchColumn() ?: 0;
$totalDue      = $conn->query("SELECT SUM(due_amount) FROM invoices WHERE agency_id=$agency_id AND due_amount > 0")->fetchColumn() ?: 0;
$overdueCount  = $conn->query("SELECT COUNT(*) FROM invoices WHERE agency_id=$agency_id AND due_amount > 0 AND issue_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

$waActive = !empty($conn->query("SELECT COUNT(*) FROM whatsapp_providers WHERE agency_id=$agency_id AND is_active=1")->fetchColumn());
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-hand-holding-dollar text-indigo-500"></i> Accounts Receivable</h2><p class="text-sm text-slate-500 mt-1">Customer dues from invoices.</p></div>
    <a href="/app/invoices" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow flex items-center gap-2"><i class="fa-solid fa-file-invoice-dollar"></i> Create Invoice</a>
</div>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total Billed</p><p class="text-2xl font-black text-slate-700"><?= $currencySymbol ?> <?= number_format($totalAR,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-emerald-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Received</p><p class="text-2xl font-black text-emerald-600"><?= $currencySymbol ?> <?= number_format($totalReceived,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-rose-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Outstanding</p><p class="text-2xl font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($totalDue,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-amber-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Overdue (30d+)</p><p class="text-2xl font-black text-amber-600"><?= $overdueCount ?></p></div>
</div>

<form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-center">
    <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_receivable">
    <input type="text" name="q" value="<?= xss_clean($filterSearch) ?>" placeholder="Search customer, invoice…" class="border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none w-52">
    <div class="flex gap-2">
        <?php foreach(['all'=>'All','due'=>'Outstanding','paid'=>'Paid'] as $k=>$l): ?>
        <a href="/app/acc_receivable?status=<?= $k ?>&q=<?= urlencode($filterSearch) ?>"
           class="px-3 py-2 rounded-xl text-sm font-bold transition <?= $filterStatus===$k?'bg-indigo-600 text-white':'bg-slate-100 text-slate-600 hover:bg-indigo-50' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
</form>

<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
<div class="overflow-x-auto"><table class="w-full text-sm">
<thead class="bg-slate-50 border-b border-slate-100"><tr class="text-left">
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Invoice #</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Customer</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Service</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Date</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Total</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-emerald-500">Paid</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-rose-500">Due</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Actions</th>
</tr></thead>
<tbody class="divide-y divide-slate-50">
<?php if(empty($invoices)): ?>
<tr><td colspan="8" class="text-center py-12 text-slate-400"><i class="fa-solid fa-hand-holding-dollar text-3xl block mb-2"></i>No invoices found.</td></tr>
<?php else: foreach($invoices as $inv):
    $isOverdue = ($inv['due_amount']>0 && $inv['issue_date'] < date('Y-m-d',strtotime('-30 days')));
?>
<tr class="hover:bg-slate-50 transition <?= $isOverdue?'bg-rose-50/30':'' ?>">
    <td class="px-4 py-3 font-mono font-bold text-indigo-700"><?= xss_clean($inv['invoice_number']) ?></td>
    <td class="px-4 py-3"><p class="font-bold text-slate-800"><?= xss_clean($inv['customer_name']) ?></p><p class="text-xs text-slate-400"><?= xss_clean($inv['mobile']??'') ?></p></td>
    <td class="px-4 py-3 text-sm text-slate-600 max-w-xs truncate"><?= xss_clean($inv['service_desc']??'') ?></td>
    <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap"><?= date('d M Y',strtotime($inv['issue_date'])) ?><?php if($isOverdue): ?> <span class="text-xs text-rose-500 font-bold">Overdue</span><?php endif; ?></td>
    <td class="px-4 py-3 font-bold text-slate-700"><?= $currencySymbol ?> <?= number_format($inv['grand_total'],2) ?></td>
    <td class="px-4 py-3 font-bold text-emerald-600"><?= $currencySymbol ?> <?= number_format($inv['paid_amount'],2) ?></td>
    <td class="px-4 py-3 font-bold <?= $inv['due_amount']>0?'text-rose-600':'text-slate-400' ?>"><?= $currencySymbol ?> <?= number_format($inv['due_amount'],2) ?></td>
    <td class="px-4 py-3">
        <a href="/app/invoices" class="text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1.5 rounded-lg transition inline-block">View</a>
        <?php if($inv['due_amount']>0 && $waActive && !$_SESSION['is_staff']): ?>
        <a href="/app/whatsapp?prefill_msg=<?= urlencode("Dear ".$inv['customer_name'].", your invoice ".$inv['invoice_number']." has an outstanding due of {$currencySymbol} ".$inv['due_amount'].". Please arrange payment at your earliest. Thank you.") ?>" class="ml-1 text-xs font-bold text-emerald-600 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1.5 rounded-lg transition inline-block"><i class="fa-brands fa-whatsapp"></i> Remind</a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table></div>
</div>
</div>
