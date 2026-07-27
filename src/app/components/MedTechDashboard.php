<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();

$stType = strtoupper((string) ($_SESSION['rhu_staff_login']['staff_type'] ?? ''));
if (empty($_SESSION['rhu_staff_login']) || ($stType !== 'MEDTECH' && !str_contains($stType, 'MEDTECH') && !str_contains($stType, 'TECHNOLOGIST'))) {
    header('Location: RHULogin.php');
    exit;
}

require_once __DIR__ . '/db.php';

function esc(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function tabUrl(string $tab, array $extra = []): string
{
    return '?' . http_build_query(array_merge(['tab' => $tab], $extra));
}

function flagClass(string $flag): string
{
    return match ($flag) {
        'normal' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'notable' => 'bg-sky-100 text-sky-800 border-sky-200',
        'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
        default => 'bg-rose-100 text-rose-800 border-rose-200'
    };
}

$tabs = [
    'overview' => ['Overview', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'],
    'rapid' => ['Rapid Diagnostic Tests', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2"/><path d="M8.5 2h7"/><path d="M7 16h10"/></svg>'],
    'referrals' => ['Specimen Referrals', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" x2="12" y1="2" y2="15"/></svg>'],
    'supplies' => ['Test Kit Supplies', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><polyline points="3.29 7 12 12 20.71 7"/><path d="m7.5 4.27 9 5.15"/></svg>'],
    'reports' => ['Laboratory Reports', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M7 16v-5"/><path d="M12 16V8"/><path d="M17 16v-3"/></svg>'],
];

$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab]))
    $tab = 'overview';

$modal = $_GET['modal'] ?? '';
$flashSuccess = $_SESSION['medtech_flash_success'] ?? '';
$flashError = $_SESSION['medtech_flash_error'] ?? '';
unset($_SESSION['medtech_flash_success'], $_SESSION['medtech_flash_error']);

$medtechStaffId = (int) ($_SESSION['rhu_staff_login']['staff_id'] ?? 0);
$medtechUserId = (int) ($_SESSION['rhu_staff_login']['id'] ?? $_SESSION['rhu_staff_login']['user_id'] ?? 0);

$medtechConsultations = [];
$consultationOptions = [];
$residentOptions = [];
$staffOptions = [];
$tests = [];
$referrals = [];
$supplies = [];

// ----------------------------------------------------
// 1. POST FORM HANDLERS FOR MEDTECH DIAGNOSTICS & SUPPLIES
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($pdo)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_test') {
        $consultationId = (int) ($_POST['consultation_id'] ?? 0);
        $testName = trim($_POST['test_name'] ?? '');
        $testType = trim($_POST['test_type'] ?? '');
        $testDate = $_POST['test_date'] ?? date('Y-m-d');
        $results = trim($_POST['results'] ?? '');
        $testStatus = trim($_POST['test_status'] ?? 'Normal');
        $orderedById = (int) ($_POST['ordered_by_id'] ?? 0) ?: null;

        if ($consultationId <= 0 || empty($testName)) {
            $_SESSION['medtech_flash_error'] = 'Please select a valid consultation and enter the test name.';
        } else {
            try {
                $statement = $pdo->prepare("INSERT INTO diagnostics (consultation_id, test_type, test_name, test_date, results, test_status, ordered_by_id) VALUES (?,?,?,?,?,?,?)");
                $statement->execute([$consultationId, $testType, $testName, $testDate, $results, $testStatus, $orderedById]);
                $_SESSION['medtech_flash_success'] = 'Rapid Diagnostic Test result saved successfully!';
            } catch (Exception $e) {
                $_SESSION['medtech_flash_error'] = 'Database Error: ' . $e->getMessage();
            }
        }
        header('Location: ' . tabUrl('rapid'));
        exit;
    }

    if ($action === 'save_lab_referral') {
        $residentId = (int) ($_POST['resident_id'] ?? 0);
        $testRequested = trim($_POST['test_requested'] ?? '');
        $facility = trim($_POST['destination_facility'] ?? '');
        $referralDate = $_POST['referral_date'] ?? date('Y-m-d');
        $notes = trim($_POST['clinical_notes'] ?? '');
        $referredById = (int) ($_POST['referred_by_id'] ?? 0) ?: null;

        if ($residentId <= 0 || empty($testRequested) || empty($facility)) {
            $_SESSION['medtech_flash_error'] = 'Please select a resident, specify the test requested, and destination facility.';
        } else {
            try {
                $statement = $pdo->prepare("INSERT INTO laboratory_referrals (resident_id, test_requested, destination_facility, referral_date, status, clinical_notes, referred_by_id) VALUES (?,?,?,?,?,?,?)");
                $statement->execute([$residentId, $testRequested, $facility, $referralDate, 'Pending', $notes, $referredById]);
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
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'kits');
        $reorderLevel = (int) ($_POST['reorder_level'] ?? 5);
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
            SELECT c.id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, r.barangay, c.consultation_date as date, c.consultation_notes
            FROM consultations c
            JOIN residents r ON c.resident_id = r.id
            LEFT JOIN staff doc_s ON c.physician_id = doc_s.id
            WHERE (c.physician_id = :sid OR doc_s.user_id = :uid OR c.chief_complaint LIKE '%Laboratory%' OR c.chief_complaint LIKE '%Blood%' OR c.chief_complaint LIKE '%MedTech%' OR c.chief_complaint LIKE '%RDT%')
            ORDER BY c.id DESC
        ");
        $mtStmt->execute(['sid' => $medtechStaffId, 'uid' => $medtechUserId]);
        $medtechConsultations = $mtStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
    }

    try {
        $consultationOptions = $pdo->query("SELECT c.id, CONCAT(r.first_name, ' ', r.last_name) patient, c.chief_complaint FROM consultations c JOIN residents r ON r.id=c.resident_id ORDER BY c.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $residentOptions = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) name, barangay FROM residents ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $staffOptions = $pdo->query("SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) name, s.staff_type FROM staff s JOIN users u ON u.id=s.user_id ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $testRows = $pdo->query("SELECT d.id, CONCAT(r.first_name, ' ', r.last_name) patient, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) age, r.barangay, d.test_name, d.results, d.test_status, d.test_type, CONCAT(u.first_name, ' ', u.last_name) ordered_by, d.test_date FROM diagnostics d JOIN consultations c ON c.id=d.consultation_id JOIN residents r ON r.id=c.resident_id LEFT JOIN staff s ON s.id=d.ordered_by_id LEFT JOIN users u ON u.id=s.user_id ORDER BY d.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($testRows as $row) {
            $status = strtolower((string) $row['test_status']);
            $flag = str_contains($status, 'abnormal') || str_contains($status, 'positive') ? 'abnormal' : (str_contains($status, 'pending') ? 'pending' : 'normal');
            $tests[] = ['RDT-' . $row['id'], $row['patient'], (int) $row['age'], $row['barangay'], $row['test_name'], $row['results'] ?: 'Pending', $flag, $row['test_type'] ?: 'Standard RDT', trim((string) $row['ordered_by']) ?: 'Attending Physician', $row['test_date']];
        }

        $referralRows = $pdo->query("SELECT lr.*, CONCAT(r.first_name, ' ', r.last_name) patient, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) age FROM laboratory_referrals lr JOIN residents r ON r.id=lr.resident_id ORDER BY lr.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($referralRows as $row) {
            $referrals[] = ['SPR-' . $row['id'], $row['patient'], (int) $row['age'], $row['test_requested'], $row['destination_facility'], $row['referral_date'], strtolower(str_replace(' ', '-', $row['status'])), $row['result_text'] ?: 'Awaiting', $row['clinical_notes'] ?: 'Routine specimen reference'];
        }

        $supplyRows = $pdo->query("SELECT * FROM laboratory_supplies ORDER BY item_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($supplyRows as $row) {
            $status = (int) $row['quantity'] <= 0 ? 'critical' : ((int) $row['quantity'] <= (int) $row['reorder_level'] ? 'low' : 'adequate');
            $supplies[] = [$row['item_name'], $row['category'] ?: 'Laboratory', (int) $row['quantity'], $row['unit'], (int) $row['reorder_level'], $row['expiry_date'], $status];
        }
    } catch (Exception $e) {
    }
}

$today = array_filter($tests, fn($r) => $r[9] === date('Y-m-d'));
$abnormal = count(array_filter($tests, fn($r) => in_array($r[6], ['abnormal', 'notable'])));
$pending = count(array_filter($referrals, fn($r) => $r[6] === 'pending'));
$critical = count(array_filter($supplies, fn($s) => $s[6] === 'critical'));
$low = count(array_filter($supplies, fn($s) => $s[6] === 'low'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Technologist Portal - ResiHUnity RHU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --rhu-teal: #0f766e;
            --rhu-aqua: #14b8a6;
            --rhu-ink: #0f172a;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at 4% 3%, rgba(20, 184, 166, .13), transparent 25rem),
                radial-gradient(circle at 96% 12%, rgba(14, 165, 233, .10), transparent 28rem),
                linear-gradient(155deg, #f8fffe 0%, #f8fafc 48%, #f5f9ff 100%);
            color: var(--rhu-ink);
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0 0 auto;
            z-index: 60;
            height: 3px;
            background: linear-gradient(90deg, #10b981, #14b8a6, #0ea5e9, #6366f1);
            pointer-events: none;
        }

        #scroll-progress {
            position: fixed;
            inset: 0 auto auto 0;
            z-index: 70;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, #34d399, #22d3ee, #60a5fa);
            box-shadow: 0 0 12px rgba(34, 211, 238, .65);
            transition: width 80ms linear;
        }

        .ambient-orb {
            position: fixed;
            z-index: -1;
            width: 20rem;
            height: 20rem;
            border-radius: 9999px;
            filter: blur(80px);
            opacity: .16;
            pointer-events: none;
            animation: orb-float 12s ease-in-out infinite alternate;
        }

        .ambient-orb-one {
            left: 8%;
            top: 16%;
            background: #2dd4bf;
        }

        .ambient-orb-two {
            right: 2%;
            bottom: 6%;
            background: #60a5fa;
            animation-delay: -5s;
        }

        @keyframes orb-float {
            from {
                transform: translate3d(-1rem, -1rem, 0) scale(.92);
            }

            to {
                transform: translate3d(2rem, 2rem, 0) scale(1.08);
            }
        }

        .sidebar-expanded {
            width: 16rem;
        }

        .sidebar-collapsed {
            width: 4.5rem !important;
        }

        .sidebar-collapsed .sidebar-label,
        .sidebar-collapsed .logo-title,
        .sidebar-collapsed .logo-mark {
            display: none !important;
        }

        .sidebar-collapsed .sidebar-item {
            justify-content: center !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            border-radius: 9999px;
        }

        .sidebar-collapsed .header-logo-container {
            justify-content: center;
            padding-left: .5rem;
            padding-right: .5rem;
        }

        .sidebar-collapsed .header-logo-inner {
            justify-content: center !important;
            width: 100%;
        }

        #sidebar {
            background: linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(240, 253, 250, .96) 55%, rgba(239, 246, 255, .96));
            border-color: rgba(153, 246, 228, .7);
            box-shadow: 12px 0 35px rgba(15, 23, 42, .06);
        }

        #sidebar .sidebar-item:hover {
            transform: translateX(3px);
            background: linear-gradient(90deg, rgba(204, 251, 241, .8), rgba(224, 242, 254, .58));
            color: var(--rhu-teal);
        }

        .nav-active {
            background-color: #ccfbf1 !important;
            color: #0f766e !important;
            font-weight: 700 !important;
        }

        .nav-active span {
            color: #0f766e !important;
        }

        header.sticky {
            background: rgba(255, 255, 255, .86) !important;
            border-color: rgba(153, 246, 228, .65) !important;
            box-shadow: 0 8px 30px rgba(15, 118, 110, .055);
            backdrop-filter: blur(18px);
        }

        .dashboard-card {
            transition: transform 220ms cubic-bezier(.2, .8, .2, 1), box-shadow 220ms ease, border-color 220ms ease;
        }

        .dashboard-card:hover {
            transform: translateY(-3px) scale(1.012);
            border-color: rgba(45, 212, 191, .75);
            box-shadow: 0 16px 35px rgba(15, 118, 110, .11);
            position: relative;
            z-index: 2;
        }

        button:not([disabled]),
        a[href] {
            transition: transform 180ms ease, box-shadow 180ms ease, background-color 180ms ease, color 180ms ease, border-color 180ms ease;
        }

        button:not([disabled]):active,
        a[href]:active {
            transform: scale(.97);
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #14b8a6 !important;
            box-shadow: 0 0 0 4px rgba(20, 184, 166, .12) !important;
        }

        .reveal-on-scroll {
            opacity: 0;
            transform: translateY(18px);
        }

        .reveal-on-scroll.is-visible {
            opacity: 1;
            transform: none;
            transition: opacity 500ms ease, transform 500ms cubic-bezier(.2, .8, .2, 1);
        }

        * {
            scrollbar-width: thin;
            scrollbar-color: #99f6e4 transparent;
        }

        @media (prefers-reduced-motion:reduce) {
            html {
                scroll-behavior: auto;
            }

            .reveal-on-scroll,
            .reveal-on-scroll.is-visible,
            .ambient-orb {
                animation: none;
                transition: none;
                transform: none;
                opacity: 1;
            }

            .dashboard-card:hover {
                transform: none;
            }
        }
    </style>
    <link rel="stylesheet" href="dashboard-enhancements.css">
    <script defer src="dashboard-enhancements.js?v=20260727-medtech2"></script>
</head>

<body
    class="min-h-screen text-slate-800 antialiased flex flex-col sm:flex-row selection:bg-teal-500 selection:text-white">
    <div id="scroll-progress" aria-hidden="true"></div>
    <div class="ambient-orb ambient-orb-one" aria-hidden="true"></div>
    <div class="ambient-orb ambient-orb-two" aria-hidden="true"></div>

    <aside id="sidebar"
        class="sidebar-expanded text-slate-700 transition-all duration-300 ease-in-out flex-shrink-0 sticky top-0 h-auto sm:h-screen z-40 flex flex-col justify-between border-r">
        <div>
            <div class="h-16 px-3 flex items-center border-b border-teal-100/60 header-logo-container">
                <div class="flex items-center gap-2.5 overflow-hidden w-full header-logo-inner">
                    <button type="button" onclick="toggleSidebar()"
                        class="p-2 rounded-full text-slate-600 hover:bg-teal-50 transition-colors shrink-0"
                        title="Toggle Menu"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 6h16M4 12h16M4 18h16" />
                        </svg></button>
                    <div class="logo-title flex items-center gap-2 min-w-0 flex-1 overflow-hidden">
                        <img src="resihunity_logo.jpg" alt=""
                            class="logo-mark h-9 w-9 object-contain object-left shrink-0 rounded-md" />
                        <div class="min-w-0 leading-tight">
                            <h1 class="text-sm font-extrabold text-slate-800 tracking-tight truncate">ResiHUnity</h1>
                            <p class="text-[9px] font-medium text-slate-400 truncate hidden lg:block">RHU MedTech Portal
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="py-3 pr-3 space-y-1 overflow-y-auto max-h-[calc(100vh-8rem)]">
                <?php foreach ($tabs as $id => [$label, $icon]): ?>
                    <a href="<?= esc(tabUrl($id)); ?>" title="<?= esc($label); ?>"
                        class="sidebar-item flex items-center gap-4 px-5 py-3 rounded-r-full text-xs font-semibold transition-all <?= $tab === $id ? 'nav-active' : 'text-slate-600 hover:text-slate-900'; ?>">
                        <span
                            class="leading-none shrink-0 text-center w-6 <?= $tab === $id ? 'text-teal-700' : 'text-slate-500'; ?>"><?= $icon; ?></span>
                        <span class="sidebar-label truncate text-xs"><?= esc($label); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="p-3 border-t border-teal-100/60 hidden sm:block">
            <a href="StaffLogout.php" data-staff-logout
                class="sidebar-item w-full flex items-center gap-4 px-4 py-2.5 rounded-r-full text-xs font-semibold text-rose-600 hover:bg-rose-50 transition-all"
                title="Log Out">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" x2="9" y1="12" y2="12" />
                </svg><span class="sidebar-label truncate">Log out</span>
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0 min-h-screen">
        <header class="sticky top-0 z-30 px-4 sm:px-8 py-3.5 flex items-center justify-between gap-4 border-b">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()"
                    class="sm:hidden p-2 rounded-full text-slate-600 hover:bg-teal-50"><svg class="w-6 h-6" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M4 6h16M4 12h16M4 18h16" />
                    </svg></button>
                <div>
                    <h1 class="text-base sm:text-lg font-bold text-slate-800 tracking-tight"><?= esc($tabs[$tab][0]); ?>
                    </h1>
                    <p class="text-xs text-slate-500 hidden sm:block font-medium">Nasugbu RHU I — Medical Laboratory
                        Division</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span
                    class="hidden md:inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-700">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span> Live Diagnostics
                </span>
                <div class="flex items-center gap-2.5 pl-3 border-l border-slate-200">
                    <div
                        class="w-9 h-9 rounded-full bg-gradient-to-br from-teal-600 to-sky-600 text-white font-bold text-xs flex items-center justify-center shadow-md">
                        <?= strtoupper(substr($_SESSION['rhu_staff_login']['name'] ?? 'M', 0, 1)) ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-slate-800 leading-tight">
                            <?= esc($_SESSION['rhu_staff_login']['name'] ?? 'Medical Technologist') ?></p>
                        <p class="text-[10px] text-slate-500 font-medium">Logged-in MedTech</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl w-full mx-auto p-4 sm:p-8 space-y-6 pb-28 sm:pb-12 flex-1">
            <?php if (!empty($flashSuccess)): ?>
                <div
                    class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-semibold text-emerald-800 flex items-center justify-between gap-2 shadow-sm">
                    <span class="flex items-center gap-2"><span
                            class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><svg
                                class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg></span><?= esc($flashSuccess) ?></span>
                    <button type="button" onclick="this.parentElement.remove()"
                        class="text-emerald-700 hover:text-emerald-950"><svg class="w-5 h-5" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($flashError)): ?>
                <div
                    class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800 flex items-center justify-between gap-2 shadow-sm">
                    <span class="flex items-center gap-2"><span
                            class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-rose-700"><svg
                                class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                                <path d="M12 9v4" />
                                <path d="M12 17h.01" />
                            </svg></span><?= esc($flashError) ?></span>
                    <button type="button" onclick="this.parentElement.remove()"
                        class="text-rose-700 hover:text-rose-950"><svg class="w-5 h-5" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg></button>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'overview'): ?>
                <div class="space-y-6">
                    <?php if ($critical > 0): ?>
                        <div
                            class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-rose-600 to-red-700 p-5 sm:p-6 text-white shadow-lg flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="flex items-start gap-4 relative z-10">
                                <span
                                    class="w-11 h-11 rounded-2xl bg-white/20 flex items-center justify-center shrink-0 border border-white/30 text-white"><svg
                                        class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                                        <path d="M12 9v4" />
                                        <path d="M12 17h.01" />
                                    </svg></span>
                                <div>
                                    <p class="font-extrabold text-base sm:text-lg tracking-tight"><?= $critical ?> Test Kit /
                                        Lab Supply Item(s) Critically Low</p>
                                    <p class="text-xs sm:text-sm text-rose-100 mt-1.5 font-medium">Immediate resupply required:
                                        <span
                                            class="font-bold underline decoration-rose-300"><?php $critNames = [];
                                            foreach ($supplies as $s)
                                                if ($s[6] === 'critical')
                                                    $critNames[] = $s[0];
                                            echo esc(implode(' · ', $critNames)); ?></span>
                                    </p>
                                </div>
                            </div>
                            <a href="<?= tabUrl('supplies') ?>"
                                class="relative z-10 text-xs bg-white hover:bg-rose-50 text-red-700 font-bold px-4 py-2.5 rounded-xl shadow-md shrink-0">Manage
                                Inventory</a>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                        <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span
                                    class="w-11 h-11 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center border border-teal-100"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path
                                            d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2" />
                                        <path d="M8.5 2h7" />
                                        <path d="M7 16h10" />
                                    </svg></span>
                                <span
                                    class="text-[11px] font-semibold text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-full border border-teal-200">Today</span>
                            </div>
                            <p class="text-3xl font-extrabold text-slate-800 mt-4"><?= count($today) ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Today's RDTs</p>
                            <p class="text-[11px] text-slate-400 font-medium">Tests completed today</p>
                        </div>
                        <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span
                                    class="w-11 h-11 rounded-xl bg-rose-50 text-rose-700 flex items-center justify-center border border-rose-100"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path
                                            d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                                        <path d="M12 9v4" />
                                        <path d="M12 17h.01" />
                                    </svg></span>
                                <span
                                    class="text-[11px] font-semibold text-rose-700 bg-rose-50 px-2.5 py-0.5 rounded-full border border-rose-200">Flags</span>
                            </div>
                            <p class="text-3xl font-extrabold text-slate-800 mt-4"><?= $abnormal ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Abnormal Flags</p>
                            <p class="text-[11px] text-slate-400 font-medium">Requires physician review</p>
                        </div>
                        <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span
                                    class="w-11 h-11 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center border border-sky-100"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8" />
                                        <polyline points="16 6 12 2 8 6" />
                                        <line x1="12" x2="12" y1="2" y2="15" />
                                    </svg></span>
                                <span
                                    class="text-[11px] font-semibold text-sky-700 bg-sky-50 px-2.5 py-0.5 rounded-full border border-sky-200">Pending</span>
                            </div>
                            <p class="text-3xl font-extrabold text-slate-800 mt-4"><?= $pending ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Pending Referrals</p>
                            <p class="text-[11px] text-slate-400 font-medium">External specimen referrals</p>
                        </div>
                        <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span
                                    class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center border border-amber-100"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path
                                            d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z" />
                                        <path d="M12 22V12" />
                                        <polyline points="3.29 7 12 12 20.71 7" />
                                        <path d="m7.5 4.27 9 5.15" />
                                    </svg></span>
                                <span
                                    class="text-[11px] font-semibold text-amber-700 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200">Stock</span>
                            </div>
                            <p class="text-3xl font-extrabold text-slate-800 mt-4"><?= $critical + $low ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Low Stock Kits</p>
                            <p class="text-[11px] text-slate-400 font-medium"><?= $critical ?> critical · <?= $low ?> low
                            </p>
                        </div>
                    </div>

                    <div class="dashboard-card bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-teal-500 animate-pulse"></span>
                                    Laboratory Consultation Queue
                                    <span
                                        class="rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-bold text-teal-800 border border-teal-200"><?= count($medtechConsultations) ?>
                                        Pending</span>
                                </h3>
                                <p class="text-xs text-slate-500 font-medium">Assigned patients waiting for diagnostic lab
                                    requests or RDT</p>
                            </div>
                            <a href="?tab=rapid&modal=test"
                                class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 transition-all flex items-center gap-1.5"><svg
                                    class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14" />
                                    <path d="M12 5v14" />
                                </svg> Record RDT Result</a>
                        </div>
                        <?php if (empty($medtechConsultations)): ?>
                            <div class="text-center py-10 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                <span
                                    class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg
                                        class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path
                                            d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2" />
                                        <path d="M8.5 2h7" />
                                        <path d="M7 16h10" />
                                    </svg></span>
                                <p class="text-sm font-semibold text-slate-700">No active laboratory requests in queue</p>
                                <p class="text-xs text-slate-400 mt-0.5">New diagnostic requests from RHU doctors will appear
                                    here.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3 max-h-80 overflow-y-auto">
                                <?php foreach ($medtechConsultations as $mc): ?>
                                    <div class="dashboard-card bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <p class="font-bold text-slate-800 text-sm"><?= esc($mc['patientName']); ?> <span
                                                        class="text-xs font-medium text-slate-500">(<?= esc($mc['age'] ?? 'N/A'); ?>y
                                                        · <?= esc($mc['gender'] ?? ''); ?>)</span></p>
                                                <p class="text-xs text-slate-600 mt-1">Chief Complaint: <span
                                                        class="font-normal"><?= esc($mc['chiefComplaint']); ?></span></p>
                                                <p class="text-[11px] text-slate-400 mt-0.5"><?= esc($mc['barangay'] ?? ''); ?> ·
                                                    <?= esc($mc['date'] ?? ''); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'rapid'): ?>
                <div class="space-y-4">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div>
                            <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path
                                        d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2" />
                                    <path d="M8.5 2h7" />
                                    <path d="M7 16h10" />
                                </svg> Rapid Diagnostic Tests</h2>
                            <p class="text-xs text-slate-500 font-medium">Record and review RDT results</p>
                        </div>
                        <a href="?tab=rapid&modal=test"
                            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 flex items-center gap-1.5"><svg
                                class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14" />
                                <path d="M12 5v14" />
                            </svg> Record RDT Result</a>
                    </div>

                    <?php if ($modal === 'test'): ?>
                        <div class="dashboard-card bg-white rounded-2xl p-6 border border-teal-200 shadow-sm space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2"><svg class="w-4 h-4"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" />
                                        <path d="M12 5v14" />
                                    </svg> Record New RDT Result</h3>
                                <a href="?tab=rapid"
                                    class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 6 6 18" />
                                        <path d="m6 6 12 12" />
                                    </svg></a>
                            </div>
                            <form method="post" class="grid gap-4 sm:grid-cols-2 text-xs">
                                <input type="hidden" name="action" value="save_test">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-700">Consultation *</label>
                                    <select required name="consultation_id"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none">
                                        <option value="">Select Consultation...</option>
                                        <?php foreach ($consultationOptions as $o): ?>
                                            <option value="<?= (int) $o['id'] ?>"><?= esc($o['patient']) ?> —
                                                <?= esc($o['chief_complaint']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-700">Test Name *</label>
                                    <input required name="test_name" placeholder="e.g. Dengue NS1, Malaria RDT"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-700">Test Type</label>
                                    <input name="test_type" placeholder="e.g. Rapid Antigen"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-700">Test Date *</label>
                                    <input required type="date" name="test_date" value="<?= date('Y-m-d') ?>"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-700">Status</label>
                                    <select name="test_status"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none">
                                        <option>Normal</option>
                                        <option>Abnormal</option>
                                        <option>Positive</option>
                                        <option>Negative</option>
                                        <option>Pending</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-700">Ordered By</label>
                                    <select name="ordered_by_id"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none">
                                        <option value="">Select Staff...</option>
                                        <?php foreach ($staffOptions as $o): ?>
                                            <option value="<?= (int) $o['id'] ?>"><?= esc($o['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="space-y-1 sm:col-span-2">
                                    <label class="font-bold text-slate-700">Results</label>
                                    <textarea name="results" rows="2" placeholder="Result findings..."
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none resize-none"></textarea>
                                </div>
                                <div class="sm:col-span-2 flex justify-end gap-2 pt-2">
                                    <a href="?tab=rapid"
                                        class="rounded-xl border border-slate-300 px-4 py-2 font-bold text-slate-600 hover:bg-slate-100">Cancel</a>
                                    <button type="submit"
                                        class="rounded-xl bg-teal-600 px-5 py-2 font-bold text-white shadow-md hover:bg-teal-700">Save
                                        RDT Result</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($tests)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span
                                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg
                                    class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path
                                        d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2" />
                                    <path d="M8.5 2h7" />
                                    <path d="M7 16h10" />
                                </svg></span>
                            <p class="text-sm font-semibold text-slate-700">No RDT records yet</p>
                            <p class="text-xs text-slate-400 mt-0.5">Recorded rapid diagnostic tests will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($tests as $t): ?>
                                <div class="dashboard-card bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-600 border border-slate-200"><?= esc($t[0]) ?></span>
                                            <span
                                                class="rounded-full px-2.5 py-0.5 text-xs font-bold border <?= flagClass($t[6]) ?>"><?= esc(strtoupper($t[6])) ?></span>
                                        </div>
                                        <span class="text-xs text-slate-400 inline-flex items-center gap-1"><svg class="w-3.5 h-3.5"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M8 2v4" />
                                                <path d="M16 2v4" />
                                                <rect width="18" height="18" x="3" y="4" rx="2" />
                                                <path d="M3 10h18" />
                                            </svg> <?= esc($t[9]) ?></span>
                                    </div>
                                    <div class="mt-2">
                                        <p class="font-bold text-slate-800 text-sm"><?= esc($t[1]) ?>, <?= (int) $t[2] ?> yrs ·
                                            <?= esc($t[3]) ?></p>
                                        <p class="text-xs font-bold text-teal-700 mt-1"><?= esc($t[4]) ?> <span
                                                class="text-slate-500 font-medium">(<?= esc($t[7]) ?>)</span></p>
                                        <p class="text-xs text-slate-600 mt-1">Results: <?= esc($t[5]) ?></p>
                                        <p class="text-[11px] text-slate-400 mt-0.5">Ordered by: <?= esc($t[8]) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'referrals'): ?>
                <div class="space-y-4">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div>
                            <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8" />
                                    <polyline points="16 6 12 2 8 6" />
                                    <line x1="12" x2="12" y1="2" y2="15" />
                                </svg> Specimen Referrals</h2>
                            <p class="text-xs text-slate-500 font-medium">External lab specimen referrals</p>
                        </div>
                        <a href="?tab=referrals&modal=referral"
                            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 flex items-center gap-1.5"><svg
                                class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14" />
                                <path d="M12 5v14" />
                            </svg> New Referral</a>
                    </div>

                    <?php if ($modal === 'referral'): ?>
                        <div class="dashboard-card bg-white rounded-2xl p-6 border border-sky-200 shadow-sm space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2"><svg class="w-4 h-4"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" />
                                        <path d="M12 5v14" />
                                    </svg> Create New Specimen Referral</h3>
                                <a href="?tab=referrals"
                                    class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 6 6 18" />
                                        <path d="m6 6 12 12" />
                                    </svg></a>
                            </div>
                            <form method="post" class="grid gap-4 sm:grid-cols-3 text-xs">
                                <input type="hidden" name="action" value="save_lab_referral">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-700">Resident Patient *</label>
                                    <select required name="resident_id"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none">
                                        <option value="">Select Resident...</option>
                                        <?php foreach ($residentOptions as $o): ?>
                                            <option value="<?= (int) $o['id'] ?>"><?= esc($o['name']) ?> (<?= esc($o['barangay']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-700">Test Requested *</label>
                                    <input required name="test_requested" placeholder="e.g. Full CBC, HbA1c"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-700">Destination Facility *</label>
                                    <input required name="destination_facility"
                                        placeholder="e.g. Apacible Memorial District Hospital"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-700">Referral Date *</label>
                                    <input required type="date" name="referral_date" value="<?= date('Y-m-d') ?>"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-700">Referring Staff</label>
                                    <select name="referred_by_id"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none">
                                        <option value="">Select Staff...</option>
                                        <?php foreach ($staffOptions as $o): ?>
                                            <option value="<?= (int) $o['id'] ?>"><?= esc($o['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="space-y-1 sm:col-span-3">
                                    <label class="font-bold text-slate-700">Clinical Notes</label>
                                    <input name="clinical_notes" placeholder="Additional clinical details..."
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none">
                                </div>
                                <div class="sm:col-span-3 flex justify-end gap-2 pt-2">
                                    <a href="?tab=referrals"
                                        class="rounded-xl border border-slate-300 px-4 py-2 font-bold text-slate-600 hover:bg-slate-100">Cancel</a>
                                    <button type="submit"
                                        class="rounded-xl bg-teal-600 px-5 py-2 font-bold text-white shadow-md hover:bg-teal-700">Save
                                        Specimen Referral</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="dashboard-card bg-white rounded-2xl p-4 text-center border border-slate-200 shadow-sm">
                            <b class="text-2xl font-extrabold text-amber-600"><?= $pending ?></b>
                            <p class="text-xs font-bold text-slate-500">Pending Results</p>
                        </div>
                        <div class="dashboard-card bg-white rounded-2xl p-4 text-center border border-slate-200 shadow-sm">
                            <b class="text-2xl font-extrabold text-emerald-600"><?= count($referrals) - $pending ?></b>
                            <p class="text-xs font-bold text-slate-500">Results Received</p>
                        </div>
                        <div class="dashboard-card bg-white rounded-2xl p-4 text-center border border-slate-200 shadow-sm">
                            <b class="text-2xl font-extrabold text-sky-700"><?= count($referrals) ?></b>
                            <p class="text-xs font-bold text-slate-500">Total Referrals</p>
                        </div>
                    </div>

                    <?php if (empty($referrals)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span
                                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg
                                    class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8" />
                                    <polyline points="16 6 12 2 8 6" />
                                    <line x1="12" x2="12" y1="2" y2="15" />
                                </svg></span>
                            <p class="text-sm font-semibold text-slate-700">No specimen referrals logged</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($referrals as $r): ?>
                                <div
                                    class="dashboard-card rounded-2xl border <?= $r[6] === 'pending' ? 'border-amber-200 bg-amber-50/20' : 'border-slate-200 bg-white' ?> p-4 shadow-sm">
                                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-600 border border-slate-200"><?= esc($r[0]) ?></span>
                                            <span
                                                class="rounded-full px-2.5 py-0.5 text-xs font-bold <?= $r[6] === 'pending' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' ?>"><?= $r[6] === 'pending' ? 'Pending External Result' : 'Result Received' ?></span>
                                        </div>
                                        <span class="text-xs text-slate-400 inline-flex items-center gap-1"><svg class="w-3.5 h-3.5"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M8 2v4" />
                                                <path d="M16 2v4" />
                                                <rect width="18" height="18" x="3" y="4" rx="2" />
                                                <path d="M3 10h18" />
                                            </svg> <?= esc($r[5]) ?></span>
                                    </div>
                                    <div class="mt-2 space-y-1">
                                        <p class="font-bold text-slate-800 text-sm"><?= esc($r[1]) ?>, <?= (int) $r[2] ?> yrs</p>
                                        <p class="text-xs font-bold text-teal-700">Requested: <?= esc($r[3]) ?></p>
                                        <p class="text-xs font-semibold text-slate-600">→ <?= esc($r[4]) ?></p>
                                        <?php if ($r[6] !== 'pending'): ?>
                                            <div
                                                class="mt-2 rounded-xl bg-emerald-50 p-2.5 text-xs font-bold text-emerald-800 border border-emerald-200">
                                                Result: <?= esc($r[7]) ?></div>
                                        <?php endif; ?>
                                        <p class="text-[11px] text-slate-400">Notes: <?= esc($r[8]) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'supplies'): ?>
                <div class="space-y-4">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div>
                            <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path
                                        d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z" />
                                    <path d="M12 22V12" />
                                    <polyline points="3.29 7 12 12 20.71 7" />
                                    <path d="m7.5 4.27 9 5.15" />
                                </svg> Test Kit & Supply Inventory</h2>
                            <p class="text-xs text-slate-500 font-medium">Track RDT strips, reorder thresholds, and expiry
                                dates</p>
                        </div>
                        <a href="?tab=supplies&modal=supply"
                            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 flex items-center gap-1.5"><svg
                                class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14" />
                                <path d="M12 5v14" />
                            </svg> Add Supply Item</a>
                    </div>

                    <?php if ($modal === 'supply'): ?>
                        <div class="dashboard-card bg-white rounded-2xl p-6 border border-teal-200 shadow-sm space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2"><svg class="w-4 h-4"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" />
                                        <path d="M12 5v14" />
                                    </svg> Add Laboratory Supply / Test Kit</h3>
                                <a href="?tab=supplies"
                                    class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 6 6 18" />
                                        <path d="m6 6 12 12" />
                                    </svg></a>
                            </div>
                            <form method="post" class="grid gap-4 sm:grid-cols-3 text-xs">
                                <input type="hidden" name="action" value="save_lab_supply">
                                <div class="space-y-1"><label class="font-bold text-slate-700">Item Name *</label><input
                                        required name="item_name" placeholder="e.g. Glucometer Test Strips"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none"></div>
                                <div class="space-y-1"><label class="font-bold text-slate-700">Category</label><input
                                        name="category" placeholder="e.g. Rapid Test Kits"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none"></div>
                                <div class="space-y-1"><label class="font-bold text-slate-700">Quantity *</label><input required
                                        type="number" min="0" name="quantity"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none"></div>
                                <div class="space-y-1"><label class="font-bold text-slate-700">Unit *</label><input required
                                        name="unit" value="kits"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none"></div>
                                <div class="space-y-1"><label class="font-bold text-slate-700">Reorder Level *</label><input
                                        required type="number" min="0" name="reorder_level" value="10"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none"></div>
                                <div class="space-y-1"><label class="font-bold text-slate-700">Expiration Date</label><input
                                        type="date" name="expiry_date"
                                        class="w-full rounded-xl border border-slate-300 p-2.5 text-xs outline-none"></div>
                                <div class="sm:col-span-3 flex justify-end gap-2 pt-2">
                                    <a href="?tab=supplies"
                                        class="rounded-xl border border-slate-300 px-4 py-2 font-bold text-slate-600 hover:bg-slate-100">Cancel</a>
                                    <button type="submit"
                                        class="rounded-xl bg-teal-600 px-5 py-2 font-bold text-white shadow-md hover:bg-teal-700">Add
                                        to Inventory</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="dashboard-card rounded-2xl border border-rose-100 bg-rose-50 p-4 text-center"><b
                                class="text-2xl font-extrabold text-rose-600"><?= $critical ?></b>
                            <p class="text-xs font-bold text-rose-800">Critical Outages</p>
                        </div>
                        <div class="dashboard-card rounded-2xl border border-amber-100 bg-amber-50 p-4 text-center"><b
                                class="text-2xl font-extrabold text-amber-600"><?= $low ?></b>
                            <p class="text-xs font-bold text-amber-800">Low Stock Items</p>
                        </div>
                        <div class="dashboard-card rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-center"><b
                                class="text-2xl font-extrabold text-emerald-600"><?= count($supplies) - $critical - $low ?></b>
                            <p class="text-xs font-bold text-emerald-800">Adequate Supplies</p>
                        </div>
                    </div>

                    <div class="dashboard-card overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <table class="w-full min-w-[620px] text-xs">
                            <thead class="bg-slate-50 text-left uppercase text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="p-3.5 font-bold">Item</th>
                                    <th class="p-3.5 font-bold">Category</th>
                                    <th class="p-3.5 font-bold">In Stock</th>
                                    <th class="p-3.5 font-bold">Reorder</th>
                                    <th class="p-3.5 font-bold">Expiry</th>
                                    <th class="p-3.5 font-bold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($supplies as $s): ?>
                                    <tr class="hover:bg-teal-50/40 transition-colors">
                                        <td class="p-3.5 font-bold text-slate-900"><?= esc($s[0]) ?></td>
                                        <td class="p-3.5 text-slate-600"><?= esc($s[1]) ?></td>
                                        <td class="p-3.5 font-bold text-slate-900"><?= $s[2] ?> <span
                                                class="font-normal text-slate-400"><?= esc($s[3]) ?></span></td>
                                        <td class="p-3.5 text-slate-600"><?= $s[4] ?>         <?= esc($s[3]) ?></td>
                                        <td class="p-3.5 text-slate-600 font-mono"><?= $s[5] ?: 'N/A' ?></td>
                                        <td class="p-3.5">
                                            <span
                                                class="rounded-full px-2.5 py-1 text-[11px] font-bold <?= $s[6] === 'critical' ? 'bg-rose-100 text-rose-800 border border-rose-200' : ($s[6] === 'low' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200') ?>"><?= strtoupper($s[6]) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'reports'): ?>
                <div class="space-y-4">
                    <div class="dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg
                                class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 3v16a2 2 0 0 0 2 2h16" />
                                <path d="M7 16v-5" />
                                <path d="M12 16V8" />
                                <path d="M17 16v-3" />
                            </svg> Laboratory Reports & Analytics</h2>
                        <p class="text-xs text-slate-500 font-medium">RDT volume and referral trends</p>
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
                    <div class="dashboard-card bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                        <h3 class="font-bold text-slate-800 text-sm">Diagnostic Test Volume Breakdown</h3>
                        <?php if (!$monthly): ?>
                            <p class="text-xs text-slate-400 italic">No diagnostic test records available.</p><?php endif; ?>
                        <div class="space-y-3">
                            <?php foreach ($monthly as [$name, $count]): ?>
                                <div class="flex items-center gap-3">
                                    <span class="w-48 text-xs font-bold text-slate-700 truncate"><?= esc($name) ?></span>
                                    <div class="h-3 flex-1 rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-3 rounded-full bg-teal-600 transition-all duration-500"
                                            style="width:<?= round($count / $chartMaximum * 100) ?>%"></div>
                                    </div>
                                    <b class="w-10 text-right text-xs text-slate-900 font-extrabold"><?= $count ?></b>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="dashboard-card bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-3">
                            <h3 class="font-bold text-slate-800 text-sm border-b border-slate-100 pb-2">Monthly Activity
                                Summary</h3>
                            <?php foreach ([['Total RDTs Performed', $total], ['External Specimen Referrals', count($referrals)], ['Abnormal Diagnostic Flags', $abnormal], ['Tracked Supply Items', count($supplies)]] as [$label, $value]): ?>
                                <div class="flex justify-between py-2 text-xs border-b border-slate-50">
                                    <span class="text-slate-600 font-medium"><?= $label ?></span>
                                    <b class="text-slate-900 font-extrabold text-sm"><?= $value ?></b>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="dashboard-card bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-3">
                            <h3 class="font-bold text-slate-800 text-sm border-b border-slate-100 pb-2">Referral Facility
                                Breakdown</h3>
                            <?php if (!$facilityCounts): ?>
                                <p class="text-xs text-slate-400 italic">No external laboratory referrals recorded.</p>
                            <?php endif; ?>
                            <?php foreach ($facilityCounts as $facility => $facilityCount): ?>
                                <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3 text-xs">
                                    <span class="font-bold text-slate-800"><?= esc($facility) ?></span>
                                    <b
                                        class="rounded-full bg-sky-100 px-2.5 py-0.5 text-xs text-sky-800 font-bold border border-sky-200"><?= (int) $facilityCount ?>
                                        Referral(s)</b>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <script>
        function toggleSidebar() {
            const s = document.getElementById('sidebar');
            if (s.classList.contains('sidebar-expanded')) { s.classList.remove('sidebar-expanded'); s.classList.add('sidebar-collapsed'); localStorage.setItem('mt_sidebar_collapsed', 'true'); }
            else { s.classList.remove('sidebar-collapsed'); s.classList.add('sidebar-expanded'); localStorage.setItem('mt_sidebar_collapsed', 'false'); }
        }
        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('mt_sidebar_collapsed') === 'true' && window.innerWidth >= 640) {
                const s = document.getElementById('sidebar'); if (s) { s.classList.remove('sidebar-expanded'); s.classList.add('sidebar-collapsed'); }
            }
            const sp = document.getElementById('scroll-progress');
            const upd = () => { if (!sp) return; const t = document.documentElement.scrollHeight - window.innerHeight; sp.style.width = (t > 0 ? Math.min((window.scrollY / t) * 100, 100) : 0) + '%'; };
            upd(); window.addEventListener('scroll', upd, { passive: true }); window.addEventListener('resize', upd);
            const items = document.querySelectorAll('.dashboard-card');
            if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                const obs = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('is-visible'); obs.unobserve(e.target); } }), { threshold: 0.08, rootMargin: '0px 0px -24px' });
                items.forEach((item, i) => { item.classList.add('reveal-on-scroll'); item.style.transitionDelay = (Math.min(i % 4, 3) * 55) + 'ms'; obs.observe(item); });
            } else items.forEach(i => i.classList.add('is-visible'));
        });
    </script>
</body>

</html>