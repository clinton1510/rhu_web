<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$stType = strtoupper((string)($_SESSION['rhu_staff_login']['staff_type'] ?? ''));
if (empty($_SESSION['rhu_staff_login']) || ($stType !== 'MEDTECH' && !str_contains($stType, 'MEDTECH') && !str_contains($stType, 'TECHNOLOGIST'))) {
    header('Location: RHULogin.php');
    exit;
}
require_once __DIR__ . '/db.php';
function esc($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
$tabs = ['overview'=>'Overview','rapid'=>'Rapid Tests','referrals'=>'Specimen Referrals','supplies'=>'Test Supplies','reports'=>'Reports'];
$tab = $_GET['tab'] ?? 'overview'; if (!isset($tabs[$tab])) $tab = 'overview';
$modal = $_GET['modal'] ?? '';
$medtechStaffId = (int)($_SESSION['rhu_staff_login']['staff_id'] ?? 0);
$medtechUserId = (int)($_SESSION['rhu_staff_login']['id'] ?? $_SESSION['rhu_staff_login']['user_id'] ?? 0);
$medtechConsultations = [];

if (!empty($pdo)) {
    try {
        $mtStmt = $pdo->prepare("
            SELECT c.id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, r.barangay, c.consultation_date as date, c.consultation_notes
            FROM consultations c
            JOIN residents r ON c.resident_id = r.id
            LEFT JOIN staff doc_s ON c.physician_id = doc_s.id
            WHERE (c.physician_id = :sid OR doc_s.user_id = :uid OR c.chief_complaint LIKE '%Laboratory%' OR c.chief_complaint LIKE '%Blood%' OR c.chief_complaint LIKE '%MedTech%')
            ORDER BY c.id DESC
        ");
        $mtStmt->execute(['sid' => $medtechStaffId, 'uid' => $medtechUserId]);
        $medtechConsultations = $mtStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {}

    $consultationOptions = $pdo->query("SELECT c.id,CONCAT(r.first_name,' ',r.last_name) patient FROM consultations c JOIN residents r ON r.id=c.resident_id ORDER BY c.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $residentOptions = $pdo->query("SELECT id,CONCAT(first_name,' ',last_name) name FROM residents ORDER BY first_name,last_name")->fetchAll(PDO::FETCH_ASSOC);
    $staffOptions = $pdo->query("SELECT s.id,CONCAT(u.first_name,' ',u.last_name) name FROM staff s JOIN users u ON u.id=s.user_id ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_test') {
            $statement = $pdo->prepare("INSERT INTO diagnostics (consultation_id,test_type,test_name,test_date,results,test_status,ordered_by_id) VALUES (?,?,?,?,?,?,?)");
            $statement->execute([(int)$_POST['consultation_id'],trim($_POST['test_type']),trim($_POST['test_name']),$_POST['test_date'],trim($_POST['results']),trim($_POST['test_status']),(int)$_POST['ordered_by_id'] ?: null]);
            header('Location: ?tab=rapid'); exit;
        }
        if ($action === 'save_lab_referral') {
            $statement = $pdo->prepare("INSERT INTO laboratory_referrals (resident_id,test_requested,destination_facility,referral_date,status,clinical_notes,referred_by_id) VALUES (?,?,?,?,?,?,?)");
            $statement->execute([(int)$_POST['resident_id'],trim($_POST['test_requested']),trim($_POST['destination_facility']),$_POST['referral_date'],'Pending',trim($_POST['clinical_notes']),(int)$_POST['referred_by_id'] ?: null]);
            header('Location: ?tab=referrals'); exit;
        }
        if ($action === 'save_lab_supply') {
            $statement = $pdo->prepare("INSERT INTO laboratory_supplies (item_name,category,quantity,unit,reorder_level,expiry_date) VALUES (?,?,?,?,?,?)");
            $statement->execute([trim($_POST['item_name']),trim($_POST['category']),(int)$_POST['quantity'],trim($_POST['unit']),(int)$_POST['reorder_level'],$_POST['expiry_date'] ?: null]);
            header('Location: ?tab=supplies'); exit;
        }
    }
    $testRows = $pdo->query("SELECT d.id, CONCAT(r.first_name,' ',r.last_name) patient, TIMESTAMPDIFF(YEAR,r.date_of_birth,CURDATE()) age, r.barangay, d.test_name, d.results, d.test_status, d.test_type, CONCAT(u.first_name,' ',u.last_name) ordered_by, d.test_date FROM diagnostics d JOIN consultations c ON c.id=d.consultation_id JOIN residents r ON r.id=c.resident_id LEFT JOIN staff s ON s.id=d.ordered_by_id LEFT JOIN users u ON u.id=s.user_id ORDER BY d.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($testRows as $row) {
        $status = strtolower((string)$row['test_status']);
        $flag = str_contains($status, 'abnormal') || str_contains($status, 'positive') ? 'abnormal' : (str_contains($status, 'pending') ? 'pending' : 'normal');
        $tests[] = ['RDT-' . $row['id'], $row['patient'], (int)$row['age'], $row['barangay'], $row['test_name'], $row['results'] ?: 'Pending', $flag, $row['test_type'] ?: 'Not recorded', trim((string)$row['ordered_by']) ?: 'Not assigned', $row['test_date']];
    }
    $referralRows = $pdo->query("SELECT lr.*, CONCAT(r.first_name,' ',r.last_name) patient, TIMESTAMPDIFF(YEAR,r.date_of_birth,CURDATE()) age FROM laboratory_referrals lr JOIN residents r ON r.id=lr.resident_id ORDER BY lr.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($referralRows as $row) {
        $referrals[] = ['SPR-' . $row['id'], $row['patient'], (int)$row['age'], $row['test_requested'], $row['destination_facility'], $row['referral_date'], strtolower(str_replace(' ', '-', $row['status'])), $row['result_text'] ?: 'Awaiting', $row['clinical_notes'] ?: 'Not recorded'];
    }
    $supplyRows = $pdo->query("SELECT * FROM laboratory_supplies ORDER BY item_name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($supplyRows as $row) {
        $status = (int)$row['quantity'] <= 0 ? 'critical' : ((int)$row['quantity'] <= (int)$row['reorder_level'] ? 'low' : 'adequate');
        $supplies[] = [$row['item_name'], $row['category'] ?: 'Laboratory', (int)$row['quantity'], $row['unit'], (int)$row['reorder_level'], $row['expiry_date'], $status];
    }
}
$today = array_filter($tests, fn($r) => $r[9] === date('Y-m-d')); $abnormal = count(array_filter($tests, fn($r) => in_array($r[6], ['abnormal','notable']))); $pending = count(array_filter($referrals, fn($r) => $r[6] === 'pending')); $critical = count(array_filter($supplies, fn($s) => $s[6] === 'critical')); $low = count(array_filter($supplies, fn($s) => $s[6] === 'low'));
function tabUrl($tab): string { return '?tab=' . urlencode($tab); }
function flagClass($flag): string { return match($flag) {'normal'=>'bg-green-100 text-green-700','notable'=>'bg-blue-100 text-blue-700','pending'=>'bg-yellow-100 text-yellow-700',default=>'bg-orange-100 text-orange-700'}; }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medical Technologist Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="dashboard-enhancements.css">
  <script defer src="dashboard-enhancements.js?v=20260726-controls3"></script>
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">

    <header class="sticky top-0 z-40 bg-gradient-to-r from-violet-700 to-purple-800 text-white shadow-xl">
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 sm:px-6">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-xl">⚗</span>
                <div>
                    <h1 class="text-base font-bold">Medical Technologist Portal</h1>
                    <p class="text-xs text-violet-200">Nasugbu Rural Health Unit I — Rapid Diagnostics</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="hidden rounded-lg bg-white/10 px-3 py-1.5 text-sm font-semibold sm:block">♥ <?= esc($_SESSION['rhu_staff_login']['name'] ?? 'Medical Technologist') ?></span>
                <a href="StaffLogout.php" data-staff-logout class="staff-logout-trigger" title="Log Out"><span class="staff-logout-glyph" aria-hidden="true"></span><span>Log out</span></a>
            </div>
        </div>
        <nav class="hidden gap-1 overflow-x-auto px-4 sm:flex">
            <?php foreach($tabs as $id=>$label): ?>
                <a href="<?= tabUrl($id) ?>" class="rounded-t-lg px-3 py-2 text-xs font-semibold <?= $tab===$id ? 'bg-white text-violet-700' : 'text-violet-100 hover:bg-white/10' ?>">
                    <?= esc($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <main class="mx-auto w-full max-w-6xl space-y-5 px-3 py-4 pb-24 sm:px-4 sm:py-6">
        <?php if ($tab === 'overview'): ?>
            <?php if ($critical): ?>
                <section class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4">
                    <span class="text-red-600">⚠</span>
                    <div>
                        <p class="text-sm font-bold text-red-880"><?= $critical ?> test kit(s) critically low — request resupply immediately</p>
                        <p class="text-sm text-red-700">
                            <?php foreach($supplies as $s) if($s[6]==='critical') echo esc($s[0]).' · '; ?>
                        </p>
                    </div>
                    <a href="<?= tabUrl('supplies') ?>" class="ml-auto rounded-lg bg-red-600 px-3 py-1.5 text-xs text-white">View</a>
                </section>
            <?php endif; ?>

            <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <?php foreach([['Today’s RDTs',count($today),'tests performed','text-violet-600'],['Abnormal Results',$abnormal,'need follow-up','text-orange-600'],['Pending Referrals',$pending,'specimen referrals','text-blue-600'],['Low/Critical Kits',$critical+$low,"$critical critical",'text-red-600']] as [$label,$value,$sub,$color]): ?>
                    <div class="rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                        <p class="text-2xl font-black <?= $color ?>"><?= $value ?></p>
                        <p class="text-sm font-bold"><?= $label ?></p>
                        <p class="text-xs text-gray-400"><?= $sub ?></p>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="rounded-xl border border-violet-200 bg-white p-5 shadow-sm space-y-3">
                <div class="flex justify-between items-center border-b border-violet-100 pb-2">
                    <h2 class="font-bold text-sm text-violet-950 flex items-center gap-2">
                        ⚗ Received Resident Lab Appointments (<?= count($medtechConsultations) ?>)
                    </h2>
                    <span class="text-[10px] font-bold bg-violet-50 text-violet-700 px-2 py-0.5 rounded-full border border-violet-200">Live Queue</span>
                </div>
                <?php if (empty($medtechConsultations)): ?>
                    <p class="text-xs text-gray-400 italic py-2 text-center">No laboratory consultations assigned to you yet.</p>
                <?php else: ?>
                    <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                        <?php foreach ($medtechConsultations as $mc): ?>
                            <div class="py-2.5 flex items-center justify-between text-xs gap-2">
                                <div>
                                    <p class="font-bold text-gray-900"><?= esc($mc['patientName']) ?> <span class="text-gray-500 font-normal">(<?= esc($mc['age'] ?? 'N/A') ?>y • <?= esc($mc['gender'] ?? 'N/A') ?> • <?= esc($mc['barangay']) ?>)</span></p>
                                    <p class="text-violet-700 font-medium"><?= esc($mc['chiefComplaint']) ?></p>
                                    <p class="text-[10px] text-gray-400 font-mono">📅 Date: <?= esc($mc['date']) ?></p>
                                </div>
                                <span class="px-2 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg font-bold text-[10px] shrink-0">Pending RDT/Lab</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="rounded-xl border border-violet-100 bg-violet-50 p-4">
                <p class="mb-2 text-sm font-bold text-violet-900">▣ Available Rapid Diagnostic Tests at this RHU</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach(['Blood Glucose','Urinalysis Dipstick','Pregnancy Test (HCG)','HBsAg Rapid','HIV Rapid Test','VDRL Rapid','Dengue NS1 RDT','Malaria RDT','TB Sputum Collection'] as $item): ?>
                        <span class="rounded-full border border-violet-200 bg-white px-2.5 py-1 text-xs font-semibold text-violet-700"><?= $item ?></span>
                    <?php endforeach; ?>
                </div>
                <p class="mt-2 text-xs text-violet-600">Samples requiring CBC, culture, or advanced chemistry are referred to higher facilities.</p>
            </section>

            <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="mb-3 flex justify-between">
                    <h2 class="font-bold">▤ Today’s Rapid Tests</h2>
                    <a href="<?= tabUrl('rapid') ?>" class="text-xs text-violet-600">View all →</a>
                </div>
                <div class="space-y-2">
                    <?php foreach($today as $r): ?>
                        <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-2.5">
                            <i class="h-2 w-2 rounded-full <?= in_array($r[6],['abnormal','notable'])?'bg-orange-500':'bg-green-500' ?>"></i>
                            <div class="min-w-0 flex-1">
                                <b class="block truncate text-sm"><?= esc($r[1]) ?></b>
                                <small class="block truncate text-gray-500"><?= esc($r[4]) ?></small>
                            </div>
                            <b class="text-xs <?= $r[6]==='normal'?'text-green-600':'text-orange-600' ?>"><?= esc($r[5]) ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

        <?php elseif ($tab === 'rapid'): ?>
            <div class="flex justify-between">
                <h2 class="text-xl font-bold">▣ Rapid Diagnostic Test Log</h2>
                <a href="?tab=rapid&modal=test" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white">＋ Record RDT</a>
            </div>
            <?php if($modal==='test'): ?><form method="post" class="grid gap-2 rounded-xl border bg-white p-4 text-sm sm:grid-cols-3"><input type="hidden" name="action" value="save_test"><select required name="consultation_id" class="rounded border p-2"><option value="">Consultation</option><?php foreach($consultationOptions as $o): ?><option value="<?= (int)$o['id'] ?>"><?= esc($o['patient']) ?></option><?php endforeach; ?></select><input required name="test_name" placeholder="Test name" class="rounded border p-2"><input required name="test_type" placeholder="Reference range / type" class="rounded border p-2"><input required type="date" name="test_date" value="<?= date('Y-m-d') ?>" class="rounded border p-2"><input required name="results" placeholder="Result" class="rounded border p-2"><select name="test_status" class="rounded border p-2"><option>Normal</option><option>Abnormal</option><option>Pending</option></select><select name="ordered_by_id" class="rounded border p-2"><option value="">Ordering staff</option><?php foreach($staffOptions as $o): ?><option value="<?= (int)$o['id'] ?>"><?= esc($o['name']) ?></option><?php endforeach; ?></select><button class="rounded bg-violet-600 p-2 font-bold text-white">Save to Database</button></form><?php endif; ?>
            <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700">⚠ Critical and abnormal results must be reported immediately to the requesting physician.</p>
            
            <div class="space-y-3">
                <?php foreach($tests as $r): ?>
                    <article class="rounded-xl border <?= in_array($r[6],['abnormal','notable'])?'border-orange-200':'border-gray-100' ?> bg-white p-4 shadow-sm">
                        <div class="flex justify-between gap-3">
                            <div>
                                <p>
                                    <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-400"><?= $r[0] ?></span> 
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold <?= flagClass($r[6]) ?>"><?= ucfirst($r[6]) ?></span>
                                </p>
                                <b class="mt-2 block"><?= esc($r[1]) ?>, <?= $r[2] ?>y · <?= esc($r[3]) ?></b>
                                <p class="text-xs font-semibold text-violet-600"><?= esc($r[4]) ?></p>
                                <p class="mt-1 text-sm font-bold <?= $r[6]==='normal'?'text-gray-800':'text-orange-600' ?>"><?= esc($r[5]) ?> <small class="font-normal text-gray-400">Ref: <?= esc($r[7]) ?></small></p>
                                <p class="mt-1 text-xs text-gray-400">By: <?= esc($r[8]) ?> · <?= $r[9] ?></p>
                            </div>
                            <span>◉</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php elseif ($tab === 'referrals'): ?>
            <div class="flex justify-between">
                <h2 class="text-xl font-bold">➤ Specimen Referrals</h2>
                <a href="?tab=referrals&modal=referral" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">＋ New Referral</a>
            </div>
            <?php if($modal==='referral'): ?><form method="post" class="grid gap-2 rounded-xl border bg-white p-4 text-sm sm:grid-cols-3"><input type="hidden" name="action" value="save_lab_referral"><select required name="resident_id" class="rounded border p-2"><option value="">Resident</option><?php foreach($residentOptions as $o): ?><option value="<?= (int)$o['id'] ?>"><?= esc($o['name']) ?></option><?php endforeach; ?></select><input required name="test_requested" placeholder="Test requested" class="rounded border p-2"><input required name="destination_facility" placeholder="Destination facility" class="rounded border p-2"><input required type="date" name="referral_date" value="<?= date('Y-m-d') ?>" class="rounded border p-2"><input name="clinical_notes" placeholder="Clinical notes" class="rounded border p-2"><select name="referred_by_id" class="rounded border p-2"><option value="">Referring staff</option><?php foreach($staffOptions as $o): ?><option value="<?= (int)$o['id'] ?>"><?= esc($o['name']) ?></option><?php endforeach; ?></select><button class="rounded bg-blue-600 p-2 font-bold text-white">Save Referral</button></form><?php endif; ?>
            <p class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-xs text-blue-700">Specimens requiring CBC, culture, HbA1c, lipid profile, and other tests not doable at RHU level are collected here and sent to higher facilities.</p>
            
            <section class="grid grid-cols-3 gap-3">
                <?php foreach([['Pending',$pending,'text-yellow-600'],['Result Received',count($referrals)-$pending,'text-green-600'],['Total (June)',count($referrals),'text-blue-600']] as [$label,$value,$color]): ?>
                    <div class="rounded-xl border border-gray-100 bg-white p-4 text-center">
                        <b class="text-2xl <?= $color ?>"><?= $value ?></b>
                        <p class="text-xs text-gray-500"><?= $label ?></p>
                    </div>
                <?php endforeach; ?>
            </section>
            
            <div class="space-y-3">
                <?php foreach($referrals as $r): ?>
                    <article class="rounded-xl border <?= $r[6]==='pending'?'border-yellow-200':'border-gray-100' ?> bg-white p-4 shadow-sm">
                        <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-400"><?= $r[0] ?></span> 
                        <span class="rounded-full px-2 py-0.5 text-xs font-bold <?= $r[6]==='pending'?'bg-yellow-100 text-yellow-700':'bg-green-100 text-green-700' ?>"><?= $r[6]==='pending'?'Pending':'✓ Result Received' ?></span>
                        <b class="mt-2 block"><?= esc($r[1]) ?>, <?= $r[2] ?>y</b>
                        <p class="text-xs font-semibold text-blue-600"><?= esc($r[3]) ?></p>
                        <p class="text-xs text-gray-500">→ <?= esc($r[4]) ?></p>
                        <?php if($r[6]!=='pending'): ?>
                            <p class="mt-1 text-xs font-semibold text-green-700">Result: <?= esc($r[7]) ?></p>
                        <?php endif; ?>
                        <p class="mt-1 text-xs text-gray-400"><?= esc($r[8]) ?> · <?= $r[5] ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php elseif ($tab === 'supplies'): ?>
            <div class="flex justify-between">
                <h2 class="text-xl font-bold">▣ Test Kit & Supply Inventory</h2>
                <a href="?tab=supplies&modal=supply" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600">＋ Add Supply</a>
            </div>
            <?php if($modal==='supply'): ?><form method="post" class="grid gap-2 rounded-xl border bg-white p-4 text-sm sm:grid-cols-3"><input type="hidden" name="action" value="save_lab_supply"><input required name="item_name" placeholder="Supply item" class="rounded border p-2"><input name="category" placeholder="Category" class="rounded border p-2"><input required type="number" min="0" name="quantity" placeholder="Quantity" class="rounded border p-2"><input required name="unit" value="kits" class="rounded border p-2"><input required type="number" min="0" name="reorder_level" placeholder="Reorder level" class="rounded border p-2"><input type="date" name="expiry_date" class="rounded border p-2"><button class="rounded bg-violet-600 p-2 font-bold text-white">Save Supply</button></form><?php endif; ?>
            
            <section class="grid grid-cols-3 gap-3">
                <?php foreach([['Critical',$critical,'text-red-600','bg-red-50'],['Low Stock',$low,'text-orange-600','bg-orange-50'],['Adequate',count($supplies)-$critical-$low,'text-green-600','bg-green-50']] as [$label,$value,$color,$bg]): ?>
                    <div class="<?= $bg ?> rounded-xl p-4">
                        <b class="text-2xl <?= $color ?>"><?= $value ?></b>
                        <p class="text-xs font-semibold"><?= $label ?></p>
                    </div>
                <?php endforeach; ?>
            </section>
            
            <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
                <table class="w-full min-w-[620px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Test Kit / Supply</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Reorder At</th>
                            <th>Expiry</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach($supplies as $s): ?>
                            <tr>
                                <td class="p-3 font-semibold"><?= esc($s[0]) ?></td>
                                <td><?= esc($s[1]) ?></td>
                                <td class="font-bold"><?= $s[2] ?> <small class="font-normal text-gray-400"><?= $s[3] ?></small></td>
                                <td><?= $s[4] ?> <?= $s[3] ?></td>
                                <td><?= $s[5] ?></td>
                                <td>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-bold <?= $s[6]==='critical'?'bg-red-100 text-red-700':($s[6]==='low'?'bg-orange-100 text-orange-700':'bg-green-100 text-green-700') ?>"><?= strtoupper($s[6]) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <h2 class="text-xl font-bold">▤ RDT Monthly Reports</h2>
            <?php
            $testCounts = [];
            foreach ($tests as $recordedTest) {
                $testCounts[$recordedTest[4]] = ($testCounts[$recordedTest[4]] ?? 0) + 1;
            }
            arsort($testCounts);
            $monthly = array_map(static fn($name, $count) => [$name, $count], array_keys($testCounts), array_values($testCounts));
            $total = array_sum(array_column($monthly, 1));
            $chartMaximum = $monthly ? max(array_column($monthly, 1)) : 1;
            $facilityCounts = [];
            foreach ($referrals as $recordedReferral) {
                $facilityCounts[$recordedReferral[4]] = ($facilityCounts[$recordedReferral[4]] ?? 0) + 1;
            }
            arsort($facilityCounts);
            ?>
            
            <section class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h3 class="mb-4 font-bold">Tests Performed — Database Records</h3>
                <?php if (!$monthly): ?><p class="text-sm text-gray-500">No diagnostic tests have been recorded yet.</p><?php endif; ?>
                <?php foreach($monthly as [$name,$count]): ?>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="w-40 text-sm text-gray-700"><?= $name ?></span>
                        <div class="h-2.5 flex-1 rounded-full bg-gray-100">
                            <div class="h-2.5 rounded-full bg-violet-500" style="width:<?= round($count / $chartMaximum * 100) ?>%"></div>
                        </div>
                        <b class="w-8 text-right text-sm"><?= $count ?></b>
                    </div>
                <?php endforeach; ?>
            </section>
            
            <section class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h3 class="mb-3 font-bold">Monthly Summary</h3>
                    <?php foreach([['Total RDTs Performed',$total],['Specimens Referred',count($referrals)],['Abnormal Results Flagged',$abnormal],['Supply Items Tracked',count($supplies)]] as [$label,$value]): ?>
                        <p class="flex justify-between border-b py-2 text-sm">
                            <span class="text-gray-600"><?= $label ?></span>
                            <b><?= $value ?></b>
                        </p>
                    <?php endforeach; ?>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h3 class="mb-3 font-bold">Referral Facilities</h3>
                    <?php if (!$facilityCounts): ?><p class="text-sm text-gray-500">No laboratory referrals have been recorded.</p><?php endif; ?>
                    <?php foreach ($facilityCounts as $facility => $facilityCount): ?><p class="mt-2 rounded-lg bg-gray-50 p-3 text-sm"><?= esc($facility) ?><b class="float-right text-blue-600"><?= (int)$facilityCount ?> referral(s)</b></p><?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <nav class="fixed bottom-0 left-0 right-0 z-50 flex border-t bg-white sm:hidden">
        <?php foreach($tabs as $id=>$label): ?>
            <a href="<?= tabUrl($id) ?>" class="flex flex-1 flex-col items-center py-2 text-[10px] font-semibold <?= $tab===$id?'text-violet-600':'text-gray-400' ?>">
                <?= esc($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

</body>
</html>
