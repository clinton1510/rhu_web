<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/portal.php';

if (!function_exists('e')) {
    function e(mixed $v): string {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$barangay = trim($_POST['barangay'] ?? '');
$certNumber = trim($_POST['certNumber'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_bhw'])) {
    if (empty($barangay) || empty($certNumber) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields (Barangay, Cert #, Email, Password)';
    } elseif (empty($pdo)) {
        $error = 'The database is unavailable. Please try again later.';
    } else {
        try {
            $statement = $pdo->prepare(
                'SELECT u.id AS user_id, u.email, u.password_hash, u.first_name, u.last_name, s.id AS staff_id, b.id AS bhw_id, b.barangay
                 FROM users u
                 INNER JOIN staff s ON s.user_id = u.id AND s.staff_type = "BHW" AND s.is_active = 1
                 INNER JOIN bhw b ON b.staff_id = s.id
                 WHERE u.email = :email AND u.is_active = 1 AND b.barangay = :barangay AND s.license_number = :certificate
                 LIMIT 1'
            );
            $statement->execute(['email' => $email, 'barangay' => $barangay, 'certificate' => $certNumber]);
            $bhw = $statement->fetch();
            if (!$bhw || !password_verify($password, $bhw['password_hash'])) $error = 'Invalid BHW credentials, assigned barangay, or certification number.';
            if ($bhw && !$error) {
                $_SESSION['bhw_user'] = ['id' => (int)$bhw['user_id'], 'staff_id' => (int)$bhw['staff_id'], 'bhw_id' => (int)$bhw['bhw_id'], 'email' => $bhw['email'], 'barangay' => $bhw['barangay'], 'name' => trim($bhw['first_name'] . ' ' . $bhw['last_name'])];
                portalAudit($pdo, (int)$bhw['user_id'], 'BHW login', 'staff', (int)$bhw['staff_id']);
                header('Location: BHWDashboard.php');
                exit;
            }
        } catch (PDOException $exception) {
            error_log('BHW login: ' . $exception->getMessage());
            $error = 'Unable to sign in right now. Please try again later.';
        }
    }
}

$barangays = [];
if (!empty($pdo)) {
    try {
        $barangays = $pdo->query('SELECT name FROM barangays ORDER BY name')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $exception) {
        error_log('BHW login barangays: ' . $exception->getMessage());
    }
}
if (empty($barangays)) {
    $barangays = ['Aga','Balaytigue','Banilad','Barangay 1 (Pob.)','Barangay 2 (Pob.)','Barangay 3 (Pob.)','Barangay 4 (Pob.)','Bilaran','Bucana','Bulihan','Calayo','Catandaan','Dayap','Kaylaway','Looc','Lumbangan','Natipuan','Pantalan','Wawa'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Health Worker Portal | ResiHUnity RHU</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['"Plus Jakarta Sans"', 'sans-serif'],
            }
          }
        }
      }
    </script>
</head>
<body class="bg-slate-950 text-white font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-emerald-500 selection:text-white">

    <!-- Ambient Background Glows -->
    <div class="fixed top-0 left-1/4 w-96 h-96 bg-emerald-600/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="fixed bottom-0 right-1/4 w-96 h-96 bg-teal-600/15 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Navigation Header -->
    <header class="p-6 relative z-10">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <a href="LandingPage.php" class="flex items-center gap-3 group">
                <img src="resihunity_logo.jpg" alt="ResiHUnity Logo" class="h-10 w-auto object-contain rounded-xl bg-white/10 p-0.5 shadow-md group-hover:scale-105 transition-transform" />
                <span class="text-xl font-extrabold tracking-tight text-white">ResiHUnity <span class="text-emerald-400">RHU</span></span>
            </a>
            <a href="LandingPage.php" class="text-xs font-semibold text-slate-300 hover:text-white transition-colors flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-900 border border-slate-800 hover:bg-slate-800">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Home
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 flex items-center justify-center p-4 py-6 relative z-10">
        <div class="w-full max-w-md">
            
            <!-- Login Card -->
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 sm:p-8 shadow-2xl space-y-5">
                
                <!-- Card Header -->
                <div class="text-center space-y-1.5">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold uppercase tracking-wider">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM16 12a4 4 0 11-8 0 4 4 0 018 0zm4 4h.01M12 20h.01"/></svg>
                        Barangay Health Worker
                    </span>
                    <h1 class="text-2xl font-extrabold text-white">BHW Portal Sign In</h1>
                    <p class="text-xs text-slate-400">Field Health Monitoring & Community Service Desk</p>
                </div>

                <!-- Error Alert -->
                <?php if ($error): ?>
                    <div class="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs font-medium flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-rose-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="login_bhw" value="1">
                    
                    <!-- Barangay Field -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Assigned Barangay</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </span>
                            <select name="barangay" required class="w-full rounded-xl border border-slate-700 bg-slate-800 text-white pl-10 pr-8 py-3 text-sm font-semibold focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none appearance-none cursor-pointer">
                                <option value="" class="bg-slate-800 text-white">Select your assigned barangay</option>
                                <?php foreach ($barangays as $b): ?>
                                    <option value="<?= htmlspecialchars($b, ENT_QUOTES, 'UTF-8') ?>" class="bg-slate-800 text-white font-medium" <?= $barangay === $b ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($b, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </span>
                        </div>
                    </div>

                    <!-- Certification Number -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">BHW Certification Number</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </span>
                            <input type="text" name="certNumber" required value="<?= htmlspecialchars($certNumber, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-slate-700 bg-slate-800 text-white placeholder-slate-400 pl-10 pr-4 py-3 text-sm font-medium focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all" placeholder="e.g. BHW-BAT-2024-001" />
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Registered Email Address</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </span>
                            <input type="email" name="email" required value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-slate-700 bg-slate-800 text-white placeholder-slate-400 pl-10 pr-4 py-3 text-sm font-medium focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all" placeholder="bhw.name@nasugbu.gov.ph" />
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Password</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </span>
                            <input id="pwd" type="password" name="password" required value="<?= htmlspecialchars($password, ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-slate-700 bg-slate-800 text-white placeholder-slate-400 pl-10 pr-12 py-3 text-sm font-medium focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all" placeholder="Enter BHW password" />
                            <button type="button" id="togglePwd" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs font-bold text-slate-400 hover:text-emerald-300 transition-colors">
                                Show
                            </button>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="pt-2">
                        <button type="submit" class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold text-sm shadow-lg shadow-emerald-600/25 transition-all flex items-center justify-center gap-2">
                            <span>Sign In to BHW Portal</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l7-7m-7 7H3"/></svg>
                        </button>
                    </div>
                </form>

                <!-- Help Alert -->
                <div class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-300 flex items-start gap-2.5 leading-relaxed">
                    <svg class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>For BHW registration or password resets, please contact Nasugbu RHU Main Office at (043) 416-1234.</span>
                </div>

                <!-- Footer Switcher -->
                <div class="pt-1 text-center">
                    <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Other System Portals</span>
                    <div class="flex items-center justify-center gap-3 text-xs text-slate-300">
                        <a href="RHULogin.php" class="hover:text-emerald-400 hover:underline">RHU Staff Login</a>
                        <span class="text-slate-700">•</span>
                        <a href="RHUAdminLogin.php" class="hover:text-emerald-400 hover:underline">Admin Console</a>
                        <span class="text-slate-700">•</span>
                        <a href="ResidentLogin.php" class="hover:text-emerald-400 hover:underline">Resident Portal</a>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="p-4 text-center text-xs text-slate-500 relative z-10">
        <p>© <?= date('Y') ?> Nasugbu Rural Health Unit I. Republic Act 10173 Data Privacy Compliant.</p>
    </footer>

    <script>
        document.getElementById('togglePwd')?.addEventListener('click', function(){
            const p = document.getElementById('pwd');
            if (!p) return;
            if (p.type === 'password') { 
                p.type = 'text'; 
                this.textContent = 'Hide'; 
            } else { 
                p.type = 'password'; 
                this.textContent = 'Show'; 
            }
        });
    </script>
</body>
</html>
