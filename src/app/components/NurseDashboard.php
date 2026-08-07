<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$stType = strtoupper((string)($_SESSION['rhu_staff_login']['staff_type'] ?? ''));
if (empty($_SESSION['rhu_staff_login']) || ($stType !== 'NURSE' && !str_contains($stType, 'NURSE'))) {
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

function parseTriageVitals(string $notes): array {
    $vitals = [];
    if (preg_match('/BP:\s*([^\s,]+)/i', $notes, $m)) $vitals['bp'] = $m[1];
    if (preg_match('/Temp:\s*([^\s,]+)/i', $notes, $m)) $vitals['temp'] = $m[1];
    if (preg_match('/(?:Wt|Weight):\s*([^\s,]+(?:\s*kg)?)/i', $notes, $m)) $vitals['weight'] = $m[1];
    if (preg_match('/RR:\s*([^\s,]+)/i', $notes, $m)) $vitals['rr'] = $m[1];
    if (preg_match('/HR:\s*([^\s,]+)/i', $notes, $m)) $vitals['hr'] = $m[1];
    return array_filter($vitals);
}

$tabs = [
    'overview' => ['Overview', '⌂'],
    'opd' => ['OPD Triage', '⚕'],
    'patients' => ['Patient Records', '▤'],
    'immunization' => ['Immunization', '⌁'],
    'nutrition' => ['Nutrition (OPT+)', '⚖'],
    'tb' => ['TB-DOTS', '🔬'],
    'disease' => ['Disease Surveillance', '⚠'],
    'bhw' => ['BHW Management', '👤'],
    'certificates' => ['Certificates', '🏅'],
];

$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab])) $tab = 'overview';

$modal = $_GET['modal'] ?? '';
$selectedResidentId = (int)($_GET['resident_id'] ?? 0);
$flashSuccess = $_SESSION['nurse_flash_success'] ?? '';
$flashError = $_SESSION['nurse_flash_error'] ?? '';
unset($_SESSION['nurse_flash_success'], $_SESSION['nurse_flash_error']);

// ----------------------------------------------------
// 1. POST FORM HANDLERS FOR NURSING & OPD TRIAGE
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($pdo)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'issue_certificate') {
        try {
            $issued = portalIssueResidentCertificate($pdo, $_POST, (int)($_SESSION['rhu_staff_login']['staff_id'] ?? 0), 'Public Health Nurse');
            $_SESSION['nurse_flash_success'] = "{$issued['type']} {$issued['number']} was issued and sent to the Resident.";
        } catch (Throwable $e) {
            $_SESSION['nurse_flash_error'] = 'Certificate Error: ' . $e->getMessage();
        }
        header('Location: ' . tabUrl('certificates')); exit;
    }

    // Action: Answer / Update Resident Consultation
    if ($action === 'answer_consultation') {
        $cslId = (int)($_POST['consultation_id'] ?? 0);
        $resId = (int)($_POST['resident_id'] ?? 0);
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $notes = trim($_POST['consultation_notes'] ?? '');
        $meds = trim($_POST['medications_prescribed'] ?? '');
        $status = trim($_POST['consultation_status'] ?? 'Completed');

        if ($cslId > 0 && !empty($pdo)) {
            try {
                $stmt = $pdo->prepare("UPDATE consultations SET diagnosis = :dx, consultation_notes = :notes, medications_prescribed = :meds, consultation_status = :st WHERE id = :id");
                $stmt->execute([
                    'dx' => $diagnosis,
                    'notes' => $notes,
                    'meds' => $meds,
                    'st' => $status,
                    'id' => $cslId
                ]);
                if ($resId > 0) {
                    portalNotifyResident($pdo, $resId, "Your OPD Triage / Consultation request has been updated by the Nurse. Status: {$status}. Assessment: {$diagnosis}", "ResidentDashboard.php?tab=appointments");
                }
                $_SESSION['nurse_flash_success'] = 'Consultation updated and response sent to resident successfully!';
            } catch (Exception $e) {
                $_SESSION['nurse_flash_error'] = 'Error updating consultation: ' . $e->getMessage();
            }
        }
        header('Location: ' . tabUrl('overview'));
        exit;
    }

    // Action: Save New OPD Triage & Vitals
    if ($action === 'save_triage') {
        $residentId = (int)($_POST['resident_id'] ?? 0);
        $physicianId = (int)($_POST['physician_id'] ?? 1);
        $chiefComplaint = trim($_POST['chief_complaint'] ?? '');
        $bp = trim($_POST['bp'] ?? '120/80');
        $temp = trim($_POST['temp'] ?? '36.8°C');
        $weight = trim($_POST['weight'] ?? '60 kg');
        $rr = trim($_POST['rr'] ?? '18/min');
        $hr = trim($_POST['hr'] ?? '75 bpm');
        $diagnosis = trim($_POST['diagnosis'] ?? 'OPD Patient Evaluation');
        $icd10 = trim($_POST['icd10'] ?? 'Z00.0');
        $meds = trim($_POST['medications'] ?? '');

        if ($residentId <= 0 || empty($chiefComplaint)) {
            $_SESSION['nurse_flash_error'] = 'Please select a valid resident patient and fill chief complaint.';
        } else {
            try {
                $notes = "NURSING TRIAGE VITALS: BP: {$bp}, Temp: {$temp}, Wt: {$weight}, RR: {$rr}, HR: {$hr}.";
                $stmt = $pdo->prepare("INSERT INTO consultations (resident_id, physician_id, consultation_date, consultation_time, chief_complaint, diagnosis, icd_code, medications_prescribed, consultation_notes, created_at) VALUES (:res, :phy, CURDATE(), CURTIME(), :chief, :dx, :icd, :meds, :notes, NOW())");
                $stmt->execute([
                    'res' => $residentId,
                    'phy' => $physicianId,
                    'chief' => $chiefComplaint,
                    'dx' => $diagnosis,
                    'icd' => $icd10,
                    'meds' => $meds,
                    'notes' => $notes
                ]);
                portalNotifyResident($pdo, $residentId, "Your OPD Triage & Vital Signs (BP: {$bp}, Temp: {$temp}) were recorded by Public Health Nurse.", "ResidentDashboard.php?tab=history");
                portalNotify($pdo, "New OPD Triage recorded for resident patient", null, 'PHYSICIAN', "RHUAdminDashboard.php");
                $_SESSION['nurse_flash_success'] = 'New OPD Triage record and vital signs saved successfully into database!';
            } catch (Exception $e) {
                $_SESSION['nurse_flash_error'] = 'Database Error: ' . $e->getMessage();
            }
        }
        header('Location: ' . tabUrl('opd'));
        exit;
    }

    // Action: Save Resident Medical Record & Profile Info
    if ($action === 'save_patient_record') {
        $residentId = (int)($_POST['resident_id'] ?? 0);
        
        $height = trim($_POST['height'] ?? '');
        $weight = trim($_POST['weight'] ?? '');
        $bp = trim($_POST['blood_pressure'] ?? '');
        $allergies = trim($_POST['allergies'] ?? '');
        $medHistory = trim($_POST['medical_history'] ?? '');

        try {
            // Ensure table structures are prepared
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS resident_health_profiles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    resident_id INT NOT NULL UNIQUE,
                    height DOUBLE(5,2) NULL,
                    weight DOUBLE(5,2) NULL,
                    blood_pressure VARCHAR(20) NULL,
                    heart_rate INT NULL,
                    temperature DOUBLE(4,1) NULL,
                    last_checkup_date DATE NULL,
                    smoking_status VARCHAR(50) NULL,
                    alcohol_consumption VARCHAR(50) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");
            } catch (Throwable $tSchema) {}

            if ($residentId <= 0) {
                // Register NEW Resident
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $dob = trim($_POST['date_of_birth'] ?? '');
                $gender = trim($_POST['gender'] ?? 'Female');
                $barangay = trim($_POST['barangay'] ?? 'Poblacion');
                $bloodType = trim($_POST['blood_type'] ?? 'O+');
                $philhealthId = trim($_POST['philhealth_id'] ?? '');
                $contactNumber = trim($_POST['contact_number'] ?? '');

                if (empty($firstName) || empty($lastName)) {
                    $_SESSION['nurse_flash_error'] = 'First name and last name are required for new resident registration.';
                    header('Location: ' . tabUrl('patients'));
                    exit;
                }
                $insRes = $pdo->prepare("INSERT INTO residents (first_name, last_name, date_of_birth, gender, barangay, blood_type, philhealth_id, contact_number, allergies, medical_conditions, created_at) VALUES (:fn, :ln, :dob, :gen, :brgy, :bt, :phi, :cn, :alg, :mc, NOW())");
                $insRes->execute([
                    'fn' => $firstName,
                    'ln' => $lastName,
                    'dob' => !empty($dob) ? $dob : '1990-01-01',
                    'gen' => $gender,
                    'brgy' => $barangay,
                    'bt' => $bloodType,
                    'phi' => $philhealthId,
                    'cn' => $contactNumber,
                    'alg' => $allergies,
                    'mc' => $medHistory
                ]);
                $residentId = (int)$pdo->lastInsertId();
            } else {
                // Update existing resident allergies & medical conditions
                $upRes = $pdo->prepare("UPDATE residents SET allergies = :alg, medical_conditions = :mc WHERE id = :rid");
                $upRes->execute(['alg' => $allergies, 'mc' => $medHistory, 'rid' => $residentId]);
            }

            // Save or Update health profile vitals record for the resident
            $chkHp = $pdo->prepare("SELECT id FROM resident_health_profiles WHERE resident_id = :rid LIMIT 1");
            $chkHp->execute(['rid' => $residentId]);
            if ($chkHp->fetchColumn()) {
                $upHp = $pdo->prepare("UPDATE resident_health_profiles SET height = :h, weight = :w, blood_pressure = :bp, last_checkup_date = CURDATE() WHERE resident_id = :rid");
                $upHp->execute([
                    'h' => !empty($height) ? (float)$height : null,
                    'w' => !empty($weight) ? (float)$weight : null,
                    'bp' => $bp,
                    'rid' => $residentId
                ]);
            } else {
                $insHp = $pdo->prepare("INSERT INTO resident_health_profiles (resident_id, height, weight, blood_pressure, last_checkup_date, created_at) VALUES (:rid, :h, :w, :bp, CURDATE(), NOW())");
                $insHp->execute([
                    'rid' => $residentId,
                    'h' => !empty($height) ? (float)$height : null,
                    'w' => !empty($weight) ? (float)$weight : null,
                    'bp' => $bp
                ]);
            }

            $_SESSION['nurse_flash_success'] = 'Resident medical profile saved successfully into database!';
        } catch (Exception $e) {
            $_SESSION['nurse_flash_error'] = 'Database Error: ' . $e->getMessage();
        }
        header('Location: ' . tabUrl('patients'));
        exit;
    }
}

// ----------------------------------------------------
// 2. LIVE MYSQL DATA HYDRATION FROM DATABASE `rhu`
// ----------------------------------------------------
$opdConsultations = [];
$patientRecords = [];
$patientProfilesMap = [];
$immunizationRecords = [];
$nutritionCases = [];
$tbCases = [];
$diseaseReports = [];
$bhwList = [];

$allResidentsList = [];
$nurseCertificateTypes = [];
$allStaffList = [];
$selectedResidentData = null;

if (!empty($pdo)) {
    try {
        // Dropdown option queries
        $allResidentsList = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, barangay FROM residents ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $nurseCertificateTypes = portalEnsureCertificateTypes($pdo, ['Nursing Health Assessment Certificate','Immunization Status Certificate','Vital Signs Assessment Certificate','Community Health Clearance']);
        $allStaffList = $pdo->query("SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) as name, s.staff_type FROM staff s JOIN users u ON s.user_id = u.id ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 1. OPD Consultations (Filtered by logged-in staff/nurse)
        $nurseStaffId = (int)($_SESSION['rhu_staff_login']['staff_id'] ?? 0);
        $nurseUserId = (int)($_SESSION['rhu_staff_login']['id'] ?? $_SESSION['rhu_staff_login']['user_id'] ?? 0);

        if ($nurseStaffId > 0) {
            $opdStmt = $pdo->prepare("
                SELECT c.id, c.resident_id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, c.icd_code as icd10, c.medications_prescribed as medications, r.barangay, c.consultation_date as date, c.referral_needed, c.referral_to, c.consultation_notes, COALESCE(c.consultation_status, 'Scheduled') AS consultation_status
                FROM consultations c
                JOIN residents r ON c.resident_id = r.id
                LEFT JOIN staff doc_s ON c.physician_id = doc_s.id
                WHERE (c.physician_id = :sid OR doc_s.user_id = :uid OR c.chief_complaint LIKE '%Nurse%' OR c.chief_complaint LIKE '%Vaccination%' OR c.chief_complaint LIKE '%Checkup%')
                ORDER BY c.id DESC
            ");
            $opdStmt->execute(['sid' => $nurseStaffId, 'uid' => $nurseUserId]);
        } else {
            $opdStmt = $pdo->query("
                SELECT c.id, c.resident_id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, c.icd_code as icd10, c.medications_prescribed as medications, r.barangay, c.consultation_date as date, c.referral_needed, c.referral_to, c.consultation_notes, COALESCE(c.consultation_status, 'Scheduled') AS consultation_status
                FROM consultations c
                JOIN residents r ON c.resident_id = r.id
                ORDER BY c.id DESC
            ");
        }
        $opdConsultations = $opdStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 2. Patient Records (Residents + Health Profiles)
        $patStmt = $pdo->query("
            SELECT r.id, CONCAT(r.first_name, ' ', r.last_name) as name, r.first_name, r.last_name, r.date_of_birth,
                   TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender,
                   COALESCE(r.blood_type, 'O+') as bloodType,
                   r.barangay, r.philhealth_id as philhealthNo, r.contact_number as contactNo, r.created_at as admissionDate,
                   r.allergies, r.medical_conditions as medicalHistory,
                   hp.height, hp.weight, hp.blood_pressure as bloodPressure, hp.last_checkup_date as lastCheckup
            FROM residents r
            LEFT JOIN resident_health_profiles hp ON hp.resident_id = r.id
            ORDER BY r.id DESC
        ");
        $patientRecords = $patStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Build associative map keyed by resident_id for JS auto-fill
        foreach ($patientRecords as $pr) {
            $patientProfilesMap[(int)$pr['id']] = [
                'id' => (int)$pr['id'],
                'first_name' => $pr['first_name'] ?? '',
                'last_name' => $pr['last_name'] ?? '',
                'name' => $pr['name'] ?? '',
                'bloodType' => !empty($pr['bloodType']) ? $pr['bloodType'] : 'O+',
                'philhealthNo' => $pr['philhealthNo'] ?? '',
                'contactNo' => $pr['contactNo'] ?? '',
                'height' => $pr['height'] ?? '',
                'weight' => $pr['weight'] ?? '',
                'bloodPressure' => $pr['bloodPressure'] ?? '',
                'allergies' => $pr['allergies'] ?? '',
                'medicalHistory' => $pr['medicalHistory'] ?? ''
            ];
        }

        if ($selectedResidentId > 0 && isset($patientProfilesMap[$selectedResidentId])) {
            $selectedResidentData = $patientProfilesMap[$selectedResidentId];
        }

        // 3. Immunization Records
        $immStmt = $pdo->query("
            SELECT vr.id, CONCAT(r.first_name, ' ', r.last_name) as childName, TIMESTAMPDIFF(MONTH, r.date_of_birth, CURDATE()) as ageMonths, r.barangay, sch.vaccine_name as vaccineName, sch.age_group as targetAge, vr.vaccination_date as dateGiven, vr.next_dose_date as nextVisit, vr.batch_number as lot
            FROM vaccination_records vr
            JOIN residents r ON vr.resident_id = r.id
            JOIN immunization_schedules sch ON vr.vaccine_id = sch.id
            ORDER BY vr.id DESC
        ");
        $immunizationRecords = $immStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 4. Nutrition Cases
        $nutrStmt = $pdo->query("
            SELECT hp.id, CONCAT(r.first_name, ' ', r.last_name) as name, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, r.barangay, hp.height, hp.weight
            FROM resident_health_profiles hp
            JOIN residents r ON hp.resident_id = r.id
            ORDER BY hp.id DESC
        ");
        $nutritionCases = $nutrStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 5. TB-DOTS Cases
        $tbStmt = $pdo->query("
            SELECT t.id, CONCAT(r.first_name, ' ', r.last_name) as name, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, t.tb_registration_number, t.tb_type as classification, t.treatment_status, t.treatment_start_date, r.barangay
            FROM tb_patients t
            JOIN residents r ON t.resident_id = r.id
            ORDER BY t.id DESC
        ");
        $tbCases = $tbStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 6. BHW List
        $bhwStmt = $pdo->query("
            SELECT b.id, CONCAT(b.first_name, ' ', b.last_name) as name, b.barangay, b.phone_number, COALESCE(b.is_active, 1) as is_active
            FROM bhw b
            ORDER BY b.id DESC
        ");
        $bhwList = $bhwStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } catch (Exception $e) {
        error_log("NurseDashboard DB Load Error: " . $e->getMessage());
    }
}

$activeTBCount = count(array_filter($tbCases, fn($t) => ($t['treatment_status'] ?? '') === 'Active' || ($t['treatment_status'] ?? '') === 'on-treatment'));
$todayOPDCount = count($opdConsultations);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Health Nurse Portal - ResiHUnity RHU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .safe-area-pb { padding-bottom: env(safe-area-inset-bottom); }
    </style>
  <link rel="stylesheet" href="dashboard-enhancements.css?v=20260728-nurse-theme2">
    <script defer src="dashboard-enhancements.js?v=20260726-controls3"></script>
</head>

<body class="nurse-palette-dashboard min-h-screen bg-slate-50 text-slate-900 selection:bg-emerald-500 selection:text-white">
    <div class="min-h-screen flex flex-col">

        <!-- HEADER -->
        <header class="bg-gradient-to-r from-emerald-800 via-teal-800 to-cyan-900 text-white shadow-xl sticky top-0 z-40 border-b border-emerald-700/40">
            <div class="px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/15 backdrop-blur-md rounded-2xl flex items-center justify-center text-2xl shadow-inner border border-white/20">
                        ⚕
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-base sm:text-lg font-extrabold tracking-tight">Public Health Nurse Portal</h1>
                            <span class="inline-flex items-center gap-1 text-[11px] bg-emerald-500/30 text-emerald-200 px-2.5 py-0.5 rounded-full font-bold border border-emerald-400/30 backdrop-blur-sm">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <?= esc($_SESSION['rhu_staff_login']['name'] ?? 'Public Health Nurse') ?>
                            </span>
                        </div>
                        <p class="text-xs text-teal-200/90 font-medium">Nasugbu Rural Health Unit I — OPD Triage, Vital Assessment & Community Nursing</p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-300/30 text-xs font-bold text-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Live Database Sync
                    </span>
                    <?= portalRenderNotificationButton(); ?>
                    <a href="StaffLogout.php" data-staff-logout class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 hover:bg-red-500/30 text-xs font-bold text-white transition-all border border-white/20 hover:border-red-400/40" title="Log Out">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span>Log out</span>
                    </a>
                </div>
            </div>

            <!-- DESKTOP TABS -->
            <div class="hidden sm:flex px-6 gap-1.5 overflow-x-auto pt-1 pb-0">
                <?php foreach ($tabs as $id => [$label, $icon]): ?>
                    <a href="<?= esc(tabUrl($id)); ?>"
                        class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold rounded-t-xl whitespace-nowrap flex-shrink-0 transition-all <?= $tab === $id ? 'bg-white text-emerald-900 shadow-lg font-extrabold border-t-2 border-emerald-500' : 'text-emerald-100 hover:bg-white/10 hover:text-white'; ?>">
                        <span class="text-sm"><?= $icon; ?></span><?= esc($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </header>

        <!-- FLASH NOTIFICATIONS -->
        <main class="max-w-7xl mx-auto w-full px-3 sm:px-6 py-4 sm:py-6 space-y-5 pb-28 sm:pb-8 flex-1">
            <?php if ($flashSuccess): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-900 px-4 py-3 rounded-2xl text-sm font-bold shadow-sm flex items-center justify-between">
                    <span class="flex items-center gap-2">✓ <?= esc($flashSuccess); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded-2xl text-sm font-bold shadow-sm flex items-center justify-between">
                    <span class="flex items-center gap-2">⚠ <?= esc($flashError); ?></span>
                </div>
            <?php endif; ?>

            <!-- TAB 1: OVERVIEW -->
            <?php if ($tab === 'overview'): ?>
                <div class="space-y-5">
                    <!-- SURVEILLANCE BANNER -->
                    <div class="bg-gradient-to-r from-red-50 via-rose-50 to-orange-50 border border-red-200/80 rounded-2xl p-4 sm:p-5 flex items-start gap-3 shadow-sm">
                        <span class="w-9 h-9 rounded-xl bg-red-100 text-red-600 flex items-center justify-center text-xl shrink-0">⚠</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-extrabold text-red-900 text-sm sm:text-base">DOH Active Disease Surveillance Alert</p>
                                <span class="bg-red-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">PIDSR Active</span>
                            </div>
                            <p class="text-xs sm:text-sm text-red-700 mt-0.5">Continuous community monitoring ongoing for Dengue, Typhoid, and Leptospirosis across Nasugbu barangays.</p>
                        </div>
                        <a href="<?= esc(tabUrl('disease')); ?>" class="text-xs bg-red-600 hover:bg-red-700 text-white font-bold px-3.5 py-2 rounded-xl whitespace-nowrap shadow-md transition-all">View Surveillance</a>
                    </div>

                    <!-- METRIC CARDS GRID -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="<?= esc(tabUrl('opd')); ?>" class="group bg-gradient-to-br from-emerald-500/10 via-white to-white rounded-2xl p-5 shadow-sm hover:shadow-md border border-emerald-100/80 transition-all">
                            <div class="flex items-center justify-between">
                                <span class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-700 flex items-center justify-center text-lg font-bold">⚕</span>
                                <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">Active</span>
                            </div>
                            <p class="text-3xl font-black text-emerald-900 mt-3 group-hover:scale-105 transition-transform"><?= $todayOPDCount; ?></p>
                            <p class="text-xs font-bold text-gray-800 mt-1">Received OPD Consults</p>
                            <p class="text-[11px] text-gray-400 font-medium">Assigned Nursing Triage</p>
                        </a>

                        <a href="<?= esc(tabUrl('patients')); ?>" class="group bg-gradient-to-br from-blue-500/10 via-white to-white rounded-2xl p-5 shadow-sm hover:shadow-md border border-blue-100/80 transition-all">
                            <div class="flex items-center justify-between">
                                <span class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-700 flex items-center justify-center text-lg font-bold">▤</span>
                                <span class="text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-200">Registry</span>
                            </div>
                            <p class="text-3xl font-black text-blue-900 mt-3 group-hover:scale-105 transition-transform"><?= count($patientRecords); ?></p>
                            <p class="text-xs font-bold text-gray-800 mt-1">Registered Patients</p>
                            <p class="text-[11px] text-gray-400 font-medium">Resident Profiles</p>
                        </a>

                        <a href="<?= esc(tabUrl('immunization')); ?>" class="group bg-gradient-to-br from-purple-500/10 via-white to-white rounded-2xl p-5 shadow-sm hover:shadow-md border border-purple-100/80 transition-all">
                            <div class="flex items-center justify-between">
                                <span class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-700 flex items-center justify-center text-lg font-bold">⌁</span>
                                <span class="text-xs font-bold text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full border border-purple-200">EPI Program</span>
                            </div>
                            <p class="text-3xl font-black text-purple-900 mt-3 group-hover:scale-105 transition-transform"><?= count($immunizationRecords); ?></p>
                            <p class="text-xs font-bold text-gray-800 mt-1">Child Immunizations</p>
                            <p class="text-[11px] text-gray-400 font-medium">Vaccination Records</p>
                        </a>

                        <a href="<?= esc(tabUrl('tb')); ?>" class="group bg-gradient-to-br from-amber-500/10 via-white to-white rounded-2xl p-5 shadow-sm hover:shadow-md border border-amber-100/80 transition-all">
                            <div class="flex items-center justify-between">
                                <span class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-700 flex items-center justify-center text-lg font-bold">🔬</span>
                                <span class="text-xs font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">DOTS</span>
                            </div>
                            <p class="text-3xl font-black text-amber-900 mt-3 group-hover:scale-105 transition-transform"><?= $activeTBCount; ?></p>
                            <p class="text-xs font-bold text-gray-800 mt-1">Active TB Cases</p>
                            <p class="text-[11px] text-gray-400 font-medium">Treatment Monitoring</p>
                        </a>
                    </div>

                    <!-- RECEIVED OPD CONSULTATION & TRIAGE LOG SUMMARY -->
                    <div class="bg-white rounded-2xl p-5 shadow-sm border border-emerald-100/90 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <div>
                                <h3 class="font-extrabold text-gray-900 text-base flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Received Resident Consultation & Triage Queue
                                </h3>
                                <p class="text-xs text-gray-500">Live consultation requests submitted by residents for Nursing Triage</p>
                            </div>
                            <a href="<?= esc(tabUrl('opd', ['modal' => 'new_triage'])); ?>" class="px-3.5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5">
                                <span>+</span> Record Triage Vitals
                            </a>
                        </div>

                        <?php if (empty($opdConsultations)): ?>
                            <div class="text-center py-8 bg-slate-50/50 rounded-2xl border border-dashed border-gray-200">
                                <span class="text-3xl block mb-2">📋</span>
                                <p class="text-sm font-bold text-gray-700">No OPD Consultations Found</p>
                                <p class="text-xs text-gray-400 mt-0.5">When residents book appointments for nursing care, they will appear here automatically.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach (array_slice($opdConsultations, 0, 5) as $c): ?>
                                    <?php $vitals = parseTriageVitals($c['consultation_notes'] ?? ''); ?>
                                    <div class="bg-gradient-to-r from-slate-50/80 to-white rounded-xl p-4 border border-gray-200/80 hover:border-emerald-200 transition-all space-y-3">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <p class="font-extrabold text-gray-900 text-sm sm:text-base"><?= esc($c['patientName']); ?></p>
                                                    <span class="text-xs font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-md">
                                                        <?= esc($c['age'] ?? 'N/A'); ?>y • <?= esc($c['gender']); ?>
                                                    </span>
                                                    <span class="text-xs font-semibold text-purple-700 bg-purple-50 px-2 py-0.5 rounded-md border border-purple-100">
                                                        📍 Barangay <?= esc($c['barangay']); ?>
                                                    </span>
                                                </div>
                                                <p class="text-xs font-semibold text-blue-900 mt-1">Chief Complaint: <span class="text-gray-800 font-normal"><?= esc($c['chiefComplaint']); ?></span></p>
                                            </div>
                                            <div class="text-right">
                                                <span class="font-mono text-xs bg-slate-100 text-slate-800 font-bold px-2.5 py-1 rounded-lg border border-slate-200"><?= esc($c['icd10'] ?: 'OPD Triage'); ?></span>
                                                <p class="text-[10px] font-semibold text-gray-400 mt-1">📅 <?= esc($c['date']); ?></p>
                                            </div>
                                        </div>

                                        <?php if (!empty($vitals)): ?>
                                            <div class="flex flex-wrap items-center gap-2 bg-emerald-50/50 p-2.5 rounded-xl border border-emerald-100/60 text-xs">
                                                <span class="font-bold text-emerald-900 text-[11px] mr-1">Vitals:</span>
                                                <?php if (!empty($vitals['bp'])): ?><span class="bg-white text-emerald-800 font-mono font-bold px-2 py-0.5 rounded-md border border-emerald-200 shadow-2xs">BP: <?= esc($vitals['bp']); ?></span><?php endif; ?>
                                                <?php if (!empty($vitals['temp'])): ?><span class="bg-white text-rose-700 font-mono font-bold px-2 py-0.5 rounded-md border border-rose-200 shadow-2xs">Temp: <?= esc($vitals['temp']); ?></span><?php endif; ?>
                                                <?php if (!empty($vitals['weight'])): ?><span class="bg-white text-blue-700 font-mono font-bold px-2 py-0.5 rounded-md border border-blue-200 shadow-2xs">Wt: <?= esc($vitals['weight']); ?></span><?php endif; ?>
                                                <?php if (!empty($vitals['hr'])): ?><span class="bg-white text-amber-700 font-mono font-bold px-2 py-0.5 rounded-md border border-amber-200 shadow-2xs">HR: <?= esc($vitals['hr']); ?></span><?php endif; ?>
                                                <?php if (!empty($vitals['rr'])): ?><span class="bg-white text-teal-700 font-mono font-bold px-2 py-0.5 rounded-md border border-teal-200 shadow-2xs">RR: <?= esc($vitals['rr']); ?></span><?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="text-xs text-gray-600 font-mono bg-white p-2.5 rounded-xl border border-gray-200/60">
                                            <?= esc($c['consultation_notes']); ?>
                                        </div>

                                        <!-- NURSE RESPONSE / UPDATE FORM -->
                                        <details class="group border-t border-emerald-100 pt-2" open>
                                            <summary class="cursor-pointer text-xs font-bold text-emerald-700 hover:text-emerald-900 flex items-center justify-between py-1">
                                                <span>💬 Answer / Update Consultation Response for Resident</span>
                                                <span class="text-[10px] bg-emerald-100 text-emerald-800 font-extrabold px-2 py-0.5 rounded-md">Status: <?= esc($c['consultation_status']); ?></span>
                                            </summary>
                                            <form method="post" class="mt-2 bg-emerald-50/50 p-3 rounded-xl border border-emerald-200/70 space-y-2.5">
                                                <input type="hidden" name="action" value="answer_consultation">
                                                <input type="hidden" name="consultation_id" value="<?= (int)$c['id']; ?>">
                                                <input type="hidden" name="resident_id" value="<?= (int)($c['resident_id'] ?? 0); ?>">
                                                
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Clinical Diagnosis / Nursing Assessment</label>
                                                        <input type="text" name="diagnosis" value="<?= esc($c['diagnosis'] ?? ''); ?>" placeholder="e.g. Acute Upper Respiratory Tract Infection" class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-emerald-500 bg-white" required>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Consultation Status</label>
                                                        <select name="consultation_status" class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-emerald-500 bg-white font-bold text-emerald-900">
                                                            <option value="Completed" <?= ($c['consultation_status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                            <option value="In Progress" <?= ($c['consultation_status'] ?? '') === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                            <option value="Scheduled" <?= ($c['consultation_status'] ?? '') === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                            <option value="Referred" <?= ($c['consultation_status'] ?? '') === 'Referred' ? 'selected' : ''; ?>>Referred to Doctor</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Nurse Notes &amp; Recommendations for Resident</label>
                                                    <textarea name="consultation_notes" rows="2" placeholder="Enter nursing triage advice, vital signs notes, and instructions..." class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-emerald-500 bg-white resize-none"><?= esc($c['consultation_notes'] ?? ''); ?></textarea>
                                                </div>

                                                <div>
                                                    <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Medications Prescribed / Given</label>
                                                    <input type="text" name="medications_prescribed" value="<?= esc($c['medications'] ?? ''); ?>" placeholder="e.g. Paracetamol 500mg 1 tab q8h prn" class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-emerald-500 bg-white">
                                                </div>

                                                <div class="flex justify-end pt-1">
                                                    <button type="submit" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-extrabold rounded-lg shadow-sm transition-all flex items-center gap-1">
                                                        <span>✓</span> Save Response &amp; Notify Resident
                                                    </button>
                                                </div>
                                            </form>
                                        </details>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 2: OPD TRIAGE & NURSING LOG -->
            <?php if ($tab === 'opd'): ?>
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-emerald-100 shadow-sm">
                        <div>
                            <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">⚕ OPD Triage & Nursing Assessment Log</h2>
                            <p class="text-xs text-gray-500">Record and review vital signs assessment for all outpatient consultations</p>
                        </div>
                        <a href="<?= esc(tabUrl('opd', ['modal' => 'new_triage'])); ?>" class="px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5">
                            <span>+</span> Record Patient Triage Vitals
                        </a>
                    </div>

                    <?php if (empty($opdConsultations)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-200/80 shadow-sm">
                            <span class="text-4xl block mb-2">📋</span>
                            <p class="text-sm font-bold text-gray-700">No OPD Triage Consultations Recorded</p>
                            <p class="text-xs text-gray-400 mt-0.5">Click "+ Record Patient Triage Vitals" above to start logging patient vital signs.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($opdConsultations as $c): ?>
                                <?php $vitals = parseTriageVitals($c['consultation_notes'] ?? ''); ?>
                                <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-200/80 hover:border-emerald-200 transition-all space-y-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 pb-3">
                                        <div>
                                            <p class="font-extrabold text-gray-900 text-base"><?= esc($c['patientName']); ?> <span class="text-xs text-gray-500 font-semibold">(<?= esc($c['age'] ?? 'N/A'); ?>y • <?= esc($c['gender']); ?>)</span></p>
                                            <p class="text-xs font-bold text-emerald-900 mt-1">Chief Complaint: <span class="text-gray-800 font-normal"><?= esc($c['chiefComplaint']); ?></span></p>
                                            <p class="text-[11px] text-gray-400 mt-0.5">Barangay: <?= esc($c['barangay']); ?> · Date: <?= esc($c['date']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-mono text-xs bg-slate-100 text-slate-800 font-bold px-2.5 py-1 rounded-lg border border-slate-200"><?= esc($c['icd10'] ?: 'OPD Triage'); ?></span>
                                            <?php if (!empty($c['referral_needed'])): ?>
                                                <span class="block mt-1 text-[11px] font-bold text-purple-700 bg-purple-100 px-2 py-0.5 rounded-full">Referred to <?= esc($c['referral_to']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($vitals)): ?>
                                        <div class="flex flex-wrap items-center gap-2 bg-emerald-50/50 p-2.5 rounded-xl border border-emerald-100/60 text-xs">
                                            <span class="font-bold text-emerald-900 text-[11px] mr-1">Vitals:</span>
                                            <?php if (!empty($vitals['bp'])): ?><span class="bg-white text-emerald-800 font-mono font-bold px-2.5 py-0.5 rounded-md border border-emerald-200">BP: <?= esc($vitals['bp']); ?></span><?php endif; ?>
                                            <?php if (!empty($vitals['temp'])): ?><span class="bg-white text-rose-700 font-mono font-bold px-2.5 py-0.5 rounded-md border border-rose-200">Temp: <?= esc($vitals['temp']); ?></span><?php endif; ?>
                                            <?php if (!empty($vitals['weight'])): ?><span class="bg-white text-blue-700 font-mono font-bold px-2.5 py-0.5 rounded-md border border-blue-200">Wt: <?= esc($vitals['weight']); ?></span><?php endif; ?>
                                            <?php if (!empty($vitals['hr'])): ?><span class="bg-white text-amber-700 font-mono font-bold px-2.5 py-0.5 rounded-md border border-amber-200">HR: <?= esc($vitals['hr']); ?></span><?php endif; ?>
                                            <?php if (!empty($vitals['rr'])): ?><span class="bg-white text-teal-700 font-mono font-bold px-2.5 py-0.5 rounded-md border border-teal-200">RR: <?= esc($vitals['rr']); ?></span><?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60 text-xs">
                                        <p class="font-bold text-slate-700 mb-1">Clinical Assessment Notes:</p>
                                        <p class="text-slate-800 font-mono"><?= esc($c['consultation_notes']); ?></p>
                                    </div>

                                    <?php if (!empty($c['medications'])): ?>
                                        <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                            <span class="text-xs font-bold text-gray-500">Prescribed Meds:</span>
                                            <?php foreach (array_filter(explode(',', $c['medications'])) as $med): ?>
                                                <span class="text-xs bg-blue-50 text-blue-700 border border-blue-100 font-semibold px-2.5 py-0.5 rounded-full"><?= esc(trim($med)); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 3: PATIENT RECORDS -->
            <?php if ($tab === 'patients'): ?>
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-blue-100 shadow-sm">
                        <div>
                            <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">▤ Municipal Resident Health Records</h2>
                            <p class="text-xs text-gray-500">Complete resident health profiles, medical history, allergies, and vitals registry</p>
                        </div>
                        <a href="<?= esc(tabUrl('patients', ['modal' => 'new_patient_record'])); ?>" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5">
                            <span>+</span> Add / Register Medical Record
                        </a>
                    </div>

                    <?php if (empty($patientRecords)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-200/80 shadow-sm">
                            <span class="text-4xl block mb-2">👤</span>
                            <p class="text-sm font-bold text-gray-700">No Patient Records Registered</p>
                            <p class="text-xs text-gray-400 mt-0.5">Click "+ Add / Register Medical Record" above to record a resident's medical info.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($patientRecords as $p): ?>
                                <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-200/80 hover:border-blue-200 transition-all space-y-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 pb-3">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded">RES-<?= $p['id']; ?></span>
                                                <p class="font-extrabold text-gray-900 text-base"><?= esc($p['name']); ?></p>
                                                <span class="text-xs font-bold text-purple-800 bg-purple-50 px-2 py-0.5 rounded border border-purple-100">
                                                    📍 <?= esc($p['barangay']); ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-600 mt-1 font-semibold">
                                                Age: <?= esc($p['age'] ?? 'N/A'); ?>y • Sex: <?= esc($p['gender']); ?> • PhilHealth No: <span class="font-mono text-blue-900"><?= esc($p['philhealthNo'] ?: 'N/A'); ?></span>
                                                <?php if (!empty($p['contactNo'])): ?> · 📞 <?= esc($p['contactNo']); ?><?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-xs text-red-700 bg-red-50 px-2.5 py-1 rounded-lg border border-red-200">
                                                Blood Type: <?= esc($p['bloodType'] ?: 'O+'); ?>
                                            </span>
                                            <a href="<?= esc(tabUrl('patients', ['modal' => 'new_patient_record', 'resident_id' => $p['id']])); ?>" class="px-3 py-1 bg-slate-100 hover:bg-blue-50 text-blue-700 border border-slate-200 hover:border-blue-300 font-bold text-xs rounded-xl transition-all">
                                                ✏ Edit Medical Record
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Medical Profile Details Pill Grid -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                                        <div class="bg-slate-50/80 p-2.5 rounded-xl border border-slate-100">
                                            <span class="font-bold text-rose-900 block text-[11px]">⚠ Known Allergies:</span>
                                            <span class="text-gray-800 font-medium"><?= esc($p['allergies'] ?: 'None Reported'); ?></span>
                                        </div>
                                        <div class="bg-slate-50/80 p-2.5 rounded-xl border border-slate-100">
                                            <span class="font-bold text-purple-900 block text-[11px]">🏥 Pre-existing Medical History:</span>
                                            <span class="text-gray-800 font-medium"><?= esc($p['medicalHistory'] ?: 'No chronic conditions recorded'); ?></span>
                                        </div>
                                    </div>

                                    <?php if (!empty($p['height']) || !empty($p['weight']) || !empty($p['bloodPressure'])): ?>
                                        <div class="flex flex-wrap items-center gap-2 bg-emerald-50/40 p-2 rounded-xl border border-emerald-100/60 text-xs">
                                            <span class="font-bold text-emerald-900 text-[11px] mr-1">Latest Physical Profile:</span>
                                            <?php if (!empty($p['height'])): ?><span class="bg-white text-emerald-800 font-mono font-bold px-2 py-0.5 rounded border border-emerald-200">Ht: <?= esc($p['height']); ?> cm</span><?php endif; ?>
                                            <?php if (!empty($p['weight'])): ?><span class="bg-white text-blue-800 font-mono font-bold px-2 py-0.5 rounded border border-blue-200">Wt: <?= esc($p['weight']); ?> kg</span><?php endif; ?>
                                            <?php if (!empty($p['bloodPressure'])): ?><span class="bg-white text-purple-800 font-mono font-bold px-2 py-0.5 rounded border border-purple-200">BP: <?= esc($p['bloodPressure']); ?></span><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 4: IMMUNIZATION -->
            <?php if ($tab === 'immunization'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">⌁ Expanded Program on Immunization (EPI) Monitoring</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div class="bg-white rounded-2xl p-4 shadow-sm border border-emerald-100 text-center">
                            <p class="text-3xl font-black text-emerald-700"><?= count($immunizationRecords); ?></p>
                            <p class="text-xs font-bold text-gray-600 mt-1">Administered Records</p>
                        </div>
                        <div class="bg-white rounded-2xl p-4 shadow-sm border border-amber-100 text-center">
                            <p class="text-3xl font-black text-amber-600">3</p>
                            <p class="text-xs font-bold text-gray-600 mt-1">Due This Month</p>
                        </div>
                        <div class="bg-white rounded-2xl p-4 shadow-sm border border-rose-100 text-center">
                            <p class="text-3xl font-black text-rose-600">0</p>
                            <p class="text-xs font-bold text-gray-600 mt-1">Overdue Vaccines</p>
                        </div>
                    </div>

                    <?php if (empty($immunizationRecords)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-200/80 shadow-sm">
                            <span class="text-4xl block mb-2">👶</span>
                            <p class="text-sm font-bold text-gray-700">No Immunization Records Found</p>
                            <p class="text-xs text-gray-400 mt-0.5">Vaccination records will appear here as doses are administered and logged.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($immunizationRecords as $im): ?>
                                <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-200/80 flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-extrabold text-gray-900 text-sm sm:text-base"><?= esc($im['childName']); ?></p>
                                        <p class="text-xs text-gray-600 mt-0.5">Vaccine: <strong class="text-indigo-900 font-extrabold"><?= esc($im['vaccineName']); ?></strong> (<?= esc($im['targetAge']); ?>) · Batch: <?= esc($im['lot']); ?></p>
                                        <p class="text-[11px] text-gray-400 mt-0.5">Barangay: <?= esc($im['barangay']); ?> · Given: <?= esc($im['dateGiven']); ?> · Next Visit: <strong class="text-emerald-700 font-bold"><?= esc($im['nextVisit']); ?></strong></p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shrink-0">Administered</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 5: NUTRITION (OPT+) -->
            <?php if ($tab === 'nutrition'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">⚖ Operation Timbang Plus (OPT+) Child Nutrition</h2>
                    <?php if (empty($nutritionCases)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-200/80 shadow-sm">
                            <span class="text-4xl block mb-2">⚖</span>
                            <p class="text-sm font-bold text-gray-700">No Operation Timbang Profiles Recorded</p>
                            <p class="text-xs text-gray-400 mt-0.5">Child growth monitoring measurements will display here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($nutritionCases as $n): ?>
                                <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-200/80 space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <p class="font-extrabold text-gray-900 text-sm"><?= esc($n['name']); ?> <span class="text-xs text-gray-500 font-semibold">(<?= esc($n['age']); ?>y / <?= esc($n['gender']); ?>)</span></p>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">Normal Growth</span>
                                    </div>
                                    <div class="flex gap-4 text-xs font-semibold text-gray-600 bg-slate-50 p-3 rounded-xl border border-slate-200/60">
                                        <span>Barangay: <strong class="text-gray-900"><?= esc($n['barangay']); ?></strong></span>
                                        <span>Height: <strong class="text-emerald-700"><?= esc($n['height']); ?> cm</strong></span>
                                        <span>Weight: <strong class="text-blue-700"><?= esc($n['weight']); ?> kg</strong></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 6: TB-DOTS -->
            <?php if ($tab === 'tb'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">🔬 TB-DOTS Case Management &amp; Treatment Adherence</h2>
                    <?php if (empty($tbCases)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-200/80 shadow-sm">
                            <span class="text-4xl block mb-2">🔬</span>
                            <p class="text-sm font-bold text-gray-700">No TB-DOTS Cases Recorded</p>
                            <p class="text-xs text-gray-400 mt-0.5">Tuberculosis patients on treatment regimen will be listed here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($tbCases as $tb): ?>
                                <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-200/80 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-extrabold text-gray-900 text-sm sm:text-base"><?= esc($tb['name']); ?> <span class="text-xs text-gray-500 font-semibold">(<?= esc($tb['age']); ?>y / <?= esc($tb['gender']); ?>)</span></p>
                                            <p class="text-xs text-gray-500 font-mono mt-0.5">DOH Reg No: <?= esc($tb['tb_registration_number']); ?> · Type: <?= esc($tb['classification']); ?></p>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-200"><?= esc($tb['treatment_status']); ?></span>
                                    </div>
                                    <p class="text-xs text-gray-600 bg-slate-50 p-2.5 rounded-xl border border-slate-100">Barangay: <strong><?= esc($tb['barangay']); ?></strong> · Treatment Started: <strong><?= esc($tb['treatment_start_date']); ?></strong></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 7: DISEASE SURVEILLANCE (PIDSR) -->
            <?php if ($tab === 'disease'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">⚠ Disease Surveillance (PIDSR)</h2>
                    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-200/80 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <h3 class="font-extrabold text-gray-900 text-sm">Notifiable Diseases Active Surveillance</h3>
                            <span class="text-xs font-bold bg-red-100 text-red-800 px-2.5 py-1 rounded-full border border-red-200">DOH Region IV-A</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div class="p-4 bg-red-50/80 border border-red-200 rounded-2xl space-y-1">
                                <p class="font-extrabold text-red-900 text-sm">Dengue Fever (ICD-10: A90)</p>
                                <p class="text-red-700">Community vector control and larval source reduction active across barangays.</p>
                            </div>
                            <div class="p-4 bg-amber-50/80 border border-amber-200 rounded-2xl space-y-1">
                                <p class="font-extrabold text-amber-900 text-sm">Leptospirosis (ICD-10: A27)</p>
                                <p class="text-amber-700">Post-flood prophylaxis distribution and health education ongoing.</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 8: BHW MANAGEMENT -->
            <?php if ($tab === 'bhw'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">👤 Barangay Health Worker (BHW) Supervisory Registry</h2>
                    <?php if (empty($bhwList)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-200/80 shadow-sm">
                            <span class="text-4xl block mb-2">👤</span>
                            <p class="text-sm font-bold text-gray-700">No BHW Volunteers Registered</p>
                            <p class="text-xs text-gray-400 mt-0.5">Assigned Barangay Health Workers will be listed here.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php foreach ($bhwList as $b): ?>
                                <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-200/80 flex items-center justify-between gap-3 hover:border-emerald-200 transition-all">
                                    <div>
                                        <p class="font-extrabold text-gray-900 text-sm sm:text-base"><?= esc($b['name']); ?></p>
                                        <p class="text-xs text-gray-600 mt-0.5">Barangay: <strong class="text-purple-900"><?= esc($b['barangay']); ?></strong></p>
                                        <p class="text-xs font-mono text-gray-500 mt-0.5">📞 Contact: <?= esc($b['phone_number'] ?: 'Not recorded'); ?></p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shrink-0">Active BHW</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($tab === 'certificates'): ?>
                <?= portalRenderCertificateIssuancePanel($pdo, $allResidentsList, $nurseCertificateTypes, (int)($_SESSION['rhu_staff_login']['staff_id'] ?? 0), 'emerald') ?>
            <?php endif; ?>
        </main>

        <!-- MOBILE BOTTOM TAB BAR -->
        <nav class="sm:hidden fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-200 safe-area-pb shadow-2xl">
            <div class="flex items-stretch">
                <?php foreach ($tabs as $id => [$label, $icon]): ?>
                    <a href="<?= esc(tabUrl($id)); ?>"
                        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-semibold transition-colors relative <?= $tab === $id ? 'text-emerald-700 font-extrabold' : 'text-gray-400'; ?>">
                        <?php if ($tab === $id): ?>
                            <span class="absolute top-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-emerald-600 rounded-full"></span>
                        <?php endif; ?>
                        <span class="text-base leading-none"><?= $icon; ?></span>
                        <span class="truncate px-0.5"><?= esc($label); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>

    </div>

    <!-- MODAL: NEW OPD TRIAGE RECORD -->
    <?php if ($modal === 'new_triage'): ?>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-end sm:items-center justify-center z-50 p-3 sm:p-4">
            <div class="bg-white rounded-3xl shadow-2xl w-full sm:max-w-xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95">
                <div class="p-5 border-b flex items-center justify-between bg-gradient-to-r from-emerald-800 to-teal-800 text-white rounded-t-3xl shrink-0">
                    <div>
                        <h2 class="text-base font-extrabold flex items-center gap-2">⚕ Record OPD Patient Triage &amp; Vitals</h2>
                        <p class="text-xs text-emerald-200">Log patient chief complaint and vital signs assessment</p>
                    </div>
                    <a href="<?= esc(tabUrl('opd')); ?>" class="text-emerald-100 hover:text-white text-lg font-bold w-8 h-8 rounded-full bg-white/10 flex items-center justify-center">✕</a>
                </div>
                <form class="p-5 space-y-4 text-xs overflow-y-auto" method="post">
                    <input type="hidden" name="action" value="save_triage">

                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Select Patient Resident *</label>
                        <select name="resident_id" required class="w-full p-3 border border-gray-300 rounded-xl text-sm font-semibold focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none">
                            <option value="">-- Select Resident Patient --</option>
                            <?php foreach ($allResidentsList as $r): ?>
                                <option value="<?= esc($r['id']); ?>"><?= esc($r['name']); ?> (<?= esc($r['barangay']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Attending Physician / Staff *</label>
                        <select name="physician_id" required class="w-full p-3 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none">
                            <?php foreach ($allStaffList as $st): ?>
                                <option value="<?= esc($st['id']); ?>"><?= esc($st['name']); ?> (<?= esc($st['staff_type']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Chief Health Complaint *</label>
                        <input name="chief_complaint" required placeholder="e.g., High fever, productive cough, and body malaise" class="w-full p-3 border border-gray-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none">
                    </div>

                    <div class="bg-emerald-50/60 p-4 rounded-2xl border border-emerald-100 space-y-3">
                        <p class="font-extrabold text-emerald-900 text-xs">Vital Signs Assessment</p>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">BP (mmHg)</label>
                                <input name="bp" value="120/80" class="w-full p-2 border border-gray-300 rounded-lg text-xs font-mono font-bold text-gray-800">
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Temp (°C)</label>
                                <input name="temp" value="36.8°C" class="w-full p-2 border border-gray-300 rounded-lg text-xs font-mono font-bold text-gray-800">
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Weight (kg)</label>
                                <input name="weight" value="60 kg" class="w-full p-2 border border-gray-300 rounded-lg text-xs font-mono font-bold text-gray-800">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Respiratory Rate</label>
                                <input name="rr" value="18/min" class="w-full p-2 border border-gray-300 rounded-lg text-xs font-bold text-gray-800">
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Heart Rate</label>
                                <input name="hr" value="75 bpm" class="w-full p-2 border border-gray-300 rounded-lg text-xs font-bold text-gray-800">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Primary Diagnosis</label>
                            <input name="diagnosis" value="Acute Upper Respiratory Tract Infection" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs font-semibold">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">ICD-10 Code</label>
                            <input name="icd10" value="J06.9" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs font-mono font-bold">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Prescribed Medications</label>
                        <input name="medications" placeholder="Paracetamol 500mg, Amoxicillin 500mg" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs">
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="<?= esc(tabUrl('opd')); ?>" class="flex-1 py-3 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-3 bg-emerald-700 text-white rounded-xl text-xs font-extrabold hover:bg-emerald-800 shadow-md transition-all">Save Nursing Triage Vitals</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- MODAL: ADD / EDIT RESIDENT MEDICAL RECORD -->
    <?php if ($modal === 'new_patient_record'): ?>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-end sm:items-center justify-center z-50 p-3 sm:p-4">
            <div class="bg-white rounded-3xl shadow-2xl w-full sm:max-w-2xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95">
                <div class="p-5 border-b flex items-center justify-between bg-gradient-to-r from-blue-700 to-indigo-800 text-white rounded-t-3xl shrink-0">
                    <div>
                        <h2 class="text-base font-extrabold flex items-center gap-2">▤ Save Resident Medical Record &amp; Health Profile</h2>
                        <p class="text-xs text-blue-200">Select a resident to auto-fill their info. Medical history and vitals can be edited.</p>
                    </div>
                    <a href="<?= esc(tabUrl('patients')); ?>" class="text-blue-100 hover:text-white text-lg font-bold w-8 h-8 rounded-full bg-white/10 flex items-center justify-center">✕</a>
                </div>
                <form class="p-5 space-y-4 text-xs overflow-y-auto" method="post">
                    <input type="hidden" name="action" value="save_patient_record">

                    <div class="bg-blue-50/60 p-4 rounded-2xl border border-blue-100 space-y-3">
                        <p class="font-extrabold text-blue-950 text-xs">Resident Identity Selection</p>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Select Existing Resident (or leave blank to create new) *</label>
                            <select name="resident_id" id="nurse_resident_select" onchange="onNurseResidentSelectChange(this)" class="w-full p-3 border border-gray-300 rounded-xl text-sm font-semibold focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none">
                                <option value="0">-- Create New Resident Record --</option>
                                <?php foreach ($allResidentsList as $r): ?>
                                    <option value="<?= esc($r['id']); ?>" <?= $selectedResidentId === (int)$r['id'] ? 'selected' : '' ?>><?= esc($r['name']); ?> (<?= esc($r['barangay']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Read-only locked callout notice for existing resident -->
                        <div id="nurse_locked_fields_notice" class="hidden p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 text-xs font-semibold flex items-center gap-2">
                            <span>🔒</span>
                            <span>Official records for <strong>Blood Type</strong>, <strong>PhilHealth ID</strong>, and <strong>Contact Number</strong> are locked. You can edit all clinical medical history, allergies, and vitals below.</span>
                        </div>

                        <div id="nurse_new_resident_fields" class="grid grid-cols-2 gap-3 pt-1">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">First Name</label>
                                <input name="first_name" id="nurse_first_name" placeholder="First Name" value="<?= esc($selectedResidentData['first_name'] ?? '') ?>" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs font-semibold">
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Last Name</label>
                                <input name="last_name" id="nurse_last_name" placeholder="Last Name" value="<?= esc($selectedResidentData['last_name'] ?? '') ?>" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs font-semibold">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2.5">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1 flex items-center justify-between">
                                <span>Blood Type</span>
                                <span class="text-[10px] text-gray-400 font-normal">🔒 Locked</span>
                            </label>
                            <select name="blood_type" id="nurse_blood_type" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs font-bold">
                                <?php foreach (['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'] as $bt): ?>
                                    <option value="<?= $bt ?>" <?= ($selectedResidentData['bloodType'] ?? 'O+') === $bt ? 'selected' : '' ?>><?= $bt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1 flex items-center justify-between">
                                <span>PhilHealth ID</span>
                                <span class="text-[10px] text-gray-400 font-normal">🔒 Locked</span>
                            </label>
                            <input name="philhealth_id" id="nurse_philhealth_id" value="<?= esc($selectedResidentData['philhealthNo'] ?? '') ?>" placeholder="PH-12-345678901-2" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1 flex items-center justify-between">
                                <span>Contact Phone</span>
                                <span class="text-[10px] text-gray-400 font-normal">🔒 Locked</span>
                            </label>
                            <input name="contact_number" id="nurse_contact_number" value="<?= esc($selectedResidentData['contactNo'] ?? '') ?>" placeholder="0917 123 4567" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs font-mono">
                        </div>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="font-extrabold text-slate-900 text-xs">Physical Vitals Profile</p>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">✏ Editable by Nurse</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2.5">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Height (cm)</label>
                                <input name="height" id="nurse_height" value="<?= esc($selectedResidentData['height'] ?? '') ?>" placeholder="e.g. 165" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs font-bold text-gray-800">
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Weight (kg)</label>
                                <input name="weight" id="nurse_weight" value="<?= esc($selectedResidentData['weight'] ?? '') ?>" placeholder="e.g. 62" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs font-bold text-gray-800">
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Baseline Blood Pressure</label>
                                <input name="blood_pressure" id="nurse_blood_pressure" value="<?= esc($selectedResidentData['bloodPressure'] ?? '') ?>" placeholder="120/80" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs font-mono font-bold text-gray-800">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-rose-950 mb-1 flex items-center justify-between">
                            <span>Known Drug & Food Allergies *</span>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">✏ Editable by Nurse</span>
                        </label>
                        <textarea name="allergies" id="nurse_allergies" rows="2" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs resize-none focus:border-blue-500 outline-none" placeholder="e.g. Penicillin, Sulfur, Seafood, Latex (Write 'None' if clear)"><?= esc($selectedResidentData['allergies'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block font-bold text-purple-950 mb-1 flex items-center justify-between">
                            <span>Pre-existing Medical History & Chronic Conditions *</span>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">✏ Editable by Nurse</span>
                        </label>
                        <textarea name="medical_history" id="nurse_medical_history" rows="2" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs resize-none focus:border-blue-500 outline-none" placeholder="e.g. Essential Hypertension, Type 2 Diabetes Mellitus, Asthma, Previous Appendectomy (2021)"><?= esc($selectedResidentData['medicalHistory'] ?? '') ?></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="<?= esc(tabUrl('patients')); ?>" class="flex-1 py-3 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-3 bg-blue-700 text-white rounded-xl text-xs font-extrabold hover:bg-blue-800 shadow-md transition-all">Save Complete Resident Medical Record</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            const residentProfilesMap = <?= json_encode($patientProfilesMap, JSON_UNESCAPED_UNICODE) ?> || {};

            function onNurseResidentSelectChange(target) {
                let selectEl = target;
                if (typeof target === 'string') {
                    selectEl = document.getElementById(target);
                }
                if (!selectEl) return;

                const rid = parseInt(selectEl.value || 0, 10);
                const profile = residentProfilesMap[rid];

                const fnInput = document.getElementById('nurse_first_name');
                const lnInput = document.getElementById('nurse_last_name');
                const btSelect = document.getElementById('nurse_blood_type');
                const phiInput = document.getElementById('nurse_philhealth_id');
                const cnInput = document.getElementById('nurse_contact_number');

                const htInput = document.getElementById('nurse_height');
                const wtInput = document.getElementById('nurse_weight');
                const bpInput = document.getElementById('nurse_blood_pressure');
                const algInput = document.getElementById('nurse_allergies');
                const mhInput = document.getElementById('nurse_medical_history');

                const lockedNotice = document.getElementById('nurse_locked_fields_notice');

                if (profile && rid > 0) {
                    if (fnInput) { fnInput.value = profile.first_name || ''; fnInput.readOnly = true; fnInput.classList.add('bg-slate-100', 'cursor-not-allowed'); }
                    if (lnInput) { lnInput.value = profile.last_name || ''; lnInput.readOnly = true; lnInput.classList.add('bg-slate-100', 'cursor-not-allowed'); }

                    if (btSelect) { btSelect.value = profile.bloodType || 'O+'; btSelect.disabled = true; btSelect.classList.add('bg-slate-100', 'cursor-not-allowed'); }
                    if (phiInput) { phiInput.value = profile.philhealthNo || ''; phiInput.readOnly = true; phiInput.classList.add('bg-slate-100', 'cursor-not-allowed'); }
                    if (cnInput) { cnInput.value = profile.contactNo || ''; cnInput.readOnly = true; cnInput.classList.add('bg-slate-100', 'cursor-not-allowed'); }

                    if (htInput) htInput.value = profile.height || '';
                    if (wtInput) wtInput.value = profile.weight || '';
                    if (bpInput) bpInput.value = profile.bloodPressure || '';
                    if (algInput) algInput.value = profile.allergies || '';
                    if (mhInput) mhInput.value = profile.medicalHistory || '';

                    if (lockedNotice) lockedNotice.classList.remove('hidden');
                } else {
                    if (fnInput) { fnInput.value = ''; fnInput.readOnly = false; fnInput.classList.remove('bg-slate-100', 'cursor-not-allowed'); }
                    if (lnInput) { lnInput.value = ''; lnInput.readOnly = false; lnInput.classList.remove('bg-slate-100', 'cursor-not-allowed'); }

                    if (btSelect) { btSelect.value = 'O+'; btSelect.disabled = false; btSelect.classList.remove('bg-slate-100', 'cursor-not-allowed'); }
                    if (phiInput) { phiInput.value = ''; phiInput.readOnly = false; phiInput.classList.remove('bg-slate-100', 'cursor-not-allowed'); }
                    if (cnInput) { cnInput.value = ''; cnInput.readOnly = false; cnInput.classList.remove('bg-slate-100', 'cursor-not-allowed'); }

                    if (htInput) htInput.value = '';
                    if (wtInput) wtInput.value = '';
                    if (bpInput) bpInput.value = '';
                    if (algInput) algInput.value = '';
                    if (mhInput) mhInput.value = '';

                    if (lockedNotice) lockedNotice.classList.add('hidden');
                }
            }

            // Execute auto-fill immediately if a resident is selected or on page load
            (function() {
                const selectEl = document.getElementById('nurse_resident_select');
                if (selectEl) {
                    onNurseResidentSelectChange(selectEl);
                }
            })();
        </script>
    <?php endif; ?>
<?= portalRenderNotificationPanel(); ?>
</body>

</html>
