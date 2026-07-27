<?php ?>
                <!-- ---------------------------------------------------- -->
                <!-- PROFILE SETTINGS UI -->
                <!-- ---------------------------------------------------- -->
                <?php if (!$_SESSION['is_staff']): ?>
                <div class="max-w-3xl bg-white p-8 rounded-2xl soft-shadow border border-slate-100 mb-6">
                    <h3 class="text-lg font-extrabold text-slate-800 mb-4"><i class="fa-solid fa-credit-card text-indigo-500 mr-2"></i>Billing & Subscription</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-slate-50 rounded-xl p-4">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Current Plan</p>
                            <p class="text-xl font-black text-slate-800 mt-1"><?= xss_clean($subscription['plan']) ?></p>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Status</p>
                            <p class="text-xl font-black mt-1 <?= $subscription['expired'] ? 'text-rose-600' : 'text-emerald-600' ?>"><?= $subscription['expired'] ? 'Expired' : 'Active' ?></p>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider"><?= $subscription['expired'] ? 'Expired On' : 'Renews / Expires On' ?></p>
                            <p class="text-xl font-black text-slate-800 mt-1"><?= $subscription['expires_at'] ? date('d M Y', strtotime($subscription['expires_at'])) : '-' ?></p>
                        </div>
                    </div>
                    <?php if ($subscription['expired']): ?>
                        <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-rose-50 border border-rose-200 rounded-xl p-4">
                            <p class="text-sm text-rose-600 font-bold"><i class="fa-solid fa-circle-exclamation mr-1"></i>Your subscription has expired - Monthly: ৳<?= number_format($subscriptionPlans['monthly']['price'] ?? 500, 0) ?>/month or Yearly: ৳<?= number_format($subscriptionPlans['yearly']['price'] ?? 3500, 0) ?>/year.</p>
                            <a href="?route=app&page=subscription_payment" class="shrink-0 bg-rose-600 text-white font-bold px-5 py-2.5 rounded-xl shadow hover:bg-rose-700 transition text-center text-sm"><i class="fa-solid fa-arrow-rotate-right mr-2"></i>Renew Now</a>
                        </div>
                    <?php else: ?>
                        <div class="mt-4">
                            <a href="?route=app&page=subscription_payment" class="inline-block bg-indigo-50 text-indigo-600 font-bold px-5 py-2.5 rounded-xl hover:bg-indigo-100 transition text-center text-sm"><i class="fa-solid fa-arrow-rotate-right mr-2"></i>Renew / Upgrade Plan</a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!$_SESSION['is_staff']): ?>
                <div class="max-w-3xl bg-white p-8 rounded-2xl soft-shadow border border-slate-100 mb-6">
                    <h3 class="text-lg font-extrabold text-slate-800 mb-4"><i class="fa-solid fa-earth-asia text-indigo-500 mr-2"></i>Country & Currency</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-xl p-4">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Country</p>
                            <p class="text-xl font-black text-slate-800 mt-1"><?= xss_clean($agency['country'] ?: 'Bangladesh') ?></p>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Operating Currency</p>
                            <p class="text-xl font-black text-slate-800 mt-1"><?= xss_clean($currencyCode) ?> (<?= $currencySymbol ?>)</p>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-4"><i class="fa-solid fa-circle-info mr-1"></i>This currency is used across Sales, Accounting, Invoices and Reports. Contact support if you need it corrected.</p>
                </div>
                <?php endif; ?>

                <?php if ($agency2faEnabled): ?>
                <div class="max-w-3xl bg-white p-8 rounded-2xl soft-shadow border border-slate-100 mb-6">
                    <h3 class="text-lg font-extrabold text-slate-800 mb-1"><i class="fa-solid fa-shield-halved text-indigo-500 mr-2"></i>Two-Factor Authentication</h3>
                    <p class="text-sm text-slate-500 mb-5">Add an extra layer of security using an authenticator app (Google Authenticator, Authy, etc).</p>
                    <?php if ($my2faAccount && $my2faAccount['totp_enabled']): ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-emerald-50 border border-emerald-100 rounded-xl p-4">
                            <p class="font-bold text-emerald-700"><i class="fa-solid fa-circle-check mr-2"></i>Two-Factor Authentication is enabled on your account.</p>
                            <button onclick="document.getElementById('disable2faModal').classList.remove('hidden'); document.getElementById('disable2faModal').classList.add('flex');" class="bg-rose-50 text-rose-600 font-bold px-5 py-2.5 rounded-xl hover:bg-rose-100 transition">Disable 2FA</button>
                        </div>
                    <?php else: ?>
                        <button onclick="start2faSetup()" class="bg-indigo-600 text-white font-bold px-6 py-3 rounded-xl hover:bg-indigo-700 shadow-lg transition"><i class="fa-solid fa-qrcode mr-2"></i>Enable Two-Factor Authentication</button>
                    <?php endif; ?>
                </div>

                <!-- 2FA Setup Modal -->
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
                        <form method="POST" action="?route=app" class="p-6 space-y-4">
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
                        fetch('?route=app&action=begin_2fa_setup')
                            .then(r => r.json())
                            .then(data => {
                                document.getElementById('setup2faBody').innerHTML = `
                                    <p class="text-sm text-slate-500">1. Scan this QR code with your authenticator app.</p>
                                    <img src="${data.qr_url}" class="mx-auto rounded-xl border border-slate-100" width="200" height="200">
                                    <p class="text-xs text-slate-400 text-center">Can't scan? Enter this key manually:<br><span class="font-mono font-bold text-slate-600">${data.secret}</span></p>
                                    <form method="POST" action="?route=app" class="space-y-3 pt-3 border-t border-slate-100">
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
                </script>
                <?php endif; ?>

                <div class="max-w-3xl bg-white p-8 rounded-2xl soft-shadow border border-slate-100">
                    <h3 class="text-2xl font-extrabold text-slate-800 mb-6"><?= $_SESSION['is_staff'] ? 'My Profile' : 'Manage Agency Profile' ?></h3>
                    <form method="POST" action="?route=app" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <?php if (!$_SESSION['is_staff']): ?>
                            <div class="flex flex-col sm:flex-row items-center gap-6 border-b pb-6">
                                <img src="<?= $logoSrc ?>" class="w-32 h-32 rounded-2xl shadow-sm object-cover border border-slate-200 bg-white">
                                <div>
                                    <label class="block text-sm font-bold text-slate-700 mb-2">Company Logo</label>
                                    <input type="file" name="logo" accept="image/*" class="text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition cursor-pointer w-full">
                                    <p class="text-xs text-slate-400 mt-2">Recommended size: 500x500px. JPG, PNG.</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <?php if (!$_SESSION['is_staff']): ?>
                                <div><label class="block text-sm font-bold text-slate-700 mb-2">Company Name</label>
                                <input type="text" name="company_name" value="<?= xss_clean($agency['company_name']) ?>" required class="w-full border border-slate-200 p-3 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white transition"></div>
                                
                                <div><label class="block text-sm font-bold text-slate-700 mb-2">Company Address</label>
                                <input type="text" name="address" value="<?= xss_clean($agency['address'] ?? '') ?>" class="w-full border border-slate-200 p-3 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white transition"></div>
                            <?php endif; ?>
                            
                            <?php 
                                if($_SESSION['is_staff']) {
                                    $u = $conn->query("SELECT * FROM staff WHERE id=".$_SESSION['staff_id'])->fetch();
                                } else {
                                    $u = $conn->query("SELECT * FROM users WHERE id=".$_SESSION['user_id'])->fetch();
                                }
                            ?>
                            
                            <div><label class="block text-sm font-bold text-slate-700 mb-2">Your Name</label>
                            <input type="text" name="full_name" value="<?= xss_clean($u['full_name']) ?>" required class="w-full border border-slate-200 p-3 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white transition"></div>
                            
                            <div><label class="block text-sm font-bold text-slate-700 mb-2">Your Phone</label>
                            <input type="text" name="phone" value="<?= xss_clean($u['phone']) ?>" required class="w-full border border-slate-200 p-3 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white transition"></div>
                            
                            <?php if ($_SESSION['is_staff']): ?>
                                <div class="col-span-1 sm:col-span-2"><label class="block text-sm font-bold text-slate-700 mb-2">Your Email</label>
                                <input type="email" name="email" value="<?= xss_clean($u['email']) ?>" required class="w-full border border-slate-200 p-3 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white transition"></div>
                            <?php endif; ?>

                            <div class="col-span-1 sm:col-span-2"><label class="block text-sm font-bold text-slate-700 mb-2">Change Password <span class="text-slate-400 font-normal">(Leave blank to keep current)</span></label>
                            <input type="password" name="new_password" placeholder="••••••••" class="w-full border border-slate-200 p-3 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none bg-slate-50 focus:bg-white transition"></div>
                        </div>
                        <div class="pt-4 border-t border-slate-100 flex justify-end">
                            <button type="submit" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-indigo-700 shadow-lg transition">Save Profile Changes</button>
                        </div>
                    </form>
                </div>

