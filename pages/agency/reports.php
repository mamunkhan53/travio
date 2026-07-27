<?php
    // Download Reports
    $download_report_types = getDownloadReportTypes($modules);
    $download_filters = [
        'report_type' => $_GET['report_type'] ?? 'sales',
        'format' => $_GET['format'] ?? 'pdf',
        'from_date' => normalizeReportDate($_GET['from_date'] ?? null, date('Y-m-01')),
        'to_date' => normalizeReportDate($_GET['to_date'] ?? null, date('Y-m-d')),
        'auto_download' => isset($_GET['download'])
    ];
    if ($download_filters['from_date'] > $download_filters['to_date']) {
        $tmpDate = $download_filters['from_date'];
        $download_filters['from_date'] = $download_filters['to_date'];
        $download_filters['to_date'] = $tmpDate;
    }
    if (!isset($download_report_types[$download_filters['report_type']])) {
        $download_filters['report_type'] = 'sales';
    }
    if (!in_array($download_filters['format'], ['pdf', 'excel'])) {
        $download_filters['format'] = 'pdf';
    }
    $download_report = null;
    if ($page === 'download') {
        $download_report = buildDownloadReport($conn, $modules, $agency_id, $download_filters['report_type'], $download_filters['from_date'], $download_filters['to_date'], $currencySymbol);
    }
?>
                <?php
                    $report_filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', $download_report['title'] . '_' . $download_filters['from_date'] . '_to_' . $download_filters['to_date']);
                ?>
                <div class="space-y-6">
                    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                            <input type="hidden" name="route" value="app">
                            <input type="hidden" name="page" value="download">
                            
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Report Type</label>
                                <select name="report_type" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white font-bold text-slate-700">
                                    <?php foreach($download_report_types as $key => $type): ?>
                                        <option value="<?= xss_clean($key) ?>" <?= $download_filters['report_type'] === $key ? 'selected' : '' ?>><?= xss_clean($type['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">Format</label>
                                <select name="format" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white font-bold text-slate-700">
                                    <option value="pdf" <?= $download_filters['format'] === 'pdf' ? 'selected' : '' ?>>PDF</option>
                                    <option value="excel" <?= $download_filters['format'] === 'excel' ? 'selected' : '' ?>>Excel</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">From</label>
                                <input type="date" name="from_date" value="<?= xss_clean($download_filters['from_date']) ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white font-bold text-slate-700">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wider">To</label>
                                <input type="date" name="to_date" value="<?= xss_clean($download_filters['to_date']) ?>" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white font-bold text-slate-700">
                            </div>

                            <div class="md:col-span-5 flex flex-col sm:flex-row gap-3 justify-end border-t border-slate-100 pt-5">
                                <button type="button" onclick="downloadReportPDF()" class="px-5 py-3 rounded-xl border border-rose-100 bg-rose-50 text-rose-600 font-bold hover:bg-rose-100 transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-file-pdf"></i> PDF
                                </button>
                                <button type="button" onclick="downloadReportExcel()" class="px-5 py-3 rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-600 font-bold hover:bg-emerald-100 transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-file-excel"></i> Excel
                                </button>
                                <button type="submit" name="download" value="1" class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-download"></i> Generate & Download
                                </button>
                            </div>
                        </form>
                    </div>

                    <div id="downloadReportPrint" class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-slate-50/50">
                            <div>
                                <div class="flex items-center gap-3 mb-2">
                                    <img src="<?= $logoSrc ?>" class="w-10 h-10 rounded-lg object-cover border border-slate-200 bg-white">
                                    <div>
                                        <h3 class="text-xl font-extrabold text-slate-800"><?= xss_clean($download_report['title']) ?></h3>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= date('d M Y', strtotime($download_filters['from_date'])) ?> - <?= date('d M Y', strtotime($download_filters['to_date'])) ?></p>
                                    </div>
                                </div>
                                <p class="text-sm text-slate-500 font-medium"><?= xss_clean($download_report['subtitle']) ?></p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-<?= max(1, min(3, count($download_report['totals']))) ?> gap-3">
                                <?php foreach($download_report['totals'] as $label => $value): ?>
                                    <div class="bg-white border border-slate-100 rounded-xl px-4 py-3 min-w-[150px]">
                                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-1"><?= xss_clean($label) ?></p>
                                        <p class="text-lg font-black text-slate-800"><?= xss_clean($value) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table id="downloadReportTable" class="w-full text-left text-sm">
                                <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                                    <tr>
                                        <?php foreach($download_report['columns'] as $column): ?>
                                            <th class="px-5 py-4 font-bold whitespace-nowrap"><?= xss_clean($column) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach($download_report['rows'] as $row): ?>
                                        <tr class="text-slate-700">
                                            <?php foreach($download_report['columns'] as $column): ?>
                                                <td class="px-5 py-3 align-top"><?= xss_clean($row[$column] ?? '') ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($download_report['rows'])): ?>
                                        <tr><td colspan="<?= count($download_report['columns']) ?>" class="p-12 text-center text-slate-400 font-medium">No report data found for this date range.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <script>
                    const downloadReportFilename = <?= json_encode($report_filename) ?>;

                    function downloadReportPDF() {
                        const element = document.getElementById('downloadReportPrint');

                        // The table lives inside an .overflow-x-auto wrapper for on-screen scrolling.
                        // html2canvas faithfully reproduces that clipping, so wide reports got cut off
                        // on the right. Fix: use html2canvas's own onclone hook to unlock the wrapper's
                        // width on ITS internal clone only - the real on-screen page is never touched,
                        // so there's no risk of it capturing blank/offscreen content.
                        html2pdf().from(element).set({
                            margin: 0.3,
                            filename: `${downloadReportFilename}.pdf`,
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: {
                                scale: 2,
                                useCORS: true,
                                onclone: function (clonedDoc) {
                                    clonedDoc.querySelectorAll('.overflow-x-auto').forEach(function (el) {
                                        el.style.overflow = 'visible';
                                        el.style.width = 'max-content';
                                        el.style.maxWidth = 'none';
                                    });
                                }
                            },
                            jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' },
                            pagebreak: { mode: ['css', 'legacy'] }
                        }).save();
                    }

                    function downloadReportExcel() {
                        const report = document.getElementById('downloadReportPrint').cloneNode(true);
                        const html = `
                            <html>
                                <head><meta charset="UTF-8"></head>
                                <body>${report.outerHTML}</body>
                            </html>
                        `;
                        const blob = new Blob(['\ufeff', html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = `${downloadReportFilename}.xls`;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(link.href);
                    }

                    <?php if ($download_filters['auto_download'] && !empty($download_report['rows'])): ?>
                        window.addEventListener('load', () => {
                            setTimeout(() => {
                                <?= $download_filters['format'] === 'excel' ? 'downloadReportExcel' : 'downloadReportPDF' ?>();
                            }, 500);
                        });
                    <?php endif; ?>
                </script>

