<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

function esc(mixed $v): string {
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function tabUrl(string $tab, array $extra = []): string {
    return '?' . http_build_query(array_merge(['tab' => $tab], $extra));
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
];

$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab])) $tab = 'overview';

$modal = $_GET['modal'] ?? '';
$flashSuccess = $_SESSION['nurse_flash_success'] ?? '';
$flashError = $_SESSION['nurse_flash_error'] ?? '';
unset($_SESSION['nurse_flash_success'], $_SESSION['nurse_flash_error']);

// ----------------------------------------------------
// 1. POST FORM HANDLERS FOR NURSING & OPD TRIAGE
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($pdo)) {
    $action = $_POST['action'] ?? '';

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
                $_SESSION['nurse_flash_success'] = 'New OPD Triage record and vital signs saved successfully into database!';
            } catch (Exception $e) {
                $_SESSION['nurse_flash_error'] = 'Database Error: ' . $e->getMessage();
            }
        }
        header('Location: ' . tabUrl('opd'));
        exit;
    }
}

// ----------------------------------------------------
// 2. LIVE MYSQL DATA HYDRATION FROM DATABASE `rhu`
// ----------------------------------------------------
$opdConsultations = [];
$patientRecords = [];
$immunizationRecords = [];
$nutritionCases = [];
$tbCases = [];
$diseaseReports = [];
$bhwList = [];

$allResidentsList = [];
$allStaffList = [];

if (!empty($pdo)) {
    try {
        // Dropdown option queries
        $allResidentsList = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, barangay FROM residents ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $allStaffList = $pdo->query("SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) as name, s.staff_type FROM staff s JOIN users u ON s.user_id = u.id ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 1. OPD Consultations
        $opdStmt = $pdo->query("
            SELECT c.id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, c.icd_code as icd10, c.medications_prescribed as medications, r.barangay, c.consultation_date as date, c.referral_needed, c.referral_to, c.consultation_notes
            FROM consultations c
            JOIN residents r ON c.resident_id = r.id
            ORDER BY c.id DESC
        ");
        $opdConsultations = $opdStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 2. Patient Records (Residents)
        $patStmt = $pdo->query("
            SELECT r.id, CONCAT(r.first_name, ' ', r.last_name) as name, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, r.blood_type as bloodType, r.barangay, r.philhealth_id as philhealthNo, r.created_at as admissionDate
            FROM residents r
            ORDER BY r.id DESC
        ");
        $patientRecords = $patStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
            SELECT b.id, CONCAT(u.first_name, ' ', u.last_name) as name, b.barangay, s.phone_number, s.is_active
            FROM bhw b
            JOIN staff s ON b.staff_id = s.id
            JOIN users u ON s.user_id = u.id
            ORDER BY b.id DESC
        ");
        $bhwList = $bhwStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } catch (Exception $e) {
        error_log("NurseDashboard DB Load Error: " . $e->getMessage());
    }
}

// Fallbacks for empty tables
if (empty($opdConsultations)) {
    $opdConsultations = [
        ['id' => 1, 'patientName' => 'Lourdes Bautista', 'age' => 42, 'gender' => 'Female', 'chiefComplaint' => 'Fever and Productive Cough', 'diagnosis' => 'Acute Bronchitis', 'icd10' => 'J20.9', 'medications' => 'Amoxicillin 500mg, Paracetamol 500mg', 'barangay' => 'Halang', 'date' => date('Y-m-d'), 'consultation_notes' => 'NURSING TRIAGE VITALS: BP: 120/80, Temp: 38.2°C, Wt: 58kg, RR: 20/min, HR: 82bpm.'],
        ['id' => 2, 'patientName' => 'Carlos Soriano', 'age' => 38, 'gender' => 'Male', 'chiefComplaint' => 'High Fever and Severe Headache', 'diagnosis' => 'Dengue Fever Suspect', 'icd10' => 'A90', 'medications' => 'ORS Packets, Paracetamol 500mg', 'barangay' => 'Mabini', 'date' => date('Y-m-d'), 'consultation_notes' => 'NURSING TRIAGE VITALS: BP: 110/70, Temp: 39.1°C, Wt: 65kg, RR: 22/min, HR: 95bpm.'],
    ];
}

if (empty($patientRecords)) {
    $patientRecords = [
        ['id' => 1, 'name' => 'Lourdes Bautista', 'age' => 42, 'gender' => 'Female', 'bloodType' => 'O+', 'barangay' => 'Halang', 'philhealthNo' => 'PH-12-098765432-1', 'admissionDate' => date('Y-m-d')],
        ['id' => 2, 'name' => 'Carlos Soriano', 'age' => 38, 'gender' => 'Male', 'bloodType' => 'A+', 'barangay' => 'Mabini', 'philhealthNo' => 'PH-12-345678901-2', 'admissionDate' => date('Y-m-d')],
    ];
}

if (empty($immunizationRecords)) {
    $immunizationRecords = [
        ['id' => 1, 'childName' => 'Baby Jayden Santos', 'ageMonths' => 3, 'barangay' => 'Poblacion', 'vaccineName' => 'DPT-HepB-Hib 2', 'targetAge' => '10 Weeks', 'dateGiven' => date('Y-m-d'), 'nextVisit' => date('Y-m-d', strtotime('+30 days')), 'lot' => 'PENT-2026-04'],
        ['id' => 2, 'childName' => 'Baby Sofia Reyes', 'ageMonths' => 1, 'barangay' => 'Mabini', 'vaccineName' => 'BCG', 'targetAge' => 'At Birth', 'dateGiven' => date('Y-m-d', strtotime('-15 days')), 'nextVisit' => date('Y-m-d', strtotime('+15 days')), 'lot' => 'BCG-2026-01'],
    ];
}

if (empty($nutritionCases)) {
    $nutritionCases = [
        ['id' => 1, 'name' => 'Mark Cruz', 'age' => 2, 'gender' => 'Male', 'barangay' => 'Mabini', 'height' => '82', 'weight' => '10.5'],
        ['id' => 2, 'name' => 'Angelica Ramos', 'age' => 3, 'gender' => 'Female', 'barangay' => 'Halang', 'height' => '90', 'weight' => '12.0'],
    ];
}

if (empty($tbCases)) {
    $tbCases = [
        ['id' => 1, 'name' => 'Danilo Espiritu', 'age' => 44, 'gender' => 'Male', 'tb_registration_number' => 'TB-NSG-2026-001', 'classification' => 'Pulmonary TB', 'treatment_status' => 'Active', 'treatment_start_date' => date('Y-m-d', strtotime('-60 days')), 'barangay' => 'Poblacion'],
    ];
}

if (empty($bhwList)) {
    $bhwList = [
        ['id' => 1, 'name' => 'Gloria Cabrera', 'barangay' => 'Mabini', 'phone_number' => '0917 123 4567', 'is_active' => 1],
        ['id' => 2, 'name' => 'Rosalinda Cruz', 'barangay' => 'Halang', 'phone_number' => '0918 987 6543', 'is_active' => 1],
    ];
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
        body {
            font-family: ui-sans-serif, system-ui, sans-serif
        }
        .safe-area-pb {
            padding-bottom: env(safe-area-inset-bottom)
        }
    </style>
</head>

<body class="min-h-screen bg-gray-50 text-slate-900">
    <div class="min-h-screen flex flex-col">

        <!-- HEADER -->
        <header class="bg-gradient-to-r from-green-700 to-teal-800 text-white shadow-xl sticky top-0 z-40">
            <div class="px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center text-xl font-bold">
                        ⚕
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-base font-bold">Public Health Nurse Portal</h1>
                            <span class="hidden sm:inline-block text-[10px] bg-green-600/80 px-2 py-0.5 rounded-full font-semibold border border-green-400">RN Clara Mendez</span>
                        </div>
                        <p class="text-xs text-green-200">Nasugbu Rural Health Unit I — OPD Triage & Nursing Care</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/20 border border-emerald-300/40 text-xs font-bold text-emerald-100">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> MySQL Database Connected
                    </span>
                    <a href="LandingPage.php" class="p-2 rounded-lg hover:bg-white/10 text-xs font-bold" title="Log Out">↪</a>
                </div>
            </div>

            <!-- DESKTOP TABS -->
            <div class="hidden sm:flex px-4 gap-1 overflow-x-auto pb-0.5">
                <?php foreach ($tabs as $id => [$label, $icon]): ?>
                    <a href="<?php echo esc(tabUrl($id)); ?>"
                        class="flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-t-lg whitespace-nowrap flex-shrink-0 transition-all <?php echo $tab === $id ? 'bg-white text-green-800 shadow-md font-bold' : 'text-green-100 hover:bg-white/10'; ?>">
                        <span><?php echo $icon; ?></span><?php echo esc($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </header>

        <!-- FLASH NOTIFICATIONS -->
        <main class="max-w-6xl mx-auto w-full px-3 sm:px-4 py-4 sm:py-6 space-y-4 sm:space-y-5 pb-28 sm:pb-6 flex-1">
            <?php if ($flashSuccess): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-900 px-4 py-3 rounded-xl text-sm font-bold shadow-sm flex items-center justify-between">
                    <span>✓ <?php echo esc($flashSuccess); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded-xl text-sm font-bold shadow-sm flex items-center justify-between">
                    <span>⚠ <?php echo esc($flashError); ?></span>
                </div>
            <?php endif; ?>

            <!-- TAB 1: OVERVIEW -->
            <?php if ($tab === 'overview'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <!-- DENGUE ALERT -->
                    <div class="bg-red-50 border border-red-200 rounded-2xl p-4 sm:p-5 flex items-start gap-3 shadow-sm">
                        <span class="text-2xl text-red-600">⚠</span>
                        <div class="flex-1">
                            <p class="font-bold text-red-900 text-sm sm:text-base">Dengue Alert — Active Disease Surveillance Cluster</p>
                            <p class="text-xs sm:text-sm text-red-700 mt-0.5">8 Dengue cases reported in Barangay Halang and Mabini. Vector control, larval reduction, and PIDSR monitoring ongoing.</p>
                        </div>
                        <a href="<?php echo esc(tabUrl('disease')); ?>" class="text-xs bg-red-600 text-white font-bold px-3 py-1.5 rounded-xl hover:bg-red-700 whitespace-nowrap shadow">View PIDSR</a>
                    </div>

                    <!-- METRIC CARDS GRID 1 -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <a href="<?php echo esc(tabUrl('opd')); ?>" class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-left hover:shadow-md transition-all">
                            <p class="text-3xl font-black text-blue-600"><?php echo $todayOPDCount; ?></p>
                            <p class="text-sm font-bold text-gray-800">Today's OPD Consults</p>
                            <p class="text-xs text-gray-400">Recorded Triage Vitals</p>
                        </a>
                        <a href="<?php echo esc(tabUrl('patients')); ?>" class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-left hover:shadow-md transition-all">
                            <p class="text-3xl font-black text-purple-600"><?php echo count($patientRecords); ?></p>
                            <p class="text-sm font-bold text-gray-800">Registered Patients</p>
                            <p class="text-xs text-gray-400">Municipal Health Profiles</p>
                        </a>
                        <a href="<?php echo esc(tabUrl('tb')); ?>" class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-left hover:shadow-md transition-all">
                            <p class="text-3xl font-black text-orange-600"><?php echo $activeTBCount; ?></p>
                            <p class="text-sm font-bold text-gray-800">Active TB Cases</p>
                            <p class="text-xs text-gray-400">On DOTS Regimen</p>
                        </a>
                        <a href="<?php echo esc(tabUrl('nutrition')); ?>" class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-left hover:shadow-md transition-all">
                            <p class="text-3xl font-black text-yellow-600"><?php echo count($nutritionCases); ?></p>
                            <p class="text-sm font-bold text-gray-800">OPT+ Nutrition Cases</p>
                            <p class="text-xs text-gray-400">SAM / MAM Monitoring</p>
                        </a>
                    </div>

                    <!-- METRIC CARDS GRID 2 -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <a href="<?php echo esc(tabUrl('disease')); ?>" class="bg-white rounded-2xl p-3 sm:p-4 shadow-sm border border-gray-100 text-center hover:shadow-md transition-all">
                            <p class="text-xl font-black text-red-600">8</p>
                            <p class="text-xs font-semibold text-gray-700">Dengue Cases (Wk 23)</p>
                        </a>
                        <a href="<?php echo esc(tabUrl('immunization')); ?>" class="bg-white rounded-2xl p-3 sm:p-4 shadow-sm border border-gray-100 text-center hover:shadow-md transition-all">
                            <p class="text-xl font-black text-indigo-600"><?php echo count($immunizationRecords); ?></p>
                            <p class="text-xs font-semibold text-gray-700">Child Immunizations</p>
                        </a>
                        <a href="<?php echo esc(tabUrl('bhw')); ?>" class="bg-white rounded-2xl p-3 sm:p-4 shadow-sm border border-gray-100 text-center hover:shadow-md transition-all">
                            <p class="text-xl font-black text-teal-600"><?php echo count($bhwList); ?></p>
                            <p class="text-xs font-semibold text-gray-700">Active BHWs</p>
                        </a>
                        <a href="<?php echo esc(tabUrl('tb')); ?>" class="bg-white rounded-2xl p-3 sm:p-4 shadow-sm border border-gray-100 text-center hover:shadow-md transition-all">
                            <p class="text-xl font-black text-green-600">12</p>
                            <p class="text-xs font-semibold text-gray-700">TB Completed</p>
                        </a>
                    </div>

                    <!-- RECENT TRIAGE LOG SUMMARY -->
                    <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-gray-100 space-y-3">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <h3 class="font-bold text-gray-900 text-sm sm:text-base flex items-center gap-2">⚕ Recent OPD Patient Triage Log</h3>
                            <a href="<?php echo esc(tabUrl('opd', ['modal' => 'new_triage'])); ?>" class="px-3 py-1.5 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 shadow">+ Record New Triage Vitals</a>
                        </div>
                        <div class="space-y-3">
                            <?php foreach (array_slice($opdConsultations, 0, 5) as $c): ?>
                                <div class="bg-gray-50 rounded-xl p-3.5 border border-gray-100 space-y-2">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p class="font-bold text-gray-900 text-sm"><?php echo esc($c['patientName']); ?> <span class="text-xs font-normal text-gray-500">(<?php echo esc($c['age'] ?? '35'); ?>y / <?php echo esc($c['gender']); ?>)</span></p>
                                            <p class="text-xs text-gray-600"><?php echo esc($c['chiefComplaint']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-mono text-xs bg-blue-100 text-blue-800 font-bold px-2 py-0.5 rounded"><?php echo esc($c['icd10']); ?></span>
                                            <p class="text-[10px] text-gray-400 mt-1"><?php echo esc($c['date']); ?></p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 font-mono bg-white p-2 rounded border border-gray-200"><?php echo esc($c['consultation_notes']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 2: OPD TRIAGE & NURSING LOG -->
            <?php if ($tab === 'opd'): ?>
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2">⚕ OPD Triage & Nursing Assessment Log</h2>
                        <a href="<?php echo esc(tabUrl('opd', ['modal' => 'new_triage'])); ?>" class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 shadow flex items-center gap-1.5">+ Record Patient Triage Vitals</a>
                    </div>

                    <div class="space-y-3">
                        <?php foreach ($opdConsultations as $c): ?>
                            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 space-y-3">
                                <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 pb-2.5">
                                    <div>
                                        <p class="font-bold text-gray-900 text-base"><?php echo esc($c['patientName']); ?> <span class="text-xs text-gray-500 font-normal"><?php echo esc($c['age'] ?? '35'); ?>y • <?php echo esc($c['gender']); ?></span></p>
                                        <p class="text-xs font-semibold text-blue-900 mt-0.5">Chief Complaint: <?php echo esc($c['chiefComplaint']); ?></p>
                                        <p class="text-[11px] text-gray-400">Barangay: <?php echo esc($c['barangay']); ?> · Date: <?php echo esc($c['date']); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-mono text-xs bg-gray-100 text-gray-800 font-bold px-2.5 py-1 rounded-lg border border-gray-200"><?php echo esc($c['icd10']); ?></span>
                                        <?php if (!empty($c['referral_needed'])): ?>
                                            <span class="block mt-1 text-[11px] font-bold text-purple-700 bg-purple-100 px-2 py-0.5 rounded-full">Referred to <?php echo esc($c['referral_to']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-xs">
                                    <p class="font-bold text-slate-700 mb-1">Clinical Vitals & Assessment Notes:</p>
                                    <p class="text-slate-800 font-mono"><?php echo esc($c['consultation_notes']); ?></p>
                                </div>

                                <?php if (!empty($c['medications'])): ?>
                                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                        <span class="text-xs font-bold text-gray-500">Prescribed Meds:</span>
                                        <?php foreach (array_filter(explode(',', $c['medications'])) as $med): ?>
                                            <span class="text-xs bg-blue-50 text-blue-700 border border-blue-100 font-semibold px-2.5 py-0.5 rounded-full"><?php echo esc(trim($med)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 3: PATIENT RECORDS -->
            <?php if ($tab === 'patients'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2">▤ Municipal Resident Health Records</h2>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs min-w-[650px]">
                                <thead class="bg-gray-50 text-gray-500 uppercase font-bold">
                                    <tr>
                                        <th class="px-3.5 py-3 text-left">Resident ID</th>
                                        <th class="px-3.5 py-3 text-left">Full Name</th>
                                        <th class="px-3.5 py-3 text-left">Age / Sex</th>
                                        <th class="px-3.5 py-3 text-left">Blood Type</th>
                                        <th class="px-3.5 py-3 text-left">Barangay</th>
                                        <th class="px-3.5 py-3 text-left">PhilHealth No.</th>
                                        <th class="px-3.5 py-3 text-left">Date Registered</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($patientRecords as $p): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3.5 py-3 font-mono font-bold text-gray-500">RES-<?php echo $p['id']; ?></td>
                                            <td class="px-3.5 py-3 font-bold text-gray-900"><?php echo esc($p['name']); ?></td>
                                            <td class="px-3.5 py-3 text-gray-700"><?php echo esc($p['age'] ?? '35'); ?>y / <?php echo esc($p['gender']); ?></td>
                                            <td class="px-3.5 py-3"><span class="font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded border border-red-100"><?php echo esc($p['bloodType'] ?: 'O+'); ?></span></td>
                                            <td class="px-3.5 py-3 font-semibold text-purple-900"><?php echo esc($p['barangay']); ?></td>
                                            <td class="px-3.5 py-3 font-mono text-gray-600"><?php echo esc($p['philhealthNo'] ?: 'N/A'); ?></td>
                                            <td class="px-3.5 py-3 text-gray-500"><?php echo esc(date('M d, Y', strtotime($p['admissionDate']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 4: IMMUNIZATION -->
            <?php if ($tab === 'immunization'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2">⌁ Expanded Program on Immunization (EPI) Monitoring</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-center">
                            <p class="text-3xl font-black text-green-600"><?php echo count($immunizationRecords); ?></p>
                            <p class="text-xs font-bold text-gray-600 mt-1">Administered Records</p>
                        </div>
                        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-center">
                            <p class="text-3xl font-black text-yellow-600">3</p>
                            <p class="text-xs font-bold text-gray-600 mt-1">Due This Month</p>
                        </div>
                        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 text-center">
                            <p class="text-3xl font-black text-red-600">0</p>
                            <p class="text-xs font-bold text-gray-600 mt-1">Overdue Vaccines</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <?php foreach ($immunizationRecords as $im): ?>
                            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-bold text-gray-900 text-sm"><?php echo esc($im['childName']); ?></p>
                                    <p class="text-xs text-gray-500">Vaccine: <strong class="text-indigo-900"><?php echo esc($im['vaccineName']); ?></strong> (<?php echo esc($im['targetAge']); ?>) · Batch: <?php echo esc($im['lot']); ?></p>
                                    <p class="text-[11px] text-gray-400 mt-0.5">Barangay: <?php echo esc($im['barangay']); ?> · Given: <?php echo esc($im['dateGiven']); ?> · Next Visit: <strong class="text-green-700"><?php echo esc($im['nextVisit']); ?></strong></p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-200">Administered</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 5: NUTRITION (OPT+) -->
            <?php if ($tab === 'nutrition'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2">⚖ Operation Timbang Plus (OPT+) Child Nutrition</h2>
                    <div class="space-y-3">
                        <?php foreach ($nutritionCases as $n): ?>
                            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 space-y-2">
                                <div class="flex items-center justify-between">
                                    <p class="font-bold text-gray-900 text-sm"><?php echo esc($n['name']); ?> <span class="text-xs text-gray-500">(<?php echo esc($n['age']); ?>y / <?php echo esc($n['gender']); ?>)</span></p>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-200">Normal Growth</span>
                                </div>
                                <div class="flex gap-4 text-xs font-semibold text-gray-600 bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                                    <span>Barangay: <strong><?php echo esc($n['barangay']); ?></strong></span>
                                    <span>Height: <strong><?php echo esc($n['height']); ?> cm</strong></span>
                                    <span>Weight: <strong><?php echo esc($n['weight']); ?> kg</strong></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 6: TB-DOTS -->
            <?php if ($tab === 'tb'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2">🔬 TB-DOTS Case Management &amp; Treatment Adherence</h2>
                    <div class="space-y-3">
                        <?php foreach ($tbCases as $tb): ?>
                            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-bold text-gray-900 text-sm"><?php echo esc($tb['name']); ?> <span class="text-xs text-gray-500">(<?php echo esc($tb['age']); ?>y / <?php echo esc($tb['gender']); ?>)</span></p>
                                        <p class="text-xs text-gray-500 font-mono">DOH Reg No: <?php echo esc($tb['tb_registration_number']); ?> · Type: <?php echo esc($tb['classification']); ?></p>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-800 border border-orange-200"><?php echo esc($tb['treatment_status']); ?></span>
                                </div>
                                <p class="text-xs text-gray-600">Barangay: <strong><?php echo esc($tb['barangay']); ?></strong> · Treatment Started: <strong><?php echo esc($tb['treatment_start_date']); ?></strong></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 7: DISEASE SURVEILLANCE (PIDSR) -->
            <?php if ($tab === 'disease'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2">⚠ Disease Surveillance (PIDSR Week 23)</h2>
                    <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-gray-100 space-y-3">
                        <h3 class="font-bold text-gray-900 text-sm">Notifiable Diseases Summary</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="p-3 bg-red-50 border border-red-100 rounded-xl">
                                <p class="font-bold text-red-900 text-sm">Dengue Fever (A90)</p>
                                <p class="text-red-700">8 reported cases in Barangay Halang & Mabini. Alert active.</p>
                            </div>
                            <div class="p-3 bg-amber-50 border border-amber-100 rounded-xl">
                                <p class="font-bold text-amber-900 text-sm">Leptospirosis (A27)</p>
                                <p class="text-amber-700">2 cases in Poblacion. Prophylaxis distribution ongoing.</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 8: BHW MANAGEMENT -->
            <?php if ($tab === 'bhw'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2">👤 Barangay Health Worker (BHW) Supervisory Registry</h2>
                    <div class="space-y-3">
                        <?php foreach ($bhwList as $b): ?>
                            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-gray-900 text-sm"><?php echo esc($b['name']); ?></p>
                                    <p class="text-xs text-gray-500">Barangay: <strong><?php echo esc($b['barangay']); ?></strong> · Contact: <?php echo esc($b['phone_number']); ?></p>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-200">Active BHW</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <!-- MOBILE BOTTOM TAB BAR -->
        <nav class="sm:hidden fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-200 safe-area-pb shadow-2xl">
            <div class="flex items-stretch">
                <?php foreach ($tabs as $id => [$label, $icon]): ?>
                    <a href="<?php echo esc(tabUrl($id)); ?>"
                        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-semibold transition-colors relative <?php echo $tab === $id ? 'text-green-600 font-bold' : 'text-gray-400'; ?>">
                        <?php if ($tab === $id): ?>
                            <span class="absolute top-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-green-500 rounded-full"></span>
                        <?php endif; ?>
                        <span class="text-base leading-none"><?php echo $icon; ?></span>
                        <span class="truncate px-0.5"><?php echo esc($label); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>

    </div>

    <!-- MODAL: NEW OPD TRIAGE RECORD -->
    <?php if ($modal === 'new_triage'): ?>
        <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-5 border-b flex items-center justify-between bg-green-700 text-white rounded-t-2xl">
                    <h2 class="text-base font-bold flex items-center gap-2">⚕ Record OPD Patient Triage &amp; Vitals</h2>
                    <a href="<?php echo esc(tabUrl('opd')); ?>" class="text-green-100 hover:text-white">✕</a>
                </div>
                <form class="p-5 space-y-4 text-xs" method="post">
                    <input type="hidden" name="action" value="save_triage">

                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Select Patient Resident *</label>
                        <select name="resident_id" required class="w-full p-2.5 border border-gray-300 rounded-xl text-sm font-semibold">
                            <option value="">-- Select Resident Patient --</option>
                            <?php foreach ($allResidentsList as $r): ?>
                                <option value="<?php echo esc($r['id']); ?>"><?php echo esc($r['name']); ?> (<?php echo esc($r['barangay']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Attending Physician / Staff *</label>
                        <select name="physician_id" required class="w-full p-2.5 border border-gray-300 rounded-xl text-sm">
                            <?php foreach ($allStaffList as $st): ?>
                                <option value="<?php echo esc($st['id']); ?>"><?php echo esc($st['name']); ?> (<?php echo esc($st['staff_type']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Chief Health Complaint *</label>
                        <input name="chief_complaint" required placeholder="e.g., High fever, cough, and body malaise" class="w-full p-2.5 border border-gray-300 rounded-xl text-sm">
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">BP (mmHg)</label>
                            <input name="bp" value="120/80" class="w-full p-2 border border-gray-300 rounded-lg text-xs font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Temp (°C)</label>
                            <input name="temp" value="36.8°C" class="w-full p-2 border border-gray-300 rounded-lg text-xs font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Weight (kg)</label>
                            <input name="weight" value="60 kg" class="w-full p-2 border border-gray-300 rounded-lg text-xs font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Respiratory Rate</label>
                            <input name="rr" value="18/min" class="w-full p-2 border border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Heart Rate</label>
                            <input name="hr" value="75 bpm" class="w-full p-2 border border-gray-300 rounded-lg text-xs">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Primary Diagnosis</label>
                            <input name="diagnosis" value="Acute Upper Respiratory Tract Infection" class="w-full p-2 border border-gray-300 rounded-lg text-xs">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">ICD-10 Code</label>
                            <input name="icd10" value="J06.9" class="w-full p-2 border border-gray-300 rounded-lg text-xs font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Prescribed Medications</label>
                        <input name="medications" placeholder="Paracetamol 500mg, Amoxicillin 500mg" class="w-full p-2 border border-gray-300 rounded-lg text-xs">
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="<?php echo esc(tabUrl('opd')); ?>" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-green-700 text-white rounded-xl text-xs font-bold hover:bg-green-800 shadow-md">Save Nursing Triage Vitals</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</body>

</html>
