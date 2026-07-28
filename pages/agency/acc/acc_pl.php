<?php
// Profit & Loss Report
$accYear  = (int)($_GET['year']  ?? date('Y'));
$accMonth = (int)($_GET['month'] ?? 0); // 0 = full year
$printMode= isset($_GET['print']);

if($accMonth > 0) {
    $accFrom = sprintf('%04d-%02d-01', $accYear, $accMonth);
    $accTo   = date('Y-m-t', strtotime($accFrom));
    $periodLabel = date('F Y', strtotime($accFrom));
} else {
    $accFrom = "$accYear-01-01"; $accTo = "$accYear-12-31"; $periodLabel = "Full Year $accYear";
}

$completedStatuses = "'Completed','Paid','Confirmed'";
$agencyInfo = $conn->query("SELECT * FROM agencies WHERE id=$agency_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// ── INCOME ────────────────────────────────────────────────────────────────
$incomeGroups = [];
$totalIncome  = 0;

// Auto-sales
$salesBySvc = [];
foreach(['passports'=>'Air Ticket / Passport','visas'=>'Visa Processing','tickets'=>'Air Ticket','umrah'=>'Umrah Package','tours'=>'Tour Package'] as $tbl=>$cat) {
    $s=$conn->prepare("SELECT SUM(selling_price-service_cost) FROM $tbl WHERE agency_id=? AND status IN ($completedStatuses) AND transaction_date BETWEEN ? AND ?");
    $s->execute([$agency_id,$accFrom,$accTo]); $amt=(float)$s->fetchColumn();
    if($amt!=0){ $salesBySvc[$cat]=($salesBySvc[$cat]??0)+$amt; }
}
if($salesBySvc) {
    $incomeGroups['Service Sales'] = $salesBySvc;
    $totalIncome += array_sum($salesBySvc);
}

// Manual income by category
$manRows=$conn->prepare("SELECT COALESCE(category,'Other Income') as cat, SUM(amount) as total FROM acc_income WHERE agency_id=? AND income_date BETWEEN ? AND ? GROUP BY cat ORDER BY total DESC");
$manRows->execute([$agency_id,$accFrom,$accTo]); $manRows=$manRows->fetchAll(PDO::FETCH_ASSOC);
if($manRows) {
    $manGroup=[];
    foreach($manRows as $m){ $manGroup[$m['cat']]=(float)$m['total']; $totalIncome+=(float)$m['total']; }
    $incomeGroups['Other Income'] = $manGroup;
}

// ── EXPENSES ─────────────────────────────────────────────────────────────
$expenseGroups = [];
$totalExpense  = 0;

$expRows=$conn->prepare("SELECT COALESCE(category,'Uncategorized') as cat, SUM(amount) as total FROM accounting_expenses WHERE agency_id=? AND expense_date BETWEEN ? AND ? GROUP BY cat ORDER BY total DESC");
$expRows->execute([$agency_id,$accFrom,$accTo]); $expRows=$expRows->fetchAll(PDO::FETCH_ASSOC);
if($expRows) {
    $expGroup=[];
    foreach($expRows as $e){ $expGroup[$e['cat']]=(float)$e['total']; $totalExpense+=(float)$e['total']; }
    $expenseGroups['Operating Expenses'] = $expGroup;
}

$grossProfit  = $totalIncome - $totalExpense;
$taxRate      = (float)$conn->query("SELECT setting_value FROM acc_settings WHERE agency_id=$agency_id AND setting_key='vat_rate'")->fetchColumn();
$taxAmount    = $grossProfit > 0 ? round($grossProfit * ($taxRate/100), 2) : 0;
$netProfit    = $grossProfit - $taxAmount;

// Monthly columns for 12-month view
$monthlyData = [];
if(!$printMode) {
    for($m=1;$m<=12;$m++) {
        $mFrom=sprintf('%04d-%02d-01',$accYear,$m); $mTo=date('Y-m-t',strtotime($mFrom));
        $mInc=0; foreach(['passports','visas','tickets','umrah','tours'] as $tbl){ $s=$conn->prepare("SELECT SUM(selling_price-service_cost) FROM $tbl WHERE agency_id=? AND status IN ($completedStatuses) AND transaction_date BETWEEN ? AND ?"); $s->execute([$agency_id,$mFrom,$mTo]); $mInc+=(float)$s->fetchColumn(); }
        $s=$conn->prepare("SELECT SUM(amount) FROM acc_income WHERE agency_id=? AND income_date BETWEEN ? AND ?"); $s->execute([$agency_id,$mFrom,$mTo]); $mInc+=(float)$s->fetchColumn();
        $s=$conn->prepare("SELECT SUM(amount) FROM accounting_expenses WHERE agency_id=? AND expense_date BETWEEN ? AND ?"); $s->execute([$agency_id,$mFrom,$mTo]); $mExp=(float)$s->fetchColumn();
        $monthlyData[]=round($mInc-$mExp,2);
    }
}
$monthNames=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-chart-line text-indigo-500"></i> Profit & Loss</h2><p class="text-sm text-slate-500 mt-1">Income statement for <?= $periodLabel ?>.</p></div>
    <div class="flex gap-2">
        <a href="?route=app&page=acc_pl&year=<?= $accYear ?>&month=<?= $accMonth ?>&print=1" target="_blank" class="bg-rose-50 text-rose-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-rose-100 transition flex items-center gap-2"><i class="fa-solid fa-print"></i> Print</a>
    </div>
</div>

<!-- Filter -->
<form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_pl">
    <div><label class="block text-xs font-bold text-slate-500 mb-1">Year</label><select name="year" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><?php for($y=date('Y');$y>=date('Y')-5;$y--): ?><option value="<?= $y ?>" <?= $accYear==$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select></div>
    <div><label class="block text-xs font-bold text-slate-500 mb-1">Month</label><select name="month" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="0" <?= $accMonth===0?'selected':'' ?>>Full Year</option><?php foreach($monthNames as $mi=>$mn): ?><option value="<?= $mi+1 ?>" <?= $accMonth===$mi+1?'selected':'' ?>><?= $mn ?></option><?php endforeach; ?></select></div>
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold self-end">Apply</button>
</form>

<!-- P&L Statement -->
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden" id="plStatement">
    <div class="bg-gradient-to-r from-slate-800 to-indigo-900 text-white px-6 py-4">
        <div class="flex justify-between items-center">
            <div><p class="text-xs text-white/70 uppercase tracking-wider font-bold">Profit & Loss Statement</p><p class="text-lg font-black mt-0.5"><?= xss_clean($agencyInfo['agency_name']??'Agency') ?></p></div>
            <div class="text-right"><p class="text-xs text-white/70 font-bold"><?= $periodLabel ?></p><p class="text-sm font-bold text-white/80"><?= date('d M Y',strtotime($accFrom)) ?> to <?= date('d M Y',strtotime($accTo)) ?></p></div>
        </div>
    </div>
    <div class="p-6">
        <!-- Income section -->
        <div class="mb-5">
            <div class="flex items-center gap-2 mb-3"><div class="w-1 h-6 bg-emerald-500 rounded-full"></div><h3 class="font-extrabold text-slate-800 uppercase text-sm tracking-wide">Income</h3></div>
            <?php foreach($incomeGroups as $groupName=>$items): ?>
            <div class="mb-3"><p class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-2 ml-2"><?= $groupName ?></p>
            <?php foreach($items as $name=>$amt): ?>
            <div class="flex justify-between items-center py-1.5 px-2 hover:bg-slate-50 rounded-lg"><span class="text-sm text-slate-600"><?= xss_clean($name) ?></span><span class="text-sm font-bold text-emerald-600"><?= $currencySymbol ?> <?= number_format($amt,2) ?></span></div>
            <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <div class="flex justify-between items-center py-2 px-2 bg-emerald-50 rounded-xl border border-emerald-100 mt-1"><span class="font-extrabold text-slate-800 uppercase text-sm">Total Income</span><span class="font-extrabold text-emerald-700 text-lg"><?= $currencySymbol ?> <?= number_format($totalIncome,2) ?></span></div>
        </div>
        <div class="border-t border-slate-100 my-4"></div>
        <!-- Expense section -->
        <div class="mb-5">
            <div class="flex items-center gap-2 mb-3"><div class="w-1 h-6 bg-rose-500 rounded-full"></div><h3 class="font-extrabold text-slate-800 uppercase text-sm tracking-wide">Expenses</h3></div>
            <?php foreach($expenseGroups as $groupName=>$items): ?>
            <div class="mb-3"><p class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-2 ml-2"><?= $groupName ?></p>
            <?php foreach($items as $name=>$amt): ?>
            <div class="flex justify-between items-center py-1.5 px-2 hover:bg-slate-50 rounded-lg"><span class="text-sm text-slate-600"><?= xss_clean($name) ?></span><span class="text-sm font-bold text-rose-600"><?= $currencySymbol ?> <?= number_format($amt,2) ?></span></div>
            <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <div class="flex justify-between items-center py-2 px-2 bg-rose-50 rounded-xl border border-rose-100 mt-1"><span class="font-extrabold text-slate-800 uppercase text-sm">Total Expenses</span><span class="font-extrabold text-rose-700 text-lg"><?= $currencySymbol ?> <?= number_format($totalExpense,2) ?></span></div>
        </div>
        <div class="border-t-2 border-slate-200 my-4"></div>
        <!-- Net profit -->
        <div class="space-y-2">
            <div class="flex justify-between items-center py-2 px-4 bg-slate-50 rounded-xl"><span class="font-bold text-slate-700">Gross Profit</span><span class="font-extrabold <?= $grossProfit>=0?'text-indigo-700':'text-red-600' ?>"><?= $currencySymbol ?> <?= number_format($grossProfit,2) ?></span></div>
            <?php if($taxRate > 0): ?>
            <div class="flex justify-between items-center py-2 px-4"><span class="text-sm text-slate-500">Tax / VAT (<?= $taxRate ?>%)</span><span class="font-bold text-amber-600">−<?= $currencySymbol ?> <?= number_format($taxAmount,2) ?></span></div>
            <?php endif; ?>
            <div class="flex justify-between items-center py-3 px-4 rounded-xl border-2 <?= $netProfit>=0?'bg-indigo-50 border-indigo-200':'bg-red-50 border-red-200' ?>">
                <span class="font-extrabold text-slate-800 text-lg uppercase tracking-wide">Net Profit</span>
                <span class="font-black text-2xl <?= $netProfit>=0?'text-indigo-700':'text-red-700' ?>"><?= $currencySymbol ?> <?= number_format($netProfit,2) ?></span>
            </div>
            <?php if($totalIncome>0): ?>
            <p class="text-xs text-slate-400 text-right">Profit Margin: <?= round(($netProfit/$totalIncome)*100,1) ?>%</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Monthly chart -->
<?php if(!$printMode): ?>
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
    <h3 class="font-extrabold text-slate-700 mb-4">Monthly Net Profit — <?= $accYear ?></h3>
    <canvas id="plChart" height="80"></canvas>
</div>
<script>
new Chart(document.getElementById('plChart'),{type:'bar',data:{labels:<?= json_encode($monthNames) ?>,datasets:[{label:'Net Profit',data:<?= json_encode($monthlyData) ?>,backgroundColor:<?= json_encode(array_map(fn($v)=>$v>=0?'#4f46e5':'#f43f5e',$monthlyData)) ?>,borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{ticks:{callback:v=>'<?= $currencySymbol ?>'+v.toLocaleString()}}}}});
</script>
<?php endif; ?>
</div>
