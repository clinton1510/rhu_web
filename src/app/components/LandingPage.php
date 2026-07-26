<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/portal.php';

function e(mixed $v): string {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function initials(string $name): string {
    $clean = preg_replace('/^(Hon\. |Dr\. |Midwife |RN )/', '', $name);
    $parts = array_values(array_filter(explode(' ', trim($clean))));
    $first = $parts[0][0] ?? '';
    $last = isset($parts[count($parts)-1]) && count($parts) > 1 ? $parts[count($parts)-1][0] : '';
    return strtoupper($first . $last);
}

// Fetch Admin Announcements, Health Events & Photo Gallery live from database / portal settings
$dbAnnouncements = getPortalAnnouncements($pdo);
$dbEvents = getPortalEvents($pdo);
$eventGallery = getPortalEventGallery($pdo);

$topAlert = !empty($dbAnnouncements) ? $dbAnnouncements[0] : null;

// Fetch all Barangays dynamically from the database `barangays` table
$dbBarangays = getPortalBarangays($pdo);

// Fetch dynamic database counts
$dbBarangayCount = 42;
$dbTotalPopulation = 48230;
$dbHealthProgramsCount = '23+';

try {
    $bgyRes = (int) $pdo->query("SELECT COUNT(*) FROM barangays")->fetchColumn();
    if ($bgyRes > 0) $dbBarangayCount = $bgyRes;
} catch (Throwable $e) {}

try {
    $popRes = (int) $pdo->query("SELECT SUM(population) FROM barangays")->fetchColumn();
    if ($popRes > 0) $dbTotalPopulation = $popRes;
} catch (Throwable $e) {}

try {
    $diseaseTypesCount = (int) $pdo->query("SELECT COUNT(*) FROM disease_types")->fetchColumn();
    $certTypesCount = (int) $pdo->query("SELECT COUNT(*) FROM certificate_types")->fetchColumn();
    $immCount = (int) $pdo->query("SELECT COUNT(*) FROM immunization_schedules")->fetchColumn();
    $totalProg = $diseaseTypesCount + $certTypesCount + $immCount;
    if ($totalProg > 0) $dbHealthProgramsCount = $totalProg . '+';
} catch (Throwable $e) {}

$rhu = [
    'name' => 'Nasugbu Rural Health Unit I',
    'municipality' => 'Nasugbu',
    'province' => 'Batangas',
    'address' => 'RHU Nasugbu, J.P. Laurel St, Poblacion, Nasugbu, Batangas',
    'contact' => '(043) 416-1234',
    'emergency' => '(043) 416-9999',
    'hours' => 'Mon–Fri: 8:00 AM – 5:00 PM',
    'mho' => 'Dr. Rosalinda V. Castillo',
    'population' => $dbTotalPopulation,
    'barangay_count' => $dbBarangayCount,
    'programs_count' => $dbHealthProgramsCount,
    'barangays' => !empty($dbBarangays) ? $dbBarangays : ['Aga','Balaytigue','Banilad','Barangay 1 (Pob.)','Barangay 2 (Pob.)','Barangay 3 (Pob.)','Barangay 4 (Pob.)','Bilaran','Bucana','Bulihan','Calayo','Catandaan','Dayap','Kaylaway','Looc','Lumbangan','Natipuan','Pantalan','Wawa']
];

$services = [
    [
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L5.6 15.11a2 2 0 01-1.183-1.84V5.05a2 2 0 011.183-1.84l2.386-.477a6 6 0 013.86.517l.318.158a6 6 0 003.86.517l2.387-.477A2 2 0 0120 4.73v9.698a2 2 0 01-.572 1.4z"/></svg>',
        'label' => 'OPD Consultations',
        'schedule' => 'Mon–Fri, 8:00 AM – 5:00 PM',
        'desc' => 'General physical examinations, medical checkups, diagnosis, treatment, and referral services.',
        'color' => 'bg-emerald-50 text-emerald-600 border-emerald-100'
    ],
    [
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>',
        'label' => 'Maternal & Child Care',
        'schedule' => 'Prenatal, Delivery & Postpartum',
        'desc' => 'Comprehensive maternal health monitoring, safe delivery care, and newborn wellness visits.',
        'color' => 'bg-rose-50 text-rose-600 border-rose-100'
    ],
    [
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
        'label' => 'Child Immunization (EPI)',
        'schedule' => 'Wed & Fri, 8:00 AM – 12:00 NN',
        'desc' => 'Free routine vaccines for infants and young children under the Expanded Program on Immunization.',
        'color' => 'bg-indigo-50 text-indigo-600 border-indigo-100'
    ],
    [
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
        'label' => 'Family Planning',
        'schedule' => 'Tuesdays, 8:00 AM – 12:00 NN',
        'desc' => 'Reproductive health counseling, contraceptives, and family planning guidance.',
        'color' => 'bg-pink-50 text-pink-600 border-pink-100'
    ],
    [
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L5.6 15.11a2 2 0 01-1.183-1.84V5.05a2 2 0 011.183-1.84l2.386-.477a6 6 0 013.86.517l.318.158a6 6 0 003.86.517l2.387-.477A2 2 0 0120 4.73v9.698a2 2 0 01-.572 1.4z"/></svg>',
        'label' => 'TB-DOTS Program',
        'schedule' => 'Daily Medicine Dispensing',
        'desc' => 'Directly Observed Therapy Short-Course for Tuberculosis testing, diagnosis, and free treatment.',
        'color' => 'bg-amber-50 text-amber-600 border-amber-100'
    ],
    [
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
        'label' => 'Disease Surveillance',
        'schedule' => '24/7 PIDSR Response',
        'desc' => 'Philippine Integrated Disease Surveillance & Response monitoring for community outbreak prevention.',
        'color' => 'bg-red-50 text-red-600 border-red-100'
    ],
    [
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
        'label' => 'Health Certificates',
        'schedule' => 'Mon–Fri, 8:00 AM – 3:00 PM',
        'desc' => 'Official medical certificates, sanitary clearances, pre-employment health checks, and birth records.',
        'color' => 'bg-teal-50 text-teal-600 border-teal-100'
    ],
    [
        'icon' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>',
        'label' => 'Sanitation Inspection',
        'schedule' => 'Field Inspections Daily',
        'desc' => 'Environmental health audits, water safety monitoring, food establishment clearance inspections.',
        'color' => 'bg-cyan-50 text-cyan-600 border-cyan-100'
    ]
];

$events = [
    ['Jun 20', 'Blood Donation Drive', 'Municipal Hall Quadrangle · 8:00 AM', 'Free BP, hemoglobin screening & donor meal kit', 'bg-red-500', 'https://images.unsplash.com/photo-1615461066841-6116e61058f4?auto=format&fit=crop&w=800&q=80'],
    ['Jun 24', 'Free Cervical Cancer Screening', 'Halang Barangay Health Station · 8:00 AM', 'Free VIA testing & doctor consultation for women ages 21–65', 'bg-pink-500', 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800&q=80'],
    ['Jun 28', 'Senior Citizens Wellness Day', 'RHU Covered Court · 8:00 AM', 'Free ECG, blood sugar test, lipid profile & maintenance meds check', 'bg-emerald-500', 'https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?auto=format&fit=crop&w=800&q=80'],
    ['Jul 01', 'Nutrition Month OPT+ Drive', 'All 8 Covered Barangays', 'Operation Timbang Plus for infants & young children (0–5 yrs)', 'bg-teal-500', 'https://images.unsplash.com/photo-1631815588090-d4bfec5b1cdb?auto=format&fit=crop&w=800&q=80'],
    ['Jul 10', 'Family Planning & Wellness Fair', 'RHU Main Clinic · 8:00 AM', 'Free FP counseling, subdermal implant, & health lectures', 'bg-purple-500', 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?auto=format&fit=crop&w=800&q=80'],
    ['Aug 01', 'National Child Immunization Day', 'RHU Main & Barangay Clinics', 'Free catch-up vaccines for Measles, Polio & Pentavalent', 'bg-blue-500', 'https://images.unsplash.com/photo-1631815588090-d4bfec5b1cdb?auto=format&fit=crop&w=800&q=80']
];

if (!empty($dbEvents)) {
    $formattedEvents = [];
    foreach ($dbEvents as $ev) {
        $formattedEvents[] = [
            $ev['event_date'],
            $ev['title'],
            $ev['venue'],
            $ev['description'],
            $ev['badge_color'] ?: 'bg-emerald-500',
            $ev['image_url'] ?? ''
        ];
    }
    $events = $formattedEvents;
}

// Fetch Municipal Officials from Admin Portal Settings (no extra DB table needed)
$portalSet = portalSettings($pdo);
$officials = [];
if (!empty($portalSet['rhu_municipal_officials'])) {
    $decodedOff = json_decode($portalSet['rhu_municipal_officials'], true);
    if (is_array($decodedOff) && count($decodedOff) > 0) {
        foreach ($decodedOff as $off) {
            if (!empty($off['name'])) {
                $officials[] = [$off['name'], $off['position'] ?? 'Municipal Official', $off['office'] ?? 'LGU Nasugbu'];
            }
        }
    }
}
if (empty($officials)) {
    $officials = [
        ['Hon. Antonio Jose A. Barcelon', 'Municipal Mayor', 'Office of the Municipal Mayor'],
        ['Hon. Larry D. Albanio', 'Municipal Vice Mayor', 'Office of the Municipal Vice Mayor'],
        ['Hon. Arlene R. Chuidian', 'Municipal Councilor', 'Committee Chair on Health & Sanitation'],
        ['Hon. Marcos H. Guevarra', 'Municipal Councilor', 'Committee on Women & Family Services'],
        ['Hon. Anne Linette A. Vivas', 'Municipal Councilor', 'Committee on Finance & Budget'],
        ['Hon. Dionisio B. Botobara', 'Municipal Councilor', 'Committee on Social Welfare']
    ];
}

// Fetch Healthcare Staff directly from Database `staff` table
$staff = [];
if (!empty($pdo)) {
    try {
        $sStmt = $pdo->query("
            SELECT s.id, u.first_name, u.last_name, u.email, s.staff_type, s.specialization, s.phone_number, s.is_active, s.date_hired
            FROM staff s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.is_active = 1
            ORDER BY s.id ASC LIMIT 12
        ");
        $fetchedStaff = $sStmt ? $sStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if (!empty($fetchedStaff)) {
            foreach ($fetchedStaff as $st) {
                $fullName = trim(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? '')) ?: 'RHU Healthcare Professional';
                $pos = $st['staff_type'] ?? 'Health Personnel';
                if ($pos === 'ADMIN_STAFF') $pos = 'RHU Administrator';
                elseif ($pos === 'PHYSICIAN') $pos = 'Rural Health Physician';
                elseif ($pos === 'NURSE') $pos = 'Public Health Nurse';
                elseif ($pos === 'MIDWIFE') $pos = 'Rural Health Midwife';
                elseif ($pos === 'BHW') $pos = 'Barangay Health Worker';
                elseif ($pos === 'MEDTECH') $pos = 'Medical Technologist';
                elseif ($pos === 'SANITARY') $pos = 'Sanitary Inspector';

                $spec = $st['specialization'] ?: 'Public Health & Primary Care';
                $hired = !empty($st['date_hired']) ? 'Plantilla (Hired ' . date('Y', strtotime($st['date_hired'])) . ')' : 'Plantilla';

                $staff[] = [$fullName, $pos, $spec, $hired, 'Active'];
            }
        }
    } catch (Exception $e) {
        error_log("LandingPage Staff Fetch Error: " . $e->getMessage());
    }
}
if (empty($staff)) {
    $staff = [
        ['Dr. Maria C. Santos', 'Municipal Health Officer (MHO)', 'Public Health & Medicine', 'Plantilla', 'Active'],
        ['Dr. Joseph T. Ramos', 'Rural Health Physician', 'General Practice & OPD', 'Plantilla', 'Active'],
        ['Midwife Rosario Peralta', 'Rural Health Midwife', 'Maternal & Neonatal Care', 'Plantilla', 'Active'],
        ['RN Clara Mendez', 'Public Health Nurse I', 'Immunization & OPD', 'Plantilla', 'Active'],
        ['RN Jose Figueroa', 'Public Health Nurse II', 'Disease Surveillance & EPI', 'Contractual', 'Active'],
        ['Ramon Villareal', 'Sanitary Inspector', 'Environmental & Sanitation Health', 'Plantilla', 'Active']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'contact') {
    $_SESSION['landing_messages'][] = [
        'name' => trim($_POST['name'] ?? ''),
        'contact' => trim($_POST['contact'] ?? ''),
        'subject' => $_POST['subject'] ?? 'General Inquiry',
        'message' => trim($_POST['message'] ?? ''),
        'at' => date('Y-m-d H:i:s')
    ];
    $_SESSION['landing_flash'] = 'Thank you! Your message has been sent to the RHU administrative desk.';
    header('Location: ?contact_success=1#contact');
    exit;
}

$flash = $_SESSION['landing_flash'] ?? '';
unset($_SESSION['landing_flash']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ResiHUnity RHU | Nasugbu Rural Health Unit I</title>
  <meta name="description" content="Official digital health portal of Nasugbu Rural Health Unit I. Access free medical consultations, immunization cards, health certificates, and public health updates.">
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', 'sans-serif'],
          },
          colors: {
            brand: {
              50: '#ecfdf5',
              100: '#d1fae5',
              500: '#10b981',
              600: '#059669',
              700: '#047857',
              800: '#065f46',
              900: '#064e3b',
            }
          }
        }
      }
    }
  </script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased selection:bg-brand-500 selection:text-white">

  <!-- Emergency Announcement Ticker Bar -->
  <div class="bg-gradient-to-r from-red-600 via-rose-600 to-red-700 text-white py-2.5 px-4 text-xs sm:text-sm font-medium shadow-sm">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
      <div class="flex items-center gap-2 text-center sm:text-left">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-white/20 text-white font-bold text-xs uppercase tracking-wide">
          <svg class="w-3.5 h-3.5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <?= e($topAlert['badge_text'] ?? $topAlert['category'] ?? 'Health Alert') ?>
        </span>
        <span>
          <?php if ($topAlert): ?>
            <b><?= e($topAlert['title']) ?>:</b> <?= e($topAlert['content']) ?>
          <?php else: ?>
            <b>Dengue Awareness Notice:</b> Active monitoring in Brgy. Halang & Mabini. Maintain clean surroundings & eliminate stagnant water.
          <?php endif; ?>
        </span>
      </div>
      <div class="flex items-center gap-3 text-xs shrink-0">
        <span>RHU Direct Line: <b><?= e($rhu['contact']) ?></b></span>
        <span class="hidden md:inline">|</span>
        <span class="hidden md:inline">Emergency: <b><?= e($rhu['emergency']) ?></b></span>
      </div>
    </div>
  </div>

  <!-- Main Navigation Header -->
  <header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-sm transition-colors duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-20">
        
        <!-- Logo & Branding -->
        <a href="#top" class="flex items-center gap-2.5 sm:gap-3 group">
          <img src="resihunity_logo.jpg" alt="ResiHUnity Logo" class="h-10 sm:h-12 w-auto object-contain rounded-xl shadow-md group-hover:scale-105 transition-transform" />
          <div>
            <span class="text-lg sm:text-xl font-extrabold tracking-tight text-slate-900 block leading-none">ResiHUnity <span class="text-brand-600">RHU</span></span>
            <span class="text-[11px] sm:text-xs text-slate-500 font-medium block mt-0.5"><?= e($rhu['name']) ?></span>
          </div>
        </a>

        <!-- Desktop Navigation Links -->
        <nav class="hidden lg:flex items-center gap-8 font-medium text-sm text-slate-600">
          <a href="#services" class="hover:text-brand-600 transition-colors">Services</a>
          <a href="#events" class="hover:text-brand-600 transition-colors">Health Events</a>
          <a href="#how-it-works" class="hover:text-brand-600 transition-colors">Portal Guide</a>
          <a href="#map" class="hover:text-brand-600 transition-colors flex items-center gap-1 font-semibold text-emerald-600">
            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            RHU Map
          </a>
          <a href="#officials" class="hover:text-brand-600 transition-colors">Officials & Staff</a>
          <a href="#contact" class="hover:text-brand-600 transition-colors">Contact</a>
        </nav>

        <!-- Access Actions & Portals -->
        <div class="hidden sm:flex items-center gap-3">
          <a href="ResidentLogin.php" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:border-brand-500 hover:text-brand-600 font-semibold text-sm transition-all">
            Resident Login
          </a>
          <a href="ResidentRegistration.php" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-brand-600 to-teal-600 text-white font-semibold text-sm shadow-md shadow-brand-600/20 hover:opacity-95 hover:shadow-lg transition-all">
            Register Free
          </a>
          
          <!-- Staff Portal Dropdown Trigger -->
          <div class="relative group">
            <button class="p-2.5 rounded-xl border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors" title="Staff Portal Access">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </button>
            <div class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-slate-100 p-2 hidden group-hover:block z-50">
              <span class="block px-3 py-1.5 text-xs font-bold text-slate-400 uppercase tracking-wider">Staff Portals</span>
              <a href="RHULogin.php" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700">RHU Healthcare Staff</a>
              <a href="BHWLogin.php" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700">Barangay Health Worker</a>
              <a href="RHUAdminLogin.php" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700">System Administrator</a>
            </div>
          </div>
        </div>

        <!-- Mobile Menu Toggle Button -->
        <button id="mobile-menu-btn" onclick="toggleMobileMenu()" class="lg:hidden p-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-100">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
      </div>
    </div>

    <!-- Mobile Overlay Drawer Navigation -->
    <div id="mobile-drawer" class="hidden lg:hidden border-t border-slate-200/80 bg-white/95 backdrop-blur-xl px-5 pt-4 pb-6 space-y-4 shadow-2xl transition-all">
      <nav class="flex flex-col space-y-2 font-semibold text-slate-800 text-sm">
        <a href="#services" onclick="toggleMobileMenu()" class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-100 transition-colors">
          <span class="p-2 rounded-lg bg-emerald-50 text-emerald-600">🩺</span> Services
        </a>
        <a href="#events" onclick="toggleMobileMenu()" class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-100 transition-colors">
          <span class="p-2 rounded-lg bg-blue-50 text-blue-600">📅</span> Health Events
        </a>
        <a href="#how-it-works" onclick="toggleMobileMenu()" class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-100 transition-colors">
          <span class="p-2 rounded-lg bg-purple-50 text-purple-600">📖</span> Portal Guide
        </a>
        <a href="#map" onclick="toggleMobileMenu()" class="flex items-center gap-3 p-2.5 rounded-xl bg-emerald-50 text-emerald-700 font-bold border border-emerald-200/60">
          <span class="p-2 rounded-lg bg-emerald-600 text-white">📍</span> RHU Resident Map
        </a>
        <a href="#officials" onclick="toggleMobileMenu()" class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-100 transition-colors">
          <span class="p-2 rounded-lg bg-amber-50 text-amber-600">🏛️</span> Officials & Staff
        </a>
        <a href="#contact" onclick="toggleMobileMenu()" class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-100 transition-colors">
          <span class="p-2 rounded-lg bg-teal-50 text-teal-600">✉️</span> Contact RHU
        </a>
      </nav>
      <div class="pt-4 border-t border-slate-200/80 flex flex-col gap-2.5">
        <a href="ResidentLogin.php" class="w-full text-center py-3 rounded-xl bg-gradient-to-r from-brand-600 to-teal-600 text-white font-bold text-sm shadow-md shadow-brand-600/20">Resident Sign In</a>
        <a href="ResidentRegistration.php" class="w-full text-center py-3 rounded-xl border border-slate-300 font-bold text-slate-700 hover:bg-slate-50 text-sm">Register Free Account</a>
        <div class="pt-2 flex justify-around text-xs font-semibold text-slate-500">
          <a href="RHULogin.php" class="hover:text-brand-600">RHU Staff</a>
          <span>•</span>
          <a href="BHWLogin.php" class="hover:text-brand-600">BHW Portal</a>
          <span>•</span>
          <a href="RHUAdminLogin.php" class="hover:text-brand-600">Admin Console</a>
        </div>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section id="top" class="relative overflow-hidden bg-gradient-to-b from-slate-900 via-slate-800 to-emerald-950 text-white pt-28 pb-20 lg:pt-36 lg:pb-28">
    <!-- Subtle Background Glow Elements -->
    <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-brand-500/20 blur-3xl pointer-events-none"></div>
    <div class="absolute top-1/2 -left-40 w-96 h-96 rounded-full bg-teal-500/15 blur-3xl pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
      <div class="grid lg:grid-cols-12 gap-12 items-center">
        
        <!-- Left Content Column -->
        <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
          <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 border border-white/15 text-brand-300 text-xs sm:text-sm font-semibold backdrop-blur-md">
            <span class="h-2 w-2 rounded-full bg-brand-400 animate-pulse"></span>
            Official Digital Health Portal · Municipality of Nasugbu
          </div>
          
          <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight text-white">
            Your Health,<br class="hidden sm:inline">
            <span class="text-emerald-300">Our Priority.</span>
          </h1>

          <p class="text-lg sm:text-xl text-slate-300 font-normal leading-relaxed max-w-2xl mx-auto lg:mx-0">
            Welcome to <strong class="text-white"><?= e($rhu['name']) ?></strong>. Access free consultations, immunization cards, medical certificates, and community health services for all residents.
          </p>

          <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 pt-2">
            <a href="ResidentLogin.php" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-gradient-to-r from-brand-500 to-teal-500 hover:from-brand-600 hover:to-teal-600 text-white font-bold text-base shadow-xl shadow-brand-500/25 hover:shadow-brand-500/40 transition-all flex items-center justify-center gap-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
              Access Resident Portal
            </a>
            <a href="#services" class="w-full sm:w-auto px-8 py-4 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-semibold text-base backdrop-blur-md transition-all flex items-center justify-center gap-2">
              Explore RHU Services
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </a>
          </div>

          <!-- Trust Badges -->
          <div class="pt-6 border-t border-white/10 flex flex-wrap items-center justify-center lg:justify-start gap-6 text-xs text-slate-300">
            <span class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-brand-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              100% Free Consultations
            </span>
            <span class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-brand-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              RA 10173 Data Privacy
            </span>
            <span class="flex items-center gap-1.5">
              <svg class="w-4 h-4 text-brand-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              DOH Accredited Facility
            </span>
          </div>
        </div>

        <!-- Right Card Highlight Column -->
        <div class="lg:col-span-5">
          <div class="rounded-3xl bg-white/10 border border-white/20 p-6 sm:p-8 backdrop-blur-xl shadow-2xl space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-white/10">
              <div>
                <h2 class="text-lg font-bold text-white">Resident Portal Privileges</h2>
                <p class="text-xs text-brand-300">Fast, convenient, digital healthcare</p>
              </div>
              <span class="px-2.5 py-1 rounded-full bg-brand-500/20 border border-brand-400/30 text-brand-300 text-xs font-semibold">
                Free Sign Up
              </span>
            </div>

            <ul class="space-y-3.5 text-sm text-slate-200">
              <li class="flex items-start gap-3">
                <span class="h-5 w-5 rounded-full bg-brand-500/20 text-brand-400 flex items-center justify-center shrink-0 mt-0.5">✓</span>
                <span>View consultation records, diagnoses & prescribed medications online.</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="h-5 w-5 rounded-full bg-brand-500/20 text-brand-400 flex items-center justify-center shrink-0 mt-0.5">✓</span>
                <span>Track infant and child immunization schedules & digital vaccine cards.</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="h-5 w-5 rounded-full bg-brand-500/20 text-brand-400 flex items-center justify-center shrink-0 mt-0.5">✓</span>
                <span>Download official health, medical, and pre-employment clearances.</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="h-5 w-5 rounded-full bg-brand-500/20 text-brand-400 flex items-center justify-center shrink-0 mt-0.5">✓</span>
                <span>Receive automated SMS notifications for follow-up appointments.</span>
              </li>
            </ul>

            <div class="pt-2">
              <a href="ResidentRegistration.php" class="block w-full py-3.5 px-4 rounded-xl bg-white hover:bg-slate-100 text-emerald-950 font-bold text-center text-sm shadow-md transition-all">
                Create Free Resident Profile →
              </a>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- Impact Statistics Counter Bar (Powered Live from Database) -->
  <section class="bg-[#0b1329] border-y border-slate-800/80 py-12 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center divide-y md:divide-y-0 md:divide-x divide-slate-800/80">
        
        <!-- Population Served -->
        <div class="p-3 space-y-1">
          <span class="block text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[#10b981] tracking-tight">
            <?= number_format($rhu['population']) ?>
          </span>
          <span class="block text-xs sm:text-sm text-slate-400 font-medium tracking-wide">
            Population Served
          </span>
        </div>

        <!-- Barangays Covered -->
        <div class="p-3 space-y-1 pt-6 md:pt-3">
          <span class="block text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[#10b981] tracking-tight">
            <?= e($rhu['barangay_count']) ?>
          </span>
          <span class="block text-xs sm:text-sm text-slate-400 font-medium tracking-wide">
            Barangays Covered
          </span>
        </div>

        <!-- Health Programs -->
        <div class="p-3 space-y-1 pt-6 md:pt-3">
          <span class="block text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[#10b981] tracking-tight">
            <?= e($rhu['programs_count']) ?>
          </span>
          <span class="block text-xs sm:text-sm text-slate-400 font-medium tracking-wide">
            Health Programs
          </span>
        </div>

        <!-- Primary Care Services -->
        <div class="p-3 space-y-1 pt-6 md:pt-3">
          <span class="block text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[#10b981] tracking-tight">
            100% FREE
          </span>
          <span class="block text-xs sm:text-sm text-slate-400 font-medium tracking-wide">
            Primary Care Services
          </span>
        </div>

      </div>
    </div>
  </section>

  <!-- Services Showcase Section -->
  <section id="services" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center max-w-3xl mx-auto mb-16">
        <span class="px-3.5 py-1.5 rounded-full bg-brand-50 border border-brand-200 text-brand-700 text-xs font-bold uppercase tracking-wider">Comprehensive Care</span>
        <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold text-slate-900">RHU Healthcare Services</h2>
        <p class="mt-4 text-base text-slate-600">All primary healthcare services are provided free of charge to residents of <?= e($rhu['municipality']) ?>. Walk-in patients and registered account holders are always welcome.</p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php foreach ($services as $srv): ?>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-transform duration-200 ease-out transform-gpu flex flex-col justify-between group">
          <div>
            <div class="h-12 w-12 rounded-2xl border <?= $srv['color'] ?> flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
              <?= $srv['icon'] ?>
            </div>
            <h3 class="text-lg font-bold text-slate-900 group-hover:text-brand-600 transition-colors"><?= e($srv['label']) ?></h3>
            <span class="inline-block mt-2 px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-600 text-xs font-semibold">
              <?= e($srv['schedule']) ?>
            </span>
            <p class="mt-3 text-xs sm:text-sm text-slate-500 leading-relaxed"><?= e($srv['desc']) ?></p>
          </div>
          <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-xs font-bold text-emerald-600">Free Service ✓</span>
            <a href="ResidentLogin.php" class="text-xs font-semibold text-slate-600 hover:text-brand-600 flex items-center gap-1">
              Inquire
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Quick Service Action Banner -->
      <div class="mt-12 rounded-3xl bg-gradient-to-r from-brand-600 to-teal-700 p-8 text-white flex flex-col md:flex-row items-center justify-between gap-6 shadow-xl">
        <div class="space-y-1 text-center md:text-left">
          <h3 class="text-xl sm:text-2xl font-bold">Ready to access your digital health profile?</h3>
          <p class="text-sm text-emerald-100">Register your account today to track consultation history, vaccine records, and requests.</p>
        </div>
        <a href="ResidentRegistration.php" class="shrink-0 px-6 py-3.5 rounded-xl bg-white text-brand-800 font-bold text-sm shadow-md hover:bg-slate-50 transition-colors">
          Create Account Now →
        </a>
      </div>
    </div>
  </section>

  <!-- How It Works Section -->
  <section id="how-it-works" class="py-20 bg-slate-50 border-y border-slate-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center max-w-3xl mx-auto mb-16">
        <span class="px-3.5 py-1.5 rounded-full bg-brand-50 border border-brand-200 text-brand-700 text-xs font-bold uppercase tracking-wider">Simple & Accessible</span>
        <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold text-slate-900">How the Resident Portal Works</h2>
        <p class="mt-4 text-base text-slate-600">Accessing RHU healthcare services and digital records has never been easier.</p>
      </div>

      <div class="grid md:grid-cols-3 gap-8">
        <div class="bg-white rounded-3xl p-8 border border-slate-200/80 shadow-sm text-center relative">
          <div class="h-16 w-16 rounded-2xl bg-brand-600 text-white font-extrabold text-2xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-brand-600/20">
            1
          </div>
          <h3 class="text-xl font-bold text-slate-900">Register Your Account</h3>
          <p class="mt-3 text-sm text-slate-500 leading-relaxed">Sign up online using your name, contact number, email, and barangay residence details in Nasugbu.</p>
        </div>

        <div class="bg-white rounded-3xl p-8 border border-slate-200/80 shadow-sm text-center relative">
          <div class="h-16 w-16 rounded-2xl bg-brand-600 text-white font-extrabold text-2xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-brand-600/20">
            2
          </div>
          <h3 class="text-xl font-bold text-slate-900">RHU Verification</h3>
          <p class="mt-3 text-sm text-slate-500 leading-relaxed">Once verified by RHU staff, your medical history, consultation records, and immunization cards link automatically.</p>
        </div>

        <div class="bg-white rounded-3xl p-8 border border-slate-200/80 shadow-sm text-center relative">
          <div class="h-16 w-16 rounded-2xl bg-brand-600 text-white font-extrabold text-2xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-brand-600/20">
            3
          </div>
          <h3 class="text-xl font-bold text-slate-900">Stay Connected & Healthy</h3>
          <p class="mt-3 text-sm text-slate-500 leading-relaxed">Receive SMS follow-up alerts, request medical clearances, and view upcoming barangay health drives.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Health Events Calendar Section -->
  <section id="events" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center max-w-3xl mx-auto mb-16">
        <span class="px-3.5 py-1.5 rounded-full bg-brand-50 border border-brand-200 text-brand-700 text-xs font-bold uppercase tracking-wider">Community Outreach</span>
        <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold text-slate-900">Upcoming Health Events</h2>
        <p class="mt-4 text-base text-slate-600">Join our upcoming free health drives, screening programs, and medical missions across <?= e($rhu['municipality']) ?>.</p>
      </div>

      <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($events as $evt): 
          $date = $evt[0] ?? '';
          $title = $evt[1] ?? '';
          $location = $evt[2] ?? '';
          $detail = $evt[3] ?? '';
          $dotColor = $evt[4] ?? 'bg-emerald-500';
          $imgUrl = $evt[5] ?? '';
        ?>
        <div class="rounded-2xl border border-slate-200/80 bg-white overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-transform duration-200 ease-out transform-gpu flex flex-col justify-between group">
          <div>
            <?php if (!empty($imgUrl)): ?>
            <div class="h-48 w-full overflow-hidden bg-slate-100 relative">
              <img src="<?= e(portalImgUrl($imgUrl)) ?>" alt="<?= e($title) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800&q=80';">
              <div class="absolute top-3 left-3 px-3 py-1 rounded-full bg-slate-900/80 backdrop-blur-md font-mono text-xs font-bold text-white shadow">
                <?= e($date) ?>
              </div>
            </div>
            <?php endif; ?>

            <div class="p-6">
              <?php if (empty($imgUrl)): ?>
              <div class="flex items-center justify-between mb-4">
                <span class="px-3 py-1 rounded-full bg-slate-100 font-mono text-xs font-bold text-slate-700">
                  <?= e($date) ?>
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                  <span class="h-2 w-2 rounded-full <?= $dotColor ?>"></span>
                  Free Entry
                </span>
              </div>
              <?php else: ?>
              <div class="flex items-center justify-between mb-2">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                  <span class="h-2 w-2 rounded-full <?= $dotColor ?>"></span>
                  Free Entry
                </span>
              </div>
              <?php endif; ?>
              <h3 class="text-lg font-bold text-slate-900 mb-1 group-hover:text-brand-600 transition-colors"><?= e($title) ?></h3>
              <p class="text-xs font-semibold text-brand-700 mb-3"><?= e($location) ?></p>
              <p class="text-xs text-slate-500 leading-relaxed"><?= e($detail) ?></p>
            </div>
          </div>
          <div class="px-6 pb-6 pt-2 border-t border-slate-100 flex justify-between items-center text-xs">
            <span class="text-slate-400">Target: All Residents</span>
            <a href="ResidentLogin.php" class="font-semibold text-brand-600 hover:underline flex items-center gap-1">
              Details & Register →
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- RHU Community Event & Photo Gallery Section -->
  <?php if (!empty($eventGallery)): ?>
  <section class="py-16 bg-slate-900 text-white border-t border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-4">
        <div>
          <span class="px-3.5 py-1.5 rounded-full bg-white/10 border border-white/15 text-brand-300 text-xs font-bold uppercase tracking-wider">Photo Gallery</span>
          <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold text-white">Health Events in Action</h2>
          <p class="mt-2 text-sm text-slate-300">Moments captured from recent public health drives, medical missions, and community programs.</p>
        </div>
        <span class="text-xs text-slate-400 font-medium">Updated by RHU Admin</span>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php foreach ($eventGallery as $gPic): ?>
        <div class="rounded-2xl bg-white/5 border border-white/10 overflow-hidden group shadow-lg hover:border-brand-500/50 transition-colors duration-200 transform-gpu">
          <div class="h-48 w-full overflow-hidden bg-slate-800 relative">
            <img src="<?= e(portalImgUrl($gPic['image_url'])) ?>" alt="<?= e($gPic['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 transform-gpu" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=400&q=80';">
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent opacity-80"></div>
            <div class="absolute bottom-3 left-3 right-3">
              <span class="px-2 py-0.5 rounded bg-brand-500/80 text-white font-semibold text-[10px] uppercase tracking-wider">
                <?= e($gPic['category'] ?? 'Event Photo') ?>
              </span>
            </div>
          </div>
          <div class="p-4">
            <h4 class="font-bold text-white text-sm leading-snug group-hover:text-brand-300 transition-colors"><?= e($gPic['title']) ?></h4>
            <p class="text-xs text-slate-400 mt-1"><?= e($gPic['date'] ?? '') ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- Municipal Leadership & RHU Healthcare Staff Section -->
  <section id="officials" class="py-20 bg-slate-50 border-t border-slate-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      
      <!-- Section Header -->
      <div class="text-center max-w-3xl mx-auto mb-16">
        <span class="px-3.5 py-1.5 rounded-full bg-brand-50 border border-brand-200 text-brand-700 text-xs font-bold uppercase tracking-wider">Leadership & Personnel</span>
        <h2 class="mt-3 text-3xl sm:text-4xl font-extrabold text-slate-900">Municipal Officials & Health Staff</h2>
        <p class="mt-4 text-base text-slate-600">In partnership with the Municipal Local Government Unit of <?= e($rhu['municipality']) ?>, dedicated to public health excellence.</p>
      </div>

      <!-- RHU Healthcare Team -->
      <div class="mb-16">
        <h3 class="text-xl font-bold text-slate-900 mb-6 text-center">RHU I Medical & Public Health Staff</h3>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($staff as $i => [$name, $position, $specialty, $employment, $status]): ?>
          <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm flex items-start gap-4 hover:shadow-md transition-shadow duration-200 transform-gpu">
            <div class="h-14 w-14 rounded-2xl bg-gradient-to-tr from-brand-600 to-teal-600 text-white font-extrabold text-lg flex items-center justify-center shrink-0 shadow-md">
              <?= e(initials($name)) ?>
            </div>
            <div>
              <h4 class="font-bold text-slate-900 text-base"><?= e($name) ?></h4>
              <p class="text-xs font-semibold text-brand-600 mt-0.5"><?= e($position) ?></p>
              <p class="text-xs text-slate-400 mt-1"><?= e($specialty) ?></p>
              <div class="mt-3 flex gap-2">
                <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 font-semibold text-[11px]">
                  <?= e($employment) ?>
                </span>
                <span class="px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 font-semibold text-[11px]">
                  PhilHealth Accredited
                </span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Municipal Officials Grid -->
      <div>
        <h3 class="text-xl font-bold text-slate-900 mb-6 text-center">Municipal Government Officials</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
          <?php foreach ($officials as $i => [$name, $position, $office]): ?>
          <div class="bg-white rounded-2xl p-4 border border-slate-200/80 text-center shadow-sm">
            <div class="h-12 w-12 rounded-full <?= $i < 2 ? 'bg-brand-600' : 'bg-slate-700' ?> text-white font-extrabold text-sm flex items-center justify-center mx-auto mb-3 shadow">
              <?= e(initials($name)) ?>
            </div>
            <h4 class="font-bold text-slate-900 text-xs leading-tight"><?= e($name) ?></h4>
            <p class="text-[11px] font-semibold text-brand-600 mt-1"><?= e($position) ?></p>
            <p class="text-[10px] text-slate-400 mt-1 leading-tight"><?= e($office) ?></p>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </section>

  <!-- Interactive Health Centers & Barangay Coverage Map Section -->
  <section id="map" class="py-20 bg-slate-950 text-white relative overflow-hidden">
    <!-- Ambient Background Glow -->
    <div class="absolute top-0 right-1/4 w-96 h-96 bg-emerald-600/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 left-1/4 w-96 h-96 bg-teal-600/15 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
      
      <!-- Section Header -->
      <div class="text-center max-w-3xl mx-auto space-y-3 mb-12">
        <span class="px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold uppercase tracking-wider">
          Resident Health Map & Station Finder
        </span>
        <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
          Locate Your Nearest Health Center
        </h2>
        <p class="text-slate-400 text-sm leading-relaxed">
          Interactive health map for Nasugbu residents. View main RHU headquarters, barangay health stations, emergency hotlines, operating hours, and turn-by-turn directions.
        </p>
      </div>

      <!-- Map & Location Selector Container -->
      <div class="grid lg:grid-cols-12 gap-6 sm:gap-8 items-start">
        
        <!-- Left: Interactive Map Container -->
        <div class="lg:col-span-8 rounded-3xl border border-slate-800 bg-slate-900/90 p-3.5 sm:p-5 shadow-2xl space-y-3.5">
          
          <!-- Mobile Horizontal Quick Station Pills -->
          <div class="sm:hidden flex items-center gap-2 overflow-x-auto pb-1.5 scrollbar-none text-xs">
            <button type="button" onclick="switchMapCenter('RHU Nasugbu (J.P. Laurel St)', 'https://maps.google.com/maps?q=14.0740668,120.6321294&t=&z=17&ie=UTF8&iwloc=&output=embed', 'https://maps.app.goo.gl/rcqBNWJBuYsd4YiXA', this)" class="station-card active shrink-0 px-3 py-1.5 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 font-bold cursor-pointer">📍 1. RHU Main</button>
            <button type="button" onclick="switchMapCenter('Barangay Wawa Health Station', 'https://maps.google.com/maps?q=Barangay%20Wawa,%20Nasugbu,%20Batangas&t=&z=15&ie=UTF8&iwloc=&output=embed', 'https://maps.google.com/?q=Barangay+Wawa+Nasugbu+Batangas', this)" class="station-card shrink-0 px-3 py-1.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-300 font-medium hover:text-white cursor-pointer">🏥 2. Wawa</button>
            <button type="button" onclick="switchMapCenter('Barangay Kaylaway Health Station', 'https://maps.google.com/maps?q=Barangay%20Kaylaway,%20Nasugbu,%20Batangas&t=&z=15&ie=UTF8&iwloc=&output=embed', 'https://maps.google.com/?q=Barangay+Kaylaway+Nasugbu+Batangas', this)" class="station-card shrink-0 px-3 py-1.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-300 font-medium hover:text-white cursor-pointer">🏥 3. Kaylaway</button>
            <button type="button" onclick="switchMapCenter('Barangay Bucana Health Station', 'https://maps.google.com/maps?q=Barangay%20Bucana,%20Nasugbu,%20Batangas&t=&z=15&ie=UTF8&iwloc=&output=embed', 'https://maps.google.com/?q=Barangay+Bucana+Nasugbu+Batangas', this)" class="station-card shrink-0 px-3 py-1.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-300 font-medium hover:text-white cursor-pointer">🏥 4. Bucana</button>
            <button type="button" onclick="switchMapCenter('Barangay Calayo Health Station', 'https://maps.google.com/maps?q=Barangay%20Calayo,%20Nasugbu,%20Batangas&t=&z=15&ie=UTF8&iwloc=&output=embed', 'https://maps.google.com/?q=Barangay+Calayo+Nasugbu+Batangas', this)" class="station-card shrink-0 px-3 py-1.5 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-300 font-medium hover:text-white cursor-pointer">🏥 5. Calayo</button>
          </div>

          <!-- Top Map Controls Bar -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3.5 bg-slate-950/90 rounded-2xl border border-slate-800/80 gap-3">
            <div class="flex items-center gap-2">
              <span class="h-2.5 w-2.5 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
              <span class="text-xs font-bold text-slate-200 truncate">Active Pin: <span id="active-center-title" class="text-emerald-400 font-extrabold">RHU Nasugbu (J.P. Laurel St)</span></span>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
              <a id="get-directions-btn" href="https://maps.app.goo.gl/rcqBNWJBuYsd4YiXA" target="_blank" rel="noopener noreferrer" class="flex-1 sm:flex-none justify-center text-center px-4 py-2.5 sm:py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold text-xs shadow-md shadow-emerald-600/20 hover:opacity-95 transition-all flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Open in Google Maps ↗
              </a>
              <a href="tel:0434169999" class="sm:hidden px-3 py-2.5 rounded-xl bg-red-500/20 border border-red-500/30 text-red-300 font-bold text-xs flex items-center justify-center gap-1" title="Call Emergency Hotline">
                ☎ Emergency
              </a>
            </div>
          </div>

          <!-- Embedded Google Map -->
          <div class="relative w-full h-[320px] sm:h-[460px] rounded-2xl overflow-hidden border border-slate-800/90 shadow-inner">
            <iframe id="rhu-map-iframe" class="w-full h-full border-0 grayscale-[15%] contrast-[110%]" src="https://maps.google.com/maps?q=14.0740668,120.6321294&t=&z=17&ie=UTF8&iwloc=&output=embed" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
          </div>

          <!-- Map Info Legend Badges -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-center p-3 bg-slate-950/70 rounded-2xl border border-slate-800/80 text-[11px] sm:text-xs">
            <div class="p-2 rounded-xl bg-slate-900/60 border border-slate-800/60">
              <span class="block text-slate-400 font-medium">Facility</span>
              <span class="text-white font-bold truncate block">RHU Nasugbu</span>
            </div>
            <div class="p-2 rounded-xl bg-slate-900/60 border border-slate-800/60">
              <span class="block text-slate-400 font-medium">GPS Location</span>
              <span class="text-emerald-400 font-bold truncate block">14.0740°N, 120.6321°E</span>
            </div>
            <div class="p-2 rounded-xl bg-slate-900/60 border border-slate-800/60">
              <span class="block text-slate-400 font-medium">Barangays</span>
              <span class="text-white font-bold truncate block">19 Sectors</span>
            </div>
            <div class="p-2 rounded-xl bg-slate-900/60 border border-slate-800/60">
              <span class="block text-slate-400 font-medium">PhilHealth</span>
              <span class="text-emerald-400 font-bold truncate block">Konsulta Desk</span>
            </div>
          </div>

        </div>

        <!-- Right: Barangay & Health Station Selection Panel (Desktop & Tablet) -->
        <div class="lg:col-span-4 space-y-4">
          <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-4 sm:p-6 shadow-2xl space-y-4">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
              <h3 class="text-base font-bold text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V11m0 0H7m3 0h3m-3-4H7m3 0h3"/></svg>
                Health Stations Directory
              </h3>
              <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Tap to View</span>
            </div>

            <!-- Health Station Cards (Clickable) -->
            <div class="space-y-3 max-h-[440px] overflow-y-auto pr-1">
              
              <!-- Main RHU -->
              <button type="button" onclick="switchMapCenter('RHU Nasugbu (J.P. Laurel St)', 'https://maps.google.com/maps?q=14.0740668,120.6321294&t=&z=17&ie=UTF8&iwloc=&output=embed', 'https://maps.app.goo.gl/rcqBNWJBuYsd4YiXA', this)" class="station-card active w-full text-left p-3.5 sm:p-4 rounded-2xl border border-emerald-500/50 bg-emerald-500/10 hover:bg-emerald-500/20 transition-all space-y-1.5 group cursor-pointer">
                <div class="flex items-center justify-between">
                  <span class="font-bold text-sm text-white group-hover:text-emerald-300 transition-colors">1. RHU Nasugbu Headquarters</span>
                  <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-300">Main Facility</span>
                </div>
                <p class="text-xs text-slate-300">J.P. Laurel St, Poblacion, Nasugbu, Batangas</p>
                <div class="flex items-center gap-3 text-[11px] text-slate-400 pt-1">
                  <span>☎ (043) 416-1234</span>
                  <span>•</span>
                  <span>⏰ Mon-Fri 8AM-5PM</span>
                </div>
              </button>

              <!-- Station 2: Wawa Coastal Station -->
              <button type="button" onclick="switchMapCenter('Barangay Wawa Health Station', 'https://maps.google.com/maps?q=Barangay%20Wawa,%20Nasugbu,%20Batangas&t=&z=15&ie=UTF8&iwloc=&output=embed', 'https://maps.google.com/?q=Barangay+Wawa+Nasugbu+Batangas', this)" class="station-card w-full text-left p-3.5 sm:p-4 rounded-2xl border border-slate-800 bg-slate-950/60 hover:bg-slate-950 hover:border-slate-700 transition-all space-y-1.5 group cursor-pointer">
                <div class="flex items-center justify-between">
                  <span class="font-bold text-sm text-white group-hover:text-emerald-300 transition-colors">2. Barangay Wawa Station</span>
                  <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-blue-500/20 text-blue-300">Coastal Sector</span>
                </div>
                <p class="text-xs text-slate-300">Seaside Desk, Bgy Wawa, Nasugbu</p>
                <div class="flex items-center gap-3 text-[11px] text-slate-400 pt-1">
                  <span>☎ 0917-889-1021</span>
                  <span>•</span>
                  <span>⏰ Mon-Sat 8AM-4PM</span>
                </div>
              </button>

              <!-- Station 3: Kaylaway Highway Station -->
              <button type="button" onclick="switchMapCenter('Barangay Kaylaway Health Station', 'https://maps.google.com/maps?q=Barangay%20Kaylaway,%20Nasugbu,%20Batangas&t=&z=15&ie=UTF8&iwloc=&output=embed', 'https://maps.google.com/?q=Barangay+Kaylaway+Nasugbu+Batangas', this)" class="station-card w-full text-left p-3.5 sm:p-4 rounded-2xl border border-slate-800 bg-slate-950/60 hover:bg-slate-950 hover:border-slate-700 transition-all space-y-1.5 group cursor-pointer">
                <div class="flex items-center justify-between">
                  <span class="font-bold text-sm text-white group-hover:text-emerald-300 transition-colors">3. Kaylaway Highway Desk</span>
                  <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-purple-500/20 text-purple-300">Upland Sector</span>
                </div>
                <p class="text-xs text-slate-300">Highway Substation, Bgy Kaylaway, Nasugbu</p>
                <div class="flex items-center gap-3 text-[11px] text-slate-400 pt-1">
                  <span>☎ 0919-554-3210</span>
                  <span>•</span>
                  <span>⏰ Daily 8AM-5PM</span>
                </div>
              </button>

              <!-- Station 4: Bucana Community Station -->
              <button type="button" onclick="switchMapCenter('Barangay Bucana Health Station', 'https://maps.google.com/maps?q=Barangay%20Bucana,%20Nasugbu,%20Batangas&t=&z=15&ie=UTF8&iwloc=&output=embed', 'https://maps.google.com/?q=Barangay+Bucana+Nasugbu+Batangas', this)" class="station-card w-full text-left p-3.5 sm:p-4 rounded-2xl border border-slate-800 bg-slate-950/60 hover:bg-slate-950 hover:border-slate-700 transition-all space-y-1.5 group cursor-pointer">
                <div class="flex items-center justify-between">
                  <span class="font-bold text-sm text-white group-hover:text-emerald-300 transition-colors">4. Bucana Health Center</span>
                  <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-teal-500/20 text-teal-300">Community Sector</span>
                </div>
                <p class="text-xs text-slate-300">Plaza Health Desk, Bgy Bucana, Nasugbu</p>
                <div class="flex items-center gap-3 text-[11px] text-slate-400 pt-1">
                  <span>☎ 0920-112-3344</span>
                  <span>•</span>
                  <span>⏰ Mon-Fri 8AM-4PM</span>
                </div>
              </button>

              <!-- Station 5: Calayo Tourist & Beach Sector -->
              <button type="button" onclick="switchMapCenter('Barangay Calayo Health Station', 'https://maps.google.com/maps?q=Barangay%20Calayo,%20Nasugbu,%20Batangas&t=&z=15&ie=UTF8&iwloc=&output=embed', 'https://maps.google.com/?q=Barangay+Calayo+Nasugbu+Batangas', this)" class="station-card w-full text-left p-3.5 sm:p-4 rounded-2xl border border-slate-800 bg-slate-950/60 hover:bg-slate-950 hover:border-slate-700 transition-all space-y-1.5 group cursor-pointer">
                <div class="flex items-center justify-between">
                  <span class="font-bold text-sm text-white group-hover:text-emerald-300 transition-colors">5. Calayo Health Desk</span>
                  <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-amber-500/20 text-amber-300">Southern Sector</span>
                </div>
                <p class="text-xs text-slate-300">Beach Road Desk, Bgy Calayo, Nasugbu</p>
                <div class="flex items-center gap-3 text-[11px] text-slate-400 pt-1">
                  <span>☎ 0917-778-9900</span>
                  <span>•</span>
                  <span>⏰ Mon-Sat 8AM-4PM</span>
                </div>
              </button>

            </div>

            <!-- Hotline Alert Banner -->
            <div class="p-3.5 rounded-2xl bg-red-500/10 border border-red-500/30 text-xs text-red-200 flex items-center gap-2.5">
              <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
              <span><strong>Emergency Dispatch:</strong> Call Ambulance <a href="tel:0434169999" class="underline font-bold text-white"><?= e($rhu['emergency']) ?></a></span>
            </div>

          </div>
        </div>

      </div>

    </div>
  </section>

  <script>
    function switchMapCenter(title, iframeUrl, directionsUrl, clickedBtn) {
      document.getElementById('active-center-title').textContent = title;
      document.getElementById('rhu-map-iframe').src = iframeUrl;
      document.getElementById('get-directions-btn').href = directionsUrl;

      // Reset all cards & pills
      const cards = document.querySelectorAll('.station-card');
      cards.forEach(card => {
        card.classList.remove('border-emerald-500/50', 'bg-emerald-500/20', 'bg-emerald-500/10', 'text-emerald-300', 'font-bold');
        card.classList.add('border-slate-800', 'bg-slate-950/60');
      });

      if (clickedBtn) {
        clickedBtn.classList.remove('border-slate-800', 'bg-slate-950/60');
        clickedBtn.classList.add('border-emerald-500/50', 'bg-emerald-500/20', 'text-emerald-300', 'font-bold');
      }
    }
  </script>

  <!-- Contact & Inquiry Section -->
  <section id="contact" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid lg:grid-cols-12 gap-12 items-start">
        
        <!-- Left Contact Info -->
        <div class="lg:col-span-5 space-y-8">
          <div>
            <span class="px-3.5 py-1.5 rounded-full bg-brand-50 border border-brand-200 text-brand-700 text-xs font-bold uppercase tracking-wider">Get In Touch</span>
            <h2 class="mt-3 text-3xl font-extrabold text-slate-900">Contact the RHU Desk</h2>
            <p class="mt-3 text-slate-600 text-sm leading-relaxed">Have questions about health services, immunization schedules, or medical clearances? Reach out to our administrative staff.</p>
          </div>

          <div class="space-y-4">
            <div class="flex items-start gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
              <div class="p-3 rounded-xl bg-brand-100 text-brand-700 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </div>
              <div>
                <span class="block text-xs font-bold uppercase text-slate-400">RHU Main Address</span>
                <span class="text-sm font-semibold text-slate-800"><?= e($rhu['address']) ?></span>
              </div>
            </div>

            <div class="flex items-start gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
              <div class="p-3 rounded-xl bg-brand-100 text-brand-700 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
              </div>
              <div>
                <span class="block text-xs font-bold uppercase text-slate-400">Contact Hotlines</span>
                <span class="text-sm font-semibold text-slate-800">Landline: <?= e($rhu['contact']) ?></span><br>
                <span class="text-xs text-red-600 font-bold">Emergency 24/7: <?= e($rhu['emergency']) ?></span>
              </div>
            </div>

            <div class="flex items-start gap-4 p-4 rounded-2xl bg-slate-50 border border-slate-200/80">
              <div class="p-3 rounded-xl bg-brand-100 text-brand-700 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </div>
              <div>
                <span class="block text-xs font-bold uppercase text-slate-400">Operating Hours</span>
                <span class="text-sm font-semibold text-slate-800"><?= e($rhu['hours']) ?></span>
              </div>
            </div>
          </div>

          <!-- Barangays Pill List -->
          <div class="p-5 rounded-2xl bg-brand-50/60 border border-brand-200/60">
            <span class="block text-xs font-bold text-brand-900 uppercase tracking-wide mb-2">Barangays Under RHU I Coverage</span>
            <div class="flex flex-wrap gap-1.5">
              <?php foreach ($rhu['barangays'] as $bgy): ?>
              <span class="px-2.5 py-1 rounded-full bg-white text-brand-800 text-xs font-semibold shadow-xs border border-brand-100">
                <?= e($bgy) ?>
              </span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Right Inquiry Form -->
        <div class="lg:col-span-7">
          <div class="bg-slate-50 rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-md">
            <h3 class="text-xl font-bold text-slate-900 mb-2">Send an Online Message</h3>
            <p class="text-xs text-slate-500 mb-6">Fill out the form below and our health officers will respond to your inquiry.</p>

            <?php if (isset($_GET['contact_success']) || $flash): ?>
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-3">
              <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
              <span><?= e($flash ?: 'Thank you! Your message has been sent to the RHU desk.') ?></span>
            </div>
            <?php endif; ?>

            <form method="post" action="LandingPage.php#contact" class="space-y-4">
              <input type="hidden" name="form" value="contact">

              <div class="grid sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Your Full Name *</label>
                  <input required name="name" type="text" placeholder="e.g. Juan dela Cruz" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all">
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Contact Number</label>
                  <input name="contact" type="text" placeholder="0917XXXXXXX" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all">
                </div>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Inquiry Subject</label>
                <select name="subject" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all bg-white">
                  <option>General Inquiry</option>
                  <option>Appointment Inquiry</option>
                  <option>Health Certificate / Clearance</option>
                  <option>Immunization Program</option>
                  <option>Feedback & Suggestions</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Your Message *</label>
                <textarea required name="message" rows="4" placeholder="How can the RHU help you?" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none transition-all"></textarea>
              </div>

              <button type="submit" class="w-full py-4 rounded-xl bg-gradient-to-r from-brand-600 to-teal-600 hover:from-brand-700 hover:to-teal-700 text-white font-bold text-sm shadow-md shadow-brand-600/20 transition-all">
                Send Message to RHU →
              </button>
            </form>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- Modern Footer -->
  <footer class="bg-slate-950 text-slate-400 py-16 border-t border-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-slate-900">
        
        <!-- Brand Info -->
        <div class="md:col-span-2 space-y-4">
          <div class="flex items-center gap-3">
            <img src="resihunity_logo.jpg" alt="ResiHUnity Logo" class="h-12 w-auto object-contain rounded-xl bg-white p-1 shadow-md" />
            <span class="text-xl font-extrabold text-white">ResiHUnity RHU</span>
          </div>
          <p class="text-sm text-slate-400 leading-relaxed max-w-md">
            The official primary care and public health portal of <?= e($rhu['name']) ?>. Dedicated to serving the communities of <?= e($rhu['municipality']) ?>, <?= e($rhu['province']) ?>.
          </p>
          <div class="flex gap-4 text-xs font-semibold text-brand-400">
            <span>✓ DOH Accredited</span>
            <span>•</span>
            <span>✓ PhilHealth Certified</span>
            <span>•</span>
            <span>✓ Universal Health Care</span>
          </div>
        </div>

        <!-- Quick Links -->
        <div>
          <h4 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Quick Navigation</h4>
          <ul class="space-y-2.5 text-sm">
            <li><a href="ResidentLogin.php" class="hover:text-white transition-colors">Resident Portal Sign In</a></li>
            <li><a href="ResidentRegistration.php" class="hover:text-white transition-colors">Resident Registration</a></li>
            <li><a href="#services" class="hover:text-white transition-colors">RHU Services</a></li>
            <li><a href="#events" class="hover:text-white transition-colors">Health Events Calendar</a></li>
            <li><a href="#contact" class="hover:text-white transition-colors">Contact Information</a></li>
          </ul>
        </div>

        <!-- Staff Portals & Compliance -->
        <div>
          <h4 class="text-sm font-bold text-white uppercase tracking-wider mb-4">Portals & Legal</h4>
          <ul class="space-y-2.5 text-sm">
            <li><a href="RHULogin.php" class="hover:text-brand-400 transition-colors">RHU Healthcare Staff Login</a></li>
            <li><a href="BHWLogin.php" class="hover:text-brand-400 transition-colors">Barangay Health Worker Portal</a></li>
            <li><a href="RHUAdminLogin.php" class="hover:text-brand-400 transition-colors">System Admin Console</a></li>
            <li class="pt-2 text-xs text-slate-500">Data Privacy Act of 2012 (RA 10173) Compliant</li>
          </ul>
        </div>

      </div>

      <!-- Footer Bottom -->
      <div class="pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
        <p>© <?= date('Y') ?> <?= e($rhu['name']) ?>. Municipality of <?= e($rhu['municipality']) ?>, <?= e($rhu['province']) ?>. All rights reserved.</p>
        <div class="flex gap-6">
          <a href="#top" class="hover:text-slate-300">Back to top ↑</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- Mobile Floating Bottom App Bar -->
  <div class="sm:hidden fixed bottom-3 left-3 right-3 z-40 bg-slate-900/95 backdrop-blur-xl border border-slate-800 rounded-2xl p-2 shadow-2xl flex items-center justify-between gap-2">
    <a href="#map" class="flex-1 py-2.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 font-bold text-xs flex items-center justify-center gap-1.5 active:scale-95 transition-transform">
      <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      RHU Map
    </a>
    <a href="ResidentLogin.php" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20 active:scale-95 transition-transform">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
      Resident Sign In
    </a>
    <a href="tel:0434169999" class="px-3.5 py-2.5 rounded-xl bg-red-500/20 border border-red-500/30 text-red-300 font-bold text-xs flex items-center justify-center active:scale-95 transition-transform" title="Call Emergency Hotline">
      ☎ Emergency
    </a>
  </div>

  <script>
    function toggleMobileMenu() {
      const drawer = document.getElementById('mobile-drawer');
      if (drawer) {
        drawer.classList.toggle('hidden');
      }
    }
  </script>
</body>
</html>
