<?php
// SC Settings — Agency Admin only
$sc_tab = $_GET['tab'] ?? 'countries';
$sc_cats = [
    'countries'          => ['Countries',          'fa-solid fa-earth-asia'],
    'universities'       => ['Universities',        'fa-solid fa-building-columns'],
    'courses'            => ['Courses',             'fa-solid fa-book'],
    'intakes'            => ['Intakes',             'fa-solid fa-calendar'],
    'visa_types'         => ['Visa Types',          'fa-solid fa-stamp'],
    'document_types'     => ['Document Types',      'fa-solid fa-folder-open'],
    'lead_sources'       => ['Lead Sources',        'fa-solid fa-bullhorn'],
    'payment_categories' => ['Payment Categories',  'fa-solid fa-coins'],
];
if (!array_key_exists($sc_tab, $sc_cats)) $sc_tab = 'countries';

$items = $conn->prepare("SELECT * FROM sc_setting_items WHERE agency_id=? AND category=? ORDER BY value ASC");
$items->execute([$agency_id, $sc_tab]);
$items = $items->fetchAll(PDO::FETCH_ASSOC);

// Stats per category
$catCounts = [];
foreach ($sc_cats as $k => $_) {
    $s = $conn->prepare("SELECT COUNT(*) FROM sc_setting_items WHERE agency_id=? AND category=?");
    $s->execute([$agency_id,$k]);
    $catCounts[$k] = (int)$s->fetchColumn();
}
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-gear text-indigo-500"></i> Student Consultancy Settings
            </h2>
            <p class="text-sm text-slate-500 mt-1">Manage lookup data used across the Student Consultancy module.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Sidebar tabs -->
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-3 h-fit">
            <p class="text-xs font-extrabold text-slate-400 uppercase tracking-wider px-3 mb-2">Categories</p>
            <?php foreach ($sc_cats as $k => [$label, $icon]): ?>
            <a href="?route=app&page=sc_settings&tab=<?= $k ?>"
               class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all mb-0.5
                      <?= $sc_tab === $k ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600' ?>">
                <i class="<?= $icon ?> w-4 text-center text-xs"></i>
                <span class="flex-1"><?= $label ?></span>
                <span class="text-xs font-bold <?= $sc_tab === $k ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' ?> px-2 py-0.5 rounded-full"><?= $catCounts[$k] ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Content -->
        <div class="lg:col-span-3 space-y-4">
            <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
                <h3 class="font-extrabold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="<?= $sc_cats[$sc_tab][1] ?> text-indigo-500"></i> <?= $sc_cats[$sc_tab][0] ?>
                </h3>
                <form method="POST" action="?route=app" class="flex gap-3 mb-6">
                    <input type="hidden" name="action" value="sc_save_setting">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="category" value="<?= $sc_tab ?>">
                    <input type="text" name="value" placeholder="Add new <?= rtrim($sc_cats[$sc_tab][0], 's') ?>..." required
                           class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-indigo-400 outline-none">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow transition flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> Add
                    </button>
                </form>

                <?php if (empty($items)): ?>
                <div class="text-center py-10 text-slate-400">
                    <i class="fa-solid fa-inbox text-3xl mb-2"></i>
                    <p class="text-sm font-bold">No <?= $sc_cats[$sc_tab][0] ?> added yet.</p>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($items as $item): ?>
                    <div class="flex items-center gap-3 bg-slate-50 rounded-xl px-4 py-3 border border-slate-100">
                        <i class="<?= $sc_cats[$sc_tab][1] ?> text-indigo-400 text-sm"></i>
                        <span class="flex-1 text-sm font-bold text-slate-700"><?= xss_clean($item['value']) ?></span>
                        <form method="POST" action="?route=app" onsubmit="return confirm('Delete this item?')">
                            <input type="hidden" name="action" value="sc_delete_setting">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="text-rose-400 hover:text-rose-600 transition">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
