<?php
// VAT / Tax Report
$accYear  = (int)($_GET['year']  ?? date('Y'));
$accMonth = (int)($_GET['month'] ?? 0);
$printMode= isset($_GET['print']);

if($accMonth > 0) {
    $accFrom = sprintf('%04d-%02d-01', $accYear, $accMonth);
    $accTo   = date('Y-m-t', strtotime($accFrom));
    $periodLabel = date('F Y', strtotime($accFrom));
} else {
    $accFrom = "$accYear-01-01"; $accTo = "$accYear-12-31"; $periodLabel = "Full Year $accYear";
}

// Get VAT rate from settings
$vatRate = (float)($conn->query("SELECT setting_value FROM acc_settings WHERE agency_id=$agency_id AND setting_key='vat_rate'")->fetchColumn()?:0);
$agencyInfo = $conn->query("SELECT * FROM agencies WHERE id=$agency_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$monthNames=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$completedStatuses = "'Completed','Paid','Confirmed'";

// Output VAT (on sales)
$salesTax=[];
$totalSalesBase=0; $totalOutputVat=0;
foreach(['passports'=>'Passport Processing','visas'=>'Visa Processing','tickets'=>'Air Ticket','umrah'=>'Umrah Package','tours'=>'Tour Package'] as $tbl=>$cat) {
    $s=$conn->prepare("SELECT SUM(selling_price) FROM $tbl WHERE agency_id=? AND status IN ($completedStatuses) AND transaction_date BETWEEN ? AND ?");
    $s->execute([$agency_id,$accFrom,$accTo]); $base=(float)$s->fetchColumn();
    $vat=round($base*($vatRate/100),2);
    if($base>0){ $salesTax[$cat]=['base'=>$base,'vat'=>$vat]; $totalSalesBase+=$base; $totalOutputVat+=$vat; }
}
$s=$conn->prepare("SELECT COALESCE(category,'Income') as cat, SUM(amount) as base FROM acc_income WHERE agency_id=? AND income_date BETWEEN ? AND ? GROUP BY cat");
$s->execute([$agency_id,$accFrom,$accTo]);
while($r=$s->fetch(PDO::FETCH_ASSOC)){
    $vat=round($r['base']*($vatRate/100),2);
    $salesTax['Manual: '.$r['cat']]=['base'=>$r['base'],'vat'=>$vat];
    $totalSalesBase+=$r['base']; $totalOutputVat+=$vat;
}

// Input VAT (on expenses)
$purchaseTax=[]; $totalExpBase=0; $totalInputVat=0;
$s=$conn->prepare("SELECT COALESCE(category,'Expense') as cat, SUM(amount) as base FROM accounting_expenses WHERE agency_id=? AND expense_date BETWEEN ? AND ? GROUP BY cat");
$s->execute([$agency_id,$accFrom,$accTo]);
while($r=$s->fetch(PDO::FETCH_ASSOC)){
    $vat=round($r['base']*($vatRate/100),2);
    $purchaseTax[$r['cat']]=['base'=>$r['base'],'vat'=>$vat];
    $totalExpBase+=$r['base']; $totalInputVat+=$vat;
}

$netVat = $totalOutputVat - $totalInputVat;

// Monthly VAT chart
$monthlyOutputVat=[]; $monthlyInputVat=[];
for($m=1;$m<=12;$m++){
    $mFrom=sprintf('%04d-%02d-01',$accYear,$m); $mTo=date('Y-m-t',strtotime($mFrom));
    $mSales=0; foreach(['passports','visas','tickets','umrah','tours'] as $tbl){ $s=$conn->prepare("SELECT SUM(selling_price) FROM $tbl WHERE agency_id=? AND status IN ($completedStatuses) AND transaction_date BETWEEN ? AND ?"); $s->execute([$agency_id,$mFrom,$mTo]); $mSales+=(float)$s->fetchColumn(); }
    $s=$conn->prepare("SELECT SUM(amount) FROM acc_income WHERE agency_id=? AND income_date BETWEEN ? AND ?"); $s->execute([$agency_id,$mFrom,$mTo]); $mSales+=(float)$s->fetchColumn();
    $s=$conn->prepare("SELECT SUM(amount) FROM accounting_expenses WHERE agency_id=? AND expense_date BETWEEN ? AND ?"); $s->execute([$agency_id,$mFrom,$mTo]); $mExp=(float)$s->fetchColumn();
    $monthlyOutputVat[]=round($mSales*($vatRate/100),2);
    $monthlyInputVat[]=round($mExp*($vatRate/100),2);
}
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-percent text-indigo-500"></i> VAT / Tax Report</h2><p class="text-sm text-slate-500 mt-1">VAT/tax summary for <?= $periodLabel ?>. Rate: <?= $vatRate ?>%</p></div>
    <button onclick="window.print()" class="bg-rose-50 text-rose-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-rose-100 transition flex items-center gap-2"><i class="fa-solid fa-print"></i> Print</button>
</div>

<form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_vat">
    <div><label class="block text-xs font-bold text-slate-500 mb-1">Year</label><select name="year" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><?php for($y=date('Y');$y>=date('Y')-5;$y--): ?><option value="<?= $y ?>" <?= $accYear==$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?></select></div>
    <div><label class="block text-xs font-bold text-slate-500 mb-1">Month</label><select name="month" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="0" <?= $accMonth===0?'selected':'' ?>>Full Year</option><?php foreach($monthNames as $mi=>$mn): ?><option value="<?= $mi+1 ?>" <?= $accMonth===$mi+1?'selected':'' ?>><?= $mn ?></option><?php endforeach; ?></select></div>
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold self-end">Apply</button>
    <a href="/app/acc_settings" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-xl text-sm font-bold self-end">Set VAT Rate</a>
</form>

<div class="grid grid-cols-3 gap-4">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-emerald-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Output VAT (Sales)</p><p class="text-2xl font-black text-emerald-600"><?= $currencySymbol ?> <?= number_format($totalOutputVat,2) ?></p><p class="text-xs text-slate-400">On <?= $currencySymbol ?> <?= number_format($totalSalesBase,2) ?> sales</p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-rose-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Input VAT (Expenses)</p><p class="text-2xl font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($totalInputVat,2) ?></p><p class="text-xs text-slate-400">On <?= $currencySymbol ?> <?= number_format($totalExpBase,2) ?> expenses</p></div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 <?= $netVat>=0?'border-l-indigo-400':'border-l-red-400' ?>"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Net VAT Payable</p><p class="text-2xl font-black <?= $netVat>=0?'text-indigo-600':'text-red-600' ?>"><?= $currencySymbol ?> <?= number_format($netVat,2) ?></p><p class="text-xs text-slate-400"><?= $netVat>=0?'Payable to authority':'Receivable / Credit' ?></p></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <!-- Output VAT table -->
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="p-4 bg-emerald-50 border-b border-emerald-100"><h3 class="font-extrabold text-emerald-800 text-sm uppercase tracking-wider">Output VAT — Sales</h3></div>
        <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-100"><tr class="text-left"><th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Category</th><th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Base Amount</th><th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-emerald-500">VAT (<?= $vatRate ?>%)</th></tr></thead>
        <tbody class="divide-y divide-slate-50">
        <?php if(empty($salesTax)): ?><tr><td colspan="3" class="text-center py-8 text-slate-400">No sales in this period.</td></tr>
        <?php else: foreach($salesTax as $cat=>$d): ?>
        <tr class="hover:bg-slate-50"><td class="px-4 py-3 text-slate-600"><?= xss_clean($cat) ?></td><td class="px-4 py-3 font-bold text-slate-700"><?= $currencySymbol ?> <?= number_format($d['base'],2) ?></td><td class="px-4 py-3 font-bold text-emerald-600"><?= $currencySymbol ?> <?= number_format($d['vat'],2) ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
        <tfoot class="bg-slate-50 border-t-2 border-slate-200"><tr><td class="px-4 py-3 font-extrabold text-slate-800">Total</td><td class="px-4 py-3 font-extrabold text-slate-700"><?= $currencySymbol ?> <?= number_format($totalSalesBase,2) ?></td><td class="px-4 py-3 font-extrabold text-emerald-700"><?= $currencySymbol ?> <?= number_format($totalOutputVat,2) ?></td></tr></tfoot>
        </table></div>
    </div>
    <!-- Input VAT table -->
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="p-4 bg-rose-50 border-b border-rose-100"><h3 class="font-extrabold text-rose-800 text-sm uppercase tracking-wider">Input VAT — Expenses</h3></div>
        <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-100"><tr class="text-left"><th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Category</th><th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Base Amount</th><th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-rose-500">VAT (<?= $vatRate ?>%)</th></tr></thead>
        <tbody class="divide-y divide-slate-50">
        <?php if(empty($purchaseTax)): ?><tr><td colspan="3" class="text-center py-8 text-slate-400">No expenses in this period.</td></tr>
        <?php else: foreach($purchaseTax as $cat=>$d): ?>
        <tr class="hover:bg-slate-50"><td class="px-4 py-3 text-slate-600"><?= xss_clean($cat) ?></td><td class="px-4 py-3 font-bold text-slate-700"><?= $currencySymbol ?> <?= number_format($d['base'],2) ?></td><td class="px-4 py-3 font-bold text-rose-600"><?= $currencySymbol ?> <?= number_format($d['vat'],2) ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody>
        <tfoot class="bg-slate-50 border-t-2 border-slate-200"><tr><td class="px-4 py-3 font-extrabold text-slate-800">Total</td><td class="px-4 py-3 font-extrabold text-slate-700"><?= $currencySymbol ?> <?= number_format($totalExpBase,2) ?></td><td class="px-4 py-3 font-extrabold text-rose-700"><?= $currencySymbol ?> <?= number_format($totalInputVat,2) ?></td></tr></tfoot>
        </table></div>
    </div>
</div>

<!-- Monthly chart -->
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
    <h3 class="font-extrabold text-slate-700 mb-4">Monthly VAT Summary — <?= $accYear ?></h3>
    <canvas id="vatChart" height="80"></canvas>
</div>
<script>
new Chart(document.getElementById('vatChart'),{type:'bar',data:{labels:<?= json_encode($monthNames) ?>,datasets:[{label:'Output VAT',data:<?= json_encode($monthlyOutputVat) ?>,backgroundColor:'#10b981',borderRadius:4,stack:'s'},{label:'Input VAT',data:<?= json_encode($monthlyInputVat) ?>,backgroundColor:'#f43f5e',borderRadius:4,stack:'s'}]},options:{responsive:true,plugins:{legend:{position:'top'}}}});
</script>
</div>
