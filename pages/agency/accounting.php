<?php
    if ($page === 'accounting') {
        // ---- Resolve the selected date range from the filter presets ----
        $accRange = $_GET['range'] ?? 'this_month';
        $todayStr = date('Y-m-d');
        switch ($accRange) {
            case 'today':
                $accFrom = $todayStr; $accTo = $todayStr; break;
            case 'this_week':
                $accFrom = date('Y-m-d', strtotime('monday this week')); $accTo = $todayStr; break;
            case 'this_year':
                $accFrom = date('Y-01-01'); $accTo = $todayStr; break;
            case 'custom':
                $accFrom = normalizeReportDate($_GET['from_date'] ?? null, date('Y-m-01'));
                $accTo = normalizeReportDate($_GET['to_date'] ?? null, $todayStr);
                break;
            case 'this_month':
            default:
                $accRange = 'this_month';
                $accFrom = date('Y-m-01'); $accTo = $todayStr; break;
        }
        if ($accFrom > $accTo) { $tmpAccDate = $accFrom; $accFrom = $accTo; $accTo = $tmpAccDate; }

        // ---- Resolve the Income/Expense type filter (affects the ledger table, chart, and exports) ----
        $accType = $_GET['type'] ?? 'all';
        if (!in_array($accType, ['all', 'income', 'expense'])) { $accType = 'all'; }

        $accounting_filters = ['range' => $accRange, 'from_date' => $accFrom, 'to_date' => $accTo, 'type' => $accType];

        // ---- TOTAL INCOME: read-only, straight from the existing Sales Net Profit formula ----
        // This uses the exact same tables/statuses/formula as the agency Dashboard's Net Profit card
        // (SUM(selling_price - service_cost) on Completed/Paid/Confirmed sales). We are only adding a
        // date-range filter and per-day grouping for this report - the Net Profit feature itself,
        // and every place it is already displayed, is completely untouched.
        // Grouped/filtered by each sale's own Transaction Date (not when it was entered into the
        // system), so backdated historical sales land in the correct month's report.
        $accIncomeByDate = [];
        $completedStatusesAcc = "'Completed', 'Paid', 'Confirmed'";
        foreach (['passports', 'visas', 'tickets', 'umrah', 'tours'] as $accTbl) {
            $stmtInc = $conn->prepare("SELECT transaction_date as d, SUM(selling_price - service_cost) as profit FROM $accTbl WHERE agency_id = ? AND status IN ($completedStatusesAcc) AND transaction_date BETWEEN ? AND ? GROUP BY transaction_date");
            $stmtInc->execute([$agency_id, $accFrom, $accTo]);
            while ($rInc = $stmtInc->fetch(PDO::FETCH_ASSOC)) {
                $dInc = $rInc['d'];
                $accIncomeByDate[$dInc] = ($accIncomeByDate[$dInc] ?? 0) + (float)$rInc['profit'];
            }
        }
        $accTotalIncome = array_sum($accIncomeByDate);

        // ---- TOTAL EXPENSES: manual entries from the new accounting_expenses table ----
        $stmtExp = $conn->prepare("SELECT * FROM accounting_expenses WHERE agency_id = ? AND expense_date BETWEEN ? AND ? ORDER BY expense_date DESC, id DESC");
        $stmtExp->execute([$agency_id, $accFrom, $accTo]);
        $accExpenseRecords = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

        $accExpenseByDate = [];
        $accTotalExpense = 0;
        foreach ($accExpenseRecords as $accEx) {
            $dExp = $accEx['expense_date'];
            $accExpenseByDate[$dExp] = ($accExpenseByDate[$dExp] ?? 0) + (float)$accEx['amount'];
            $accTotalExpense += (float)$accEx['amount'];
        }

        $accBalanceProfit = $accTotalIncome - $accTotalExpense;

        // ---- Build a single ledger (Date | Description | Income | Expense | Balance Profit), newest first ----
        $accLedger = [];
        foreach ($accIncomeByDate as $dRow => $amtRow) {
            $accLedger[] = ['date' => $dRow, 'type' => 'income', 'description' => 'Sales Net Profit (Auto)', 'income' => $amtRow, 'expense' => 0, 'row' => null];
        }
        foreach ($accExpenseRecords as $accEx) {
            $desc = trim($accEx['title'] ?: 'Expense');
            if (!empty($accEx['category'])) $desc .= ' (' . $accEx['category'] . ')';
            $accLedger[] = ['date' => $accEx['expense_date'], 'type' => 'expense', 'description' => $desc, 'income' => 0, 'expense' => (float)$accEx['amount'], 'row' => $accEx];
        }
        // Chronological pass to compute a running Balance Profit, then flip to newest-first for display
        usort($accLedger, function($a, $b) {
            $c = strcmp($a['date'], $b['date']);
            if ($c !== 0) return $c;
            return strcmp($a['type'], $b['type']);
        });
        $accRunning = 0;
        foreach ($accLedger as &$accLe) {
            $accRunning += $accLe['income'] - $accLe['expense'];
            $accLe['balance'] = $accRunning;
        }
        unset($accLe);
        $accLedger = array_reverse($accLedger);

        // ---- Filtered view of the ledger for display/export (Type filter: all / income / expense) ----
        // Note: the summary cards always show the full period's Total Income / Total Expenses / Balance
        // Profit for context - only the table below and the chart narrow down to the selected type.
        $accLedgerDisplay = ($accType === 'all') ? $accLedger : array_values(array_filter($accLedger, function($le) use ($accType) {
            return $le['type'] === $accType;
        }));
        $accDisplayIncomeTotal = array_sum(array_column($accLedgerDisplay, 'income'));
        $accDisplayExpenseTotal = array_sum(array_column($accLedgerDisplay, 'expense'));

        // ---- Chart data (chronological order), narrowed to the selected type ----
        if ($accType === 'income') {
            $accChartDates = array_keys($accIncomeByDate);
        } elseif ($accType === 'expense') {
            $accChartDates = array_keys($accExpenseByDate);
        } else {
            $accChartDates = array_unique(array_merge(array_keys($accIncomeByDate), array_keys($accExpenseByDate)));
        }
        sort($accChartDates);
        $accChartLabels = []; $accChartIncome = []; $accChartExpense = []; $accChartBalance = [];
        $accChartRunning = 0;
        foreach ($accChartDates as $dChart) {
            $accChartLabels[] = date('d M', strtotime($dChart));
            $incVal = round($accIncomeByDate[$dChart] ?? 0, 2);
            $expVal = round($accExpenseByDate[$dChart] ?? 0, 2);
            $accChartRunning += ($incVal - $expVal);
            $accChartIncome[] = $incVal;
            $accChartExpense[] = $expVal;
            $accChartBalance[] = round($accChartRunning, 2);
        }

        $accExpenseCategories = ['Office Rent', 'Utility Bill', 'Staff Salary', 'Transport', 'Marketing', 'Bank Charges', 'Printing & Stationery', 'Office Supplies', 'Maintenance', 'Miscellaneous'];
        $accPaymentMethods = ['Cash', 'Bank Transfer', 'bKash', 'Nagad', 'Card', 'Other'];
        $accRedirectQs = 'range=' . urlencode($accRange) . '&from_date=' . urlencode($accFrom) . '&to_date=' . urlencode($accTo) . '&type=' . urlencode($accType);
    }
?>
                <!-- ---------------------------------------------------- -->
                <!-- ACCOUNTING MODULE (single-page: summary + expenses + chart + exports) -->
                <!-- ---------------------------------------------------- -->
                <?php
                    $accCanAdd = has_permission('can_add_expense');
                    $accCanEditExp = has_permission('can_edit_expense');
                    $accCanDelExp = has_permission('can_delete_expense');
                    $accTypeLabel = $accType === 'income' ? 'Income' : ($accType === 'expense' ? 'Expense' : 'Full');
                    $accReportFilename = preg_replace('/[^A-Za-z0-9_-]+/', '_', 'Accounting_' . $accTypeLabel . '_Report_' . $accFrom . '_to_' . $accTo);
                ?>
                <div class="space-y-6">
                    <!-- FILTER BAR -->
                    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
                        <form method="GET" id="accFilterForm" class="grid grid-cols-1 md:grid-cols-7 gap-4 items-end">
                            <input type="hidden" name="route" value="app">
                            <input type="hidden" name="page" value="accounting">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Date Range</label>
                                <select name="range" id="accRangeSelect" onchange="accToggleCustom()" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white font-bold text-slate-700">
                                    <option value="today" <?= $accounting_filters['range']==='today'?'selected':'' ?>>Today</option>
                                    <option value="this_week" <?= $accounting_filters['range']==='this_week'?'selected':'' ?>>This Week</option>
                                    <option value="this_month" <?= $accounting_filters['range']==='this_month'?'selected':'' ?>>This Month</option>
                                    <option value="this_year" <?= $accounting_filters['range']==='this_year'?'selected':'' ?>>This Year</option>
                                    <option value="custom" <?= $accounting_filters['range']==='custom'?'selected':'' ?>>Custom Range</option>
                                </select>
                            </div>
                            <div id="accFromWrap" class="<?= $accounting_filters['range']==='custom' ? '' : 'hidden' ?>">
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">From</label>
                                <input type="date" name="from_date" value="<?= xss_clean($accFrom) ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white font-bold text-slate-700">
                            </div>
                            <div id="accToWrap" class="<?= $accounting_filters['range']==='custom' ? '' : 'hidden' ?>">
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">To</label>
                                <input type="date" name="to_date" value="<?= xss_clean($accTo) ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white font-bold text-slate-700">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Show</label>
                                <select name="type" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white font-bold text-slate-700">
                                    <option value="all" <?= $accType==='all'?'selected':'' ?>>All (Income &amp; Expense)</option>
                                    <option value="income" <?= $accType==='income'?'selected':'' ?>>Income Only</option>
                                    <option value="expense" <?= $accType==='expense'?'selected':'' ?>>Expense Only</option>
                                </select>
                            </div>
                            <div class="md:col-span-2 flex flex-col sm:flex-row gap-3 justify-end">
                                <button type="submit" class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition flex items-center justify-center gap-2 w-full sm:w-auto">
                                    <i class="fa-solid fa-filter"></i> Apply
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if ($accType !== 'all'): ?>
                    <div class="bg-indigo-50 border border-indigo-100 text-indigo-700 rounded-xl px-5 py-3 text-sm font-bold flex items-center gap-2">
                        <i class="fa-solid fa-circle-info"></i>
                        Showing <?= $accType === 'income' ? 'Income' : 'Expense' ?> entries only for this date range. The report below (and the PDF/Excel download) reflects this filter.
                    </div>
                    <?php endif; ?>

                    <!-- SUMMARY CARDS -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white p-5 rounded-2xl soft-shadow border border-slate-100 relative overflow-hidden">
                            <div class="absolute -right-4 -top-4 w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-500 text-2xl"><i class="fa-solid fa-arrow-trend-up"></i></div>
                            <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Total Income</p>
                            <p class="text-2xl font-extrabold text-emerald-600 mt-2 truncate"><?= $currencySymbol ?> <?= number_format($accTotalIncome, 2) ?></p>
                            <p class="text-[11px] text-slate-400 font-bold mt-1 uppercase tracking-wider">From Sales Net Profit</p>
                        </div>
                        <div class="bg-white p-5 rounded-2xl soft-shadow border border-slate-100 relative overflow-hidden">
                            <div class="absolute -right-4 -top-4 w-16 h-16 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 text-2xl"><i class="fa-solid fa-arrow-trend-down"></i></div>
                            <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Total Expenses</p>
                            <p class="text-2xl font-extrabold text-rose-600 mt-2 truncate"><?= $currencySymbol ?> <?= number_format($accTotalExpense, 2) ?></p>
                            <p class="text-[11px] text-slate-400 font-bold mt-1 uppercase tracking-wider">Manually recorded</p>
                        </div>
                        <div class="bg-white p-5 rounded-2xl soft-shadow border border-slate-100 relative overflow-hidden">
                            <div class="absolute -right-4 -top-4 w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center text-indigo-500 text-2xl"><i class="fa-solid fa-scale-balanced"></i></div>
                            <p class="text-sm text-slate-500 font-bold uppercase tracking-wider">Balance Profit</p>
                            <p class="text-2xl font-extrabold <?= $accBalanceProfit >= 0 ? 'text-indigo-600' : 'text-rose-600' ?> mt-2 truncate"><?= $currencySymbol ?> <?= number_format($accBalanceProfit, 2) ?></p>
                            <p class="text-[11px] text-slate-400 font-bold mt-1 uppercase tracking-wider">Income − Expenses</p>
                        </div>
                    </div>

                    <!-- CHART -->
                    <div class="bg-white p-6 rounded-2xl soft-shadow border border-slate-100">
                        <h3 class="font-extrabold text-slate-800 mb-6 text-lg">
                            <i class="fa-solid fa-chart-column text-indigo-500 mr-2"></i>
                            <?= $accType === 'income' ? 'Income Trend' : ($accType === 'expense' ? 'Expense Trend' : 'Income vs Expense / Balance Profit') ?>
                        </h3>
                        <canvas id="accChart" height="100"></canvas>
                    </div>
                    <script>
                        new Chart(document.getElementById('accChart'), {
                            data: {
                                labels: <?= json_encode($accChartLabels) ?>,
                                datasets: [
                                    <?php if ($accType !== 'expense'): ?>
                                    { type: 'bar', label: 'Income (<?= $currencySymbol ?>)', data: <?= json_encode($accChartIncome) ?>, backgroundColor: '#10b981', borderRadius: 4, order: 2 },
                                    <?php endif; ?>
                                    <?php if ($accType !== 'income'): ?>
                                    { type: 'bar', label: 'Expense (<?= $currencySymbol ?>)', data: <?= json_encode($accChartExpense) ?>, backgroundColor: '#f43f5e', borderRadius: 4, order: 2 },
                                    <?php endif; ?>
                                    <?php if ($accType === 'all'): ?>
                                    { type: 'line', label: 'Balance Profit (<?= $currencySymbol ?>)', data: <?= json_encode($accChartBalance) ?>, borderColor: '#4f46e5', backgroundColor: '#4f46e5', borderWidth: 3, tension: 0.3, order: 1 }
                                    <?php endif; ?>
                                ]
                            },
                            options: { responsive: true, interaction: { mode: 'index', intersect: false } }
                        });
                    </script>

                    <!-- LEDGER TABLE + EXPENSE MANAGEMENT -->
                    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-slate-50/50 no-print">
                            <div>
                                <h3 class="text-lg font-extrabold text-slate-800">Accounting Ledger</h3>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-1"><?= date('d M Y', strtotime($accFrom)) ?> - <?= date('d M Y', strtotime($accTo)) ?></p>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <?php if ($accCanAdd): ?>
                                    <button onclick="openExpModal('add')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center justify-center gap-2 transition"><i class="fa-solid fa-plus"></i> Add Expense</button>
                                <?php endif; ?>
                                <button type="button" onclick="accDownloadPDF()" class="px-5 py-2.5 rounded-xl border border-rose-100 bg-rose-50 text-rose-600 font-bold hover:bg-rose-100 transition flex items-center justify-center gap-2"><i class="fa-solid fa-file-pdf"></i> PDF</button>
                                <button type="button" onclick="accDownloadExcel()" class="px-5 py-2.5 rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-600 font-bold hover:bg-emerald-100 transition flex items-center justify-center gap-2"><i class="fa-solid fa-file-excel"></i> Excel</button>
                            </div>
                        </div>

                        <!-- Printable Area (also used for PDF/Excel export) -->
                        <div id="accPrintable">
                            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <img src="<?= $logoSrc ?>" class="w-12 h-12 rounded-lg object-cover border border-slate-200 bg-white">
                                    <div>
                                        <p class="font-extrabold text-slate-800"><?= xss_clean($agency['company_name']) ?></p>
                                        <p class="text-xs text-slate-500"><?= xss_clean($agency['address'] ?? '') ?></p>
                                        <p class="text-xs text-slate-500">Mobile: <?= xss_clean($agency['company_phone']) ?> &middot; Email: <?= xss_clean($agency['company_email']) ?></p>
                                    </div>
                                </div>
                                <div class="text-left sm:text-right">
                                    <p class="text-sm font-extrabold text-slate-800">Accounting Report</p>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= date('d M Y', strtotime($accFrom)) ?> - <?= date('d M Y', strtotime($accTo)) ?></p>
                                </div>
                            </div>

                            <div class="px-6 pt-6 grid grid-cols-3 gap-3">
                                <div class="bg-slate-50 border border-slate-100 rounded-xl px-4 py-3">
                                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-1">Total Income</p>
                                    <p class="text-lg font-black text-emerald-600"><?= $currencySymbol ?> <?= number_format($accTotalIncome, 2) ?></p>
                                </div>
                                <div class="bg-slate-50 border border-slate-100 rounded-xl px-4 py-3">
                                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-1">Total Expenses</p>
                                    <p class="text-lg font-black text-rose-600"><?= $currencySymbol ?> <?= number_format($accTotalExpense, 2) ?></p>
                                </div>
                                <div class="bg-slate-50 border border-slate-100 rounded-xl px-4 py-3">
                                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-1">Balance Profit</p>
                                    <p class="text-lg font-black <?= $accBalanceProfit >= 0 ? 'text-indigo-600' : 'text-rose-600' ?>"><?= $currencySymbol ?> <?= number_format($accBalanceProfit, 2) ?></p>
                                </div>
                            </div>

                            <div class="overflow-x-auto mt-6">
                                <table id="accLedgerTable" class="w-full text-left text-sm">
                                    <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                                        <tr>
                                            <th class="px-6 py-4 font-bold">Date</th>
                                            <th class="px-6 py-4 font-bold">Description</th>
                                            <th class="px-6 py-4 font-bold text-emerald-500">Income</th>
                                            <th class="px-6 py-4 font-bold text-rose-500">Expense</th>
                                            <th class="px-6 py-4 font-bold text-indigo-500">Balance Profit</th>
                                            <th class="px-6 py-4 font-bold text-right no-print">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach ($accLedgerDisplay as $le): ?>
                                            <tr class="hover:bg-slate-50 transition-colors">
                                                <td class="px-6 py-3 font-bold text-slate-700 whitespace-nowrap"><?= date('d M, Y', strtotime($le['date'])) ?></td>
                                                <td class="px-6 py-3 font-medium text-slate-600"><?= xss_clean($le['description']) ?></td>
                                                <td class="px-6 py-3 font-bold text-emerald-600"><?= $le['income'] > 0 ? '<?= $currencySymbol ?> '.number_format($le['income'], 2) : '-' ?></td>
                                                <td class="px-6 py-3 font-bold text-rose-600"><?= $le['expense'] > 0 ? '<?= $currencySymbol ?> '.number_format($le['expense'], 2) : '-' ?></td>
                                                <td class="px-6 py-3 font-extrabold <?= $le['balance'] >= 0 ? 'text-indigo-700' : 'text-rose-700' ?>"><?= $currencySymbol ?> <?= number_format($le['balance'], 2) ?></td>
                                                <td class="px-6 py-3 text-right whitespace-nowrap no-print">
                                                    <?php if ($le['type'] === 'expense'): ?>
                                                        <?php if ($accCanEditExp): ?>
                                                            <button onclick="openExpModal('edit', '<?= rawurlencode(json_encode($le['row'])) ?>')" class="text-indigo-600 bg-indigo-50 w-8 h-8 rounded-lg hover:bg-indigo-100 mx-1 transition"><i class="fa-solid fa-pen"></i></button>
                                                        <?php endif; ?>
                                                        <?php if ($accCanDelExp): ?>
                                                            <a href="?route=app&action=delete_expense&id=<?= urlencode($le['row']['id']) ?>&redirect_qs=<?= urlencode($accRedirectQs) ?>" onclick="return confirm('Delete this expense?')" class="text-rose-600 bg-rose-50 w-8 h-8 inline-flex items-center justify-center rounded-lg hover:bg-rose-100 transition"><i class="fa-solid fa-trash"></i></a>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-slate-300 text-xs">Auto</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($accLedgerDisplay)): ?>
                                            <tr><td colspan="6" class="p-12 text-center text-slate-400 font-medium">No <?= $accType === 'income' ? 'income' : ($accType === 'expense' ? 'expenses' : 'income or expenses') ?> found for this date range.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-slate-50 border-t-2 border-slate-200">
                                            <td colspan="2" class="px-6 py-4 font-extrabold text-slate-800 text-right">TOTALS</td>
                                            <td class="px-6 py-4 font-extrabold text-emerald-600"><?= $currencySymbol ?> <?= number_format($accDisplayIncomeTotal, 2) ?></td>
                                            <td class="px-6 py-4 font-extrabold text-rose-600"><?= $currencySymbol ?> <?= number_format($accDisplayExpenseTotal, 2) ?></td>
                                            <td class="px-6 py-4 font-extrabold <?= ($accDisplayIncomeTotal - $accDisplayExpenseTotal) >= 0 ? 'text-indigo-700' : 'text-rose-700' ?>"><?= $currencySymbol ?> <?= number_format($accDisplayIncomeTotal - $accDisplayExpenseTotal, 2) ?></td>
                                            <td class="no-print"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div class="px-6 pb-8 pt-16 grid grid-cols-2 gap-8 max-w-xl ml-auto text-center">
                                <div><div class="border-t border-slate-400 pt-2 text-xs font-bold text-slate-500 uppercase tracking-wider">Prepared By</div></div>
                                <div><div class="border-t border-slate-400 pt-2 text-xs font-bold text-slate-500 uppercase tracking-wider">Authorized Signature</div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expense Add/Edit Modal -->
                <div id="expModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-y-auto max-h-[90vh] custom-scrollbar">
                        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50 sticky top-0 z-10">
                            <h3 class="font-extrabold text-slate-800 text-lg flex items-center gap-2" id="expModalTitle"><i class="fa-solid fa-file-invoice text-indigo-500"></i> Add Expense</h3>
                            <button onclick="closeExpModal()" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center transition"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <form method="POST" action="?route=app" class="p-6 space-y-5" id="expForm">
                            <input type="hidden" name="action" value="save_expense">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="expense_id" id="exp_id" value="">
                            <input type="hidden" name="redirect_qs" value="<?= xss_clean($accRedirectQs) ?>">

                            <div><label class="block text-sm font-bold text-slate-700 mb-2">Date</label><input type="date" name="expense_date" id="exp_date" required value="<?= date('Y-m-d') ?>" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition"></div>

                            <div><label class="block text-sm font-bold text-slate-700 mb-2">Category</label>
                                <input type="text" list="expCategoryList" name="category" id="exp_category" required placeholder="e.g. Office Rent" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                <datalist id="expCategoryList">
                                    <?php foreach ($accExpenseCategories as $cat): ?><option value="<?= xss_clean($cat) ?>"><?php endforeach; ?>
                                </datalist>
                            </div>

                            <div><label class="block text-sm font-bold text-slate-700 mb-2">Title</label><input type="text" name="title" id="exp_title" required placeholder="e.g. July Office Rent" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition"></div>

                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="block text-sm font-bold text-slate-700 mb-2">Amount (<?= $currencySymbol ?>)</label><input type="number" step="0.01" min="0" name="amount" id="exp_amount" required class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition"></div>
                                <div><label class="block text-sm font-bold text-slate-700 mb-2">Payment Method</label>
                                    <select name="payment_method" id="exp_method" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                        <?php foreach ($accPaymentMethods as $pm): ?><option value="<?= xss_clean($pm) ?>"><?= xss_clean($pm) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div><label class="block text-sm font-bold text-slate-700 mb-2">Remarks</label><textarea name="remarks" id="exp_remarks" rows="3" placeholder="Optional notes..." class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition"></textarea></div>

                            <div class="flex flex-col sm:flex-row gap-4 pt-4 border-t border-slate-100">
                                <button type="button" onclick="closeExpModal()" class="w-full sm:w-1/3 py-3 border border-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-50 transition">Cancel</button>
                                <button type="submit" class="w-full sm:w-2/3 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition">Save Expense</button>
                            </div>
                        </form>
                    </div>
                </div>

                <style>
                    @media print { .no-print { display: none !important; } }
                </style>

                <script>
                    function accToggleCustom() {
                        const isCustom = document.getElementById('accRangeSelect').value === 'custom';
                        document.getElementById('accFromWrap').classList.toggle('hidden', !isCustom);
                        document.getElementById('accToWrap').classList.toggle('hidden', !isCustom);
                    }

                    function openExpModal(action, dataStr = null) {
                        document.getElementById('expModal').classList.remove('hidden');
                        if (action === 'add') {
                            document.getElementById('expModalTitle').innerHTML = '<i class="fa-solid fa-file-invoice text-indigo-500 mr-2"></i> Add Expense';
                            document.getElementById('expForm').reset();
                            document.getElementById('exp_id').value = '';
                            document.getElementById('exp_date').value = new Date().toISOString().split('T')[0];
                        } else if (action === 'edit' && dataStr) {
                            document.getElementById('expModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-indigo-500 mr-2"></i> Edit Expense';
                            const data = JSON.parse(decodeURIComponent(dataStr));
                            document.getElementById('exp_id').value = data.id;
                            document.getElementById('exp_date').value = data.expense_date;
                            document.getElementById('exp_category').value = data.category;
                            document.getElementById('exp_title').value = data.title;
                            document.getElementById('exp_amount').value = data.amount;
                            document.getElementById('exp_method').value = data.payment_method;
                            document.getElementById('exp_remarks').value = data.remarks;
                        }
                    }

                    function closeExpModal() {
                        document.getElementById('expModal').classList.add('hidden');
                    }

                    const accReportFilename = <?= json_encode($accReportFilename) ?>;

                    function accDownloadPDF() {
                        const element = document.getElementById('accPrintable');
                        html2pdf().from(element).set({
                            margin: 0.3,
                            filename: `${accReportFilename}.pdf`,
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: {
                                scale: 2,
                                useCORS: true,
                                onclone: function (clonedDoc) {
                                    clonedDoc.querySelectorAll('.no-print').forEach(function (el) { el.style.display = 'none'; });
                                    clonedDoc.querySelectorAll('.overflow-x-auto').forEach(function (el) {
                                        el.style.overflow = 'visible';
                                        el.style.width = 'max-content';
                                        el.style.maxWidth = 'none';
                                    });
                                }
                            },
                            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' },
                            pagebreak: { mode: ['css', 'legacy'] }
                        }).toPdf().get('pdf').then(function (pdf) {
                            const totalPages = pdf.internal.getNumberOfPages();
                            for (let i = 1; i <= totalPages; i++) {
                                pdf.setPage(i);
                                const pageSize = pdf.internal.pageSize;
                                pdf.setFontSize(8);
                                pdf.setTextColor(150);
                                pdf.text(`Page ${i} of ${totalPages}`, pageSize.getWidth() - 1.0, pageSize.getHeight() - 0.25);
                            }
                        }).save();
                    }

                    function accDownloadExcel() {
                        const clone = document.getElementById('accPrintable').cloneNode(true);
                        clone.querySelectorAll('.no-print').forEach(el => el.remove());
                        const html = `<html><head><meta charset="UTF-8"></head><body>${clone.outerHTML}</body></html>`;
                        const blob = new Blob(['\ufeff', html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = `${accReportFilename}.xls`;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(link.href);
                    }
                </script>

