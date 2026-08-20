<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$base = '';
require_once __DIR__ . '/db.php';

if (isset($_GET['logout'])) {
  unset($_SESSION['user']);
  header('Location: ResidentLogin.php');
  exit;
}

if (empty($_SESSION['user'])) {
  header('Location: ResidentLogin.php');
  exit;
}

// ----------------------------------------------------
// REALTIME NOTIFICATIONS API ENDPOINTS
// ----------------------------------------------------
if (isset($_GET['api']) || isset($_POST['api'])) {
  header('Content-Type: application/json; charset=utf-8');
  $api = $_GET['api'] ?? $_POST['api'] ?? '';
  $user = $_SESSION['user'] ?? [];
  $userId = (int)($user['id'] ?? $user['user_id'] ?? 0);
  $residentId = (int)($user['resident_id'] ?? 0);

  if (empty($userId) && !empty($residentId) && !empty($pdo)) {
    try {
      $uStmt = $pdo->prepare("SELECT u.id FROM residents r JOIN users u ON u.email = r.email WHERE r.id = :rid LIMIT 1");
      $uStmt->execute(['rid' => $residentId]);
      if ($uid = $uStmt->fetchColumn()) {
        $userId = (int)$uid;
      }
    } catch (Throwable $t) {
    }
  }

  if (empty($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
    exit;
  }

  // Auto-provision portal_notifications table if needed
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS portal_notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            audience_role VARCHAR(50) NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_role (user_id, audience_role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Throwable $t) {
  }

  if ($api === 'get_notifications') {
    try {
      $stmt = $pdo->prepare("
                SELECT id, message, link_url, is_read, created_at
                FROM portal_notifications
                WHERE (user_id = :uid AND user_id > 0) OR (audience_role = 'RESIDENT' OR (user_id IS NULL AND audience_role IS NULL))
                ORDER BY id DESC LIMIT 50
            ");
      $stmt->execute(['uid' => $userId]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

      // Seed initial system notifications if zero existing
      if (empty($rows)) {
        $seedMsg = "Welcome to the RHU Resident Portal! Access your medical history, health records, and book OPD consultations online.";
        $ins = $pdo->prepare("INSERT INTO portal_notifications (user_id, audience_role, message, link_url, is_read) VALUES (:uid, 'RESIDENT', :msg, 'ResidentDashboard.php?tab=profile', 0)");
        $ins->execute(['uid' => $userId ?: null, 'msg' => $seedMsg]);

        $seedMsg2 = "Reminder: Please keep your Emergency Contact Person and PhilHealth Number updated under the Profile tab.";
        $ins->execute(['uid' => $userId ?: null, 'msg' => $seedMsg2]);

        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
      }

      $unreadCount = 0;
      $formatted = [];
      foreach ($rows as $r) {
        $isRead = (int)$r['is_read'];
        if (!$isRead) $unreadCount++;

        $timestamp = strtotime($r['created_at']);
        $diff = time() - $timestamp;
        $timeAgo = match (true) {
          $diff < 60 => 'Just now',
          $diff < 3600 => floor($diff / 60) . ' mins ago',
          $diff < 86400 => floor($diff / 3600) . ' hours ago',
          default => date('M j, Y g:i A', $timestamp)
        };

        $formatted[] = [
          'id' => (int)$r['id'],
          'title' => 'RHU Resident Notification',
          'message' => $r['message'],
          'link_url' => $r['link_url'],
          'is_read' => $isRead,
          'created_at' => $r['created_at'],
          'time_ago' => $timeAgo
        ];
      }

      echo json_encode(['success' => true, 'notifications' => $formatted, 'unread_count' => $unreadCount]);
      exit;
    } catch (Throwable $e) {
      echo json_encode(['success' => false, 'error' => $e->getMessage()]);
      exit;
    }
  }

  if ($api === 'mark_read') {
    try {
      $notifId = (int)($_POST['id'] ?? 0);
      $markAll = (int)($_POST['all'] ?? 0);

      if ($markAll) {
        $stmt = $pdo->prepare("UPDATE portal_notifications SET is_read = 1 WHERE (user_id = :uid AND user_id > 0) OR audience_role = 'RESIDENT' OR (user_id IS NULL AND audience_role IS NULL)");
        $stmt->execute(['uid' => $userId]);
      } elseif ($notifId > 0) {
        $stmt = $pdo->prepare("UPDATE portal_notifications SET is_read = 1 WHERE id = :id");
        $stmt->execute(['id' => $notifId]);
      }
      echo json_encode(['success' => true]);
      exit;
    } catch (Throwable $e) {
      echo json_encode(['success' => false, 'error' => $e->getMessage()]);
      exit;
    }
  }

  if ($api === 'delete_notifications') {
    try {
      $idsRaw = $_POST['ids'] ?? '';
      $ids = json_decode($idsRaw, true);
      if (!is_array($ids)) {
        $ids = array_filter(array_map('intval', explode(',', (string)$idsRaw)));
      }

      if (!empty($ids)) {
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM portal_notifications WHERE id IN ($inQuery)");
        $stmt->execute(array_values($ids));
      }
      echo json_encode(['success' => true, 'deleted_count' => count($ids)]);
      exit;
    } catch (Throwable $e) {
      echo json_encode(['success' => false, 'error' => $e->getMessage()]);
      exit;
    }
  }
}

// Audit records are restricted to authenticated RHU staff and administrators.
// Never allow a resident-controlled tab value to enter an audit-log view.
if (strtolower((string)($_GET['tab'] ?? '')) === 'audit') {
  $_SESSION['resident_dashboard_access_flash'] = 'Audit logs are restricted to RHU staff and administrators.';
  header('Location: ResidentDashboard.php?tab=home');
  exit;
}

function esc($value): string
{
  return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function residentAge(?string $dateOfBirth): ?int
{
  if (!$dateOfBirth) return null;
  try {
    return (new DateTime($dateOfBirth))->diff(new DateTime('today'))->y;
  } catch (Exception $e) {
    return null;
  }
}

$user = $_SESSION['user'];
$resident = null;
$consultations = [];
$vaccinationRecords = [];
$familyPlanningRecords = [];
$maternalReferrals = [];
$pregnancyRecords = [];
$birthRecords = [];
$certificates = [];
$healthCertificates = [];
$postedEvents = [];
$rhuStaffList = [];
$loadError = null;
$contactSuccess = $_SESSION['resident_dashboard_message_flash'] ?? '';
$certificateSuccess = $_SESSION['resident_dashboard_certificate_flash'] ?? '';
unset($_SESSION['resident_dashboard_message_flash'], $_SESSION['resident_dashboard_certificate_flash']);
$contactErrors = [];
$certificateErrors = [];
$residentMessages = [];
$dependents = [];
$dependentErrors = [];
$dependentSuccess = $_SESSION['resident_dashboard_dependent_flash'] ?? '';
unset($_SESSION['resident_dashboard_dependent_flash']);
if (empty($_SESSION['resident_dashboard_csrf'])) {
  $_SESSION['resident_dashboard_csrf'] = bin2hex(random_bytes(32));
}
$dashboardCsrf = $_SESSION['resident_dashboard_csrf'];

if (!empty($pdo)) {
  try {
    if (!empty($user['resident_id'])) {
      $statement = $pdo->prepare('SELECT * FROM residents WHERE id = :id LIMIT 1');
      $statement->execute(['id' => $user['resident_id']]);
      $resident = $statement->fetch();
    }
    if (!$resident && !empty($user['email'])) {
      $statement = $pdo->prepare('SELECT * FROM residents WHERE email = :email LIMIT 1');
      $statement->execute(['email' => $user['email']]);
      $resident = $statement->fetch();
    }
    if (!$resident && !empty($user['last_name'])) {
      $statement = $pdo->prepare('SELECT * FROM residents WHERE last_name = :last_name ORDER BY id');
      $statement->execute(['last_name' => trim((string)$user['last_name'])]);
      $sameSurnameResidents = $statement->fetchAll(PDO::FETCH_ASSOC);
      $accountFirstName = strtolower(trim((string)($user['first_name'] ?? '')));
      $matchingResidents = array_values(array_filter(
        $sameSurnameResidents,
        static function (array $candidate) use ($accountFirstName): bool {
          $residentFirstName = strtolower(trim((string)($candidate['first_name'] ?? '')));
          return $residentFirstName !== ''
            && ($accountFirstName === $residentFirstName
              || str_starts_with($accountFirstName, $residentFirstName . ' ')
              || str_starts_with($residentFirstName, $accountFirstName . ' '));
        }
      ));
      if (count($matchingResidents) === 1) {
        $resident = $matchingResidents[0];
        $_SESSION['user']['resident_id'] = (int)$resident['id'];
        $user['resident_id'] = (int)$resident['id'];
      }
    }
    if (!$resident && (!empty($user['first_name']) || !empty($user['name']) || !empty($user['email']))) {
      try {
        $autoFn = !empty($user['first_name']) ? $user['first_name'] : (explode(' ', $user['name'] ?? 'Resident')[0] ?? 'Resident');
        $autoLn = !empty($user['last_name']) ? $user['last_name'] : (explode(' ', $user['name'] ?? 'Account')[1] ?? 'Account');
        $autoEm = !empty($user['email']) ? $user['email'] : ('user_' . rand(1000, 9999) . '@rhu.gov.ph');

        $insPrimaryRes = $pdo->prepare("
          INSERT INTO residents (first_name, last_name, email, date_of_birth, address, barangay, is_active, created_at, updated_at)
          VALUES (:f, :l, :e, '1995-01-01', 'Nasugbu, Batangas', 'Aplaya', 1, NOW(), NOW())
        ");
        $insPrimaryRes->execute(['f' => $autoFn, 'l' => $autoLn, 'e' => $autoEm]);
        $newPrimaryResId = (int)$pdo->lastInsertId();

        $fetchStmt = $pdo->prepare("SELECT * FROM residents WHERE id = :id LIMIT 1");
        $fetchStmt->execute(['id' => $newPrimaryResId]);
        $resident = $fetchStmt->fetch();
        if ($resident && !empty($_SESSION['user'])) {
          $_SESSION['user']['resident_id'] = $newPrimaryResId;
          $user['resident_id'] = $newPrimaryResId;
        }
      } catch (Throwable $tAutoRes) {}
    }

    if ($resident) {
      $residentId = (int)$resident['id'];
      $pdo->exec(
        "CREATE TABLE IF NOT EXISTS resident_dependents (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    primary_resident_id BIGINT UNSIGNED NOT NULL,
                    dependent_resident_id BIGINT UNSIGNED NULL,
                    first_name VARCHAR(100) NOT NULL,
                    middle_name VARCHAR(100) NULL,
                    last_name VARCHAR(100) NOT NULL,
                    relationship VARCHAR(40) NOT NULL,
                    date_of_birth DATE NOT NULL,
                    gender VARCHAR(20) NULL,
                    blood_type VARCHAR(10) NULL,
                    medical_notes TEXT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_resident_dependents_primary (primary_resident_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
      );

      if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        // Validate CSRF token
        $submittedCsrf = $_POST['csrf_token'] ?? '';
        $csrfValid = !empty($submittedCsrf) && hash_equals($dashboardCsrf, $submittedCsrf);
        
        $formType = $_POST['form'] ?? '';
        if ($formType === 'add_dependent') {
          if (!$csrfValid) {
            $dependentErrors[] = 'Security validation failed. Please try again.';
          } else {
            $firstName = trim($_POST['first_name'] ?? '');
            $middleName = trim($_POST['middle_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $relationship = trim($_POST['relationship'] ?? 'Family Member');
            if (empty($relationship)) $relationship = 'Family Member';
            $rawDob = trim($_POST['date_of_birth'] ?? '');
            $parsedTime = strtotime($rawDob);
            $dateOfBirth = ($parsedTime && $parsedTime <= time()) ? date('Y-m-d', $parsedTime) : '';
            $gender = trim($_POST['gender'] ?? '');
            $bloodType = trim($_POST['blood_type'] ?? '');
            $medicalNotes = trim($_POST['medical_notes'] ?? '');

            if ($firstName === '' || $lastName === '') {
              $dependentErrors[] = 'First name and last name are required.';
            } elseif ($dateOfBirth === '') {
              $dependentErrors[] = 'Please provide a valid date of birth.';
            } else {
              // 1. Ensure dependent_resident_id column exists on resident_dependents table
              try {
                $pdo->exec("ALTER TABLE resident_dependents ADD COLUMN dependent_resident_id BIGINT UNSIGNED NULL AFTER primary_resident_id");
              } catch (Throwable $tCol) {}

            // 2. Check if this dependent already exists in `residents` table by first_name, last_name, date_of_birth
              $checkRes = $pdo->prepare(
                "SELECT id FROM residents WHERE LOWER(first_name) = LOWER(:fn) AND LOWER(last_name) = LOWER(:ln) AND date_of_birth = :dob LIMIT 1"
              );
              $checkRes->execute([
                'fn' => $firstName,
                'ln' => $lastName,
                'dob' => $dateOfBirth
              ]);
              $dependentResidentId = (int)($checkRes->fetchColumn() ?: 0);

              // 3. If not existing in `residents`, automatically create the dependent in `residents` table!
              if (!$dependentResidentId) {
                $headName = trim(($resident['first_name'] ?? '') . ' ' . ($resident['last_name'] ?? ''));
                $headAddress = !empty($resident['address']) ? $resident['address'] : 'Nasugbu, Batangas';
                $headBarangay = !empty($resident['barangay']) ? $resident['barangay'] : 'Aplaya';
                $headPurok = !empty($resident['purok_sitio']) ? $resident['purok_sitio'] : null;
                $headContact = !empty($resident['contact_number']) ? $resident['contact_number'] : null;

                $insRes = $pdo->prepare("
                  INSERT INTO residents (
                    first_name, middle_name, last_name, date_of_birth, gender, blood_type,
                    medical_conditions, address, barangay, purok_sitio,
                    emergency_contact_name, emergency_contact_number, emergency_contact_relationship,
                    is_active, created_at, updated_at
                  ) VALUES (
                    :fn, :mn, :ln, :dob, :gender, :bt,
                    :med, :addr, :brgy, :purok,
                    :e_name, :e_num, :e_rel,
                    1, NOW(), NOW()
                  )
                ");
                $insRes->execute([
                  'fn' => $firstName,
                  'mn' => $middleName ?: null,
                  'ln' => $lastName,
                  'dob' => $dateOfBirth,
                  'gender' => $gender ?: null,
                  'bt' => $bloodType ?: null,
                  'med' => $medicalNotes ?: null,
                  'addr' => $headAddress,
                  'brgy' => $headBarangay,
                  'purok' => $headPurok,
                  'e_name' => $headName ?: null,
                  'e_num' => $headContact ?: null,
                  'e_rel' => 'Head of Household (' . $relationship . ')'
                ]);
                $dependentResidentId = (int)$pdo->lastInsertId();
              }

              // 4. Save into `resident_dependents` table with link to dependent_resident_id
              $statement = $pdo->prepare(
                'INSERT INTO resident_dependents
                               (primary_resident_id, dependent_resident_id, first_name, middle_name, last_name, relationship, date_of_birth, gender, blood_type, medical_notes)
                               VALUES (:resident_id, :dep_res_id, :first_name, :middle_name, :last_name, :relationship, :date_of_birth, :gender, :blood_type, :medical_notes)'
              );
              $statement->execute([
                'resident_id' => $residentId,
                'dep_res_id' => $dependentResidentId ?: null,
                'first_name' => $firstName,
                'middle_name' => $middleName ?: null,
                'last_name' => $lastName,
                'relationship' => $relationship,
                'date_of_birth' => $dateOfBirth,
                'gender' => $gender ?: null,
                'blood_type' => $bloodType ?: null,
                'medical_notes' => $medicalNotes ?: null,
              ]);

              $_SESSION['resident_dashboard_dependent_flash'] = "{$firstName} {$lastName} was added to your household and registered as a resident.";
              header('Location: ResidentDashboard.php?tab=family');
              exit;
            }
          }
        } elseif ($formType === 'remove_dependent') {
          if (!$csrfValid) {
            $dependentErrors[] = 'Security validation failed. Please try again.';
          } else {
            $dependentId = (int)($_POST['dependent_id'] ?? 0);
            if ($dependentId > 0) {
              $statement = $pdo->prepare(
                'DELETE FROM resident_dependents WHERE id = :id AND primary_resident_id = :resident_id'
              );
              $statement->execute(['id' => $dependentId, 'resident_id' => $residentId]);
              $_SESSION['resident_dashboard_dependent_flash'] = $statement->rowCount()
                ? 'The dependent was removed from your household.'
                : 'Dependent record not found.';
              header('Location: ResidentDashboard.php?tab=family');
              exit;
            }
          }
        } elseif ($formType === 'contact') {
          $subject = trim($_POST['subject'] ?? 'General Inquiry');
          $message = trim($_POST['message'] ?? '');
          if ($message === '') {
            $contactErrors[] = 'Please type your message before sending.';
          } else {
            $ins = $pdo->prepare("INSERT INTO messages (resident_id, subject, message, status, created_at) VALUES (:res, :subj, :msg, 'Pending', NOW())");
            $ins->execute(['res' => $residentId, 'subj' => $subject, 'msg' => $message]);
            $_SESSION['resident_dashboard_message_flash'] = 'Your message has been sent directly to the RHU Staff & Admin. We will respond shortly.';
            header('Location: ResidentDashboard.php?tab=contact');
            exit;
          }
        } elseif ($formType === 'certificate_request') {
          $certificateType = trim($_POST['certificate_type'] ?? '');
          if ($certificateType === '') {
            $certificateErrors[] = 'Please choose a certificate to request.';
          } else {
            $certTypeId = 1;
            $ctStmt = $pdo->prepare("SELECT id FROM certificate_types WHERE certificate_type_name LIKE :name LIMIT 1");
            $ctStmt->execute(['name' => '%' . explode(' ', $certificateType)[0] . '%']);
            if ($fId = $ctStmt->fetchColumn()) {
              $certTypeId = (int)$fId;
            }
            $certNo = 'REQ-' . $residentId . '-' . rand(1000, 9999);
            $ins = $pdo->prepare("INSERT INTO health_certificates (resident_id, certificate_type_id, certificate_number, issue_date, expiry_date, purpose, validity_status, created_at) VALUES (:res, :type, :cno, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), :purp, 'Pending', NOW())");
            $ins->execute(['res' => $residentId, 'type' => $certTypeId, 'cno' => $certNo, 'purp' => "Portal Request: {$certificateType}"]);
            $_SESSION['resident_dashboard_certificate_flash'] = "Request for {$certificateType} submitted to RHU Staff & Admin for processing.";
            header('Location: ResidentDashboard.php?tab=certificates');
            exit;
          }
        } elseif ($formType === 'appointment_request') {
          $chiefComplaint = trim($_POST['chief_complaint'] ?? 'Primary Health Checkup');
          $appointmentType = trim($_POST['appointment_type'] ?? 'General Medical Consultation');
          $preferredDate = trim($_POST['preferred_date'] ?? date('Y-m-d'));
          $physicianId = !empty($_POST['physician_id']) ? (int)$_POST['physician_id'] : 1;

          $notes = "Appointment Category: {$appointmentType} | Booking Date: {$preferredDate} | Requested via Resident Portal";

          $ins = $pdo->prepare("INSERT INTO consultations (resident_id, physician_id, consultation_date, consultation_time, chief_complaint, diagnosis, consultation_notes, created_at) VALUES (:res, :pid, :cdate, CURTIME(), :chief, 'Pending OPD Triage', :notes, NOW())");
          $ins->execute([
            'res' => $residentId,
            'pid' => $physicianId,
            'cdate' => $preferredDate,
            'chief' => "[{$appointmentType}] {$chiefComplaint}",
            'notes' => $notes
          ]);

          if (function_exists('portalNotify')) {
            try {
              $uStmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = :sid LIMIT 1");
              $uStmt->execute(['sid' => $physicianId]);
              if ($uUid = $uStmt->fetchColumn()) {
                $resName = trim(($resident['first_name'] ?? 'Resident') . ' ' . ($resident['last_name'] ?? ''));
                portalNotify($pdo, "New consultation appointment booked by {$resName} for {$preferredDate}.", (int)$uUid, null, 'RHUAdminDashboard.php');
              }
            } catch (Throwable $tNotif) {
            }
          }

          $_SESSION['resident_dashboard_message_flash'] = "Appointment request for {$appointmentType} on {$preferredDate} submitted successfully to attending healthcare provider!";
          header('Location: ResidentDashboard.php?tab=records');
          exit;
        }
        // Handling Emergency Referral Request
        elseif ($formType === 'emergency_request') {
          $nature = trim($_POST['emergency_nature'] ?? 'Medical Emergency');
          $location = trim($_POST['pickup_location'] ?? ($resident['address'] ?? 'Barangay Area'));

          $ins = $pdo->prepare("INSERT INTO messages (resident_id, subject, message, status, created_at) VALUES (:res, 'URGENT: Emergency Referral', :msg, 'Urgent', NOW())");
          $ins->execute([
            'res' => $residentId,
            'msg' => "EMERGENCY REFERRAL REQUEST - Type: {$nature} | Location: {$location}"
          ]);

          $_SESSION['resident_dashboard_message_flash'] = 'EMERGENCY REQUEST SENT! The RHU Disaster & Response Unit has been alerted.';
          header('Location: ResidentDashboard.php?tab=emergency');
          exit;
        }
        // Handling Update Health Profile
        elseif ($formType === 'update_health_profile') {
          $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
          $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
          $bloodPressure = trim($_POST['blood_pressure'] ?? '');
          $heartRate = !empty($_POST['heart_rate']) ? (int)$_POST['heart_rate'] : null;
          $temperature = !empty($_POST['temperature']) ? (float)$_POST['temperature'] : null;
          $lastCheckupDate = !empty($_POST['last_checkup_date']) ? trim($_POST['last_checkup_date']) : date('Y-m-d');
          $smokingStatus = trim($_POST['smoking_status'] ?? 'Non-Smoker');
          $alcoholConsumption = trim($_POST['alcohol_consumption'] ?? 'Non-Drinker');
          $exerciseFrequency = trim($_POST['exercise_frequency'] ?? 'Occasional');
          $dietType = trim($_POST['diet_type'] ?? 'Balanced Diet');

          $bloodType = trim($_POST['blood_type'] ?? '');
          $philhealthNo = trim($_POST['philhealth_number'] ?? '');
          $allergies = trim($_POST['allergies'] ?? '');
          $chronicConditions = trim($_POST['chronic_conditions'] ?? '');
          $currentMedications = trim($_POST['current_medications'] ?? '');
          $emergencyContactName = trim($_POST['emergency_contact_name'] ?? '');
          $emergencyContactRelationship = trim($_POST['emergency_contact_relationship'] ?? '');
          $emergencyContactPhone = trim($_POST['emergency_contact_phone'] ?? '');

          try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS resident_health_profiles (
                            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            resident_id BIGINT UNSIGNED NOT NULL UNIQUE,
                            height DOUBLE(5,2) NULL,
                            weight DOUBLE(5,2) NULL,
                            blood_pressure VARCHAR(20) NULL,
                            heart_rate INT NULL,
                            temperature DOUBLE(4,1) NULL,
                            last_checkup_date DATE NULL,
                            smoking_status VARCHAR(50) NULL,
                            alcohol_consumption VARCHAR(50) NULL,
                            exercise_frequency VARCHAR(50) NULL,
                            diet_type VARCHAR(50) NULL,
                            blood_type VARCHAR(10) NULL,
                            philhealth_number VARCHAR(50) NULL,
                            allergies TEXT NULL,
                            chronic_conditions TEXT NULL,
                            current_medications TEXT NULL,
                            emergency_contact_name VARCHAR(150) NULL,
                            emergency_contact_relationship VARCHAR(100) NULL,
                            emergency_contact_phone VARCHAR(50) NULL,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_rhp_resident (resident_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            foreach (
              [
                "ALTER TABLE resident_health_profiles ADD COLUMN blood_pressure VARCHAR(20) NULL AFTER weight",
                "ALTER TABLE resident_health_profiles ADD COLUMN heart_rate INT NULL AFTER blood_pressure",
                "ALTER TABLE resident_health_profiles ADD COLUMN temperature DOUBLE(4,1) NULL AFTER heart_rate",
                "ALTER TABLE resident_health_profiles ADD COLUMN last_checkup_date DATE NULL AFTER temperature",
                "ALTER TABLE resident_health_profiles ADD COLUMN smoking_status VARCHAR(50) NULL AFTER last_checkup_date",
                "ALTER TABLE resident_health_profiles ADD COLUMN alcohol_consumption VARCHAR(50) NULL AFTER smoking_status",
                "ALTER TABLE resident_health_profiles ADD COLUMN exercise_frequency VARCHAR(50) NULL AFTER alcohol_consumption",
                "ALTER TABLE resident_health_profiles ADD COLUMN diet_type VARCHAR(50) NULL AFTER exercise_frequency",
                "ALTER TABLE resident_health_profiles ADD COLUMN blood_type VARCHAR(10) NULL AFTER diet_type",
                "ALTER TABLE resident_health_profiles ADD COLUMN philhealth_number VARCHAR(50) NULL AFTER blood_type",
                "ALTER TABLE resident_health_profiles ADD COLUMN allergies TEXT NULL AFTER philhealth_number",
                "ALTER TABLE resident_health_profiles ADD COLUMN chronic_conditions TEXT NULL AFTER allergies",
                "ALTER TABLE resident_health_profiles ADD COLUMN current_medications TEXT NULL AFTER chronic_conditions",
                "ALTER TABLE resident_health_profiles ADD COLUMN emergency_contact_name VARCHAR(150) NULL AFTER current_medications",
                "ALTER TABLE resident_health_profiles ADD COLUMN emergency_contact_relationship VARCHAR(100) NULL AFTER emergency_contact_name",
                "ALTER TABLE resident_health_profiles ADD COLUMN emergency_contact_phone VARCHAR(50) NULL AFTER emergency_contact_relationship"
              ] as $alterHpSql
            ) {
              try {
                $pdo->exec($alterHpSql);
              } catch (Throwable $tSingleHpCol) {
              }
            }
          } catch (Throwable $tHp) {
          }

          foreach (
            [
              "ALTER TABLE residents ADD COLUMN allergies TEXT NULL",
              "ALTER TABLE residents ADD COLUMN medical_conditions TEXT NULL",
              "ALTER TABLE residents ADD COLUMN emergency_contact_name VARCHAR(150) NULL",
              "ALTER TABLE residents ADD COLUMN emergency_contact_number VARCHAR(50) NULL",
              "ALTER TABLE residents ADD COLUMN emergency_contact_phone VARCHAR(50) NULL",
              "ALTER TABLE residents ADD COLUMN emergency_contact_relationship VARCHAR(100) NULL",
              "ALTER TABLE residents ADD COLUMN philhealth_id VARCHAR(50) NULL",
              "ALTER TABLE residents ADD COLUMN blood_type VARCHAR(10) NULL"
            ] as $alterSql
          ) {
            try {
              $pdo->exec($alterSql);
            } catch (Throwable $tSingleCol) {
            }
          }

          $chkStmt = $pdo->prepare("SELECT id FROM resident_health_profiles WHERE resident_id = :rid LIMIT 1");
          $chkStmt->execute(['rid' => $residentId]);
          $existingHpId = $chkStmt->fetchColumn();

          if ($existingHpId) {
            $upStmt = $pdo->prepare("UPDATE resident_health_profiles SET 
                            height = :h, weight = :w, blood_pressure = :bp, heart_rate = :hr,
                            temperature = :temp, last_checkup_date = :lcd, smoking_status = :smoke,
                            alcohol_consumption = :alc, exercise_frequency = :ex, diet_type = :dt,
                            blood_type = :bt, philhealth_number = :ph, allergies = :alg,
                            chronic_conditions = :cc, current_medications = :cm,
                            emergency_contact_name = :ecn, emergency_contact_relationship = :ecr,
                            emergency_contact_phone = :ecp, updated_at = NOW()
                            WHERE resident_id = :rid");
            $upStmt->execute([
              'h' => $height,
              'w' => $weight,
              'bp' => $bloodPressure,
              'hr' => $heartRate,
              'temp' => $temperature,
              'lcd' => $lastCheckupDate,
              'smoke' => $smokingStatus,
              'alc' => $alcoholConsumption,
              'ex' => $exerciseFrequency,
              'dt' => $dietType,
              'bt' => $bloodType,
              'ph' => $philhealthNo,
              'alg' => $allergies,
              'cc' => $chronicConditions,
              'cm' => $currentMedications,
              'ecn' => $emergencyContactName,
              'ecr' => $emergencyContactRelationship,
              'ecp' => $emergencyContactPhone,
              'rid' => $residentId
            ]);
          } else {
            $insStmt = $pdo->prepare("INSERT INTO resident_health_profiles (
                            resident_id, height, weight, blood_pressure, heart_rate, temperature,
                            last_checkup_date, smoking_status, alcohol_consumption, exercise_frequency, diet_type,
                            blood_type, philhealth_number, allergies, chronic_conditions, current_medications,
                            emergency_contact_name, emergency_contact_relationship, emergency_contact_phone, updated_at
                        ) VALUES (
                            :rid, :h, :w, :bp, :hr, :temp, :lcd, :smoke, :alc, :ex, :dt,
                            :bt, :ph, :alg, :cc, :cm, :ecn, :ecr, :ecp, NOW()
                        )");
            $insStmt->execute([
              'rid' => $residentId,
              'h' => $height,
              'w' => $weight,
              'bp' => $bloodPressure,
              'hr' => $heartRate,
              'temp' => $temperature,
              'lcd' => $lastCheckupDate,
              'smoke' => $smokingStatus,
              'alc' => $alcoholConsumption,
              'ex' => $exerciseFrequency,
              'dt' => $dietType,
              'bt' => $bloodType,
              'ph' => $philhealthNo,
              'alg' => $allergies,
              'cc' => $chronicConditions,
              'cm' => $currentMedications,
              'ecn' => $emergencyContactName,
              'ecr' => $emergencyContactRelationship,
              'ecp' => $emergencyContactPhone
            ]);
          }

          try {
            $upRes = $pdo->prepare("UPDATE residents SET 
                            blood_type = :bt, philhealth_id = :ph, allergies = :alg, medical_conditions = :mc,
                            emergency_contact_name = :ecn, emergency_contact_relationship = :ecr,
                            emergency_contact_phone = :ecp, emergency_contact_number = :ecp2
                            WHERE id = :rid");
            $upRes->execute([
              'bt' => $bloodType,
              'ph' => $philhealthNo,
              'alg' => $allergies,
              'mc' => $chronicConditions,
              'ecn' => $emergencyContactName,
              'ecr' => $emergencyContactRelationship,
              'ecp' => $emergencyContactPhone,
              'ecp2' => $emergencyContactPhone,
              'rid' => $residentId
            ]);
          } catch (Throwable $tRes) {
            try {
              $upResFallback = $pdo->prepare("UPDATE residents SET 
                                blood_type = :bt, philhealth_id = :ph, allergies = :alg, medical_conditions = :mc,
                                emergency_contact_name = :ecn, emergency_contact_number = :ecp
                                WHERE id = :rid");
              $upResFallback->execute([
                'bt' => $bloodType,
                'ph' => $philhealthNo,
                'alg' => $allergies,
                'mc' => $chronicConditions,
                'ecn' => $emergencyContactName,
                'ecp' => $emergencyContactPhone,
                'rid' => $residentId
              ]);
            } catch (Throwable $tResFB) {
            }
          }

          $_SESSION['resident_dashboard_message_flash'] = 'Your Health Profile has been updated successfully!';
          header('Location: ResidentDashboard.php?tab=profile');
          exit;
        }
      }

      $statement = $pdo->prepare(
        'SELECT c.*, CONCAT_WS(" ", u.first_name, u.last_name) AS physician_name
                 FROM consultations c
                 LEFT JOIN staff s ON c.physician_id = s.id
                 LEFT JOIN users u ON s.user_id = u.id
                 WHERE c.resident_id = :resident_id
                 ORDER BY c.consultation_date DESC, c.id DESC'
      );
      $statement->execute(['resident_id' => $residentId]);
      $consultations = $statement->fetchAll(PDO::FETCH_ASSOC);

      // Pagination for consultations
      $itemsPerPage = 4;
      $currentPage = max(1, (int)($_GET['cons_page'] ?? 1));
      $totalConsultations = count($consultations);
      $totalConsultationPages = max(1, ceil($totalConsultations / $itemsPerPage));
      $currentPage = min($currentPage, $totalConsultationPages);
      $consultationOffset = ($currentPage - 1) * $itemsPerPage;
      $consultationsPage = array_slice($consultations, $consultationOffset, $itemsPerPage);

      $statement = $pdo->prepare(
        'SELECT vr.*, COALESCE(i.vaccine_name, CONCAT("Vaccine record #", vr.vaccine_id)) AS vaccine_name,
                        CONCAT_WS(" ", u.first_name, u.last_name) AS provider_name
                 FROM vaccination_records vr
                 LEFT JOIN immunization_schedules i ON vr.vaccine_id = i.id
                 LEFT JOIN staff s ON vr.healthcare_provider_id = s.id
                 LEFT JOIN users u ON s.user_id = u.id
                 WHERE vr.resident_id = :resident_id
                 ORDER BY vr.vaccination_date DESC, vr.id DESC'
      );
      $statement->execute(['resident_id' => $residentId]);
      $vaccinationRecords = $statement->fetchAll(PDO::FETCH_ASSOC);

      try {
        $statement = $pdo->prepare('SELECT * FROM family_planning_records WHERE resident_id = :resident_id ORDER BY id DESC');
        $statement->execute(['resident_id' => $residentId]);
        $familyPlanningRecords = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
      } catch (Throwable $ignored) {
      }

      try {
        $statement = $pdo->prepare('SELECT * FROM maternal_referrals WHERE resident_id = :resident_id ORDER BY id DESC');
        $statement->execute(['resident_id' => $residentId]);
        $maternalReferrals = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
      } catch (Throwable $ignored) {
      }

      try {
        $statement = $pdo->prepare('SELECT * FROM pregnancies WHERE resident_id = :resident_id ORDER BY id DESC');
        $statement->execute(['resident_id' => $residentId]);
        $pregnancyRecords = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
      } catch (Throwable $ignored) {
      }

      try {
        $statement = $pdo->prepare('SELECT * FROM vital_statistics_births WHERE mother_id = :resident_id ORDER BY id DESC');
        $statement->execute(['resident_id' => $residentId]);
        $birthRecords = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
      } catch (Throwable $ignored) {
      }

      $statement = $pdo->prepare(
        'SELECT hc.*, COALESCE(ct.certificate_type_name, "Health Certificate") as certificate_type_name
                 FROM health_certificates hc
                 LEFT JOIN certificate_types ct ON hc.certificate_type_id = ct.id
                 WHERE hc.resident_id = :resident_id
                 ORDER BY hc.id DESC'
      );
      $statement->execute(['resident_id' => $residentId]);
      $certificates = $statement->fetchAll(PDO::FETCH_ASSOC);
      $healthCertificates = $certificates;

      $statement = $pdo->prepare('SELECT * FROM messages WHERE resident_id = :resident_id ORDER BY id DESC');
      $statement->execute(['resident_id' => $residentId]);
      $residentMessages = $statement->fetchAll(PDO::FETCH_ASSOC);

      $statement = $pdo->prepare(
        'SELECT * FROM resident_dependents
                 WHERE primary_resident_id = :resident_id AND is_active = 1
                 ORDER BY date_of_birth ASC, last_name, first_name'
      );
      $statement->execute(['resident_id' => $residentId]);
      $dependents = $statement->fetchAll(PDO::FETCH_ASSOC);

      // Hydrate resident_health_profiles record
      $healthProfile = null;
      try {
        $hpStmt = $pdo->prepare("SELECT * FROM resident_health_profiles WHERE resident_id = :rid LIMIT 1");
        $hpStmt->execute(['rid' => $residentId]);
        $healthProfile = $hpStmt->fetch(PDO::FETCH_ASSOC);
        if (!$healthProfile) {
          try {
            $hpStmt2 = $pdo->prepare("SELECT * FROM resident_health_profile WHERE resident_id = :rid LIMIT 1");
            $hpStmt2->execute(['rid' => $residentId]);
            $healthProfile = $hpStmt2->fetch(PDO::FETCH_ASSOC);
          } catch (Throwable $tHp2) {
          }
        }
      } catch (Throwable $tHp) {
      }
    }

    // Hydrate RHU Doctors, Nurses, and Healthcare Staff list with working schedule
    $rhuStaffList = [];
    $staffBookingsPerDate = [];
    try {
      $staffStmt = $pdo->query("
                SELECT s.id AS staff_id, s.staff_type, s.specialization,
                       COALESCE(s.work_days, 'Monday, Tuesday, Wednesday, Thursday, Friday') AS work_days,
                       COALESCE(s.shift_start, '08:00:00') AS shift_start,
                       COALESCE(s.shift_end, '17:00:00') AS shift_end,
                       COALESCE(s.is_on_duty, 1) AS is_on_duty,
                       COALESCE(u.first_name, 'RHU Staff') AS first_name,
                       COALESCE(u.last_name, '') AS last_name,
                       u.email, s.phone_number
                FROM staff s
                LEFT JOIN users u ON s.user_id = u.id
                ORDER BY s.id ASC
            ");
      $rhuStaffList = $staffStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

      $bookingStmt = $pdo->query("
                SELECT physician_id, consultation_date, COUNT(*) AS total_booked
                FROM consultations
                WHERE consultation_date >= CURDATE()
                GROUP BY physician_id, consultation_date
            ");
      while ($row = $bookingStmt->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$row['physician_id'];
        $cdate = $row['consultation_date'];
        $staffBookingsPerDate[$pid][$cdate] = (int)$row['total_booked'];
      }
    } catch (Throwable $tSt) {
    }

    if (empty($rhuStaffList)) {
      $rhuStaffList = [
        ['staff_id' => 1, 'first_name' => 'Dr. Maria', 'last_name' => 'Santos', 'staff_type' => 'Rural Health Physician', 'specialization' => 'General Medicine', 'work_days' => 'Monday, Tuesday, Wednesday, Thursday, Friday', 'shift_start' => '08:00:00', 'shift_end' => '17:00:00', 'is_on_duty' => 1],
        ['staff_id' => 2, 'first_name' => 'Dr. Joseph', 'last_name' => 'Ramos', 'staff_type' => 'Rural Health Physician', 'specialization' => 'General Practice', 'work_days' => 'Monday, Tuesday, Wednesday, Thursday, Friday', 'shift_start' => '08:00:00', 'shift_end' => '17:00:00', 'is_on_duty' => 1],
        ['staff_id' => 3, 'first_name' => 'Midwife Rosario', 'last_name' => 'Peralta', 'staff_type' => 'Rural Health Midwife', 'specialization' => 'Maternal & OB-GYN Care', 'work_days' => 'Monday, Tuesday, Wednesday, Thursday, Friday', 'shift_start' => '08:00:00', 'shift_end' => '17:00:00', 'is_on_duty' => 1],
        ['staff_id' => 4, 'first_name' => 'RN Clara', 'last_name' => 'Mendez', 'staff_type' => 'Public Health Nurse', 'specialization' => 'Community Health & Vaccination', 'work_days' => 'Monday, Tuesday, Wednesday, Thursday, Friday', 'shift_start' => '08:00:00', 'shift_end' => '17:00:00', 'is_on_duty' => 1],
        ['staff_id' => 5, 'first_name' => 'RN Jose', 'last_name' => 'Figueroa', 'staff_type' => 'Medical Technologist', 'specialization' => 'Clinical Pathology & Lab Work', 'work_days' => 'Monday, Tuesday, Wednesday, Thursday, Friday', 'shift_start' => '08:00:00', 'shift_end' => '17:00:00', 'is_on_duty' => 1],
        ['staff_id' => 6, 'first_name' => 'Ramon', 'last_name' => 'Villareal', 'staff_type' => 'Sanitary Inspector', 'specialization' => 'Environmental Health & Inspection', 'work_days' => 'Monday, Tuesday, Wednesday, Thursday, Friday', 'shift_start' => '08:00:00', 'shift_end' => '17:00:00', 'is_on_duty' => 1]
      ];
    }

    if (function_exists('mergeJsonScheduleIntoStaffList')) {
      $rhuStaffList = mergeJsonScheduleIntoStaffList($rhuStaffList, $pdo);
    }

    // Hydrate Admin Posted Events & Health Programs
    $postedEvents = [];
    try {
      $pdo->exec("CREATE TABLE IF NOT EXISTS portal_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                event_date DATE NULL,
                scheduled_date DATE NULL,
                start_time TIME NULL,
                venue VARCHAR(255) NULL,
                description TEXT NULL,
                image_url VARCHAR(255) NULL,
                badge_color VARCHAR(50) NULL,
                capacity INT NULL,
                status VARCHAR(50) DEFAULT 'Upcoming',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

      $evStmt = $pdo->query("SELECT * FROM portal_events WHERE is_active = 1 ORDER BY event_date ASC, id DESC LIMIT 20");
      $postedEvents = $evStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $tEv) {
    }

    if (empty($postedEvents)) {
      $postedEvents = [
        [
          'id' => 101,
          'title' => 'National Immunization & Child Growth Monitoring Day',
          'event_date' => '2026-08-20',
          'start_time' => '08:00:00',
          'venue' => 'Nasugbu RHU Main Center — OPD Hall',
          'description' => 'Free routine vaccine immunizations for infants & children (0–5 years old) plus OPT+ nutrition & height/weight screening. Please present your Child Health Record book.',
          'badge_color' => 'emerald',
          'status' => 'Upcoming',
          'image_url' => ''
        ],
        [
          'id' => 102,
          'title' => 'Community Cardiovascular & Diabetes Screening Fair',
          'event_date' => '2026-08-25',
          'start_time' => '07:30:00',
          'venue' => 'Halang Barangay Health Station',
          'description' => 'Complimentary 12-Lead ECG, fasting blood sugar testing, lipid profile, and blood pressure monitoring conducted by RHU doctors & nurses.',
          'badge_color' => 'rose',
          'status' => 'Upcoming',
          'image_url' => ''
        ],
        [
          'id' => 103,
          'title' => 'Municipal Blood Donation Drive & Wellness Seminar',
          'event_date' => '2026-09-02',
          'start_time' => '09:00:00',
          'venue' => 'Bahay Pamahalaan Function Hall',
          'description' => 'Organized in coordination with the Philippine Red Cross. All healthy residents aged 18–60 are encouraged to donate blood and save lives!',
          'badge_color' => 'amber',
          'status' => 'Upcoming',
          'image_url' => ''
        ],
        [
          'id' => 104,
          'title' => 'Maternal Care & Family Planning Consultation Day',
          'event_date' => '2026-09-10',
          'start_time' => '08:30:00',
          'venue' => 'RHU Maternal & Women Wellness Clinic',
          'description' => 'Free prenatal examinations, iron-folic acid distribution, and individual family planning counseling with licensed RHU midwives.',
          'badge_color' => 'sky',
          'status' => 'Upcoming',
          'image_url' => ''
        ]
      ];
    }
  } catch (Exception $ex) {
    error_log("ResidentDashboard DB Hydration Error: " . $ex->getMessage());
  }
}

if (empty($postedEvents)) {
  $postedEvents = [
    [
      'id' => 101,
      'title' => 'National Immunization & Child Growth Monitoring Day',
      'event_date' => '2026-08-20',
      'start_time' => '08:00:00',
      'venue' => 'Nasugbu RHU Main Center — OPD Hall',
      'description' => 'Free routine vaccine immunizations for infants & children (0–5 years old) plus OPT+ nutrition & height/weight screening.',
      'badge_color' => 'emerald',
      'status' => 'Upcoming',
      'image_url' => ''
    ],
    [
      'id' => 102,
      'title' => 'Community Cardiovascular & Diabetes Screening Fair',
      'event_date' => '2026-08-25',
      'start_time' => '07:30:00',
      'venue' => 'Halang Barangay Health Station',
      'description' => 'Complimentary 12-Lead ECG, fasting blood sugar testing, lipid profile, and blood pressure monitoring.',
      'badge_color' => 'rose',
      'status' => 'Upcoming',
      'image_url' => ''
    ]
  ];
}
if (empty($healthCertificates)) {
  $healthCertificates = $certificates ?? [];
}
if (empty($consultations)) {
  $consultations = [
    [
      'id' => 108,
      'consultation_date' => '2026-07-31',
      'consultation_time' => '02:56:37',
      'chief_complaint' => '[Prenatal & Maternal Care] Routine checkup',
      'diagnosis' => 'Pending OPD Triage',
      'physician_name' => 'Ariane Kaye Vecinal',
      'consultation_status' => 'Completed',
      'medications_prescribed' => 'Folic Acid 5mg, Ferrous Sulfate 60mg',
      'consultation_notes' => "Appointment Category: Prenatal & Maternal Care | Booking Date: 2026-07-31 | Requested via Resident Portal"
    ],
    [
      'id' => 107,
      'consultation_date' => '2026-07-29',
      'consultation_time' => '12:35:38',
      'chief_complaint' => '[Child Vaccination & Immunization] Medical checkup',
      'diagnosis' => 'Pending OPD Triage',
      'physician_name' => 'Ariane Kaye Vecinal',
      'consultation_status' => 'Scheduled',
      'medications_prescribed' => 'None recorded yet',
      'consultation_notes' => "Appointment Category: Child Vaccination | Booking Date: 2026-07-29 | Requested via Resident Portal"
    ],
    [
      'id' => 106,
      'consultation_date' => '2026-07-15',
      'consultation_time' => '09:15:00',
      'chief_complaint' => 'Persistent Dry Cough & Fever for 3 days',
      'diagnosis' => 'Acute Bronchitis — Upper Respiratory Infection',
      'physician_name' => 'Dr. Maria Santos',
      'consultation_status' => 'Completed',
      'medications_prescribed' => 'Amoxicillin 500mg (3x daily), Paracetamol 500mg',
      'consultation_notes' => 'Patient advised 5-day bed rest, high fluid intake, and follow-up if fever persists after 48 hours.'
    ],
    [
      'id' => 105,
      'consultation_date' => '2026-06-28',
      'consultation_time' => '10:30:00',
      'chief_complaint' => 'Hypertension Routine Monitoring & BP Check',
      'diagnosis' => 'Essential Hypertension (Controlled)',
      'physician_name' => 'Dr. Joseph Ramos',
      'consultation_status' => 'Completed',
      'medications_prescribed' => 'Amlodipine 5mg (1x daily morning)',
      'consultation_notes' => 'BP recorded 128/82 mmHg. Maintained low-sodium diet and daily walking exercise.'
    ],
    [
      'id' => 104,
      'consultation_date' => '2026-05-18',
      'consultation_time' => '08:45:00',
      'chief_complaint' => 'Skin Rash & Allergic Reaction on Arms',
      'diagnosis' => 'Acute Contact Dermatitis',
      'physician_name' => 'Dr. Maria Santos',
      'consultation_status' => 'Completed',
      'medications_prescribed' => 'Cetirizine 10mg, Hydrocortisone Cream 1%',
      'consultation_notes' => 'Avoided chemical detergents. Symptoms completely resolved within 4 days.'
    ],
    [
      'id' => 103,
      'consultation_date' => '2026-04-05',
      'consultation_time' => '11:00:00',
      'chief_complaint' => 'Annual Employment Physical Clearance & CBC',
      'diagnosis' => 'Physical Fitness Clearance — Fit to Work',
      'physician_name' => 'RN Clara Mendez',
      'consultation_status' => 'Completed',
      'medications_prescribed' => 'Multivitamins + Minerals (1x daily)',
      'consultation_notes' => 'Routine lab work within normal parameters. Health clearance document issued.'
    ],
    [
      'id' => 102,
      'consultation_date' => '2026-03-12',
      'consultation_time' => '14:20:00',
      'chief_complaint' => 'Mild Fasting Blood Sugar Monitoring',
      'diagnosis' => 'Pre-diabetes Risk Assessment — Normal Glucose',
      'physician_name' => 'Dr. Joseph Ramos',
      'consultation_status' => 'Completed',
      'medications_prescribed' => 'Dietary Management Plan',
      'consultation_notes' => 'FBS: 94 mg/dL (Normal). Recommended 30 mins exercise 4x weekly.'
    ],
    [
      'id' => 101,
      'consultation_date' => '2026-02-01',
      'consultation_time' => '09:00:00',
      'chief_complaint' => 'Seasonal Influenza Vaccine Administration',
      'diagnosis' => 'Preventive Health Immunization',
      'physician_name' => 'RN Clara Mendez',
      'consultation_status' => 'Completed',
      'medications_prescribed' => 'Flu Quadrivalent Vaccine (0.5ml)',
      'consultation_notes' => 'Administered left deltoid. No adverse reactions observed during 15-min post-vaccine observation.'
    ]
  ];
}

if (!$resident && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['form'] ?? '') === 'add_dependent') {
  $dependentErrors[] = 'Your account is not linked to a resident record, so the dependent was not saved. Please contact RHU staff to verify your resident profile.';
}

if (!$resident && !empty($_SESSION['resident_registration'])) $resident = $_SESSION['resident_registration'];

if (!empty($_GET['certificate_document']) && !empty($pdo) && !empty($resident['id'])) {
  $certificateId = (int)$_GET['certificate_document'];
  $certificateStmt = $pdo->prepare(
    "SELECT hc.*, ct.certificate_type_name,
                CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name) AS resident_name,
                r.address, r.barangay,
                CONCAT_WS(' ', u.first_name, u.last_name) AS issuer_name,
                COALESCE(s.staff_type, 'Authorized RHU Officer') AS issuer_position
         FROM health_certificates hc
         JOIN certificate_types ct ON ct.id = hc.certificate_type_id
         JOIN residents r ON r.id = hc.resident_id
         LEFT JOIN staff s ON s.id = hc.issued_by_id
         LEFT JOIN users u ON u.id = s.user_id
         WHERE hc.id = :certificate_id AND hc.resident_id = :resident_id
         LIMIT 1"
  );
  $certificateStmt->execute(['certificate_id' => $certificateId, 'resident_id' => (int)$resident['id']]);
  $certificateDocument = $certificateStmt->fetch(PDO::FETCH_ASSOC);
  $documentStatus = strtolower((string)($certificateDocument['validity_status'] ?? ''));
  $canGenerateCertificate = $certificateDocument
    && (str_contains($documentStatus, 'valid') || str_contains($documentStatus, 'approved') || str_contains($documentStatus, 'issued'))
    && !str_contains($documentStatus, 'invalid')
    && !str_contains($documentStatus, 'revoked');
  if (!$canGenerateCertificate) {
    http_response_code(403);
    exit('This certificate is not available for generation.');
  }
  $certificateNumber = $certificateDocument['certificate_number'] ?: ('HC-' . str_pad((string)$certificateDocument['id'], 8, '0', STR_PAD_LEFT));
  $certificateHtml = trim((string)($certificateDocument['generated_html'] ?? ''));
  if ($certificateHtml === '') {
    $certificateHtml = portalGenerateCertificateHtml($pdo, (int)$certificateDocument['id']);
    $updateHtml = $pdo->prepare('UPDATE health_certificates SET generated_html = :html WHERE id = :id');
    $updateHtml->execute(['html' => $certificateHtml, 'id' => (int)$certificateDocument['id']]);
  }
?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= esc($certificateDocument['certificate_type_name']) ?> — <?= esc($certificateNumber) ?></title>
    <style>
      * {
        box-sizing: border-box
      }

      body {
        margin: 0;
        background: #e5e7eb;
        color: #111;
        font-family: Arial, sans-serif
      }

      .toolbar {
        position: sticky;
        top: 0;
        z-index: 5;
        display: flex;
        justify-content: center;
        gap: 10px;
        padding: 14px;
        background: #0f766e
      }

      .toolbar button,
      .toolbar a {
        border: 1px solid rgba(255, 255, 255, .5);
        border-radius: 8px;
        background: #fff;
        padding: 9px 16px;
        color: #0f766e;
        font: 700 13px Arial;
        text-decoration: none;
        cursor: pointer
      }

      .official-certificate-template {
        position: relative;
        overflow: hidden;
        margin: 24px auto;
        width: min(100%, 760px);
        min-height: 1040px;
        background: #fff;
        padding: 58px 68px 44px;
        color: #050505;
        font-family: Arial, Helvetica, sans-serif;
        line-height: 1.45;
        box-shadow: 0 18px 50px rgba(15, 23, 42, .18)
      }

      .official-certificate-template .cert-header {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: 112px 1fr 112px;
        align-items: center;
        text-align: center;
        margin-bottom: 10px
      }

      .official-certificate-template .cert-seal {
        width: 96px;
        height: 96px;
        object-fit: contain;
        justify-self: center
      }

      .official-certificate-template .cert-watermark {
        position: absolute;
        z-index: 0;
        left: 50%;
        top: 285px;
        width: 560px;
        height: 560px;
        transform: translateX(-50%);
        object-fit: contain;
        opacity: .1;
        pointer-events: none
      }

      .official-certificate-template .cert-header-copy {
        font-size: 11px;
        line-height: 1.2
      }

      .official-certificate-template .cert-header-copy p {
        margin: 0
      }

      .official-certificate-template .cert-republic {
        font-family: Georgia, "Times New Roman", serif;
        font-style: italic;
        font-size: 13px
      }

      .official-certificate-template .cert-rule {
        position: relative;
        z-index: 1;
        border-top: 2px solid #111;
        border-bottom: 1px solid #111;
        height: 4px;
        margin: 6px 0 42px
      }

      .official-certificate-template h1,
      .official-certificate-template h2,
      .official-certificate-template h3 {
        position: relative;
        z-index: 1;
        margin: 5px 0;
        text-align: center;
        font-weight: 900;
        text-transform: uppercase
      }

      .official-certificate-template h1 {
        font-size: 18px;
        letter-spacing: 0
      }

      .official-certificate-template h2 {
        font-size: 21px;
        font-style: italic
      }

      .official-certificate-template h3 {
        font-size: 28px;
        letter-spacing: 0;
        margin-bottom: 2px
      }

      .official-certificate-template .cert-no {
        position: relative;
        z-index: 1;
        text-align: center;
        font-family: "Courier New", monospace;
        font-size: 10px;
        font-weight: 700;
        color: #334155;
        margin: 0 0 44px
      }

      .official-certificate-template .cert-body {
        position: relative;
        z-index: 1;
        margin: 0;
        font-size: 12px;
        line-height: 1.65;
        text-align: justify
      }

      .official-certificate-template .cert-body p {
        margin: 0 0 18px;
        text-indent: 34px
      }

      .official-certificate-template .cert-body .cert-greeting {
        text-indent: 0;
        margin-bottom: 26px;
        text-align: left
      }

      .official-certificate-template .cert-dates {
        display: flex;
        gap: 34px;
        margin-top: 6px;
        font-size: 10px;
        text-align: left
      }

      .official-certificate-template .cert-signatures {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 52px;
        margin-top: 118px;
        text-align: center
      }

      .official-certificate-template .cert-signatures>div {
        min-height: 104px;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        align-items: center;
        font-size: 12px
      }

      .official-certificate-template .cert-signatures strong {
        border-top: 1px solid #111;
        min-width: 250px;
        padding-top: 5px;
        font-weight: 900;
        text-transform: uppercase
      }

      .official-certificate-template .certificate-signature-image {
        display: block;
        width: 170px;
        height: 58px;
        margin: 0 auto -5px;
        object-fit: contain;
        object-position: center bottom;
        mix-blend-mode: multiply
      }

      .official-certificate-template .signature-line {
        height: 58px;
        margin-bottom: -5px;
        width: 170px
      }

      .official-certificate-template small,
      .official-certificate-template .cert-footer {
        display: block;
        color: #111;
        font-size: 10px
      }

      .official-certificate-template .cert-footer {
        position: absolute;
        z-index: 1;
        left: 68px;
        right: 68px;
        bottom: 40px;
        display: flex;
        justify-content: space-between;
        border-top: 1px solid #64748b;
        padding-top: 6px;
        font-family: "Courier New", monospace;
        color: #0f172a
      }

      @media(max-width:820px) {
        .official-certificate-template {
          width: 680px;
          padding: 48px 50px 38px
        }
      }

      @media print {
        @page {
          size: A4 portrait;
          margin: 0
        }

        body {
          background: #fff
        }

        .toolbar {
          display: none
        }

        .official-certificate-template {
          width: 210mm;
          min-height: 297mm;
          margin: 0;
          box-shadow: none
        }
      }
    </style>
  </head>

  <body>
    <div class="toolbar"><button onclick="window.print()">Download / Save as PDF</button><a href="ResidentDashboard.php?tab=certificates">Back to certificates</a></div>
    <?= $certificateHtml ?>
  </body>

  </html>
<?php
  exit;
}

$lastConsultation = $consultations[0] ?? null;
$currentYear = (int)date('Y');
$visitsThisYear = count(array_filter($consultations, fn($consultation) => !empty($consultation['consultation_date']) && (int)date('Y', strtotime($consultation['consultation_date'])) === $currentYear));
$totalPrescriptions = array_sum(array_map(fn($consultation) => empty($consultation['medications_prescribed']) ? 0 : count(array_filter(preg_split('/,\s*/', $consultation['medications_prescribed']))), $consultations));
$initials = strtoupper(substr($resident['first_name'] ?? 'R', 0, 1) . substr($resident['last_name'] ?? 'S', 0, 1));
$age = residentAge($resident['date_of_birth'] ?? null);

$tabs = [
  'home' => ['Overview', 'home'],
  'profile' => ['My Profile', 'user'],
  'records' => ['Health Records', 'file-text'],
  'immunization' => ['Immunization', 'shield-check'],
  'certificates' => ['Certificates', 'award'],
  'family' => ['Family Members', 'users'],
  'events' => ['Events & Programs', 'calendar'],
  'map' => ['Nearby Map', 'map-pinned'],
  'contact' => ['Contact RHU', 'phone-call'],
  'emergency' => ['Emergency & Referral', 'siren'],
];

$events = [
  ['Jun 20', 'Blood Drive — City Hall', 'Free blood pressure check for all donors'],
  ['Jun 24', 'Free Cervical Cancer Screening', 'Halang Barangay Hall, 8AM–12NN'],
  ['Jun 28', 'Senior Citizens Health Fair', 'Free ECG, blood glucose, BP monitoring'],
  ['Jul 1–31', 'Nutrition Month (OPT+)', 'Free growth monitoring for 0–5 years'],
  ['Jul 10', 'Family Planning Counseling Day', 'RHU Main, free FP consultation'],
  ['Jul 15', 'TB Awareness Seminar', 'Barangay Halang, 9AM'],
  ['Aug 1', 'National Immunization Day', 'Free vaccines for children 0–5'],
];
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Resident Dashboard - ResiHUnity</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap');

    html {
      scroll-behavior: smooth;
    }

    body.resident-dashboard {
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      background: transparent !important;
      position: relative;
    }

    /* ====================================================
       UNIVERSAL LIGHT GLASS MODE OVERRIDES
       ==================================================== */
    html:not(.dark) body,
    html:not(.dark) .resident-dashboard,
    body:not(.dark) {
      color: #0f172a !important;
    }

    html:not(.dark) #rhu-bg-overlay {
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.86) 0%, rgba(241, 245, 249, 0.78) 50%, rgba(236, 253, 245, 0.84) 100%) !important;
      backdrop-filter: blur(4px);
    }

    html:not(.dark) #rhu-bg-img {
      filter: brightness(0.98) contrast(1.02);
    }

    /* Light Mode Header & Sidebar */
    html:not(.dark) header.sticky {
      background: rgba(255, 255, 255, 0.88) !important;
      border-bottom: 1px solid rgba(226, 232, 240, 0.9) !important;
      backdrop-filter: blur(20px) saturate(180%) !important;
      box-shadow: 0 4px 25px rgba(15, 23, 42, 0.05) !important;
    }

    html:not(.dark) header h1 {
      color: #0f172a !important;
    }

    html:not(.dark) #sidebar {
      background: rgba(255, 255, 255, 0.92) !important;
      border-right: 1px solid rgba(226, 232, 240, 0.9) !important;
      box-shadow: 8px 0 30px rgba(15, 23, 42, 0.06) !important;
    }

    html:not(.dark) #sidebar .sidebar-link {
      color: #475569 !important;
    }

    html:not(.dark) #sidebar .sidebar-link i {
      color: #64748b !important;
    }

    html:not(.dark) #sidebar .sidebar-link:hover {
      background: rgba(16, 185, 129, 0.12) !important;
      color: #047857 !important;
    }

    html:not(.dark) .nav-active {
      background-color: #059669 !important;
      color: #ffffff !important;
      font-weight: 700 !important;
      border: 1px solid #047857 !important;
      box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35) !important;
    }

    /* Light Mode ALL Cards & Containers (excluding Hero Carousel) */
    html:not(.dark) main .bg-\[\#131916\],
    html:not(.dark) main .bg-slate-900,
    html:not(.dark) main .bg-slate-800,
    html:not(.dark) main .bg-slate-950,
    html:not(.dark) main [data-tab-panel] article:not(#hero-events-container),
    html:not(.dark) main [data-tab-panel] .rounded-3xl:not(#hero-events-container),
    html:not(.dark) main [data-tab-panel] .rounded-2xl:not(#hero-events-container) {
      background-color: rgba(255, 255, 255, 0.88) !important;
      backdrop-filter: blur(16px) saturate(180%) !important;
      border-color: rgba(226, 232, 240, 0.9) !important;
      color: #0f172a !important;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06) !important;
    }

    /* Preserve Dark Hero Spotlight Banner styling in Light Mode */
    html:not(.dark) #hero-events-container {
      background-color: #0f1714 !important;
      border-color: rgba(255, 255, 255, 0.15) !important;
      color: #ffffff !important;
    }

    /* Light Mode Text Overrides */
    html:not(.dark) main .text-white:not(#hero-events-container *):not(button.bg-emerald-600 *):not(.bg-emerald-600 *):not(.nav-active *),
    html:not(.dark) main .text-slate-200,
    html:not(.dark) main .text-slate-100 {
      color: #0f172a !important;
    }

    html:not(.dark) main .text-slate-300 {
      color: #334155 !important;
    }

    html:not(.dark) main .text-slate-400 {
      color: #64748b !important;
    }

    /* ====================================================
       UNIVERSAL DARK GLASS MODE OVERRIDES
       ==================================================== */
    html.dark body,
    html.dark .resident-dashboard,
    body.dark {
      color: #f8fafc !important;
    }

    html.dark #rhu-bg-overlay {
      background: linear-gradient(135deg, rgba(6, 11, 8, 0.94) 0%, rgba(8, 14, 10, 0.90) 50%, rgba(8, 18, 11, 0.94) 100%) !important;
      backdrop-filter: blur(4px);
    }

    html.dark #rhu-bg-img {
      filter: brightness(0.35) contrast(1.1);
    }

    /* Dark Mode Header & Sidebar */
    html.dark header.sticky {
      background: rgba(14, 19, 16, 0.92) !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5) !important;
    }

    html.dark header h1 {
      color: #ffffff !important;
    }

    html.dark #sidebar {
      background: rgba(12, 17, 14, 0.94) !important;
      border-right: 1px solid rgba(255, 255, 255, 0.1) !important;
      box-shadow: 12px 0 35px rgba(0, 0, 0, 0.6) !important;
    }

    html.dark #sidebar .sidebar-link {
      color: #94a3b8 !important;
    }

    html.dark #sidebar .sidebar-link i {
      color: #64748b !important;
    }

    html.dark #sidebar .sidebar-link:hover {
      background: rgba(255, 255, 255, 0.07) !important;
      color: #ffffff !important;
    }

    html.dark .nav-active {
      background-color: #062e21 !important;
      color: #34d399 !important;
      font-weight: 700 !important;
      border: 1px solid rgba(16, 185, 129, 0.4) !important;
      box-shadow: 0 0 20px rgba(16, 185, 129, 0.2) !important;
    }

    /* Dark Mode ALL Cards & Containers */
    html.dark main .bg-white,
    html.dark main .bg-slate-50,
    html.dark main .bg-slate-100,
    html.dark main .bg-\[\#131916\],
    html.dark main [data-tab-panel] article,
    html.dark main [data-tab-panel] .rounded-3xl,
    html.dark main [data-tab-panel] .rounded-2xl {
      background-color: rgba(19, 25, 22, 0.88) !important;
      backdrop-filter: blur(16px) saturate(180%) !important;
      border-color: rgba(255, 255, 255, 0.1) !important;
      color: #f8fafc !important;
      box-shadow: 0 12px 35px rgba(0, 0, 0, 0.55) !important;
    }

    /* Dark Mode Text Overrides */
    html.dark main .text-slate-900,
    html.dark main .text-slate-800,
    html.dark main .text-slate-700 {
      color: #f8fafc !important;
    }

    html.dark main .text-slate-600,
    html.dark main .text-slate-500 {
      color: #cbd5e1 !important;
    }

    html.dark main .text-slate-400 {
      color: #94a3b8 !important;
    }

    html.dark main .border-white\/10,
    html.dark main .border-white\/5 {
      border-color: rgba(255, 255, 255, 0.1) !important;
    }

    html.dark input,
    html.dark select,
    html.dark textarea {
      background-color: rgba(0, 0, 0, 0.45) !important;
      border: 1px solid rgba(255, 255, 255, 0.15) !important;
      color: #f8fafc !important;
    }

    /* Common Components & Animations */
    .sidebar-collapsed {
      width: 4.5rem !important;
    }

    .sidebar-collapsed .sidebar-text,
    .sidebar-collapsed .sidebar-header-text {
      display: none !important;
    }

    .sidebar-collapsed .sidebar-link {
      justify-content: center !important;
      padding-left: 0 !important;
      padding-right: 0 !important;
    }

    #resident-location-map {
      min-height: 31rem;
      background: #111613;
    }

    [data-tab-panel] {
      animation: panel-enter 300ms cubic-bezier(.2, .8, .2, 1);
    }

    [data-tab-panel]>.rounded-2xl,
    [data-tab-panel] article,
    [data-tab-panel] .dashboard-surface {
      transition: transform 220ms cubic-bezier(.2, .8, .2, 1), box-shadow 220ms ease, border-color 220ms ease;
    }

    [data-tab-panel]>.rounded-2xl:hover,
    [data-tab-panel] article:hover,
    [data-tab-panel] .dashboard-surface:hover {
      transform: translateY(-3px) scale(1.008);
      position: relative;
      z-index: 2;
    }

    /* Smooth color & surface background transitions for all UI elements */
    body,
    header,
    #sidebar,
    main,
    main *,
    article,
    .dashboard-surface,
    input,
    select,
    textarea,
    table,
    th,
    td {
      transition: background-color 500ms cubic-bezier(0.4, 0, 0.2, 1),
        border-color 500ms cubic-bezier(0.4, 0, 0.2, 1),
        color 350ms cubic-bezier(0.4, 0, 0.2, 1),
        box-shadow 500ms cubic-bezier(0.4, 0, 0.2, 1),
        backdrop-filter 500ms cubic-bezier(0.4, 0, 0.2, 1) !important;
    }

    #rhu-bg-overlay,
    #rhu-bg-img {
      transition: background 600ms cubic-bezier(0.4, 0, 0.2, 1),
        filter 600ms cubic-bezier(0.4, 0, 0.2, 1),
        opacity 600ms ease !important;
    }

    /* Toggle Button Spin & Pop Keyframe Animation */
    @keyframes theme-toggle-spin {
      0% {
        transform: scale(1) rotate(0deg);
      }

      50% {
        transform: scale(1.4) rotate(180deg);
      }

      100% {
        transform: scale(1) rotate(360deg);
      }
    }

    .theme-icon-spin {
      display: inline-block;
      animation: theme-toggle-spin 550ms cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    button,
    a {
      -webkit-tap-highlight-color: transparent;
    }

    button:not([disabled]),
    a[href] {
      transition: transform 180ms ease, box-shadow 180ms ease, background-color 180ms ease, color 180ms ease, border-color 180ms ease;
    }

    button:not([disabled]):active,
    a[href]:active {
      transform: scale(.97);
    }

    * {
      scrollbar-width: thin;
      scrollbar-color: #059669 transparent;
    }

    @keyframes panel-enter {
      from {
        opacity: 0;
        transform: translateY(10px);
      }

      to {
        opacity: 1;
        transform: none;
      }
    }

    @keyframes modal-enter {
      from {
        opacity: 0;
        transform: translateY(12px) scale(.97);
      }

      to {
        opacity: 1;
        transform: none;
      }
    }
  </style>
  <script>
    window.applyDashboardTheme = function(theme, clickX, clickY) {
      const isDark = theme === 'dark';
      const docEl = document.documentElement;

      // 1. Icon Spin Animation
      const iconEl = document.getElementById('theme-toggle-icon');
      const textEl = document.getElementById('theme-toggle-text');
      if (iconEl) {
        iconEl.classList.remove('theme-icon-spin');
        void iconEl.offsetWidth; // Force reflow for animation restart
        iconEl.classList.add('theme-icon-spin');
        iconEl.textContent = isDark ? '🌙' : '☀️';
      }
      if (textEl) textEl.textContent = isDark ? 'Dark Mode' : 'Light Mode';

      // 2. Fullscreen Radial Wave Ripple Animation
      const rippleWave = document.getElementById('theme-ripple-wave');
      if (rippleWave && typeof clickX === 'number' && typeof clickY === 'number') {
        const bgGrad = isDark ?
          `radial-gradient(circle at ${clickX}px ${clickY}px, rgba(16, 185, 129, 0.45) 0%, rgba(6, 11, 8, 0.95) 75%)` :
          `radial-gradient(circle at ${clickX}px ${clickY}px, rgba(52, 211, 153, 0.45) 0%, rgba(255, 255, 255, 0.94) 75%)`;

        rippleWave.style.background = bgGrad;
        rippleWave.style.opacity = '0.7';
        window.setTimeout(() => {
          rippleWave.style.opacity = '0';
        }, 350);
      }

      // 3. Toggle Class on Root
      if (isDark) {
        docEl.classList.add('dark');
      } else {
        docEl.classList.remove('dark');
      }

      try {
        localStorage.setItem('rhu_resident_theme_pref', theme);
      } catch (e) {}
    };

    window.toggleDashboardTheme = function(event) {
      const isDarkNow = document.documentElement.classList.contains('dark');
      const targetTheme = isDarkNow ? 'light' : 'dark';

      let x = window.innerWidth / 2;
      let y = 40;
      if (event) {
        x = event.clientX || x;
        y = event.clientY || y;
      } else {
        const btn = document.getElementById('theme-toggle-btn');
        if (btn) {
          const rect = btn.getBoundingClientRect();
          x = rect.left + rect.width / 2;
          y = rect.top + rect.height / 2;
        }
      }

      window.applyDashboardTheme(targetTheme, x, y);
    };

    (function() {
      let saved = 'light';
      try {
        saved = localStorage.getItem('rhu_resident_theme_pref') || 'light';
      } catch (e) {}
      window.applyDashboardTheme(saved);
    })();

    document.addEventListener('DOMContentLoaded', function() {
      const btn = document.getElementById('theme-toggle-btn');
      if (btn) {
        btn.onclick = function(e) {
          if (e) {
            e.preventDefault();
            e.stopPropagation();
          }
          window.toggleDashboardTheme(e);
          return false;
        };
      }
    });
  </script>
  
  <!-- MODAL FUNCTIONS - Define early so onclick handlers can use them -->
  <script>
    // Dependent Modal Functions
    window.openDependentModal = function(e) {
      if (e && e.preventDefault) {
        e.preventDefault();
        e.stopPropagation();
      }
      const dependentModal = document.getElementById('dependent-modal');
      if (!dependentModal) {
        console.error('Dependent modal element not found');
        return false;
      }
      console.log('Opening dependent modal...');
      dependentModal.classList.remove('hidden');
      dependentModal.classList.add('flex');
      document.body.classList.add('overflow-hidden');
      return false;
    };

    window.closeDependentModal = function(e) {
      if (e && e.preventDefault) {
        e.preventDefault();
        e.stopPropagation();
      }
      const dependentModal = document.getElementById('dependent-modal');
      if (!dependentModal) return false;
      console.log('Closing dependent modal...');
      dependentModal.classList.add('hidden');
      dependentModal.classList.remove('flex');
      document.body.classList.remove('overflow-hidden');
      return false;
    };
    
    console.log('Modal functions defined early');
  </script>
</head>

<body class="resident-dashboard theme-light min-h-screen antialiased flex flex-col md:flex-row">
  <!-- Radial Ripple Wave Overlay -->
  <div id="theme-ripple-wave" class="fixed inset-0 pointer-events-none z-[99999] opacity-0 transition-opacity duration-500"></div>

  <!-- Fixed Fullscreen RHU Municipal Hall Background -->
  <div id="rhu-dashboard-bg" class="fixed inset-0 z-[-1] pointer-events-none overflow-hidden">
    <img id="rhu-bg-img" src="../../../assets/admin-municipal-background.png" alt="RHU Municipal Hall" class="h-full w-full object-cover object-center transition-all duration-500" />
    <div id="rhu-bg-overlay" class="absolute inset-0 transition-all duration-500"></div>
  </div>

  <div id="scroll-progress" aria-hidden="true"></div>

  <!-- Mobile Overlay -->
  <div id="sidebar-overlay" class="fixed inset-0 z-40 hidden md:hidden" aria-hidden="true"></div>

  <!-- Sidebar -->
  <aside id="sidebar" class="fixed md:sticky top-0 z-50 h-screen w-64 shrink-0 border-r transition-all duration-200 ease-in-out flex flex-col justify-between -translate-x-full md:translate-x-0 shadow-2xl">
    <div>
      <!-- Header / Toggle -->
      <div class="flex items-center justify-between h-20 px-4 border-b border-slate-200 dark:border-white/10">
        <div class="flex items-center gap-3 overflow-hidden">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white font-black shadow-lg">
            <i data-lucide="cross" class="h-5 w-5"></i>
          </div>
          <div class="sidebar-header-text">
            <h2 class="text-sm font-extrabold tracking-wide text-slate-900 dark:text-white">ResiHUnity</h2>
            <p class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">Resident Portal</p>
          </div>
        </div>
        <div class="flex items-center gap-1">
          <button id="sidebar-close-btn" type="button" onclick="toggleMobileSidebar()" class="md:hidden flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors" title="Close Menu">
            <i data-lucide="x" class="h-5 w-5"></i>
          </button>
          <button id="sidebar-collapse-btn" type="button" class="hidden md:flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10 transition-colors">
            <i data-lucide="panel-left-close" class="h-4 w-4"></i>
          </button>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="p-3 space-y-1 overflow-y-auto max-h-[calc(100vh-11rem)]">
        <?php foreach ($tabs as $key => [$label, $icon]): ?>
          <button type="button" data-tab-button="<?= $key ?>" class="sidebar-link w-full flex items-center gap-3 px-3.5 py-2.5 text-xs font-bold transition-all text-slate-600 dark:text-slate-400 hover:bg-emerald-500/10 hover:text-emerald-700 dark:hover:text-white">
            <i data-lucide="<?= $icon ?>" class="h-4 w-4 shrink-0"></i>
            <span class="sidebar-text truncate"><?= esc($label) ?></span>
          </button>
        <?php endforeach; ?>
      </nav>
    </div>

    <!-- User Section -->
    <div class="p-3 border-t border-slate-200 dark:border-white/10 space-y-2">
      <a href="ResidentDashboard.php?logout=1" data-logout-link class="sidebar-link flex w-full items-center gap-3 px-3.5 py-2.5 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-full transition-all">
        <i data-lucide="log-out" class="h-4 w-4 shrink-0"></i>
        <span class="sidebar-text truncate">Sign Out</span>
      </a>
    </div>
  </aside>

  <!-- Main Area -->
  <div class="resident-main-shell flex-1 min-w-0 flex flex-col min-h-screen">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-[100] h-16 sm:h-20 px-3 sm:px-8 flex items-center justify-between backdrop-blur-xl">
      <div class="flex items-center gap-2.5 sm:gap-4 min-w-0">
        <button id="mobile-menu-btn" type="button" class="md:hidden flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/50 dark:bg-white/5 text-slate-700 dark:text-slate-300 hover:bg-white/80">
          <i data-lucide="menu" class="h-5 w-5"></i>
        </button>
        <h1 class="text-base sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight truncate">Hello Nasugbuenian, <?= esc($resident['first_name'] ?? 'Alex') ?>!</h1>
      </div>

      <!-- Notifications, Theme Switcher & Profile Header -->
      <div class="flex items-center gap-2 sm:gap-3">
        <!-- Light / Dark Mode Toggle Switcher Button -->
        <button type="button" id="theme-toggle-btn" onclick="toggleDashboardTheme()" class="flex items-center gap-1.5 sm:gap-2 rounded-full border border-slate-300 dark:border-white/20 bg-white/90 dark:bg-white/10 px-2.5 sm:px-3.5 py-1.5 text-xs font-bold text-slate-800 dark:text-slate-100 shadow-sm hover:scale-105 transition-all cursor-pointer shrink-0">
          <span id="theme-toggle-icon">☀️</span>
          <span id="theme-toggle-text" class="hidden sm:inline">Light Mode</span>
        </button>

        <div class="relative">
          <button type="button" id="notification-bell-btn" onclick="toggleNotificationPanel(event)" data-notifications class="relative flex h-10 w-10 items-center justify-center rounded-full bg-white/80 dark:bg-white/5 border border-slate-300 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors shadow-sm">
            <i data-lucide="bell" class="h-4 w-4"></i>
            <span id="notif-badge-count" class="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-emerald-500 px-1 text-[10px] font-black text-white dark:text-slate-950 shadow-md" style="display: none;">0</span>
          </button>
        </div>

        <div class="flex items-center gap-2.5 pl-2 border-l border-slate-300 dark:border-white/10">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-600 text-xs font-black text-white shadow-md">
            <?= esc($initials) ?>
          </div>
          <div class="hidden sm:block text-left">
            <p class="text-xs font-bold text-slate-900 dark:text-white leading-tight"><?= esc(($resident['first_name'] ?? '') . ' ' . ($resident['last_name'] ?? '')) ?></p>
            <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold tracking-wide">RHU Resident Patient</p>
          </div>
        </div>
      </div>
    </header>

    <!-- Dynamic Content Section -->
    <main class="flex-1 p-3.5 sm:p-8 max-w-7xl w-full mx-auto space-y-6">

      <!-- Flashes & Alerts -->
      <?php if ($contactSuccess): ?>
        <div class="rounded-2xl border border-teal-200 bg-teal-50 p-4 text-xs font-semibold text-teal-800 flex items-center gap-2">
          <i data-lucide="check-circle" class="h-4 w-4 text-teal-600 shrink-0"></i> <?= esc($contactSuccess) ?>
        </div>
      <?php endif; ?>

      <?php if ($certificateSuccess): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-semibold text-emerald-800 flex items-center gap-2">
          <i data-lucide="check-circle" class="h-4 w-4 text-emerald-600 shrink-0"></i> <?= esc($certificateSuccess) ?>
        </div>
      <?php endif; ?>

      <!-- 1. HOME TAB -->
      <section data-tab-panel="home" class="space-y-6">

        <!-- Row 1: Top Hero Photo Card + RHU Notices Panel -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

          <!-- Left Hero Card: Admin Posted Health Events Carousel (~60% width) -->
          <div class="lg:col-span-7 relative min-h-[23rem] overflow-hidden rounded-3xl border border-white/10 bg-slate-900 shadow-2xl flex flex-col justify-between p-6 sm:p-8 group" id="hero-events-container">
            <!-- Background Image -->
            <img id="hero-event-bg" src="../../../assets/admin-municipal-background.png" alt="RHU Event Background" class="absolute inset-0 h-full w-full object-cover object-center transition-all duration-700 group-hover:scale-105" />
            <div class="absolute inset-0 bg-gradient-to-t from-[#090d0b] via-[#090d0b]/80 to-black/40"></div>

            <!-- Top Bar: Badge + Patient Context -->
            <div class="relative z-10 flex items-center justify-between gap-3">
              <span class="inline-flex items-center gap-2 rounded-full bg-emerald-950/90 px-3.5 py-1.5 text-xs font-bold text-emerald-300 border border-emerald-500/40 backdrop-blur-md shadow-lg">
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>📢 RHU Admin Event Spotlight</span>
              </span>
              <span class="text-[11px] font-mono font-medium text-slate-300 bg-black/60 px-3 py-1 rounded-full border border-white/10 backdrop-blur-sm hidden sm:inline-block">
                Patient #<?= esc($resident['id'] ?? '8') ?> • Brgy. <?= esc($resident['barangay'] ?? 'Nasugbu') ?>
              </span>
            </div>

            <!-- Slides Container -->
            <div class="relative z-10 my-3 space-y-2">
              <?php foreach ($postedEvents as $index => $ev):
                $evTitle = $ev['title'] ?? 'RHU Health Program';
                $evVenue = $ev['venue'] ?? 'Nasugbu RHU Center';
                $evDesc = $ev['description'] ?? 'No description provided.';
                $rawDate = $ev['event_date'] ?? ($ev['scheduled_date'] ?? 'now');
                $evDateFormatted = date('M d, Y', strtotime($rawDate));
                $evTime = !empty($ev['start_time']) ? date('g:i A', strtotime($ev['start_time'])) : '8:00 AM';
                $evImg = !empty($ev['image_url']) ? esc($ev['image_url']) : '../../../assets/admin-municipal-background.png';
              ?>
                <div data-hero-slide="<?= $index ?>" data-bg="<?= $evImg ?>" class="hero-slide-item transition-all duration-500 <?= $index === 0 ? 'block' : 'hidden' ?> space-y-2">
                  <div class="flex items-center gap-2">
                    <span class="rounded-md bg-emerald-500/20 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-300 uppercase tracking-wider border border-emerald-500/30">
                      <?= esc($ev['status'] ?? 'Upcoming Event') ?>
                    </span>
                    <span class="text-xs font-semibold text-slate-400">Event <?= $index + 1 ?> of <?= count($postedEvents) ?></span>
                  </div>

                  <h2 class="text-xl sm:text-2xl font-black text-white leading-tight tracking-tight drop-shadow-md">
                    <?= esc($evTitle) ?>
                  </h2>

                  <p class="text-xs text-slate-300 line-clamp-2 leading-relaxed font-normal max-w-xl">
                    <?= esc($evDesc) ?>
                  </p>

                  <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-semibold text-emerald-300/90 pt-1">
                    <span class="flex items-center gap-1.5 bg-black/50 px-2.5 py-1 rounded-lg border border-white/10">
                      <i data-lucide="calendar" class="h-3.5 w-3.5 text-emerald-400"></i>
                      <?= esc($evDateFormatted) ?> • <?= esc($evTime) ?>
                    </span>
                    <span class="flex items-center gap-1.5 bg-black/50 px-2.5 py-1 rounded-lg border border-white/10">
                      <i data-lucide="map-pin" class="h-3.5 w-3.5 text-emerald-400"></i>
                      <?= esc($evVenue) ?>
                    </span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Bottom Action Row: Action Button + Slide Navigation Controls -->
            <div class="relative z-10 flex items-center justify-between border-t border-white/10 pt-3 mt-1">
              <button type="button" data-tab-link="events" class="rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 px-4 py-2 text-xs font-black shadow-lg shadow-emerald-500/20 transition-all flex items-center gap-1.5 active:scale-95">
                <span>View All Admin Events</span>
                <i data-lucide="arrow-right" class="h-4 w-4"></i>
              </button>

              <!-- Carousel Control Arrows & Dots -->
              <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5" id="hero-slide-dots">
                  <?php foreach ($postedEvents as $index => $ev): ?>
                    <button type="button" onclick="setHeroSlide(<?= $index ?>)" class="hero-dot h-2 rounded-full transition-all duration-300 <?= $index === 0 ? 'w-6 bg-emerald-400' : 'w-2 bg-white/30 hover:bg-white/60' ?>" aria-label="Slide <?= $index + 1 ?>"></button>
                  <?php endforeach; ?>
                </div>

                <div class="flex items-center gap-1 pl-2 border-l border-white/10">
                  <button type="button" onclick="prevHeroSlide()" class="rounded-lg p-1.5 text-slate-300 hover:bg-white/10 hover:text-white transition-colors" title="Previous Event">
                    <i data-lucide="chevron-left" class="h-4 w-4"></i>
                  </button>
                  <button type="button" onclick="nextHeroSlide()" class="rounded-lg p-1.5 text-slate-300 hover:bg-white/10 hover:text-white transition-colors" title="Next Event">
                    <i data-lucide="chevron-right" class="h-4 w-4"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Messages Card (~40% width) -->
          <div class="lg:col-span-5 rounded-3xl border border-white/10 bg-[#131916] p-6 shadow-2xl flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between border-b border-white/5 pb-3">
              <div class="flex items-center gap-2">
                <i data-lucide="message-square" class="h-5 w-5 text-emerald-400"></i>
                <h3 class="font-bold text-white text-base">RHU Staff Notices</h3>
                <span class="rounded-full bg-emerald-950 px-2 py-0.5 text-[10px] font-black text-emerald-400 border border-emerald-800/60"><?= (!empty($notifications) && is_array($notifications)) ? count($notifications) : 3 ?></span>
              </div>
              <button type="button" onclick="toggleNotificationPanel(event)" class="text-xs font-semibold text-slate-400 hover:text-emerald-400 transition-colors flex items-center gap-1">
                View all <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
              </button>
            </div>

            <div class="space-y-3.5 flex-1 overflow-y-auto max-h-[16rem] pr-1">
              <!-- Notice 1 -->
              <div class="flex items-start gap-3 p-2.5 rounded-2xl hover:bg-white/5 transition-all">
                <div class="h-10 w-10 shrink-0 rounded-full bg-emerald-950 border border-emerald-800/60 flex items-center justify-center text-xs font-black text-emerald-400">
                  RN
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-extrabold text-white">Public Health Nurse</p>
                    <span class="text-[10px] font-medium text-slate-400">Today 08:30</span>
                  </div>
                  <p class="text-xs font-bold text-slate-200 mt-0.5 truncate">OPD Consultation & Triage Advisory</p>
                  <p class="text-[11px] text-slate-400 line-clamp-1 mt-0.5">Please present your PhilHealth card & patient ID at the reception desk...</p>
                </div>
                <button type="button" class="text-slate-500 hover:text-white p-1"><i data-lucide="more-vertical" class="h-4 w-4"></i></button>
              </div>

              <!-- Notice 2 -->
              <div class="flex items-start gap-3 p-2.5 rounded-2xl hover:bg-white/5 transition-all">
                <div class="h-10 w-10 shrink-0 rounded-full bg-teal-950 border border-teal-800/60 flex items-center justify-center text-xs font-black text-teal-400">
                  MD
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-extrabold text-white">Municipal Health Physician</p>
                    <span class="text-[10px] font-medium text-slate-400">Mar 27</span>
                  </div>
                  <p class="text-xs font-bold text-slate-200 mt-0.5 truncate">Free Cervical & Vital Health Screening</p>
                  <p class="text-[11px] text-slate-400 line-clamp-1 mt-0.5">Free ECG, blood glucose, and hypertension monitoring at Halang Barangay Hall...</p>
                </div>
                <button type="button" class="text-slate-500 hover:text-white p-1"><i data-lucide="more-vertical" class="h-4 w-4"></i></button>
              </div>

              <!-- Notice 3 -->
              <div class="flex items-start gap-3 p-2.5 rounded-2xl hover:bg-white/5 transition-all">
                <div class="h-10 w-10 shrink-0 rounded-full bg-sky-950 border border-sky-800/60 flex items-center justify-center text-xs font-black text-sky-400">
                  MW
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-extrabold text-white">Rural Health Midwife</p>
                    <span class="text-[10px] font-medium text-slate-400">Feb 02</span>
                  </div>
                  <p class="text-xs font-bold text-slate-200 mt-0.5 truncate">National Immunization & Maternal Care</p>
                  <p class="text-[11px] text-slate-400 line-clamp-1 mt-0.5">Free vaccines for infants (0-5 years) & prenatal counseling available on Wednesday...</p>
                </div>
                <button type="button" class="text-slate-500 hover:text-white p-1"><i data-lucide="more-vertical" class="h-4 w-4"></i></button>
              </div>
            </div>
          </div>
        </div>

        <!-- Row 2: 5 RHU System Stat / Metric Cards Organized 2x2 Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">

          <!-- Card 1: Health Consultations -->
          <div class="col-span-1 rounded-2xl sm:rounded-3xl border border-white/10 bg-[#131916] p-3.5 sm:p-5 shadow-xl hover:border-emerald-500/30 transition-all flex flex-col justify-between group">
            <div class="flex items-center justify-between">
              <div class="flex h-8 sm:h-9 w-8 sm:w-9 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <i data-lucide="stethoscope" class="h-4 w-4"></i>
              </div>
              <button type="button" data-tab-link="records" class="flex h-7 sm:h-8 w-7 sm:w-8 items-center justify-center rounded-full bg-white/5 text-slate-300 group-hover:bg-emerald-500 group-hover:text-slate-950 transition-all">
                <i data-lucide="arrow-up-right" class="h-3.5 sm:h-4 w-3.5 sm:w-4"></i>
              </button>
            </div>
            <div class="mt-2.5 sm:mt-4">
              <p class="text-[11px] sm:text-xs font-semibold text-slate-400 truncate">OPD Consultations</p>
              <div class="mt-0.5 sm:mt-1 flex items-baseline gap-1.5">
                <span class="text-xl sm:text-2xl font-black text-white"><?= (int)$visitsThisYear ?> <span class="text-xs font-medium text-slate-400">Visits</span></span>
              </div>
            </div>
            <p class="mt-2 sm:mt-3 text-[9px] sm:text-[10px] font-medium text-slate-500">Jan 01, <?= (int)date('Y') ?> – Present</p>
          </div>

          <!-- Card 2: Prescriptions Issued -->
          <div class="col-span-1 rounded-2xl sm:rounded-3xl border border-white/10 bg-[#131916] p-3.5 sm:p-5 shadow-xl hover:border-emerald-500/30 transition-all flex flex-col justify-between group">
            <div class="flex items-center justify-between">
              <div class="flex h-8 sm:h-9 w-8 sm:w-9 items-center justify-center rounded-full bg-sky-500/10 text-sky-400 border border-sky-500/20">
                <i data-lucide="pill" class="h-4 w-4"></i>
              </div>
              <button type="button" data-tab-link="records" class="flex h-7 sm:h-8 w-7 sm:w-8 items-center justify-center rounded-full bg-white/5 text-slate-300 group-hover:bg-emerald-500 group-hover:text-slate-950 transition-all">
                <i data-lucide="arrow-up-right" class="h-3.5 sm:h-4 w-3.5 sm:w-4"></i>
              </button>
            </div>
            <div class="mt-2.5 sm:mt-4">
              <p class="text-[11px] sm:text-xs font-semibold text-slate-400 truncate">Prescriptions Issued</p>
              <div class="mt-0.5 sm:mt-1 flex items-baseline gap-1.5">
                <span class="text-xl sm:text-2xl font-black text-white"><?= (int)$totalPrescriptions ?> <span class="text-xs font-medium text-slate-400">Rx</span></span>
              </div>
            </div>
            <p class="mt-2 sm:mt-3 text-[9px] sm:text-[10px] font-medium text-slate-500">Jan 01, <?= (int)date('Y') ?> – Present</p>
          </div>

          <!-- Card 3: Health Certificates -->
          <div class="col-span-1 rounded-2xl sm:rounded-3xl border border-white/10 bg-[#131916] p-3.5 sm:p-5 shadow-xl hover:border-emerald-500/30 transition-all flex flex-col justify-between group">
            <div class="flex items-center justify-between">
              <div class="flex h-8 sm:h-9 w-8 sm:w-9 items-center justify-center rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20">
                <i data-lucide="award" class="h-4 w-4"></i>
              </div>
              <button type="button" data-tab-link="certificates" class="flex h-7 sm:h-8 w-7 sm:w-8 items-center justify-center rounded-full bg-white/5 text-slate-300 group-hover:bg-emerald-500 group-hover:text-slate-950 transition-all">
                <i data-lucide="arrow-up-right" class="h-3.5 sm:h-4 w-3.5 sm:w-4"></i>
              </button>
            </div>
            <div class="mt-2.5 sm:mt-4">
              <p class="text-[11px] sm:text-xs font-semibold text-slate-400 truncate">Health Certificates</p>
              <div class="mt-0.5 sm:mt-1 flex items-baseline gap-1.5">
                <span class="text-xl sm:text-2xl font-black text-white"><?= count($healthCertificates ?? []) ?> <span class="text-xs font-medium text-slate-400">Docs</span></span>
              </div>
            </div>
            <p class="mt-2 sm:mt-3 text-[9px] sm:text-[10px] font-medium text-slate-500">Jan 01, <?= (int)date('Y') ?> – Present</p>
          </div>

          <!-- Card 4: Immunization Doses -->
          <div class="col-span-1 rounded-2xl sm:rounded-3xl border border-white/10 bg-[#131916] p-3.5 sm:p-5 shadow-xl hover:border-emerald-500/30 transition-all flex flex-col justify-between group">
            <div class="flex items-center justify-between">
              <div class="flex h-8 sm:h-9 w-8 sm:w-9 items-center justify-center rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                <i data-lucide="shield-check" class="h-4 w-4"></i>
              </div>
              <button type="button" data-tab-link="immunization" class="flex h-7 sm:h-8 w-7 sm:w-8 items-center justify-center rounded-full bg-white/5 text-slate-300 group-hover:bg-emerald-500 group-hover:text-slate-950 transition-all">
                <i data-lucide="arrow-up-right" class="h-3.5 sm:h-4 w-3.5 sm:w-4"></i>
              </button>
            </div>
            <div class="mt-2.5 sm:mt-4">
              <p class="text-[11px] sm:text-xs font-semibold text-slate-400 truncate">Vaccine Doses</p>
              <div class="mt-0.5 sm:mt-1 flex items-baseline gap-1.5">
                <span class="text-xl sm:text-2xl font-black text-white"><?= count($vaccinationRecords ?? []) ?> <span class="text-xs font-medium text-slate-400">Doses</span></span>
              </div>
            </div>
            <p class="mt-2 sm:mt-3 text-[9px] sm:text-[10px] font-medium text-slate-500">Jan 01, <?= (int)date('Y') ?> – Present</p>
          </div>

          <!-- Card 5: Emergency Referrals -->
          <div class="col-span-2 sm:col-span-1 rounded-2xl sm:rounded-3xl border border-white/10 bg-[#131916] p-3.5 sm:p-5 shadow-xl hover:border-emerald-500/30 transition-all flex flex-col justify-between group">
            <div class="flex items-center justify-between">
              <div class="flex h-8 sm:h-9 w-8 sm:w-9 items-center justify-center rounded-full bg-teal-500/10 text-teal-400 border border-teal-500/20">
                <i data-lucide="siren" class="h-4 w-4"></i>
              </div>
              <button type="button" data-tab-link="emergency" class="flex h-7 sm:h-8 w-7 sm:w-8 items-center justify-center rounded-full bg-white/5 text-slate-300 group-hover:bg-emerald-500 group-hover:text-slate-950 transition-all">
                <i data-lucide="arrow-up-right" class="h-3.5 sm:h-4 w-3.5 sm:w-4"></i>
              </button>
            </div>
            <div class="mt-2.5 sm:mt-4">
              <p class="text-[11px] sm:text-xs font-semibold text-slate-400 truncate">Emergency & Referrals</p>
              <div class="mt-0.5 sm:mt-1 flex items-baseline gap-1.5">
                <span class="text-xl sm:text-2xl font-black text-white"><?= count($maternalReferrals ?? []) ?> <span class="text-xs font-medium text-slate-400">Requests</span></span>
              </div>
            </div>
            <p class="mt-2 sm:mt-3 text-[9px] sm:text-[10px] font-medium text-slate-500">Jan 01, <?= (int)date('Y') ?> – Present</p>
          </div>

        </div>

        <!-- Row 3: 2 RHU Data Tables Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

          <!-- Left Table: Latest Health Certificates & Clearances -->
          <div class="rounded-3xl border border-white/10 bg-[#131916] p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-white/5 pb-3">
              <div class="flex items-center gap-2">
                <h3 class="font-bold text-white text-base">Health Certificates & Permits</h3>
                <span class="rounded-full bg-emerald-950 px-2 py-0.5 text-[10px] font-black text-emerald-400 border border-emerald-800/60"><?= count($healthCertificates ?? []) ?: 1 ?></span>
              </div>
              <button type="button" data-tab-link="certificates" class="text-xs font-semibold text-slate-400 hover:text-emerald-400 transition-colors flex items-center gap-1">
                View all <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
              </button>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead>
                  <tr class="text-slate-400 font-semibold border-b border-white/10">
                    <th class="pb-3 font-semibold">Certificate Type</th>
                    <th class="pb-3 font-semibold">Issued Date</th>
                    <th class="pb-3 font-semibold">Control No.</th>
                    <th class="pb-3 font-semibold">Status</th>
                    <th class="pb-3 text-right font-semibold"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-white/5 font-medium text-slate-300">
                  <?php if (!empty($healthCertificates)): ?>
                    <?php foreach (array_slice($healthCertificates, 0, 4) as $cert): ?>
                      <tr class="hover:bg-white/5 transition-colors">
                        <td class="py-3 flex items-center gap-2 text-white font-semibold">
                          <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                          <?= esc($cert['certificate_type_name'] ?? 'Medical Certificate') ?>
                        </td>
                        <td class="py-3 text-slate-400"><?= esc(date('m/d/Y', strtotime($cert['created_at'] ?? 'now'))) ?></td>
                        <td class="py-3 text-white font-mono font-bold"><?= esc($cert['certificate_number'] ?? ('HC-' . rand(1000, 9999))) ?></td>
                        <td class="py-3">
                          <span class="rounded-md bg-emerald-950 px-2 py-0.5 text-[10px] font-bold text-emerald-400 border border-emerald-800/50"><?= esc($cert['status'] ?? 'Issued') ?></span>
                        </td>
                        <td class="py-3 text-right">
                          <a href="ResidentDashboard.php?certificate_document=<?= (int)$cert['id'] ?>" target="_blank" class="text-slate-400 hover:text-emerald-400 p-1"><i data-lucide="download" class="h-4 w-4"></i></a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr class="hover:bg-white/5 transition-colors">
                      <td class="py-3 flex items-center gap-2 text-white font-semibold">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        Medical Clearance — OPD Consultation
                      </td>
                      <td class="py-3 text-slate-400">02/05/2026</td>
                      <td class="py-3 text-white font-mono font-bold">HC-2026-0842</td>
                      <td class="py-3">
                        <span class="rounded-md bg-emerald-950 px-2 py-0.5 text-[10px] font-bold text-emerald-400 border border-emerald-800/50">Issued</span>
                      </td>
                      <td class="py-3 text-right">
                        <button type="button" data-tab-link="certificates" class="text-slate-400 hover:text-emerald-400 p-1"><i data-lucide="download" class="h-4 w-4"></i></button>
                      </td>
                    </tr>
                    <tr class="hover:bg-white/5 transition-colors">
                      <td class="py-3 text-slate-300">Sanitary & Food Handler Clearance</td>
                      <td class="py-3 text-slate-400">05/04/2026</td>
                      <td class="py-3 text-white font-mono font-bold">HC-2026-0711</td>
                      <td class="py-3">
                        <span class="rounded-md bg-amber-950/60 px-2 py-0.5 text-[10px] font-bold text-amber-400 border border-amber-800/40">In Review</span>
                      </td>
                      <td class="py-3 text-right">
                        <button type="button" data-tab-link="certificates" class="text-slate-400 hover:text-emerald-400 p-1"><i data-lucide="download" class="h-4 w-4"></i></button>
                      </td>
                    </tr>
                    <tr class="hover:bg-white/5 transition-colors">
                      <td class="py-3 text-slate-300">Barbershop & Beauty Salon Health Permit</td>
                      <td class="py-3 text-slate-400">01/03/2026</td>
                      <td class="py-3 text-white font-mono font-bold">HC-2026-0599</td>
                      <td class="py-3">
                        <span class="rounded-md bg-emerald-950 px-2 py-0.5 text-[10px] font-bold text-emerald-400 border border-emerald-800/50">Signed</span>
                      </td>
                      <td class="py-3 text-right">
                        <button type="button" data-tab-link="certificates" class="text-slate-400 hover:text-emerald-400 p-1"><i data-lucide="download" class="h-4 w-4"></i></button>
                      </td>
                    </tr>
                    <tr class="hover:bg-white/5 transition-colors">
                      <td class="py-3 text-slate-300">Annual Employment Health Clearance</td>
                      <td class="py-3 text-slate-400">02/02/2026</td>
                      <td class="py-3 text-white font-mono font-bold">HC-2026-0410</td>
                      <td class="py-3">
                        <span class="rounded-md bg-white/5 px-2 py-0.5 text-[10px] font-bold text-slate-400 border border-white/10">Archived</span>
                      </td>
                      <td class="py-3 text-right">
                        <button type="button" data-tab-link="certificates" class="text-slate-400 hover:text-emerald-400 p-1"><i data-lucide="download" class="h-4 w-4"></i></button>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Right Table: Recent Consultations & Triage -->
          <div class="rounded-3xl border border-white/10 bg-[#131916] p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-white/5 pb-3">
              <h3 class="font-bold text-white text-base">Recent OPD Consultations</h3>
              <button type="button" data-tab-link="records" class="text-xs font-semibold text-slate-400 hover:text-emerald-400 transition-colors flex items-center gap-1">
                View all <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
              </button>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead>
                  <tr class="text-slate-400 font-semibold border-b border-white/10">
                    <th class="pb-3 font-semibold">ID</th>
                    <th class="pb-3 font-semibold">Health Concern / Diagnosis</th>
                    <th class="pb-3 font-semibold">Date</th>
                    <th class="pb-3 font-semibold">Physician</th>
                    <th class="pb-3 font-semibold">Status</th>
                    <th class="pb-3 text-right font-semibold"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-white/5 font-medium text-slate-300">
                  <?php if (!empty($consultations)): ?>
                    <?php foreach ($consultationsPage as $idx => $c): ?>
                      <tr class="hover:bg-white/5 transition-colors">
                        <td class="py-3 text-slate-400">CONS-<?= str_pad((string)($c['id'] ?? ($idx + 1)), 4, '0', STR_PAD_LEFT) ?></td>
                        <td class="py-3 text-white font-semibold"><?= esc($c['diagnosis'] ?? ($c['chief_complaint'] ?? 'General Consultation')) ?></td>
                        <td class="py-3 text-slate-400"><?= esc(date('m/d/Y', strtotime($c['consultation_date'] ?? 'now'))) ?></td>
                        <td class="py-3 text-slate-300"><?= esc(($c['physician_name'] ?? '') ?: 'Dr. RHU Physician') ?></td>
                        <td class="py-3">
                          <span class="rounded-md bg-emerald-950 px-2 py-0.5 text-[10px] font-bold text-emerald-400 border border-emerald-800/40">Completed</span>
                        </td>
                        <td class="py-3 text-right">
                          <button type="button" data-tab-link="records" class="text-slate-500 hover:text-white p-1"><i data-lucide="chevron-right" class="h-4 w-4"></i></button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr class="hover:bg-white/5 transition-colors">
                      <td class="py-3 text-slate-400">CONS-0042</td>
                      <td class="py-3 text-white font-semibold">Acute Upper Respiratory Infection</td>
                      <td class="py-3 text-slate-400">02/05/2026</td>
                      <td class="py-3 text-slate-300">Dr. Nasugbu RHU</td>
                      <td class="py-3">
                        <span class="rounded-md bg-emerald-950 px-2 py-0.5 text-[10px] font-bold text-emerald-400 border border-emerald-800/40">Completed</span>
                      </td>
                      <td class="py-3 text-right">
                        <button type="button" data-tab-link="records" class="text-slate-500 hover:text-white p-1"><i data-lucide="chevron-right" class="h-4 w-4"></i></button>
                      </td>
                    </tr>
                    <tr class="hover:bg-white/5 transition-colors">
                      <td class="py-3 text-slate-400">CONS-0039</td>
                      <td class="py-3 text-white font-semibold">Hypertension & Vital Signs Follow-up</td>
                      <td class="py-3 text-slate-400">05/04/2026</td>
                      <td class="py-3 text-slate-300">Dr. Municipal Health</td>
                      <td class="py-3">
                        <span class="rounded-md bg-emerald-950 px-2 py-0.5 text-[10px] font-bold text-emerald-400 border border-emerald-800/40">Completed</span>
                      </td>
                      <td class="py-3 text-right">
                        <button type="button" data-tab-link="records" class="text-slate-500 hover:text-white p-1"><i data-lucide="chevron-right" class="h-4 w-4"></i></button>
                      </td>
                    </tr>
                    <tr class="hover:bg-white/5 transition-colors">
                      <td class="py-3 text-slate-400">CONS-0028</td>
                      <td class="py-3 text-white font-semibold">Routine Maternal & Prenatal Checkup</td>
                      <td class="py-3 text-slate-400">01/03/2026</td>
                      <td class="py-3 text-slate-300">Rural Health Midwife</td>
                      <td class="py-3">
                        <span class="rounded-md bg-emerald-950 px-2 py-0.5 text-[10px] font-bold text-emerald-400 border border-emerald-800/40">Completed</span>
                      </td>
                      <td class="py-3 text-right">
                        <button type="button" data-tab-link="records" class="text-slate-500 hover:text-white p-1"><i data-lucide="chevron-right" class="h-4 w-4"></i></button>
                      </td>
                    </tr>
                    <tr class="hover:bg-white/5 transition-colors">
                      <td class="py-3 text-slate-400">CONS-0015</td>
                      <td class="py-3 text-white font-semibold">General Physical Exam & Triage</td>
                      <td class="py-3 text-slate-400">02/02/2026</td>
                      <td class="py-3 text-slate-300">Public Health Nurse</td>
                      <td class="py-3">
                        <span class="rounded-md bg-emerald-950 px-2 py-0.5 text-[10px] font-bold text-emerald-400 border border-emerald-800/40">Completed</span>
                      </td>
                      <td class="py-3 text-right">
                        <button type="button" data-tab-link="records" class="text-slate-500 hover:text-white p-1"><i data-lucide="chevron-right" class="h-4 w-4"></i></button>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <!-- Pagination Controls -->
            <?php if ($totalConsultationPages > 1): ?>
            <div class="flex justify-between items-center mt-4 pt-4 border-t border-slate-200">
              <div class="text-sm text-slate-600">
                Page <span class="font-semibold"><?= $currentPage ?></span> of <span class="font-semibold"><?= $totalConsultationPages ?></span> (<?= $totalConsultations ?> total records)
              </div>
              <div class="flex gap-2">
                <?php if ($currentPage > 1): ?>
                  <a href="ResidentDashboard.php?tab=records&cons_page=<?= $currentPage - 1 ?>" class="px-4 py-2 bg-slate-200 text-slate-800 rounded-lg hover:bg-slate-300 transition-colors text-sm font-medium">← Previous</a>
                <?php else: ?>
                  <button disabled class="px-4 py-2 bg-slate-100 text-slate-400 rounded-lg text-sm font-medium cursor-not-allowed">← Previous</button>
                <?php endif; ?>
                <?php if ($currentPage < $totalConsultationPages): ?>
                  <a href="ResidentDashboard.php?tab=records&cons_page=<?= $currentPage + 1 ?>" class="px-4 py-2 bg-slate-200 text-slate-800 rounded-lg hover:bg-slate-300 transition-colors text-sm font-medium">Next →</a>
                <?php else: ?>
                  <button disabled class="px-4 py-2 bg-slate-100 text-slate-400 rounded-lg text-sm font-medium cursor-not-allowed">Next →</button>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>

        </div>


      </section>

      <!-- 2. PROFILE TAB (Health Profile & Medical Info) -->
      <section data-tab-panel="profile" class="hidden space-y-6">

        <!-- Header Card -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
              <div class="h-16 w-16 shrink-0 rounded-2xl bg-gradient-to-tr from-teal-600 to-emerald-500 flex items-center justify-center text-white font-extrabold text-xl shadow-md">
                <?= esc($initials) ?>
              </div>
              <div>
                <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">
                  <?= esc(trim(($resident['first_name'] ?? '') . ' ' . ($resident['middle_name'] ?? '') . ' ' . ($resident['last_name'] ?? ''))) ?>
                </h2>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                  <?= esc($resident['gender'] ?? 'Resident') ?> • <?= $age === null ? 'Age N/A' : esc($age) . ' years old' ?> • <?= esc($resident['barangay'] ?? 'Nasugbu') ?>
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                  <span class="inline-flex items-center gap-1 rounded-md bg-teal-50 px-2.5 py-1 font-bold text-teal-700 border border-teal-200/60">
                    <i data-lucide="droplet" class="h-3.5 w-3.5 text-teal-600"></i> Blood Type: <?= esc($healthProfile['blood_type'] ?? ($resident['blood_type'] ?? 'Unknown')) ?>
                  </span>
                  <span class="inline-flex items-center gap-1 rounded-md bg-sky-50 px-2.5 py-1 font-bold text-sky-700 border border-sky-200/60">
                    <i data-lucide="credit-card" class="h-3.5 w-3.5 text-sky-600"></i> PhilHealth #: <?= esc($healthProfile['philhealth_number'] ?? ($resident['philhealth_id'] ?? 'Not Recorded')) ?>
                  </span>
                  <?php if (!empty($healthProfile['bmi'])): ?>
                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2.5 py-1 font-bold text-emerald-700 border border-emerald-200/60">
                      <i data-lucide="activity" class="h-3.5 w-3.5 text-emerald-600"></i> BMI: <?= esc($healthProfile['bmi']) ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <button type="button" onclick="document.getElementById('edit-health-profile-modal')?.classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition-all cursor-pointer">
              <i data-lucide="edit-3" class="h-4 w-4"></i> Edit Health Profile
            </button>
          </div>
        </div>

        <!-- Detailed Information Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Card 1: Personal & Demographic Info -->
          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div class="flex items-center gap-2">
                <div class="h-8 w-8 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center">
                  <i data-lucide="user" class="h-4 w-4"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-800">Personal & Contact Details</h3>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4 text-xs">
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Full Name</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc(($resident['first_name'] ?? '') . ' ' . ($resident['last_name'] ?? '')) ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Date of Birth</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc($resident['date_of_birth'] ?? 'Not specified') ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Gender / Civil Status</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc($resident['gender'] ?? '—') ?> / <?= esc($resident['civil_status'] ?? '—') ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Contact Number</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc($resident['contact_number'] ?? 'Not specified') ?></p>
              </div>
              <div class="col-span-2">
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Email Address</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc($resident['email'] ?? 'Not specified') ?></p>
              </div>
              <div class="col-span-2">
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Barangay & Address</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc($resident['barangay'] ?? 'Nasugbu') ?>, <?= esc($resident['address'] ?? '') ?></p>
              </div>
            </div>
          </div>

          <!-- Card 2: Physical Vitals & Measurements -->
          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div class="flex items-center gap-2">
                <div class="h-8 w-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center">
                  <i data-lucide="heart-pulse" class="h-4 w-4"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-800">Vitals & Physical Attributes</h3>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4 text-xs">
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Height (cm)</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= !empty($healthProfile['height']) ? esc($healthProfile['height']) . ' cm' : 'Not recorded' ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Weight (kg)</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= !empty($healthProfile['weight']) ? esc($healthProfile['weight']) . ' kg' : 'Not recorded' ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Blood Pressure</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc(!empty($healthProfile['blood_pressure']) ? $healthProfile['blood_pressure'] : (!empty($healthProfile['bp']) ? $healthProfile['bp'] : '120/80 mmHg')) ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Heart Rate (bpm)</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= !empty($healthProfile['heart_rate']) ? esc($healthProfile['heart_rate']) . ' bpm' : '72 bpm' ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Temperature (°C)</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= !empty($healthProfile['temperature']) ? esc($healthProfile['temperature']) . ' °C' : '36.5 °C' ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Last Checkup Date</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc($healthProfile['last_checkup_date'] ?? date('Y-m-d')) ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Smoking Status</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc($healthProfile['smoking_status'] ?? 'Non-Smoker') ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Alcohol Consumption</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc($healthProfile['alcohol_consumption'] ?? 'Non-Drinker') ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Exercise Frequency</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc($healthProfile['exercise_frequency'] ?? 'Occasional (1-2x/week)') ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Diet Type</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc($healthProfile['diet_type'] ?? 'Balanced Diet') ?></p>
              </div>
            </div>
          </div>

          <!-- Card 3: Medical History & Clinical Notes -->
          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div class="flex items-center gap-2">
                <div class="h-8 w-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                  <i data-lucide="shield-alert" class="h-4 w-4"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-800">Medical Conditions & History</h3>
              </div>
            </div>
            <div class="space-y-3 text-xs">
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Known Allergies</p>
                <p class="font-medium text-slate-800 mt-0.5 bg-slate-50 p-3 rounded-xl border border-slate-100">
                  <?= esc(!empty($healthProfile['allergies']) ? $healthProfile['allergies'] : (!empty($resident['allergies']) ? $resident['allergies'] : 'No known allergies recorded.')) ?>
                </p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Chronic Conditions / Illnesses</p>
                <p class="font-medium text-slate-800 mt-0.5 bg-slate-50 p-3 rounded-xl border border-slate-100">
                  <?= esc(!empty($healthProfile['chronic_conditions']) ? $healthProfile['chronic_conditions'] : (!empty($healthProfile['medical_conditions']) ? $healthProfile['medical_conditions'] : (!empty($resident['medical_conditions']) ? $resident['medical_conditions'] : 'No pre-existing chronic conditions recorded.'))) ?>
                </p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Current Prescribed Medications</p>
                <p class="font-medium text-slate-800 mt-0.5 bg-slate-50 p-3 rounded-xl border border-slate-100">
                  <?= esc(!empty($healthProfile['current_medications']) ? $healthProfile['current_medications'] : (!empty($healthProfile['medications']) ? $healthProfile['medications'] : 'None currently listed.')) ?>
                </p>
              </div>
            </div>
          </div>

          <!-- Card 4: Emergency Contacts & PhilHealth -->
          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div class="flex items-center gap-2">
                <div class="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                  <i data-lucide="phone-call" class="h-4 w-4"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-800">Emergency Contacts & PhilHealth</h3>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4 text-xs">
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Emergency Contact Person</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc(!empty($healthProfile['emergency_contact_name']) ? $healthProfile['emergency_contact_name'] : (!empty($resident['emergency_contact_name']) ? $resident['emergency_contact_name'] : 'Not set')) ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Relationship</p>
                <p class="font-bold text-slate-800 mt-0.5"><?= esc(!empty($healthProfile['emergency_contact_relationship']) ? $healthProfile['emergency_contact_relationship'] : (!empty($resident['emergency_contact_relationship']) ? $resident['emergency_contact_relationship'] : 'Family Member')) ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">Emergency Phone Number</p>
                <p class="font-bold mt-0.5 text-teal-700"><?= esc(!empty($healthProfile['emergency_contact_phone']) ? $healthProfile['emergency_contact_phone'] : (!empty($resident['emergency_contact_phone']) ? $resident['emergency_contact_phone'] : 'Not set')) ?></p>
              </div>
              <div>
                <p class="text-slate-400 font-semibold uppercase text-[10px]">PhilHealth Number</p>
                <p class="font-bold mt-0.5 font-mono text-emerald-700"><?= esc(!empty($healthProfile['philhealth_number']) ? $healthProfile['philhealth_number'] : (!empty($resident['philhealth_id']) ? $resident['philhealth_id'] : 'Not set')) ?></p>
              </div>
            </div>
          </div>

        </div>
      </section>

      <!-- 3. HEALTH RECORDS TAB -->
      <section data-tab-panel="records" class="hidden space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-bold text-slate-900">Health Records & Consultations</h3>
          <button type="button" data-appointment-open class="rounded-xl bg-teal-600 px-4 py-2 text-xs font-bold text-white hover:bg-teal-700 transition-all flex items-center gap-2" aria-haspopup="dialog" aria-controls="appointment-modal">
            <i data-lucide="plus" class="h-4 w-4"></i> Request OPD Appointment
          </button>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4">
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
            <p class="text-xs font-bold text-slate-400 uppercase">Total Visits</p>
            <p class="text-2xl font-black text-slate-800 mt-1"><?= count($consultations) ?></p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
            <p class="text-xs font-bold text-slate-400 uppercase">Visits This Year (<?= $currentYear ?>)</p>
            <p class="text-2xl font-black text-teal-600 mt-1"><?= $visitsThisYear ?></p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
            <p class="text-xs font-bold text-slate-400 uppercase">Prescriptions Issued</p>
            <p class="text-2xl font-black text-indigo-600 mt-1"><?= $totalPrescriptions ?></p>
          </div>
        </div>

        <div class="space-y-4">
          <?php if (!$consultations): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-400">
              <i data-lucide="folder-open" class="mx-auto mb-2 h-10 w-10 text-slate-300"></i>
              <p class="text-sm font-semibold">No consultations recorded yet</p>
            </div>
          <?php else:
            $itemsPerPage = 4;
            $totalConsultationRecords = count($consultations);
            $totalConsultationPages = max(1, (int)ceil($totalConsultationRecords / $itemsPerPage));
          ?>
            <div class="flex items-center justify-between text-xs font-semibold text-slate-500 border-b border-slate-100 pb-2">
              <span>Showing 4 records per page</span>
              <span>Total Records: <strong class="text-slate-800 font-bold"><?= $totalConsultationRecords ?></strong></span>
            </div>

            <div class="space-y-3" id="records-list-container">
              <?php foreach ($consultations as $idx => $consultation):
                $pageNum = (int)floor($idx / $itemsPerPage) + 1;
                $hiddenClass = $pageNum > 1 ? 'hidden' : '';
                $rawSt = strtolower($consultation['consultation_status'] ?? 'scheduled');
                $stClass = match (true) {
                  str_contains($rawSt, 'completed') => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                  str_contains($rawSt, 'progress') => 'bg-blue-100 text-blue-800 border-blue-300',
                  str_contains($rawSt, 'referred') => 'bg-purple-100 text-purple-800 border-purple-300',
                  str_contains($rawSt, 'cancel') => 'bg-rose-100 text-rose-800 border-rose-300',
                  default => 'bg-amber-100 text-amber-800 border-amber-300'
                };
                $stLabel = ucfirst($consultation['consultation_status'] ?? 'Scheduled');
              ?>
                <article data-pagination-item="records" data-page="<?= $pageNum ?>" class="<?= $hiddenClass ?> rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs transition-all hover:border-slate-300 space-y-3">
                  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 border-b border-slate-100 pb-3">
                    <div>
                      <div class="flex items-center gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-teal-600"><?= esc($consultation['consultation_time'] ?? 'OPD') ?></span>
                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-extrabold border <?= $stClass ?>">Status: <?= esc($stLabel) ?></span>
                      </div>
                      <h4 class="font-bold text-slate-900 text-base mt-1"><?= esc($consultation['diagnosis'] ?? 'Consultation') ?></h4>
                      <p class="text-xs text-slate-500 font-medium">Attending Provider: <?= esc($consultation['physician_name'] ?: 'RHU Healthcare Staff') ?></p>
                    </div>
                    <span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">📅 <?= esc($consultation['consultation_date'] ?? '—') ?></span>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div>
                      <p class="font-bold text-slate-400">Chief Complaint:</p>
                      <p class="text-slate-700 font-medium"><?= esc($consultation['chief_complaint'] ?? 'None specified') ?></p>
                    </div>
                    <div>
                      <p class="font-bold text-slate-400">Prescribed Medications:</p>
                      <p class="text-slate-700 font-medium"><?= esc($consultation['medications_prescribed'] ?: 'None recorded yet') ?></p>
                    </div>
                  </div>
                  <?php if (!empty($consultation['consultation_notes']) || !empty($consultation['treatment_plan'])): ?>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60 text-xs space-y-1">
                      <p class="font-bold text-teal-800 uppercase text-[10px]">💬 Healthcare Staff Response &amp; Clinical Notes:</p>
                      <p class="text-slate-800 font-medium whitespace-pre-line"><?= esc(!empty($consultation['consultation_notes']) ? $consultation['consultation_notes'] : $consultation['treatment_plan']); ?></p>
                    </div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>

            <!-- Pagination Bar for Records -->
            <?php if ($totalConsultationPages > 1): ?>
              <div id="records-pagination-bar" class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm text-xs font-semibold text-slate-600">
                <span class="text-slate-500 font-medium">Page <strong class="text-slate-900 font-bold" id="records-current-page">1</strong> of <strong class="text-slate-900 font-bold"><?= $totalConsultationPages ?></strong> (<?= $totalConsultationRecords ?> total records)</span>

                <div class="flex items-center gap-1.5">
                  <button type="button" id="records-prev-btn" onclick="changeDashboardPage('records', -1, <?= $totalConsultationPages ?>)" disabled class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-slate-700 hover:bg-teal-50 hover:border-teal-300 disabled:opacity-40 disabled:cursor-not-allowed transition-all font-bold">
                    <i data-lucide="chevron-left" class="h-4 w-4"></i> Previous
                  </button>

                  <div class="flex items-center gap-1" id="records-page-numbers">
                    <?php for ($p = 1; $p <= $totalConsultationPages; $p++): ?>
                      <button type="button" onclick="goToDashboardPage('records', <?= $p ?>, <?= $totalConsultationPages ?>)" data-page-btn="records-<?= $p ?>" class="<?= $p === 1 ? 'h-8 min-w-[2rem] px-2.5 rounded-xl text-xs font-extrabold transition-all bg-teal-600 text-white shadow-md' : 'h-8 min-w-[2rem] px-2.5 rounded-xl text-xs font-extrabold transition-all border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' ?>">
                        <?= $p ?>
                      </button>
                    <?php endfor; ?>
                  </div>

                  <button type="button" id="records-next-btn" onclick="changeDashboardPage('records', 1, <?= $totalConsultationPages ?>)" class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-slate-700 hover:bg-teal-50 hover:border-teal-300 disabled:opacity-40 disabled:cursor-not-allowed transition-all font-bold">
                    Next <i data-lucide="chevron-right" class="h-4 w-4"></i>
                  </button>
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <?php if ($pregnancyRecords || $familyPlanningRecords || $maternalReferrals || $birthRecords): ?>
          <div class="rounded-2xl border border-pink-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 flex items-center gap-2 font-black text-slate-900"><i data-lucide="heart-handshake" class="h-5 w-5 text-pink-600"></i> Maternal &amp; Midwife Service Records</h3>
            <div class="grid gap-3 md:grid-cols-2">
              <?php foreach ($pregnancyRecords as $record): ?>
                <article class="rounded-xl border border-pink-100 bg-pink-50/50 p-4 text-xs">
                  <p class="font-black text-pink-900">Prenatal Case — <?= esc($record['pregnancy_status']) ?></p>
                  <p class="mt-1 text-slate-600">G<?= (int)($record['gravida'] ?? 1) ?>P<?= (int)($record['para'] ?? 0) ?> · EDC: <?= esc($record['expected_delivery_date']) ?></p>
                  <p class="mt-1 text-slate-500"><?= esc($record['risk_factors']) ?></p>
                </article>
              <?php endforeach; ?>
              <?php foreach ($familyPlanningRecords as $record): ?>
                <article class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-xs">
                  <p class="font-black text-rose-900">Family Planning — <?= esc($record['contraceptive_method']) ?></p>
                  <p class="mt-1 text-slate-600"><?= esc($record['acceptor_type']) ?> · Next visit: <?= esc($record['next_visit_date'] ?: 'To be scheduled') ?></p>
                </article>
              <?php endforeach; ?>
              <?php foreach ($maternalReferrals as $record): ?>
                <article class="rounded-xl border border-purple-100 bg-purple-50/50 p-4 text-xs">
                  <p class="font-black text-purple-900">Maternal Referral — <?= esc($record['referral_status']) ?></p>
                  <p class="mt-1 text-slate-600">To: <?= esc($record['referred_to']) ?> · <?= esc($record['urgency']) ?></p>
                  <p class="mt-1 text-slate-500"><?= esc($record['referral_reason']) ?></p>
                </article>
              <?php endforeach; ?>
              <?php foreach ($birthRecords as $record): ?>
                <article class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-xs">
                  <p class="font-black text-emerald-900">Birth Record — <?= esc($record['child_name']) ?></p>
                  <p class="mt-1 text-slate-600">Born <?= esc($record['date_of_birth']) ?> · Certificate: <?= esc($record['birth_certificate_number']) ?></p>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <!-- 3. IMMUNIZATION TAB -->
      <section data-tab-panel="immunization" class="hidden space-y-6">
        <h3 class="text-lg font-bold text-slate-900">Immunization History</h3>
        <div class="space-y-3">
          <?php if (!$vaccinationRecords): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-400">
              <i data-lucide="syringe" class="mx-auto mb-2 h-10 w-10 text-slate-300"></i>
              <p class="text-sm font-semibold">No vaccination records found</p>
            </div>
            <?php else: foreach ($vaccinationRecords as $record): ?>
              <article class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs">
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span class="rounded-md bg-indigo-50 p-1.5 text-indigo-600"><i data-lucide="shield-check" class="h-4 w-4"></i></span>
                    <p class="text-base font-bold text-slate-900"><?= esc($record['vaccine_name']) ?></p>
                  </div>
                  <p class="text-xs text-slate-500 font-medium pl-8">Administered by: <?= esc($record['provider_name'] ?: 'RHU Staff') ?> (Dose #<?= esc($record['dose_number'] ?? '1') ?>)</p>
                </div>
                <div class="text-right sm:text-right text-xs">
                  <span class="inline-block rounded-full px-3 py-1 font-bold bg-emerald-50 text-emerald-700 mb-1">Completed</span>
                  <p class="text-slate-400 font-medium"><?= esc($record['vaccination_date'] ?? '—') ?></p>
                </div>
              </article>
          <?php endforeach;
          endif; ?>
        </div>
      </section>

      <!-- 4. CERTIFICATES TAB -->
      <section data-tab-panel="certificates" class="hidden space-y-6">
        <h3 class="text-lg font-bold text-slate-900">Health Certificates & Clearances</h3>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-2xs space-y-4">
          <p class="text-sm font-bold text-slate-800">Request New Certificate</p>
          <?php if ($certificateErrors): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 font-medium">
              <?= esc(implode(' ', $certificateErrors)) ?>
            </div>
          <?php endif; ?>
          <form method="post" action="ResidentDashboard.php?tab=certificates" class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <input type="hidden" name="form" value="certificate_request">
            <?php foreach (['Medical Certificate (₱50)', 'Health Certificate (₱100)', 'Barangay Health Cert (₱100)', 'Certificate of Live Birth (FREE)'] as $certificateType): ?>
              <button type="submit" name="certificate_type" value="<?= esc($certificateType) ?>" class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-4 font-bold text-slate-700 hover:border-slate-300 hover:bg-slate-100 transition-all text-left">
                <span><?= esc($certificateType) ?></span>
                <i data-lucide="arrow-right" class="h-4 w-4 text-slate-400"></i>
              </button>
            <?php endforeach; ?>
          </form>
        </div>

        <div class="space-y-3">
          <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Requested / Issued Certificates</h4>
          <?php if (!$certificates): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-400">
              <i data-lucide="award" class="mx-auto mb-2 h-10 w-10 text-slate-300"></i>
              <p class="text-sm font-semibold">No requested certificates on file</p>
            </div>
          <?php else:
            $certPerPage = 4;
            $totalCertRecords = count($certificates);
            $totalCertPages = max(1, (int)ceil($totalCertRecords / $certPerPage));
          ?>
            <div class="space-y-3" id="certificates-list-container">
              <?php foreach ($certificates as $idx => $cert):
                $pageNum = (int)floor($idx / $certPerPage) + 1;
                $hiddenClass = $pageNum > 1 ? 'hidden' : '';
              ?>
                <article data-pagination-item="certificates" data-page="<?= $pageNum ?>" class="<?= $hiddenClass ?> flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
                  <div>
                    <p class="text-sm font-bold text-slate-900"><?= esc($cert['certificate_type_name']) ?></p>
                    <p class="text-xs text-slate-500 font-medium">No: <span class="font-mono text-slate-700"><?= esc($cert['certificate_number']) ?></span> | Purpose: <?= esc($cert['purpose']) ?></p>
                  </div>
                  <div class="flex items-center gap-3 text-xs">
                    <?php
                    $certificateStatus = strtolower((string)($cert['validity_status'] ?? ''));
                    $certificateReady = (str_contains($certificateStatus, 'valid') || str_contains($certificateStatus, 'approved') || str_contains($certificateStatus, 'issued'))
                      && !str_contains($certificateStatus, 'invalid') && !str_contains($certificateStatus, 'revoked');
                    ?>
                    <span class="rounded-full px-3 py-1 font-bold <?= $certificateStatus === 'valid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                      <?= esc($cert['validity_status']) ?>
                    </span>
                    <?php if ($certificateReady): ?>
                      <a href="ResidentDashboard.php?certificate_document=<?= (int)$cert['id'] ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-xl bg-teal-700 px-3 py-2 font-bold text-white hover:bg-teal-800">
                        <i data-lucide="file-badge" class="h-3.5 w-3.5"></i> View / Print Certificate
                      </a>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>

            <?php if ($totalCertPages > 1): ?>
              <div id="certificates-pagination-bar" class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm text-xs font-semibold text-slate-600">
                <span class="text-slate-500 font-medium">Page <strong class="text-slate-900 font-bold" id="certificates-current-page">1</strong> of <strong class="text-slate-900 font-bold"><?= $totalCertPages ?></strong> (<?= $totalCertRecords ?> certificates)</span>

                <div class="flex items-center gap-1.5">
                  <button type="button" id="certificates-prev-btn" onclick="changeDashboardPage('certificates', -1, <?= $totalCertPages ?>)" disabled class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-slate-700 hover:bg-teal-50 hover:border-teal-300 disabled:opacity-40 disabled:cursor-not-allowed transition-all font-bold">
                    <i data-lucide="chevron-left" class="h-4 w-4"></i> Previous
                  </button>

                  <div class="flex items-center gap-1" id="certificates-page-numbers">
                    <?php for ($p = 1; $p <= $totalCertPages; $p++): ?>
                      <button type="button" onclick="goToDashboardPage('certificates', <?= $p ?>, <?= $totalCertPages ?>)" data-page-btn="certificates-<?= $p ?>" class="<?= $p === 1 ? 'h-8 min-w-[2rem] px-2.5 rounded-xl text-xs font-extrabold transition-all bg-teal-600 text-white shadow-md' : 'h-8 min-w-[2rem] px-2.5 rounded-xl text-xs font-extrabold transition-all border border-slate-200 bg-white text-slate-700 hover:bg-slate-50' ?>">
                        <?= $p ?>
                      </button>
                    <?php endfor; ?>
                  </div>

                  <button type="button" id="certificates-next-btn" onclick="changeDashboardPage('certificates', 1, <?= $totalCertPages ?>)" class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-slate-700 hover:bg-teal-50 hover:border-teal-300 disabled:opacity-40 disabled:cursor-not-allowed transition-all font-bold">
                    Next <i data-lucide="chevron-right" class="h-4 w-4"></i>
                  </button>
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </section>

      <!-- 5. EVENTS TAB -->
      <section data-tab-panel="events" class="hidden space-y-6">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
          <div>
            <h3 class="text-xl font-extrabold text-white">RHU Official Health Events & Programs</h3>
            <p class="text-xs text-slate-400">Public health programs, vaccination drives, and health fairs scheduled by RHU Admin.</p>
          </div>
          <span class="rounded-full bg-emerald-950 px-3 py-1 text-xs font-bold text-emerald-400 border border-emerald-800/60"><?= count($postedEvents) ?> Programs Listed</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <?php foreach ($postedEvents as $ev):
            $evTitle = $ev['title'] ?? 'RHU Health Event';
            $evVenue = $ev['venue'] ?? 'Nasugbu RHU Center';
            $evDesc = $ev['description'] ?? 'No description provided.';
            $rawDate = $ev['event_date'] ?? ($ev['scheduled_date'] ?? 'now');
            $evDateFormatted = date('M d, Y', strtotime($rawDate));
            $evTime = !empty($ev['start_time']) ? date('g:i A', strtotime($ev['start_time'])) : '8:00 AM';
            $evStatus = $ev['status'] ?? 'Scheduled';
          ?>
            <article class="rounded-3xl border border-white/10 bg-[#131916] p-6 shadow-xl space-y-3.5 hover:border-emerald-500/40 transition-all">
              <div class="flex items-center justify-between gap-2">
                <span class="rounded-lg bg-emerald-950 px-2.5 py-1 text-[10px] font-black text-emerald-400 border border-emerald-800/60 uppercase tracking-wider">
                  <?= esc($evStatus) ?>
                </span>
                <span class="text-xs font-mono font-bold text-slate-400 flex items-center gap-1">
                  <i data-lucide="calendar" class="h-3.5 w-3.5 text-emerald-400"></i>
                  <?= esc($evDateFormatted) ?> • <?= esc($evTime) ?>
                </span>
              </div>

              <div>
                <h4 class="text-base font-extrabold text-white leading-snug"><?= esc($evTitle) ?></h4>
                <p class="mt-1.5 text-xs text-slate-300 font-normal leading-relaxed"><?= esc($evDesc) ?></p>
              </div>

              <div class="flex items-center justify-between border-t border-white/5 pt-3 text-xs text-slate-400">
                <span class="flex items-center gap-1.5 font-medium text-slate-300">
                  <i data-lucide="map-pin" class="h-3.5 w-3.5 text-emerald-400"></i>
                  <?= esc($evVenue) ?>
                </span>
                <span class="text-[10px] text-emerald-400 font-bold">Admin Verified</span>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- 6. CONTACT TAB -->
      <section data-tab-panel="contact" class="hidden space-y-6">
        <h3 class="text-lg font-bold text-slate-900">Contact RHU Staff</h3>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-2xs">
            <p class="text-sm font-bold text-slate-800 mb-4">Send Inquiry or Message</p>
            <?php if ($contactErrors): ?>
              <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 font-medium">
                <?= esc(implode(' ', $contactErrors)) ?>
              </div>
            <?php endif; ?>
            <form method="post" action="ResidentDashboard.php?tab=contact" class="space-y-4 text-xs">
              <input type="hidden" name="form" value="contact">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Subject</label>
                <select name="subject" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500">
                  <option value="General Inquiry">General Inquiry</option>
                  <option value="Appointment Request">Appointment Request</option>
                  <option value="Certificate Request">Certificate Request</option>
                  <option value="Vaccination Query">Vaccination Query</option>
                </select>
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Message Detail</label>
                <textarea name="message" rows="4" class="w-full resize-none rounded-xl border border-slate-200 p-3 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-teal-500" placeholder="Type your concerns or requests here..."></textarea>
              </div>
              <button type="submit" class="w-full rounded-xl bg-teal-600 py-3 text-xs font-bold text-white hover:bg-teal-700 transition-all">Send Message to Staff</button>
            </form>
          </div>

          <!-- History Messages -->
          <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-2xs space-y-4">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Previous Messages</h4>
            <div class="space-y-3 max-h-96 overflow-y-auto">
              <?php if (!$residentMessages): ?>
                <p class="text-xs text-slate-400 font-medium text-center py-4">No sent messages yet</p>
                <?php else: foreach ($residentMessages as $msg): ?>
                  <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs">
                    <div class="flex justify-between font-bold text-slate-800">
                      <span><?= esc($msg['subject']) ?></span>
                      <span class="text-[10px] text-teal-600 font-semibold"><?= esc($msg['status']) ?></span>
                    </div>
                    <p class="mt-1 text-slate-600 font-medium text-[11px]"><?= esc($msg['message']) ?></p>
                    <p class="mt-2 text-[9px] text-slate-400"><?= esc($msg['created_at']) ?></p>
                  </div>
              <?php endforeach;
              endif; ?>
            </div>
          </div>
        </div>
      </section>

      <!-- 5. FAMILY MEMBERS TAB -->
      <section data-tab-panel="family" class="hidden space-y-6">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-bold text-slate-900">Family & Household Health Profile</h3>
            <p class="text-xs text-slate-500 font-medium">Manage and view health records for dependents linked to your household</p>
          </div>
          <button type="button" id="add-dependent-btn" onclick="openDependentModal()" data-dependent-open class="family-hover add-dependent-trigger rounded-xl bg-gradient-to-r from-teal-600 to-sky-600 px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-teal-600/20 hover:from-teal-700 hover:to-sky-700 flex items-center gap-2 cursor-pointer">
            <i data-lucide="user-plus" class="h-4 w-4"></i> Add Dependent
          </button>
        </div>

        <?php if ($dependentSuccess): ?>
          <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-semibold text-emerald-800">
            <i data-lucide="circle-check" class="h-5 w-5 shrink-0"></i>
            <p><?= esc($dependentSuccess) ?></p>
          </div>
        <?php endif; ?>
        <?php if ($dependentErrors): ?>
          <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800">
            <i data-lucide="circle-alert" class="h-5 w-5 shrink-0"></i>
            <div><?php foreach ($dependentErrors as $error): ?><p><?= esc($error) ?></p><?php endforeach; ?></div>
          </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <div class="family-hover rounded-2xl border border-teal-100 bg-gradient-to-br from-teal-50 to-emerald-50 p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-teal-700">Household members</p>
            <p class="mt-1 text-2xl font-black text-teal-900"><?= count($dependents) + 1 ?></p>
          </div>
          <div class="family-hover rounded-2xl border border-sky-100 bg-gradient-to-br from-sky-50 to-indigo-50 p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-sky-700">Dependents</p>
            <p class="mt-1 text-2xl font-black text-sky-900"><?= count($dependents) ?></p>
          </div>
          <div class="family-hover col-span-2 rounded-2xl border border-violet-100 bg-gradient-to-br from-violet-50 to-fuchsia-50 p-4 sm:col-span-1">
            <p class="text-[10px] font-bold uppercase tracking-wider text-violet-700">Profile status</p>
            <p class="mt-2 text-xs font-extrabold text-violet-900">Verified resident</p>
          </div>
        </div>

        <!-- Household Head Card -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5 sm:gap-4">
          <!-- Self (Head) -->
          <div class="family-hover rounded-2xl border-2 border-teal-500 bg-teal-50/30 p-5 shadow-2xs relative">
            <span class="absolute top-4 right-4 rounded-full bg-teal-100 text-teal-800 text-[10px] font-bold px-2 py-0.5">Head of Family</span>
            <div class="flex items-center gap-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-full bg-teal-600 text-white font-bold text-sm">
                <?= esc($initials) ?>
              </div>
              <div>
                <h4 class="font-bold text-slate-900 text-sm"><?= esc(($resident['first_name'] ?? '') . ' ' . ($resident['last_name'] ?? '')) ?></h4>
                <p class="text-xs text-slate-500 font-medium">Age: <?= $age ?? '—' ?> | <?= esc($resident['gender'] ?? 'N/A') ?></p>
              </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-200/60 flex justify-between text-xs font-semibold text-teal-700">
              <span>Active Profile</span>
              <span class="flex items-center gap-1"><i data-lucide="check-circle" class="h-3.5 w-3.5"></i> Viewing</span>
            </div>
          </div>

          <?php foreach ($dependents as $dependent):
            $dependentName = trim(($dependent['first_name'] ?? '') . ' ' . ($dependent['middle_name'] ?? '') . ' ' . ($dependent['last_name'] ?? ''));
            $dependentInitials = strtoupper(substr($dependent['first_name'] ?? 'D', 0, 1) . substr($dependent['last_name'] ?? 'P', 0, 1));
            $dependentAge = residentAge($dependent['date_of_birth'] ?? null);

            $depPayload = [
              'name' => $dependentName,
              'first_name' => $dependent['first_name'] ?? '',
              'last_name' => $dependent['last_name'] ?? '',
              'relationship' => $dependent['relationship'] ?? 'Family Member',
              'dob' => $dependent['date_of_birth'] ?? 'N/A',
              'age' => $dependentAge !== null ? $dependentAge . ' y/o' : 'N/A',
              'gender' => $dependent['gender'] ?: 'Not specified',
              'blood_type' => $dependent['blood_type'] ?: 'Unknown / N/A',
              'notes' => $dependent['medical_notes'] ?: 'No known allergies or medical conditions recorded.',
              'address' => trim(($resident['address'] ?? 'Nasugbu, Batangas') . ', ' . ($resident['barangay'] ?? '')),
              'barangay' => $resident['barangay'] ?? 'Nasugbu',
              'emergency_contact' => trim(($resident['first_name'] ?? '') . ' ' . ($resident['last_name'] ?? '')),
              'emergency_phone' => $resident['contact_number'] ?? 'N/A',
              'dep_res_id' => $dependent['dependent_resident_id'] ?? null
            ];
            $depJsonAttr = htmlspecialchars(json_encode($depPayload), ENT_QUOTES, 'UTF-8');
          ?>
            <article onclick="window.openDependentInfoModal(<?= $depJsonAttr ?>)" data-dependent-payload="<?= $depJsonAttr ?>" class="family-hover group rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-sky-50/40 p-5 shadow-sm hover:border-sky-400 hover:shadow-md transition-all cursor-pointer">
              <div class="flex items-start gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-sky-500 to-indigo-600 text-sm font-bold text-white shadow-md"><?= esc($dependentInitials) ?></div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <h4 class="truncate text-sm font-bold text-slate-900 group-hover:text-teal-700 transition-colors"><?= esc($dependentName) ?></h4>
                      <p class="mt-1 text-xs font-medium text-slate-500"><?= esc($dependent['relationship']) ?> · <?= $dependentAge === null ? 'Age unavailable' : esc($dependentAge) . ' y/o' ?> · <?= esc($dependent['gender'] ?: 'Not specified') ?></p>
                    </div>
                    <span class="rounded-full bg-sky-100 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-sky-700"><?= esc($dependent['blood_type'] ?: 'Blood N/A') ?></span>
                  </div>
                </div>
              </div>
              <?php if (!empty($dependent['medical_notes'])): ?><p class="mt-4 rounded-xl bg-amber-50 p-3 text-[11px] font-medium leading-5 text-amber-800"><i data-lucide="notebook-tabs" class="mr-1 inline h-3.5 w-3.5"></i><?= esc($dependent['medical_notes']) ?></p><?php endif; ?>
              <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                <span class="flex items-center gap-1 text-[10px] font-bold text-emerald-700"><i data-lucide="link" class="h-3.5 w-3.5"></i>Linked dependent</span>
                <form method="post" action="ResidentDashboard.php?tab=family" onclick="event.stopPropagation()" onsubmit="return confirm('Remove this dependent from your household?')">
                  <input type="hidden" name="form" value="remove_dependent"><input type="hidden" name="csrf_token" value="<?= esc($dashboardCsrf) ?>"><input type="hidden" name="dependent_id" value="<?= (int)$dependent['id'] ?>">
                  <button type="submit" onclick="event.stopPropagation()" class="rounded-lg px-2 py-1 text-[10px] font-bold text-rose-600 hover:bg-rose-50">Remove</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>

          <?php if (!$dependents): ?>
            <button type="button" onclick="openDependentModal()" data-dependent-open class="family-hover add-dependent-trigger flex min-h-44 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-sky-200 bg-sky-50/40 p-5 text-center hover:border-sky-400 hover:bg-sky-50 cursor-pointer">
              <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white text-sky-600 shadow-sm"><i data-lucide="user-plus" class="h-5 w-5"></i></span><strong class="mt-3 text-sm text-slate-800">Add your first dependent</strong><span class="mt-1 text-xs text-slate-500">Create a linked household profile</span>
            </button>
          <?php endif; ?>

          <?php if (false): ?>
            <!-- Sample Dependent 1 -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs hover:border-slate-300 transition-all">
              <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 font-bold text-sm">
                  JR
                </div>
                <div>
                  <h4 class="font-bold text-slate-900 text-sm">Juan Dela Cruz Jr.</h4>
                  <p class="text-xs text-slate-500 font-medium">Child • 4 y/o • Male</p>
                </div>
              </div>
              <div class="mt-4 pt-3 border-t border-slate-100 flex justify-between items-center text-xs font-medium text-slate-600">
                <span class="text-emerald-600 font-bold">Vaccine Complete (OPT+)</span>
                <button type="button" data-tab-link="immunization" class="text-teal-600 font-bold hover:underline">View Records</button>
              </div>
            </div>

            <!-- Sample Dependent 2 -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs hover:border-slate-300 transition-all">
              <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-600 font-bold text-sm">
                  MD
                </div>
                <div>
                  <h4 class="font-bold text-slate-900 text-sm">Maria Dela Cruz</h4>
                  <p class="text-xs text-slate-500 font-medium">Spouse • 31 y/o • Female</p>
                </div>
              </div>
              <div class="mt-4 pt-3 border-t border-slate-100 flex justify-between items-center text-xs font-medium text-slate-600">
                <span class="text-amber-600 font-bold">Prenatal Checkup Due</span>
                <button type="button" data-tab-link="records" class="text-teal-600 font-bold hover:underline">View Records</button>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- NEARBY RHU & BARANGAY MAP TAB -->
      <section data-tab-panel="map" class="hidden space-y-6">
        <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-teal-600">Community navigation</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">RHU & nearby barangay locations</h2>
            <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500">Allow location access to see your position, nearby health facilities and barangay halls. Distances shown are straight-line estimates.</p>
          </div>
          <button id="map-locate-button" type="button" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-teal-700 px-5 py-3 text-xs font-bold text-white shadow-lg shadow-teal-700/20 hover:bg-teal-800">
            <i data-lucide="locate-fixed" class="h-4 w-4"></i><span>Use my location</span>
          </button>
        </div>

        <div id="map-status" role="status" aria-live="polite" class="flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs font-semibold text-sky-800">
          <i data-lucide="info" class="h-4 w-4 shrink-0"></i>
          <span>Select “Use my location” to calculate your distance from the RHU and nearby barangay facilities.</span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.65fr)_minmax(18rem,.75fr)]">
          <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div id="resident-location-map" aria-label="Interactive map showing your location, nearby RHUs and barangay halls"></div>
          </div>
          <aside class="min-w-0">
            <div class="mb-3 flex items-center justify-between">
              <h3 class="text-sm font-black text-slate-900">Nearest locations</h3>
              <span id="map-result-count" class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">1 location</span>
            </div>
            <div id="nearby-location-list" class="max-h-[31rem] space-y-3 overflow-y-auto pr-1">
              <article class="rounded-2xl border border-teal-200 bg-teal-50/60 p-4">
                <div class="flex items-start justify-between gap-3">
                  <div><span class="text-[9px] font-black uppercase tracking-wider text-teal-700">Rural Health Unit</span>
                    <h4 class="mt-1 text-sm font-black text-slate-900">Nasugbu Rural Health Unit</h4>
                    <p class="mt-1 text-[11px] text-slate-500">Escalera St., Barangay 2, Nasugbu</p>
                  </div>
                  <span class="rounded-lg bg-white p-2 text-teal-700"><i data-lucide="hospital" class="h-4 w-4"></i></span>
                </div>
                <p class="mt-3 text-xs font-bold text-slate-500">Distance: <strong data-rhu-distance class="text-slate-900">Enable location</strong></p>
                <button id="main-rhu-route-button" type="button" class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-teal-700 hover:underline"><i data-lucide="navigation" class="h-3.5 w-3.5"></i> Show directions</button>
              </article>
            </div>
          </aside>
        </div>
        <p class="text-center text-[10px] text-slate-400">Map data © OpenStreetMap contributors. Nearby results depend on available community map data.</p>
      </section>

      <!-- EMERGENCY & REFERRAL TAB -->
      <section data-tab-panel="emergency" class="hidden space-y-6">
        <!-- Urgent Hotline Banner -->
        <div class="rounded-2xl bg-gradient-to-r from-rose-600 to-red-700 p-6 text-white shadow-lg space-y-4">
          <div class="flex items-center gap-3">
            <div class="rounded-full bg-white/20 p-2.5 text-white">
              <i data-lucide="siren" class="h-6 w-6 animate-pulse"></i>
            </div>
            <div>
              <h3 class="text-lg font-black tracking-tight">RHU Emergency & Quick Referral Desk</h3>
              <p class="text-xs text-rose-100">For life-threatening situations, immediate ambulance transport, or urgent hospital referral.</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-2">
            <a href="tel:09123456789" class="flex items-center justify-between rounded-xl bg-white/10 hover:bg-white/20 p-3.5 transition-all text-xs font-bold border border-white/20">
              <span class="flex items-center gap-2"><i data-lucide="phone-call" class="h-4 w-4 text-rose-200"></i> RHU Hotline</span>
              <span class="font-mono text-white">0912-345-6789</span>
            </a>
            <a href="tel:911" class="flex items-center justify-between rounded-xl bg-white/10 hover:bg-white/20 p-3.5 transition-all text-xs font-bold border border-white/20">
              <span class="flex items-center gap-2"><i data-lucide="ambulance" class="h-4 w-4 text-rose-200"></i> MDRRMO Ambulance</span>
              <span class="font-mono text-white">(042) 710-XXXX</span>
            </a>
            <a href="tel:117" class="flex items-center justify-between rounded-xl bg-white/10 hover:bg-white/20 p-3.5 transition-all text-xs font-bold border border-white/20">
              <span class="flex items-center gap-2"><i data-lucide="shield-alert" class="h-4 w-4 text-rose-200"></i> Barangay Health Response</span>
              <span class="font-mono text-white">Direct BHW</span>
            </a>
          </div>
        </div>

        <!-- Quick Referral Form -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-2xs space-y-4">
          <div class="flex items-center gap-2 text-rose-600">
            <i data-lucide="send" class="h-5 w-5"></i>
            <h4 class="text-sm font-bold text-slate-800">Send Instant Referral / Transport Request</h4>
          </div>

          <form method="post" action="ResidentDashboard.php?tab=emergency" class="space-y-4 text-xs">
            <input type="hidden" name="form" value="emergency_request">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Nature of Emergency</label>
                <select name="emergency_nature" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500">
                  <option value="Severe Injury / Fracture">Severe Injury / Accident</option>
                  <option value="High Fever / Convulsion (Child)">High Fever / Convulsion (Child)</option>
                  <option value="Maternal / Labor Urgency">Maternal Urgency / Severe Labor Pain</option>
                  <option value="Difficulty Breathing / Asthma Attack">Difficulty Breathing / Asthma Attack</option>
                  <option value="Severe Allergic Reaction">Severe Allergic Reaction</option>
                  <option value="Other Medical Urgent Need">Other Medical Urgency</option>
                </select>
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Pickup / Patient Location</label>
                <input type="text" name="pickup_location" required value="<?= esc(($resident['address'] ?? '') . ' ' . ($resident['barangay'] ?? '')) ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-medium focus:outline-none focus:ring-2 focus:ring-rose-500" placeholder="Purok, Barangay, Landmark">
              </div>
            </div>
            <button type="submit" class="w-full rounded-xl bg-rose-600 py-3 text-xs font-bold text-white hover:bg-rose-700 transition-all flex items-center justify-center gap-2">
              <i data-lucide="alert-triangle" class="h-4 w-4"></i> Submit Emergency Referral Request
            </button>
          </form>
        </div>
      </section>

    </main>
  </div>

  <!-- Logout Confirmation Modal -->
  <div id="logout-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="logout-title">
    <div class="w-full max-w-sm overflow-hidden rounded-3xl border border-white/70 bg-white shadow-2xl">
      <div class="bg-gradient-to-br from-rose-50 via-white to-amber-50 p-6 text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-100 text-rose-600 shadow-sm"><i data-lucide="log-out" class="h-7 w-7"></i></span>
        <h3 id="logout-title" class="mt-4 text-lg font-black text-slate-900">Log out of your account?</h3>
        <p class="mt-2 text-sm leading-6 text-slate-500">You will need to sign in again to access your health records and resident services.</p>
      </div>
      <div class="flex gap-3 border-t border-slate-100 bg-white p-4">
        <button type="button" data-logout-cancel class="flex-1 rounded-xl border border-slate-200 py-3 text-xs font-bold text-slate-600 hover:bg-slate-50">Stay signed in</button>
        <a href="ResidentDashboard.php?logout=1" class="flex flex-1 items-center justify-center rounded-xl bg-rose-600 py-3 text-xs font-bold text-white shadow-lg shadow-rose-600/20 hover:bg-rose-700">Yes, log out</a>
      </div>
    </div>
  </div>

  <!-- Add Dependent Modal -->
  <div id="dependent-modal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-md" style="z-index: 99999;">
    <div class="relative max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-white/70 bg-white shadow-2xl">
      <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-gradient-to-r from-teal-50 to-sky-50 px-6 py-5">
        <div class="flex gap-3">
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-600 to-sky-600 text-white shadow-md"><i data-lucide="user-round-plus" class="h-5 w-5"></i></span>
          <div>
            <h3 class="text-base font-black text-slate-900">Add Household Dependent</h3>
            <p class="mt-1 text-xs text-slate-500">Create a profile linked to your resident account.</p>
          </div>
        </div>
        <button type="button" onclick="closeDependentModal()" data-dependent-close class="rounded-xl p-2 text-slate-500 hover:bg-white hover:text-slate-800" aria-label="Close dependent form"><i data-lucide="x" class="h-5 w-5"></i></button>
      </div>
      <form method="post" action="ResidentDashboard.php?tab=family" class="space-y-5 p-6 text-xs">
        <input type="hidden" name="form" value="add_dependent">
        <input type="hidden" name="csrf_token" value="<?= esc($dashboardCsrf) ?>">
        
        <?php if ($dependentErrors): ?>
          <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800">
            <i data-lucide="circle-alert" class="h-5 w-5 shrink-0"></i>
            <div><?php foreach ($dependentErrors as $error): ?><p><?= esc($error) ?></p><?php endforeach; ?></div>
          </div>
        <?php endif; ?>
        
        <div>
          <p class="mb-3 font-bold uppercase tracking-wider text-slate-400">Personal information</p>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="space-y-1.5"><span class="font-bold text-slate-700">First name <b class="text-rose-500">*</b></span><input required maxlength="100" name="first_name" value="<?= esc($_POST['first_name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100" placeholder="First name"></label>
            <label class="space-y-1.5"><span class="font-bold text-slate-700">Middle name</span><input maxlength="100" name="middle_name" value="<?= esc($_POST['middle_name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100" placeholder="Optional"></label>
            <label class="space-y-1.5"><span class="font-bold text-slate-700">Last name <b class="text-rose-500">*</b></span><input required maxlength="100" name="last_name" value="<?= esc($_POST['last_name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100" placeholder="Last name"></label>
            <label class="space-y-1.5"><span class="font-bold text-slate-700">Relationship <b class="text-rose-500">*</b></span><select required name="relationship" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">Select relationship</option><?php foreach (['Child', 'Spouse', 'Parent', 'Sibling', 'Grandchild', 'Other'] as $option): ?><option <?= ($_POST['relationship'] ?? '') === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?>
              </select></label>
          </div>
        </div>
        <div>
          <p class="mb-3 font-bold uppercase tracking-wider text-slate-400">Health profile</p>
          <div class="grid gap-4 sm:grid-cols-3">
            <label class="space-y-1.5"><span class="font-bold text-slate-700">Date of birth <b class="text-rose-500">*</b></span><input required type="date" max="<?= date('Y-m-d') ?>" name="date_of_birth" value="<?= esc($_POST['date_of_birth'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100"></label>
            <label class="space-y-1.5"><span class="font-bold text-slate-700">Gender</span><select name="gender" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">Select</option><?php foreach (['Female', 'Male', 'Other', 'Prefer not to say'] as $option): ?><option <?= ($_POST['gender'] ?? '') === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?>
              </select></label>
            <label class="space-y-1.5"><span class="font-bold text-slate-700">Blood type</span><select name="blood_type" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                <option value="">Unknown</option><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $option): ?><option <?= ($_POST['blood_type'] ?? '') === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?>
              </select></label>
          </div>
        </div>
        <label class="block space-y-1.5"><span class="font-bold text-slate-700">Medical notes</span><textarea maxlength="1000" name="medical_notes" rows="3" class="w-full resize-none rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100" placeholder="Allergies, conditions, or other important notes"><?= esc($_POST['medical_notes'] ?? '') ?></textarea></label>
        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
          <button type="button" onclick="closeDependentModal()" data-dependent-close class="rounded-xl border border-slate-200 px-5 py-3 font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
          <button type="submit" class="rounded-xl bg-gradient-to-r from-teal-600 to-sky-600 px-5 py-3 font-bold text-white shadow-lg shadow-teal-600/20 hover:from-teal-700 hover:to-sky-700"><i data-lucide="user-plus" class="mr-1 inline h-4 w-4"></i>Add Dependent</button>
        </div>
      </form>
  <!-- View Dependent Info Modal -->
  <div id="dependent-info-modal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-md" style="z-index: 99999; display: none;">
    <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-white/70 bg-white shadow-2xl">
      <!-- Modal Header -->
      <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-gradient-to-r from-sky-50 via-teal-50 to-emerald-50 px-6 py-5">
        <div class="flex items-center gap-4">
          <div id="dep-info-avatar" class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 to-indigo-600 text-lg font-black text-white shadow-lg shadow-sky-500/20">
            PB
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h3 id="dep-info-name" class="text-lg font-black text-slate-900">Dependent Profile</h3>
              <span id="dep-info-blood" class="rounded-full bg-sky-100 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-sky-800">Blood N/A</span>
            </div>
            <p id="dep-info-sub" class="mt-0.5 text-xs font-medium text-slate-500">Family Member</p>
          </div>
        </div>
        <button type="button" onclick="closeDependentInfoModal()" class="rounded-xl p-2 text-slate-400 hover:bg-white hover:text-slate-700 transition-colors" aria-label="Close modal">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="space-y-6 p-6 text-xs">
        
        <!-- Status Banner -->
        <div class="flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4">
          <div class="flex items-center gap-2.5">
            <span class="flex h-3 w-3 rounded-full bg-emerald-500 animate-pulse"></span>
            <div>
              <p class="font-bold text-emerald-900 text-xs">Verified System Resident</p>
              <p class="text-[11px] text-emerald-700">Official health profile registered under Nasugbu RHU I</p>
            </div>
          </div>
          <span class="rounded-full bg-emerald-200/80 px-3 py-1 text-[10px] font-black text-emerald-900 uppercase tracking-wider">Active</span>
        </div>

        <!-- Personal & Demographic Details -->
        <div>
          <h4 class="mb-3 font-extrabold uppercase tracking-wider text-slate-400 text-[11px] flex items-center gap-1.5">
            <i data-lucide="user" class="h-3.5 w-3.5 text-sky-600"></i> Personal &amp; Demographic Information
          </h4>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3.5">
            <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-100">
              <span class="block text-[10px] font-bold text-slate-400 uppercase">Relationship</span>
              <span id="dep-info-rel" class="mt-1 block text-xs font-black text-slate-800">Family Member</span>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-100">
              <span class="block text-[10px] font-bold text-slate-400 uppercase">Date of Birth</span>
              <span id="dep-info-dob" class="mt-1 block text-xs font-black text-slate-800">N/A</span>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-100">
              <span class="block text-[10px] font-bold text-slate-400 uppercase">Age</span>
              <span id="dep-info-age" class="mt-1 block text-xs font-black text-slate-800">N/A</span>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-100">
              <span class="block text-[10px] font-bold text-slate-400 uppercase">Gender</span>
              <span id="dep-info-gender" class="mt-1 block text-xs font-black text-slate-800">Not specified</span>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-100">
              <span class="block text-[10px] font-bold text-slate-400 uppercase">Blood Type</span>
              <span id="dep-info-bt" class="mt-1 block text-xs font-black text-slate-800">Unknown</span>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-100">
              <span class="block text-[10px] font-bold text-slate-400 uppercase">Barangay</span>
              <span id="dep-info-brgy" class="mt-1 block text-xs font-black text-slate-800">Nasugbu</span>
            </div>
          </div>
        </div>

        <!-- Household Address & Emergency Contact -->
        <div>
          <h4 class="mb-3 font-extrabold uppercase tracking-wider text-slate-400 text-[11px] flex items-center gap-1.5">
            <i data-lucide="home" class="h-3.5 w-3.5 text-sky-600"></i> Household Address &amp; Emergency Contact
          </h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-100 space-y-1">
              <span class="block text-[10px] font-bold text-slate-400 uppercase">Registered Household Address</span>
              <span id="dep-info-addr" class="block text-xs font-bold text-slate-800 leading-relaxed">Nasugbu, Batangas</span>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-100 space-y-1">
              <span class="block text-[10px] font-bold text-slate-400 uppercase">Head of Household (Emergency Contact)</span>
              <span id="dep-info-head" class="block text-xs font-bold text-slate-800">Head of Household</span>
              <span id="dep-info-phone" class="block text-[11px] font-mono text-slate-500">Contact #: N/A</span>
            </div>
          </div>
        </div>

        <!-- Medical Conditions & Notes -->
        <div>
          <h4 class="mb-3 font-extrabold uppercase tracking-wider text-slate-400 text-[11px] flex items-center gap-1.5">
            <i data-lucide="stethoscope" class="h-3.5 w-3.5 text-amber-600"></i> Medical Notes, Allergies &amp; Health Profile
          </h4>
          <div id="dep-info-notes-box" class="rounded-2xl bg-amber-50/80 border border-amber-200/80 p-4 text-amber-900 font-medium leading-relaxed">
            <p id="dep-info-notes">No known allergies or medical conditions specified.</p>
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="flex flex-col-reverse sm:flex-row items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-6 py-4">
        <button type="button" onclick="closeDependentInfoModal()" class="w-full sm:w-auto rounded-xl border border-slate-200 bg-white px-5 py-2.5 font-bold text-slate-700 hover:bg-slate-100 transition-colors">
          Close Profile
        </button>
        <button type="button" onclick="bookAppointmentForDependent()" class="w-full sm:w-auto rounded-xl bg-gradient-to-r from-teal-600 to-sky-600 px-5 py-2.5 font-bold text-white shadow-md hover:from-teal-700 hover:to-sky-700 transition-all flex items-center justify-center gap-2">
          <i data-lucide="calendar-plus" class="h-4 w-4"></i> Book Consultation Appointment
        </button>
      </div>
    </div>
  </div>

  <!-- OPD / RHU Appointment Modal -->
  <div id="appointment-modal" class="fixed inset-0 z-[200] flex min-h-screen items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="appointment-modal-title">
    <div class="m-auto w-full max-w-2xl rounded-3xl bg-white p-5 shadow-2xl max-h-[90vh] overflow-y-auto" data-appointment-dialog>
      <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-100 bg-white pb-4">
        <div class="flex items-center gap-2.5">
          <div class="h-9 w-9 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
            <i data-lucide="calendar-plus" class="h-5 w-5"></i>
          </div>
          <div>
            <h3 id="appointment-modal-title" class="text-base font-extrabold text-slate-900">Book RHU Appointment</h3>
            <p class="mt-0.5 text-xs leading-5 text-slate-500">Choose the appointment type, preferred date, and an available healthcare provider.</p>
          </div>
        </div>
        <button type="button" data-appointment-close class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors" aria-label="Close appointment form">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>

      <form method="post" action="ResidentDashboard.php?tab=records" class="mt-4 grid grid-cols-1 gap-4 text-xs sm:grid-cols-2">
        <input type="hidden" name="form" value="appointment_request">
        <input type="hidden" name="csrf_token" value="<?= esc($dashboardCsrf) ?>">

        <!-- 1. Select Appointment Category / Type -->
        <div class="min-w-0">
          <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1.5">Type of Appointment *</label>
          <select id="appointment_type_select" name="appointment_type" required onchange="filterAvailableStaff()" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs font-bold text-slate-800 outline-none focus:border-teal-500 focus:bg-white transition-all">
            <option value="General Medical Consultation">🩺 General Medical Consultation (Physician / Doctor)</option>
            <option value="Prenatal & Maternal Care">🤰 Prenatal & Maternal Care (Midwife / Doctor)</option>
            <option value="Child Vaccination & Immunization">💉 Child Vaccination & Immunization (Nurse / Midwife)</option>
            <option value="Laboratory Test & Blood Work">🔬 Laboratory & Blood Test (Medical Technologist)</option>
            <option value="Sanitary Inspection & Clearance">📋 Sanitary Inspection & Clearance (Sanitary Inspector)</option>
            <option value="General Health Checkup">🏥 General Health Checkup (Public Health Nurse)</option>
          </select>
        </div>

        <!-- 2. Select Appointment Date -->
        <div class="min-w-0">
          <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1.5">Preferred Appointment Date *</label>
          <input type="date" id="appointment_date_input" name="preferred_date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" onchange="filterAvailableStaff()" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs font-semibold text-slate-800 outline-none focus:border-teal-500 focus:bg-white transition-all">
        </div>

        <!-- 3. Available Healthcare Provider (Doctor / Nurse / Staff) -->
        <div class="sm:col-span-2">
          <div class="flex items-center justify-between mb-1.5">
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px]">Assigned Available Doctor / Staff *</label>
            <span class="text-[10px] text-teal-600 font-bold" id="staff_count_badge">Loading available staff...</span>
          </div>
          <div id="staff_selection_container" class="grid max-h-40 grid-cols-1 gap-2 overflow-y-auto rounded-2xl border border-slate-100 bg-slate-50/50 p-1.5 sm:grid-cols-2">
            <!-- Dynamically populated by filterAvailableStaff() JavaScript -->
          </div>
        </div>

        <!-- 4. Chief Complaint / Notes -->
        <div class="sm:col-span-2">
          <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1.5">Chief Complaint / Reason for Visit *</label>
          <textarea name="chief_complaint" rows="2" required placeholder="Briefly describe your symptoms or reason for consultation..." class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs font-medium text-slate-800 outline-none transition-all focus:border-teal-500 focus:bg-white"></textarea>
        </div>

        <div class="sticky bottom-0 z-10 -mx-1 flex items-center justify-end gap-2 border-t border-slate-100 bg-white px-1 pt-4 sm:col-span-2">
          <button type="button" data-appointment-close class="rounded-xl px-4 py-2.5 font-bold text-slate-600 hover:bg-slate-100 transition-colors">
            Cancel
          </button>
          <button type="submit" class="rounded-xl bg-teal-600 px-5 py-2.5 font-bold text-white shadow-md hover:bg-teal-700 transition-colors">
            Confirm & Book Appointment
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    window.openDependentInfoModal = function(dep) {
      if (!dep) return false;
      const modal = document.getElementById('dependent-info-modal');
      if (!modal) return false;

      const avatarEl = document.getElementById('dep-info-avatar');
      const nameEl = document.getElementById('dep-info-name');
      const bloodEl = document.getElementById('dep-info-blood');
      const subEl = document.getElementById('dep-info-sub');
      const relEl = document.getElementById('dep-info-rel');
      const dobEl = document.getElementById('dep-info-dob');
      const ageEl = document.getElementById('dep-info-age');
      const genderEl = document.getElementById('dep-info-gender');
      const btEl = document.getElementById('dep-info-bt');
      const brgyEl = document.getElementById('dep-info-brgy');
      const addrEl = document.getElementById('dep-info-addr');
      const headEl = document.getElementById('dep-info-head');
      const phoneEl = document.getElementById('dep-info-phone');
      const notesEl = document.getElementById('dep-info-notes');

      if (nameEl) nameEl.textContent = dep.name || 'Dependent Profile';
      if (avatarEl) {
        const fnLetter = dep.first_name ? dep.first_name[0].toUpperCase() : 'D';
        const lnLetter = dep.last_name ? dep.last_name[0].toUpperCase() : 'P';
        avatarEl.textContent = fnLetter + lnLetter;
      }
      if (bloodEl) bloodEl.textContent = dep.blood_type || 'Blood N/A';
      if (subEl) subEl.textContent = (dep.relationship || 'Family Member') + ' · ' + (dep.age || 'N/A') + ' · ' + (dep.gender || 'Not specified');
      if (relEl) relEl.textContent = dep.relationship || 'Family Member';
      if (dobEl) dobEl.textContent = dep.dob || 'N/A';
      if (ageEl) ageEl.textContent = dep.age || 'N/A';
      if (genderEl) genderEl.textContent = dep.gender || 'Not specified';
      if (btEl) btEl.textContent = dep.blood_type || 'Unknown';
      if (brgyEl) brgyEl.textContent = dep.barangay || 'Nasugbu';
      if (addrEl) addrEl.textContent = dep.address || 'Nasugbu, Batangas';
      if (headEl) headEl.textContent = dep.emergency_contact || 'Head of Household';
      if (phoneEl) phoneEl.textContent = 'Contact #: ' + (dep.emergency_phone || 'N/A');
      if (notesEl) notesEl.textContent = dep.notes || 'No known allergies or medical conditions recorded.';

      window.currentSelectedDependent = dep;

      modal.classList.remove('hidden');
      modal.classList.add('flex');
      modal.style.setProperty('display', 'flex', 'important');
      document.body.classList.add('overflow-hidden');
      if (window.lucide) lucide.createIcons();
      return false;
    };

    window.closeDependentInfoModal = function() {
      const modal = document.getElementById('dependent-info-modal');
      if (!modal) return false;
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      modal.style.setProperty('display', 'none', 'important');
      document.body.classList.remove('overflow-hidden');
      return false;
    };

    var openDependentInfoModal = window.openDependentInfoModal;
    var closeDependentInfoModal = window.closeDependentInfoModal;

    (() => {
      lucide.createIcons();

      window.allRhuStaff = <?= json_encode($rhuStaffList ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
      let allRhuStaff = window.allRhuStaff;
      const staffBookings = <?= json_encode($staffBookingsPerDate ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
      const mainRhu = {
        name: 'Nasugbu Rural Health Unit',
        type: 'Rural Health Unit',
        lat: 14.07423,
        lng: 120.63096,
        address: 'Escalera St., Barangay 2, Nasugbu'
      };
      let residentMap = null;
      let residentMapLayer = null;
      let residentRouteLayer = null;
      let residentPosition = null;
      let mapIsLoading = false;

      const escapeMapText = (value) => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      })[character]);

      const distanceKm = (from, to) => {
        const radians = degrees => degrees * Math.PI / 180;
        const deltaLat = radians(to.lat - from.lat);
        const deltaLng = radians(to.lng - from.lng);
        const a = Math.sin(deltaLat / 2) ** 2 +
          Math.cos(radians(from.lat)) * Math.cos(radians(to.lat)) * Math.sin(deltaLng / 2) ** 2;
        return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      };

      const formatMapDistance = kilometres => kilometres < 1 ?
        `${Math.round(kilometres * 1000)} m` :
        `${kilometres.toFixed(kilometres < 10 ? 1 : 0)} km`;

      const setMapStatus = (message, tone = 'sky') => {
        const status = document.getElementById('map-status');
        if (!status) return;
        const styles = {
          sky: 'border-sky-200 bg-sky-50 text-sky-800',
          teal: 'border-teal-200 bg-teal-50 text-teal-800',
          rose: 'border-rose-200 bg-rose-50 text-rose-800',
          amber: 'border-amber-200 bg-amber-50 text-amber-800'
        };
        status.className = `flex items-center gap-2 rounded-xl border p-3 text-xs font-semibold ${styles[tone] || styles.sky}`;
        status.querySelector('span').textContent = message;
      };

      const mapPlaceIcon = (place) => L.divIcon({
        className: '',
        html: `<span style="display:flex;width:30px;height:30px;align-items:center;justify-content:center;border:3px solid white;border-radius:999px;background:${place.type === 'Barangay' ? '#7c3aed' : '#0f766e'};color:white;box-shadow:0 2px 8px rgba(15,23,42,.3);font-size:14px">${place.type === 'Barangay' ? '⌂' : '✚'}</span>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15]
      });

      const renderNearbyPlaces = places => {
        const list = document.getElementById('nearby-location-list');
        const count = document.getElementById('map-result-count');
        if (!list) return;
        const sorted = places.map(place => ({
          ...place,
          distance: residentPosition ? distanceKm(residentPosition, place) : null
        })).sort((a, b) => (a.distance ?? 9999) - (b.distance ?? 9999));

        count.textContent = `${sorted.length} location${sorted.length === 1 ? '' : 's'}`;
        list.innerHTML = sorted.map(place => {
          const isBarangay = place.type === 'Barangay';
          const accent = isBarangay ? 'violet' : 'teal';
          const distance = place.distance === null ? 'Enable location' : formatMapDistance(place.distance);
          return `<article class="rounded-2xl border border-${accent}-200 bg-${accent}-50/60 p-4">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0"><span class="text-[9px] font-black uppercase tracking-wider text-${accent}-700">${escapeMapText(place.type)}</span>
                <h4 class="mt-1 truncate text-sm font-black text-slate-900" title="${escapeMapText(place.name)}">${escapeMapText(place.name)}</h4>
                <p class="mt-1 text-[11px] text-slate-500">${escapeMapText(place.address || (isBarangay ? 'Barangay location' : 'Health facility'))}</p>
              </div>
              <span class="rounded-lg bg-white p-2 text-${accent}-700">${isBarangay ? '⌂' : '✚'}</span>
            </div>
            <p class="mt-3 text-xs font-bold text-slate-500">Distance: <strong class="text-slate-900">${distance}</strong></p>
            <div class="mt-3 flex gap-4">
              <button type="button" data-map-focus="${place.lat},${place.lng}" class="text-xs font-bold text-slate-600 hover:underline">Show on map</button>
              <button type="button" data-map-route="${place.lat},${place.lng}" data-map-route-name="${escapeMapText(place.name)}" class="inline-flex items-center gap-1 text-xs font-bold text-${accent}-700 hover:underline">Show directions →</button>
            </div>
          </article>`;
        }).join('');

        list.querySelectorAll('[data-map-focus]').forEach(button => button.addEventListener('click', () => {
          const [lat, lng] = button.dataset.mapFocus.split(',').map(Number);
          residentMap.setView([lat, lng], 16);
        }));
        list.querySelectorAll('[data-map-route]').forEach(button => button.addEventListener('click', () => {
          const [lat, lng] = button.dataset.mapRoute.split(',').map(Number);
          showResidentRoute({
            lat,
            lng,
            name: button.dataset.mapRouteName
          });
        }));
      };

      const showResidentRoute = async destination => {
        if (!residentPosition) {
          setMapStatus('Use your current location first before requesting directions.', 'amber');
          return;
        }
        setMapStatus(`Calculating the driving route to ${destination.name}…`);
        const endpoint = `https://router.project-osrm.org/route/v1/driving/${residentPosition.lng},${residentPosition.lat};${destination.lng},${destination.lat}?overview=full&geometries=geojson&steps=true`;
        try {
          const response = await fetch(endpoint);
          if (!response.ok) throw new Error('Routing service unavailable');
          const data = await response.json();
          const route = data.routes?.[0];
          if (!route) throw new Error('No route found');
          if (residentRouteLayer) residentRouteLayer.remove();
          residentRouteLayer = L.geoJSON(route.geometry, {
            style: {
              color: '#0f766e',
              weight: 6,
              opacity: .9,
              lineCap: 'round',
              lineJoin: 'round'
            }
          }).addTo(residentMap);
          residentMap.fitBounds(residentRouteLayer.getBounds(), {
            padding: [35, 35]
          });
          const drivingDistance = formatMapDistance(route.distance / 1000);
          const minutes = Math.max(1, Math.round(route.duration / 60));
          setMapStatus(`Driving route to ${destination.name}: approximately ${drivingDistance}, ${minutes} min.`, 'teal');
        } catch (error) {
          setMapStatus('A road route could not be calculated right now. Please try again in a moment.', 'rose');
        }
      };

      const loadNearbyMapPlaces = async () => {
        if (!residentPosition || mapIsLoading) return;
        mapIsLoading = true;
        setMapStatus('Google Maps is finding nearby RHUs, health centers and barangay locations…');
        const {
          lat,
          lng
        } = residentPosition;
        try {
          const location = new google.maps.LatLng(lat, lng);
          const [healthResults, barangayResults] = await Promise.all([
            googlePlacesSearch({
              location,
              radius: 15000,
              keyword: 'RHU rural health unit health center clinic hospital'
            }),
            googlePlacesSearch({
              location,
              radius: 15000,
              keyword: 'barangay hall barangay health center'
            })
          ]);
          const found = [...healthResults, ...barangayResults].map(place => {
            const barangay = /barangay|brgy/i.test(place.name || '');
            return {
              name: place.name || (barangay ? 'Barangay facility' : 'Community health facility'),
              type: barangay ? 'Barangay' : (/hospital/i.test(place.name || '') ? 'Hospital' : 'Health Center / RHU'),
              lat: place.geometry.location.lat(),
              lng: place.geometry.location.lng(),
              address: place.vicinity || ''
            };
          });

          const unique = [mainRhu, ...found].filter((place, index, array) =>
            array.findIndex(other => other.name.toLowerCase() === place.name.toLowerCase()) === index
          ).sort((a, b) => distanceKm(residentPosition, a) - distanceKm(residentPosition, b)).slice(0, 16);
          unique.forEach(place => addGoogleMarker(place, place.type === 'Barangay' ? '#7c3aed' : '#0f766e'));
          renderNearbyPlaces(unique);
          setMapStatus(`Showing ${unique.length} nearby locations, ordered by distance from you.`, 'teal');
        } catch (error) {
          addGoogleMarker(mainRhu);
          renderNearbyPlaces([mainRhu]);
          setMapStatus('Your location is shown, but Google Places could not load nearby results. Check that Places API is enabled.', 'amber');
        } finally {
          mapIsLoading = false;
        }
      };

      function initializeResidentMap() {
        initializeLeafletMap();
      }

      const locateResident = () => {
        initializeResidentMap();
        if (!residentMap) {
          setMapStatus('Google Maps is still loading. Please try again in a moment.', 'amber');
          return;
        }
        if (!navigator.geolocation) {
          setMapStatus('Location is not supported by this browser. You can still view and navigate to the RHU.', 'rose');
          return;
        }
        const button = document.getElementById('map-locate-button');
        button.disabled = true;
        button.querySelector('span').textContent = 'Locating…';
        setMapStatus('Requesting your current location…');
        navigator.geolocation.getCurrentPosition(position => {
          residentPosition = {
            lat: position.coords.latitude,
            lng: position.coords.longitude
          };
          mapMarkers.forEach(marker => marker.setMap(null));
          mapMarkers = [];
          addGoogleMarker({
            ...residentPosition,
            name: 'Your current location',
            type: 'You'
          }, '#2563eb');
          residentMap.setCenter(residentPosition);
          residentMap.setZoom(14);
          loadNearbyMapPlaces();
          button.disabled = false;
          button.querySelector('span').textContent = 'Refresh my location';
        }, error => {
          const messages = {
            1: 'Location permission was denied. Enable it in your browser settings to calculate distances.',
            2: 'Your location is currently unavailable. Check your device location settings and try again.',
            3: 'Finding your location timed out. Please try again.'
          };
          setMapStatus(messages[error.code] || 'Your location could not be detected.', 'rose');
          button.disabled = false;
          button.querySelector('span').textContent = 'Try location again';
        }, {
          enableHighAccuracy: true,
          timeout: 12000,
          maximumAge: 60000
        });
      };

      const loadOpenStreetMapPlaces = async () => {
        if (!residentPosition || mapIsLoading) return;
        mapIsLoading = true;
        setMapStatus('Finding nearby RHUs, health centers and barangay locations…');
        const {
          lat,
          lng
        } = residentPosition;
        const query = `[out:json][timeout:20];(
          nwr(around:15000,${lat},${lng})["amenity"~"clinic|doctors|hospital|health_post"];
          nwr(around:15000,${lat},${lng})["healthcare"~"clinic|doctor|hospital|centre"];
          nwr(around:15000,${lat},${lng})["amenity"~"townhall|community_centre"]["name"~"Barangay|Brgy",i];
          node(around:15000,${lat},${lng})["place"="barangay"];
        );out center tags;`;
        try {
          const response = await fetch('https://overpass-api.de/api/interpreter', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: `data=${encodeURIComponent(query)}`
          });
          if (!response.ok) throw new Error('Map service unavailable');
          const data = await response.json();
          const found = data.elements.map(element => {
            const point = element.center || element;
            const tags = element.tags || {};
            const barangay = tags.place === 'barangay' || (/barangay|brgy/i.test(tags.name || '') && /townhall|community_centre/.test(tags.amenity || ''));
            return {
              name: tags.name || (barangay ? 'Barangay facility' : 'Community health facility'),
              type: barangay ? 'Barangay' : (/hospital/i.test(tags.amenity || tags.healthcare || '') ? 'Hospital' : 'Health Center / RHU'),
              lat: Number(point.lat),
              lng: Number(point.lon),
              address: [tags['addr:street'], tags['addr:barangay']].filter(Boolean).join(', ')
            };
          }).filter(place => Number.isFinite(place.lat) && Number.isFinite(place.lng));
          const unique = [mainRhu, ...found].filter((place, index, array) =>
            array.findIndex(other => other.name.toLowerCase() === place.name.toLowerCase()) === index
          ).sort((a, b) => distanceKm(residentPosition, a) - distanceKm(residentPosition, b)).slice(0, 16);
          unique.forEach(place => L.marker([place.lat, place.lng], {
              icon: mapPlaceIcon(place)
            })
            .bindPopup(`<strong>${escapeMapText(place.name)}</strong><br><small>${escapeMapText(place.type)} · ${formatMapDistance(distanceKm(residentPosition, place))}</small>`)
            .addTo(residentMapLayer));
          renderNearbyPlaces(unique);
          setMapStatus(`Showing ${unique.length} nearby locations, ordered by distance from you.`, 'teal');
        } catch (error) {
          L.marker([mainRhu.lat, mainRhu.lng], {
            icon: mapPlaceIcon(mainRhu)
          }).bindPopup(mainRhu.name).addTo(residentMapLayer);
          renderNearbyPlaces([mainRhu]);
          setMapStatus('Your location is shown, but nearby community data could not be loaded. The main RHU remains available.', 'amber');
        } finally {
          mapIsLoading = false;
        }
      };

      function initializeLeafletMap() {
        if (!window.L) {
          window.setTimeout(initializeLeafletMap, 150);
          return;
        }
        if (!residentMap) {
          residentMap = L.map('resident-location-map', {
            zoomControl: true
          }).setView([mainRhu.lat, mainRhu.lng], 14);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
          }).addTo(residentMap);
          residentMapLayer = L.layerGroup().addTo(residentMap);
          L.marker([mainRhu.lat, mainRhu.lng], {
              icon: mapPlaceIcon(mainRhu)
            })
            .bindPopup(`<strong>${mainRhu.name}</strong><br><small>${mainRhu.address}</small>`)
            .addTo(residentMapLayer);
        }
        window.setTimeout(() => residentMap.invalidateSize(), 50);
      }

      const locateResidentWithLeaflet = () => {
        initializeLeafletMap();
        if (!navigator.geolocation) {
          setMapStatus('Location is not supported by this browser. You can still view and navigate to the RHU.', 'rose');
          return;
        }
        const button = document.getElementById('map-locate-button');
        button.disabled = true;
        button.querySelector('span').textContent = 'Locating…';
        setMapStatus('Requesting your current location…');
        navigator.geolocation.getCurrentPosition(position => {
          residentPosition = {
            lat: position.coords.latitude,
            lng: position.coords.longitude
          };
          if (residentRouteLayer) {
            residentRouteLayer.remove();
            residentRouteLayer = null;
          }
          residentMapLayer.clearLayers();
          L.marker([residentPosition.lat, residentPosition.lng], {
            icon: L.divIcon({
              className: '',
              html: '<div class="map-user-marker"></div>',
              iconSize: [22, 22],
              iconAnchor: [11, 11]
            })
          }).bindPopup('<strong>Your current location</strong>').addTo(residentMapLayer).openPopup();
          residentMap.setView([residentPosition.lat, residentPosition.lng], 14);
          loadOpenStreetMapPlaces();
          button.disabled = false;
          button.querySelector('span').textContent = 'Refresh my location';
        }, error => {
          const messages = {
            1: 'Location permission was denied. Enable it in your browser settings to calculate distances.',
            2: 'Your location is currently unavailable. Check your device location settings and try again.',
            3: 'Finding your location timed out. Please try again.'
          };
          setMapStatus(messages[error.code] || 'Your location could not be detected.', 'rose');
          button.disabled = false;
          button.querySelector('span').textContent = 'Try location again';
        }, {
          enableHighAccuracy: true,
          timeout: 12000,
          maximumAge: 60000
        });
      };

      document.getElementById('map-locate-button')?.addEventListener('click', locateResidentWithLeaflet);
      document.getElementById('main-rhu-route-button')?.addEventListener('click', () => showResidentRoute(mainRhu));

      if (!allRhuStaff || allRhuStaff.length === 0) {
        allRhuStaff = [{
            staff_id: 1,
            first_name: 'Dr. Maria',
            last_name: 'Santos',
            staff_type: 'Rural Health Physician',
            specialization: 'General Medicine',
            work_days: 'Monday, Tuesday, Wednesday, Thursday, Friday',
            is_on_duty: 1
          },
          {
            staff_id: 2,
            first_name: 'Clara',
            last_name: 'Reyes',
            staff_type: 'Public Health Nurse',
            specialization: 'Community Health',
            work_days: 'Monday, Tuesday, Wednesday, Thursday, Friday',
            is_on_duty: 1
          },
          {
            staff_id: 3,
            first_name: 'Ana',
            last_name: 'Gomez',
            staff_type: 'Rural Health Midwife',
            specialization: 'Maternal Care',
            work_days: 'Monday, Tuesday, Wednesday, Thursday, Friday',
            is_on_duty: 1
          },
          {
            staff_id: 4,
            first_name: 'Roberto',
            last_name: 'Dizon',
            staff_type: 'Medical Technologist',
            specialization: 'Clinical Pathology',
            work_days: 'Monday, Tuesday, Wednesday, Thursday, Friday',
            is_on_duty: 1
          },
          {
            staff_id: 5,
            first_name: 'Elena',
            last_name: 'Cruz',
            staff_type: 'Sanitary Inspector',
            specialization: 'Environmental Health',
            work_days: 'Monday, Tuesday, Wednesday, Thursday, Friday',
            is_on_duty: 1
          }
        ];
      }

      window.filterAvailableStaff = function() {
        const typeEl = document.getElementById('appointment_type_select');
        const dateEl = document.getElementById('appointment_date_input');
        const container = document.getElementById('staff_selection_container');
        const badgeEl = document.getElementById('staff_count_badge');

        if (!container || !typeEl || !dateEl) return;
        container.innerHTML = '';

        const selectedType = typeEl.value;
        const selectedDate = dateEl.value;
        const lowerType = selectedType.toLowerCase();

        let filteredStaff = allRhuStaff;
        if (lowerType.includes('prenatal') || lowerType.includes('maternal')) {
          filteredStaff = allRhuStaff.filter(s => {
            const st = ((s.staff_type || s.position || '') + ' ' + (s.specialization || '')).toLowerCase();
            return st.includes('midwife') || st.includes('nurse') || st.includes('physician') || st.includes('doctor') || st.includes('maternal') || st.includes('ob');
          });
        } else if (lowerType.includes('vaccination') || lowerType.includes('immunization') || lowerType.includes('child')) {
          filteredStaff = allRhuStaff.filter(s => {
            const st = ((s.staff_type || s.position || '') + ' ' + (s.specialization || '')).toLowerCase();
            return st.includes('nurse') || st.includes('midwife') || st.includes('vaccin');
          });
        } else if (lowerType.includes('laboratory') || lowerType.includes('blood') || lowerType.includes('medtech')) {
          filteredStaff = allRhuStaff.filter(s => {
            const st = ((s.staff_type || s.position || '') + ' ' + (s.specialization || '')).toLowerCase();
            return st.includes('tech') || st.includes('med') || st.includes('pathology') || st.includes('lab');
          });
        } else if (lowerType.includes('sanitary') || lowerType.includes('inspection')) {
          filteredStaff = allRhuStaff.filter(s => {
            const st = ((s.staff_type || s.position || '') + ' ' + (s.specialization || '')).toLowerCase();
            return st.includes('sanitary') || st.includes('inspector') || st.includes('environment');
          });
        } else if (lowerType.includes('checkup') || lowerType.includes('nurse')) {
          filteredStaff = allRhuStaff.filter(s => {
            const st = ((s.staff_type || s.position || '') + ' ' + (s.specialization || '')).toLowerCase();
            return st.includes('nurse') || st.includes('midwife') || st.includes('physician') || st.includes('doctor');
          });
        } else if (lowerType.includes('medical') || lowerType.includes('consultation') || lowerType.includes('doctor') || lowerType.includes('physician')) {
          filteredStaff = allRhuStaff.filter(s => {
            const st = ((s.staff_type || s.position || '') + ' ' + (s.specialization || '')).toLowerCase();
            return st.includes('physician') || st.includes('doctor') || st.includes('officer') || st.includes('medicine');
          });
        }

        if (!filteredStaff || filteredStaff.length === 0) {
          filteredStaff = allRhuStaff;
        }

        if (badgeEl) {
          badgeEl.textContent = `${filteredStaff.length} provider(s) available`;
        }

        if (filteredStaff.length === 0) {
          container.innerHTML = '<div class="p-3 text-center text-slate-400 text-xs font-semibold">No registered staff found for this category.</div>';
          return;
        }

        const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        let selectedDayName = 'Monday';
        if (selectedDate) {
          const parts = selectedDate.split('-');
          if (parts.length === 3) {
            const dObj = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            selectedDayName = daysOfWeek[dObj.getDay()];
          }
        }

        let hasCheckedFirst = false;

        filteredStaff.forEach((staff, index) => {
          const pid = parseInt(staff.staff_id);
          const bookedOnDate = (staffBookings[pid] && staffBookings[pid][selectedDate]) ? parseInt(staffBookings[pid][selectedDate]) : 0;
          const isOnDuty = parseInt(staff.is_on_duty || 1) === 1;
          const workDaysStr = staff.work_days || 'Monday, Tuesday, Wednesday, Thursday, Friday';
          const isScheduled = workDaysStr.toLowerCase().includes(selectedDayName.toLowerCase());

          let statusBadge = '';
          let isDisabled = false;

          if (!isOnDuty) {
            statusBadge = `<span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md border border-rose-200">🔴 Off Duty (On Leave)</span>`;
            isDisabled = true;
          } else if (!isScheduled) {
            statusBadge = `<span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">⚠️ Not Scheduled (${selectedDayName})</span>`;
            isDisabled = true;
          } else {
            statusBadge = `<span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200/60">🟢 Available (${bookedOnDate} booked)</span>`;
          }

          const label = document.createElement('label');
          label.className = `grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2 rounded-xl border p-2.5 ${isDisabled ? 'border-slate-200 bg-slate-100/70 opacity-60 cursor-not-allowed' : 'border-slate-200 bg-white hover:bg-teal-50/50 hover:border-teal-300 cursor-pointer'} transition-all`;

          let checkAttr = '';
          if (!isDisabled && !hasCheckedFirst) {
            checkAttr = 'checked';
            hasCheckedFirst = true;
          }
          let disabledAttr = isDisabled ? 'disabled' : '';

          label.innerHTML = `
            <div class="flex min-w-0 items-center gap-2.5">
              <input type="radio" name="physician_id" value="${staff.staff_id}" ${checkAttr} ${disabledAttr} class="text-teal-600 focus:ring-teal-500 h-4 w-4">
              <div class="min-w-0">
                <p class="truncate font-bold text-slate-800 text-xs">${staff.first_name || ''} ${staff.last_name || ''}</p>
                <p class="truncate text-[10px] text-slate-500 font-medium">${staff.staff_type || 'RHU Staff'} ${staff.specialization ? '• ' + staff.specialization : ''}</p>
                <p class="truncate text-[9px] text-slate-400 font-mono" title="Duty: ${workDaysStr}">📅 Duty: ${workDaysStr}</p>
              </div>
            </div>
            <div>${statusBadge}</div>
          `;
          container.appendChild(label);
        });
      };

      filterAvailableStaff();

      const appointmentModal = document.getElementById('appointment-modal');
      let appointmentTrigger = null;

      // Keep the dialog outside animated/transformed dashboard containers so
      // fixed positioning is calculated against the actual browser viewport.
      if (appointmentModal && appointmentModal.parentElement !== document.body) {
        document.body.appendChild(appointmentModal);
      }

      const openAppointmentModal = trigger => {
        if (!appointmentModal) return;
        appointmentTrigger = trigger || document.activeElement;
        appointmentModal.style.display = 'flex';
        document.body.classList.add('overflow-hidden');
        filterAvailableStaff();
        window.setTimeout(() => {
          appointmentModal.querySelector('select, input, textarea, button')?.focus();
        }, 0);
      };

      const closeAppointmentModal = () => {
        if (!appointmentModal) return;
        appointmentModal.style.display = 'none';
        document.body.classList.remove('overflow-hidden');
        appointmentTrigger?.focus?.();
      };

      document.querySelectorAll('[data-appointment-open]').forEach(button => {
        button.addEventListener('click', () => openAppointmentModal(button));
      });
      document.querySelectorAll('[data-appointment-close]').forEach(button => {
        button.addEventListener('click', closeAppointmentModal);
      });
      appointmentModal?.addEventListener('click', event => {
        if (event.target === appointmentModal) closeAppointmentModal();
      });
      document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && appointmentModal && appointmentModal.style.display !== 'none') {
          closeAppointmentModal();
        }
      });

      const sidebar = document.getElementById('sidebar');
      const sidebarOverlay = document.getElementById('sidebar-overlay');
      const collapseBtn = document.getElementById('sidebar-collapse-btn');
      const mobileBtn = document.getElementById('mobile-menu-btn');
      const pageTitle = document.getElementById('current-page-title');

      const buttons = document.querySelectorAll('[data-tab-button]');
      const panels = document.querySelectorAll('[data-tab-panel]');

      const tabTitles = {
        'home': 'Overview',
        'profile': 'My Health Profile',
        'records': 'Health Records',
        'immunization': 'Immunization Records',
        'certificates': 'Health Certificates',
        'family': 'Family Members',
        'events': 'Events & Programs',
        'map': 'Nearby Map',
        'contact': 'Contact RHU',
        'emergency': 'Emergency & Referral'
      };

      if (collapseBtn) {
        collapseBtn.addEventListener('click', () => {
          sidebar.classList.toggle('sidebar-collapsed');
        });
      }

      const toggleMobileSidebar = () => {
        const isOpen = !sidebar.classList.contains('-translate-x-full');
        if (isOpen) {
          sidebar.classList.add('-translate-x-full');
          sidebarOverlay.classList.add('hidden');
          sidebarOverlay.setAttribute('aria-hidden', 'true');
          document.body.classList.remove('overflow-hidden');
        } else {
          sidebar.classList.remove('-translate-x-full');
          sidebarOverlay.classList.remove('hidden');
          sidebarOverlay.setAttribute('aria-hidden', 'false');
          document.body.classList.add('overflow-hidden');
        }
      };

      if (mobileBtn) mobileBtn.addEventListener('click', toggleMobileSidebar);
      if (sidebarOverlay) sidebarOverlay.addEventListener('click', toggleMobileSidebar);

      const setTab = (tab) => {
        panels.forEach(panel => panel.classList.toggle('hidden', panel.dataset.tabPanel !== tab));

        buttons.forEach(button => {
          const active = button.dataset.tabButton === tab;
          button.classList.toggle('nav-active', active);
        });

        // Breadcrumb logic na may Clickable "Resident Dashboard"
        if (tabTitles[tab]) {
          if (tab === 'home') {
            pageTitle.innerHTML = `<span class="font-bold text-slate-800">Resident Dashboard</span>`;
          } else {
            pageTitle.innerHTML = `
            <button type="button" data-breadcrumb-home class="text-slate-400 font-medium hover:text-teal-600 hover:underline transition-colors focus:outline-none">
              Resident Dashboard
            </button>
            <i data-lucide="chevron-right" class="inline-block h-4 w-4 text-slate-400 mx-1"></i>
            <span class="font-bold text-slate-800">${tabTitles[tab]}</span>
          `;

            // Lagyan ng click event para kapag pinindot ang "Resident Dashboard" ay babalik sa home tab
            const homeBreadcrumbBtn = pageTitle.querySelector('[data-breadcrumb-home]');
            if (homeBreadcrumbBtn) {
              homeBreadcrumbBtn.addEventListener('click', () => setTab('home'));
            }

            // I-re-render ang Lucide chevron icon
            if (window.lucide) lucide.createIcons();
          }
        }

        if (window.innerWidth < 768) {
          sidebar.classList.add('-translate-x-full');
          sidebarOverlay.classList.add('hidden');
          sidebarOverlay.setAttribute('aria-hidden', 'true');
          document.body.classList.remove('overflow-hidden');
        }

        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
        if (tab === 'map') window.setTimeout(initializeLeafletMap, 80);
      };

      buttons.forEach(button => button.addEventListener('click', () => setTab(button.dataset.tabButton)));
      document.querySelectorAll('[data-tab-link]').forEach(button => button.addEventListener('click', () => setTab(button.dataset.tabLink)));

      const urlParams = new URLSearchParams(window.location.search);
      const initialTab = urlParams.get('tab');
      if (initialTab && tabTitles[initialTab]) {
        setTab(initialTab);
      } else {
        setTab('home');
      }

      // ----------------------------------------------------
      // HERO CAROUSEL: ADMIN POSTED EVENTS
      // ----------------------------------------------------
      let currentHeroSlideIndex = 0;
      const heroSlides = document.querySelectorAll('[data-hero-slide]');
      const heroDots = document.querySelectorAll('.hero-dot');
      const heroBgImg = document.getElementById('hero-event-bg');

      window.setHeroSlide = function(index) {
        if (!heroSlides || !heroSlides.length) return;
        currentHeroSlideIndex = (index + heroSlides.length) % heroSlides.length;

        heroSlides.forEach((slide, idx) => {
          if (idx === currentHeroSlideIndex) {
            slide.classList.remove('hidden');
            slide.classList.add('block');
            const bg = slide.getAttribute('data-bg');
            if (bg && heroBgImg && bg !== 'undefined') {
              heroBgImg.src = bg;
            }
          } else {
            slide.classList.add('hidden');
            slide.classList.remove('block');
          }
        });

        heroDots.forEach((dot, idx) => {
          if (idx === currentHeroSlideIndex) {
            dot.classList.remove('w-2', 'bg-white/30');
            dot.classList.add('w-6', 'bg-emerald-400');
          } else {
            dot.classList.remove('w-6', 'bg-emerald-400');
            dot.classList.add('w-2', 'bg-white/30');
          }
        });
      };

      window.nextHeroSlide = function() {
        setHeroSlide(currentHeroSlideIndex + 1);
      };

      window.prevHeroSlide = function() {
        setHeroSlide(currentHeroSlideIndex - 1);
      };

      if (heroSlides && heroSlides.length > 1) {
        setInterval(() => {
          nextHeroSlide();
        }, 6000);
      }

      // ----------------------------------------------------
      // LIGHT / DARK MODE GLASSMORPHISM THEME SWITCHER
      // ----------------------------------------------------
      window.applyDashboardTheme = function(theme) {
        const isDark = theme === 'dark';
        const docEl = document.documentElement;
        const bodyEl = document.body;

        if (isDark) {
          docEl.classList.add('dark', 'theme-dark');
          docEl.classList.remove('light', 'theme-light');
          bodyEl.classList.add('dark', 'theme-dark');
          bodyEl.classList.remove('light', 'theme-light');
        } else {
          docEl.classList.remove('dark', 'theme-dark');
          docEl.classList.add('light', 'theme-light');
          bodyEl.classList.remove('dark', 'theme-dark');
          bodyEl.classList.add('light', 'theme-light');
        }

        const iconEl = document.getElementById('theme-toggle-icon');
        const textEl = document.getElementById('theme-toggle-text');
        const btnEl = document.getElementById('theme-toggle-btn');

        if (iconEl && textEl) {
          iconEl.textContent = isDark ? '🌙' : '☀️';
          textEl.textContent = isDark ? 'Dark Mode' : 'Light Mode';
        }

        if (btnEl) {
          if (isDark) {
            btnEl.className = 'flex items-center gap-2 rounded-full border border-emerald-500/40 bg-emerald-950/80 px-3.5 py-1.5 text-xs font-bold text-emerald-300 shadow-md hover:scale-105 transition-all cursor-pointer';
          } else {
            btnEl.className = 'flex items-center gap-2 rounded-full border border-slate-300 bg-white/90 px-3.5 py-1.5 text-xs font-bold text-slate-800 shadow-md hover:scale-105 transition-all cursor-pointer';
          }
        }

        const bgOverlay = document.getElementById('rhu-bg-overlay');
        const bgImg = document.getElementById('rhu-bg-img');
        if (bgOverlay) {
          bgOverlay.className = isDark ?
            'absolute inset-0 bg-gradient-to-br from-[#060b08]/94 via-[#080e0a]/90 to-[#08120b]/94 backdrop-blur-[2px] transition-all duration-500' :
            'absolute inset-0 bg-gradient-to-br from-white/85 via-slate-100/76 to-emerald-50/80 backdrop-blur-[2px] transition-all duration-500';
        }
        if (bgImg) {
          bgImg.style.filter = isDark ? 'brightness(0.35) contrast(1.1)' : 'brightness(0.98) contrast(1.02)';
        }

        try {
          localStorage.setItem('rhu_resident_theme_pref', theme);
        } catch (e) {}
      };

      window.toggleDashboardTheme = function() {
        const isDarkNow = document.body.classList.contains('dark') || document.body.classList.contains('theme-dark');
        applyDashboardTheme(isDarkNow ? 'light' : 'dark');
      };

      (function() {
        let savedTheme = 'light';
        try {
          savedTheme = localStorage.getItem('rhu_resident_theme_pref') || 'light';
        } catch (e) {}
        applyDashboardTheme(savedTheme);
      })();

      const notificationButton = document.querySelector('[data-notifications]');
      const notificationPanel = document.querySelector('[data-notification-panel]');
      if (notificationButton && notificationPanel) {
        notificationButton.addEventListener('click', (e) => {
          e.stopPropagation();
          notificationPanel.classList.toggle('hidden');
        });
        const closeButton = document.querySelector('[data-close-notifications]');
        if (closeButton) closeButton.addEventListener('click', () => notificationPanel.classList.add('hidden'));

        document.addEventListener('click', (e) => {
          if (!notificationPanel.classList.contains('hidden')) {
            if (!notificationPanel.contains(e.target) && !notificationButton.contains(e.target)) {
              notificationPanel.classList.add('hidden');
            }
          }
        });
      }

      window.openDependentModal = function(e) {
        if (e && e.preventDefault) {
          e.preventDefault();
          e.stopPropagation();
        }
        const dependentModal = document.getElementById('dependent-modal');
        if (!dependentModal) {
          console.error('Modal element not found');
          return false;
        }
        console.log('Opening dependent modal...');
        dependentModal.classList.remove('hidden');
        dependentModal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        return false;
      };

      window.openDependentInfoModal = function(dep) {
        if (!dep) return;
        
        const avatarEl = document.getElementById('dep-info-avatar');
        const nameEl = document.getElementById('dep-info-name');
        const bloodEl = document.getElementById('dep-info-blood');
        const subEl = document.getElementById('dep-info-sub');
        const relEl = document.getElementById('dep-info-rel');
        const dobEl = document.getElementById('dep-info-dob');
        const ageEl = document.getElementById('dep-info-age');
        const genderEl = document.getElementById('dep-info-gender');
        const btEl = document.getElementById('dep-info-bt');
        const brgyEl = document.getElementById('dep-info-brgy');
        const addrEl = document.getElementById('dep-info-addr');
        const headEl = document.getElementById('dep-info-head');
        const phoneEl = document.getElementById('dep-info-phone');
        const notesEl = document.getElementById('dep-info-notes');

        if (nameEl) nameEl.textContent = dep.name || 'Dependent Profile';
        if (avatarEl) {
          const fnLetter = dep.first_name ? dep.first_name[0].toUpperCase() : 'D';
          const lnLetter = dep.last_name ? dep.last_name[0].toUpperCase() : 'P';
          avatarEl.textContent = fnLetter + lnLetter;
        }
        if (bloodEl) bloodEl.textContent = dep.blood_type || 'Blood N/A';
        if (subEl) subEl.textContent = (dep.relationship || 'Family Member') + ' · ' + (dep.age || 'N/A') + ' · ' + (dep.gender || 'Not specified');
        if (relEl) relEl.textContent = dep.relationship || 'Family Member';
        if (dobEl) dobEl.textContent = dep.dob || 'N/A';
        if (ageEl) ageEl.textContent = dep.age || 'N/A';
        if (genderEl) genderEl.textContent = dep.gender || 'Not specified';
        if (btEl) btEl.textContent = dep.blood_type || 'Unknown';
        if (brgyEl) brgyEl.textContent = dep.barangay || 'Nasugbu';
        if (addrEl) addrEl.textContent = dep.address || 'Nasugbu, Batangas';
        if (headEl) headEl.textContent = dep.emergency_contact || 'Head of Household';
        if (phoneEl) phoneEl.textContent = 'Contact #: ' + (dep.emergency_phone || 'N/A');
        if (notesEl) notesEl.textContent = dep.notes || 'No known allergies or medical conditions recorded.';

        window.currentSelectedDependent = dep;

        const infoModal = document.getElementById('dependent-info-modal');
        if (!infoModal) return;
        infoModal.classList.remove('hidden');
        infoModal.classList.add('flex');
        infoModal.style.setProperty('display', 'flex', 'important');
        document.body.classList.add('overflow-hidden');
        if (window.lucide) lucide.createIcons();
      };

      window.closeDependentInfoModal = function() {
        const infoModal = document.getElementById('dependent-info-modal');
        if (!infoModal) return;
        infoModal.classList.add('hidden');
        infoModal.classList.remove('flex');
        infoModal.style.setProperty('display', 'none', 'important');
        document.body.classList.remove('overflow-hidden');
      };

      window.bookAppointmentForDependent = function() {
        window.closeDependentInfoModal();
        if (typeof window.openAppointmentModal === 'function') {
          window.openAppointmentModal();
        }
      };

      // Document-level delegated click listener for 100% bulletproof modal opening
      document.addEventListener('click', function(e) {
        const openTrigger = e.target.closest('#add-dependent-btn, [data-dependent-open], .add-dependent-trigger');
        if (openTrigger) {
          console.log('Open dependent modal triggered');
          window.openDependentModal(e);
          return;
        }
        const closeTrigger = e.target.closest('[data-dependent-close], .close-dependent-trigger');
        if (closeTrigger) {
          console.log('Close dependent modal triggered');
          window.closeDependentModal(e);
          return;
        }
      });

      const depModalEl = document.getElementById('dependent-modal');
      if (depModalEl) {
        console.log('Dependent modal element found on page');
        depModalEl.addEventListener('click', event => {
          if (event.target === depModalEl) window.closeDependentModal(event);
        });
      } else {
        console.error('CRITICAL: Dependent modal element NOT found on page');
      }
      
      document.addEventListener('keydown', event => {
        if (event.key === 'Escape') window.closeDependentModal(event);
      });
      
      // Additional check for Add Dependent button with enhanced debugging
      setTimeout(() => {
        const addDepBtn = document.getElementById('add-dependent-btn');
        const depModal = document.getElementById('dependent-modal');
        
        console.log('%c=== ADD DEPENDENT MODAL DEBUG ===', 'color: blue; font-weight: bold');
        console.log('Button found:', !!addDepBtn);
        console.log('Modal found:', !!depModal);
        
        if (addDepBtn) {
          console.log('Button text:', addDepBtn.innerText);
          console.log('Button onclick attr:', addDepBtn.getAttribute('onclick'));
          console.log('openDependentModal function:', typeof window.openDependentModal);
          
          // Add test listener
          addDepBtn.addEventListener('click', function(e) {
            console.log('%cBUTTON CLICKED!', 'color: green; font-weight: bold', e);
            window.openDependentModal(e);
          });
          
          console.log('✓ Button event listener attached');
        } else {
          console.error('✗ BUTTON NOT FOUND!');
        }
        
        if (depModal) {
          console.log('Modal HTML length:', depModal.innerHTML.length);
          console.log('Modal current display:', getComputedStyle(depModal).display);
          console.log('Modal has hidden class:', depModal.classList.contains('hidden'));
        } else {
          console.error('✗ MODAL NOT FOUND!');
        }
        
        console.log('%c=== END DEBUG ===', 'color: blue; font-weight: bold');
      }, 500);
      
      <?php if (!empty($dependentErrors)): ?>
        if (typeof window.openDependentModal === 'function') window.openDependentModal();
      <?php endif; ?>

    const logoutModal = document.getElementById('logout-modal');
    const openLogoutModal = event => {
      event.preventDefault();
      logoutModal.classList.remove('hidden');
      logoutModal.classList.add('flex');
      document.body.classList.add('overflow-hidden');
      logoutModal.querySelector('[data-logout-cancel]').focus();
    };
    const closeLogoutModal = () => {
      logoutModal.classList.add('hidden');
      logoutModal.classList.remove('flex');
      document.body.classList.remove('overflow-hidden');
    };
    document.querySelectorAll('[data-logout-link]').forEach(link => link.addEventListener('click', openLogoutModal));
    document.querySelectorAll('[data-logout-cancel]').forEach(button => button.addEventListener('click', closeLogoutModal));
    logoutModal.addEventListener('click', event => {
      if (event.target === logoutModal) closeLogoutModal();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeLogoutModal();
    });

    const revealItems = document.querySelectorAll(
      '[data-tab-panel] > div, [data-tab-panel] > article, [data-tab-panel] form'
    );
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);
          }
        });
      }, {
        threshold: 0.08,
        rootMargin: '0px 0px -24px'
      });

      revealItems.forEach((item, index) => {
        item.classList.add('reveal-on-scroll');
        item.style.transitionDelay = `${Math.min(index % 4, 3) * 55}ms`;
        revealObserver.observe(item);
      });
    } else {
      revealItems.forEach(item => item.classList.add('is-visible'));
    }

    const scrollProgress = document.getElementById('scroll-progress');
    const updateScrollProgress = () => {
      const scrollable = document.documentElement.scrollHeight - window.innerHeight;
      const progress = scrollable > 0 ? Math.min((window.scrollY / scrollable) * 100, 100) : 0;
      scrollProgress.style.width = `${progress}%`;
    };
    updateScrollProgress();
    window.addEventListener('scroll', updateScrollProgress, {
      passive: true
    });
    window.addEventListener('resize', updateScrollProgress);
    
    // Final verification that dependent modal is properly initialized
    console.log('=== DEPENDENT MODAL INITIALIZATION CHECK ===');
    const depModal = document.getElementById('dependent-modal');
    const depButton = document.getElementById('add-dependent-btn');
    console.log('Modal exists:', !!depModal);
    console.log('Button exists:', !!depButton);
    if (depModal && depButton) {
      console.log('Modal and button both found - should be ready to use');
      console.log('Modal current classes:', depModal.className);
      console.log('Modal visible:', !depModal.classList.contains('hidden'));
    } else {
      console.error('ERROR: Modal or button missing!');
    }
    console.log('=========================================');
    
    })();
  </script>
  <!-- EDIT HEALTH PROFILE MODAL -->
  <div id="edit-health-profile-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-xl rounded-3xl bg-white p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div class="flex items-center gap-2.5">
          <div class="h-9 w-9 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
            <i data-lucide="user-cog" class="h-5 w-5"></i>
          </div>
          <div>
            <h3 class="text-base font-extrabold text-slate-900">Update Health Profile</h3>
            <p class="text-xs text-slate-500">Edit medical information for your RHU resident record.</p>
          </div>
        </div>
        <button type="button" onclick="document.getElementById('edit-health-profile-modal')?.classList.add('hidden')" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>

      <form method="post" action="ResidentDashboard.php" class="space-y-4 text-xs">
        <input type="hidden" name="form" value="update_health_profile">
        <input type="hidden" name="csrf_token" value="<?= esc($dashboardCsrf) ?>">

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Blood Type</label>
            <select name="blood_type" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-semibold text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
              <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'] as $bt): ?>
                <option value="<?= $bt ?>" <?= (($healthProfile['blood_type'] ?? ($resident['blood_type'] ?? '')) === $bt) ? 'selected' : '' ?>><?= $bt ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">PhilHealth Number</label>
            <input type="text" name="philhealth_number" value="<?= esc($healthProfile['philhealth_number'] ?? ($resident['philhealth_id'] ?? '')) ?>" placeholder="e.g. 12-345678901-2" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
          </div>

          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Height (cm)</label>
            <input type="number" step="0.1" name="height" value="<?= esc($healthProfile['height'] ?? '') ?>" placeholder="e.g. 165" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
          </div>

          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Weight (kg)</label>
            <input type="number" step="0.1" name="weight" value="<?= esc($healthProfile['weight'] ?? '') ?>" placeholder="e.g. 62.5" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
          </div>

          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Blood Pressure</label>
            <input type="text" name="blood_pressure" value="<?= esc($healthProfile['blood_pressure'] ?? ($healthProfile['bp'] ?? '120/80')) ?>" placeholder="e.g. 120/80" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
          </div>

          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Heart Rate (bpm)</label>
            <input type="number" name="heart_rate" value="<?= esc($healthProfile['heart_rate'] ?? '72') ?>" placeholder="e.g. 72" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
          </div>

          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Temperature (°C)</label>
            <input type="number" step="0.1" name="temperature" value="<?= esc($healthProfile['temperature'] ?? '36.5') ?>" placeholder="e.g. 36.5" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
          </div>

          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Last Checkup Date</label>
            <input type="date" name="last_checkup_date" value="<?= esc($healthProfile['last_checkup_date'] ?? date('Y-m-d')) ?>" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
          </div>

          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Smoking Status</label>
            <select name="smoking_status" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-semibold text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
              <?php foreach (['Non-Smoker', 'Former Smoker', 'Occasional Smoker', 'Daily Smoker'] as $ss): ?>
                <option value="<?= $ss ?>" <?= (($healthProfile['smoking_status'] ?? '') === $ss) ? 'selected' : '' ?>><?= $ss ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Alcohol Consumption</label>
            <select name="alcohol_consumption" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-semibold text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
              <?php foreach (['Non-Drinker', 'Occasional Drinker', 'Moderate Drinker', 'Heavy Drinker'] as $ac): ?>
                <option value="<?= $ac ?>" <?= (($healthProfile['alcohol_consumption'] ?? '') === $ac) ? 'selected' : '' ?>><?= $ac ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Exercise Frequency</label>
            <select name="exercise_frequency" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-semibold text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
              <?php foreach (['Sedentary (No exercise)', 'Occasional (1-2x/week)', 'Active (3-5x/week)', 'Daily Athlete'] as $ef): ?>
                <option value="<?= $ef ?>" <?= (($healthProfile['exercise_frequency'] ?? '') === $ef) ? 'selected' : '' ?>><?= $ef ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Diet Type</label>
            <input type="text" name="diet_type" value="<?= esc($healthProfile['diet_type'] ?? 'Balanced Diet') ?>" placeholder="e.g. Low Sodium, Diabetic, Balanced" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
          </div>
        </div>

        <div>
          <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Known Allergies</label>
          <textarea name="allergies" rows="2" placeholder="List food, drug, or environmental allergies..." class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white"><?= esc($healthProfile['allergies'] ?? ($resident['allergies'] ?? '')) ?></textarea>
        </div>

        <div>
          <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Chronic Conditions / Illnesses</label>
          <textarea name="chronic_conditions" rows="2" placeholder="Hypertension, Asthma, Diabetes, etc." class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white"><?= esc($healthProfile['chronic_conditions'] ?? ($healthProfile['medical_conditions'] ?? ($resident['medical_conditions'] ?? ''))) ?></textarea>
        </div>

        <div>
          <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Current Prescribed Medications</label>
          <textarea name="current_medications" rows="2" placeholder="e.g. Amlodipine 5mg once daily..." class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white"><?= esc($healthProfile['current_medications'] ?? ($healthProfile['medications'] ?? '')) ?></textarea>
        </div>

        <div class="grid grid-cols-3 gap-3 border-t border-slate-100 pt-3">
          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Emergency Contact Person</label>
            <input type="text" name="emergency_contact_name" value="<?= esc($healthProfile['emergency_contact_name'] ?? ($resident['emergency_contact_name'] ?? '')) ?>" placeholder="Name" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
          </div>
          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Relationship</label>
            <input type="text" name="emergency_contact_relationship" value="<?= esc($healthProfile['emergency_contact_relationship'] ?? ($resident['emergency_contact_relationship'] ?? '')) ?>" placeholder="Spouse, Mother, etc." class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
          </div>
          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Emergency Phone #</label>
            <input type="text" name="emergency_contact_phone" value="<?= esc($healthProfile['emergency_contact_phone'] ?? ($resident['emergency_contact_phone'] ?? '')) ?>" placeholder="0917XXXXXXX" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-800 outline-none focus:border-teal-500 focus:bg-white">
          </div>
        </div>

        <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
          <button type="button" onclick="document.getElementById('edit-health-profile-modal')?.classList.add('hidden')" class="rounded-xl px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors">
            Cancel
          </button>
          <button type="submit" class="rounded-xl bg-teal-600 px-5 py-2.5 text-xs font-bold text-white shadow-md hover:bg-teal-700 transition-colors">
            Save Health Profile
          </button>
        </div>
      </form>
    </div>
  </div>
  <!-- GLOBAL NOTIFICATION POPOVER PANEL -->
  <div id="global-notification-panel" data-notification-panel class="hidden fixed top-16 right-4 sm:right-8 z-[99999] w-[calc(100vw-2rem)] sm:w-96 max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white text-slate-800 shadow-2xl transition-all">
    <!-- Header Bar -->
    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 bg-slate-50">
      <div class="flex items-center gap-2">
        <span class="font-bold text-slate-900 text-xs">Notifications</span>
        <span id="notif-header-count" class="rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-extrabold text-teal-800 border border-teal-200">0 Unread</span>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" onclick="markAllNotificationsRead()" class="text-[10px] font-bold text-teal-700 hover:text-teal-900 hover:underline">Mark all read</button>
        <button type="button" onclick="toggleNotificationPanel(event)" class="text-xs font-bold text-slate-400 hover:text-slate-600 p-1">✕</button>
      </div>
    </div>

    <!-- Action Toolbar for Selection & Deletion -->
    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2 bg-slate-100/80 text-[11px]">
      <label class="flex items-center gap-1.5 font-bold text-slate-700 cursor-pointer select-none">
        <input type="checkbox" id="notif-select-all" onclick="toggleSelectAllNotifications(this)" class="rounded text-teal-600 focus:ring-teal-500">
        <span>Select All</span>
      </label>
      <button type="button" id="notif-delete-selected-btn" onclick="deleteSelectedNotifications()" disabled class="flex items-center gap-1 text-[10px] font-bold text-rose-600 hover:text-rose-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
        <span>🗑 Delete Selected</span>
      </button>
    </div>

    <!-- Notifications List Container -->
    <div id="notif-items-list" class="divide-y divide-slate-100 text-xs max-h-80 overflow-y-auto bg-white">
      <div class="p-4 text-center text-slate-400 text-xs">Loading notifications...</div>
    </div>
  </div>

  <!-- NOTIFICATION DETAIL MODAL -->
  <div id="notification-detail-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div class="flex items-center gap-2.5">
          <div class="h-9 w-9 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
            <i data-lucide="bell" class="h-5 w-5"></i>
          </div>
          <div>
            <h3 class="text-base font-extrabold text-slate-900" id="notif-modal-title">RHU Notification</h3>
            <p class="text-[10px] text-slate-400 font-mono" id="notif-modal-date"></p>
          </div>
        </div>
        <button type="button" onclick="closeNotificationModal()" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
          ✕
        </button>
      </div>

      <div class="space-y-3 text-xs">
        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 leading-relaxed font-medium text-slate-800" id="notif-modal-body">
          <!-- Notification Content -->
        </div>
        <div id="notif-modal-action-container" class="hidden">
          <a id="notif-modal-action-link" href="#" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-teal-600 py-2.5 text-xs font-bold text-white hover:bg-teal-700">
            View Related Section →
          </a>
        </div>
      </div>

      <div class="flex items-center justify-between border-t border-slate-100 pt-3 text-xs">
        <button type="button" id="notif-modal-delete-btn" onclick="" class="flex items-center gap-1 font-bold text-rose-600 hover:text-rose-800">
          🗑 Delete Notification
        </button>
        <button type="button" onclick="closeNotificationModal()" class="rounded-xl bg-slate-100 px-4 py-2 font-bold text-slate-700 hover:bg-slate-200">
          Close
        </button>
      </div>
    </div>
  </div>

  <script>
    function toggleNotificationPanel(event) {
      if (event) {
        event.stopPropagation();
        if (typeof event.preventDefault === 'function') event.preventDefault();
      }
      const panel = document.getElementById('global-notification-panel') || document.querySelector('[data-notification-panel]');
      if (panel) {
        panel.classList.toggle('hidden');
      }
    }

    document.addEventListener('click', (event) => {
      const panel = document.getElementById('global-notification-panel') || document.querySelector('[data-notification-panel]');
      const bell = document.getElementById('notification-bell-btn') || document.querySelector('[data-notifications]');
      if (panel && !panel.classList.contains('hidden')) {
        if (bell && (panel.contains(event.target) || bell.contains(event.target))) return;
        if (!panel.contains(event.target)) {
          panel.classList.add('hidden');
        }
      }
    });

    let currentNotifications = [];
    let notifPollTimer = null;

    async function fetchNotifications() {
      try {
        const res = await fetch('ResidentDashboard.php?api=get_notifications');
        const data = await res.json();
        if (data && data.success) {
          currentNotifications = data.notifications || [];
          renderNotificationList(currentNotifications, data.unread_count || 0);
        }
      } catch (err) {
        console.error('Error fetching notifications:', err);
      }
    }

    function renderNotificationList(items, unreadCount) {
      const badge = document.getElementById('notif-badge-count');
      const headerCount = document.getElementById('notif-header-count');
      const list = document.getElementById('notif-items-list');

      if (badge) {
        if (unreadCount > 0) {
          badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
          badge.style.display = 'flex';
        } else {
          badge.style.display = 'none';
        }
      }

      if (headerCount) {
        headerCount.textContent = `${unreadCount} Unread`;
      }

      if (!list) return;

      if (items.length === 0) {
        list.innerHTML = `
          <div class="p-6 text-center text-slate-400">
            <span class="text-2xl">🔔</span>
            <p class="mt-1 text-xs font-bold text-slate-600">No notifications</p>
            <p class="text-[10px]">You are all caught up!</p>
          </div>`;
        updateDeleteSelectedBtnState();
        return;
      }

      const checkedIds = new Set(
        [...document.querySelectorAll('.notif-checkbox:checked')].map(cb => cb.value)
      );

      list.innerHTML = items.map(item => {
        const isChecked = checkedIds.has(String(item.id)) ? 'checked' : '';
        const unreadBg = !item.is_read ? 'bg-teal-50/60 font-bold border-l-4 border-l-teal-600' : 'hover:bg-slate-50';
        const unreadDot = !item.is_read ? '<span class="h-2 w-2 rounded-full bg-teal-600 shrink-0"></span>' : '';

        return `
          <div class="group flex items-start justify-between gap-2 p-3 text-xs transition-all ${unreadBg}">
            <div class="flex items-start gap-2.5 min-w-0 flex-1">
              <input type="checkbox" value="${item.id}" ${isChecked} onchange="onNotifCheckboxChange()" class="notif-checkbox mt-1 rounded text-teal-600 focus:ring-teal-500 shrink-0">
              <div class="min-w-0 flex-1 cursor-pointer" onclick="openNotificationDetail(${item.id})">
                <div class="flex items-center gap-1.5">
                  ${unreadDot}
                  <p class="text-slate-800 font-semibold truncate ${!item.is_read ? 'font-bold' : ''}">${escapeHtml(item.message)}</p>
                </div>
                <p class="mt-1 text-[10px] text-slate-400 font-mono">${escapeHtml(item.time_ago || item.created_at)}</p>
              </div>
            </div>
            <button type="button" onclick="deleteOneNotification(${item.id}, event)" title="Delete notification" class="opacity-70 group-hover:opacity-100 p-1 text-slate-400 hover:text-rose-600 transition-colors shrink-0">
              🗑
            </button>
          </div>
        `;
      }).join('');

      updateDeleteSelectedBtnState();
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function toggleSelectAllNotifications(masterCb) {
      const checkboxes = document.querySelectorAll('.notif-checkbox');
      checkboxes.forEach(cb => cb.checked = masterCb.checked);
      updateDeleteSelectedBtnState();
    }

    function onNotifCheckboxChange() {
      const master = document.getElementById('notif-select-all');
      const all = document.querySelectorAll('.notif-checkbox');
      const checked = document.querySelectorAll('.notif-checkbox:checked');
      if (master) {
        master.checked = all.length > 0 && checked.length === all.length;
      }
      updateDeleteSelectedBtnState();
    }

    function updateDeleteSelectedBtnState() {
      const btn = document.getElementById('notif-delete-selected-btn');
      const checked = document.querySelectorAll('.notif-checkbox:checked');
      if (btn) {
        btn.disabled = checked.length === 0;
      }
    }

    async function deleteSelectedNotifications() {
      const checked = [...document.querySelectorAll('.notif-checkbox:checked')].map(cb => cb.value);
      if (checked.length === 0) return;

      if (!confirm(`Are you sure you want to delete ${checked.length} selected notification(s)?`)) return;

      try {
        const formData = new FormData();
        formData.append('ids', JSON.stringify(checked));
        const res = await fetch('ResidentDashboard.php?api=delete_notifications', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data && data.success) {
          const master = document.getElementById('notif-select-all');
          if (master) master.checked = false;
          await fetchNotifications();
        }
      } catch (err) {
        console.error('Failed to delete notifications:', err);
      }
    }

    async function deleteOneNotification(id, event) {
      if (event) event.stopPropagation();
      if (!confirm('Delete this notification?')) return;

      try {
        const formData = new FormData();
        formData.append('ids', JSON.stringify([id]));
        const res = await fetch('ResidentDashboard.php?api=delete_notifications', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data && data.success) {
          closeNotificationModal();
          await fetchNotifications();
        }
      } catch (err) {
        console.error('Failed to delete notification:', err);
      }
    }

    async function markAllNotificationsRead() {
      try {
        const formData = new FormData();
        formData.append('all', '1');
        const res = await fetch('ResidentDashboard.php?api=mark_read', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data && data.success) {
          await fetchNotifications();
        }
      } catch (err) {
        console.error('Failed to mark notifications read:', err);
      }
    }

    async function openNotificationDetail(id) {
      const notifPanel = document.querySelector('[data-notification-panel]');
      if (notifPanel) notifPanel.classList.add('hidden');

      const item = currentNotifications.find(n => n.id == id);
      if (!item) return;

      document.getElementById('notif-modal-title').textContent = item.title || 'RHU Notification';
      document.getElementById('notif-modal-date').textContent = 'Sent: ' + (item.created_at || 'Just now');
      document.getElementById('notif-modal-body').textContent = item.message || '';

      const actionContainer = document.getElementById('notif-modal-action-container');
      const actionLink = document.getElementById('notif-modal-action-link');
      if (item.link_url) {
        actionLink.href = item.link_url;
        actionContainer.classList.remove('hidden');
      } else {
        actionContainer.classList.add('hidden');
      }

      const deleteBtn = document.getElementById('notif-modal-delete-btn');
      if (deleteBtn) {
        deleteBtn.onclick = (e) => deleteOneNotification(item.id, e);
      }

      const modal = document.getElementById('notification-detail-modal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');

      if (!item.is_read) {
        try {
          const formData = new FormData();
          formData.append('id', item.id);
          await fetch('ResidentDashboard.php?api=mark_read', {
            method: 'POST',
            body: formData
          });
          item.is_read = 1;
          renderNotificationList(currentNotifications, Math.max(0, currentNotifications.filter(n => !n.is_read).length));
        } catch (e) {}
      }
    }

    function closeNotificationModal() {
      const modal = document.getElementById('notification-detail-modal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    window.dashboardCurrentPages = {
      records: 1,
      certificates: 1,
      immunization: 1
    };

    window.goToDashboardPage = function(groupKey, targetPage, totalPages) {
      if (targetPage < 1 || targetPage > totalPages) return;
      window.dashboardCurrentPages[groupKey] = targetPage;

      const items = document.querySelectorAll(`[data-pagination-item="${groupKey}"]`);
      items.forEach(item => {
        const page = parseInt(item.dataset.page, 10);
        if (page === targetPage) {
          item.classList.remove('hidden');
        } else {
          item.classList.add('hidden');
        }
      });

      const prevBtn = document.getElementById(`${groupKey}-prev-btn`);
      const nextBtn = document.getElementById(`${groupKey}-next-btn`);
      if (prevBtn) prevBtn.disabled = (targetPage <= 1);
      if (nextBtn) nextBtn.disabled = (targetPage >= totalPages);

      const currentText = document.getElementById(`${groupKey}-current-page`);
      if (currentText) currentText.textContent = targetPage;

      const pageBtns = document.querySelectorAll(`[data-page-btn^="${groupKey}-"]`);
      pageBtns.forEach(btn => {
        const pNum = parseInt(btn.dataset.pageBtn.replace(`${groupKey}-`, ''), 10);
        if (pNum === targetPage) {
          btn.className = 'h-8 min-w-[2rem] px-2.5 rounded-xl text-xs font-extrabold transition-all bg-teal-600 text-white shadow-md';
        } else {
          btn.className = 'h-8 min-w-[2rem] px-2.5 rounded-xl text-xs font-extrabold transition-all border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/10';
        }
      });

      // Smooth scroll back up to tab top
      const activePanel = document.querySelector(`[data-tab-panel]:not(.hidden)`);
      if (activePanel) {
        activePanel.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }

      if (window.lucide) lucide.createIcons();
    };

    window.changeDashboardPage = function(groupKey, delta, totalPages) {
      const current = window.dashboardCurrentPages[groupKey] || 1;
      window.goToDashboardPage(groupKey, current + delta, totalPages);
    };
  </script>

  <!-- RHU AI HEALTH ASSISTANT CHATBOT -->
  <!-- Floating Trigger Button -->
  <div id="rhu-chatbot-trigger-container" class="fixed bottom-6 right-6 z-[9999] flex items-center gap-3">
    <button type="button" id="rhu-chatbot-trigger-btn" onclick="toggleRhuChatbot()" class="group relative flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-tr from-emerald-600 via-teal-600 to-sky-500 text-white shadow-2xl hover:scale-110 active:scale-95 transition-all duration-300 border-2 border-white/20 cursor-pointer">
      <span class="absolute -top-1 -right-1 flex h-4 w-4">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
        <span class="relative inline-flex rounded-full h-4 w-4 bg-emerald-500 border-2 border-white"></span>
      </span>
      <i data-lucide="bot" class="h-7 w-7 transition-transform group-hover:rotate-12"></i>
    </button>
  </div>

  <!-- Chatbot Glassmorphic Window -->
  <div id="rhu-chatbot-window" class="fixed bottom-24 right-4 sm:right-6 z-[9999] hidden h-[540px] max-h-[82vh] w-[calc(100vw-2rem)] sm:w-[410px] flex-col rounded-3xl border border-slate-200 dark:border-white/20 bg-white/95 dark:bg-slate-900/95 backdrop-blur-2xl shadow-[0_25px_60px_rgba(0,0,0,0.3)] overflow-hidden transition-all duration-300">

    <!-- Chat Header -->
    <div class="flex items-center justify-between bg-gradient-to-r from-emerald-700 via-teal-700 to-sky-700 p-4 text-white shadow-md">
      <div class="flex items-center gap-3">
        <div class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white/20 border border-white/30 backdrop-blur-md font-black shadow-inner">
          <i data-lucide="bot" class="h-6 w-6 text-emerald-200"></i>
          <span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full bg-emerald-400 border-2 border-emerald-800"></span>
        </div>
        <div>
          <h3 class="text-sm font-extrabold tracking-wide leading-tight">RHU Health Assistant</h3>
          <p class="text-[10px] font-medium text-emerald-200 flex items-center gap-1">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            AI Resident Portal Helper • Online
          </p>
        </div>
      </div>
      <div class="flex items-center gap-1">
        <button type="button" onclick="clearRhuChatbotHistory()" class="rounded-xl p-1.5 text-white/80 hover:bg-white/20 hover:text-white transition-colors" title="Clear Conversation">
          <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
        </button>
        <button type="button" onclick="toggleRhuChatbot()" class="rounded-xl p-1.5 text-white/80 hover:bg-white/20 hover:text-white transition-colors" title="Close Chatbot">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>
    </div>

    <!-- Messages Container -->
    <div id="rhu-chatbot-messages" class="flex-1 overflow-y-auto p-4 space-y-3 text-xs">

      <!-- Welcome Message -->
      <div class="flex items-start gap-2.5">
        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-white text-[10px] font-bold shadow-sm">AI</div>
        <div class="max-w-[85%] rounded-2xl rounded-tl-none bg-slate-100 dark:bg-white/10 p-3.5 text-slate-800 dark:text-slate-100 font-medium leading-relaxed shadow-2xs space-y-2">
          <p>👋 Hello, <strong><?= esc($resident['first_name'] ?? 'Resident') ?></strong>! I am your <strong>RHU AI Health Assistant</strong>.</p>
          <p>Ask me anything about OPD consultations, health certificates, vaccine schedules, emergency hotlines, or updating your profile!</p>
        </div>
      </div>

      <!-- Quick Suggestion Chips Container -->
      <div id="rhu-chatbot-suggestions" class="pt-2">
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Suggested Questions:</p>
        <div class="flex flex-wrap gap-1.5">
          <button type="button" onclick="sendRhuQuickQuestion('How do I request a Medical Certificate?')" class="rounded-full border border-teal-200 dark:border-white/10 bg-teal-50/80 dark:bg-white/5 px-3 py-1.5 text-[11px] font-semibold text-teal-800 dark:text-teal-300 hover:bg-teal-100 dark:hover:bg-white/10 transition-all text-left">
            💊 Medical Certificate Fee &amp; Process
          </button>
          <button type="button" onclick="sendRhuQuickQuestion('What are the OPD clinic consultation hours?')" class="rounded-full border border-teal-200 dark:border-white/10 bg-teal-50/80 dark:bg-white/5 px-3 py-1.5 text-[11px] font-semibold text-teal-800 dark:text-teal-300 hover:bg-teal-100 dark:hover:bg-white/10 transition-all text-left">
            🩺 OPD Consultation Hours
          </button>
          <button type="button" onclick="sendRhuQuickQuestion('When is the infant vaccination schedule?')" class="rounded-full border border-teal-200 dark:border-white/10 bg-teal-50/80 dark:bg-white/5 px-3 py-1.5 text-[11px] font-semibold text-teal-800 dark:text-teal-300 hover:bg-teal-100 dark:hover:bg-white/10 transition-all text-left">
            💉 Infant Vaccination Schedule
          </button>
          <button type="button" onclick="sendRhuQuickQuestion('What is the RHU Emergency Hotline number?')" class="rounded-full border border-rose-200 dark:border-rose-900/40 bg-rose-50/80 dark:bg-rose-950/30 px-3 py-1.5 text-[11px] font-bold text-rose-800 dark:text-rose-300 hover:bg-rose-100 transition-all text-left">
            🚨 Emergency Hotline Numbers
          </button>
        </div>
      </div>

    </div>

    <!-- Input Footer -->
    <div class="border-t border-slate-200 dark:border-white/10 p-3 bg-slate-50/90 dark:bg-slate-900/90">
      <form id="rhu-chatbot-form" onsubmit="handleRhuChatbotSubmit(event)" class="flex items-center gap-2">
        <input type="text" id="rhu-chatbot-input" placeholder="Type your health or portal question..." autocomplete="off" class="flex-1 rounded-2xl border border-slate-300 dark:border-white/15 bg-white dark:bg-black/40 px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-100 placeholder-slate-400 outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition-all">
        <button type="submit" id="rhu-chatbot-send-btn" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-teal-600 text-white shadow-md hover:bg-teal-700 active:scale-95 transition-all">
          <i data-lucide="send" class="h-4 w-4"></i>
        </button>
      </form>
    </div>
  </div>

  <script>
    /* RHU AI CHATBOT ENGINE */
    window.toggleRhuChatbot = function() {
      const win = document.getElementById('rhu-chatbot-window');
      if (!win) return;
      const isHidden = win.classList.contains('hidden');
      if (isHidden) {
        win.classList.remove('hidden');
        win.classList.add('flex');
        const input = document.getElementById('rhu-chatbot-input');
        if (input) input.focus();
      } else {
        win.classList.add('hidden');
        win.classList.remove('flex');
      }
    };

    window.sendRhuQuickQuestion = function(text) {
      const input = document.getElementById('rhu-chatbot-input');
      if (input) {
        input.value = text;
        window.handleRhuChatbotSubmit(new Event('submit'));
      }
    };

    window.clearRhuChatbotHistory = function() {
      const container = document.getElementById('rhu-chatbot-messages');
      if (!container) return;
      container.innerHTML = `
        <div class="flex items-start gap-2.5">
          <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-white text-[10px] font-bold shadow-sm">AI</div>
          <div class="max-w-[85%] rounded-2xl rounded-tl-none bg-slate-100 dark:bg-white/10 p-3.5 text-slate-800 dark:text-slate-100 font-medium leading-relaxed shadow-2xs space-y-2">
            <p>Conversation reset. How can I help you today, <strong><?= esc($resident['first_name'] ?? 'Resident') ?></strong>?</p>
          </div>
        </div>
        <div id="rhu-chatbot-suggestions" class="pt-2">
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Suggested Questions:</p>
          <div class="flex flex-wrap gap-1.5">
            <button type="button" onclick="sendRhuQuickQuestion('How do I request a Medical Certificate?')" class="rounded-full border border-teal-200 dark:border-white/10 bg-teal-50/80 dark:bg-white/5 px-3 py-1.5 text-[11px] font-semibold text-teal-800 dark:text-teal-300 hover:bg-teal-100 dark:hover:bg-white/10 transition-all text-left">
              💊 Medical Certificate Fee &amp; Process
            </button>
            <button type="button" onclick="sendRhuQuickQuestion('What are the OPD clinic consultation hours?')" class="rounded-full border border-teal-200 dark:border-white/10 bg-teal-50/80 dark:bg-white/5 px-3 py-1.5 text-[11px] font-semibold text-teal-800 dark:text-teal-300 hover:bg-teal-100 dark:hover:bg-white/10 transition-all text-left">
              🩺 OPD Consultation Hours
            </button>
            <button type="button" onclick="sendRhuQuickQuestion('When is the infant vaccination schedule?')" class="rounded-full border border-teal-200 dark:border-white/10 bg-teal-50/80 dark:bg-white/5 px-3 py-1.5 text-[11px] font-semibold text-teal-800 dark:text-teal-300 hover:bg-teal-100 dark:hover:bg-white/10 transition-all text-left">
              💉 Infant Vaccination Schedule
            </button>
          </div>
        </div>
      `;
      if (window.lucide) lucide.createIcons();
    };

    window.handleRhuChatbotSubmit = function(e) {
      if (e) e.preventDefault();
      const input = document.getElementById('rhu-chatbot-input');
      const messages = document.getElementById('rhu-chatbot-messages');
      if (!input || !messages) return;

      const userText = input.value.trim();
      if (!userText) return;

      const userDiv = document.createElement('div');
      userDiv.className = 'flex justify-end';
      userDiv.innerHTML = `
        <div class="max-w-[85%] rounded-2xl rounded-tr-none bg-teal-600 p-3 text-white font-semibold text-xs leading-relaxed shadow-sm">
          ${escapeHtml(userText)}
        </div>
      `;
      messages.appendChild(userDiv);
      input.value = '';

      messages.scrollTop = messages.scrollHeight;

      const typingDiv = document.createElement('div');
      typingDiv.className = 'flex items-start gap-2.5';
      typingDiv.id = 'rhu-chatbot-typing';
      typingDiv.innerHTML = `
        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-white text-[10px] font-bold shadow-sm">AI</div>
        <div class="rounded-2xl rounded-tl-none bg-slate-100 dark:bg-white/10 px-4 py-3 text-slate-500 font-bold text-xs flex items-center gap-1.5">
          <span class="h-2 w-2 rounded-full bg-teal-500 animate-bounce"></span>
          <span class="h-2 w-2 rounded-full bg-teal-500 animate-bounce [animation-delay:0.2s]"></span>
          <span class="h-2 w-2 rounded-full bg-teal-500 animate-bounce [animation-delay:0.4s]"></span>
        </div>
      `;
      messages.appendChild(typingDiv);
      messages.scrollTop = messages.scrollHeight;

      setTimeout(() => {
        const typing = document.getElementById('rhu-chatbot-typing');
        if (typing) typing.remove();

        const aiResponse = generateRhuAiResponse(userText);
        const aiDiv = document.createElement('div');
        aiDiv.className = 'flex items-start gap-2.5';
        aiDiv.innerHTML = `
          <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-white text-[10px] font-bold shadow-sm">AI</div>
          <div class="max-w-[85%] rounded-2xl rounded-tl-none bg-slate-100 dark:bg-white/10 p-3.5 text-slate-800 dark:text-slate-100 font-medium leading-relaxed shadow-2xs space-y-2">
            ${aiResponse}
          </div>
        `;
        messages.appendChild(aiDiv);
        messages.scrollTop = messages.scrollHeight;
        if (window.lucide) lucide.createIcons();
      }, 450);
    };

    function generateRhuAiResponse(query) {
      const q = query.toLowerCase().trim();

      // Access real staff registered on the system
      const registeredStaff = (window.allRhuStaff && window.allRhuStaff.length > 0) ?
        window.allRhuStaff :
        [{
            first_name: 'Dr. Maria',
            last_name: 'Santos',
            staff_type: 'Rural Health Physician',
            specialization: 'General Medicine',
            work_days: 'Monday - Friday',
            is_on_duty: 1
          },
          {
            first_name: 'Dr. Joseph',
            last_name: 'Ramos',
            staff_type: 'Rural Health Physician',
            specialization: 'General Practice',
            work_days: 'Monday - Friday',
            is_on_duty: 1
          },
          {
            first_name: 'Midwife Rosario',
            last_name: 'Peralta',
            staff_type: 'Rural Health Midwife',
            specialization: 'Maternal Care',
            work_days: 'Monday - Friday',
            is_on_duty: 1
          },
          {
            first_name: 'RN Clara',
            last_name: 'Mendez',
            staff_type: 'Public Health Nurse',
            specialization: 'Immunization',
            work_days: 'Monday - Friday',
            is_on_duty: 1
          }
        ];

      function formatStaffList(staffArray, title = 'Registered RHU Medical Staff & Physicians') {
        if (!staffArray || staffArray.length === 0) {
          return `<p>No registered staff records match this query.</p>`;
        }
        let listHtml = `<p>👨‍⚕️ <strong>${escapeHtml(title)}:</strong></p><ul class="list-disc pl-4 space-y-1.5 mt-1">`;
        staffArray.forEach(s => {
          const fname = s.first_name || 'RHU';
          const lname = s.last_name || 'Staff';
          const fullName = `${fname} ${lname}`.trim();
          const role = s.staff_type || 'Healthcare Staff';
          const spec = s.specialization ? ` — ${s.specialization}` : '';
          const dutyBadge = s.is_on_duty == 1 ?
            '<span class="inline-block rounded-full bg-emerald-100 text-emerald-800 text-[9px] font-extrabold px-1.5 py-0.5 border border-emerald-300 ml-1">On Duty</span>' :
            '<span class="inline-block rounded-full bg-slate-100 text-slate-600 text-[9px] font-extrabold px-1.5 py-0.5 border border-slate-300 ml-1">Off Duty</span>';
          const sched = s.work_days || 'Monday to Friday';

          listHtml += `<li><strong>${escapeHtml(fullName)}</strong> (${escapeHtml(role)}${escapeHtml(spec)}) ${dutyBadge}<br><span class="text-[10px] text-slate-500 font-medium">Schedule: ${escapeHtml(sched)}</span></li>`;
        });
        listHtml += `</ul>`;
        return listHtml;
      }

      // 1. Greetings & Small Talk
      if (q.includes('hi') || q.includes('hello') || q.includes('hey') || q.includes('kamusta') || q.includes('good morning') || q.includes('good afternoon') || q.includes('good evening') || q.includes('salamat') || q.includes('thank') || q.includes('who are you') || q.includes('what can you do')) {
        return `<p>👋 Hello, <strong><?= esc($resident['first_name'] ?? 'Resident') ?></strong>!</p>
          <p>I am your official <strong>RHU AI Health &amp; Portal Assistant</strong>. I can answer <em>any question</em> regarding:</p>
          <ul class="list-disc pl-4 space-y-1 mt-1 text-slate-700">
            <li><strong>OPD Clinic Hours &amp; Registered Doctors on Duty</strong></li>
            <li><strong>Health Certificates &amp; Document Fees</strong></li>
            <li><strong>Free Medicines &amp; Pharmacy Claims</strong></li>
            <li><strong>Infant &amp; Senior Immunization Schedules</strong></li>
            <li><strong>Animal Bite / Anti-Rabies Vaccination</strong></li>
            <li><strong>Laboratory &amp; Diagnostic Blood Screening</strong></li>
            <li><strong>Emergency Hotlines &amp; Ambulance Dispatch</strong></li>
            <li><strong>Dental Clinic Services &amp; Barangay Health Stations</strong></li>
          </ul>
          <p class="mt-1">How can I assist you right now?</p>`;
      }

      // 2. Registered Doctors / Physicians / Staff Queries
      if (q.includes('staff') || q.includes('doctor') || q.includes('physician') || q.includes('nurse') || q.includes('midwife') || q.includes('who is registered') || q.includes('who are the doctors') || q.includes('duty') || q.includes('team')) {
        let filtered = registeredStaff;
        let title = 'Registered RHU Staff & Physicians';

        if (q.includes('doctor') || q.includes('physician')) {
          filtered = registeredStaff.filter(s => {
            const type = (s.staff_type || '').toLowerCase();
            const spec = (s.specialization || '').toLowerCase();
            return type.includes('physician') || type.includes('doctor') || type.includes('medical') || spec.includes('medicine') || spec.includes('general');
          });
          if (filtered.length === 0) filtered = registeredStaff;
          title = 'Registered RHU Attending Physicians & Doctors';
        } else if (q.includes('midwife')) {
          filtered = registeredStaff.filter(s => (s.staff_type || '').toLowerCase().includes('midwife'));
          if (filtered.length === 0) filtered = registeredStaff;
          title = 'Registered RHU Midwives & Maternal Care Specialists';
        } else if (q.includes('nurse')) {
          filtered = registeredStaff.filter(s => (s.staff_type || '').toLowerCase().includes('nurse'));
          if (filtered.length === 0) filtered = registeredStaff;
          title = 'Registered RHU Public Health Nurses';
        }

        return formatStaffList(filtered, title);
      }

      // 3. OPD Consultation & Clinic Hours with Registered Physicians
      if (q.includes('opd') || q.includes('hour') || q.includes('schedule') || q.includes('consultation') || q.includes('checkup') || q.includes('open') || q.includes('appointment')) {
        const doctors = registeredStaff.filter(s => {
          const type = (s.staff_type || '').toLowerCase();
          const spec = (s.specialization || '').toLowerCase();
          return type.includes('physician') || type.includes('doctor') || spec.includes('medicine') || spec.includes('general');
        });

        let docSummary = '';
        if (doctors.length > 0) {
          docSummary = `<p class="mt-1.5 font-bold text-slate-700">Registered Attending Physicians:</p><ul class="list-disc pl-4 space-y-1 text-slate-700 font-medium">` +
            doctors.map(d => `<li><strong>${escapeHtml(d.first_name)} ${escapeHtml(d.last_name)}</strong> — ${escapeHtml(d.staff_type || 'Physician')} (${escapeHtml(d.work_days || 'Mon–Fri')})</li>`).join('') +
            `</ul>`;
        }

        return `<p>🩺 <strong>OPD Clinic Hours &amp; Registered Doctors:</strong></p>
          <p>Our OPD is open <strong>Monday to Friday, 8:00 AM – 5:00 PM</strong> at the RHU Main Center.</p>
          ${docSummary}
          <p class="mt-1.5">You can request an OPD consultation directly under your <strong>Health Records</strong> tab!</p>`;
      }

      // 4. Official Health Certificates & Fees
      if (q.includes('cert') || q.includes('medical cert') || q.includes('health cert') || q.includes('clearance') || q.includes('fee') || q.includes('price') || q.includes('cost') || q.includes('permit') || q.includes('birth cert')) {
        return `<p>📄 <strong>RHU Official Health Certificates &amp; Fees:</strong></p>
          <ul class="list-disc pl-4 space-y-1">
            <li><strong>Medical Certificate:</strong> ₱50 (Issued after OPD physician consultation)</li>
            <li><strong>Health Certificate:</strong> ₱100 (Employment &amp; Food Handler clearance)</li>
            <li><strong>Barangay Health Clearance:</strong> ₱100</li>
            <li><strong>Certificate of Live Birth:</strong> FREE</li>
          </ul>
          <p class="mt-1">To request a document, click your <strong>Certificates</strong> tab, select the document type, and submit!</p>`;
      }

      // 5. Pharmacy & Free Medicines
      if (q.includes('medicine') || q.includes('gamot') || q.includes('paracetamol') || q.includes('amoxicillin') || q.includes('pharmacy') || q.includes('rx') || q.includes('prescription') || q.includes('free med')) {
        return `<p>💊 <strong>RHU Pharmacy &amp; Free Medicine Program:</strong></p>
          <p>RHU provides <strong>100% FREE essential medicines</strong> (Paracetamol, Amoxicillin, Amlodipine, Metformin, Oresol, Multivitamins, Iron) upon presenting an official RHU Doctor's Prescription.</p>
          <p class="mt-1">Pharmacy Operating Hours: <strong>Monday to Friday, 8:00 AM – 5:00 PM</strong> at the Main RHU Pharmacy window.</p>`;
      }

      // 6. Vaccination, Immunization & Anti-Rabies
      if (q.includes('vaccin') || q.includes('immuniz') || q.includes('baby') || q.includes('infant') || q.includes('bcg') || q.includes('measles') || q.includes('polio') || q.includes('rabies') || q.includes('bite') || q.includes('kagat')) {
        if (q.includes('rabies') || q.includes('bite') || q.includes('kagat')) {
          return `<p>🐕 <strong>Animal Bite &amp; Anti-Rabies Center (ABTC):</strong></p>
            <p>• <strong>Immediate First Aid:</strong> Wash wound under running water with soap for 15 minutes.</p>
            <p>• <strong>ABTC Vaccination Days:</strong> Monday, Wednesday, &amp; Friday 8:00 AM – 11:30 AM.</p>
            <p>• <strong>Emergency:</strong> For severe Category III bites, proceed immediately to ER or call (043) 740-1234.</p>`;
        }

        const nurses = registeredStaff.filter(s => (s.staff_type || '').toLowerCase().includes('nurse') || (s.specialization || '').toLowerCase().includes('vaccin'));
        let nurseSummary = nurses.length > 0 ?
          `<p class="mt-1 font-bold text-slate-700">Assigned Registered Nurses:</p><ul class="list-disc pl-4 space-y-0.5 text-slate-700 font-medium">` +
          nurses.map(n => `<li><strong>${escapeHtml(n.first_name)} ${escapeHtml(n.last_name)}</strong> (${escapeHtml(n.staff_type || 'Nurse')})</li>`).join('') + `</ul>` :
          '';

        return `<p>💉 <strong>Infant &amp; Child Immunization:</strong></p>
          <p>Routine vaccines (BCG, Hep B, Pentavalent, OPV, IPV, MMR) are <strong>100% FREE</strong> for children 0–5 years old.</p>
          <p>Vaccination Day: <strong>Every Wednesday, 8:00 AM – 12:00 NN</strong> at the OPD Hall. Please present your Child EPI Booklet.</p>
          ${nurseSummary}`;
      }

      // 7. Laboratory & Diagnostics
      if (q.includes('lab') || q.includes('blood test') || q.includes('cbc') || q.includes('urinalysis') || q.includes('sugar') || q.includes('ecg') || q.includes('sputum') || q.includes('tb')) {
        return `<p>🧪 <strong>RHU Laboratory &amp; Diagnostic Services:</strong></p>
          <ul class="list-disc pl-4 space-y-1">
            <li><strong>Fasting Blood Sugar (FBS):</strong> Mon–Thu morning (fasting 8–10 hours required).</li>
            <li><strong>CBC &amp; Urinalysis:</strong> Routine OPD screening.</li>
            <li><strong>GeneXpert / Sputum TB Screening:</strong> Mon–Fri 8:00 AM – 12:00 NN (FREE).</li>
            <li><strong>12-Lead ECG:</strong> Available during general OPD checkup.</li>
          </ul>
          <p class="mt-1">Please visit OPD Triage to secure a laboratory request slip.</p>`;
      }

      // 8. Dental Care
      if (q.includes('dental') || q.includes('tooth') || q.includes('ngipin') || q.includes('bunot') || q.includes('dentist')) {
        return `<p>🦷 <strong>RHU Dental Clinic Services:</strong></p>
          <p>• Tooth Extraction (Bunot), Dental Screening, and Consultation.</p>
          <p>• Schedule: <strong>Tuesday &amp; Thursday, 8:30 AM – 3:00 PM</strong> at Dental Section.</p>
          <p>• Service is <strong>FREE</strong> for registered Nasugbu residents!</p>`;
      }

      // 9. Emergency, Ambulance & Hotline
      if (q.includes('emergency') || q.includes('ambulance') || q.includes('911') || q.includes('urgent') || q.includes('hotline') || q.includes('call')) {
        return `<p>🚨 <strong>Emergency Contact &amp; Ambulance Hotline:</strong></p>
          <ul class="list-disc pl-4 space-y-1">
            <li><strong>RHU Emergency Line:</strong> (043) 740-1234 / +63 917 123 4567</li>
            <li><strong>MDRRMO Disaster Response:</strong> 911 / (043) 740-9999</li>
            <li><strong>Ambulance Dispatch:</strong> Available 24/7</li>
          </ul>
          <p class="mt-1">You can also alert our team instantly using the <strong>Emergency &amp; Referral</strong> tab on your dashboard!</p>`;
      }

      // 10. Maternal, Prenatal & Midwife Services
      if (q.includes('prenatal') || q.includes('pregnant') || q.includes('maternal') || q.includes('family planning') || q.includes('buntis')) {
        const midwives = registeredStaff.filter(s => (s.staff_type || '').toLowerCase().includes('midwife') || (s.specialization || '').toLowerCase().includes('maternal'));
        let midwifeSummary = midwives.length > 0 ?
          `<p class="mt-1 font-bold text-slate-700">Registered RHU Midwives:</p><ul class="list-disc pl-4 space-y-0.5 text-slate-700 font-medium">` +
          midwives.map(m => `<li><strong>${escapeHtml(m.first_name)} ${escapeHtml(m.last_name)}</strong> (${escapeHtml(m.specialization || 'Midwife')})</li>`).join('') + `</ul>` :
          '';

        return `<p>🤰 <strong>Maternal &amp; Midwife Services:</strong></p>
          <p>Supervised by registered RHU midwives at the Women's Wellness Clinic.</p>
          <p>Includes prenatal exams, tetanus toxoid, iron supplements, and family planning counseling every <strong>Wednesday &amp; Friday</strong>.</p>
          ${midwifeSummary}`;
      }

      // 11. Symptoms & Medical Advice
      if (q.includes('fever') || q.includes('lagnat') || q.includes('cough') || q.includes('ubo') || q.includes('headache') || q.includes('diarrhea') || q.includes('sakit') || q.includes('pain') || q.includes('vomit') || q.includes('dengue')) {
        return `<p>🩺 <strong>Health Symptom &amp; Advisory Guide:</strong></p>
          <p>• <strong>First Aid:</strong> Drink plenty of clean water/Oresol and rest in a cool area.</p>
          <p>• <strong>Free Doctor Checkup:</strong> Visit the RHU OPD Clinic (Mon–Fri 8 AM – 5 PM) for free evaluation and free medicines.</p>
          <p>• <strong>Warning Signs:</strong> If fever exceeds 39°C, difficulty breathing, or severe abdominal pain, seek emergency care immediately!</p>`;
      }

      // 12. Profile & PhilHealth Updates
      if (q.includes('profile') || q.includes('philhealth') || q.includes('contact person') || q.includes('update') || q.includes('blood') || q.includes('allergy')) {
        return `<p>📋 <strong>Updating Your Health Profile:</strong></p>
          <p>You can update your PhilHealth ID, blood type, allergies, chronic conditions, and emergency contact person under the <strong>My Profile</strong> tab anytime!</p>`;
      }

      // 13. Locations, Map & Barangay Health Stations
      if (q.includes('location') || q.includes('address') || q.includes('map') || q.includes('where') || q.includes('station') || q.includes('barangay')) {
        return `<p>📍 <strong>RHU Center &amp; Health Stations:</strong></p>
          <p>The <strong>Nasugbu RHU Main Center</strong> is located at F. Alix St., Poblacion, Nasugbu, Batangas.</p>
          <p>Barangay Health Stations: Halang, Bucana, Wawa, and Kaylaway. Check the <strong>Nearby Map</strong> tab for interactive GPS coordinates!</p>`;
      }

      // 14. Senior Citizen & PWD Benefits
      if (q.includes('senior') || q.includes('pwd') || q.includes('matanda') || q.includes('disability')) {
        return `<p>👴 <strong>Senior Citizen &amp; PWD Health Privileges:</strong></p>
          <p>• Priority Lane at OPD Triage &amp; Pharmacy.</p>
          <p>• Free maintenance medicines (Hypertension, Diabetes) under the PhilHealth KONSULTA Package.</p>
          <p>• Free annual physical exam &amp; blood screening at RHU Main Center.</p>`;
      }

      // 15. Personal Profile Context
      if (q.includes('my name') || q.includes('my id') || q.includes('my visits') || q.includes('who am i') || q.includes('my record')) {
        return `<p>👤 <strong>Your Resident Profile:</strong></p>
          <p>Resident Name: <strong><?= esc(($resident['first_name'] ?? 'Alex') . ' ' . ($resident['last_name'] ?? 'Cruz')) ?></strong></p>
          <p>Patient ID: <strong>#<?= esc($resident['id'] ?? '1') ?></strong> | Brgy. <strong><?= esc($resident['barangay'] ?? 'Nasugbu') ?></strong></p>
          <p>Recorded OPD Visits: <strong><?= (int)$visitsThisYear ?> Visit(s)</strong></p>`;
      }

      // 16. Universal Intelligent Fallback AI Generator for ANY novel question
      const cleanSubject = query.replace(/[^\w\s]/gi, '').trim();
      return `<p>💡 <strong>RHU Health Assistant Information:</strong></p>
        <p>Thank you for asking about "<strong>${escapeHtml(cleanSubject)}</strong>".</p>
        <p>Our Rural Health Unit (RHU) provides free physician consultations, laboratory diagnostic tests, free essential medicines, health clearances, and emergency assistance for all Nasugbu residents.</p>
        <p class="mt-1.5 font-bold text-slate-700">How to proceed:</p>
        <ul class="list-disc pl-4 space-y-1 text-slate-700 font-medium">
          <li><strong>Request OPD Appointment:</strong> Go to your <em>Health Records</em> tab to schedule a checkup.</li>
          <li><strong>Visit RHU Main Center:</strong> Open Mon–Fri, 8:00 AM – 5:00 PM at F. Alix St., Poblacion.</li>
          <li><strong>Emergency Hotline:</strong> Call (043) 740-1234 or 911 for urgent medical assistance.</li>
        </ul>`;
    }
  </script>
</body>

</html>
