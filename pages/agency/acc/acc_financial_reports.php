<?php
// Financial Reports Hub
$accRange = $_GET['range'] ?? 'this_month';
$todayStr = date('Y-m-d');
switch ($accRange) {
    case 'today':     $accFrom=$todayStr; $accTo=$todayStr; break;
    case 'this_week': $accFrom=date('Y-m-d',strtotime('monday this week')); $accTo=$todayStr; break;
    case 'this_year': $accFrom=date('Y-01-01'); $accTo=$todayStr; break;
    case 'custom':    $accFrom=normalizeReportDate($_GET['from_date']??null,date('Y-m-01')); $accTo=normalizeReportDate($_GET['to_date']??null,$todayStr); break;
    default:          $accRange='this_month'; $accFrom=date('Y-m-01'); $accTo=$todayStr;
}
if($accFrom>$accTo)[$accFrom,$accTo]=[$accTo,$accFrom];
$completedStatuses="'Completed','Paid','Confirmed'";

// KPIs
$salesIncome=0;
foreach(['passports','visas','tickets','umrah','tours'] as $tbl){ $s=$conn->prepare("SELECT SUM(selling_price-service_cost) FROM $tbl WHERE agency_id=? AND status IN ($completedStatuses) AND transaction_date BETWEEN ? AND ?"); $s->execute([$agency_id,$accFrom,$accTo]); $salesIncome+=(float)$s->fetchColumn(); }
$s=$conn->prepare("SELECT SUM(amount) FROM acc_income WHERE agency_id=? AND income_date BETWEEN ? AND ?"); $s->execute([$agency_id,$accFrom,$accTo]); $manualIncome=(float)$s->fetchColumn();
$totalIncome=$salesIncome+$manualIncome;
$s=$conn->prepare("SELECT SUM(amount) FROM accounting_expenses WHERE agency_id=? AND expense_date BETWEEN ? AND ?"); $s->execute([$agency_id,$accFrom,$accTo]); $totalExpense=(float)$s->fetchColumn();
$netProfit=$totalIncome-$totalExpense;
$arDue=(float)$conn->query("SELECT SUM(due_amount) FROM invoices WHERE agency_id=$agency_id AND due_amount>0")->fetchColumn();
$apDue=(float)$conn->query("SELECT SUM(due_amount) FROM acc_payables WHERE agency_id=$agency_id AND status!='Paid'")->fetchColumn();

// Sales by service for donut
$svcData=[];
foreach(['passports'=>'Passport','visas'=>'Visa','tickets'=>'Air Ticket','umrah'=>'Umrah','tours'=>'Tour'] as $tbl=>$cat){
    $s=$conn->prepare("SELECT SUM(selling_price-service_cost) FROM $tbl WHERE agency_id=? AND status IN ($completedStatuses) AND transaction_date BETWEEN ? AND ?");
    $s->execute([$agency_id,$accFrom,$accTo]); $v=(float)$s->fetchColumn();
    if($v>0) $svcData[$cat]=$v;
}

// Expense by category
$expCats=$conn->prepare("SELECT COALESCE(category,'Other') as cat, SUM(amount) as total FROM accounting_expenses WHERE agency_id=? AND expense_date BETWEEN ? AND ? GROUP BY cat ORDER BY total DESC LIMIT 8");
$expCats->execute([$agency_id,$accFrom,$accTo]); $expCats=$expCats->fetchAll(PDO::FETCH_ASSOC);

// Monthly 12-month data
$monthlyInc=[]; $monthlyExp=[];
for($m=11;$m>=0;$m--){
    $mFrom=date('Y-m-01',strtotime("-$m months")); $mTo=date('Y-m-t',strtotime("-$m months"));
    $mI=0; foreach(['passports','visas','tickets','umrah','tours'] as $tbl){ $s=$conn->prepare("SELECT SUM(selling_price-service_cost) FROM $tbl WHERE agency_id=? AND status IN ($completedStatuses) AND transaction_date BETWEEN ? AND ?"); $s->execute([$agency_id,$mFrom,$mTo]); $mI+=(float)$s->fetchColumn(); }
    $s=$conn->prepare("SELECT SUM(amount) FROM acc_income WHERE agency_id=? AND income_date BETWEEN ? AND ?"); $s->execute([$agency_id,$mFrom,$mTo]); $mI+=(float)$s->fetchColumn();
    $s=$conn->prepare("SELECT SUM(amount) FROM accounting_expenses WHERE agency_id=? AND expense_date BETWEEN ? AND ?"); $s->execute([$agency_id,$mFrom,$mTo]); $mE=(float)$s->fetchColumn();
    $monthlyInc[]=round($mI,2); $monthlyExp[]=round($mE,2);
}
$chartMonths=[]; for($m=11;$m>=0;$m--) $chartMonths[]=date('M y',strtotime("-$m months"));

$reportLinks=[
    ['acc_pl',         'Profit & Loss',        'fa-chart-line',            'bg-violet-50 border-violet-100 text-violet-600',  "Comprehensive income statement by period"],
    ['acc_balance_sheet','Balance Sheet',       'fa-scale-balanced',        'bg-blue-50 border-blue-100 text-blue-600',        "Financial position at any date"],
    ['acc_general_ledger','General Ledger',     'fa-book',                  'bg-indigo-50 border-indigo-100 text-indigo-600',  "Complete chronological transaction log"],
    ['acc_cash_book',  'Cash Book',             'fa-money-bill-wave',       'bg-teal-50 border-teal-100 text-teal-600',        "Cash in/out with running balance"],
    ['acc_bank_book',  'Bank Book',             'fa-building-columns',      'bg-sky-50 border-sky-100 text-sky-600',           "Bank deposits, withdrawals & transfers"],
    ['acc_receivable', 'Accounts Receivable',   'fa-hand-holding-dollar',   'bg-emerald-50 border-emerald-100 text-emerald-600',"Outstanding customer invoices"],
    ['acc_payable',    'Accounts Payable',       'fa-file-invoice',          'bg-amber-50 border-amber-100 text-amber-600',     "Vendor/supplier due payments"],
    ['acc_vat',        'VAT / Tax Report',       'fa-percent',               'bg-rose-50 border-rose-100 text-rose-600',        "Input & output VAT summary"],
];
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-chart-pie text-indigo-500"></i> Financial Reports Hub</h2><p class="text-sm text-slate-500 mt-1">All financial reports and analytics in one place.</p></div>
    <form method="GET" class="flex gap-2 items-center">
        <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_financial_reports">
        <select name="range" onchange="document.getElementById('frCustom').classList.toggle('hidden',this.value!=='custom');if(this.value!=='custom')this.form.submit()" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <?php foreach(['today'=>'Today','this_week'=>'This Week','this_month'=>'This Month','this_year'=>'This Year','custom'=>'Custom'] as $k=>$l): ?><option value="<?= $k ?>" <?= $accRange===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?>
        </select>
        <div id="frCustom" class="<?= $accRange==='custom'?'flex':'hidden' ?> gap-2">
            <input type="date" name="from_date" value="<?= $accFrom ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <input type="date" name="to_date" value="<?= $accTo ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold">Go</button>
        </div>
    </form>
</div>

<!-- KPI Row -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-emerald-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Income</p><p class="text-xl font-black text-emerald-600"><?= $currencySymbol ?> <?= number_format($totalIncome,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-rose-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Expenses</p><p class="text-xl font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($totalExpense,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 <?= $netProfit>=0?'border-l-indigo-400':'border-l-red-500' ?>"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Net Profit</p><p class="text-xl font-black <?= $netProfit>=0?'text-indigo-600':'text-red-600' ?>"><?= $currencySymbol ?> <?= number_format($netProfit,2) ?></p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">AR / AP</p><p class="text-sm font-black text-amber-600"><?= $currencySymbol ?> <?= number_format($arDue,2) ?> / <?= $currencySymbol ?> <?= number_format($apDue,2) ?></p></div>
</div>

<!-- Charts row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
        <h3 class="font-extrabold text-slate-700 mb-4">12-Month Trend</h3>
        <canvas id="frTrendChart" height="100"></canvas>
    </div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
        <h3 class="font-extrabold text-slate-700 mb-4">Sales by Service</h3>
        <?php if(empty($svcData)): ?><div class="flex items-center justify-center h-32 text-slate-400 text-sm">No sales data for period</div>
        <?php else: ?>
        <canvas id="frDonutChart" height="180"></canvas>
        <?php endif; ?>
    </div>
</div>

<!-- Expense breakdown -->
<?php if(!empty($expCats)): ?>
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
    <h3 class="font-extrabold text-slate-700 mb-4">Top Expense Categories</h3>
    <div class="space-y-2">
    <?php foreach($expCats as $ec):
        $pct=$totalExpense>0 ? round($ec['total']/$totalExpense*100) : 0;
    ?>
    <div class="flex items-center gap-3">
        <div class="w-32 text-xs font-bold text-slate-600 truncate"><?= xss_clean($ec['cat']) ?></div>
        <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden"><div class="bg-rose-500 h-3 rounded-full" style="width:<?= $pct ?>%"></div></div>
        <div class="text-xs font-bold text-rose-600 w-24 text-right"><?= $currencySymbol ?> <?= number_format($ec['total'],2) ?> (<?= $pct ?>%)</div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Report links grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
<?php foreach($reportLinks as [$pg,$title,$icon,$cls,$desc]): ?>
<a href="/app/<?= $pg ?>" class="bg-white rounded-2xl soft-shadow border <?= $cls ?> p-5 hover:scale-[1.02] transition-transform group block">
    <div class="flex items-center gap-3 mb-2">
        <div class="w-10 h-10 rounded-xl <?= explode(' ',$cls)[0] ?> flex items-center justify-center group-hover:scale-110 transition-transform">
            <i class="fa-solid <?= $icon ?> text-lg"></i>
        </div>
        <h3 class="font-extrabold text-slate-800 text-sm"><?= $title ?></h3>
    </div>
    <p class="text-xs text-slate-500"><?= $desc ?></p>
</a>
<?php endforeach; ?>
</div>
</div>
<script>
new Chart(document.getElementById('frTrendChart'),{data:{labels:<?= json_encode($chartMonths) ?>,datasets:[{type:'bar',label:'Income',data:<?= json_encode($monthlyInc) ?>,backgroundColor:'rgba(16,185,129,0.7)',borderRadius:4,order:2},{type:'bar',label:'Expenses',data:<?= json_encode($monthlyExp) ?>,backgroundColor:'rgba(244,63,94,0.7)',borderRadius:4,order:2},{type:'line',label:'Net',data:<?= json_encode(array_map(fn($i,$e)=>round($i-$e,2),$monthlyInc,$monthlyExp)) ?>,borderColor:'#4f46e5',backgroundColor:'rgba(79,70,229,0.08)',borderWidth:3,tension:0.3,fill:true,order:1}]},options:{responsive:true,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top'}}}});
<?php if(!empty($svcData)): ?>
new Chart(document.getElementById('frDonutChart'),{type:'doughnut',data:{labels:<?= json_encode(array_keys($svcData)) ?>,datasets:[{data:<?= json_encode(array_values($svcData)) ?>,backgroundColor:['#4f46e5','#10b981','#f59e0b','#3b82f6','#8b5cf6'],borderWidth:2}]},options:{responsive:true,plugins:{legend:{position:'bottom'}}}});
<?php endif; ?>
</script>
