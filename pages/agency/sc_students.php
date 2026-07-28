<?php
// Students page — list or profile view
$sc_student_id = trim($_GET['id'] ?? '');
$sc_stab = $_GET['tab'] ?? 'overview';

if ($sc_student_id) {
    // ── PROFILE VIEW ────────────────────────────────────────────────────────
    $profile_stmt = $conn->prepare("SELECT s.*, st.full_name as staff_name FROM sc_students s LEFT JOIN staff st ON s.reference_staff_id=st.id WHERE s.id=? AND s.agency_id=?");
    $profile_stmt->execute([$sc_student_id, $agency_id]);
    $sc_prof = $profile_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sc_prof) { flash("Student not found.", "error"); redirect("?route=app&page=sc_students"); }

    $sc_apps    = $conn->prepare("SELECT * FROM sc_applications WHERE student_id=? AND agency_id=? ORDER BY created_at DESC");
    $sc_apps->execute([$sc_student_id,$agency_id]); $sc_apps = $sc_apps->fetchAll(PDO::FETCH_ASSOC);

    $sc_docs    = $conn->prepare("SELECT * FROM sc_documents WHERE student_id=? AND agency_id=? ORDER BY created_at DESC");
    $sc_docs->execute([$sc_student_id,$agency_id]); $sc_docs = $sc_docs->fetchAll(PDO::FETCH_ASSOC);

    $sc_visas   = $conn->prepare("SELECT * FROM sc_visa WHERE student_id=? AND agency_id=? ORDER BY created_at DESC");
    $sc_visas->execute([$sc_student_id,$agency_id]); $sc_visas = $sc_visas->fetchAll(PDO::FETCH_ASSOC);

    $sc_pmts    = $conn->prepare("SELECT * FROM sc_payments WHERE student_id=? AND agency_id=? ORDER BY created_at DESC");
    $sc_pmts->execute([$sc_student_id,$agency_id]); $sc_pmts = $sc_pmts->fetchAll(PDO::FETCH_ASSOC);

    $sc_fups    = $conn->prepare("SELECT rf.*, st.full_name as staff_name FROM record_followups rf LEFT JOIN staff st ON rf.staff_id=st.id WHERE rf.agency_id=? AND rf.module_name='sc_students' AND rf.record_id=? ORDER BY rf.created_at DESC");
    $sc_fups->execute([$agency_id,$sc_student_id]); $sc_fups = $sc_fups->fetchAll(PDO::FETCH_ASSOC);

    $totalPaid = array_sum(array_column($sc_pmts,'paid_amount'));
    $totalDue  = array_sum(array_column($sc_pmts,'due_amount'));

    $sc_tabs = ['overview'=>'Overview','applications'=>'Applications ('.count($sc_apps).')','documents'=>'Documents ('.count($sc_docs).')','visa'=>'Visa ('.count($sc_visas).')','payments'=>'Payments ('.count($sc_pmts).')','timeline'=>'Timeline ('.count($sc_fups).')'];
    $appStatColors = ['Draft'=>'bg-slate-100 text-slate-600','Applied'=>'bg-blue-100 text-blue-700','Waiting Decision'=>'bg-amber-100 text-amber-700','Conditional Offer'=>'bg-violet-100 text-violet-700','Unconditional Offer'=>'bg-indigo-100 text-indigo-700','Accepted'=>'bg-emerald-100 text-emerald-700','Rejected'=>'bg-rose-100 text-rose-700'];
    $docStatColors  = ['Uploaded'=>'bg-blue-100 text-blue-700','Pending'=>'bg-amber-100 text-amber-700','Verified'=>'bg-emerald-100 text-emerald-700','Expired'=>'bg-rose-100 text-rose-700'];
    $visaStatColors = ['Not Started'=>'bg-slate-100 text-slate-600','Documents Ready'=>'bg-amber-100 text-amber-700','Submitted'=>'bg-blue-100 text-blue-700','Under Review'=>'bg-violet-100 text-violet-700','Approved'=>'bg-emerald-100 text-emerald-700','Rejected'=>'bg-rose-100 text-rose-700'];

    // countries/intakes/doc_types for forms
    $sc_countries_r=$conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='countries' ORDER BY value"); $sc_countries_r->execute([$agency_id]);
    $sc_countries=$sc_countries_r->fetchAll(PDO::FETCH_COLUMN);
    $sc_intakes_r=$conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='intakes' ORDER BY value"); $sc_intakes_r->execute([$agency_id]);
    $sc_intakes=$sc_intakes_r->fetchAll(PDO::FETCH_COLUMN);
    $sc_doctypes_r=$conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='document_types' ORDER BY value"); $sc_doctypes_r->execute([$agency_id]);
    $sc_doctypes=$sc_doctypes_r->fetchAll(PDO::FETCH_COLUMN);
    $sc_unis_r=$conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='universities' ORDER BY value"); $sc_unis_r->execute([$agency_id]);
    $sc_unis=$sc_unis_r->fetchAll(PDO::FETCH_COLUMN);
    $sc_pcats_r=$conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='payment_categories' ORDER BY value"); $sc_pcats_r->execute([$agency_id]);
    $sc_pcats=$sc_pcats_r->fetchAll(PDO::FETCH_COLUMN);
    $sc_vtypes_r=$conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='visa_types' ORDER BY value"); $sc_vtypes_r->execute([$agency_id]);
    $sc_vtypes=$sc_vtypes_r->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="space-y-5">
    <div class="flex items-center gap-3">
        <a href="?route=app&page=sc_students" class="text-slate-400 hover:text-indigo-600 transition"><i class="fa-solid fa-arrow-left"></i></a>
        <div class="flex-1">
            <h2 class="text-2xl font-extrabold text-slate-800"><?= xss_clean($sc_prof['student_name']) ?></h2>
            <p class="text-sm text-slate-500"><?= xss_clean($sc_prof['mobile']) ?> <?= $sc_prof['email'] ? '· '.xss_clean($sc_prof['email']) : '' ?> · ID: <?= $sc_student_id ?></p>
        </div>
        <span class="text-sm font-bold px-3 py-1.5 rounded-full <?= $sc_prof['current_status']==='Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>"><?= xss_clean($sc_prof['current_status']) ?></span>
        <?php if (has_permission('can_manage_sc_students')): ?>
        <button onclick="document.getElementById('scEditStudentModal').classList.remove('hidden');document.getElementById('scEditStudentModal').classList.add('flex')" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-4 py-2 rounded-xl text-sm font-bold transition">Edit</button>
        <?php endif; ?>
    </div>

    <!-- Quick stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Applications</p><p class="text-2xl font-black text-indigo-600"><?= count($sc_apps) ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Documents</p><p class="text-2xl font-black text-blue-600"><?= count($sc_docs) ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Total Paid</p><p class="text-2xl font-black text-emerald-600"><?= number_format($totalPaid,2) ?></p></div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4"><p class="text-xs font-bold text-slate-400 uppercase mb-1">Due</p><p class="text-2xl font-black text-rose-500"><?= number_format($totalDue,2) ?></p></div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="flex border-b border-slate-100 overflow-x-auto">
            <?php foreach ($sc_tabs as $tk => $tl): ?>
            <a href="?route=app&page=sc_students&id=<?= $sc_student_id ?>&tab=<?= $tk ?>"
               class="px-4 py-3 text-sm font-bold whitespace-nowrap transition <?= $sc_stab===$tk ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500 hover:text-indigo-600' ?>">
               <?= $tl ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="p-6">

        <?php if ($sc_stab === 'overview'): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <h4 class="font-extrabold text-slate-700 border-b pb-2">Personal Information</h4>
                    <?php $pf=['date_of_birth'=>'Date of Birth','nationality'=>'Nationality','passport_no'=>'Passport No.','passport_expiry'=>'Passport Expiry','ielts_score'=>'IELTS/PTE Score']; foreach($pf as $k=>$l): if(!empty($sc_prof[$k])): ?>
                    <div class="flex gap-2"><span class="text-xs font-bold text-slate-400 w-32 shrink-0"><?= $l ?></span><span class="text-sm font-bold text-slate-700"><?= xss_clean($sc_prof[$k]) ?></span></div>
                    <?php endif; endforeach; ?>
                </div>
                <div class="space-y-3">
                    <h4 class="font-extrabold text-slate-700 border-b pb-2">Guardian Information</h4>
                    <?php $gf=['guardian_name'=>'Name','guardian_mobile'=>'Mobile','guardian_relation'=>'Relation']; foreach($gf as $k=>$l): if(!empty($sc_prof[$k])): ?>
                    <div class="flex gap-2"><span class="text-xs font-bold text-slate-400 w-32 shrink-0"><?= $l ?></span><span class="text-sm font-bold text-slate-700"><?= xss_clean($sc_prof[$k]) ?></span></div>
                    <?php endif; endforeach; ?>
                </div>
                <?php if($sc_prof['education_background']): ?>
                <div class="sm:col-span-2 space-y-2"><h4 class="font-extrabold text-slate-700 border-b pb-2">Education Background</h4><p class="text-sm text-slate-600 whitespace-pre-wrap"><?= xss_clean($sc_prof['education_background']) ?></p></div>
                <?php endif; ?>
                <?php if($sc_prof['notes']): ?>
                <div class="sm:col-span-2 space-y-2"><h4 class="font-extrabold text-slate-700 border-b pb-2">Notes</h4><p class="text-sm text-slate-600 whitespace-pre-wrap"><?= xss_clean($sc_prof['notes']) ?></p></div>
                <?php endif; ?>
            </div>

        <?php elseif ($sc_stab === 'applications'): ?>
            <?php if (has_permission('can_manage_sc_applications')): ?>
            <button onclick="document.getElementById('scAppModal').classList.remove('hidden');document.getElementById('scAppModal').classList.add('flex')" class="mb-4 bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow inline-flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Application</button>
            <?php endif; ?>
            <?php if(empty($sc_apps)): ?><p class="text-slate-400 text-sm py-8 text-center"><i class="fa-solid fa-building-columns text-3xl block mb-2"></i>No applications yet.</p>
            <?php else: ?>
            <div class="space-y-3">
            <?php foreach($sc_apps as $ap): ?>
                <div class="border border-slate-100 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="flex-1">
                        <p class="font-extrabold text-slate-800"><?= xss_clean($ap['university_name']??'—') ?></p>
                        <p class="text-sm text-slate-600"><?= xss_clean($ap['course']??'') ?> <?= $ap['intake'] ? '· '.xss_clean($ap['intake']) : '' ?></p>
                        <p class="text-xs text-slate-400 mt-1">Tuition: <?= number_format($ap['tuition_fee'],2) ?> <?= $ap['scholarship']>0 ? '· Scholarship: '.number_format($ap['scholarship'],2) : '' ?></p>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $appStatColors[$ap['offer_status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= xss_clean($ap['offer_status']) ?></span>
                    <?php if($ap['offer_letter_path']): ?><a href="/<?= xss_clean($ap['offer_letter_path']) ?>" target="_blank" class="text-xs font-bold text-indigo-600 hover:underline">Offer Letter</a><?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php elseif ($sc_stab === 'documents'): ?>
            <?php if (has_permission('can_manage_sc_students')): ?>
            <button onclick="document.getElementById('scDocModal').classList.remove('hidden');document.getElementById('scDocModal').classList.add('flex')" class="mb-4 bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow inline-flex items-center gap-2"><i class="fa-solid fa-upload"></i> Upload Document</button>
            <?php endif; ?>
            <?php if(empty($sc_docs)): ?><p class="text-slate-400 text-sm py-8 text-center"><i class="fa-solid fa-folder-open text-3xl block mb-2"></i>No documents uploaded.</p>
            <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php foreach($sc_docs as $doc): ?>
                <div class="border border-slate-100 rounded-xl p-4 flex items-start gap-3">
                    <i class="fa-solid fa-file-alt text-indigo-400 text-xl mt-0.5"></i>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-slate-800 text-sm"><?= xss_clean($doc['doc_type']??'Document') ?></p>
                        <p class="text-xs text-slate-500 truncate"><?= xss_clean($doc['file_name']??'') ?></p>
                        <?php if($doc['expiry_date']): ?><p class="text-xs text-amber-600 font-bold">Expires: <?= date('d M Y',strtotime($doc['expiry_date'])) ?></p><?php endif; ?>
                    </div>
                    <div class="flex flex-col items-end gap-1.5">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?= $docStatColors[$doc['doc_status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= xss_clean($doc['doc_status']) ?></span>
                        <?php if($doc['file_path']): ?><a href="/<?= xss_clean($doc['file_path']) ?>" target="_blank" class="text-xs font-bold text-indigo-600 hover:underline">Download</a><?php endif; ?>
                        <?php if(has_permission('can_manage_sc_students')): ?>
                        <form method="POST" action="?route=app" onsubmit="return confirm('Delete?')">
                            <input type="hidden" name="action" value="sc_delete_document">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $doc['id'] ?>">
                            <button type="submit" class="text-rose-400 hover:text-rose-600 text-xs"><i class="fa-solid fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php elseif ($sc_stab === 'visa'): ?>
            <?php if (has_permission('can_manage_sc_applications')): ?>
            <button onclick="document.getElementById('scVisaModal').classList.remove('hidden');document.getElementById('scVisaModal').classList.add('flex')" class="mb-4 bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow inline-flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Visa Record</button>
            <?php endif; ?>
            <?php foreach($sc_visas as $v): ?>
            <div class="border border-slate-100 rounded-xl p-4 mb-3">
                <div class="flex items-center justify-between mb-3">
                    <div><p class="font-extrabold text-slate-800"><?= xss_clean($v['destination_country']??'') ?> — <?= xss_clean($v['visa_type']??'') ?></p><p class="text-xs text-slate-500"><?= xss_clean($v['embassy']??'') ?></p></div>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $visaStatColors[$v['status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= xss_clean($v['status']) ?></span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                    <?php $vd=['application_date'=>'Applied','biometrics_date'=>'Biometrics','medical_date'=>'Medical','interview_date'=>'Interview','decision_date'=>'Decision']; foreach($vd as $vc=>$vl): if(!empty($v[$vc])): ?>
                    <div class="bg-slate-50 rounded-lg p-2"><p class="text-slate-400 font-bold"><?= $vl ?></p><p class="text-slate-700 font-bold"><?= date('d M Y',strtotime($v[$vc])) ?></p></div>
                    <?php endif; endforeach; ?>
                    <?php if($v['visa_number']): ?><div class="bg-emerald-50 rounded-lg p-2"><p class="text-slate-400 font-bold text-xs">Visa No.</p><p class="text-emerald-700 font-bold"><?= xss_clean($v['visa_number']) ?></p></div><?php endif; ?>
                </div>
            </div>
            <?php endforeach; if(empty($sc_visas)) echo '<p class="text-slate-400 text-sm py-8 text-center"><i class="fa-solid fa-stamp text-3xl block mb-2"></i>No visa records.</p>'; ?>

        <?php elseif ($sc_stab === 'payments'): ?>
            <?php if (has_permission('can_manage_sc_payments')): ?>
            <button onclick="document.getElementById('scPmtModal').classList.remove('hidden');document.getElementById('scPmtModal').classList.add('flex')" class="mb-4 bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow inline-flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Payment</button>
            <?php endif; ?>
            <?php foreach($sc_pmts as $pm): ?>
            <div class="border border-slate-100 rounded-xl p-4 mb-3 flex items-center gap-4">
                <div class="flex-1">
                    <p class="font-bold text-slate-800"><?= xss_clean($pm['payment_type']??'Payment') ?></p>
                    <p class="text-xs text-slate-500"><?= $pm['payment_date'] ? date('d M Y',strtotime($pm['payment_date'])) : 'No date' ?> <?= $pm['notes'] ? '· '.xss_clean($pm['notes']) : '' ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-black text-slate-800">Total: <?= number_format($pm['total_amount'],2) ?></p>
                    <p class="text-xs text-emerald-600 font-bold">Paid: <?= number_format($pm['paid_amount'],2) ?></p>
                    <?php if($pm['due_amount']>0): ?><p class="text-xs text-rose-500 font-bold">Due: <?= number_format($pm['due_amount'],2) ?></p><?php endif; ?>
                </div>
                <a href="?route=app&page=invoices&prefill=<?= urlencode(json_encode(['customer_name'=>$sc_prof['student_name'],'mobile'=>$sc_prof['mobile'],'service_desc'=>$pm['payment_type'],'grand_total'=>$pm['total_amount'],'paid_amount'=>$pm['paid_amount'],'due_amount'=>$pm['due_amount']])) ?>"
                   class="text-xs font-bold text-teal-600 bg-teal-50 px-3 py-1.5 rounded-lg hover:bg-teal-100 transition">Invoice</a>
            </div>
            <?php endforeach; if(empty($sc_pmts)) echo '<p class="text-slate-400 text-sm py-8 text-center"><i class="fa-solid fa-coins text-3xl block mb-2"></i>No payment records.</p>'; ?>

        <?php elseif ($sc_stab === 'timeline'): ?>
            <form method="POST" action="?route=app" class="mb-5 bg-slate-50 rounded-xl p-4 border border-slate-200 space-y-3">
                <input type="hidden" name="action" value="add_record_followup">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="table" value="sc_students">
                <input type="hidden" name="record_id" value="<?= $sc_student_id ?>">
                <textarea name="note" rows="2" placeholder="Add a note, call log, meeting summary…" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"></textarea>
                <div class="flex gap-3 items-center">
                    <div class="flex-1"><label class="text-xs font-bold text-slate-600">Next Follow-up Date</label><input type="date" name="follow_up_date" class="w-full mt-1 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
                    <button type="submit" class="mt-5 bg-indigo-600 text-white px-5 py-2 rounded-xl text-sm font-bold shadow">Add Note</button>
                </div>
            </form>
            <?php if(empty($sc_fups)): ?><p class="text-slate-400 text-sm py-8 text-center">No timeline entries yet.</p><?php endif; ?>
            <div class="space-y-3">
            <?php foreach($sc_fups as $fu): ?>
                <div class="flex gap-3">
                    <div class="w-2 h-2 rounded-full bg-indigo-400 mt-2 shrink-0"></div>
                    <div class="flex-1 bg-slate-50 rounded-xl p-3 border border-slate-100">
                        <p class="text-sm text-slate-700 whitespace-pre-wrap"><?= xss_clean($fu['note']) ?></p>
                        <p class="text-xs text-slate-400 mt-1"><?= timeAgo($fu['created_at']) ?> <?= $fu['staff_name'] ? '· '.xss_clean($fu['staff_name']) : '' ?></p>
                        <?php if($fu['follow_up_date']): ?><p class="text-xs text-indigo-600 font-bold mt-1">📅 Follow-up: <?= date('d M Y',strtotime($fu['follow_up_date'])) ?></p><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div id="scEditStudentModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white">
        <h3 class="font-extrabold text-slate-800">Edit Student Profile</h3>
        <button onclick="document.getElementById('scEditStudentModal').classList.add('hidden');document.getElementById('scEditStudentModal').classList.remove('flex')" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button>
    </div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="sc_save_student">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="id" value="<?= xss_clean($sc_student_id) ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php $editFields = ['student_name'=>'Student Name','mobile'=>'Mobile','email'=>'Email','date_of_birth'=>'Date of Birth','passport_no'=>'Passport No.','passport_expiry'=>'Passport Expiry','nationality'=>'Nationality','ielts_score'=>'IELTS/PTE Score','guardian_name'=>'Guardian Name','guardian_mobile'=>'Guardian Mobile','guardian_relation'=>'Guardian Relation']; ?>
            <?php foreach($editFields as $ef=>$el): $et = in_array($ef,['date_of_birth','passport_expiry'])?'date':'text'; ?>
            <div><label class="block text-xs font-bold text-slate-700 mb-1"><?= $el ?></label><input type="<?= $et ?>" name="<?= $ef ?>" value="<?= xss_clean($sc_prof[$ef]??'') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <?php endforeach; ?>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                <select name="current_status" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                    <?php foreach(['Active','Enrolled','Graduated','Deferred','Cancelled'] as $st): ?><option value="<?= $st ?>" <?= $sc_prof['current_status']===$st?'selected':'' ?>><?= $st ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Education Background</label><textarea name="education_background" rows="3" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"><?= xss_clean($sc_prof['education_background']??'') ?></textarea></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Notes</label><textarea name="notes" rows="2" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"><?= xss_clean($sc_prof['notes']??'') ?></textarea></div>
        <div class="flex gap-3"><button type="button" onclick="document.getElementById('scEditStudentModal').classList.add('hidden');document.getElementById('scEditStudentModal').classList.remove('flex')" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 rounded-xl text-sm font-bold">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl text-sm font-bold shadow">Save</button></div>
    </form>
  </div>
</div>

<!-- Application Modal -->
<div id="scAppModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800">Add Application</h3><button onclick="document.getElementById('scAppModal').classList.add('hidden');document.getElementById('scAppModal').classList.remove('flex')" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="sc_save_application"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="student_id" value="<?= $sc_student_id ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">University</label><input list="sc_uni_list" name="university_name" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><datalist id="sc_uni_list"><?php foreach($sc_unis as $u): ?><option value="<?= xss_clean($u) ?>"><?php endforeach; ?></datalist></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Course</label><input type="text" name="course" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Intake</label><select name="intake" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select —</option><?php foreach($sc_intakes as $i): ?><option value="<?= xss_clean($i) ?>"><?= xss_clean($i) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Tuition Fee</label><input type="number" step="0.01" name="tuition_fee" value="0" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Scholarship</label><input type="number" step="0.01" name="scholarship" value="0" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Application Date</label><input type="date" name="application_date" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Offer Status</label><select name="offer_status" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><?php foreach(['Draft','Applied','Waiting Decision','Conditional Offer','Unconditional Offer','Accepted','Rejected'] as $os): ?><option value="<?= $os ?>"><?= $os ?></option><?php endforeach; ?></select></div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Notes</label><textarea name="notes" rows="2" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"></textarea></div>
        <div class="flex gap-3"><button type="button" onclick="document.getElementById('scAppModal').classList.add('hidden');document.getElementById('scAppModal').classList.remove('flex')" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save</button></div>
    </form>
  </div>
</div>

<!-- Document Upload Modal -->
<div id="scDocModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800">Upload Document</h3><button onclick="document.getElementById('scDocModal').classList.add('hidden');document.getElementById('scDocModal').classList.remove('flex')" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" enctype="multipart/form-data" class="p-6 space-y-4">
        <input type="hidden" name="action" value="sc_upload_document"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="student_id" value="<?= $sc_student_id ?>">
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Document Type</label><select name="doc_type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="Other">Other</option><?php foreach($sc_doctypes as $dt): ?><option value="<?= xss_clean($dt) ?>"><?= xss_clean($dt) ?></option><?php endforeach; ?></select></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Select File</label><input type="file" name="doc_file" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Status</label><select name="doc_status" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option>Uploaded</option><option>Pending</option><option>Verified</option><option>Expired</option></select></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Expiry Date</label><input type="date" name="expiry_date" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Notes</label><input type="text" name="doc_notes" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div class="flex gap-3"><button type="button" onclick="document.getElementById('scDocModal').classList.add('hidden');document.getElementById('scDocModal').classList.remove('flex')" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Upload</button></div>
    </form>
  </div>
</div>

<!-- Visa Modal -->
<div id="scVisaModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800">Add Visa Record</h3><button onclick="document.getElementById('scVisaModal').classList.add('hidden');document.getElementById('scVisaModal').classList.remove('flex')" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="sc_save_visa"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="student_id" value="<?= $sc_student_id ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Destination Country</label><select name="destination_country" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select —</option><?php foreach($sc_countries as $c): ?><option value="<?= xss_clean($c) ?>"><?= xss_clean($c) ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Visa Type</label><select name="visa_type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select —</option><?php foreach($sc_vtypes as $vt): ?><option value="<?= xss_clean($vt) ?>"><?= xss_clean($vt) ?></option><?php endforeach; ?></select></div>
            <div class="sm:col-span-2"><label class="block text-xs font-bold text-slate-700 mb-1">Embassy</label><input type="text" name="embassy" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <?php foreach(['application_date'=>'Application Date','biometrics_date'=>'Biometrics Date','medical_date'=>'Medical Date','interview_date'=>'Interview Date','decision_date'=>'Decision Date'] as $vf=>$vl): ?>
            <div><label class="block text-xs font-bold text-slate-700 mb-1"><?= $vl ?></label><input type="date" name="<?= $vf ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <?php endforeach; ?>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Status</label><select name="status" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><?php foreach(['Not Started','Documents Ready','Submitted','Under Review','Approved','Rejected'] as $vs): ?><option><?= $vs ?></option><?php endforeach; ?></select></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Visa Number</label><input type="text" name="visa_number" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Notes</label><textarea name="notes" rows="2" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"></textarea></div>
        <div class="flex gap-3"><button type="button" onclick="document.getElementById('scVisaModal').classList.add('hidden');document.getElementById('scVisaModal').classList.remove('flex')" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save</button></div>
    </form>
  </div>
</div>

<!-- Payment Modal -->
<div id="scPmtModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800">Add Payment</h3><button onclick="document.getElementById('scPmtModal').classList.add('hidden');document.getElementById('scPmtModal').classList.remove('flex')" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="sc_save_payment"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="student_id" value="<?= $sc_student_id ?>">
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Payment Type</label><select name="payment_type" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">— Select —</option><?php foreach(array_merge($sc_pcats,['Consultancy Fee','Application Fee','Tuition Deposit','Visa Fee','Medical Fee','Service Charge','Other']) as $pc): ?><option><?= xss_clean($pc) ?></option><?php endforeach; ?></select></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Total Amount</label><input type="number" step="0.01" name="total_amount" value="0" oninput="calcDue()" id="sc_total" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Paid Amount</label><input type="number" step="0.01" name="paid_amount" value="0" oninput="calcDue()" id="sc_paid" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Due Amount</label><input type="number" step="0.01" name="due_amount" id="sc_due" value="0" readonly class="w-full border border-emerald-200 bg-emerald-50 rounded-xl px-3 py-2.5 text-sm font-bold text-emerald-700"></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Payment Date</label><input type="date" name="payment_date" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Notes</label><textarea name="notes" rows="2" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"></textarea></div>
        <div class="flex gap-3"><button type="button" onclick="document.getElementById('scPmtModal').classList.add('hidden');document.getElementById('scPmtModal').classList.remove('flex')" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Save</button></div>
    </form>
  </div>
</div>
<script>function calcDue(){const t=parseFloat(document.getElementById('sc_total').value)||0,p=parseFloat(document.getElementById('sc_paid').value)||0;document.getElementById('sc_due').value=Math.max(0,t-p).toFixed(2);}</script>
<?php

} else {
    // ── LIST VIEW ────────────────────────────────────────────────────────────
    $sc_search = trim($_GET['q'] ?? '');
    $sc_rstatus = trim($_GET['status'] ?? '');
    $where = "s.agency_id=?"; $params = [$agency_id];
    if ($sc_search) { $where .= " AND (s.student_name LIKE ? OR s.mobile LIKE ? OR s.email LIKE ?)"; $params=array_merge($params,["%$sc_search%","%$sc_search%","%$sc_search%"]); }
    if ($sc_rstatus) { $where .= " AND s.current_status=?"; $params[] = $sc_rstatus; }
    $rf = $_SESSION['is_staff'] ? " AND s.reference_staff_id=".(int)$_SESSION['staff_id'] : "";
    $students_stmt = $conn->prepare("SELECT s.*, st.full_name as staff_name, (SELECT COUNT(*) FROM sc_applications WHERE student_id=s.id) as app_count FROM sc_students s LEFT JOIN staff st ON s.reference_staff_id=st.id WHERE $where $rf ORDER BY s.created_at DESC");
    $students_stmt->execute($params);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalStudents = $conn->query("SELECT COUNT(*) FROM sc_students WHERE agency_id=$agency_id")->fetchColumn();
?>
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-id-card text-indigo-500"></i> Students</h2><p class="text-sm text-slate-500 mt-1">Converted student profiles with full history.</p></div>
        <?php if (has_permission('can_manage_sc_students') && !$_SESSION['is_staff']): ?>
        <button onclick="document.getElementById('scAddStudentModal').classList.remove('hidden');document.getElementById('scAddStudentModal').classList.add('flex')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow transition flex items-center gap-2"><i class="fa-solid fa-plus"></i> Add Student</button>
        <?php endif; ?>
    </div>
    <form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-center">
        <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="sc_students">
        <input type="text" name="q" value="<?= xss_clean($sc_search) ?>" placeholder="Search name, mobile…" class="border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none w-52">
        <select name="status" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
            <option value="">All Statuses</option>
            <?php foreach(['Active','Enrolled','Graduated','Deferred','Cancelled'] as $st): ?><option value="<?= $st ?>" <?= $sc_rstatus===$st?'selected':'' ?>><?= $st ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold">Filter</button>
        <a href="?route=app&page=sc_students" class="text-sm text-slate-500 hover:text-slate-700 font-bold">Reset</a>
    </form>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left">
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Student</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Passport</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Applications</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Staff</th>
                        <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                <?php if (empty($students)): ?>
                    <tr><td colspan="6" class="text-center py-12 text-slate-400"><i class="fa-solid fa-id-card text-3xl mb-2 block"></i>No students yet.</td></tr>
                <?php else: foreach ($students as $st): ?>
                    <tr class="hover:bg-slate-50 transition cursor-pointer" onclick="window.location='?route=app&page=sc_students&id=<?= $st['id'] ?>'">
                        <td class="px-4 py-3"><p class="font-bold text-slate-800"><?= xss_clean($st['student_name']) ?></p><p class="text-xs text-slate-500"><?= xss_clean($st['mobile']) ?></p></td>
                        <td class="px-4 py-3 text-xs text-slate-600 font-bold"><?= xss_clean($st['passport_no']??'—') ?></td>
                        <td class="px-4 py-3"><span class="text-xs font-bold bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full"><?= $st['app_count'] ?></span></td>
                        <td class="px-4 py-3"><span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $st['current_status']==='Active'?'bg-emerald-100 text-emerald-700':($st['current_status']==='Graduated'?'bg-violet-100 text-violet-700':'bg-slate-100 text-slate-600') ?>"><?= xss_clean($st['current_status']) ?></span></td>
                        <td class="px-4 py-3 text-xs text-slate-500 font-bold"><?= xss_clean($st['staff_name']??'—') ?></td>
                        <td class="px-4 py-3" onclick="event.stopPropagation()">
                            <a href="?route=app&page=sc_students&id=<?= $st['id'] ?>" class="text-xs font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition">View Profile</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div id="scAddStudentModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800">Add Student Profile</h3><button onclick="document.getElementById('scAddStudentModal').classList.add('hidden');document.getElementById('scAddStudentModal').classList.remove('flex')" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="?route=app" class="p-6 space-y-4">
        <input type="hidden" name="action" value="sc_save_student"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Student Name *</label><input type="text" name="student_name" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Mobile *</label><input type="text" name="mobile" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Email</label><input type="email" name="email" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Date of Birth</label><input type="date" name="date_of_birth" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Passport No.</label><input type="text" name="passport_no" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Passport Expiry</label><input type="date" name="passport_expiry" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Nationality</label><input type="text" name="nationality" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">IELTS/PTE</label><input type="text" name="ielts_score" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        </div>
        <div class="flex gap-3"><button type="button" onclick="document.getElementById('scAddStudentModal').classList.add('hidden');document.getElementById('scAddStudentModal').classList.remove('flex')" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Create Student</button></div>
    </form>
  </div>
</div>
<?php } ?>
