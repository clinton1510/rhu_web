<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$stType = strtoupper((string)($_SESSION['rhu_staff_login']['staff_type'] ?? ''));
if (empty($_SESSION['rhu_staff_login']) || ($stType !== 'MIDWIFE' && !str_contains($stType, 'MIDWIFE'))) {
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

$tabs = [
    'overview' => ['Overview', '⌂'],
    'maternal' => ['Maternal Health', '👶'],
    'fp' => ['Family Planning', '♥'],
    'immunization' => ['Immunization', '🔬'],
    'vital' => ['Vital Statistics', '▤'],
    'opd' => ['Prenatal OPD', '📋'],
    'certificates' => ['Certificates', '🏅'],
];

$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab])) $tab = 'overview';

$modal = $_GET['modal'] ?? '';
$flashSuccess = $_SESSION['midwife_flash_success'] ?? '';
$flashError = $_SESSION['midwife_flash_error'] ?? '';
unset($_SESSION['midwife_flash_success'], $_SESSION['midwife_flash_error']);

// Keep older installations compatible with the structured prenatal form.
if (!empty($pdo)) {
    foreach ([
        "CREATE TABLE IF NOT EXISTS family_planning_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, resident_id INT NOT NULL,
            contraceptive_method VARCHAR(100) NOT NULL, acceptor_type VARCHAR(50) NOT NULL DEFAULT 'New Acceptor',
            last_supply_date DATE NOT NULL, next_visit_date DATE NULL, status VARCHAR(30) NOT NULL DEFAULT 'Active',
            clinical_notes TEXT NULL, healthcare_provider_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_fp_resident (resident_id), INDEX idx_fp_next_visit (next_visit_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS maternal_referrals (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, resident_id INT NOT NULL, pregnancy_id BIGINT UNSIGNED NULL,
            diagnosis VARCHAR(255) NOT NULL, referred_to VARCHAR(255) NOT NULL, referral_reason TEXT NOT NULL,
            urgency VARCHAR(30) NOT NULL DEFAULT 'Routine', referral_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            referred_by_id INT NULL, referral_date DATE NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_referral_resident (resident_id), INDEX idx_referral_status (referral_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ] as $midwifeTableSql) {
        try { $pdo->exec($midwifeTableSql); } catch (Throwable $ignored) {}
    }
    foreach ([
        "ALTER TABLE pregnancies ADD COLUMN gravida INT UNSIGNED NOT NULL DEFAULT 1 AFTER resident_id",
        "ALTER TABLE pregnancies ADD COLUMN para INT UNSIGNED NOT NULL DEFAULT 0 AFTER gravida"
    ] as $pregnancySchemaUpdate) {
        try {
            $pdo->exec($pregnancySchemaUpdate);
        } catch (Throwable $ignored) {
            // The column already exists, or migrations are managed externally.
        }
    }
}

$loggedInStaffId = (int)($_SESSION['rhu_staff_login']['staff_id'] ?? 0);
$midwifeProfile = null;
if (!empty($pdo) && $loggedInStaffId > 0) {
    try {
        $midwifeStmt = $pdo->prepare('SELECT id, staff_id, specialty, cases_assisted, assigned_facility FROM midwife WHERE staff_id = :staff_id LIMIT 1');
        $midwifeStmt->execute(['staff_id' => $loggedInStaffId]);
        $midwifeProfile = $midwifeStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$midwifeProfile) {
            $createMidwife = $pdo->prepare("INSERT INTO midwife (staff_id, specialty, cases_assisted, assigned_facility)
                VALUES (:staff_id, 'Maternal and Newborn Care', 0, 'Nasugbu RHU I')");
            $createMidwife->execute(['staff_id' => $loggedInStaffId]);
            $midwifeProfile = [
                'id' => (int)$pdo->lastInsertId(),
                'staff_id' => $loggedInStaffId,
                'specialty' => 'Maternal and Newborn Care',
                'cases_assisted' => 0,
                'assigned_facility' => 'Nasugbu RHU I',
            ];
        }
    } catch (Throwable $midwifeProfileError) {
        error_log('Midwife profile load error: ' . $midwifeProfileError->getMessage());
    }
}

// ----------------------------------------------------
// 1. POST FORM HANDLERS FOR MIDWIFERY & PRENATAL CARE
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($pdo)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'issue_certificate') {
        try {
            $issued = portalIssueResidentCertificate($pdo, $_POST, $loggedInStaffId, 'Rural Health Midwife');
            $_SESSION['midwife_flash_success'] = "{$issued['type']} {$issued['number']} was issued and sent to the Resident.";
        } catch (Throwable $e) {
            $_SESSION['midwife_flash_error'] = 'Certificate Error: ' . $e->getMessage();
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
                    portalNotifyResident($pdo, $resId, "Your Prenatal & Maternal consultation has been updated by the Midwife. Status: {$status}. Assessment: {$diagnosis}", "ResidentDashboard.php?tab=appointments");
                }
                $_SESSION['midwife_flash_success'] = 'Consultation updated and response sent to resident successfully!';
            } catch (Exception $e) {
                $_SESSION['midwife_flash_error'] = 'Error updating consultation: ' . $e->getMessage();
            }
        }
        header('Location: ' . tabUrl('overview'));
        exit;
    }

    // Action: Save New Maternal Pregnancy Case
    if ($action === 'save_maternal') {
        $residentSelection = (string)($_POST['resident_id'] ?? '');
        $isNewMother = $residentSelection === 'new';
        $residentId = $isNewMother ? 0 : (int)$residentSelection;
        $gravida = (int)($_POST['gravida'] ?? 1);
        $para = (int)($_POST['para'] ?? 0);
        $lmp = trim($_POST['lmp'] ?? date('Y-m-d', strtotime('-3 months')));
        $edc = trim($_POST['edc'] ?? date('Y-m-d', strtotime('+6 months')));
        $highRisk = isset($_POST['high_risk']) ? 1 : 0;
        $riskFactors = trim($_POST['risk_factors'] ?? 'Routine Monitoring');
        $status = trim($_POST['pregnancy_status'] ?? 'Active');

        if ($residentId <= 0 && !$isNewMother) {
            $_SESSION['midwife_flash_error'] = 'Please select a valid resident mother.';
        } elseif ($gravida < 1 || $para < 0 || $para > $gravida) {
            $_SESSION['midwife_flash_error'] = 'Enter a valid obstetric history. Para cannot be greater than gravida.';
        } elseif (!DateTime::createFromFormat('Y-m-d', $lmp) || !DateTime::createFromFormat('Y-m-d', $edc) || $edc <= $lmp) {
            $_SESSION['midwife_flash_error'] = 'Enter valid LMP and EDC dates. The EDC must be after the LMP.';
        } else {
            try {
                $pdo->beginTransaction();
                if ($isNewMother) {
                    $newFirstName = trim($_POST['new_first_name'] ?? '');
                    $newMiddleName = trim($_POST['new_middle_name'] ?? '');
                    $newLastName = trim($_POST['new_last_name'] ?? '');
                    $newDob = trim($_POST['new_date_of_birth'] ?? '');
                    $newBarangay = trim($_POST['new_barangay'] ?? '');
                    $newAddress = trim($_POST['new_address'] ?? '');
                    $newContact = trim($_POST['new_contact_number'] ?? '');
                    if ($newFirstName === '' || $newLastName === '' || $newDob === '' || $newBarangay === '') {
                        throw new RuntimeException('Complete the new mother’s name, birth date, and barangay.');
                    }
                    $duplicateStmt = $pdo->prepare("SELECT id FROM residents
                        WHERE first_name = :first_name AND last_name = :last_name AND date_of_birth = :dob LIMIT 1");
                    $duplicateStmt->execute(['first_name'=>$newFirstName, 'last_name'=>$newLastName, 'dob'=>$newDob]);
                    $residentId = (int)($duplicateStmt->fetchColumn() ?: 0);
                    if ($residentId <= 0) {
                        $newResidentStmt = $pdo->prepare("INSERT INTO residents
                            (first_name, middle_name, last_name, date_of_birth, gender, contact_number, address, barangay, is_active, created_at, updated_at)
                            VALUES (:first_name, :middle_name, :last_name, :dob, 'Female', :contact, :address, :barangay, 1, NOW(), NOW())");
                        $newResidentStmt->execute([
                            'first_name'=>$newFirstName, 'middle_name'=>$newMiddleName !== '' ? $newMiddleName : null,
                            'last_name'=>$newLastName, 'dob'=>$newDob, 'contact'=>$newContact !== '' ? $newContact : null,
                            'address'=>$newAddress !== '' ? $newAddress : $newBarangay, 'barangay'=>$newBarangay
                        ]);
                        $residentId = (int)$pdo->lastInsertId();
                    }
                }
                $motherCheck = $pdo->prepare("SELECT id FROM residents WHERE id = :id AND gender LIKE 'Female%' LIMIT 1");
                $motherCheck->execute(['id' => $residentId]);
                if (!$motherCheck->fetchColumn()) {
                    throw new RuntimeException('The selected female resident no longer exists.');
                }

                $stmt = $pdo->prepare("INSERT INTO pregnancies
                    (resident_id, gravida, para, last_menstrual_period, expected_delivery_date, risk_factors, high_risk, pregnancy_status, created_at, updated_at)
                    VALUES (:res, :gravida, :para, :lmp, :edc, :rf, :hr, :st, NOW(), NOW())");
                $stmt->execute([
                    'res' => $residentId,
                    'gravida' => $gravida,
                    'para' => $para,
                    'lmp' => $lmp,
                    'edc' => $edc,
                    'rf' => $riskFactors !== '' ? $riskFactors : 'Routine Monitoring',
                    'hr' => $highRisk,
                    'st' => $status
                ]);
                if (!empty($midwifeProfile['id'])) {
                    $pdo->prepare('UPDATE midwife SET cases_assisted = cases_assisted + 1 WHERE id = :id')
                        ->execute(['id' => (int)$midwifeProfile['id']]);
                    $midwifeProfile['cases_assisted'] = (int)$midwifeProfile['cases_assisted'] + 1;
                }
                $pdo->commit();
                portalNotifyResident($pdo, $residentId, "Your maternal health & prenatal tracking record (EDC: {$edc}) has been updated by Rural Health Midwife.", "ResidentDashboard.php?tab=history");
                $_SESSION['midwife_flash_success'] = 'New Maternal Prenatal record saved successfully into database!';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['midwife_flash_error'] = 'Database Error: ' . $e->getMessage();
            }
        }
        header('Location: ' . tabUrl('maternal'));
        exit;
    }

    if ($action === 'save_family_planning') {
        $residentId = (int)($_POST['resident_id'] ?? 0);
        $method = trim($_POST['contraceptive_method'] ?? '');
        $acceptorType = trim($_POST['acceptor_type'] ?? 'New Acceptor');
        $supplyDate = trim($_POST['last_supply_date'] ?? date('Y-m-d'));
        $nextVisit = trim($_POST['next_visit_date'] ?? '');
        $notes = trim($_POST['clinical_notes'] ?? '');
        try {
            if ($residentId <= 0 || $method === '') throw new RuntimeException('Select a resident and contraceptive method.');
            $stmt = $pdo->prepare("INSERT INTO family_planning_records
                (resident_id, contraceptive_method, acceptor_type, last_supply_date, next_visit_date, status, clinical_notes, healthcare_provider_id)
                VALUES (:resident, :method, :acceptor, :supply, :next_visit, 'Active', :notes, :provider)");
            $stmt->execute(['resident'=>$residentId, 'method'=>$method, 'acceptor'=>$acceptorType, 'supply'=>$supplyDate,
                'next_visit'=>$nextVisit !== '' ? $nextVisit : null, 'notes'=>$notes,
                'provider'=>$loggedInStaffId ?: null]);
            portalNotifyResident($pdo, $residentId, "Your family planning record was updated. Method: {$method}. Next visit: " . ($nextVisit ?: 'To be scheduled') . '.', 'ResidentDashboard.php?tab=records');
            $_SESSION['midwife_flash_success'] = 'Family planning client record saved.';
        } catch (Throwable $e) { $_SESSION['midwife_flash_error'] = 'Family Planning Error: ' . $e->getMessage(); }
        header('Location: ' . tabUrl('fp')); exit;
    }

    if ($action === 'save_immunization') {
        $residentId = (int)($_POST['resident_id'] ?? 0);
        $vaccineId = (int)($_POST['vaccine_id'] ?? 0);
        $dateGiven = trim($_POST['vaccination_date'] ?? date('Y-m-d'));
        $nextDose = trim($_POST['next_dose_date'] ?? '');
        $batch = trim($_POST['batch_number'] ?? '');
        try {
            if ($residentId <= 0 || $vaccineId <= 0 || $batch === '') throw new RuntimeException('Resident, vaccine, and batch number are required.');
            $stmt = $pdo->prepare("INSERT INTO vaccination_records
                (resident_id, vaccine_id, vaccination_date, healthcare_provider_id, batch_number, site_of_injection, adverse_reactions, next_dose_date)
                VALUES (:resident, :vaccine, :given, :provider, :batch, :site, :reaction, :next_dose)");
            $stmt->execute(['resident'=>$residentId, 'vaccine'=>$vaccineId, 'given'=>$dateGiven,
                'provider'=>$loggedInStaffId ?: null, 'batch'=>$batch,
                'site'=>trim($_POST['site_of_injection'] ?? ''), 'reaction'=>trim($_POST['adverse_reactions'] ?? ''),
                'next_dose'=>$nextDose !== '' ? $nextDose : null]);
            portalNotifyResident($pdo, $residentId, "A vaccination was recorded on {$dateGiven}. Next dose: " . ($nextDose ?: 'Not required') . '.', 'ResidentDashboard.php?tab=immunization');
            $_SESSION['midwife_flash_success'] = 'Immunization record saved and resident notified.';
        } catch (Throwable $e) { $_SESSION['midwife_flash_error'] = 'Immunization Error: ' . $e->getMessage(); }
        header('Location: ' . tabUrl('immunization')); exit;
    }

    if ($action === 'save_birth_record') {
        $motherId = (int)($_POST['mother_id'] ?? 0);
        $childName = trim($_POST['child_name'] ?? '');
        $birthDate = trim($_POST['date_of_birth'] ?? '');
        try {
            if ($motherId <= 0 || $childName === '' || $birthDate === '') throw new RuntimeException('Mother, child name, and birth date are required.');
            $certificateNo = trim($_POST['birth_certificate_number'] ?? '') ?: 'BR-' . date('YmdHis');
            $stmt = $pdo->prepare("INSERT INTO vital_statistics_births
                (birth_certificate_number, child_name, date_of_birth, time_of_birth, place_of_birth, mother_id, father_name, gender, birth_weight_kg, birth_length_cm, delivery_attendant_id, registered_date)
                VALUES (:certificate, :child, :birth_date, :birth_time, :place, :mother, :father, :gender, :weight, :length, :attendant, CURDATE())");
            $stmt->execute(['certificate'=>$certificateNo, 'child'=>$childName, 'birth_date'=>$birthDate,
                'birth_time'=>($_POST['time_of_birth'] ?? '') ?: null,
                'place'=>trim($_POST['place_of_birth'] ?? '') ?: ($midwifeProfile['assigned_facility'] ?? 'Nasugbu RHU I'),
                'mother'=>$motherId, 'father'=>trim($_POST['father_name'] ?? ''), 'gender'=>trim($_POST['gender'] ?? ''),
                'weight'=>($_POST['birth_weight_kg'] ?? '') !== '' ? $_POST['birth_weight_kg'] : null,
                'length'=>($_POST['birth_length_cm'] ?? '') !== '' ? $_POST['birth_length_cm'] : null,
                'attendant'=>$loggedInStaffId ?: null]);
            if (!empty($midwifeProfile['id'])) {
                $pdo->prepare('UPDATE midwife SET cases_assisted = cases_assisted + 1 WHERE id = :id')
                    ->execute(['id' => (int)$midwifeProfile['id']]);
                $midwifeProfile['cases_assisted'] = (int)$midwifeProfile['cases_assisted'] + 1;
            }
            portalNotifyResident($pdo, $motherId, "Birth record {$certificateNo} for {$childName} was registered.", 'ResidentDashboard.php?tab=family');
            $_SESSION['midwife_flash_success'] = 'Birth and vital-statistics record registered.';
        } catch (Throwable $e) { $_SESSION['midwife_flash_error'] = 'Vital Statistics Error: ' . $e->getMessage(); }
        header('Location: ' . tabUrl('vital')); exit;
    }

    if ($action === 'save_referral') {
        $residentId = (int)($_POST['resident_id'] ?? 0);
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $facility = trim($_POST['referred_to'] ?? '');
        $reason = trim($_POST['referral_reason'] ?? '');
        try {
            if ($residentId <= 0 || $diagnosis === '' || $facility === '' || $reason === '') throw new RuntimeException('Complete all required referral details.');
            $pregnancyStmt = $pdo->prepare("SELECT id FROM pregnancies WHERE resident_id = :resident AND pregnancy_status = 'Active' ORDER BY id DESC LIMIT 1");
            $pregnancyStmt->execute(['resident'=>$residentId]);
            $pregnancyId = $pregnancyStmt->fetchColumn() ?: null;
            $stmt = $pdo->prepare("INSERT INTO maternal_referrals
                (resident_id, pregnancy_id, diagnosis, referred_to, referral_reason, urgency, referral_status, referred_by_id, referral_date)
                VALUES (:resident, :pregnancy, :diagnosis, :facility, :reason, :urgency, 'Pending', :provider, CURDATE())");
            $stmt->execute(['resident'=>$residentId, 'pregnancy'=>$pregnancyId, 'diagnosis'=>$diagnosis, 'facility'=>$facility,
                'reason'=>$reason, 'urgency'=>trim($_POST['urgency'] ?? 'Routine'),
                'provider'=>$loggedInStaffId ?: null]);
            portalNotifyResident($pdo, $residentId, "Maternal referral created for {$facility}. Diagnosis: {$diagnosis}.", 'ResidentDashboard.php?tab=records');
            $_SESSION['midwife_flash_success'] = 'Maternal referral created and resident notified.';
        } catch (Throwable $e) { $_SESSION['midwife_flash_error'] = 'Referral Error: ' . $e->getMessage(); }
        header('Location: ' . tabUrl('referrals')); exit;
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
$allResidentsList = [];
$vaccineSchedules = [];
$midwifeCertificateTypes = [];
$allStaffList = [];

if (!empty($pdo)) {
    try {
        // Dropdown options
        $allMothersList = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, barangay FROM residents WHERE gender LIKE 'Female%' ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $allResidentsList = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, barangay FROM residents ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $vaccineSchedules = $pdo->query("SELECT id, vaccine_name, age_group FROM immunization_schedules ORDER BY vaccine_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $midwifeCertificateTypes = portalEnsureCertificateTypes($pdo, ['Prenatal Care Certificate','Maternal Health Certificate','Birth Attendance Certificate','Family Planning Counseling Certificate']);
        $allStaffList = $pdo->query("SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) as name, s.staff_type FROM staff s JOIN users u ON s.user_id = u.id ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 1. Maternal Pregnancies
        $pStmt = $pdo->query("
            SELECT p.id, p.gravida, p.para, CONCAT(r.first_name, ' ', r.last_name) as name, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, r.barangay, r.blood_type as bloodType, p.last_menstrual_period as lmp, p.expected_delivery_date as edc, p.risk_factors as risks, p.high_risk as highRisk, p.pregnancy_status as status, DATE_ADD(p.expected_delivery_date, INTERVAL -1 MONTH) as nextVisit
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

        $fpClients = $pdo->query("SELECT fp.id, CONCAT(r.first_name, ' ', r.last_name) AS name,
            TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) AS age, r.barangay,
            fp.contraceptive_method AS method, fp.acceptor_type AS acceptorType,
            fp.last_supply_date AS lastSupply, fp.next_visit_date AS nextVisit,
            CASE WHEN fp.status = 'Active' AND fp.next_visit_date < CURDATE() THEN 'Overdue' ELSE fp.status END AS status
            FROM family_planning_records fp JOIN residents r ON r.id = fp.resident_id ORDER BY fp.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $vitalRecords = $pdo->query("SELECT vb.id, vb.child_name AS name, CONCAT(r.first_name, ' ', r.last_name) AS motherName,
            vb.date_of_birth AS date, r.barangay, CONCAT(COALESCE(vb.birth_weight_kg, 0), ' kg') AS weight,
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'RHU Midwife') AS attendant,
            CASE WHEN vb.registered_date IS NULL THEN 'Pending' ELSE 'Registered' END AS registrationStatus,
            vb.birth_certificate_number AS lncrn
            FROM vital_statistics_births vb JOIN residents r ON r.id = vb.mother_id
            LEFT JOIN staff s ON s.id = vb.delivery_attendant_id LEFT JOIN users u ON u.id = s.user_id
            ORDER BY vb.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $referralsList = $pdo->query("SELECT mr.id, mr.resident_id, CONCAT(r.first_name, ' ', r.last_name) AS patientName,
            TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) AS age, mr.diagnosis,
            mr.referred_to AS referredTo, mr.referral_reason AS reason, mr.urgency, mr.referral_status AS status
            FROM maternal_referrals mr JOIN residents r ON r.id = mr.resident_id ORDER BY mr.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 3. Prenatal OPD Consultations (Filtered by assigned staff)
        $midwifeStaffId = $loggedInStaffId;
        $midwifeUserId = (int)($_SESSION['rhu_staff_login']['id'] ?? 0);

        if ($midwifeStaffId > 0) {
            $opdStmt = $pdo->prepare("
                SELECT c.id, c.resident_id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, c.icd_code as icd10, c.medications_prescribed as medications, r.barangay, c.consultation_date as date, c.referral_needed, c.referral_to, c.consultation_notes, COALESCE(c.consultation_status, 'Scheduled') AS consultation_status
                FROM consultations c
                JOIN residents r ON c.resident_id = r.id
                LEFT JOIN staff doc_s ON c.physician_id = doc_s.id
                WHERE (c.physician_id = :sid OR doc_s.user_id = :uid OR c.chief_complaint LIKE '%Prenatal%' OR c.chief_complaint LIKE '%Maternal%' OR c.chief_complaint LIKE '%Midwife%')
                ORDER BY c.id DESC
            ");
            $opdStmt->execute(['sid' => $midwifeStaffId, 'uid' => $midwifeUserId]);
        } else {
            $opdStmt = $pdo->query("
                SELECT c.id, c.resident_id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, c.icd_code as icd10, c.medications_prescribed as medications, r.barangay, c.consultation_date as date, c.referral_needed, c.referral_to, c.consultation_notes, COALESCE(c.consultation_status, 'Scheduled') AS consultation_status
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
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .safe-area-pb { padding-bottom: env(safe-area-inset-bottom); }
    </style>
  <link rel="stylesheet" href="dashboard-enhancements.css?v=20260728-nurse-theme2">
    <script defer src="dashboard-enhancements.js?v=20260726-controls3"></script>
</head>

<body class="nurse-palette-dashboard min-h-screen bg-slate-50 text-slate-900 selection:bg-pink-500 selection:text-white">
    <div class="min-h-screen flex flex-col">

        <!-- HEADER -->
<header class="bg-gradient-to-r from-emerald-800 via-teal-800 to-cyan-900 text-white shadow-xl sticky top-0 z-40 border-b border-emerald-700/40">
            <div class="px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/15 backdrop-blur-md rounded-2xl flex items-center justify-center text-2xl shadow-inner border border-white/20">
                        👶
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-base sm:text-lg font-extrabold tracking-tight">Midwife Health Portal</h1>
                            <span class="inline-flex items-center gap-1 text-[11px] bg-pink-500/30 text-pink-200 px-2.5 py-0.5 rounded-full font-bold border border-pink-400/30 backdrop-blur-sm">
                                <span class="w-1.5 h-1.5 rounded-full bg-pink-400 animate-pulse"></span>
                                <?= esc($_SESSION['rhu_staff_login']['name'] ?? 'Rural Health Midwife') ?>
                            </span>
                        </div>
                        <p class="text-xs text-pink-200/90 font-medium"><?= esc($midwifeProfile['assigned_facility'] ?? 'Nasugbu RHU I') ?> — <?= esc($midwifeProfile['specialty'] ?? 'Maternal, Child Health & Family Planning') ?> · <?= (int)($midwifeProfile['cases_assisted'] ?? 0) ?> cases assisted</p>
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
                        class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold rounded-t-xl whitespace-nowrap flex-shrink-0 transition-all <?= $tab === $id ? 'bg-white text-pink-900 shadow-lg font-extrabold border-t-2 border-pink-400' : 'text-pink-100 hover:bg-white/10 hover:text-white'; ?>">
                        <span class="text-sm"><?= $icon; ?></span><?= esc($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </header>

        <!-- MAIN CONTENT AREA -->
        <main class="max-w-7xl mx-auto w-full px-3 sm:px-6 py-4 sm:py-6 space-y-5 pb-28 sm:pb-8 flex-1">

            <!-- FLASH NOTIFICATIONS -->
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
                    <!-- HIGH-RISK MATERNAL ALERT -->
                    <?php if ($highRiskCount > 0): ?>
                        <div class="bg-gradient-to-r from-red-50 via-rose-50 to-pink-50 border border-red-200/80 rounded-2xl p-4 sm:p-5 flex items-start gap-3 shadow-sm">
                            <span class="w-9 h-9 rounded-xl bg-red-100 text-red-600 flex items-center justify-center text-xl shrink-0">⚠</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-extrabold text-red-900 text-sm sm:text-base">High-Risk Pregnancy Alert — Immediate Midwife Follow-Up</p>
                                <p class="text-xs sm:text-sm text-red-700 mt-0.5"><?= $highRiskCount; ?> High-risk expectant mothers identified. Hospital delivery referral &amp; blood donor standby required.</p>
                            </div>
                            <a href="<?= esc(tabUrl('maternal')); ?>" class="text-xs bg-red-600 hover:bg-red-700 text-white font-bold px-3.5 py-2 rounded-xl shadow-md whitespace-nowrap transition-all">Review Cases</a>
                        </div>
                    <?php endif; ?>

                    <!-- METRICS GRID -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="<?= esc(tabUrl('maternal')); ?>" class="group bg-gradient-to-br from-pink-500/10 via-white to-white rounded-2xl p-5 shadow-sm hover:shadow-md border border-pink-100/80 transition-all">
                            <div class="flex items-center justify-between">
                                <span class="w-10 h-10 rounded-xl bg-pink-500/10 text-pink-700 flex items-center justify-center text-lg font-bold">👶</span>
                                <span class="text-xs font-bold text-pink-700 bg-pink-50 px-2 py-0.5 rounded-full border border-pink-200">Active</span>
                            </div>
                            <p class="text-3xl font-black text-pink-900 mt-3 group-hover:scale-105 transition-transform"><?= $activePrenatalCount; ?></p>
                            <p class="text-xs font-bold text-gray-800 mt-1">Active Prenatal Cases</p>
                            <p class="text-[11px] text-gray-400 font-medium">Ongoing Checkups</p>
                        </a>

                        <a href="<?= esc(tabUrl('maternal')); ?>" class="group bg-gradient-to-br from-red-500/10 via-white to-white rounded-2xl p-5 shadow-sm hover:shadow-md border border-red-100/80 transition-all">
                            <div class="flex items-center justify-between">
                                <span class="w-10 h-10 rounded-xl bg-red-500/10 text-red-700 flex items-center justify-center text-lg font-bold">⚠</span>
                                <span class="text-xs font-bold text-red-700 bg-red-50 px-2 py-0.5 rounded-full border border-red-200">High Risk</span>
                            </div>
                            <p class="text-3xl font-black text-red-900 mt-3 group-hover:scale-105 transition-transform"><?= $highRiskCount; ?></p>
                            <p class="text-xs font-bold text-gray-800 mt-1">High-Risk Mothers</p>
                            <p class="text-[11px] text-gray-400 font-medium">Special Care Required</p>
                        </a>

                        <a href="<?= esc(tabUrl('maternal')); ?>" class="group bg-gradient-to-br from-purple-500/10 via-white to-white rounded-2xl p-5 shadow-sm hover:shadow-md border border-purple-100/80 transition-all">
                            <div class="flex items-center justify-between">
                                <span class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-700 flex items-center justify-center text-lg font-bold">🤰</span>
                                <span class="text-xs font-bold text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full border border-purple-200">Follow-up</span>
                            </div>
                            <p class="text-3xl font-black text-purple-900 mt-3 group-hover:scale-105 transition-transform"><?= $postpartumCount; ?></p>
                            <p class="text-xs font-bold text-gray-800 mt-1">Postpartum Mothers</p>
                            <p class="text-[11px] text-gray-400 font-medium">Post-natal Care</p>
                        </a>

                        <a href="<?= esc(tabUrl('fp')); ?>" class="group bg-gradient-to-br from-rose-500/10 via-white to-white rounded-2xl p-5 shadow-sm hover:shadow-md border border-rose-100/80 transition-all">
                            <div class="flex items-center justify-between">
                                <span class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-700 flex items-center justify-center text-lg font-bold">♥</span>
                                <span class="text-xs font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-200">FP Registry</span>
                            </div>
                            <p class="text-3xl font-black text-rose-900 mt-3 group-hover:scale-105 transition-transform"><?= count($fpClients); ?></p>
                            <p class="text-xs font-bold text-gray-800 mt-1">Family Planning Clients</p>
                            <p class="text-[11px] text-gray-400 font-medium">Contraceptive Supply</p>
                        </a>
                    </div>

                    <!-- RECEIVED PRENATAL CONSULTATIONS QUEUE -->
                    <div class="bg-white rounded-2xl p-5 shadow-sm border border-pink-100/90 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <div>
                                <h3 class="font-extrabold text-gray-900 text-base flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-pink-500 animate-pulse"></span>
                                    Received Resident Prenatal Consultations
                                </h3>
                                <p class="text-xs text-gray-500">Live consultation requests submitted by residents for Midwifery & Prenatal Care</p>
                            </div>
                            <a href="<?= esc(tabUrl('maternal', ['modal' => 'new_maternal'])); ?>" class="px-3.5 py-2 bg-pink-700 hover:bg-pink-800 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5">
                                <span>+</span> New Prenatal Case
                            </a>
                        </div>

                        <?php if (empty($prenatalOPDList)): ?>
                            <div class="text-center py-8 bg-slate-50/50 rounded-2xl border border-dashed border-gray-200">
                                <span class="text-3xl block mb-2">📋</span>
                                <p class="text-sm font-bold text-gray-700">No Prenatal Consultations Assigned Yet</p>
                                <p class="text-xs text-gray-400 mt-0.5">When expectant mothers book appointment requests, they will appear here automatically.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach (array_slice($prenatalOPDList, 0, 5) as $opd): ?>
                                    <div class="bg-gradient-to-r from-slate-50/80 to-white rounded-xl p-4 border border-gray-200/80 hover:border-pink-200 transition-all space-y-3">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <p class="font-extrabold text-gray-900 text-sm sm:text-base"><?= esc($opd['patientName']); ?></p>
                                                    <span class="text-xs font-bold text-pink-800 bg-pink-100 px-2 py-0.5 rounded-md">
                                                        <?= esc($opd['age'] ?? 'N/A'); ?>y • <?= esc($opd['gender']); ?>
                                                    </span>
                                                    <span class="text-xs font-semibold text-purple-700 bg-purple-50 px-2 py-0.5 rounded-md border border-purple-100">
                                                        📍 Barangay <?= esc($opd['barangay']); ?>
                                                    </span>
                                                </div>
                                                <p class="text-xs font-semibold text-pink-900 mt-1">Chief Complaint: <span class="text-gray-800 font-normal"><?= esc($opd['chiefComplaint']); ?></span></p>
                                            </div>
                                            <div class="text-right">
                                                <span class="font-mono text-xs bg-pink-50 text-pink-900 font-bold px-2.5 py-1 rounded-lg border border-pink-200"><?= esc($opd['icd10'] ?: 'Z34.8'); ?></span>
                                                <p class="text-[10px] font-semibold text-gray-400 mt-1">📅 <?= esc($opd['date']); ?></p>
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-600 font-mono bg-white p-2.5 rounded-xl border border-gray-200/60">
                                            <?= esc($opd['consultation_notes']); ?>
                                        </div>

                                        <!-- MIDWIFE RESPONSE / UPDATE FORM -->
                                        <details class="group border-t border-pink-100 pt-2" open>
                                            <summary class="cursor-pointer text-xs font-bold text-pink-700 hover:text-pink-900 flex items-center justify-between py-1">
                                                <span>💬 Answer / Update Consultation Response for Resident</span>
                                                <span class="text-[10px] bg-pink-100 text-pink-800 font-extrabold px-2 py-0.5 rounded-md">Status: <?= esc($opd['consultation_status']); ?></span>
                                            </summary>
                                            <form method="post" class="mt-2 bg-pink-50/50 p-3 rounded-xl border border-pink-200/70 space-y-2.5">
                                                <input type="hidden" name="action" value="answer_consultation">
                                                <input type="hidden" name="consultation_id" value="<?= (int)$opd['id']; ?>">
                                                <input type="hidden" name="resident_id" value="<?= (int)($opd['resident_id'] ?? 0); ?>">
                                                
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Clinical Diagnosis / Assessment</label>
                                                        <input type="text" name="diagnosis" value="<?= esc($opd['diagnosis'] ?? ''); ?>" placeholder="e.g. Normal Pregnancy 16 weeks AOG" class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-pink-500 bg-white" required>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Consultation Status</label>
                                                        <select name="consultation_status" class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-pink-500 bg-white font-bold text-pink-900">
                                                            <option value="Completed" <?= ($opd['consultation_status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                            <option value="In Progress" <?= ($opd['consultation_status'] ?? '') === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                            <option value="Scheduled" <?= ($opd['consultation_status'] ?? '') === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                            <option value="Referred" <?= ($opd['consultation_status'] ?? '') === 'Referred' ? 'selected' : ''; ?>>Referred to Doctor</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Midwife Notes &amp; Advice for Resident</label>
                                                    <textarea name="consultation_notes" rows="2" placeholder="Enter prenatal advice, diet recommendations, and next visit schedule..." class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-pink-500 bg-white resize-none"><?= esc($opd['consultation_notes'] ?? ''); ?></textarea>
                                                </div>

                                                <div>
                                                    <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Prescriptions / Supplements</label>
                                                    <input type="text" name="medications_prescribed" value="<?= esc($opd['medications'] ?? ''); ?>" placeholder="e.g. Ferrous Sulfate + Folic Acid 1 tab OD" class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-pink-500 bg-white">
                                                </div>

                                                <div class="flex justify-end pt-1">
                                                    <button type="submit" class="px-4 py-2 bg-pink-700 hover:bg-pink-800 text-white text-xs font-extrabold rounded-lg shadow-sm transition-all flex items-center gap-1">
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

            <!-- TAB 2: MATERNAL HEALTH RECORDS -->
            <?php if ($tab === 'maternal'): ?>
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-pink-100 shadow-sm">
                        <div>
                            <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">👶 Maternal Health &amp; Prenatal Care Registry</h2>
                            <p class="text-xs text-gray-500">Comprehensive pregnancy tracking, EDC calculations, and high-risk monitoring</p>
                        </div>
                        <a href="<?= esc(tabUrl('maternal', ['modal' => 'new_maternal'])); ?>" class="px-4 py-2.5 bg-pink-700 hover:bg-pink-800 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5">
                            <span>+</span> Register New Prenatal Case
                        </a>
                    </div>

                    <?php if (empty($maternalCases)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-200/80 shadow-sm">
                            <span class="text-4xl block mb-2">🤰</span>
                            <p class="text-sm font-bold text-gray-700">No Maternal Prenatal Cases Recorded</p>
                            <p class="text-xs text-gray-400 mt-0.5">Click "+ Register New Prenatal Case" above to add an expectant mother.</p>
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
                                <div class="bg-white rounded-2xl p-5 shadow-sm border-l-4 <?= !empty($mom['highRisk']) ? 'border-red-500' : 'border-emerald-500'; ?> border border-gray-200/80 space-y-3">
                                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-100 pb-3">
                                        <div>
                                            <p class="font-extrabold text-gray-900 text-base"><?= esc($mom['name']); ?> <span class="text-xs text-gray-500 font-semibold">(<?= esc($mom['age']); ?> y/o<?= $gpTag; ?>)</span></p>
                                            <p class="text-xs text-gray-600 font-semibold mt-0.5">Barangay: <?= esc($mom['barangay']); ?> · Blood Type: <span class="text-red-700 font-bold bg-red-50 px-2 py-0.5 rounded border border-red-200"><?= esc($mom['bloodType']); ?></span></p>
                                            <p class="text-[11px] text-gray-400 mt-0.5">LMP: <?= esc($mom['lmp']); ?> · Expected EDC: <strong class="text-gray-900 font-extrabold"><?= esc($mom['edc']); ?></strong></p>
                                        </div>
                                        <div class="text-right">
                                            <span class="px-3 py-1 rounded-full text-xs font-bold <?= !empty($mom['highRisk']) ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200'; ?>">
                                                <?= !empty($mom['highRisk']) ? '⚠ HIGH RISK' : '✓ LOW RISK'; ?>
                                            </span>
                                            <p class="text-xs font-bold text-pink-700 mt-1.5">Next Visit: <?= esc($mom['nextVisit']); ?></p>
                                        </div>
                                    </div>

                                    <div class="bg-pink-50/60 p-3.5 rounded-xl border border-pink-100 text-xs">
                                        <p class="font-bold text-pink-900 mb-1">Risk Factors &amp; Clinical Health Plan:</p>
                                        <p class="text-pink-950 font-medium"><?= esc($mom['risks']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 3: FAMILY PLANNING REGISTRY -->
            <?php if ($tab === 'fp'): ?>
                <div class="space-y-4">
                    <details class="bg-white rounded-2xl border border-rose-200 p-4 shadow-sm">
                        <summary class="cursor-pointer font-extrabold text-rose-800">+ Register Family Planning Client</summary>
                        <form method="post" class="mt-4 grid gap-3 sm:grid-cols-2 text-xs">
                            <input type="hidden" name="action" value="save_family_planning">
                            <select required name="resident_id" class="rounded-xl border p-3"><option value="">Select resident</option><?php foreach ($allMothersList as $r): ?><option value="<?= (int)$r['id'] ?>"><?= esc($r['name']) ?> — <?= esc($r['barangay']) ?></option><?php endforeach; ?></select>
                            <select required name="contraceptive_method" class="rounded-xl border p-3"><option value="">Select method</option><?php foreach (['Pills','Injectable','Condom','IUD','Implant','Natural Family Planning','Permanent Method'] as $method): ?><option><?= esc($method) ?></option><?php endforeach; ?></select>
                            <select name="acceptor_type" class="rounded-xl border p-3"><option>New Acceptor</option><option>Continuing User</option><option>Changing Method</option><option>Restarting User</option></select>
                            <input required type="date" name="last_supply_date" value="<?= date('Y-m-d') ?>" class="rounded-xl border p-3">
                            <input type="date" name="next_visit_date" class="rounded-xl border p-3" aria-label="Next visit date">
                            <input name="clinical_notes" placeholder="Counseling or clinical notes" class="rounded-xl border p-3">
                            <button class="sm:col-span-2 rounded-xl bg-rose-700 p-3 font-bold text-white">Save Record &amp; Notify Resident</button>
                        </form>
                    </details>
                    <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">♥ Family Planning &amp; Reproductive Health Registry</h2>
                    <?php if (empty($fpClients)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-200/80 shadow-sm">
                            <span class="text-4xl block mb-2">💊</span>
                            <p class="text-sm font-bold text-gray-700">No Family Planning Clients Registered</p>
                            <p class="text-xs text-gray-400 mt-0.5">Contraceptive users and acceptors will be logged here.</p>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs min-w-[650px]">
                                    <thead class="bg-slate-50 text-slate-600 uppercase font-bold border-b border-gray-200">
                                        <tr>
                                            <th class="px-4 py-3.5 text-left">Client Name</th>
                                            <th class="px-4 py-3.5 text-left">Age</th>
                                            <th class="px-4 py-3.5 text-left">Barangay</th>
                                            <th class="px-4 py-3.5 text-left">Contraceptive Method</th>
                                            <th class="px-4 py-3.5 text-left">Acceptor Type</th>
                                            <th class="px-4 py-3.5 text-left">Last Supply Date</th>
                                            <th class="px-4 py-3.5 text-left">Next Visit Date</th>
                                            <th class="px-4 py-3.5 text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach ($fpClients as $fp): ?>
                                            <tr class="hover:bg-slate-50/80 transition-colors">
                                                <td class="px-4 py-3.5 font-extrabold text-gray-900"><?= esc($fp['name']); ?></td>
                                                <td class="px-4 py-3.5 text-gray-700 font-semibold"><?= esc($fp['age']); ?></td>
                                                <td class="px-4 py-3.5 font-semibold text-purple-900"><?= esc($fp['barangay']); ?></td>
                                                <td class="px-4 py-3.5 text-rose-900 font-extrabold"><?= esc($fp['method']); ?></td>
                                                <td class="px-4 py-3.5"><span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800"><?= esc($fp['acceptorType']); ?></span></td>
                                                <td class="px-4 py-3.5 text-gray-500 font-mono"><?= esc($fp['lastSupply']); ?></td>
                                                <td class="px-4 py-3.5 font-bold text-rose-600 font-mono"><?= esc($fp['nextVisit']); ?></td>
                                                <td class="px-4 py-3.5"><span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= strtolower($fp['status']) === 'overdue' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'; ?>"><?= esc($fp['status']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 4: IMMUNIZATION -->
            <?php if ($tab === 'immunization'): ?>
                <div class="space-y-4">
                    <details class="bg-white rounded-2xl border border-indigo-200 p-4 shadow-sm">
                        <summary class="cursor-pointer font-extrabold text-indigo-800">+ Record Administered Vaccine</summary>
                        <form method="post" class="mt-4 grid gap-3 sm:grid-cols-2 text-xs">
                            <input type="hidden" name="action" value="save_immunization">
                            <select required name="resident_id" class="rounded-xl border p-3"><option value="">Select resident/child</option><?php foreach ($allResidentsList as $r): ?><option value="<?= (int)$r['id'] ?>"><?= esc($r['name']) ?> — <?= esc($r['barangay']) ?></option><?php endforeach; ?></select>
                            <select required name="vaccine_id" class="rounded-xl border p-3"><option value="">Select vaccine</option><?php foreach ($vaccineSchedules as $v): ?><option value="<?= (int)$v['id'] ?>"><?= esc($v['vaccine_name']) ?> (<?= esc($v['age_group']) ?>)</option><?php endforeach; ?></select>
                            <input required type="date" name="vaccination_date" value="<?= date('Y-m-d') ?>" class="rounded-xl border p-3">
                            <input required name="batch_number" placeholder="Vaccine batch/lot number" class="rounded-xl border p-3">
                            <input name="site_of_injection" placeholder="Injection site" class="rounded-xl border p-3">
                            <input type="date" name="next_dose_date" class="rounded-xl border p-3" aria-label="Next dose date">
                            <input name="adverse_reactions" placeholder="Adverse reactions, if any" class="sm:col-span-2 rounded-xl border p-3">
                            <button class="sm:col-span-2 rounded-xl bg-indigo-700 p-3 font-bold text-white">Save Vaccination &amp; Notify Resident</button>
                        </form>
                    </details>
                    <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">🔬 Child Immunization &amp; Vaccine Cards</h2>
                    <?php if (empty($immunizationRecords)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-200/80 shadow-sm">
                            <span class="text-4xl block mb-2">👶</span>
                            <p class="text-sm font-bold text-gray-700">No Child Vaccination Records</p>
                            <p class="text-xs text-gray-400 mt-0.5">Administered vaccine doses will be logged here.</p>
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

            <!-- TAB 5: VITAL STATISTICS -->
            <?php if ($tab === 'vital'): ?>
                <div class="space-y-4">
                    <details class="bg-white rounded-2xl border border-emerald-200 p-4 shadow-sm">
                        <summary class="cursor-pointer font-extrabold text-emerald-800">+ Register Birth Record</summary>
                        <form method="post" class="mt-4 grid gap-3 sm:grid-cols-2 text-xs">
                            <input type="hidden" name="action" value="save_birth_record">
                            <select required name="mother_id" class="rounded-xl border p-3"><option value="">Select mother</option><?php foreach ($allMothersList as $r): ?><option value="<?= (int)$r['id'] ?>"><?= esc($r['name']) ?> — <?= esc($r['barangay']) ?></option><?php endforeach; ?></select>
                            <input required name="child_name" placeholder="Child's complete name" class="rounded-xl border p-3">
                            <input required type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" class="rounded-xl border p-3">
                            <input type="time" name="time_of_birth" class="rounded-xl border p-3">
                            <select name="gender" class="rounded-xl border p-3"><option value="">Gender</option><option>Female</option><option>Male</option></select>
                            <input name="father_name" placeholder="Father's name" class="rounded-xl border p-3">
                            <input name="place_of_birth" value="<?= esc($midwifeProfile['assigned_facility'] ?? 'Nasugbu RHU I') ?>" placeholder="Place of birth" class="rounded-xl border p-3">
                            <input name="birth_certificate_number" placeholder="Certificate number (auto if blank)" class="rounded-xl border p-3">
                            <input type="number" step="0.01" min="0" name="birth_weight_kg" placeholder="Weight (kg)" class="rounded-xl border p-3">
                            <input type="number" step="0.1" min="0" name="birth_length_cm" placeholder="Length (cm)" class="rounded-xl border p-3">
                            <button class="sm:col-span-2 rounded-xl bg-emerald-700 p-3 font-bold text-white">Register Birth &amp; Notify Mother</button>
                        </form>
                    </details>
                    <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">▤ Municipal Births &amp; Vital Statistics</h2>
                    <?php if (empty($vitalRecords)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-200/80 shadow-sm">
                            <span class="text-4xl block mb-2">👶</span>
                            <p class="text-sm font-bold text-gray-700">No Registered Birth Records</p>
                            <p class="text-xs text-gray-400 mt-0.5">Municipal birth registrations will display here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($vitalRecords as $vr): ?>
                                <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-200/80 flex items-center justify-between">
                                    <div>
                                        <p class="font-extrabold text-gray-900 text-sm sm:text-base"><?= esc($vr['name']); ?> <span class="text-xs text-gray-500 font-semibold">(Mother: <?= esc($vr['motherName']); ?>)</span></p>
                                        <p class="text-xs text-gray-600 mt-0.5">Date of Birth: <?= esc($vr['date']); ?> · Barangay: <?= esc($vr['barangay']); ?> · Weight: <?= esc($vr['weight']); ?></p>
                                        <p class="text-[11px] text-gray-400 mt-0.5">Birth Attendant: <?= esc($vr['attendant']); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"><?= esc($vr['registrationStatus']); ?></span>
                                        <p class="text-xs font-mono text-gray-400 mt-1"><?= esc($vr['lncrn']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 6: OB REFERRALS -->
            <?php if ($tab === 'referrals'): ?>
                <div class="space-y-4">
                    <details class="bg-white rounded-2xl border border-purple-200 p-4 shadow-sm">
                        <summary class="cursor-pointer font-extrabold text-purple-800">+ Create Maternal Referral</summary>
                        <form method="post" class="mt-4 grid gap-3 sm:grid-cols-2 text-xs">
                            <input type="hidden" name="action" value="save_referral">
                            <select required name="resident_id" class="rounded-xl border p-3"><option value="">Select expectant mother</option><?php foreach ($allMothersList as $r): ?><option value="<?= (int)$r['id'] ?>"><?= esc($r['name']) ?> — <?= esc($r['barangay']) ?></option><?php endforeach; ?></select>
                            <select name="urgency" class="rounded-xl border p-3"><option>Routine</option><option>Urgent</option><option>Emergency</option></select>
                            <input required name="diagnosis" placeholder="Clinical diagnosis" class="rounded-xl border p-3">
                            <input required name="referred_to" placeholder="Receiving hospital/facility" class="rounded-xl border p-3">
                            <textarea required name="referral_reason" rows="3" placeholder="Referral reason and clinical findings" class="sm:col-span-2 rounded-xl border p-3"></textarea>
                            <button class="sm:col-span-2 rounded-xl bg-purple-700 p-3 font-bold text-white">Create Referral &amp; Notify Resident</button>
                        </form>
                    </details>
                    <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">↗ High-Risk OB Hospital Referrals</h2>
                    <?php if (empty($referralsList)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-200/80 shadow-sm">
                            <span class="text-4xl block mb-2">🏥</span>
                            <p class="text-sm font-bold text-gray-700">No Active OB Hospital Referrals</p>
                            <p class="text-xs text-gray-400 mt-0.5">Emergency and tertiary hospital referral forms will be listed here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($referralsList as $ref): ?>
                                <div class="bg-white rounded-2xl p-5 shadow-sm border border-purple-200/80 space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-mono text-xs font-extrabold text-purple-700 bg-purple-50 px-2.5 py-1 rounded-lg border border-purple-200"><?= esc($ref['id']); ?></span>
                                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-200"><?= esc($ref['urgency']); ?></span>
                                    </div>
                                    <p class="font-extrabold text-gray-900 text-base"><?= esc($ref['patientName']); ?> (<?= esc($ref['age']); ?> y/o)</p>
                                    <p class="text-xs text-gray-700"><strong>Diagnosis:</strong> <?= esc($ref['diagnosis']); ?></p>
                                    <p class="text-xs text-purple-900"><strong>Referred To:</strong> <?= esc($ref['referredTo']); ?></p>
                                    <p class="text-xs text-gray-500 font-mono bg-slate-50 p-2.5 rounded-xl border border-slate-100"><strong>Reason:</strong> <?= esc($ref['reason']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 7: PRENATAL OPD CONSULTATIONS -->
            <?php if ($tab === 'opd'): ?>
                <div class="space-y-4">
                    <h2 class="text-base sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">📋 Routine Prenatal OPD Checkups</h2>
                    <?php if (empty($prenatalOPDList)): ?>
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-200/80 shadow-sm">
                            <span class="text-4xl block mb-2">📋</span>
                            <p class="text-sm font-bold text-gray-700">No Routine Prenatal Checkups Found</p>
                            <p class="text-xs text-gray-400 mt-0.5">Routine OPD prenatal notes will be listed here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($prenatalOPDList as $opd): ?>
                                <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-200/80 space-y-2.5">
                                    <div class="flex items-center justify-between">
                                        <p class="font-extrabold text-gray-900 text-base"><?= esc($opd['patientName']); ?> <span class="text-xs font-semibold text-gray-500">(<?= esc($opd['age'] ?? 'N/A'); ?>y / <?= esc($opd['gender']); ?>)</span></p>
                                        <span class="font-mono text-xs bg-pink-100 text-pink-900 font-bold px-2.5 py-1 rounded-lg border border-pink-200"><?= esc($opd['icd10'] ?: 'Z34.8'); ?></span>
                                    </div>
                                    <p class="text-xs text-gray-700"><strong>Chief Complaint:</strong> <?= esc($opd['chiefComplaint']); ?></p>
                                    <div class="text-xs text-gray-600 font-mono bg-slate-50 p-3 rounded-xl border border-slate-200/60"><?= esc($opd['consultation_notes']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'certificates'): ?>
                <?= portalRenderCertificateIssuancePanel($pdo, $allResidentsList, $midwifeCertificateTypes, $loggedInStaffId, 'pink') ?>
            <?php endif; ?>
        </main>

        <!-- MOBILE BOTTOM TAB BAR -->
        <nav class="sm:hidden fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-200 safe-area-pb shadow-2xl">
            <div class="flex items-stretch">
                <?php foreach ($tabs as $id => [$label, $icon]): ?>
                    <a href="<?= esc(tabUrl($id)); ?>"
                        class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-semibold transition-colors relative <?= $tab === $id ? 'text-pink-700 font-extrabold' : 'text-gray-400'; ?>">
                        <?php if ($tab === $id): ?>
                            <span class="absolute top-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-pink-600 rounded-full"></span>
                        <?php endif; ?>
                        <span class="text-base leading-none"><?= $icon; ?></span>
                        <span class="truncate px-0.5"><?= esc($label); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>

    </div>

    <!-- MODAL: REGISTER NEW MATERNAL PRENATAL CASE -->
    <?php if ($modal === 'new_maternal'): ?>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-end sm:items-center justify-center z-50 p-3 sm:p-4">
            <div class="bg-white rounded-3xl shadow-2xl w-full sm:max-w-xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95">
                <div class="p-5 border-b flex items-center justify-between bg-gradient-to-r from-pink-800 to-rose-800 text-white rounded-t-3xl shrink-0">
                    <div>
                        <h2 class="text-base font-extrabold flex items-center gap-2">👶 Register New Prenatal Maternal Case</h2>
                        <p class="text-xs text-pink-200">Log pregnancy tracking, LMP, and EDC calculation</p>
                    </div>
                    <a href="<?= esc(tabUrl('maternal')); ?>" class="text-pink-100 hover:text-white text-lg font-bold w-8 h-8 rounded-full bg-white/10 flex items-center justify-center">✕</a>
                </div>
                <form class="p-5 space-y-4 text-xs overflow-y-auto" method="post">
                    <input type="hidden" name="action" value="save_maternal">

                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Select Expectant Mother *</label>
                        <select id="maternal-resident-select" name="resident_id" required class="w-full p-3 border border-gray-300 rounded-xl text-sm font-semibold focus:border-pink-500 focus:ring-2 focus:ring-pink-200 outline-none">
                            <option value="">-- Select Female Resident --</option>
                            <option value="new">+ Type and register a new mother</option>
                            <?php foreach ($allMothersList as $m): ?>
                                <option value="<?= esc($m['id']); ?>"><?= esc($m['name']); ?> (<?= esc($m['barangay']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="new-mother-fields" class="hidden rounded-2xl border border-teal-200 bg-teal-50/60 p-4">
                        <p class="mb-3 font-extrabold text-teal-900">New Mother Information</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input name="new_first_name" data-new-mother-required placeholder="First name *" class="rounded-xl border border-gray-300 p-3">
                            <input name="new_middle_name" placeholder="Middle name" class="rounded-xl border border-gray-300 p-3">
                            <input name="new_last_name" data-new-mother-required placeholder="Last name *" class="rounded-xl border border-gray-300 p-3">
                            <input type="date" name="new_date_of_birth" data-new-mother-required max="<?= date('Y-m-d') ?>" class="rounded-xl border border-gray-300 p-3" aria-label="Date of birth">
                            <input name="new_barangay" data-new-mother-required placeholder="Barangay *" class="rounded-xl border border-gray-300 p-3">
                            <input name="new_contact_number" placeholder="Contact number" class="rounded-xl border border-gray-300 p-3">
                            <input name="new_address" placeholder="Complete address" class="rounded-xl border border-gray-300 p-3 sm:col-span-2">
                        </div>
                        <p class="mt-2 text-[10px] text-teal-700">A resident record will be created and linked automatically to this prenatal case.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Gravida (Total Pregnancies)</label>
                            <input type="number" name="gravida" value="1" min="1" class="w-full p-3 border border-gray-300 rounded-xl text-sm font-bold">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-700 mb-1">Para (Total Deliveries)</label>
                            <input type="number" name="para" value="0" min="0" class="w-full p-3 border border-gray-300 rounded-xl text-sm font-bold">
                        </div>
                    </div>

                    <div class="bg-pink-50/60 p-4 rounded-2xl border border-pink-100 space-y-3">
                        <p class="font-extrabold text-pink-900 text-xs">Maternal Timeline & Dates</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Last Menstrual Period (LMP) *</label>
                                <input type="date" name="lmp" required value="<?= date('Y-m-d', strtotime('-3 months')); ?>" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs font-mono font-bold text-gray-800">
                            </div>
                            <div>
                                <label class="block font-bold text-gray-700 mb-1">Expected EDC *</label>
                                <input type="date" name="edc" required value="<?= date('Y-m-d', strtotime('+6 months')); ?>" class="w-full p-2.5 border border-gray-300 rounded-xl text-xs font-mono font-bold text-gray-800">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 p-3.5 bg-red-50 rounded-2xl border border-red-200">
                        <input type="checkbox" name="high_risk" value="1" id="high_risk_chk" class="w-4 h-4 text-red-600 rounded">
                        <label for="high_risk_chk" class="font-extrabold text-red-900 cursor-pointer text-xs">Classify as HIGH-RISK Pregnancy</label>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 mb-1">Risk Factors &amp; Clinical Health Plan</label>
                        <textarea name="risk_factors" rows="3" class="w-full p-3 border border-gray-300 rounded-xl text-xs resize-none focus:border-pink-500 focus:ring-2 focus:ring-pink-200 outline-none" placeholder="e.g., Gestational hypertension, previous C-section, teenage pregnancy"></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="<?= esc(tabUrl('maternal')); ?>" class="flex-1 py-3 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-3 bg-pink-700 text-white rounded-xl text-xs font-extrabold hover:bg-pink-800 shadow-md transition-all">Save Prenatal Maternal Case</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?= portalRenderNotificationPanel(); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const residentSelect = document.getElementById('maternal-resident-select');
    const newMotherFields = document.getElementById('new-mother-fields');
    if (!residentSelect || !newMotherFields) return;
    const updateNewMotherFields = () => {
        const isNew = residentSelect.value === 'new';
        newMotherFields.classList.toggle('hidden', !isNew);
        newMotherFields.querySelectorAll('[data-new-mother-required]').forEach(input => {
            input.required = isNew;
        });
    };
    residentSelect.addEventListener('change', updateNewMotherFields);
    updateNewMotherFields();
});
</script>
</body>

</html>
