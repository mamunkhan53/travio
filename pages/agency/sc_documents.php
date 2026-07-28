<?php
// Documents — standalone list view across all students
$sc_filter_student = trim($_GET['student_id'] ?? '');
$sc_filter_status  = trim($_GET['status'] ?? '');
$sc_search = trim($_GET['q'] ?? '');

$where = "d.agency_id=?"; $params = [$agency_id];
if ($sc_filter_student) { $where .= " AND d.student_id=?"; $params[] = $sc_filter_student; }
if ($sc_filter_status)  { $where .= " AND d.doc_status=?"; $params[] = $sc_filter_status; }
if ($sc_search) { $where .= " AND (s.student_name LIKE ? OR d.doc_type LIKE ?)"; $params=array_merge($params,["%$sc_search%","%$sc_search%"]); }

$docs = $conn->prepare("SELECT d.*, s.student_name FROM sc_documents d JOIN sc_students s ON d.student_id=s.id WHERE $where ORDER BY d.created_at DESC");
$docs->execute($params); $docs = $docs->fetchAll(PDO::FETCH_ASSOC);

$students_r = $conn->prepare("SELECT id, student_name FROM sc_students WHERE agency_id=? ORDER BY student_name"); $students_r->execute([$agency_id]); $students_list = $students_r->fetchAll(PDO::FETCH_ASSOC);
$sc_doctypes_r = $conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='document_types' ORDER BY value"); $sc_doctypes_r->execute([$agency_id]); $sc_doctypes = $sc_doctypes_r->fetchAll(PDO::FETCH_COLUMN);

$statusColors = ['Uploaded'=>'bg-blue-100 text-blue-700','Pending'=>'bg-amber-100 text-amber-700','Verified'=>'bg-emerald-100 text-emerald-700','Expired'=>'bg-rose-100 text-rose-700'];

$totalDocs    = $conn->query("SELECT COUNT(*) FROM sc_documents d JOIN sc_students s ON d.student_id=s.id WHERE d.agency_id=$agency_id")->fetchColumn();
$pendingDocs  = $conn->query("SELECT COUNT(*) FROM sc_documents d JOIN sc_students s ON d.student_id=s.id WHERE d.agency_id=$agency_id AND d.doc_status='Pending'")->fetchColumn();
$verifiedDocs = $conn->query("SELECT COUNT(*) FROM sc_documents d JOIN sc_students s ON d.student_id=s.id WHERE d.agency_id=$agency_id AND d.doc_status='Verified'")->fetchColumn();
$expiredDocs  = $conn->query("SELECT COUNT(*) FROM sc_documents d JOIN sc_students s ON d.student_id=s.id WHERE d.agency_id=$agency_id AND d.doc_status='Expired'")->fetchColumn();
?>
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-folder-open text-indigo-500"></i> Documents</h2><p class="text-sm text-slate-500 mt-1">Manage all student documents and their verification status.</p></div>
        <?php if (has_permission('can_manage_sc_students')): ?>
        <button onclick="document.getElementById('scDocListModal').classList.remove('hidden');document.getElementById('scDocListModal').classList.add('flex')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow transition flex items-center gap-2"><i class="fa-solid fa-upload"></i> Upload Document</button>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total</p><p class="text-3xl font-black text-indigo-600"><?= $totalDocs ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Pending</p><p class="text-3xl font-black text-amber-500"><?= $pendingDocs ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Verified</p><p class="text-3xl font-black text-emerald-600"><?= $verifiedDocs ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Expired</p><p class="text-3xl font-black text-rose-500"><?= $expiredDocs ?></p></div>
    </div>

    <form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-center">
        <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="sc_documents">
        <input type="text" name="q" value="<?= xss_clean($sc_search) ?>" placeholder="Search student, type…" class="border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none w-48">
        <select name="status" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">All Statuses</option>
            <?php foreach(array_keys($statusColors) as $s): ?><option value="<?= $s ?>" <?= $sc_filter_status===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
        </select>
        <select name="student_id" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">All Students</option>
            <?php foreach($students_list as $sl): ?><option value="<?= $sl['id'] ?>" <?= $sc_filter_student===$sl['id']?'selected':'' ?>><?= xss_clean($sl['student_name']) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold">Filter</button>
        <a href="?route=app&page=sc_documents" class="text-sm text-slate-500 hover:text-slate-700 font-bold">Reset</a>
    </form>

    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left">
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Student</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Document Type</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">File</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Expiry</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Uploaded</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php if (empty($docs)): ?>
                    <tr><td colspan="7" class="text-center py-12 text-slate-400"><i class="fa-solid fa-folder-open text-3xl mb-2 block"></i>No documents found.</td></tr>
                <?php else: foreach ($docs as $doc): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3"><a href="?route=app&page=sc_students&id=<?= $doc['student_id'] ?>&tab=documents" class="font-bold text-indigo-700 hover:underline text-sm"><?= xss_clean($doc['student_name']) ?></a></td>
                        <td class="px-4 py-3 font-bold text-slate-700"><?= xss_clean($doc['doc_type']??'—') ?></td>
                        <td class="px-4 py-3">
                            <?php if ($doc['file_path']): ?>
                            <a href="/<?= xss_clean($doc['file_path']) ?>" target="_blank" class="text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1.5 rounded-lg inline-flex items-center gap-1 transition"><i class="fa-solid fa-download text-xs"></i> Download</a>
                            <?php else: ?>
                            <span class="text-xs text-slate-400">No file</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3"><span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $statusColors[$doc['doc_status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= xss_clean($doc['doc_status']) ?></span></td>
                        <td class="px-4 py-3 text-xs <?= ($doc['expiry_date'] && $doc['expiry_date'] < date('Y-m-d')) ? 'text-rose-600 font-bold' : 'text-slate-500' ?>"><?= $doc['expiry_date'] ? date('d M Y', strtotime($doc['expiry_date'])) : '—' ?></td>
                        <td class="px-4 py-3 text-xs text-slate-500"><?= timeAgo($doc['created_at']) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <?php if (has_permission('can_manage_sc_students')): ?>
                                <button onclick='openDocEditModal(<?= htmlspecialchars(json_encode($doc), ENT_QUOTES) ?>)' class="text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 px-2.5 py-1.5 rounded-lg transition">Edit Status</button>
                                <form method="POST" action="?route=app" class="inline" onsubmit="return confirm('Delete document?')"><input type="hidden" name="action" value="sc_delete_document"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" value="<?= $doc['id'] ?>"><button class="text-xs font-bold text-rose-500 bg-rose-50 hover:bg-rose-100 px-2.5 py-1.5 rounded-lg transition">Del</button></form>
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

<!-- Upload Modal -->
<div id="scDocListModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800">Upload Document</h3><button onclick="document.getElementById('scDocListModal').classList.add('hidden');document.getElementById('scDocListModal').classList.remove('flex')" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" enctype="multipart/form-data" class="p-6 space-y-4">
        <input type="hidden" name="action" value="sc_upload_document"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Student *</label><select name="student_id" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select Student —</option><?php foreach($students_list as $sl): ?><option value="<?= $sl['id'] ?>"><?= xss_clean($sl['student_name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Document Type</label><select name="doc_type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="Other">Other</option><?php foreach($sc_doctypes as $dt): ?><option value="<?= xss_clean($dt) ?>"><?= xss_clean($dt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Select File</label><input type="file" name="doc_file" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm"></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Status</label><select name="doc_status" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option>Uploaded</option><option>Pending</option><option>Verified</option></select></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Expiry Date</label><input type="date" name="expiry_date" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        </div>
        <div class="flex gap-3"><button type="button" onclick="document.getElementById('scDocListModal').classList.add('hidden');document.getElementById('scDocListModal').classList.remove('flex')" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Upload</button></div>
    </form>
  </div>
</div>

<!-- Edit Status Modal -->
<div id="scDocEditModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
    <div class="px-6 py-4 border-b flex justify-between items-center"><h3 class="font-extrabold text-slate-800">Update Document Status</h3><button onclick="document.getElementById('scDocEditModal').classList.add('hidden');document.getElementById('scDocEditModal').classList.remove('flex')" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="sc_update_document"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" id="docEditId">
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Status</label><select name="doc_status" id="docEditStatus" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option>Uploaded</option><option>Pending</option><option>Verified</option><option>Expired</option></select></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Expiry Date</label><input type="date" name="expiry_date" id="docEditExpiry" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Notes</label><input type="text" name="doc_notes" id="docEditNotes" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div class="flex gap-3"><button type="button" onclick="document.getElementById('scDocEditModal').classList.add('hidden');document.getElementById('scDocEditModal').classList.remove('flex')" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save</button></div>
    </form>
  </div>
</div>
<script>
function openDocEditModal(d){
    document.getElementById('docEditId').value=d.id;
    document.getElementById('docEditStatus').value=d.doc_status||'Pending';
    document.getElementById('docEditExpiry').value=d.expiry_date||'';
    document.getElementById('docEditNotes').value=d.notes||'';
    const m=document.getElementById('scDocEditModal');m.classList.remove('hidden');m.classList.add('flex');
}
</script>
