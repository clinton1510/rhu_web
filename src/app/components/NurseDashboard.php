<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$stType = strtoupper((string)($_SESSION['rhu_staff_login']['staff_type'] ?? ''));
if (empty($_SESSION['rhu_staff_login']) || ($stType !== 'NURSE' && !str_contains($stType, 'NURSE'))) {
    header('Location: RHULogin.php');
    exit;
}
require_once __DIR__ . '/db.php';

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
    'overview' => [
        'Overview', 
        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>'
    ],
    'opd' => [
        'OPD Triage', 
        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>'
    ],
    'patients' => [
        'Patient Records', 
        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
    ],
    'immunization' => [
        'Immunization', 
        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>'
    ],
    'nutrition' => [
        'Nutrition (OPT+)', 
        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 0L9 3m3 2l3-2"/></svg>'
    ],
    'tb' => [
        'TB-DOTS', 
        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>'
    ],
    'disease' => [
        'Disease Surveillance', 
        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>'
    ],
    'bhw' => [
        'BHW Management', 
        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>'
    ],
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
$allStaffList = [];
$selectedResidentData = null;

if (!empty($pdo)) {
    try {
        // Dropdown option queries
        $allResidentsList = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, barangay FROM residents ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $allStaffList = $pdo->query("SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) as name, s.staff_type FROM staff s JOIN users u ON s.user_id = u.id ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 1. OPD Consultations (Filtered by logged-in staff/nurse)
        $nurseStaffId = (int)($_SESSION['rhu_staff_login']['staff_id'] ?? 0);
        $nurseUserId = (int)($_SESSION['rhu_staff_login']['id'] ?? $_SESSION['rhu_staff_login']['user_id'] ?? 0);

        if ($nurseStaffId > 0) {
            $opdStmt = $pdo->prepare("
                SELECT c.id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, c.icd_code as icd10, c.medications_prescribed as medications, r.barangay, c.consultation_date as date, c.referral_needed, c.referral_to, c.consultation_notes
                FROM consultations c
                JOIN residents r ON c.resident_id = r.id
                LEFT JOIN staff doc_s ON c.physician_id = doc_s.id
                WHERE (c.physician_id = :sid OR doc_s.user_id = :uid OR c.chief_complaint LIKE '%Nurse%' OR c.chief_complaint LIKE '%Vaccination%' OR c.chief_complaint LIKE '%Checkup%')
                ORDER BY c.id DESC
            ");
            $opdStmt->execute(['sid' => $nurseStaffId, 'uid' => $nurseUserId]);
        } else {
            $opdStmt = $pdo->query("
                SELECT c.id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, c.icd_code as icd10, c.medications_prescribed as medications, r.barangay, c.consultation_date as date, c.referral_needed, c.referral_to, c.consultation_notes
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
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        :root {
            --rhu-teal: #0f766e;
            --rhu-aqua: #14b8a6;
            --rhu-sky: #0284c7;
            --rhu-ink: #0f172a;
        }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background:
                radial-gradient(circle at 4% 3%, rgba(20,184,166,.13), transparent 25rem),
                radial-gradient(circle at 96% 12%, rgba(14,165,233,.10), transparent 28rem),
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
            box-shadow: 0 0 12px rgba(34,211,238,.65);
            transition: width 80ms linear;
        }
        .ambient-orb {
            position: fixed;
            z-index: -1;
            width: 20rem;
            height: 20rem;
            border-radius: 9999px;
            filter: blur(80px);
            opacity: .18;
            pointer-events: none;
            animation: orb-float 12s ease-in-out infinite alternate;
        }
        .ambient-orb-one { left: 10%; top: 18%; background: #2dd4bf; }
        .ambient-orb-two { right: 3%; bottom: 4%; background: #60a5fa; animation-delay: -5s; }
        @keyframes orb-float {
            from { transform: translate3d(-1rem,-1rem,0) scale(.92); }
            to { transform: translate3d(2rem,2rem,0) scale(1.08); }
        }
        .sidebar-expanded { width: 16rem; }
        .sidebar-collapsed { width: 4.5rem !important; }
        .sidebar-collapsed .sidebar-label,
        .sidebar-collapsed .logo-title { display: none !important; }
        .sidebar-collapsed .sidebar-item {
            justify-content: center !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            border-radius: 9999px;
        }
        .sidebar-collapsed .header-logo-container { justify-content: center; }
        #sidebar {
            background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(240,253,250,.96) 55%, rgba(239,246,255,.96));
            border-color: rgba(153,246,228,.7);
            box-shadow: 12px 0 35px rgba(15,23,42,.06);
        }
        #sidebar .sidebar-item:hover {
            transform: translateX(3px);
            background: linear-gradient(90deg, rgba(204,251,241,.8), rgba(224,242,254,.58));
            color: var(--rhu-teal);
        }
        .nav-active {
            background-color: #ccfbf1 !important;
            color: #0f766e !important;
            font-weight: 700 !important;
        }
        .nav-active span { color: #0f766e !important; }
        header.sticky {
            background: rgba(255,255,255,.84) !important;
            border-color: rgba(153,246,228,.65) !important;
            box-shadow: 0 8px 30px rgba(15,118,110,.055);
            backdrop-filter: blur(18px);
        }
        .dashboard-card {
            transition: transform 220ms cubic-bezier(.2,.8,.2,1), box-shadow 220ms ease, border-color 220ms ease;
        }
        .dashboard-card:hover {
            transform: translateY(-3px) scale(1.012);
            border-color: rgba(45,212,191,.75);
            box-shadow: 0 16px 35px rgba(15,118,110,.11);
            position: relative;
            z-index: 2;
        }
        button:not([disabled]), a[href] {
            transition: transform 180ms ease, box-shadow 180ms ease, background-color 180ms ease, color 180ms ease, border-color 180ms ease;
        }
        button:not([disabled]):active, a[href]:active { transform: scale(.97); }
        input, select, textarea {
            transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--rhu-aqua) !important;
            box-shadow: 0 0 0 4px rgba(20,184,166,.12) !important;
        }
        .reveal-on-scroll {
            opacity: 0;
            transform: translateY(18px);
        }
        .reveal-on-scroll.is-visible {
            opacity: 1;
            transform: none;
            transition: opacity 500ms ease, transform 500ms cubic-bezier(.2,.8,.2,1);
        }
        * {
            scrollbar-width: thin;
            scrollbar-color: #99f6e4 transparent;
        }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .reveal-on-scroll, .reveal-on-scroll.is-visible, .ambient-orb {
                animation: none; transition: none; transform: none; opacity: 1;
            }
            .dashboard-card:hover { transform: none; }
        }
    </style>
    <link rel="stylesheet" href="dashboard-enhancements.css">
    <script defer src="dashboard-enhancements.js?v=20260726-controls3"></script>
</head>

<body class="min-h-screen text-slate-800 antialiased flex flex-col sm:flex-row selection:bg-teal-500 selection:text-white">
    <div id="scroll-progress" aria-hidden="true"></div>
    <div class="ambient-orb ambient-orb-one" aria-hidden="true"></div>
    <div class="ambient-orb ambient-orb-two" aria-hidden="true"></div>

    <!-- SIDEBAR NAVIGATION -->
    <aside id="sidebar" class="sidebar-expanded text-slate-700 transition-all duration-300 ease-in-out flex-shrink-0 sticky top-0 h-auto sm:h-screen z-40 flex flex-col justify-between border-r">
        <div>
            <div class="h-16 px-4 flex items-center justify-between border-b border-teal-100/60 header-logo-container">
                <div class="flex items-center gap-3 overflow-hidden">
                    <button onclick="toggleSidebar()" class="p-2 rounded-full text-slate-600 hover:bg-teal-50 transition-colors" title="Toggle Main Menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div class="logo-title truncate flex items-center gap-2 min-w-0">
                        <img src="resihunity_logo.jpg" alt="" class="h-9 w-9 object-contain object-left shrink-0 rounded-md" />
                        <div class="min-w-0 leading-tight">
                            <h1 class="text-sm font-extrabold text-slate-800 tracking-tight truncate">ResiHUnity</h1>
                            <p class="text-[9px] font-medium text-slate-400 truncate hidden lg:block">RHU Nurse Portal</p>
                        </div>
                    </div>
                    <div class="logo-icon-collapsed hidden shrink-0 items-center justify-center">
                        <img src="resihunity_logo.jpg" alt="ResiHUnity" class="h-8 w-8 object-contain object-left rounded-lg" />
                    </div>
                </div>
            </div>

            <nav class="py-3 pr-3 space-y-1 overflow-y-auto max-h-[calc(100vh-8rem)]">
                <?php foreach ($tabs as $id => [$label, $icon]): ?>
                    <a href="<?= esc(tabUrl($id)); ?>"
                       title="<?= esc($label); ?>"
                       class="sidebar-item flex items-center gap-4 px-5 py-3 rounded-r-full text-xs font-semibold transition-all <?= $tab === $id ? 'nav-active' : 'text-slate-600 hover:text-slate-900'; ?>">
                        <span class="text-lg leading-none shrink-0 text-center w-6 <?= $tab === $id ? 'text-teal-700' : 'text-slate-500'; ?>"><?= $icon; ?></span>
                        <span class="sidebar-label truncate text-xs"><?= esc($label); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="p-3 border-t border-teal-100/60 hidden sm:block">
            <a href="StaffLogout.php" data-staff-logout class="sidebar-item w-full flex items-center gap-4 px-4 py-2.5 rounded-r-full text-xs font-semibold text-rose-600 hover:bg-rose-50 transition-all" title="Log Out">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span class="sidebar-label truncate">Log out</span>
            </a>
        </div>
    </aside>

    <!-- MAIN DASHBOARD CONTENT AREA -->
    <div class="flex-1 flex flex-col min-w-0 min-h-screen">

        <!-- MAIN HEADER BAR -->
        <header class="sticky top-0 z-30 px-4 sm:px-8 py-3.5 flex items-center justify-between gap-4 border-b">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="sm:hidden p-2 rounded-full text-slate-600 hover:bg-teal-50" title="Toggle Navigation Sidebar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h1 class="text-base sm:text-lg font-bold text-slate-800 tracking-tight flex items-center gap-2">
                        <?= esc($tabs[$tab][0]); ?>
                    </h1>
                    <p class="text-xs text-slate-500 hidden sm:block font-medium">Nasugbu Rural Health Unit I — Public Health Nurse Dashboard</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden md:inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-700">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span> Live Database Sync
                </span>
                
                <div class="flex items-center gap-2.5 pl-3 border-l border-slate-200">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-teal-600 to-sky-600 text-white font-bold text-xs flex items-center justify-center shadow-md">
                        <?= strtoupper(substr($_SESSION['rhu_staff_login']['name'] ?? 'N', 0, 1)) ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-xs font-bold text-slate-800 leading-tight"><?= esc($_SESSION['rhu_staff_login']['name'] ?? 'Public Health Nurse') ?></p>
                        <p class="text-[10px] text-slate-500 font-medium">Logged-in Nurse</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- FLASH NOTIFICATIONS & TAB CONTENT -->
        <main class="max-w-7xl w-full mx-auto p-4 sm:p-8 space-y-6 pb-28 sm:pb-12 flex-1">
            <?php if ($flashSuccess): ?>
                <div class="rounded-2xl border border-teal-200 bg-teal-50 p-4 text-xs font-semibold text-teal-800 flex items-center gap-2 shadow-sm">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-teal-100 text-teal-700"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                    <?= esc($flashSuccess); ?>
                </div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800 flex items-center gap-2 shadow-sm">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-100 text-rose-700"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></span>
                    <?= esc($flashError); ?>
                </div>
            <?php endif; ?>

            <!-- TAB 1: OVERVIEW -->
            <?php if ($tab === 'overview'): ?>
                <div class="space-y-6">
                    <!-- SURVEILLANCE BANNER -->
                    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-rose-600 to-red-700 p-5 sm:p-6 text-white shadow-lg flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-start gap-4 relative z-10">
                            <span class="w-11 h-11 rounded-2xl bg-white/20 flex items-center justify-center shrink-0 border border-white/30 shadow-sm text-white"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></span>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-extrabold text-base sm:text-lg tracking-tight">DOH Active Disease Surveillance Alert</p>
                                    <span class="bg-white text-red-600 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">PIDSR Active</span>
                                </div>
                                <p class="text-xs sm:text-sm text-rose-100 mt-1.5 font-medium">Continuous community monitoring ongoing for Dengue, Typhoid, and Leptospirosis across Nasugbu barangays.</p>
                            </div>
                        </div>
                        <a href="<?= esc(tabUrl('disease')); ?>" class="relative z-10 text-xs bg-white hover:bg-rose-50 text-red-700 font-bold px-4 py-2.5 rounded-xl shadow-md transition-all shrink-0">View Surveillance Details</a>
                    </div>

                    <!-- METRIC CARDS GRID -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                        <a href="<?= esc(tabUrl('opd')); ?>" class="dashboard-card group bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="w-11 h-11 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center border border-teal-100"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg></span>
                                <span class="text-[11px] font-semibold text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-full border border-teal-200">Active Queue</span>
                            </div>
                            <p class="text-3xl font-extrabold text-slate-800 mt-4 group-hover:text-teal-700 transition-colors"><?= $todayOPDCount; ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Received OPD Consults</p>
                            <p class="text-[11px] text-slate-400 font-medium">Assigned Nursing Triage</p>
                        </a>

                        <a href="<?= esc(tabUrl('patients')); ?>" class="dashboard-card group bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="w-11 h-11 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center border border-sky-100"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg></span>
                                <span class="text-[11px] font-semibold text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-full border border-sky-200">Registry</span>
                            </div>
                            <p class="text-3xl font-extrabold text-slate-800 mt-4 group-hover:text-sky-700 transition-colors"><?= count($patientRecords); ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Registered Patients</p>
                            <p class="text-[11px] text-slate-400 font-medium">Resident Health Profiles</p>
                        </a>

                        <a href="<?= esc(tabUrl('immunization')); ?>" class="dashboard-card group bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center border border-indigo-100"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 2 4 4"/><path d="m17 7 3-3"/><path d="M19 9 8.7 19.3c-1 1-2.5 1-3.4 0l-.6-.6c-1-1-1-2.5 0-3.4L15 5"/><path d="m9 11 4 4"/><path d="m5 19-3 3"/><path d="m14 4 6 6"/></svg></span>
                                <span class="text-[11px] font-semibold text-indigo-700 bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-200">EPI Program</span>
                            </div>
                            <p class="text-3xl font-extrabold text-slate-800 mt-4 group-hover:text-indigo-700 transition-colors"><?= count($immunizationRecords); ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Child Immunizations</p>
                            <p class="text-[11px] text-slate-400 font-medium">Vaccination Records</p>
                        </a>

                        <a href="<?= esc(tabUrl('tb')); ?>" class="dashboard-card group bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center border border-amber-100"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18h8"/><path d="M3 22h18"/><path d="M14 22a7 7 0 1 0 0-14h-1"/><path d="M9 14h2"/><path d="M9 12a2 2 0 0 1-2-2V6h6v4a2 2 0 0 1-2 2Z"/><path d="M12 6V3a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v3"/></svg></span>
                                <span class="text-[11px] font-semibold text-amber-700 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200">DOTS</span>
                            </div>
                            <p class="text-3xl font-extrabold text-slate-800 mt-4 group-hover:text-amber-700 transition-colors"><?= $activeTBCount; ?></p>
                            <p class="text-xs font-bold text-slate-700 mt-1">Active TB Cases</p>
                            <p class="text-[11px] text-slate-400 font-medium">Treatment Monitoring</p>
                        </a>
                    </div>

                    <!-- RECEIVED OPD CONSULTATION & TRIAGE LOG SUMMARY -->
                    <div class="dashboard-card bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h3 class="font-bold text-slate-800 text-base flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-teal-500 animate-pulse"></span>
                                    Received Resident Consultation & Triage Queue
                                </h3>
                                <p class="text-xs text-slate-500 font-medium">Live consultation requests submitted by residents for Nursing Triage</p>
                            </div>
                            <a href="<?= esc(tabUrl('opd', ['modal' => 'new_triage'])); ?>" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 transition-all flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg> Record Triage Vitals
                            </a>
                        </div>

                        <?php if (empty($opdConsultations)): ?>
                            <div class="text-center py-10 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg></span>
                                <p class="text-sm font-semibold text-slate-700">No OPD Consultations Found</p>
                                <p class="text-xs text-slate-400 mt-0.5">When residents book appointments for nursing care, they will appear here automatically.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach (array_slice($opdConsultations, 0, 5) as $c): ?>
                                    <?php $vitals = parseTriageVitals($c['consultation_notes'] ?? ''); ?>
                                    <div class="dashboard-card bg-white rounded-xl p-4 border border-slate-200 shadow-sm space-y-3">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <p class="font-bold text-slate-800 text-sm sm:text-base"><?= esc($c['patientName']); ?></p>
                                                    <span class="text-xs font-semibold text-slate-700 bg-slate-200/80 px-2 py-0.5 rounded-md">
                                                        <?= esc($c['age'] ?? 'N/A'); ?>y • <?= esc($c['gender']); ?>
                                                    </span>
                                                    <span class="text-xs font-medium text-slate-600 bg-white px-2 py-0.5 rounded-md border border-slate-200">
                                                        <span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg> Barangay</span> <?= esc($c['barangay']); ?>
                                                    </span>
                                                </div>
                                                <p class="text-xs font-semibold text-slate-700 mt-1">Chief Complaint: <span class="text-slate-600 font-normal"><?= esc($c['chiefComplaint']); ?></span></p>
                                            </div>
                                            <div class="text-right">
                                                <span class="font-mono text-xs bg-white text-slate-700 font-semibold px-2.5 py-1 rounded-lg border border-slate-200"><?= esc($c['icd10'] ?: 'OPD Triage'); ?></span>
                                                <p class="text-[10px] font-medium text-slate-400 mt-1"><span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg></span> <?= esc($c['date']); ?></p>
                                            </div>
                                        </div>

                                        <?php if (!empty($vitals)): ?>
                                            <div class="flex flex-wrap items-center gap-2 bg-white p-2 rounded-lg border border-slate-200 text-xs">
                                                <span class="font-semibold text-slate-700 text-[11px] mr-1">Vitals:</span>
                                                <?php if (!empty($vitals['bp'])): ?><span class="bg-slate-100 text-slate-800 font-mono font-semibold px-2 py-0.5 rounded">BP: <?= esc($vitals['bp']); ?></span><?php endif; ?>
                                                <?php if (!empty($vitals['temp'])): ?><span class="bg-rose-50 text-rose-700 font-mono font-semibold px-2 py-0.5 rounded border border-rose-100">Temp: <?= esc($vitals['temp']); ?></span><?php endif; ?>
                                                <?php if (!empty($vitals['weight'])): ?><span class="bg-sky-50 text-sky-700 font-mono font-semibold px-2 py-0.5 rounded border border-sky-100">Wt: <?= esc($vitals['weight']); ?></span><?php endif; ?>
                                                <?php if (!empty($vitals['hr'])): ?><span class="bg-amber-50 text-amber-700 font-mono font-semibold px-2 py-0.5 rounded border border-amber-100">HR: <?= esc($vitals['hr']); ?></span><?php endif; ?>
                                                <?php if (!empty($vitals['rr'])): ?><span class="bg-teal-50 text-teal-700 font-mono font-semibold px-2 py-0.5 rounded border border-teal-100">RR: <?= esc($vitals['rr']); ?></span><?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="text-xs text-slate-600 font-mono bg-white p-3 rounded-lg border border-slate-200/80">
                                            <?= esc($c['consultation_notes']); ?>
                                        </div>
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
                    <div class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div>
                            <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg> OPD Triage &amp; Nursing Assessment Log</h2>
                            <p class="text-xs text-slate-500">Record and review vital signs assessment for all outpatient consultations</p>
                        </div>
                        <a href="<?= esc(tabUrl('opd', ['modal' => 'new_triage'])); ?>" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-xl transition-all flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg> Record Patient Triage Vitals
                        </a>
                    </div>

                    <?php if (empty($opdConsultations)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg></span>
                            <p class="text-sm font-semibold text-slate-700">No OPD Triage Consultations Recorded</p>
                            <p class="text-xs text-slate-400 mt-0.5">Click "+ Record Patient Triage Vitals" above to start logging patient vital signs.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($opdConsultations as $c): ?>
                                <?php $vitals = parseTriageVitals($c['consultation_notes'] ?? ''); ?>
                                <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm space-y-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3">
                                        <div>
                                            <p class="font-bold text-slate-800 text-base"><?= esc($c['patientName']); ?> <span class="text-xs text-slate-500 font-medium">(<?= esc($c['age'] ?? 'N/A'); ?>y • <?= esc($c['gender']); ?>)</span></p>
                                            <p class="text-xs font-semibold text-slate-700 mt-1">Chief Complaint: <span class="text-slate-600 font-normal"><?= esc($c['chiefComplaint']); ?></span></p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">Barangay: <?= esc($c['barangay']); ?> · Date: <?= esc($c['date']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-mono text-xs bg-slate-100 text-slate-700 font-semibold px-2.5 py-1 rounded-lg border border-slate-200"><?= esc($c['icd10'] ?: 'OPD Triage'); ?></span>
                                            <?php if (!empty($c['referral_needed'])): ?>
                                                <span class="block mt-1 text-[11px] font-semibold text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full border border-purple-100">Referred to <?= esc($c['referral_to']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($vitals)): ?>
                                        <div class="flex flex-wrap items-center gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs">
                                            <span class="font-semibold text-slate-700 text-[11px] mr-1">Vitals:</span>
                                            <?php if (!empty($vitals['bp'])): ?><span class="bg-white text-slate-800 font-mono font-semibold px-2.5 py-0.5 rounded border border-slate-200">BP: <?= esc($vitals['bp']); ?></span><?php endif; ?>
                                            <?php if (!empty($vitals['temp'])): ?><span class="bg-white text-rose-700 font-mono font-semibold px-2.5 py-0.5 rounded border border-rose-200">Temp: <?= esc($vitals['temp']); ?></span><?php endif; ?>
                                            <?php if (!empty($vitals['weight'])): ?><span class="bg-white text-sky-700 font-mono font-semibold px-2.5 py-0.5 rounded border border-sky-200">Wt: <?= esc($vitals['weight']); ?></span><?php endif; ?>
                                            <?php if (!empty($vitals['hr'])): ?><span class="bg-white text-amber-700 font-mono font-semibold px-2.5 py-0.5 rounded border border-amber-200">HR: <?= esc($vitals['hr']); ?></span><?php endif; ?>
                                            <?php if (!empty($vitals['rr'])): ?><span class="bg-white text-teal-700 font-mono font-semibold px-2.5 py-0.5 rounded border border-teal-200">RR: <?= esc($vitals['rr']); ?></span><?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/80 text-xs">
                                        <p class="font-semibold text-slate-700 mb-1">Clinical Assessment Notes:</p>
                                        <p class="text-slate-800 font-mono"><?= esc($c['consultation_notes']); ?></p>
                                    </div>

                                    <?php if (!empty($c['medications'])): ?>
                                        <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                            <span class="text-xs font-semibold text-slate-500">Prescribed Meds:</span>
                                            <?php foreach (array_filter(explode(',', $c['medications'])) as $med): ?>
                                                <span class="text-xs bg-teal-50 text-teal-700 border border-teal-100 font-medium px-2.5 py-0.5 rounded-full"><?= esc(trim($med)); ?></span>
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
                    <div class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                        <div>
                            <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg> Municipal Resident Health Records</h2>
                            <p class="text-xs text-slate-500">Complete resident health profiles, medical history, allergies, and vitals registry</p>
                        </div>
                        <a href="<?= esc(tabUrl('patients', ['modal' => 'new_patient_record'])); ?>" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-xl transition-all flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg> Add / Register Medical Record
                        </a>
                    </div>

                    <?php if (empty($patientRecords)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                            <p class="text-sm font-semibold text-slate-700">No Patient Records Registered</p>
                            <p class="text-xs text-slate-400 mt-0.5">Click "+ Add / Register Medical Record" above to record a resident's medical info.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($patientRecords as $p): ?>
                                <div class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm space-y-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded">RES-<?= $p['id']; ?></span>
                                                <p class="font-bold text-slate-800 text-base"><?= esc($p['name']); ?></p>
                                                <span class="text-xs font-medium text-slate-600 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                                    <span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg></span> <?= esc($p['barangay']); ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-600 mt-1 font-medium">
                                                Age: <?= esc($p['age'] ?? 'N/A'); ?>y • Sex: <?= esc($p['gender']); ?> • PhilHealth No: <span class="font-mono text-slate-800"><?= esc($p['philhealthNo'] ?: 'N/A'); ?></span>
                                                <?php if (!empty($p['contactNo'])): ?> · <span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span> <?= esc($p['contactNo']); ?><?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-xs text-rose-700 bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-200">
                                                Blood Type: <?= esc($p['bloodType'] ?: 'O+'); ?>
                                            </span>
                                            <a href="<?= esc(tabUrl('patients', ['modal' => 'new_patient_record', 'resident_id' => $p['id']])); ?>" class="px-3 py-1 bg-slate-50 hover:bg-teal-50 text-teal-700 border border-slate-200 hover:border-teal-300 font-semibold text-xs rounded-xl transition-all">
                                                <span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg> Edit Medical Record</span>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                                        <div class="bg-slate-50/80 p-2.5 rounded-xl border border-slate-100">
                                            <span class="font-semibold text-rose-800 block text-[11px]"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> Known Allergies:</span>
                                            <span class="text-slate-800 font-medium"><?= esc($p['allergies'] ?: 'None Reported'); ?></span>
                                        </div>
                                        <div class="bg-slate-50/80 p-2.5 rounded-xl border border-slate-100">
                                            <span class="font-semibold text-slate-800 block text-[11px]"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v4"/><path d="M14 14h-4"/><path d="M14 18h-4"/><path d="M14 8h-4"/><path d="M18 12h2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2h2"/><path d="M18 22V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v18"/></svg> Pre-existing Medical History:</span>
                                            <span class="text-slate-800 font-medium"><?= esc($p['medicalHistory'] ?: 'No chronic conditions recorded'); ?></span>
                                        </div>
                                    </div>

                                    <?php if (!empty($p['height']) || !empty($p['weight']) || !empty($p['bloodPressure'])): ?>
                                        <div class="flex flex-wrap items-center gap-2 bg-slate-50 p-2 rounded-xl border border-slate-200 text-xs">
                                            <span class="font-semibold text-slate-700 text-[11px] mr-1">Latest Physical Profile:</span>
                                            <?php if (!empty($p['height'])): ?><span class="bg-white text-slate-800 font-mono font-semibold px-2 py-0.5 rounded border border-slate-200">Ht: <?= esc($p['height']); ?> cm</span><?php endif; ?>
                                            <?php if (!empty($p['weight'])): ?><span class="bg-white text-sky-800 font-mono font-semibold px-2 py-0.5 rounded border border-sky-200">Wt: <?= esc($p['weight']); ?> kg</span><?php endif; ?>
                                            <?php if (!empty($p['bloodPressure'])): ?><span class="bg-white text-slate-800 font-mono font-semibold px-2 py-0.5 rounded border border-slate-200">BP: <?= esc($p['bloodPressure']); ?></span><?php endif; ?>
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
                    <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 2 4 4"/><path d="m17 7 3-3"/><path d="M19 9 8.7 19.3c-1 1-2.5 1-3.4 0l-.6-.6c-1-1-1-2.5 0-3.4L15 5"/><path d="m9 11 4 4"/><path d="m5 19-3 3"/><path d="m14 4 6 6"/></svg> Expanded Program on Immunization (EPI) Monitoring</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div class="bg-white rounded-2xl p-5 border border-slate-200/90 text-center">
                            <p class="text-3xl font-extrabold text-sky-700"><?= count($immunizationRecords); ?></p>
                            <p class="text-xs font-semibold text-slate-600 mt-1">Administered Records</p>
                        </div>
                        <div class="bg-white rounded-2xl p-5 border border-slate-200/90 text-center">
                            <p class="text-3xl font-extrabold text-amber-600">3</p>
                            <p class="text-xs font-semibold text-slate-600 mt-1">Due This Month</p>
                        </div>
                        <div class="bg-white rounded-2xl p-5 border border-slate-200/90 text-center">
                            <p class="text-3xl font-extrabold text-rose-600">0</p>
                            <p class="text-xs font-semibold text-slate-600 mt-1">Overdue Vaccines</p>
                        </div>
                    </div>

                    <?php if (empty($immunizationRecords)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h.01"/><path d="M15 12h.01"/><path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5"/><path d="M19 6.3a9 9 0 0 1 1.8 3.9 2 2 0 0 1 0 3.6 9 9 0 0 1-17.6 0 2 2 0 0 1 0-3.6A9 9 0 0 1 12 3c2 0 3.5 1.1 3.5 2.5s-.9 2.5-2 2.5c-.8 0-1.5-.4-1.5-1"/></svg></span>
                            <p class="text-sm font-semibold text-slate-700">No Immunization Records Found</p>
                            <p class="text-xs text-slate-400 mt-0.5">Vaccination records will appear here as doses are administered and logged.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($immunizationRecords as $im): ?>
                                <div class="dashboard-card bg-white rounded-2xl p-4 border border-slate-200 shadow-sm flex items-center justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-800 text-sm sm:text-base"><?= esc($im['childName']); ?></p>
                                        <p class="text-xs text-slate-600 mt-0.5">Vaccine: <strong class="text-slate-800 font-bold"><?= esc($im['vaccineName']); ?></strong> (<?= esc($im['targetAge']); ?>) · Batch: <?= esc($im['lot']); ?></p>
                                        <p class="text-[11px] text-slate-400 mt-0.5">Barangay: <?= esc($im['barangay']); ?> · Given: <?= esc($im['dateGiven']); ?> · Next Visit: <strong class="text-teal-700 font-semibold"><?= esc($im['nextVisit']); ?></strong></p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 shrink-0">Administered</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 5: NUTRITION (OPT+) -->
            <?php if ($tab === 'nutrition'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg> Operation Timbang Plus (OPT+) Child Nutrition</h2>
                    <?php if (empty($nutritionCases)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg></span>
                            <p class="text-sm font-semibold text-slate-700">No Operation Timbang Profiles Recorded</p>
                            <p class="text-xs text-slate-400 mt-0.5">Child growth monitoring measurements will display here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($nutritionCases as $n): ?>
                                <div class="dashboard-card bg-white rounded-2xl p-4 border border-slate-200 shadow-sm space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <p class="font-bold text-slate-800 text-sm"><?= esc($n['name']); ?> <span class="text-xs text-slate-500 font-medium">(<?= esc($n['age']); ?>y / <?= esc($n['gender']); ?>)</span></p>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">Normal Growth</span>
                                    </div>
                                    <div class="flex gap-4 text-xs font-medium text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200/80">
                                        <span>Barangay: <strong class="text-slate-800"><?= esc($n['barangay']); ?></strong></span>
                                        <span>Height: <strong class="text-sky-700"><?= esc($n['height']); ?> cm</strong></span>
                                        <span>Weight: <strong class="text-slate-800"><?= esc($n['weight']); ?> kg</strong></span>
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
                    <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18h8"/><path d="M3 22h18"/><path d="M14 22a7 7 0 1 0 0-14h-1"/><path d="M9 14h2"/><path d="M9 12a2 2 0 0 1-2-2V6h6v4a2 2 0 0 1-2 2Z"/><path d="M12 6V3a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v3"/></svg> TB-DOTS Case Management &amp; Treatment Adherence</h2>
                    <?php if (empty($tbCases)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18h8"/><path d="M3 22h18"/><path d="M14 22a7 7 0 1 0 0-14h-1"/><path d="M9 14h2"/><path d="M9 12a2 2 0 0 1-2-2V6h6v4a2 2 0 0 1-2 2Z"/><path d="M12 6V3a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v3"/></svg></span>
                            <p class="text-sm font-semibold text-slate-700">No TB-DOTS Cases Recorded</p>
                            <p class="text-xs text-slate-400 mt-0.5">Tuberculosis patients on treatment regimen will be listed here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($tbCases as $tb): ?>
                                <div class="dashboard-card bg-white rounded-2xl p-4 border border-slate-200 shadow-sm space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm sm:text-base"><?= esc($tb['name']); ?> <span class="text-xs text-slate-500 font-medium">(<?= esc($tb['age']); ?>y / <?= esc($tb['gender']); ?>)</span></p>
                                            <p class="text-xs text-slate-500 font-mono mt-0.5">DOH Reg No: <?= esc($tb['tb_registration_number']); ?> · Type: <?= esc($tb['classification']); ?></p>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200"><?= esc($tb['treatment_status']); ?></span>
                                    </div>
                                    <p class="text-xs text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-200/80">Barangay: <strong><?= esc($tb['barangay']); ?></strong> · Treatment Started: <strong><?= esc($tb['treatment_start_date']); ?></strong></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 7: DISEASE SURVEILLANCE (PIDSR) -->
            <?php if ($tab === 'disease'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> Disease Surveillance (PIDSR)</h2>
                    <div class="dashboard-card bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="font-bold text-slate-800 text-sm">Notifiable Diseases Active Surveillance</h3>
                            <span class="text-xs font-bold bg-rose-50 text-rose-800 px-2.5 py-1 rounded-full border border-rose-200">DOH Region IV-A</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div class="p-4 bg-rose-50/60 border border-rose-200/80 rounded-xl space-y-1">
                                <p class="font-bold text-rose-900 text-sm">Dengue Fever (ICD-10: A90)</p>
                                <p class="text-rose-700">Community vector control and larval source reduction active across barangays.</p>
                            </div>
                            <div class="p-4 bg-amber-50/60 border border-amber-200/80 rounded-xl space-y-1">
                                <p class="font-bold text-amber-900 text-sm">Leptospirosis (ICD-10: A27)</p>
                                <p class="text-amber-700">Post-flood prophylaxis distribution and health education ongoing.</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 8: BHW MANAGEMENT -->
            <?php if ($tab === 'bhw'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Barangay Health Worker (BHW) Supervisory Registry</h2>
                    <?php if (empty($bhwList)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-dashed border-slate-200 shadow-sm">
                            <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                            <p class="text-sm font-semibold text-slate-700">No BHW Volunteers Registered</p>
                            <p class="text-xs text-slate-400 mt-0.5">Assigned Barangay Health Workers will be listed here.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php foreach ($bhwList as $b): ?>
                                <div class="dashboard-card bg-white rounded-2xl p-4 border border-slate-200 shadow-sm flex items-center justify-between gap-3 hover:border-teal-200 transition-all">
                                    <div>
                                        <p class="font-bold text-slate-800 text-sm sm:text-base"><?= esc($b['name']); ?></p>
                                        <p class="text-xs text-slate-600 mt-0.5">Barangay: <strong class="text-slate-800"><?= esc($b['barangay']); ?></strong></p>
                                        <p class="text-xs font-mono text-slate-500 mt-0.5"><span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg> Contact:</span> <?= esc($b['phone_number'] ?: 'Not recorded'); ?></p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 shrink-0">Active BHW</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- MODAL: NEW OPD TRIAGE RECORD -->
    <?php if ($modal === 'new_triage'): ?>
        <div class="fixed inset-0 bg-slate-950/45 backdrop-blur-sm flex items-end sm:items-center justify-center z-50 p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-200">
                <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-white rounded-t-2xl shrink-0">
                    <div>
                        <h2 class="text-base font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg> Record OPD Patient Triage &amp; Vitals</h2>
                        <p class="text-xs text-slate-500">Log patient chief complaint and vital signs assessment</p>
                    </div>
                    <a href="<?= esc(tabUrl('opd')); ?>" class="text-slate-400 hover:text-slate-700 text-lg font-bold w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></a>
                </div>
                <form class="p-5 space-y-4 text-xs overflow-y-auto" method="post">
                    <input type="hidden" name="action" value="save_triage">

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Select Patient Resident *</label>
                        <select name="resident_id" required class="w-full p-3 border border-slate-300 rounded-xl text-sm font-semibold focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none">
                            <option value="">-- Select Resident Patient --</option>
                            <?php foreach ($allResidentsList as $r): ?>
                                <option value="<?= esc($r['id']); ?>"><?= esc($r['name']); ?> (<?= esc($r['barangay']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Attending Physician / Staff *</label>
                        <select name="physician_id" required class="w-full p-3 border border-slate-300 rounded-xl text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none">
                            <?php foreach ($allStaffList as $st): ?>
                                <option value="<?= esc($st['id']); ?>"><?= esc($st['name']); ?> (<?= esc($st['staff_type']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Chief Health Complaint *</label>
                        <input name="chief_complaint" required placeholder="e.g., High fever, productive cough, and body malaise" class="w-full p-3 border border-slate-300 rounded-xl text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none">
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                        <p class="font-bold text-slate-800 text-xs">Vital Signs Assessment</p>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">BP (mmHg)</label>
                                <input name="bp" value="120/80" class="w-full p-2 border border-slate-300 rounded-lg text-xs font-mono font-bold text-slate-800">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Temp (°C)</label>
                                <input name="temp" value="36.8°C" class="w-full p-2 border border-slate-300 rounded-lg text-xs font-mono font-bold text-slate-800">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Weight (kg)</label>
                                <input name="weight" value="60 kg" class="w-full p-2 border border-slate-300 rounded-lg text-xs font-mono font-bold text-slate-800">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Respiratory Rate</label>
                                <input name="rr" value="18/min" class="w-full p-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-800">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Heart Rate</label>
                                <input name="hr" value="75 bpm" class="w-full p-2 border border-slate-300 rounded-lg text-xs font-bold text-slate-800">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Primary Diagnosis</label>
                            <input name="diagnosis" value="Acute Upper Respiratory Tract Infection" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-semibold">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">ICD-10 Code</label>
                            <input name="icd10" value="J06.9" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-mono font-bold">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Prescribed Medications</label>
                        <input name="medications" placeholder="Paracetamol 500mg, Amoxicillin 500mg" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs">
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="<?= esc(tabUrl('opd')); ?>" class="flex-1 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-teal-600 text-white rounded-xl text-xs font-bold hover:bg-teal-700 shadow-md shadow-teal-600/20 shadow-xs transition-all">Save Nursing Triage Vitals</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- MODAL: ADD / EDIT RESIDENT MEDICAL RECORD -->
    <?php if ($modal === 'new_patient_record'): ?>
        <div class="fixed inset-0 bg-slate-950/45 backdrop-blur-sm flex items-end sm:items-center justify-center z-50 p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-2xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-200">
                <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-white rounded-t-2xl shrink-0">
                    <div>
                        <h2 class="text-base font-bold text-slate-800 flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg> Save Resident Medical Record &amp; Health Profile</h2>
                        <p class="text-xs text-slate-500">Select a resident to auto-fill their info. Medical history and vitals can be edited.</p>
                    </div>
                    <a href="<?= esc(tabUrl('patients')); ?>" class="text-slate-400 hover:text-slate-700 text-lg font-bold w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></a>
                </div>
                <form class="p-5 space-y-4 text-xs overflow-y-auto" method="post">
                    <input type="hidden" name="action" value="save_patient_record">

                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                        <p class="font-bold text-slate-800 text-xs">Resident Identity Selection</p>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Select Existing Resident (or leave blank to create new) *</label>
                            <select name="resident_id" id="nurse_resident_select" onchange="onNurseResidentSelectChange(this)" class="w-full p-3 border border-slate-300 rounded-xl text-sm font-semibold focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none">
                                <option value="0">-- Create New Resident Record --</option>
                                <?php foreach ($allResidentsList as $r): ?>
                                    <option value="<?= esc($r['id']); ?>" <?= $selectedResidentId === (int)$r['id'] ? 'selected' : '' ?>><?= esc($r['name']); ?> (<?= esc($r['barangay']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="nurse_locked_fields_notice" class="hidden p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 text-xs font-semibold flex items-center gap-2">
                            <span class="text-amber-700"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                            <span>Official records for <strong>Blood Type</strong>, <strong>PhilHealth ID</strong>, and <strong>Contact Number</strong> are locked. You can edit all clinical medical history, allergies, and vitals below.</span>
                        </div>

                        <div id="nurse_new_resident_fields" class="grid grid-cols-2 gap-3 pt-1">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">First Name</label>
                                <input name="first_name" id="nurse_first_name" placeholder="First Name" value="<?= esc($selectedResidentData['first_name'] ?? '') ?>" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-semibold">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Last Name</label>
                                <input name="last_name" id="nurse_last_name" placeholder="Last Name" value="<?= esc($selectedResidentData['last_name'] ?? '') ?>" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-semibold">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2.5">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1 flex items-center justify-between">
                                <span>Blood Type</span>
                                <span class="text-[10px] text-slate-400 font-normal"><span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Locked</span></span>
                            </label>
                            <select name="blood_type" id="nurse_blood_type" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-bold">
                                <?php foreach (['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'] as $bt): ?>
                                    <option value="<?= $bt ?>" <?= ($selectedResidentData['bloodType'] ?? 'O+') === $bt ? 'selected' : '' ?>><?= $bt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1 flex items-center justify-between">
                                <span>PhilHealth ID</span>
                                <span class="text-[10px] text-slate-400 font-normal"><span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Locked</span></span>
                            </label>
                            <input name="philhealth_id" id="nurse_philhealth_id" value="<?= esc($selectedResidentData['philhealthNo'] ?? '') ?>" placeholder="PH-12-345678901-2" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1 flex items-center justify-between">
                                <span>Contact Phone</span>
                                <span class="text-[10px] text-slate-400 font-normal"><span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Locked</span></span>
                            </label>
                            <input name="contact_number" id="nurse_contact_number" value="<?= esc($selectedResidentData['contactNo'] ?? '') ?>" placeholder="0917 123 4567" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-mono">
                        </div>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="font-bold text-slate-800 text-xs">Physical Vitals Profile</p>
                            <span class="text-[10px] font-bold text-teal-700 bg-teal-50 px-2 py-0.5 rounded border border-sky-200"><span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg> Editable by Nurse</span></span>
                        </div>
                        <div class="grid grid-cols-3 gap-2.5">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Height (cm)</label>
                                <input name="height" id="nurse_height" value="<?= esc($selectedResidentData['height'] ?? '') ?>" placeholder="e.g. 165" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-800">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Weight (kg)</label>
                                <input name="weight" id="nurse_weight" value="<?= esc($selectedResidentData['weight'] ?? '') ?>" placeholder="e.g. 62" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-800">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Baseline Blood Pressure</label>
                                <input name="blood_pressure" id="nurse_blood_pressure" value="<?= esc($selectedResidentData['bloodPressure'] ?? '') ?>" placeholder="120/80" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-800">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-800 mb-1 flex items-center justify-between">
                            <span>Known Drug & Food Allergies *</span>
                            <span class="text-[10px] font-bold text-teal-700 bg-teal-50 px-2 py-0.5 rounded border border-sky-200"><span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg> Editable by Nurse</span></span>
                        </label>
                        <textarea name="allergies" id="nurse_allergies" rows="2" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs resize-none focus:border-teal-500 outline-none" placeholder="e.g. Penicillin, Sulfur, Seafood, Latex (Write 'None' if clear)"><?= esc($selectedResidentData['allergies'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-800 mb-1 flex items-center justify-between">
                            <span>Pre-existing Medical History & Chronic Conditions *</span>
                            <span class="text-[10px] font-bold text-teal-700 bg-teal-50 px-2 py-0.5 rounded border border-sky-200"><span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/></svg> Editable by Nurse</span></span>
                        </label>
                        <textarea name="medical_history" id="nurse_medical_history" rows="2" class="w-full p-2.5 border border-slate-300 rounded-xl text-xs resize-none focus:border-teal-500 outline-none" placeholder="e.g. Essential Hypertension, Type 2 Diabetes Mellitus, Asthma, Previous Appendectomy (2021)"><?= esc($selectedResidentData['medicalHistory'] ?? '') ?></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="<?= esc(tabUrl('patients')); ?>" class="flex-1 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-teal-600 text-white rounded-xl text-xs font-bold hover:bg-teal-700 shadow-md shadow-teal-600/20 shadow-xs transition-all">Save Complete Resident Medical Record</button>
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

            (function() {
                const selectEl = document.getElementById('nurse_resident_select');
                if (selectEl) {
                    onNurseResidentSelectChange(selectEl);
                }
            })();
        </script>
    <?php endif; ?>

    <!-- SIDEBAR TOGGLE + UI ENHANCEMENTS -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('sidebar-expanded')) {
                sidebar.classList.remove('sidebar-expanded');
                sidebar.classList.add('sidebar-collapsed');
                localStorage.setItem('sidebar_collapsed', 'true');
            } else {
                sidebar.classList.remove('sidebar-collapsed');
                sidebar.classList.add('sidebar-expanded');
                localStorage.setItem('sidebar_collapsed', 'false');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Restore collapsed sidebar preference
            if (localStorage.getItem('sidebar_collapsed') === 'true' && window.innerWidth >= 640) {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.classList.remove('sidebar-expanded');
                    sidebar.classList.add('sidebar-collapsed');
                }
            }

            // Scroll progress bar
            const scrollProgress = document.getElementById('scroll-progress');
            const updateScrollProgress = () => {
                if (!scrollProgress) return;
                const scrollable = document.documentElement.scrollHeight - window.innerHeight;
                const progress = scrollable > 0 ? Math.min((window.scrollY / scrollable) * 100, 100) : 0;
                scrollProgress.style.width = progress + '%';
            };
            updateScrollProgress();
            window.addEventListener('scroll', updateScrollProgress, { passive: true });
            window.addEventListener('resize', updateScrollProgress);

            // Reveal-on-scroll for cards
            const revealItems = document.querySelectorAll('.dashboard-card, main > div > div');
            if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                const revealObserver = new IntersectionObserver(entries => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            revealObserver.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.08, rootMargin: '0px 0px -24px' });

                revealItems.forEach((item, index) => {
                    item.classList.add('reveal-on-scroll');
                    item.style.transitionDelay = (Math.min(index % 4, 3) * 55) + 'ms';
                    revealObserver.observe(item);
                });
            } else {
                revealItems.forEach(item => item.classList.add('is-visible'));
            }
        });
    </script>
</body>
</html>