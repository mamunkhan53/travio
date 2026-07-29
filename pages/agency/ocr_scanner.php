<?php
// =========================================================================
// OCR DOCUMENT SCANNER — Main Page
// =========================================================================

$ocr_agency_id = $_SESSION['agency_id'];
$ocr_tab  = $_GET['tab'] ?? 'documents';
$ocr_edit = trim($_GET['edit'] ?? '');

// ── OCR engine availability ───────────────────────────────────────────────
$ocrAvailable = (trim(shell_exec('which tesseract 2>/dev/null') ?? '') !== '');

// ── Fetch document list ───────────────────────────────────────────────────
$ocr_type_filter = trim($_GET['type'] ?? '');
$ocr_search      = trim($_GET['q']    ?? '');
$ocr_where = "WHERE d.agency_id = ?";
$ocr_params = [$ocr_agency_id];
if ($ocr_type_filter) { $ocr_where .= " AND d.document_type = ?"; $ocr_params[] = $ocr_type_filter; }
if ($ocr_search)      { $ocr_where .= " AND (d.full_name LIKE ? OR d.document_number LIKE ? OR d.nid_number LIKE ?)"; $ocr_params[] = "%$ocr_search%"; $ocr_params[] = "%$ocr_search%"; $ocr_params[] = "%$ocr_search%"; }

$ocrDocs = $conn->prepare("SELECT d.*, s.full_name AS staff_name
    FROM ocr_documents d
    LEFT JOIN staff s ON s.id = d.uploaded_by_staff_id
    $ocr_where ORDER BY d.created_at DESC");
$ocrDocs->execute($ocr_params);
$ocrDocs = $ocrDocs->fetchAll(PDO::FETCH_ASSOC);

// ── Stats ────────────────────────────────────────────────────────────────
$totalDocs    = count($ocrDocs);
$passportCount = count(array_filter($ocrDocs, fn($d) => $d['document_type'] === 'Passport'));
$nidCount      = count(array_filter($ocrDocs, fn($d) => $d['document_type'] === 'NID'));
$today         = date('Y-m-d');
$in30Days      = date('Y-m-d', strtotime('+30 days'));
$expiringCount = count(array_filter($ocrDocs, fn($d) => $d['expiry_date'] && $d['expiry_date'] >= $today && $d['expiry_date'] <= $in30Days));
$expiredCount  = count(array_filter($ocrDocs, fn($d) => $d['expiry_date'] && $d['expiry_date'] < $today));

// ── Edit mode: load existing document ────────────────────────────────────
$editDoc = null;
if ($ocr_edit) {
    $ed = $conn->prepare("SELECT * FROM ocr_documents WHERE id=? AND agency_id=? LIMIT 1");
    $ed->execute([$ocr_edit, $ocr_agency_id]);
    $editDoc = $ed->fetch(PDO::FETCH_ASSOC);
    if ($editDoc) $ocr_tab = 'upload';
}

// ── Customer list for linking ─────────────────────────────────────────────
$cuStmt = $conn->prepare("SELECT id, name, mobile FROM customers WHERE agency_id=? ORDER BY name ASC LIMIT 200");
$cuStmt->execute([$ocr_agency_id]);
$customerList = $cuStmt->fetchAll(PDO::FETCH_ASSOC);

$docTypes = ['Passport', 'NID', 'Visa', 'Birth Certificate', 'Driving License', 'Other'];
$docTypeIcons  = ['Passport'=>'fa-passport','NID'=>'fa-id-card','Visa'=>'fa-file-signature','Birth Certificate'=>'fa-baby','Driving License'=>'fa-car','Other'=>'fa-file-alt'];
$docTypeColors = ['Passport'=>'bg-blue-100 text-blue-700','NID'=>'bg-indigo-100 text-indigo-700','Visa'=>'bg-purple-100 text-purple-700','Birth Certificate'=>'bg-green-100 text-green-700','Driving License'=>'bg-amber-100 text-amber-700','Other'=>'bg-slate-100 text-slate-600'];

// Expiry badge helper
function ocrExpiryBadge($expiryDate) {
    if (!$expiryDate) return '';
    $today  = date('Y-m-d');
    $in30   = date('Y-m-d', strtotime('+30 days'));
    if ($expiryDate < $today)   return '<span class="text-[10px] font-bold bg-rose-100 text-rose-700 px-1.5 py-0.5 rounded-full ml-1">Expired</span>';
    if ($expiryDate <= $in30)   return '<span class="text-[10px] font-bold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full ml-1">Expiring Soon</span>';
    return '';
}
?>

<!-- Page Tabs -->
<div class="flex items-center gap-2 mb-6 flex-wrap">
    <a href="?route=app&page=ocr_scanner&tab=documents"
       class="px-4 py-2 rounded-xl text-sm font-bold transition-all <?= $ocr_tab==='documents' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'bg-white text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600' ?>">
        <i class="fa-solid fa-table-list mr-1.5"></i>Documents
        <?php if ($totalDocs): ?><span class="ml-1 bg-white/20 text-white text-[10px] font-black px-1.5 py-0.5 rounded-full <?= $ocr_tab!=='documents' ? 'bg-indigo-100 text-indigo-700' : '' ?>"><?= $totalDocs ?></span><?php endif; ?>
    </a>
    <a href="?route=app&page=ocr_scanner&tab=upload"
       class="px-4 py-2 rounded-xl text-sm font-bold transition-all <?= $ocr_tab==='upload' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'bg-white text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600' ?>">
        <i class="fa-solid fa-cloud-arrow-up mr-1.5"></i><?= $editDoc ? 'Edit Document' : 'Upload & Scan' ?>
    </a>
    <?php if (!$_SESSION['is_staff']): ?>
    <a href="?route=app&page=ocr_scanner&tab=settings"
       class="px-4 py-2 rounded-xl text-sm font-bold transition-all <?= $ocr_tab==='settings' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'bg-white text-slate-600 border border-slate-200 hover:bg-indigo-50 hover:text-indigo-600' ?>">
        <i class="fa-solid fa-gear mr-1.5"></i>Settings
    </a>
    <?php endif; ?>
    <div class="ml-auto flex items-center gap-2">
        <?php if ($ocrAvailable): ?>
        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-xl flex items-center gap-1.5">
            <i class="fa-solid fa-font"></i> Tesseract OCR Ready
        </span>
        <?php else: ?>
        <span class="text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-xl flex items-center gap-1.5">
            <i class="fa-solid fa-triangle-exclamation"></i> OCR Engine Missing
        </span>
        <?php endif; ?>
    </div>
</div>

<?php if ($ocr_tab === 'documents'): ?>
<!-- ═══════════════════════════════ DOCUMENTS TAB ═══════════════════════════════ -->

<!-- Stats -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <?php foreach ([
        ['Total Documents', $totalDocs, 'fa-folder-open', 'indigo'],
        ['Passports', $passportCount, 'fa-passport', 'blue'],
        ['NIDs', $nidCount, 'fa-id-card', 'violet'],
        ['Expiring (30d)', $expiringCount + $expiredCount, 'fa-triangle-exclamation', $expiredCount ? 'rose' : 'amber'],
    ] as [$label, $count, $icon, $color]): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
        <span class="w-10 h-10 rounded-xl bg-<?= $color ?>-100 flex items-center justify-center flex-shrink-0">
            <i class="fa-solid <?= $icon ?> text-<?= $color ?>-600"></i>
        </span>
        <div>
            <p class="text-xl font-black text-slate-800"><?= $count ?></p>
            <p class="text-xs text-slate-400 font-semibold"><?= $label ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap gap-3 items-center">
    <form method="GET" class="flex flex-wrap gap-3 items-center flex-1" id="ocrFilterForm">
        <input type="hidden" name="route" value="app">
        <input type="hidden" name="page" value="ocr_scanner">
        <input type="hidden" name="tab" value="documents">
        <div class="relative flex-1 min-w-[180px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
            <input type="text" name="q" value="<?= xss_clean($ocr_search) ?>" placeholder="Search name, document number…"
                   class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
        </div>
        <select name="type" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 text-slate-700">
            <option value="">All Types</option>
            <?php foreach ($docTypes as $dt): ?>
            <option value="<?= $dt ?>" <?= $ocr_type_filter===$dt ? 'selected' : '' ?>><?= $dt ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition">Filter</button>
        <?php if ($ocr_search || $ocr_type_filter): ?>
        <a href="?route=app&page=ocr_scanner" class="px-4 py-2 bg-slate-100 text-slate-600 text-sm font-bold rounded-xl hover:bg-slate-200 transition">Clear</a>
        <?php endif; ?>
    </form>
    <a href="?route=app&page=ocr_scanner&tab=upload" class="px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition flex items-center gap-2 flex-shrink-0">
        <i class="fa-solid fa-plus"></i> Scan Document
    </a>
</div>

<!-- Documents Table -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <?php if (empty($ocrDocs)): ?>
    <div class="py-16 text-center">
        <i class="fa-solid fa-id-card-clip text-slate-200 text-5xl mb-4 block"></i>
        <p class="text-slate-400 font-semibold text-sm">No documents scanned yet.</p>
        <a href="?route=app&page=ocr_scanner&tab=upload" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition">
            <i class="fa-solid fa-cloud-arrow-up"></i> Upload First Document
        </a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100">
                    <th class="text-left px-4 py-3 font-bold text-slate-500 text-xs uppercase tracking-wider">Document</th>
                    <th class="text-left px-4 py-3 font-bold text-slate-500 text-xs uppercase tracking-wider hidden sm:table-cell">Type</th>
                    <th class="text-left px-4 py-3 font-bold text-slate-500 text-xs uppercase tracking-wider hidden md:table-cell">Number</th>
                    <th class="text-left px-4 py-3 font-bold text-slate-500 text-xs uppercase tracking-wider hidden lg:table-cell">Expiry</th>
                    <th class="text-left px-4 py-3 font-bold text-slate-500 text-xs uppercase tracking-wider hidden xl:table-cell">Uploaded By</th>
                    <th class="text-left px-4 py-3 font-bold text-slate-500 text-xs uppercase tracking-wider">Status</th>
                    <th class="text-right px-4 py-3 font-bold text-slate-500 text-xs uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($ocrDocs as $doc):
                    $icon  = $docTypeIcons[$doc['document_type']]  ?? 'fa-file-alt';
                    $color = $docTypeColors[$doc['document_type']] ?? 'bg-slate-100 text-slate-600';
                    $isExpired = $doc['expiry_date'] && $doc['expiry_date'] < $today;
                    $isExpiring = $doc['expiry_date'] && $doc['expiry_date'] >= $today && $doc['expiry_date'] <= $in30Days;
                ?>
                <tr class="hover:bg-slate-50/50 transition group">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 <?= $color ?>">
                                <i class="fa-solid <?= $icon ?> text-sm"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="font-bold text-slate-800 truncate max-w-[150px]"><?= xss_clean($doc['full_name'] ?: '—') ?></p>
                                <p class="text-[11px] text-slate-400"><?= $doc['id'] ?> · <?= $doc['date_of_birth'] ? date('d M Y', strtotime($doc['date_of_birth'])) : '' ?><?= $doc['age'] ? ' · Age '.$doc['age'] : '' ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 hidden sm:table-cell">
                        <span class="text-xs font-bold px-2 py-1 rounded-lg <?= $color ?>"><?= xss_clean($doc['document_type']) ?></span>
                    </td>
                    <td class="px-4 py-3 hidden md:table-cell">
                        <p class="font-mono text-sm text-slate-700"><?= xss_clean($doc['document_number'] ?: '—') ?></p>
                        <?php if ($doc['nid_number']): ?><p class="text-xs text-slate-400">NID: <?= xss_clean($doc['nid_number']) ?></p><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 hidden lg:table-cell">
                        <?php if ($doc['expiry_date']): ?>
                        <span class="<?= $isExpired ? 'text-rose-600' : ($isExpiring ? 'text-amber-600' : 'text-slate-700') ?> font-semibold text-xs">
                            <?= date('d M Y', strtotime($doc['expiry_date'])) ?>
                        </span>
                        <?= ocrExpiryBadge($doc['expiry_date']) ?>
                        <?php else: ?><span class="text-slate-300 text-xs">—</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500 hidden xl:table-cell">
                        <?= xss_clean($doc['staff_name'] ?: 'Admin') ?>
                        <br><span class="text-slate-300"><?= date('d M Y', strtotime($doc['created_at'])) ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-bold px-2 py-1 rounded-lg <?= $doc['status']==='Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                            <?= xss_clean($doc['status']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <button onclick="viewOcrDoc(<?= htmlspecialchars(json_encode($doc), ENT_QUOTES) ?>)"
                                    class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-indigo-100 hover:text-indigo-600 flex items-center justify-center text-slate-500 transition" title="View">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </button>
                            <a href="?route=app&page=ocr_scanner&tab=upload&edit=<?= $doc['id'] ?>"
                               class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-blue-100 hover:text-blue-600 flex items-center justify-center text-slate-500 transition" title="Edit">
                                <i class="fa-solid fa-pen text-xs"></i>
                            </a>
                            <?php if ($doc['file_path'] && file_exists(__DIR__.'/../../'.$doc['file_path'])): ?>
                            <a href="/<?= xss_clean($doc['file_path']) ?>" download
                               class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-green-100 hover:text-green-600 flex items-center justify-center text-slate-500 transition" title="Download">
                                <i class="fa-solid fa-download text-xs"></i>
                            </a>
                            <?php endif; ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this document permanently?')">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="ocr_delete_document">
                                <input type="hidden" name="id" value="<?= $doc['id'] ?>">
                                <button type="submit" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-rose-100 hover:text-rose-600 flex items-center justify-center text-slate-500 transition" title="Delete">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($ocr_tab === 'upload'): ?>
<!-- ═══════════════════════════════ UPLOAD & SCAN TAB ═══════════════════════════════ -->

<div class="max-w-4xl mx-auto">
    <?php if (!$ocrAvailable): ?>
    <div class="mb-5 flex items-start gap-3 p-4 bg-rose-50 border border-rose-200 rounded-2xl text-sm text-rose-700">
        <i class="fa-solid fa-triangle-exclamation mt-0.5 flex-shrink-0"></i>
        <div>
            <p class="font-bold">Tesseract OCR engine not found</p>
            <p class="mt-0.5 font-medium opacity-80">The OCR engine is not installed on this server. Contact your administrator. You can still upload documents and fill in fields manually.</p>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="ocrForm">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="action" value="ocr_save_document">
        <?php if ($editDoc): ?><input type="hidden" name="id" value="<?= $editDoc['id'] ?>"><?php endif; ?>
        <input type="hidden" name="ocr_confidence" id="ocrConfidenceInput" value="<?= $editDoc['ocr_confidence'] ?? '' ?>">
        <input type="hidden" name="ocr_raw_text" id="ocrRawTextInput" value="">
        <input type="hidden" name="create_customer" id="createCustomerInput" value="0">

        <!-- File upload zone -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-up text-indigo-500"></i>
                    <?= $editDoc ? 'Replace Document File (optional)' : 'Upload Document' ?>
                </h3>
                <?php if ($ocrAvailable): ?>
                <span class="text-xs font-bold text-indigo-600 bg-indigo-50 border border-indigo-200 px-3 py-1 rounded-xl flex items-center gap-1.5">
                    <i class="fa-solid fa-font"></i> Tesseract OCR Ready
                </span>
                <?php endif; ?>
            </div>

            <?php if ($editDoc && $editDoc['file_path'] && file_exists(__DIR__.'/../../'.$editDoc['file_path'])): ?>
            <div class="mb-3 flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                <i class="fa-solid fa-file text-indigo-500"></i>
                <span class="text-sm text-slate-700 font-semibold flex-1 truncate"><?= basename($editDoc['file_path']) ?></span>
                <a href="/<?= xss_clean($editDoc['file_path']) ?>" download class="text-xs text-indigo-600 font-bold hover:underline">Download</a>
            </div>
            <?php endif; ?>

            <div id="dropZone"
                 class="border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/30 transition-all"
                 onclick="document.getElementById('ocrFileInput').click()"
                 ondragover="event.preventDefault();this.classList.add('border-indigo-400','bg-indigo-50/30')"
                 ondragleave="this.classList.remove('border-indigo-400','bg-indigo-50/30')"
                 ondrop="handleDrop(event)">
                <input type="file" name="ocr_file" id="ocrFileInput" class="hidden"
                       accept=".jpg,.jpeg,.png,.webp,.pdf,.heic,.gif"
                       onchange="handleFileSelect(this.files[0])">
                <div id="dropZoneContent">
                    <i class="fa-solid fa-id-card-clip text-slate-300 text-4xl mb-3 block"></i>
                    <p class="text-sm font-bold text-slate-500">Drag & drop a document here, or <span class="text-indigo-600">click to browse</span></p>
                    <p class="text-xs text-slate-400 mt-1">Supports JPG, PNG, WEBP, PDF, HEIC — text extracted via Tesseract OCR</p>
                </div>
                <div id="filePreview" class="hidden flex-col items-center gap-3">
                    <img id="filePreviewImg" src="" alt="" class="max-h-40 rounded-xl shadow-md object-contain hidden">
                    <div id="filePreviewPdf" class="w-14 h-14 bg-rose-100 rounded-2xl flex items-center justify-center hidden">
                        <i class="fa-solid fa-file-pdf text-rose-600 text-2xl"></i>
                    </div>
                    <p id="filePreviewName" class="text-sm font-bold text-slate-700"></p>
                    <button type="button" onclick="clearFile(event)" class="text-xs text-rose-500 hover:underline">Remove file</button>
                    <?php if ($ocrAvailable): ?>
                    <button type="button" id="scanBtn" onclick="runOcrScan()"
                            class="mt-1 px-5 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition flex items-center gap-2 mx-auto">
                        <i class="fa-solid fa-magnifying-glass"></i> Extract with OCR
                    </button>
                    <?php else: ?>
                    <p class="text-xs text-amber-600 font-semibold mt-2">OCR engine unavailable — fill fields manually.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- OCR Status -->
            <div id="ocrStatus" class="hidden mt-3">
                <div id="ocrLoading" class="hidden flex items-center gap-3 p-3 bg-indigo-50 rounded-xl text-sm text-indigo-700 font-semibold">
                    <i class="fa-solid fa-spinner fa-spin"></i> Running Tesseract OCR — this may take a moment…
                </div>
                <div id="ocrSuccess" class="hidden flex items-center gap-2 p-3 bg-emerald-50 rounded-xl text-sm text-emerald-700 font-semibold">
                    <i class="fa-solid fa-circle-check"></i> <span id="ocrSuccessMsg"></span>
                </div>
                <div id="ocrError" class="hidden flex items-center gap-2 p-3 bg-rose-50 rounded-xl text-sm text-rose-700 font-semibold">
                    <i class="fa-solid fa-circle-exclamation"></i> <span id="ocrErrorMsg"></span>
                </div>
            </div>
        </div>

        <!-- Extracted Fields -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-file-lines text-indigo-500"></i> Document Information
                </h3>
                <div id="aiBadge" class="hidden items-center gap-1.5 text-xs font-bold text-purple-700 bg-purple-100 border border-purple-200 px-3 py-1 rounded-xl">
                    <i class="fa-solid fa-font text-xs"></i> OCR Filled
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Document Type -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Document Type</label>
                    <div class="flex flex-wrap gap-2" id="docTypeGroup">
                        <?php foreach ($docTypes as $dt): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="document_type" value="<?= $dt ?>" class="hidden peer"
                                   <?= ($editDoc ? $editDoc['document_type'] : 'Passport') === $dt ? 'checked' : '' ?>>
                            <span class="px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-500 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 hover:border-indigo-400 transition block select-none">
                                <i class="fa-solid <?= $docTypeIcons[$dt] ?> mr-1"></i><?= $dt ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Full Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Full Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="full_name" id="f_full_name" required
                           value="<?= xss_clean($editDoc['full_name'] ?? '') ?>"
                           class="ocr-field w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <!-- Document Number -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Document / Passport Number</label>
                    <input type="text" name="document_number" id="f_document_number"
                           value="<?= xss_clean($editDoc['document_number'] ?? '') ?>"
                           class="ocr-field w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <!-- NID Number -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">NID / National ID Number</label>
                    <input type="text" name="nid_number" id="f_nid_number"
                           value="<?= xss_clean($editDoc['nid_number'] ?? '') ?>"
                           class="ocr-field w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <!-- Date of Birth -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Date of Birth</label>
                    <input type="date" name="date_of_birth" id="f_date_of_birth"
                           value="<?= xss_clean($editDoc['date_of_birth'] ?? '') ?>"
                           class="ocr-field w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                           onchange="calcAge(this.value)">
                </div>

                <!-- Age (auto-calculated) -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Age <span class="text-slate-300">(auto-calculated)</span></label>
                    <input type="number" name="age" id="f_age"
                           value="<?= xss_clean($editDoc['age'] ?? '') ?>" readonly
                           class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-50 text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                </div>

                <!-- Gender -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Gender</label>
                    <select name="gender" id="f_gender" class="ocr-field w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        <option value="">— Select —</option>
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                        <option value="<?= $g ?>" <?= ($editDoc['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Nationality -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Nationality</label>
                    <input type="text" name="nationality" id="f_nationality"
                           value="<?= xss_clean($editDoc['nationality'] ?? '') ?>"
                           class="ocr-field w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <!-- Issue Date -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Issue Date</label>
                    <input type="date" name="issue_date" id="f_issue_date"
                           value="<?= xss_clean($editDoc['issue_date'] ?? '') ?>"
                           class="ocr-field w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <!-- Expiry Date -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Expiry Date</label>
                    <input type="date" name="expiry_date" id="f_expiry_date"
                           value="<?= xss_clean($editDoc['expiry_date'] ?? '') ?>"
                           class="ocr-field w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    <div id="expiryWarning" class="hidden mt-1 text-xs font-bold text-amber-600 flex items-center gap-1">
                        <i class="fa-solid fa-triangle-exclamation"></i> <span id="expiryWarningText"></span>
                    </div>
                </div>

                <!-- Country of Issue -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Country of Issue</label>
                    <input type="text" name="issue_country" id="f_issue_country"
                           value="<?= xss_clean($editDoc['issue_country'] ?? '') ?>"
                           class="ocr-field w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <!-- Father Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Father's Name</label>
                    <input type="text" name="father_name" id="f_father_name"
                           value="<?= xss_clean($editDoc['father_name'] ?? '') ?>"
                           class="ocr-field w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <!-- Mother Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Mother's Name</label>
                    <input type="text" name="mother_name" id="f_mother_name"
                           value="<?= xss_clean($editDoc['mother_name'] ?? '') ?>"
                           class="ocr-field w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <!-- Mobile -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Mobile</label>
                    <input type="text" name="mobile" id="f_mobile"
                           value="<?= xss_clean($editDoc['mobile'] ?? '') ?>"
                           class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Email</label>
                    <input type="email" name="email" id="f_email"
                           value="<?= xss_clean($editDoc['email'] ?? '') ?>"
                           class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <!-- Address -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Address</label>
                    <textarea name="address" id="f_address" rows="2"
                              class="ocr-field w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 resize-none"><?= xss_clean($editDoc['address'] ?? '') ?></textarea>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Status</label>
                    <select name="status" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                        <?php foreach (['Active','Archived'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($editDoc['status'] ?? 'Active') === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Customer Linking -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-5">
            <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
                <i class="fa-solid fa-user-tie text-indigo-500"></i> Link to Customer Profile
            </h3>
            <div class="flex flex-col sm:flex-row gap-3">
                <select name="customer_id" id="customerSelect" class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    <option value="">— No customer linked —</option>
                    <?php foreach ($customerList as $cu): ?>
                    <option value="<?= xss_clean($cu['id']) ?>" <?= ($editDoc['customer_id'] ?? '') === $cu['id'] ? 'selected' : '' ?>>
                        <?= xss_clean($cu['name']) ?> · <?= xss_clean($cu['mobile']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" onclick="toggleCreateCustomer()"
                        class="px-4 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 transition flex items-center gap-2 justify-center whitespace-nowrap">
                    <i class="fa-solid fa-user-plus"></i> <span id="createCuBtnText">Auto-Create Customer</span>
                </button>
            </div>
            <div id="createCuNote" class="hidden mt-3 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-700 font-semibold">
                <i class="fa-solid fa-circle-check mr-1"></i> A new customer will be created from the document data above (or linked to an existing one if the same passport/NID is found).
            </div>
        </div>

        <!-- Save -->
        <div class="flex items-center justify-between gap-3">
            <a href="?route=app&page=ocr_scanner" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-sm font-bold rounded-xl hover:bg-slate-200 transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i>
                <?= $editDoc ? 'Update Document' : 'Save Document' ?>
            </button>
        </div>
    </form>
</div>

<?php elseif ($ocr_tab === 'settings' && !$_SESSION['is_staff']): ?>
<!-- ═══════════════════════════════ SETTINGS TAB ═══════════════════════════════ -->

<div class="max-w-2xl mx-auto space-y-5">
    <!-- Engine status card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-1">
            <i class="fa-solid fa-font text-indigo-500"></i> OCR Engine
        </h3>
        <p class="text-sm text-slate-400 mb-5">This module uses <strong>Tesseract OCR</strong> — a free, open-source engine. No API key or internet connection required.</p>
        <div class="flex items-center gap-3 p-4 rounded-xl <?= $ocrAvailable ? 'bg-emerald-50 border border-emerald-200' : 'bg-rose-50 border border-rose-200' ?>">
            <i class="fa-solid <?= $ocrAvailable ? 'fa-circle-check text-emerald-600' : 'fa-circle-xmark text-rose-600' ?> text-xl"></i>
            <div>
                <p class="font-bold text-sm <?= $ocrAvailable ? 'text-emerald-800' : 'text-rose-800' ?>">
                    <?= $ocrAvailable ? 'Tesseract OCR is installed and ready' : 'Tesseract OCR is NOT installed' ?>
                </p>
                <?php if ($ocrAvailable): ?>
                <p class="text-xs text-emerald-600 mt-0.5 font-mono"><?= htmlspecialchars(trim(shell_exec('tesseract --version 2>&1 | head -1') ?? '')) ?></p>
                <?php else: ?>
                <p class="text-xs text-rose-600 mt-0.5">Contact your server administrator to install the <code>tesseract</code> package.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Supported documents -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-4">
            <i class="fa-solid fa-id-card-clip text-indigo-500"></i> Supported Document Types
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php foreach ([
                ['fa-passport','text-blue-500','bg-blue-50','Passport','MRZ zone parsed automatically. Extracts passport number, name, nationality, DOB, expiry, gender.'],
                ['fa-id-card','text-orange-500','bg-orange-50','Bangladesh NID','Extracts NID number (10/13/17 digits), name, father, mother, DOB, address.'],
                ['fa-stamp','text-violet-500','bg-violet-50','Visa','Extracts visa number, name, nationality, issue/expiry dates, country.'],
                ['fa-file-lines','text-slate-500','bg-slate-50','Other Documents','Generic field extraction for birth certificates, driving licences, etc.'],
            ] as [$icon,$clr,$bg,$title,$desc]): ?>
            <div class="flex items-start gap-3 p-3 rounded-xl border border-slate-100 <?= $bg ?>">
                <i class="fa-solid <?= $icon ?> <?= $clr ?> mt-0.5 text-lg flex-shrink-0"></i>
                <div>
                    <p class="font-bold text-sm text-slate-700"><?= $title ?></p>
                    <p class="text-xs text-slate-500 mt-0.5"><?= $desc ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- How it works -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-3">
            <i class="fa-solid fa-circle-info text-indigo-500"></i> How OCR Extraction Works
        </h3>
        <div class="space-y-2.5 text-sm text-slate-600">
            <div class="flex items-start gap-3"><span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-black flex items-center justify-center flex-shrink-0 mt-0.5">1</span><p>Upload a clear photo or scan of a Passport, NID, Visa, or other identity document.</p></div>
            <div class="flex items-start gap-3"><span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-black flex items-center justify-center flex-shrink-0 mt-0.5">2</span><p>Click <strong>Extract with OCR</strong> — Tesseract reads the text locally on the server (no data sent externally).</p></div>
            <div class="flex items-start gap-3"><span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-black flex items-center justify-center flex-shrink-0 mt-0.5">3</span><p>The system auto-detects the document type and fills in all matching fields. Review and correct if needed.</p></div>
            <div class="flex items-start gap-3"><span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 text-xs font-black flex items-center justify-center flex-shrink-0 mt-0.5">4</span><p>Once saved, use <strong>Import from OCR</strong> in any booking form to reuse the data without re-scanning.</p></div>
        </div>
        <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-700">
            <i class="fa-solid fa-lightbulb mr-1"></i>
            <strong>Best results:</strong> Use a well-lit, high-resolution scan (at least 200 DPI). Blurry or low-contrast images reduce OCR accuracy. Always review extracted fields before saving.
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ═══════════════════════════════ VIEW DOCUMENT MODAL ═══════════════════════════════ -->
<div id="viewOcrModal" class="fixed inset-0 z-[220] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeViewOcr()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto pointer-events-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 sticky top-0 bg-white z-10">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-id-card-clip text-indigo-500"></i>
                    <span id="viewModalTitle">Document Details</span>
                </h3>
                <div class="flex items-center gap-2">
                    <a id="viewEditLink" href="#" class="px-3 py-1.5 text-xs font-bold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition">
                        <i class="fa-solid fa-pen mr-1"></i>Edit
                    </a>
                    <button onclick="closeViewOcr()" class="w-8 h-8 rounded-xl hover:bg-slate-100 flex items-center justify-center text-slate-400 transition">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>
            </div>
            <div id="viewModalBody" class="p-6"></div>
        </div>
    </div>
</div>

<script>
// ── File handling ──────────────────────────────────────────────────────────────
let _selectedFile = null;

function handleFileSelect(file) {
    if (!file) return;
    _selectedFile = file;
    document.getElementById('dropZoneContent').classList.add('hidden');
    const fp = document.getElementById('filePreview');
    fp.classList.remove('hidden'); fp.classList.add('flex');
    document.getElementById('filePreviewName').textContent = file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
    const img = document.getElementById('filePreviewImg');
    const pdf = document.getElementById('filePreviewPdf');
    if (file.type.startsWith('image/')) {
        img.src = URL.createObjectURL(file); img.classList.remove('hidden'); pdf.classList.add('hidden');
    } else {
        pdf.classList.remove('hidden'); img.classList.add('hidden');
    }
}
function handleDrop(e) {
    e.preventDefault();
    document.getElementById('dropZone').classList.remove('border-indigo-400','bg-indigo-50/30');
    const f = e.dataTransfer.files[0];
    if (f) {
        document.getElementById('ocrFileInput').files;
        const dt = new DataTransfer(); dt.items.add(f);
        document.getElementById('ocrFileInput').files = dt.files;
        handleFileSelect(f);
    }
}
function clearFile(e) {
    e.stopPropagation();
    _selectedFile = null;
    document.getElementById('ocrFileInput').value = '';
    document.getElementById('dropZoneContent').classList.remove('hidden');
    const fp = document.getElementById('filePreview');
    fp.classList.add('hidden'); fp.classList.remove('flex');
    document.getElementById('ocrStatus').classList.add('hidden');
}

// ── Tesseract OCR Scan ─────────────────────────────────────────────────────────
function runOcrScan() {
    if (!_selectedFile) { alert('Please select a file first.'); return; }
    const status = document.getElementById('ocrStatus');
    status.classList.remove('hidden');
    document.getElementById('ocrLoading').classList.remove('hidden');
    document.getElementById('ocrSuccess').classList.add('hidden');
    document.getElementById('ocrError').classList.add('hidden');
    const scanBtn = document.getElementById('scanBtn');
    if (scanBtn) scanBtn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'ocr_process_file');
    fd.append('csrf_token', document.querySelector('[name=csrf_token]').value);
    fd.append('ocr_file', _selectedFile);

    fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(function(res) {
            document.getElementById('ocrLoading').classList.add('hidden');
            if (scanBtn) scanBtn.disabled = false;
            if (!res.success) {
                document.getElementById('ocrError').classList.remove('hidden');
                document.getElementById('ocrErrorMsg').textContent = res.message || 'OCR failed.';
                return;
            }
            // Store raw text for saving
            if (res.raw_text) document.getElementById('ocrRawTextInput').value = res.raw_text;
            // Store confidence
            if (res.confidence !== undefined) document.getElementById('ocrConfidenceInput').value = res.confidence;

            if (res.low_confidence) {
                // Show warning in amber
                document.getElementById('ocrError').classList.remove('hidden');
                document.getElementById('ocrError').className = document.getElementById('ocrError').className
                    .replace('bg-rose-50','bg-amber-50').replace('text-rose-700','text-amber-700');
                document.getElementById('ocrErrorMsg').textContent = res.message || 'Low confidence — please review all fields.';
            }
            document.getElementById('ocrSuccess').classList.remove('hidden');
            if (res.data && Object.keys(res.data).length > 1) {
                fillOcrFields(res.data);
                document.getElementById('ocrSuccessMsg').textContent = res.message || 'Fields extracted — review before saving.';
            } else {
                document.getElementById('ocrSuccessMsg').textContent = res.message || 'OCR complete — enter data manually.';
            }
        })
        .catch(function() {
            document.getElementById('ocrLoading').classList.add('hidden');
            document.getElementById('ocrError').classList.remove('hidden');
            document.getElementById('ocrErrorMsg').textContent = 'Network error. Try again.';
            if (scanBtn) scanBtn.disabled = false;
        });
}

function fillOcrFields(data) {
    const map = {
        full_name: 'f_full_name', document_number: 'f_document_number', nid_number: 'f_nid_number',
        date_of_birth: 'f_date_of_birth', gender: 'f_gender', nationality: 'f_nationality',
        issue_date: 'f_issue_date', expiry_date: 'f_expiry_date', issue_country: 'f_issue_country',
        father_name: 'f_father_name', mother_name: 'f_mother_name', address: 'f_address'
    };
    let filled = false;
    Object.entries(map).forEach(function([key, elId]) {
        const el = document.getElementById(elId);
        if (!el || !data[key]) return;
        el.value = data[key];
        el.classList.add('ring-2', 'ring-purple-400', 'border-purple-300');
        filled = true;
    });
    // Document type radio
    if (data.document_type) {
        const r = document.querySelector(`input[name=document_type][value="${data.document_type}"]`);
        if (r) r.checked = true;
    }
    if (data.date_of_birth) calcAge(data.date_of_birth);
    if (filled) document.getElementById('aiBadge').classList.remove('hidden');
}

// ── Age calculator ─────────────────────────────────────────────────────────────
function calcAge(dob) {
    if (!dob) return;
    const birth = new Date(dob), today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    const ageEl = document.getElementById('f_age');
    if (ageEl) ageEl.value = age >= 0 ? age : '';
}

// ── Expiry warning ─────────────────────────────────────────────────────────────
document.getElementById('f_expiry_date') && document.getElementById('f_expiry_date').addEventListener('change', function() {
    const val = this.value;
    const warn = document.getElementById('expiryWarning');
    const warnText = document.getElementById('expiryWarningText');
    if (!val) { warn.classList.add('hidden'); return; }
    const exp = new Date(val), today = new Date(), in30 = new Date(); in30.setDate(today.getDate()+30);
    if (exp < today) { warn.classList.remove('hidden'); warnText.textContent = 'This document has expired!'; warn.classList.remove('text-amber-600'); warn.classList.add('text-rose-600'); }
    else if (exp <= in30) { warn.classList.remove('hidden'); warnText.textContent = 'Expiring within 30 days!'; warn.classList.remove('text-rose-600'); warn.classList.add('text-amber-600'); }
    else warn.classList.add('hidden');
});

// ── Create customer toggle ─────────────────────────────────────────────────────
function toggleCreateCustomer() {
    const inp = document.getElementById('createCustomerInput');
    const note = document.getElementById('createCuNote');
    const btn  = document.getElementById('createCuBtnText');
    const sel  = document.getElementById('customerSelect');
    if (inp.value === '1') {
        inp.value = '0'; note.classList.add('hidden'); btn.textContent = 'Auto-Create Customer'; sel.disabled = false;
    } else {
        inp.value = '1'; note.classList.remove('hidden'); btn.textContent = 'Cancel Auto-Create'; sel.disabled = true; sel.value = '';
    }
}

// ── View modal ─────────────────────────────────────────────────────────────────
function viewOcrDoc(doc) {
    document.getElementById('viewModalTitle').textContent = (doc.document_type || 'Document') + ' — ' + (doc.full_name || '');
    document.getElementById('viewEditLink').href = '?route=app&page=ocr_scanner&tab=upload&edit=' + doc.id;
    const fields = [
        ['Document ID', doc.id], ['Type', doc.document_type], ['Doc Number', doc.document_number],
        ['NID Number', doc.nid_number], ['Full Name', doc.full_name], ['Date of Birth', doc.date_of_birth],
        ['Age', doc.age], ['Gender', doc.gender], ['Nationality', doc.nationality],
        ['Issue Date', doc.issue_date], ['Expiry Date', doc.expiry_date], ['Issue Country', doc.issue_country],
        ['Father\'s Name', doc.father_name], ['Mother\'s Name', doc.mother_name],
        ['Mobile', doc.mobile], ['Email', doc.email], ['Address', doc.address],
        ['Status', doc.status], ['OCR Confidence', doc.ocr_confidence ? doc.ocr_confidence + '%' : null],
        ['Uploaded', doc.created_at]
    ].filter(([l, v]) => v);

    const typeColors = {Passport:'bg-blue-100 text-blue-700',NID:'bg-indigo-100 text-indigo-700',Visa:'bg-purple-100 text-purple-700','Birth Certificate':'bg-green-100 text-green-700',Other:'bg-slate-100 text-slate-600'};
    const today = new Date().toISOString().split('T')[0];
    const isExpired = doc.expiry_date && doc.expiry_date < today;
    const isExpiring = doc.expiry_date && doc.expiry_date >= today && doc.expiry_date <= new Date(Date.now()+30*86400000).toISOString().split('T')[0];

    let html = `<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">`;
    fields.forEach(function([label, value]) {
        let extra = '';
        if (label === 'Expiry Date' && isExpired) extra = '<span class="ml-2 text-[10px] font-bold bg-rose-100 text-rose-700 px-1.5 py-0.5 rounded-full">Expired</span>';
        else if (label === 'Expiry Date' && isExpiring) extra = '<span class="ml-2 text-[10px] font-bold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">Expiring Soon</span>';
        if (label === 'Address') {
            html += `<div class="sm:col-span-2 bg-slate-50 rounded-xl p-3"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">${label}</p><p class="text-sm text-slate-800 font-semibold">${value}</p></div>`;
        } else {
            html += `<div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">${label}</p><p class="text-sm text-slate-800 font-semibold">${value}${extra}</p></div>`;
        }
    });

    // File preview
    if (doc.file_path) {
        const isImg = /\.(jpg|jpeg|png|webp|gif)$/i.test(doc.file_path);
        if (isImg) {
            html += `<div class="sm:col-span-2 bg-slate-50 rounded-xl p-3"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Document Preview</p><img src="/${doc.file_path}" class="max-h-60 rounded-xl shadow object-contain w-full" alt=""></div>`;
        } else {
            html += `<div class="sm:col-span-2 bg-slate-50 rounded-xl p-3"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">File</p><a href="/${doc.file_path}" download class="text-sm text-indigo-600 font-bold hover:underline flex items-center gap-2"><i class="fa-solid fa-download"></i> Download ${doc.file_path.split('/').pop()}</a></div>`;
        }
    }
    html += `</div>`;
    document.getElementById('viewModalBody').innerHTML = html;
    document.getElementById('viewOcrModal').classList.remove('hidden');
}
function closeViewOcr() {
    document.getElementById('viewOcrModal').classList.add('hidden');
}

// Init age if editing
document.addEventListener('DOMContentLoaded', function() {
    const dobEl = document.getElementById('f_date_of_birth');
    if (dobEl && dobEl.value) calcAge(dobEl.value);
    const expEl = document.getElementById('f_expiry_date');
    if (expEl && expEl.value) expEl.dispatchEvent(new Event('change'));
});
</script>
