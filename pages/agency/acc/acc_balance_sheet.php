<?php
// Balance Sheet
$asOfDate = $_GET['as_of'] ?? date('Y-m-d');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$asOfDate)) $asOfDate=date('Y-m-d');
$agencyInfo = $conn->query("SELECT * FROM agencies WHERE id=$agency_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$completedStatuses = "'Completed','Paid','Confirmed'";

// ── ASSETS ────────────────────────────────────────────────────────────────
$cashOpening=(float)$conn->query("SELECT setting_value FROM acc_settings WHERE agency_id=$agency_id AND setting_key='opening_cash_balance'")->fetchColumn();
$cashIn=(float)$conn->query("SELECT SUM(amount) FROM acc_cash_transactions WHERE agency_id=$agency_id AND transaction_type='in' AND transaction_date<='$asOfDate'")->fetchColumn();
$cashOut=(float)$conn->query("SELECT SUM(amount) FROM acc_cash_transactions WHERE agency_id=$agency_id AND transaction_type='out' AND transaction_date<='$asOfDate'")->fetchColumn();
$cashBalance=$cashOpening+$cashIn-$cashOut;

$bankOpening=(float)$conn->query("SELECT setting_value FROM acc_settings WHERE agency_id=$agency_id AND setting_key='opening_bank_balance'")->fetchColumn();
$bankDep=(float)$conn->query("SELECT SUM(amount) FROM acc_bank_transactions WHERE agency_id=$agency_id AND transaction_type='deposit' AND transaction_date<='$asOfDate'")->fetchColumn();
$bankWith=(float)$conn->query("SELECT SUM(amount) FROM acc_bank_transactions WHERE agency_id=$agency_id AND transaction_type IN ('withdrawal','transfer') AND transaction_date<='$asOfDate'")->fetchColumn();
$bankBalance=$bankOpening+$bankDep-$bankWith;

$arBalance=(float)$conn->query("SELECT SUM(due_amount) FROM invoices WHERE agency_id=$agency_id AND due_amount>0")->fetchColumn();
$coaAssets=$conn->query("SELECT account_name,opening_balance FROM acc_chart_of_accounts WHERE agency_id=$agency_id AND account_type='Asset' AND is_active=1 ORDER BY account_code")->fetchAll(PDO::FETCH_ASSOC);
$coaAssetsTotal=array_sum(array_column($coaAssets,'opening_balance'));

$currentAssets=['Cash in Hand'=>$cashBalance,'Cash at Bank'=>$bankBalance,'Accounts Receivable'=>$arBalance];
$totalCurrentAssets=array_sum($currentAssets);
$totalAssets=$totalCurrentAssets+$coaAssetsTotal;

// ── LIABILITIES ───────────────────────────────────────────────────────────
$apBalance=(float)$conn->query("SELECT SUM(due_amount) FROM acc_payables WHERE agency_id=$agency_id AND status!='Paid'")->fetchColumn();
$coaLiab=$conn->query("SELECT account_name,opening_balance FROM acc_chart_of_accounts WHERE agency_id=$agency_id AND account_type='Liability' AND is_active=1 ORDER BY account_code")->fetchAll(PDO::FETCH_ASSOC);
$coaLiabTotal=array_sum(array_column($coaLiab,'opening_balance'));
$currentLiabilities=['Accounts Payable'=>$apBalance];
$totalCurrentLiab=array_sum($currentLiabilities)+$coaLiabTotal;

// ── EQUITY ────────────────────────────────────────────────────────────────
$coaEquity=$conn->query("SELECT account_name,opening_balance FROM acc_chart_of_accounts WHERE agency_id=$agency_id AND account_type='Equity' AND is_active=1 ORDER BY account_code")->fetchAll(PDO::FETCH_ASSOC);
$openingEquity=array_sum(array_column($coaEquity,'opening_balance'));

// Retained earnings = all net profit to date
$histIncome=0;
foreach(['passports','visas','tickets','umrah','tours'] as $tbl){ $s=$conn->prepare("SELECT SUM(selling_price-service_cost) FROM $tbl WHERE agency_id=? AND status IN ($completedStatuses) AND transaction_date<='$asOfDate'"); $s->execute([$agency_id]); $histIncome+=(float)$s->fetchColumn(); }
$s=$conn->prepare("SELECT SUM(amount) FROM acc_income WHERE agency_id=? AND income_date<=?"); $s->execute([$agency_id,$asOfDate]); $histIncome+=(float)$s->fetchColumn();
$s=$conn->prepare("SELECT SUM(amount) FROM accounting_expenses WHERE agency_id=? AND expense_date<=?"); $s->execute([$agency_id,$asOfDate]); $histExp=(float)$s->fetchColumn();
$retainedEarnings=$histIncome-$histExp;

$totalEquity=$openingEquity+$retainedEarnings;
$totalLiabEquity=$totalCurrentLiab+$totalEquity;
$balanced=round($totalAssets,2)===round($totalLiabEquity,2);
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-scale-balanced text-indigo-500"></i> Balance Sheet</h2><p class="text-sm text-slate-500 mt-1">Financial position as of <?= date('d M Y',strtotime($asOfDate)) ?>.</p></div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-rose-50 text-rose-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-rose-100 transition flex items-center gap-2"><i class="fa-solid fa-print"></i> Print</button>
    </div>
</div>
<form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_balance_sheet">
    <div><label class="block text-xs font-bold text-slate-500 mb-1">As of Date</label><input type="date" name="as_of" value="<?= $asOfDate ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold self-end">Apply</button>
</form>

<?php if(!$balanced): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center gap-3 text-amber-700 text-sm font-bold"><i class="fa-solid fa-triangle-exclamation"></i> Note: Balance sheet may not balance as opening balances in Chart of Accounts may not cover all historical entries.</div>
<?php endif; ?>

<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
<div class="bg-gradient-to-r from-slate-800 to-indigo-900 text-white px-6 py-4 flex justify-between items-center">
    <div><p class="text-xs text-white/70 uppercase tracking-wider">Balance Sheet</p><p class="font-black text-lg"><?= xss_clean($agencyInfo['agency_name']??'Agency') ?></p></div>
    <div class="text-right"><p class="text-xs text-white/70">As of</p><p class="font-bold"><?= date('d M Y',strtotime($asOfDate)) ?></p></div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-slate-100">
<!-- ASSETS column -->
<div class="p-6 space-y-4">
    <h3 class="font-extrabold text-slate-800 uppercase text-sm tracking-wider flex items-center gap-2"><i class="fa-solid fa-coins text-blue-500"></i> Assets</h3>
    <div>
        <p class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-2">Current Assets</p>
        <?php foreach($currentAssets as $name=>$amt): ?>
        <div class="flex justify-between py-1.5 text-sm hover:bg-slate-50 rounded-lg px-2"><span class="text-slate-600"><?= $name ?></span><span class="font-bold text-blue-700"><?= $currencySymbol ?> <?= number_format($amt,2) ?></span></div>
        <?php endforeach; ?>
        <div class="flex justify-between py-1.5 px-2 bg-blue-50 rounded-lg mt-1 font-bold"><span class="text-slate-700 text-sm">Total Current Assets</span><span class="text-blue-700"><?= $currencySymbol ?> <?= number_format($totalCurrentAssets,2) ?></span></div>
    </div>
    <?php if($coaAssets): ?>
    <div>
        <p class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-2">Fixed Assets (Opening)</p>
        <?php foreach($coaAssets as $a): ?>
        <div class="flex justify-between py-1.5 text-sm hover:bg-slate-50 rounded-lg px-2"><span class="text-slate-600"><?= xss_clean($a['account_name']) ?></span><span class="font-bold text-blue-700"><?= $currencySymbol ?> <?= number_format($a['opening_balance'],2) ?></span></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="flex justify-between py-3 px-4 bg-blue-700 text-white rounded-xl font-extrabold"><span>TOTAL ASSETS</span><span><?= $currencySymbol ?> <?= number_format($totalAssets,2) ?></span></div>
</div>
<!-- LIABILITIES + EQUITY column -->
<div class="p-6 space-y-4">
    <h3 class="font-extrabold text-slate-800 uppercase text-sm tracking-wider flex items-center gap-2"><i class="fa-solid fa-file-invoice text-amber-500"></i> Liabilities & Equity</h3>
    <div>
        <p class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-2">Current Liabilities</p>
        <?php foreach($currentLiabilities as $name=>$amt): ?>
        <div class="flex justify-between py-1.5 text-sm hover:bg-slate-50 rounded-lg px-2"><span class="text-slate-600"><?= $name ?></span><span class="font-bold text-amber-600"><?= $currencySymbol ?> <?= number_format($amt,2) ?></span></div>
        <?php endforeach; ?>
        <?php foreach($coaLiab as $l): ?><div class="flex justify-between py-1.5 text-sm hover:bg-slate-50 rounded-lg px-2"><span class="text-slate-600"><?= xss_clean($l['account_name']) ?></span><span class="font-bold text-amber-600"><?= $currencySymbol ?> <?= number_format($l['opening_balance'],2) ?></span></div><?php endforeach; ?>
        <div class="flex justify-between py-1.5 px-2 bg-amber-50 rounded-lg mt-1 font-bold"><span class="text-slate-700 text-sm">Total Liabilities</span><span class="text-amber-700"><?= $currencySymbol ?> <?= number_format($totalCurrentLiab,2) ?></span></div>
    </div>
    <div>
        <p class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-2">Equity</p>
        <?php foreach($coaEquity as $e): ?><div class="flex justify-between py-1.5 text-sm hover:bg-slate-50 rounded-lg px-2"><span class="text-slate-600"><?= xss_clean($e['account_name']) ?></span><span class="font-bold text-violet-600"><?= $currencySymbol ?> <?= number_format($e['opening_balance'],2) ?></span></div><?php endforeach; ?>
        <div class="flex justify-between py-1.5 text-sm hover:bg-slate-50 rounded-lg px-2"><span class="text-slate-600">Retained Earnings</span><span class="font-bold text-violet-600"><?= $currencySymbol ?> <?= number_format($retainedEarnings,2) ?></span></div>
        <div class="flex justify-between py-1.5 px-2 bg-violet-50 rounded-lg mt-1 font-bold"><span class="text-slate-700 text-sm">Total Equity</span><span class="text-violet-700"><?= $currencySymbol ?> <?= number_format($totalEquity,2) ?></span></div>
    </div>
    <div class="flex justify-between py-3 px-4 <?= $balanced?'bg-violet-700':'bg-amber-600' ?> text-white rounded-xl font-extrabold"><span>TOTAL LIAB + EQUITY</span><span><?= $currencySymbol ?> <?= number_format($totalLiabEquity,2) ?></span></div>
</div>
</div>
</div>
</div>
