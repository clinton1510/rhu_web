<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
$stType = strtoupper((string) ($_SESSION['rhu_staff_login']['staff_type'] ?? ''));
if (empty($_SESSION['rhu_staff_login']) || ($stType !== 'MIDWIFE' && !str_contains($stType, 'MIDWIFE'))) {
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

$tabs = [
    'overview' => ['Overview', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'],
    'maternal' => ['Maternal Health', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h.01"/><path d="M15 12h.01"/><path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5"/><path d="M19 6.3a9 9 0 0 1 1.8 3.9 2 2 0 0 1 0 3.6 9 9 0 0 1-17.6 0 2 2 0 0 1 0-3.6A9 9 0 0 1 12 3c2 0 3.5 1.1 3.5 2.5s-.9 2.5-2 2.5c-.8 0-1.5-.4-1.5-1"/></svg>'],
    'fp' => ['Family Planning', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>'],
    'immunization' => ['Immunization', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 2 4 4"/><path d="m17 7 3-3"/><path d="M19 9 8.7 19.3c-1 1-2.5 1-3.4 0l-.6-.6c-1-1-1-2.5 0-3.4L15 5"/><path d="m9 11 4 4"/><path d="m5 19-3 3"/><path d="m14 4 6 6"/></svg>'],
    'vital' => ['Vital Statistics', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>'],
    'referrals' => ['Referrals', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" x2="12" y1="2" y2="15"/></svg>'],
    'opd' => ['Prenatal OPD', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>'],
];

$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab]))
    $tab = 'overview';

$modal = $_GET['modal'] ?? '';
$flashSuccess = $_SESSION['midwife_flash_success'] ?? '';
$flashError = $_SESSION['midwife_flash_error'] ?? '';
unset($_SESSION['midwife_flash_success'], $_SESSION['midwife_flash_error']);

// ----------------------------------------------------
// 1. POST FORM HANDLERS FOR MIDWIFERY & PRENATAL CARE
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($pdo)) {
    $action = $_POST['action'] ?? '';

    // Action: Save New Maternal Pregnancy Case
    if ($action === 'save_maternal') {
        $residentId = (int) ($_POST['resident_id'] ?? 0);
        $gravida = (int) ($_POST['gravida'] ?? 1);
        $para = (int) ($_POST['para'] ?? 0);
        $lmp = trim($_POST['lmp'] ?? date('Y-m-d', strtotime('-3 months')));
        $edc = trim($_POST['edc'] ?? date('Y-m-d', strtotime('+6 months')));
        $highRisk = isset($_POST['high_risk']) ? 1 : 0;
        $riskFactors = trim($_POST['risk_factors'] ?? 'Routine Monitoring');
        $status = trim($_POST['pregnancy_status'] ?? 'Active');

        if ($residentId <= 0) {
            $_SESSION['midwife_flash_error'] = 'Please select a valid resident mother.';
        } else {
            try {
                $riskNotes = "G{$gravida}P{$para} - {$riskFactors}";
                $stmt = $pdo->prepare("INSERT INTO pregnancies (resident_id, last_menstrual_period, expected_delivery_date, risk_factors, high_risk, pregnancy_status, created_at) VALUES (:res, :lmp, :edc, :rf, :hr, :st, NOW())");
                $stmt->execute([
                    'res' => $residentId,
                    'lmp' => $lmp,
                    'edc' => $edc,
                    'rf' => $riskNotes,
                    'hr' => $highRisk,
                    'st' => $status
                ]);
                $_SESSION['midwife_flash_success'] = 'New Maternal Prenatal record saved successfully into database!';
            } catch (Exception $e) {
                $_SESSION['midwife_flash_error'] = 'Database Error: ' . $e->getMessage();
            }
        }
        header('Location: ' . tabUrl('maternal'));
        exit;
    }
}

// ----------------------------------------------------
// 2. LIVE MYSQL DATA HYDRATION FROM DATABASE `rhu`
// ----------------------------------------------------
$maternalCases = [];
$fpClients = [];
$immunizationRecords = [];
$vitalRecords = [];
$referralsList = [];
$prenatalOPDList = [];

$allMothersList = [];
$allStaffList = [];

if (!empty($pdo)) {
    try {
        // Dropdown options
        $allMothersList = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, barangay FROM residents WHERE gender LIKE 'Female%' ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $allStaffList = $pdo->query("SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) as name, s.staff_type FROM staff s JOIN users u ON s.user_id = u.id ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 1. Maternal Pregnancies
        $pStmt = $pdo->query("
            SELECT p.id, CONCAT(r.first_name, ' ', r.last_name) as name, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, r.barangay, r.blood_type as bloodType, p.last_menstrual_period as lmp, p.expected_delivery_date as edc, p.risk_factors as risks, p.high_risk as highRisk, p.pregnancy_status as status, DATE_ADD(p.expected_delivery_date, INTERVAL -1 MONTH) as nextVisit
            FROM pregnancies p
            JOIN residents r ON p.resident_id = r.id
            ORDER BY p.id DESC
        ");
        $maternalCases = $pStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 2. Immunization Records
        $immStmt = $pdo->query("
            SELECT vr.id, CONCAT(r.first_name, ' ', r.last_name) as childName, TIMESTAMPDIFF(MONTH, r.date_of_birth, CURDATE()) as ageMonths, r.barangay, sch.vaccine_name as vaccineName, sch.age_group as targetAge, vr.vaccination_date as dateGiven, vr.next_dose_date as nextVisit, vr.batch_number as lot
            FROM vaccination_records vr
            JOIN residents r ON vr.resident_id = r.id
            JOIN immunization_schedules sch ON vr.vaccine_id = sch.id
            ORDER BY vr.id DESC
        ");
        $immunizationRecords = $immStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 3. Prenatal OPD Consultations (Filtered by assigned staff)
        $midwifeStaffId = (int) ($_SESSION['rhu_staff_login']['staff_id'] ?? 0);
        $midwifeUserId = (int) ($_SESSION['rhu_staff_login']['id'] ?? 0);

        if ($midwifeStaffId > 0) {
            $opdStmt = $pdo->prepare("
                SELECT c.id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, c.icd_code as icd10, c.medications_prescribed as medications, r.barangay, c.consultation_date as date, c.referral_needed, c.referral_to, c.consultation_notes
                FROM consultations c
                JOIN residents r ON c.resident_id = r.id
                LEFT JOIN staff doc_s ON c.physician_id = doc_s.id
                WHERE (c.physician_id = :sid OR doc_s.user_id = :uid OR c.chief_complaint LIKE '%Prenatal%' OR c.chief_complaint LIKE '%Maternal%' OR c.chief_complaint LIKE '%Midwife%')
                ORDER BY c.id DESC
            ");
            $opdStmt->execute(['sid' => $midwifeStaffId, 'uid' => $midwifeUserId]);
        } else {
            $opdStmt = $pdo->query("
                SELECT c.id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, c.icd_code as icd10, c.medications_prescribed as medications, r.barangay, c.consultation_date as date, c.referral_needed, c.referral_to, c.consultation_notes
                FROM consultations c
                JOIN residents r ON c.resident_id = r.id
                ORDER BY c.id DESC
            ");
        }
        $prenatalOPDList = $opdStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } catch (Exception $e) {
        error_log("MidwifeDashboard DB Load Error: " . $e->getMessage());
    }
}

// Clean live calculations
$activePrenatalCount = count(array_filter($maternalCases, fn($m) => ($m['status'] ?? '') === 'Active' || ($m['status'] ?? '') === 'active_prenatal'));
$highRiskCount = count(array_filter($maternalCases, fn($m) => !empty($m['highRisk'])));
$postpartumCount = count(array_filter($maternalCases, fn($m) => strtolower($m['status'] ?? '') === 'postpartum'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Midwife Portal - ResiHUnity RHU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        :root {
            --rhu-rose: #0f766e;
            --rhu-aqua: #14b8a6;
            --rhu-ink: #0f172a;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at 4% 3%, rgba(20, 184, 166, .13), transparent 25rem),
                radial-gradient(circle at 96% 12%, rgba(14, 165, 233, .08), transparent 28rem),
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
        .sidebar-collapsed .logo-mark,
        .sidebar-collapsed .logo-icon-collapsed {
            display: none !important;
        }

        .sidebar-collapsed .header-logo-inner {
            justify-content: center !important;
            width: 100%;
        }


        .sidebar-collapsed .sidebar-item {
            justify-content: center !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            border-radius: 9999px;
        }

        .sidebar-collapsed .header-logo-container {
            justify-content: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        #sidebar {
            background: linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(240, 253, 250, .96) 55%, rgba(239, 246, 255, .96));
            border-color: rgba(153, 246, 228, .7);
            box-shadow: 12px 0 35px rgba(15, 23, 42, .06);
        }

        #sidebar .sidebar-item:hover {
            transform: translateX(3px);
            background: linear-gradient(90deg, rgba(204, 251, 241, .8), rgba(224, 242, 254, .58));
            color: var(--rhu-rose);
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
            border-color: #ec4899 !important;
            box-shadow: 0 0 0 4px rgba(20, 184, 166, .13) !important;
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
    <script defer src="dashboard-enhancements.js?v=20260726-controls3"></script>
</head>

<body
    class="min-h-screen text-slate-800 antialiased flex flex-col sm:flex-row selection:bg-teal-500 selection:text-white">
    <div id="scroll-progress" aria-hidden="true"></div>
    <div class="ambient-orb ambient-orb-one" aria-hidden="true"></div>
    <div class="ambient-orb ambient-orb-two" aria-hidden="true"></div>

    <aside id="sidebar"
        class="sidebar-expanded text-slate-700 transition-all duration-300 ease-in-out flex-shrink-0 sticky top-0 h-auto sm:h-screen z-40 flex flex-col justify-between border-r">
        <div>
            <div class="h-16 px-4 flex items-center justify-between border-b border-teal-100/60 header-logo-container">
                <div class="flex items-center gap-2.5 overflow-hidden w-full header-logo-inner">
                    <button onclick="toggleSidebar()"
                        class="p-2 rounded-full text-slate-600 hover:bg-teal-50 transition-colors"
                        title="Toggle Menu"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 6h16M4 12h16M4 18h16" />
                        </svg></button>
                    <div class="logo-title truncate flex items-center gap-2 min-w-0">
                        <img src="resihunity_logo.jpg" alt=""
                            class="logo-mark h-9 w-9 object-contain object-left shrink-0 rounded-md" />
                        <div class="min-w-0 leading-tight">
                            <h1 class="text-sm font-extrabold text-slate-800 tracking-tight truncate">ResiHUnity</h1>
                            <p class="text-[9px] font-medium text-slate-400 truncate hidden lg:block">RHU Midwife Portal
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
                    <p class="text-xs text-slate-500 hidden sm:block font-medium">Nasugbu RHU I — Maternal, Child Health
                        &amp; Family Planning</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span
                    class="hidden md:inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-700">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span> Live Database Sync
                </span>
                <div class="flex items-center gap-2.5 pl-3 border-l border-slate-200">
                    <div
                        class="w-9 h-9 rounded-full bg-gradient-to-br from-teal-600 to-sky-600 text-white font-bold text-xs flex items-center justify-center shadow-md">
                        <?= strtoupper(substr($_SESSION['rhu_staff_login']['name'] ?? 'M', 0, 1)) ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-slate-800 leading-tight">
                            <?= esc($_SESSION['rhu_staff_login']['name'] ?? 'Rural Health Midwife') ?></p>
                        <p class="text-[10px] text-slate-500 font-medium">Logged-in Midwife</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl w-full mx-auto p-4 sm:p-8 space-y-6 pb-28 sm:pb-12 flex-1">
            <?php if ($flashSuccess): ?>
                <div
                    class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-semibold text-emerald-800 flex items-center gap-2 shadow-sm">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><svg
                            class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5" />
                        </svg></span>
                    <?= esc($flashSuccess); ?>
                </div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div
                    class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800 flex items-center gap-2 shadow-sm">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-rose-700"><svg
                            class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                            <path d="M12 9v4" />
                            <path d="M12 17h.01" />
                        </svg></span>
                    <?= esc($flashError); ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'overview'): ?>
                <div class="space-y-6">
                    <?php if ($highRiskCount > 0): ?>
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
                                    <p class="font-extrabold text-base sm:text-lg tracking-tight">High-Risk Pregnancy Alert —
                                        Immediate Follow-Up</p>
                                    <p class="text-xs sm:text-sm text-rose-100 mt-1.5 font-medium"><?= $highRiskCount; ?>
                                        high-risk expectant mothers identified. Hospital delivery referral &amp; blood donor
                                        standby required.</p>
                                </div>
                            </div>
                            <a href="<?= esc(tabUrl('maternal')); ?>"
                                class="relative z-10 text-xs bg-white hover:bg-rose-50 text-red-700 font-bold px-4 py-2.5 rounded-xl shadow-md shrink-0">Review
                                Cases</a>
                        </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                        <a href="<?= esc(tabUrl('maternal')); ?>"
                            class="dashboard-card group bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between"><span
                                    class="w-11 h-11 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center border border-teal-100"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 12h.01" />
                                        <path d="M15 12h.01" />
                                        <path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5" />
                                        <path
                                            d="M19 6.3a9 9 0 0 1 1.8 3.9 2 2 0 0 1 0 3.6 9 9 0 0 1-17.6 0 2 2 0 0 1 0-3.6A9 9 0 0 1 12 3c2 0 3.5 1.1 3.5 2.5s-.9 2.5-2 2.5c-.8 0-1.5-.4-1.5-1" />
                                    </svg></span><span
                                    class="text-[11px] font-semibold text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-full border border-teal-200">Active</span>
                            </div>
                            <p
                                class="text-3xl font-extrabold text-slate-800 mt-4 group-hover:text-teal-700 transition-colors">
                                <?= $activePrenatalCount; ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Active Prenatal Cases</p>
                            <p class="text-[11px] text-slate-400 font-medium">Ongoing Checkups</p>
                        </a>
                        <a href="<?= esc(tabUrl('maternal')); ?>"
                            class="dashboard-card group bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between"><span
                                    class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center border border-rose-100"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path
                                            d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                                        <path d="M12 9v4" />
                                        <path d="M12 17h.01" />
                                    </svg></span><span
                                    class="text-[11px] font-semibold text-rose-700 bg-rose-50 px-2.5 py-0.5 rounded-full border border-rose-200">High
                                    Risk</span></div>
                            <p
                                class="text-3xl font-extrabold text-slate-800 mt-4 group-hover:text-rose-700 transition-colors">
                                <?= $highRiskCount; ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">High-Risk Mothers</p>
                            <p class="text-[11px] text-slate-400 font-medium">Special Care Required</p>
                        </a>
                        <a href="<?= esc(tabUrl('maternal')); ?>"
                            class="dashboard-card group bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between"><span
                                    class="w-11 h-11 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center border border-sky-100"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path
                                            d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
                                    </svg></span><span
                                    class="text-[11px] font-semibold text-sky-700 bg-sky-50 px-2.5 py-0.5 rounded-full border border-sky-200">Follow-up</span>
                            </div>
                            <p
                                class="text-3xl font-extrabold text-slate-800 mt-4 group-hover:text-sky-700 transition-colors">
                                <?= $postpartumCount; ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Postpartum Mothers</p>
                            <p class="text-[11px] text-slate-400 font-medium">Post-natal Care</p>
                        </a>
                        <a href="<?= esc(tabUrl('fp')); ?>"
                            class="dashboard-card group bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between"><span
                                    class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center border border-indigo-100"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z" />
                                        <path d="m8.5 8.5 7 7" />
                                    </svg></span><span
                                    class="text-[11px] font-semibold text-indigo-700 bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-200">FP
                                    Registry</span></div>
                            <p
                                class="text-3xl font-extrabold text-slate-800 mt-4 group-hover:text-indigo-700 transition-colors">
                                <?= count($fpClients); ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Family Planning Clients</p>
                            <p class="text-[11px] text-slate-400 font-medium">Contraceptive Supply</p>
                        </a>
                    </div>
                    <div class="dashboard-card bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h3 class="font-bold text-slate-800 text-base flex items-center gap-2"><span
                                        class="w-2.5 h-2.5 rounded-full bg-teal-500 animate-pulse"></span> Received Resident
                                    Prenatal Consultations</h3>
                                <p class="text-xs text-slate-500 font-medium">Live consultation requests for Midwifery &amp;
                                    Prenatal Care</p>
                            </div>
                            <a href="<?= esc(tabUrl('maternal', ['modal' => 'new_maternal'])); ?>"
                                class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 transition-all flex items-center gap-1.5"><svg
                                    class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14" />
                                    <path d="M12 5v14" />
                                </svg> New Prenatal Case</a>
                        </div>
                        <?php if (empty($prenatalOPDList)): ?>
                            <div class="text-center py-10 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                <span
                                    class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg
                                        class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
                                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                                        <path d="M12 11h4" />
                                        <path d="M12 16h4" />
                                        <path d="M8 11h.01" />
                                        <path d="M8 16h.01" />
                                    </svg></span>
                                <p class="text-sm font-semibold text-slate-700">No Prenatal Consultations Assigned Yet</p>
                                <p class="text-xs text-slate-400 mt-0.5">When expectant mothers book appointments, they will
                                    appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach (array_slice($prenatalOPDList, 0, 5) as $opd): ?>
                                    <div class="dashboard-card bg-white rounded-xl p-4 border border-slate-200 shadow-sm space-y-2">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="font-bold text-slate-800 text-sm sm:text-base">
                                                        <?= esc($opd['patientName']); ?></p>
                                                    <span
                                                        class="text-xs font-semibold text-teal-800 bg-teal-50 px-2 py-0.5 rounded-md border border-teal-100"><?= esc($opd['age'] ?? 'N/A'); ?>y
                                                        · <?= esc($opd['gender']); ?></span>
                                                    <span
                                                        class="text-xs font-medium text-slate-600 bg-slate-50 px-2 py-0.5 rounded-md border border-slate-200 inline-flex items-center gap-1"><svg
                                                            class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path
                                                                d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
                                                            <circle cx="12" cy="10" r="3" />
                                                        </svg> <?= esc($opd['barangay']); ?></span>
                                                </div>
                                                <p class="text-xs font-semibold text-slate-700 mt-1">Chief Complaint: <span
                                                        class="text-slate-600 font-normal"><?= esc($opd['chiefComplaint']); ?></span>
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <span
                                                    class="font-mono text-xs bg-teal-50 text-teal-900 font-semibold px-2.5 py-1 rounded-lg border border-teal-200"><?= esc($opd['icd10'] ?: 'Z34.8'); ?></span>
                                                <p
                                                    class="text-[10px] font-medium text-slate-400 mt-1 inline-flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M8 2v4" />
                                                        <path d="M16 2v4" />
                                                        <rect width="18" height="18" x="3" y="4" rx="2" />
                                                        <path d="M3 10h18" />
                                                    </svg> <?= esc($opd['date']); ?></p>
                                            </div>
                                        </div>
                                        <div
                                            class="text-xs text-slate-600 font-mono bg-slate-50 p-2.5 rounded-xl border border-slate-200/60">
                                            <?= esc($opd['consultation_notes']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'maternal'): ?>
                <div class="space-y-4">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div>
                            <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 12h.01" />
                                    <path d="M15 12h.01" />
                                    <path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5" />
                                    <path
                                        d="M19 6.3a9 9 0 0 1 1.8 3.9 2 2 0 0 1 0 3.6 9 9 0 0 1-17.6 0 2 2 0 0 1 0-3.6A9 9 0 0 1 12 3c2 0 3.5 1.1 3.5 2.5s-.9 2.5-2 2.5c-.8 0-1.5-.4-1.5-1" />
                                </svg> Maternal Health &amp; Prenatal Care Registry</h2>
                            <p class="text-xs text-slate-500 font-medium">Pregnancy tracking, EDC calculations, and
                                high-risk monitoring</p>
                        </div>
                        <a href="<?= esc(tabUrl('maternal', ['modal' => 'new_maternal'])); ?>"
                            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 transition-all flex items-center gap-1.5"><svg
                                class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14" />
                                <path d="M12 5v14" />
                            </svg> Register New Prenatal Case</a>
                    </div>
                    <?php if (empty($maternalCases)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span
                                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg
                                    class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 12h.01" />
                                    <path d="M15 12h.01" />
                                    <path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5" />
                                    <path
                                        d="M19 6.3a9 9 0 0 1 1.8 3.9 2 2 0 0 1 0 3.6 9 9 0 0 1-17.6 0 2 2 0 0 1 0-3.6A9 9 0 0 1 12 3c2 0 3.5 1.1 3.5 2.5s-.9 2.5-2 2.5c-.8 0-1.5-.4-1.5-1" />
                                </svg></span>
                            <p class="text-sm font-semibold text-slate-700">No Maternal Prenatal Cases Recorded</p>
                            <p class="text-xs text-slate-400 mt-0.5">Click "Register New Prenatal Case" above to add an
                                expectant mother.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($maternalCases as $mom):
                                $gpTag = '';
                                if (isset($mom['gravida']) && isset($mom['para'])) {
                                    $gpTag = ' · G' . esc($mom['gravida']) . 'P' . esc($mom['para']);
                                } elseif (!empty($mom['risks']) && preg_match('/G\d+P\d+/i', $mom['risks'], $mMatch)) {
                                    $gpTag = ' · ' . esc(strtoupper($mMatch[0]));
                                }
                                ?>
                                <div
                                    class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm border-l-4 <?= !empty($mom['highRisk']) ? 'border-l-rose-500' : 'border-l-emerald-500'; ?> space-y-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3">
                                        <div>
                                            <p class="font-bold text-slate-800 text-base"><?= esc($mom['name']); ?> <span
                                                    class="text-xs text-slate-500 font-medium">(<?= esc($mom['age']); ?>
                                                    y/o<?= $gpTag; ?>)</span></p>
                                            <p class="text-xs text-slate-600 font-medium mt-0.5">Barangay:
                                                <?= esc($mom['barangay']); ?> · Blood Type: <span
                                                    class="text-rose-700 font-bold bg-rose-50 px-2 py-0.5 rounded border border-rose-200"><?= esc($mom['bloodType']); ?></span>
                                            </p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">LMP: <?= esc($mom['lmp']); ?> · Expected
                                                EDC: <strong class="text-slate-800 font-bold"><?= esc($mom['edc']); ?></strong></p>
                                        </div>
                                        <div class="text-right">
                                            <?php if (!empty($mom['highRisk'])): ?>
                                                <span
                                                    class="px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200 inline-flex items-center gap-1"><svg
                                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path
                                                            d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                                                        <path d="M12 9v4" />
                                                        <path d="M12 17h.01" />
                                                    </svg> HIGH RISK</span>
                                            <?php else: ?>
                                                <span
                                                    class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 inline-flex items-center gap-1"><svg
                                                        class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M20 6 9 17l-5-5" />
                                                    </svg> LOW RISK</span>
                                            <?php endif; ?>
                                            <p class="text-xs font-bold text-teal-700 mt-1.5">Next Visit:
                                                <?= esc($mom['nextVisit']); ?></p>
                                        </div>
                                    </div>
                                    <div class="bg-teal-50/60 p-3.5 rounded-xl border border-teal-100 text-xs">
                                        <p class="font-bold text-teal-900 mb-1">Risk Factors &amp; Clinical Health Plan:</p>
                                        <p class="text-teal-950 font-medium"><?= esc($mom['risks']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'fp'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path
                                d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
                        </svg> Family Planning &amp; Reproductive Health Registry</h2>
                    <?php if (empty($fpClients)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span
                                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg
                                    class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z" />
                                    <path d="m8.5 8.5 7 7" />
                                </svg></span>
                            <p class="text-sm font-semibold text-slate-700">No Family Planning Clients Registered</p>
                            <p class="text-xs text-slate-400 mt-0.5">Contraceptive users and acceptors will be logged here.</p>
                        </div>
                    <?php else: ?>
                        <div class="dashboard-card bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs min-w-[650px]">
                                    <thead class="bg-slate-50 text-slate-600 uppercase font-bold border-b border-slate-200">
                                        <tr>
                                            <th class="px-4 py-3.5 text-left">Client Name</th>
                                            <th class="px-4 py-3.5 text-left">Age</th>
                                            <th class="px-4 py-3.5 text-left">Barangay</th>
                                            <th class="px-4 py-3.5 text-left">Method</th>
                                            <th class="px-4 py-3.5 text-left">Acceptor Type</th>
                                            <th class="px-4 py-3.5 text-left">Last Supply</th>
                                            <th class="px-4 py-3.5 text-left">Next Visit</th>
                                            <th class="px-4 py-3.5 text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <?php foreach ($fpClients as $fp): ?>
                                            <tr class="hover:bg-teal-50/40 transition-colors">
                                                <td class="px-4 py-3.5 font-bold text-slate-800"><?= esc($fp['name']); ?></td>
                                                <td class="px-4 py-3.5 text-slate-700 font-semibold"><?= esc($fp['age']); ?></td>
                                                <td class="px-4 py-3.5 font-semibold text-slate-700"><?= esc($fp['barangay']); ?>
                                                </td>
                                                <td class="px-4 py-3.5 text-teal-900 font-bold"><?= esc($fp['method']); ?></td>
                                                <td class="px-4 py-3.5"><span
                                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-800"><?= esc($fp['acceptorType']); ?></span>
                                                </td>
                                                <td class="px-4 py-3.5 text-slate-500 font-mono"><?= esc($fp['lastSupply']); ?></td>
                                                <td class="px-4 py-3.5 font-bold text-teal-600 font-mono">
                                                    <?= esc($fp['nextVisit']); ?></td>
                                                <td class="px-4 py-3.5"><span
                                                        class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= strtolower($fp['status']) === 'overdue' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'; ?>"><?= esc($fp['status']); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'immunization'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="m18 2 4 4" />
                            <path d="m17 7 3-3" />
                            <path d="M19 9 8.7 19.3c-1 1-2.5 1-3.4 0l-.6-.6c-1-1-1-2.5 0-3.4L15 5" />
                            <path d="m9 11 4 4" />
                            <path d="m5 19-3 3" />
                            <path d="m14 4 6 6" />
                        </svg> Child Immunization &amp; Vaccine Cards</h2>
                    <?php if (empty($immunizationRecords)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span
                                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg
                                    class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 12h.01" />
                                    <path d="M15 12h.01" />
                                    <path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5" />
                                    <path
                                        d="M19 6.3a9 9 0 0 1 1.8 3.9 2 2 0 0 1 0 3.6 9 9 0 0 1-17.6 0 2 2 0 0 1 0-3.6A9 9 0 0 1 12 3c2 0 3.5 1.1 3.5 2.5s-.9 2.5-2 2.5c-.8 0-1.5-.4-1.5-1" />
                                </svg></span>
                            <p class="text-sm font-semibold text-slate-700">No Child Vaccination Records</p>
                            <p class="text-xs text-slate-400 mt-0.5">Administered vaccine doses will be logged here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($immunizationRecords as $im): ?>
                                <div
                                    class="dashboard-card bg-white rounded-2xl p-4 border border-slate-200 shadow-sm flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-800 text-sm sm:text-base"><?= esc($im['childName']); ?></p>
                                        <p class="text-xs text-slate-600 mt-0.5">Vaccine: <strong
                                                class="text-indigo-900 font-bold"><?= esc($im['vaccineName']); ?></strong>
                                            (<?= esc($im['targetAge']); ?>) · Batch: <?= esc($im['lot']); ?></p>
                                        <p class="text-[11px] text-slate-400 mt-0.5">Barangay: <?= esc($im['barangay']); ?> · Given:
                                            <?= esc($im['dateGiven']); ?> · Next: <strong
                                                class="text-emerald-700 font-bold"><?= esc($im['nextVisit']); ?></strong></p>
                                    </div>
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 shrink-0">Administered</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'vital'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                            <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                            <path d="M10 9H8" />
                            <path d="M16 13H8" />
                            <path d="M16 17H8" />
                        </svg> Municipal Births &amp; Vital Statistics</h2>
                    <?php if (empty($vitalRecords)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span
                                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg
                                    class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                                    <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                                    <path d="M10 9H8" />
                                    <path d="M16 13H8" />
                                    <path d="M16 17H8" />
                                </svg></span>
                            <p class="text-sm font-semibold text-slate-700">No Registered Birth Records</p>
                            <p class="text-xs text-slate-400 mt-0.5">Municipal birth registrations will display here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($vitalRecords as $vr): ?>
                                <div
                                    class="dashboard-card bg-white rounded-2xl p-4 border border-slate-200 shadow-sm flex items-center justify-between">
                                    <div>
                                        <p class="font-bold text-slate-800 text-sm sm:text-base"><?= esc($vr['name']); ?> <span
                                                class="text-xs text-slate-500 font-medium">(Mother:
                                                <?= esc($vr['motherName']); ?>)</span></p>
                                        <p class="text-xs text-slate-600 mt-0.5">DOB: <?= esc($vr['date']); ?> · Barangay:
                                            <?= esc($vr['barangay']); ?> · Weight: <?= esc($vr['weight']); ?></p>
                                        <p class="text-[11px] text-slate-400 mt-0.5">Birth Attendant: <?= esc($vr['attendant']); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span
                                            class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200"><?= esc($vr['registrationStatus']); ?></span>
                                        <p class="text-xs font-mono text-slate-400 mt-1"><?= esc($vr['lncrn']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'referrals'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8" />
                            <polyline points="16 6 12 2 8 6" />
                            <line x1="12" x2="12" y1="2" y2="15" />
                        </svg> High-Risk OB Hospital Referrals</h2>
                    <?php if (empty($referralsList)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span
                                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg
                                    class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 6v4" />
                                    <path d="M14 14h-4" />
                                    <path d="M14 18h-4" />
                                    <path d="M14 8h-4" />
                                    <path d="M18 12h2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h2" />
                                    <path d="M18 22V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v18" />
                                </svg></span>
                            <p class="text-sm font-semibold text-slate-700">No Active OB Hospital Referrals</p>
                            <p class="text-xs text-slate-400 mt-0.5">Emergency and tertiary hospital referral forms will be
                                listed here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($referralsList as $ref): ?>
                                <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <span
                                            class="font-mono text-xs font-bold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200"><?= esc($ref['id']); ?></span>
                                        <span
                                            class="px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-900 border border-amber-200"><?= esc($ref['urgency']); ?></span>
                                    </div>
                                    <p class="font-bold text-slate-800 text-base"><?= esc($ref['patientName']); ?>
                                        (<?= esc($ref['age']); ?> y/o)</p>
                                    <p class="text-xs text-slate-700"><strong>Diagnosis:</strong> <?= esc($ref['diagnosis']); ?></p>
                                    <p class="text-xs text-indigo-900"><strong>Referred To:</strong> <?= esc($ref['referredTo']); ?>
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 font-mono bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                        <strong>Reason:</strong> <?= esc($ref['reason']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'opd'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                            <path d="M12 11h4" />
                            <path d="M12 16h4" />
                            <path d="M8 11h.01" />
                            <path d="M8 16h.01" />
                        </svg> Routine Prenatal OPD Checkups</h2>
                    <?php if (empty($prenatalOPDList)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span
                                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg
                                    class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
                                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                                    <path d="M12 11h4" />
                                    <path d="M12 16h4" />
                                    <path d="M8 11h.01" />
                                    <path d="M8 16h.01" />
                                </svg></span>
                            <p class="text-sm font-semibold text-slate-700">No Routine Prenatal Checkups Found</p>
                            <p class="text-xs text-slate-400 mt-0.5">Routine OPD prenatal notes will be listed here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($prenatalOPDList as $opd): ?>
                                <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <p class="font-bold text-slate-800 text-base"><?= esc($opd['patientName']); ?> <span
                                                class="text-xs font-medium text-slate-500">(<?= esc($opd['age'] ?? 'N/A'); ?>y /
                                                <?= esc($opd['gender']); ?>)</span></p>
                                        <span
                                            class="font-mono text-xs bg-teal-50 text-teal-900 font-bold px-2.5 py-1 rounded-lg border border-teal-200"><?= esc($opd['icd10'] ?: 'Z34.8'); ?></span>
                                    </div>
                                    <p class="text-xs text-slate-700"><strong>Chief Complaint:</strong>
                                        <?= esc($opd['chiefComplaint']); ?></p>
                                    <div
                                        class="text-xs text-slate-600 font-mono bg-slate-50 p-3 rounded-xl border border-slate-200/60">
                                        <?= esc($opd['consultation_notes']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <?php if ($modal === 'new_maternal'): ?>
        <div
            class="fixed inset-0 bg-slate-950/45 backdrop-blur-sm flex items-end sm:items-center justify-center z-50 p-3 sm:p-4">
            <div
                class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-200">
                <div
                    class="p-5 border-b border-slate-200 flex items-center justify-between bg-white rounded-t-2xl shrink-0">
                    <div>
                        <h2 class="text-base font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 12h.01" />
                                <path d="M15 12h.01" />
                                <path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5" />
                                <path
                                    d="M19 6.3a9 9 0 0 1 1.8 3.9 2 2 0 0 1 0 3.6 9 9 0 0 1-17.6 0 2 2 0 0 1 0-3.6A9 9 0 0 1 12 3c2 0 3.5 1.1 3.5 2.5s-.9 2.5-2 2.5c-.8 0-1.5-.4-1.5-1" />
                            </svg> Register New Prenatal Maternal Case</h2>
                        <p class="text-xs text-slate-500">Log pregnancy tracking, LMP, and EDC calculation</p>
                    </div>
                    <a href="<?= esc(tabUrl('maternal')); ?>"
                        class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center"><svg
                            class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg></a>
                </div>
                <form class="p-5 space-y-4 text-xs overflow-y-auto" method="post">
                    <input type="hidden" name="action" value="save_maternal">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Select Expectant Mother *</label>
                        <select name="resident_id" required
                            class="w-full p-3 border border-slate-300 rounded-xl text-sm font-semibold outline-none">
                            <option value="">-- Select Female Resident --</option>
                            <?php foreach ($allMothersList as $m): ?>
                                <option value="<?= esc($m['id']); ?>"><?= esc($m['name']); ?> (<?= esc($m['barangay']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block font-bold text-slate-700 mb-1">Gravida (Total Pregnancies)</label><input
                                type="number" name="gravida" value="1" min="1"
                                class="w-full p-3 border border-slate-300 rounded-xl text-sm font-bold"></div>
                        <div><label class="block font-bold text-slate-700 mb-1">Para (Total Deliveries)</label><input
                                type="number" name="para" value="0" min="0"
                                class="w-full p-3 border border-slate-300 rounded-xl text-sm font-bold"></div>
                    </div>
                    <div class="bg-teal-50/60 p-4 rounded-2xl border border-teal-100 space-y-3">
                        <p class="font-bold text-teal-900 text-xs">Maternal Timeline &amp; Dates</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="block font-bold text-slate-700 mb-1">Last Menstrual Period (LMP)
                                    *</label><input type="date" name="lmp" required
                                    value="<?= date('Y-m-d', strtotime('-3 months')); ?>"
                                    class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-800">
                            </div>
                            <div><label class="block font-bold text-slate-700 mb-1">Expected EDC *</label><input type="date"
                                    name="edc" required value="<?= date('Y-m-d', strtotime('+6 months')); ?>"
                                    class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-800">
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3.5 bg-rose-50 rounded-2xl border border-rose-200">
                        <input type="checkbox" name="high_risk" value="1" id="high_risk_chk"
                            class="w-4 h-4 text-rose-600 rounded">
                        <label for="high_risk_chk" class="font-bold text-rose-900 cursor-pointer text-xs">Classify as
                            HIGH-RISK Pregnancy</label>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Risk Factors &amp; Clinical Health Plan</label>
                        <textarea name="risk_factors" rows="3"
                            class="w-full p-3 border border-slate-300 rounded-xl text-xs resize-none outline-none"
                            placeholder="e.g., Gestational hypertension, previous C-section, teenage pregnancy"></textarea>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <a href="<?= esc(tabUrl('maternal')); ?>"
                            class="flex-1 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 text-center">Cancel</a>
                        <button type="submit"
                            class="flex-1 py-2.5 bg-teal-600 text-white rounded-xl text-xs font-bold hover:bg-teal-700 shadow-md shadow-teal-600/20 transition-all">Save
                            Prenatal Maternal Case</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function toggleSidebar() {
            const s = document.getElementById('sidebar');
            if (s.classList.contains('sidebar-expanded')) { s.classList.remove('sidebar-expanded'); s.classList.add('sidebar-collapsed'); localStorage.setItem('mw_sidebar_collapsed', 'true'); }
            else { s.classList.remove('sidebar-collapsed'); s.classList.add('sidebar-expanded'); localStorage.setItem('mw_sidebar_collapsed', 'false'); }
        }
        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('mw_sidebar_collapsed') === 'true' && window.innerWidth >= 640) {
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
