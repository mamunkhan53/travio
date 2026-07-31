<?php
// =========================================================================
// WHATSAPP AUTOMATION MANAGEMENT PAGE
// Agency Admin only — staff are blocked at the dispatch level.
// =========================================================================

if (isAgencySubscriptionExpired($conn, $agency_id)) {
    echo '<div class="bg-rose-50 border border-rose-200 rounded-2xl p-8 text-center">
        <i class="fa-solid fa-lock text-4xl text-rose-300 mb-3"></i>
        <h3 class="font-extrabold text-rose-800 text-lg">Subscription Expired</h3>
        <p class="text-rose-700 text-sm mt-1">Renew your plan to manage automations.</p>
    </div>';
    return;
}

// Process any due queue items
processWAAutomationQueue($conn, $agency_id);

// Load existing automation configs for this agency
$existingStmt = $conn->prepare("SELECT * FROM whatsapp_automations WHERE agency_id = ?");
$existingStmt->execute([$agency_id]);
$existingConfigs = [];
foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existingConfigs[$row['automation_type']] = $row;
}

// Active provider check
$provStmt = $conn->prepare("SELECT provider_name FROM whatsapp_providers WHERE agency_id=? AND is_active=1 LIMIT 1");
$provStmt->execute([$agency_id]);
$activeProviderName = $provStmt->fetchColumn();

// Stats
$totalEnabled = $conn->prepare("SELECT COUNT(*) FROM whatsapp_automations WHERE agency_id=? AND is_enabled=1");
$totalEnabled->execute([$agency_id]);
$totalEnabled = (int)$totalEnabled->fetchColumn();

$sentThisMonth = $conn->prepare(
    "SELECT COUNT(*) FROM whatsapp_message_logs WHERE agency_id=? AND sent_by_type='automation' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
);
$sentThisMonth->execute([$agency_id]);
$sentThisMonth = (int)$sentThisMonth->fetchColumn();

$queuePending = $conn->prepare("SELECT COUNT(*) FROM whatsapp_automation_queue WHERE agency_id=? AND status='Pending'");
$queuePending->execute([$agency_id]);
$queuePending = (int)$queuePending->fetchColumn();

// Automation type definitions — single source of truth for UI + defaults
$autoTypes = [
    'booking_confirmation' => [
        'title'   => 'Booking Confirmation',
        'icon'    => 'fa-circle-check',
        'color'   => 'emerald',
        'desc'    => 'Sent when a sale is set to Confirmed. Instant acknowledgement builds customer trust.',
        'trigger' => 'Status → Confirmed (any service)',
        'timing'  => false,
        'vars'    => ['{CustomerName}','{CompanyName}','{ServiceName}','{OfficePhone}'],
        'default' => "Dear {CustomerName}, your {ServiceName} booking has been confirmed with {CompanyName}! 🎉\nThank you for choosing us. Contact us anytime at {OfficePhone}.",
    ],
    'payment_reminder' => [
        'title'   => 'Payment Reminder',
        'icon'    => 'fa-money-bill-wave',
        'color'   => 'amber',
        'desc'    => 'Sent when an invoice is generated with an outstanding balance. Timing lets you schedule a follow-up.',
        'trigger' => 'Invoice created with due amount',
        'timing'  => true,
        'vars'    => ['{CustomerName}','{CompanyName}','{InvoiceNo}','{InvoiceAmount}','{DueAmount}','{DueDate}','{OfficePhone}'],
        'default' => "Dear {CustomerName}, a payment of {DueAmount} is outstanding on invoice {InvoiceNo} with {CompanyName}.\nPlease settle by {DueDate}. Contact us at {OfficePhone}.",
    ],
    'followup_reminder' => [
        'title'   => 'Follow-up Reminder',
        'icon'    => 'fa-comment-dots',
        'color'   => 'indigo',
        'desc'    => 'Sent to the customer on their scheduled follow-up date.',
        'trigger' => 'Follow-up date set on a Lead or Sale',
        'timing'  => true,
        'vars'    => ['{CustomerName}','{CompanyName}','{ServiceName}','{OfficePhone}'],
        'default' => "Dear {CustomerName}, we wanted to follow up regarding your {ServiceName} with {CompanyName}.\nOur team is here to help. Contact us at {OfficePhone}.",
    ],
    'flight_reminder' => [
        'title'   => 'Flight Reminder',
        'icon'    => 'fa-plane-departure',
        'color'   => 'sky',
        'desc'    => 'Sent before the travel date for air ticket bookings. Configure how many days/hours before.',
        'trigger' => 'Ticket travel date approaching',
        'timing'  => true,
        'vars'    => ['{CustomerName}','{CompanyName}','{FlightDate}','{FlightTime}','{OfficePhone}'],
        'default' => "Dear {CustomerName}, your flight is scheduled on {FlightDate} 🛫\nPlease have all travel documents ready. For assistance, contact {CompanyName} at {OfficePhone}.",
    ],
    'visa_status_update' => [
        'title'   => 'Visa Status Update',
        'icon'    => 'fa-file-signature',
        'color'   => 'violet',
        'desc'    => 'Sent whenever a visa application status changes.',
        'trigger' => 'Visa record status changed',
        'timing'  => false,
        'vars'    => ['{CustomerName}','{CompanyName}','{VisaCountry}','{VisaStatus}','{OfficePhone}'],
        'default' => "Dear {CustomerName}, your visa application for {VisaCountry} has been updated.\nNew Status: *{VisaStatus}*\nFor details, contact {CompanyName} at {OfficePhone}.",
    ],
    'passport_ready' => [
        'title'   => 'Passport Ready Notification',
        'icon'    => 'fa-passport',
        'color'   => 'blue',
        'desc'    => 'Sent when a passport status becomes Ready or Collected.',
        'trigger' => 'Passport status → Ready / Collected',
        'timing'  => false,
        'vars'    => ['{CustomerName}','{CompanyName}','{PassportNumber}','{OfficePhone}'],
        'default' => "Dear {CustomerName}, great news! 🎉 Your passport (#{PassportNumber}) is ready for collection.\nContact {CompanyName} at {OfficePhone} to arrange pickup.",
    ],
    'invoice_notification' => [
        'title'   => 'Invoice Notification',
        'icon'    => 'fa-file-invoice-dollar',
        'color'   => 'teal',
        'desc'    => 'Sent immediately when a new invoice is generated.',
        'trigger' => 'New invoice created',
        'timing'  => false,
        'vars'    => ['{CustomerName}','{CompanyName}','{ServiceName}','{InvoiceNo}','{InvoiceAmount}','{DueAmount}','{OfficePhone}'],
        'default' => "Dear {CustomerName}, your invoice *{InvoiceNo}* for {ServiceName} has been issued by {CompanyName}.\n💰 Total: {InvoiceAmount} | Due: {DueAmount}\nContact us at {OfficePhone}.",
    ],
    'customer_feedback' => [
        'title'   => 'Customer Feedback Request',
        'icon'    => 'fa-star',
        'color'   => 'rose',
        'desc'    => 'Sent after a service is marked Completed or Paid. Use timing to delay by a few days.',
        'trigger' => 'Status → Completed / Paid',
        'timing'  => true,
        'vars'    => ['{CustomerName}','{CompanyName}','{ServiceName}','{OfficePhone}'],
        'default' => "Dear {CustomerName}, thank you for choosing {CompanyName} for your {ServiceName}! 🌟\nWe hope you had a wonderful experience. Your feedback means a lot to us. Contact: {OfficePhone}",
    ],
];

// Color map for Tailwind
$colorMap = [
    'emerald'=> ['bg'=>'bg-emerald-50', 'text'=>'text-emerald-600', 'badge'=>'bg-emerald-100 text-emerald-700', 'border'=>'border-emerald-200'],
    'amber'  => ['bg'=>'bg-amber-50',   'text'=>'text-amber-600',   'badge'=>'bg-amber-100 text-amber-700',     'border'=>'border-amber-200'],
    'indigo' => ['bg'=>'bg-indigo-50',  'text'=>'text-indigo-600',  'badge'=>'bg-indigo-100 text-indigo-700',   'border'=>'border-indigo-200'],
    'sky'    => ['bg'=>'bg-sky-50',     'text'=>'text-sky-600',     'badge'=>'bg-sky-100 text-sky-700',         'border'=>'border-sky-200'],
    'violet' => ['bg'=>'bg-violet-50',  'text'=>'text-violet-600',  'badge'=>'bg-violet-100 text-violet-700',   'border'=>'border-violet-200'],
    'blue'   => ['bg'=>'bg-blue-50',    'text'=>'text-blue-600',    'badge'=>'bg-blue-100 text-blue-700',       'border'=>'border-blue-200'],
    'teal'   => ['bg'=>'bg-teal-50',    'text'=>'text-teal-600',    'badge'=>'bg-teal-100 text-teal-700',       'border'=>'border-teal-200'],
    'rose'   => ['bg'=>'bg-rose-50',    'text'=>'text-rose-600',    'badge'=>'bg-rose-100 text-rose-700',       'border'=>'border-rose-200'],
];

// Agency phone for test-send pre-fill
$agRow = $conn->prepare("SELECT company_phone FROM agencies WHERE id=?");
$agRow->execute([$agency_id]);
$agPhone = $agRow->fetchColumn() ?: '';
?>

<div class="space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-robot text-indigo-500"></i> WhatsApp Automation
            </h2>
            <p class="text-sm text-slate-500 mt-1">Configure automatic messages triggered by business events. Manual sending is unaffected.</p>
        </div>
        <a href="/app/whatsapp" class="text-sm font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-4 py-2.5 rounded-xl transition flex items-center gap-2">
            <i class="fa-brands fa-whatsapp"></i> Manual Send
        </a>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Provider</p>
            <?php if ($activeProviderName): ?>
                <p class="text-sm font-extrabold text-emerald-600 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                    <?= xss_clean($activeProviderName) ?>
                </p>
            <?php else: ?>
                <p class="text-sm font-extrabold text-rose-500 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-rose-400 inline-block"></span>Not configured
                </p>
                <a href="/app/whatsapp?tab=settings" class="text-xs text-indigo-500 font-bold hover:underline">Set up →</a>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Active Rules</p>
            <p class="text-3xl font-black text-indigo-600"><?= $totalEnabled ?></p>
            <p class="text-xs text-slate-400">of <?= count($autoTypes) ?> available</p>
        </div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Sent This Month</p>
            <p class="text-3xl font-black text-emerald-600"><?= number_format($sentThisMonth) ?></p>
        </div>
        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Queued</p>
            <p class="text-3xl font-black text-amber-500"><?= number_format($queuePending) ?></p>
            <p class="text-xs text-slate-400">scheduled messages</p>
        </div>
    </div>

    <?php if (!$activeProviderName): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center gap-3">
        <i class="fa-solid fa-triangle-exclamation text-amber-500 text-xl"></i>
        <div class="flex-1">
            <p class="font-extrabold text-amber-800 text-sm">No active WhatsApp provider</p>
            <p class="text-xs text-amber-700 mt-0.5">Automations will be logged but <strong>not delivered</strong> until you configure a provider.</p>
        </div>
        <a href="/app/whatsapp?tab=settings" class="text-xs font-bold text-indigo-600 bg-white border border-indigo-200 px-4 py-2 rounded-xl hover:bg-indigo-50 transition whitespace-nowrap">Configure Provider →</a>
    </div>
    <?php endif; ?>

    <!-- Automation Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <?php foreach ($autoTypes as $typeKey => $typeDef):
        $cfg      = $existingConfigs[$typeKey] ?? null;
        $enabled  = $cfg ? (bool)$cfg['is_enabled'] : false;
        $template = $cfg ? $cfg['message_template'] : $typeDef['default'];
        $timing   = $cfg ? $cfg['send_timing']      : 'immediately';
        $timVal   = $cfg ? (int)$cfg['timing_value'] : 1;
        $timUnit  = $cfg ? $cfg['timing_unit']       : 'days';
        $cl       = $colorMap[$typeDef['color']];
        $cardId   = 'card_' . $typeKey;
    ?>
    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">

        <!-- Card Header -->
        <div class="flex items-start gap-4 p-5 <?= $enabled ? 'border-b border-slate-100' : '' ?> cursor-pointer select-none"
             onclick="toggleCard('<?= $cardId ?>')">
            <div class="w-11 h-11 rounded-xl <?= $cl['bg'] ?> flex items-center justify-center flex-shrink-0 text-lg <?= $cl['text'] ?>">
                <i class="fa-solid <?= $typeDef['icon'] ?>"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-extrabold text-slate-800 text-sm"><?= xss_clean($typeDef['title']) ?></h3>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $cl['badge'] ?>"><?= xss_clean($typeDef['trigger']) ?></span>
                </div>
                <p class="text-xs text-slate-500 mt-1"><?= xss_clean($typeDef['desc']) ?></p>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
                <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' ?>">
                    <?= $enabled ? 'ON' : 'OFF' ?>
                </span>
                <i class="fa-solid fa-chevron-down text-slate-400 text-xs transition-transform" id="chevron_<?= $cardId ?>"></i>
            </div>
        </div>

        <!-- Card Body (collapsible) -->
        <div id="<?= $cardId ?>" class="<?= $enabled ? '' : 'hidden' ?>">
            <form method="POST" action="" class="p-5 space-y-4 border-t border-slate-50">
                <input type="hidden" name="action"          value="save_automation">
                <input type="hidden" name="csrf_token"      value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="automation_type" value="<?= $typeKey ?>">

                <!-- Enable toggle -->
                <label class="flex items-center gap-3 cursor-pointer bg-slate-50 p-3 rounded-xl border border-slate-200 hover:border-indigo-300 transition">
                    <input type="checkbox" name="is_enabled" value="1" class="w-4 h-4 text-indigo-600 rounded"
                           <?= $enabled ? 'checked' : '' ?>>
                    <div>
                        <span class="text-sm font-bold text-slate-800">Enable this automation</span>
                        <p class="text-xs text-slate-400">When enabled, messages are sent automatically when the trigger condition is met.</p>
                    </div>
                </label>

                <?php if ($typeDef['timing']): ?>
                <!-- Timing -->
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 space-y-3">
                    <p class="text-xs font-extrabold text-slate-600 uppercase tracking-wider">Send Timing</p>
                    <div class="flex flex-wrap gap-3 items-center">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="send_timing" value="immediately"
                                   class="text-indigo-600" <?= $timing === 'immediately' ? 'checked' : '' ?>
                                   onchange="toggleTimingFields('<?= $typeKey ?>', this.value)">
                            <span class="text-sm font-bold text-slate-700">Immediately</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="send_timing" value="before"
                                   class="text-indigo-600" <?= $timing === 'before' ? 'checked' : '' ?>
                                   onchange="toggleTimingFields('<?= $typeKey ?>', this.value)">
                            <span class="text-sm font-bold text-slate-700">Before trigger date</span>
                        </label>
                    </div>
                    <div id="timing_fields_<?= $typeKey ?>" class="flex items-center gap-2 <?= $timing === 'before' ? '' : 'hidden' ?>">
                        <input type="number" name="timing_value" value="<?= $timVal ?>" min="1" max="365"
                               class="w-20 border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-indigo-400 outline-none">
                        <select name="timing_unit"
                                class="border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-indigo-400 outline-none">
                            <option value="hours" <?= $timUnit === 'hours' ? 'selected' : '' ?>>Hours before</option>
                            <option value="days"  <?= $timUnit === 'days'  ? 'selected' : '' ?>>Days before</option>
                        </select>
                    </div>
                </div>
                <?php else: ?>
                <input type="hidden" name="send_timing" value="immediately">
                <input type="hidden" name="timing_value" value="0">
                <input type="hidden" name="timing_unit" value="days">
                <?php endif; ?>

                <!-- Template Editor -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-extrabold text-slate-700 uppercase tracking-wider">Message Template</label>
                        <button type="button" onclick="previewTemplate('<?= $typeKey ?>')"
                                class="text-xs text-indigo-600 font-bold hover:underline flex items-center gap-1">
                            <i class="fa-solid fa-eye text-xs"></i> Preview
                        </button>
                    </div>
                    <textarea name="message_template" id="tmpl_<?= $typeKey ?>" rows="5"
                              class="w-full border border-slate-200 rounded-xl p-3 text-sm font-medium focus:ring-2 focus:ring-indigo-400 outline-none resize-none leading-relaxed"><?= xss_clean($template) ?></textarea>
                    <!-- Variable chips -->
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <?php foreach ($typeDef['vars'] as $v): ?>
                        <button type="button" onclick="insertVar('tmpl_<?= $typeKey ?>', '<?= $v ?>')"
                                class="text-[10px] font-bold px-2 py-1 rounded-lg bg-slate-100 text-slate-600 hover:bg-indigo-100 hover:text-indigo-700 transition border border-slate-200">
                            <?= xss_clean($v) ?>
                        </button>
                        <?php endforeach; ?>
                        <span class="text-[10px] text-slate-400 py-1">↑ click to insert</span>
                    </div>
                </div>

                <!-- Actions row -->
                <div class="flex flex-col sm:flex-row gap-3 pt-1">
                    <button type="submit"
                            class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-bold shadow transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Save Settings
                    </button>
                    <button type="button" onclick="openTestModal('<?= $typeKey ?>', '<?= xss_clean(addslashes($typeDef['title'])) ?>')"
                            class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl text-sm font-bold transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i> Test Send
                    </button>
                </div>
            </form>

            <!-- Last sent history mini-table -->
            <?php
            $lastLogs = $conn->prepare(
                "SELECT l.created_at, l.status, r.customer_name, r.phone
                 FROM whatsapp_message_logs l
                 JOIN whatsapp_message_recipients r ON r.log_id = l.id
                 WHERE l.agency_id = ? AND l.sent_by_type = 'automation'
                   AND l.message_body LIKE ?
                 ORDER BY l.created_at DESC LIMIT 3"
            );
            // Match by a snippet of the template as a heuristic identifier
            $tmplSnippet = '%' . mb_substr(strip_tags($typeDef['default']), 0, 20) . '%';
            $lastLogs->execute([$agency_id, $tmplSnippet]);
            $lastSent = $lastLogs->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (!empty($lastSent)): ?>
            <div class="px-5 pb-5">
                <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mb-2">Recent Sends</p>
                <div class="space-y-1.5">
                    <?php foreach ($lastSent as $ls): ?>
                    <div class="flex items-center gap-2 text-xs text-slate-600">
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold <?= $ls['status']==='Sent' ? 'bg-emerald-100 text-emerald-700' : ($ls['status']==='Failed' ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-500') ?>"><?= $ls['status'] ?></span>
                        <span class="font-bold truncate"><?= xss_clean($ls['customer_name'] ?: $ls['phone']) ?></span>
                        <span class="text-slate-400 ml-auto whitespace-nowrap"><?= timeAgo($ls['created_at']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div><!-- end grid -->

</div><!-- end space-y-6 -->

<!-- Preview Modal -->
<div id="waAutoPreviewModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-extrabold text-slate-800">Message Preview</h3>
            <button onclick="document.getElementById('waAutoPreviewModal').classList.add('hidden');document.getElementById('waAutoPreviewModal').classList.remove('flex');"
                    class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 mb-4">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Rendered with sample data</p>
                <p id="waAutoPreviewText" class="text-sm text-slate-700 whitespace-pre-wrap leading-relaxed font-medium"></p>
            </div>
            <div style="background:#e5ddd5; border-radius:12px; padding:12px;">
                <div style="display:flex; justify-content:flex-end;">
                    <div id="waAutoPreviewBubble"
                         style="background:#dcf8c6; border-radius:12px 0 12px 12px; max-width:85%; padding:10px 14px; font-size:13px; white-space:pre-wrap; word-break:break-word; box-shadow:0 1px 2px rgba(0,0,0,.15);">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Send Modal -->
<div id="waAutoTestModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
            <h3 class="font-extrabold text-slate-800 text-lg" id="waAutoTestTitle">Test Send</h3>
            <button onclick="document.getElementById('waAutoTestModal').classList.add('hidden');document.getElementById('waAutoTestModal').classList.remove('flex');"
                    class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <form method="POST" action="" class="p-6 space-y-4">
            <input type="hidden" name="action"          value="test_automation">
            <input type="hidden" name="csrf_token"      value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="automation_type" id="waAutoTestType" value="">
            <textarea name="message_template" id="waAutoTestTemplate" class="hidden"></textarea>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Send test to phone number</label>
                <input type="text" name="test_phone" value="<?= xss_clean($agPhone) ?>" required
                       placeholder="+8801700000000"
                       class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:ring-2 focus:ring-indigo-400 outline-none">
                <p class="text-xs text-slate-400 mt-1">Variables will be filled with sample data. Sent via your active provider.</p>
            </div>

            <div class="flex gap-3 pt-1">
                <button type="button"
                        onclick="document.getElementById('waAutoTestModal').classList.add('hidden');document.getElementById('waAutoTestModal').classList.remove('flex');"
                        class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-3 rounded-xl text-sm font-bold transition">
                    Cancel
                </button>
                <button type="submit"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-xl text-sm font-bold shadow transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> Send Test
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Sample data for preview
const WA_SAMPLE = {
    '{CustomerName}'  : 'John Doe',
    '{CompanyName}'   : '<?= xss_clean(addslashes($agency['company_name'] ?? 'Your Company')) ?>',
    '{ServiceName}'   : 'Air Ticket (Dhaka → Dubai)',
    '{InvoiceNo}'     : 'INV-0042',
    '{InvoiceAmount}' : '৳25,000',
    '{DueAmount}'     : '৳5,000',
    '{DueDate}'       : '<?= date('d M Y', strtotime('+7 days')) ?>',
    '{FlightDate}'    : '<?= date('d M Y', strtotime('+3 days')) ?>',
    '{FlightTime}'    : '10:30 AM',
    '{VisaCountry}'   : 'Saudi Arabia',
    '{VisaStatus}'    : 'Approved',
    '{PassportNumber}': 'P-123456',
    '{OfficePhone}'   : '<?= xss_clean(addslashes($agPhone)) ?>',
};

function replaceVars(tmpl) {
    let out = tmpl;
    Object.entries(WA_SAMPLE).forEach(([k, v]) => { out = out.replaceAll(k, v); });
    return out;
}

function toggleCard(id) {
    const el = document.getElementById(id);
    const ch = document.getElementById('chevron_' + id);
    const open = !el.classList.contains('hidden');
    el.classList.toggle('hidden', open);
    if (ch) ch.style.transform = open ? '' : 'rotate(180deg)';
}

function toggleTimingFields(typeKey, val) {
    const f = document.getElementById('timing_fields_' + typeKey);
    if (f) f.classList.toggle('hidden', val !== 'before');
}

function insertVar(textareaId, varText) {
    const ta = document.getElementById(textareaId);
    if (!ta) return;
    const s = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.substring(0, s) + varText + ta.value.substring(e);
    ta.selectionStart = ta.selectionEnd = s + varText.length;
    ta.focus();
}

function previewTemplate(typeKey) {
    const tmpl = document.getElementById('tmpl_' + typeKey)?.value || '';
    const rendered = replaceVars(tmpl);
    document.getElementById('waAutoPreviewText').textContent   = rendered;
    document.getElementById('waAutoPreviewBubble').textContent = rendered;
    const m = document.getElementById('waAutoPreviewModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function openTestModal(typeKey, title) {
    const tmpl = document.getElementById('tmpl_' + typeKey)?.value || '';
    document.getElementById('waAutoTestTitle').textContent    = 'Test: ' + title;
    document.getElementById('waAutoTestType').value           = typeKey;
    document.getElementById('waAutoTestTemplate').value       = tmpl;
    const m = document.getElementById('waAutoTestModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

// Init chevrons for open cards
document.querySelectorAll('[id^="card_"]').forEach(el => {
    if (!el.classList.contains('hidden')) {
        const ch = document.getElementById('chevron_' + el.id);
        if (ch) ch.style.transform = 'rotate(180deg)';
    }
});
</script>
