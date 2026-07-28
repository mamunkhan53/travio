<?php
// General Ledger — full chronological view of all financial transactions
$accRange = $_GET['range'] ?? 'this_month';
$todayStr = date('Y-m-d');
switch ($accRange) {
    case 'today':     $accFrom=$todayStr; $accTo=$todayStr; break;
    case 'this_week': $accFrom=date('Y-m-d',strtotime('monday this week')); $accTo=$todayStr; break;
    case 'this_year': $accFrom=date('Y-01-01'); $accTo=$todayStr; break;
    case 'custom':    $accFrom=normalizeReportDate($_GET['from_date']??null,date('Y-m-01')); $accTo=normalizeReportDate($_GET['to_date']??null,$todayStr); break;
    default:          $accRange='this_month'; $accFrom=date('Y-m-01'); $accTo=$todayStr;
}
if($accFrom>$accTo) [$accFrom,$accTo]=[$accTo,$accFrom];
$filterType  = $_GET['type'] ?? 'all';
$filterStaff = (int)($_GET['staff_id'] ?? 0);

$ledger = [];
$completedStatuses = "'Completed','Paid','Confirmed'";

// 1. Sales (auto income)
foreach(['passports'=>'Passport Processing','visas'=>'Visa Processing','tickets'=>'Air Ticket','umrah'=>'Umrah Package','tours'=>'Tour Package'] as $tbl=>$cat) {
    $s=$conn->prepare("SELECT t.transaction_date as d, t.name as description, (t.selling_price-t.service_cost) as amount, t.id as ref, t.reference_staff_id as staff_id FROM $tbl t WHERE t.agency_id=? AND t.status IN ($completedStatuses) AND t.transaction_date BETWEEN ? AND ?".($filterStaff?" AND t.reference_staff_id=$filterStaff":""));
    $s->execute([$agency_id,$accFrom,$accTo]);
    while($r=$s->fetch(PDO::FETCH_ASSOC)){
        $ledger[]=['date'=>$r['d'],'source'=>ucfirst($tbl),'description'=>$cat.': '.$r['description'],'reference'=>$r['ref'],'type'=>'income','income'=>max(0,(float)$r['amount']),'expense'=>0,'staff_id'=>$r['staff_id']];
    }
}

// 2. Manual income
$s=$conn->prepare("SELECT income_date as d,category,description,amount,id,reference_staff_id as staff_id FROM acc_income WHERE agency_id=? AND income_date BETWEEN ? AND ?".($filterStaff?" AND reference_staff_id=$filterStaff":""));
$s->execute([$agency_id,$accFrom,$accTo]);
while($r=$s->fetch(PDO::FETCH_ASSOC)){
    $ledger[]=['date'=>$r['d'],'source'=>'Manual Income','description'=>($r['category']?$r['category'].': ':'').$r['description'],'reference'=>$r['id'],'type'=>'income','income'=>(float)$r['amount'],'expense'=>0,'staff_id'=>$r['staff_id']];
}

// 3. Expenses
$s=$conn->prepare("SELECT expense_date as d,category,title,amount,id,created_by_staff_id as staff_id FROM accounting_expenses WHERE agency_id=? AND expense_date BETWEEN ? AND ?".($filterStaff?" AND created_by_staff_id=$filterStaff":""));
$s->execute([$agency_id,$accFrom,$accTo]);
while($r=$s->fetch(PDO::FETCH_ASSOC)){
    $ledger[]=['date'=>$r['d'],'source'=>'Expense','description'=>($r['category']?$r['category'].': ':'').($r['title']??''),'reference'=>$r['id'],'type'=>'expense','income'=>0,'expense'=>(float)$r['amount'],'staff_id'=>$r['staff_id']];
}

// 4. Journal entries
$s=$conn->prepare("SELECT jl.*, j.journal_date as d, j.reference, j.description as jdesc FROM acc_journal_lines jl JOIN acc_journals j ON jl.journal_id=j.id WHERE j.agency_id=? AND j.journal_date BETWEEN ? AND ?");
$s->execute([$agency_id,$accFrom,$accTo]);
while($r=$s->fetch(PDO::FETCH_ASSOC)){
    if($r['debit']>0) $ledger[]=['date'=>$r['d'],'source'=>'Journal','description'=>'DR '.$r['account_name'].': '.$r['jdesc'],'reference'=>$r['journal_id'],'type'=>'expense','income'=>0,'expense'=>(float)$r['debit'],'staff_id'=>null];
    if($r['credit']>0) $ledger[]=['date'=>$r['d'],'source'=>'Journal','description'=>'CR '.$r['account_name'].': '.$r['jdesc'],'reference'=>$r['journal_id'],'type'=>'income','income'=>(float)$r['credit'],'expense'=>0,'staff_id'=>null];
}

// 5. Cash In/Out
$s=$conn->prepare("SELECT transaction_date as d,description,amount,transaction_type,id,created_by_staff_id as staff_id FROM acc_cash_transactions WHERE agency_id=? AND transaction_date BETWEEN ? AND ?");
$s->execute([$agency_id,$accFrom,$accTo]);
while($r=$s->fetch(PDO::FETCH_ASSOC)){
    $isIn=$r['transaction_type']==='in';
    $ledger[]=['date'=>$r['d'],'source'=>'Cash Book','description'=>'Cash '.($isIn?'In':'Out').': '.$r['description'],'reference'=>$r['id'],'type'=>$isIn?'income':'expense','income'=>$isIn?(float)$r['amount']:0,'expense'=>!$isIn?(float)$r['amount']:0,'staff_id'=>$r['staff_id']];
}

// 6. Bank transactions
$s=$conn->prepare("SELECT transaction_date as d,description,amount,transaction_type,id,bank_account_name FROM acc_bank_transactions WHERE agency_id=? AND transaction_date BETWEEN ? AND ?");
$s->execute([$agency_id,$accFrom,$accTo]);
while($r=$s->fetch(PDO::FETCH_ASSOC)){
    $isIn=$r['transaction_type']==='deposit';
    $ledger[]=['date'=>$r['d'],'source'=>'Bank Book','description'=>ucfirst($r['transaction_type']).': '.$r['description'],'reference'=>$r['id'],'type'=>$isIn?'income':'expense','income'=>$isIn?(float)$r['amount']:0,'expense'=>!$isIn?(float)$r['amount']:0,'staff_id'=>null];
}

// Filter by type
if($filterType==='income') $ledger=array_filter($ledger,fn($l)=>$l['type']==='income');
if($filterType==='expense') $ledger=array_filter($ledger,fn($l)=>$l['type']==='expense');

// Sort + running balance
usort($ledger,fn($a,$b)=>strcmp($a['date'],$b['date']));
$running=0;
foreach($ledger as &$le){ $running+=$le['income']-$le['expense']; $le['balance']=$running; }
unset($le);
$ledger=array_reverse($ledger);

$totalIncome=array_sum(array_column($ledger,'income'));
$totalExpense=array_sum(array_column($ledger,'expense'));
$reportFile='General_Ledger_'.$accFrom.'_to_'.$accTo;
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-book text-indigo-500"></i> General Ledger</h2><p class="text-sm text-slate-500 mt-1">Chronological record of all financial transactions.</p></div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-rose-50 text-rose-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-rose-100 transition flex items-center gap-2"><i class="fa-solid fa-print"></i> Print</button>
    </div>
</div>
<form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_general_ledger">
    <div><label class="block text-xs font-bold text-slate-500 mb-1">Range</label>
        <select name="range" onchange="document.getElementById('glCustom').classList.toggle('hidden',this.value!=='custom')" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <?php foreach(['today'=>'Today','this_week'=>'This Week','this_month'=>'This Month','this_year'=>'This Year','custom'=>'Custom'] as $k=>$l): ?><option value="<?= $k ?>" <?= $accRange===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?>
        </select>
    </div>
    <div id="glCustom" class="<?= $accRange==='custom'?'flex':'hidden' ?> gap-2">
        <div><label class="block text-xs font-bold text-slate-500 mb-1">From</label><input type="date" name="from_date" value="<?= $accFrom ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-xs font-bold text-slate-500 mb-1">To</label><input type="date" name="to_date" value="<?= $accTo ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    </div>
    <div><label class="block text-xs font-bold text-slate-500 mb-1">Type</label>
        <select name="type" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="all" <?= $filterType==='all'?'selected':'' ?>>All</option>
            <option value="income" <?= $filterType==='income'?'selected':'' ?>>Income Only</option>
            <option value="expense" <?= $filterType==='expense'?'selected':'' ?>>Expense Only</option>
        </select>
    </div>
    <?php if(!$_SESSION['is_staff'] && !empty($all_staff)): ?>
    <div><label class="block text-xs font-bold text-slate-500 mb-1">Staff</label>
        <select name="staff_id" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="0">All Staff</option>
            <?php foreach($all_staff as $st): ?><option value="<?= $st['id'] ?>" <?= $filterStaff==$st['id']?'selected':'' ?>><?= xss_clean($st['full_name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold self-end">Apply</button>
</form>
<div class="grid grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total Income</p><p class="text-2xl font-black text-emerald-600"><?= $currencySymbol ?> <?= number_format($totalIncome,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total Expense</p><p class="text-2xl font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($totalExpense,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Net</p><p class="text-2xl font-black <?= ($totalIncome-$totalExpense)>=0?'text-indigo-600':'text-red-600' ?>"><?= $currencySymbol ?> <?= number_format($totalIncome-$totalExpense,2) ?></p></div>
</div>
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden" id="glPrintable">
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead class="bg-slate-50 border-b border-slate-100"><tr class="text-left">
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Date</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Source</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Description</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Ref</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-emerald-500">Income</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-rose-500">Expense</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-indigo-500">Balance</th>
</tr></thead>
<tbody class="divide-y divide-slate-50">
<?php if(empty($ledger)): ?>
<tr><td colspan="7" class="text-center py-12 text-slate-400">No transactions found for this period.</td></tr>
<?php else: foreach($ledger as $le): ?>
<tr class="hover:bg-slate-50 transition">
    <td class="px-4 py-3 font-bold text-slate-700 whitespace-nowrap"><?= date('d M Y',strtotime($le['date'])) ?></td>
    <td class="px-4 py-3"><span class="text-xs font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600"><?= xss_clean($le['source']) ?></span></td>
    <td class="px-4 py-3 text-slate-600 max-w-xs truncate"><?= xss_clean($le['description']) ?></td>
    <td class="px-4 py-3 text-xs font-mono text-slate-400"><?= xss_clean($le['reference']??'') ?></td>
    <td class="px-4 py-3 font-bold text-emerald-600"><?= $le['income']>0 ? $currencySymbol.' '.number_format($le['income'],2) : '-' ?></td>
    <td class="px-4 py-3 font-bold text-rose-600"><?= $le['expense']>0 ? $currencySymbol.' '.number_format($le['expense'],2) : '-' ?></td>
    <td class="px-4 py-3 font-extrabold <?= $le['balance']>=0?'text-indigo-700':'text-red-700' ?>"><?= $currencySymbol ?> <?= number_format($le['balance'],2) ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
<tfoot class="bg-slate-50 border-t-2 border-slate-200">
<tr><td colspan="4" class="px-4 py-3 font-extrabold text-slate-800 text-right">TOTALS</td>
<td class="px-4 py-3 font-extrabold text-emerald-600"><?= $currencySymbol ?> <?= number_format($totalIncome,2) ?></td>
<td class="px-4 py-3 font-extrabold text-rose-600"><?= $currencySymbol ?> <?= number_format($totalExpense,2) ?></td>
<td class="px-4 py-3 font-extrabold <?= ($totalIncome-$totalExpense)>=0?'text-indigo-700':'text-red-700' ?>"><?= $currencySymbol ?> <?= number_format($totalIncome-$totalExpense,2) ?></td>
</tr>
</tfoot>
</table>
</div>
</div>
</div>
