<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$stType = strtoupper((string)($_SESSION['rhu_staff_login']['staff_type'] ?? ''));
if (empty($_SESSION['rhu_staff_login']) || ($stType !== 'MEDTECH' && !str_contains($stType, 'MEDTECH') && !str_contains($stType, 'TECHNOLOGIST'))) {
    header('Location: RHULogin.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/portal.php';
portalHandleNotificationApi($pdo);

function esc(mixed $v): string {
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function tabUrl(string $tab, array $extra = []): string {
    return '?' . http_build_query(array_merge(['tab' => $tab], $extra));
}

function flagClass(string $flag): string {
    return match($flag) {
        'normal' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'notable' => 'bg-sky-100 text-sky-800 border-sky-200',
        'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
        default => 'bg-rose-100 text-rose-800 border-rose-200'
    };
}

$tabs = [
    'overview'  => ['Overview', '⌂'],
    'rapid'     => ['Rapid Diagnostic Tests', '⚗'],
    'referrals' => ['Specimen Referrals', '↗'],
    'supplies'  => ['Test Kit Supplies', '📦'],
    'reports'   => ['Laboratory Reports', '📊'],
    'certificates' => ['Certificates', '🏅'],
];

$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab])) $tab = 'overview';

$modal = $_GET['modal'] ?? '';
$flashSuccess = $_SESSION['medtech_flash_success'] ?? '';
$flashError = $_SESSION['medtech_flash_error'] ?? '';
unset($_SESSION['medtech_flash_success'], $_SESSION['medtech_flash_error']);

$medtechStaffId = (int)($_SESSION['rhu_staff_login']['staff_id'] ?? 0);
$medtechUserId = (int)($_SESSION['rhu_staff_login']['id'] ?? $_SESSION['rhu_staff_login']['user_id'] ?? 0);

$medtechConsultations = [];
$consultationOptions = [];
$residentOptions = [];
$staffOptions = [];
$tests = [];
$referrals = [];
$supplies = [];
$medtechCertificateTypes = [];

// ----------------------------------------------------
// 1. POST FORM HANDLERS FOR MEDTECH DIAGNOSTICS & SUPPLIES
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($pdo)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'issue_certificate') {
        try {
            $issued = portalIssueResidentCertificate($pdo, $_POST, $medtechStaffId, 'Medical Technologist');
            $_SESSION['medtech_flash_success'] = "{$issued['type']} {$issued['number']} was issued and sent to the Resident.";
        } catch (Throwable $e) {
            $_SESSION['medtech_flash_error'] = 'Certificate Error: ' . $e->getMessage();
        }
        header('Location: ' . tabUrl('certificates')); exit;
    }

    // Action: Answer / Update Resident Laboratory Consultation
    if ($action === 'answer_consultation') {
        $cslId = (int)($_POST['consultation_id'] ?? 0);
        $resId = (int)($_POST['resident_id'] ?? 0);
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $notes = trim($_POST['consultation_notes'] ?? '');
        $status = trim($_POST['consultation_status'] ?? 'Completed');

        if ($cslId > 0 && !empty($pdo)) {
            try {
                $stmt = $pdo->prepare("UPDATE consultations SET diagnosis = :dx, consultation_notes = :notes, consultation_status = :st WHERE id = :id");
                $stmt->execute([
                    'dx' => $diagnosis,
                    'notes' => $notes,
                    'st' => $status,
                    'id' => $cslId
                ]);
                if ($resId > 0) {
                    portalNotifyResident($pdo, $resId, "Your Laboratory / Diagnostic request result has been updated by MedTech. Status: {$status}. Assessment: {$diagnosis}", "ResidentDashboard.php?tab=appointments");
                }
                $_SESSION['medtech_flash_success'] = 'Laboratory consultation updated and response sent to resident!';
            } catch (Exception $e) {
                $_SESSION['medtech_flash_error'] = 'Error updating consultation: ' . $e->getMessage();
            }
        }
        header('Location: ' . tabUrl('overview'));
        exit;
    }

    if ($action === 'save_test') {
        $consultationId = (int)($_POST['consultation_id'] ?? 0);
        $testName = trim($_POST['test_name'] ?? '');
        $testType = trim($_POST['test_type'] ?? '');
        $testDate = $_POST['test_date'] ?? date('Y-m-d');
        $results = trim($_POST['results'] ?? '');
        $testStatus = trim($_POST['test_status'] ?? 'Normal');
        $orderedById = (int)($_POST['ordered_by_id'] ?? 0) ?: null;

        if ($consultationId <= 0 || empty($testName)) {
            $_SESSION['medtech_flash_error'] = 'Please select a valid consultation and enter the test name.';
        } else {
            try {
                $statement = $pdo->prepare("INSERT INTO diagnostics (consultation_id, test_type, test_name, test_date, results, test_status, ordered_by_id) VALUES (?,?,?,?,?,?,?)");
                $statement->execute([$consultationId, $testType, $testName, $testDate, $results, $testStatus, $orderedById]);
                try {
                    $resStmt = $pdo->prepare("SELECT resident_id FROM consultations WHERE id = :cid LIMIT 1");
                    $resStmt->execute(['cid' => $consultationId]);
                    if ($resId = $resStmt->fetchColumn()) {
                        portalNotifyResident($pdo, (int)$resId, "Your diagnostic lab test result ({$testName}: {$testStatus}) is available.", "ResidentDashboard.php?tab=history");
                    }
                } catch (Throwable $tNotif) {}
                $_SESSION['medtech_flash_success'] = 'Rapid Diagnostic Test result saved successfully!';
            } catch (Exception $e) {
                $_SESSION['medtech_flash_error'] = 'Database Error: ' . $e->getMessage();
            }
        }
        header('Location: ' . tabUrl('rapid'));
        exit;
    }

    if ($action === 'save_lab_referral') {
        $residentId = (int)($_POST['resident_id'] ?? 0);
        $testRequested = trim($_POST['test_requested'] ?? '');
        $facility = trim($_POST['destination_facility'] ?? '');
        $referralDate = $_POST['referral_date'] ?? date('Y-m-d');
        $notes = trim($_POST['clinical_notes'] ?? '');
        $referredById = (int)($_POST['referred_by_id'] ?? 0) ?: null;

        if ($residentId <= 0 || empty($testRequested) || empty($facility)) {
            $_SESSION['medtech_flash_error'] = 'Please select a resident, specify the test requested, and destination facility.';
        } else {
            try {
                $statement = $pdo->prepare("INSERT INTO laboratory_referrals (resident_id, test_requested, destination_facility, referral_date, status, clinical_notes, referred_by_id) VALUES (?,?,?,?,?,?,?)");
                $statement->execute([$residentId, $testRequested, $facility, $referralDate, 'Pending', $notes, $referredById]);
                portalNotifyResident($pdo, $residentId, "Laboratory specimen referral created for {$testRequested} at {$facility}.", "ResidentDashboard.php?tab=history");
                $_SESSION['medtech_flash_success'] = 'Specimen referral recorded successfully!';
            } catch (Exception $e) {
                $_SESSION['medtech_flash_error'] = 'Database Error: ' . $e->getMessage();
            }
        }
        header('Location: ' . tabUrl('referrals'));
        exit;
    }

    if ($action === 'save_lab_supply') {
        $itemName = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? 'Laboratory');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'kits');
        $reorderLevel = (int)($_POST['reorder_level'] ?? 5);
        $expiryDate = $_POST['expiry_date'] ?: null;

        if (empty($itemName)) {
            $_SESSION['medtech_flash_error'] = 'Please enter a valid supply item name.';
        } else {
            try {
                $statement = $pdo->prepare("INSERT INTO laboratory_supplies (item_name, category, quantity, unit, reorder_level, expiry_date) VALUES (?,?,?,?,?,?)");
                $statement->execute([$itemName, $category, $quantity, $unit, $reorderLevel, $expiryDate]);
                $_SESSION['medtech_flash_success'] = 'Laboratory supply item added to inventory!';
            } catch (Exception $e) {
                $_SESSION['medtech_flash_error'] = 'Database Error: ' . $e->getMessage();
            }
        }
        header('Location: ' . tabUrl('supplies'));
        exit;
    }
}

// ----------------------------------------------------
// 2. LIVE DATABASE HYDRATION
// ----------------------------------------------------
if (!empty($pdo)) {
    try {
        $mtStmt = $pdo->prepare("
            SELECT c.id, c.resident_id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, r.barangay, c.consultation_date as date, c.consultation_notes, COALESCE(c.consultation_status, 'Scheduled') AS consultation_status
            FROM consultations c
            JOIN residents r ON c.resident_id = r.id
            LEFT JOIN staff doc_s ON c.physician_id = doc_s.id
            WHERE (c.physician_id = :sid OR doc_s.user_id = :uid OR c.chief_complaint LIKE '%Laboratory%' OR c.chief_complaint LIKE '%Blood%' OR c.chief_complaint LIKE '%MedTech%' OR c.chief_complaint LIKE '%RDT%')
            ORDER BY c.id DESC
        ");
        $mtStmt->execute(['sid' => $medtechStaffId, 'uid' => $medtechUserId]);
        $medtechConsultations = $mtStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {}

    try {
        $consultationOptions = $pdo->query("SELECT c.id, CONCAT(r.first_name, ' ', r.last_name) patient, c.chief_complaint FROM consultations c JOIN residents r ON r.id=c.resident_id ORDER BY c.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $residentOptions = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) name, barangay FROM residents ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $medtechCertificateTypes = portalEnsureCertificateTypes($pdo, ['Laboratory Result Certificate','Diagnostic Test Certificate','Medical Laboratory Clearance','Specimen Examination Certificate']);
        $staffOptions = $pdo->query("SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) name, s.staff_type FROM staff s JOIN users u ON u.id=s.user_id ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $testRows = $pdo->query("SELECT d.id, CONCAT(r.first_name, ' ', r.last_name) patient, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) age, r.barangay, d.test_name, d.results, d.test_status, d.test_type, CONCAT(u.first_name, ' ', u.last_name) ordered_by, d.test_date FROM diagnostics d JOIN consultations c ON c.id=d.consultation_id JOIN residents r ON r.id=c.resident_id LEFT JOIN staff s ON s.id=d.ordered_by_id LEFT JOIN users u ON u.id=s.user_id ORDER BY d.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($testRows as $row) {
            $status = strtolower((string)$row['test_status']);
            $flag = str_contains($status, 'abnormal') || str_contains($status, 'positive') ? 'abnormal' : (str_contains($status, 'pending') ? 'pending' : 'normal');
            $tests[] = ['RDT-' . $row['id'], $row['patient'], (int)$row['age'], $row['barangay'], $row['test_name'], $row['results'] ?: 'Pending', $flag, $row['test_type'] ?: 'Standard RDT', trim((string)$row['ordered_by']) ?: 'Attending Physician', $row['test_date']];
        }

        $referralRows = $pdo->query("SELECT lr.*, CONCAT(r.first_name, ' ', r.last_name) patient, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) age FROM laboratory_referrals lr JOIN residents r ON r.id=lr.resident_id ORDER BY lr.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($referralRows as $row) {
            $referrals[] = ['SPR-' . $row['id'], $row['patient'], (int)$row['age'], $row['test_requested'], $row['destination_facility'], $row['referral_date'], strtolower(str_replace(' ', '-', $row['status'])), $row['result_text'] ?: 'Awaiting', $row['clinical_notes'] ?: 'Routine specimen reference'];
        }

        $supplyRows = $pdo->query("SELECT * FROM laboratory_supplies ORDER BY item_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($supplyRows as $row) {
            $status = (int)$row['quantity'] <= 0 ? 'critical' : ((int)$row['quantity'] <= (int)$row['reorder_level'] ? 'low' : 'adequate');
            $supplies[] = [$row['item_name'], $row['category'] ?: 'Laboratory', (int)$row['quantity'], $row['unit'], (int)$row['reorder_level'], $row['expiry_date'], $status];
        }
    } catch (Exception $e) {}
}

$today = array_filter($tests, fn($r) => $r[9] === date('Y-m-d'));
$abnormal = count(array_filter($tests, fn($r) => in_array($r[6], ['abnormal', 'notable'])));
$pending = count(array_filter($referrals, fn($r) => $r[6] === 'pending'));
$critical = count(array_filter($supplies, fn($s) => $s[6] === 'critical'));
$low = count(array_filter($supplies, fn($s) => $s[6] === 'low'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medical Technologist Portal — RHU Rapid Diagnostics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .safe-area-pb { padding-bottom: env(safe-area-inset-bottom); }
    </style>
  <link rel="stylesheet" href="dashboard-enhancements.css?v=20260728-nurse-theme2">
    <script defer src="dashboard-enhancements.js?v=20260727-medtech2"></script>
</head>
<body class="nurse-palette-dashboard min-h-screen bg-slate-50 text-slate-900 selection:bg-violet-500 selection:text-white">
    <div class="min-h-screen flex flex-col">

        <!-- HEADER -->
<header class="sticky top-0 z-40 bg-gradient-to-r from-emerald-800 via-teal-800 to-cyan-900 text-white shadow-xl border-b border-emerald-700/40 backdrop-blur-md">
            <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/15 backdrop-blur-md rounded-2xl flex items-center justify-center text-2xl shadow-inner border border-white/20">
                        ⚗
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-base sm:text-lg font-extrabold tracking-tight">Medical Technologist Portal</h1>
                            <span class="inline-flex items-center gap-1 text-[11px] bg-violet-500/30 text-violet-200 px-2.5 py-0.5 rounded-full font-bold border border-violet-400/30 backdrop-blur-sm">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                Live Diagnostics
                            </span>
                        </div>
                        <p class="text-xs text-violet-200/90 font-medium">Nasugbu Rural Health Unit I — Medical Laboratory Division</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <?= portalRenderNotificationButton(); ?>
                    <div class="hidden sm:flex items-center gap-2 rounded-xl bg-white/10 px-3 py-1.5 text-xs font-semibold backdrop-blur-sm border border-white/10">
                        <span class="text-emerald-400">♥</span>
                        <span><?= esc($_SESSION['rhu_staff_login']['name'] ?? 'Medical Technologist') ?></span>
                    </div>
                    <a href="StaffLogout.php" data-staff-logout class="staff-logout-trigger flex items-center gap-1.5 rounded-xl bg-rose-600/80 hover:bg-rose-600 px-3 py-1.5 text-xs font-bold text-white transition-all shadow-sm" title="Log Out">
                        <span class="staff-logout-glyph" aria-hidden="true">↪</span>
                        <span>Log out</span>
                    </a>
                </div>
            </div>

            <!-- DESKTOP NAVIGATION TABS -->
            <nav class="hidden sm:flex gap-1 overflow-x-auto px-4 border-t border-white/10 bg-black/10 backdrop-blur-sm">
                <?php foreach($tabs as $id => [$label, $icon]): ?>
                    <a href="<?= tabUrl($id) ?>" class="flex items-center gap-2 rounded-t-xl px-4 py-2.5 text-xs font-bold transition-all border-t-2 <?= $tab === $id ? 'bg-white text-violet-900 border-violet-400 shadow-md' : 'text-violet-200 hover:bg-white/10 hover:text-white border-transparent' ?>">
                        <span class="text-base"><?= $icon ?></span>
                        <span><?= esc($label) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </header>

        <!-- MAIN CONTENT CONTAINER -->
        <main class="mx-auto w-full max-w-7xl flex-1 space-y-6 px-4 py-6 pb-24 sm:px-6">

            <!-- FLASH NOTIFICATIONS -->
            <?php if (!empty($flashSuccess)): ?>
                <div class="flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50/90 p-4 text-sm font-semibold text-emerald-900 shadow-sm backdrop-blur-sm">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white font-bold">✓</span>
                        <p><?= esc($flashSuccess) ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-950 font-bold px-2">✕</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($flashError)): ?>
                <div class="flex items-center justify-between rounded-2xl border border-rose-200 bg-rose-50/90 p-4 text-sm font-semibold text-rose-900 shadow-sm backdrop-blur-sm">
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rose-600 text-white font-bold">⚠</span>
                        <p><?= esc($flashError) ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-rose-700 hover:text-rose-950 font-bold px-2">✕</button>
                </div>
            <?php endif; ?>

            <!-- TAB 1: OVERVIEW -->
            <?php if ($tab === 'overview'): ?>

                <!-- Critical Stock Warning Banner -->
                <?php if ($critical > 0): ?>
                    <section class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-2xl border border-rose-300 bg-gradient-to-r from-rose-500 via-rose-600 to-red-600 p-5 text-white shadow-lg">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white/20 text-2xl backdrop-blur-sm">⚠</span>
                            <div>
                                <h3 class="text-base font-bold tracking-tight"><?= $critical ?> Test Kit / Laboratory Supply Item(s) Critically Low</h3>
                                <p class="text-xs text-rose-100 mt-0.5">
                                    Immediate resupply request required for: 
                                    <span class="font-bold underline decoration-rose-300">
                                        <?php 
                                        $critNames = [];
                                        foreach($supplies as $s) if($s[6] === 'critical') $critNames[] = $s[0];
                                        echo esc(implode(' · ', $critNames));
                                        ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <a href="<?= tabUrl('supplies') ?>" class="shrink-0 rounded-xl bg-white px-4 py-2 text-xs font-extrabold text-rose-700 shadow-md hover:bg-rose-50 transition-all">
                            Manage Inventory →
                        </a>
                    </section>
                <?php endif; ?>

                <!-- Quick KPI Summary Widgets -->
                <section class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-2xl border border-violet-100 bg-white p-5 shadow-sm transition-all hover:shadow-md hover:border-violet-300">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Today's RDTs</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-100 text-violet-700 font-bold">⚗</span>
                        </div>
                        <p class="mt-3 text-3xl font-black text-violet-900"><?= count($today) ?></p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Tests completed today</p>
                    </div>

                    <div class="rounded-2xl border border-rose-100 bg-white p-5 shadow-sm transition-all hover:shadow-md hover:border-rose-300">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Abnormal Flags</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-100 text-rose-700 font-bold">⚠️</span>
                        </div>
                        <p class="mt-3 text-3xl font-black text-rose-600"><?= $abnormal ?></p>
                        <p class="mt-1 text-xs font-semibold text-rose-600/80">Requires physician review</p>
                    </div>

                    <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm transition-all hover:shadow-md hover:border-sky-300">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Pending Referrals</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-sky-100 text-sky-700 font-bold">↗</span>
                        </div>
                        <p class="mt-3 text-3xl font-black text-sky-700"><?= $pending ?></p>
                        <p class="mt-1 text-xs font-semibold text-slate-500">External specimen referrals</p>
                    </div>

                    <div class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm transition-all hover:shadow-md hover:border-amber-300">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Low Stock Kits</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-100 text-amber-700 font-bold">📦</span>
                        </div>
                        <p class="mt-3 text-3xl font-black text-amber-600"><?= $critical + $low ?></p>
                        <p class="mt-1 text-xs font-semibold text-slate-500"><?= $critical ?> critical · <?= $low ?> low</p>
                    </div>
                </section>

                <!-- Live Laboratory Consultation Queue -->
                <section class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                                <span>⚗ Laboratory Consultation Queue</span>
                                <span class="rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-extrabold text-violet-800 border border-violet-200">
                                    <?= count($medtechConsultations) ?> Pending
                                </span>
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Assigned patients waiting for diagnostic lab requests or rapid diagnostic testing.</p>
                        </div>
                        <a href="?tab=rapid&modal=test" class="inline-flex items-center gap-1.5 rounded-xl bg-violet-700 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-violet-800 transition-all">
                            <span>＋</span>
                            <span>Record RDT Result</span>
                        </a>
                    </div>

                    <?php if (empty($medtechConsultations)): ?>
                        <div class="py-8 text-center">
                            <span class="text-3xl">🧪</span>
                            <p class="mt-2 text-sm font-semibold text-slate-600">No active laboratory requests assigned in queue.</p>
                            <p class="text-xs text-slate-400">New diagnostic requests from RHU doctors will appear here in real time.</p>
                        </div>
                    <?php else: ?>
                        <div class="mt-4 divide-y divide-slate-100 max-h-80 overflow-y-auto">
                            <?php foreach ($medtechConsultations as $mc): ?>
                                <div class="py-3.5 flex flex-col space-y-3 bg-slate-50/50 p-3 rounded-xl border border-slate-200/60 mb-2">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <b class="text-sm text-slate-900"><?= esc($mc['patientName']) ?></b>
                                                <span class="text-xs text-slate-500 font-medium">
                                                    (<?= esc($mc['age'] ?? 'N/A') ?>y • <?= esc($mc['gender'] ?? 'N/A') ?> • <?= esc($mc['barangay']) ?>)
                                                </span>
                                            </div>
                                            <p class="text-xs font-semibold text-violet-700">Complaint / Lab Request: <?= esc($mc['chiefComplaint']) ?></p>
                                            <p class="text-[11px] text-slate-400 font-mono">📅 Requested Date: <?= esc($mc['date']) ?></p>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <span class="rounded-lg bg-amber-50 px-2.5 py-1 text-[11px] font-extrabold text-amber-700 border border-amber-200">
                                                Status: <?= esc($mc['consultation_status']) ?>
                                            </span>
                                            <a href="?tab=rapid&modal=test" class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700 hover:bg-violet-600 hover:text-white transition-all">
                                                Perform RDT →
                                            </a>
                                        </div>
                                    </div>

                                    <!-- MEDTECH RESPONSE / UPDATE FORM -->
                                    <details class="group border-t border-violet-100 pt-2" open>
                                        <summary class="cursor-pointer text-xs font-bold text-violet-700 hover:text-violet-900 flex items-center justify-between py-1">
                                            <span>💬 Answer / Update Diagnostic Test Response for Resident</span>
                                        </summary>
                                        <form method="post" class="mt-2 bg-white p-3 rounded-xl border border-violet-200/70 space-y-2.5">
                                            <input type="hidden" name="action" value="answer_consultation">
                                            <input type="hidden" name="consultation_id" value="<?= (int)$mc['id']; ?>">
                                            <input type="hidden" name="resident_id" value="<?= (int)($mc['resident_id'] ?? 0); ?>">
                                            
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 mb-0.5">Diagnostic Result Summary</label>
                                                    <input type="text" name="diagnosis" value="<?= esc($mc['diagnosis'] ?? ''); ?>" placeholder="e.g. Fasting Blood Sugar: 95 mg/dL (Normal)" class="w-full p-2 border border-slate-300 rounded-lg text-xs outline-none focus:border-violet-500 bg-white" required>
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-bold text-slate-700 mb-0.5">Status</label>
                                                    <select name="consultation_status" class="w-full p-2 border border-slate-300 rounded-lg text-xs outline-none focus:border-violet-500 bg-white font-bold text-violet-900">
                                                        <option value="Completed" <?= ($mc['consultation_status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                        <option value="In Progress" <?= ($mc['consultation_status'] ?? '') === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                        <option value="Scheduled" <?= ($mc['consultation_status'] ?? '') === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                        <option value="Referred" <?= ($mc['consultation_status'] ?? '') === 'Referred' ? 'selected' : ''; ?>>Referred to Doctor</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-700 mb-0.5">MedTech Remarks &amp; Lab Notes for Resident</label>
                                                <textarea name="consultation_notes" rows="2" placeholder="Enter laboratory testing details, specimen notes, or collection instructions..." class="w-full p-2 border border-slate-300 rounded-lg text-xs outline-none focus:border-violet-500 bg-white resize-none"><?= esc($mc['consultation_notes'] ?? ''); ?></textarea>
                                            </div>

                                            <div class="flex justify-end pt-1">
                                                <button type="submit" class="px-4 py-2 bg-violet-700 hover:bg-violet-800 text-white text-xs font-extrabold rounded-lg shadow-sm transition-all flex items-center gap-1">
                                                    <span>✓</span> Save Response &amp; Notify Resident
                                                </button>
                                            </div>
                                        </form>
                                    </details>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- RHU Rapid Diagnostic Capabilities -->
                <section class="rounded-2xl border border-violet-200/70 bg-gradient-to-br from-violet-50 via-indigo-50/40 to-slate-50 p-6 shadow-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-lg">▣</span>
                        <h3 class="text-sm font-bold text-violet-950 uppercase tracking-wide">Available On-Site Rapid Diagnostic Tests (RDTs)</h3>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php 
                        $rdtCapabilities = [
                            'Blood Glucose (Glucometer)', 
                            'Urinalysis (10-Parameter Dipstick)', 
                            'HCG Pregnancy Test (Urine)', 
                            'HBsAg Rapid Test (Hepatitis B)', 
                            'HIV 1/2 Rapid Antibody', 
                            'Syphilis (VDRL/RPR) Rapid', 
                            'Dengue NS1 Antigen & IgG/IgM RDT', 
                            'Malaria Ag Rapid Diagnostic Test', 
                            'TB Sputum GeneXpert / AFB Collection'
                        ];
                        foreach($rdtCapabilities as $capability): 
                        ?>
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-violet-200 bg-white px-3 py-1.5 text-xs font-bold text-violet-800 shadow-sm hover:border-violet-400 transition-all">
                                <span class="w-1.5 h-1.5 rounded-full bg-violet-600"></span>
                                <?= $capability ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-3 text-xs text-violet-700/80 italic">
                        Note: Complex automated chemistry, full CBC counter, and bacterial cultures are processed via the Specimen Referrals module to designated secondary & tertiary hospital laboratories.
                    </p>
                </section>

                <!-- Today's RDT Results Widget -->
                <section class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <span>▤ Today's Completed Diagnostic Tests</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600"><?= count($today) ?> Total</span>
                        </h3>
                        <a href="<?= tabUrl('rapid') ?>" class="text-xs font-bold text-violet-700 hover:underline">View All Test Logs →</a>
                    </div>

                    <?php if (empty($today)): ?>
                        <p class="text-xs text-slate-400 italic py-4 text-center">No diagnostic test results recorded for today yet.</p>
                    <?php else: ?>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <?php foreach($today as $r): ?>
                                <div class="flex items-start gap-3 rounded-xl border <?= in_array($r[6], ['abnormal', 'notable']) ? 'border-rose-200 bg-rose-50/50' : 'border-slate-100 bg-slate-50/60' ?> p-3.5 shadow-2xs">
                                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full <?= in_array($r[6], ['abnormal', 'notable']) ? 'bg-rose-500 animate-pulse' : 'bg-emerald-500' ?>"></span>
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <b class="truncate text-xs text-slate-900 font-bold"><?= esc($r[1]) ?></b>
                                            <span class="rounded-md border px-2 py-0.5 text-[10px] font-extrabold <?= flagClass($r[6]) ?>">
                                                <?= ucfirst($r[6]) ?>
                                            </span>
                                        </div>
                                        <p class="text-xs font-semibold text-violet-700"><?= esc($r[4]) ?></p>
                                        <p class="text-xs font-bold text-slate-800">Result: <?= esc($r[5]) ?></p>
                                        <p class="text-[10px] text-slate-400">Ordered by: <?= esc($r[8]) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

            <!-- TAB 2: RAPID DIAGNOSTIC TESTS -->
            <?php elseif ($tab === 'rapid'): ?>

                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
                    <div>
                        <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                            <span>⚗ Rapid Diagnostic Test (RDT) Log</span>
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">Manage on-site rapid laboratory test findings and patient diagnostic reports.</p>
                    </div>
                    <a href="?tab=rapid&modal=test" class="inline-flex items-center gap-2 rounded-xl bg-violet-700 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-violet-800 transition-all">
                        <span>＋ Record RDT Result</span>
                    </a>
                </div>

                <!-- MODAL: RECORD RDT RESULT -->
                <?php if ($modal === 'test'): ?>
                    <section class="rounded-2xl border border-violet-200 bg-white p-6 shadow-xl space-y-4">
                        <div class="flex items-center justify-between border-b border-violet-100 pb-3">
                            <h3 class="text-base font-bold text-violet-950 flex items-center gap-2">
                                <span>＋ Record New Rapid Diagnostic Test (RDT)</span>
                            </h3>
                            <a href="?tab=rapid" class="text-xs font-bold text-slate-400 hover:text-slate-600">✕ Cancel</a>
                        </div>
                        <form method="post" class="grid gap-4 sm:grid-cols-3 text-xs">
                            <input type="hidden" name="action" value="save_test">

                            <div class="sm:col-span-2 space-y-1">
                                <label class="font-bold text-slate-700">Patient Consultation Request *</label>
                                <select required name="consultation_id" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                                    <option value="">Select Patient Consultation...</option>
                                    <?php foreach($consultationOptions as $o): ?>
                                        <option value="<?= (int)$o['id'] ?>"><?= esc($o['patient']) ?> — (<?= esc($o['chief_complaint']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Test Name / Kit *</label>
                                <input required name="test_name" placeholder="e.g. Blood Glucose, Dengue NS1" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Test Type / Reference Range</label>
                                <input required name="test_type" placeholder="e.g. Fasting Blood Sugar (70-99 mg/dL)" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Test Date *</label>
                                <input required type="date" name="test_date" value="<?= date('Y-m-d') ?>" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Diagnostic Result *</label>
                                <input required name="results" placeholder="e.g. 118 mg/dL (Elevated), Non-Reactive" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Result Flag / Status *</label>
                                <select name="test_status" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                                    <option value="Normal">Normal / Negative</option>
                                    <option value="Abnormal">Abnormal / Positive Flag</option>
                                    <option value="Pending">Pending Repeat Confirmation</option>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Ordering Physician / Staff</label>
                                <select name="ordered_by_id" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                                    <option value="">Select Ordering Staff...</option>
                                    <?php foreach($staffOptions as $o): ?>
                                        <option value="<?= (int)$o['id'] ?>"><?= esc($o['name']) ?> (<?= esc($o['staff_type']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="sm:col-span-3 flex justify-end gap-2 pt-2">
                                <a href="?tab=rapid" class="rounded-xl border border-slate-300 px-4 py-2 font-bold text-slate-600 hover:bg-slate-100">Cancel</a>
                                <button type="submit" class="rounded-xl bg-violet-700 px-5 py-2 font-bold text-white shadow-md hover:bg-violet-800">Save Diagnostic Result</button>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>

                <!-- Critical Clinical Notice -->
                <div class="rounded-2xl border border-amber-200 bg-amber-50/90 p-4 text-xs font-semibold text-amber-900 flex items-center gap-3">
                    <span class="text-base">⚠</span>
                    <p>Clinical Directive: All abnormal, positive, or critical diagnostic results must be immediately communicated to the attending MHO physician or requesting nurse for rapid clinical intervention.</p>
                </div>

                <!-- Test Search Bar -->
                <div class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-2xs">
                    <span class="text-slate-400">🔍</span>
                    <input type="text" id="rdtSearchInput" onkeyup="filterRDTs()" placeholder="Search test results by patient name, test type, or status..." class="w-full text-xs outline-none bg-transparent">
                </div>

                <!-- RDT Cards List -->
                <div id="rdtListContainer" class="space-y-3">
                    <?php if (empty($tests)): ?>
                        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center">
                            <span class="text-3xl">📋</span>
                            <p class="mt-2 text-sm font-bold text-slate-700">No RDT entries logged yet.</p>
                            <p class="text-xs text-slate-400">Click "Record RDT Result" to log a new diagnostic finding.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($tests as $r): ?>
                            <article class="rdt-item rounded-2xl border <?= in_array($r[6], ['abnormal', 'notable']) ? 'border-rose-200 bg-rose-50/30' : 'border-slate-200/80 bg-white' ?> p-4 shadow-sm hover:shadow-md transition-all">
                                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                    <div class="space-y-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-600 border border-slate-200"><?= esc($r[0]) ?></span>
                                            <span class="rounded-full border px-2.5 py-0.5 text-xs font-extrabold <?= flagClass($r[6]) ?>">
                                                <?= ucfirst($r[6]) ?>
                                            </span>
                                            <span class="text-xs text-slate-400">📅 <?= esc($r[9]) ?></span>
                                        </div>
                                        <h3 class="text-sm font-bold text-slate-900 mt-1">
                                            <?= esc($r[1]) ?>, <span class="font-medium text-slate-600"><?= $r[2] ?> yrs · <?= esc($r[3]) ?></span>
                                        </h3>
                                        <p class="text-xs font-bold text-violet-700"><?= esc($r[4]) ?></p>
                                        <p class="text-sm font-extrabold <?= $r[6] === 'normal' ? 'text-slate-800' : 'text-rose-600' ?>">
                                            Result: <?= esc($r[5]) ?> 
                                            <span class="text-xs font-normal text-slate-400">(Type/Ref: <?= esc($r[7]) ?>)</span>
                                        </p>
                                    </div>
                                    <div class="text-left sm:text-right shrink-0 text-xs text-slate-500">
                                        <p class="font-semibold text-slate-700">Ordered by:</p>
                                        <p><?= esc($r[8]) ?></p>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <script>
                function filterRDTs() {
                    const query = document.getElementById('rdtSearchInput').value.toLowerCase();
                    const items = document.querySelectorAll('.rdt-item');
                    items.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        item.style.display = text.includes(query) ? '' : 'none';
                    });
                }
                </script>

            <!-- TAB 3: SPECIMEN REFERRALS -->
            <?php elseif ($tab === 'referrals'): ?>

                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
                    <div>
                        <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                            <span>↗ Specimen Referrals & External Testing</span>
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">Track diagnostic specimens sent to higher-level partner hospital laboratories.</p>
                    </div>
                    <a href="?tab=referrals&modal=referral" class="inline-flex items-center gap-2 rounded-xl bg-sky-700 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-sky-800 transition-all">
                        <span>＋ New Referral</span>
                    </a>
                </div>

                <!-- MODAL: NEW REFERRAL -->
                <?php if ($modal === 'referral'): ?>
                    <section class="rounded-2xl border border-sky-200 bg-white p-6 shadow-xl space-y-4">
                        <div class="flex items-center justify-between border-b border-sky-100 pb-3">
                            <h3 class="text-base font-bold text-sky-950 flex items-center gap-2">
                                <span>＋ Create New Specimen Referral</span>
                            </h3>
                            <a href="?tab=referrals" class="text-xs font-bold text-slate-400 hover:text-slate-600">✕ Cancel</a>
                        </div>
                        <form method="post" class="grid gap-4 sm:grid-cols-3 text-xs">
                            <input type="hidden" name="action" value="save_lab_referral">

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Resident Patient *</label>
                                <select required name="resident_id" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none">
                                    <option value="">Select Resident Patient...</option>
                                    <?php foreach($residentOptions as $o): ?>
                                        <option value="<?= (int)$o['id'] ?>"><?= esc($o['name']) ?> (<?= esc($o['barangay']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Test Requested *</label>
                                <input required name="test_requested" placeholder="e.g. Full CBC with Platelet, Lipid Profile, HbA1c" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Destination Facility *</label>
                                <input required name="destination_facility" placeholder="e.g. Apacible Memorial District Hospital" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Referral Date *</label>
                                <input required type="date" name="referral_date" value="<?= date('Y-m-d') ?>" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Referring Staff</label>
                                <select name="referred_by_id" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none">
                                    <option value="">Select Referring Staff...</option>
                                    <?php foreach($staffOptions as $o): ?>
                                        <option value="<?= (int)$o['id'] ?>"><?= esc($o['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="space-y-1 sm:col-span-3">
                                <label class="font-bold text-slate-700">Clinical Notes / Reason for Referral</label>
                                <input name="clinical_notes" placeholder="Enter additional clinical details..." class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-sky-500 focus:ring-2 focus:ring-sky-200 outline-none">
                            </div>

                            <div class="sm:col-span-3 flex justify-end gap-2 pt-2">
                                <a href="?tab=referrals" class="rounded-xl border border-slate-300 px-4 py-2 font-bold text-slate-600 hover:bg-slate-100">Cancel</a>
                                <button type="submit" class="rounded-xl bg-sky-700 px-5 py-2 font-bold text-white shadow-md hover:bg-sky-800">Save Specimen Referral</button>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>

                <div class="grid grid-cols-3 gap-4">
                    <div class="rounded-2xl border border-amber-100 bg-white p-4 text-center shadow-sm">
                        <b class="text-2xl font-black text-amber-600"><?= $pending ?></b>
                        <p class="text-xs font-bold text-slate-500">Pending Results</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-100 bg-white p-4 text-center shadow-sm">
                        <b class="text-2xl font-black text-emerald-600"><?= count($referrals) - $pending ?></b>
                        <p class="text-xs font-bold text-slate-500">Results Received</p>
                    </div>
                    <div class="rounded-2xl border border-sky-100 bg-white p-4 text-center shadow-sm">
                        <b class="text-2xl font-black text-sky-700"><?= count($referrals) ?></b>
                        <p class="text-xs font-bold text-slate-500">Total Referrals</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <?php if (empty($referrals)): ?>
                        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center">
                            <span class="text-3xl">📤</span>
                            <p class="mt-2 text-sm font-bold text-slate-700">No specimen referrals logged.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($referrals as $r): ?>
                            <article class="rounded-2xl border <?= $r[6] === 'pending' ? 'border-amber-200 bg-amber-50/20' : 'border-slate-200/80 bg-white' ?> p-4 shadow-sm hover:shadow-md transition-all">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-600 border border-slate-200"><?= $r[0] ?></span>
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-extrabold <?= $r[6] === 'pending' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' ?>">
                                            <?= $r[6] === 'pending' ? 'Pending External Result' : '✓ Result Received' ?>
                                        </span>
                                    </div>
                                    <span class="text-xs text-slate-400">📅 Date: <?= $r[5] ?></span>
                                </div>
                                <div class="mt-3 space-y-1">
                                    <b class="text-sm text-slate-900"><?= esc($r[1]) ?>, <?= $r[2] ?> yrs</b>
                                    <p class="text-xs font-bold text-sky-700">Requested: <?= esc($r[3]) ?></p>
                                    <p class="text-xs font-semibold text-slate-600">→ Destination Facility: <?= esc($r[4]) ?></p>
                                    <?php if ($r[6] !== 'pending'): ?>
                                        <div class="mt-2 rounded-xl bg-emerald-50 p-2.5 text-xs font-bold text-emerald-800 border border-emerald-200">
                                            Result Findings: <?= esc($r[7]) ?>
                                        </div>
                                    <?php endif; ?>
                                    <p class="text-[11px] text-slate-400 mt-1">Notes: <?= esc($r[8]) ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            <!-- TAB 4: TEST SUPPLIES -->
            <?php elseif ($tab === 'supplies'): ?>

                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
                    <div>
                        <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                            <span>📦 Diagnostic Test Kit & Supply Inventory</span>
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">Track RDT test strip availability, reorder thresholds, and expiration dates.</p>
                    </div>
                    <a href="?tab=supplies&modal=supply" class="inline-flex items-center gap-2 rounded-xl bg-violet-700 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-violet-800 transition-all">
                        <span>＋ Add Supply Item</span>
                    </a>
                </div>

                <!-- MODAL: ADD SUPPLY ITEM -->
                <?php if ($modal === 'supply'): ?>
                    <section class="rounded-2xl border border-violet-200 bg-white p-6 shadow-xl space-y-4">
                        <div class="flex items-center justify-between border-b border-violet-100 pb-3">
                            <h3 class="text-base font-bold text-violet-950 flex items-center gap-2">
                                <span>＋ Add Laboratory Supply / Test Kit</span>
                            </h3>
                            <a href="?tab=supplies" class="text-xs font-bold text-slate-400 hover:text-slate-600">✕ Cancel</a>
                        </div>
                        <form method="post" class="grid gap-4 sm:grid-cols-3 text-xs">
                            <input type="hidden" name="action" value="save_lab_supply">

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Item Name *</label>
                                <input required name="item_name" placeholder="e.g. Glucometer Test Strips" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Category</label>
                                <input name="category" placeholder="e.g. Rapid Test Kits, Reagents" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Current Quantity *</label>
                                <input required type="number" min="0" name="quantity" placeholder="Quantity" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Unit of Measurement *</label>
                                <input required name="unit" value="kits" placeholder="kits, boxes, strips" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Reorder Alert Level *</label>
                                <input required type="number" min="0" name="reorder_level" value="10" placeholder="Reorder Level" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                            </div>

                            <div class="space-y-1">
                                <label class="font-bold text-slate-700">Expiration Date</label>
                                <input type="date" name="expiry_date" class="w-full rounded-xl border border-slate-300 p-2.5 text-xs focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none">
                            </div>

                            <div class="sm:col-span-3 flex justify-end gap-2 pt-2">
                                <a href="?tab=supplies" class="rounded-xl border border-slate-300 px-4 py-2 font-bold text-slate-600 hover:bg-slate-100">Cancel</a>
                                <button type="submit" class="rounded-xl bg-violet-700 px-5 py-2 font-bold text-white shadow-md hover:bg-violet-800">Add to Supply Inventory</button>
                            </div>
                        </form>
                    </section>
                <?php endif; ?>

                <div class="grid grid-cols-3 gap-4">
                    <div class="rounded-2xl border border-rose-100 bg-rose-50 p-4 text-center">
                        <b class="text-2xl font-black text-rose-600"><?= $critical ?></b>
                        <p class="text-xs font-bold text-rose-800">Critical Outages</p>
                    </div>
                    <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4 text-center">
                        <b class="text-2xl font-black text-amber-600"><?= $low ?></b>
                        <p class="text-xs font-bold text-amber-800">Low Stock Items</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-center">
                        <b class="text-2xl font-black text-emerald-600"><?= count($supplies) - $critical - $low ?></b>
                        <p class="text-xs font-bold text-emerald-800">Adequate Supplies</p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full min-w-[620px] text-xs">
                        <thead class="bg-slate-50 text-left uppercase text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="p-3.5 font-extrabold">Item Description</th>
                                <th class="p-3.5 font-extrabold">Category</th>
                                <th class="p-3.5 font-extrabold">In Stock</th>
                                <th class="p-3.5 font-extrabold">Reorder Threshold</th>
                                <th class="p-3.5 font-extrabold">Expiration Date</th>
                                <th class="p-3.5 font-extrabold">Stock Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($supplies as $s): ?>
                                <tr class="hover:bg-slate-50/80 transition-all">
                                    <td class="p-3.5 font-bold text-slate-900"><?= esc($s[0]) ?></td>
                                    <td class="p-3.5 text-slate-600"><?= esc($s[1]) ?></td>
                                    <td class="p-3.5 font-black text-slate-900"><?= $s[2] ?> <span class="font-normal text-slate-400"><?= esc($s[3]) ?></span></td>
                                    <td class="p-3.5 text-slate-600"><?= $s[4] ?> <?= esc($s[3]) ?></td>
                                    <td class="p-3.5 text-slate-600 font-mono"><?= $s[5] ?: 'N/A' ?></td>
                                    <td class="p-3.5">
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-extrabold <?= $s[6] === 'critical' ? 'bg-rose-100 text-rose-800 border border-rose-200' : ($s[6] === 'low' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200') ?>">
                                            <?= strtoupper($s[6]) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <!-- TAB 5: REPORTS -->
            <?php else: ?>

                <div class="border-b border-slate-200/80 pb-4">
                    <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <span>📊 Laboratory Diagnostic Reports & Analytics</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Statistical breakdown of RDT tests performed and referral trends.</p>
                </div>

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

                <section class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-900 text-sm">Diagnostic Test Volume Breakdown</h3>
                    <?php if (!$monthly): ?>
                        <p class="text-xs text-slate-400 italic">No diagnostic test records available for reporting.</p>
                    <?php endif; ?>

                    <div class="space-y-3">
                        <?php foreach($monthly as [$name, $count]): ?>
                            <div class="flex items-center gap-3">
                                <span class="w-48 text-xs font-bold text-slate-700 truncate"><?= esc($name) ?></span>
                                <div class="h-3 flex-1 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-3 rounded-full bg-violet-600 transition-all duration-500" style="width:<?= round($count / $chartMaximum * 100) ?>%"></div>
                                </div>
                                <b class="w-10 text-right text-xs text-slate-900 font-extrabold"><?= $count ?></b>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-3">
                        <h3 class="font-bold text-slate-900 text-sm border-b border-slate-100 pb-2">Monthly Activity Summary</h3>
                        <?php foreach([
                            ['Total RDTs Performed', $total],
                            ['External Specimen Referrals', count($referrals)],
                            ['Abnormal Diagnostic Flags', $abnormal],
                            ['Tracked Supply Items', count($supplies)]
                        ] as [$label, $value]): ?>
                            <div class="flex justify-between py-2 text-xs border-b border-slate-50">
                                <span class="text-slate-600 font-medium"><?= $label ?></span>
                                <b class="text-slate-900 font-extrabold text-sm"><?= $value ?></b>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-3">
                        <h3 class="font-bold text-slate-900 text-sm border-b border-slate-100 pb-2">Referral Facility Breakdown</h3>
                        <?php if (!$facilityCounts): ?>
                            <p class="text-xs text-slate-400 italic">No external laboratory referrals recorded.</p>
                        <?php endif; ?>

                        <?php foreach ($facilityCounts as $facility => $facilityCount): ?>
                            <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3 text-xs">
                                <span class="font-bold text-slate-800"><?= esc($facility) ?></span>
                                <b class="rounded-full bg-sky-100 px-2.5 py-0.5 text-xs text-sky-800 font-extrabold border border-sky-200"><?= (int)$facilityCount ?> Referral(s)</b>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

            <?php endif; ?>

            <?php if ($tab === 'certificates'): ?>
                <?= portalRenderCertificateIssuancePanel($pdo, $residentOptions, $medtechCertificateTypes, $medtechStaffId, 'violet') ?>
            <?php endif; ?>
        </main>

        <!-- MOBILE BOTTOM NAVIGATION -->
        <nav class="fixed bottom-0 left-0 right-0 z-50 flex border-t border-slate-200 bg-white/95 backdrop-blur-md sm:hidden safe-area-pb shadow-lg">
            <?php foreach($tabs as $id => [$label, $icon]): ?>
                <a href="<?= tabUrl($id) ?>" class="flex flex-1 flex-col items-center py-2 text-[10px] font-bold transition-all <?= $tab === $id ? 'text-violet-700 bg-violet-50/60' : 'text-slate-400 hover:text-slate-600' ?>">
                    <span class="text-base mb-0.5"><?= $icon ?></span>
                    <span><?= esc($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
<?= portalRenderNotificationPanel(); ?>
</body>
</html>
