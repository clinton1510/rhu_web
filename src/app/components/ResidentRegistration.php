<?php
if (session_status() === PHP_SESSION_NONE) session_start();
@include_once __DIR__ . '/db.php';

if (!function_exists('e')) {
    function e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

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
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        if (isset($pdo) && $pdo) {
            try {
                // Ensure password_hash column exists on residents table
                try {
                    $pdo->exec("ALTER TABLE residents ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
                } catch (Throwable $t) {}

                $pdo->beginTransaction();
                $stmt = $pdo->prepare('SELECT id FROM residents WHERE LOWER(email) = LOWER(:email) LIMIT 1');
                $stmt->execute(['email' => $email]);
                if ($stmt->fetch()) {
                    throw new Exception('A resident account with that email address already exists.');
                }

                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO residents (first_name, last_name, date_of_birth, contact_number, email, password_hash, address, barangay, philhealth_id, is_active, created_at) VALUES (:first_name, :last_name, :dob, :phone, :email, :password_hash, :address, :barangay, :philhealth_id, 1, NOW())');
                $stmt->execute([
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'dob' => $dob ?: null,
                    'phone' => $phone,
                    'email' => $email,
                    'password_hash' => $password_hash,
                    'address' => $address,
                    'barangay' => $barangay,
                    'philhealth_id' => $philhealth_no
                ]);

                // Sync into users table for unified system authentication
                try {
                    $insUser = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active, created_at) VALUES (:u, :e, :h, :f, :l, 1, 1, NOW()) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)");
                    $insUser->execute([
                        'u' => strtolower(preg_replace('/[^a-z0-9]/i', '', explode('@', $email)[0])) . rand(100, 999),
                        'e' => $email,
                        'h' => $password_hash,
                        'f' => $first_name,
                        'l' => $last_name
                    ]);
                } catch (Throwable $tUser) {}

                $pdo->commit();

                // Dispatch Welcome Email
                @include_once __DIR__ . '/mailer.php';
                if (function_exists('sendRHUEmail')) {
                    $welcomeSubject = "Welcome to ResiHUnity RHU Resident Portal!";
                    $welcomeBody = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #cbd5e1; border-radius: 16px; background-color: #ffffff;'>
                            <div style='text-align: center; border-bottom: 2px solid #0f766e; padding-bottom: 16px; margin-bottom: 20px;'>
                                <h2 style='color: #0f766e; margin: 0; font-size: 20px;'>Nasugbu Rural Health Unit I</h2>
                                <p style='color: #64748b; font-size: 13px; margin: 4px 0 0 0;'>Official Resident Portal Registration</p>
                            </div>
                            <p style='font-size: 14px; color: #1e293b;'>Hello <strong>" . htmlspecialchars($first_name . ' ' . $last_name) . "</strong>,</p>
                            <p style='font-size: 14px; color: #334155;'>Your resident account for <strong>" . htmlspecialchars($barangay) . "</strong> has been created successfully!</p>
                            <p style='font-size: 13px; color: #475569;'>You can now log into your Resident Dashboard to request OPD physician consultations, view health records, request health certificates, and access 24/7 emergency services.</p>
                            <p style='text-align: center; margin-top: 24px;'>
                                <a href='http://localhost/RHU/rhu_web/src/app/components/ResidentLogin.php' style='background-color: #0d9488; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 14px; display: inline-block;'>Log In to Resident Portal</a>
                            </p>
                        </div>
                    ";
                    sendRHUEmail($email, $welcomeSubject, $welcomeBody);
                }

                $_SESSION['resident_registration_flash'] = 'Account created successfully! You can now sign in to your Resident Portal.';
                header('Location: ResidentLogin.php');
                exit;
            } catch (Exception $ex) {
                if (isset($pdo) && $pdo && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('ResidentRegistration: ' . $ex->getMessage());
                $error = $ex->getMessage();
            }
        } else {
            $error = 'The database is unavailable. Please try again later.';
        }
    }
}

$flash = $_SESSION['resident_registration_flash'] ?? '';
unset($_SESSION['resident_registration_flash']);

$dbBarangays = [];
if (isset($pdo) && $pdo) {
    try {
        $dbBarangays = $pdo->query("SELECT name FROM barangays ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $eBrgy) {
        error_log('Barangay fetch error: ' . $eBrgy->getMessage());
    }
}
if (empty($dbBarangays)) {
    $dbBarangays = ['Aga','Anilao','Balaytigue','Balibago','Banilad','Barangay 1 (Pob.)','Barangay 2 (Pob.)','Barangay 3 (Pob.)','Barangay 4 (Pob.)','Bilaran','Bucana','Bulihan','Calayo','Catandaan','Cogunan','Dayap','Halang','Kaylaway','Looc','Lumbangan','Mabini','Nagsabaran','Natipuan','Pantalan','Poblacion','Wawa'];
}
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
<body class="bg-slate-50 min-h-screen font-sans flex flex-col justify-between antialiased">
  
  <!-- Navigation Header -->
  <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <a href="index.php" class="flex items-center gap-3 group">
        <div class="w-10 h-10 rounded-xl bg-teal-700 flex items-center justify-center text-white font-extrabold text-lg shadow-md group-hover:bg-teal-800 transition-all">
          R
        </div>
        <div>
          <span class="font-extrabold text-slate-900 text-lg tracking-tight">ResiHUnity</span>
          <span class="text-xs text-teal-700 font-bold block -mt-1">Nasugbu RHU Portal</span>
        </div>
      </a>
      <a href="ResidentLogin.php" class="text-sm font-semibold text-teal-700 hover:text-teal-900 transition-colors">
        Already have an account? Sign in &rarr;
      </a>
    </div>
  </header>

  <!-- Main Registration Section -->
  <main class="max-w-2xl mx-auto w-full px-4 sm:px-6 py-8">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-6 sm:p-10">
      
      <div class="text-center mb-8">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Create Resident Account</h1>
        <p class="text-sm text-slate-500 mt-2">Register to schedule OPD consultations, access medical records, and request health certificates.</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-xs font-semibold text-red-800 flex items-start gap-3">
          <span class="text-red-500 font-bold text-base">⚠️</span>
          <div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($flash)): ?>
        <div class="mb-6 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-xs font-semibold text-emerald-800 flex items-start gap-3">
          <span class="text-emerald-500 font-bold text-base">✅</span>
          <div><?= e($flash) ?></div>
        </div>
      <?php endif; ?>

      <form method="post" action="ResidentRegistration.php" class="space-y-5 text-sm">
        
        <!-- Full Name -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-bold text-slate-700 mb-1">First Name *</label>
            <input required type="text" name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>" placeholder="Juan" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 placeholder-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition-all">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Last Name *</label>
            <input required type="text" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>" placeholder="Dela Cruz" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 placeholder-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition-all">
          </div>
        </div>

        <!-- Contact & Email -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Email Address *</label>
            <input required type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="juan.delacruz@gmail.com" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 placeholder-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition-all">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Mobile / Phone Number</label>
            <input type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="09171234567" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 placeholder-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition-all">
          </div>
        </div>

        <!-- DOB & PhilHealth -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Date of Birth *</label>
            <input required type="date" name="dob" max="<?= date('Y-m-d') ?>" value="<?= e($_POST['dob'] ?? '') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition-all bg-white">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">PhilHealth ID No. (Optional)</label>
            <input type="text" name="philhealth_no" value="<?= e($_POST['philhealth_no'] ?? '') ?>" placeholder="12-345678901-2" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 placeholder-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition-all">
          </div>
        </div>

        <!-- Barangay & Address -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Barangay Location *</label>
            <select required name="barangay" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition-all bg-white">
              <option value="">-- Select Barangay --</option>
              <?php foreach ($dbBarangays as $b): ?>
                <option value="<?= e($b) ?>" <?= (($_POST['barangay'] ?? '') === $b) ? 'selected' : '' ?>><?= e($b) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Complete Address / Sitio *</label>
            <input required type="text" name="address" value="<?= e($_POST['address'] ?? '') ?>" placeholder="House No., Street Name" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 placeholder-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition-all">
          </div>
        </div>

        <!-- Password -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-slate-100 pt-4">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Password *</label>
            <input required type="password" name="password" placeholder="At least 6 characters" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 placeholder-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition-all">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1">Confirm Password *</label>
            <input required type="password" name="confirm_password" placeholder="Re-enter password" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 placeholder-slate-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 outline-none transition-all">
          </div>
        </div>

        <div class="pt-2">
          <button type="submit" class="w-full rounded-xl bg-teal-700 hover:bg-teal-800 text-white font-extrabold py-3.5 px-6 shadow-lg shadow-teal-700/20 transition-all cursor-pointer">
            Create Account &amp; Enter Resident Portal
          </button>
        </div>

      </form>

    </div>
  </main>

  <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
    &copy; <?= date('Y') ?> Nasugbu Rural Health Unit I · Official Resident Portal
  </footer>

</body>
</html>
