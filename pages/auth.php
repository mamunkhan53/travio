<?php
// ── Shared auth page styles & layout helpers ──────────────────────────────────
function authStyles() { ?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap');
    #sz-auth {
        --teal:       #2BC4B0;
        --teal-dim:   rgba(43,196,176,0.15);
        --teal-glow:  rgba(43,196,176,0.35);
        --deep:       #03181F;
        --dark:       #0A0C11;
        --surface:    rgba(22,26,35,0.7);
        --border:     rgba(255,255,255,0.08);
        --border-md:  rgba(255,255,255,0.13);
        --text-1:     #EAEDF3;
        --text-2:     #9AA4B2;
        --text-3:     #68727F;
    }
    #sz-auth * { box-sizing: border-box; }
    #sz-auth { font-family: 'Inter', sans-serif; background: var(--dark); min-height: 100vh; color: var(--text-1); }
    #sz-auth .disp { font-family: 'Space Grotesk', sans-serif; }

    /* Input */
    #sz-auth .sz-input {
        width: 100%;
        background: rgba(255,255,255,0.04);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 12px 16px;
        color: var(--text-1);
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    #sz-auth .sz-input::placeholder { color: var(--text-3); }
    #sz-auth .sz-input:focus {
        border-color: var(--teal);
        box-shadow: 0 0 0 3px rgba(43,196,176,0.14);
        background: rgba(255,255,255,0.06);
    }
    #sz-auth .sz-input-icon { position: relative; }
    #sz-auth .sz-input-icon i {
        position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
        color: var(--text-3); font-size: 13px; pointer-events: none;
    }
    #sz-auth .sz-input-icon .sz-input { padding-left: 40px; }

    /* Select */
    #sz-auth select.sz-input { cursor: pointer; }
    #sz-auth select.sz-input option { background: #161A23; color: var(--text-1); }

    /* Button */
    #sz-auth .sz-btn {
        width: 100%;
        background: var(--teal);
        color: #fff;
        border: none;
        border-radius: 12px;
        padding: 13px 20px;
        font-size: 15px;
        font-weight: 700;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        box-shadow: 0 10px 30px -10px var(--teal-glow);
        transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    #sz-auth .sz-btn:hover { background: #1FB8A4; transform: translateY(-2px); box-shadow: 0 16px 36px -10px var(--teal-glow); }
    #sz-auth .sz-btn:active { transform: translateY(0); }
    #sz-auth .sz-btn-ghost {
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--border);
        color: var(--text-2);
        border-radius: 12px;
        padding: 11px 20px;
        font-size: 14px;
        font-weight: 600;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        transition: background 0.2s, border-color 0.2s;
        text-decoration: none;
        display: inline-flex; align-items: center; gap: 6px;
    }
    #sz-auth .sz-btn-ghost:hover { background: rgba(255,255,255,0.09); border-color: var(--border-md); color: var(--text-1); }

    /* Label */
    #sz-auth .sz-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-2);
        margin-bottom: 7px;
        letter-spacing: 0.01em;
    }

    /* Card */
    #sz-auth .sz-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 18px 20px;
        backdrop-filter: blur(12px);
    }

    /* Left panel blobs */
    #sz-auth .blob { filter: blur(70px); pointer-events: none; border-radius: 9999px; position: absolute; }

    /* Floating animation */
    @keyframes sz-fl { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
    #sz-auth .fl  { animation: sz-fl 5s ease-in-out infinite; }
    #sz-auth .fl2 { animation: sz-fl 5s ease-in-out infinite; animation-delay:1.5s; }

    /* Divider */
    #sz-auth .divider {
        display: flex; align-items: center; gap: 12px;
        color: var(--text-3); font-size: 12px;
    }
    #sz-auth .divider::before,
    #sz-auth .divider::after { content:''; flex:1; height:1px; background: var(--border); }

    /* Checkbox */
    #sz-auth input[type=checkbox] { accent-color: var(--teal); width:15px; height:15px; }

    /* Link */
    #sz-auth a.sz-link { color: var(--teal); font-weight: 600; text-decoration: none; }
    #sz-auth a.sz-link:hover { text-decoration: underline; }

    /* OTP input */
    #sz-auth .otp-input {
        text-align:center; letter-spacing: 0.5em; font-size: 1.75rem;
        font-weight: 800; font-family: 'Space Grotesk', sans-serif;
    }

    /* Right panel background */
    #sz-auth .right-panel {
        background: linear-gradient(145deg, #03181F, #0A0C11);
    }

    /* Responsive */
    @media(max-width:1023px) { #sz-auth .left-panel { display:none; } }
</style>
<?php }

// ── Left decorative panel (shared) ───────────────────────────────────────────
function authLeftPanel($heading, $sub) { ?>
<div class="left-panel lg:w-1/2 relative flex flex-col items-start justify-center overflow-hidden p-12 xl:p-20"
     style="background:linear-gradient(160deg,#03181F 60%,#061E25);">
    <!-- Blobs -->
    <div class="blob" style="width:360px;height:360px;background:rgba(43,196,176,0.18);top:-80px;right:-80px;"></div>
    <div class="blob" style="width:260px;height:260px;background:rgba(97,218,251,0.10);bottom:-60px;left:-60px;"></div>

    <!-- Logo -->
    <div class="relative z-10 mb-14">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center"
                 style="background:linear-gradient(135deg,#2BC4B0,#61DAFB);box-shadow:0 8px 24px rgba(43,196,176,0.4);">
                <i class="fa-solid fa-plane-departure text-white text-lg"></i>
            </div>
            <span class="disp text-2xl font-bold text-white">Travio</span>
        </div>
        <span class="text-xs font-semibold" style="color:rgba(43,196,176,0.7);letter-spacing:0.08em;">CLOUD ERP SAAS</span>
    </div>

    <!-- Heading -->
    <div class="relative z-10 mb-12">
        <h2 class="disp text-5xl font-bold text-white leading-tight mb-4"><?= $heading ?></h2>
        <p class="text-lg leading-relaxed max-w-sm" style="color:#9AA4B2;"><?= $sub ?></p>
    </div>

    <!-- Feature list -->
    <div class="relative z-10 space-y-3 mb-12">
        <?php foreach ([
            ['fa-plane-departure','Air Ticket & Visa Processing'],
            ['fa-users-viewfinder','Lead & CRM Management'],
            ['fa-kaaba','Hajj & Umrah Bookings'],
            ['fa-calculator','Finance & Accounting'],
        ] as [$icon,$label]): ?>
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                 style="background:rgba(43,196,176,0.12);">
                <i class="fa-solid <?= $icon ?> text-xs" style="color:#2BC4B0;"></i>
            </div>
            <span class="text-sm font-medium" style="color:#9AA4B2;"><?= $label ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Floating stat card -->
    <div class="relative z-10 sz-card fl flex items-center gap-4 max-w-xs">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0"
             style="background:rgba(63,168,95,0.15);">
            <i class="fa-solid fa-shield-check" style="color:#3FA85F;"></i>
        </div>
        <div>
            <p class="text-xs font-bold uppercase tracking-wider" style="color:#68727F;">System Status</p>
            <p class="text-sm font-bold text-white">All Services Operational</p>
        </div>
    </div>
</div>
<?php }

// ── renderAuthPage (login + register) ────────────────────────────────────────
function renderAuthPage($type, $countryCurrencyMap) {
    $isLogin = $type === 'login';
    $detectedCountry = !$isLogin
        ? (matchSupportedCountry(getVisitorCountryName(), $countryCurrencyMap) ?: 'Bangladesh')
        : 'Bangladesh';
    $is2faStep = $isLogin
        && (($_GET['step'] ?? '') === '2fa')
        && (!empty($_SESSION['pending_2fa_user_id']) || !empty($_SESSION['pending_2fa_staff_id']));
    $unverifiedEmail = $_SESSION['unverified_email'] ?? null;
    unset($_SESSION['unverified_email']);

    authStyles();
    $heading = $isLogin ? 'Welcome<br>Back.' : 'Join the<br>Network.';
    $sub     = $isLogin
        ? 'Manage your travel business from anywhere. Track leads, issue tickets, and manage staff operations seamlessly.'
        : 'Start managing your travel business today with the most powerful cloud ERP designed exclusively for agencies.';
?>
<div id="sz-auth" class="flex min-h-screen">
    <?php authLeftPanel($heading, $sub); ?>

    <!-- Right: Form panel -->
    <div class="right-panel w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10 lg:p-16 min-h-screen">
        <div class="w-full max-w-md">

            <!-- Mobile logo -->
            <div class="flex items-center gap-2 mb-10 lg:hidden">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                     style="background:linear-gradient(135deg,#2BC4B0,#61DAFB);">
                    <i class="fa-solid fa-plane-departure text-white text-sm"></i>
                </div>
                <span class="disp text-xl font-bold text-white">Travio</span>
            </div>

            <!-- Page title -->
            <div class="mb-8">
                <h1 class="disp text-3xl font-bold text-white mb-2">
                    <?= $is2faStep ? 'Two-Factor Auth' : ($isLogin ? 'Sign In' : 'Create Account') ?>
                </h1>
                <p style="color:#9AA4B2;font-size:14px;">
                    <?php if ($is2faStep): ?>
                        Enter the 6-digit code from your authenticator app.
                    <?php elseif ($isLogin): ?>
                        Enter your credentials to access your dashboard.
                    <?php else: ?>
                        Fill in the details below to register your agency.
                    <?php endif; ?>
                </p>
            </div>

            <!-- Unverified email banner -->
            <?php if ($unverifiedEmail): ?>
            <div class="mb-6 rounded-xl p-4 text-sm" style="background:rgba(240,169,59,0.12);border:1px solid rgba(240,169,59,0.3);">
                <p class="font-bold mb-1" style="color:#F0A93B;"><i class="fa-solid fa-envelope-circle-check mr-1"></i>Email not verified</p>
                <form method="POST" action="/login">
                    <input type="hidden" name="action" value="resend_verification">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="email" value="<?= xss_clean($unverifiedEmail) ?>">
                    <button type="submit" class="font-bold underline" style="color:#F0A93B;background:none;border:none;cursor:pointer;padding:0;">
                        Resend verification link to <?= xss_clean($unverifiedEmail) ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- ── 2FA Step ── -->
            <?php if ($is2faStep): ?>
            <form action="/login" method="POST" class="space-y-5">
                <input type="hidden" name="action" value="verify_2fa_login">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div>
                    <label class="sz-label">Authentication Code</label>
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                           autofocus required placeholder="123 456"
                           class="sz-input otp-input">
                </div>
                <button type="submit" class="sz-btn mt-2">
                    <i class="fa-solid fa-lock-open"></i> Verify & Sign In
                </button>
            </form>
            <div class="mt-8 text-center" style="border-top:1px solid rgba(255,255,255,0.06);padding-top:24px;">
                <a href="/login" class="sz-link text-sm">Cancel and start over</a>
            </div>

            <?php else: ?>
            <!-- ── Main Form ── -->
            <form action="/<?= $type ?>" method="POST" class="space-y-4" onsubmit="return validateForm()">
                <input type="hidden" name="action" value="<?= $type ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <?php if (!$isLogin): ?>
                <!-- Agency Name -->
                <div>
                    <label class="sz-label">Agency Name</label>
                    <div class="sz-input-icon">
                        <i class="fa-solid fa-building"></i>
                        <input type="text" name="company_name" required placeholder="e.g. Skyline Travels" class="sz-input">
                    </div>
                </div>
                <!-- Full Name -->
                <div>
                    <label class="sz-label">Your Full Name</label>
                    <div class="sz-input-icon">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="full_name" required placeholder="Your Name" class="sz-input">
                    </div>
                </div>
                <!-- Phone + Country -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="sz-label">Phone Number</label>
                        <div class="sz-input-icon">
                            <i class="fa-solid fa-phone"></i>
                            <input type="text" name="phone" required placeholder="+880..." class="sz-input">
                        </div>
                    </div>
                    <div>
                        <label class="sz-label">Country</label>
                        <select name="country" id="reg_country" onchange="updateCurrencyPreview()" class="sz-input">
                            <?php foreach ($countryCurrencyMap as $cName => $info): ?>
                            <option value="<?= xss_clean($cName) ?>" <?= $cName === $detectedCountry ? 'selected' : '' ?>>
                                <?= $info['flag'] ?> <?= xss_clean($cName) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs mt-1.5" id="currencyPreview" style="color:#68727F;"></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Email -->
                <div>
                    <label class="sz-label"><?= $isLogin ? 'Email or Username' : 'Email Address' ?></label>
                    <div class="sz-input-icon">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="<?= $isLogin ? 'text' : 'email' ?>" name="email" required
                               placeholder="admin@agency.com" class="sz-input">
                    </div>
                </div>

                <?php if (!$isLogin): ?>
                <!-- Passwords side-by-side -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="sz-label">Password</label>
                        <div class="sz-input-icon">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="reg_pass" name="password" required placeholder="••••••••" class="sz-input">
                        </div>
                    </div>
                    <div>
                        <label class="sz-label">Confirm Password</label>
                        <div class="sz-input-icon">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="reg_confirm" name="confirm_password" required placeholder="••••••••" class="sz-input">
                        </div>
                    </div>
                </div>
                <p id="passError" class="text-xs font-bold hidden" style="color:#F87171;margin-top:-8px;">Passwords do not match.</p>
                <?php else: ?>
                <!-- Password with forgot link -->
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="sz-label" style="margin-bottom:0;">Password</label>
                        <a href="/forgot-password" class="sz-link text-xs">Forgot password?</a>
                    </div>
                    <div class="sz-input-icon">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" required placeholder="••••••••" class="sz-input">
                    </div>
                </div>
                <!-- Remember me -->
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember" class="text-sm cursor-pointer" style="color:#9AA4B2;">Remember me</label>
                </div>
                <?php endif; ?>

                <!-- Submit -->
                <button type="submit" class="sz-btn" style="margin-top:8px;">
                    <?php if ($isLogin): ?>
                        <i class="fa-solid fa-right-to-bracket"></i> Sign In Securely
                    <?php else: ?>
                        <i class="fa-solid fa-rocket"></i> Create Account
                    <?php endif; ?>
                </button>
            </form>

            <!-- Switch link -->
            <div class="mt-7 text-center text-sm" style="border-top:1px solid rgba(255,255,255,0.06);padding-top:24px;color:#68727F;">
                <?php if ($isLogin): ?>
                    Don't have an account? <a href="/register" class="sz-link">Register Agency</a>
                <?php else: ?>
                    Already have an account? <a href="/login" class="sz-link">Sign In Instead</a>
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
    const c = document.getElementById('reg_country').value;
    const info = COUNTRY_CURRENCY[c];
    const el = document.getElementById('currencyPreview');
    el.innerHTML = info ? 'Currency: <strong style="color:#9AA4B2;">' + info.code + ' (' + info.symbol + ')</strong>' : '';
}
document.addEventListener('DOMContentLoaded', updateCurrencyPreview);
<?php endif; ?>
</script>
<?php
}

// ── renderForgotPasswordPage ──────────────────────────────────────────────────
function renderForgotPasswordPage() {
    $sent = ($_GET['sent'] ?? '') === '1';
    authStyles();
?>
<div id="sz-auth" class="flex min-h-screen">
    <?php authLeftPanel('Reset Your<br>Password.', 'Enter your registered email address and we\'ll send you a secure link to reset your password.'); ?>

    <div class="right-panel w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10 lg:p-16 min-h-screen">
        <div class="w-full max-w-md">

            <!-- Mobile logo -->
            <div class="flex items-center gap-2 mb-10 lg:hidden">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                     style="background:linear-gradient(135deg,#2BC4B0,#61DAFB);">
                    <i class="fa-solid fa-plane-departure text-white text-sm"></i>
                </div>
                <span class="disp text-xl font-bold text-white">Travio</span>
            </div>

            <?php if ($sent): ?>
            <!-- Success state -->
            <div class="text-center">
                <div class="w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-6"
                     style="background:rgba(43,196,176,0.12);">
                    <i class="fa-solid fa-envelope-circle-check text-4xl" style="color:#2BC4B0;"></i>
                </div>
                <h1 class="disp text-3xl font-bold text-white mb-3">Check Your Email</h1>
                <p class="text-sm mb-8" style="color:#9AA4B2;line-height:1.7;">
                    If that email address is registered with Travio, you'll receive a password reset link shortly.
                    Check your spam folder if you don't see it within a few minutes.
                </p>
                <a href="/login" class="sz-btn" style="text-decoration:none;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Sign In
                </a>
            </div>
            <?php else: ?>
            <!-- Form state -->
            <div class="mb-8">
                <h1 class="disp text-3xl font-bold text-white mb-2">Forgot Password?</h1>
                <p style="color:#9AA4B2;font-size:14px;">Enter your email address to receive a reset link.</p>
            </div>

            <form action="/forgot-password" method="POST" class="space-y-5">
                <input type="hidden" name="action" value="forgot_password">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div>
                    <label class="sz-label">Email Address</label>
                    <div class="sz-input-icon">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" name="email" required autofocus
                               placeholder="admin@agency.com" class="sz-input">
                    </div>
                </div>
                <button type="submit" class="sz-btn">
                    <i class="fa-solid fa-paper-plane"></i> Send Reset Link
                </button>
            </form>

            <div class="mt-7 text-center text-sm" style="border-top:1px solid rgba(255,255,255,0.06);padding-top:24px;color:#68727F;">
                Remember your password? <a href="/login" class="sz-link">Sign In</a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php
}

// ── renderResetPasswordPage ───────────────────────────────────────────────────
function renderResetPasswordPage($conn) {
    $token = trim($_GET['token'] ?? '');
    authStyles();

    // Validate token
    $tokenRow = null;
    $tokenError = '';
    if (!$token) {
        $tokenError = 'No reset token provided.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$token]);
        $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tokenRow) $tokenError = 'This reset link is invalid or has expired (links are valid for 1 hour).';
    }
?>
<div id="sz-auth" class="flex min-h-screen">
    <?php authLeftPanel('Set a New<br>Password.', 'Choose a strong password for your Travio account. You\'ll be signed in right after.'); ?>

    <div class="right-panel w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-10 lg:p-16 min-h-screen">
        <div class="w-full max-w-md">

            <!-- Mobile logo -->
            <div class="flex items-center gap-2 mb-10 lg:hidden">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                     style="background:linear-gradient(135deg,#2BC4B0,#61DAFB);">
                    <i class="fa-solid fa-plane-departure text-white text-sm"></i>
                </div>
                <span class="disp text-xl font-bold text-white">Travio</span>
            </div>

            <?php if ($tokenError): ?>
            <!-- Invalid token -->
            <div class="text-center">
                <div class="w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-6"
                     style="background:rgba(248,113,113,0.12);">
                    <i class="fa-solid fa-link-slash text-4xl" style="color:#F87171;"></i>
                </div>
                <h1 class="disp text-3xl font-bold text-white mb-3">Link Expired</h1>
                <p class="text-sm mb-8" style="color:#9AA4B2;line-height:1.7;"><?= xss_clean($tokenError) ?></p>
                <a href="/forgot-password" class="sz-btn" style="text-decoration:none;">
                    <i class="fa-solid fa-rotate-right"></i> Request a New Link
                </a>
            </div>
            <?php else: ?>
            <!-- Reset form -->
            <div class="mb-8">
                <h1 class="disp text-3xl font-bold text-white mb-2">New Password</h1>
                <p style="color:#9AA4B2;font-size:14px;">
                    Resetting password for <strong style="color:#2BC4B0;"><?= xss_clean($tokenRow['email']) ?></strong>
                </p>
            </div>

            <form action="/reset-password" method="POST" class="space-y-5" onsubmit="return checkNewPass()">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="token" value="<?= xss_clean($token) ?>">
                <div>
                    <label class="sz-label">New Password</label>
                    <div class="sz-input-icon">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="np1" name="password" required minlength="8"
                               placeholder="Min. 8 characters" class="sz-input">
                    </div>
                </div>
                <div>
                    <label class="sz-label">Confirm New Password</label>
                    <div class="sz-input-icon">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="np2" name="confirm_password" required
                               placeholder="Repeat your password" class="sz-input">
                    </div>
                    <p id="npErr" class="text-xs font-bold hidden mt-1.5" style="color:#F87171;">Passwords do not match.</p>
                </div>
                <button type="submit" class="sz-btn">
                    <i class="fa-solid fa-key"></i> Reset Password
                </button>
            </form>
            <?php endif; ?>

        </div>
    </div>
</div>
<script>
function checkNewPass() {
    const p1 = document.getElementById('np1').value;
    const p2 = document.getElementById('np2').value;
    if (p1 !== p2) {
        document.getElementById('npErr').classList.remove('hidden');
        return false;
    }
    return true;
}
</script>
<?php
}
