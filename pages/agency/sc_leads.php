<?php
// Student Leads page
$sc_status_opts  = ['New','Contacted','Interested','Documents Pending','Application Started','Converted','Cancelled'];
$sc_filter_status= trim($_GET['status'] ?? '');
$sc_filter_country=trim($_GET['country'] ?? '');
$sc_search       = trim($_GET['q'] ?? '');

// Load countries from settings
$sc_countries_r = $conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='countries' ORDER BY value");
$sc_countries_r->execute([$agency_id]);
$sc_countries = $sc_countries_r->fetchAll(PDO::FETCH_COLUMN);

// Load lead sources from settings
$sc_sources_r = $conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='lead_sources' ORDER BY value");
$sc_sources_r->execute([$agency_id]);
$sc_sources = $sc_sources_r->fetchAll(PDO::FETCH_COLUMN);

$where = "l.agency_id = ?"; $params = [$agency_id];
if ($sc_filter_status) { $where .= " AND l.status=?"; $params[] = $sc_filter_status; }
if ($sc_filter_country){ $where .= " AND l.preferred_country=?"; $params[] = $sc_filter_country; }
if ($sc_search) { $where .= " AND (l.student_name LIKE ? OR l.mobile LIKE ? OR l.email LIKE ?)"; $params[] = "%$sc_search%"; $params[] = "%$sc_search%"; $params[] = "%$sc_search%"; }
$rf  = $_SESSION['is_staff'] ? " AND l.reference_staff_id=".(int)$_SESSION['staff_id'] : "";

$leads_stmt = $conn->prepare("SELECT l.*, s.full_name as staff_name FROM sc_leads l LEFT JOIN staff s ON l.reference_staff_id=s.id WHERE $where $rf ORDER BY l.created_at DESC");
$leads_stmt->execute($params);
$leads = $leads_stmt->fetchAll(PDO::FETCH_ASSOC);

$statusColors = ['New'=>'bg-blue-100 text-blue-700','Contacted'=>'bg-amber-100 text-amber-700','Interested'=>'bg-indigo-100 text-indigo-700','Documents Pending'=>'bg-orange-100 text-orange-700','Application Started'=>'bg-violet-100 text-violet-700','Converted'=>'bg-emerald-100 text-emerald-700','Cancelled'=>'bg-rose-100 text-rose-700'];

// Stats
$totalLeads    = $conn->query("SELECT COUNT(*) FROM sc_leads WHERE agency_id=$agency_id")->fetchColumn();
$newLeads      = $conn->query("SELECT COUNT(*) FROM sc_leads WHERE agency_id=$agency_id AND status='New'")->fetchColumn();
$convertedLeads= $conn->query("SELECT COUNT(*) FROM sc_leads WHERE agency_id=$agency_id AND status='Converted'")->fetchColumn();
?>
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-user-graduate text-indigo-500"></i> Student Leads</h2>
            <p class="text-sm text-slate-500 mt-1">Study abroad enquiries and prospect pipeline.</p>
        </div>
        <?php if (has_permission('can_manage_sc_leads')): ?>
        <button onclick="openScLeadModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow transition flex items-center gap-2">
            <i class="fa-solid fa-plus"></i> Add Lead
        </button>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Leads</p><p class="text-3xl font-black text-indigo-600"><?= $totalLeads ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">New</p><p class="text-3xl font-black text-blue-600"><?= $newLeads ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Converted</p><p class="text-3xl font-black text-emerald-600"><?= $convertedLeads ?></p></div>
    </div>

    <!-- Filters -->
    <form method="GET" action="" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-center">
        <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="sc_leads">
        <input type="text" name="q" value="<?= xss_clean($sc_search) ?>" placeholder="Search name, mobile, email…"
               class="border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none w-52">
        <select name="status" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">All Statuses</option>
            <?php foreach ($sc_status_opts as $s): ?><option value="<?= $s ?>" <?= $sc_filter_status===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
        </select>
        <?php if (!empty($sc_countries)): ?>
        <select name="country" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">All Countries</option>
            <?php foreach ($sc_countries as $c): ?><option value="<?= xss_clean($c) ?>" <?= $sc_filter_country===$c?'selected':'' ?>><?= xss_clean($c) ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold">Filter</button>
        <a href="?route=app&page=sc_leads" class="text-sm text-slate-500 hover:text-slate-700 font-bold">Reset</a>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left">
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Student</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Country / Intake</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Staff</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php if (empty($leads)): ?>
                    <tr><td colspan="6" class="text-center py-12 text-slate-400"><i class="fa-solid fa-user-graduate text-3xl mb-2 block"></i>No leads found.</td></tr>
                <?php else: foreach ($leads as $l): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3">
                            <p class="font-bold text-slate-800"><?= xss_clean($l['student_name']) ?></p>
                            <p class="text-xs text-slate-500"><?= xss_clean($l['mobile']) ?> <?= $l['email'] ? '· '.xss_clean($l['email']) : '' ?></p>
                            <?php if ($l['ielts_score']): ?><p class="text-xs text-indigo-600 font-bold">IELTS/PTE: <?= xss_clean($l['ielts_score']) ?></p><?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-bold text-slate-700"><?= xss_clean($l['preferred_country'] ?: '—') ?></p>
                            <p class="text-xs text-slate-500"><?= xss_clean($l['preferred_intake'] ?: '') ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $statusColors[$l['status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= xss_clean($l['status']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-slate-600 text-xs font-bold"><?= xss_clean($l['staff_name'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-slate-500 text-xs"><?= $l['created_date'] ? date('d M Y', strtotime($l['created_date'])) : date('d M Y', strtotime($l['created_at'])) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                <?php if (has_permission('can_manage_sc_leads')): ?>
                                <button onclick='openScLeadModal(<?= htmlspecialchars(json_encode($l), ENT_QUOTES) ?>)' class="text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1.5 rounded-lg transition">Edit</button>
                                <?php endif; ?>
                                <?php if ($l['status'] !== 'Converted' && has_permission('can_manage_sc_students')): ?>
                                <form method="POST" action="?route=app" onsubmit="return confirm('Convert this lead to a Student profile?')">
                                    <input type="hidden" name="action" value="sc_convert_lead">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
                                    <button type="submit" class="text-xs font-bold text-emerald-600 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1.5 rounded-lg transition">Convert</button>
                                </form>
                                <?php endif; ?>
                                <?php if (!$_SESSION['is_staff'] && has_permission('can_manage_sc_leads')): ?>
                                <form method="POST" action="?route=app" onsubmit="return confirm('Delete this lead?')">
                                    <input type="hidden" name="action" value="sc_delete_lead">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                    <button type="submit" class="text-xs font-bold text-rose-500 bg-rose-50 hover:bg-rose-100 px-2.5 py-1.5 rounded-lg transition">Del</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="scLeadModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white">
        <h3 class="font-extrabold text-slate-800" id="scLeadModalTitle">Add Student Lead</h3>
        <button onclick="closeScLeadModal()" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button>
    </div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="sc_save_lead">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="id" id="sl_id">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Student Name *</label><input type="text" name="student_name" id="sl_name" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Mobile *</label><input type="text" name="mobile" id="sl_mobile" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Email</label><input type="email" name="email" id="sl_email" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Passport No.</label><input type="text" name="passport_no" id="sl_passport" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Education Background</label><input type="text" name="education_background" id="sl_edu" placeholder="e.g. A-Levels, Bachelor's…" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">IELTS/PTE Score</label><input type="text" name="ielts_score" id="sl_ielts" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Preferred Country</label>
                <select name="preferred_country" id="sl_country" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                    <option value="">— Select —</option>
                    <?php foreach ($sc_countries as $c): ?><option value="<?= xss_clean($c) ?>"><?= xss_clean($c) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Preferred University</label><input type="text" name="preferred_university" id="sl_uni" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Preferred Intake</label>
                <select name="preferred_intake" id="sl_intake" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                    <option value="">— Select —</option>
                    <?php $intk=$conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='intakes' ORDER BY value"); $intk->execute([$agency_id]); foreach($intk->fetchAll(PDO::FETCH_COLUMN) as $iv): ?><option value="<?= xss_clean($iv) ?>"><?= xss_clean($iv) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Budget</label><input type="text" name="budget" id="sl_budget" placeholder="e.g. 15,000 USD" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Lead Source</label>
                <select name="lead_source" id="sl_source" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                    <option value="">— Select —</option>
                    <?php $lsrc=$conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='lead_sources' ORDER BY value"); $lsrc->execute([$agency_id]); foreach($lsrc->fetchAll(PDO::FETCH_COLUMN) as $sv): ?><option value="<?= xss_clean($sv) ?>"><?= xss_clean($sv) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                <select name="status" id="sl_status" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                    <?php foreach ($sc_status_opts as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Date</label><input type="date" name="created_date" id="sl_date" value="<?= date('Y-m-d') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <?php if (!$_SESSION['is_staff'] && !empty($all_staff)): ?>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Assigned Staff</label>
                <select name="reference_staff_id" id="sl_staff" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                    <option value="">— None —</option>
                    <?php foreach ($all_staff as $st): ?><option value="<?= $st['id'] ?>"><?= xss_clean($st['full_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Notes</label><textarea name="notes" id="sl_notes" rows="3" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"></textarea></div>
        <div class="flex gap-3 pt-2">
            <button type="button" onclick="closeScLeadModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-xl text-sm font-bold">Cancel</button>
            <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl text-sm font-bold shadow">Save Lead</button>
        </div>
    </form>
  </div>
</div>
<script>
function openScLeadModal(d=null){
    const m=document.getElementById('scLeadModal');
    document.getElementById('scLeadModalTitle').textContent = d ? 'Edit Student Lead' : 'Add Student Lead';
    ['id','name','mobile','email','passport','edu','ielts','uni','budget','notes','date'].forEach(k=>{const el=document.getElementById('sl_'+k);if(el)el.value='';});
    ['country','intake','source','status','staff'].forEach(k=>{const el=document.getElementById('sl_'+k);if(el)el.value='';});
    document.querySelector('[name="status"]').value='New';
    document.querySelector('[name="created_date"]').value='<?= date('Y-m-d') ?>';
    if(d){
        const map={id:'id',student_name:'name',mobile:'mobile',email:'email',passport_no:'passport',education_background:'edu',ielts_score:'ielts',preferred_university:'uni',budget:'budget',notes:'notes',created_date:'date',preferred_country:'country',preferred_intake:'intake',lead_source:'source',status:'status',reference_staff_id:'staff'};
        Object.entries(map).forEach(([dk,ek])=>{const el=document.getElementById('sl_'+ek);if(el&&d[dk]!=null)el.value=d[dk];});
    }
    m.classList.remove('hidden');m.classList.add('flex');
}
function closeScLeadModal(){const m=document.getElementById('scLeadModal');m.classList.add('hidden');m.classList.remove('flex');}
</script>
