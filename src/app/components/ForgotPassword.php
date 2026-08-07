<?php
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');
@include_once __DIR__ . '/db.php';
@include_once __DIR__ . '/mailer.php';

if (!function_exists('e')) {
    function e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

// Auto-create password_resets database table if it doesn't exist yet
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            portal VARCHAR(50) NOT NULL DEFAULT 'resident',
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $tTable) {
        error_log("Failed creating password_resets table: " . $tTable->getMessage());
    }
}

$step = $_GET['step'] ?? $_POST['step'] ?? 'request';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$portal = trim($_GET['portal'] ?? $_POST['portal'] ?? 'resident');

$error = '';
$success = '';
$emailSent = false;
$validResetToken = false;
$resetAccount = null;

// STEP 1: Handle Request (Email verification link)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid registered email address.';
    } else {
        if (isset($pdo) && $pdo) {
            try {
                // Check where this email belongs (residents, bhw, or users table)
                $foundPortal = null;
                $userDisplayName = 'Valued User';

                // 1. Check residents table
                $rStmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM residents WHERE email = :e LIMIT 1");
                $rStmt->execute(['e' => $email]);
                $rUser = $rStmt->fetch(PDO::FETCH_ASSOC);
                if ($rUser) {
                    $foundPortal = 'resident';
                    $userDisplayName = trim(($rUser['first_name'] ?? '') . ' ' . ($rUser['last_name'] ?? '')) ?: 'Resident';
                } else {
                    // 2. Check bhw table
                    $bStmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM bhw WHERE email = :e LIMIT 1");
                    $bStmt->execute(['e' => $email]);
                    $bUser = $bStmt->fetch(PDO::FETCH_ASSOC);
                    if ($bUser) {
                        $foundPortal = 'bhw';
                        $userDisplayName = trim(($bUser['first_name'] ?? '') . ' ' . ($bUser['last_name'] ?? '')) ?: 'BHW Worker';
                    } else {
                        // 3. Check users table (staff/admin)
                        $uStmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE email = :e LIMIT 1");
                        $uStmt->execute(['e' => $email]);
                        $uUser = $uStmt->fetch(PDO::FETCH_ASSOC);
                        if ($uUser) {
                            $foundPortal = 'staff';
                            $userDisplayName = trim(($uUser['first_name'] ?? '') . ' ' . ($uUser['last_name'] ?? '')) ?: 'Staff Account';
                        }
                    }
                }

                if (!$foundPortal) {
                    $error = 'No account found registered under that email address. Please verify your email.';
                } else {
                    // Generate secure verification token (valid for 1 hour)
                    $newToken = bin2hex(random_bytes(32));

                    // Remove existing tokens for this email
                    $delStmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :e");
                    $delStmt->execute(['e' => $email]);

                    // Save new reset token using MySQL NOW() + INTERVAL 1 HOUR for timezone consistency
                    $insStmt = $pdo->prepare("INSERT INTO password_resets (email, token, portal, expires_at, created_at) VALUES (:e, :t, :p, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())");
                    $insStmt->execute([
                        'e' => $email,
                        't' => $newToken,
                        'p' => $foundPortal
                    ]);

                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    // Clean host to target XAMPP Apache (removing dev ports like 3000/5173 if present)
                    $host = preg_replace('/:(3000|5173|8080)$/', '', $rawHost);
                    $resetUrl = "{$protocol}://{$host}/RHU/rhu_web/src/app/components/ForgotPassword.php?step=reset&token={$newToken}";

                    // Send Email
                    $subject = "Verify Password Reset Request - ResiHUnity RHU";
                    $htmlBody = "
                    <div style='font-family: Arial, sans-serif; padding: 25px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;'>
                        <div style='text-align: center; margin-bottom: 25px;'>
                            <h2 style='color: #0d9488; margin: 0; font-size: 22px;'>ResiHUnity RHU Security</h2>
                            <p style='color: #64748b; font-size: 14px; margin-top: 4px;'>Password Reset Request Verification</p>
                        </div>
                        
                        <p style='font-size: 15px;'>Hello <strong>" . htmlspecialchars($userDisplayName) . "</strong>,</p>
                        <p style='font-size: 14px; color: #334155; line-height: 1.6;'>We received a request to change the password for your ResiHUnity account linked to <strong>" . htmlspecialchars($email) . "</strong>.</p>
                        
                        <div style='background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 18px; border-radius: 12px; margin: 25px 0; text-align: center;'>
                            <p style='margin: 0 0 15px 0; color: #166534; font-weight: bold; font-size: 15px;'>Confirm your request to change your password:</p>
                            <a href='" . htmlspecialchars($resetUrl) . "' style='display: inline-block; background-color: #0d9488; color: #ffffff; padding: 12px 28px; border-radius: 10px; text-decoration: none; font-weight: bold; font-size: 15px; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.25);'>Verify & Change Password →</a>
                        </div>

                        <p style='font-size: 13px; color: #64748b; line-height: 1.5;'>If you did not request a password change, you can safely ignore this email. Your password will remain unchanged.</p>
                        <p style='font-size: 12px; color: #94a3b8; border-t: 1px solid #f1f5f9; padding-top: 15px; margin-top: 25px;'>This verification link is valid for 1 hour. Link: <br><a href='" . htmlspecialchars($resetUrl) . "' style='color: #0d9488;'>" . htmlspecialchars($resetUrl) . "</a></p>
                    </div>";

                    $mailResult = function_exists('sendRHUEmail') ? sendRHUEmail($email, $subject, $htmlBody) : ['success' => false];
                    
                    $emailSent = true;
                    $success = "Verification email sent to {$email}! Please check your inbox and click the verification button to proceed with changing your password.";
                }
            } catch (Exception $ex) {
                $error = 'Database error: ' . $ex->getMessage();
            }
        } else {
            $error = 'Database connection unavailable.';
        }
    }
}

// STEP 2 & 3: Validate Token for Reset Procedure
if ($step === 'reset' || $step === 'update_password') {
    if (!empty($token) && isset($pdo) && $pdo) {
        try {
            $checkStmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :t AND expires_at > NOW() LIMIT 1");
            $checkStmt->execute(['t' => $token]);
            $resetAccount = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($resetAccount) {
                $validResetToken = true;
            } else {
                $error = 'This password reset verification link is invalid or has expired. Please request a new verification link.';
            }
        } catch (Exception $ex) {
            $error = 'Database error validating reset link: ' . $ex->getMessage();
        }
    } else if (empty($token)) {
        $error = 'Missing reset token.';
    }
}

// STEP 3: Handle Password Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'update_password') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$validResetToken || !$resetAccount) {
        $error = 'Invalid reset session. Please request a new password reset link.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Your new password must be at least 6 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match. Please enter matching passwords.';
    } else {
        try {
            $passHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $userEmail = $resetAccount['email'];
            $targetPortal = $resetAccount['portal'];

            // Update in corresponding database table
            if ($targetPortal === 'resident') {
                $up = $pdo->prepare("UPDATE residents SET password_hash = :h WHERE email = :e");
                $up->execute(['h' => $passHash, 'e' => $userEmail]);
            } elseif ($targetPortal === 'bhw') {
                $up = $pdo->prepare("UPDATE bhw SET password_hash = :h WHERE email = :e");
                $up->execute(['h' => $passHash, 'e' => $userEmail]);
            } else {
                $up = $pdo->prepare("UPDATE users SET password_hash = :h WHERE email = :e");
                $up->execute(['h' => $passHash, 'e' => $userEmail]);
            }

            // Invalidate/delete reset token
            $del = $pdo->prepare("DELETE FROM password_resets WHERE token = :t OR email = :e");
            $del->execute(['t' => $token, 'e' => $userEmail]);

            $step = 'completed';
            $success = '🎉 Your password has been successfully updated! You can now log in with your new password.';
        } catch (Exception $ex) {
            $error = 'Failed to update password: ' . $ex->getMessage();
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password | ResiHUnity RHU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../styles/login-theme.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 relative overflow-x-hidden">

    <!-- Background Decoration -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl"></div>
    </div>

    <div class="w-full max-w-md relative z-10">

        <!-- Header Branding -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 p-0.5 shadow-xl shadow-emerald-500/20 mb-3">
                <div class="w-full h-full bg-slate-900 rounded-[14px] flex items-center justify-center">
                    <svg class="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-white">ResiHUnity RHU</h1>
            <p class="text-xs text-slate-400 mt-1 font-medium">Password Recovery Procedure</p>
        </div>

        <!-- Main Card -->
        <div class="bg-slate-900/90 backdrop-blur-xl border border-slate-800 rounded-3xl p-6 md:p-8 shadow-2xl space-y-6">

            <!-- Global Alerts -->
            <?php if ($error): ?>
                <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs font-medium flex items-start gap-3">
                    <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success && $step !== 'completed'): ?>
                <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs font-medium flex items-start gap-3 leading-relaxed">
                    <svg class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span><?= e($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- STATE 1: REQUEST VERIFICATION LINK -->
            <?php if ($step === 'request' && !$emailSent): ?>
                <div>
                    <h2 class="text-lg font-bold text-white mb-1">Forgot Password?</h2>
                    <p class="text-xs text-slate-400 leading-relaxed mb-5">
                        Enter your registered email address below. We will send you an email with a verification link to confirm your password change request.
                    </p>

                    <form method="post" action="ForgotPassword.php" class="space-y-4">
                        <input type="hidden" name="step" value="request">
                        
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Registered Email</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </span>
                                <input required type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="user@nasugbu.rhu.gov.ph" class="w-full rounded-xl border border-slate-700 bg-slate-800 text-white placeholder-slate-400 pl-10 pr-4 py-3 text-sm font-medium focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all">
                            </div>
                        </div>

                        <button type="submit" class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-bold text-sm shadow-lg shadow-emerald-500/25 transition-all flex items-center justify-center gap-2">
                            <span>Send Verification Email</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l7-7m-7 7H3"/></svg>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- STATE 1 CONFIRMED: EMAIL SENT -->
            <?php if ($emailSent): ?>
                <div class="text-center space-y-4 py-2">
                    <div class="w-14 h-14 bg-emerald-500/10 border border-emerald-500/20 rounded-full flex items-center justify-center mx-auto text-emerald-400">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <h2 class="text-lg font-bold text-white">Check Your Email</h2>
                    <p class="text-xs text-slate-300 leading-relaxed">
                        We sent a password reset verification link to:<br>
                        <strong class="text-emerald-400 font-mono text-sm"><?= e($_POST['email'] ?? '') ?></strong>
                    </p>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Please open your inbox and click the <strong class="text-white">Verify & Change Password</strong> link inside the email to proceed with changing your password.
                    </p>
                    <div class="pt-2">
                        <a href="ResidentLogin.php" class="inline-block text-xs font-semibold text-emerald-400 hover:text-emerald-300 hover:underline">
                            ← Return to Login Portal
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- STATE 2: CHANGE PASSWORD PROCEDURE FORM -->
            <?php if (($step === 'reset' || $step === 'update_password') && $validResetToken && $resetAccount && $step !== 'completed'): ?>
                <div>
                    <div class="mb-4">
                        <h2 class="text-lg font-bold text-white mb-1">Create New Password</h2>
                        <p class="text-xs text-slate-400">
                            Verified account: <strong class="text-emerald-400 font-mono"><?= e($resetAccount['email']) ?></strong>
                        </p>
                    </div>

                    <form method="post" action="ForgotPassword.php" class="space-y-4">
                        <input type="hidden" name="step" value="update_password">
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <!-- New Password -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">New Password</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </span>
                                <input id="newPwd" required type="password" name="new_password" minlength="6" placeholder="Enter at least 6 characters" class="w-full rounded-xl border border-slate-700 bg-slate-800 text-white placeholder-slate-400 pl-10 pr-12 py-3 text-sm font-medium focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all">
                                <button type="button" id="toggleNewPwd" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs font-bold text-slate-400 hover:text-emerald-300 transition-colors">
                                    Show
                                </button>
                            </div>
                        </div>

                        <!-- Confirm New Password -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider">Confirm New Password</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </span>
                                <input id="confirmPwd" required type="password" name="confirm_password" minlength="6" placeholder="Re-enter your new password" class="w-full rounded-xl border border-slate-700 bg-slate-800 text-white placeholder-slate-400 pl-10 pr-12 py-3 text-sm font-medium focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all">
                                <button type="button" id="toggleConfirmPwd" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs font-bold text-slate-400 hover:text-emerald-300 transition-colors">
                                    Show
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-bold text-sm shadow-lg shadow-emerald-500/25 transition-all flex items-center justify-center gap-2">
                            <span>Save New Password</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- STATE 3: COMPLETED SUCCESS -->
            <?php if ($step === 'completed'): ?>
                <div class="text-center space-y-4 py-2">
                    <div class="w-16 h-16 bg-emerald-500/10 border border-emerald-500/20 rounded-full flex items-center justify-center mx-auto text-emerald-400">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h2 class="text-xl font-extrabold text-white">Password Changed!</h2>
                    <p class="text-xs text-slate-300 leading-relaxed">
                        <?= e($success) ?>
                    </p>

                    <div class="pt-4 grid grid-cols-1 gap-2.5">
                        <a href="ResidentLogin.php" class="w-full py-3 px-4 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs transition-colors block text-center shadow-lg shadow-emerald-500/20">
                            Sign In to Resident Portal
                        </a>
                        <a href="BHWLogin.php" class="w-full py-3 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs border border-slate-700 transition-colors block text-center">
                            Sign In to BHW Portal
                        </a>
                        <a href="RHULogin.php" class="w-full py-3 px-4 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs border border-slate-700 transition-colors block text-center">
                            Sign In to RHU Staff Portal
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Footer links -->
            <?php if ($step !== 'completed'): ?>
                <div class="pt-4 border-t border-slate-800 text-center">
                    <a href="ResidentLogin.php" class="text-xs font-semibold text-slate-400 hover:text-white transition-colors">
                        ← Back to Resident Login
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        function setupToggle(inputId, buttonId) {
            const input = document.getElementById(inputId);
            const btn = document.getElementById(buttonId);
            if (input && btn) {
                btn.addEventListener('click', function () {
                    const isPwd = input.type === 'password';
                    input.type = isPwd ? 'text' : 'password';
                    btn.textContent = isPwd ? 'Hide' : 'Show';
                });
            }
        }
        setupToggle('newPwd', 'toggleNewPwd');
        setupToggle('confirmPwd', 'toggleConfirmPwd');
    </script>
</body>
</html>
