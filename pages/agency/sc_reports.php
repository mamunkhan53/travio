<?php
if (!has_permission('can_view_sc_reports') && $_SESSION['is_staff']) {
    echo '<div class="bg-rose-50 border border-rose-200 rounded-2xl p-8 text-center"><i class="fa-solid fa-lock text-4xl text-rose-300 mb-3"></i><h3 class="font-extrabold text-rose-800">Access Denied</h3><p class="text-rose-700 text-sm mt-1">You do not have permission to view reports.</p></div>';
    return;
}

// Filters
$sc_from = $_GET['from'] ?? date('Y-m-01');
$sc_to   = $_GET['to']   ?? date('Y-m-d');
$sc_staff_id = (int)($_GET['staff_id'] ?? 0);
$sc_country  = trim($_GET['country'] ?? '');
$sc_intake   = trim($_GET['intake'] ?? '');
$sc_report   = $_GET['report'] ?? 'leads';

$sc_countries_r = $conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='countries' ORDER BY value"); $sc_countries_r->execute([$agency_id]); $sc_countries = $sc_countries_r->fetchAll(PDO::FETCH_COLUMN);
$sc_intakes_r   = $conn->prepare("SELECT value FROM sc_setting_items WHERE agency_id=? AND category='intakes' ORDER BY value"); $sc_intakes_r->execute([$agency_id]); $sc_intakes = $sc_intakes_r->fetchAll(PDO::FETCH_COLUMN);

// Summaries
$totalLeads     = $conn->query("SELECT COUNT(*) FROM sc_leads WHERE agency_id=$agency_id")->fetchColumn();
$totalStudents  = $conn->query("SELECT COUNT(*) FROM sc_students WHERE agency_id=$agency_id")->fetchColumn();
$totalApps      = $conn->query("SELECT COUNT(*) FROM sc_applications a JOIN sc_students s ON a.student_id=s.id WHERE a.agency_id=$agency_id")->fetchColumn();
$totalVisas     = $conn->query("SELECT COUNT(*) FROM sc_visa v JOIN sc_students s ON v.student_id=s.id WHERE v.agency_id=$agency_id")->fetchColumn();
$totalRevenue   = $conn->query("SELECT SUM(total_amount) FROM sc_payments p JOIN sc_students s ON p.student_id=s.id WHERE p.agency_id=$agency_id")->fetchColumn() ?: 0;
$totalDue       = $conn->query("SELECT SUM(due_amount) FROM sc_payments p JOIN sc_students s ON p.student_id=s.id WHERE p.agency_id=$agency_id")->fetchColumn() ?: 0;

$reports = ['leads'=>'Lead Report','students'=>'Student Admissions','visa'=>'Visa Report','payments'=>'Payment Report','country'=>'Country-wise','university'=>'University-wise'];
?>
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-chart-bar text-indigo-500"></i> Student Consultancy Reports</h2></div>
        <button onclick="window.print()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-2.5 rounded-xl text-sm font-bold transition flex items-center gap-2"><i class="fa-solid fa-print"></i> Print</button>
    </div>

    <!-- Summary tiles -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <?php $tiles=[['Leads',$totalLeads,'text-blue-600','sc_leads'],['Students',$totalStudents,'text-indigo-600','sc_students'],['Applications',$totalApps,'text-violet-600','sc_applications'],['Visas',$totalVisas,'text-emerald-600','sc_visa'],['Revenue',number_format($totalRevenue,2),'text-teal-600','sc_payments'],['Due',number_format($totalDue,2),'text-rose-500','sc_payments']];
        foreach($tiles as [$tl,$tv,$tc,$tp]): ?>
        <a href="/app/<?= $tp ?>" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 hover:border-indigo-200 transition">
            <p class="text-xs font-bold text-slate-400 uppercase mb-1"><?= $tl ?></p>
            <p class="text-2xl font-black <?= $tc ?>"><?= $tv ?></p>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filters + report tabs -->
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="flex border-b border-slate-100 overflow-x-auto">
            <?php foreach ($reports as $rk => $rl): ?>
            <a href="/app/sc_reports?report=<?= $rk ?>&from=<?= $sc_from ?>&to=<?= $sc_to ?>"
               class="px-4 py-3 text-sm font-bold whitespace-nowrap transition <?= $sc_report===$rk ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500 hover:text-indigo-600' ?>"><?= $rl ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="p-5">
            <form method="GET" class="flex flex-wrap gap-3 items-center mb-5">
                <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="sc_reports"><input type="hidden" name="report" value="<?= $sc_report ?>">
                <div><label class="block text-xs font-bold text-slate-500 mb-1">From</label><input type="date" name="from" value="<?= $sc_from ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
                <div><label class="block text-xs font-bold text-slate-500 mb-1">To</label><input type="date" name="to" value="<?= $sc_to ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
                <?php if (!empty($sc_countries)): ?><div><label class="block text-xs font-bold text-slate-500 mb-1">Country</label><select name="country" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="">All</option><?php foreach($sc_countries as $c): ?><option value="<?= xss_clean($c) ?>" <?= $sc_country===$c?'selected':'' ?>><?= xss_clean($c) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                <?php if (!empty($all_staff)): ?><div><label class="block text-xs font-bold text-slate-500 mb-1">Staff</label><select name="staff_id" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"><option value="0">All Staff</option><?php foreach($all_staff as $st): ?><option value="<?= $st['id'] ?>" <?= $sc_staff_id==$st['id']?'selected':'' ?>><?= xss_clean($st['full_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                <div class="self-end"><button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold">Apply</button></div>
            </form>

            <?php
            $dateFilter = "AND DATE(created_at) BETWEEN '$sc_from' AND '$sc_to'";
            $sfFilter   = $sc_staff_id ? " AND reference_staff_id=$sc_staff_id" : "";
            $ctFilter   = $sc_country  ? " AND preferred_country=".($conn->quote($sc_country)) : "";
            $ctFilter2  = $sc_country  ? " AND destination_country=".($conn->quote($sc_country)) : "";

            if ($sc_report === 'leads'):
                $rows = $conn->query("SELECT status, COUNT(*) as cnt FROM sc_leads WHERE agency_id=$agency_id $dateFilter $sfFilter $ctFilter GROUP BY status ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
                $details = $conn->query("SELECT l.*, st.full_name as staff_name FROM sc_leads l LEFT JOIN staff st ON l.reference_staff_id=st.id WHERE l.agency_id=$agency_id $dateFilter $sfFilter $ctFilter ORDER BY l.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
                $statusColors2 = ['New'=>'bg-blue-100 text-blue-700','Contacted'=>'bg-amber-100 text-amber-700','Interested'=>'bg-indigo-100 text-indigo-700','Documents Pending'=>'bg-orange-100 text-orange-700','Application Started'=>'bg-violet-100 text-violet-700','Converted'=>'bg-emerald-100 text-emerald-700','Cancelled'=>'bg-rose-100 text-rose-700'];
            ?>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                <?php foreach($rows as $r): ?><div class="bg-slate-50 rounded-xl p-3 text-center"><p class="text-xs font-bold text-slate-500"><?= xss_clean($r['status']) ?></p><p class="text-2xl font-black text-indigo-600"><?= $r['cnt'] ?></p></div><?php endforeach; ?>
            </div>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50"><tr class="text-left"><?php foreach(['Student','Mobile','Country','Intake','Status','Staff','Date'] as $h): ?><th class="px-3 py-2 text-xs font-extrabold text-slate-500 uppercase"><?= $h ?></th><?php endforeach; ?></tr></thead><tbody class="divide-y divide-slate-50"><?php foreach($details as $r): ?><tr class="hover:bg-slate-50"><td class="px-3 py-2 font-bold text-slate-800"><?= xss_clean($r['student_name']) ?></td><td class="px-3 py-2 text-slate-600"><?= xss_clean($r['mobile']) ?></td><td class="px-3 py-2"><?= xss_clean($r['preferred_country']??'—') ?></td><td class="px-3 py-2"><?= xss_clean($r['preferred_intake']??'—') ?></td><td class="px-3 py-2"><span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $statusColors2[$r['status']]??'bg-slate-100 text-slate-600' ?>"><?= xss_clean($r['status']) ?></span></td><td class="px-3 py-2 text-xs text-slate-500"><?= xss_clean($r['staff_name']??'—') ?></td><td class="px-3 py-2 text-xs text-slate-500"><?= date('d M Y',strtotime($r['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div>

            <?php elseif ($sc_report === 'students'):
                $details = $conn->query("SELECT s.student_name, s.mobile, s.nationality, s.ielts_score, s.current_status, st.full_name as staff_name, s.created_at, (SELECT COUNT(*) FROM sc_applications WHERE student_id=s.id) as apps FROM sc_students s LEFT JOIN staff st ON s.reference_staff_id=st.id WHERE s.agency_id=$agency_id $sfFilter ORDER BY s.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50"><tr class="text-left"><?php foreach(['Student','Mobile','Nationality','IELTS','Applications','Status','Staff','Enrolled'] as $h): ?><th class="px-3 py-2 text-xs font-extrabold text-slate-500 uppercase"><?= $h ?></th><?php endforeach; ?></tr></thead><tbody class="divide-y divide-slate-50"><?php foreach($details as $r): ?><tr class="hover:bg-slate-50"><td class="px-3 py-2 font-bold text-slate-800"><?= xss_clean($r['student_name']) ?></td><td class="px-3 py-2 text-xs"><?= xss_clean($r['mobile']) ?></td><td class="px-3 py-2 text-xs"><?= xss_clean($r['nationality']??'—') ?></td><td class="px-3 py-2 text-xs font-bold text-indigo-600"><?= xss_clean($r['ielts_score']??'—') ?></td><td class="px-3 py-2 text-center"><span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2 py-0.5 rounded-full"><?= $r['apps'] ?></span></td><td class="px-3 py-2 text-xs"><span class="<?= $r['current_status']==='Active'?'text-emerald-600':'text-slate-500' ?> font-bold"><?= xss_clean($r['current_status']) ?></span></td><td class="px-3 py-2 text-xs text-slate-500"><?= xss_clean($r['staff_name']??'—') ?></td><td class="px-3 py-2 text-xs text-slate-500"><?= date('d M Y',strtotime($r['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div>

            <?php elseif ($sc_report === 'visa'):
                $rows = $conn->query("SELECT status, COUNT(*) as cnt FROM sc_visa v JOIN sc_students s ON v.student_id=s.id WHERE v.agency_id=$agency_id $ctFilter2 GROUP BY status ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
                $details = $conn->query("SELECT v.*, s.student_name FROM sc_visa v JOIN sc_students s ON v.student_id=s.id WHERE v.agency_id=$agency_id $ctFilter2 $sfFilter ORDER BY v.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
                $vsColors=['Not Started'=>'bg-slate-100 text-slate-600','Documents Ready'=>'bg-amber-100 text-amber-700','Submitted'=>'bg-blue-100 text-blue-700','Under Review'=>'bg-violet-100 text-violet-700','Approved'=>'bg-emerald-100 text-emerald-700','Rejected'=>'bg-rose-100 text-rose-700'];
            ?>
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-5"><?php foreach($rows as $r): ?><div class="bg-slate-50 rounded-xl p-3 text-center"><p class="text-xs font-bold text-slate-500 text-[10px]"><?= xss_clean($r['status']) ?></p><p class="text-2xl font-black text-indigo-600"><?= $r['cnt'] ?></p></div><?php endforeach; ?></div>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50"><tr class="text-left"><?php foreach(['Student','Country','Type','Applied','Interview','Decision','Status','Visa No.'] as $h): ?><th class="px-3 py-2 text-xs font-extrabold text-slate-500 uppercase"><?= $h ?></th><?php endforeach; ?></tr></thead><tbody class="divide-y divide-slate-50"><?php foreach($details as $r): ?><tr class="hover:bg-slate-50"><td class="px-3 py-2 font-bold text-slate-800"><?= xss_clean($r['student_name']) ?></td><td class="px-3 py-2 text-xs"><?= xss_clean($r['destination_country']??'—') ?></td><td class="px-3 py-2 text-xs"><?= xss_clean($r['visa_type']??'—') ?></td><td class="px-3 py-2 text-xs"><?= $r['application_date']?date('d M Y',strtotime($r['application_date'])):'—' ?></td><td class="px-3 py-2 text-xs"><?= $r['interview_date']?date('d M Y',strtotime($r['interview_date'])):'—' ?></td><td class="px-3 py-2 text-xs"><?= $r['decision_date']?date('d M Y',strtotime($r['decision_date'])):'—' ?></td><td class="px-3 py-2"><span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $vsColors[$r['status']]??'bg-slate-100 text-slate-600' ?>"><?= xss_clean($r['status']) ?></span></td><td class="px-3 py-2 font-bold text-emerald-700 text-xs"><?= xss_clean($r['visa_number']??'—') ?></td></tr><?php endforeach; ?></tbody></table></div>

            <?php elseif ($sc_report === 'payments'):
                $rows = $conn->query("SELECT payment_type, SUM(total_amount) as total, SUM(paid_amount) as paid, SUM(due_amount) as due, COUNT(*) as cnt FROM sc_payments p JOIN sc_students s ON p.student_id=s.id WHERE p.agency_id=$agency_id $sfFilter GROUP BY payment_type ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50"><tr class="text-left"><?php foreach(['Payment Type','Records','Total','Paid','Due'] as $h): ?><th class="px-3 py-2 text-xs font-extrabold text-slate-500 uppercase"><?= $h ?></th><?php endforeach; ?></tr></thead><tbody class="divide-y divide-slate-50"><?php foreach($rows as $r): ?><tr class="hover:bg-slate-50"><td class="px-3 py-2 font-bold text-slate-800"><?= xss_clean($r['payment_type']??'—') ?></td><td class="px-3 py-2 text-center"><span class="bg-indigo-100 text-indigo-700 text-xs font-bold px-2 py-0.5 rounded-full"><?= $r['cnt'] ?></span></td><td class="px-3 py-2 font-bold text-slate-700"><?= number_format($r['total'],2) ?></td><td class="px-3 py-2 font-bold text-emerald-600"><?= number_format($r['paid'],2) ?></td><td class="px-3 py-2 font-bold text-rose-500"><?= number_format($r['due'],2) ?></td></tr><?php endforeach; if(empty($rows)): ?><tr><td colspan="5" class="text-center py-8 text-slate-400">No payment data.</td></tr><?php endif; ?></tbody></table></div>

            <?php elseif ($sc_report === 'country'):
                $rows = $conn->query("SELECT preferred_country, COUNT(*) as leads, SUM(status='Converted') as converted FROM sc_leads WHERE agency_id=$agency_id AND preferred_country!='' GROUP BY preferred_country ORDER BY leads DESC")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50"><tr class="text-left"><?php foreach(['Country','Total Leads','Converted','Conversion %'] as $h): ?><th class="px-3 py-2 text-xs font-extrabold text-slate-500 uppercase"><?= $h ?></th><?php endforeach; ?></tr></thead><tbody class="divide-y divide-slate-50"><?php foreach($rows as $r): $conv=$r['leads']>0?round($r['converted']/$r['leads']*100):0; ?><tr class="hover:bg-slate-50"><td class="px-3 py-2 font-bold text-slate-800"><?= xss_clean($r['preferred_country']) ?></td><td class="px-3 py-2 font-bold text-indigo-600"><?= $r['leads'] ?></td><td class="px-3 py-2 font-bold text-emerald-600"><?= $r['converted'] ?></td><td class="px-3 py-2"><div class="flex items-center gap-2"><div class="flex-1 bg-slate-200 rounded-full h-2"><div class="bg-indigo-500 h-2 rounded-full" style="width:<?= $conv ?>%"></div></div><span class="text-xs font-bold text-slate-600 w-8"><?= $conv ?>%</span></div></td></tr><?php endforeach; if(empty($rows)): ?><tr><td colspan="4" class="text-center py-8 text-slate-400">No data. Add countries in Settings first.</td></tr><?php endif; ?></tbody></table></div>

            <?php elseif ($sc_report === 'university'):
                $rows = $conn->query("SELECT university_name, COUNT(*) as apps, SUM(offer_status='Accepted') as accepted, SUM(tuition_fee) as total_fees FROM sc_applications a JOIN sc_students s ON a.student_id=s.id WHERE a.agency_id=$agency_id AND university_name!='' GROUP BY university_name ORDER BY apps DESC")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50"><tr class="text-left"><?php foreach(['University','Applications','Accepted','Total Fees'] as $h): ?><th class="px-3 py-2 text-xs font-extrabold text-slate-500 uppercase"><?= $h ?></th><?php endforeach; ?></tr></thead><tbody class="divide-y divide-slate-50"><?php foreach($rows as $r): ?><tr class="hover:bg-slate-50"><td class="px-3 py-2 font-bold text-slate-800"><?= xss_clean($r['university_name']) ?></td><td class="px-3 py-2 font-bold text-indigo-600"><?= $r['apps'] ?></td><td class="px-3 py-2 font-bold text-emerald-600"><?= $r['accepted'] ?></td><td class="px-3 py-2 font-bold text-slate-700"><?= number_format($r['total_fees'],2) ?></td></tr><?php endforeach; if(empty($rows)): ?><tr><td colspan="4" class="text-center py-8 text-slate-400">No university data.</td></tr><?php endif; ?></tbody></table></div>
            <?php endif; ?>
        </div>
    </div>
</div>
