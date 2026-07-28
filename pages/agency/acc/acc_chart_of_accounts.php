<?php
// Chart of Accounts — admin only
$acc_type_filter = trim($_GET['type'] ?? '');
$where = "agency_id=?"; $params = [$agency_id];
if ($acc_type_filter) { $where .= " AND account_type=?"; $params[] = $acc_type_filter; }
$accounts = $conn->prepare("SELECT * FROM acc_chart_of_accounts WHERE $where ORDER BY account_type, account_code");
$accounts->execute($params); $accounts = $accounts->fetchAll(PDO::FETCH_ASSOC);

$typeColors=['Asset'=>'bg-blue-100 text-blue-700','Liability'=>'bg-amber-100 text-amber-700','Income'=>'bg-emerald-100 text-emerald-700','Expense'=>'bg-rose-100 text-rose-700','Equity'=>'bg-violet-100 text-violet-700'];
$typeCounts=[];
foreach(['Asset','Liability','Income','Expense','Equity'] as $t){
    $s=$conn->prepare("SELECT COUNT(*) FROM acc_chart_of_accounts WHERE agency_id=? AND account_type=?");
    $s->execute([$agency_id,$t]); $typeCounts[$t]=(int)$s->fetchColumn();
}

// Seed default accounts if none exist
$totalAccounts = $conn->query("SELECT COUNT(*) FROM acc_chart_of_accounts WHERE agency_id=$agency_id")->fetchColumn();
if ($totalAccounts == 0 && !$_SESSION['is_staff']) {
    $defaults=[
        ['1001','Cash','Asset','Cash & Bank'],['1002','Bank Account','Asset','Cash & Bank'],['1003','Accounts Receivable','Asset','Current Assets'],['1004','Office Equipment','Asset','Fixed Assets'],
        ['2001','Accounts Payable','Liability','Current Liabilities'],['2002','Customer Advance','Liability','Current Liabilities'],['2003','Supplier Due','Liability','Current Liabilities'],
        ['3001','Owner Capital','Equity','Equity'],['3002','Retained Earnings','Equity','Equity'],
        ['4001','Air Ticket Sales','Income','Sales'],['4002','Visa Processing','Income','Sales'],['4003','Student Consultancy','Income','Sales'],['4004','Hotel Booking','Income','Sales'],['4005','Umrah Package','Income','Sales'],['4006','Tour Package','Income','Sales'],['4007','Service Charge','Income','Other Income'],['4008','Other Income','Income','Other Income'],
        ['5001','Staff Salary','Expense','Personnel'],['5002','Office Rent','Expense','Operating'],['5003','Internet & Utilities','Expense','Operating'],['5004','Electricity','Expense','Operating'],['5005','Marketing','Expense','Operating'],['5006','Office Supplies','Expense','Operating'],['5007','Bank Charges','Expense','Finance'],['5008','Transport','Expense','Operating'],['5009','Other Expenses','Expense','Operating'],
    ];
    foreach($defaults as [$code,$name,$type,$group]){
        $conn->prepare("INSERT IGNORE INTO acc_chart_of_accounts (agency_id,account_code,account_name,account_type,account_group) VALUES (?,?,?,?,?)")->execute([$agency_id,$code,$name,$type,$group]);
    }
    redirect("?route=app&page=acc_chart_of_accounts");
}
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-list text-indigo-500"></i> Chart of Accounts</h2><p class="text-sm text-slate-500 mt-1">Manage all accounting accounts for your agency.</p></div>
    <?php if(!$_SESSION['is_staff']): ?>
    <button onclick="document.getElementById('accAcctModal').classList.remove('hidden');document.getElementById('accAcctModal').classList.add('flex')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Account</button>
    <?php endif; ?>
</div>

<!-- Type summary tiles -->
<div class="grid grid-cols-5 gap-3">
<?php foreach(['Asset','Liability','Income','Expense','Equity'] as $t): ?>
<a href="?route=app&page=acc_chart_of_accounts&type=<?= $t === $acc_type_filter ? '' : $t ?>"
   class="bg-white rounded-2xl soft-shadow border <?= $acc_type_filter===$t?'border-indigo-300':'border-slate-100' ?> p-4 text-center hover:border-indigo-200 transition">
    <p class="text-xs font-bold text-slate-500 uppercase mb-1"><?= $t ?></p>
    <?php $tTextMap=['Asset'=>'text-blue-700','Liability'=>'text-amber-700','Income'=>'text-emerald-700','Expense'=>'text-rose-700','Equity'=>'text-violet-700']; ?>
    <p class="text-2xl font-black <?= $tTextMap[$t]??'text-slate-700' ?>"><?= $typeCounts[$t] ?></p>
</a>
<?php endforeach; ?>
</div>

<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead class="bg-slate-50 border-b border-slate-100"><tr class="text-left">
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Code</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Account Name</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Type</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Group</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Opening Balance</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Status</th>
<?php if(!$_SESSION['is_staff']): ?><th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Actions</th><?php endif; ?>
</tr></thead>
<tbody class="divide-y divide-slate-50">
<?php if(empty($accounts)): ?>
<tr><td colspan="7" class="text-center py-12 text-slate-400"><i class="fa-solid fa-list text-3xl block mb-2"></i>No accounts found.</td></tr>
<?php else: foreach($accounts as $ac): ?>
<tr class="hover:bg-slate-50 transition <?= !$ac['is_active']?'opacity-50':'' ?>">
    <td class="px-4 py-3 font-mono font-bold text-slate-700"><?= xss_clean($ac['account_code']) ?></td>
    <td class="px-4 py-3 font-bold text-slate-800"><?= xss_clean($ac['account_name']) ?></td>
    <td class="px-4 py-3"><span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $typeColors[$ac['account_type']]??'bg-slate-100 text-slate-600' ?>"><?= $ac['account_type'] ?></span></td>
    <td class="px-4 py-3 text-sm text-slate-600"><?= xss_clean($ac['account_group']??'') ?></td>
    <td class="px-4 py-3 font-bold text-slate-700"><?= $currencySymbol ?> <?= number_format($ac['opening_balance'],2) ?></td>
    <td class="px-4 py-3"><span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $ac['is_active']?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-500' ?>"><?= $ac['is_active']?'Active':'Inactive' ?></span></td>
    <?php if(!$_SESSION['is_staff']): ?>
    <td class="px-4 py-3 flex items-center gap-2">
        <button onclick='editAccAccount(<?= htmlspecialchars(json_encode($ac),ENT_QUOTES) ?>)' class="text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1.5 rounded-lg transition">Edit</button>
        <form method="POST" action="?route=app" class="inline"><input type="hidden" name="action" value="toggle_acc_account"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" value="<?= $ac['id'] ?>"><button class="text-xs font-bold text-amber-600 bg-amber-50 hover:bg-amber-100 px-2.5 py-1.5 rounded-lg transition"><?= $ac['is_active']?'Deactivate':'Activate' ?></button></form>
    </td>
    <?php endif; ?>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>
</div>

<!-- Add/Edit Modal -->
<div id="accAcctModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800" id="accAcctModalTitle">Add Account</h3><button onclick="closeAcctModal()" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="save_acc_account"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" id="acct_id">
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Account Code *</label><input type="text" name="account_code" id="acct_code" required placeholder="e.g. 1001" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Account Name *</label><input type="text" name="account_name" id="acct_name" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Account Type *</label>
            <select name="account_type" id="acct_type" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                <?php foreach(['Asset','Liability','Income','Expense','Equity'] as $t): ?><option><?= $t ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Account Group</label><input type="text" name="account_group" id="acct_group" placeholder="e.g. Cash & Bank" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Opening Balance</label><input type="number" step="0.01" name="opening_balance" id="acct_opening" value="0" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div class="flex gap-3"><button type="button" onclick="closeAcctModal()" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save</button></div>
    </form>
  </div>
</div>
<script>
function editAccAccount(d){
    document.getElementById('accAcctModalTitle').textContent='Edit Account';
    document.getElementById('acct_id').value=d.id;
    document.getElementById('acct_code').value=d.account_code;
    document.getElementById('acct_name').value=d.account_name;
    document.getElementById('acct_type').value=d.account_type;
    document.getElementById('acct_group').value=d.account_group||'';
    document.getElementById('acct_opening').value=d.opening_balance||0;
    document.getElementById('accAcctModal').classList.remove('hidden');document.getElementById('accAcctModal').classList.add('flex');
}
function closeAcctModal(){document.getElementById('accAcctModal').classList.add('hidden');document.getElementById('accAcctModal').classList.remove('flex');document.getElementById('accAcctModalTitle').textContent='Add Account';document.getElementById('acct_id').value='';}
</script>
