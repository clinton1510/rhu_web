<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['bhw_user'])) {
    header('Location: BHWLogin.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/portal.php';
portalHandleNotificationApi($pdo);

function esc($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function dashboardUrl(string $tab = 'overview', array $extra = []): string {
    return '?' . http_build_query(array_merge(['tab' => $tab], $extra));
}

$tabs = ['overview' => ['Overview', '⌂'], 'donors' => ['My Donors', '♟'], 'drives' => ['Blood Drives', '▣'], 'report' => ['Report Need', '➤']];
$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab])) { $tab = 'overview'; }
$modal = $_GET['modal'] ?? '';
$flash = $_SESSION['bhw_flash'] ?? '';
unset($_SESSION['bhw_flash']);

// These are the PHP equivalent of the data consumed by BHWDashboard.tsx.
$bhw = ['name' => 'Gloria Cabrera', 'barangay' => 'Mabini', 'certificationNumber' => 'BHW-NSG-002', 'assignedHouseholds' => 55, 'donorsReferred' => 19];
$rhu = ['name' => 'Nasugbu Rural Health Unit I', 'mho' => 'Dr. Rosalinda V. Castillo', 'contactNumber' => '(043) 416-1234'];
$donors = [
    ['id' => 'D-M01', 'name' => 'Juan Santos', 'bloodType' => 'O+', 'age' => 31, 'gender' => 'Male', 'contactNumber' => '0917 123 4567', 'donationHistory' => 3, 'cluster' => 'Reliable', 'availability' => true, 'verified' => true, 'nextEligibleDate' => '2026-08-14'],
    ['id' => 'D-M02', 'name' => 'Liza Reyes', 'bloodType' => 'A+', 'age' => 27, 'gender' => 'Female', 'contactNumber' => '0918 456 7890', 'donationHistory' => 1, 'cluster' => 'New', 'availability' => true, 'verified' => true, 'nextEligibleDate' => '2026-07-28'],
    ['id' => 'D-M03', 'name' => 'Ramon Cruz', 'bloodType' => 'B+', 'age' => 42, 'gender' => 'Male', 'contactNumber' => '0920 987 6543', 'donationHistory' => 5, 'cluster' => 'Reliable', 'availability' => false, 'verified' => false, 'nextEligibleDate' => '2026-09-02'],
];
if (!empty($_SESSION['bhw_referred_donors'])) { $donors = array_merge($_SESSION['bhw_referred_donors'], $donors); }
$drives = [
    ['id' => 'BD-M01', 'title' => 'Mabini Barangay Blood Drive', 'scheduledDate' => '2026-08-22', 'startTime' => '8:00 AM', 'endTime' => '3:00 PM', 'venue' => 'Mabini Barangay Hall', 'targetDonors' => 35, 'actualDonors' => null, 'status' => 'scheduled', 'notes' => 'Pre-registration is encouraged.', 'bloodTypesNeeded' => ['O+', 'A+', 'B+']],
    ['id' => 'BD-M02', 'title' => 'Mabini Community Blood Drive', 'scheduledDate' => '2026-05-16', 'startTime' => '8:00 AM', 'endTime' => '2:00 PM', 'venue' => 'Mabini Covered Court', 'targetDonors' => 30, 'actualDonors' => 26, 'status' => 'completed', 'notes' => 'All collected units were endorsed to the RHU.', 'bloodTypesNeeded' => ['O-', 'AB-']],
];
$activities = [
    ['text' => 'Submitted donor Juan Santos to RHU registry', 'time' => '2 hours ago', 'color' => 'bg-green-500'],
    ['text' => 'Confirmed blood drive venue: Mabini Barangay Hall', 'time' => 'Yesterday', 'color' => 'bg-blue-500'],
    ['text' => 'Reported blood need: O+ for household #45', 'time' => '2 days ago', 'color' => 'bg-red-500'],
    ['text' => 'House-to-house donor recruitment: 12 households', 'time' => '3 days ago', 'color' => 'bg-purple-500'],
];
$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Hydrate the BHW portal exclusively from persistent database records.
$donors = $drives = $activities = [];
if (!empty($pdo)) {
    $requestedBhwId = (int)($_SESSION['bhw_user']['bhw_id'] ?? 0);
    $profileStatement = $pdo->prepare("SELECT b.id,b.barangay,b.coverage_population,CONCAT(u.first_name,' ',u.last_name) name,s.license_number FROM bhw b LEFT JOIN staff s ON s.id=b.staff_id LEFT JOIN users u ON u.id=s.user_id WHERE (? > 0 AND b.id=?) OR ?=0 ORDER BY b.id LIMIT 1");
    $profileStatement->execute([$requestedBhwId, $requestedBhwId, $requestedBhwId]);
    $profile = $profileStatement->fetch(PDO::FETCH_ASSOC) ?: [];
    $bhwId = (int)($profile['id'] ?? 0);
    $bhw = [
        'name' => trim((string)($profile['name'] ?? '')) ?: 'Unassigned BHW Profile',
        'barangay' => $profile['barangay'] ?? 'Not assigned',
        'certificationNumber' => $profile['license_number'] ?? ('BHW-' . $bhwId),
        'assignedHouseholds' => (int)($profile['coverage_population'] ?? 0),
        'donorsReferred' => 0,
    ];
    $settings = $pdo->query("SELECT setting_key,setting_value FROM portal_settings")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    $rhu = ['name' => $settings['rhu_name'] ?? 'Rural Health Unit', 'mho' => $settings['rhu_mho_name'] ?? 'Not recorded', 'contactNumber' => $settings['rhu_contact'] ?? 'Not recorded'];

    $donorStatement = $pdo->prepare("SELECT * FROM bhw_donor_referrals WHERE (?=0 OR bhw_id=?) ORDER BY id DESC");
    $donorStatement->execute([$bhwId, $bhwId]);
    foreach ($donorStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $verified = strtolower($row['status']) === 'verified';
        $donors[] = ['id'=>'D-'.$row['id'],'name'=>$row['full_name'],'bloodType'=>$row['blood_type'],'age'=>(int)$row['age'],'gender'=>$row['gender'],'contactNumber'=>$row['contact_number'],'donationHistory'=>0,'cluster'=>$row['status'],'availability'=>$verified,'verified'=>$verified,'nextEligibleDate'=>null];
    }
    $bhw['donorsReferred'] = count($donors);
    $driveStatement = $pdo->prepare("SELECT * FROM blood_drives WHERE (?=0 OR bhw_id=?) ORDER BY scheduled_date DESC");
    $driveStatement->execute([$bhwId, $bhwId]);
    foreach ($driveStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $drives[] = ['id'=>'BD-'.$row['id'],'title'=>$row['title'],'scheduledDate'=>$row['scheduled_date'],'startTime'=>$row['start_time'] ?: 'Not recorded','endTime'=>$row['end_time'] ?: 'Not recorded','venue'=>$row['venue'],'targetDonors'=>(int)$row['target_donors'],'actualDonors'=>$row['actual_donors'] === null ? null : (int)$row['actual_donors'],'status'=>strtolower($row['status']),'notes'=>$row['notes'],'bloodTypesNeeded'=>array_values(array_filter(array_map('trim',explode(',',(string)$row['blood_types_needed']))))];
    }
    foreach (array_slice($donors, 0, 5) as $donor) $activities[] = ['text'=>'Submitted donor '.$donor['name'].' to RHU registry','time'=>'Stored in database','color'=>'bg-green-500'];
    $reportStatement = $pdo->prepare("SELECT * FROM blood_need_reports WHERE (?=0 OR bhw_id=?) ORDER BY id DESC LIMIT 5");
    $reportStatement->execute([$bhwId, $bhwId]);
    foreach ($reportStatement->fetchAll(PDO::FETCH_ASSOC) as $row) $activities[] = ['text'=>'Reported '.$row['blood_type'].' blood need for '.$row['patient_name'],'time'=>$row['created_at'],'color'=>'bg-red-500'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';
    if ($form === 'donor') {
        $newDonor = ['id' => 'D-NEW-' . time(), 'name' => trim($_POST['name'] ?? ''), 'bloodType' => $_POST['bloodType'] ?? '', 'age' => (int) ($_POST['age'] ?? 0), 'gender' => $_POST['gender'] ?? 'Male', 'contactNumber' => trim($_POST['contactNumber'] ?? ''), 'donationHistory' => 0, 'cluster' => 'New', 'availability' => false, 'verified' => false, 'nextEligibleDate' => null];
        if ($newDonor['name'] !== '' && $newDonor['age'] >= 17 && $newDonor['contactNumber'] !== '') {
            if (!empty($pdo)) {
                $statement = $pdo->prepare("INSERT INTO bhw_donor_referrals (bhw_id,full_name,blood_type,age,gender,contact_number,address) VALUES (?,?,?,?,?,?,?)");
                $statement->execute([$bhwId ?: null,$newDonor['name'],$newDonor['bloodType'],$newDonor['age'],$newDonor['gender'],$newDonor['contactNumber'],trim($_POST['address'] ?? '') ?: null]);
                $_SESSION['bhw_flash'] = 'Donor referral saved to the RHU database.';
            }
        }
        header('Location: ' . dashboardUrl('donors')); exit;
    }
    if ($form === 'report') {
        if (!empty($pdo)) {
            $statement = $pdo->prepare("INSERT INTO blood_need_reports (bhw_id,patient_name,blood_type,urgency,description) VALUES (?,?,?,?,?)");
            $statement->execute([$bhwId ?: null,trim($_POST['patientName'] ?? ''),$_POST['bloodType'] ?? '',$_POST['urgency'] ?? 'urgent',trim($_POST['description'] ?? '')]);
            $_SESSION['bhw_flash'] = 'Blood need report saved to the RHU database.';
        }
        header('Location: ' . dashboardUrl('report')); exit;
    }
}

function formatDate(?string $date): string { return !empty($date) && strtotime($date) !== false ? date('F j, Y', strtotime($date)) : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BHW Dashboard - ResiHUnity RHU</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>body{font-family:ui-sans-serif,system-ui,sans-serif}.safe-area-pb{padding-bottom:env(safe-area-inset-bottom)}@media(max-width:639px){.desktop-tabs{display:none}}@media(min-width:640px){.mobile-tabs{display:none}}</style>
  <link rel="stylesheet" href="dashboard-enhancements.css">
  <script defer src="dashboard-enhancements.js?v=20260726-controls3"></script>
</head>
<body class="bg-gray-50 text-gray-900">
<div class="min-h-screen">
  <header class="sticky top-0 z-40 bg-gradient-to-r from-green-700 to-green-800 text-white shadow-lg">
    <div class="max-w-full px-4 py-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3"><div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-lg">♥</div><div><div class="flex items-center gap-2"><h1 class="text-base font-bold">ResiHUnity RHU</h1><span class="hidden rounded-full bg-green-600 px-2 py-0.5 text-xs text-green-100 sm:block">BHW Portal</span></div><p class="text-xs text-green-200">Barangay <?= esc($bhw['barangay']) ?> — <?= esc($bhw['name']) ?></p></div></div>
        <div class="flex items-center gap-2"><?= portalRenderNotificationButton(); ?><a href="StaffLogout.php?portal=bhw" data-staff-logout class="staff-logout-trigger" aria-label="Log out"><span class="staff-logout-glyph" aria-hidden="true"></span><span>Log out</span></a></div>
      </div>
      <nav class="desktop-tabs mt-2 gap-1 overflow-x-auto pb-0.5 sm:flex">
        <?php foreach ($tabs as $id => [$label, $icon]): ?><a href="<?= esc(dashboardUrl($id)) ?>" class="flex items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-semibold whitespace-nowrap transition <?= $tab === $id ? 'bg-white text-green-700 shadow-sm' : 'text-green-100 hover:bg-green-600' ?>"><span><?= $icon ?></span><?= esc($label) ?></a><?php endforeach; ?>
      </nav>
    </div>
  </header>

  <main class="mx-auto w-full max-w-4xl px-3 py-4 pb-28 sm:px-4 sm:py-6 sm:pb-6">
    <?php if ($flash): ?><div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-3 text-sm font-medium text-green-800">✓ <?= esc($flash) ?></div><?php endif; ?>
    <?php if ($tab === 'overview'): ?>
      <div class="space-y-5">
        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm"><div class="flex items-center gap-4"><div class="flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-xl font-bold text-green-700"><?= esc(substr($bhw['name'], 0, 1)) ?></div><div class="flex-1"><h2 class="text-lg font-bold"><?= esc($bhw['name']) ?></h2><p class="text-sm text-gray-600">Barangay Health Worker • Barangay <?= esc($bhw['barangay']) ?></p><p class="font-mono text-xs text-gray-400"><?= esc($bhw['certificationNumber']) ?></p></div><div class="text-right"><span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">Active</span><p class="mt-1 text-xs text-gray-400"><?= $bhw['assignedHouseholds'] ?> Households</p></div></div><div class="mt-4 grid grid-cols-3 gap-3 border-t border-gray-100 pt-4 text-center"><div><p class="text-xl font-bold text-green-700"><?= count($donors) ?></p><p class="text-xs text-gray-500">Donors in Registry</p></div><div><p class="text-xl font-bold text-blue-700"><?= $bhw['donorsReferred'] ?></p><p class="text-xs text-gray-500">Total Referred</p></div><div><p class="text-xl font-bold text-orange-600"><?= count($drives) ?></p><p class="text-xs text-gray-500">Drives Managed</p></div></div></section>
        <section class="grid grid-cols-2 gap-3"><a href="<?= esc(dashboardUrl('donors', ['modal' => 'donor'])) ?>" class="flex items-center gap-3 rounded-xl bg-green-600 p-4 text-white shadow-sm hover:bg-green-700"><span class="text-2xl">＋</span><span><b class="block text-sm">Refer Donor</b><small class="text-xs text-green-100">Submit new donor to RHU</small></span></a><a href="<?= esc(dashboardUrl('report')) ?>" class="flex items-center gap-3 rounded-xl bg-red-600 p-4 text-white shadow-sm hover:bg-red-700"><span class="text-xl">⚠</span><span><b class="block text-sm">Report Blood Need</b><small class="text-xs text-red-100">Alert RHU of urgent need</small></span></a><a href="<?= esc(dashboardUrl('drives')) ?>" class="flex items-center gap-3 rounded-xl bg-blue-600 p-4 text-white shadow-sm hover:bg-blue-700"><span class="text-xl">▣</span><span><b class="block text-sm">Blood Drive</b><small class="text-xs text-blue-100">View upcoming drives</small></span></a><a href="RHUAdminDashboard.php" class="flex items-center gap-3 rounded-xl bg-gray-700 p-4 text-white shadow-sm hover:bg-gray-800"><span class="text-xl">➤</span><span><b class="block text-sm">Contact RHU</b><small class="text-xs text-gray-300">Message RHU staff</small></span></a></section>
        <?php foreach ($drives as $drive): if ($drive['status'] !== 'scheduled') continue; ?><section class="rounded-xl border border-blue-200 bg-blue-50 p-4"><div class="flex gap-3"><span class="text-blue-600">▣</span><div><p class="font-bold text-blue-900">Upcoming Blood Drive</p><p class="mt-1 text-sm font-semibold text-blue-800"><?= esc($drive['title']) ?></p><p class="text-xs text-blue-700"><?= esc(formatDate($drive['scheduledDate'])) ?> • <?= esc($drive['startTime']) ?> – <?= esc($drive['endTime']) ?></p><p class="text-xs text-blue-700"><?= esc($drive['venue']) ?> • Target: <?= $drive['targetDonors'] ?> donors</p></div></div></section><?php endforeach; ?>
        <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm"><h3 class="mb-4 font-bold">Recent Activity</h3><div class="space-y-3"><?php foreach ($activities as $activity): ?><div class="flex items-start gap-3"><span class="mt-2 h-2 w-2 shrink-0 rounded-full <?= $activity['color'] ?>"></span><div><p class="text-sm text-gray-800"><?= esc($activity['text']) ?></p><p class="text-xs text-gray-400"><?= esc($activity['time']) ?></p></div></div><?php endforeach; ?></div></section>
        <section class="rounded-xl bg-gray-800 p-4 text-white"><p class="mb-1 text-xs text-gray-400">Supervising RHU</p><p class="font-bold"><?= esc($rhu['name']) ?></p><p class="text-sm text-gray-300"><?= esc($rhu['mho']) ?> (MHO)</p><p class="mt-2 text-sm text-gray-300">☎ <?= esc($rhu['contactNumber']) ?></p></section>
      </div>
    <?php elseif ($tab === 'donors'): ?>
      <div class="space-y-4"><div class="flex items-center justify-between"><h2 class="text-xl font-bold">My Referred Donors</h2><a href="<?= esc(dashboardUrl('donors', ['modal' => 'donor'])) ?>" class="rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">＋ Refer Donor</a></div><div class="space-y-3"><?php foreach ($donors as $donor): ?><section class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm"><div class="flex items-center justify-between"><div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 font-bold text-red-700"><?= esc($donor['bloodType'] ?: '?') ?></span><div><p class="font-bold"><?= esc($donor['name']) ?></p><p class="text-xs text-gray-500"><?= $donor['age'] ?>y • <?= esc($donor['gender']) ?> • <?= esc($donor['contactNumber']) ?></p><p class="text-xs text-gray-400"><?= $donor['donationHistory'] ?> donations • <?= esc($donor['cluster']) ?></p></div></div><div class="text-right"><span class="rounded-full px-2 py-0.5 text-xs font-semibold <?= $donor['availability'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>"><?= $donor['availability'] ? 'Available' : 'Unavailable' ?></span><?php if ($donor['verified']): ?><p class="mt-1 text-xs text-green-600">✓ Verified</p><?php endif; ?></div></div><?php if ($donor['nextEligibleDate']): ?><p class="mt-2 rounded-lg bg-blue-50 px-3 py-1 text-xs text-blue-600">Next eligible: <?= esc(formatDate($donor['nextEligibleDate'])) ?></p><?php endif; ?></section><?php endforeach; ?></div></div>
    <?php elseif ($tab === 'drives'): ?>
      <div class="space-y-4"><h2 class="text-xl font-bold">Blood Drives — Barangay <?= esc($bhw['barangay']) ?></h2><?php foreach ($drives as $drive): ?><section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm"><div class="mb-3"><h3 class="font-bold"><?= esc($drive['title']) ?></h3><span class="rounded-full px-2 py-0.5 text-xs font-semibold <?= $drive['status'] === 'scheduled' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' ?>"><?= esc(ucfirst($drive['status'])) ?></span></div><div class="space-y-2 text-sm text-gray-600"><p>⌖ <?= esc($drive['venue']) ?></p><p>▣ <?= esc(formatDate($drive['scheduledDate'])) ?></p><p>♟ Target: <?= $drive['targetDonors'] ?> donors<?= $drive['actualDonors'] !== null ? ' • Actual: ' . $drive['actualDonors'] : '' ?></p></div><?php if ($drive['notes']): ?><p class="mt-3 rounded-lg border border-amber-100 bg-amber-50 p-2 text-xs text-amber-700"><?= esc($drive['notes']) ?></p><?php endif; ?><div class="mt-3 flex flex-wrap gap-1"><?php foreach ($drive['bloodTypesNeeded'] as $type): ?><span class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700"><?= esc($type) ?></span><?php endforeach; ?></div><?php if ($drive['status'] === 'scheduled'): ?><div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-xs text-green-700"><b class="text-green-800">Your Action Items:</b><ul class="mt-2 space-y-1"><li>✓ Inform households in your assigned area</li><li>✓ Confirm venue availability with barangay captain</li><li>✓ Coordinate with PRC mobile unit (if applicable)</li><li>✓ Recruit target donors from registry</li></ul></div><?php endif; ?></section><?php endforeach; ?><section class="rounded-xl border border-blue-100 bg-blue-50 p-4"><p class="text-sm font-bold text-blue-900">Want to organize a blood drive?</p><p class="mt-1 text-xs text-blue-700">Contact RHU to schedule a blood donation drive for your barangay. They will coordinate with the Philippine Red Cross for mobile blood collection.</p><a class="mt-2 inline-block text-xs font-semibold text-blue-700 hover:underline" href="tel:<?= esc($rhu['contactNumber']) ?>">☎ <?= esc($rhu['contactNumber']) ?></a></section></div>
    <?php else: ?>
      <div class="space-y-4"><h2 class="text-xl font-bold">Report Blood Need</h2><section class="rounded-xl border border-red-200 bg-red-50 p-4"><p class="text-sm font-bold text-red-900">⚠ Emergency Blood Need?</p><p class="text-sm text-red-700">For life-threatening emergencies, call RHU immediately: <strong><?= esc($rhu['contactNumber']) ?></strong></p></section><section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm"><h3 class="mb-4 font-bold">Submit Blood Need Report</h3><form method="post" class="space-y-4"><input type="hidden" name="form" value="report"><label class="block text-sm font-semibold text-gray-700">Patient Name<input required name="patientName" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal" placeholder="Patient's full name"></label><div class="grid grid-cols-2 gap-3"><label class="block text-sm font-semibold text-gray-700">Blood Type Needed<select required name="bloodType" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal"><option value="">Select</option><?php foreach ($bloodTypes as $type): ?><option><?= esc($type) ?></option><?php endforeach; ?></select></label><label class="block text-sm font-semibold text-gray-700">Urgency<select name="urgency" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal"><option value="critical">Critical</option><option value="urgent" selected>Urgent</option><option value="moderate">Moderate</option></select></label></div><label class="block text-sm font-semibold text-gray-700">Description<textarea required name="description" rows="3" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal" placeholder="Describe the situation and location..."></textarea></label><button class="w-full rounded-lg bg-red-600 py-3 font-semibold text-white hover:bg-red-700">Report to RHU</button></form></section></div>
    <?php endif; ?>
  </main>
  <nav class="mobile-tabs safe-area-pb fixed bottom-0 left-0 right-0 z-50 border-t border-gray-200 bg-white"><div class="flex items-stretch"><?php foreach ($tabs as $id => [$label, $icon]): ?><a href="<?= esc(dashboardUrl($id)) ?>" class="relative flex flex-1 flex-col items-center gap-0.5 py-2 text-[10px] font-semibold <?= $tab === $id ? 'text-green-600' : 'text-gray-400' ?>"><?php if ($tab === $id): ?><span class="absolute top-0 h-0.5 w-6 rounded-full bg-green-500"></span><?php endif; ?><span class="text-base"><?= $icon ?></span><?= esc($label) ?></a><?php endforeach; ?></div></nav>
</div>

<?php if ($modal === 'donor'): ?>
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"><div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl bg-white shadow-2xl"><div class="sticky top-0 flex items-center justify-between border-b bg-white p-5"><h2 class="text-lg font-bold">♟ Refer New Donor</h2><a href="<?= esc(dashboardUrl('donors')) ?>" class="text-xl text-gray-400">×</a></div><form method="post" class="space-y-4 p-5"><input type="hidden" name="form" value="donor"><label class="block text-sm font-semibold text-gray-700">Full Name<input required name="name" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal" placeholder="First Middle Last"></label><div class="grid grid-cols-3 gap-3"><label class="block text-sm font-semibold text-gray-700">Blood Type<select name="bloodType" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal"><option value="">Select</option><?php foreach ($bloodTypes as $type): ?><option><?= esc($type) ?></option><?php endforeach; ?></select></label><label class="block text-sm font-semibold text-gray-700">Age<input required name="age" type="number" min="17" max="65" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal"></label><label class="block text-sm font-semibold text-gray-700">Gender<select name="gender" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal"><option>Male</option><option>Female</option></select></label></div><div class="grid grid-cols-2 gap-3"><label class="block text-sm font-semibold text-gray-700">Weight (kg)<input name="weight" type="number" min="50" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal" placeholder="Min. 50 kg"></label><label class="block text-sm font-semibold text-gray-700">Contact Number<input required name="contactNumber" type="tel" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal" placeholder="09XXXXXXXXX"></label></div><label class="block text-sm font-semibold text-gray-700">Home Address in Barangay <?= esc($bhw['barangay']) ?><input required name="address" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal" placeholder="Purok/Street, House No."></label><label class="block text-sm font-semibold text-gray-700">Occupation<input name="occupation" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal"></label><label class="block text-sm font-semibold text-gray-700">PhilHealth ID (optional)<input name="philhealthId" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 font-normal" placeholder="XXXX-XXXX-XXXX"></label><div class="rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-xs text-yellow-700"><b class="text-yellow-800">Eligibility Reminder</b><ul class="mt-1"><li>• Age: 17–65 years old</li><li>• Weight: At least 50 kg</li><li>• No fever or illness in the last 14 days</li><li>• No blood donation in the past 56 days</li><li>• Not pregnant or breastfeeding</li></ul></div><div class="flex gap-3"><a href="<?= esc(dashboardUrl('donors')) ?>" class="flex-1 rounded-lg border border-gray-300 py-2.5 text-center text-sm font-semibold text-gray-700">Cancel</a><button class="flex-1 rounded-lg bg-green-600 py-2.5 text-sm font-semibold text-white hover:bg-green-700">Submit Referral</button></div></form></div></div>
<?php endif; ?>
<?= portalRenderNotificationPanel(); ?>
</body>
</html>
