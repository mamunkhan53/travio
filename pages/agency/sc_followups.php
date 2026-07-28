<?php
// Follow-ups — show record_followups for sc_leads and sc_students
$sc_filter_module = trim($_GET['module'] ?? '');
$sc_filter_student= trim($_GET['student_id'] ?? '');
$sc_search = trim($_GET['q'] ?? '');
$sc_upcoming_only = isset($_GET['upcoming']);

$where = "rf.agency_id=? AND rf.module_name IN ('sc_leads','sc_students')"; $params = [$agency_id];
if ($sc_filter_module) { $where .= " AND rf.module_name=?"; $params[] = $sc_filter_module; }
if ($sc_filter_student){ $where .= " AND rf.record_id=?"; $params[] = $sc_filter_student; }
if ($sc_upcoming_only) { $where .= " AND rf.follow_up_date >= CURDATE()"; }
if ($sc_search) { $where .= " AND rf.note LIKE ?"; $params[] = "%$sc_search%"; }
$sf = $_SESSION['is_staff'] ? " AND rf.staff_id=".(int)$_SESSION['staff_id'] : "";

$fups = $conn->prepare("SELECT rf.*, st.full_name as staff_name FROM record_followups rf LEFT JOIN staff st ON rf.staff_id=st.id WHERE $where $sf ORDER BY rf.created_at DESC LIMIT 200");
$fups->execute($params); $fups = $fups->fetchAll(PDO::FETCH_ASSOC);

$students_r = $conn->prepare("SELECT id, student_name FROM sc_students WHERE agency_id=? ORDER BY student_name"); $students_r->execute([$agency_id]); $students_list = $students_r->fetchAll(PDO::FETCH_ASSOC);
$leads_r    = $conn->prepare("SELECT id, student_name FROM sc_leads WHERE agency_id=? ORDER BY student_name"); $leads_r->execute([$agency_id]); $leads_list = $leads_r->fetchAll(PDO::FETCH_ASSOC);

$totalFups    = $conn->query("SELECT COUNT(*) FROM record_followups WHERE agency_id=$agency_id AND module_name IN ('sc_leads','sc_students')")->fetchColumn();
$upcomingFups = $conn->query("SELECT COUNT(*) FROM record_followups WHERE agency_id=$agency_id AND module_name IN ('sc_leads','sc_students') AND follow_up_date >= CURDATE()")->fetchColumn();
$todayFups    = $conn->query("SELECT COUNT(*) FROM record_followups WHERE agency_id=$agency_id AND module_name IN ('sc_leads','sc_students') AND follow_up_date = CURDATE()")->fetchColumn();
?>
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-calendar-check text-indigo-500"></i> Follow-ups</h2><p class="text-sm text-slate-500 mt-1">All follow-up notes and reminders for students and leads.</p></div>
        <button onclick="document.getElementById('scFupModal').classList.remove('hidden');document.getElementById('scFupModal').classList.add('flex')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow transition flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Follow-up</button>
    </div>
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total Notes</p><p class="text-3xl font-black text-indigo-600"><?= $totalFups ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Upcoming</p><p class="text-3xl font-black text-amber-500"><?= $upcomingFups ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5 border-l-4 border-l-rose-400"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Due Today</p><p class="text-3xl font-black text-rose-500"><?= $todayFups ?></p></div>
    </div>
    <form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-center">
        <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="sc_followups">
        <input type="text" name="q" value="<?= xss_clean($sc_search) ?>" placeholder="Search note…" class="border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none w-44">
        <select name="module" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">Leads & Students</option>
            <option value="sc_leads" <?= $sc_filter_module==='sc_leads'?'selected':'' ?>>Student Leads</option>
            <option value="sc_students" <?= $sc_filter_module==='sc_students'?'selected':'' ?>>Students</option>
        </select>
        <label class="flex items-center gap-2 text-sm font-bold text-slate-600 cursor-pointer"><input type="checkbox" name="upcoming" <?= $sc_upcoming_only?'checked':'' ?> class="rounded"> Upcoming only</label>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold">Filter</button>
        <a href="?route=app&page=sc_followups" class="text-sm text-slate-500 hover:text-slate-700 font-bold">Reset</a>
    </form>
    <div class="space-y-3">
        <?php if (empty($fups)): ?>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-12 text-center text-slate-400">
            <i class="fa-solid fa-calendar-check text-4xl mb-3 block"></i><p class="font-bold">No follow-up records found.</p>
        </div>
        <?php else: foreach ($fups as $fu):
            $isLead = $fu['module_name'] === 'sc_leads';
            $isToday = ($fu['follow_up_date'] === date('Y-m-d'));
            $isPast  = ($fu['follow_up_date'] && $fu['follow_up_date'] < date('Y-m-d'));
        ?>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex gap-4 <?= $isPast&&$fu['follow_up_date'] ? 'border-l-4 border-l-rose-300' : ($isToday ? 'border-l-4 border-l-amber-400' : '') ?>">
            <div class="w-10 h-10 rounded-xl <?= $isLead ? 'bg-blue-50 text-blue-600' : 'bg-indigo-50 text-indigo-600' ?> flex items-center justify-center text-sm shrink-0">
                <i class="fa-solid <?= $isLead ? 'fa-user-graduate' : 'fa-id-card' ?>"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="text-xs font-extrabold px-2 py-0.5 rounded-full <?= $isLead ? 'bg-blue-100 text-blue-700' : 'bg-indigo-100 text-indigo-700' ?>"><?= $isLead ? 'Lead' : 'Student' ?></span>
                        <span class="text-xs font-bold text-slate-500 ml-2">#<?= xss_clean($fu['record_id']) ?></span>
                        <?php if ($fu['staff_name']): ?><span class="text-xs text-slate-400 ml-2">· <?= xss_clean($fu['staff_name']) ?></span><?php endif; ?>
                    </div>
                    <span class="text-xs text-slate-400 whitespace-nowrap"><?= timeAgo($fu['created_at']) ?></span>
                </div>
                <p class="text-sm text-slate-700 mt-1.5 whitespace-pre-wrap"><?= xss_clean($fu['note']) ?></p>
                <?php if ($fu['follow_up_date']): ?>
                <p class="text-xs font-bold mt-1.5 <?= $isPast ? 'text-rose-500' : ($isToday ? 'text-amber-600' : 'text-indigo-600') ?>">
                    <i class="fa-solid fa-calendar-day"></i>
                    Follow-up: <?= date('d M Y', strtotime($fu['follow_up_date'])) ?>
                    <?= $isToday ? ' (Today)' : ($isPast ? ' (Overdue)' : '') ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- Add Follow-up Modal -->
<div id="scFupModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
    <div class="px-6 py-4 border-b flex justify-between items-center"><h3 class="font-extrabold text-slate-800">Add Follow-up Note</h3><button onclick="document.getElementById('scFupModal').classList.add('hidden');document.getElementById('scFupModal').classList.remove('flex')" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="add_record_followup"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div><label class="block text-xs font-bold text-slate-700 mb-1">For</label>
            <select name="table" id="scFupTable" onchange="updateFupRecords(this.value)" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                <option value="sc_leads">Student Lead</option>
                <option value="sc_students">Student</option>
            </select>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Select Record *</label>
            <select name="record_id" id="scFupRecord" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                <option value="">— Select Lead —</option>
                <?php foreach($leads_list as $ll): ?><option value="<?= $ll['id'] ?>" data-group="sc_leads"><?= xss_clean($ll['student_name']) ?> (<?= $ll['id'] ?>)</option><?php endforeach; ?>
                <?php foreach($students_list as $sl): ?><option value="<?= $sl['id'] ?>" data-group="sc_students" style="display:none"><?= xss_clean($sl['student_name']) ?> (<?= $sl['id'] ?>)</option><?php endforeach; ?>
            </select>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Note / Action Taken *</label><textarea name="note" rows="3" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"></textarea></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Next Follow-up Date</label><input type="date" name="follow_up_date" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div class="flex gap-3"><button type="button" onclick="document.getElementById('scFupModal').classList.add('hidden');document.getElementById('scFupModal').classList.remove('flex')" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save Note</button></div>
    </form>
  </div>
</div>
<script>
function updateFupRecords(group){
    document.querySelectorAll('#scFupRecord option[data-group]').forEach(o=>{o.style.display=o.dataset.group===group?'':'none';o.disabled=o.dataset.group!==group;});
    document.getElementById('scFupRecord').value='';
    document.getElementById('scFupRecord').options[0].text='— Select '+( group==='sc_leads'?'Lead':'Student')+' —';
}
</script>
