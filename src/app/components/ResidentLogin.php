<?php
if (session_status() === PHP_SESSION_NONE) session_start();
@include_once __DIR__ . '/db.php';


function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$error = '';
$flash = $_SESSION['resident_registration_flash'] ?? '';
unset($_SESSION['resident_registration_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($email === '' || $password === '') {
                $error = 'Email and password are required.';
        } else {
                if (isset($pdo) && $pdo) {
                        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, first_name, last_name, role_id FROM users WHERE email = :email LIMIT 1');
                        $stmt->execute(['email' => $email]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($user && password_verify($password, $user['password_hash'])) {
                                // authenticated
                                $_SESSION['user'] = ['id' => (int)$user['id'], 'username' => $user['username'], 'email' => $user['email'], 'first_name' => $user['first_name'], 'last_name' => $user['last_name'], 'role_id' => (int)$user['role_id']];
                                // attach resident record id if present
                                try {
                                    $rstmt = $pdo->prepare('SELECT id FROM residents WHERE email = :email LIMIT 1');
                                    $rstmt->execute(['email' => $email]);
                                    $resId = $rstmt->fetchColumn();
                                    if ($resId) $_SESSION['user']['resident_id'] = (int)$resId;
                                } catch (Exception $e) {
                                    // ignore
                                }
                                header('Location: ResidentDashboard.php');
                                exit;
                            }
                        $error = 'Invalid email or password.';
                } else $error = 'The database is unavailable. Please try again later.';
        }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Resident Portal - Sign In</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{background:#f8fafc}</style>
</head>
<body>
    <div class="min-h-screen flex items-center justify-center py-12 px-4 bg-gray-50">
        <div class="w-full max-w-md">
            <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-xl">
                <div class="mb-4 text-center">
                  <a href="LandingPage.php"><span class="text-2xl">♥</span><span class="text-lg font-bold">RedPulse RHU</span></a>
                </div>
                <h1 class="text-2xl font-bold mb-2">Resident Portal</h1>
                <p class="text-sm text-gray-500 mb-4">Sign in to access your health records</p>
                <?php if ($flash): ?><div class="mb-3 rounded border border-green-200 bg-green-50 p-3 text-green-700">✓ <?= e($flash) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="mb-3 rounded border border-red-200 bg-red-50 p-3 text-red-700">✕ <?= e($error) ?></div><?php endif; ?>
                <form method="post" class="space-y-4">
                    <label class="block text-sm">Email address
                        <input required name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@email.com" class="mt-2 w-full rounded border px-3 py-2">
                    </label>
                    <label class="block text-sm">Password
                        <div class="relative">
                            <input id="pwd" required name="password" type="password" placeholder="Enter your password" class="mt-2 w-full rounded border px-3 py-2 pr-12">
                            <button type="button" id="togglePwd" class="absolute right-2 top-1/2 -translate-y-1/2 text-sm text-gray-600">Show</button>
                        </div>
                    </label>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center text-sm"><input type="checkbox" class="mr-2"> Remember me</label>
                        <a href="ResidentLogin.php?step=forgot" class="text-sm text-blue-600">Forgot password?</a>
                    </div>
                    <div>
                        <button class="w-full rounded bg-emerald-600 text-white py-2 font-semibold">Sign In to Resident Portal</button>
                    </div>
                </form>
                <p class="text-center text-sm text-gray-500 mt-4">Don't have an account? 
                    <a href="ResidentRegistration.php">Create one</a></p>
                <div class="mt-5 space-y-2 text-center">
                    <div class="flex flex-wrap items-center justify-center gap-3 text-sm">
                        <a href="RHULogin.php">RHU Staff</a> 
                        <span class="text-gray-300">|</span>
                       <a href="RHUAdminLogin.php">RHU Admin</a> 
                        <span class="text-gray-300">|</span>
                       <a href="BHWLogin.php">BHW</a> 
                    </div>
                    <a href="LandingPage.php">
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('togglePwd')?.addEventListener('click', function(){
            const p = document.getElementById('pwd');
            if (!p) return;
            if (p.type === 'password') { p.type = 'text'; this.textContent = 'Hide'; } else { p.type = 'password'; this.textContent = 'Show'; }
        });
    </script>
</body>
</html>
