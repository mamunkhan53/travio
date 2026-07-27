<?php
// =========================================================================
// WHATSAPP MESSAGING MODULE
// Three tabs: Compose & Send | Message History | Provider Settings
// =========================================================================

$wa_tab = $_GET['tab'] ?? 'compose';
if (!in_array($wa_tab, ['compose', 'history', 'settings'])) $wa_tab = 'compose';

// ---- Load provider ----
$wa_provStmt = $conn->prepare("SELECT * FROM whatsapp_providers WHERE agency_id = ? ORDER BY is_active DESC, updated_at DESC LIMIT 1");
$wa_provStmt->execute([$agency_id]);
$wa_provider = $wa_provStmt->fetch(PDO::FETCH_ASSOC);
$wa_hasProvider = $wa_provider && $wa_provider['is_active'];

// ---- Load customers for Compose tab (with optional search/filter) ----
$wa_search   = trim($_GET['wa_search'] ?? '');
$wa_filter   = $_GET['wa_filter'] ?? ''; // category filter
$wa_customers = [];
if ($wa_tab === 'compose') {
    $wa_where = "agency_id = ?";
    $wa_params = [$agency_id];
    if (!empty($wa_search)) {
        $wa_where .= " AND (name LIKE ? OR mobile LIKE ?)";
        $wa_params[] = "%{$wa_search}%";
        $wa_params[] = "%{$wa_search}%";
    }
    if (!empty($wa_filter)) {
        $wa_where .= " AND category = ?";
        $wa_params[] = $wa_filter;
    }
    $wa_custStmt = $conn->prepare("SELECT id, name, mobile, category FROM customers WHERE {$wa_where} AND mobile IS NOT NULL AND mobile != '' ORDER BY name ASC");
    $wa_custStmt->execute($wa_params);
    $wa_customers = $wa_custStmt->fetchAll(PDO::FETCH_ASSOC);

    // Distinct categories for filter dropdown
    $wa_categories = $conn->prepare("SELECT DISTINCT category FROM customers WHERE agency_id = ? AND category IS NOT NULL AND category != '' ORDER BY category ASC");
    $wa_categories->execute([$agency_id]);
    $wa_categories = $wa_categories->fetchAll(PDO::FETCH_COLUMN);
}

// ---- Load message history for History tab ----
$wa_logs = [];
if ($wa_tab === 'history') {
    $wa_hist_status = $_GET['wa_status'] ?? '';
    $wa_hist_from   = $_GET['wa_from'] ?? '';
    $wa_hist_to     = $_GET['wa_to'] ?? '';

    $wa_hist_where  = "l.agency_id = ?";
    $wa_hist_params = [$agency_id];
    if (!empty($wa_hist_status)) {
        $wa_hist_where .= " AND l.status = ?"; $wa_hist_params[] = $wa_hist_status;
    }
    if (!empty($wa_hist_from)) {
        $wa_hist_where .= " AND DATE(l.created_at) >= ?"; $wa_hist_params[] = $wa_hist_from;
    }
    if (!empty($wa_hist_to)) {
        $wa_hist_where .= " AND DATE(l.created_at) <= ?"; $wa_hist_params[] = $wa_hist_to;
    }

    $wa_logsStmt = $conn->prepare(
        "SELECT l.*, p.provider_name, p.api_type,
                CASE WHEN l.sent_by_type='staff' THEN s.full_name ELSE 'Admin' END as sender_name
         FROM whatsapp_message_logs l
         LEFT JOIN whatsapp_providers p ON p.id = l.provider_id
         LEFT JOIN staff s ON s.id = l.sent_by_staff_id
         WHERE {$wa_hist_where}
         ORDER BY l.created_at DESC LIMIT 200"
    );
    $wa_logsStmt->execute($wa_hist_params);
    $wa_logs = $wa_logsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---- Summary stats ----
$wa_totalCampaigns  = $conn->prepare("SELECT COUNT(*) FROM whatsapp_message_logs WHERE agency_id = ?");
$wa_totalCampaigns->execute([$agency_id]);
$wa_totalCampaigns = (int)$wa_totalCampaigns->fetchColumn();

$wa_totalReached = $conn->prepare("SELECT SUM(sent_count) FROM whatsapp_message_logs WHERE agency_id = ? AND status IN ('Sent','Partial')");
$wa_totalReached->execute([$agency_id]);
$wa_totalReached = (int)$wa_totalReached->fetchColumn();

$wa_totalCustomers = $conn->prepare("SELECT COUNT(*) FROM customers WHERE agency_id = ? AND mobile IS NOT NULL AND mobile != ''");
$wa_totalCustomers->execute([$agency_id]);
$wa_totalCustomers = (int)$wa_totalCustomers->fetchColumn();

// Status colour helper
function waStatusBadge($status) {
    $map = [
        'Sent'        => 'bg-emerald-100 text-emerald-700',
        'Partial'     => 'bg-amber-100 text-amber-700',
        'Failed'      => 'bg-rose-100 text-rose-700',
        'No Provider' => 'bg-slate-100 text-slate-500',
        'Processing'  => 'bg-blue-100 text-blue-700',
        'Pending'     => 'bg-yellow-100 text-yellow-700',
    ];
    $cls = $map[$status] ?? 'bg-slate-100 text-slate-500';
    return "<span class=\"px-2.5 py-1 rounded-lg text-xs font-bold {$cls}\">{$status}</span>";
}
?>

<!-- ============================================================ -->
<!-- WHATSAPP MODULE HEADER + STATS -->
<!-- ============================================================ -->
<div class="space-y-6">

    <!-- Stats row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Provider</p>
            <?php if ($wa_hasProvider): ?>
                <p class="text-sm font-extrabold text-emerald-600 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                    <?= xss_clean($wa_provider['provider_name']) ?>
                </p>
                <p class="text-xs text-slate-400 mt-0.5"><?= strtoupper(str_replace('_', ' ', $wa_provider['api_type'])) ?></p>
            <?php else: ?>
                <p class="text-sm font-extrabold text-rose-500 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-rose-400 inline-block"></span>
                    Not Configured
                </p>
                <a href="?route=app&page=whatsapp&tab=settings" class="text-xs text-indigo-500 hover:underline font-bold">Set up now →</a>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Campaigns Sent</p>
            <p class="text-3xl font-black text-slate-800"><?= number_format($wa_totalCampaigns) ?></p>
        </div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Messages Delivered</p>
            <p class="text-3xl font-black text-emerald-600"><?= number_format($wa_totalReached) ?></p>
        </div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Reachable Customers</p>
            <p class="text-3xl font-black text-indigo-600"><?= number_format($wa_totalCustomers) ?></p>
        </div>
    </div>

    <!-- Tab navigation -->
    <div class="flex gap-1 bg-white rounded-2xl soft-shadow border border-slate-100 p-1.5">
        <?php
        $waTabs = [
            'compose' => ['fa-paper-plane', 'Compose & Send'],
            'history' => ['fa-clock-rotate-left', 'Message History'],
            'settings'=> ['fa-gear', 'Provider Settings'],
        ];
        foreach ($waTabs as $key => [$icon, $label]):
            $active = $wa_tab === $key;
        ?>
            <a href="?route=app&page=whatsapp&tab=<?= $key ?>"
               class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition <?= $active ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-500 hover:bg-slate-100 hover:text-indigo-600' ?>">
                <i class="fa-solid <?= $icon ?>"></i>
                <span class="hidden sm:inline"><?= $label ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 1: COMPOSE & SEND -->
    <!-- ============================================================ -->
    <?php if ($wa_tab === 'compose'): ?>

    <?php if (!has_permission('can_send_whatsapp')): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-8 text-center">
            <i class="fa-solid fa-lock text-4xl text-amber-300 mb-3"></i>
            <h3 class="font-extrabold text-amber-800 text-lg">Permission Required</h3>
            <p class="text-amber-700 text-sm mt-1">You do not have permission to send WhatsApp messages. Ask your Agency Admin to grant the <strong>Send WhatsApp Messages</strong> permission.</p>
        </div>
    <?php else: ?>

    <form method="GET" action="?route=app" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-end">
        <input type="hidden" name="route" value="app">
        <input type="hidden" name="page" value="whatsapp">
        <input type="hidden" name="tab" value="compose">
        <div class="flex-1 min-w-[180px] relative">
            <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input type="text" name="wa_search" value="<?= xss_clean($wa_search) ?>"
                   placeholder="Search by name or phone..."
                   class="w-full pl-9 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <?php if (!empty($wa_categories)): ?>
        <select name="wa_filter" class="border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none">
            <option value="">All Categories</option>
            <?php foreach ($wa_categories as $cat): ?>
                <option value="<?= xss_clean($cat) ?>" <?= $wa_filter === $cat ? 'selected' : '' ?>><?= xss_clean($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm transition">
            <i class="fa-solid fa-filter mr-1"></i> Filter
        </button>
        <?php if (!empty($wa_search) || !empty($wa_filter)): ?>
            <a href="?route=app&page=whatsapp&tab=compose" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2.5 rounded-xl text-sm font-bold transition">
                <i class="fa-solid fa-xmark mr-1"></i> Clear
            </a>
        <?php endif; ?>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        <!-- LEFT: Customer Selector -->
        <div class="lg:col-span-2 bg-white rounded-2xl soft-shadow border border-slate-100 flex flex-col overflow-hidden" style="max-height: 640px;">
            <div class="p-4 border-b bg-slate-50/50 flex items-center justify-between flex-shrink-0">
                <div>
                    <h3 class="font-extrabold text-slate-800 text-sm">Select Recipients</h3>
                    <p class="text-xs text-slate-400 mt-0.5"><?= count($wa_customers) ?> customer(s) with phone numbers</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" onclick="waSelectAll()" class="text-xs bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-lg font-bold hover:bg-indigo-100 transition">All</button>
                    <button type="button" onclick="waClearAll()" class="text-xs bg-slate-100 text-slate-600 px-3 py-1.5 rounded-lg font-bold hover:bg-slate-200 transition">None</button>
                </div>
            </div>

            <?php if (empty($wa_customers)): ?>
                <div class="flex-1 flex items-center justify-center p-8 text-center">
                    <div>
                        <i class="fa-solid fa-users-slash text-4xl text-slate-200 mb-3"></i>
                        <p class="text-slate-400 font-medium text-sm">No customers with phone numbers found.</p>
                        <a href="?route=app&page=customers" class="text-indigo-500 text-xs hover:underline font-bold">Go to Customer DB →</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-slate-50" id="waCustomerList">
                    <?php foreach ($wa_customers as $wc): ?>
                    <label class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-indigo-50 transition select-none wa-customer-row">
                        <input type="checkbox" name="recipients[]" value="<?= $wc['id'] ?>"
                               class="wa-checkbox w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500"
                               onchange="waUpdateCount()">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-800 truncate"><?= xss_clean($wc['name'] ?: '—') ?></p>
                            <p class="text-xs text-slate-400 font-medium"><?= xss_clean($wc['mobile']) ?></p>
                        </div>
                        <?php if (!empty($wc['category'])): ?>
                            <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full font-bold whitespace-nowrap"><?= xss_clean($wc['category']) ?></span>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="p-3 border-t bg-slate-50/50 text-center flex-shrink-0">
                <p class="text-xs text-slate-500 font-medium"><span id="waSelectedCount" class="font-extrabold text-indigo-600">0</span> selected</p>
            </div>
        </div>

        <!-- RIGHT: Message Composer + Preview -->
        <div class="lg:col-span-3 flex flex-col gap-4">

            <!-- Composer -->
            <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
                <div class="p-4 border-b bg-slate-50/50">
                    <h3 class="font-extrabold text-slate-800 text-sm">Compose Message</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Plain text only — emojis supported ✅</p>
                </div>
                <div class="p-4">
                    <textarea id="waMessageBody" rows="7"
                              placeholder="Type your message here..."
                              onkeyup="waUpdatePreview()"
                              oninput="waUpdatePreview()"
                              class="w-full border border-slate-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none resize-none font-medium leading-relaxed"></textarea>
                    <div class="flex justify-between mt-1.5">
                        <p class="text-xs text-slate-400">Characters: <span id="waCharCount" class="font-bold">0</span></p>
                        <p class="text-xs text-slate-400">SMS parts: <span id="waSmsCount" class="font-bold">0</span></p>
                    </div>
                </div>
            </div>

            <!-- Live Preview -->
            <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
                <div class="p-4 border-b bg-slate-50/50">
                    <h3 class="font-extrabold text-slate-800 text-sm flex items-center gap-2">
                        <i class="fa-brands fa-whatsapp" style="color: #25D366;"></i> Message Preview
                    </h3>
                </div>
                <div class="p-4" style="background: #e5ddd5; min-height: 100px; border-radius: 0 0 1rem 1rem;">
                    <div class="flex justify-end">
                        <div id="waPreviewBubble"
                             style="background: #dcf8c6; border-radius: 12px 0 12px 12px; max-width: 80%; padding: 10px 14px; font-size: 14px; font-family: sans-serif; white-space: pre-wrap; word-break: break-word; color: #111; box-shadow: 0 1px 2px rgba(0,0,0,.15); min-width: 120px; min-height: 36px;">
                            <span class="wa-preview-text" style="color: #bbb; font-style: italic; font-size: 12px;">Your message will appear here...</span>
                        </div>
                    </div>
                    <div class="flex justify-end mt-1">
                        <span style="font-size: 11px; color: #667781;"><?= date('g:i A') ?> ✓✓</span>
                    </div>
                </div>
            </div>

            <!-- Send Bar -->
            <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4">
                <?php if (!$wa_hasProvider): ?>
                    <div class="flex items-center gap-3 p-3 bg-amber-50 border border-amber-200 rounded-xl mb-3">
                        <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
                        <div class="flex-1">
                            <p class="text-sm font-bold text-amber-800">No active provider configured</p>
                            <p class="text-xs text-amber-700">Messages will be <strong>logged but not sent</strong> until you configure a provider.</p>
                        </div>
                        <a href="?route=app&page=whatsapp&tab=settings" class="text-xs font-bold text-indigo-600 hover:underline whitespace-nowrap">Set up →</a>
                    </div>
                <?php endif; ?>

                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="button" onclick="waOpenSendModal(false)"
                            class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-3 rounded-xl text-sm font-bold shadow-md flex items-center justify-center gap-2 transition" id="waSendBtn">
                        <i class="fa-brands fa-whatsapp"></i>
                        Send to <span id="waSendBtnCount">0</span> selected
                    </button>
                    <button type="button" onclick="waOpenSendModal(true)"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-xl text-sm font-bold shadow-md flex items-center justify-center gap-2 transition">
                        <i class="fa-solid fa-users"></i>
                        Send to All (<?= number_format($wa_totalCustomers) ?>)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Confirmation Modal -->
    <div id="waSendModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
                <h3 class="font-extrabold text-slate-800 text-lg flex items-center gap-2">
                    <i class="fa-brands fa-whatsapp" style="color: #25D366;"></i> Confirm Send
                </h3>
                <button onclick="document.getElementById('waSendModal').classList.add('hidden')"
                        class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <form method="POST" action="?route=app" id="waSendForm">
                <input type="hidden" name="action" value="send_whatsapp">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="send_to_all" id="waSendToAllInput" value="">
                <div id="waRecipientsContainer"></div>
                <textarea name="message_body" id="waSendFormMessage" class="hidden"></textarea>

                <div class="p-6 space-y-4">
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Message Preview</p>
                        <p id="waConfirmPreview" class="text-sm text-slate-700 leading-relaxed font-medium whitespace-pre-wrap"></p>
                    </div>
                    <div class="flex items-center gap-3 bg-indigo-50 border border-indigo-100 rounded-xl p-4">
                        <i class="fa-solid fa-paper-plane text-indigo-500 text-xl"></i>
                        <div>
                            <p class="font-extrabold text-slate-800" id="waConfirmCount"></p>
                            <p class="text-xs text-slate-500" id="waConfirmProviderNote"></p>
                        </div>
                    </div>
                    <?php if (!$wa_hasProvider): ?>
                    <div class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
                        <p class="text-sm text-amber-800 font-bold">No active provider — message will be logged only, not delivered.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="px-6 pb-6 flex gap-3">
                    <button type="button" onclick="document.getElementById('waSendModal').classList.add('hidden')"
                            class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 px-5 py-3 rounded-xl text-sm font-bold transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="flex-1 text-white px-5 py-3 rounded-xl text-sm font-bold shadow-md transition flex items-center justify-center gap-2"
                            style="background: #25D366;" onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'">
                        <i class="fa-brands fa-whatsapp"></i> Confirm & Send
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php endif; // has_permission check ?>

    <!-- ============================================================ -->
    <!-- TAB 2: MESSAGE HISTORY -->
    <!-- ============================================================ -->
    <?php elseif ($wa_tab === 'history'): ?>

    <!-- History filters -->
    <form method="GET" action="?route=app" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-4 flex flex-wrap gap-3 items-end">
        <input type="hidden" name="route" value="app">
        <input type="hidden" name="page" value="whatsapp">
        <input type="hidden" name="tab" value="history">
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">From</label>
            <input type="date" name="wa_from" value="<?= xss_clean($_GET['wa_from'] ?? '') ?>"
                   class="border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">To</label>
            <input type="date" name="wa_to" value="<?= xss_clean($_GET['wa_to'] ?? '') ?>"
                   class="border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Status</label>
            <select name="wa_status" class="border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none">
                <option value="">All Statuses</option>
                <?php foreach (['Sent', 'Partial', 'Failed', 'No Provider'] as $ws): ?>
                    <option value="<?= $ws ?>" <?= ($_GET['wa_status'] ?? '') === $ws ? 'selected' : '' ?>><?= $ws ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-sm transition">
            <i class="fa-solid fa-filter mr-1"></i> Filter
        </button>
        <a href="?route=app&page=whatsapp&tab=history" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2.5 rounded-xl text-sm font-bold transition">
            <i class="fa-solid fa-xmark mr-1"></i> Clear
        </a>
    </form>

    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
        <div class="p-5 border-b bg-slate-50/50">
            <h3 class="font-extrabold text-slate-800">Sent Message History</h3>
            <p class="text-xs text-slate-400 mt-0.5"><?= count($wa_logs) ?> campaign(s) found</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-white text-slate-400 uppercase tracking-wider text-xs border-b">
                    <tr>
                        <th class="px-5 py-4 font-bold">Campaign ID</th>
                        <th class="px-5 py-4 font-bold">Date & Time</th>
                        <th class="px-5 py-4 font-bold">Message</th>
                        <th class="px-5 py-4 font-bold text-center">Recipients</th>
                        <th class="px-5 py-4 font-bold text-center">Sent</th>
                        <th class="px-5 py-4 font-bold text-center">Failed</th>
                        <th class="px-5 py-4 font-bold">Status</th>
                        <th class="px-5 py-4 font-bold">Sent By</th>
                        <th class="px-5 py-4 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($wa_logs)): ?>
                        <tr>
                            <td colspan="9" class="p-12 text-center">
                                <i class="fa-brands fa-whatsapp text-5xl text-slate-200 mb-3 block" style="color: #d1d5db;"></i>
                                <p class="text-slate-400 font-medium">No messages sent yet.</p>
                                <a href="?route=app&page=whatsapp&tab=compose" class="text-indigo-500 text-sm font-bold hover:underline">Compose your first message →</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($wa_logs as $wl): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-4 font-extrabold text-indigo-600"><?= xss_clean($wl['id']) ?></td>
                            <td class="px-5 py-4 text-xs font-medium whitespace-nowrap">
                                <?= date('d M Y', strtotime($wl['created_at'])) ?><br>
                                <span class="text-slate-400"><?= date('g:i A', strtotime($wl['created_at'])) ?></span>
                            </td>
                            <td class="px-5 py-4 max-w-xs">
                                <p class="text-slate-700 font-medium truncate" style="max-width: 220px;"
                                   title="<?= xss_clean($wl['message_body']) ?>">
                                    <?= xss_clean(mb_strimwidth($wl['message_body'], 0, 60, '…')) ?>
                                </p>
                                <?php if (!empty($wl['provider_name'])): ?>
                                    <p class="text-[10px] text-slate-400 mt-0.5"><?= xss_clean($wl['provider_name']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-center font-extrabold text-slate-800"><?= $wl['recipient_count'] ?></td>
                            <td class="px-5 py-4 text-center font-extrabold text-emerald-600"><?= $wl['sent_count'] ?></td>
                            <td class="px-5 py-4 text-center font-extrabold <?= $wl['failed_count'] > 0 ? 'text-rose-500' : 'text-slate-300' ?>"><?= $wl['failed_count'] ?></td>
                            <td class="px-5 py-4"><?= waStatusBadge($wl['status']) ?></td>
                            <td class="px-5 py-4 text-xs font-bold text-slate-600"><?= xss_clean($wl['sender_name']) ?></td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <button onclick="waShowRecipients('<?= xss_clean($wl['id']) ?>')"
                                        class="text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-indigo-100 transition mr-1">
                                    <i class="fa-solid fa-list-ul mr-1"></i>Details
                                </button>
                                <?php if (!$_SESSION['is_staff']): ?>
                                <a href="?route=app&action=delete_whatsapp_log&id=<?= $wl['id'] ?>"
                                   onclick="return confirm('Delete this message log and all recipient records?')"
                                   class="text-rose-500 bg-rose-50 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-rose-100 transition">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recipients Detail Modal -->
    <div id="waRecipientsModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden" style="max-height: 85vh;">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50 sticky top-0">
                <h3 class="font-extrabold text-slate-800 text-lg flex items-center gap-2">
                    <i class="fa-solid fa-users text-indigo-500"></i>
                    <span id="waRecipModalTitle">Recipients</span>
                </h3>
                <button onclick="document.getElementById('waRecipientsModal').classList.add('hidden')"
                        class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div id="waRecipModalBody" class="overflow-y-auto custom-scrollbar" style="max-height: calc(85vh - 80px);">
                <div class="p-8 text-center text-slate-400">
                    <i class="fa-solid fa-spinner fa-spin text-3xl mb-3 text-indigo-300"></i>
                    <p class="font-medium">Loading recipients...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TAB 3: PROVIDER SETTINGS -->
    <!-- ============================================================ -->
    <?php elseif ($wa_tab === 'settings'): ?>

    <?php if ($_SESSION['is_staff']): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-8 text-center">
            <i class="fa-solid fa-lock text-4xl text-amber-300 mb-3"></i>
            <h3 class="font-extrabold text-amber-800 text-lg">Admin Access Only</h3>
            <p class="text-amber-700 text-sm mt-1">Provider settings can only be configured by the Agency Admin.</p>
        </div>
    <?php else: ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Provider Form -->
        <div class="lg:col-span-2 bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
            <div class="p-6 border-b bg-slate-50/50">
                <h3 class="font-extrabold text-slate-800 text-base flex items-center gap-2">
                    <i class="fa-solid fa-plug text-indigo-500"></i>
                    <?= $wa_provider ? 'Update Provider' : 'Configure Provider' ?>
                </h3>
                <p class="text-xs text-slate-400 mt-1">Connect your WhatsApp Business API. Credentials are stored only in your database.</p>
            </div>
            <form method="POST" action="?route=app" class="p-6 space-y-5">
                <input type="hidden" name="action" value="save_whatsapp_provider">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="provider_id" value="<?= $wa_provider ? $wa_provider['id'] : 0 ?>">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Provider Label <span class="text-rose-400">*</span></label>
                        <input type="text" name="provider_name" required
                               value="<?= xss_clean($wa_provider['provider_name'] ?? 'My WhatsApp Provider') ?>"
                               placeholder="e.g. WATI Production"
                               class="w-full border border-slate-200 p-2.5 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">API Provider / Type <span class="text-rose-400">*</span></label>
                        <select name="api_type" id="waApiType" onchange="waToggleFields()"
                                class="w-full border border-slate-200 p-2.5 rounded-xl text-sm font-bold text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50">
                            <?php
                            $waApiTypes = [
                                'custom_webhook' => 'Custom Webhook (Generic HTTP POST)',
                                'meta_cloud'     => 'Meta Cloud API (Official WhatsApp Business)',
                                'twilio'         => 'Twilio WhatsApp',
                                'vonage'         => 'Vonage / Nexmo',
                                'wati'           => 'WATI (WhatsApp Team Inbox)',
                            ];
                            foreach ($waApiTypes as $val => $label):
                            ?>
                                <option value="<?= $val ?>" <?= ($wa_provider['api_type'] ?? 'custom_webhook') === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Endpoint URL (shown for custom_webhook and wati) -->
                <div id="waFieldEndpoint">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5" id="waEndpointLabel">Endpoint URL</label>
                    <input type="url" name="api_endpoint"
                           value="<?= xss_clean($wa_provider['api_endpoint'] ?? '') ?>"
                           placeholder="https://your-api-endpoint.com/send"
                           class="w-full border border-slate-200 p-2.5 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5" id="waApiKeyLabel">API Key / Access Token / Account SID</label>
                        <input type="text" name="api_key"
                               value="<?= xss_clean($wa_provider['api_key'] ?? '') ?>"
                               placeholder="Paste your key or token here"
                               class="w-full border border-slate-200 p-2.5 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 font-mono text-xs">
                        <p class="text-[10px] text-slate-400 mt-1" id="waApiKeyHint">Used in the Authorization header</p>
                    </div>
                    <div id="waFieldSecret">
                        <label class="block text-xs font-bold text-slate-700 mb-1.5" id="waApiSecretLabel">API Secret / Auth Token</label>
                        <input type="text" name="api_secret"
                               value="<?= xss_clean($wa_provider['api_secret'] ?? '') ?>"
                               placeholder="Required for Twilio, Vonage"
                               class="w-full border border-slate-200 p-2.5 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 font-mono text-xs">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5" id="waFromLabel">From Number / Phone Number ID / Sender ID</label>
                    <input type="text" name="from_number"
                           value="<?= xss_clean($wa_provider['from_number'] ?? '') ?>"
                           placeholder="e.g. +880XXXXXXXXXX or your Meta phone_number_id"
                           class="w-full border border-slate-200 p-2.5 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50">
                    <p class="text-[10px] text-slate-400 mt-1" id="waFromHint">The WhatsApp number/ID messages are sent from</p>
                </div>

                <div id="waFieldExtra">
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Extra Parameters <span class="font-normal text-slate-400">(JSON, optional)</span>
                    </label>
                    <textarea name="extra_params" rows="3"
                              placeholder='{"key": "value"}'
                              class="w-full border border-slate-200 p-2.5 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 font-mono text-xs resize-none"><?= xss_clean($wa_provider['extra_params'] ?? '') ?></textarea>
                    <p class="text-[10px] text-slate-400 mt-1">Optional JSON object merged into the webhook payload for custom webhooks.</p>
                </div>

                <label class="flex items-center gap-3 cursor-pointer bg-slate-50 p-3 rounded-xl border border-slate-200 hover:border-indigo-300 transition">
                    <input type="checkbox" name="is_active" value="1" class="w-4 h-4 text-indigo-600 rounded"
                           <?= (!empty($wa_provider['is_active'])) ? 'checked' : '' ?>>
                    <div>
                        <span class="text-sm font-bold text-slate-800">Mark as Active Provider</span>
                        <p class="text-xs text-slate-400">Only the active provider is used for sending. Uncheck to save config without activating.</p>
                    </div>
                </label>

                <div class="flex justify-end pt-2 border-t border-slate-100">
                    <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-xl font-bold shadow-lg transition flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <?= $wa_provider ? 'Update Provider' : 'Save Provider' ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Help panel -->
        <div class="space-y-4">
            <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
                <h4 class="font-extrabold text-slate-800 text-sm mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-circle-info text-indigo-400"></i> Quick Setup Guides
                </h4>
                <div class="space-y-3 text-xs text-slate-600">
                    <div class="border-b pb-3">
                        <p class="font-bold text-slate-800 mb-1">Meta Cloud API</p>
                        <ol class="list-decimal list-inside space-y-1 text-slate-500">
                            <li>Create a Meta Business App at developers.facebook.com</li>
                            <li>Add the WhatsApp product</li>
                            <li>Copy the Phone Number ID and Temporary/Permanent Token</li>
                            <li>Select <strong>Meta Cloud API</strong> and paste them above</li>
                        </ol>
                    </div>
                    <div class="border-b pb-3">
                        <p class="font-bold text-slate-800 mb-1">Twilio</p>
                        <ol class="list-decimal list-inside space-y-1 text-slate-500">
                            <li>Log in at console.twilio.com</li>
                            <li>Copy Account SID (API Key) and Auth Token (Secret)</li>
                            <li>Set From Number to your Twilio WhatsApp sender</li>
                        </ol>
                    </div>
                    <div class="border-b pb-3">
                        <p class="font-bold text-slate-800 mb-1">WATI</p>
                        <ol class="list-decimal list-inside space-y-1 text-slate-500">
                            <li>Log in to your WATI dashboard</li>
                            <li>Copy the API Endpoint and API Token</li>
                            <li>Select <strong>WATI</strong> above</li>
                        </ol>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800 mb-1">Custom Webhook</p>
                        <p class="text-slate-500">Any HTTP endpoint that accepts a POST with <code class="bg-slate-100 px-1 rounded">phone</code> and <code class="bg-slate-100 px-1 rounded">message</code> fields (JSON). Extra Parameters lets you add any provider-specific fields.</p>
                    </div>
                </div>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-xs text-amber-800">
                <p class="font-bold mb-1"><i class="fa-solid fa-shield-halved mr-1"></i> Security Note</p>
                <p>API keys are stored in your private database. Never share them. Rotate them immediately if compromised.</p>
            </div>

            <?php if ($wa_provider): ?>
            <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
                <h4 class="font-extrabold text-slate-800 text-sm mb-3">Current Config</h4>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between"><span class="text-slate-500">Provider</span><span class="font-bold text-slate-800"><?= xss_clean($wa_provider['provider_name']) ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Type</span><span class="font-bold text-indigo-600 uppercase"><?= str_replace('_', ' ', $wa_provider['api_type']) ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Active</span><span class="font-bold <?= $wa_provider['is_active'] ? 'text-emerald-600' : 'text-rose-500' ?>"><?= $wa_provider['is_active'] ? '✓ Yes' : '✗ No' ?></span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Updated</span><span class="font-bold text-slate-800"><?= timeAgo($wa_provider['updated_at']) ?></span></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; // staff check ?>

    <?php endif; // tab switch ?>

</div><!-- end .space-y-6 -->

<!-- ============================================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================================ -->
<script>
// ---- Compose tab helpers ----
function waSelectAll() {
    document.querySelectorAll('.wa-checkbox').forEach(cb => cb.checked = true);
    waUpdateCount();
}
function waClearAll() {
    document.querySelectorAll('.wa-checkbox').forEach(cb => cb.checked = false);
    waUpdateCount();
}
function waUpdateCount() {
    const n = document.querySelectorAll('.wa-checkbox:checked').length;
    const el = document.getElementById('waSelectedCount');
    const btn = document.getElementById('waSendBtnCount');
    if (el) el.textContent = n;
    if (btn) btn.textContent = n;
}
function waUpdatePreview() {
    const body = document.getElementById('waMessageBody');
    if (!body) return;
    const text = body.value;
    const bubble = document.getElementById('waPreviewBubble');
    const charEl = document.getElementById('waCharCount');
    const smsEl  = document.getElementById('waSmsCount');
    if (bubble) {
        if (text.trim() === '') {
            bubble.innerHTML = '<span style="color:#bbb;font-style:italic;font-size:12px;">Your message will appear here...</span>';
        } else {
            bubble.textContent = text;
        }
    }
    if (charEl) charEl.textContent = text.length;
    if (smsEl)  smsEl.textContent  = Math.ceil(Math.max(text.length, 1) / 160);
}
function waOpenSendModal(toAll) {
    const body    = document.getElementById('waMessageBody');
    const modal   = document.getElementById('waSendModal');
    const preview = document.getElementById('waConfirmPreview');
    const count   = document.getElementById('waConfirmCount');
    const note    = document.getElementById('waConfirmProviderNote');
    const toAllIn = document.getElementById('waSendToAllInput');
    const msgIn   = document.getElementById('waSendFormMessage');
    const container = document.getElementById('waRecipientsContainer');

    if (!body || body.value.trim() === '') {
        alert('Please write a message first.');
        return;
    }

    const checkedBoxes = document.querySelectorAll('.wa-checkbox:checked');
    if (!toAll && checkedBoxes.length === 0) {
        alert('Please select at least one recipient, or use "Send to All".');
        return;
    }

    // Populate hidden form fields
    msgIn.value   = body.value;
    toAllIn.value = toAll ? '1' : '';
    container.innerHTML = '';
    if (!toAll) {
        checkedBoxes.forEach(cb => {
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'recipients[]';
            inp.value = cb.value;
            container.appendChild(inp);
        });
    }

    // Update modal text
    if (preview) preview.textContent = body.value;
    const recipCount = toAll ? parseInt('<?= $wa_totalCustomers ?>') : checkedBoxes.length;
    if (count) count.textContent = 'Sending to ' + recipCount + ' recipient(s)';
    if (note)  note.textContent  = toAll ? 'All customers with phone numbers in your database.' : 'Only the customers you selected.';

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

// ---- History tab: load recipient detail via fetch ----
function waShowRecipients(logId) {
    const modal = document.getElementById('waRecipientsModal');
    const body  = document.getElementById('waRecipModalBody');
    const title = document.getElementById('waRecipModalTitle');
    if (!modal) return;
    title.textContent = 'Recipients for ' + logId;
    body.innerHTML = '<div class="p-8 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin text-3xl mb-3 text-indigo-300 block"></i><p class="font-medium">Loading...</p></div>';
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    fetch('?route=app&page=whatsapp&wa_recipients_ajax=1&log_id=' + encodeURIComponent(logId))
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                body.innerHTML = '<div class="p-8 text-center text-slate-400"><p>No recipient records found.</p></div>';
                return;
            }
            const statusColor = {
                'Sent': 'bg-emerald-100 text-emerald-700',
                'Failed': 'bg-rose-100 text-rose-700',
                'No Provider': 'bg-slate-100 text-slate-500',
                'Pending': 'bg-yellow-100 text-yellow-700',
            };
            let html = '<div class="overflow-x-auto"><table class="w-full text-sm text-left">';
            html += '<thead class="bg-slate-50 text-slate-400 text-xs uppercase border-b"><tr><th class="px-5 py-3 font-bold">Name</th><th class="px-5 py-3 font-bold">Phone</th><th class="px-5 py-3 font-bold">Status</th><th class="px-5 py-3 font-bold">Note</th></tr></thead><tbody class="divide-y divide-slate-100">';
            data.forEach(r => {
                const cls = statusColor[r.status] || 'bg-slate-100 text-slate-500';
                html += `<tr class="hover:bg-slate-50"><td class="px-5 py-3 font-bold text-slate-800">${r.customer_name || '—'}</td><td class="px-5 py-3 font-mono text-xs">${r.phone}</td><td class="px-5 py-3"><span class="px-2.5 py-1 rounded-lg text-xs font-bold ${cls}">${r.status}</span></td><td class="px-5 py-3 text-xs text-slate-500">${r.error_message || (r.sent_at ? '✓ '+r.sent_at : '')}</td></tr>`;
            });
            html += '</tbody></table></div>';
            body.innerHTML = html;
        })
        .catch(() => {
            body.innerHTML = '<div class="p-8 text-center text-rose-400"><p>Failed to load recipients.</p></div>';
        });
}

// ---- Settings tab: toggle field visibility by provider type ----
function waToggleFields() {
    const type = document.getElementById('waApiType')?.value;
    if (!type) return;
    const hints = {
        meta_cloud:      { key: 'Permanent/Temporary Access Token', keyHint: 'From Meta Developer console', from: 'Phone Number ID (from Meta dashboard)', fromHint: 'Not the phone number — the Meta-assigned ID', showSecret: false, showEndpoint: false, endpointLabel: 'API Base URL (leave blank for default v18.0)' },
        twilio:          { key: 'Account SID', keyHint: 'From twilio.com/console', from: 'WhatsApp Sender Number (e.g. +14155238886)', fromHint: 'Your Twilio WhatsApp-enabled number', showSecret: true,  showEndpoint: false, endpointLabel: '' },
        vonage:          { key: 'API Key', keyHint: 'From dashboard.nexmo.com', from: 'From Number (WhatsApp Business number)', fromHint: 'Must be a Vonage-approved WhatsApp sender', showSecret: true,  showEndpoint: true,  endpointLabel: 'Vonage Messages API Base URL' },
        wati:            { key: 'API Bearer Token', keyHint: 'From WATI dashboard → API settings', from: 'Not required for WATI (leave blank)', fromHint: 'WATI routes via your linked WhatsApp number', showSecret: false, showEndpoint: true,  endpointLabel: 'WATI Endpoint URL (e.g. https://live-mt-server.wati.io/xxxxx)' },
        custom_webhook:  { key: 'API Key / Bearer Token (optional)', keyHint: 'Added as Authorization: Bearer header if set', from: 'Sender ID (if your provider needs it)', fromHint: 'Included in the webhook payload as "from"', showSecret: false, showEndpoint: true,  endpointLabel: 'Webhook Endpoint URL *' },
    };
    const h = hints[type] || hints.custom_webhook;
    const keyLabel    = document.getElementById('waApiKeyLabel');
    const keyHint     = document.getElementById('waApiKeyHint');
    const secretField = document.getElementById('waFieldSecret');
    const endpointField = document.getElementById('waFieldEndpoint');
    const endpointLabel = document.getElementById('waEndpointLabel');
    const fromLabel   = document.getElementById('waFromLabel');
    const fromHint    = document.getElementById('waFromHint');
    const extraField  = document.getElementById('waFieldExtra');

    if (keyLabel) keyLabel.textContent = h.key;
    if (keyHint)  keyHint.textContent  = h.keyHint;
    if (fromLabel) fromLabel.textContent = h.from;
    if (fromHint)  fromHint.textContent  = h.fromHint;
    if (secretField)  secretField.style.display  = h.showSecret  ? '' : 'none';
    if (endpointField) endpointField.style.display = h.showEndpoint ? '' : 'none';
    if (endpointLabel) endpointLabel.textContent   = h.endpointLabel;
    // Show extra params only for custom webhook
    if (extraField) extraField.style.display = type === 'custom_webhook' ? '' : 'none';
}

// Init on page load
waToggleFields();
waUpdatePreview();
waUpdateCount();
</script>

