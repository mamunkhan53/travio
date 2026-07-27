<?php
    if ($page === 'customer_profile') {
        $cust_id = $_GET['id'] ?? '';
        $stmtC = $conn->prepare("SELECT * FROM customers WHERE id = ? AND agency_id = ?");
        $stmtC->execute([$cust_id, $agency_id]);
        $customer = $stmtC->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            flash('Customer not found.', 'error');
            redirect('?route=app&page=customers');
        }
        
        $mob = $customer['mobile'];
        $customer_orders = [];
        
        if (!empty($mob)) {
            $customer_orders = $conn->query("
                SELECT transaction_date as dt, id as invoice_no, 'Passport' as service_type, type as description, selling_price as amount, status, reference_staff_id, 'passports' as module_table FROM passports WHERE agency_id=$agency_id AND mobile='$mob'
                UNION ALL
                SELECT transaction_date as dt, id, 'Visa', type, selling_price, status, reference_staff_id, 'visas' FROM visas WHERE agency_id=$agency_id AND mobile='$mob'
                UNION ALL
                SELECT transaction_date as dt, id, 'Air Ticket', route, selling_price, status, reference_staff_id, 'tickets' FROM tickets WHERE agency_id=$agency_id AND mobile='$mob'
                UNION ALL
                SELECT transaction_date as dt, id, 'Umrah Package', package, selling_price, status, reference_staff_id, 'umrah' FROM umrah WHERE agency_id=$agency_id AND mobile='$mob'
                UNION ALL
                SELECT transaction_date as dt, id, 'Tour Package', package, selling_price, status, reference_staff_id, 'tours' FROM tours WHERE agency_id=$agency_id AND mobile='$mob'
                ORDER BY dt DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
        }

        $total_orders = count($customer_orders);
        $total_spent = array_sum(array_column($customer_orders, 'amount'));

        $stmtF = $conn->prepare("SELECT * FROM customer_followups WHERE agency_id=? AND customer_id=? ORDER BY created_at DESC");
        $stmtF->execute([$agency_id, $cust_id]);
        $followups = $stmtF->fetchAll(PDO::FETCH_ASSOC);
    }
?>
                <!-- ---------------------------------------------------- -->
                <!-- NEW CUSTOMER PROFILE VIEW -->
                <!-- ---------------------------------------------------- -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-3"><i class="fa-solid fa-user-circle text-indigo-500"></i> Customer Profile</h2>
                    <a href="?route=app&page=customers" class="text-sm font-bold text-slate-500 hover:text-indigo-600 bg-white border border-slate-200 px-4 py-2 rounded-lg shadow-sm transition"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Database</a>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Left Col: Profile & Follow-ups -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- Card 1: Details -->
                        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
                            <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl mb-4 font-bold">
                                <?= substr($customer['name'], 0, 1) ?>
                            </div>
                            <h3 class="text-xl font-extrabold text-slate-800 mb-1"><?= xss_clean($customer['name']) ?></h3>
                            <p class="text-sm font-medium text-slate-500 mb-6 bg-slate-100 inline-block px-3 py-1 rounded-full"><?= $customer['id'] ?></p>
                            
                            <ul class="space-y-4 text-sm border-t border-slate-100 pt-6">
                                <li class="flex items-center gap-3 text-slate-600"><i class="fa-solid fa-phone text-slate-400 w-4"></i> <strong><?= xss_clean($customer['mobile']) ?></strong></li>
                                <li class="flex items-center gap-3 text-slate-600"><i class="fa-solid fa-envelope text-slate-400 w-4"></i> <?= xss_clean($customer['email'] ?: 'No Email Provided') ?></li>
                                <li class="flex items-center gap-3 text-slate-600"><i class="fa-solid fa-calendar-check text-slate-400 w-4"></i> Since: <?= date('M d, Y', strtotime($customer['created_at'])) ?></li>
                            </ul>

                            <div class="grid grid-cols-2 gap-4 mt-6 pt-6 border-t border-slate-100">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Orders</p>
                                    <p class="text-2xl font-black text-indigo-600"><?= $total_orders ?></p>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Spend</p>
                                    <p class="text-2xl font-black text-emerald-600 truncate" title="<?= $currencySymbol ?> <?= number_format($total_spent) ?>"><?= $currencySymbol ?> <?= number_format($total_spent) ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Follow-Up System -->
                        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden flex flex-col h-[500px]">
                            <div class="p-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                                <h4 class="font-extrabold text-slate-700 flex items-center gap-2"><i class="fa-solid fa-comments text-indigo-500"></i> Follow-Up Notes</h4>
                            </div>
                            
                            <div class="flex-1 overflow-y-auto p-4 space-y-4 custom-scrollbar bg-slate-50/50">
                                <?php foreach($followups as $f): ?>
                                    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm relative">
                                        <p class="text-xs text-slate-400 font-bold mb-2 flex justify-between">
                                            <span><i class="fa-solid fa-user-pen mr-1"></i> <?= $f['staff_id'] ? ($staffMap[$f['staff_id']]??'Unknown') : 'Admin' ?></span>
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
                                <?php if(empty($followups)): ?>
                                    <div class="text-center text-slate-400 py-8 text-sm font-medium">No follow-ups recorded yet.</div>
                                <?php endif; ?>
                            </div>

                            <?php if (has_permission('can_manage_customers')): ?>
                                <form method="POST" class="p-4 bg-white border-t border-slate-100">
                                    <input type="hidden" name="action" value="add_followup">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
                                    <textarea name="note" required rows="2" placeholder="Write a note... e.g. Called customer, requested passport." class="w-full border border-slate-200 p-3 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm mb-3 bg-slate-50 focus:bg-white resize-none"></textarea>
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 relative">
                                            <i class="fa-solid fa-calendar-day absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                            <input type="date" name="follow_up_date" class="w-full pl-8 pr-3 py-2 border border-slate-200 rounded-lg text-xs font-bold text-slate-600 outline-none focus:border-indigo-500 bg-slate-50 cursor-pointer" title="Next Follow-up Date">
                                        </div>
                                        <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-bold shadow hover:bg-indigo-700 transition">Save Note</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="p-4 bg-slate-50 border-t border-slate-200 text-center text-xs font-bold text-slate-400">
                                    You do not have permission to add follow-ups.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Col: Order History Table -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 h-full flex flex-col">
                            <div class="p-6 border-b flex justify-between items-center bg-slate-50/50">
                                <h3 class="font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-clock-rotate-left text-indigo-500"></i> Service & Order History</h3>
                            </div>
                            <div class="flex-1 overflow-x-auto p-0">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b border-slate-100 sticky top-0">
                                        <tr><th class="px-6 py-4 font-bold">Date</th><th class="px-6 py-4 font-bold">ID / Invoice</th><th class="px-6 py-4 font-bold">Service Type</th><th class="px-6 py-4 font-bold">Amount</th><th class="px-6 py-4 font-bold">Status</th><th class="px-6 py-4 font-bold">Ref Staff</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach($customer_orders as $ord): ?>
                                            <tr class="hover:bg-slate-50 transition-colors">
                                                <td class="px-6 py-4 font-medium whitespace-nowrap"><?= date('d M, Y', strtotime($ord['dt'])) ?></td>
                                                <td class="px-6 py-4 font-extrabold text-indigo-600">
                                                    <a href="?route=app&page=query_history&table=<?= $ord['module_table'] ?>&id=<?= $ord['invoice_no'] ?>" class="hover:underline"><?= $ord['invoice_no'] ?></a>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="font-bold text-slate-700 block"><?= $ord['service_type'] ?></span>
                                                    <span class="text-xs text-slate-400 truncate block max-w-[150px]" title="<?= xss_clean($ord['description']) ?>"><?= xss_clean($ord['description']) ?></span>
                                                </td>
                                                <td class="px-6 py-4 font-bold text-slate-800"><?= $currencySymbol ?> <?= number_format($ord['amount']) ?></td>
                                                <td class="px-6 py-4">
                                                    <?php $color = in_array($ord['status'], ['Completed','Paid','Confirmed']) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>
                                                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $color ?>"><?= $ord['status'] ?></span>
                                                </td>
                                                <td class="px-6 py-4 text-xs font-bold text-slate-500">
                                                    <?= $ord['reference_staff_id'] ? ($staffMap[$ord['reference_staff_id']]??'Unknown') : 'Self' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if(empty($customer_orders)): ?>
                                            <tr><td colspan="6" class="p-12 text-center text-slate-400 font-medium">No order history found for this customer.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

