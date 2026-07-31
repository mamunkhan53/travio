<?php
// ── Hajj & Umrah — Packages ──────────────────────────────────────────────────
require_once __DIR__ . '/../../includes/umrah_actions.php';
$agency_id = $_SESSION['agency_id'];
$packages  = $conn->prepare("SELECT * FROM umrah_packages WHERE agency_id=? ORDER BY package_type, package_name");
$packages->execute([$agency_id]);
$packages  = $packages->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="p-5 border-b flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50">
            <div>
                <h2 class="font-extrabold text-slate-800 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-box-open text-indigo-500"></i> Packages
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Manage Hajj & Umrah packages offered by your agency</p>
            </div>
            <?php if (!$_SESSION['is_staff']): ?>
            <button onclick="openPkgModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-md flex items-center gap-2 transition">
                <i class="fa-solid fa-plus"></i> Add Package
            </button>
            <?php endif; ?>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" id="pkgTable">
                <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                    <tr>
                        <th class="px-6 py-4 font-bold">ID</th>
                        <th class="px-6 py-4 font-bold">Type</th>
                        <th class="px-6 py-4 font-bold">Package Name</th>
                        <th class="px-6 py-4 font-bold">Duration</th>
                        <th class="px-6 py-4 font-bold">Price (<?= htmlspecialchars($currencySymbol) ?>)</th>
                        <th class="px-6 py-4 font-bold">Status</th>
                        <?php if (!$_SESSION['is_staff']): ?>
                        <th class="px-6 py-4 font-bold text-right">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($packages): foreach ($packages as $p): ?>
                    <tr class="hover:bg-slate-50 transition text-slate-700">
                        <td class="px-6 py-4 font-extrabold text-indigo-600"><?= htmlspecialchars($p['id']) ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $p['package_type']==='Hajj' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' ?>">
                                <?= htmlspecialchars($p['package_type']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 font-semibold"><?= htmlspecialchars($p['package_name']) ?></td>
                        <td class="px-6 py-4"><?= htmlspecialchars($p['duration'] ?: '—') ?></td>
                        <td class="px-6 py-4 font-bold"><?= number_format($p['price'], 2) ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $p['status']==='Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                                <?= htmlspecialchars($p['status']) ?>
                            </span>
                        </td>
                        <?php if (!$_SESSION['is_staff']): ?>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <button onclick='openPkgModal(<?= htmlspecialchars(json_encode($p)) ?>)' class="text-indigo-600 bg-indigo-50 w-8 h-8 rounded-lg hover:bg-indigo-100 mx-1 transition"><i class="fa-solid fa-pen"></i></button>
                            <form method="POST" action="" class="inline" onsubmit="return confirm('Delete this package?')">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="umrah_delete_package">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
                                <button type="submit" class="text-rose-600 bg-rose-50 w-8 h-8 rounded-lg hover:bg-rose-100 transition"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="7" class="px-6 py-12 text-center text-slate-400">No packages yet. Add your first Hajj or Umrah package.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!$_SESSION['is_staff']): ?>
<!-- Package Modal -->
<div id="pkgModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-y-auto max-h-[90vh] custom-scrollbar">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50 sticky top-0">
            <h3 class="font-extrabold text-slate-800 text-lg" id="pkgModalTitle"><i class="fa-solid fa-box-open text-indigo-500 mr-2"></i> Package</h3>
            <button onclick="closePkgModal()" class="w-8 h-8 rounded-full bg-slate-200/50 text-slate-400 hover:text-slate-700 flex items-center justify-center transition"><i class="fa-solid fa-times"></i></button>
        </div>
        <form method="POST" action="" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="umrah_save_package">
            <input type="hidden" name="id" id="pkg_id" value="">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Package Type *</label>
                    <select name="package_type" id="pkg_type" required class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="Umrah">Umrah</option>
                        <option value="Hajj">Hajj</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Status *</label>
                    <select name="status" id="pkg_status" required class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Package Name *</label>
                <input type="text" name="package_name" id="pkg_name" required class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="e.g. 15-Day Umrah Standard">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Duration</label>
                    <input type="text" name="duration" id="pkg_duration" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="e.g. 15 Days">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">Price (<?= htmlspecialchars($currencySymbol) ?>) *</label>
                    <input type="number" name="price" id="pkg_price" required min="0" step="0.01" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="0.00">
                </div>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">Description</label>
                <textarea name="description" id="pkg_desc" rows="3" class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 outline-none resize-none" placeholder="Package details, inclusions, hotels..."></textarea>
            </div>
            <div class="flex gap-3 pt-2 border-t border-slate-100">
                <button type="button" onclick="closePkgModal()" class="w-1/3 py-3 border border-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" class="w-2/3 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition">Save Package</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPkgModal(data) {
    document.getElementById('pkgModal').classList.remove('hidden');
    if (data) {
        document.getElementById('pkgModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-indigo-500 mr-2"></i> Edit Package';
        document.getElementById('pkg_id').value       = data.id || '';
        document.getElementById('pkg_type').value     = data.package_type || 'Umrah';
        document.getElementById('pkg_name').value     = data.package_name || '';
        document.getElementById('pkg_duration').value = data.duration || '';
        document.getElementById('pkg_price').value    = data.price || '';
        document.getElementById('pkg_status').value   = data.status || 'Active';
        document.getElementById('pkg_desc').value     = data.description || '';
    } else {
        document.getElementById('pkgModalTitle').innerHTML = '<i class="fa-solid fa-plus text-indigo-500 mr-2"></i> Add Package';
        document.getElementById('pkg_id').value = '';
        document.getElementById('pkgModal').querySelector('form').reset();
    }
}
function closePkgModal() { document.getElementById('pkgModal').classList.add('hidden'); }
</script>
<?php endif; ?>
