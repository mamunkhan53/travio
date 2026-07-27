<?php
function renderAuthPage($type, $countryCurrencyMap) {
    $isLogin = $type === 'login';
    $detectedCountry = !$isLogin ? (matchSupportedCountry(getVisitorCountryName(), $countryCurrencyMap) ?: 'Bangladesh') : 'Bangladesh';
    $is2faStep = $isLogin && (($_GET['step'] ?? '') === '2fa') && (!empty($_SESSION['pending_2fa_user_id']) || !empty($_SESSION['pending_2fa_staff_id']));
    $unverifiedEmail = $_SESSION['unverified_email'] ?? null;
    unset($_SESSION['unverified_email']);
?>
    <div class="min-h-screen flex bg-white">
        <div class="hidden lg:flex lg:w-1/2 relative bg-slate-900 items-center justify-center overflow-hidden">
            <img src="https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=1200&q=80" class="absolute inset-0 w-full h-full object-cover opacity-40 mix-blend-overlay">
            <div class="absolute inset-0 bg-gradient-to-t from-blue-900/90 via-blue-900/40 to-transparent"></div>
            
            <div class="relative z-10 p-16 text-white max-w-lg mt-32">
                <div class="w-16 h-16 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center text-3xl mb-8 border border-white/20">
                    <i class="fa-solid fa-plane-departure text-white"></i>
                </div>
                <?php if ($isLogin): ?>
                    <h2 class="text-5xl font-extrabold mb-6 leading-tight">Welcome Back.</h2>
                    <p class="text-xl text-blue-100 leading-relaxed font-light">Manage your travel business from anywhere. Track leads, issue tickets, and manage staff operations seamlessly.</p>
                <?php else: ?>
                    <h2 class="text-5xl font-extrabold mb-6 leading-tight">Join the Network.</h2>
                    <p class="text-xl text-blue-100 leading-relaxed font-light">Start managing your travel business today with the most powerful cloud ERP designed exclusively for agencies.</p>
                <?php endif; ?>
                
                <div class="mt-12 bg-white/10 backdrop-blur-md border border-white/20 p-4 rounded-2xl flex items-center gap-4 animate-float">
                    <div class="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center text-xl shadow-lg"><i class="fa-solid fa-chart-line text-white"></i></div>
                    <div>
                        <p class="text-blue-200 text-sm">System Status</p>
                        <p class="font-bold text-lg text-white">All Services Operational</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 lg:p-20 relative bg-slate-50 lg:bg-white">
            <div class="w-full max-w-md bg-white p-8 lg:p-0 rounded-3xl lg:rounded-none shadow-xl lg:shadow-none border border-slate-100 lg:border-none relative z-10">
                <div class="lg:hidden flex items-center justify-center gap-2 mb-10">
                    <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-200">
                        <i class="fa-solid fa-plane-departure"></i>
                    </div>
                    <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight">South Zone</h1>
                </div>

                <div class="mb-10 text-center lg:text-left">
                    <h2 class="text-3xl font-extrabold text-slate-900 mb-2"><?= $isLogin ? 'Sign In to Dashboard' : 'Register Your Agency' ?></h2>
                    <p class="text-slate-500"><?= $isLogin ? 'Enter your details to access your account.' : 'Fill in the form below to create your account.' ?></p>
                </div>

                <?php if ($is2faStep): ?>
                    <form action="?route=login" method="POST" class="space-y-5">
                        <input type="hidden" name="action" value="verify_2fa_login">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Authentication Code</label>
                            <p class="text-sm text-slate-500 mb-3">Open your authenticator app and enter the 6-digit code for South Zone ERP.</p>
                            <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autofocus required placeholder="123456" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50 focus:bg-white transition text-center text-2xl tracking-[0.5em] font-black">
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-3.5 rounded-xl font-extrabold text-lg hover:bg-blue-700 shadow-xl shadow-blue-200 transition transform hover:-translate-y-0.5 mt-4">Verify & Sign In</button>
                    </form>
                    <div class="mt-8 text-center text-sm font-medium border-t border-slate-100 pt-8">
                        <a href="?route=login" class="text-blue-600 font-extrabold hover:underline">Cancel and start over</a>
                    </div>
                <?php else: ?>

                <?php if ($unverifiedEmail): ?>
                    <div class="mb-6 bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm">
                        <p class="text-amber-800 font-bold mb-2"><i class="fa-solid fa-envelope-circle-check mr-1"></i>Didn't get the verification email?</p>
                        <form method="POST" action="?route=login">
                            <input type="hidden" name="action" value="resend_verification">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="email" value="<?= xss_clean($unverifiedEmail) ?>">
                            <button type="submit" class="text-amber-700 font-extrabold underline">Resend verification link to <?= xss_clean($unverifiedEmail) ?></button>
                        </form>
                    </div>
                <?php endif; ?>

                <form action="?route=<?= $type ?>" method="POST" class="space-y-5" onsubmit="return validateForm()">
                    <input type="hidden" name="action" value="<?= $type ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <?php if (!$isLogin): ?>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Agency Name</label>
                            <input type="text" name="company_name" required placeholder="e.g. Skyline Travels" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-slate-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Full Name</label>
                            <input type="text" name="full_name" required placeholder="Your Name" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50 focus:bg-white transition">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Phone Number</label>
                                <input type="text" name="phone" required placeholder="+880..." class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50 focus:bg-white transition">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Country</label>
                                <select name="country" id="reg_country" onchange="updateCurrencyPreview()" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50 focus:bg-white transition cursor-pointer">
                                    <?php foreach ($countryCurrencyMap as $countryName => $info): ?>
                                        <option value="<?= xss_clean($countryName) ?>" <?= $countryName === $detectedCountry ? 'selected' : '' ?>><?= $info['flag'] ?> <?= xss_clean($countryName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-slate-400 font-bold mt-1.5" id="currencyPreview"></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Email or Username</label>
                        <div class="relative">
                            <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="<?= $isLogin ? 'text' : 'email' ?>" name="email" required placeholder="admin@agency.com" class="w-full pl-11 pr-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50 focus:bg-white transition">
                        </div>
                    </div>

                    <?php if (!$isLogin): ?>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Password</label>
                                <input type="password" id="reg_pass" name="password" required placeholder="••••••••" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50 focus:bg-white transition">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Confirm Password</label>
                                <input type="password" id="reg_confirm" name="confirm_password" required placeholder="••••••••" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50 focus:bg-white transition">
                            </div>
                        </div>
                        <p id="passError" class="text-xs text-rose-500 font-bold hidden mt-1">Passwords do not match.</p>
                    <?php else: ?>
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-sm font-bold text-slate-700">Password</label>
                                <a href="#" class="text-sm font-bold text-blue-600 hover:text-blue-700">Forgot Password?</a>
                            </div>
                            <div class="relative">
                                <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="password" name="password" required placeholder="••••••••" class="w-full pl-11 pr-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-slate-50 focus:bg-white transition">
                            </div>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="remember" class="w-4 h-4 text-blue-600 bg-slate-100 border-slate-300 rounded focus:ring-blue-500 focus:ring-2">
                            <label for="remember" class="ml-2 text-sm font-medium text-slate-600">Remember Me</label>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-3.5 rounded-xl font-extrabold text-lg hover:bg-blue-700 shadow-xl shadow-blue-200 transition transform hover:-translate-y-0.5 mt-4">
                        <?= $isLogin ? 'Login Securely' : 'Create Account' ?>
                    </button>
                </form>

                <div class="mt-8 text-center text-sm font-medium border-t border-slate-100 pt-8">
                    <?php if ($isLogin): ?>
                        Don't have an account? <a href="?route=register" class="text-blue-600 font-extrabold hover:underline">Register Agency</a>
                    <?php else: ?>
                        Already have an account? <a href="?route=login" class="text-blue-600 font-extrabold hover:underline">Sign In Instead</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function validateForm() {
            <?php if (!$isLogin): ?>
            const p1 = document.getElementById('reg_pass').value;
            const p2 = document.getElementById('reg_confirm').value;
            if (p1 !== p2) {
                document.getElementById('passError').classList.remove('hidden');
                return false;
            }
            <?php endif; ?>
            return true;
        }

        <?php if (!$isLogin): ?>
        const COUNTRY_CURRENCY = <?= json_encode($countryCurrencyMap) ?>;
        function updateCurrencyPreview() {
            const country = document.getElementById('reg_country').value;
            const info = COUNTRY_CURRENCY[country];
            const el = document.getElementById('currencyPreview');
            if (info) {
                el.innerHTML = 'Your account currency will be set to <span class="text-slate-600">' + info.code + ' (' + info.symbol + ')</span>.';
            } else {
                el.innerHTML = '';
            }
        }
        document.addEventListener('DOMContentLoaded', updateCurrencyPreview);
        <?php endif; ?>
    </script>
<?php }
