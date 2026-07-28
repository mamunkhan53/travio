<?php
// Cash Book
$accFrom = normalizeReportDate($_GET['from_date']??null, date('Y-m-01'));
$accTo   = normalizeReportDate($_GET['to_date']??null, date('Y-m-d'));
if($accFrom>$accTo)[$accFrom,$accTo]=[$accTo,$accFrom];

$openingBalance = (float)(function() use($conn,$agency_id){
    $s=$conn->prepare("SELECT setting_value FROM acc_settings WHERE agency_id=? AND setting_key='opening_cash_balance'");
    $s->execute([$agency_id]); return $s->fetchColumn()?:0;
})();

$txns = $conn->prepare("SELECT ct.*, st.full_name as staff_name FROM acc_cash_transactions ct LEFT JOIN staff st ON ct.created_by_staff_id=st.id WHERE ct.agency_id=? AND ct.transaction_date BETWEEN ? AND ? ORDER BY ct.transaction_date ASC, ct.created_at ASC");
$txns->execute([$agency_id,$accFrom,$accTo]); $txns=$txns->fetchAll(PDO::FETCH_ASSOC);

$cashIn = (float)$conn->query("SELECT SUM(amount) FROM acc_cash_transactions WHERE agency_id=$agency_id AND transaction_type='in' AND transaction_date BETWEEN '$accFrom' AND '$accTo'")->fetchColumn();
$cashOut= (float)$conn->query("SELECT SUM(amount) FROM acc_cash_transactions WHERE agency_id=$agency_id AND transaction_type='out' AND transaction_date BETWEEN '$accFrom' AND '$accTo'")->fetchColumn();

$runBal = $openingBalance;
foreach($txns as &$t){ $runBal += $t['transaction_type']==='in' ? $t['amount'] : -$t['amount']; $t['balance']=$runBal; }
unset($t);
$closingBalance = $openingBalance + $cashIn - $cashOut;
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-money-bill-wave text-indigo-500"></i> Cash Book</h2><p class="text-sm text-slate-500 mt-1">Track all cash in/out transactions.</p></div>
    <?php if(has_permission('can_manage_acc_cash') || !$_SESSION['is_staff']): ?>
    <button onclick="openCashTxModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Transaction</button>
    <?php endif; ?>
</div>

<form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_cash_book">
    <div><label class="block text-xs font-bold text-slate-500 mb-1">From</label><input type="date" name="from_date" value="<?= $accFrom ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <div><label class="block text-xs font-bold text-slate-500 mb-1">To</label><input type="date" name="to_date" value="<?= $accTo ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold self-end">Apply</button>
    <button type="button" onclick="window.print()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-xl text-sm font-bold self-end">Print</button>
</form>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Opening Balance</p><p class="text-2xl font-black text-slate-700"><?= $currencySymbol ?> <?= number_format($openingBalance,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 border-l-4 border-l-emerald-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Cash In</p><p class="text-2xl font-black text-emerald-600"><?= $currencySymbol ?> <?= number_format($cashIn,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 border-l-4 border-l-rose-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Cash Out</p><p class="text-2xl font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($cashOut,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 border-l-4 border-l-indigo-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Closing Balance</p><p class="text-2xl font-black text-indigo-600"><?= $currencySymbol ?> <?= number_format($closingBalance,2) ?></p></div>
</div>

<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead class="bg-slate-50 border-b border-slate-100"><tr class="text-left">
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Date</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Description</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Reference</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-emerald-500">Cash In</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-rose-500">Cash Out</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-indigo-500">Balance</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase no-print">Actions</th>
</tr></thead>
<tbody class="divide-y divide-slate-50">
<tr class="bg-slate-50"><td colspan="3" class="px-4 py-2 font-bold text-slate-600 text-xs uppercase tracking-wider">Opening Balance</td><td class="px-4 py-2"></td><td class="px-4 py-2"></td><td class="px-4 py-2 font-extrabold text-slate-700"><?= $currencySymbol ?> <?= number_format($openingBalance,2) ?></td><td class="no-print"></td></tr>
<?php if(empty($txns)): ?>
<tr><td colspan="7" class="text-center py-10 text-slate-400">No cash transactions in this period.</td></tr>
<?php else: foreach($txns as $t): ?>
<tr class="hover:bg-slate-50 transition">
    <td class="px-4 py-3 font-bold text-slate-700 whitespace-nowrap"><?= date('d M Y',strtotime($t['transaction_date'])) ?></td>
    <td class="px-4 py-3 text-slate-600"><?= xss_clean($t['description']??'') ?></td>
    <td class="px-4 py-3 text-xs font-mono text-slate-400"><?= xss_clean($t['reference']??'') ?></td>
    <td class="px-4 py-3 font-bold text-emerald-600"><?= $t['transaction_type']==='in' ? $currencySymbol.' '.number_format($t['amount'],2) : '-' ?></td>
    <td class="px-4 py-3 font-bold text-rose-600"><?= $t['transaction_type']==='out' ? $currencySymbol.' '.number_format($t['amount'],2) : '-' ?></td>
    <td class="px-4 py-3 font-extrabold <?= $t['balance']>=0?'text-indigo-700':'text-red-700' ?>"><?= $currencySymbol ?> <?= number_format($t['balance'],2) ?></td>
    <td class="px-4 py-3 no-print">
        <button onclick='editCashTx(<?= htmlspecialchars(json_encode($t),ENT_QUOTES) ?>)' class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1.5 rounded-lg hover:bg-indigo-100 transition">Edit</button>
        <form method="POST" action="?route=app" class="inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_cash_transaction"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="ml-1 text-xs font-bold text-rose-500 bg-rose-50 px-2.5 py-1.5 rounded-lg hover:bg-rose-100 transition">Del</button></form>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
<tfoot class="bg-slate-50 border-t-2 border-slate-200">
<tr><td colspan="3" class="px-4 py-3 font-extrabold text-slate-800 text-right">TOTALS</td>
<td class="px-4 py-3 font-extrabold text-emerald-600"><?= $currencySymbol ?> <?= number_format($cashIn,2) ?></td>
<td class="px-4 py-3 font-extrabold text-rose-600"><?= $currencySymbol ?> <?= number_format($cashOut,2) ?></td>
<td class="px-4 py-3 font-extrabold text-indigo-700"><?= $currencySymbol ?> <?= number_format($closingBalance,2) ?></td>
<td class="no-print"></td>
</tr>
</tfoot>
</table>
</div>
</div>
</div>

<!-- Add/Edit Modal -->
<div id="cashTxModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
    <div class="px-6 py-4 border-b flex justify-between items-center"><h3 class="font-extrabold text-slate-800" id="cashTxTitle">Add Cash Transaction</h3><button onclick="closeCashTxModal()" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="save_cash_transaction"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="tx_id" id="cash_tx_id">
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Date</label><input type="date" name="transaction_date" id="cash_date" value="<?= date('Y-m-d') ?>" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Type</label>
                <select name="transaction_type" id="cash_type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                    <option value="in">Cash In</option><option value="out">Cash Out</option>
                </select>
            </div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Description</label><input type="text" name="description" id="cash_desc" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Amount</label><input type="number" step="0.01" name="amount" id="cash_amount" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Reference</label><input type="text" name="reference" id="cash_ref" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        </div>
        <div class="flex gap-3"><button type="button" onclick="closeCashTxModal()" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save</button></div>
    </form>
  </div>
</div>
<script>
function openCashTxModal(){document.getElementById('cashTxTitle').textContent='Add Cash Transaction';document.getElementById('cash_tx_id').value='';document.getElementById('cash_date').value='<?= date('Y-m-d') ?>';['cash_desc','cash_amount','cash_ref'].forEach(i=>document.getElementById(i).value='');document.getElementById('cash_type').value='in';document.getElementById('cashTxModal').classList.remove('hidden');document.getElementById('cashTxModal').classList.add('flex');}
function editCashTx(d){document.getElementById('cashTxTitle').textContent='Edit Transaction';document.getElementById('cash_tx_id').value=d.id;document.getElementById('cash_date').value=d.transaction_date;document.getElementById('cash_type').value=d.transaction_type;document.getElementById('cash_desc').value=d.description||'';document.getElementById('cash_amount').value=d.amount;document.getElementById('cash_ref').value=d.reference||'';document.getElementById('cashTxModal').classList.remove('hidden');document.getElementById('cashTxModal').classList.add('flex');}
function closeCashTxModal(){document.getElementById('cashTxModal').classList.add('hidden');document.getElementById('cashTxModal').classList.remove('flex');}
</script>
