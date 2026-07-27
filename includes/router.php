<?php
// =========================================================================
// 5. VIEW ENGINE & TEMPLATES
// =========================================================================
ob_start();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>South Zone - Travel Agency SaaS ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .invoice-print { display: none; }
        .soft-shadow { box-shadow: 0 4px 20px -2px rgba(0,0,0,0.05); }
        
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-12px); } 100% { transform: translateY(0px); } }
        .animate-float { animation: float 4s ease-in-out infinite; }
        .animate-float-delayed { animation: float 4s ease-in-out infinite; animation-delay: 2s; }
        .bg-grid-pattern { background-image: radial-gradient(#e5e7eb 1px, transparent 1px); background-size: 20px 20px; }
    </style>
</head>
<body class="text-slate-800 selection:bg-blue-500 selection:text-white">

<?php 
// Show Flash Messages globally
if (isset($_SESSION['flash'])): 
    $f = $_SESSION['flash'];
    $color = $f['type'] === 'error' ? 'bg-rose-500' : 'bg-emerald-500';
?>
    <div id="flash" class="fixed top-4 right-4 <?= $color ?> text-white px-6 py-3 rounded-xl shadow-2xl z-50 font-bold flex items-center gap-2 animate-bounce">
        <i class="fa-solid <?= $f['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
        <?= xss_clean($f['message']) ?>
    </div>
    <script>setTimeout(() => document.getElementById('flash').style.display = 'none', 4000);</script>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<?php
// ROUTER SWITCH
switch ($route) {
    case 'home': renderLandingPage($conn); break;
    case 'login': renderAuthPage('login', $countryCurrencyMap); break;
    case 'register': renderAuthPage('register', $countryCurrencyMap); break;
    case 'verify_email':
        $token = $_GET['token'] ?? '';
        $stmt = $conn->prepare("SELECT * FROM users WHERE email_verification_token = ? AND email_verification_token IS NOT NULL");
        $stmt->execute([$token]);
        $verifyUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($verifyUser) {
            $conn->prepare("UPDATE users SET email_verified = 1, email_verification_token = NULL WHERE id = ?")->execute([$verifyUser['id']]);
            flash("Email verified! You can now log in.");
        } else {
            flash("This verification link is invalid or has already been used.", "error");
        }
        redirect('?route=login');
        break;
    case 'admin_dashboard':
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'Super Admin') redirect('?route=login');
        renderSuperAdmin($conn); break;
    case 'app':
        if (empty($_SESSION['agency_id'])) redirect('?route=login');
        renderAgencyApp($conn, $modules); break;
    default: renderLandingPage($conn);
}
?>
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }
</script>
</body>
</html>
<?php ob_end_flush(); ?>
