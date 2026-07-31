<?php
// Visa Processing — standalone list view
$sc_filter_student = trim($_GET['student_id'] ?? '');
$sc_filter_status  = trim($_GET['status'] ?? '');
$sc_search = trim($_GET['q'] ?? '');

$where = "v.agency_id=?"; $params = [$agency_id];
if ($sc_filter_student) { $where .= " AND v.student_id=?"; $params[] = $sc_filter_student; }
if ($sc_filter_status)  { $where .= " AND v.status=?"; $params[] = $sc_filter_status; }
if ($sc_search) { $where .= " AND (s.student_name LIKE ? OR v.destination_country LIKE ?)"; $params=array_merge($params,["%$sc_search%","%$sc_search%"]); }

$visas = $conn->prepare("SELECT v.*, s.student_name, s.mobile FROM sc_visa v JOIN sc_students s ON v.student_id=s.id WHERE $where ORDER BY v.created_at DESC");
$visas->execute($params); $visas = $visas->fetchAll(PDO::FETCH_ASSOC);

$students_r = $conn->prepare("SELECT id, student_name FROM sc_students WHERE agency_id=? ORDER BY student_name"); $students_r->execute([$agency_id]); $students_list = $students_r->fetchAll(PDO::FETCH_ASSOC);
$sc_countries_r = $conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='countries' ORDER BY value"); $sc_countries_r->execute([$agency_id]); $sc_countries = $sc_countries_r->fetchAll(PDO::FETCH_COLUMN);
$sc_vtypes_r = $conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='visa_types' ORDER BY value"); $sc_vtypes_r->execute([$agency_id]); $sc_vtypes = $sc_vtypes_r->fetchAll(PDO::FETCH_COLUMN);

$statusColors = ['Not Started'=>'bg-slate-100 text-slate-600','Documents Ready'=>'bg-amber-100 text-amber-700','Submitted'=>'bg-blue-100 text-blue-700','Under Review'=>'bg-violet-100 text-violet-700','Approved'=>'bg-emerald-100 text-emerald-700','Rejected'=>'bg-rose-100 text-rose-700'];
$sc_visa_statuses = array_keys($statusColors);

$totalVisa    = $conn->query("SELECT COUNT(*) FROM sc_visa v JOIN sc_students s ON v.student_id=s.id WHERE v.agency_id=$agency_id")->fetchColumn();
$approvedVisa = $conn->query("SELECT COUNT(*) FROM sc_visa v JOIN sc_students s ON v.student_id=s.id WHERE v.agency_id=$agency_id AND v.status='Approved'")->fetchColumn();
$pendingVisa  = $conn->query("SELECT COUNT(*) FROM sc_visa v JOIN sc_students s ON v.student_id=s.id WHERE v.agency_id=$agency_id AND v.status IN ('Submitted','Under Review')")->fetchColumn();
?>
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-stamp text-indigo-500"></i> Visa Processing</h2><p class="text-sm text-slate-500 mt-1">Track student visa applications and key dates.</p></div>
        <?php if (has_permission('can_manage_sc_applications')): ?>
        <button onclick="document.getElementById('scVisaListModal').classList.remove('hidden');document.getElementById('scVisaListModal').classList.add('flex')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow transition flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Visa Record</button>
        <?php endif; ?>
    </div>
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total Cases</p><p class="text-3xl font-black text-indigo-600"><?= $totalVisa ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Approved</p><p class="text-3xl font-black text-emerald-600"><?= $approvedVisa ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">In Progress</p><p class="text-3xl font-black text-amber-500"><?= $pendingVisa ?></p></div>
    </div>
    <form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-center">
        <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="sc_visa">
        <input type="text" name="q" value="<?= xss_clean($sc_search) ?>" placeholder="Search student, country…" class="border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none w-48">
        <select name="status" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">All Statuses</option>
            <?php foreach($sc_visa_statuses as $s): ?><option value="<?= $s ?>" <?= $sc_filter_status===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
        </select>
        <select name="student_id" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">All Students</option>
            <?php foreach($students_list as $sl): ?><option value="<?= $sl['id'] ?>" <?= $sc_filter_student===$sl['id']?'selected':'' ?>><?= xss_clean($sl['student_name']) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold">Filter</button>
        <a href="/app/sc_visa" class="text-sm text-slate-500 hover:text-slate-700 font-bold">Reset</a>
    </form>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left">
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Student</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Country / Type</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Key Dates</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Visa No.</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php if (empty($visas)): ?>
                    <tr><td colspan="6" class="text-center py-12 text-slate-400"><i class="fa-solid fa-stamp text-3xl mb-2 block"></i>No visa records found.</td></tr>
                <?php else: foreach ($visas as $v): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3"><a href="/app/sc_students?id=<?= $v['student_id'] ?>&tab=visa" class="font-bold text-indigo-700 hover:underline"><?= xss_clean($v['student_name']) ?></a></td>
                        <td class="px-4 py-3"><p class="font-bold text-slate-800"><?= xss_clean($v['destination_country']??'—') ?></p><p class="text-xs text-slate-500"><?= xss_clean($v['visa_type']??'') ?></p></td>
                        <td class="px-4 py-3 text-xs space-y-0.5">
                            <?php if($v['application_date']): ?><p class="text-slate-500">Applied: <span class="font-bold text-slate-700"><?= date('d M Y',strtotime($v['application_date'])) ?></span></p><?php endif; ?>
                            <?php if($v['interview_date']): ?><p class="text-violet-600 font-bold">Interview: <?= date('d M Y',strtotime($v['interview_date'])) ?></p><?php endif; ?>
                            <?php if($v['decision_date']): ?><p class="text-emerald-600 font-bold">Decision: <?= date('d M Y',strtotime($v['decision_date'])) ?></p><?php endif; ?>
                        </td>
                        <td class="px-4 py-3"><span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $statusColors[$v['status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= xss_clean($v['status']) ?></span></td>
                        <td class="px-4 py-3 text-sm font-bold text-slate-700"><?= xss_clean($v['visa_number']??'—') ?></td>
                        <td class="px-4 py-3">
                            <button onclick='openScVisaEdit(<?= htmlspecialchars(json_encode($v), ENT_QUOTES) ?>)' class="text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1.5 rounded-lg transition">Edit</button>
                            <?php if (!$_SESSION['is_staff']): ?>
                            <form method="POST" action="" class="inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="sc_delete_visa"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="ml-1 text-xs font-bold text-rose-500 bg-rose-50 hover:bg-rose-100 px-2.5 py-1.5 rounded-lg transition">Del</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Visa Modal -->
<div id="scVisaListModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800" id="scVisaModalTitle">Add Visa Record</h3><button onclick="closeScVisaModal()" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="" class="p-6 space-y-4">
        <input type="hidden" name="action" value="sc_save_visa"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="id" id="sv_id">
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Student *</label><select name="student_id" id="sv_student" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select Student —</option><?php foreach($students_list as $sl): ?><option value="<?= $sl['id'] ?>"><?= xss_clean($sl['student_name']) ?></option><?php endforeach; ?></select></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Destination Country</label><select name="destination_country" id="sv_country" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select —</option><?php foreach($sc_countries as $c): ?><option value="<?= xss_clean($c) ?>"><?= xss_clean($c) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Visa Type</label><select name="visa_type" id="sv_type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select —</option><?php foreach($sc_vtypes as $vt): ?><option value="<?= xss_clean($vt) ?>"><?= xss_clean($vt) ?></option><?php endforeach; ?></select></div>
            <div class="col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">Embassy</label><input type="text" name="embassy" id="sv_embassy" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <?php foreach(['application_date'=>'Application Date','biometrics_date'=>'Biometrics Date','medical_date'=>'Medical Date','interview_date'=>'Interview Date','decision_date'=>'Decision Date'] as $vf=>$vl): ?>
            <div><label class="block text-xs font-bold text-slate-700 mb-1"><?= $vl ?></label><input type="date" name="<?= $vf ?>" id="sv_<?= $vf ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <?php endforeach; ?>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Status</label><select name="status" id="sv_status" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><?php foreach($sc_visa_statuses as $vs): ?><option><?= $vs ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Visa Number</label><input type="text" name="visa_number" id="sv_visa_number" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        </div>
        <div class="flex gap-3"><button type="button" onclick="closeScVisaModal()" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save</button></div>
    </form>
  </div>
</div>
<script>
function openScVisaEdit(d){
    document.getElementById('scVisaModalTitle').textContent='Edit Visa Record';
    document.getElementById('sv_id').value=d.id||'';
    document.getElementById('sv_student').value=d.student_id||'';
    document.getElementById('sv_country').value=d.destination_country||'';
    document.getElementById('sv_type').value=d.visa_type||'';
    document.getElementById('sv_embassy').value=d.embassy||'';
    ['application_date','biometrics_date','medical_date','interview_date','decision_date'].forEach(f=>{const el=document.getElementById('sv_'+f);if(el)el.value=d[f]||'';});
    document.getElementById('sv_status').value=d.status||'Not Started';
    document.getElementById('sv_visa_number').value=d.visa_number||'';
    document.getElementById('scVisaListModal').classList.remove('hidden');document.getElementById('scVisaListModal').classList.add('flex');
}
function closeScVisaModal(){const m=document.getElementById('scVisaListModal');m.classList.add('hidden');m.classList.remove('flex');document.getElementById('scVisaModalTitle').textContent='Add Visa Record';document.getElementById('sv_id').value='';}
</script>
