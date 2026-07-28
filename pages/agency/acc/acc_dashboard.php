<?php
// ── Finance Dashboard ──────────────────────────────────────────────────────
$accRange = $_GET['range'] ?? 'this_month';
$todayStr = date('Y-m-d');
switch ($accRange) {
    case 'today':      $accFrom = $todayStr; $accTo = $todayStr; break;
    case 'this_week':  $accFrom = date('Y-m-d', strtotime('monday this week')); $accTo = $todayStr; break;
    case 'this_year':  $accFrom = date('Y-01-01'); $accTo = $todayStr; break;
    case 'last_month': $accFrom = date('Y-m-01', strtotime('first day of last month')); $accTo = date('Y-m-t', strtotime('last day of last month')); break;
    case 'custom':     $accFrom = normalizeReportDate($_GET['from_date'] ?? null, date('Y-m-01')); $accTo = normalizeReportDate($_GET['to_date'] ?? null, $todayStr); break;
    default:           $accRange = 'this_month'; $accFrom = date('Y-m-01'); $accTo = $todayStr;
}
if ($accFrom > $accTo) { [$accFrom,$accTo] = [$accTo,$accFrom]; }

// Helper: get acc setting
function accSetting($conn, $agency_id, $key, $default = '') {
    $s = $conn->prepare("SELECT setting_value FROM acc_settings WHERE agency_id=? AND setting_key=?");
    $s->execute([$agency_id,$key]); return $s->fetchColumn() ?: $default;
}

// ── Income: Sales Net Profit (auto) ───────────────────────────────────────
$completedStatuses = "'Completed','Paid','Confirmed'";
$salesIncome = 0;
foreach (['passports','visas','tickets','umrah','tours'] as $tbl) {
    $s=$conn->prepare("SELECT SUM(selling_price - service_cost) FROM $tbl WHERE agency_id=? AND status IN ($completedStatuses) AND transaction_date BETWEEN ? AND ?");
    $s->execute([$agency_id,$accFrom,$accTo]);
    $salesIncome += (float)$s->fetchColumn();
}

// ── Income: Manual income records ─────────────────────────────────────────
$manualIncomeRow = $conn->prepare("SELECT SUM(amount) FROM acc_income WHERE agency_id=? AND income_date BETWEEN ? AND ?");
$manualIncomeRow->execute([$agency_id,$accFrom,$accTo]);
$manualIncome = (float)$manualIncomeRow->fetchColumn();
$totalIncome = $salesIncome + $manualIncome;

// ── Expenses ─────────────────────────────────────────────────────────────
$expRow = $conn->prepare("SELECT SUM(amount) FROM accounting_expenses WHERE agency_id=? AND expense_date BETWEEN ? AND ?");
$expRow->execute([$agency_id,$accFrom,$accTo]);
$totalExpense = (float)$expRow->fetchColumn();

$netProfit = $totalIncome - $totalExpense;

// ── Accounts Receivable ───────────────────────────────────────────────────
$arRow = $conn->prepare("SELECT COUNT(*) as cnt, SUM(due_amount) as due FROM invoices WHERE agency_id=? AND due_amount > 0");
$arRow->execute([$agency_id]); $arData = $arRow->fetch(PDO::FETCH_ASSOC);

// ── Accounts Payable ─────────────────────────────────────────────────────
$apRow = $conn->prepare("SELECT COUNT(*) as cnt, SUM(due_amount) as due FROM acc_payables WHERE agency_id=? AND status != 'Paid'");
$apRow->execute([$agency_id]); $apData = $apRow->fetch(PDO::FETCH_ASSOC);

// ── Cash Balance ──────────────────────────────────────────────────────────
$openCash = (float)accSetting($conn,$agency_id,'opening_cash_balance','0');
$cashIn   = (float)$conn->prepare("SELECT SUM(amount) FROM acc_cash_transactions WHERE agency_id=? AND transaction_type='in'")->execute([$agency_id]) ? $conn->query("SELECT SUM(amount) FROM acc_cash_transactions WHERE agency_id=$agency_id AND transaction_type='in'")->fetchColumn() : 0;
$cashOut  = (float)$conn->query("SELECT SUM(amount) FROM acc_cash_transactions WHERE agency_id=$agency_id AND transaction_type='out'")->fetchColumn();
$cashBalance = $openCash + $cashIn - $cashOut;

// ── Bank Balance ──────────────────────────────────────────────────────────
$openBank   = (float)accSetting($conn,$agency_id,'opening_bank_balance','0');
$bankDepos  = (float)$conn->query("SELECT SUM(amount) FROM acc_bank_transactions WHERE agency_id=$agency_id AND transaction_type='deposit'")->fetchColumn();
$bankWithd  = (float)$conn->query("SELECT SUM(amount) FROM acc_bank_transactions WHERE agency_id=$agency_id AND transaction_type='withdrawal'")->fetchColumn();
$bankBalance= $openBank + $bankDepos - $bankWithd;

// ── Monthly chart (12 months rolling) ────────────────────────────────────
$chartMonths=[]; $chartInc=[]; $chartExp=[]; $chartProfit=[];
for ($m=11;$m>=0;$m--) {
    $mFrom = date('Y-m-01', strtotime("-$m months"));
    $mTo   = date('Y-m-t',  strtotime("-$m months"));
    $chartMonths[] = date('M y', strtotime($mFrom));
    $mInc = 0;
    foreach (['passports','visas','tickets','umrah','tours'] as $tbl) {
        $s=$conn->prepare("SELECT SUM(selling_price-service_cost) FROM $tbl WHERE agency_id=? AND status IN ($completedStatuses) AND transaction_date BETWEEN ? AND ?");
        $s->execute([$agency_id,$mFrom,$mTo]); $mInc += (float)$s->fetchColumn();
    }
    $s=$conn->prepare("SELECT SUM(amount) FROM acc_income WHERE agency_id=? AND income_date BETWEEN ? AND ?");
    $s->execute([$agency_id,$mFrom,$mTo]); $mInc += (float)$s->fetchColumn();
    $s=$conn->prepare("SELECT SUM(amount) FROM accounting_expenses WHERE agency_id=? AND expense_date BETWEEN ? AND ?");
    $s->execute([$agency_id,$mFrom,$mTo]); $mExp = (float)$s->fetchColumn();
    $chartInc[]    = round($mInc,2);
    $chartExp[]    = round($mExp,2);
    $chartProfit[] = round($mInc-$mExp,2);
}

// ── Recent activity ───────────────────────────────────────────────────────
$recentInvoices = $conn->query("SELECT id,invoice_number,customer_name,grand_total,due_amount,issue_date FROM invoices WHERE agency_id=$agency_id ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$recentExpenses = $conn->query("SELECT id,expense_date,category,title,amount FROM accounting_expenses WHERE agency_id=$agency_id ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-calculator text-indigo-500"></i> Finance Dashboard</h2>
    <p class="text-sm text-slate-500 mt-1">Complete financial overview of your agency.</p></div>
    <form method="GET" class="flex flex-wrap gap-2 items-center">
        <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_dashboard">
        <select name="range" onchange="this.form.submit()" class="border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-indigo-400 outline-none">
            <?php foreach(['today'=>'Today','this_week'=>'This Week','this_month'=>'This Month','last_month'=>'Last Month','this_year'=>'This Year','custom'=>'Custom'] as $k=>$l): ?>
            <option value="<?= $k ?>" <?= $accRange===$k?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <?php if($accRange==='custom'): ?>
        <input type="date" name="from_date" value="<?= $accFrom ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        <input type="date" name="to_date" value="<?= $accTo ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold">Apply</button>
        <?php endif; ?>
    </form>
</div>

<!-- KPI Cards Row 1 -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-emerald-400">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Income</p>
        <p class="text-2xl font-black text-emerald-600"><?= $currencySymbol ?> <?= number_format($totalIncome,2) ?></p>
        <p class="text-xs text-slate-400 mt-1">Sales + Manual</p>
    </div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-rose-400">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Expenses</p>
        <p class="text-2xl font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($totalExpense,2) ?></p>
        <p class="text-xs text-slate-400 mt-1">Period total</p>
    </div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 <?= $netProfit>=0 ? 'border-l-indigo-400' : 'border-l-red-500' ?>">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Net Profit</p>
        <p class="text-2xl font-black <?= $netProfit>=0?'text-indigo-600':'text-red-600' ?>"><?= $currencySymbol ?> <?= number_format($netProfit,2) ?></p>
        <p class="text-xs text-slate-400 mt-1">Income − Expenses</p>
    </div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-amber-400">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">A/R Outstanding</p>
        <p class="text-2xl font-black text-amber-600"><?= $currencySymbol ?> <?= number_format($arData['due']??0,2) ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= (int)($arData['cnt']??0) ?> invoices due</p>
    </div>
</div>
<!-- KPI Cards Row 2 -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-orange-400">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">A/P Outstanding</p>
        <p class="text-2xl font-black text-orange-600"><?= $currencySymbol ?> <?= number_format($apData['due']??0,2) ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= (int)($apData['cnt']??0) ?> vendors due</p>
    </div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-teal-400">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Cash Balance</p>
        <p class="text-2xl font-black text-teal-600"><?= $currencySymbol ?> <?= number_format($cashBalance,2) ?></p>
        <p class="text-xs text-slate-400 mt-1"><a href="?route=app&page=acc_cash_book" class="hover:underline">View cash book</a></p>
    </div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-blue-400">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Bank Balance</p>
        <p class="text-2xl font-black text-blue-600"><?= $currencySymbol ?> <?= number_format($bankBalance,2) ?></p>
        <p class="text-xs text-slate-400 mt-1"><a href="?route=app&page=acc_bank_book" class="hover:underline">View bank book</a></p>
    </div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Period</p>
        <p class="text-sm font-black text-slate-700"><?= date('d M',strtotime($accFrom)) ?></p>
        <p class="text-xs text-slate-500">to <?= date('d M Y',strtotime($accTo)) ?></p>
    </div>
</div>

<!-- Chart -->
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
    <h3 class="font-extrabold text-slate-800 mb-4 flex items-center gap-2"><i class="fa-solid fa-chart-column text-indigo-400"></i> 12-Month Income vs Expense</h3>
    <canvas id="finDashChart" height="90"></canvas>
</div>
<script>
new Chart(document.getElementById('finDashChart'), {
    data: {
        labels: <?= json_encode($chartMonths) ?>,
        datasets: [
            { type:'bar', label:'Income', data:<?= json_encode($chartInc) ?>, backgroundColor:'#10b981', borderRadius:4, order:2 },
            { type:'bar', label:'Expenses', data:<?= json_encode($chartExp) ?>, backgroundColor:'#f43f5e', borderRadius:4, order:2 },
            { type:'line', label:'Net Profit', data:<?= json_encode($chartProfit) ?>, borderColor:'#4f46e5', backgroundColor:'rgba(79,70,229,0.1)', borderWidth:3, tension:0.3, fill:true, order:1 }
        ]
    },
    options: { responsive:true, interaction:{mode:'index',intersect:false}, plugins:{legend:{position:'top'}} }
});
</script>

<!-- Recent Activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
        <h3 class="font-extrabold text-slate-700 mb-4 flex items-center gap-2"><i class="fa-solid fa-file-invoice-dollar text-teal-500"></i> Recent Invoices</h3>
        <?php if(empty($recentInvoices)): ?><p class="text-sm text-slate-400 text-center py-6">No invoices yet.</p><?php else: ?>
        <div class="space-y-2">
        <?php foreach($recentInvoices as $inv): ?>
        <div class="flex items-center gap-3 text-sm">
            <div class="flex-1"><p class="font-bold text-slate-800"><?= xss_clean($inv['customer_name']) ?></p><p class="text-xs text-slate-400"><?= xss_clean($inv['invoice_number']) ?> · <?= date('d M Y',strtotime($inv['issue_date'])) ?></p></div>
            <div class="text-right"><p class="font-black text-slate-700"><?= $currencySymbol ?> <?= number_format($inv['grand_total'],2) ?></p><?php if($inv['due_amount']>0): ?><p class="text-xs text-rose-500 font-bold">Due: <?= $currencySymbol ?> <?= number_format($inv['due_amount'],2) ?></p><?php endif; ?></div>
        </div>
        <?php endforeach; endif; ?>
        </div>
        <a href="?route=app&page=acc_receivable" class="text-xs font-bold text-indigo-600 mt-3 inline-block hover:underline">View all receivables →</a>
    </div>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
        <h3 class="font-extrabold text-slate-700 mb-4 flex items-center gap-2"><i class="fa-solid fa-receipt text-rose-500"></i> Recent Expenses</h3>
        <?php if(empty($recentExpenses)): ?><p class="text-sm text-slate-400 text-center py-6">No expenses yet.</p><?php else: ?>
        <div class="space-y-2">
        <?php foreach($recentExpenses as $ex): ?>
        <div class="flex items-center gap-3 text-sm">
            <div class="flex-1"><p class="font-bold text-slate-800"><?= xss_clean($ex['title']??$ex['category']) ?></p><p class="text-xs text-slate-400"><?= xss_clean($ex['category']??'') ?> · <?= date('d M Y',strtotime($ex['expense_date'])) ?></p></div>
            <p class="font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($ex['amount'],2) ?></p>
        </div>
        <?php endforeach; endif; ?>
        </div>
        <a href="?route=app&page=acc_expenses" class="text-xs font-bold text-indigo-600 mt-3 inline-block hover:underline">View all expenses →</a>
    </div>
</div>

<!-- Quick links -->
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
    <h3 class="font-extrabold text-slate-700 mb-4">Quick Actions</h3>
    <div class="flex flex-wrap gap-3">
        <?php $ql=[['acc_income','Add Income','fa-plus text-emerald-600','bg-emerald-50'],['acc_expenses','Add Expense','fa-plus text-rose-600','bg-rose-50'],['acc_journals','New Journal','fa-journal-whills text-indigo-600','bg-indigo-50'],['acc_payment_vouchers','Payment Voucher','fa-money-check-dollar text-orange-600','bg-orange-50'],['acc_receipt_vouchers','Receipt Voucher','fa-file-lines text-teal-600','bg-teal-50'],['acc_pl','P&L Report','fa-chart-line text-violet-600','bg-violet-50']];
        foreach($ql as [$pg,$lbl,$ic,$bg]): ?>
        <a href="?route=app&page=<?= $pg ?>" class="flex items-center gap-2 <?= $bg ?> px-4 py-2.5 rounded-xl text-sm font-bold hover:opacity-80 transition">
            <i class="fa-solid <?= $ic ?>"></i> <?= $lbl ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
</div>
