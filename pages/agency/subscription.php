<?php ?>
                <!-- ---------------------------------------------------- -->
                <!-- SUBSCRIPTION RENEWAL / PAYMENT PAGE -->
                <!-- ---------------------------------------------------- -->
                <div class="max-w-4xl mx-auto space-y-6">

                    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Current Plan</p>
                            <p class="text-2xl font-black text-slate-800 mt-1"><?= xss_clean($subscription['plan']) ?> <span class="text-sm font-bold <?= $subscription['expired'] ? 'text-rose-500' : 'text-emerald-500' ?>">· <?= $subscription['expired'] ? 'Expired' : 'Active' ?></span></p>
                            <p class="text-sm text-slate-500 mt-1"><?= $subscription['expired'] ? 'Expired on' : 'Expires on' ?> <?= $subscription['expires_at'] ? date('d M Y', strtotime($subscription['expires_at'])) : '-' ?></p>
                        </div>
                    </div>

                    <form method="POST" action="?route=app" enctype="multipart/form-data" class="bg-white rounded-2xl soft-shadow border border-slate-100 p-6 sm:p-8 space-y-8">
                        <input type="hidden" name="action" value="submit_subscription_payment">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="plan_key" id="rp_plan_key" value="monthly" required>
                        <input type="hidden" name="method" id="rp_method" value="">

                        <!-- STEP 1: PACKAGE -->
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-400 uppercase tracking-wider mb-4">1. Select a Package</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach (['monthly', 'yearly'] as $pk):
                                    $p = $subscriptionPlans[$pk] ?? null; if (!$p) continue;
                                ?>
                                    <div onclick="selectPackage('<?= $pk ?>')" id="rp_pkg_<?= $pk ?>" class="package-card cursor-pointer rounded-2xl border-2 p-5 transition <?= $pk === 'monthly' ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-indigo-300' ?>">
                                        <div class="flex items-center justify-between mb-2">
                                            <p class="font-extrabold text-slate-800"><?= xss_clean($p['name']) ?></p>
                                            <i class="fa-solid fa-circle-check text-indigo-500 <?= $pk === 'monthly' ? '' : 'opacity-0' ?> check-icon"></i>
                                        </div>
                                        <p class="text-2xl font-black text-indigo-600">৳<?= number_format($p['price'], 0) ?><span class="text-sm font-medium text-slate-400"> / <?= $p['duration_days'] >= 300 ? 'year' : 'month' ?></span></p>
                                        <p class="text-xs text-slate-500 mt-2"><?= xss_clean($p['terms']) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- STEP 2: PAYMENT METHOD -->
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-400 uppercase tracking-wider mb-4">2. Choose Payment Method</h3>
                            <div class="flex flex-wrap gap-3 mb-4">
                                <?php $first = true; foreach ($paymentMethods as $mk => $m): if (!$m['is_active']) continue; ?>
                                    <button type="button" onclick="selectMethod('<?= $mk ?>')" id="rp_tab_<?= $mk ?>" class="method-tab px-5 py-2.5 rounded-xl font-bold text-sm border-2 transition <?= $first ? 'border-indigo-500 bg-indigo-50 text-indigo-600' : 'border-slate-200 text-slate-500 hover:border-indigo-300' ?>"><?= xss_clean($m['display_name']) ?></button>
                                <?php $first = false; endforeach; ?>
                            </div>
                            <?php $first = true; foreach ($paymentMethods as $mk => $m): if (!$m['is_active']) continue; ?>
                                <div id="rp_details_<?= $mk ?>" class="method-details bg-slate-50 border border-slate-200 rounded-xl p-4 <?= $first ? '' : 'hidden' ?>">
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Account Details</p>
                                    <p class="font-bold text-slate-800 mb-3 whitespace-pre-line"><?= xss_clean($m['account_details']) ?></p>
                                    <p class="text-sm text-slate-500 whitespace-pre-line"><?= xss_clean($m['instructions']) ?></p>
                                </div>
                            <?php $first = false; endforeach; ?>
                            <?php if (empty($paymentMethods)): ?>
                                <p class="text-sm text-slate-400">No payment methods are configured yet. Please contact your Super Admin.</p>
                            <?php endif; ?>
                        </div>

                        <!-- STEP 3: TRANSACTION DETAILS -->
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-400 uppercase tracking-wider mb-4">3. Transaction Details</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-2">Transaction / Reference ID <span class="text-rose-500">*</span></label>
                                    <input type="text" name="reference" required placeholder="e.g. 8N7X2K1P9Q" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-2">Payment Screenshot <span class="text-slate-400 font-normal">(Optional)</span></label>
                                    <input type="file" name="screenshot" accept="image/*" class="w-full border border-slate-200 p-2.5 rounded-xl bg-slate-50 text-sm">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-bold text-slate-700 mb-2">Note <span class="text-slate-400 font-normal">(Optional)</span></label>
                                    <textarea name="note" rows="2" placeholder="Anything else we should know?" class="w-full border border-slate-200 p-3 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none transition"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex justify-end">
                            <button type="submit" class="bg-indigo-600 text-white px-8 py-3.5 rounded-xl font-extrabold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition"><i class="fa-solid fa-paper-plane mr-2"></i>Submit Payment</button>
                        </div>
                    </form>

                    <!-- PAST SUBMISSIONS -->
                    <?php
                        $myPayments = $conn->prepare("SELECT * FROM subscription_payments WHERE agency_id = ? ORDER BY created_at DESC LIMIT 15");
                        $myPayments->execute([$agency_id]);
                        $myPayments = $myPayments->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php if (!empty($myPayments)): ?>
                    <div class="bg-white rounded-2xl soft-shadow border border-slate-100 overflow-hidden">
                        <div class="p-6 bg-slate-50/50 border-b"><h3 class="font-bold text-slate-800">Your Payment Submissions</h3></div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-white text-slate-500 border-b">
                                    <tr><th class="p-4">Date</th><th class="p-4">Package</th><th class="p-4">Amount</th><th class="p-4">Method</th><th class="p-4">Reference</th><th class="p-4">Status</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($myPayments as $mp):
                                        $stBadge = ['Pending' => 'bg-amber-100 text-amber-700', 'Approved' => 'bg-emerald-100 text-emerald-700', 'Declined' => 'bg-rose-100 text-rose-700'][$mp['status']] ?? 'bg-slate-100 text-slate-600';
                                    ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="p-4 text-slate-500"><?= date('d M Y', strtotime($mp['created_at'])) ?></td>
                                            <td class="p-4 font-bold text-slate-800"><?= ucfirst($mp['plan_key']) ?></td>
                                            <td class="p-4 font-bold text-slate-700">৳<?= number_format($mp['amount'], 0) ?></td>
                                            <td class="p-4 text-slate-600"><?= xss_clean($mp['method']) ?></td>
                                            <td class="p-4 text-slate-600"><?= xss_clean($mp['reference']) ?></td>
                                            <td class="p-4">
                                                <span class="px-3 py-1 text-xs rounded-full font-bold <?= $stBadge ?>"><?= $mp['status'] ?></span>
                                                <?php if ($mp['status'] === 'Declined' && !empty($mp['decline_reason'])): ?>
                                                    <p class="text-[11px] text-rose-500 mt-1"><?= xss_clean($mp['decline_reason']) ?></p>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <script>
                    function selectPackage(key) {
                        document.getElementById('rp_plan_key').value = key;
                        document.querySelectorAll('.package-card').forEach(el => {
                            el.classList.remove('border-indigo-500', 'bg-indigo-50');
                            el.classList.add('border-slate-200');
                            el.querySelector('.check-icon').classList.add('opacity-0');
                        });
                        const card = document.getElementById('rp_pkg_' + key);
                        card.classList.remove('border-slate-200');
                        card.classList.add('border-indigo-500', 'bg-indigo-50');
                        card.querySelector('.check-icon').classList.remove('opacity-0');
                    }
                    function selectMethod(key) {
                        document.getElementById('rp_method').value = key;
                        document.querySelectorAll('.method-tab').forEach(el => {
                            el.classList.remove('border-indigo-500', 'bg-indigo-50', 'text-indigo-600');
                            el.classList.add('border-slate-200', 'text-slate-500');
                        });
                        document.querySelectorAll('.method-details').forEach(el => el.classList.add('hidden'));
                        const tab = document.getElementById('rp_tab_' + key);
                        tab.classList.remove('border-slate-200', 'text-slate-500');
                        tab.classList.add('border-indigo-500', 'bg-indigo-50', 'text-indigo-600');
                        document.getElementById('rp_details_' + key).classList.remove('hidden');
                    }
                    // Initialize hidden method field to whichever tab renders active first
                    (function() {
                        const firstTab = document.querySelector('.method-tab');
                        if (firstTab) {
                            const key = firstTab.id.replace('rp_tab_', '');
                            document.getElementById('rp_method').value = key;
                        }
                    })();
                </script>

