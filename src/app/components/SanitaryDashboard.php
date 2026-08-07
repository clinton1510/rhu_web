<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
$stType = strtoupper((string) ($_SESSION['rhu_staff_login']['staff_type'] ?? ''));
if (empty($_SESSION['rhu_staff_login']) || ($stType !== 'SANITARY_INSPECTOR' && !str_contains($stType, 'SANITARY') && !str_contains($stType, 'INSPECTOR'))) {
    header('Location: RHULogin.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/portal.php';
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$sanitaryFlashSuccess = $_SESSION['sanitary_flash_success'] ?? '';
$sanitaryFlashError = $_SESSION['sanitary_flash_error'] ?? '';
unset($_SESSION['sanitary_flash_success'], $_SESSION['sanitary_flash_error']);

if (!empty($pdo)) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sanitation_notices (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            inspection_id BIGINT UNSIGNED NOT NULL,
            notice_number VARCHAR(60) NOT NULL UNIQUE,
            issued_by_staff_id INT NULL,
            issued_date DATE NOT NULL,
            violations INT NOT NULL DEFAULT 0,
            findings TEXT NULL,
            notice_status VARCHAR(30) NOT NULL DEFAULT 'Issued',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notice_inspection (inspection_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $noticeSchemaError) {}
}

if (!empty($pdo) && isset($_GET['print_inspection'])) {
    $printId = (int)$_GET['print_inspection'];
    $printStmt = $pdo->prepare("SELECT si.*, CONCAT(u.first_name, ' ', u.last_name) AS inspector_name
        FROM sanitation_inspections si
        LEFT JOIN staff s ON s.id = si.inspector_staff_id
        LEFT JOIN users u ON u.id = s.user_id
        WHERE si.id = :id LIMIT 1");
    $printStmt->execute(['id' => $printId]);
    $report = $printStmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) { http_response_code(404); exit('Inspection report not found.'); }
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Inspection Report <?= e('SI' . $report['id']) ?></title>
    <style>
      body{font-family:Arial,sans-serif;color:#172033;margin:40px}
      .print-button{margin-bottom:18px;border:0;border-radius:8px;background:#059669;color:#fff;padding:10px 16px;font-weight:700;cursor:pointer}
      .head{display:grid;grid-template-columns:92px 1fr 92px;align-items:center;border-bottom:3px solid #0f766e;padding-bottom:16px;margin-bottom:24px;text-align:center}
      .report-logo{width:82px;height:82px;object-fit:contain;justify-self:start}
      .head-copy{grid-column:2}
      .office{margin:0 0 5px;color:#475569;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
      h1{margin:0;color:#0f766e;font-size:25px}
      .head p{margin:7px 0 0;color:#475569}
      .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
      .box{border:1px solid #cbd5e1;border-radius:8px;padding:12px}
      .full{grid-column:1/-1}
      .label{font-size:11px;text-transform:uppercase;color:#64748b;font-weight:bold}
      .value{margin-top:5px;font-size:14px}
      .sign{margin-top:70px;width:260px;border-top:1px solid #334155;padding-top:6px;text-align:center}
      @media print{.print-button{display:none}body{margin:18mm}.head{break-inside:avoid}.report-logo{print-color-adjust:exact;-webkit-print-color-adjust:exact}}
    </style></head>
    <body><button class="print-button" onclick="window.print()">Print this report</button><div class="head"><img class="report-logo" src="nasugbu_seal.png" alt="Municipality of Nasugbu official seal"><div class="head-copy"><p class="office">Municipality of Nasugbu &middot; Rural Health Unit I</p><h1>Sanitation Inspection Report</h1><p>Official Inspection Record &middot; Report <?= e('SI' . $report['id']) ?></p></div></div>
    <div class="grid">
      <div class="box"><div class="label">Establishment</div><div class="value"><?= e($report['establishment']) ?></div></div>
      <div class="box"><div class="label">Barangay</div><div class="value"><?= e($report['barangay']) ?></div></div>
      <div class="box"><div class="label">Inspection Date</div><div class="value"><?= e($report['inspection_date']) ?></div></div>
      <div class="box"><div class="label">Next Inspection</div><div class="value"><?= e($report['next_inspection_date'] ?: 'Not scheduled') ?></div></div>
      <div class="box"><div class="label">Status</div><div class="value"><?= e($report['status']) ?></div></div>
      <div class="box"><div class="label">Compliance</div><div class="value"><?= e($report['compliance_rate']) ?>% · <?= (int)$report['violations'] ?> violation(s)</div></div>
      <div class="box full"><div class="label">Findings</div><div class="value"><?= nl2br(e($report['findings'] ?: 'No findings recorded.')) ?></div></div>
    </div><div class="sign"><?= e($report['inspector_name'] ?: 'Sanitary Inspector') ?><br><small>Inspecting Officer</small></div>
    <script>window.addEventListener('load',()=>window.print());</script></body></html>
    <?php exit;
}

$tabs = ['overview' => 'Overview', 'inspections' => 'Inspections', 'certificates' => 'Certificates', 'disease' => 'Disease'];
$tab = $_GET['tab'] ?? 'overview'; if (!isset($tabs[$tab])) $tab = 'overview';
$modal = $_GET['modal'] ?? '';
$residentOptions = $certificateOptions = $staffOptions = [];
if (!empty($pdo)) {
    $residentOptions = $pdo->query("SELECT id,CONCAT(first_name,' ',last_name) name FROM residents ORDER BY first_name,last_name")->fetchAll(PDO::FETCH_ASSOC);
    $certificateOptions = $pdo->query("SELECT id,certificate_type_name name FROM certificate_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $staffOptions = $pdo->query("SELECT s.id,CONCAT(u.first_name,' ',u.last_name) name FROM staff s JOIN users u ON u.id=s.user_id ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
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
                        portalNotifyResident($pdo, $resId, "Your Sanitary Inspection request response has been updated by Sanitary Inspector. Status: {$status}. Findings: {$diagnosis}", "ResidentDashboard.php?tab=appointments");
                    }
                    $_SESSION['sanitary_flash_success'] = 'Sanitation consultation updated and response sent to resident!';
                } catch (Exception $e) {
                    $_SESSION['sanitary_flash_error'] = 'Error updating consultation: ' . $e->getMessage();
                }
            }
            header('Location: ?tab=overview'); exit;
        }
        if ($action === 'save_sanitary_inspection') {
            $statement = $pdo->prepare("INSERT INTO sanitation_inspections (establishment,barangay,inspector_staff_id,inspection_date,next_inspection_date,status,compliance_rate,violations,findings) VALUES (?,?,?,?,?,?,?,?,?)");
            $statement->execute([trim($_POST['establishment']), trim($_POST['barangay']), (int) $_POST['inspector_staff_id'] ?: null, $_POST['inspection_date'], $_POST['next_inspection_date'] ?: null, $_POST['status'], (float) $_POST['compliance_rate'], (int) $_POST['violations'], trim($_POST['findings'])]);
            header('Location: ?tab=inspections');
            exit;
        }
        if ($action === 'issue_inspection_notice') {
            $inspectionId = (int)($_POST['inspection_id'] ?? 0);
            try {
                $inspectionStmt = $pdo->prepare('SELECT id, establishment, violations, findings FROM sanitation_inspections WHERE id = :id LIMIT 1');
                $inspectionStmt->execute(['id' => $inspectionId]);
                $inspection = $inspectionStmt->fetch(PDO::FETCH_ASSOC);
                if (!$inspection) throw new RuntimeException('Inspection record not found.');
                if ((int)$inspection['violations'] <= 0) throw new RuntimeException('A notice requires at least one recorded violation.');
                $existingStmt = $pdo->prepare("SELECT notice_number FROM sanitation_notices WHERE inspection_id = :id AND notice_status = 'Issued' LIMIT 1");
                $existingStmt->execute(['id' => $inspectionId]);
                $noticeNumber = $existingStmt->fetchColumn();
                if (!$noticeNumber) {
                    $noticeNumber = 'SN-' . date('Ymd') . '-' . str_pad((string)$inspectionId, 5, '0', STR_PAD_LEFT);
                    $noticeStmt = $pdo->prepare("INSERT INTO sanitation_notices
                        (inspection_id, notice_number, issued_by_staff_id, issued_date, violations, findings, notice_status)
                        VALUES (:inspection, :number, :staff, CURDATE(), :violations, :findings, 'Issued')");
                    $noticeStmt->execute(['inspection'=>$inspectionId, 'number'=>$noticeNumber,
                        'staff'=>(int)($_SESSION['rhu_staff_login']['staff_id'] ?? 0) ?: null,
                        'violations'=>(int)$inspection['violations'], 'findings'=>$inspection['findings']]);
                    portalNotify($pdo, "Sanitation notice {$noticeNumber} issued to {$inspection['establishment']}.", null, 'ADMIN', 'RHUAdminDashboard.php?tab=sanitation');
                }
                $_SESSION['sanitary_flash_success'] = "Notice {$noticeNumber} has been issued.";
            } catch (Throwable $noticeError) {
                $_SESSION['sanitary_flash_error'] = 'Notice Error: ' . $noticeError->getMessage();
            }
            header('Location: ?tab=inspections'); exit;
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
            SELECT c.id, c.resident_id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, r.barangay, c.consultation_date as date, c.consultation_notes, COALESCE(c.consultation_status, 'Scheduled') AS consultation_status
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

    $inspectionStmt = $pdo->query("SELECT si.*, CONCAT_WS(' ', u.first_name, u.last_name) AS inspector_name
        FROM sanitation_inspections si
        LEFT JOIN staff s ON s.id = si.inspector_staff_id
        LEFT JOIN users u ON u.id = s.user_id
        ORDER BY si.inspection_date DESC, si.id DESC");
    foreach ($inspectionStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rawStatus = strtolower($row['status']);
        $status = str_contains($rawStatus, 'compliant') && !str_contains($rawStatus, 'non') ? 'passed' : (str_contains($rawStatus, 'conditional') || str_contains($rawStatus, 'follow') ? 'conditional' : 'failed');
        $findings = array_values(array_filter(array_map('trim', preg_split('/[\r\n;]+/', (string)$row['findings']))));
        $inspections[] = ['SI' . $row['id'], $row['establishment'], 'Establishment', $row['barangay'], $row['inspection_date'], $row['next_inspection_date'] ?: 'Not scheduled', $status, (int)($row['compliance_rate'] ?? 0), (int)$row['violations'], $findings, trim((string)($row['inspector_name'] ?? '')) ?: ($_SESSION['rhu_staff_login']['name'] ?? 'Sanitary Inspector')];
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
  <link rel="stylesheet" href="dashboard-enhancements.css?v=20260728-nurse-theme2">
  <script defer src="dashboard-enhancements.js?v=20260726-controls3"></script>
</head>
<body class="nurse-palette-dashboard min-h-screen bg-gray-50 text-gray-900">

<header class="sticky top-0 z-40 bg-gradient-to-r from-emerald-800 via-teal-800 to-cyan-900 text-white shadow-xl border-b border-emerald-700/40">
        <div class="flex items-center justify-between px-4 py-3 sm:px-6">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-xl">♢</span>
                <div>
                    <h1 class="text-base font-bold">Sanitary Inspector Portal</h1>
                    <p class="text-xs text-teal-200">Nasugbu Rural Health Unit I</p>
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

    <main class="mx-auto max-w-5xl space-y-4 px-3 py-4 pb-20 sm:px-4 sm:py-6">
        <?php if ($sanitaryFlashSuccess): ?><div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-bold text-emerald-800"><?= e($sanitaryFlashSuccess) ?></div><?php endif; ?>
        <?php if ($sanitaryFlashError): ?><div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-red-800"><?= e($sanitaryFlashError) ?></div><?php endif; ?>
        <?php if($tab==='overview'): ?>
            <section class="flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4">
                <span class="text-red-600">⚠</span>
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

            <section class="rounded-xl border border-teal-200 bg-white p-5 shadow-sm space-y-3">
                <div class="flex justify-between items-center border-b border-teal-100 pb-2">
                    <h2 class="font-bold text-sm text-teal-950 flex items-center gap-2">
                        ♢ Received Sanitary Inspection & Clearance Requests (<?= count($sanitaryConsultations) ?>)
                    </h2>
                    <span class="text-[10px] font-bold bg-teal-50 text-teal-700 px-2 py-0.5 rounded-full border border-teal-200">Live Queue</span>
                </div>
                <?php if (empty($sanitaryConsultations)): ?>
                    <p class="text-xs text-gray-400 italic py-2 text-center">No sanitary inspection requests assigned to you yet.</p>
                <?php else: ?>
                    <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                        <?php foreach ($sanitaryConsultations as $sc): ?>
                            <div class="py-3 flex flex-col space-y-2 bg-slate-50/50 p-3 rounded-xl border border-teal-100 my-1">
                                <div class="flex items-center justify-between text-xs gap-2">
                                    <div>
                                        <p class="font-bold text-gray-900"><?= e($sc['patientName']) ?> <span class="text-gray-500 font-normal">(<?= e($sc['age'] ?? 'N/A') ?>y • <?= e($sc['gender'] ?? 'N/A') ?> • <?= e($sc['barangay']) ?>)</span></p>
                                        <p class="text-teal-700 font-medium"><?= e($sc['chiefComplaint']) ?></p>
                                        <p class="text-[10px] text-gray-400 font-mono">📅 Requested Date: <?= e($sc['date']) ?></p>
                                    </div>
                                    <span class="px-2 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg font-bold text-[10px] shrink-0">Status: <?= e($sc['consultation_status']) ?></span>
                                </div>

                                <!-- SANITARY INSPECTOR RESPONSE / UPDATE FORM -->
                                <details class="group border-t border-teal-100 pt-2" open>
                                    <summary class="cursor-pointer text-xs font-bold text-teal-700 hover:text-teal-900 flex items-center justify-between py-1">
                                        <span>💬 Answer / Update Sanitary Response for Resident</span>
                                    </summary>
                                    <form method="post" class="mt-2 bg-white p-3 rounded-xl border border-teal-200/70 space-y-2.5">
                                        <input type="hidden" name="action" value="answer_consultation">
                                        <input type="hidden" name="consultation_id" value="<?= (int)$sc['id']; ?>">
                                        <input type="hidden" name="resident_id" value="<?= (int)($sc['resident_id'] ?? 0); ?>">
                                        
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Inspection Assessment / Findings</label>
                                                <input type="text" name="diagnosis" value="<?= e($sc['diagnosis'] ?? ''); ?>" placeholder="e.g. Sanitary Clearance Approved / Compliant" class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-teal-500 bg-white" required>
                                            </div>
                                            <div>
                                                <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Status</label>
                                                <select name="consultation_status" class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-teal-500 bg-white font-bold text-teal-900">
                                                    <option value="Completed" <?= ($sc['consultation_status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="In Progress" <?= ($sc['consultation_status'] ?? '') === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                    <option value="Scheduled" <?= ($sc['consultation_status'] ?? '') === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                    <option value="Referred" <?= ($sc['consultation_status'] ?? '') === 'Referred' ? 'selected' : ''; ?>>Referred</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Inspector Notes &amp; Recommendations for Resident</label>
                                            <textarea name="consultation_notes" rows="2" placeholder="Enter sanitary inspection notes, permit requirements, or advice..." class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-teal-500 bg-white resize-none"><?= e($sc['consultation_notes'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="flex justify-end pt-1">
                                            <button type="submit" class="px-4 py-2 bg-teal-700 hover:bg-teal-800 text-white text-xs font-extrabold rounded-lg shadow-sm transition-all flex items-center gap-1">
                                                <span>✓</span> Save Response &amp; Notify Resident
                                            </button>
                                        </div>
                                    </form>
                                </details>
                            </div>
                            <a href="?tab=inspections"
                                class="relative z-10 text-xs bg-white hover:bg-rose-50 text-red-700 font-bold px-4 py-2.5 rounded-xl shadow-md shrink-0">Review</a>
                        </div>
                    <?php endif; ?>
                    </div>

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

        <?php elseif($tab==='inspections'): ?>
            <div class="flex justify-between">
                <h2 class="text-xl font-bold">▣ Sanitation & Environmental Health Inspections</h2>
                <a href="?tab=inspections&modal=inspection" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">＋ New Inspection</a>
            </div>
            <?php if($modal==='inspection'): ?><form method="post" class="grid gap-2 rounded-xl border bg-white p-4 text-sm sm:grid-cols-3"><input type="hidden" name="action" value="save_sanitary_inspection"><input required name="establishment" placeholder="Establishment" class="rounded border p-2"><input required name="barangay" placeholder="Barangay" class="rounded border p-2"><select name="inspector_staff_id" class="rounded border p-2"><option value="">Inspector</option><?php foreach($staffOptions as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select><input required type="date" name="inspection_date" value="<?= date('Y-m-d') ?>" class="rounded border p-2"><input type="date" name="next_inspection_date" class="rounded border p-2"><select name="status" class="rounded border p-2"><option>Compliant</option><option>Conditional</option><option>Non-compliant</option></select><input required type="number" min="0" max="100" name="compliance_rate" placeholder="Compliance %" class="rounded border p-2"><input required type="number" min="0" name="violations" placeholder="Violations" class="rounded border p-2"><textarea name="findings" placeholder="Findings" class="rounded border p-2"></textarea><button class="rounded bg-teal-600 p-2 font-bold text-white">Save Inspection</button></form><?php endif; ?>
            
            <div class="space-y-3">
                <?php foreach($inspections as $i): $color=$i[6]==='passed'?'green':($i[6]==='conditional'?'yellow':'red'); ?>
                    <article class="rounded-xl border-l-4 border-<?= $color ?>-400 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap justify-between gap-2">
                            <div>
                                <b><?= e($i[1]) ?></b>
                                <p class="text-xs text-gray-500"><?= e($i[2]) ?> · <?= e($i[3]) ?> · Inspector: <?= e($i[10]) ?></p>
                                <p class="text-xs text-gray-400">Inspected: <?= $i[4] ?> · Next: <?= $i[5] ?></p>
                            </div>
                            <div class="text-right">
                                <span class="rounded-full bg-<?= $color ?>-100 px-2 py-1 text-xs font-bold text-<?= $color ?>-700"><?= strtoupper($i[6]) ?></span>
                                <b class="mt-1 block text-lg text-<?= $color ?>-600"><?= $i[7] ?>%</b>
                                <small class="text-gray-400">compliance</small>
                            </div>
                        </div>
                        <div class="mt-3 h-2 rounded-full bg-gray-100">
                            <i class="block h-2 rounded-full bg-<?= $color ?>-500" style="width:<?= $i[7] ?>%"></i>
                        </div>
                        <p class="mt-3 text-xs font-bold text-gray-500">Findings (<?= $i[8] ?> violation<?= $i[8]===1?'':'s' ?>)</p>
                        <?php foreach($i[9] as $finding): ?>
                            <p class="mt-1 text-xs text-gray-600">• <?= e($finding) ?></p>
                        <?php endforeach; ?>
                        <div class="mt-3">
                            <form method="post" class="inline" onsubmit="return confirm('Issue a formal sanitation notice for this inspection?')">
                                <input type="hidden" name="action" value="issue_inspection_notice">
                                <input type="hidden" name="inspection_id" value="<?= (int)substr($i[0], 2) ?>">
                                <button type="submit" class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs text-white hover:bg-teal-700">Issue Notice</button>
                            </form>
                            <a href="?print_inspection=<?= (int)substr($i[0], 2) ?>" target="_blank" rel="noopener" class="ml-2 inline-block rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50">Print Report</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php elseif($tab==='certificates'): ?>
            <div class="flex justify-between">
                <h2 class="text-xl font-bold">▤ Health Certificates</h2>
                <a href="?tab=certificates&modal=certificate" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white">＋ Issue Certificate</a>
            </div>
            <?php if($modal==='certificate'): ?><form method="post" class="grid gap-2 rounded-xl border bg-white p-4 text-sm sm:grid-cols-3"><input type="hidden" name="action" value="save_sanitary_certificate"><select required name="resident_id" class="rounded border p-2"><option value="">Resident</option><?php foreach($residentOptions as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select><select required name="certificate_type_id" class="rounded border p-2"><?php foreach($certificateOptions as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select><input required name="certificate_number" value="HC-<?= date('Ymd-His') ?>" class="rounded border p-2"><input required type="date" name="issue_date" value="<?= date('Y-m-d') ?>" class="rounded border p-2"><input type="date" name="expiry_date" class="rounded border p-2"><select name="issued_by_id" class="rounded border p-2"><option value="">Issuer</option><?php foreach($staffOptions as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select><input required name="purpose" placeholder="Purpose" class="rounded border p-2"><button class="rounded bg-green-600 p-2 font-bold text-white">Save Certificate</button></form><?php endif; ?>
            
            <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
                <table class="w-full min-w-[700px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Cert No.</th>
                            <th>Type</th>
                            <th>Recipient</th>
                            <th>Purpose</th>
                            <th>Issued</th>
                            <th>Valid Until</th>
                            <th>Fee</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach($certs as $c): ?>
                            <tr>
                                <td class="p-3 font-mono text-xs"><?= $c[0] ?></td>
                                <td><?= e($c[1]) ?></td>
                                <td class="font-semibold"><?= e($c[2]) ?></td>
                                <td><?= e($c[3]) ?></td>
                                <td><?= $c[4] ?></td>
                                <td><?= $c[5] ?></td>
                                <td><?= $c[6]?'₱'.$c[6]:'FREE' ?></td>
                                <td>
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700"><?= $c[7] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <h2 class="text-xl font-bold">♢ Disease Surveillance — Environmental Focus</h2>
            <div class="space-y-3">
                <?php foreach($diseases as $d): ?>
                    <article class="rounded-xl border <?= $d[6]?'border-red-200 bg-red-50/30':'border-gray-100 bg-white' ?> p-4 shadow-sm">
                        <div class="flex justify-between">
                            <div>
                                <b><?= e($d[1]) ?> <?= $d[6]?'<small class="text-red-500">⚠ ALERT</small>':'' ?></b>
                                <p class="text-xs text-gray-500">ICD-10: <?= $d[2] ?> · <?= $d[3] ?> · Barangays: <?= implode(', ',$d[4]) ?></p>
                                <p class="mt-1 text-xs font-semibold">Action: <?= e($d[7]) ?></p>
                            </div>
                            <div class="text-right">
                                <b class="text-2xl <?= $d[5]>3?'text-red-600':'text-gray-900' ?>"><?= $d[5] ?></b>
                                <small class="block text-gray-400">cases</small>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            Age groups: <?php foreach($d[8] as $age=>$count) echo "$age: $count "; ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <nav class="fixed bottom-0 left-0 right-0 z-50 flex border-t bg-white sm:hidden">
        <?php foreach($tabs as $id=>$label): ?>
            <a href="?tab=<?= $id ?>" class="flex flex-1 justify-center py-2 text-[10px] font-semibold <?= $tab===$id?'text-teal-600':'text-gray-400' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>

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