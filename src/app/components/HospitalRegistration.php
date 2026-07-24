<?php
if (session_status() === PHP_SESSION_NONE) session_start();
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['hospital_registration'] = [
        'hospitalName' => trim($_POST['hospitalName'] ?? ''),
        'licenseNumber' => trim($_POST['licenseNumber'] ?? ''),
        'contactPerson' => trim($_POST['contactPerson'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'emergencyLine' => trim($_POST['emergencyLine'] ?? ''),
        'submittedAt' => date('c'),
    ];
    $_SESSION['hospital_registration_flash'] = 'Your registration has been submitted for verification.';
    header('Location: HospitalRegistration.php');
    exit;
}
$flash = $_SESSION['hospital_registration_flash'] ?? '';
unset($_SESSION['hospital_registration_flash']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hospital Registration - RedPulse</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
  <header class="border-b bg-white">
    <div class="mx-auto max-w-4xl px-4 py-4 sm:px-6 lg:px-8">
      <a href="LandingPage.php" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900">← Back to Home</a>
    </div>
  </header>
  <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <section class="mb-8 text-center">
      <div class="mb-4 flex items-center justify-center gap-2"><span class="text-4xl text-blue-600">▣</span><h1 class="text-3xl font-bold">Hospital Registration</h1></div>
      <p class="text-gray-600">Register your medical facility to access RedPulse donor network</p>
      <p class="mt-2 text-sm text-gray-500">Already registered? <a href="LoginSelection.php" class="font-semibold text-blue-600 hover:text-blue-700">Sign in here</a></p>
    </section>
    <?php if ($flash): ?><div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-green-800">✓ <?= e($flash) ?></div><?php endif; ?>
    <section class="rounded-xl bg-white p-8 shadow-sm">
      <form method="post" class="space-y-6">
        <label class="block text-sm font-medium text-gray-700">Hospital/Medical Facility Name *<input required name="hospitalName" class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-2" placeholder="Philippine General Hospital"></label>
        <label class="block text-sm font-medium text-gray-700">DOH License Number *<input required name="licenseNumber" class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-2" placeholder="DOH-XXXXX-XXXX"></label>
        <div class="grid gap-6 md:grid-cols-2"><label class="block text-sm font-medium text-gray-700">Contact Person *<input required name="contactPerson" class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-2" placeholder="Dr. Juan Dela Cruz"></label><label class="block text-sm font-medium text-gray-700">Email Address *<input required name="email" type="email" class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-2" placeholder="contact@hospital.ph"></label></div>
        <div class="grid gap-6 md:grid-cols-2"><label class="block text-sm font-medium text-gray-700">Office Phone *<input required name="phone" type="tel" class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-2" placeholder="(02) 1234-5678"></label><label class="block text-sm font-medium text-gray-700">Emergency Hotline *<input required name="emergencyLine" type="tel" class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-2" placeholder="911 or local emergency"></label></div>
        <label class="block text-sm font-medium text-gray-700">Complete Address *<input required name="address" class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-2" placeholder="Taft Avenue, Ermita"></label>
        <label class="block text-sm font-medium text-gray-700">City/Municipality *<input required name="city" class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-2" placeholder="Manila"></label>
        <aside class="rounded-lg border border-blue-200 bg-blue-50 p-6 text-sm text-blue-800"><h2 class="mb-3 font-semibold text-blue-900">Hospital Compliance Requirements</h2><div class="space-y-2"><p><b>Valid DOH Accreditation:</b> Must have current Department of Health license and blood bank accreditation.</p><p><b>Data Privacy:</b> Must comply with the Data Privacy Act of 2012 in handling donor information.</p><p><b>Ethical Standards:</b> Must uphold RA 7719 principles of voluntary, non-remunerated blood donation.</p><p><b>Verification:</b> Credentials will be verified by RedPulse admin before approval.</p><p><b>Authorized Personnel Only:</b> Access is limited to licensed medical professionals.</p></div></aside>
        <label class="flex items-start gap-3 text-sm text-gray-700"><input required name="agreeCompliance" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600">I certify that this facility is DOH-accredited and agree to comply with RedPulse policies, RA 7719, and the Data Privacy Act of 2012. *</label>
        <div class="flex justify-between border-t pt-6"><a href="LandingPage.php" class="px-6 py-2 text-gray-600 hover:text-gray-900">Cancel</a><button class="rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white hover:bg-blue-700">Submit for Verification</button></div>
      </form>
    </section>
    <p class="mt-6 text-center text-sm text-gray-600">Your account will be reviewed within 2–3 business days. You’ll receive the verification status via email.</p>
  </main>
</body>
</html>
