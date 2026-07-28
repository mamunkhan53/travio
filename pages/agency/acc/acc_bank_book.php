<?php
$accFrom = normalizeReportDate($_GET['from_date']??null, date('Y-m-01'));
$accTo   = normalizeReportDate($_GET['to_date']??null, date('Y-m-d'));
if($accFrom>$accTo)[$accFrom,$accTo]=[$accTo,$accFrom];

$openingBank = (float)(function() use($conn,$agency_id){ $s=$conn->prepare("SELECT setting_value FROM acc_settings WHERE agency_id=? AND setting_key='opening_bank_balance'"); $s->execute([$agency_id]); return $s->fetchColumn()?:0; })();

$txns=$conn->prepare("SELECT bt.*,st.full_name as staff_name FROM acc_bank_transactions bt LEFT JOIN staff st ON bt.created_by_staff_id=st.id WHERE bt.agency_id=? AND bt.transaction_date BETWEEN ? AND ? ORDER BY bt.transaction_date ASC, bt.created_at ASC");
$txns->execute([$agency_id,$accFrom,$accTo]); $txns=$txns->fetchAll(PDO::FETCH_ASSOC);

$bankDepos = (float)$conn->query("SELECT SUM(amount) FROM acc_bank_transactions WHERE agency_id=$agency_id AND transaction_type='deposit' AND transaction_date BETWEEN '$accFrom' AND '$accTo'")->fetchColumn();
$bankWithd = (float)$conn->query("SELECT SUM(amount) FROM acc_bank_transactions WHERE agency_id=$agency_id AND transaction_type IN ('withdrawal','transfer') AND transaction_date BETWEEN '$accFrom' AND '$accTo'")->fetchColumn();

$runBal=$openingBank;
foreach($txns as &$t){ $isIn=$t['transaction_type']==='deposit'; $runBal+=($isIn?1:-1)*$t['amount']; $t['balance']=$runBal; }
unset($t);
$closingBalance=$openingBank+$bankDepos-$bankWithd;

// Bank accounts
$bankAccounts=$conn->query("SELECT DISTINCT bank_account_name FROM acc_bank_transactions WHERE agency_id=$agency_id")->fetchAll(PDO::FETCH_COLUMN);
if(empty($bankAccounts)) $bankAccounts=['Main Account'];
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-building-columns text-indigo-500"></i> Bank Book</h2><p class="text-sm text-slate-500 mt-1">Track all bank deposits, withdrawals and transfers.</p></div>
    <?php if(has_permission('can_manage_acc_bank') || !$_SESSION['is_staff']): ?>
    <button onclick="openBankTxModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Transaction</button>
    <?php endif; ?>
</div>

<form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_bank_book">
    <div><label class="block text-xs font-bold text-slate-500 mb-1">From</label><input type="date" name="from_date" value="<?= $accFrom ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <div><label class="block text-xs font-bold text-slate-500 mb-1">To</label><input type="date" name="to_date" value="<?= $accTo ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold self-end">Apply</button>
    <button type="button" onclick="window.print()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-xl text-sm font-bold self-end">Print</button>
</form>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Opening Balance</p><p class="text-2xl font-black text-slate-700"><?= $currencySymbol ?> <?= number_format($openingBank,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 border-l-4 border-l-emerald-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Deposits</p><p class="text-2xl font-black text-emerald-600"><?= $currencySymbol ?> <?= number_format($bankDepos,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 border-l-4 border-l-rose-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Withdrawals</p><p class="text-2xl font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($bankWithd,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 border-l-4 border-l-indigo-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Closing Balance</p><p class="text-2xl font-black text-indigo-600"><?= $currencySymbol ?> <?= number_format($closingBalance,2) ?></p></div>
</div>

<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
<div class="overflow-x-auto"><table class="w-full text-sm">
<thead class="bg-slate-50 border-b border-slate-100"><tr class="text-left">
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Date</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Account</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Type</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Description</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-emerald-500">Deposit</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-rose-500">Withdrawal</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-indigo-500">Balance</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase no-print">Actions</th>
</tr></thead>
<tbody class="divide-y divide-slate-50">
<tr class="bg-slate-50"><td colspan="4" class="px-4 py-2 font-bold text-slate-600 text-xs uppercase tracking-wider">Opening Balance</td><td></td><td></td><td class="px-4 py-2 font-extrabold text-slate-700"><?= $currencySymbol ?> <?= number_format($openingBank,2) ?></td><td class="no-print"></td></tr>
<?php if(empty($txns)): ?>
<tr><td colspan="8" class="text-center py-10 text-slate-400">No bank transactions in this period.</td></tr>
<?php else: foreach($txns as $t):
    $isIn=$t['transaction_type']==='deposit';
    $typeColors=['deposit'=>'bg-emerald-100 text-emerald-700','withdrawal'=>'bg-rose-100 text-rose-700','transfer'=>'bg-blue-100 text-blue-700'];
?>
<tr class="hover:bg-slate-50 transition">
    <td class="px-4 py-3 font-bold text-slate-700 whitespace-nowrap"><?= date('d M Y',strtotime($t['transaction_date'])) ?></td>
    <td class="px-4 py-3 text-xs text-slate-600 font-bold"><?= xss_clean($t['bank_account_name']??'Main Account') ?></td>
    <td class="px-4 py-3"><span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $typeColors[$t['transaction_type']]??'bg-slate-100 text-slate-600' ?>"><?= ucfirst($t['transaction_type']) ?></span></td>
    <td class="px-4 py-3 text-slate-600"><?= xss_clean($t['description']??'') ?></td>
    <td class="px-4 py-3 font-bold text-emerald-600"><?= $isIn ? $currencySymbol.' '.number_format($t['amount'],2) : '-' ?></td>
    <td class="px-4 py-3 font-bold text-rose-600"><?= !$isIn ? $currencySymbol.' '.number_format($t['amount'],2) : '-' ?></td>
    <td class="px-4 py-3 font-extrabold <?= $t['balance']>=0?'text-indigo-700':'text-red-700' ?>"><?= $currencySymbol ?> <?= number_format($t['balance'],2) ?></td>
    <td class="px-4 py-3 no-print">
        <button onclick='editBankTx(<?= htmlspecialchars(json_encode($t),ENT_QUOTES) ?>)' class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1.5 rounded-lg hover:bg-indigo-100 transition">Edit</button>
        <form method="POST" action="?route=app" class="inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_bank_transaction"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="ml-1 text-xs font-bold text-rose-500 bg-rose-50 px-2.5 py-1.5 rounded-lg hover:bg-rose-100 transition">Del</button></form>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
<tfoot class="bg-slate-50 border-t-2 border-slate-200">
<tr><td colspan="4" class="px-4 py-3 font-extrabold text-right text-slate-800">TOTALS</td>
<td class="px-4 py-3 font-extrabold text-emerald-600"><?= $currencySymbol ?> <?= number_format($bankDepos,2) ?></td>
<td class="px-4 py-3 font-extrabold text-rose-600"><?= $currencySymbol ?> <?= number_format($bankWithd,2) ?></td>
<td class="px-4 py-3 font-extrabold text-indigo-700"><?= $currencySymbol ?> <?= number_format($closingBalance,2) ?></td>
<td class="no-print"></td>
</tr></tfoot>
</table></div>
</div>
</div>

<div id="bankTxModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
    <div class="px-6 py-4 border-b flex justify-between items-center"><h3 class="font-extrabold text-slate-800" id="bankTxTitle">Add Bank Transaction</h3><button onclick="closeBankTxModal()" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="save_bank_transaction"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="tx_id" id="bank_tx_id">
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Bank Account</label>
            <input list="bankAccList" name="bank_account_name" id="bank_acct" placeholder="Main Account" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <datalist id="bankAccList"><?php foreach($bankAccounts as $ba): ?><option value="<?= xss_clean($ba) ?>"><?php endforeach; ?></datalist>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Date</label><input type="date" name="transaction_date" id="bank_date" value="<?= date('Y-m-d') ?>" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Type</label>
                <select name="transaction_type" id="bank_type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                    <option value="deposit">Deposit</option><option value="withdrawal">Withdrawal</option><option value="transfer">Transfer</option>
                </select>
            </div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Description</label><input type="text" name="description" id="bank_desc" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Amount</label><input type="number" step="0.01" name="amount" id="bank_amount" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Reference</label><input type="text" name="reference" id="bank_ref" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        </div>
        <div class="flex gap-3"><button type="button" onclick="closeBankTxModal()" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save</button></div>
    </form>
  </div>
</div>
<script>
function openBankTxModal(){document.getElementById('bankTxTitle').textContent='Add Bank Transaction';document.getElementById('bank_tx_id').value='';document.getElementById('bank_date').value='<?= date('Y-m-d') ?>';['bank_acct','bank_desc','bank_amount','bank_ref'].forEach(i=>document.getElementById(i).value='');document.getElementById('bank_type').value='deposit';document.getElementById('bankTxModal').classList.remove('hidden');document.getElementById('bankTxModal').classList.add('flex');}
function editBankTx(d){document.getElementById('bankTxTitle').textContent='Edit Transaction';document.getElementById('bank_tx_id').value=d.id;document.getElementById('bank_acct').value=d.bank_account_name||'Main Account';document.getElementById('bank_date').value=d.transaction_date;document.getElementById('bank_type').value=d.transaction_type;document.getElementById('bank_desc').value=d.description||'';document.getElementById('bank_amount').value=d.amount;document.getElementById('bank_ref').value=d.reference||'';document.getElementById('bankTxModal').classList.remove('hidden');document.getElementById('bankTxModal').classList.add('flex');}
function closeBankTxModal(){document.getElementById('bankTxModal').classList.add('hidden');document.getElementById('bankTxModal').classList.remove('flex');}
</script>
