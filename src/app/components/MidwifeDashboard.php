<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
$stType = strtoupper((string) ($_SESSION['rhu_staff_login']['staff_type'] ?? ''));
if (empty($_SESSION['rhu_staff_login']) || ($stType !== 'MIDWIFE' && !str_contains($stType, 'MIDWIFE'))) {
    header('Location: RHULogin.php');
    exit;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/portal.php';
portalHandleNotificationApi($pdo);

function esc(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}


function iconSvg(string $name, string $class = 'w-5 h-5'): string
{
    $icons = [
        'menu' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
        'logout' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/><path d="M13 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7"/></svg>',
        'close' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
        'shield' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function tabUrl(string $tab, array $extra = []): string
{
    return '?' . http_build_query(array_merge(['tab' => $tab], $extra));
}

$tabs = [
    'overview' => ['Overview', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'],
    'maternal' => ['Maternal Health', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M9 12h.01M15 12h.01"/><path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5"/><path d="M12 3a9 9 0 1 0 9 9"/></svg>'],
    'fp' => ['Family Planning', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>'],
    'opd' => ['Prenatal OPD', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>'],
    'referrals' => ['Referrals', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" x2="12" y1="2" y2="15"/></svg>'],
    'immunization' => ['Immunization', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="m18 2 4 4"/><path d="m17 7 3-3"/><path d="M19 9 8.7 19.3c-1 1-2.5 1-3.4 0l-.6-.6c-1-1-1-2.5 0-3.4L15 5"/><path d="m9 11 4 4"/><path d="m5 19-3 3"/><path d="m14 4 6 6"/></svg>'],
    'vital' => ['Vital Statistics', '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>']
];

$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab]))
    $tab = 'overview';

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
        try {
            $pdo->exec($midwifeTableSql);
        } catch (Throwable $ignored) {
        }
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

$loggedInStaffId = (int) ($_SESSION['rhu_staff_login']['staff_id'] ?? 0);
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
                'id' => (int) $pdo->lastInsertId(),
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
        header('Location: ' . tabUrl('certificates'));
        exit;
    }

    // Action: Answer / Update Resident Consultation
    if ($action === 'answer_consultation') {
        $cslId = (int) ($_POST['consultation_id'] ?? 0);
        $resId = (int) ($_POST['resident_id'] ?? 0);
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
        $residentSelection = (string) ($_POST['resident_id'] ?? '');
        $isNewMother = $residentSelection === 'new';
        $residentId = $isNewMother ? 0 : (int) $residentSelection;
        $gravida = (int) ($_POST['gravida'] ?? 1);
        $para = (int) ($_POST['para'] ?? 0);
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
                    $duplicateStmt->execute(['first_name' => $newFirstName, 'last_name' => $newLastName, 'dob' => $newDob]);
                    $residentId = (int) ($duplicateStmt->fetchColumn() ?: 0);
                    if ($residentId <= 0) {
                        $newResidentStmt = $pdo->prepare("INSERT INTO residents
                            (first_name, middle_name, last_name, date_of_birth, gender, contact_number, address, barangay, is_active, created_at, updated_at)
                            VALUES (:first_name, :middle_name, :last_name, :dob, 'Female', :contact, :address, :barangay, 1, NOW(), NOW())");
                        $newResidentStmt->execute([
                            'first_name' => $newFirstName,
                            'middle_name' => $newMiddleName !== '' ? $newMiddleName : null,
                            'last_name' => $newLastName,
                            'dob' => $newDob,
                            'contact' => $newContact !== '' ? $newContact : null,
                            'address' => $newAddress !== '' ? $newAddress : $newBarangay,
                            'barangay' => $newBarangay
                        ]);
                        $residentId = (int) $pdo->lastInsertId();
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
                        ->execute(['id' => (int) $midwifeProfile['id']]);
                    $midwifeProfile['cases_assisted'] = (int) $midwifeProfile['cases_assisted'] + 1;
                }
                $pdo->commit();
                portalNotifyResident($pdo, $residentId, "Your maternal health & prenatal tracking record (EDC: {$edc}) has been updated by Rural Health Midwife.", "ResidentDashboard.php?tab=history");
                $_SESSION['midwife_flash_success'] = 'New Maternal Prenatal record saved successfully into database!';
            } catch (Exception $e) {
                if ($pdo->inTransaction())
                    $pdo->rollBack();
                $_SESSION['midwife_flash_error'] = 'Database Error: ' . $e->getMessage();
            }
        }
        header('Location: ' . tabUrl('maternal'));
        exit;
    }

    if ($action === 'save_family_planning') {
        $residentId = (int) ($_POST['resident_id'] ?? 0);
        $method = trim($_POST['contraceptive_method'] ?? '');
        $acceptorType = trim($_POST['acceptor_type'] ?? 'New Acceptor');
        $supplyDate = trim($_POST['last_supply_date'] ?? date('Y-m-d'));
        $nextVisit = trim($_POST['next_visit_date'] ?? '');
        $notes = trim($_POST['clinical_notes'] ?? '');
        try {
            if ($residentId <= 0 || $method === '')
                throw new RuntimeException('Select a resident and contraceptive method.');
            $stmt = $pdo->prepare("INSERT INTO family_planning_records
                (resident_id, contraceptive_method, acceptor_type, last_supply_date, next_visit_date, status, clinical_notes, healthcare_provider_id)
                VALUES (:resident, :method, :acceptor, :supply, :next_visit, 'Active', :notes, :provider)");
            $stmt->execute([
                'resident' => $residentId,
                'method' => $method,
                'acceptor' => $acceptorType,
                'supply' => $supplyDate,
                'next_visit' => $nextVisit !== '' ? $nextVisit : null,
                'notes' => $notes,
                'provider' => $loggedInStaffId ?: null
            ]);
            portalNotifyResident($pdo, $residentId, "Your family planning record was updated. Method: {$method}. Next visit: " . ($nextVisit ?: 'To be scheduled') . '.', 'ResidentDashboard.php?tab=records');
            $_SESSION['midwife_flash_success'] = 'Family planning client record saved.';
        } catch (Throwable $e) {
            $_SESSION['midwife_flash_error'] = 'Family Planning Error: ' . $e->getMessage();
        }
        header('Location: ' . tabUrl('fp'));
        exit;
    }

    if ($action === 'save_immunization') {
        $residentId = (int) ($_POST['resident_id'] ?? 0);
        $vaccineId = (int) ($_POST['vaccine_id'] ?? 0);
        $dateGiven = trim($_POST['vaccination_date'] ?? date('Y-m-d'));
        $nextDose = trim($_POST['next_dose_date'] ?? '');
        $batch = trim($_POST['batch_number'] ?? '');
        try {
            if ($residentId <= 0 || $vaccineId <= 0 || $batch === '')
                throw new RuntimeException('Resident, vaccine, and batch number are required.');
            $stmt = $pdo->prepare("INSERT INTO vaccination_records
                (resident_id, vaccine_id, vaccination_date, healthcare_provider_id, batch_number, site_of_injection, adverse_reactions, next_dose_date)
                VALUES (:resident, :vaccine, :given, :provider, :batch, :site, :reaction, :next_dose)");
            $stmt->execute([
                'resident' => $residentId,
                'vaccine' => $vaccineId,
                'given' => $dateGiven,
                'provider' => $loggedInStaffId ?: null,
                'batch' => $batch,
                'site' => trim($_POST['site_of_injection'] ?? ''),
                'reaction' => trim($_POST['adverse_reactions'] ?? ''),
                'next_dose' => $nextDose !== '' ? $nextDose : null
            ]);
            portalNotifyResident($pdo, $residentId, "A vaccination was recorded on {$dateGiven}. Next dose: " . ($nextDose ?: 'Not required') . '.', 'ResidentDashboard.php?tab=immunization');
            $_SESSION['midwife_flash_success'] = 'Immunization record saved and resident notified.';
        } catch (Throwable $e) {
            $_SESSION['midwife_flash_error'] = 'Immunization Error: ' . $e->getMessage();
        }
        header('Location: ' . tabUrl('immunization'));
        exit;
    }

    if ($action === 'save_birth_record') {
        $motherId = (int) ($_POST['mother_id'] ?? 0);
        $childName = trim($_POST['child_name'] ?? '');
        $birthDate = trim($_POST['date_of_birth'] ?? '');
        try {
            if ($motherId <= 0 || $childName === '' || $birthDate === '')
                throw new RuntimeException('Mother, child name, and birth date are required.');
            $certificateNo = trim($_POST['birth_certificate_number'] ?? '') ?: 'BR-' . date('YmdHis');
            $stmt = $pdo->prepare("INSERT INTO vital_statistics_births
                (birth_certificate_number, child_name, date_of_birth, time_of_birth, place_of_birth, mother_id, father_name, gender, birth_weight_kg, birth_length_cm, delivery_attendant_id, registered_date)
                VALUES (:certificate, :child, :birth_date, :birth_time, :place, :mother, :father, :gender, :weight, :length, :attendant, CURDATE())");
            $stmt->execute([
                'certificate' => $certificateNo,
                'child' => $childName,
                'birth_date' => $birthDate,
                'birth_time' => ($_POST['time_of_birth'] ?? '') ?: null,
                'place' => trim($_POST['place_of_birth'] ?? '') ?: ($midwifeProfile['assigned_facility'] ?? 'Nasugbu RHU I'),
                'mother' => $motherId,
                'father' => trim($_POST['father_name'] ?? ''),
                'gender' => trim($_POST['gender'] ?? ''),
                'weight' => ($_POST['birth_weight_kg'] ?? '') !== '' ? $_POST['birth_weight_kg'] : null,
                'length' => ($_POST['birth_length_cm'] ?? '') !== '' ? $_POST['birth_length_cm'] : null,
                'attendant' => $loggedInStaffId ?: null
            ]);
            if (!empty($midwifeProfile['id'])) {
                $pdo->prepare('UPDATE midwife SET cases_assisted = cases_assisted + 1 WHERE id = :id')
                    ->execute(['id' => (int) $midwifeProfile['id']]);
                $midwifeProfile['cases_assisted'] = (int) $midwifeProfile['cases_assisted'] + 1;
            }
            portalNotifyResident($pdo, $motherId, "Birth record {$certificateNo} for {$childName} was registered.", 'ResidentDashboard.php?tab=family');
            $_SESSION['midwife_flash_success'] = 'Birth and vital-statistics record registered.';
        } catch (Throwable $e) {
            $_SESSION['midwife_flash_error'] = 'Vital Statistics Error: ' . $e->getMessage();
        }
        header('Location: ' . tabUrl('vital'));
        exit;
    }

    if ($action === 'save_referral') {
        $residentId = (int) ($_POST['resident_id'] ?? 0);
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $facility = trim($_POST['referred_to'] ?? '');
        $reason = trim($_POST['referral_reason'] ?? '');
        try {
            if ($residentId <= 0 || $diagnosis === '' || $facility === '' || $reason === '')
                throw new RuntimeException('Complete all required referral details.');
            $pregnancyStmt = $pdo->prepare("SELECT id FROM pregnancies WHERE resident_id = :resident AND pregnancy_status = 'Active' ORDER BY id DESC LIMIT 1");
            $pregnancyStmt->execute(['resident' => $residentId]);
            $pregnancyId = $pregnancyStmt->fetchColumn() ?: null;
            $stmt = $pdo->prepare("INSERT INTO maternal_referrals
                (resident_id, pregnancy_id, diagnosis, referred_to, referral_reason, urgency, referral_status, referred_by_id, referral_date)
                VALUES (:resident, :pregnancy, :diagnosis, :facility, :reason, :urgency, 'Pending', :provider, CURDATE())");
            $stmt->execute([
                'resident' => $residentId,
                'pregnancy' => $pregnancyId,
                'diagnosis' => $diagnosis,
                'facility' => $facility,
                'reason' => $reason,
                'urgency' => trim($_POST['urgency'] ?? 'Routine'),
                'provider' => $loggedInStaffId ?: null
            ]);
            portalNotifyResident($pdo, $residentId, "Maternal referral created for {$facility}. Diagnosis: {$diagnosis}.", 'ResidentDashboard.php?tab=records');
            $_SESSION['midwife_flash_success'] = 'Maternal referral created and resident notified.';
        } catch (Throwable $e) {
            $_SESSION['midwife_flash_error'] = 'Referral Error: ' . $e->getMessage();
        }
        header('Location: ' . tabUrl('referrals'));
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
        $midwifeCertificateTypes = portalEnsureCertificateTypes($pdo, ['Prenatal Care Certificate', 'Maternal Health Certificate', 'Birth Attendance Certificate', 'Family Planning Counseling Certificate']);
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
        $midwifeUserId = (int) ($_SESSION['rhu_staff_login']['id'] ?? 0);

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
    <title>Midwife Portal RHU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html {
            scroll-behavior: auto;
        }

        body.rhu-midwife-ui {
  overflow: hidden;
  background: #f3f6f4;
  color: #0f172a;
  height: 100vh;
  height: 100dvh;
}

        .midwife-sidebar {
  width: 14rem;
  background: #fff;
  border-right: 1px solid #e5ebe7;
  display: flex;
  flex-direction: column;
  height: 100vh;
  height: 100dvh;
  position: sticky;
  top: 0;
  z-index: 30;
  flex-shrink: 0;
  overflow: hidden;
}

        .midwife-sidebar-brand {
            position: relative;
            overflow: hidden;
            min-height: 4.75rem;
            padding: 0.9rem 1rem;
            flex-shrink: 0;
        }

        .midwife-sidebar-brand .brand-bg {
            position: absolute;
            inset: 0;
            background-image: url('../../../assets/admin-municipal-background.png');
            background-size: cover;
            background-position: center;
            filter: saturate(1.2) brightness(0.52);
        }

        .midwife-sidebar-brand .brand-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(100deg, rgba(13, 53, 24, 0.92) 0%, rgba(23, 63, 45, 0.82) 55%, rgba(47, 111, 73, 0.7) 100%);
        }

        .admin-shell-header {
            background: #0b3c35;
            border-bottom: 1px solid rgba(167, 243, 208, .22);
            box-shadow: 0 10px 28px rgba(2, 28, 23, 0.18);
            min-height: 4rem;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .admin-shell-header.is-scrolled {
            box-shadow: 0 14px 32px -18px rgba(15, 23, 42, 0.65);
        }

        .nav-section-label {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #94a3b8;
            padding: 0.85rem 1.1rem 0.35rem;
        }

        .nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0.1rem 0.65rem;
  padding: 0.65rem 0.85rem;
  border-radius: 0.85rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #475569;
  transition: background .15s ease, color .15s ease;
}

        .nav-item:hover {
  background: #f0fdf6;
  color: #0f766e;
}

        .nav-item.is-active {
            background: #e8f8ef;
            color: #0b3c35;
            font-weight: 800;
            box-shadow: inset 0 0 0 1px #c6ebd4;
        }

        .nav-item.is-active svg {
            color: #0f766e;
        }

        .midwife-main-wrap {
  position: relative;
  flex: 1;
  min-width: 0;
  min-height: 0;
  height: 100vh;
  height: 100dvh;
  display: flex;
  flex-direction: column;
  background: #f3f6f4;
  isolation: isolate;
  overflow: hidden;
}

        .midwife-main-wrap::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('../../../assets/admin-municipal-background.png');
            background-size: cover;
            background-position: center top;
            opacity: 0.06;
            pointer-events: none;
            z-index: 0;
        }

        .midwife-main-wrap>* {
            position: relative;
            z-index: 1;
        }

        .midwife-main-wrap>.admin-shell-header,
        .midwife-main-wrap>header.admin-shell-header {
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .midwife-main-wrap > main {
  z-index: 1;
  position: relative;
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
  -webkit-overflow-scrolling: touch;
}

        .dashboard-card {
  background: rgba(255,255,255,0.95);
  transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}

        .dashboard-card:hover {
  border-color: #a7e0bc;
  box-shadow: 0 6px 18px rgba(11, 60, 53, 0.08);
  transform: translateY(-1px);
}

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #0d9488 !important;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.14) !important;
        }

        @media (max-width: 1023px) {
            .midwife-sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                z-index: 60;
                height: 100vh;
                transform: translateX(-105%);
                transition: transform .2s ease;
                box-shadow: 12px 0 40px rgba(15, 23, 42, .18);
            }

            .midwife-sidebar.is-open {
                transform: translateX(0);
            }

            .sidebar-backdrop {
                position: fixed;
                inset: 0;
                z-index: 50;
                background: rgba(2, 6, 23, .42);
                opacity: 0;
                pointer-events: none;
                transition: opacity .15s ease;
            }

            .sidebar-backdrop.is-open {
                opacity: 1;
                pointer-events: auto;
            }

            body.drawer-open {
                overflow: hidden;
            }
        }

        @media (min-width: 1024px) {
            .sidebar-backdrop {
                display: none !important;
            }

            .midwife-sidebar {
                transform: none !important;
            }
        }

        @media (prefers-reduced-motion: reduce) {
  .dashboard-card:hover { transform: none; }
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
  }
}
        }
    
/* subtle interactive — not OA */
a.bg-teal-600:hover,
button.bg-teal-600:hover {
  filter: brightness(1.05);
}
a.bg-teal-600,
button.bg-teal-600,
button.bg-teal-700 {
  transition: background-color .15s ease, filter .15s ease, box-shadow .15s ease;
}
</style>
    <link rel="stylesheet" href="dashboard-enhancements.css">
    <!-- dashboard-enhancements.js disabled: reduced motion -->
</head>

<body class="rhu-midwife-ui antialiased">
    <div class="flex h-screen max-h-screen overflow-hidden">
        <div data-drawer-backdrop class="sidebar-backdrop lg:hidden" aria-hidden="true"></div>

        <aside id="midwife-sidebar" data-feature-drawer class="midwife-sidebar shrink-0"
            aria-label="Midwife navigation">
            <div class="midwife-sidebar-brand">
                <div class="brand-bg" aria-hidden="true"></div>
                <div class="brand-overlay" aria-hidden="true"></div>
                <div class="relative z-10 flex items-center gap-3">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/30 bg-white shadow-md overflow-hidden">
                        <img src="../../../assets/nasugbu_seal.png" alt="Nasugbu Seal" class="h-10 w-10 object-contain"
                            onerror="this.onerror=null;this.src='nasugbu_seal.png';" />
                    </span>
                    <div class="min-w-0 text-white">
                        <p class="text-[14px] font-black leading-tight tracking-tight drop-shadow-sm">RURAL HEALTH UNIT
                        </p>
                        <p class="text-[11px] font-semibold text-white/90 truncate">
                            <?= esc($tabs[$tab][0] ?? 'Overview') ?></p>
                    </div>
                </div>
                <button type="button" data-drawer-close
                    class="absolute top-2.5 right-2.5 z-10 grid h-8 w-8 place-items-center rounded-full border border-white/25 bg-white/10 text-white lg:hidden"
                    aria-label="Close menu"><?= iconSvg('close', 'w-4 h-4') ?></button>
            </div>

            <nav class="flex-1 overflow-y-auto py-2 min-h-0">
                <?php
                $drawerGroups = [
                    'Dashboard' => ['overview'],
                    'Maternal Care' => ['maternal', 'fp', 'opd', 'referrals'],
                    'Child & Vitals' => ['immunization', 'vital'],
                ];
                foreach ($drawerGroups as $groupLabel => $groupTabs):
                    $visible = array_values(array_filter($groupTabs, fn($k) => isset($tabs[$k])));
                    if (!$visible)
                        continue;
                    ?>
                    <p class="nav-section-label"><?= esc($groupLabel) ?></p>
                    <?php foreach ($visible as $id):
                        [$label, $icon] = $tabs[$id];
                        $active = $tab === $id;
                        ?>
                        <a href="<?= esc(tabUrl($id)) ?>" class="nav-item <?= $active ? 'is-active' : '' ?>">
                            <span class="shrink-0 opacity-90"><?= $icon ?></span>
                            <span class="truncate flex-1"><?= esc($label) ?></span>
                            <?php if ($active): ?><span class="text-teal-700 text-sm font-black">→</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </nav>

            <div class="border-t border-slate-100 p-3 shrink-0">
                <a href="StaffLogout.php" data-staff-logout
                    class="nav-item text-slate-500 hover:bg-rose-50 hover:text-rose-700">
                    <?= iconSvg('logout', 'w-5 h-5') ?>
                    <span>Log Out</span>
                </a>
            </div>
        </aside>

        <div class="midwife-main-wrap">
            <header class="admin-shell-header dashboard-header sticky top-0 z-50 text-[#f4faf7]">
                <div class="flex h-20 items-center justify-between gap-3 px-4 sm:px-5">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <button type="button" data-drawer-open
                            class="lg:hidden flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-white/15 text-white/95 hover:bg-white/10"
                            aria-label="Open menu" aria-expanded="false">
                            <?= iconSvg('menu', 'w-4 h-4') ?>
                        </button>
                        <div class="flex items-center gap-2">
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-full border border-[#e8f3d8]/80 bg-[#dfeecb] text-[#0b3b2f]">
                                <?= iconSvg('shield', 'w-3.5 h-3.5') ?>
                            </span>
                            <span class="text-[11px] font-black uppercase tracking-[0.16em] text-[#f5f5f2]">Midwife
                                Panel</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-2.5">
                        <?php if (function_exists('portalRenderNotificationButton')) {
                            echo portalRenderNotificationButton();
                        } ?>
                        <div
                            class="flex items-center gap-2 rounded-full border border-[#dbeadf]/20 bg-white/10 pl-1 pr-2.5 py-1">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-[#dceec4] text-[13px] font-black text-[#0b3b2f]">
                                <?= esc(strtoupper(substr($_SESSION['rhu_staff_login']['name'] ?? 'M', 0, 1))) ?>
                            </div>
                            <div class="hidden sm:block text-left leading-tight pr-1">
                                <p class="text-[12px] font-bold text-white">
                                    <?= esc($_SESSION['rhu_staff_login']['name'] ?? 'Rural Health Midwife') ?></p>
                                <p class="text-[9px] font-semibold uppercase tracking-wider text-[#cfe5d8]">Midwife</p>
                            </div>
                        </div>
                        <a href="StaffLogout.php" data-staff-logout
                            class="inline-flex h-9 items-center gap-1.5 rounded-full border border-white/20 bg-[#f3faf4] px-3 text-xs font-bold text-[#0c3a32] hover:bg-white transition">
                            <?= iconSvg('logout', 'w-3.5 h-3.5') ?>
                            <span class="hidden sm:inline">Log out</span>
                        </a>
                    </div>
                </div>
            </header>

            <main class="flex-1 mx-auto w-full max-w-7xl p-4 sm:p-6 space-y-5 pb-6">


                <?php if ($flashSuccess): ?>
                    <div
                        class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-semibold text-emerald-800 flex items-center gap-2 shadow-sm">
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><svg
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
                                            class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path
                                                d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                                            <path d="M12 9v4" />
                                            <path d="M12 17h.01" />
                                        </svg></span>
                                    <div>
                                        <p class="font-extrabold text-base sm:text-lg tracking-tight">High-Risk Pregnancy Alert
                                            —
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
                                    <?= $activePrenatalCount; ?>
                                </p>
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
                                    <?= $highRiskCount; ?>
                                </p>
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
                                    <?= $postpartumCount; ?>
                                </p>
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
                                    <?= count($fpClients); ?>
                                </p>
                                <p class="text-xs font-bold text-slate-700 mt-1">Family Planning Clients</p>
                                <p class="text-[11px] text-slate-400 font-medium">Contraceptive Supply</p>
                            </a>
                        </div>
                        <div class="dashboard-card bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                                <div>
                                    <h3 class="font-bold text-slate-800 text-base flex items-center gap-2"><span
                                            class="w-2.5 h-2.5 rounded-full bg-teal-500 animate-pulse"></span> Received
                                        Resident
                                        Prenatal Consultations</h3>
                                    <p class="text-xs text-slate-500 font-medium">Live consultation requests for Midwifery
                                        &amp;
                                        Prenatal Care</p>
                                </div>
                                <a href="<?= esc(tabUrl('maternal', ['modal' => 'new_maternal'])); ?>"
                                    class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 transition-all flex items-center gap-1.5"><svg
                                        class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 12h14" />
                                        <path d="M12 5v14" />
                                    </svg> New Prenatal Case</a>
                            </div>
                            <?php if (empty($prenatalOPDList)): ?>
                                <div class="text-center py-10 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                                    <span
                                        class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg
                                            class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
                                            <path
                                                d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
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
                                        <div
                                            class="bg-gradient-to-r from-slate-50/80 to-white rounded-xl p-4 border border-gray-200/80 hover:border-pink-200 transition-all space-y-3">
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="font-bold text-slate-800 text-sm sm:text-base">
                                                            <?= esc($opd['patientName']); ?>
                                                        </p>
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
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <path d="M8 2v4" />
                                                            <path d="M16 2v4" />
                                                            <rect width="18" height="18" x="3" y="4" rx="2" />
                                                            <path d="M3 10h18" />
                                                        </svg> <?= esc($opd['date']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div
                                                class="text-xs text-gray-600 font-mono bg-white p-2.5 rounded-xl border border-gray-200/60">
                                                <?= esc($opd['consultation_notes']); ?>
                                            </div>

                                            <!-- MIDWIFE RESPONSE / UPDATE FORM -->
                                            <details class="group border-t border-pink-100 pt-2" open>
                                                <summary
                                                    class="cursor-pointer text-xs font-bold text-pink-700 hover:text-pink-900 flex items-center justify-between py-1">
                                                    <span>💬 Answer / Update Consultation Response for Resident</span>
                                                    <span
                                                        class="text-[10px] bg-pink-100 text-pink-800 font-extrabold px-2 py-0.5 rounded-md">Status:
                                                        <?= esc($opd['consultation_status']); ?></span>
                                                </summary>
                                                <form method="post"
                                                    class="mt-2 bg-pink-50/50 p-3 rounded-xl border border-pink-200/70 space-y-2.5">
                                                    <input type="hidden" name="action" value="answer_consultation">
                                                    <input type="hidden" name="consultation_id" value="<?= (int) $opd['id']; ?>">
                                                    <input type="hidden" name="resident_id"
                                                        value="<?= (int) ($opd['resident_id'] ?? 0); ?>">

                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                        <div>
                                                            <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Clinical
                                                                Diagnosis / Assessment</label>
                                                            <input type="text" name="diagnosis"
                                                                value="<?= esc($opd['diagnosis'] ?? ''); ?>"
                                                                placeholder="e.g. Normal Pregnancy 16 weeks AOG"
                                                                class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-pink-500 bg-white"
                                                                required>
                                                        </div>
                                                        <div>
                                                            <label
                                                                class="block text-[11px] font-bold text-gray-700 mb-0.5">Consultation
                                                                Status</label>
                                                            <select name="consultation_status"
                                                                class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-teal-500 bg-white font-bold text-teal-900">
                                                                <option value="Completed" <?= ($opd['consultation_status'] ?? '') === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                                <option value="In Progress" <?= ($opd['consultation_status'] ?? '') === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                                <option value="Scheduled" <?= ($opd['consultation_status'] ?? '') === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                                <option value="Referred" <?= ($opd['consultation_status'] ?? '') === 'Referred' ? 'selected' : ''; ?>>Referred to Doctor
                                                                </option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="block text-[11px] font-bold text-gray-700 mb-0.5">Midwife
                                                            Notes &amp; Advice for Resident</label>
                                                        <textarea name="consultation_notes" rows="2"
                                                            placeholder="Enter prenatal advice, diet recommendations, and next visit schedule..."
                                                            class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-teal-500 bg-white resize-none"><?= esc($opd['consultation_notes'] ?? ''); ?></textarea>
                                                    </div>

                                                    <div>
                                                        <label
                                                            class="block text-[11px] font-bold text-gray-700 mb-0.5">Prescriptions /
                                                            Supplements</label>
                                                        <input type="text" name="medications_prescribed"
                                                            value="<?= esc($opd['medications'] ?? ''); ?>"
                                                            placeholder="e.g. Ferrous Sulfate + Folic Acid 1 tab OD"
                                                            class="w-full p-2 border border-gray-300 rounded-lg text-xs outline-none focus:border-pink-500 bg-white">
                                                    </div>

                                                    <div class="flex justify-end pt-1">
                                                        <button type="submit"
                                                            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-extrabold rounded-lg shadow-sm transition-all flex items-center gap-1">
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

                <?php if ($tab === 'maternal'): ?>
                    <div class="space-y-4">
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg
                                        class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3">
                                            <div>
                                                <p class="font-bold text-slate-800 text-base"><?= esc($mom['name']); ?> <span
                                                        class="text-xs text-slate-500 font-medium">(<?= esc($mom['age']); ?>
                                                        y/o<?= $gpTag; ?>)</span></p>
                                                <p class="text-xs text-slate-600 font-medium mt-0.5">Barangay:
                                                    <?= esc($mom['barangay']); ?> · Blood Type: <span
                                                        class="text-rose-700 font-bold bg-rose-50 px-2 py-0.5 rounded border border-rose-200"><?= esc($mom['bloodType']); ?></span>
                                                </p>
                                                <p class="text-[11px] text-slate-400 mt-0.5">LMP: <?= esc($mom['lmp']); ?> ·
                                                    Expected
                                                    EDC: <strong class="text-slate-800 font-bold"><?= esc($mom['edc']); ?></strong>
                                                </p>
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
                                                    <?= esc($mom['nextVisit']); ?>
                                                </p>
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
                        <div class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                                    Family Planning &amp; Reproductive Health Registry
                                </h2>
                                <p class="text-xs text-slate-500 font-medium">Contraceptive methods, acceptor tracking, and supply follow-ups</p>
                            </div>
                            <a href="<?= esc(tabUrl('fp', ['modal' => 'new_fp'])); ?>"
                                class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 transition-all flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg> Register FP Client
                            </a>
                        </div>

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
                                <p class="text-xs text-slate-400 mt-0.5">Contraceptive users and acceptors will be logged here.
                                </p>
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
                                                    <td class="px-4 py-3.5 text-slate-700 font-semibold"><?= esc($fp['age']); ?>
                                                    </td>
                                                    <td class="px-4 py-3.5 font-semibold text-slate-700">
                                                        <?= esc($fp['barangay']); ?>
                                                    </td>
                                                    <td class="px-4 py-3.5 text-teal-900 font-bold"><?= esc($fp['method']); ?></td>
                                                    <td class="px-4 py-3.5"><span
                                                            class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-800"><?= esc($fp['acceptorType']); ?></span>
                                                    </td>
                                                    <td class="px-4 py-3.5 text-slate-500 font-mono"><?= esc($fp['lastSupply']); ?>
                                                    </td>
                                                    <td class="px-4 py-3.5 font-bold text-teal-600 font-mono">
                                                        <?= esc($fp['nextVisit']); ?>
                                                    </td>
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
                        <div class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 2 4 4"/><path d="m17 7 3-3"/><path d="M19 9 8.7 19.3c-1 1-2.5 1-3.4 0l-.6-.6c-1-1-1-2.5 0-3.4L15 5"/><path d="m9 11 4 4"/><path d="m5 19-3 3"/><path d="m14 4 6 6"/></svg>
                                    Immunization &amp; Vaccine Registry
                                </h2>
                                <p class="text-xs text-slate-500 font-medium">Record administered vaccines and schedule next doses</p>
                            </div>
                            <a href="<?= esc(tabUrl('immunization', ['modal' => 'new_immunization'])); ?>"
                                class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 transition-all flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg> Record Vaccine
                            </a>
                        </div>

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
                                            <p class="font-bold text-slate-800 text-sm sm:text-base"><?= esc($im['childName']); ?>
                                            </p>
                                            <p class="text-xs text-slate-600 mt-0.5">Vaccine: <strong
                                                    class="text-indigo-900 font-bold"><?= esc($im['vaccineName']); ?></strong>
                                                (<?= esc($im['targetAge']); ?>) · Batch: <?= esc($im['lot']); ?></p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">Barangay: <?= esc($im['barangay']); ?> ·
                                                Given:
                                                <?= esc($im['dateGiven']); ?> · Next: <strong
                                                    class="text-emerald-700 font-bold"><?= esc($im['nextVisit']); ?></strong>
                                            </p>
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
                        <div class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                                    Municipal Births &amp; Vital Statistics
                                </h2>
                                <p class="text-xs text-slate-500 font-medium">Register births and update vital records</p>
                            </div>
                            <a href="<?= esc(tabUrl('vital', ['modal' => 'new_birth'])); ?>"
                                class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 transition-all flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg> Register Birth
                            </a>
                        </div>

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
                                                <?= esc($vr['barangay']); ?> · Weight: <?= esc($vr['weight']); ?>
                                            </p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">Birth Attendant:
                                                <?= esc($vr['attendant']); ?>
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
                        <div class="flex flex-wrap items-center justify-between gap-3 dashboard-card bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                            <div>
                                <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" x2="12" y1="2" y2="15"/></svg>
                                    High-Risk OB Hospital Referrals
                                </h2>
                                <p class="text-xs text-slate-500 font-medium">Create maternal referrals to hospitals and tertiary facilities</p>
                            </div>
                            <a href="<?= esc(tabUrl('referrals', ['modal' => 'new_referral'])); ?>"
                                class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-600/20 transition-all flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg> Create Referral
                            </a>
                        </div>

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
                                    <div
                                        class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <span
                                                class="font-mono text-xs font-bold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200"><?= esc($ref['id']); ?></span>
                                            <span
                                                class="px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-900 border border-amber-200"><?= esc($ref['urgency']); ?></span>
                                        </div>
                                        <p class="font-bold text-slate-800 text-base"><?= esc($ref['patientName']); ?>
                                            (<?= esc($ref['age']); ?> y/o)</p>
                                        <p class="text-xs text-slate-700"><strong>Diagnosis:</strong> <?= esc($ref['diagnosis']); ?>
                                        </p>
                                        <p class="text-xs text-indigo-900"><strong>Referred To:</strong>
                                            <?= esc($ref['referredTo']); ?>
                                        </p>
                                        <p
                                            class="text-xs text-slate-500 font-mono bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                            <strong>Reason:</strong> <?= esc($ref['reason']); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($tab === 'opd'): ?>
                    <div class="space-y-4">
                        <h2 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2"><svg
                                class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
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
                                    <div
                                        class="dashboard-card bg-white rounded-2xl p-5 border border-slate-200 shadow-sm space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <p class="font-bold text-slate-800 text-base"><?= esc($opd['patientName']); ?> <span
                                                    class="text-xs font-medium text-slate-500">(<?= esc($opd['age'] ?? 'N/A'); ?>y /
                                                    <?= esc($opd['gender']); ?>)</span></p>
                                            <span
                                                class="font-mono text-xs bg-teal-50 text-teal-900 font-bold px-2.5 py-1 rounded-lg border border-teal-200"><?= esc($opd['icd10'] ?: 'Z34.8'); ?></span>
                                        </div>
                                        <p class="text-xs text-slate-700"><strong>Chief Complaint:</strong>
                                            <?= esc($opd['chiefComplaint']); ?>
                                        </p>
                                        <div
                                            class="text-xs text-slate-600 font-mono bg-slate-50 p-3 rounded-xl border border-slate-200/60">
                                            <?= esc($opd['consultation_notes']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>




            </main>
        </div>
    </div>

    
    <?php if ($modal === 'new_fp'): ?>
        <div class="fixed inset-0 bg-slate-950/45 backdrop-blur-sm flex items-end sm:items-center justify-center z-50 p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-200">
                <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-white rounded-t-2xl shrink-0">
                    <div>
                        <h2 class="text-base font-bold text-slate-800">Register Family Planning Client</h2>
                        <p class="text-xs text-slate-500">Contraceptive method, supply date, and follow-up</p>
                    </div>
                    <a href="<?= esc(tabUrl('fp')); ?>" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></a>
                </div>
                <form class="p-5 space-y-4 text-xs overflow-y-auto" method="post">

                    <input type="hidden" name="action" value="save_family_planning">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Select Resident *</label>
                        <select required name="resident_id" class="w-full p-3 border border-slate-300 rounded-xl text-sm font-semibold focus:border-teal-500 focus:ring-2 focus:ring-teal-200 outline-none">
                            <option value="">-- Select resident --</option>
                            <?php foreach ($allMothersList as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= esc($r['name']) ?> — <?= esc($r['barangay']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Contraceptive Method *</label>
                            <select required name="contraceptive_method" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                                <option value="">Select method</option>
                                <?php foreach (['Pills', 'Injectable', 'Condom', 'IUD', 'Implant', 'Natural Family Planning', 'Permanent Method'] as $method): ?>
                                    <option><?= esc($method) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Acceptor Type</label>
                            <select name="acceptor_type" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                                <option>New Acceptor</option>
                                <option>Continuing User</option>
                                <option>Changing Method</option>
                                <option>Restarting User</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Last Supply Date *</label>
                            <input required type="date" name="last_supply_date" value="<?= date('Y-m-d') ?>" class="w-full p-3 border border-slate-300 rounded-xl text-sm font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Next Visit Date</label>
                            <input type="date" name="next_visit_date" class="w-full p-3 border border-slate-300 rounded-xl text-sm font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Clinical Notes</label>
                        <textarea name="clinical_notes" rows="3" placeholder="Counseling or clinical notes" class="w-full p-3 border border-slate-300 rounded-xl text-xs resize-none"></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="<?= esc(tabUrl('fp')); ?>" class="flex-1 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-teal-600 text-white rounded-xl text-xs font-bold hover:bg-teal-700 shadow-md shadow-teal-600/20 transition-all">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($modal === 'new_immunization'): ?>
        <div class="fixed inset-0 bg-slate-950/45 backdrop-blur-sm flex items-end sm:items-center justify-center z-50 p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-200">
                <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-white rounded-t-2xl shrink-0">
                    <div>
                        <h2 class="text-base font-bold text-slate-800">Record Administered Vaccine</h2>
                        <p class="text-xs text-slate-500">Vaccine, batch number, and next dose</p>
                    </div>
                    <a href="<?= esc(tabUrl('immunization')); ?>" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></a>
                </div>
                <form class="p-5 space-y-4 text-xs overflow-y-auto" method="post">

                    <input type="hidden" name="action" value="save_immunization">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Select Resident / Child *</label>
                        <select required name="resident_id" class="w-full p-3 border border-slate-300 rounded-xl text-sm font-semibold focus:border-teal-500 focus:ring-2 focus:ring-teal-200 outline-none">
                            <option value="">-- Select resident --</option>
                            <?php foreach ($allResidentsList as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= esc($r['name']) ?> — <?= esc($r['barangay']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Vaccine *</label>
                            <select required name="vaccine_id" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                                <option value="">Select vaccine</option>
                                <?php foreach ($vaccineSchedules as $v): ?>
                                    <option value="<?= (int)$v['id'] ?>"><?= esc($v['vaccine_name']) ?> (<?= esc($v['age_group']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Vaccination Date *</label>
                            <input required type="date" name="vaccination_date" value="<?= date('Y-m-d') ?>" class="w-full p-3 border border-slate-300 rounded-xl text-sm font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Batch / Lot Number *</label>
                            <input required name="batch_number" placeholder="Vaccine batch/lot number" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Injection Site</label>
                            <input name="site_of_injection" placeholder="e.g., Left deltoid" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Next Dose Date</label>
                            <input type="date" name="next_dose_date" class="w-full p-3 border border-slate-300 rounded-xl text-sm font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Adverse Reactions</label>
                        <input name="adverse_reactions" placeholder="Adverse reactions, if any" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="<?= esc(tabUrl('immunization')); ?>" class="flex-1 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-teal-600 text-white rounded-xl text-xs font-bold hover:bg-teal-700 shadow-md shadow-teal-600/20 transition-all">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($modal === 'new_birth'): ?>
        <div class="fixed inset-0 bg-slate-950/45 backdrop-blur-sm flex items-end sm:items-center justify-center z-50 p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-200">
                <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-white rounded-t-2xl shrink-0">
                    <div>
                        <h2 class="text-base font-bold text-slate-800">Register Birth Record</h2>
                        <p class="text-xs text-slate-500">Child details and vital statistics</p>
                    </div>
                    <a href="<?= esc(tabUrl('vital')); ?>" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></a>
                </div>
                <form class="p-5 space-y-4 text-xs overflow-y-auto" method="post">

                    <input type="hidden" name="action" value="save_birth_record">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Select Mother *</label>
                        <select required name="mother_id" class="w-full p-3 border border-slate-300 rounded-xl text-sm font-semibold focus:border-teal-500 focus:ring-2 focus:ring-teal-200 outline-none">
                            <option value="">-- Select mother --</option>
                            <?php foreach ($allMothersList as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= esc($r['name']) ?> — <?= esc($r['barangay']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-slate-700 mb-1">Child's Complete Name *</label>
                            <input required name="child_name" placeholder="Child's complete name" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Date of Birth *</label>
                            <input required type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" class="w-full p-3 border border-slate-300 rounded-xl text-sm font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Time of Birth</label>
                            <input type="time" name="time_of_birth" class="w-full p-3 border border-slate-300 rounded-xl text-sm font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Gender</label>
                            <select name="gender" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                                <option value="">Select</option>
                                <option>Female</option>
                                <option>Male</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Father's Name</label>
                            <input name="father_name" placeholder="Father's name" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Place of Birth</label>
                            <input name="place_of_birth" value="<?= esc($midwifeProfile['assigned_facility'] ?? 'Nasugbu RHU I') ?>" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Certificate Number</label>
                            <input name="birth_certificate_number" placeholder="Auto if blank" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Weight (kg)</label>
                            <input type="number" step="0.01" min="0" name="birth_weight_kg" placeholder="e.g., 3.20" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Length (cm)</label>
                            <input type="number" step="0.1" min="0" name="birth_length_cm" placeholder="e.g., 49.0" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="<?= esc(tabUrl('vital')); ?>" class="flex-1 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-teal-600 text-white rounded-xl text-xs font-bold hover:bg-teal-700 shadow-md shadow-teal-600/20 transition-all">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($modal === 'new_referral'): ?>
        <div class="fixed inset-0 bg-slate-950/45 backdrop-blur-sm flex items-end sm:items-center justify-center z-50 p-3 sm:p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-200">
                <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-white rounded-t-2xl shrink-0">
                    <div>
                        <h2 class="text-base font-bold text-slate-800">Create Maternal Referral</h2>
                        <p class="text-xs text-slate-500">Hospital referral for high-risk OB cases</p>
                    </div>
                    <a href="<?= esc(tabUrl('referrals')); ?>" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></a>
                </div>
                <form class="p-5 space-y-4 text-xs overflow-y-auto" method="post">

                    <input type="hidden" name="action" value="save_referral">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Expectant Mother *</label>
                        <select required name="resident_id" class="w-full p-3 border border-slate-300 rounded-xl text-sm font-semibold focus:border-teal-500 focus:ring-2 focus:ring-teal-200 outline-none">
                            <option value="">-- Select expectant mother --</option>
                            <?php foreach ($allMothersList as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= esc($r['name']) ?> — <?= esc($r['barangay']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Urgency</label>
                            <select name="urgency" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                                <option>Routine</option>
                                <option>Urgent</option>
                                <option>Emergency</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Diagnosis *</label>
                            <input required name="diagnosis" placeholder="Clinical diagnosis" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-slate-700 mb-1">Referred To *</label>
                            <input required name="referred_to" placeholder="Receiving hospital/facility" class="w-full p-3 border border-slate-300 rounded-xl text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Referral Reason *</label>
                        <textarea required name="referral_reason" rows="3" placeholder="Referral reason and clinical findings" class="w-full p-3 border border-slate-300 rounded-xl text-xs resize-none"></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="<?= esc(tabUrl('referrals')); ?>" class="flex-1 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-teal-600 text-white rounded-xl text-xs font-bold hover:bg-teal-700 shadow-md shadow-teal-600/20 transition-all">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

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
                        <label class="block font-bold text-gray-700 mb-1">Select Expectant Mother *</label>
                        <select id="maternal-resident-select" name="resident_id" required
                            class="w-full p-3 border border-gray-300 rounded-xl text-sm font-semibold focus:border-teal-500 focus:ring-2 focus:ring-teal-200 outline-none">
                            <option value="">-- Select Female Resident --</option>
                            <option value="new">+ Type and register a new mother</option>
                            <?php foreach ($allMothersList as $m): ?>
                                <option value="<?= esc($m['id']); ?>"><?= esc($m['name']); ?> (<?= esc($m['barangay']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="new-mother-fields" class="hidden rounded-2xl border border-teal-200 bg-teal-50/60 p-4">
                        <p class="mb-3 font-extrabold text-teal-900">New Mother Information</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input name="new_first_name" data-new-mother-required placeholder="First name *"
                                class="rounded-xl border border-gray-300 p-3">
                            <input name="new_middle_name" placeholder="Middle name"
                                class="rounded-xl border border-gray-300 p-3">
                            <input name="new_last_name" data-new-mother-required placeholder="Last name *"
                                class="rounded-xl border border-gray-300 p-3">
                            <input type="date" name="new_date_of_birth" data-new-mother-required max="<?= date('Y-m-d') ?>"
                                class="rounded-xl border border-gray-300 p-3" aria-label="Date of birth">
                            <input name="new_barangay" data-new-mother-required placeholder="Barangay *"
                                class="rounded-xl border border-gray-300 p-3">
                            <input name="new_contact_number" placeholder="Contact number"
                                class="rounded-xl border border-gray-300 p-3">
                            <input name="new_address" placeholder="Complete address"
                                class="rounded-xl border border-gray-300 p-3 sm:col-span-2">
                        </div>
                        <p class="mt-2 text-[10px] text-teal-700">A resident record will be created and linked automatically
                            to this prenatal case.</p>
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





    <script>
        (function () {
            const sidebar = document.querySelector('[data-feature-drawer]');
            const backdrop = document.querySelector('[data-drawer-backdrop]');
            const openBtn = document.querySelector('[data-drawer-open]');
            const closeBtn = document.querySelector('[data-drawer-close]');
            const header = document.querySelector('.dashboard-header');
            const setOpen = (open) => {
                sidebar?.classList.toggle('is-open', open);
                backdrop?.classList.toggle('is-open', open);
                document.body.classList.toggle('drawer-open', open);
                openBtn?.setAttribute('aria-expanded', String(!!open));
            };
            openBtn?.addEventListener('click', () => setOpen(true));
            closeBtn?.addEventListener('click', () => setOpen(false));
            backdrop?.addEventListener('click', () => setOpen(false));
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
            /* scroll shadow effect disabled for reduced motion */
        })();
    </script>
</body>

</html>
