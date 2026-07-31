<?php
$accFrom = normalizeReportDate($_GET['from_date']??null, date('Y-m-01'));
$accTo   = normalizeReportDate($_GET['to_date']??null, date('Y-m-d'));
if($accFrom>$accTo) [$accFrom,$accTo]=[$accTo,$accFrom];
$viewId  = trim($_GET['view'] ?? '');

if($viewId) {
    $jrn=$conn->prepare("SELECT j.*,s.full_name as staff_name FROM acc_journals j LEFT JOIN staff s ON j.created_by_staff_id=s.id WHERE j.id=? AND j.agency_id=?");
    $jrn->execute([$viewId,$agency_id]); $jrn=$jrn->fetch(PDO::FETCH_ASSOC);
    $lines=$conn->prepare("SELECT * FROM acc_journal_lines WHERE journal_id=? AND agency_id=?");
    $lines->execute([$viewId,$agency_id]); $lines=$lines->fetchAll(PDO::FETCH_ASSOC);
    $totalDebit=array_sum(array_column($lines,'debit'));
    $totalCredit=array_sum(array_column($lines,'credit'));
    if(!$jrn){ flash("Journal not found.","error"); redirect("/app/acc_journals"); }
}

$journals=$conn->prepare("SELECT j.*,s.full_name as staff_name, (SELECT SUM(debit) FROM acc_journal_lines WHERE journal_id=j.id) as total_debit FROM acc_journals j LEFT JOIN staff s ON j.created_by_staff_id=s.id WHERE j.agency_id=? AND j.journal_date BETWEEN ? AND ? ORDER BY j.journal_date DESC, j.created_at DESC");
$journals->execute([$agency_id,$accFrom,$accTo]); $journals=$journals->fetchAll(PDO::FETCH_ASSOC);

// Load accounts for autocomplete
$coaAccounts=$conn->query("SELECT account_code, account_name FROM acc_chart_of_accounts WHERE agency_id=$agency_id AND is_active=1 ORDER BY account_code")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="space-y-5">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div><h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-journal-whills text-indigo-500"></i> Journal Entries</h2><p class="text-sm text-slate-500 mt-1">Manual double-entry bookkeeping records.</p></div>
    <?php if(has_permission('can_manage_acc_journals') || !$_SESSION['is_staff']): ?>
    <button onclick="document.getElementById('accJournalModal').classList.remove('hidden');document.getElementById('accJournalModal').classList.add('flex')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow flex items-center gap-2"><i class="fa-solid fa-plus"></i> New Journal Entry</button>
    <?php endif; ?>
</div>

<?php if($viewId && $jrn): ?>
<div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
    <div class="flex items-center justify-between mb-4">
        <div><h3 class="font-extrabold text-slate-800 text-lg"><?= xss_clean($jrn['id']) ?></h3><p class="text-sm text-slate-500"><?= date('d M Y',strtotime($jrn['journal_date'])) ?> · <?= xss_clean($jrn['description']??'') ?> <?= $jrn['reference']?'· Ref: '.xss_clean($jrn['reference']):'' ?></p></div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-xl text-sm font-bold hover:bg-slate-200 transition">Print</button>
            <a href="/app/acc_journals?from_date=<?= $accFrom ?>&to_date=<?= $accTo ?>" class="bg-indigo-50 text-indigo-700 px-4 py-2 rounded-xl text-sm font-bold hover:bg-indigo-100 transition">← Back</a>
        </div>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm border border-slate-100 rounded-xl overflow-hidden">
    <thead class="bg-slate-50"><tr class="text-left">
    <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">#</th>
    <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Account Code</th>
    <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Account Name</th>
    <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Description</th>
    <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-indigo-500">Debit</th>
    <th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase text-emerald-500">Credit</th>
    </tr></thead>
    <tbody class="divide-y divide-slate-100">
    <?php foreach($lines as $i=>$ln): ?>
    <tr class="hover:bg-slate-50">
        <td class="px-4 py-3 text-slate-500 font-bold"><?= $i+1 ?></td>
        <td class="px-4 py-3 font-mono text-indigo-700 font-bold"><?= xss_clean($ln['account_code']??'') ?></td>
        <td class="px-4 py-3 font-bold text-slate-800"><?= xss_clean($ln['account_name']??'') ?></td>
        <td class="px-4 py-3 text-slate-600"><?= xss_clean($ln['description']??'') ?></td>
        <td class="px-4 py-3 font-bold text-indigo-600"><?= $ln['debit']>0 ? $currencySymbol.' '.number_format($ln['debit'],2) : '' ?></td>
        <td class="px-4 py-3 font-bold text-emerald-600"><?= $ln['credit']>0 ? $currencySymbol.' '.number_format($ln['credit'],2) : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot class="bg-slate-50 border-t-2 border-slate-200">
    <tr><td colspan="4" class="px-4 py-3 font-extrabold text-right text-slate-800">TOTALS</td>
    <td class="px-4 py-3 font-extrabold text-indigo-600"><?= $currencySymbol ?> <?= number_format($totalDebit,2) ?></td>
    <td class="px-4 py-3 font-extrabold text-emerald-600"><?= $currencySymbol ?> <?= number_format($totalCredit,2) ?></td>
    </tr>
    </tfoot>
    </table>
    </div>
    <?php if(round($totalDebit,2)===round($totalCredit,2)): ?>
    <div class="mt-3 flex items-center gap-2 text-emerald-600 text-sm font-bold"><i class="fa-solid fa-circle-check"></i> Entry is balanced</div>
    <?php else: ?>
    <div class="mt-3 flex items-center gap-2 text-rose-600 text-sm font-bold"><i class="fa-solid fa-triangle-exclamation"></i> Warning: Entry is NOT balanced</div>
    <?php endif; ?>
</div>
<?php else: ?>

<form method="GET" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-end">
    <input type="hidden" name="route" value="app"><input type="hidden" name="page" value="acc_journals">
    <div><label class="block text-xs font-bold text-slate-500 mb-1">From</label><input type="date" name="from_date" value="<?= $accFrom ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <div><label class="block text-xs font-bold text-slate-500 mb-1">To</label><input type="date" name="to_date" value="<?= $accTo ?>" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold self-end">Apply</button>
</form>

<div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
<div class="overflow-x-auto"><table class="w-full text-sm">
<thead class="bg-slate-50 border-b border-slate-100"><tr class="text-left">
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Journal ID</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Date</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Reference</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Description</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Total Debit</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Staff</th>
<th class="px-4 py-3 text-xs font-extrabold text-slate-500 uppercase">Actions</th>
</tr></thead>
<tbody class="divide-y divide-slate-50">
<?php if(empty($journals)): ?>
<tr><td colspan="7" class="text-center py-12 text-slate-400"><i class="fa-solid fa-journal-whills text-3xl block mb-2"></i>No journal entries found.</td></tr>
<?php else: foreach($journals as $j): ?>
<tr class="hover:bg-slate-50 transition">
    <td class="px-4 py-3 font-mono font-bold text-indigo-700"><?= xss_clean($j['id']) ?></td>
    <td class="px-4 py-3 font-bold text-slate-700"><?= date('d M Y',strtotime($j['journal_date'])) ?></td>
    <td class="px-4 py-3 text-slate-600 text-sm"><?= xss_clean($j['reference']??'—') ?></td>
    <td class="px-4 py-3 text-slate-600 max-w-xs truncate"><?= xss_clean($j['description']??'') ?></td>
    <td class="px-4 py-3 font-bold text-slate-700"><?= $currencySymbol ?> <?= number_format($j['total_debit']??0,2) ?></td>
    <td class="px-4 py-3 text-xs text-slate-500"><?= xss_clean($j['staff_name']??'—') ?></td>
    <td class="px-4 py-3">
        <a href="/app/acc_journals?view=<?= $j['id'] ?>&from_date=<?= $accFrom ?>&to_date=<?= $accTo ?>" class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1.5 rounded-lg hover:bg-indigo-100 transition">View</a>
        <?php if(!$_SESSION['is_staff']): ?>
        <form method="POST" action="" class="inline" onsubmit="return confirm('Delete this journal entry and all its lines?')"><input type="hidden" name="action" value="delete_acc_journal"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="id" value="<?= $j['id'] ?>"><button class="ml-1 text-xs font-bold text-rose-500 bg-rose-50 px-2.5 py-1.5 rounded-lg hover:bg-rose-100 transition">Del</button></form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table></div>
</div>
<?php endif; ?>
</div>

<!-- New Journal Modal -->
<div id="accJournalModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white"><h3 class="font-extrabold text-slate-800">New Journal Entry</h3><button onclick="closeJournalModal()" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center"><i class="fa-solid fa-times"></i></button></div>
    <form method="POST" action="" enctype="multipart/form-data" class="p-6 space-y-5">
        <input type="hidden" name="action" value="save_acc_journal"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Date *</label><input type="date" name="journal_date" value="<?= date('Y-m-d') ?>" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Reference</label><input type="text" name="reference" placeholder="e.g. REF-001" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
            <div><label class="block text-xs font-bold text-slate-700 mb-1">Attachment</label><input type="file" name="attachment" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm"></div>
        </div>
        <div><label class="block text-xs font-bold text-slate-700 mb-1">Description</label><input type="text" name="description" required class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none"></div>
        <div>
            <div class="flex items-center justify-between mb-2"><label class="block text-xs font-bold text-slate-700">Journal Lines (min 2, debits = credits)</label><button type="button" onclick="addJournalLine()" class="text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded-lg hover:bg-indigo-100 transition">+ Add Line</button></div>
            <div id="journalLines" class="space-y-2 border border-slate-200 rounded-xl p-3">
                <div class="grid grid-cols-12 gap-2 text-xs font-bold text-slate-500 uppercase px-1"><div class="col-span-4">Account</div><div class="col-span-3">Description</div><div class="col-span-2 text-indigo-500">Debit</div><div class="col-span-2 text-emerald-500">Credit</div><div class="col-span-1"></div></div>
                <?php for($i=0;$i<2;$i++): ?>
                <div class="grid grid-cols-12 gap-2 journal-line">
                    <div class="col-span-4"><input list="accCoaList" name="line_account[]" placeholder="Code|Account Name" required class="w-full border border-slate-200 rounded-lg px-2 py-2 text-xs focus:ring-2 focus:ring-indigo-400 outline-none"></div>
                    <div class="col-span-3"><input type="text" name="line_desc[]" placeholder="Description" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-xs focus:ring-2 focus:ring-indigo-400 outline-none"></div>
                    <div class="col-span-2"><input type="number" step="0.01" name="line_debit[]" value="0" oninput="updateJournalBalance()" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-xs focus:ring-2 focus:ring-indigo-400 outline-none"></div>
                    <div class="col-span-2"><input type="number" step="0.01" name="line_credit[]" value="0" oninput="updateJournalBalance()" class="w-full border border-slate-200 rounded-lg px-2 py-2 text-xs focus:ring-2 focus:ring-indigo-400 outline-none"></div>
                    <div class="col-span-1 flex items-center justify-center"><button type="button" onclick="removeJournalLine(this)" class="text-rose-400 hover:text-rose-600"><i class="fa-solid fa-trash text-xs"></i></button></div>
                </div>
                <?php endfor; ?>
            </div>
            <datalist id="accCoaList"><?php foreach($coaAccounts as $ca): ?><option value="<?= xss_clean($ca['account_code'].'|'.$ca['account_name']) ?>"><?php endforeach; ?></datalist>
            <div class="mt-2 text-xs font-bold flex gap-4" id="jrnBalance"><span class="text-indigo-600">Debit: <span id="jrnDebit">0.00</span></span><span class="text-emerald-600">Credit: <span id="jrnCredit">0.00</span></span><span id="jrnBalStatus" class="text-slate-500"></span></div>
        </div>
        <div class="flex gap-3"><button type="button" onclick="closeJournalModal()" class="flex-1 bg-slate-100 py-3 rounded-xl text-sm font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl text-sm font-bold shadow">Post Journal</button></div>
    </form>
  </div>
</div>
<script>
function addJournalLine(){const c=document.getElementById('journalLines');const t=c.querySelector('.journal-line').cloneNode(true);t.querySelectorAll('input').forEach(i=>{if(i.type==='number')i.value='0';else i.value='';});c.appendChild(t);}
function removeJournalLine(btn){const lines=document.querySelectorAll('.journal-line');if(lines.length<=2)return;btn.closest('.journal-line').remove();updateJournalBalance();}
function updateJournalBalance(){let d=0,cr=0;document.querySelectorAll('[name="line_debit[]"]').forEach(i=>d+=parseFloat(i.value)||0);document.querySelectorAll('[name="line_credit[]"]').forEach(i=>cr+=parseFloat(i.value)||0);document.getElementById('jrnDebit').textContent=d.toFixed(2);document.getElementById('jrnCredit').textContent=cr.toFixed(2);const ok=Math.abs(d-cr)<0.01;document.getElementById('jrnBalStatus').textContent=ok?'✓ Balanced':'✗ Not balanced';document.getElementById('jrnBalStatus').className=ok?'text-emerald-600 font-bold':'text-rose-600 font-bold';}
function closeJournalModal(){document.getElementById('accJournalModal').classList.add('hidden');document.getElementById('accJournalModal').classList.remove('flex');}
</script>
