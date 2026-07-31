<?php
    if ($page === 'query_history') {
        $allowedHistoryTables = ['enquiries', 'passports', 'visas', 'tickets', 'umrah', 'tours', 'invoices'];
        $history_table = $_GET['table'] ?? 'enquiries';
        $history_id = $_GET['id'] ?? '';

        if (!in_array($history_table, $allowedHistoryTables) || !array_key_exists($history_table, $modules) || empty($history_id)) {
            flash('Record history not found.', 'error');
            redirect('/app/enquiries');
        }

        $stmtH = $conn->prepare("
            SELECT t.*, ref.full_name as reference_name, creator.full_name as created_by_name, updater.full_name as updated_by_name
            FROM $history_table t
            LEFT JOIN staff ref ON t.reference_staff_id = ref.id
            LEFT JOIN staff creator ON t.created_by_staff_id = creator.id
            LEFT JOIN staff updater ON t.updated_by_staff_id = updater.id
            WHERE t.id = ? AND t.agency_id = ?
        ");
        $stmtH->execute([$history_id, $agency_id]);
        $history_record = $stmtH->fetch(PDO::FETCH_ASSOC);

        if (!$history_record) {
            flash('Record not found.', 'error');
            redirect("/app/$history_table");
        }
        if ($_SESSION['is_staff'] && (int)($history_record['reference_staff_id'] ?? 0) !== (int)$_SESSION['staff_id']) {
            http_response_code(403); die("403 Access Denied");
        }

        $history_type = $history_table === 'enquiries' ? 'Query' : ($history_table === 'invoices' ? 'Invoice' : 'Sale');
        $history_title = $history_type . ' History';
        $history_customer = $history_record['customer'] ?? $history_record['name'] ?? $history_record['customer_name'] ?? 'Unknown';
        $history_mobile = $history_record['mobile'] ?? '';
        $history_status = $history_record['status'] ?? (($history_table === 'invoices' && (float)($history_record['due_amount'] ?? 0) <= 0) ? 'Paid' : 'Open');
        $history_amount = $history_record['selling_price'] ?? $history_record['grand_total'] ?? null;
        $history_date = $history_record['transaction_date'] ?? $history_record['date'] ?? $history_record['issue_date'] ?? $history_record['created_at'] ?? null;
        $history_fields = [];

        if ($history_table === 'invoices') {
            $history_fields = [
                'invoice_number' => 'Invoice Number',
                'issue_date' => 'Issue Date',
                'customer_name' => 'Customer Name',
                'mobile' => 'Mobile Number',
                'email' => 'Email',
                'service_desc' => 'Service Description',
                'grand_total' => 'Grand Total',
                'paid_amount' => 'Paid Amount',
                'due_amount' => 'Due Amount'
            ];
        } else {
            foreach ($modules[$history_table]['fields'] as $col => $config) {
                $history_fields[$col] = $config['label'];
            }
        }

        $stmtHF = $conn->prepare("
            SELECT rf.*, s.full_name as staff_name
            FROM record_followups rf
            LEFT JOIN staff s ON rf.staff_id = s.id
            WHERE rf.agency_id = ? AND rf.module_name = ? AND rf.record_id = ?
            ORDER BY rf.created_at DESC
        ");
        $stmtHF->execute([$agency_id, $history_table, $history_id]);
        $history_followups = $stmtHF->fetchAll(PDO::FETCH_ASSOC);

        $can_add_record_followup = $history_table === 'enquiries' ? has_permission('can_edit_enquiry') : has_permission('can_edit_sale');
    }
?>
                <!-- ---------------------------------------------------- -->
                <!-- QUERY / SALE HISTORY VIEW -->
                <!-- ---------------------------------------------------- -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-3">
                            <i class="fa-solid fa-clock-rotate-left text-indigo-500"></i> <?= xss_clean($history_title) ?>
                        </h2>
                        <p class="text-sm text-slate-500 font-bold mt-1"><?= xss_clean($modules[$history_table]['title']) ?> / <?= xss_clean($history_id) ?></p>
                    </div>
                    <a href="/app/<?= $history_table ?>" class="text-sm font-bold text-slate-500 hover:text-indigo-600 bg-white border border-slate-200 px-4 py-2 rounded-lg shadow-sm transition">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Back to <?= xss_clean($modules[$history_table]['title']) ?>
                    </a>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1 space-y-6">
                        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
                            <div class="flex items-start justify-between gap-4 mb-6">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2"><?= xss_clean($history_type) ?> ID</p>
                                    <h3 class="text-2xl font-black text-indigo-600"><?= xss_clean($history_id) ?></h3>
                                </div>
                                <?php $historyStatusColor = in_array($history_status, ['Completed','Paid','Confirmed']) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>
                                <span class="px-3 py-1 rounded-lg text-xs font-bold <?= $historyStatusColor ?>"><?= xss_clean($history_status) ?></span>
                            </div>

                            <ul class="space-y-4 text-sm border-t border-slate-100 pt-6">
                                <li class="flex items-center gap-3 text-slate-600"><i class="fa-solid fa-user text-slate-400 w-4"></i> <strong><?= xss_clean($history_customer) ?></strong></li>
                                <li class="flex items-center gap-3 text-slate-600"><i class="fa-solid fa-phone text-slate-400 w-4"></i> <?= xss_clean($history_mobile ?: 'No Mobile Provided') ?></li>
                                <li class="flex items-center gap-3 text-slate-600"><i class="fa-solid fa-calendar-day text-slate-400 w-4"></i> <?= $history_date ? date('M d, Y', strtotime($history_date)) : 'No Date' ?></li>
                                <li class="flex items-center gap-3 text-slate-600"><i class="fa-solid fa-user-tie text-slate-400 w-4"></i> <?= $history_record['reference_name'] ? xss_clean($history_record['reference_name']) : 'System / None' ?></li>
                            </ul>

                            <div class="grid grid-cols-2 gap-4 mt-6 pt-6 border-t border-slate-100">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Updates</p>
                                    <p class="text-2xl font-black text-indigo-600"><?= count($history_followups) ?></p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Amount</p>
                                    <p class="text-2xl font-black text-emerald-600 truncate"><?= $history_amount !== null ? $currencyCode . ' ' . number_format((float)$history_amount, 2) : '-' ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
                            <h4 class="font-extrabold text-slate-800 flex items-center gap-2 mb-5"><i class="fa-solid fa-list-check text-indigo-500"></i> Record Details</h4>
                            <div class="space-y-3">
                                <?php foreach($history_fields as $field => $label): ?>
                                    <?php
                                        $detailValue = $history_record[$field] ?? '';
                                        if ($detailValue === '' || $detailValue === null) {
                                            $detailDisplay = '-';
                                        } elseif (in_array($field, ['service_cost', 'selling_price', 'grand_total', 'paid_amount', 'due_amount', 'unit_price', 'subtotal', 'discount', 'tax'])) {
                                            $detailDisplay = $currencyCode . ' ' . number_format((float)$detailValue, 2);
                                        } else {
                                            $detailDisplay = $detailValue;
                                        }
                                    ?>
                                    <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= xss_clean($label) ?></span>
                                        <span class="text-sm font-bold text-slate-700 text-right max-w-[55%] break-words"><?= nl2br(xss_clean($detailDisplay)) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
                            <h4 class="font-extrabold text-slate-800 flex items-center gap-2 mb-5"><i class="fa-solid fa-route text-indigo-500"></i> Change Timeline</h4>
                            <div class="space-y-4">
                                <div class="flex gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-plus"></i></div>
                                    <div>
                                        <p class="font-bold text-slate-800">Record created</p>
                                        <p class="text-xs text-slate-500 font-medium mt-1">
                                            <?= !empty($history_record['created_at']) ? date('M d, Y h:i A', strtotime($history_record['created_at'])) : 'Creation time unavailable' ?>
                                            by <?= $history_record['created_by_name'] ? xss_clean($history_record['created_by_name']) : 'Admin/System' ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-pen"></i></div>
                                    <div>
                                        <p class="font-bold text-slate-800">Last record update</p>
                                        <p class="text-xs text-slate-500 font-medium mt-1">
                                            <?= !empty($history_record['updated_at']) ? date('M d, Y h:i A', strtotime($history_record['updated_at'])) : 'No update time available' ?>
                                            by <?= $history_record['updated_by_name'] ? xss_clean($history_record['updated_by_name']) : 'Admin/System' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden flex flex-col min-h-[520px]">
                            <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                                <h4 class="font-extrabold text-slate-700 flex items-center gap-2"><i class="fa-solid fa-comments text-indigo-500"></i> Follow-Up Updates</h4>
                            </div>

                            <div class="flex-1 overflow-y-auto p-4 space-y-4 custom-scrollbar bg-slate-50/50">
                                <?php foreach($history_followups as $f): ?>
                                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                                        <p class="text-xs text-slate-400 font-bold mb-2 flex justify-between gap-3">
                                            <span><i class="fa-solid fa-user-pen mr-1"></i> <?= $f['staff_id'] ? xss_clean($f['staff_name'] ?: 'Unknown') : 'Admin' ?></span>
                                            <span><?= date('M d, H:i', strtotime($f['created_at'])) ?></span>
                                        </p>
                                        <p class="text-sm text-slate-700 leading-relaxed"><?= nl2br(xss_clean($f['note'])) ?></p>
                                        <?php if($f['follow_up_date']): ?>
                                            <div class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 rounded-lg text-xs font-bold border border-amber-100">
                                                <i class="fa-solid fa-clock"></i> Next: <?= date('M d, Y', strtotime($f['follow_up_date'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if(empty($history_followups)): ?>
                                    <div class="text-center text-slate-400 py-12 text-sm font-medium">No follow-up updates recorded yet.</div>
                                <?php endif; ?>
                            </div>

                            <?php if ($can_add_record_followup): ?>
                                <form method="POST" class="p-4 bg-white border-t border-slate-100">
                                    <input type="hidden" name="action" value="add_record_followup">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="table" value="<?= xss_clean($history_table) ?>">
                                    <input type="hidden" name="record_id" value="<?= xss_clean($history_id) ?>">
                                    <textarea name="note" required rows="3" placeholder="Write an update or follow-up note..." class="w-full border border-slate-200 p-3 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm mb-3 bg-slate-50 focus:bg-white resize-none"></textarea>
                                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                                        <div class="flex-1 relative">
                                            <i class="fa-solid fa-calendar-day absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                            <input type="date" name="follow_up_date" class="w-full pl-8 pr-3 py-2 border border-slate-200 rounded-lg text-xs font-bold text-slate-600 outline-none focus:border-indigo-500 bg-slate-50 cursor-pointer" title="Next Follow-up Date">
                                        </div>
                                        <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-bold shadow hover:bg-indigo-700 transition">Save Update</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="p-4 bg-slate-50 border-t border-slate-200 text-center text-xs font-bold text-slate-400">
                                    You do not have permission to add updates for this record.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

