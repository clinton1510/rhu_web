<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/portal.php';
require_once __DIR__ . '/mailer.php';

/**
 * Fetches the single active Admin account from the database.
 */
function getActiveAdminAccount(?PDO $pdo): ?array
{
  if (!$pdo) return null;
  try {
    $stmt = $pdo->prepare("
      SELECT 
        u.id AS user_id, u.username, u.email, u.password_hash, u.first_name, u.last_name, u.role_id, u.is_active, u.created_at,
        s.id AS staff_id, s.license_number AS admin_code, s.specialization AS designation, s.phone_number, s.date_hired,
        r.name AS role_name 
      FROM users u 
      LEFT JOIN staff s ON s.user_id = u.id 
      LEFT JOIN roles r ON u.role_id = r.id 
      WHERE u.is_active = 1 
      AND (r.name IN ('RHU_ADMIN', 'SUPER_ADMIN', 'ADMIN_STAFF') OR u.role_id = 9)
      ORDER BY u.id ASC 
      LIMIT 1
    ");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    return $admin ?: null;
  } catch (Exception $e) {
    return null;
  }
}

$activeAdmin = getActiveAdminAccount($pdo);
$step = $_GET['step'] ?? 'credentials';
$error = '';
$infoMsg = '';

if (!empty($_SESSION['admin_login_flash'])) {
  $infoMsg = $_SESSION['admin_login_flash'];
  unset($_SESSION['admin_login_flash']);
}

/**
 * Dispatches 2FA MFA code via email.
 */
function sendMfaEmail(string $targetEmail, string $code): bool
{
  $subject = 'RedPulse RHU - Your 2-Factor Authentication (2FA) Code';
  $message = "
    <html>
    <head>
      <title>RedPulse RHU 2-Factor Authentication</title>
      <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px; color: #333; }
        .container { max-width: 500px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; border: 1px solid #e1e8ed; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .header { text-align: center; border-bottom: 2px solid #7c3aed; padding-bottom: 15px; margin-bottom: 20px; }
        .header h2 { color: #5b21b6; margin: 0; }
        .code-box { background: #f3e8ff; border: 2px dashed #7c3aed; padding: 15px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #5b21b6; border-radius: 8px; margin: 25px 0; }
        .footer { font-size: 12px; color: #888; text-align: center; margin-top: 25px; border-top: 1px solid #eee; padding-top: 15px; }
      </style>
    </head>
    <body>
      <div class='container'>
        <div class='header'>
          <h2>RedPulse RHU System</h2>
          <p style='margin:5px 0 0 0; color:#666; font-size:14px;'>Municipal Health Office - Nasugbu, Batangas</p>
        </div>
        <p>Dear Administrator,</p>
        <p>Your two-factor authentication (2FA) verification code for accessing the RHU Administrator Panel is:</p>
        <div class='code-box'>{$code}</div>
        <p>This code is valid for <strong>10 minutes</strong>. If you did not attempt to sign in to the RedPulse RHU System, please secure your account immediately.</p>
        <div class='footer'>
          <p>&copy; " . date('Y') . " Nasugbu Rural Health Unit I. All rights reserved.</p>
          <p>Republic Act 10173 - Data Privacy Act Compliant</p>
        </div>
      </div>
    </body>
    </html>
  ";

  $res = sendRHUEmail($targetEmail, $subject, $message);
  return (bool) ($res['success'] ?? false);
}

// Resend 2FA Action
if (isset($_GET['action']) && $_GET['action'] === 'resend' && !empty($_SESSION['pending_admin_user'])) {
  $targetEmail = $_SESSION['rhu_admin_email'] ?? '';
  if ($targetEmail) {
    $newCode = sprintf('%06d', mt_rand(100000, 999999));
    $_SESSION['mfa_otp'] = $newCode;
    $_SESSION['mfa_otp_expires'] = time() + 600;
    sendMfaEmail($targetEmail, $newCode);
    $_SESSION['mfa_flash_info'] = "A new 2FA verification code has been emailed to {$targetEmail}.";
  }
  header('Location: RHUAdminLogin.php?step=mfa');
  exit;
}

if (!empty($_SESSION['mfa_flash_info'])) {
  $infoMsg = $_SESSION['mfa_flash_info'];
  unset($_SESSION['mfa_flash_info']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $actionStep = $_POST['step'] ?? '';

  if ($actionStep === 'credentials') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
      $error = 'Please enter your admin email address and password.';
    } elseif (empty($pdo)) {
      $error = 'Database connection failed. Please ensure XAMPP MySQL is running.';
    } elseif (!$activeAdmin) {
      $error = 'No active administrator account exists in the database. Registration is required.';
    } else {
      // 1. STRICT UNIQUE ACCOUNT CHECK: Email MUST match the unique registered admin email in DB!
      if (strtolower($email) !== strtolower($activeAdmin['email'])) {
        $error = 'Access denied. Only the registered System Administrator account is authorized to sign in.';
      } elseif (!password_verify($password, $activeAdmin['password_hash'])) {
        $error = 'Incorrect password for the System Administrator account.';
      } else {
        // Valid Unique Admin Credentials
        $_SESSION['pending_admin_user'] = $activeAdmin;
        $_SESSION['rhu_admin_email'] = $activeAdmin['email'];

        // Generate unique 6-digit MFA OTP Code
        $otpCode = sprintf('%06d', mt_rand(100000, 999999));
        $_SESSION['mfa_otp'] = $otpCode;
        $_SESSION['mfa_otp_email'] = $activeAdmin['email'];
        $_SESSION['mfa_otp_expires'] = time() + 600; // 10 mins

        // Dispatch 2FA code strictly to the registered admin's email
        sendMfaEmail($activeAdmin['email'], $otpCode);

        $_SESSION['mfa_flash_info'] = "A 6-digit 2FA verification code has been sent to your registered email address ({$activeAdmin['email']}).";
        header('Location: RHUAdminLogin.php?step=mfa');
        exit;
      }
    }
  } elseif ($actionStep === 'mfa') {
    $mfaCode = trim($_POST['mfaCode'] ?? '');
    $storedOtp = $_SESSION['mfa_otp'] ?? '';
    $otpExpires = $_SESSION['mfa_otp_expires'] ?? 0;

    if (strlen($mfaCode) !== 6 || !ctype_digit($mfaCode)) {
      $error = 'Please enter a valid 6-digit numeric 2FA verification code.';
      $step = 'mfa';
    } elseif ($otpExpires > 0 && time() > $otpExpires) {
      $error = 'Your 2FA code has expired. Please click "Resend 2FA Code" to receive a new code.';
      $step = 'mfa';
    } elseif ($storedOtp === '' || $mfaCode !== $storedOtp) {
      // 2. STRICT 2FA CODE VALIDATION: Will NOT accept unless it is the exact code sent to the email!
      $error = 'Incorrect 2FA code. Access will not be granted unless you enter the exact code sent to your email address.';
      $step = 'mfa';
    } else {
      $user = $_SESSION['pending_admin_user'] ?? null;
      $_SESSION['rhu_admin_authenticated'] = true;
      if ($user) {
        $_SESSION['user'] = $user;
      }
      unset($_SESSION['pending_admin_user'], $_SESSION['mfa_otp'], $_SESSION['mfa_otp_expires']);

      if (!empty($pdo) && !empty($user['user_id'])) {
        try {
          $upd = $pdo->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id');
          $upd->execute(['id' => $user['user_id']]);
          portalAudit($pdo, (int)$user['user_id'], 'RHU Admin Login (Strict 2FA Verified)', 'users', (int)$user['user_id']);
        } catch (Exception $e) {
          error_log('RHUAdminLogin audit error: ' . $e->getMessage());
        }
      }

      header('Location: RHUAdminDashboard.php');
      exit;
    }
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RHU Administrator Login - RedPulse RHU</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-900 via-purple-950 to-slate-900 px-4 py-8 text-white">
  <main class="w-full max-w-md">
    <header class="mb-8 text-center">
      <a href="LandingPage.php" class="inline-flex items-center gap-2">
        <span class="text-4xl text-red-500">♥</span>
        <span class="text-3xl font-bold">RedPulse RHU</span>
      </a>
      <p class="mt-5 inline-block rounded-full border border-purple-700 bg-purple-900/60 px-4 py-1.5 text-sm font-bold text-purple-200">♢ MHO / Administrator Access</p>
      <h1 class="mt-3 text-2xl font-bold">Municipal Health Officer</h1>
      <p class="text-sm text-white/70">&amp; RHU System Administrator</p>
      <p class="text-xs text-slate-400">Nasugbu Rural Health Unit I</p>
    </header>

    <section class="rounded-2xl border border-white/10 bg-white/5 p-8 backdrop-blur-sm shadow-2xl">
      <?php if ($error): ?>
        <div class="mb-5 rounded-lg border border-red-500/30 bg-red-500/10 p-3.5 text-sm text-red-300">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if ($infoMsg): ?>
        <div class="mb-5 rounded-lg border border-purple-500/30 bg-purple-500/20 p-3.5 text-xs text-purple-200 flex items-start gap-2.5 leading-relaxed">
          <span class="text-base text-purple-400">✉</span>
          <div><?= htmlspecialchars($infoMsg, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      <?php endif; ?>

      <?php if (!$activeAdmin): ?>
        <!-- NO ACTIVE ADMIN IN DB -> REGISTRATION REQUIRED -->
        <div class="text-center py-4 space-y-4">
          <div class="w-16 h-16 bg-purple-900/50 border border-purple-500/40 rounded-full flex items-center justify-center mx-auto text-purple-300 text-2xl">
            👤
          </div>
          <div>
            <h2 class="text-lg font-bold text-white">No System Administrator Account Found</h2>
            <p class="text-xs text-slate-300 mt-1">There is currently no active System Administrator account registered in the database (or the previous account was deactivated).</p>
          </div>
          <a href="RHUAdminRegister.php" class="inline-block w-full rounded-lg bg-gradient-to-r from-purple-600 to-purple-700 py-3 font-semibold text-sm text-white hover:from-purple-500 hover:to-purple-600 shadow-lg transition-all">
            + Register System Administrator Account
          </a>
        </div>
      <?php else: ?>
        <!-- ACTIVE ADMIN EXISTS -> SHOW LOGIN FORM FOR THE UNIQUE REGISTERED ADMIN -->
        <div class="mb-5 flex items-center gap-2 text-xs">
          <span class="flex h-5 w-5 items-center justify-center rounded-full <?= $step === 'credentials' ? 'bg-purple-600' : 'bg-green-600' ?>">
            <?= $step === 'credentials' ? '1' : '✓' ?>
          </span>
          <span class="<?= $step === 'credentials' ? 'text-slate-300' : 'text-green-400' ?>">
            <?= $step === 'credentials' ? 'Enter admin credentials' : 'Credentials verified' ?>
          </span>
          <i class="mx-2 h-px flex-1 bg-white/10"></i>
          <span class="flex h-5 w-5 items-center justify-center rounded-full <?= $step === 'mfa' ? 'bg-purple-600' : 'bg-white/10 text-slate-500' ?>">2</span>
          <span class="<?= $step === 'mfa' ? 'text-slate-300' : 'text-slate-600' ?>">2FA verification</span>
        </div>

        <?php if ($step === 'credentials'): ?>
          <form method="post" class="space-y-5">
            <input type="hidden" name="step" value="credentials">
            <label class="block text-sm font-semibold text-slate-300">
              Admin Email Address
              <input required name="email" type="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="mt-2 w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-white focus:border-purple-500 focus:outline-none" placeholder="Enter registered admin email">
            </label>
            <label class="block text-sm font-semibold text-slate-300">
              Password
              <input required name="password" type="password" class="mt-2 w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-white focus:border-purple-500 focus:outline-none" placeholder="Enter password">
            </label>
            <button class="w-full rounded-lg bg-gradient-to-r from-purple-600 to-purple-700 py-3 font-semibold text-white hover:from-purple-500 hover:to-purple-600 shadow-lg">Send 2FA Code →</button>
          </form>
        <?php else: ?>
          <form method="post" class="space-y-5">
            <input type="hidden" name="step" value="mfa">
            <div class="rounded-xl border border-purple-700 bg-purple-900/40 p-4 text-center">
              <p class="text-2xl text-purple-400">✉</p>
              <b class="text-sm text-purple-200">Two-Factor Authentication</b>
              <p class="mt-1 text-xs text-slate-300">Enter the 6-digit code sent to your registered email address (<strong><?= htmlspecialchars($_SESSION['rhu_admin_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>).</p>
            </div>

            <label class="block text-sm font-semibold text-slate-300">
              6-Digit 2FA Code
              <input required name="mfaCode" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" class="mt-2 w-full rounded-lg border border-white/10 bg-white/5 px-4 py-4 text-center font-mono text-2xl tracking-[0.5em] text-white focus:border-purple-500 focus:outline-none" placeholder="------" autofocus>
            </label>
            <button class="w-full rounded-lg bg-gradient-to-r from-purple-600 to-purple-700 py-3 font-semibold text-white hover:from-purple-500 hover:to-purple-600 shadow-lg">Verify Code &amp; Sign In</button>

            <div class="flex items-center justify-between text-xs text-slate-400">
              <a href="RHUAdminLogin.php?action=resend" class="text-purple-300 hover:underline">Resend 2FA Code</a>
              <a href="RHUAdminLogin.php" class="hover:underline">← Change Email</a>
            </div>
          </form>
        <?php endif; ?>
      <?php endif; ?>

      <aside class="mt-5 flex gap-2 rounded-lg border border-yellow-500/20 bg-yellow-500/10 p-3 text-xs text-yellow-300">
        <span>⚠</span>Admin access is restricted to authorized DOH/LGU personnel. All sessions are logged and audited per RA 10173.
      </aside>
    </section>

    <footer class="mt-6 text-center text-sm text-slate-500">
      <a href="RHULogin.php" class="hover:text-slate-300">RHU Staff Login</a>
      <span class="mx-2 text-slate-700">|</span>
      <a href="BHWLogin.php" class="hover:text-slate-300">BHW Portal</a>
      <span class="mx-2 text-slate-700">|</span>
      <a href="ResidentLogin.php" class="hover:text-slate-300">Resident Portal</a>
      <br>
      <a href="LandingPage.php" class="mt-2 inline-block text-slate-600 hover:text-slate-400">← Back to Home</a>
    </footer>
  </main>
</body>

</html>