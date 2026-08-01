<?php
function renderSuperAdmin($conn) {
    $tab = $_GET['tab'] ?? 'agencies';
    $agencies = $conn->query("SELECT * FROM agencies ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $plans = getSubscriptionPlans($conn);
    $paymentMethods = getPaymentMethods($conn);
    $stats = [
        'Total' => $conn->query("SELECT COUNT(*) FROM agencies")->fetchColumn(),
        'Active' => $conn->query("SELECT COUNT(*) FROM agencies WHERE status='Active'")->fetchColumn(),
        'Pending' => $conn->query("SELECT COUNT(*) FROM agencies WHERE status='Pending Approval'")->fetchColumn(),
        'Trial' => $conn->query("SELECT COUNT(*) FROM agencies WHERE subscription_plan='Trial' AND subscription_expires_at >= NOW()")->fetchColumn(),
        'Expired' => $conn->query("SELECT COUNT(*) FROM agencies WHERE subscription_expires_at < NOW()")->fetchColumn(),
    ];
    $pendingPaymentCount = $conn->query("SELECT COUNT(*) FROM subscription_payments WHERE status='Pending'")->fetchColumn();

    $payments = $conn->query("SELECT sp.*, a.company_name FROM subscription_payments sp JOIN agencies a ON a.id = sp.agency_id ORDER BY (sp.status='Pending') DESC, sp.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

    $emailVerificationRequired = getPlatformSetting($conn, 'email_verification_required', '0') === '1';
    $agency2faEnabled = getPlatformSetting($conn, 'agency_2fa_enabled', '0') === '1';
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $superAdminUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // Keyed by agency_id, for the "Manage Login" modal on the Agencies tab
    $agencyUsersById = [];
    foreach ($conn->query("SELECT * FROM users WHERE role = 'Agency Admin'")->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $agencyUsersById[$u['agency_id']] = $u;
    }
?>
    <div class="min-h-screen bg-slate-50 flex flex-col">
        <header class="bg-indigo-900 text-white px-8 py-4 flex justify-between items-center shadow-md">
            <h1 class="text-xl font-bold"><i class="fa-solid fa-shield-halved mr-2"></i>Super Admin Portal</h1>
            <a href="/logout" class="bg-rose-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-rose-600">Logout</a>
        </header>
        <main class="flex-1 p-8 max-w-7xl mx-auto w-full">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                <?php foreach($stats as $label => $val): ?>
                    <div class="bg-white p-5 rounded-2xl soft-shadow border border-slate-100">
                        <p class="text-slate-500 font-medium text-xs uppercase tracking-wider"><?= $label ?></p>
                        <p class="text-3xl font-bold text-slate-800 mt-1"><?= $val ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- TABS -->
            <div class="flex flex-wrap gap-2 mb-6 border-b border-slate-200">
                <a href="/admin?tab=agencies" class="px-5 py-3 font-bold text-sm rounded-t-xl <?= $tab==='agencies' ? 'bg-white border border-b-0 border-slate-200 text-indigo-600' : 'text-slate-500 hover:text-indigo-600' ?>"><i class="fa-solid fa-building mr-2"></i>Agencies & Subscriptions</a>
                <a href="/admin?tab=plans" class="px-5 py-3 font-bold text-sm rounded-t-xl <?= $tab==='plans' ? 'bg-white border border-b-0 border-slate-200 text-indigo-600' : 'text-slate-500 hover:text-indigo-600' ?>"><i class="fa-solid fa-tags mr-2"></i>Subscription Packages</a>
                <a href="/admin?tab=payment_methods" class="px-5 py-3 font-bold text-sm rounded-t-xl <?= $tab==='payment_methods' ? 'bg-white border border-b-0 border-slate-200 text-indigo-600' : 'text-slate-500 hover:text-indigo-600' ?>"><i class="fa-solid fa-wallet mr-2"></i>Payment Methods</a>
                <a href="/admin?tab=payment_requests" class="px-5 py-3 font-bold text-sm rounded-t-xl relative <?= $tab==='payment_requests' ? 'bg-white border border-b-0 border-slate-200 text-indigo-600' : 'text-slate-500 hover:text-indigo-600' ?>">
                    <i class="fa-solid fa-receipt mr-2"></i>Payment Requests
                    <?php if ($pendingPaymentCount > 0): ?><span class="ml-1 bg-rose-500 text-white text-[10px] font-black px-1.5 py-0.5 rounded-full align-top"><?= $pendingPaymentCount ?></span><?php endif; ?>
                </a>
                <a href="/admin?tab=settings" class="px-5 py-3 font-bold text-sm rounded-t-xl <?= $tab==='settings' ? 'bg-white border border-b-0 border-slate-200 text-indigo-600' : 'text-slate-500 hover:text-indigo-600' ?>"><i class="fa-solid fa-gear mr-2"></i>Settings</a>
            </div>

            <?php if ($tab === 'plans'): ?>
                <!-- ---------------------------------------------------- -->
                <!-- SUBSCRIPTION PACKAGES (Trial / Monthly / Yearly) -->
                <!-- ---------------------------------------------------- -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($plans as $key => $p): ?>
                        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6 flex flex-col">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-extrabold text-slate-800 text-lg"><?= xss_clean($p['name']) ?></h3>
                                <span class="px-3 py-1 text-xs rounded-full font-bold <?= $p['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= $p['is_active'] ? 'Active' : 'Disabled' ?></span>
                            </div>
                            <p class="text-3xl font-black text-indigo-600 mb-1">৳<?= number_format($p['price'], 0) ?><span class="text-sm font-bold text-slate-400"> &middot; $<?= number_format($p['price_usd'] ?? 0, 2) ?></span></p>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Every <?= $p['duration_days'] ?> days</p>
                            <p class="text-sm text-slate-500 flex-1 mb-4"><?= xss_clean($p['terms']) ?></p>
                            <button onclick="openPlanModal('<?= $key ?>')" class="w-full bg-indigo-50 text-indigo-600 font-bold py-2.5 rounded-xl hover:bg-indigo-100 transition"><i class="fa-solid fa-pen mr-2"></i>Edit Package</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Edit Plan Modal (one shared modal, populated via JS) -->
                <div id="planModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
                        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
                            <h3 class="font-extrabold text-slate-800 text-lg" id="planModalTitle">Edit Package</h3>
                            <button onclick="document.getElementById('planModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <form method="POST" action="/admin" class="p-6 space-y-4">
                            <input type="hidden" name="action" value="save_plan">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="plan_key" id="pl_key">
                            <div><label class="block text-xs font-bold text-slate-700 mb-1">Package Name</label><input type="text" name="name" id="pl_name" required class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50"></div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="block text-xs font-bold text-slate-700 mb-1">Price (BDT)</label><input type="number" step="0.01" name="price" id="pl_price" required class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50 font-bold"></div>
                                <div><label class="block text-xs font-bold text-slate-700 mb-1">Price (USD)</label><input type="number" step="0.01" name="price_usd" id="pl_price_usd" required class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50 font-bold"></div>
                            </div>
                            <div><label class="block text-xs font-bold text-slate-700 mb-1">Duration (Days)</label><input type="number" name="duration_days" id="pl_duration" required class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50 font-bold"></div>
                            <div><label class="block text-xs font-bold text-slate-700 mb-1">Terms / Description</label><textarea name="terms" id="pl_terms" rows="3" class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50"></textarea></div>
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" id="pl_active" class="w-4 h-4"> Package is active / selectable</label>
                            <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 shadow-lg transition">Save Package</button>
                        </form>
                    </div>
                </div>
                <script>
                    const planData = <?= json_encode($plans) ?>;
                    function openPlanModal(key) {
                        const p = planData[key];
                        document.getElementById('planModalTitle').innerText = 'Edit ' + p.name;
                        document.getElementById('pl_key').value = p.plan_key;
                        document.getElementById('pl_name').value = p.name;
                        document.getElementById('pl_price').value = p.price;
                        document.getElementById('pl_price_usd').value = p.price_usd;
                        document.getElementById('pl_duration').value = p.duration_days;
                        document.getElementById('pl_terms').value = p.terms;
                        document.getElementById('pl_active').checked = p.is_active == 1;
                        document.getElementById('planModal').classList.remove('hidden');
                        document.getElementById('planModal').classList.add('flex');
                    }
                </script>

            <?php elseif ($tab === 'payment_methods'): ?>
                <!-- ---------------------------------------------------- -->
                <!-- MANUAL PAYMENT METHODS (bKash / Nagad / Bank) -->
                <!-- ---------------------------------------------------- -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($paymentMethods as $key => $m): ?>
                        <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6 flex flex-col">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-extrabold text-slate-800 text-lg"><?= xss_clean($m['display_name']) ?></h3>
                                <span class="px-3 py-1 text-xs rounded-full font-bold <?= $m['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= $m['is_active'] ? 'Visible to Agencies' : 'Hidden' ?></span>
                            </div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Account Details</p>
                            <p class="text-sm text-slate-700 whitespace-pre-line mb-4 flex-1"><?= xss_clean($m['account_details']) ?: '<span class="text-slate-300">Not set</span>' ?></p>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Instructions</p>
                            <p class="text-sm text-slate-500 whitespace-pre-line mb-4"><?= xss_clean($m['instructions']) ?: '<span class="text-slate-300">Not set</span>' ?></p>
                            <button onclick='openMethodModal(<?= json_encode($m) ?>)' class="w-full bg-indigo-50 text-indigo-600 font-bold py-2.5 rounded-xl hover:bg-indigo-100 transition"><i class="fa-solid fa-pen mr-2"></i>Edit</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="methodModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
                        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
                            <h3 class="font-extrabold text-slate-800 text-lg" id="methodModalTitle">Edit Payment Method</h3>
                            <button onclick="document.getElementById('methodModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <form method="POST" action="/admin" class="p-6 space-y-4">
                            <input type="hidden" name="action" value="save_payment_method">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="method_key" id="pm_key">
                            <div><label class="block text-xs font-bold text-slate-700 mb-1">Display Name</label><input type="text" name="display_name" id="pm_name" required class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50"></div>
                            <div><label class="block text-xs font-bold text-slate-700 mb-1">Account Details <span class="text-slate-400 font-normal">(number, account name, etc.)</span></label><textarea name="account_details" id="pm_details" rows="3" class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50"></textarea></div>
                            <div><label class="block text-xs font-bold text-slate-700 mb-1">Instructions for Agencies</label><textarea name="instructions" id="pm_instructions" rows="3" class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50"></textarea></div>
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" id="pm_active" class="w-4 h-4"> Visible to agencies on the Renew page</label>
                            <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 shadow-lg transition">Save Payment Method</button>
                        </form>
                    </div>
                </div>
                <script>
                    function openMethodModal(m) {
                        document.getElementById('methodModalTitle').innerText = 'Edit ' + m.display_name;
                        document.getElementById('pm_key').value = m.method_key;
                        document.getElementById('pm_name').value = m.display_name;
                        document.getElementById('pm_details').value = m.account_details || '';
                        document.getElementById('pm_instructions').value = m.instructions || '';
                        document.getElementById('pm_active').checked = m.is_active == 1;
                        document.getElementById('methodModal').classList.remove('hidden');
                        document.getElementById('methodModal').classList.add('flex');
                    }
                </script>

            <?php elseif ($tab === 'payment_requests'): ?>
                <!-- ---------------------------------------------------- -->
                <!-- AGENCY-SUBMITTED PAYMENT REQUESTS: APPROVE / DECLINE -->
                <!-- ---------------------------------------------------- -->
                <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
                    <div class="p-6 bg-slate-50/50 border-b"><h2 class="font-bold text-slate-800">Subscription Payment Requests</h2></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-white text-slate-500 border-b">
                                <tr><th class="p-4">Agency</th><th class="p-4">Package</th><th class="p-4">Amount</th><th class="p-4">Method</th><th class="p-4">Reference</th><th class="p-4">Proof</th><th class="p-4">Status</th><th class="p-4">Date</th><th class="p-4 text-right">Actions</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($payments as $pay): 
                                    $stBadge = ['Pending' => 'bg-amber-100 text-amber-700', 'Approved' => 'bg-emerald-100 text-emerald-700', 'Declined' => 'bg-rose-100 text-rose-700'][$pay['status']] ?? 'bg-slate-100 text-slate-600';
                                ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-4 font-bold text-slate-800"><?= xss_clean($pay['company_name']) ?></td>
                                        <td class="p-4 text-indigo-600 font-bold"><?= ucfirst($pay['plan_key']) ?></td>
                                        <td class="p-4 font-extrabold text-emerald-600">৳<?= number_format($pay['amount'], 0) ?></td>
                                        <td class="p-4 text-slate-600"><?= xss_clean($pay['method'] ?: '-') ?></td>
                                        <td class="p-4 text-slate-600"><?= xss_clean($pay['reference'] ?: '-') ?></td>
                                        <td class="p-4">
                                            <?php if (!empty($pay['screenshot'])): ?>
                                                <a href="<?= $pay['screenshot'] ?>" target="_blank"><img src="<?= $pay['screenshot'] ?>" class="w-12 h-12 object-cover rounded-lg border border-slate-200 hover:opacity-80 transition"></a>
                                            <?php else: ?>
                                                <span class="text-slate-300 text-xs">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4">
                                            <span class="px-3 py-1 text-xs rounded-full font-bold <?= $stBadge ?>"><?= $pay['status'] ?></span>
                                            <?php if ($pay['status'] === 'Declined' && !empty($pay['decline_reason'])): ?>
                                                <p class="text-[11px] text-rose-500 mt-1 max-w-[160px]"><?= xss_clean($pay['decline_reason']) ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-slate-500"><?= date('d M Y, h:i A', strtotime($pay['created_at'])) ?></td>
                                        <td class="p-4 text-right whitespace-nowrap">
                                            <?php if ($pay['status'] === 'Pending'): ?>
                                                <form method="POST" action="/admin" class="inline">
                                                    <input type="hidden" name="action" value="approve_payment">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                                                    <button type="submit" class="text-xs bg-emerald-500 text-white px-3 py-2 rounded-lg shadow hover:bg-emerald-600 font-bold mr-2">Approve</button>
                                                </form>
                                                <button onclick="openDeclineModal(<?= $pay['id'] ?>)" class="text-xs bg-rose-500 text-white px-3 py-2 rounded-lg shadow hover:bg-rose-600 font-bold">Decline</button>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400"><?= xss_clean($pay['reviewed_by'] ?: '-') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if(empty($payments)): ?>
                                    <tr><td colspan="9" class="p-12 text-center text-slate-400 font-medium">No payment requests yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="declineModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
                        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
                            <h3 class="font-extrabold text-slate-800 text-lg">Decline Payment Request</h3>
                            <button onclick="document.getElementById('declineModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <form method="POST" action="/admin" class="p-6 space-y-4">
                            <input type="hidden" name="action" value="decline_payment">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="payment_id" id="dp_payment_id">
                            <div><label class="block text-xs font-bold text-slate-700 mb-1">Reason (shown to the agency)</label><textarea name="decline_reason" rows="3" placeholder="e.g. Transaction ID not found / amount mismatch" class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50"></textarea></div>
                            <button type="submit" class="w-full bg-rose-600 text-white py-3 rounded-xl font-bold hover:bg-rose-700 shadow-lg transition">Confirm Decline</button>
                        </form>
                    </div>
                </div>
                <script>
                    function openDeclineModal(id) {
                        document.getElementById('dp_payment_id').value = id;
                        document.getElementById('declineModal').classList.remove('hidden');
                        document.getElementById('declineModal').classList.add('flex');
                    }
                </script>

            <?php elseif ($tab === 'settings'): ?>
                <!-- ---------------------------------------------------- -->
                <!-- PLATFORM SETTINGS -->
                <!-- ---------------------------------------------------- -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
                        <h3 class="font-extrabold text-slate-800 text-lg mb-1"><i class="fa-solid fa-envelope-circle-check text-indigo-500 mr-2"></i>Email Verification</h3>
                        <p class="text-sm text-slate-500 mb-5">When on, new agencies must click a link in their email before they can log in. When off, accounts are usable immediately after registration.</p>
                        <form method="POST" action="/admin" class="flex items-center justify-between bg-slate-50 rounded-xl p-4">
                            <input type="hidden" name="action" value="save_platform_settings">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="setting_key" value="email_verification_required">
                            <span class="font-bold text-slate-700">Require Email Verification</span>
                            <button type="submit" name="setting_value" value="<?= $emailVerificationRequired ? '0' : '1' ?>" class="relative w-14 h-8 rounded-full transition <?= $emailVerificationRequired ? 'bg-emerald-500' : 'bg-slate-300' ?>">
                                <span class="absolute top-1 <?= $emailVerificationRequired ? 'right-1' : 'left-1' ?> w-6 h-6 bg-white rounded-full shadow transition"></span>
                            </button>
                        </form>
                    </div>

                    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6">
                        <h3 class="font-extrabold text-slate-800 text-lg mb-1"><i class="fa-solid fa-shield-halved text-indigo-500 mr-2"></i>Two-Factor Authentication</h3>
                        <p class="text-sm text-slate-500 mb-5">When on, Agency Admins get a "Two-Factor Authentication" option on their Profile page to secure their own login. This never affects your own Super Admin 2FA below.</p>
                        <form method="POST" action="/admin" class="flex items-center justify-between bg-slate-50 rounded-xl p-4">
                            <input type="hidden" name="action" value="save_platform_settings">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="setting_key" value="agency_2fa_enabled">
                            <span class="font-bold text-slate-700">Allow 2FA for Agency Users</span>
                            <button type="submit" name="setting_value" value="<?= $agency2faEnabled ? '0' : '1' ?>" class="relative w-14 h-8 rounded-full transition <?= $agency2faEnabled ? 'bg-emerald-500' : 'bg-slate-300' ?>">
                                <span class="absolute top-1 <?= $agency2faEnabled ? 'right-1' : 'left-1' ?> w-6 h-6 bg-white rounded-full shadow transition"></span>
                            </button>
                        </form>
                    </div>

                    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6 lg:col-span-2">
                        <h3 class="font-extrabold text-slate-800 text-lg mb-1"><i class="fa-solid fa-key text-indigo-500 mr-2"></i>Change Password</h3>
                        <p class="text-sm text-slate-500 mb-5">Update the password for your own Super Admin login.</p>
                        <form method="POST" action="/admin" class="max-w-lg space-y-4" onsubmit="return checkSuperAdminNewPass()">
                            <input type="hidden" name="action" value="change_super_admin_password">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Current Password</label>
                                <input type="password" name="current_password" required class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">New Password</label>
                                <input type="password" id="sa_np1" name="new_password" required minlength="8" placeholder="Min. 8 characters" class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Confirm New Password</label>
                                <input type="password" id="sa_np2" name="confirm_password" required class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50">
                                <p id="saNpErr" class="text-xs font-bold hidden mt-1.5 text-rose-500">Passwords do not match.</p>
                            </div>
                            <button type="submit" class="bg-indigo-600 text-white font-bold px-6 py-3 rounded-xl hover:bg-indigo-700 shadow-lg transition"><i class="fa-solid fa-key mr-2"></i>Update Password</button>
                        </form>
                    </div>

                    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6 lg:col-span-2">
                        <h3 class="font-extrabold text-slate-800 text-lg mb-1"><i class="fa-solid fa-user-shield text-indigo-500 mr-2"></i>My Account Security</h3>
                        <p class="text-sm text-slate-500 mb-5">Secure your own Super Admin login with an authenticator app (Google Authenticator, Authy, etc).</p>
                        <?php if ($superAdminUser['totp_enabled']): ?>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-emerald-50 border border-emerald-100 rounded-xl p-4">
                                <p class="font-bold text-emerald-700"><i class="fa-solid fa-circle-check mr-2"></i>Two-Factor Authentication is enabled on your account.</p>
                                <button onclick="document.getElementById('disable2faModal').classList.remove('hidden'); document.getElementById('disable2faModal').classList.add('flex');" class="bg-rose-50 text-rose-600 font-bold px-5 py-2.5 rounded-xl hover:bg-rose-100 transition">Disable 2FA</button>
                            </div>
                        <?php else: ?>
                            <button onclick="start2faSetup()" class="bg-indigo-600 text-white font-bold px-6 py-3 rounded-xl hover:bg-indigo-700 shadow-lg transition"><i class="fa-solid fa-qrcode mr-2"></i>Enable Two-Factor Authentication</button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2FA Setup Modal (Step 1: scan QR -> Step 2: confirm code) -->
                <div id="setup2faModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
                        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
                            <h3 class="font-extrabold text-slate-800 text-lg">Enable Two-Factor Authentication</h3>
                            <button onclick="close2faModal()" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <div class="p-6 space-y-4" id="setup2faBody">
                            <p class="text-sm text-slate-500 text-center">Loading...</p>
                        </div>
                    </div>
                </div>

                <!-- Disable 2FA Modal (requires current password) -->
                <div id="disable2faModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
                        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
                            <h3 class="font-extrabold text-slate-800 text-lg">Disable Two-Factor Authentication</h3>
                            <button onclick="document.getElementById('disable2faModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <form method="POST" action="/admin" class="p-6 space-y-4">
                            <input type="hidden" name="action" value="disable_2fa">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div><label class="block text-xs font-bold text-slate-700 mb-1">Confirm your password</label><input type="password" name="password" required class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50"></div>
                            <button type="submit" class="w-full bg-rose-600 text-white py-3 rounded-xl font-bold hover:bg-rose-700 shadow-lg transition">Disable 2FA</button>
                        </form>
                    </div>
                </div>

                <script>
                    function start2faSetup() {
                        document.getElementById('setup2faModal').classList.remove('hidden');
                        document.getElementById('setup2faModal').classList.add('flex');
                        fetch('/admin?action=begin_2fa_setup')
                            .then(r => r.json())
                            .then(data => {
                                document.getElementById('setup2faBody').innerHTML = `
                                    <p class="text-sm text-slate-500">1. Scan this QR code with your authenticator app.</p>
                                    <img src="${data.qr_url}" class="mx-auto rounded-xl border border-slate-100" width="200" height="200">
                                    <p class="text-xs text-slate-400 text-center">Can't scan? Enter this key manually:<br><span class="font-mono font-bold text-slate-600">${data.secret}</span></p>
                                    <form method="POST" action="/admin" class="space-y-3 pt-3 border-t border-slate-100">
                                        <input type="hidden" name="action" value="confirm_2fa">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <label class="block text-xs font-bold text-slate-700">2. Enter the 6-digit code it shows</label>
                                        <input type="text" name="code" inputmode="numeric" maxlength="6" required class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 text-center text-xl tracking-[0.4em] font-black">
                                        <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 shadow-lg transition">Confirm & Enable</button>
                                    </form>
                                `;
                            });
                    }
                    function close2faModal() {
                        document.getElementById('setup2faModal').classList.add('hidden');
                    }
                    function checkSuperAdminNewPass() {
                        const p1 = document.getElementById('sa_np1').value;
                        const p2 = document.getElementById('sa_np2').value;
                        if (p1 !== p2) {
                            document.getElementById('saNpErr').classList.remove('hidden');
                            return false;
                        }
                        document.getElementById('saNpErr').classList.add('hidden');
                        return true;
                    }
                </script>

            <?php else: ?>
                <!-- ---------------------------------------------------- -->
                <!-- AGENCIES & SUBSCRIPTIONS -->
                <!-- ---------------------------------------------------- -->
                <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
                    <div class="p-6 bg-slate-50/50 border-b"><h2 class="font-bold text-slate-800">Agency Registrations & Subscriptions</h2></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-white text-slate-500 border-b">
                                <tr><th class="p-4">Company</th><th class="p-4">Email</th><th class="p-4">Approval</th><th class="p-4">Plan</th><th class="p-4">Expires</th><th class="p-4 text-right">Actions</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($agencies as $a): 
                                    $sub = subscriptionStatusInfo($a);
                                    if ($sub['expired']) { $subBadge = 'bg-rose-100 text-rose-700'; $subLabel = 'Expired'; }
                                    elseif ($sub['plan'] === 'Trial') { $subBadge = 'bg-amber-100 text-amber-700'; $subLabel = 'Trial'; }
                                    else { $subBadge = 'bg-emerald-100 text-emerald-700'; $subLabel = $sub['plan']; }
                                ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="p-4 font-bold text-slate-800"><?= xss_clean($a['company_name']) ?></td>
                                        <td class="p-4 text-slate-600"><?= xss_clean($a['company_email']) ?></td>
                                        <td class="p-4">
                                            <span class="px-3 py-1 text-xs rounded-full font-medium <?= $a['status'] === 'Active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                                                <?= $a['status'] ?>
                                            </span>
                                        </td>
                                        <td class="p-4">
                                            <span class="px-3 py-1 text-xs rounded-full font-bold <?= $subBadge ?>"><?= $subLabel ?></span>
                                            <?php if(!$sub['expired'] && $sub['days_left'] !== null): ?><span class="block text-[11px] text-slate-400 mt-1"><?= $sub['days_left'] ?> days left</span><?php endif; ?>
                                        </td>
                                        <td class="p-4 text-slate-600"><?= $a['subscription_expires_at'] ? date('d M Y', strtotime($a['subscription_expires_at'])) : '-' ?></td>
                                        <td class="p-4 text-right whitespace-nowrap">
                                            <?php $aSlim = ['id'=>$a['id'],'company_name'=>$a['company_name'],'subscription_plan'=>$a['subscription_plan'],'subscription_amount'=>$a['subscription_amount'],'subscription_expires_at'=>$a['subscription_expires_at'],'subscription_notes'=>$a['subscription_notes']]; ?>
                                            <button onclick='openSubModal(<?= json_encode($aSlim) ?>)' class="text-xs bg-indigo-50 text-indigo-600 px-3 py-2 rounded-lg hover:bg-indigo-100 font-bold mr-2"><i class="fa-solid fa-credit-card mr-1"></i>Subscription</button>
                                            <?php $loginUser = $agencyUsersById[$a['id']] ?? null; ?>
                                            <?php if ($loginUser): ?>
                                                <?php $luSlim = ['id'=>$loginUser['id'],'email'=>$loginUser['email'],'company_name'=>$a['company_name'],'email_verified'=>$loginUser['email_verified']]; ?>
                                                <button onclick='openLoginModal(<?= json_encode($luSlim) ?>)' class="text-xs bg-slate-100 text-slate-600 px-3 py-2 rounded-lg hover:bg-slate-200 font-bold mr-2"><i class="fa-solid fa-user-lock mr-1"></i>Manage Login</button>
                                            <?php endif; ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="admin_action">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="agency_id" value="<?= $a['id'] ?>">
                                                <?php if($a['status'] !== 'Active'): ?>
                                                    <button type="submit" name="status" value="Active" class="text-xs bg-emerald-500 text-white px-3 py-2 rounded-lg shadow hover:bg-emerald-600 font-bold">Approve</button>
                                                <?php else: ?>
                                                    <button type="submit" name="status" value="Suspended" class="text-xs bg-rose-500 text-white px-3 py-2 rounded-lg shadow hover:bg-rose-600 font-bold">Suspend</button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Manage Subscription Modal -->
                <div id="subModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
                        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50 sticky top-0">
                            <h3 class="font-extrabold text-slate-800 text-lg" id="subModalTitle">Manage Subscription</h3>
                            <button onclick="document.getElementById('subModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <form method="POST" action="/admin" class="p-6 space-y-5">
                            <input type="hidden" name="action" value="manage_subscription">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="agency_id" id="sm_agency_id">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Plan</label>
                                    <select name="plan_key" id="sm_plan" onchange="planChanged()" class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50 font-bold">
                                        <?php foreach($plans as $key => $p): ?>
                                            <option value="<?= $key ?>" data-price="<?= $p['price'] ?>" data-days="<?= $p['duration_days'] ?>"><?= xss_clean($p['name']) ?></option>
                                        <?php endforeach; ?>
                                        <option value="custom" data-price="0" data-days="30">Custom</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Amount (BDT)</label>
                                    <input type="number" step="0.01" name="custom_amount" id="sm_amount" class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50 font-bold">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Subscription Expires On</label>
                                <input type="date" name="expires_at" id="sm_expires" required class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50 font-bold">
                                <div class="flex gap-2 mt-2">
                                    <button type="button" onclick="extendDays(30)" class="text-xs bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg font-bold text-slate-600">+30 days</button>
                                    <button type="button" onclick="extendDays(365)" class="text-xs bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg font-bold text-slate-600">+365 days</button>
                                    <button type="button" onclick="extendDays(0)" class="text-xs bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg font-bold text-slate-600">Today</button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Admin Notes / Terms for this Agency</label>
                                <textarea name="admin_notes" id="sm_notes" rows="2" class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50"></textarea>
                            </div>

                            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4">
                                <p class="text-xs font-extrabold text-indigo-800 uppercase tracking-wider mb-3">Optionally Record a Payment</p>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div><label class="block text-[11px] font-bold text-slate-600 mb-1">Amount Paid</label><input type="number" step="0.01" name="payment_amount" class="w-full border border-slate-200 p-2 rounded-lg bg-white text-sm"></div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Method</label>
                                        <select name="payment_method" class="w-full border border-slate-200 p-2 rounded-lg bg-white text-sm">
                                            <option>bKash</option><option>Nagad</option><option>Bank Transfer</option><option>Cash</option><option>Other</option>
                                        </select>
                                    </div>
                                    <div><label class="block text-[11px] font-bold text-slate-600 mb-1">Reference / Txn ID</label><input type="text" name="payment_reference" class="w-full border border-slate-200 p-2 rounded-lg bg-white text-sm"></div>
                                </div>
                                <div class="mt-3"><input type="text" name="payment_note" placeholder="Optional note" class="w-full border border-slate-200 p-2 rounded-lg bg-white text-sm"></div>
                            </div>

                            <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 shadow-lg transition">Save Subscription</button>
                        </form>
                    </div>
                </div>
                <script>
                    function openSubModal(agency) {
                        document.getElementById('subModalTitle').innerText = 'Manage Subscription — ' + agency.company_name;
                        document.getElementById('sm_agency_id').value = agency.id;
                        const planSel = document.getElementById('sm_plan');
                        const planVal = (agency.subscription_plan || 'Trial').toLowerCase();
                        planSel.value = ['trial','monthly','yearly'].includes(planVal) ? planVal : 'custom';
                        document.getElementById('sm_amount').value = agency.subscription_amount || 0;
                        document.getElementById('sm_expires').value = agency.subscription_expires_at ? agency.subscription_expires_at.substring(0,10) : '';
                        document.getElementById('sm_notes').value = agency.subscription_notes || '';
                        document.getElementById('subModal').classList.remove('hidden');
                        document.getElementById('subModal').classList.add('flex');
                    }
                    function planChanged() {
                        const sel = document.getElementById('sm_plan');
                        const opt = sel.options[sel.selectedIndex];
                        document.getElementById('sm_amount').value = opt.dataset.price;
                    }
                    function extendDays(n) {
                        const d = new Date();
                        d.setDate(d.getDate() + n);
                        document.getElementById('sm_expires').value = d.toISOString().substring(0,10);
                    }
                </script>

                <!-- Manage Login Modal (change email / reset password / manually verify) -->
                <div id="loginModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
                        <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50">
                            <h3 class="font-extrabold text-slate-800 text-lg" id="loginModalTitle">Manage Login</h3>
                            <button onclick="document.getElementById('loginModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 bg-slate-200/50 w-8 h-8 rounded-full flex items-center justify-center"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <form method="POST" action="/admin" class="p-6 space-y-4">
                            <input type="hidden" name="action" value="update_agency_login">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="user_id" id="lm_user_id">
                            <div><label class="block text-xs font-bold text-slate-700 mb-1">Login Email</label><input type="email" name="email" id="lm_email" required class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50"></div>
                            <div><label class="block text-xs font-bold text-slate-700 mb-1">New Password <span class="text-slate-400 font-normal">(leave blank to keep current)</span></label><input type="password" name="new_password" placeholder="••••••••" class="w-full border border-slate-200 p-2.5 rounded-lg bg-slate-50"></div>
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="email_verified" id="lm_verified" value="1" class="w-4 h-4"> Email is verified</label>
                            <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 shadow-lg transition">Save Changes</button>
                        </form>
                    </div>
                </div>
                <script>
                    function openLoginModal(u) {
                        document.getElementById('loginModalTitle').innerText = 'Manage Login — ' + u.company_name;
                        document.getElementById('lm_user_id').value = u.id;
                        document.getElementById('lm_email').value = u.email;
                        document.getElementById('lm_verified').checked = u.email_verified == 1;
                        document.getElementById('loginModal').classList.remove('hidden');
                        document.getElementById('loginModal').classList.add('flex');
                    }
                </script>
            <?php endif; ?>
        </main>
    </div>
<?php }
