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
        if (!$resident && !empty($user['id'])) {
            $statement = $pdo->prepare('SELECT * FROM residents WHERE user_id = :uid LIMIT 1');
            $statement->execute(['uid' => $user['id']]);
            $resident = $statement->fetch();
        }

        if ($resident) {
            $residentId = (int)$resident['id'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $formType = $_POST['form'] ?? '';
                if ($formType === 'contact') {
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
                } elseif ($formType === 'emergency_request') {
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
        }
    } catch (Exception $ex) {
        error_log("ResidentDashboard DB Hydration Error: " . $ex->getMessage());
    }
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
    body { font-family: 'Plus Jakarta Sans', sans-serif; }

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
  </style>
</head>
<body class="min-h-screen bg-white text-slate-800 antialiased flex flex-col md:flex-row">

  <!-- Mobile Overlay -->
  <div id="sidebar-overlay" class="fixed inset-0 z-40 bg-slate-900/30 backdrop-blur-xs hidden md:hidden"></div>

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
      <a href="ResidentDashboard.php?logout=1" class="sidebar-link w-full flex items-center gap-4 px-4 py-3 rounded-r-full text-sm font-semibold text-rose-600 hover:bg-rose-50 transition-all">
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
              ['Family Members', 'family', 'bg-rose-50 text-rose-600', 'users'],
              ['Health Events', 'events', 'bg-rose-50 text-rose-600', 'calendar'],
              ['Contact RHU', 'contact', 'bg-teal-50 text-teal-600', 'phone-call'],
              ['Emergency & Referral', 'emergency', 'bg-rose-50 text-rose-600', 'siren'],
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
            <button type="button" class="rounded-xl bg-teal-600 px-4 py-2 text-xs font-bold text-white hover:bg-teal-700 transition-all flex items-center gap-2">
            <i data-lucide="user-plus" class="h-4 w-4"></i> Add Dependent
            </button>
        </div>

        <!-- Household Head Card -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Self (Head) -->
            <div class="rounded-2xl border-2 border-teal-500 bg-teal-50/30 p-5 shadow-2xs relative">
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
        'home': 'Overview',
        'records': 'Health Records',
        'immunization': 'Immunization History',
        'certificates': 'My Certificates',
        'family': 'Household Profile',
        'emergency': 'Emergency & Referral Desk',
        'events': 'Events & Health Programs',
        'contact': 'Contact RHU'
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
        } else {
          sidebar.classList.remove('-translate-x-full');
          sidebarOverlay.classList.remove('hidden');
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
    })();
  </script>
</body>
</html>

