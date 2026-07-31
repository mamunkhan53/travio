<?php
if($_SESSION['is_staff']){ flash("Access denied.","error"); redirect("/app/acc_dashboard"); }

// Load current settings
function getAccSetting($conn,$agency_id,$key,$default=''){
    $s=$conn->prepare("SELECT setting_value FROM acc_settings WHERE agency_id=? AND setting_key=?");
    $s->execute([$agency_id,$key]); return $s->fetchColumn()?:$default;
}
$settings=[
    'opening_cash_balance'  => getAccSetting($conn,$agency_id,'opening_cash_balance','0'),
    'opening_bank_balance'  => getAccSetting($conn,$agency_id,'opening_bank_balance','0'),
    'bank_account_name'     => getAccSetting($conn,$agency_id,'bank_account_name','Main Account'),
    'bank_account_number'   => getAccSetting($conn,$agency_id,'bank_account_number',''),
    'bank_name'             => getAccSetting($conn,$agency_id,'bank_name',''),
    'vat_rate'              => getAccSetting($conn,$agency_id,'vat_rate','0'),
    'vat_registration_no'   => getAccSetting($conn,$agency_id,'vat_registration_no',''),
    'fiscal_year_start'     => getAccSetting($conn,$agency_id,'fiscal_year_start','01-01'),
    'income_categories'     => getAccSetting($conn,$agency_id,'income_categories',''),
    'expense_categories'    => getAccSetting($conn,$agency_id,'expense_categories',''),
    'voucher_prefix_pv'     => getAccSetting($conn,$agency_id,'voucher_prefix_pv','PV'),
    'voucher_prefix_rv'     => getAccSetting($conn,$agency_id,'voucher_prefix_rv','RV'),
    'show_vat_in_reports'   => getAccSetting($conn,$agency_id,'show_vat_in_reports','1'),
];
// Chart of accounts summary
$coaSummary=$conn->query("SELECT account_type, COUNT(*) as cnt FROM acc_chart_of_accounts WHERE agency_id=$agency_id GROUP BY account_type ORDER BY account_type")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
<div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-gear text-indigo-500"></i> Accounting Settings</h2><p class="text-sm text-slate-500 mt-1">Configure your accounting module preferences.</p></div>

<form method="POST" action="" class="space-y-6">
<input type="hidden" name="action" value="save_acc_settings_bulk">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<!-- Opening Balances -->
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 bg-slate-50 border-b border-slate-100"><h3 class="font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-money-bill-wave text-teal-500"></i> Opening Balances</h3></div>
    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Opening Cash Balance</label><div class="relative"><span class="absolute left-3 top-2.5 text-slate-400 font-bold text-sm"><?= $currencySymbol ?></span><input type="number" step="0.01" name="opening_cash_balance" value="<?= xss_clean($settings['opening_cash_balance']) ?>" class="w-full border border-slate-200 rounded-xl pl-8 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div><p class="text-xs text-slate-400 mt-1">Starting cash amount before using this system.</p></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Opening Bank Balance</label><div class="relative"><span class="absolute left-3 top-2.5 text-slate-400 font-bold text-sm"><?= $currencySymbol ?></span><input type="number" step="0.01" name="opening_bank_balance" value="<?= xss_clean($settings['opening_bank_balance']) ?>" class="w-full border border-slate-200 rounded-xl pl-8 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div><p class="text-xs text-slate-400 mt-1">Starting bank account balance.</p></div>
    </div>
</div>

<!-- Bank Details -->
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 bg-slate-50 border-b border-slate-100"><h3 class="font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-building-columns text-blue-500"></i> Bank Details</h3></div>
    <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Account Label</label><input type="text" name="bank_account_name" value="<?= xss_clean($settings['bank_account_name']) ?>" placeholder="e.g. Main Account" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Account Number</label><input type="text" name="bank_account_number" value="<?= xss_clean($settings['bank_account_number']) ?>" placeholder="Bank account number" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Bank Name</label><input type="text" name="bank_name" value="<?= xss_clean($settings['bank_name']) ?>" placeholder="e.g. City Bank" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    </div>
</div>

<!-- VAT / Tax -->
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 bg-slate-50 border-b border-slate-100"><h3 class="font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-percent text-rose-500"></i> VAT / Tax Settings</h3></div>
    <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div><label class="block text-sm font-bold text-slate-700 mb-2">VAT Rate (%)</label><input type="number" step="0.01" min="0" max="100" name="vat_rate" value="<?= xss_clean($settings['vat_rate']) ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><p class="text-xs text-slate-400 mt-1">Set to 0 to disable VAT calculations.</p></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-2">VAT Registration No.</label><input type="text" name="vat_registration_no" value="<?= xss_clean($settings['vat_registration_no']) ?>" placeholder="Your VAT registration number" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Show VAT in Reports</label>
            <select name="show_vat_in_reports" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                <option value="1" <?= $settings['show_vat_in_reports']==='1'?'selected':'' ?>>Yes</option>
                <option value="0" <?= $settings['show_vat_in_reports']==='0'?'selected':'' ?>>No</option>
            </select>
        </div>
    </div>
</div>

<!-- Fiscal Year & Vouchers -->
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 bg-slate-50 border-b border-slate-100"><h3 class="font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-calendar-alt text-violet-500"></i> Fiscal Year & Vouchers</h3></div>
    <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Fiscal Year Start (MM-DD)</label><input type="text" name="fiscal_year_start" value="<?= xss_clean($settings['fiscal_year_start']) ?>" placeholder="01-01" maxlength="5" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><p class="text-xs text-slate-400 mt-1">Format: MM-DD (e.g. 01-01 for Jan 1st)</p></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Payment Voucher Prefix</label><input type="text" name="voucher_prefix_pv" value="<?= xss_clean($settings['voucher_prefix_pv']) ?>" placeholder="PV" maxlength="10" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Receipt Voucher Prefix</label><input type="text" name="voucher_prefix_rv" value="<?= xss_clean($settings['voucher_prefix_rv']) ?>" placeholder="RV" maxlength="10" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    </div>
</div>

<!-- Categories -->
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 bg-slate-50 border-b border-slate-100"><h3 class="font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-tags text-amber-500"></i> Custom Categories</h3><p class="text-xs text-slate-500 mt-1">One per line. Leave blank to use defaults.</p></div>
    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Income Categories</label><textarea name="income_categories" rows="8" placeholder="Air Ticket&#10;Visa Processing&#10;Student Consultancy&#10;Hotel Booking&#10;Service Charge&#10;Other Income" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none font-mono"><?= xss_clean($settings['income_categories']) ?></textarea></div>
        <div><label class="block text-sm font-bold text-slate-700 mb-2">Expense Categories</label><textarea name="expense_categories" rows="8" placeholder="Office Rent&#10;Staff Salary&#10;Utility Bill&#10;Internet&#10;Marketing&#10;Office Supplies&#10;Bank Charges&#10;Transport" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none font-mono"><?= xss_clean($settings['expense_categories']) ?></textarea></div>
    </div>
</div>

<div class="flex justify-end gap-3">
    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-xl font-bold text-sm shadow-md shadow-indigo-200"><i class="fa-solid fa-save mr-2"></i>Save Settings</button>
</div>
</form>

<!-- Chart of Accounts summary -->
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-list text-indigo-400"></i> Chart of Accounts Summary</h3>
        <a href="/app/acc_chart_of_accounts" class="text-sm font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-4 py-2 rounded-xl transition">Manage →</a>
    </div>
    <div class="grid grid-cols-5 gap-3">
    <?php
    $coaMap=[];
    foreach($coaSummary as $cs) $coaMap[$cs['account_type']]=$cs['cnt'];
    $colors=['Asset'=>'bg-blue-100 text-blue-700','Liability'=>'bg-amber-100 text-amber-700','Income'=>'bg-emerald-100 text-emerald-700','Expense'=>'bg-rose-100 text-rose-700','Equity'=>'bg-violet-100 text-violet-700'];
    foreach(['Asset','Liability','Income','Expense','Equity'] as $t):
    ?>
    <div class="<?= $colors[$t] ?> rounded-xl p-4 text-center"><p class="text-xs font-bold uppercase opacity-70 mb-1"><?= $t ?></p><p class="text-2xl font-black"><?= $coaMap[$t]??0 ?></p><p class="text-xs mt-0.5 opacity-60">accounts</p></div>
    <?php endforeach; ?>
    </div>
</div>
</div>
