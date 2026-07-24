<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/portal.php';

$showPassword = isset($_POST['toggle_password_bhw']) ? ($_SESSION['bhw_show_password'] = !($_SESSION['bhw_show_password'] ?? false)) : ($_SESSION['bhw_show_password'] ?? false);
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$barangay = trim($_POST['barangay'] ?? '');
$certNumber = trim($_POST['certNumber'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_bhw'])) {
    if (empty($barangay) || empty($certNumber) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
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
            if (!$bhw || !password_verify($password, $bhw['password_hash'])) $error = 'Invalid BHW credentials, barangay, or certificate number.';
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

define('RECAPTCHA_SITE_KEY', 'YOUR_RECAPTCHA_SITE_KEY');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BHW Login - RedPulse RHU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="bg-gradient-to-br from-green-50 via-white to-emerald-50">
    <div class="min-h-screen flex items-center justify-center px-4 py-8">
        <div class="max-w-md w-full">
            <!-- Header -->
            <div class="text-center mb-8">
                <a href="LandingPage.php" class="inline-flex items-center justify-center gap-2 mb-4">
                    <svg class="w-10 h-10 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span class="text-3xl font-bold text-gray-900">RedPulse RHU</span>
                </a>
                <div class="flex items-center justify-center gap-2 mb-1">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM16 12a4 4 0 11-8 0 4 4 0 018 0zm4 4h.01M12 20h.01"></path>
                    </svg>
                    <h1 class="text-2xl font-bold text-gray-900">BHW Portal</h1>
                </div>
                <div class="flex items-center justify-center gap-1 text-gray-500 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span>Nasugbu Rural Health Unit</span>
                </div>
            </div>

            <!-- Login Form -->
            <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-red-700 text-sm"><?= $error ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5">
                    <!-- Barangay -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Assigned Barangay</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            </svg>
                            <select name="barangay" required value="<?= $barangay ?>" class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent appearance-none">
                                <option value="">Select your barangay</option>
                                <?php foreach ($barangays as $b): ?>
                                    <option value="<?= $b ?>" <?= $barangay === $b ? 'selected' : '' ?>><?= $b ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- BHW Cert Number -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">BHW Certification Number</label>
                        <input type="text" name="certNumber" required value="<?= $certNumber ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="BHW-BAT-YYYY-###" />
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <input type="email" name="email" required value="<?= $email ?>" class="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="your.name@nasugbu.gov.ph" />
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <input type="<?= $showPassword ? 'text' : 'password' ?>" name="password" required value="<?= $password ?>" class="w-full pl-11 pr-11 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Enter your password" />
                            <button type="submit" name="toggle_password_bhw" value="1" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <?php if ($showPassword): ?>
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                <?php else: ?>
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-4.803m5.604-1.752A10.05 10.05 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.064 10.064 0 01-5.999 5.999m3.12 3.12l-5.8-5.8m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"></path>
                                    </svg>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>

                    <!-- reCAPTCHA -->
                    <div class="mt-2">
                        <div class="g-recaptcha" data-sitekey="<?= RECAPTCHA_SITE_KEY ?>"></div>
                        <input type="hidden" name="captcha" value="1">
                    </div>

                    <!-- Submit -->
                    <button type="submit" name="login_bhw" value="1" class="w-full bg-gradient-to-r from-green-600 to-green-700 text-white py-3 rounded-lg font-semibold hover:from-green-700 hover:to-green-800 transition-all shadow-lg hover:shadow-xl">
                        Sign In to BHW Portal
                    </button>
                </form>

                <div class="mt-5 p-3 bg-green-50 rounded-lg border border-green-100 flex items-start gap-2">
                    <svg class="w-4 h-4 text-green-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-xs text-green-700">For account registration or password reset, please contact Nasugbu RHU at (043) 416-1234.</p>
                </div>
            </div>

            <!-- Footer Links -->
            <div class="mt-6 text-center space-y-3">
                <div class="flex items-center justify-center gap-4 text-sm">
                    <a href="DonorDashboard.php" class="text-gray-600 hover:text-red-600 font-semibold">Donor Portal</a>
                    <span class="text-gray-300">|</span>
                    <a href="RHULogin.php" class="text-gray-600 hover:text-blue-600 font-semibold">RHU Staff Login</a>
                </div>
                <a href="LandingPage.php" class="inline-block text-sm text-gray-500 hover:text-gray-700">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
