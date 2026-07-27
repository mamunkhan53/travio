<?php ?>
                <!-- GENERIC CRUD DATA TABLE -->
                <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden flex flex-col">
                    <div class="p-5 border-b flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50">
                        <div class="relative w-full sm:w-72">
                            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="Search records..." class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                        </div>
                        <?php 
                        $can_add = true;
                        if ($_SESSION['is_staff']) {
                            if ($page === 'enquiries' && !has_permission('can_add_enquiry')) $can_add = false;
                            elseif (in_array($page, ['passports', 'visas', 'tickets', 'umrah', 'tours']) && !has_permission('can_add_sale')) $can_add = false;
                            elseif ($page === 'customers') $can_add = false;
                        }
                        if ($can_add && $page !== 'invoices'): ?>
                            <button onclick="openModal('add')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center gap-2 w-full sm:w-auto justify-center transition">
                                <i class="fa-solid fa-plus"></i> Add New Record
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm" id="dataTable">
                            <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                                <tr>
                                    <th class="px-6 py-4 font-bold">ID</th>
                                    <?php foreach ($modules[$page]['fields'] as $col => $config): 
                                        if ($col === 'referred_by') continue; 
                                        $colLabel = in_array($col, ['service_cost', 'selling_price']) ? $config['label'] . " ($currencySymbol)" : $config['label'];
                                    ?>
                                        <th class="px-6 py-4 font-bold"><?= $colLabel ?></th>
                                    <?php endforeach; ?>
                                    <?php if ($page !== 'customers'): ?>
                                        <th class="px-6 py-4 font-bold text-indigo-500">Ref. Staff</th>
                                    <?php endif; ?>
                                    <th class="px-6 py-4 font-bold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (count($records) > 0): ?>
                                    <?php foreach ($records as $row): ?>
                                        <tr class="hover:bg-slate-50 text-slate-700 transition-colors">
                                            <td class="px-6 py-4 font-extrabold text-indigo-600 whitespace-nowrap">
                                                <?php if ($page === 'customers'): ?>
                                                    <a href="?route=app&page=customer_profile&id=<?= $row['id'] ?>" class="hover:underline"><?= $row['id'] ?></a>
                                                <?php elseif (in_array($page, ['enquiries', 'passports', 'visas', 'tickets', 'umrah', 'tours'])): ?>
                                                    <a href="?route=app&page=query_history&table=<?= $page ?>&id=<?= $row['id'] ?>" class="hover:underline"><?= $row['id'] ?></a>
                                                <?php else: ?>
                                                    <?= $row['id'] ?>
                                                <?php endif; ?>
                                            </td>
                                            <?php foreach ($modules[$page]['fields'] as $col => $config): 
                                                if ($col === 'referred_by') continue; 
                                            ?>
                                                <td class="px-6 py-4 font-medium">
                                                    <?php 
                                                        $val = xss_clean($row[$col]);
                                                        if ($page === 'customers' && $col === 'name'): ?>
                                                            <a href="?route=app&page=customer_profile&id=<?= $row['id'] ?>" class="text-indigo-600 font-bold hover:underline"><?= $val ?></a>
                                                        <?php elseif ($config['type'] === 'select'): 
                                                            $color = ($val==='Completed'||$val==='Paid'||$val==='Confirmed') ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700';
                                                    ?>
                                                        <span class="px-3 py-1 <?= $color ?> rounded-lg text-xs font-bold"><?= $val ?></span>
                                                    <?php else: echo $val; endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            
                                            <?php if ($page !== 'customers'): ?>
                                            <td class="px-6 py-4 font-bold text-slate-800">
                                                <?= $row['reference_staff_id'] ? '<i class="fa-solid fa-user-tie text-indigo-400 mr-1"></i> '.xss_clean($row['reference_name']) : '<span class="text-slate-400 text-xs">System</span>' ?>
                                            </td>
                                            <?php endif; ?>

                                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                                <?php 
                                                $can_edit = true; $can_del = true;
                                                if ($_SESSION['is_staff']) {
                                                    if ($page === 'enquiries') {
                                                        $can_edit = has_permission('can_edit_enquiry'); $can_del = has_permission('can_delete_enquiry');
                                                    } elseif (in_array($page, ['passports', 'visas', 'tickets', 'umrah', 'tours'])) {
                                                        $can_edit = has_permission('can_edit_sale'); $can_del = has_permission('can_delete_sale');
                                                    } elseif ($page === 'customers') {
                                                        $can_edit = has_permission('can_manage_customers'); $can_del = has_permission('can_manage_customers');
                                                    }
                                                }
                                                ?>
                                                
                                                <?php if ($page === 'customers'): ?>
                                                    <a href="?route=app&page=customer_profile&id=<?= $row['id'] ?>" class="text-emerald-600 bg-emerald-50 w-8 h-8 inline-flex items-center justify-center rounded-lg hover:bg-emerald-100 transition" title="View Profile"><i class="fa-solid fa-address-card"></i></a>
                                                <?php endif; ?>

                                                <?php if($can_edit): ?>
                                                    <button onclick="openModal('edit', '<?= rawurlencode(json_encode($row)) ?>')" class="text-indigo-600 bg-indigo-50 w-8 h-8 rounded-lg hover:bg-indigo-100 mx-1 transition"><i class="fa-solid fa-pen"></i></button>
                                                <?php endif; ?>
                                                <?php if($can_del): ?>
                                                    <a href="?route=app&action=delete&table=<?= $page ?>&id=<?= $row['id'] ?>" onclick="return confirm('Delete this record?')" class="text-rose-600 bg-rose-50 w-8 h-8 inline-flex items-center justify-center rounded-lg hover:bg-rose-100 transition"><i class="fa-solid fa-trash"></i></a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="100%" class="px-6 py-12 text-center text-slate-400 font-medium">No records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Generic CRUD Modal -->
                <div id="dataModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-y-auto max-h-[90vh] custom-scrollbar" id="modalContent">
                        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50 sticky top-0 z-10">
                            <h3 class="font-extrabold text-slate-800 text-lg flex items-center gap-2" id="modalTitle"><i class="fa-solid fa-database text-indigo-500"></i> Record Data</h3>
                            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center transition"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <form method="POST" action="?route=app" class="p-6 sm:p-8" id="crudForm">
                            <input type="hidden" name="action" id="formAction" value="add">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="table" value="<?= $page ?>">
                            <input type="hidden" name="id" id="formId" value="">
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <?php foreach ($modules[$page]['fields'] as $col => $config): 
                                    if ($col === 'referred_by') continue; 
                                    $colLabel = in_array($col, ['service_cost', 'selling_price']) ? $config['label'] . " ($currencySymbol)" : $config['label'];
                                ?>
                                    <div class="<?= in_array($col, ['notes', 'service']) ? 'col-span-1 sm:col-span-2' : '' ?>">
                                        <label class="block text-sm font-bold text-slate-700 mb-2"><?= $colLabel ?></label>
                                        <?php if ($config['type'] === 'select'): ?>
                                            <select name="<?= $col ?>" id="input_<?= $col ?>" required class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white transition">
                                                <option value="">Select Option...</option>
                                                <?php foreach($config['options'] as $opt): ?>
                                                    <option value="<?= $opt ?>"><?= $opt ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="<?= $config['type'] ?>" name="<?= $col ?>" id="input_<?= $col ?>" <?= $config['type']==='number'?'step="0.01"':'' ?> required class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white transition" placeholder="<?= $colLabel ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (!$_SESSION['is_staff'] && in_array($page, ['enquiries', 'passports', 'visas', 'tickets', 'umrah', 'tours'])): ?>
                                    <!-- Admin Staff Reference Assign -->
                                    <div class="col-span-1 sm:col-span-2">
                                        <label class="block text-sm font-bold text-slate-700 mb-2">Reference Staff (Auto-calculates commission)</label>
                                        <select name="reference_staff_id" id="input_reference_staff_id" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white transition font-bold text-indigo-700">
                                            <option value="">-- No Staff / Self --</option>
                                            <?php foreach($all_staff as $st): ?>
                                                <option value="<?= $st['id'] ?>"><?= xss_clean($st['full_name']) ?> (<?= $st['role'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if(isset($modules[$page]['is_service'])): ?>
                                <div class="mt-6 p-4 bg-indigo-50 border border-indigo-100 rounded-xl flex items-start gap-3">
                                    <i class="fa-solid fa-bolt text-indigo-500 mt-0.5"></i>
                                    <p class="text-xs text-indigo-800 leading-relaxed font-medium"><strong>Smart Automation:</strong> Selling Price and Service Cost determine Net Profit. When status changes to <em>Completed</em>, commission is dynamically dispatched to the referenced staff. A deadline notification will be auto-scheduled.</p>
                                </div>
                            <?php endif; ?>

                            <div class="mt-8 flex flex-col sm:flex-row gap-4 pt-6 border-t border-slate-100">
                                <button type="button" onclick="closeModal()" class="w-full sm:w-1/3 py-3 border border-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-50 transition">Cancel</button>
                                <button type="submit" class="w-full sm:w-2/3 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition">Save Record</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    function openModal(action, dataStr = null) {
                        const m = document.getElementById('dataModal');
                        m.classList.remove('hidden');
                        document.getElementById('formAction').value = action;
                        
                        if (action === 'add') {
                            document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-folder-plus text-indigo-500 mr-2"></i> Add New Record';
                            document.getElementById('crudForm').reset();
                            document.getElementById('formId').value = '';
                            const txnDateEl = document.getElementById('input_transaction_date');
                            if (txnDateEl) txnDateEl.value = new Date().toISOString().split('T')[0];
                        } else if (action === 'edit' && dataStr) {
                            document.getElementById('modalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-indigo-500 mr-2"></i> Edit Record';
                            const data = JSON.parse(decodeURIComponent(dataStr));
                            document.getElementById('formId').value = data.id;
                            for (const key in data) {
                                const el = document.getElementById('input_' + key);
                                if (el) el.value = data[key];
                            }
                        }
                    }

                    function closeModal() {
                        document.getElementById('dataModal').classList.add('hidden');
                    }

                    function searchTable() {
                        const filter = document.getElementById("searchInput").value.toLowerCase();
                        const tr = document.getElementById("dataTable").getElementsByTagName("tr");
                        for (let i = 1; i < tr.length; i++) {
                            let visible = false;
                            const tds = tr[i].getElementsByTagName("td");
                            for (let j = 0; j < tds.length - 1; j++) {
                                if (tds[j] && tds[j].innerText.toLowerCase().indexOf(filter) > -1) {
                                    visible = true; break;
                                }
                            }
                            tr[i].style.display = visible ? "" : "none";
                        }
                    }
                </script>
