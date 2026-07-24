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
                // check existing by email
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $stmt->execute(['email' => $email]);
                if ($stmt->fetch()) {
                  throw new Exception('An account with that email already exists.');
                }

                // determine role_id for residents; create if missing
                $roleId = $pdo->query("SELECT id FROM roles WHERE UPPER(name) = 'RESIDENT' LIMIT 1")->fetchColumn();
                if (!$roleId) {
                  $ins = $pdo->prepare('INSERT INTO roles (name, description) VALUES (:name, :desc)');
                  $ins->execute(['name' => 'RESIDENT', 'desc' => 'Resident role']);
                  $roleId = (int)$pdo->lastInsertId();
                }

                // insert user (username required by schema)
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $username = preg_replace('/[^a-z0-9._-]/i', '_', strtolower($email));
                $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active, created_at) VALUES (:username, :email, :password_hash, :first_name, :last_name, :role_id, 0, NOW())');
                $stmt->execute(['username' => $username, 'email' => $email, 'password_hash' => $password_hash, 'first_name' => $first_name, 'last_name' => $last_name, 'role_id' => $roleId]);
                $userId = (int)$pdo->lastInsertId();

                // insert resident record (matches schema columns)
                $stmt = $pdo->prepare('INSERT INTO residents (first_name, last_name, date_of_birth, contact_number, email, address, barangay, philhealth_id, created_at) VALUES (:first_name, :last_name, :dob, :phone, :email, :address, :barangay, :philhealth_id, NOW())');
                $stmt->execute(['first_name' => $first_name, 'last_name' => $last_name, 'dob' => $dob ?: null, 'phone' => $phone, 'email' => $email, 'address' => $address, 'barangay' => $barangay, 'philhealth_id' => $philhealth_no]);

                $pdo->commit();
                $_SESSION['resident_registration_flash'] = 'Registration submitted — please check your email for verification.';
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
  <title>Resident Registration - RedPulse</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>body{background:#f8fafc}</style>
</head>
<body>
  <div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md">
      <div class="rounded-2xl border border-gray-100 bg-white p-8 shadow-xl">
        <div class="mb-4 text-center">
          <a href="LandingPage.php" class="inline-flex items-center gap-2 text-gray-700">Resident Registration</span></a>
        </div>
        <p class="text-sm text-gray-500 mb-4">Create your resident account to access RedPulse services.</p>
        <?php if ($flash): ?><div class="mb-3 rounded border border-green-200 bg-green-50 p-3 text-green-700">✓ <?= e($flash) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-3 rounded border border-red-200 bg-red-50 p-3 text-red-700">✕ <?= e($error) ?></div><?php endif; ?>
        <form method="post" class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm">First name *
              <input required name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>" class="mt-2 w-full rounded border px-3 py-2" placeholder="Maria">
            </label>
            <label class="block text-sm">Last name *
              <input required name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>" class="mt-2 w-full rounded border px-3 py-2" placeholder="Dela Cruz">
            </label>
          </div>
          <label class="block text-sm">Email *
            <input required type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" class="mt-2 w-full rounded border px-3 py-2" placeholder="you@example.com">
          </label>
          <label class="block text-sm">Password *
            <div class="relative">
              <input id="pwd" required type="password" name="password" class="mt-2 w-full rounded border px-3 py-2 pr-12" placeholder="Choose a strong password">
              <button type="button" id="togglePwd" class="absolute right-2 top-1/2 -translate-y-1/2 text-sm text-gray-600">Show</button>
            </div>
          </label>
          <label class="block text-sm">Confirm Password *
            <div class="relative">
              <input id="confirm_pwd" required type="password" name="confirm_password" class="mt-2 w-full rounded border px-3 py-2 pr-12" placeholder="Confirm password">
              <button type="button" id="toggleConfirmPwd" class="absolute right-2 top-1/2 -translate-y-1/2 text-sm text-gray-600">Show</button>
            </div>
          </label>
          <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm">Phone
              <input name="phone" value="<?= e($_POST['phone'] ?? '') ?>" class="mt-2 w-full rounded border px-3 py-2" placeholder="0917xxxxxxx">
            </label>
            <label class="block text-sm">Barangay
              <input name="barangay" value="<?= e($_POST['barangay'] ?? '') ?>" class="mt-2 w-full rounded border px-3 py-2" placeholder="Halang">
            </label>
          </div>
          <label class="block text-sm">Address
            <input name="address" value="<?= e($_POST['address'] ?? '') ?>" class="mt-2 w-full rounded border px-3 py-2" placeholder="Street, City">
          </label>
          <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm">PhilHealth No.
              <input name="philhealth_no" value="<?= e($_POST['philhealth_no'] ?? '') ?>" class="mt-2 w-full rounded border px-3 py-2" placeholder="PH-XXXXXXXXX">
            </label>
            <label class="block text-sm">Date of Birth
              <input type="date" name="dob" value="<?= e($_POST['dob'] ?? '') ?>" class="mt-2 w-full rounded border px-3 py-2">
            </label>
          </div>
          <div class="flex items-center justify-between">
            <a href="LandingPage.php" class="text-sm text-gray-600">Cancel</a>
            <button class="rounded bg-emerald-600 text-white px-4 py-2 font-semibold">Create Account</button>
          </div>
        </form>
      </div>
    </div>
  </div>
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
