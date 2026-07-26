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

// Audit records are restricted to authenticated RHU staff and administrators.
// Never allow a resident-controlled tab value to enter an audit-log view.
if (strtolower((string)($_GET['tab'] ?? '')) === 'audit') {
    $_SESSION['resident_dashboard_access_flash'] = 'Audit logs are restricted to RHU staff and administrators.';
    header('Location: ResidentDashboard.php?tab=home');
    exit;
}

function esc($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function residentAge(?string $dateOfBirth): ?int {
    if (!$dateOfBirth) return null;
    try { return (new DateTime($dateOfBirth))->diff(new DateTime('today'))->y; } catch (Exception $e) { return null; }
}

$user = $_SESSION['user'];
$resident = null;
$consultations = [];
$vaccinationRecords = [];
$certificates = [];
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
        if ($resident) {
            $residentId = (int)$resident['id'];
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS resident_dependents (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    primary_resident_id BIGINT UNSIGNED NOT NULL,
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

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $formType = $_POST['form'] ?? '';
                $submittedCsrf = (string)($_POST['csrf_token'] ?? '');
                if (in_array($formType, ['add_dependent', 'remove_dependent'], true)
                    && !hash_equals($dashboardCsrf, $submittedCsrf)) {
                    $dependentErrors[] = 'Your session expired. Please refresh the page and try again.';
                } elseif ($formType === 'add_dependent') {
                    $firstName = trim($_POST['first_name'] ?? '');
                    $middleName = trim($_POST['middle_name'] ?? '');
                    $lastName = trim($_POST['last_name'] ?? '');
                    $relationship = trim($_POST['relationship'] ?? '');
                    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
                    $gender = trim($_POST['gender'] ?? '');
                    $bloodType = trim($_POST['blood_type'] ?? '');
                    $medicalNotes = trim($_POST['medical_notes'] ?? '');
                    $allowedRelationships = ['Child', 'Spouse', 'Parent', 'Sibling', 'Grandchild', 'Other'];
                    $allowedGenders = ['Female', 'Male', 'Other', 'Prefer not to say'];
                    $allowedBloodTypes = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];

                    if ($firstName === '' || $lastName === '' || $relationship === '' || $dateOfBirth === '') {
                        $dependentErrors[] = 'First name, last name, relationship, and date of birth are required.';
                    } elseif (!in_array($relationship, $allowedRelationships, true)) {
                        $dependentErrors[] = 'Please select a valid relationship.';
                    } elseif ($gender !== '' && !in_array($gender, $allowedGenders, true)) {
                        $dependentErrors[] = 'Please select a valid gender.';
                    } elseif (!in_array($bloodType, $allowedBloodTypes, true)) {
                        $dependentErrors[] = 'Please select a valid blood type.';
                    } elseif (!DateTime::createFromFormat('Y-m-d', $dateOfBirth) || $dateOfBirth > date('Y-m-d')) {
                        $dependentErrors[] = 'Please provide a valid date of birth.';
                    } else {
                        $statement = $pdo->prepare(
                            'INSERT INTO resident_dependents
                             (primary_resident_id, first_name, middle_name, last_name, relationship, date_of_birth, gender, blood_type, medical_notes)
                             VALUES (:resident_id, :first_name, :middle_name, :last_name, :relationship, :date_of_birth, :gender, :blood_type, :medical_notes)'
                        );
                        $statement->execute([
                            'resident_id' => $residentId,
                            'first_name' => $firstName,
                            'middle_name' => $middleName ?: null,
                            'last_name' => $lastName,
                            'relationship' => $relationship,
                            'date_of_birth' => $dateOfBirth,
                            'gender' => $gender ?: null,
                            'blood_type' => $bloodType ?: null,
                            'medical_notes' => $medicalNotes ?: null,
                        ]);
                        $_SESSION['resident_dashboard_dependent_flash'] = "{$firstName} {$lastName} was added to your household.";
                        header('Location: ResidentDashboard.php?tab=family');
                        exit;
                    }
                } elseif ($formType === 'remove_dependent') {
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
                    $preferredDate = trim($_POST['preferred_date'] ?? date('Y-m-d'));
                    $ins = $pdo->prepare("INSERT INTO consultations (resident_id, physician_id, consultation_date, consultation_time, chief_complaint, diagnosis, consultation_notes, created_at) VALUES (:res, 1, :cdate, CURTIME(), :chief, 'Pending OPD Triage', 'Online Appointment Request from Resident Portal', NOW())");
                    $ins->execute(['res' => $residentId, 'cdate' => $preferredDate, 'chief' => $chiefComplaint]);
                    $_SESSION['resident_dashboard_message_flash'] = 'OPD Consultation Appointment request submitted to RHU Staff & Attending Physician.';
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

            $statement = $pdo->prepare(
                'SELECT hc.*, COALESCE(ct.certificate_type_name, "Health Certificate") as certificate_type_name
                 FROM health_certificates hc
                 LEFT JOIN certificate_types ct ON hc.certificate_type_id = ct.id
                 WHERE hc.resident_id = :resident_id
                 ORDER BY hc.id DESC'
            );
            $statement->execute(['resident_id' => $residentId]);
            $certificates = $statement->fetchAll(PDO::FETCH_ASSOC);

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
        }
    } catch (Exception $ex) {
        error_log("ResidentDashboard DB Hydration Error: " . $ex->getMessage());
    }
}

if (!$resident && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add_dependent') {
    $dependentErrors[] = 'Your account is not linked to a resident record, so the dependent was not saved. Please contact RHU staff to verify your resident profile.';
}

if (!$resident && !empty($_SESSION['resident_registration'])) $resident = $_SESSION['resident_registration'];

$lastConsultation = $consultations[0] ?? null;
$currentYear = (int)date('Y');
$visitsThisYear = count(array_filter($consultations, fn($consultation) => !empty($consultation['consultation_date']) && (int)date('Y', strtotime($consultation['consultation_date'])) === $currentYear));
$totalPrescriptions = array_sum(array_map(fn($consultation) => empty($consultation['medications_prescribed']) ? 0 : count(array_filter(preg_split('/,\s*/', $consultation['medications_prescribed']))), $consultations));
$initials = strtoupper(substr($resident['first_name'] ?? 'R', 0, 1) . substr($resident['last_name'] ?? 'S', 0, 1));
$age = residentAge($resident['date_of_birth'] ?? null);

$tabs = [
    'home' => ['Overview', 'home'],
    'records' => ['Health Records', 'file-text'],
    'immunization' => ['Immunization', 'shield-check'],
    'certificates' => ['Certificates', 'award'],
    'family' => ['Family Members', 'users'], 
    'events' => ['Events & Programs', 'calendar'],
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
  <title>Resident Dashboard - RedPulse RHU</title>
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
    #sidebar-overlay {
      background: rgba(15, 23, 42, .34);
      backdrop-filter: blur(5px);
      -webkit-backdrop-filter: blur(5px);
      transition: opacity 220ms ease, backdrop-filter 220ms ease;
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

    /* Collapsed Sidebar Style */
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

    /* Google Classroom Active Tab Pill Style */
    .nav-active {
      background-color: #e8f0fe !important;
      color: #1a73e8 !important;
      font-weight: 700 !important;
    }
    .nav-active i {
      color: #1a73e8 !important;
    }
    [data-tab-panel] {
      animation: panel-enter 300ms cubic-bezier(.2,.8,.2,1);
    }
    [data-tab-panel] > .rounded-2xl,
    [data-tab-panel] article,
    [data-tab-panel] .dashboard-surface {
      transition: transform 220ms cubic-bezier(.2,.8,.2,1), box-shadow 220ms ease, border-color 220ms ease;
    }
    [data-tab-panel] > .rounded-2xl:hover,
    [data-tab-panel] article:hover,
    [data-tab-panel] .dashboard-surface:hover {
      transform: translateY(-3px) scale(1.012);
      border-color: rgba(45,212,191,.75);
      box-shadow: 0 16px 35px rgba(15,118,110,.11);
      position: relative;
      z-index: 2;
    }
    button, a {
      -webkit-tap-highlight-color: transparent;
    }
    button:not([disabled]), a[href] {
      transition: transform 180ms ease, box-shadow 180ms ease, background-color 180ms ease, color 180ms ease, border-color 180ms ease;
    }
    button:not([disabled]):active, a[href]:active {
      transform: scale(.97);
    }
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
    #sidebar {
      background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(240,253,250,.96) 55%, rgba(239,246,255,.96));
      border-color: rgba(153,246,228,.7);
      box-shadow: 12px 0 35px rgba(15,23,42,.06);
    }
    #sidebar .sidebar-link:hover {
      transform: translateX(3px);
      background: linear-gradient(90deg, rgba(204,251,241,.8), rgba(224,242,254,.58));
      color: var(--rhu-teal);
    }
    header.sticky {
      background: rgba(255,255,255,.84) !important;
      border-color: rgba(153,246,228,.65) !important;
      box-shadow: 0 8px 30px rgba(15,118,110,.055);
      backdrop-filter: blur(18px);
    }
    [data-tab-panel] h3,
    [data-tab-panel] h4 {
      letter-spacing: -.015em;
    }
    [data-tab-panel] .bg-white {
      background-image: linear-gradient(145deg, rgba(255,255,255,.98), rgba(248,250,252,.94));
    }
    [data-notification-panel] {
      animation: popover-enter 190ms cubic-bezier(.2,.8,.2,1);
      border-color: rgba(153,246,228,.8) !important;
      box-shadow: 0 20px 45px rgba(15,23,42,.16) !important;
    }
    #dependent-modal > div,
    #appointment-modal > div,
    #logout-modal > div {
      animation: modal-enter 240ms cubic-bezier(.2,.8,.2,1);
    }
    * {
      scrollbar-width: thin;
      scrollbar-color: #99f6e4 transparent;
    }
    @keyframes panel-enter {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: none; }
    }
    @keyframes modal-enter {
      from { opacity: 0; transform: translateY(12px) scale(.97); }
      to { opacity: 1; transform: none; }
    }
    @keyframes popover-enter {
      from { opacity: 0; transform: translateY(-6px) scale(.98); }
      to { opacity: 1; transform: none; }
    }
    @keyframes orb-float {
      from { transform: translate3d(-1rem,-1rem,0) scale(.92); }
      to { transform: translate3d(2rem,2rem,0) scale(1.08); }
    }
    .family-hover {
      transform: translateZ(0) scale(1);
      transition: transform 220ms cubic-bezier(.2,.8,.2,1), box-shadow 220ms ease, border-color 220ms ease;
      will-change: transform;
    }
    .family-hover:hover {
      transform: translateY(-3px) scale(1.025);
      box-shadow: 0 16px 32px rgba(15, 118, 110, .14);
      z-index: 2;
    }
    @media (prefers-reduced-motion: reduce) {
      html { scroll-behavior: auto; }
      [data-tab-panel],
      #dependent-modal > div,
      #appointment-modal > div,
      #logout-modal > div,
      .reveal-on-scroll,
      .reveal-on-scroll.is-visible {
        animation: none;
        transition: none;
        transform: none;
        opacity: 1;
      }
      .family-hover,
      .family-hover:hover {
        transform: none;
        transition: none;
      }
      .ambient-orb { animation: none; }
    }
  </style>
  <link rel="stylesheet" href="dashboard-enhancements.css">
  <script defer src="dashboard-enhancements.js"></script>
</head>
<body class="min-h-screen bg-white text-slate-800 antialiased flex flex-col md:flex-row">
  <div id="scroll-progress" aria-hidden="true"></div>
  <div class="ambient-orb ambient-orb-one" aria-hidden="true"></div>
  <div class="ambient-orb ambient-orb-two" aria-hidden="true"></div>

  <!-- Mobile Overlay -->
  <div id="sidebar-overlay" class="fixed inset-0 z-40 hidden md:hidden" aria-hidden="true"></div>

  <!-- Google Classroom White Sidebar -->
  <aside id="sidebar" class="fixed md:sticky top-0 z-50 h-screen w-64 shrink-0 bg-white border-r border-slate-200 transition-all duration-200 ease-in-out flex flex-col justify-between -translate-x-full md:translate-x-0">
    <div>
      <!-- Header / Toggle -->
      <div class="flex items-center justify-between h-16 px-4 border-b border-slate-100">
        <div class="flex items-center gap-3 overflow-hidden">
          <button id="sidebar-collapse-btn" type="button" class="flex h-10 w-10 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100 transition-colors" title="Toggle Menu">
            <i data-lucide="menu" class="h-5 w-5"></i>
          </button>
          <div class="sidebar-header-text flex items-center gap-2">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-600 text-white font-bold">
              <i data-lucide="activity" class="h-4 w-4"></i>
            </div>
            <span class="text-base font-bold text-slate-800 tracking-tight">RedPulse</span>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <nav class="p-2 space-y-1">
        <?php foreach ($tabs as $id => [$label, $icon]): ?>
          <button type="button" data-tab-button="<?= esc($id) ?>" class="sidebar-link w-full flex items-center gap-4 px-4 py-3 rounded-r-full text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-all">
            <i data-lucide="<?= esc($icon) ?>" class="h-5 w-5 shrink-0 text-slate-500"></i>
            <span class="sidebar-text truncate"><?= esc($label) ?></span>
          </button>
        <?php endforeach; ?>
      </nav>
    </div>

    <!-- Sign Out -->
    <div class="p-2 border-t border-slate-100">
      <a href="ResidentDashboard.php?logout=1" data-logout-link class="sidebar-link w-full flex items-center gap-4 px-4 py-3 rounded-r-full text-sm font-semibold text-rose-600 hover:bg-rose-50 transition-all">
        <i data-lucide="log-out" class="h-5 w-5 shrink-0"></i>
        <span class="sidebar-text truncate">Sign Out</span>
      </a>
    </div>
  </aside>

  <!-- Main Area -->
  <div class="flex-1 min-w-0 flex flex-col min-h-screen bg-slate-50/50">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-slate-200 px-4 sm:px-8 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button id="mobile-menu-btn" type="button" class="md:hidden flex h-10 w-10 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100">
          <i data-lucide="menu" class="h-5 w-5"></i>
        </button>
        <h2 id="current-page-title" class="text-lg font-bold text-slate-800">Dashboard</h2>
      </div>

      <!-- Notifications & Profile Header -->
      <div class="flex items-center gap-3">
        <div class="relative">
          <button type="button" data-notifications class="flex h-10 w-10 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100 transition-colors">
            <i data-lucide="bell" class="h-5 w-5"></i>
            <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500"></span>
          </button>
          
          <div data-notification-panel class="hidden absolute right-0 top-12 z-50 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white text-slate-800 shadow-xl transition-all">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 bg-slate-50">
              <p class="text-xs font-bold text-slate-900">Notifications</p>
              <button type="button" data-close-notifications class="text-xs font-semibold text-slate-400 hover:text-slate-600">Close</button>
            </div>
            <div class="divide-y divide-slate-100 text-xs">
              <div class="p-4 hover:bg-slate-50">
                <p class="font-medium text-slate-700">Hypertension follow-up due on <strong>July 10, 2026</strong>.</p>
                <p class="mt-1 text-[10px] text-slate-400">2 days ago</p>
              </div>
              <div class="p-4 hover:bg-slate-50">
                <p class="font-medium text-slate-700">Annual influenza vaccine due October 2026.</p>
                <p class="mt-1 text-[10px] text-slate-400">1 week ago</p>
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2 pl-2 border-l border-slate-200">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-teal-700 text-xs font-bold text-white">
            <?= esc($initials) ?>
          </div>
          <div class="hidden sm:block text-left">
            <p class="text-xs font-bold text-slate-800 leading-tight"><?= esc($resident['first_name'] ?? 'Resident') ?></p>
            <p class="text-[10px] text-slate-400 font-medium">Resident Patient</p>
          </div>
        </div>
      </div>
    </header>

    <!-- Dynamic Content Section -->
    <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto space-y-6">

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
        <!-- Banner Card -->
        <div class="relative overflow-hidden rounded-2xl bg-teal-800 p-6 text-white shadow-sm sm:p-8">
          <div class="relative z-10 flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
            <div>
              <p class="text-xs font-medium uppercase tracking-wider text-teal-200">Welcome Back</p>
              <h2 class="mt-1 text-2xl font-extrabold sm:text-3xl tracking-tight">
                <?= esc(($resident['first_name'] ?? '') ? ($resident['first_name'] . ' ' . ($resident['last_name'] ?? '')) : 'Resident') ?>
              </h2>
              <div class="mt-4 flex flex-wrap gap-2 text-xs font-medium">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1">
                  <i data-lucide="map-pin" class="h-3.5 w-3.5 text-teal-300"></i> <?= esc($resident['barangay'] ?? 'Unknown') ?>
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1">
                  <i data-lucide="droplet" class="h-3.5 w-3.5 text-rose-300"></i> <?= esc($resident['blood_type'] ?? 'Unknown') ?>
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1">
                  <i data-lucide="user" class="h-3.5 w-3.5 text-emerald-300"></i> <?= $age === null ? '—' : esc($age) . ' y/o' ?>
                </span>
              </div>
            </div>
            <div class="hidden sm:block text-right">
              <span class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 text-white">
                <i data-lucide="user-check" class="h-8 w-8"></i>
              </span>
            </div>
          </div>
          <div class="mt-6 grid grid-cols-2 gap-4 border-t border-white/10 pt-4 text-xs">
            <div>
              <p class="text-teal-200 font-medium">PhilHealth No.</p>
              <p class="mt-0.5 font-semibold text-white tracking-wider"><?= esc($resident['philhealth_id'] ?? 'Not available') ?></p>
            </div>
            <div>
              <p class="text-teal-200 font-medium">Patient ID</p>
              <p class="mt-0.5 font-semibold text-white tracking-wider"><?= esc($resident['id'] ?? '—') ?></p>
            </div>
          </div>
        </div>

        <!-- Health Reminders -->
        <div class="flex items-start gap-3.5 rounded-2xl border border-amber-200 bg-amber-50/60 p-4 text-amber-900 shadow-2xs">
          <div class="rounded-lg bg-amber-100 p-2 text-amber-700 shrink-0">
            <i data-lucide="bell" class="h-5 w-5"></i>
          </div>
          <div>
            <p class="text-sm font-bold text-amber-900">Health Reminders</p>
            <ul class="mt-1 space-y-1 text-xs text-amber-800 font-medium">
              <li class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Hypertension follow-up due: <strong>July 10, 2026</strong></li>
              <li class="flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Annual influenza vaccine due: <strong>October 2026</strong></li>
            </ul>
          </div>
        </div>

        <!-- Quick Shortcuts -->
        <div>
          <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">Quick Shortcuts</h3>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <?php 
            $quickAccess = [
              ['Health Records', 'records', 'bg-sky-50 text-sky-600', 'stethoscope'],
              ['Immunization', 'immunization', 'bg-indigo-50 text-indigo-600', 'shield-check'],
              ['Certificates', 'certificates', 'bg-emerald-50 text-emerald-600', 'award'],
              ['Health Events', 'events', 'bg-rose-50 text-rose-600', 'calendar'],
              ['Contact RHU', 'contact', 'bg-teal-50 text-teal-600', 'phone-call'],
              ['Health Tips', 'home', 'bg-purple-50 text-purple-600', 'heart-pulse']
            ];
            foreach ($quickAccess as [$label, $target, $style, $icon]): ?>
              <button type="button" data-tab-link="<?= esc($target) ?>" class="flex flex-col items-center justify-center gap-2.5 rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-2xs transition-all hover:border-slate-300">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl <?= $style ?>">
                  <i data-lucide="<?= esc($icon) ?>" class="h-5 w-5"></i>
                </span>
                <span class="text-xs font-bold text-slate-700"><?= esc($label) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Last Consultation Block -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 flex items-center gap-2">
              <i data-lucide="clock" class="h-4 w-4 text-teal-600"></i> Last Consultation
            </h3>
            <button type="button" data-tab-link="records" class="text-xs font-semibold text-teal-600 hover:text-teal-700 flex items-center gap-1">
              View all <i data-lucide="chevron-right" class="h-3 w-3"></i>
            </button>
          </div>
          <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
            <p class="font-bold text-slate-800 text-base"><?= esc($lastConsultation['diagnosis'] ?? 'No consultations yet') ?></p>
            <p class="mt-0.5 text-xs text-slate-500"><?= esc(($lastConsultation['physician_name'] ?? '') ?: 'No physician assigned') ?></p>
            <?php if (!empty($lastConsultation['consultation_date'])): ?>
              <p class="mt-2 text-[11px] font-medium text-slate-400">Date: <?= esc($lastConsultation['consultation_date']) ?></p>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- 2. HEALTH RECORDS TAB -->
      <section data-tab-panel="records" class="hidden space-y-6">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-bold text-slate-900">Health Records & Consultations</h3>
          <button type="button" onclick="document.getElementById('appointment-modal').classList.remove('hidden')" class="rounded-xl bg-teal-600 px-4 py-2 text-xs font-bold text-white hover:bg-teal-700 transition-all flex items-center gap-2">
            <i data-lucide="plus" class="h-4 w-4"></i> Request OPD Appointment
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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

        <div class="space-y-3">
          <?php if (!$consultations): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-400">
              <i data-lucide="folder-open" class="mx-auto mb-2 h-10 w-10 text-slate-300"></i>
              <p class="text-sm font-semibold">No consultations recorded yet</p>
            </div>
          <?php else: foreach ($consultations as $consultation): ?>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs transition-all hover:border-slate-300">
              <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 border-b border-slate-100 pb-3">
                <div>
                  <span class="text-[11px] font-bold uppercase tracking-wider text-teal-600"><?= esc($consultation['consultation_time'] ?? 'OPD') ?></span>
                  <h4 class="font-bold text-slate-900 text-base"><?= esc($consultation['diagnosis'] ?? 'Consultation') ?></h4>
                  <p class="text-xs text-slate-500 font-medium">Attending: <?= esc($consultation['physician_name'] ?: 'Doctor') ?></p>
                </div>
                <span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600"><?= esc($consultation['consultation_date'] ?? '—') ?></span>
              </div>
              <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div>
                  <p class="font-bold text-slate-400">Chief Complaint:</p>
                  <p class="text-slate-700 font-medium"><?= esc($consultation['chief_complaint'] ?? 'None specified') ?></p>
                </div>
                <div>
                  <p class="font-bold text-slate-400">Prescribed Medications:</p>
                  <p class="text-slate-700 font-medium"><?= esc($consultation['medications_prescribed'] ?? 'None') ?></p>
                </div>
              </div>
            </article>
          <?php endforeach; endif; ?>
        </div>
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
          <?php endforeach; endif; ?>
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
          <?php else: foreach ($certificates as $cert): ?>
            <article class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
              <div>
                <p class="text-sm font-bold text-slate-900"><?= esc($cert['certificate_type_name']) ?></p>
                <p class="text-xs text-slate-500 font-medium">No: <span class="font-mono text-slate-700"><?= esc($cert['certificate_number']) ?></span> | Purpose: <?= esc($cert['purpose']) ?></p>
              </div>
              <div class="flex items-center gap-3 text-xs">
                <span class="rounded-full px-3 py-1 font-bold <?= strtolower($cert['validity_status']) === 'valid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                  <?= esc($cert['validity_status']) ?>
                </span>
              </div>
            </article>
          <?php endforeach; endif; ?>
        </div>
      </section>

      <!-- 5. EVENTS TAB -->
      <section data-tab-panel="events" class="hidden space-y-6">
        <h3 class="text-lg font-bold text-slate-900">Health Programs & Events</h3>
        <div class="space-y-3">
          <?php foreach ($events as [$date, $title, $detail]): ?>
            <article class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs">
              <div class="flex items-start gap-3">
                <span class="rounded-xl bg-teal-50 px-3 py-2 text-xs font-bold text-teal-700 shrink-0 text-center font-mono"><?= esc($date) ?></span>
                <div>
                  <h4 class="text-sm font-bold text-slate-900"><?= esc($title) ?></h4>
                  <p class="mt-1 text-xs text-slate-500 font-medium"><?= esc($detail) ?></p>
                </div>
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
              <?php endforeach; endif; ?>
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
          <button type="button" data-dependent-open class="family-hover rounded-xl bg-gradient-to-r from-teal-600 to-sky-600 px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-teal-600/20 hover:from-teal-700 hover:to-sky-700 flex items-center gap-2">
            <i data-lucide="user-plus" class="h-4 w-4"></i> Add Dependent
          </button>
        </div>

        <?php if ($dependentSuccess): ?>
          <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-semibold text-emerald-800">
            <i data-lucide="circle-check" class="h-5 w-5 shrink-0"></i><p><?= esc($dependentSuccess) ?></p>
          </div>
        <?php endif; ?>
        <?php if ($dependentErrors): ?>
          <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800">
            <i data-lucide="circle-alert" class="h-5 w-5 shrink-0"></i>
            <div><?php foreach ($dependentErrors as $error): ?><p><?= esc($error) ?></p><?php endforeach; ?></div>
          </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
          <div class="family-hover rounded-2xl border border-teal-100 bg-gradient-to-br from-teal-50 to-emerald-50 p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-teal-700">Household members</p><p class="mt-1 text-2xl font-black text-teal-900"><?= count($dependents) + 1 ?></p></div>
          <div class="family-hover rounded-2xl border border-sky-100 bg-gradient-to-br from-sky-50 to-indigo-50 p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-sky-700">Dependents</p><p class="mt-1 text-2xl font-black text-sky-900"><?= count($dependents) ?></p></div>
          <div class="family-hover col-span-2 rounded-2xl border border-violet-100 bg-gradient-to-br from-violet-50 to-fuchsia-50 p-4 sm:col-span-1"><p class="text-[10px] font-bold uppercase tracking-wider text-violet-700">Profile status</p><p class="mt-2 text-xs font-extrabold text-violet-900">Verified resident</p></div>
        </div>

        <!-- Household Head Card -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
          ?>
            <article class="family-hover group rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-sky-50/40 p-5 shadow-sm hover:border-sky-300">
              <div class="flex items-start gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-sky-500 to-indigo-600 text-sm font-bold text-white shadow-md"><?= esc($dependentInitials) ?></div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0"><h4 class="truncate text-sm font-bold text-slate-900"><?= esc($dependentName) ?></h4><p class="mt-1 text-xs font-medium text-slate-500"><?= esc($dependent['relationship']) ?> · <?= $dependentAge === null ? 'Age unavailable' : esc($dependentAge) . ' y/o' ?> · <?= esc($dependent['gender'] ?: 'Not specified') ?></p></div>
                    <span class="rounded-full bg-sky-100 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-sky-700"><?= esc($dependent['blood_type'] ?: 'Blood N/A') ?></span>
                  </div>
                </div>
              </div>
              <?php if (!empty($dependent['medical_notes'])): ?><p class="mt-4 rounded-xl bg-amber-50 p-3 text-[11px] font-medium leading-5 text-amber-800"><i data-lucide="notebook-tabs" class="mr-1 inline h-3.5 w-3.5"></i><?= esc($dependent['medical_notes']) ?></p><?php endif; ?>
              <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                <span class="flex items-center gap-1 text-[10px] font-bold text-emerald-700"><i data-lucide="link" class="h-3.5 w-3.5"></i>Linked dependent</span>
                <form method="post" action="ResidentDashboard.php?tab=family" onsubmit="return confirm('Remove this dependent from your household?')">
                  <input type="hidden" name="form" value="remove_dependent"><input type="hidden" name="csrf_token" value="<?= esc($dashboardCsrf) ?>"><input type="hidden" name="dependent_id" value="<?= (int)$dependent['id'] ?>">
                  <button type="submit" class="rounded-lg px-2 py-1 text-[10px] font-bold text-rose-600 hover:bg-rose-50">Remove</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>

          <?php if (!$dependents): ?>
            <button type="button" data-dependent-open class="family-hover flex min-h-44 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-sky-200 bg-sky-50/40 p-5 text-center hover:border-sky-400 hover:bg-sky-50">
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

      <!-- 6. EMERGENCY & REFERRAL TAB -->
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
  <div id="dependent-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm">
    <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-white/70 bg-white shadow-2xl">
      <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-gradient-to-r from-teal-50 to-sky-50 px-6 py-5">
        <div class="flex gap-3">
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-600 to-sky-600 text-white shadow-md"><i data-lucide="user-round-plus" class="h-5 w-5"></i></span>
          <div><h3 class="text-base font-black text-slate-900">Add Household Dependent</h3><p class="mt-1 text-xs text-slate-500">Create a profile linked to your resident account.</p></div>
        </div>
        <button type="button" data-dependent-close class="rounded-xl p-2 text-slate-500 hover:bg-white hover:text-slate-800" aria-label="Close dependent form"><i data-lucide="x" class="h-5 w-5"></i></button>
      </div>
      <form method="post" action="ResidentDashboard.php?tab=family" class="space-y-5 p-6 text-xs">
        <input type="hidden" name="form" value="add_dependent">
        <input type="hidden" name="csrf_token" value="<?= esc($dashboardCsrf) ?>">
        <div>
          <p class="mb-3 font-bold uppercase tracking-wider text-slate-400">Personal information</p>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="space-y-1.5"><span class="font-bold text-slate-700">First name <b class="text-rose-500">*</b></span><input required maxlength="100" name="first_name" value="<?= esc($_POST['first_name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100" placeholder="First name"></label>
            <label class="space-y-1.5"><span class="font-bold text-slate-700">Middle name</span><input maxlength="100" name="middle_name" value="<?= esc($_POST['middle_name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100" placeholder="Optional"></label>
            <label class="space-y-1.5"><span class="font-bold text-slate-700">Last name <b class="text-rose-500">*</b></span><input required maxlength="100" name="last_name" value="<?= esc($_POST['last_name'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100" placeholder="Last name"></label>
            <label class="space-y-1.5"><span class="font-bold text-slate-700">Relationship <b class="text-rose-500">*</b></span><select required name="relationship" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100"><option value="">Select relationship</option><?php foreach (['Child','Spouse','Parent','Sibling','Grandchild','Other'] as $option): ?><option <?= ($_POST['relationship'] ?? '') === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select></label>
          </div>
        </div>
        <div>
          <p class="mb-3 font-bold uppercase tracking-wider text-slate-400">Health profile</p>
          <div class="grid gap-4 sm:grid-cols-3">
            <label class="space-y-1.5"><span class="font-bold text-slate-700">Date of birth <b class="text-rose-500">*</b></span><input required type="date" max="<?= date('Y-m-d') ?>" name="date_of_birth" value="<?= esc($_POST['date_of_birth'] ?? '') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100"></label>
            <label class="space-y-1.5"><span class="font-bold text-slate-700">Gender</span><select name="gender" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100"><option value="">Select</option><?php foreach (['Female','Male','Other','Prefer not to say'] as $option): ?><option <?= ($_POST['gender'] ?? '') === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select></label>
            <label class="space-y-1.5"><span class="font-bold text-slate-700">Blood type</span><select name="blood_type" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100"><option value="">Unknown</option><?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $option): ?><option <?= ($_POST['blood_type'] ?? '') === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select></label>
          </div>
        </div>
        <label class="block space-y-1.5"><span class="font-bold text-slate-700">Medical notes</span><textarea maxlength="1000" name="medical_notes" rows="3" class="w-full resize-none rounded-xl border border-slate-200 px-3 py-3 font-medium outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100" placeholder="Allergies, conditions, or other important notes"><?= esc($_POST['medical_notes'] ?? '') ?></textarea></label>
        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
          <button type="button" data-dependent-close class="rounded-xl border border-slate-200 px-5 py-3 font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
          <button type="submit" class="rounded-xl bg-gradient-to-r from-teal-600 to-sky-600 px-5 py-3 font-bold text-white shadow-lg shadow-teal-600/20 hover:from-teal-700 hover:to-sky-700"><i data-lucide="user-plus" class="mr-1 inline h-4 w-4"></i>Add Dependent</button>
        </div>
      </form>
    </div>
  </div>

  <!-- OPD Appointment Modal -->
  <div id="appointment-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs hidden p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <h3 class="text-sm font-bold text-slate-900">Request OPD Consultation</h3>
        <button type="button" onclick="document.getElementById('appointment-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>
      <form method="post" action="ResidentDashboard.php?tab=records" class="space-y-3 text-xs">
        <input type="hidden" name="form" value="appointment_request">
        <div>
          <label class="block font-bold text-slate-700 mb-1">Chief Complaint / Purpose</label>
          <input type="text" name="chief_complaint" required class="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-medium focus:outline-none focus:ring-2 focus:ring-teal-500" placeholder="e.g. Fever, Checkup, Cough">
        </div>
        <div>
          <label class="block font-bold text-slate-700 mb-1">Preferred Date</label>
          <input type="date" name="preferred_date" required value="<?= date('Y-m-d') ?>" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-medium focus:outline-none focus:ring-2 focus:ring-teal-500">
        </div>
        <div class="pt-2 flex gap-2">
          <button type="button" onclick="document.getElementById('appointment-modal').classList.add('hidden')" class="flex-1 rounded-xl border border-slate-200 py-2.5 font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
          <button type="submit" class="flex-1 rounded-xl bg-teal-600 py-2.5 font-bold text-white hover:bg-teal-700">Submit Request</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (() => {
      lucide.createIcons();

      const sidebar = document.getElementById('sidebar');
      const sidebarOverlay = document.getElementById('sidebar-overlay');
      const collapseBtn = document.getElementById('sidebar-collapse-btn');
      const mobileBtn = document.getElementById('mobile-menu-btn');
      const pageTitle = document.getElementById('current-page-title');

      const buttons = document.querySelectorAll('[data-tab-button]');
      const panels = document.querySelectorAll('[data-tab-panel]');

      const tabTitles = {
        'home': 'Resident Dashboard',
        'records': 'Health Records',
        'immunization': 'Immunization History',
        'certificates': 'My Certificates',
        'family': 'Family Members',
        'events': 'Events & Health Programs',
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

      window.scrollTo({ top: 0, behavior: 'smooth' });
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

      const notificationButton = document.querySelector('[data-notifications]');
      const notificationPanel = document.querySelector('[data-notification-panel]');
      if (notificationButton && notificationPanel) {
        notificationButton.addEventListener('click', () => notificationPanel.classList.toggle('hidden'));
        const closeButton = document.querySelector('[data-close-notifications]');
        if (closeButton) closeButton.addEventListener('click', () => notificationPanel.classList.add('hidden'));
      }

      const dependentModal = document.getElementById('dependent-modal');
      const openDependentModal = () => {
        dependentModal.classList.remove('hidden');
        dependentModal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
      };
      const closeDependentModal = () => {
        dependentModal.classList.add('hidden');
        dependentModal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
      };
      document.querySelectorAll('[data-dependent-open]').forEach(button => button.addEventListener('click', openDependentModal));
      document.querySelectorAll('[data-dependent-close]').forEach(button => button.addEventListener('click', closeDependentModal));
      dependentModal.addEventListener('click', event => { if (event.target === dependentModal) closeDependentModal(); });
      document.addEventListener('keydown', event => { if (event.key === 'Escape') closeDependentModal(); });
      <?php if ($dependentErrors): ?>openDependentModal();<?php endif; ?>

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
      logoutModal.addEventListener('click', event => { if (event.target === logoutModal) closeLogoutModal(); });
      document.addEventListener('keydown', event => { if (event.key === 'Escape') closeLogoutModal(); });

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
        }, { threshold: 0.08, rootMargin: '0px 0px -24px' });

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
      window.addEventListener('scroll', updateScrollProgress, { passive: true });
      window.addEventListener('resize', updateScrollProgress);
    })();
  </script>
</body>
</html>
