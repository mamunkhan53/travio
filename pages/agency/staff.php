<?php
    if ($page === 'staff' && !$_SESSION['is_staff']) {
        $staff_records = $conn->query("SELECT * FROM staff WHERE agency_id=$agency_id ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($page === 'staff_history' && !$_SESSION['is_staff']) {
        $filter_staff = $_GET['staff_id'] ?? null;
        $filter_month = $_GET['month'] ?? date('Y-m');
        $hist_records = []; $hist_totals = ['sales' => 0, 'cost' => 0, 'profit' => 0, 'commission' => 0, 'cnt_enq'=>0, 'cnt_sale'=>0];
        
        if ($filter_staff) {
            $stObj = $conn->query("SELECT commission_rate FROM staff WHERE id=$filter_staff")->fetch(PDO::FETCH_ASSOC);
            $cRate = $stObj ? ($stObj['commission_rate']/100) : 0;
            
            // Enquiries
            $enq = $conn->query("SELECT date as dt, 'Enquiry' as type, customer, category as pnr, 0 as sale, 0 as cost, 0 as profit, 0 as comm, status FROM enquiries WHERE agency_id=$agency_id AND reference_staff_id=$filter_staff AND DATE_FORMAT(date, '%Y-%m')='$filter_month'")->fetchAll(PDO::FETCH_ASSOC);
            $hist_totals['cnt_enq'] += count($enq);
            $hist_records = array_merge($hist_records, $enq);
            
            // Sales
            $completedStatuses = ['Completed', 'Paid', 'Confirmed'];
            foreach(['passports', 'visas', 'tickets', 'umrah', 'tours'] as $tbl) {
                $sales = $conn->query("SELECT transaction_date as dt, 'Sale' as type, name as customer, id as pnr, selling_price as sale, service_cost as cost, (selling_price - service_cost) as profit, status FROM $tbl WHERE agency_id=$agency_id AND reference_staff_id=$filter_staff AND DATE_FORMAT(transaction_date, '%Y-%m')='$filter_month'")->fetchAll(PDO::FETCH_ASSOC);
                foreach($sales as $s) {
                    if (in_array($s['status'], $completedStatuses)) {
                        $s['comm'] = $s['profit'] * $cRate;
                        $hist_totals['cnt_sale']++;
                        $hist_totals['sales'] += $s['sale'];
                        $hist_totals['cost'] += $s['cost'];
                        $hist_totals['profit'] += $s['profit'];
                        $hist_totals['commission'] += $s['comm'];
                    } else {
                        $s['comm'] = 0; $s['profit'] = 0;
                    }
                    $hist_records[] = $s;
                }
            }
            usort($hist_records, function($a, $b) { return strtotime($b['dt']) - strtotime($a['dt']); });
        }
    }
?>
<?php if ($page === 'staff' && !$_SESSION['is_staff']): ?>
                <!-- ---------------------------------------------------- -->
                <!-- STAFF MANAGEMENT UI -->
                <!-- ---------------------------------------------------- -->
                <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden flex flex-col">
                    <div class="p-5 border-b flex justify-between items-center bg-slate-50/50">
                        <h3 class="font-extrabold text-slate-800">Agency Staff & Permissions</h3>
                        <button onclick="openStaffModal('add')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center gap-2 transition">
                            <i class="fa-solid fa-plus"></i> Add Staff
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-600">
                            <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                                <tr><th class="px-6 py-4 font-bold">Staff Name</th><th class="px-6 py-4 font-bold">Role</th><th class="px-6 py-4 font-bold">Contact</th><th class="px-6 py-4 font-bold">Commission</th><th class="px-6 py-4 font-bold">Status</th><th class="px-6 py-4 font-bold text-right">Actions</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($staff_records as $s): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="px-6 py-4 font-bold text-slate-800">
                                            <?= xss_clean($s['full_name']) ?><br>
                                            <span class="text-xs text-slate-400 font-normal">@<?= xss_clean($s['username']) ?></span>
                                        </td>
                                        <td class="px-6 py-4 font-bold text-indigo-600"><?= xss_clean($s['role']) ?></td>
                                        <td class="px-6 py-4 text-xs"><?= xss_clean($s['phone']) ?><br><?= xss_clean($s['email']) ?></td>
                                        <td class="px-6 py-4 font-bold text-emerald-500"><?= $s['commission_rate'] ?>%</td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 rounded-lg text-xs font-bold <?= $s['status']==='Active'?'bg-emerald-100 text-emerald-700':'bg-rose-100 text-rose-700' ?>"><?= $s['status'] ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <?php 
                                            // Fetch perms for edit modal
                                            $p = $conn->query("SELECT * FROM staff_permissions WHERE staff_id={$s['id']}")->fetch(PDO::FETCH_ASSOC);
                                            $s_data = array_merge($s, $p?:[]);
                                            ?>
                                            <button onclick="openStaffModal('edit', '<?= rawurlencode(json_encode($s_data)) ?>')" class="text-indigo-600 bg-indigo-50 w-8 h-8 rounded-lg hover:bg-indigo-100 transition"><i class="fa-solid fa-pen"></i></button>
                                            <a href="?route=app&action=delete&table=staff&id=<?= $s['id'] ?>" onclick="return confirm('Delete this staff member?')" class="text-rose-600 bg-rose-50 w-8 h-8 inline-flex items-center justify-center rounded-lg hover:bg-rose-100 transition ml-1"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="staffModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-y-auto max-h-[90vh] custom-scrollbar">
                        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50 sticky top-0 z-10">
                            <h3 class="font-extrabold text-slate-800 text-lg flex items-center gap-2" id="staffModalTitle"><i class="fa-solid fa-user-tie text-indigo-500"></i> Manage Staff</h3>
                            <button onclick="document.getElementById('staffModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center transition"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <form method="POST" action="?route=app" class="p-6 sm:p-8">
                            <input type="hidden" name="action" value="save_staff">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" id="st_id" value="">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="font-bold text-slate-800 mb-4 border-b pb-2">Profile & Login</h4>
                                    <div class="space-y-4">
                                        <div><label class="block text-xs font-bold text-slate-700 mb-1">Full Name</label><input type="text" name="full_name" id="st_name" required class="w-full border border-slate-200 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-slate-50"></div>
                                        <div><label class="block text-xs font-bold text-slate-700 mb-1">Email</label><input type="email" name="email" id="st_email" required class="w-full border border-slate-200 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-slate-50"></div>
                                        <div><label class="block text-xs font-bold text-slate-700 mb-1">Phone</label><input type="text" name="phone" id="st_phone" required class="w-full border border-slate-200 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-slate-50"></div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div><label class="block text-xs font-bold text-slate-700 mb-1">Username</label><input type="text" name="username" id="st_user" required class="w-full border border-slate-200 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-slate-50"></div>
                                            <div><label class="block text-xs font-bold text-slate-700 mb-1">Password</label><input type="password" name="password" id="st_pass" class="w-full border border-slate-200 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-slate-50" placeholder="••••••••"></div>
                                        </div>
                                        <div class="grid grid-cols-3 gap-3">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 mb-1">Role</label>
                                                <select name="role" id="st_role" class="w-full border border-slate-200 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-slate-50">
                                                    <option>Accountant</option><option>Sales Executive</option><option>Marketing Executive</option><option>Ticketing Officer</option><option>Visa Executive</option><option>Manager</option>
                                                </select>
                                            </div>
                                            <div><label class="block text-xs font-bold text-slate-700 mb-1">Commission (%)</label><input type="number" step="0.01" name="commission_rate" id="st_comm" value="20" class="w-full border border-slate-200 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-slate-50 font-bold"></div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                                                <select name="status" id="st_status" class="w-full border border-slate-200 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-slate-50">
                                                    <option value="Active">Active</option><option value="Inactive">Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 class="font-bold text-slate-800 mb-4 border-b pb-2">Module Permissions</h4>
                                    <div class="space-y-3 bg-slate-50 p-4 rounded-xl border border-slate-100 h-[380px] overflow-y-auto">
                                        <?php 
                                        $permsList = [
                                            'can_add_enquiry'=>'Add Enquiries/Leads', 'can_edit_enquiry'=>'Edit Enquiries', 'can_delete_enquiry'=>'Delete Enquiries',
                                            'can_add_sale'=>'Add Sales (Visa, Ticket, etc)', 'can_edit_sale'=>'Edit Sales', 'can_delete_sale'=>'Delete Sales',
                                            'can_add_expense'=>'Add Expenses', 'can_edit_expense'=>'Edit Expenses', 'can_delete_expense'=>'Delete Expenses',
                                            'can_view_reports'=>'View Analytics & Reports', 'can_manage_customers'=>'Manage Customer DB',
                                            'can_send_whatsapp'=>'Send WhatsApp Messages',
                                            'can_manage_sc_leads'=>'SC: Manage Student Leads', 'can_manage_sc_students'=>'SC: Manage Students',
                                            'can_manage_sc_applications'=>'SC: Manage Applications', 'can_manage_sc_payments'=>'SC: Manage Payments',
                                            'can_view_sc_reports'=>'SC: View Reports'
                                        ];
                                        foreach($permsList as $k => $v): ?>
                                            <label class="flex items-center gap-3 cursor-pointer hover:bg-slate-100 p-2 rounded">
                                                <input type="checkbox" name="<?= $k ?>" id="perm_<?= $k ?>" class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500">
                                                <span class="text-sm font-bold text-slate-700"><?= $v ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-8 flex justify-end gap-4 pt-6 border-t border-slate-100">
                                <button type="submit" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-indigo-700 shadow-lg transition">Save Staff Profile</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <script>
                    function openStaffModal(action, dataStr = null) {
                        document.getElementById('staffModal').classList.remove('hidden');
                        document.getElementById('st_pass').required = (action === 'add');
                        if (action === 'add') {
                            document.getElementById('staffModalTitle').innerHTML = '<i class="fa-solid fa-user-plus text-indigo-500 mr-2"></i> Add New Staff';
                            document.getElementById('st_id').value = '';
                            ['st_name','st_email','st_phone','st_user','st_pass'].forEach(id => document.getElementById(id).value = '');
                            document.getElementById('st_comm').value = '20';
                            document.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false);
                        } else {
                            document.getElementById('staffModalTitle').innerHTML = '<i class="fa-solid fa-user-pen text-indigo-500 mr-2"></i> Edit Staff';
                            const data = JSON.parse(decodeURIComponent(dataStr));
                            document.getElementById('st_id').value = data.id;
                            document.getElementById('st_name').value = data.full_name;
                            document.getElementById('st_email').value = data.email;
                            document.getElementById('st_phone').value = data.phone;
                            document.getElementById('st_user').value = data.username;
                            document.getElementById('st_role').value = data.role;
                            document.getElementById('st_status').value = data.status;
                            document.getElementById('st_comm').value = data.commission_rate;
                            
                            ['can_add_enquiry','can_edit_enquiry','can_delete_enquiry','can_add_sale','can_edit_sale','can_delete_sale','can_add_expense','can_edit_expense','can_delete_expense','can_view_reports','can_manage_customers','can_send_whatsapp','can_manage_sc_leads','can_manage_sc_students','can_manage_sc_applications','can_manage_sc_payments','can_view_sc_reports'].forEach(p => {
                                if (document.getElementById('perm_'+p)) document.getElementById('perm_'+p).checked = (data[p] == 1);
                            });
                        }
                    }
                </script>

<?php elseif ($page === 'staff_history' && !$_SESSION['is_staff']): ?>
                <!-- ---------------------------------------------------- -->
                <!-- STAFF HISTORY UI -->
                <!-- ---------------------------------------------------- -->
                <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden flex flex-col mb-6">
                    <form method="GET" class="p-6 border-b flex flex-wrap gap-4 bg-slate-50/50 items-end">
                        <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="staff_history">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Select Staff</label>
                            <select name="staff_id" class="px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-white font-bold text-slate-700 w-64">
                                <option value="">-- Choose Staff --</option>
                                <?php foreach($all_staff as $st): ?>
                                    <option value="<?= $st['id'] ?>" <?= (($_GET['staff_id']??'')==$st['id'])?'selected':'' ?>><?= xss_clean($st['full_name']) ?> (<?= $st['role'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-wider">Select Month</label>
                            <input type="month" name="month" value="<?= $_GET['month'] ?? date('Y-m') ?>" class="px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-white font-bold text-slate-700">
                        </div>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-md transition"><i class="fa-solid fa-filter mr-2"></i> Filter Data</button>
                    </form>
                    
                    <?php if(isset($_GET['staff_id']) && !empty($_GET['staff_id'])): ?>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 p-6 bg-white border-b border-slate-100">
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-100"><p class="text-xs text-slate-500 font-bold uppercase mb-1">Enquiries</p><p class="text-2xl font-black text-slate-800"><?= $hist_totals['cnt_enq'] ?></p></div>
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-100"><p class="text-xs text-slate-500 font-bold uppercase mb-1">Total Sales</p><p class="text-2xl font-black text-indigo-600"><?= $hist_totals['cnt_sale'] ?></p></div>
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-100"><p class="text-xs text-slate-500 font-bold uppercase mb-1">Turnover</p><p class="text-2xl font-black text-slate-800"><?= $currencySymbol ?> <?= number_format($hist_totals['sales']) ?></p></div>
                            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-100"><p class="text-xs text-emerald-600 font-bold uppercase mb-1">Generated Profit</p><p class="text-2xl font-black text-emerald-600"><?= $currencySymbol ?> <?= number_format($hist_totals['profit']) ?></p></div>
                            <div class="p-4 rounded-xl bg-amber-50 border border-amber-100"><p class="text-xs text-amber-600 font-bold uppercase mb-1">Earned Commission</p><p class="text-2xl font-black text-amber-600"><?= $currencySymbol ?> <?= number_format($hist_totals['commission']) ?></p></div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm" id="dataTable">
                                <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider text-xs border-b">
                                    <tr><th class="px-6 py-3 font-bold">Date</th><th class="px-6 py-3 font-bold">Type</th><th class="px-6 py-3 font-bold">Customer</th><th class="px-6 py-3 font-bold">Service/PNR</th><th class="px-6 py-3 font-bold">Sale (<?= $currencySymbol ?>)</th><th class="px-6 py-3 font-bold">Cost (<?= $currencySymbol ?>)</th><th class="px-6 py-3 font-bold text-emerald-600">Profit (<?= $currencySymbol ?>)</th><th class="px-6 py-3 font-bold text-amber-600">Comm. (<?= $currencySymbol ?>)</th><th class="px-6 py-3 font-bold">Status</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach($hist_records as $r): ?>
                                        <tr class="hover:bg-slate-50 text-slate-700 transition-colors">
                                            <td class="px-6 py-3 font-medium whitespace-nowrap"><?= date('d M, Y', strtotime($r['dt'])) ?></td>
                                            <td class="px-6 py-3 font-bold <?= $r['type']==='Sale'?'text-indigo-600':'text-slate-400' ?>"><?= $r['type'] ?></td>
                                            <td class="px-6 py-3 font-bold"><?= xss_clean($r['customer']) ?></td>
                                            <td class="px-6 py-3 text-xs"><?= xss_clean($r['pnr']) ?></td>
                                            <td class="px-6 py-3 font-bold"><?= $r['sale']>0 ? number_format($r['sale']) : '-' ?></td>
                                            <td class="px-6 py-3 text-slate-500"><?= $r['cost']>0 ? number_format($r['cost']) : '-' ?></td>
                                            <td class="px-6 py-3 font-bold text-emerald-500"><?= $r['profit']>0 ? number_format($r['profit']) : '-' ?></td>
                                            <td class="px-6 py-3 font-bold text-amber-500"><?= $r['comm']>0 ? number_format($r['comm']) : '-' ?></td>
                                            <td class="px-6 py-3"><span class="px-2 py-1 rounded text-xs font-bold bg-slate-100"><?= $r['status'] ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($hist_records)): ?>
                                        <tr><td colspan="9" class="p-8 text-center text-slate-400 font-medium">No activity found for this month.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-16 text-center text-slate-400">
                            <i class="fa-solid fa-user-magnifying-glass text-6xl mb-4 text-slate-200"></i>
                            <h3 class="text-xl font-bold text-slate-500">Select a Staff Member</h3>
                            <p>Choose a staff member and month from the filters above to view their detailed performance report and commission history.</p>
                        </div>
                    <?php endif; ?>
                </div>

<?php endif; ?>
