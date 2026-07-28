<?php
// University Applications — standalone list with student filter
$sc_filter_student = trim($_GET['student_id'] ?? '');
$sc_filter_status  = trim($_GET['status'] ?? '');
$sc_search = trim($_GET['q'] ?? '');

$where = "a.agency_id=?"; $params = [$agency_id];
if ($sc_filter_student) { $where .= " AND a.student_id=?"; $params[] = $sc_filter_student; }
if ($sc_filter_status)  { $where .= " AND a.offer_status=?"; $params[] = $sc_filter_status; }
if ($sc_search) { $where .= " AND (a.university_name LIKE ? OR a.course LIKE ? OR s.student_name LIKE ?)"; $params=array_merge($params,["%$sc_search%","%$sc_search%","%$sc_search%"]); }

$apps = $conn->prepare("SELECT a.*, s.student_name, s.mobile FROM sc_applications a JOIN sc_students s ON a.student_id=s.id WHERE $where ORDER BY a.created_at DESC");
$apps->execute($params); $apps = $apps->fetchAll(PDO::FETCH_ASSOC);

$statusColors = ['Draft'=>'bg-slate-100 text-slate-600','Applied'=>'bg-blue-100 text-blue-700','Waiting Decision'=>'bg-amber-100 text-amber-700','Conditional Offer'=>'bg-violet-100 text-violet-700','Unconditional Offer'=>'bg-indigo-100 text-indigo-700','Accepted'=>'bg-emerald-100 text-emerald-700','Rejected'=>'bg-rose-100 text-rose-700'];

$sc_unis_r = $conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='universities' ORDER BY value"); $sc_unis_r->execute([$agency_id]); $sc_unis = $sc_unis_r->fetchAll(PDO::FETCH_COLUMN);
$sc_intakes_r = $conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='intakes' ORDER BY value"); $sc_intakes_r->execute([$agency_id]); $sc_intakes = $sc_intakes_r->fetchAll(PDO::FETCH_COLUMN);
$students_r = $conn->prepare("SELECT id, student_name FROM sc_students WHERE agency_id=? ORDER BY student_name"); $students_r->execute([$agency_id]); $students_list = $students_r->fetchAll(PDO::FETCH_ASSOC);

$totalApps = $conn->query("SELECT COUNT(*) FROM sc_applications a JOIN sc_students s ON a.student_id=s.id WHERE a.agency_id=$agency_id")->fetchColumn();
$acceptedApps = $conn->query("SELECT COUNT(*) FROM sc_applications a JOIN sc_students s ON a.student_id=s.id WHERE a.agency_id=$agency_id AND a.offer_status='Accepted'")->fetchColumn();
?>
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-building-columns text-indigo-500"></i> University Applications</h2><p class="text-sm text-slate-500 mt-1">Track all student university applications.</p></div>
        <?php if (has_permission('can_manage_sc_applications')): ?>
        <button onclick="document.getElementById('scAppListModal').classList.remove('hidden');document.getElementById('scAppListModal').classList.add('flex')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow transition flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Application</button>
        <?php endif; ?>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total</p><p class="text-3xl font-black text-indigo-600"><?= $totalApps ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Accepted</p><p class="text-3xl font-black text-emerald-600"><?= $acceptedApps ?></p></div>
    </div>
    <form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-center">
        <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="sc_applications">
        <input type="text" name="q" value="<?= xss_clean($sc_search) ?>" placeholder="Search university, course…" class="border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none w-52">
        <select name="status" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">All Statuses</option>
            <?php foreach(array_keys($statusColors) as $s): ?><option value="<?= $s ?>" <?= $sc_filter_status===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
        </select>
        <select name="student_id" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">All Students</option>
            <?php foreach($students_list as $sl): ?><option value="<?= $sl['id'] ?>" <?= $sc_filter_student===$sl['id']?'selected':'' ?>><?= xss_clean($sl['student_name']) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold">Filter</button>
        <a href="?route=app&page=sc_applications" class="text-sm text-slate-500 hover:text-slate-700 font-bold">Reset</a>
    </form>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left">
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Student</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">University / Course</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Intake</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Fees</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php if (empty($apps)): ?>
                    <tr><td colspan="6" class="text-center py-12 text-slate-400"><i class="fa-solid fa-building-columns text-3xl mb-2 block"></i>No applications found.</td></tr>
                <?php else: foreach ($apps as $ap): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3"><a href="?route=app&page=sc_students&id=<?= $ap['student_id'] ?>&tab=applications" class="font-bold text-indigo-700 hover:underline"><?= xss_clean($ap['student_name']) ?></a><p class="text-xs text-slate-500"><?= xss_clean($ap['mobile']) ?></p></td>
                        <td class="px-4 py-3"><p class="font-bold text-slate-800"><?= xss_clean($ap['university_name']??'—') ?></p><p class="text-xs text-slate-500"><?= xss_clean($ap['course']??'') ?></p></td>
                        <td class="px-4 py-3 text-sm text-slate-600"><?= xss_clean($ap['intake']??'—') ?></td>
                        <td class="px-4 py-3"><p class="text-sm font-bold text-slate-700"><?= number_format($ap['tuition_fee'],2) ?></p><?php if($ap['scholarship']>0): ?><p class="text-xs text-emerald-600 font-bold">-<?= number_format($ap['scholarship'],2) ?> scholarship</p><?php endif; ?></td>
                        <td class="px-4 py-3"><span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $statusColors[$ap['offer_status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= xss_clean($ap['offer_status']) ?></span></td>
                        <td class="px-4 py-3">
                            <a href="?route=app&page=sc_students&id=<?= $ap['student_id'] ?>&tab=applications" class="text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1.5 rounded-lg transition">View</a>
                            <?php if (!$_SESSION['is_staff'] && has_permission('can_manage_sc_applications')): ?>
                            <form method="POST" action="?route=app" class="inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="sc_delete_application"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" value="<?= $ap['id'] ?>"><button class="ml-1 text-xs font-bold text-rose-500 bg-rose-50 hover:bg-rose-100 px-2.5 py-1.5 rounded-lg transition">Del</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Add Application Modal -->
<div id="scAppListModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800">Add Application</h3><button onclick="document.getElementById('scAppListModal').classList.add('hidden');document.getElementById('scAppListModal').classList.remove('flex')" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="sc_save_application"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Student *</label><select name="student_id" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select Student —</option><?php foreach($students_list as $sl): ?><option value="<?= $sl['id'] ?>"><?= xss_clean($sl['student_name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">University *</label><input list="scal_unis" name="university_name" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><datalist id="scal_unis"><?php foreach($sc_unis as $u): ?><option value="<?= xss_clean($u) ?>"><?php endforeach; ?></datalist></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Course</label><input type="text" name="course" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Intake</label><select name="intake" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select —</option><?php foreach($sc_intakes as $i): ?><option><?= xss_clean($i) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Tuition Fee</label><input type="number" step="0.01" name="tuition_fee" value="0" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Scholarship</label><input type="number" step="0.01" name="scholarship" value="0" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Application Date</label><input type="date" name="application_date" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Offer Status</label><select name="offer_status" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><?php foreach(array_keys($statusColors) as $s): ?><option><?= $s ?></option><?php endforeach; ?></select></div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Notes</label><textarea name="notes" rows="2" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"></textarea></div>
        <div class="flex gap-3"><button type="button" onclick="document.getElementById('scAppListModal').classList.add('hidden');document.getElementById('scAppListModal').classList.remove('flex')" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save</button></div>
    </form>
  </div>
</div>
