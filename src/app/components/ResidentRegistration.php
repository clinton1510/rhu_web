<?php
if (session_status() === PHP_SESSION_NONE) session_start();
@include_once __DIR__ . '/db.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $philhealth_no = trim($_POST['philhealth_no'] ?? '');
    $dob = trim($_POST['dob'] ?? '');

    if ($first_name === '' || $last_name === '' || $email === '' || $password === '' || $dob === '' || $barangay === '' || $address === '') {
      $error = 'First name, last name, email, password, date of birth, barangay, and address are required.';
    } elseif ($password !== $confirm_password) {
      $error = 'Password and confirmation do not match.';
    } elseif (strlen($password) < 8) {
      $error = 'Password must be at least 8 characters.';
    } else {
        if (isset($pdo) && $pdo) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $stmt->execute(['email' => $email]);
                if ($stmt->fetch()) {
                  throw new Exception('An account with that email address already exists.');
                }

                $roleId = $pdo->query("SELECT id FROM roles WHERE UPPER(name) = 'RESIDENT' LIMIT 1")->fetchColumn();
                if (!$roleId) {
                  $ins = $pdo->prepare('INSERT INTO roles (name, description) VALUES (:name, :desc)');
                  $ins->execute(['name' => 'RESIDENT', 'desc' => 'Resident role']);
                  $roleId = (int)$pdo->lastInsertId();
                }

                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $username = preg_replace('/[^a-z0-9._-]/i', '_', strtolower($email));
                $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active, created_at) VALUES (:username, :email, :password_hash, :first_name, :last_name, :role_id, 1, NOW())');
                $stmt->execute(['username' => $username, 'email' => $email, 'password_hash' => $password_hash, 'first_name' => $first_name, 'last_name' => $last_name, 'role_id' => $roleId]);
                $userId = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare('INSERT INTO residents (first_name, last_name, date_of_birth, contact_number, email, address, barangay, philhealth_id, created_at) VALUES (:first_name, :last_name, :dob, :phone, :email, :address, :barangay, :philhealth_id, NOW())');
                $stmt->execute(['first_name' => $first_name, 'last_name' => $last_name, 'dob' => $dob ?: null, 'phone' => $phone, 'email' => $email, 'address' => $address, 'barangay' => $barangay, 'philhealth_id' => $philhealth_no]);

                $pdo->commit();
                $_SESSION['resident_registration_flash'] = 'Account created successfully! You can now sign in to your Resident Portal.';
                header('Location: ResidentLogin.php');
                exit;
            } catch (Exception $e) {
                if (isset($pdo) && $pdo && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('ResidentRegistration: ' . $e->getMessage());
                $error = $e->getMessage();
            }
        } else $error = 'The database is unavailable. Please try again later.';
    }
}

$flash = $_SESSION['resident_registration_flash'] ?? '';
unset($_SESSION['resident_registration_flash']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Resident Registration | ResiHUnity RHU</title>
  
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
<body class="bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 text-white font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-emerald-500 selection:text-white">

  <!-- Subtle Background Glows -->
  <div class="fixed top-0 left-1/4 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
  <div class="fixed bottom-0 right-1/4 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

  <!-- Navigation Header -->
  <header class="p-6 relative z-10">
      <div class="max-w-7xl mx-auto flex items-center justify-between">
          <a href="LandingPage.php" class="flex items-center gap-3 group">
              <img src="resihunity_logo.jpg" alt="ResiHUnity Logo" class="h-10 w-auto object-contain rounded-xl bg-white/10 p-0.5 shadow-md group-hover:scale-105 transition-transform" />
              <span class="text-xl font-extrabold tracking-tight text-white">ResiHUnity <span class="text-emerald-400">RHU</span></span>
          </a>
          <a href="ResidentLogin.php" class="text-xs font-semibold text-slate-300 hover:text-white transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10">
              Already have an account? Sign In →
          </a>
      </div>
  </header>

  <!-- Main Content -->
  <main class="flex-1 flex items-center justify-center p-4 py-8 relative z-10">
    <div class="w-full max-w-xl">
      
      <!-- Registration Card -->
      <div class="rounded-3xl border border-white/15 bg-white/10 p-6 sm:p-8 backdrop-blur-xl shadow-2xl space-y-6">
        
        <!-- Header -->
        <div class="text-center space-y-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-xs font-bold uppercase tracking-wider">
                Free Resident Access
            </span>
            <h1 class="text-2xl font-extrabold text-white">Register Resident Account</h1>
            <p class="text-xs text-slate-300">Fill in your information to register your RHU healthcare profile</p>
        </div>

        <?php if ($flash): ?>
            <div class="p-3.5 rounded-2xl bg-emerald-500/20 border border-emerald-400/40 text-emerald-200 text-xs font-medium flex items-center gap-2.5">
                <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span><?= e($flash) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="p-3.5 rounded-2xl bg-rose-500/20 border border-rose-400/40 text-rose-200 text-xs font-medium flex items-center gap-2.5">
                <svg class="w-4 h-4 text-rose-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <form method="post" class="space-y-4">
          
          <div class="grid sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">First Name *</label>
              <input required name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>" class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder-slate-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none transition-all" placeholder="e.g. Maria">
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Last Name *</label>
              <input required name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>" class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder-slate-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none transition-all" placeholder="e.g. Santos">
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Email Address *</label>
            <input required type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder-slate-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none transition-all" placeholder="you@example.com">
          </div>

          <div class="grid sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Password *</label>
              <div class="relative">
                <input id="pwd" required type="password" name="password" class="w-full rounded-xl border border-white/15 bg-white/10 pl-4 pr-12 py-3 text-sm text-white placeholder-slate-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none transition-all" placeholder="Min. 8 characters">
                <button type="button" id="togglePwd" class="absolute inset-y-0 right-0 pr-3 flex items-center text-xs font-bold text-slate-400 hover:text-emerald-300 transition-colors">Show</button>
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Confirm Password *</label>
              <div class="relative">
                <input id="confirm_pwd" required type="password" name="confirm_password" class="w-full rounded-xl border border-white/15 bg-white/10 pl-4 pr-12 py-3 text-sm text-white placeholder-slate-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none transition-all" placeholder="Repeat password">
                <button type="button" id="toggleConfirmPwd" class="absolute inset-y-0 right-0 pr-3 flex items-center text-xs font-bold text-slate-400 hover:text-emerald-300 transition-colors">Show</button>
              </div>
            </div>
          </div>

          <div class="grid sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Contact Number</label>
              <input name="phone" value="<?= e($_POST['phone'] ?? '') ?>" class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder-slate-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none transition-all" placeholder="0917XXXXXXX">
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Barangay *</label>
              <input required name="barangay" value="<?= e($_POST['barangay'] ?? '') ?>" class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder-slate-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none transition-all" placeholder="e.g. Halang / Pob. 1">
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Street / House Address *</label>
            <input required name="address" value="<?= e($_POST['address'] ?? '') ?>" class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder-slate-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none transition-all" placeholder="Complete address">
          </div>

          <div class="grid sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">PhilHealth No. (Optional)</label>
              <input name="philhealth_no" value="<?= e($_POST['philhealth_no'] ?? '') ?>" class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white placeholder-slate-400 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none transition-all" placeholder="PH-XXXXXXXXX">
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Date of Birth *</label>
              <input required type="date" name="dob" value="<?= e($_POST['dob'] ?? '') ?>" class="w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm text-white focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 outline-none transition-all bg-slate-800">
            </div>
          </div>

          <div class="pt-4 flex items-center justify-between gap-4">
            <a href="ResidentLogin.php" class="px-5 py-3 rounded-xl border border-white/15 text-slate-300 hover:text-white hover:bg-white/5 font-semibold text-xs transition-all">
              ← Back to Sign In
            </a>
            <button type="submit" class="px-6 py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-bold text-xs shadow-xl shadow-emerald-500/25 transition-all">
              Create Free Account →
            </button>
          </div>
        </form>

      </div>
    </div>
  </main>

  <footer class="p-6 text-center text-xs text-slate-500 relative z-10">
    <p>© <?= date('Y') ?> Nasugbu Rural Health Unit I. Republic Act 10173 Data Privacy Compliant.</p>
  </footer>

  <script>
    document.getElementById('togglePwd')?.addEventListener('click', function(){
      const p = document.getElementById('pwd');
      if (!p) return;
      if (p.type === 'password') { p.type = 'text'; this.textContent = 'Hide'; } else { p.type = 'password'; this.textContent = 'Show'; }
    });
    document.getElementById('toggleConfirmPwd')?.addEventListener('click', function(){
      const p = document.getElementById('confirm_pwd');
      if (!p) return;
      if (p.type === 'password') { p.type = 'text'; this.textContent = 'Hide'; } else { p.type = 'password'; this.textContent = 'Show'; }
    });
  </script>
</body>
</html>
