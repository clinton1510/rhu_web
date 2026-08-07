<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$stType = strtoupper((string)($_SESSION['rhu_staff_login']['staff_type'] ?? ''));
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
            $statement->execute([trim($_POST['establishment']),trim($_POST['barangay']),(int)$_POST['inspector_staff_id'] ?: null,$_POST['inspection_date'],$_POST['next_inspection_date'] ?: null,$_POST['status'],(float)$_POST['compliance_rate'],(int)$_POST['violations'],trim($_POST['findings'])]);
            header('Location: ?tab=inspections'); exit;
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
            $statement->execute([(int)$_POST['resident_id'],(int)$_POST['certificate_type_id'],trim($_POST['certificate_number']),$_POST['issue_date'],$_POST['expiry_date'] ?: null,(int)$_POST['issued_by_id'] ?: null,trim($_POST['purpose'])]);
            header('Location: ?tab=certificates'); exit;
        }
    }
}
$sanitaryStaffId = (int)($_SESSION['rhu_staff_login']['staff_id'] ?? 0);
$sanitaryUserId = (int)($_SESSION['rhu_staff_login']['id'] ?? $_SESSION['rhu_staff_login']['user_id'] ?? 0);
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
    } catch (Exception $e) {}

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
        $diseases[] = ['DR-' . $row['id'], $row['disease_name'], $row['icd_code'], 'Week ' . date('W', strtotime($row['case_date'])), [$row['barangay']], 1, !(bool)$row['reported_to_doh'], $row['treatment'] ?: 'Not recorded', []];
    }
}
$passed=count(array_filter($inspections,fn($i)=>$i[6]==='passed')); $failed=count(array_filter($inspections,fn($i)=>$i[6]==='failed')); $conditional=count(array_filter($inspections,fn($i)=>$i[6]==='conditional')); $avg=count($inspections) ? round(array_sum(array_column($inspections,7))/count($inspections)) : 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sanitary Inspector Portal</title>
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
            <div class="flex items-center gap-2">
                <span class="hidden rounded-lg bg-white/10 px-3 py-1.5 text-sm font-semibold sm:block">♥ <?= e($_SESSION['rhu_staff_login']['name'] ?? 'Sanitary Inspector') ?></span>
                <a href="StaffLogout.php" data-staff-logout class="staff-logout-trigger" title="Log Out"><span class="staff-logout-glyph" aria-hidden="true"></span><span>Log out</span></a>
            </div>
        </div>
        <nav class="hidden gap-1 px-4 sm:flex">
            <?php foreach($tabs as $id=>$label): ?>
                <a href="?tab=<?= $id ?>" class="rounded-t-lg px-3 py-2 text-xs font-semibold <?= $tab===$id?'bg-white text-teal-700':'text-teal-100 hover:bg-white/10' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <main class="mx-auto max-w-5xl space-y-4 px-3 py-4 pb-20 sm:px-4 sm:py-6">
        <?php if ($sanitaryFlashSuccess): ?><div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-bold text-emerald-800"><?= e($sanitaryFlashSuccess) ?></div><?php endif; ?>
        <?php if ($sanitaryFlashError): ?><div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-red-800"><?= e($sanitaryFlashError) ?></div><?php endif; ?>
        <?php if($tab==='overview'): ?>
            <section class="flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4">
                <span class="text-red-600">⚠</span>
                <div>
                    <b class="text-sm text-red-800"><?= $failed ?> establishment(s) failed inspection</b>
                    <p class="text-sm text-red-700">
                        <?php foreach($inspections as $i) if($i[6]==='failed') echo e($i[1]); ?>
                    </p>
                </div>
                <a class="ml-auto rounded-lg bg-red-600 px-3 py-1.5 text-xs text-white" href="?tab=inspections">Review</a>
            </section>

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
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <?php foreach([['Passed',$passed,'text-green-600','bg-green-50'],['Conditional',$conditional,'text-yellow-600','bg-yellow-50'],['Failed',$failed,'text-red-600','bg-red-50'],['Avg Compliance',$avg.'%','text-teal-600','bg-teal-50']] as [$label,$value,$color,$bg]): ?>
                    <div class="<?= $bg ?> rounded-xl border border-white p-4 shadow-sm">
                        <b class="text-2xl font-black <?= $color ?>"><?= $value ?></b>
                        <p class="text-sm font-bold"><?= $label ?></p>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 font-bold">▣ Upcoming Re-inspections</h2>
                    <?php foreach($inspections as $i): if($i[6]==='passed') continue; ?>
                        <div class="mb-2 flex justify-between rounded-lg bg-gray-50 p-2.5">
                            <div>
                                <b class="block text-sm"><?= e($i[1]) ?></b>
                                <small class="text-gray-500"><?= e($i[2]) ?> · <?= e($i[3]) ?></small>
                            </div>
                            <div class="text-right">
                                <b class="text-xs text-teal-600"><?= $i[5] ?></b>
                                <small class="block rounded <?= $i[6]==='failed'?'bg-red-100 text-red-700':'bg-yellow-100 text-yellow-700' ?> px-1.5 text-xs">
                                    <?= $i[6] ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 font-bold">⌁ Disease Alerts (Environmental)</h2>
                    <?php foreach($diseases as $d): ?>
                        <div class="mb-2 flex justify-between rounded-lg <?= $d[6]?'border border-red-100 bg-red-50':'bg-gray-50' ?> p-2.5">
                            <div>
                                <b class="block text-sm"><?= e($d[1]) ?></b>
                                <small class="text-gray-500"><?= implode(', ',$d[4]) ?> · <?= $d[5] ?> cases</small>
                            </div>
                            <span class="text-xs font-bold <?= $d[6]?'text-red-700':'text-gray-600' ?>">
                                <?= $d[6]?'ALERT':'Watch' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

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

</body>
</html>
