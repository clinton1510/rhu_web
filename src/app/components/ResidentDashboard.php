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
$portalEvents = [];

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

            // 1. Process POST submissions directly into MySQL database
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
                } elseif ($formType === 'event_registration') {
                    $eventId = (int)($_POST['event_id'] ?? 0);
                    $eventCheck = $pdo->prepare("SELECT id FROM portal_events WHERE id = :id AND status = 'Scheduled' AND scheduled_date >= CURDATE()");
                    $eventCheck->execute(['id' => $eventId]);
                    if ($eventCheck->fetchColumn()) {
                        $ins = $pdo->prepare(
                            "INSERT INTO event_registrations (event_id, resident_id, status)
                             VALUES (:event_id, :resident_id, 'Pending')
                             ON DUPLICATE KEY UPDATE status = VALUES(status)"
                        );
                        $ins->execute(['event_id' => $eventId, 'resident_id' => $residentId]);
                        $_SESSION['resident_dashboard_message_flash'] = 'Your event registration was submitted for RHU confirmation.';
                    }
                    header('Location: ResidentDashboard.php?tab=events');
                    exit;
                }
            }

            // 2. Fetch live data for current resident
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
                "SELECT pe.*, er.status AS registration_status
                 FROM portal_events pe
                 LEFT JOIN event_registrations er ON er.event_id = pe.id AND er.resident_id = :resident_id
                 WHERE pe.status = 'Scheduled' AND pe.scheduled_date >= CURDATE()
                 ORDER BY pe.scheduled_date, pe.start_time"
            );
            $statement->execute(['resident_id' => $residentId]);
            $portalEvents = $statement->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $ex) {
        error_log("ResidentDashboard DB Hydration Error: " . $ex->getMessage());
    }
}
if ($resident) {
    if (isset($_GET['download']) && ctype_digit((string) $_GET['download'])) {
        $downloadId = (int) $_GET['download'];
        foreach ($consultations as $consultation) {
            if ((int) $consultation['id'] === $downloadId) {
                $filename = 'consultation-' . $downloadId . '.txt';
                header('Content-Type: text/plain; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
                echo "Consultation Summary\n";
                echo "Patient: " . ($resident['first_name'] ?? 'Resident') . " " . ($resident['last_name'] ?? '') . "\n";
                echo "Date: " . ($consultation['consultation_date'] ?? 'N/A') . "\n";
                echo "Physician: " . ($consultation['physician_name'] ?? 'N/A') . "\n";
                echo "Diagnosis: " . ($consultation['diagnosis'] ?? 'N/A') . "\n\n";
                echo "Medications: " . ($consultation['medications_prescribed'] ?? 'None') . "\n";
                echo "Notes: " . ($consultation['treatment_plan'] ?? 'No additional notes.') . "\n";
                exit;
            }
        }
    }
    if (isset($_GET['download_immunization'])) {
        $filename = 'immunization-card-' . ($resident['id'] ?? 'resident') . '.txt';
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        echo "Immunization Card\n";
        echo "Resident: " . ($resident['first_name'] ?? 'Resident') . " " . ($resident['last_name'] ?? '') . "\n";
        echo "PhilHealth No.: " . ($resident['philhealth_id'] ?? 'N/A') . "\n\n";
        foreach ($vaccinationRecords as $record) {
            echo "Vaccine: " . ($record['vaccine_name'] ?? 'Unknown') . "\n";
            echo "Date: " . ($record['vaccination_date'] ?? 'N/A') . "\n";
            echo "Provider: " . ($record['provider_name'] ?? 'N/A') . "\n";
            if (!empty($record['next_dose_date'])) {
                echo "Next Dose Due: " . $record['next_dose_date'] . "\n";
            }
            echo "---\n";
        }
        exit;
    }
}

if (!$resident && !empty($_SESSION['resident_registration'])) $resident = $_SESSION['resident_registration'];

$lastConsultation = $consultations[0] ?? null;
$currentYear = (int)date('Y');
$visitsThisYear = count(array_filter($consultations, fn($consultation) => !empty($consultation['consultation_date']) && (int)date('Y', strtotime($consultation['consultation_date'])) === $currentYear));
$totalPrescriptions = array_sum(array_map(fn($consultation) => empty($consultation['medications_prescribed']) ? 0 : count(array_filter(preg_split('/,\s*/', $consultation['medications_prescribed']))), $consultations));
$initials = strtoupper(substr($resident['first_name'] ?? 'R', 0, 1) . substr($resident['last_name'] ?? 'S', 0, 1));
$age = residentAge($resident['date_of_birth'] ?? null);
$tabs = ['home' => ['Home', '⌂'], 'records' => ['My Records', '▤'], 'immunization' => ['Immunization', '⌁'], 'certificates' => ['Certificates', '▧'], 'events' => ['Events & Programs', '▣'], 'contact' => ['Contact RHU', '☎']];
$events = [
    ['Jun 20', 'Blood Drive — City Hall', 'Free blood pressure check for all donors', 'red'],
    ['Jun 24', 'Free Cervical Cancer Screening', 'Halang Barangay Hall, 8AM–12NN', 'pink'],
    ['Jun 28', 'Senior Citizens Health Fair', 'Free ECG, blood glucose, BP monitoring', 'blue'],
    ['Jul 1–31', 'Nutrition Month (OPT+)', 'Free growth monitoring for 0–5 years', 'green'],
    ['Jul 10', 'Family Planning Counseling Day', 'RHU Main, free FP consultation', 'rose'],
    ['Jul 15', 'TB Awareness Seminar', 'Barangay Halang, 9AM', 'orange'],
    ['Aug 1', 'National Immunization Day', 'Free vaccines for children 0–5', 'indigo'],
];
$eventClasses = ['red' => 'border-red-200 bg-red-50', 'pink' => 'border-pink-200 bg-pink-50', 'blue' => 'border-blue-200 bg-blue-50', 'green' => 'border-green-200 bg-green-50', 'rose' => 'border-rose-200 bg-rose-50', 'orange' => 'border-orange-200 bg-orange-50', 'indigo' => 'border-indigo-200 bg-indigo-50'];
$dotClasses = ['red' => 'bg-red-500', 'pink' => 'bg-pink-500', 'blue' => 'bg-blue-500', 'green' => 'bg-green-500', 'rose' => 'bg-rose-500', 'orange' => 'bg-orange-500', 'indigo' => 'bg-indigo-500'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Resident Dashboard - RedPulse RHU</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50">
  <header class="sticky top-0 z-40 bg-gradient-to-r from-emerald-700 to-teal-700 text-white shadow-xl">
    <div class="px-4 py-3 sm:px-6"><div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-3"><div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-red-300">♥</div><div><h1 class="text-base font-bold">RedPulse RHU</h1><p class="text-xs text-emerald-200">Resident Health Portal</p></div></div>
      <div class="flex items-center gap-2">
        <div class="relative"><button type="button" data-notifications class="relative rounded-lg p-2 hover:bg-white/10" aria-label="Notifications">♢<span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-red-400"></span></button>
          <div data-notification-panel class="hidden absolute right-0 top-10 z-50 w-72 overflow-hidden rounded-xl border border-gray-100 bg-white text-gray-800 shadow-2xl"><div class="flex items-center justify-between border-b p-3"><p class="text-sm font-bold">Notifications</p><button type="button" data-close-notifications class="text-xs font-medium text-gray-500">Close</button></div><div class="border-b bg-emerald-50/60 p-3 text-xs"><p>Your hypertension follow-up is due on July 10, 2026.</p><p class="mt-1 text-gray-400">2 days ago</p></div><div class="border-b bg-emerald-50/60 p-3 text-xs"><p>Annual influenza vaccine due October 2026. Schedule now.</p><p class="mt-1 text-gray-400">1 week ago</p></div><div class="p-3 text-xs"><p>Medical certificate HC-2026-041 is ready for download.</p><p class="mt-1 text-gray-400">Jun 10</p></div></div>
        </div>
        <div class="flex items-center gap-2 rounded-lg bg-white/10 px-3 py-1.5"><div class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-300 text-xs font-bold text-emerald-900"><?= esc($initials) ?></div><span class="hidden text-sm font-semibold sm:block"><?= esc($resident['first_name'] ?? 'Resident') ?></span></div>
        <a href="ResidentDashboard.php?logout=1" class="rounded-lg p-2 text-sm hover:bg-white/10">Sign out</a>
      </div>
    </div></div>
    <nav class="hidden gap-1 overflow-x-auto px-4 pb-0.5 sm:flex"><?php foreach ($tabs as $id => [$label, $icon]): ?><button type="button" data-tab-button="<?= esc($id) ?>" class="whitespace-nowrap rounded-t-lg px-3 py-2 text-xs font-semibold text-emerald-100 hover:bg-white/10"><?= esc($icon) ?> <?= esc($label) ?></button><?php endforeach; ?></nav>
  </header>

  <main class="mx-auto w-full max-w-4xl space-y-4 px-3 py-4 pb-28 sm:px-4 sm:py-6 sm:pb-6">
    <?php if ($loadError): ?><div class="rounded-3xl border border-red-200 bg-red-50 p-8 text-center text-red-700"><p class="mb-2 font-semibold">Unable to load your data.</p><p><?= esc($loadError) ?></p></div><?php endif; ?>

    <section data-tab-panel="home" class="space-y-4 sm:space-y-5">
      <div class="rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 p-5 text-white"><div class="flex items-start justify-between"><div><p class="text-sm text-emerald-200">Good day,</p><h2 class="mt-0.5 text-2xl font-black"><?= esc(($resident['first_name'] ?? '') ? ($resident['first_name'] . ' ' . ($resident['last_name'] ?? '')) : 'Resident') ?></h2><div class="mt-3 flex flex-wrap gap-3 text-sm"><span class="rounded-full bg-white/20 px-3 py-1">⌖ <?= esc($resident['barangay'] ?? 'Unknown') ?></span><span class="rounded-full bg-white/20 px-3 py-1">♥ <?= esc($resident['blood_type'] ?? 'Unknown') ?></span><span class="rounded-full bg-white/20 px-3 py-1">♙ <?= $age === null ? '—' : esc($age) . ' y/o' ?></span></div></div><div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 text-3xl">♙</div></div><div class="mt-4 grid grid-cols-2 gap-3 border-t border-white/20 pt-4 text-xs"><div><p class="text-emerald-200">PhilHealth No.</p><p class="font-semibold"><?= esc($resident['philhealth_id'] ?? 'Not available') ?></p></div><div><p class="text-emerald-200">Patient ID</p><p class="font-semibold"><?= esc($resident['id'] ?? '—') ?></p></div></div></div>
      <div class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"><span class="text-amber-600">⚠</span><div><p class="text-sm font-bold text-amber-800">Health Reminders</p><ul class="mt-1 space-y-1 text-sm text-amber-700"><li>• Hypertension follow-up due: <strong>July 10, 2026</strong></li><li>• Annual influenza vaccine due: <strong>October 2026</strong></li></ul></div></div>
      <div><h3 class="mb-3 font-bold text-gray-900">Quick Access</h3><div class="grid grid-cols-2 gap-3 sm:grid-cols-3"><?php foreach ([['My Consultations','records','bg-blue-600','⚕'],['Immunization Card','immunization','bg-indigo-600','⌁'],['My Certificates','certificates','bg-green-600','▧'],['Health Events','events','bg-rose-600','▣'],['Contact RHU','contact','bg-teal-600','☎'],['Health Tips','home','bg-purple-600','♥']] as [$label, $target, $color, $icon]): ?><button type="button" data-tab-link="<?= esc($target) ?>" class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"><span class="flex h-9 w-9 items-center justify-center rounded-lg <?= esc($color) ?> text-white"><?= esc($icon) ?></span><span class="text-sm font-semibold leading-tight text-gray-700"><?= esc($label) ?></span></button><?php endforeach; ?></div></div>
      <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5"><div class="mb-3 flex items-center justify-between"><h3 class="font-bold text-gray-900">Last Visit</h3><button type="button" data-tab-link="records" class="text-xs text-emerald-600 hover:underline">View all →</button></div><div class="rounded-lg bg-gray-50 p-4"><div class="flex items-start justify-between"><div><p class="font-bold text-gray-900"><?= esc($lastConsultation['diagnosis'] ?? 'No consultations yet') ?></p><p class="mt-1 text-sm text-gray-500"><?= esc(($lastConsultation['physician_name'] ?? '') ?: 'No physician assigned') ?></p><?php if (!empty($lastConsultation['medications_prescribed'])): ?><div class="mt-2 flex flex-wrap gap-1"><?php foreach (array_filter(preg_split('/,\s*/', $lastConsultation['medications_prescribed'])) as $medication): ?><span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700"><?= esc($medication) ?></span><?php endforeach; ?></div><?php endif; ?></div><span class="ml-3 whitespace-nowrap font-mono text-xs text-gray-400"><?= esc($lastConsultation['consultation_date'] ?? '—') ?></span></div><?php if (!empty($lastConsultation['follow_up_date'])): ?><div class="mt-3 border-t border-gray-200 pt-3 text-sm text-gray-600">◷ Next visit: <strong class="text-gray-900"><?= esc($lastConsultation['follow_up_date']) ?></strong></div><?php endif; ?></div></div>
      <div><h3 class="mb-3 font-bold text-gray-900">★ Health Tips</h3><div class="space-y-3"><div class="flex gap-3 rounded-xl bg-red-50 p-4 text-red-700">♥<p class="text-sm">Monitor your blood pressure regularly. Hypertension has no symptoms but can lead to stroke.</p></div><div class="flex gap-3 rounded-xl bg-blue-50 p-4 text-blue-700">⚕<p class="text-sm">At least 150 minutes of moderate physical activity per week reduces chronic disease risk by 30%.</p></div><div class="flex gap-3 rounded-xl bg-indigo-50 p-4 text-indigo-700">♜<p class="text-sm">Stay up-to-date with your vaccinations. Annual flu vaccine is recommended for adults 18+.</p></div></div></div>
    </section>

    <section data-tab-panel="records" class="hidden space-y-4 sm:space-y-5"><div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-base font-bold text-gray-900 sm:text-xl">▤ My Health Records</h2><button type="button" data-scroll-to="appointment-request" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm">+ Book OPD Appointment</button></div><div class="grid grid-cols-3 gap-3"><?php foreach ([['Total Visits',count($consultations),'text-blue-600'],['This Year',$visitsThisYear,'text-emerald-600'],['Prescriptions',$totalPrescriptions,'text-purple-600']] as [$label, $value, $color]): ?><div class="rounded-xl border border-gray-100 bg-white p-3 text-center shadow-sm sm:p-4"><p class="text-2xl font-black <?= esc($color) ?>"><?= esc($value) ?></p><p class="mt-0.5 text-xs font-semibold text-gray-500"><?= esc($label) ?></p></div><?php endforeach; ?></div><div class="space-y-3"><?php if (!$consultations): ?><div class="rounded-xl border border-gray-100 bg-white p-5 text-center text-gray-500 shadow-sm">No consultations recorded yet. Your past visits will appear here once they are available.</div><?php else: foreach ($consultations as $index => $consultation): $medications = array_filter(preg_split('/,\s*/', $consultation['medications_prescribed'] ?? '')); ?><article class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm"><div class="flex flex-wrap items-start justify-between gap-2"><div class="flex items-start gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 text-blue-600">⚕</span><div><p class="font-bold text-gray-900"><?= esc($consultation['diagnosis'] ?? 'Consultation details') ?></p><p class="text-sm text-gray-500"><?= esc($consultation['physician_name'] ?: 'Physician not available') ?></p></div></div><div class="text-right"><p class="font-mono text-xs text-gray-400"><?= esc($consultation['consultation_date'] ?? '—') ?></p><?php if (!empty($consultation['treatment_plan'])): ?><span class="mt-1 inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">Treatment plan</span><?php endif; ?></div></div><div class="mt-3 border-t border-gray-100 pt-3"><p class="mb-1.5 text-xs font-semibold text-gray-500">Medications prescribed:</p><div class="flex flex-wrap gap-1"><?php if ($medications): foreach ($medications as $medication): ?><span class="rounded-full border border-blue-100 bg-blue-50 px-2 py-0.5 text-xs text-blue-700"><?= esc($medication) ?></span><?php endforeach; else: ?><span class="text-xs text-gray-400">None recorded</span><?php endif; ?></div></div><div class="mt-2 flex items-center justify-between text-xs text-gray-400"><span class="font-mono"><?= esc($consultation['id'] ?? 'CONS-' . ($index + 1)) ?></span><a href="ResidentDashboard.php?tab=records&download=<?= (int) ($consultation['id'] ?? 0) ?>" class="font-semibold text-blue-600 hover:underline">⇩ Download</a></div></article><?php endforeach; endif; ?></div>

    <div id="appointment-request" class="rounded-xl border border-blue-200 bg-blue-50/70 p-5 space-y-3">
        <p class="text-sm font-bold text-blue-900 flex items-center gap-2">📅 Request an OPD Consultation Appointment</p>
        <form method="post" action="ResidentDashboard.php?tab=records" class="space-y-3 text-xs">
            <input type="hidden" name="form" value="appointment_request">
            <div>
                <label class="block font-bold text-gray-700 mb-1">Chief Health Complaint / Reason for Visit *</label>
                <input required name="chief_complaint" placeholder="e.g., Follow-up consultation for blood pressure / Cough for 3 days" class="w-full p-2.5 bg-white border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block font-bold text-gray-700 mb-1">Preferred Appointment Date *</label>
                <input type="date" required name="preferred_date" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" min="<?php echo date('Y-m-d'); ?>" class="w-full p-2.5 bg-white border border-gray-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="w-full py-2.5 bg-blue-600 text-white font-bold rounded-lg text-sm hover:bg-blue-700 shadow-md">Submit Appointment Request to RHU Doctor</button>
        </form>
    </div>
    </section>

    <section data-tab-panel="immunization" class="hidden space-y-4 sm:space-y-5"><h2 class="text-base font-bold text-gray-900 sm:text-xl">⌁ Immunization Record</h2><div class="flex gap-3 rounded-xl border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-700">✓<p>Your immunization records are verified by <strong>Nasugbu Rural Health Unit I</strong>. Present this screen or download your card at the RHU office.</p></div><div class="space-y-3"><?php if (!$vaccinationRecords): ?><div class="rounded-xl border border-gray-100 bg-white p-5 text-center text-gray-500 shadow-sm">No vaccination records found. Your immunization history will appear here once available.</div><?php else: foreach ($vaccinationRecords as $record): $upToDate = !empty($record['next_dose_date']); ?><article class="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-4 shadow-sm"><span class="flex h-10 w-10 items-center justify-center rounded-xl <?= $upToDate ? 'bg-indigo-100 text-indigo-600' : 'bg-green-100 text-green-600' ?>">♜</span><div class="min-w-0 flex-1"><p class="text-sm font-bold text-gray-900"><?= esc($record['vaccine_name']) ?></p><p class="mt-0.5 text-xs text-gray-500">Last given: <?= esc($record['vaccination_date'] ?? '—') ?></p><?php if ($upToDate): ?><p class="mt-0.5 text-xs text-emerald-700">Next due: <strong><?= esc($record['next_dose_date']) ?></strong></p><?php endif; ?><?php if (!empty($record['provider_name'])): ?><p class="mt-0.5 text-xs text-gray-500">Provided by: <?= esc($record['provider_name']) ?></p><?php endif; ?></div><span class="rounded-full px-2 py-1 text-xs font-bold <?= $upToDate ? 'bg-indigo-100 text-indigo-700' : 'bg-green-100 text-green-700' ?>"><?= $upToDate ? 'Up to date' : 'Complete' ?></span></article><?php endforeach; endif; ?></div><div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5"><h3 class="mb-3 font-bold text-gray-900">ⓘ Recommended for Adults 36+</h3><div class="space-y-2 text-sm text-gray-600"><?php foreach (['Annual Influenza Vaccine','Pneumococcal vaccine every 5 years','Td booster every 10 years','Hepatitis B (if not previously vaccinated)','COVID-19 booster (per DOH schedule)'] as $recommendation): ?><p>› <?= esc($recommendation) ?></p><?php endforeach; ?></div></div><a href="ResidentDashboard.php?tab=immunization&download_immunization=1" class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 py-3 font-semibold text-white hover:bg-indigo-700">⇩ Download Immunization Card</a></section>

    <section data-tab-panel="certificates" class="hidden space-y-4 sm:space-y-5"><div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-base font-bold text-gray-900 sm:text-xl">▧ My Certificates</h2><button type="button" data-scroll-to="certificate-request" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">+ Request New</button></div><?php if ($certificateSuccess): ?><div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700"><?= esc($certificateSuccess) ?></div><?php endif; ?><?php if ($certificateErrors): ?><div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?php foreach ($certificateErrors as $error): ?><p><?= esc($error) ?></p><?php endforeach; ?></div><?php endif; ?><?php if (!$certificates): ?><div class="rounded-xl border border-gray-100 bg-white p-5 text-center text-gray-500 shadow-sm">No certificate records are available yet for this account.</div><?php else: ?><div class="space-y-3"><?php foreach ($certificates as $certificate): ?><div class="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 shadow-sm"><div><p class="font-bold text-gray-900"><?= esc($certificate['certificate_type_name'] ?? 'Health Certificate') ?></p><p class="text-xs text-gray-500">Issued <?= esc($certificate['issue_date']) ?> · <?= esc($certificate['certificate_number'] ?? 'Pending number') ?></p></div><span class="rounded-full bg-green-100 px-2 py-1 text-xs font-bold text-green-700"><?= esc($certificate['validity_status'] ?? 'Issued') ?></span></div><?php endforeach; ?></div><?php endif; ?><div id="certificate-request" class="rounded-xl border border-gray-200 bg-gray-50 p-4"><p class="mb-2 text-sm font-bold text-gray-700">Request a Certificate</p><form method="post" action="ResidentDashboard.php?tab=certificates" class="grid grid-cols-2 gap-2 text-xs" id="certificate-request-form"><input type="hidden" name="form" value="certificate_request"><?php foreach (['Medical Certificate (₱50)','Health Certificate (₱100)','Barangay Health Cert (₱100)','Certificate of Live Birth (FREE)'] as $certificateType): ?><button type="submit" name="certificate_type" value="<?= esc($certificateType) ?>" class="rounded-lg border border-gray-200 bg-white p-2.5 text-left font-semibold text-gray-700 hover:border-green-400 hover:bg-green-50"><?= esc($certificateType) ?></button><?php endforeach; ?></form></div></section>

    <section data-tab-panel="events" class="hidden space-y-4 sm:space-y-5">
      <h2 class="text-base font-bold text-gray-900 sm:text-xl">RHU Events &amp; Health Programs</h2>
      <div class="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700"><p>All events are <strong>FREE</strong> for registered residents of Nasugbu. Bring a valid ID and your PhilHealth card.</p></div>
      <div class="space-y-3">
        <?php if (!$portalEvents): ?><p class="rounded-xl bg-white p-5 text-center text-sm text-gray-500">No upcoming events are currently scheduled.</p><?php endif; ?>
        <?php foreach ($portalEvents as $event): ?>
          <article class="flex items-start gap-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-indigo-500"></span>
            <div class="flex-1">
              <div class="flex flex-wrap items-center gap-2"><span class="rounded border border-gray-200 bg-white px-2 py-0.5 font-mono text-xs text-gray-500"><?= esc($event['event_date']) ?></span><span class="text-sm font-bold text-gray-900"><?= esc($event['title']) ?></span></div>
              <p class="mt-1 text-xs text-gray-600"><?= esc($event['description'] ?? '') ?> · <?= esc($event['venue']) ?></p>
            </div>
            <?php if (!empty($event['registration_status'])): ?>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-indigo-700"><?= esc($event['registration_status']) ?></span>
            <?php else: ?>
              <form method="post" action="ResidentDashboard.php?tab=events">
                <input type="hidden" name="form" value="event_registration">
                <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
                <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white">Register</button>
              </form>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section data-tab-panel="contact" class="hidden space-y-4 sm:space-y-5"><h2 class="text-base font-bold text-gray-900 sm:text-xl">☎ Contact the RHU</h2><div class="space-y-3 rounded-xl border border-gray-100 bg-white p-5 shadow-sm sm:space-y-4"><h3 class="font-bold text-gray-900">Nasugbu Rural Health Unit I</h3><?php foreach ([['⌖','Address','Poblacion, Nasugbu, Batangas'],['☎','Contact','(043) 416-1234'],['◷','Hours','Mon–Fri: 8:00 AM – 5:00 PM'],['♙','Municipal Health Officer','Dr. Chedric Bascoguin']] as [$icon, $label, $value]): ?><div class="flex gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50 text-teal-600"><?= esc($icon) ?></span><div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500"><?= esc($label) ?></p><p class="mt-0.5 text-sm font-medium text-gray-800"><?= esc($value) ?></p></div></div><?php endforeach; ?></div><div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm"><h3 class="mb-3 font-bold text-gray-900">Send a Message to RHU Staff</h3><?php if ($contactSuccess): ?><div class="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700"><?= esc($contactSuccess) ?></div><?php endif; ?><?php if ($contactErrors): ?><div class="mb-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?php foreach ($contactErrors as $error): ?><p><?= esc($error) ?></p><?php endforeach; ?></div><?php endif; ?><form method="post" action="ResidentDashboard.php?tab=contact" class="space-y-3"><input type="hidden" name="form" value="contact"><label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Message Type</label><select name="subject" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm"><option value="General Inquiry">General Inquiry</option><option value="Appointment Request">Appointment Request</option><option value="Certificate Request">Certificate Request</option><option value="Health Concern">Health Concern</option><option value="Feedback / Complaint">Feedback / Complaint</option></select><label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Message</label><textarea name="message" rows="4" class="w-full resize-none rounded-lg border border-gray-300 px-3 py-2.5 text-sm" placeholder="Type your message here..."></textarea><button type="submit" class="w-full rounded-lg bg-teal-600 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 shadow-md">Send Message to RHU Staff</button></form></div>

    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm space-y-3">
        <h3 class="font-bold text-gray-900 text-sm">Your Sent Messages & Staff Replies</h3>
        <?php if (empty($residentMessages)): ?>
            <p class="text-xs text-gray-500">No messages sent yet. Messages you send above will be dispatched directly to RHU Staff and Admin dashboards.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($residentMessages as $msg): ?>
                    <div class="p-3.5 rounded-xl border border-gray-100 bg-gray-50 space-y-1.5">
                        <div class="flex justify-between items-center text-xs">
                            <span class="font-bold text-gray-900"><?php echo esc($msg['subject']); ?></span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo $msg['status'] === 'Replied' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-amber-100 text-amber-700 border border-amber-200'; ?>">
                                <?php echo esc($msg['status']); ?>
                            </span>
                        </div>
                        <p class="text-xs text-gray-700 leading-relaxed"><?php echo esc($msg['message']); ?></p>
                        <p class="text-[10px] text-gray-400"><?php echo esc(date('M d, Y h:i A', strtotime($msg['created_at']))); ?></p>
                        <?php if (!empty($msg['admin_reply'])): ?>
                            <div class="mt-2 p-2.5 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-900 space-y-1">
                                <p class="font-bold flex items-center gap-1 text-blue-800">💬 RHU Staff Official Response:</p>
                                <p class="text-blue-950 font-medium"><?php echo esc($msg['admin_reply']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    </section>
  </main>

  <nav class="fixed bottom-0 left-0 right-0 z-50 border-t border-gray-200 bg-white sm:hidden"><div class="flex"><?php foreach ($tabs as $id => [$label, $icon]): ?><button type="button" data-tab-button="<?= esc($id) ?>" class="flex flex-1 flex-col items-center gap-0.5 py-2 text-gray-400"><span class="text-base"><?= esc($icon) ?></span><span class="w-full truncate px-0.5 text-center text-[10px] font-semibold"><?= esc($label) ?></span></button><?php endforeach; ?></div></nav>
  <script>
    (() => {
      const buttons = document.querySelectorAll('[data-tab-button]');
      const panels = document.querySelectorAll('[data-tab-panel]');
      const setTab = (tab) => {
        panels.forEach(panel => panel.classList.toggle('hidden', panel.dataset.tabPanel !== tab));
        buttons.forEach(button => {
          const active = button.dataset.tabButton === tab;
          button.classList.toggle('bg-white', active && !button.closest('nav.sm\\:hidden'));
          button.classList.toggle('text-emerald-700', active);
          button.classList.toggle('text-emerald-100', !active && !button.closest('nav.sm\\:hidden'));
          button.classList.toggle('text-emerald-600', active && !!button.closest('nav.sm\\:hidden'));
          button.classList.toggle('text-gray-400', !active && !!button.closest('nav.sm\\:hidden'));
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
      };

      buttons.forEach(button => button.addEventListener('click', () => setTab(button.dataset.tabButton)));
      document.querySelectorAll('[data-tab-link]').forEach(button => button.addEventListener('click', () => setTab(button.dataset.tabLink)));
      document.querySelectorAll('[data-scroll-to]').forEach(button => button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.scrollTo);
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }));
      document.querySelectorAll('[data-event-action]').forEach(button => button.addEventListener('click', () => {
        const title = button.dataset.eventTitle || 'Event';
        const date = button.dataset.eventDate || '';
        alert(`${title}\n${date}`);
      }));

      const urlParams = new URLSearchParams(window.location.search);
      const initialTab = urlParams.get('tab');
      if (initialTab && Array.from(buttons).some(button => button.dataset.tabButton === initialTab)) {
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
