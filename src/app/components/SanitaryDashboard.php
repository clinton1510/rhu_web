<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
$stType = strtoupper((string) ($_SESSION['rhu_staff_login']['staff_type'] ?? ''));
if (empty($_SESSION['rhu_staff_login']) || ($stType !== 'SANITARY_INSPECTOR' && !str_contains($stType, 'SANITARY') && !str_contains($stType, 'INSPECTOR'))) {
    header('Location: RHULogin.php');
    exit;
}
require_once __DIR__ . '/db.php';
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
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
    'inspections' => ['Inspections', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>'],
    'certificates' => ['Certificates', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>'],
    'disease' => ['Disease', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>'],
];
$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab]))
    $tab = 'overview';
$modal = $_GET['modal'] ?? '';
$residentOptions = $certificateOptions = $staffOptions = [];
if (!empty($pdo)) {
    $residentOptions = $pdo->query("SELECT id,CONCAT(first_name,' ',last_name) name FROM residents ORDER BY first_name,last_name")->fetchAll(PDO::FETCH_ASSOC);
    $certificateOptions = $pdo->query("SELECT id,certificate_type_name name FROM certificate_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $staffOptions = $pdo->query("SELECT s.id,CONCAT(u.first_name,' ',u.last_name) name FROM staff s JOIN users u ON u.id=s.user_id ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_sanitary_inspection') {
            $statement = $pdo->prepare("INSERT INTO sanitation_inspections (establishment,barangay,inspector_staff_id,inspection_date,next_inspection_date,status,compliance_rate,violations,findings) VALUES (?,?,?,?,?,?,?,?,?)");
            $statement->execute([trim($_POST['establishment']), trim($_POST['barangay']), (int) $_POST['inspector_staff_id'] ?: null, $_POST['inspection_date'], $_POST['next_inspection_date'] ?: null, $_POST['status'], (float) $_POST['compliance_rate'], (int) $_POST['violations'], trim($_POST['findings'])]);
            header('Location: ?tab=inspections');
            exit;
        }
        if ($action === 'save_sanitary_certificate') {
            $statement = $pdo->prepare("INSERT INTO health_certificates (resident_id,certificate_type_id,certificate_number,issue_date,expiry_date,issued_by_id,purpose,validity_status) VALUES (?,?,?,?,?,?,?,'Valid')");
            $statement->execute([(int) $_POST['resident_id'], (int) $_POST['certificate_type_id'], trim($_POST['certificate_number']), $_POST['issue_date'], $_POST['expiry_date'] ?: null, (int) $_POST['issued_by_id'] ?: null, trim($_POST['purpose'])]);
            header('Location: ?tab=certificates');
            exit;
        }
    }
}
$sanitaryStaffId = (int) ($_SESSION['rhu_staff_login']['staff_id'] ?? 0);
$sanitaryUserId = (int) ($_SESSION['rhu_staff_login']['id'] ?? $_SESSION['rhu_staff_login']['user_id'] ?? 0);
$sanitaryConsultations = [];

$inspections = $certs = $diseases = [];
if (!empty($pdo)) {
    try {
        $sanStmt = $pdo->prepare("
            SELECT c.id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, r.barangay, c.consultation_date as date, c.consultation_notes
            FROM consultations c
            JOIN residents r ON c.resident_id = r.id
            LEFT JOIN staff doc_s ON c.physician_id = doc_s.id
            WHERE (c.physician_id = :sid OR doc_s.user_id = :uid OR c.chief_complaint LIKE '%Sanitary%' OR c.chief_complaint LIKE '%Inspection%' OR c.chief_complaint LIKE '%Clearance%')
            ORDER BY c.id DESC
        ");
        $sanStmt->execute(['sid' => $sanitaryStaffId, 'uid' => $sanitaryUserId]);
        $sanitaryConsultations = $sanStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
    }

    foreach ($pdo->query("SELECT * FROM sanitation_inspections ORDER BY inspection_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rawStatus = strtolower($row['status']);
        $status = str_contains($rawStatus, 'compliant') && !str_contains($rawStatus, 'non') ? 'passed' : (str_contains($rawStatus, 'conditional') || str_contains($rawStatus, 'follow') ? 'conditional' : 'failed');
        $findings = array_values(array_filter(array_map('trim', preg_split('/[\r\n;]+/', (string) $row['findings']))));
        $inspections[] = ['SI' . $row['id'], $row['establishment'], 'Establishment', $row['barangay'], $row['inspection_date'], $row['next_inspection_date'] ?: 'Not scheduled', $status, (int) ($row['compliance_rate'] ?? 0), (int) $row['violations'], $findings];
    }
    foreach ($pdo->query("SELECT hc.*, ct.certificate_type_name, CONCAT(r.first_name,' ',r.last_name) recipient FROM health_certificates hc JOIN certificate_types ct ON ct.id=hc.certificate_type_id JOIN residents r ON r.id=hc.resident_id ORDER BY hc.issue_date DESC, hc.id DESC")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $certs[] = [$row['certificate_number'] ?: 'HC-' . $row['id'], $row['certificate_type_name'], $row['recipient'], $row['purpose'] ?: 'Not recorded', $row['issue_date'], $row['expiry_date'] ?: 'Not recorded', null, strtolower($row['validity_status'] ?: 'issued')];
    }
    foreach ($pdo->query("SELECT dc.id,dt.disease_name,dt.icd_code,dc.case_date,r.barangay,dc.treatment,dc.reported_to_doh FROM disease_cases dc JOIN disease_types dt ON dt.id=dc.disease_id JOIN residents r ON r.id=dc.resident_id ORDER BY dc.case_date DESC")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $diseases[] = ['DR-' . $row['id'], $row['disease_name'], $row['icd_code'], 'Week ' . date('W', strtotime($row['case_date'])), [$row['barangay']], 1, !(bool) $row['reported_to_doh'], $row['treatment'] ?: 'Not recorded', []];
    }
}
$passed = count(array_filter($inspections, fn($i) => $i[6] === 'passed'));
$failed = count(array_filter($inspections, fn($i) => $i[6] === 'failed'));
$conditional = count(array_filter($inspections, fn($i) => $i[6] === 'conditional'));
$avg = count($inspections) ? round(array_sum(array_column($inspections, 7)) / count($inspections)) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sanitary Inspector Portal - ResiHUnity RHU</title>
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
                            <p class="text-[9px] font-medium text-slate-400 truncate hidden lg:block">RHU Sanitary
                                Portal</p>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="py-3 pr-3 space-y-1 overflow-y-auto max-h-[calc(100vh-8rem)]">
                <?php foreach ($tabs as $id => [$label, $icon]): ?>
                    <a href="<?= e(tabUrl($id)); ?>" title="<?= e($label); ?>"
                        class="sidebar-item flex items-center gap-4 px-5 py-3 rounded-r-full text-xs font-semibold transition-all <?= $tab === $id ? 'nav-active' : 'text-slate-600 hover:text-slate-900'; ?>">
                        <span
                            class="leading-none shrink-0 text-center w-6 <?= $tab === $id ? 'text-teal-700' : 'text-slate-500'; ?>"><?= $icon; ?></span>
                        <span class="sidebar-label truncate text-xs"><?= e($label); ?></span>
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
                    <h1 class="text-base sm:text-lg font-bold text-slate-800 tracking-tight"><?= e($tabs[$tab][0]); ?>
                    </h1>
                    <p class="text-xs text-slate-500 hidden sm:block font-medium">Nasugbu RHU I — Sanitation &amp;
                        Environmental Health</p>
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
                        <?= strtoupper(substr($_SESSION['rhu_staff_login']['name'] ?? 'S', 0, 1)) ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-slate-800 leading-tight">
                            <?= e($_SESSION['rhu_staff_login']['name'] ?? 'Sanitary Inspector') ?></p>
                        <p class="text-[10px] text-slate-500 font-medium">Logged-in Inspector</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl w-full mx-auto p-4 sm:p-8 space-y-6 pb-28 sm:pb-12 flex-1">

            <?php if ($tab === 'overview'): ?>
                <div class="space-y-6">
                    <?php if ($failed > 0): ?>
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
                                    <p class="font-extrabold text-base sm:text-lg tracking-tight"><?= $failed ?>
                                        establishment(s) failed inspection</p>
                                    <p class="text-xs sm:text-sm text-rose-100 mt-1.5 font-medium">
                                        <?php foreach ($inspections as $i)
                                            if ($i[6] === 'failed')
                                                echo e($i[1]) . ' '; ?>
                                    </p>
                                </div>
                            </div>
                            <a href="?tab=inspections"
                                class="relative z-10 text-xs bg-white hover:bg-rose-50 text-red-700 font-bold px-4 py-2.5 rounded-xl shadow-md shrink-0">Review</a>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                        <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span
                                    class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center border border-emerald-100"><svg
                                        class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 6 9 17l-5-5" />
                                    </svg></span>
                                <span
                                    class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">Passed</span>
                            </div>
                            <p class="text-3xl font-extrabold text-slate-800 mt-4"><?= $passed ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Passed</p>
                        </div>
                        <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span
                                    class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center border border-amber-100"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path
                                            d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                                        <path d="M12 9v4" />
                                        <path d="M12 17h.01" />
                                    </svg></span>
                                <span
                                    class="text-[11px] font-semibold text-amber-700 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200">Conditional</span>
                            </div>
                            <p class="text-3xl font-extrabold text-slate-800 mt-4"><?= $conditional ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Conditional</p>
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
                                    class="text-[11px] font-semibold text-rose-700 bg-rose-50 px-2.5 py-0.5 rounded-full border border-rose-200">Failed</span>
                            </div>
                            <p class="text-3xl font-extrabold text-slate-800 mt-4"><?= $failed ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Failed</p>
                        </div>
                        <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span
                                    class="w-11 h-11 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center border border-teal-100"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path
                                            d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
                                    </svg></span>
                                <span
                                    class="text-[11px] font-semibold text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-full border border-teal-200">Avg</span>
                            </div>
                            <p class="text-3xl font-extrabold text-slate-800 mt-4"><?= $avg ?>%</p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Avg Compliance</p>
                        </div>
                    </div>

                    <div class="dashboard-card bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-teal-500 animate-pulse"></span>
                                    Sanitary Inspection &amp; Clearance Requests
                                    <span
                                        class="rounded-full bg-teal-50 px-2.5 py-0.5 text-xs font-bold text-teal-800 border border-teal-200"><?= count($sanitaryConsultations) ?></span>
                                </h3>
                                <p class="text-xs text-slate-500 font-medium">Live queue of inspection requests</p>
                            </div>
                        </div>
                        <?php if (empty($sanitaryConsultations)): ?>
                            <div class="text-center py-8 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
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
                                <p class="text-sm font-semibold text-slate-700">No sanitary inspection requests assigned yet</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3 max-h-64 overflow-y-auto">
                                <?php foreach ($sanitaryConsultations as $sc): ?>
                                    <div
                                        class="dashboard-card bg-white rounded-xl p-4 border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm"><?= e($sc['patientName']) ?> <span
                                                    class="text-xs font-medium text-slate-500">(<?= e($sc['age'] ?? 'N/A') ?>y ·
                                                    <?= e($sc['gender'] ?? 'N/A') ?> · <?= e($sc['barangay']) ?>)</span></p>
                                            <p class="text-xs text-teal-700 font-medium mt-0.5"><?= e($sc['chiefComplaint']) ?></p>
                                            <p class="text-[10px] text-slate-400 mt-0.5 inline-flex items-center gap-1"><svg
                                                    class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M8 2v4" />
                                                    <path d="M16 2v4" />
                                                    <rect width="18" height="18" x="3" y="4" rx="2" />
                                                    <path d="M3 10h18" />
                                                </svg> <?= e($sc['date']) ?></p>
                                        </div>
                                        <span
                                            class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg font-bold text-[10px] shrink-0">Pending
                                            Inspection</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <h2 class="mb-3 font-bold text-slate-800 flex items-center gap-2"><svg class="w-3.5 h-3.5"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M8 2v4" />
                                    <path d="M16 2v4" />
                                    <rect width="18" height="18" x="3" y="4" rx="2" />
                                    <path d="M3 10h18" />
                                </svg> Upcoming Re-inspections</h2>
                            <?php $hasRe = false;
                            foreach ($inspections as $i):
                                if ($i[6] === 'passed')
                                    continue;
                                $hasRe = true; ?>
                                <div class="mb-2 flex justify-between rounded-xl bg-slate-50 p-3 border border-slate-100">
                                    <div>
                                        <b class="block text-sm text-slate-800"><?= e($i[1]) ?></b>
                                        <small class="text-slate-500"><?= e($i[2]) ?> · <?= e($i[3]) ?></small>
                                    </div>
                                    <div class="text-right">
                                        <b class="text-xs text-teal-600"><?= e($i[5]) ?></b>
                                        <small
                                            class="block rounded mt-0.5 <?= $i[6] === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' ?> px-1.5 text-xs font-bold"><?= e($i[6]) ?></small>
                                    </div>
                                </div>
                            <?php endforeach;
                            if (!$hasRe): ?>
                                <p class="text-xs text-slate-400 italic">No re-inspections scheduled.</p>
                            <?php endif; ?>
                        </div>
                        <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <h2 class="mb-3 font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                                    <path d="M12 9v4" />
                                    <path d="M12 17h.01" />
                                </svg> Disease Alerts (Environmental)</h2>
                            <?php if (empty($diseases)): ?>
                                <p class="text-xs text-slate-400 italic">No disease alerts.</p><?php endif; ?>
                            <?php foreach ($diseases as $d): ?>
                                <div
                                    class="mb-2 flex justify-between rounded-xl <?= $d[6] ? 'border border-rose-100 bg-rose-50' : 'bg-slate-50 border border-slate-100' ?> p-3">
                                    <div>
                                        <b class="block text-sm text-slate-800"><?= e($d[1]) ?></b>
                                        <small class="text-slate-500"><?= e(implode(', ', $d[4])) ?> · <?= (int) $d[5] ?>
                                            cases</small>
                                    </div>
                                    <span
                                        class="text-xs font-bold <?= $d[6] ? 'text-rose-700' : 'text-slate-600' ?>"><?= $d[6] ? 'ALERT' : 'Watch' ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'inspections'): ?>
                <div class="space-y-4">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div>
                            <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
                                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
                                    <path d="M12 11h4" />
                                    <path d="M12 16h4" />
                                    <path d="M8 11h.01" />
                                    <path d="M8 16h.01" />
                                </svg> Sanitation &amp; Environmental Health Inspections</h2>
                            <p class="text-xs text-slate-500 font-medium">Establishment inspections and compliance tracking
                            </p>
                        </div>
                        <a href="?tab=inspections&modal=inspection"
                            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 flex items-center gap-1.5"><svg
                                class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14" />
                                <path d="M12 5v14" />
                            </svg> New Inspection</a>
                    </div>

                    <?php if ($modal === 'inspection'): ?>
                        <div class="dashboard-card bg-white rounded-2xl p-6 border border-teal-200 shadow-sm space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2"><svg class="w-4 h-4"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" />
                                        <path d="M12 5v14" />
                                    </svg> New Sanitation Inspection</h3>
                                <a href="?tab=inspections"
                                    class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 6 6 18" />
                                        <path d="m6 6 12 12" />
                                    </svg></a>
                            </div>
                            <form method="post" class="grid gap-3 sm:grid-cols-3 text-xs">
                                <input type="hidden" name="action" value="save_sanitary_inspection">
                                <input required name="establishment" placeholder="Establishment"
                                    class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                <input required name="barangay" placeholder="Barangay"
                                    class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                <select name="inspector_staff_id" class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                    <option value="">Inspector</option><?php foreach ($staffOptions as $o): ?>
                                        <option value="<?= (int) $o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
                                </select>
                                <input required type="date" name="inspection_date" value="<?= date('Y-m-d') ?>"
                                    class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                <input type="date" name="next_inspection_date"
                                    class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                <select name="status" class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                    <option>Compliant</option>
                                    <option>Conditional</option>
                                    <option>Non-compliant</option>
                                </select>
                                <input required type="number" min="0" max="100" name="compliance_rate"
                                    placeholder="Compliance %" class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                <input required type="number" min="0" name="violations" placeholder="Violations"
                                    class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                <textarea name="findings" placeholder="Findings"
                                    class="rounded-xl border border-slate-300 p-2.5 outline-none sm:col-span-3"
                                    rows="2"></textarea>
                                <div class="sm:col-span-3 flex justify-end gap-2">
                                    <a href="?tab=inspections"
                                        class="rounded-xl border border-slate-300 px-4 py-2 font-bold text-slate-600 hover:bg-slate-100">Cancel</a>
                                    <button type="submit"
                                        class="rounded-xl bg-teal-600 px-5 py-2 font-bold text-white shadow-md hover:bg-teal-700">Save
                                        Inspection</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="space-y-3">
                        <?php if (empty($inspections)): ?>
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
                                <p class="text-sm font-semibold text-slate-700">No inspections recorded</p>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($inspections as $i):
                            $color = $i[6] === 'passed' ? 'emerald' : ($i[6] === 'conditional' ? 'amber' : 'rose');
                            ?>
                            <article
                                class="dashboard-card rounded-2xl border border-slate-200 border-l-4 border-l-<?= $color ?>-500 bg-white p-5 shadow-sm">
                                <div class="flex flex-wrap justify-between gap-2">
                                    <div>
                                        <b class="text-slate-800"><?= e($i[1]) ?></b>
                                        <p class="text-xs text-slate-500"><?= e($i[2]) ?> · <?= e($i[3]) ?></p>
                                        <p class="text-xs text-slate-400 mt-0.5">Inspected: <?= e($i[4]) ?> · Next:
                                            <?= e($i[5]) ?></p>
                                    </div>
                                    <div class="text-right">
                                        <span
                                            class="rounded-full bg-<?= $color ?>-100 px-2.5 py-1 text-xs font-bold text-<?= $color ?>-700 border border-<?= $color ?>-200"><?= strtoupper($i[6]) ?></span>
                                        <b class="mt-1 block text-lg text-<?= $color ?>-600"><?= (int) $i[7] ?>%</b>
                                        <small class="text-slate-400">compliance</small>
                                    </div>
                                </div>
                                <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-2 rounded-full bg-<?= $color ?>-500" style="width:<?= (int) $i[7] ?>%"></div>
                                </div>
                                <p class="mt-3 text-xs font-bold text-slate-500">Findings (<?= (int) $i[8] ?>
                                    violation<?= $i[8] == 1 ? '' : 's' ?>)</p>
                                <?php foreach ($i[9] as $finding): ?>
                                    <p class="mt-1 text-xs text-slate-600">• <?= e($finding) ?></p>
                                <?php endforeach; ?>
                                <div class="mt-3 flex gap-2">
                                    <button type="button"
                                        class="rounded-xl bg-teal-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-teal-700">Issue
                                        Notice</button>
                                    <button type="button"
                                        class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Print
                                        Report</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'certificates'): ?>
                <div class="space-y-4">
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div>
                            <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
                                    <path d="M14 2v4a2 2 0 0 0 2 2h4" />
                                    <path d="M10 9H8" />
                                    <path d="M16 13H8" />
                                    <path d="M16 17H8" />
                                </svg> Health Certificates</h2>
                            <p class="text-xs text-slate-500 font-medium">Issue and track sanitary health certificates</p>
                        </div>
                        <a href="?tab=certificates&modal=certificate"
                            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 flex items-center gap-1.5"><svg
                                class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14" />
                                <path d="M12 5v14" />
                            </svg> Issue Certificate</a>
                    </div>

                    <?php if ($modal === 'certificate'): ?>
                        <div class="dashboard-card bg-white rounded-2xl p-6 border border-teal-200 shadow-sm space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2"><svg class="w-4 h-4"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" />
                                        <path d="M12 5v14" />
                                    </svg> Issue Health Certificate</h3>
                                <a href="?tab=certificates"
                                    class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 6 6 18" />
                                        <path d="m6 6 12 12" />
                                    </svg></a>
                            </div>
                            <form method="post" class="grid gap-3 sm:grid-cols-3 text-xs">
                                <input type="hidden" name="action" value="save_sanitary_certificate">
                                <select required name="resident_id"
                                    class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                    <option value="">Resident</option><?php foreach ($residentOptions as $o): ?>
                                        <option value="<?= (int) $o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
                                </select>
                                <select required name="certificate_type_id"
                                    class="rounded-xl border border-slate-300 p-2.5 outline-none"><?php foreach ($certificateOptions as $o): ?>
                                        <option value="<?= (int) $o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
                                </select>
                                <input required name="certificate_number" value="HC-<?= date('Ymd-His') ?>"
                                    class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                <input required type="date" name="issue_date" value="<?= date('Y-m-d') ?>"
                                    class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                <input type="date" name="expiry_date"
                                    class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                <select name="issued_by_id" class="rounded-xl border border-slate-300 p-2.5 outline-none">
                                    <option value="">Issuer</option><?php foreach ($staffOptions as $o): ?>
                                        <option value="<?= (int) $o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?>
                                </select>
                                <input required name="purpose" placeholder="Purpose"
                                    class="rounded-xl border border-slate-300 p-2.5 outline-none sm:col-span-2">
                                <div class="sm:col-span-3 flex justify-end gap-2">
                                    <a href="?tab=certificates"
                                        class="rounded-xl border border-slate-300 px-4 py-2 font-bold text-slate-600 hover:bg-slate-100">Cancel</a>
                                    <button type="submit"
                                        class="rounded-xl bg-teal-600 px-5 py-2 font-bold text-white shadow-md hover:bg-teal-700">Save
                                        Certificate</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="dashboard-card overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <table class="w-full min-w-[700px] text-xs">
                            <thead class="bg-slate-50 text-left uppercase text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="p-3.5 font-bold">Cert No.</th>
                                    <th class="p-3.5 font-bold">Type</th>
                                    <th class="p-3.5 font-bold">Recipient</th>
                                    <th class="p-3.5 font-bold">Purpose</th>
                                    <th class="p-3.5 font-bold">Issued</th>
                                    <th class="p-3.5 font-bold">Valid Until</th>
                                    <th class="p-3.5 font-bold">Fee</th>
                                    <th class="p-3.5 font-bold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($certs as $c): ?>
                                    <tr class="hover:bg-teal-50/40 transition-colors">
                                        <td class="p-3.5 font-mono text-xs"><?= e($c[0]) ?></td>
                                        <td class="p-3.5"><?= e($c[1]) ?></td>
                                        <td class="p-3.5 font-bold text-slate-800"><?= e($c[2]) ?></td>
                                        <td class="p-3.5"><?= e($c[3]) ?></td>
                                        <td class="p-3.5"><?= e($c[4]) ?></td>
                                        <td class="p-3.5"><?= e($c[5]) ?></td>
                                        <td class="p-3.5"><?= $c[6] ? '₱' . $c[6] : 'FREE' ?></td>
                                        <td class="p-3.5"><span
                                                class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200"><?= e($c[7]) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'disease'): ?>
                <div class="space-y-4">
                    <div class="dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg
                                class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                                <path d="M12 9v4" />
                                <path d="M12 17h.01" />
                            </svg> Disease Surveillance — Environmental Focus</h2>
                        <p class="text-xs text-slate-500 font-medium">Environmental disease case monitoring</p>
                    </div>
                    <?php if (empty($diseases)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span
                                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg
                                    class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                                    <path d="M12 9v4" />
                                    <path d="M12 17h.01" />
                                </svg></span>
                            <p class="text-sm font-semibold text-slate-700">No disease cases recorded</p>
                        </div>
                    <?php endif; ?>
                    <div class="space-y-3">
                        <?php foreach ($diseases as $d): ?>
                            <article
                                class="dashboard-card rounded-2xl border <?= $d[6] ? 'border-rose-200 bg-rose-50/30' : 'border-slate-200 bg-white' ?> p-5 shadow-sm">
                                <div class="flex justify-between gap-3">
                                    <div>
                                        <b class="text-slate-800"><?= e($d[1]) ?>
                                            <?= $d[6] ? '<span class="text-xs font-bold text-rose-600 ml-1">ALERT</span>' : '' ?></b>
                                        <p class="text-xs text-slate-500 mt-0.5">ICD-10: <?= e($d[2]) ?> · <?= e($d[3]) ?> ·
                                            Barangays: <?= e(implode(', ', $d[4])) ?></p>
                                        <p class="mt-1 text-xs font-semibold text-slate-700">Action: <?= e($d[7]) ?></p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <b
                                            class="text-2xl <?= (int) $d[5] > 3 ? 'text-rose-600' : 'text-slate-900' ?>"><?= (int) $d[5] ?></b>
                                        <small class="block text-slate-400">cases</small>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <script>
        function toggleSidebar() {
            const s = document.getElementById('sidebar');
            if (s.classList.contains('sidebar-expanded')) { s.classList.remove('sidebar-expanded'); s.classList.add('sidebar-collapsed'); localStorage.setItem('si_sidebar_collapsed', 'true'); }
            else { s.classList.remove('sidebar-collapsed'); s.classList.add('sidebar-expanded'); localStorage.setItem('si_sidebar_collapsed', 'false'); }
        }
        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('si_sidebar_collapsed') === 'true' && window.innerWidth >= 640) {
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