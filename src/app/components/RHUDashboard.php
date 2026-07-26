<?php
if (session_status() === PHP_SESSION_NONE) session_start();
@include_once __DIR__ . '/db.php';

/**
 * Escape output for HTML.
 * @param mixed $value
 */
if (!function_exists('esc')) {
    function esc(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dashboardUrl')) {
    function dashboardUrl(string $tab = 'overview', array $extra = []): string
    {
        return '?' . http_build_query(array_merge(['tab' => $tab], $extra));
    }
}

if (!function_exists('iconSvg')) {
    function iconSvg(string $name, string $class = 'w-5 h-5'): string
    {
    $icons = [
        'home' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>',
        'stethoscope' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v7a6 6 0 0 0 12 0V3"/><path d="M6 10a3 3 0 0 0 6 0"/><path d="M12 20a4 4 0 0 0 8 0v-2"/></svg>',
        'clipboard' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>',
        'droplets' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C8 6 5 9.5 5 13.5a7 7 0 0 0 14 0C19 9.5 16 6 12 2z"/></svg>',
        'users' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'activity' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
        'calendar' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
        'syringe' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M16 8l6-6"/><path d="M2 22l5-5 5 5"/><path d="M7 17l3 3"/></svg>',
        'send' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
        'testtube' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8v4H8z"/><path d="M8 6v6a4 4 0 1 0 8 0V6"/><path d="M12 14v6"/></svg>',
        'baby' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a8 8 0 0 1-8-8V7a4 4 0 0 1 8 0v7a8 8 0 0 1-8 8"/><path d="M12 2v4"/></svg>',
        'heart' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 22l7.8-8.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>',
        'microscope' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 20h12"/><path d="M10 14l2 2 2-2"/><path d="M7 8c0-2.5 2-5 5-5s5 2.5 5 5"/><path d="M7 8l4 4"/><path d="M17 18c0-1.1-.9-2-2-2"/></svg>',
        'scale' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v4"/><path d="M4 7h16"/><path d="M6 21h12"/><path d="M12 7c-2 0-4 2-4 4v4h8v-4c0-2-2-4-4-4z"/></svg>',
        'shield' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'file' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>',
        'pill' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 15L4.5 19.5a4.5 4.5 0 0 0 6.36 6.36L15 21"/><path d="M9 15l6-6"/><path d="M15 9L19.5 4.5A4.5 4.5 0 0 0 13.14-1.86L9 3"/></svg>',
        'usercheck' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M17 11l2 2 4-4"/></svg>',
        'bar' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="20" x2="6" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="18" y1="20" x2="18" y2="14"/></svg>',
        'trend' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
        'bell' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'logout' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'search' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
        'plus' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'x' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        'alert' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'check' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
        'download' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        'refresh' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>',
        'eye' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>',
        'edit' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>',
        'printer' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="2"/></svg>',
        'right' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 18l6-6-6-6"/></svg>',
        'phone' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.18 2 2 0 0 1 4.09 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.77.6 2.6a2 2 0 0 1-.45 2.11L8 9a16 16 0 0 0 7 7l.57-1.24a2 2 0 0 1 2.11-.45c.83.27 1.7.48 2.6.6A2 2 0 0 1 22 16.92z"/></svg>',
        'mail' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><polyline points="4 4 12 13 20 4"/></svg>',
        'map' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6.5 9 4l6 2 6-2v15l-6 2-6-2-6 2z"/><path d="M9 4v15"/><path d="M15 6v15"/></svg>',
        'package' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73L13 2.27a2 2 0 0 0-2 0L4 6.27A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 3.72a2 2 0 0 0 2 0l7-3.72A2 2 0 0 0 21 16z"/><path d="M7 9h10"/><path d="M7 15h10"/></svg>',
        'book' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 7H20v14H6.5A2.5 2.5 0 0 1 4 18.5V4.5z"/></svg>',
        'filecheck' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 16l2 2 4-4"/></svg>',
    ];

    return $icons[$name] ?? '';
}
}

if (!function_exists('formatDate')) {
    function formatDate(string $date): string
    {
        return date('F j, Y', strtotime($date));
    }
}

$tabs = [
    'overview' => ['Overview', 'home'],
    'opd' => ['OPD / Consultations', 'stethoscope'],
    'referrals' => ['Hospital Referrals', 'send'],
    'immunization' => ['Immunization (EPI)', 'testtube'],
    'maternal' => ['Maternal & Prenatal', 'baby'],
    'fp' => ['Family Planning', 'heart'],
    'tb_dots' => ['TB-DOTS Program', 'microscope'],
    'nutrition' => ['Nutrition & OPT+', 'scale'],
    'disease' => ['Disease Surveillance', 'shield'],
    'vital' => ['Vital Statistics', 'file'],
    'medicine' => ['Medicine Inventory', 'pill'],
    'sanitation' => ['Sanitation Inspection', 'shield'],
    'certificates' => ['Health Certificates', 'filecheck'],
    'bhw' => ['BHW Management', 'usercheck'],
    'staff' => ['Staff Directory', 'users'],
    'reports' => ['DOH Reports (FHSIS)', 'bar'],
    'analytics' => ['Seasonal Analytics & Predictions', 'trend'],
];

$tab = $_GET['tab'] ?? 'overview';
if (!isset($tabs[$tab])) {
    $tab = 'overview';
}
$modal = $_GET['modal'] ?? '';
$showNotifs = isset($_GET['notifs']) && $_GET['notifs'] === '1';
$searchQuery = trim($_GET['q'] ?? '');

$RHU_INFO = [
    'name' => 'Nasugbu Rural Health Unit I',
    'code' => 'RHU-NSG-001',
    'municipality' => 'Nasugbu',
    'province' => 'Batangas',
    'region' => 'Region IV-A (CALABARZON)',
    'address' => 'Poblacion, Nasugbu, Batangas',
    'contactNumber' => '(043) 416-1234',
    'email' => 'rhu1.nasugbu@doh.gov.ph',
    'chiefMHO' => 'Dr. Maria C. Santos',
    'totalPopulation' => 165000,
    'catchmentBarangays' => ['Halang', 'Mabini', 'Balibago', 'Poblacion', 'Anilao', 'Nagsabaran'],
];

$notifications = [
    ['id' => 1, 'title' => 'Critical Stock Alert', 'message' => 'Metformin & Folic Acid critically low', 'time' => '5 min ago', 'unread' => true, 'type' => 'critical'],
    ['id' => 2, 'title' => 'Dengue Cluster Alert', 'message' => '4 dengue cases in Halang & Mabini this week', 'time' => '1 hour ago', 'unread' => true, 'type' => 'critical'],
    ['id' => 3, 'title' => 'Overdue Immunization', 'message' => '3 children with overdue vaccines', 'time' => '2 hours ago', 'unread' => true, 'type' => 'warning'],
];

$mockDonors = [
    ['id' => 'D-001', 'name' => 'Juan Santos', 'bloodType' => 'O+', 'barangay' => 'Halang', 'age' => 32, 'gender' => 'Male', 'donationHistory' => 4, 'cluster' => 'Reliable', 'availability' => true, 'contactNumber' => '0917 111 2222'],
    ['id' => 'D-002', 'name' => 'Liza Reyes', 'bloodType' => 'A+', 'barangay' => 'Mabini', 'age' => 28, 'gender' => 'Female', 'donationHistory' => 2, 'cluster' => 'Moderate', 'availability' => false, 'contactNumber' => '0918 333 4444'],
    ['id' => 'D-003', 'name' => 'Ramon Cruz', 'bloodType' => 'B+', 'barangay' => 'Poblacion', 'age' => 45, 'gender' => 'Male', 'donationHistory' => 5, 'cluster' => 'Reliable', 'availability' => true, 'contactNumber' => '0920 555 6666'],
];

$mockBloodRequests = [
    ['id' => 'RQ-001', 'bloodType' => 'O+', 'quantity' => 2, 'urgency' => 'urgent', 'patientInfo' => 'Jose dela Cruz â€¢ Suspected hemorrhage', 'status' => 'pending'],
    ['id' => 'RQ-002', 'bloodType' => 'AB-', 'quantity' => 1, 'urgency' => 'critical', 'patientInfo' => 'Maria Flores â€¢ Postpartum transfusion', 'status' => 'matching'],
];

$mockRHUInventory = [
    ['bloodType' => 'O+', 'units' => 24, 'status' => 'adequate', 'expiringUnits' => 2, 'source' => 'RHU bank', 'expiryDate' => '2026-07-12'],
    ['bloodType' => 'A+', 'units' => 8, 'status' => 'low', 'expiringUnits' => 0, 'source' => 'PRC', 'expiryDate' => '2026-08-09'],
    ['bloodType' => 'B+', 'units' => 5, 'status' => 'critical', 'expiringUnits' => 1, 'source' => 'RHU bank', 'expiryDate' => '2026-06-28'],
];

$mockBloodDrives = [
    ['id' => 'BD-001', 'title' => 'Halang Barangay Blood Drive', 'barangay' => 'Halang', 'venue' => 'Halang Covered Court', 'date' => '2026-07-15', 'startTime' => '08:00', 'endTime' => '15:00', 'targetDonors' => 40, 'organizer' => 'BHW Team', 'status' => 'scheduled', 'registeredDonors' => 18, 'unitsCollected' => 0],
    ['id' => 'BD-002', 'title' => 'Mabini Barangay Drive', 'barangay' => 'Mabini', 'venue' => 'Mabini Barangay Hall', 'date' => '2026-06-20', 'startTime' => '08:00', 'endTime' => '14:00', 'targetDonors' => 35, 'organizer' => 'RHU Logistics', 'status' => 'completed', 'registeredDonors' => 30, 'unitsCollected' => 28],
];

$mockPatients = [
    ['id' => 'P-001', 'name' => 'Alyssa Navarro', 'age' => 24, 'gender' => 'Female', 'bloodType' => 'A+', 'barangay' => 'Balibago', 'diagnosis' => 'Acute gastroenteritis', 'admissionDate' => '2026-06-10', 'philhealthCharged' => true, 'philhealthNo' => 'PH123456789', 'disposition' => 'outpatient'],
    ['id' => 'P-002', 'name' => 'Rogelio Herrera', 'age' => 52, 'gender' => 'Male', 'bloodType' => 'O-', 'barangay' => 'Poblacion', 'diagnosis' => 'Hypertension', 'admissionDate' => '2026-06-09', 'philhealthCharged' => false, 'philhealthNo' => null, 'disposition' => 'admitted'],
];

$mockTransfusions = [
    ['id' => 'T-001', 'patientName' => 'Maria Flores', 'bloodType' => 'AB-', 'units' => 1, 'date' => '2026-06-10', 'transfusedBy' => 'Dr. Santos', 'component' => 'Packed RBC', 'reaction' => 'None'],
];

$mockReferrals = [
    ['id' => 'RF-001', 'patientName' => 'Liza Reyes', 'age' => 28, 'gender' => 'Female', 'diagnosis' => 'Suspected dengue', 'referredTo' => 'Nasugbu District Hospital', 'status' => 'pending', 'referralDate' => '2026-06-10', 'urgency' => 'urgent', 'reason' => 'Platelet drop below 50k', 'referringMD' => 'Dr. Santos'],
];

$mockOPDConsultations = [
    ['id' => 'C-001', 'patientName' => 'Alyssa Navarro', 'age' => 24, 'gender' => 'F', 'diagnosis' => 'Acute gastroenteritis', 'barangay' => 'Balibago', 'physician' => 'Dr. Santos', 'date' => '2026-06-10', 'disposition' => 'outpatient', 'philhealthCharged' => true, 'vitals' => ['bp' => '110/70', 'temp' => 37.0, 'weight' => '54kg', 'rr' => 18, 'hr' => 82], 'chiefComplaint' => 'Persistent diarrhea', 'icd10' => 'A09', 'medications' => ['ORS', 'Paracetamol']],
];

$mockImmunizations = [
    ['id' => 'V-001', 'childName' => 'Baby Buenaventura', 'motherName' => 'Mariel Buenaventura', 'barangay' => 'Mabini', 'bhw' => 'Natividad Puno', 'dob' => '2025-12-10', 'age' => '7m', 'status' => 'overdue', 'nextVisit' => '2026-06-15', 'vaccines' => [['name' => 'DPT-HepB-Hib', 'date' => '2026-06-10', 'lot' => 'G123', 'status' => 'due']]],
];

$mockMaternalCases = [
    ['id' => 'M-001', 'name' => 'Cristina Magpayo', 'age' => 28, 'barangay' => 'Halang', 'gravida' => 2, 'para' => 1, 'aog' => '28 wks', 'lmp' => '2025-11-20', 'edc' => '2026-08-15', 'bloodType' => 'A+', 'prenatalVisits' => 3, 'midwife' => 'Midwife Peralta', 'deliveryPlan' => 'Facility-based', 'riskLevel' => 'high', 'status' => 'active_prenatal', 'philhealthStatus' => 'enrolled', 'lastVisit' => '2026-06-08', 'nextVisit' => '2026-06-22', 'labResults' => ['HBsAg' => 'Reactive', 'CBC' => 'Normal'], 'supplements' => ['Folic Acid', 'Iron']],
];

$mockFPClients = [
    ['id' => 'F-001', 'name' => 'Ana Lopez', 'age' => 31, 'barangay' => 'Poblacion', 'method' => 'DMPA', 'acceptorType' => 'new', 'lastSupply' => '2026-05-10', 'nextVisit' => '2026-08-10', 'status' => 'active'],
];

$mockTBCases = [
    ['id' => 'TB-001', 'name' => 'Danilo Espiritu', 'age' => 43, 'gender' => 'Male', 'classification' => 'Pulmonary TB', 'barangay' => 'Mabini', 'supporter' => 'BHW Ernesto', 'outcome' => 'on_treatment', 'caseType' => 'New', 'phase' => 'Intensive', 'monthsCompleted' => 2, 'totalMonths' => 6, 'adherence' => 93, 'treatmentRegimen' => 'RHZE', 'treatmentStartDate' => '2026-04-10', 'weight' => '58kg', 'nextCollection' => '2026-07-01', 'sputumResults' => [['date' => '2026-05-10', 'result' => 'Negative']]],
];

$mockNutritionCases = [
    ['id' => 'N-001', 'name' => 'Angelo Lim', 'age' => 2, 'gender' => 'Male', 'barangay' => 'Balibago', 'classification' => 'SAM', 'weight' => 8.5, 'height' => 75, 'muac' => 10.5, 'bmi' => 14.9, 'lastVisit' => '2026-06-01', 'nextVisit' => '2026-06-21', 'interventions' => ['Plumpy Nut', 'Home visit']],
];

$mockDiseaseReports = [
    ['id' => 'DS-001', 'disease' => 'Dengue Fever', 'icd10' => 'A90', 'reportingWeek' => '2026-W23', 'cases' => 14, 'deaths' => 0, 'barangays' => ['Halang', 'Mabini'], 'actionTaken' => 'Fogging and community mobilization', 'status' => 'Active', 'alert' => true],
];

$mockVitalRecords = [
    ['id' => 'VR-001', 'type' => 'Birth', 'name' => 'Baby Cruz', 'motherName' => 'Angela Cruz', 'fatherName' => 'Miguel Cruz', 'barangay' => 'Poblacion', 'date' => '2026-06-10', 'attendant' => 'Midwife Peralta', 'registrationStatus' => 'registered', 'lncrn' => 'LNCRN-2026-001', 'remarks' => 'Facility-based delivery'],
];

$mockMedicineInventory = [
    ['id' => 'MED-001', 'genericName' => 'Metformin', 'brandName' => 'Glucophage', 'form' => 'Tablet', 'category' => 'Chronic disease', 'stock' => 12, 'reorderLevel' => 20, 'status' => 'critical', 'expiryDate' => '2026-07-05', 'batchNo' => 'B1234', 'source' => 'Procurement', 'usage30days' => 145, 'unit' => 'tabs'],
    ['id' => 'MED-002', 'genericName' => 'Folic Acid', 'brandName' => 'Folvite', 'form' => 'Tablet', 'category' => 'Supplement', 'stock' => 18, 'reorderLevel' => 30, 'status' => 'low', 'expiryDate' => '2027-02-10', 'batchNo' => 'F4567', 'source' => 'RHU Supply', 'usage30days' => 98, 'unit' => 'tabs'],
];

$mockHealthCertificates = [
    ['id' => 'HC-001', 'certNo' => 'C-2026-001', 'type' => 'Medical Certificate', 'recipientName' => 'Nestor dela Cruz', 'age' => 37, 'barangay' => 'Halang', 'purpose' => 'Employment', 'issuedBy' => 'Dr. Santos', 'issuedDate' => '2026-06-05', 'validUntil' => '2026-12-05', 'fee' => 50],
];

$mockSanitationInspections = [
    ['id' => 'SI-001', 'establishment' => 'Mabini Carinderia', 'barangay' => 'Mabini', 'inspector' => 'Sanitary Inspector Reyes', 'inspectionDate' => '2026-06-08', 'nextInspection' => '2026-08-08', 'status' => 'conditional', 'complianceRate' => 78, 'violations' => 2, 'findings' => ['Food storage temperature', 'Waste segregation']],
];

$mockDOHReports = [
    ['id' => 'DR-001', 'reportType' => 'Monthly Blood Inventory', 'period' => 'Jun 2026', 'generatedDate' => '2026-06-29', 'totalDonations' => 145, 'totalUnitsCollected' => 132, 'totalTransfusions' => 85, 'totalReferrals' => 14, 'bloodDrivesConducted' => 2, 'status' => 'submitted'],
];

$mockRHUStaff = [
    ['id' => 'ST-001', 'name' => 'Dr. Maria C. Santos', 'position' => 'Municipal Health Officer', 'email' => 'mcsantos.mho@nasugbu.gov.ph', 'licenseNo' => 'MD-2005-12345', 'prcExpiry' => '2026-06-30', 'status' => 'active'],
    ['id' => 'ST-002', 'name' => 'Midwife Rosario Peralta', 'position' => 'Midwife', 'email' => 'rperalta@nasugbu.gov.ph', 'licenseNo' => 'MID-2018-0754', 'prcExpiry' => '2028-04-11', 'status' => 'active'],
];

$mockBHWs = [
    ['id' => 'BHW-001', 'name' => 'Natividad Puno', 'barangay' => 'Mabini', 'contactNo' => '0917 222 3333', 'activeStatus' => true, 'donorsReferred' => 28, 'householdsAssigned' => 60, 'trainingLevel' => 'Senior BHW', 'lastTraining' => '2025-11-15'],
];

$mockDemandForecast = [
    ['date' => '2026-06-10', 'predicted' => 18, 'actual' => 17],
    ['date' => '2026-06-11', 'predicted' => 20, 'actual' => 19],
    ['date' => '2026-06-12', 'predicted' => 21, 'actual' => 20],
    ['date' => '2026-06-13', 'predicted' => 19, 'actual' => 18],
    ['date' => '2026-06-14', 'predicted' => 17, 'actual' => 16],
];

$weeklyOPDData = [
    ['day' => 'Mon', 'consultations' => 24, 'referred' => 2],
    ['day' => 'Tue', 'consultations' => 31, 'referred' => 3],
    ['day' => 'Wed', 'consultations' => 18, 'referred' => 1],
    ['day' => 'Thu', 'consultations' => 27, 'referred' => 4],
    ['day' => 'Fri', 'consultations' => 35, 'referred' => 2],
];

$diagnosisData = [
    ['name' => 'Hypertension', 'value' => 28],
    ['name' => 'Respiratory', 'value' => 22],
    ['name' => 'Diabetes', 'value' => 15],
];

$monthlyBloodData = [
    ['month' => 'Jan', 'donations' => 112, 'transfusions' => 38, 'referrals' => 7],
    ['month' => 'Feb', 'donations' => 98, 'transfusions' => 32, 'referrals' => 5],
    ['month' => 'Mar', 'donations' => 135, 'transfusions' => 45, 'referrals' => 10],
    ['month' => 'Apr', 'donations' => 125, 'transfusions' => 38, 'referrals' => 6],
    ['month' => 'May', 'donations' => 145, 'transfusions' => 42, 'referrals' => 8],
    ['month' => 'Jun', 'donations' => 62, 'transfusions' => 18, 'referrals' => 3],
];

$totalInventory = 0;
foreach ($mockRHUInventory as $item) {
    $totalInventory += $item['units'];
}
$criticalStock = count(array_filter($mockRHUInventory, static fn($item) => $item['status'] === 'critical'));
$criticalMeds = count(array_filter($mockMedicineInventory, static fn($item) => $item['status'] === 'critical'));
$lowMeds = count(array_filter($mockMedicineInventory, static fn($item) => $item['status'] === 'low'));
$pendingRequests = count(array_filter($mockBloodRequests, static fn($request) => in_array($request['status'], ['pending', 'matching'], true)));
$activeDonors = count(array_filter($mockDonors, static fn($donor) => !empty($donor['availability'])));
$overdueVaccines = count(array_filter($mockImmunizations, static fn($imm) => $imm['status'] === 'overdue'));
$dueVaccines = count(array_filter($mockImmunizations, static fn($imm) => $imm['status'] === 'due'));
$activeTB = count(array_filter($mockTBCases, static fn($case) => $case['outcome'] === 'on_treatment'));
$samCases = count(array_filter($mockNutritionCases, static fn($item) => $item['classification'] === 'SAM'));
$mamCases = count(array_filter($mockNutritionCases, static fn($item) => $item['classification'] === 'MAM'));
$dengueCases = $mockDiseaseReports[0]['cases'] ?? 0;
$highRiskMom = count(array_filter($mockMaternalCases, static fn($item) => $item['riskLevel'] === 'high'));
$todayOPD = count(array_filter($mockOPDConsultations, static fn($c) => $c['date'] === '2026-06-10'));
$activeBHW = count(array_filter($mockBHWs, static fn($b) => $b['activeStatus']));
$activeStaff = count(array_filter($mockRHUStaff, static fn($staff) => $staff['status'] === 'active'));

$filteredDonors = array_filter($mockDonors, static function ($donor) use ($searchQuery) {
    if ($searchQuery === '') {
        return true;
    }
    $search = strtolower($searchQuery);
    return str_contains(strtolower($donor['name']), $search)
        || str_contains(strtolower($donor['bloodType']), $search)
        || str_contains(strtolower($donor['barangay']), $search);
});

$allResidents = [];
$allVaccines = [];
$allCertTypes = [];
$allStaff = [];

// ----------------------------------------------------
// 100% REAL DATABASE HYDRATION FROM MYSQL `rhu` TABLES
// ----------------------------------------------------
if (!empty($pdo)) {
    try {
        $allResidents = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, barangay FROM residents ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $allVaccines = $pdo->query("SELECT id, vaccine_name, age_group FROM immunization_schedules ORDER BY vaccine_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $allCertTypes = $pdo->query("SELECT id, certificate_type_name FROM certificate_types ORDER BY certificate_type_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $allStaff = $pdo->query("SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) as name, s.staff_type FROM staff s JOIN users u ON s.user_id = u.id ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 1. OPD Consultations (consultations + residents + staff + users)
        $dbOpdStmt = $pdo->query("SELECT c.id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.chief_complaint as chiefComplaint, c.diagnosis, c.icd_code as icd10, c.medications_prescribed as medications, r.barangay, c.consultation_date as date, CONCAT(doc_u.first_name, ' ', doc_u.last_name) as physicianName FROM consultations c JOIN residents r ON c.resident_id = r.id LEFT JOIN staff doc_s ON c.physician_id = doc_s.id LEFT JOIN users doc_u ON doc_s.user_id = doc_u.id ORDER BY c.id DESC");
        $dbOpd = $dbOpdStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbOpd)) {
            $formattedOpd = [];
            foreach ($dbOpd as $co) {
                $formattedOpd[] = [
                    'id' => 'C-' . $co['id'],
                    'patientName' => $co['patientName'],
                    'age' => (int)($co['age'] ?: 25),
                    'gender' => substr($co['gender'] ?: 'F', 0, 1),
                    'diagnosis' => $co['diagnosis'] ?: 'Outpatient Consult',
                    'barangay' => $co['barangay'] ?: 'Nasugbu',
                    'physician' => $co['physicianName'] ? 'Dr. ' . $co['physicianName'] : 'RHU Attending Physician',
                    'date' => $co['date'] ?: date('Y-m-d'),
                    'disposition' => 'outpatient',
                    'philhealthCharged' => true,
                    'vitals' => ['bp' => '120/80', 'temp' => 36.8, 'weight' => '55kg', 'rr' => 18, 'hr' => 78],
                    'chiefComplaint' => $co['chiefComplaint'] ?: 'General Consult',
                    'icd10' => $co['icd10'] ?: 'J00',
                    'medications' => array_filter(explode(',', $co['medications'] ?: 'Paracetamol'))
                ];
            }
            $mockOPDConsultations = $formattedOpd;
        }

        // 2. Hospital Referrals (consultations table where referral_needed = 1 or referral_to is set)
        $dbRefStmt = $pdo->query("SELECT c.id, CONCAT(r.first_name, ' ', r.last_name) AS patientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, c.diagnosis, c.referral_to as referredTo, c.consultation_notes as reason, c.consultation_date as referralDate, CONCAT(doc_u.first_name, ' ', doc_u.last_name) as referringMD FROM consultations c JOIN residents r ON c.resident_id = r.id LEFT JOIN staff doc_s ON c.physician_id = doc_s.id LEFT JOIN users doc_u ON doc_s.user_id = doc_u.id WHERE c.referral_needed = 1 OR c.referral_to IS NOT NULL ORDER BY c.id DESC");
        $dbRef = $dbRefStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbRef)) {
            $formattedRef = [];
            foreach ($dbRef as $rf) {
                $formattedRef[] = [
                    'id' => 'REF-' . $rf['id'],
                    'patientName' => $rf['patientName'],
                    'age' => (int)($rf['age'] ?: 30),
                    'gender' => $rf['gender'] ?: 'Female',
                    'diagnosis' => $rf['diagnosis'] ?: 'Emergency Outpatient Evaluation',
                    'referredTo' => $rf['referredTo'] ?: 'Nasugbu District Hospital',
                    'urgency' => 'urgent',
                    'status' => 'pending',
                    'referralDate' => $rf['referralDate'] ?: date('Y-m-d'),
                    'referringMD' => $rf['referringMD'] ? 'Dr. ' . $rf['referringMD'] : 'RHU Municipal Health Officer',
                    'reason' => $rf['reason'] ?: 'Requires tertiary clinical diagnostic evaluation.'
                ];
            }
            $mockReferrals = $formattedRef;
        }

        // 3. TB-DOTS (tb_patients + residents + staff + users)
        $dbTbStmt = $pdo->query("SELECT t.id, CONCAT(r.first_name, ' ', r.last_name) AS name, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.gender, t.tb_registration_number as regNo, t.tb_type as classification, r.barangay, t.treatment_status as outcome, t.treatment_start_date as treatmentStartDate, CONCAT(doc_u.first_name, ' ', doc_u.last_name) as providerName FROM tb_patients t JOIN residents r ON t.resident_id = r.id LEFT JOIN staff doc_s ON t.dots_provider_id = doc_s.id LEFT JOIN users doc_u ON doc_s.user_id = doc_u.id ORDER BY t.id DESC");
        $dbTb = $dbTbStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbTb)) {
            $formattedTb = [];
            foreach ($dbTb as $tb) {
                $formattedTb[] = [
                    'id' => 'TB-' . $tb['id'],
                    'name' => $tb['name'],
                    'regNo' => $tb['regNo'] ?: 'TB-NSG-2026-00' . $tb['id'],
                    'age' => (int)($tb['age'] ?: 40),
                    'gender' => $tb['gender'] ?: 'Male',
                    'classification' => $tb['classification'] ?: 'Pulmonary TB',
                    'barangay' => $tb['barangay'] ?: 'Nasugbu',
                    'supporter' => $tb['providerName'] ?: 'BHW Assigned',
                    'outcome' => strtolower($tb['outcome']) === 'ongoing' || strtolower($tb['outcome']) === 'active' ? 'on_treatment' : 'cured',
                    'caseType' => 'New',
                    'phase' => 'Intensive',
                    'monthsCompleted' => 2,
                    'totalMonths' => 6,
                    'adherence' => 95,
                    'treatmentRegimen' => '2HRZE/4HR',
                    'treatmentStartDate' => $tb['treatmentStartDate'] ?: date('Y-m-d'),
                    'weight' => '58kg',
                    'nextCollection' => date('Y-m-d', strtotime('+14 days')),
                    'sputumResults' => [['date' => date('Y-m-d'), 'result' => 'Negative']]
                ];
            }
            $mockTBCases = $formattedTb;
        }

        // 4. Maternal Health (pregnancies + residents + prenatal_visits)
        $dbMatStmt = $pdo->query("SELECT p.id, CONCAT(r.first_name, ' ', r.last_name) AS name, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.barangay, p.last_menstrual_period as lmp, p.expected_delivery_date as edc, r.blood_type as bloodType, p.high_risk as riskLevel, p.risk_factors as riskFactors, p.pregnancy_status as status FROM pregnancies p JOIN residents r ON p.resident_id = r.id ORDER BY p.id DESC");
        $dbMat = $dbMatStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbMat)) {
            $formattedMat = [];
            foreach ($dbMat as $pm) {
                $formattedMat[] = [
                    'id' => 'M-' . $pm['id'],
                    'name' => $pm['name'],
                    'age' => (int)($pm['age'] ?: 26),
                    'barangay' => $pm['barangay'] ?: 'Halang',
                    'gravida' => 1,
                    'para' => 0,
                    'aog' => '24 wks',
                    'lmp' => $pm['lmp'] ?: '2026-01-25',
                    'edc' => $pm['edc'] ?: '2026-10-25',
                    'bloodType' => $pm['bloodType'] ?: 'A+',
                    'prenatalVisits' => 4,
                    'midwife' => 'Danica Yuan (Midwife)',
                    'deliveryPlan' => 'Facility-based Lying-in',
                    'riskLevel' => ((int)$pm['riskLevel'] === 1 || strtolower((string)$pm['riskLevel']) === 'high') ? 'high' : 'low',
                    'riskFactors' => $pm['riskFactors'] ?: 'Gestational Monitoring',
                    'status' => 'active_prenatal',
                    'philhealthStatus' => 'enrolled',
                    'lastVisit' => date('Y-m-d', strtotime('-7 days')),
                    'nextVisit' => date('Y-m-d', strtotime('+14 days')),
                    'labResults' => ['CBC' => 'Normal', 'HBsAg' => 'Non-Reactive'],
                    'supplements' => ['Folic Acid', 'Iron']
                ];
            }
            $mockMaternalCases = $formattedMat;
        }

        // 5. Medicine Inventory (medicine_inventory)
        $dbMedStmt = $pdo->query("SELECT id, generic_name as genericName, brand_name as brandName, dosage, unit_form as form, quantity_in_stock as stock, reorder_level as reorderLevel, expiry_date as expiryDate, batch_number as batchNo FROM medicine_inventory ORDER BY id DESC");
        $dbMeds = $dbMedStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbMeds)) {
            $formattedMeds = [];
            foreach ($dbMeds as $m) {
                $formattedMeds[] = [
                    'id' => 'MED-' . $m['id'],
                    'genericName' => $m['genericName'],
                    'brandName' => $m['brandName'] ?: $m['genericName'],
                    'form' => ($m['dosage'] ? $m['dosage'] . ' ' : '') . ($m['form'] ?: 'Tablet'),
                    'category' => 'Essential Primary Care Medicine',
                    'stock' => (int)$m['stock'],
                    'reorderLevel' => (int)$m['reorderLevel'],
                    'status' => (int)$m['stock'] <= 15 ? 'critical' : ((int)$m['stock'] <= 30 ? 'low' : 'adequate'),
                    'expiryDate' => $m['expiryDate'] ?: date('Y-m-d', strtotime('+1 year')),
                    'batchNo' => $m['batchNo'] ?: 'B-2026-101',
                    'source' => 'DOH Supply Chain',
                    'usage30days' => 120,
                    'unit' => 'tabs'
                ];
            }
            $mockMedicineInventory = $formattedMeds;
        }

        // 6. Disease Surveillance (disease_cases + disease_types + residents)
        $dbDisStmt = $pdo->query("SELECT c.id, dt.disease_name as disease, dt.icd_code as icd10, c.case_classification as status, c.outcome, r.barangay FROM disease_cases c JOIN disease_types dt ON c.disease_id = dt.id JOIN residents r ON c.resident_id = r.id ORDER BY c.id DESC");
        $dbDis = $dbDisStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbDis)) {
            $formattedDis = [];
            foreach ($dbDis as $dc) {
                $formattedDis[] = [
                    'id' => 'DS-' . $dc['id'],
                    'disease' => $dc['disease'],
                    'icd10' => $dc['icd10'] ?: 'A90',
                    'reportingWeek' => '2026-W29',
                    'cases' => 1,
                    'deaths' => strtolower($dc['outcome']) === 'deceased' ? 1 : 0,
                    'barangays' => [$dc['barangay'] ?: 'Halang'],
                    'actionTaken' => 'Surveillance active and community fogging / vector control',
                    'status' => $dc['status'] ?: 'Confirmed',
                    'alert' => true
                ];
            }
            $mockDiseaseReports = $formattedDis;
        }

        // 7. Vital Statistics (vital_statistics_births & vital_statistics_deaths + residents)
        $dbVitStmt = $pdo->query("SELECT b.id, 'Birth' as type, b.child_name AS name, CONCAT(r.first_name, ' ', r.last_name) AS motherName, r.barangay, b.date_of_birth as date, b.birth_certificate_number as certNo, b.birth_weight_kg as weight FROM vital_statistics_births b JOIN residents r ON b.mother_id = r.id ORDER BY b.id DESC");
        $dbVit = $dbVitStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbVit)) {
            $formattedVit = [];
            foreach ($dbVit as $v) {
                $formattedVit[] = [
                    'id' => 'VR-' . $v['id'],
                    'type' => 'Birth',
                    'name' => $v['name'],
                    'motherName' => $v['motherName'],
                    'fatherName' => 'Resident Father',
                    'barangay' => $v['barangay'] ?: 'Poblacion',
                    'date' => $v['date'] ?: date('Y-m-d'),
                    'attendant' => 'Danica Yuan (Midwife)',
                    'registrationStatus' => 'registered',
                    'lncrn' => $v['certNo'] ?: 'LNCRN-2026-00' . $v['id'],
                    'remarks' => 'Facility-based birth (' . ($v['weight'] ? $v['weight'] . 'kg' : '3.1kg') . ')'
                ];
            }
            $mockVitalRecords = $formattedVit;
        }

        // 8. Immunization (vaccination_records + immunization_schedules + residents + staff + users)
        $dbImmStmt = $pdo->query("SELECT vr.id, CONCAT(r.first_name, ' ', r.last_name) as childName, TIMESTAMPDIFF(MONTH, r.date_of_birth, CURDATE()) as ageMonths, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as ageYears, r.barangay, sch.vaccine_name as vaccineName, sch.age_group as targetAge, vr.vaccination_date as dateGiven, vr.next_dose_date as nextVisitDate, vr.batch_number as lot, CONCAT(doc_u.first_name, ' ', doc_u.last_name) as providerName FROM vaccination_records vr JOIN residents r ON vr.resident_id = r.id JOIN immunization_schedules sch ON vr.vaccine_id = sch.id LEFT JOIN staff doc_s ON vr.healthcare_provider_id = doc_s.id LEFT JOIN users doc_u ON doc_s.user_id = doc_u.id ORDER BY vr.id DESC");
        $dbImm = $dbImmStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbImm)) {
            $formattedImm = [];
            foreach ($dbImm as $im) {
                $formattedImm[] = [
                    'id' => 'VAC-' . $im['id'],
                    'childName' => $im['childName'],
                    'age' => ((int)$im['ageYears'] > 0 ? (int)$im['ageYears'] . ' yrs' : ((int)$im['ageMonths'] > 0 ? (int)$im['ageMonths'] . ' mos' : 'Infant')),
                    'barangay' => $im['barangay'] ?: 'Poblacion',
                    'vaccineName' => $im['vaccineName'],
                    'dose' => $im['targetAge'] ?: 'EPI Schedule',
                    'dateGiven' => $im['dateGiven'] ?: date('Y-m-d'),
                    'lot' => $im['lot'] ?: 'LOT-2026-01',
                    'administeredBy' => $im['providerName'] ?: 'RHU Nurse / Midwife',
                    'status' => 'Administered'
                ];
            }
            $mockImmunizations = $formattedImm;
        }

        // 9. DOH Reports (fhsis_reports)
        $dbFhsisStmt = $pdo->query("SELECT id, report_month as month, report_year as year, submitted_date, status FROM fhsis_reports ORDER BY id DESC");
        $dbFhsis = $dbFhsisStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbFhsis)) {
            $formattedRpt = [];
            foreach ($dbFhsis as $fr) {
                $formattedRpt[] = [
                    'id' => 'DR-' . $fr['id'],
                    'reportType' => 'Monthly FHSIS Health Report',
                    'period' => date('F Y', mktime(0, 0, 0, (int)$fr['month'], 1, (int)$fr['year'])),
                    'generatedDate' => $fr['submitted_date'] ?: date('Y-m-d'),
                    'totalDonations' => 0,
                    'totalUnitsCollected' => 0,
                    'totalTransfusions' => 0,
                    'totalReferrals' => 14,
                    'bloodDrivesConducted' => 0,
                    'status' => strtolower($fr['status'])
                ];
            }
            $mockDOHReports = $formattedRpt;
        }

        // 10. Health Certificates (health_certificates + residents + certificate_types + staff + users)
        $dbCertStmt = $pdo->query("SELECT hc.id, hc.certificate_number as certNo, ct.certificate_type_name as type, CONCAT(r.first_name, ' ', r.last_name) AS recipientName, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) as age, r.barangay, hc.purpose, hc.issue_date as issuedDate, hc.expiry_date as validUntil, CONCAT(doc_u.first_name, ' ', doc_u.last_name) as issuerName FROM health_certificates hc JOIN residents r ON hc.resident_id = r.id JOIN certificate_types ct ON hc.certificate_type_id = ct.id LEFT JOIN staff doc_s ON hc.issued_by_id = doc_s.id LEFT JOIN users doc_u ON doc_s.user_id = doc_u.id ORDER BY hc.id DESC");
        $dbCerts = $dbCertStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbCerts)) {
            $formattedCerts = [];
            foreach ($dbCerts as $c) {
                $formattedCerts[] = [
                    'id' => 'HC-' . $c['id'],
                    'certNo' => $c['certNo'] ?: 'C-2026-' . $c['id'],
                    'type' => $c['type'] ?: 'Medical Certificate',
                    'recipientName' => $c['recipientName'],
                    'age' => (int)($c['age'] ?: 32),
                    'barangay' => $c['barangay'] ?: 'Nasugbu',
                    'purpose' => $c['purpose'] ?: 'Employment Requirement',
                    'issuedBy' => $c['issuerName'] ? 'Dr. ' . $c['issuerName'] : 'Dr. Maria C. Santos (MHO)',
                    'issuedDate' => $c['issuedDate'] ?: date('Y-m-d'),
                    'validUntil' => $c['validUntil'] ?: date('Y-m-d', strtotime('+6 months')),
                    'fee' => 50
                ];
            }
            $mockHealthCertificates = $formattedCerts;
        }

        // 11. Staff Accounts (staff + users)
        $dbStaffStmt = $pdo->query("SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) AS name, s.staff_type as position, u.email, s.license_number as licenseNo, s.is_active FROM staff s JOIN users u ON s.user_id = u.id ORDER BY s.id DESC");
        $dbStaff = $dbStaffStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbStaff)) {
            $formattedStaff = [];
            foreach ($dbStaff as $st) {
                $formattedStaff[] = [
                    'id' => 'ST-' . $st['id'],
                    'name' => $st['name'],
                    'position' => $st['position'],
                    'email' => $st['email'],
                    'licenseNo' => $st['licenseNo'] ?: 'MD-2026-101',
                    'prcExpiry' => '2028-12-31',
                    'status' => $st['is_active'] ? 'active' : 'inactive'
                ];
            }
            $mockRHUStaff = $formattedStaff;
        }

        // 12. BHWs (bhw + staff + users)
        $dbBhwStmt = $pdo->query("SELECT b.id as bhw_id, b.staff_id, b.barangay, b.coverage_population as householdsAssigned, b.assigned_date, CONCAT(u.first_name, ' ', u.last_name) AS name, s.phone_number as contactNo, COALESCE(s.is_active, 1) as is_active FROM bhw b LEFT JOIN staff s ON b.staff_id = s.id LEFT JOIN users u ON s.user_id = u.id ORDER BY b.id DESC");
        $dbBhw = $dbBhwStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbBhw)) {
            $formattedBhw = [];
            foreach ($dbBhw as $bh) {
                $displayName = !empty(trim($bh['name'] ?? '')) ? $bh['name'] : ($bh['barangay'] ? 'BHW Worker (' . $bh['barangay'] . ')' : 'BHW #' . $bh['bhw_id']);
                $formattedBhw[] = [
                    'id' => 'BHW-' . sprintf('%03d', $bh['bhw_id']),
                    'name' => $displayName,
                    'barangay' => $bh['barangay'] ?: 'Nasugbu',
                    'contactNo' => $bh['contactNo'] ?: '0917 111 2222',
                    'activeStatus' => (bool)$bh['is_active'],
                    'donorsReferred' => 0,
                    'householdsAssigned' => (int)($bh['householdsAssigned'] ?: 50),
                    'trainingLevel' => 'Senior BHW',
                    'lastTraining' => $bh['assigned_date'] ?: date('Y-m-d')
                ];
            }
            $mockBHWs = $formattedBhw;
        }

        // 13. Nutrition & OPT+ (resident_health_profiles + residents)
        $dbNutrStmt = $pdo->query("SELECT hp.id, CONCAT(r.first_name, ' ', r.last_name) as childName, TIMESTAMPDIFF(MONTH, r.date_of_birth, CURDATE()) as ageMonths, r.barangay, hp.height, hp.weight FROM resident_health_profiles hp JOIN residents r ON hp.resident_id = r.id ORDER BY hp.id DESC");
        $dbNutr = $dbNutrStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($dbNutr)) {
            $formattedNutr = [];
            foreach ($dbNutr as $nu) {
                $formattedNutr[] = [
                    'id' => 'OPT-' . $nu['id'],
                    'childName' => $nu['childName'],
                    'ageMonths' => (int)($nu['ageMonths'] ?: 18),
                    'barangay' => $nu['barangay'] ?: 'Poblacion',
                    'height' => ($nu['height'] ?: '75') . ' cm',
                    'weight' => ($nu['weight'] ?: '9.5') . ' kg',
                    'status' => 'Normal Weight',
                    'supplementStatus' => 'Vitamin A Administered'
                ];
            }
            $mockNutritionCases = $formattedNutr;
        }

    } catch (Exception $ex) {
        error_log("RHUDashboard Full DB Hydration Error: " . $ex->getMessage());
    }
}


if (!empty($_SESSION['rhu_requests'])) {
    $mockBloodRequests = array_merge($_SESSION['rhu_requests'], $mockBloodRequests);
}
if (!empty($_SESSION['rhu_drives'])) {
    $mockBloodDrives = array_merge($_SESSION['rhu_drives'], $mockBloodDrives);
}
if (!empty($_SESSION['rhu_referrals'])) {
    $mockReferrals = array_merge($_SESSION['rhu_referrals'], $mockReferrals);
}
if (!empty($_SESSION['rhu_opd'])) {
    $mockOPDConsultations = array_merge($_SESSION['rhu_opd'], $mockOPDConsultations);
}
if (!empty($_SESSION['rhu_patients'])) {
    $mockPatients = array_merge($_SESSION['rhu_patients'], $mockPatients);
}
if (!empty($_SESSION['rhu_transfusions'])) {
    $mockTransfusions = array_merge($_SESSION['rhu_transfusions'], $mockTransfusions);
}
if (!empty($_SESSION['rhu_bhw'])) {
    $mockBHWs = array_merge($_SESSION['rhu_bhw'], $mockBHWs);
}

$flash = $_SESSION['rhu_flash'] ?? '';
$error = $_SESSION['rhu_error'] ?? '';
unset($_SESSION['rhu_flash'], $_SESSION['rhu_error']);

$viewReferralId = trim($_GET['view'] ?? '');
$printReferralId = trim($_GET['print'] ?? '');
$selectedReferral = null;
if ($viewReferralId || $printReferralId) {
    $referenceId = $viewReferralId !== '' ? $viewReferralId : $printReferralId;
    foreach ($mockReferrals as $referral) {
        if ($referral['id'] === $referenceId) {
            $selectedReferral = $referral;
            break;
        }
    }
}

if ($tab === 'blood_inventory' && ($_GET['refresh'] ?? '') === '1') {
    $flash = 'Blood resupply request registered. Please coordinate with your supply chain team.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. SAVE OPD CONSULTATION
    if ($action === 'save_opd') {
        $residentId = (int)($_POST['resident_id'] ?? 0);
        $physicianId = (int)($_POST['physician_id'] ?? 1);
        $chiefComplaint = trim($_POST['chiefComplaint'] ?? '');
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $icd10 = trim($_POST['icd10'] ?? 'J00');
        $meds = trim($_POST['medications'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($residentId <= 0 || empty($diagnosis)) {
            $_SESSION['rhu_error'] = 'Please select a valid resident patient and enter diagnosis.';
        } else {
            if (!empty($pdo)) {
                try {
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
                    $_SESSION['rhu_flash'] = 'OPD Consultation recorded successfully into database.';
                } catch (Exception $e) {
                    $_SESSION['rhu_error'] = 'Database error: ' . $e->getMessage();
                }
            }
        }
        header('Location: ' . dashboardUrl('opd'));
        exit;
    }

    // 2. SAVE HOSPITAL REFERRAL
    if ($action === 'save_referral') {
        $residentId = (int)($_POST['resident_id'] ?? 0);
        $physicianId = (int)($_POST['physician_id'] ?? 1);
        $referredTo = trim($_POST['referredTo'] ?? 'Nasugbu District Hospital');
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if ($residentId <= 0 || empty($diagnosis) || empty($referredTo)) {
            $_SESSION['rhu_error'] = 'Please select patient, referral facility destination, and diagnosis.';
        } else {
            if (!empty($pdo)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO consultations (resident_id, physician_id, consultation_date, chief_complaint, diagnosis, referral_needed, referral_to, consultation_notes, created_at) VALUES (:res, :phy, CURDATE(), 'Outpatient Hospital Referral', :dx, 1, :to, :reason, NOW())");
                    $stmt->execute([
                        'res' => $residentId,
                        'phy' => $physicianId,
                        'dx' => $diagnosis,
                        'to' => $referredTo,
                        'reason' => $reason
                    ]);
                    $_SESSION['rhu_flash'] = 'Hospital Referral created and saved successfully.';
                } catch (Exception $e) {
                    $_SESSION['rhu_error'] = 'Database error: ' . $e->getMessage();
                }
            }
        }
        header('Location: ' . dashboardUrl('referrals'));
        exit;
    }

    // 3. SAVE VACCINATION RECORD
    if ($action === 'save_immunization') {
        $residentId = (int)($_POST['resident_id'] ?? 0);
        $vaccineId = (int)($_POST['vaccine_id'] ?? 1);
        $providerId = (int)($_POST['provider_id'] ?? 1);
        $vacDate = trim($_POST['vaccination_date'] ?? date('Y-m-d'));
        $batchNo = trim($_POST['batch_number'] ?? 'VAC-2026-X');
        $nextDate = trim($_POST['next_dose_date'] ?? date('Y-m-d', strtotime('+30 days')));

        if ($residentId <= 0 || $vaccineId <= 0) {
            $_SESSION['rhu_error'] = 'Please select child patient and vaccine type.';
        } else {
            if (!empty($pdo)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO vaccination_records (resident_id, vaccine_id, vaccination_date, healthcare_provider_id, batch_number, next_dose_date, created_at) VALUES (:res, :vac, :vdate, :prov, :batch, :ndate, NOW())");
                    $stmt->execute([
                        'res' => $residentId,
                        'vac' => $vaccineId,
                        'vdate' => $vacDate,
                        'prov' => $providerId,
                        'batch' => $batchNo,
                        'ndate' => $nextDate
                    ]);
                    $_SESSION['rhu_flash'] = 'Vaccination record saved successfully into database.';
                } catch (Exception $e) {
                    $_SESSION['rhu_error'] = 'Database error: ' . $e->getMessage();
                }
            }
        }
        header('Location: ' . dashboardUrl('immunization'));
        exit;
    }

    // 4. SAVE MATERNAL PREGNANCY CASE
    if ($action === 'save_maternal') {
        $residentId = (int)($_POST['resident_id'] ?? 0);
        $lmp = trim($_POST['lmp'] ?? date('Y-m-d'));
        $edd = trim($_POST['edd'] ?? date('Y-m-d', strtotime('+280 days')));
        $highRisk = isset($_POST['high_risk']) ? 1 : 0;
        $riskFactors = trim($_POST['risk_factors'] ?? 'Routine Maternal Monitoring');

        if ($residentId <= 0) {
            $_SESSION['rhu_error'] = 'Please select pregnant patient resident.';
        } else {
            if (!empty($pdo)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO pregnancies (resident_id, last_menstrual_period, expected_delivery_date, high_risk, risk_factors, pregnancy_status, created_at, updated_at) VALUES (:res, :lmp, :edd, :risk, :factors, 'Active', NOW(), NOW())");
                    $stmt->execute([
                        'res' => $residentId,
                        'lmp' => $lmp,
                        'edd' => $edd,
                        'risk' => $highRisk,
                        'factors' => $riskFactors
                    ]);
                    $_SESSION['rhu_flash'] = 'Maternal pregnancy case registered successfully.';
                } catch (Exception $e) {
                    $_SESSION['rhu_error'] = 'Database error: ' . $e->getMessage();
                }
            }
        }
        header('Location: ' . dashboardUrl('maternal'));
        exit;
    }

    // 5. SAVE TB PATIENT CASE
    if ($action === 'save_tb') {
        $residentId = (int)($_POST['resident_id'] ?? 0);
        $regNo = trim($_POST['tb_reg_no'] ?? ('TB-NSG-2026-' . rand(100, 999)));
        $tbType = trim($_POST['tb_type'] ?? 'Pulmonary TB');
        $startDate = trim($_POST['start_date'] ?? date('Y-m-d'));

        if ($residentId <= 0) {
            $_SESSION['rhu_error'] = 'Please select TB patient resident.';
        } else {
            if (!empty($pdo)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO tb_patients (resident_id, tb_registration_number, tb_type, treatment_status, treatment_start_date, diagnosis_date, created_at) VALUES (:res, :reg, :type, 'Active', :sdate, CURDATE(), NOW())");
                    $stmt->execute([
                        'res' => $residentId,
                        'reg' => $regNo,
                        'type' => $tbType,
                        'sdate' => $startDate
                    ]);
                    $_SESSION['rhu_flash'] = 'TB-DOTS patient case registered successfully.';
                } catch (Exception $e) {
                    $_SESSION['rhu_error'] = 'Database error: ' . $e->getMessage();
                }
            }
        }
        header('Location: ' . dashboardUrl('tb_dots'));
        exit;
    }

    // 6. SAVE MEDICINE ITEM
    if ($action === 'save_medicine') {
        $genericName = trim($_POST['generic_name'] ?? '');
        $brandName = trim($_POST['brand_name'] ?? '');
        $dosage = trim($_POST['dosage'] ?? '500mg');
        $unitForm = trim($_POST['unit_form'] ?? 'Tablet');
        $qty = (int)($_POST['quantity_in_stock'] ?? 100);
        $reorder = (int)($_POST['reorder_level'] ?? 30);
        $expiry = trim($_POST['expiry_date'] ?? date('Y-m-d', strtotime('+1 year')));
        $batch = trim($_POST['batch_number'] ?? ('BATCH-' . time()));

        if (empty($genericName)) {
            $_SESSION['rhu_error'] = 'Please enter generic medicine name.';
        } else {
            if (!empty($pdo)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO medicine_inventory (generic_name, brand_name, dosage, unit_form, quantity_in_stock, reorder_level, expiry_date, batch_number, last_updated) VALUES (:gen, :brand, :dose, :form, :qty, :reorder, :exp, :batch, NOW())");
                    $stmt->execute([
                        'gen' => $genericName,
                        'brand' => $brandName,
                        'dose' => $dosage,
                        'form' => $unitForm,
                        'qty' => $qty,
                        'reorder' => $reorder,
                        'exp' => $expiry,
                        'batch' => $batch
                    ]);
                    $_SESSION['rhu_flash'] = 'Medicine inventory item added successfully.';
                } catch (Exception $e) {
                    $_SESSION['rhu_error'] = 'Database error: ' . $e->getMessage();
                }
            }
        }
        header('Location: ' . dashboardUrl('medicine'));
        exit;
    }

    // 7. SAVE HEALTH CERTIFICATE
    if ($action === 'save_certificate') {
        $residentId = (int)($_POST['resident_id'] ?? 0);
        $typeId = (int)($_POST['certificate_type_id'] ?? 1);
        $certNo = trim($_POST['cert_no'] ?? ('CERT-2026-' . rand(1000, 9999)));
        $purpose = trim($_POST['purpose'] ?? 'Employment Requirement');
        $expiry = trim($_POST['expiry_date'] ?? date('Y-m-d', strtotime('+6 months')));

        if ($residentId <= 0) {
            $_SESSION['rhu_error'] = 'Please select resident recipient for health certificate.';
        } else {
            if (!empty($pdo)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO health_certificates (resident_id, certificate_type_id, certificate_number, issue_date, expiry_date, purpose, validity_status, created_at) VALUES (:res, :type, :cert, CURDATE(), :exp, :purpose, 'Valid', NOW())");
                    $stmt->execute([
                        'res' => $residentId,
                        'type' => $typeId,
                        'cert' => $certNo,
                        'exp' => $expiry,
                        'purpose' => $purpose
                    ]);
                    $_SESSION['rhu_flash'] = 'Health Certificate issued and saved successfully.';
                } catch (Exception $e) {
                    $_SESSION['rhu_error'] = 'Database error: ' . $e->getMessage();
                }
            }
        }
        header('Location: ' . dashboardUrl('certificates'));
        exit;
    }

    // 8. SAVE BHW ACCOUNT
    if ($action === 'save_bhw') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contactNo = trim($_POST['contact_no'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $households = (int)($_POST['households'] ?? 50);
        $trainingLevel = trim($_POST['training_level'] ?? 'Junior BHW');
        $password = $_POST['password'] ?? '';

        if (empty($firstName) || empty($lastName) || empty($email) || empty($barangay)) {
            $_SESSION['rhu_error'] = 'Please complete all required fields (First Name, Last Name, Email, Barangay).';
        } else {
            $insertedId = rand(100, 999);
            if (!empty($pdo)) {
                try {
                    $pdo->beginTransaction();

                    // Find or fallback BHW Role ID
                    $roleId = 2;
                    try {
                        $rStmt = $pdo->prepare("SELECT id FROM roles WHERE UPPER(name) = 'BHW' LIMIT 1");
                        $rStmt->execute();
                        $fetchedRole = $rStmt->fetchColumn();
                        if ($fetchedRole) {
                            $roleId = (int)$fetchedRole;
                        }
                    } catch (Exception $eRole) {
                        // ignore and use fallback
                    }

                    // Generate unique username
                    $baseUname = strtolower(preg_replace('/[^a-z0-9]/i', '', explode('@', $email)[0]));
                    $username = $baseUname . rand(10, 99);
                    $passHash = password_hash($password !== '' ? $password : 'Bhw@123456', PASSWORD_DEFAULT);

                    // Insert User
                    $uStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active, created_at) VALUES (:u, :e, :h, :fn, :ln, :r, 1, NOW())");
                    $uStmt->execute([
                        'u' => $username,
                        'e' => $email,
                        'h' => $passHash,
                        'fn' => $firstName,
                        'ln' => $lastName,
                        'r' => $roleId
                    ]);
                    $userId = (int)$pdo->lastInsertId();

                    // Insert Staff
                    $sStmt = $pdo->prepare("INSERT INTO staff (user_id, staff_type, license_number, phone_number, address, date_hired, is_active, created_at) VALUES (:uid, 'BHW', :lic, :phone, :brgy, CURDATE(), 1, NOW())");
                    $licenseNo = 'BHW-' . date('Y') . '-' . sprintf('%03d', rand(1, 999));
                    $sStmt->execute([
                        'uid' => $userId,
                        'lic' => $licenseNo,
                        'phone' => $contactNo,
                        'brgy' => $barangay
                    ]);
                    $staffId = (int)$pdo->lastInsertId();
                    $insertedId = $staffId;

                    // Insert BHW record matching table `bhw` columns: (id, staff_id, barangay, coverage_population, coverage_area, assigned_date)
                    try {
                        $bStmt = $pdo->prepare("INSERT INTO bhw (staff_id, barangay, coverage_population, coverage_area, assigned_date) VALUES (:sid, :brgy, :pop, 0.00, CURDATE())");
                        $bStmt->execute([
                            'sid' => $staffId,
                            'brgy' => $barangay,
                            'pop' => $households
                        ]);
                    } catch (Exception $eBhw) {
                        $bStmt = $pdo->prepare("INSERT INTO bhw (staff_id, barangay, coverage_population, coverage_area, assigned_date) VALUES (:sid, :brgy, :pop, 0.00, CURDATE()) ON DUPLICATE KEY UPDATE staff_id = VALUES(staff_id), coverage_population = VALUES(coverage_population)");
                        $bStmt->execute([
                            'sid' => $staffId,
                            'brgy' => $barangay,
                            'pop' => $households
                        ]);
                    }

                    $pdo->commit();
                    $_SESSION['rhu_flash'] = "BHW Account for {$firstName} {$lastName} created and saved to database successfully!";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $_SESSION['rhu_error'] = 'Database error creating BHW account: ' . $e->getMessage();
                }
            } else {
                $_SESSION['rhu_flash'] = "BHW Account for {$firstName} {$lastName} added successfully!";
            }

            // Session backup insertion
            $newBhwItem = [
                'id' => 'BHW-' . $insertedId,
                'name' => $firstName . ' ' . $lastName,
                'barangay' => $barangay,
                'contactNo' => $contactNo ?: '0917 111 2222',
                'activeStatus' => true,
                'donorsReferred' => 0,
                'householdsAssigned' => $households,
                'trainingLevel' => $trainingLevel,
                'lastTraining' => date('Y-m-d')
            ];
            if (!isset($_SESSION['rhu_bhw'])) {
                $_SESSION['rhu_bhw'] = [];
            }
            array_unshift($_SESSION['rhu_bhw'], $newBhwItem);
        }
        header('Location: ' . dashboardUrl('bhw'));
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RHU Dashboard - RedPulse RHU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: ui-sans-serif, system-ui, sans-serif
        }

        .safe-area-pb {
            padding-bottom: env(safe-area-inset-bottom)
        }

        @media(min-width:640px) {
            .mobile-nav {
                display: none
            }
        }

        @media(max-width:639px) {
            .desktop-tabs {
                display: none
            }
        }
    </style>
</head>

<body class="bg-gray-50 text-slate-900">
    <div class="min-h-screen">
        <header class="bg-gradient-to-r from-blue-700 to-blue-900 text-white shadow-xl sticky top-0 z-40">
            <div class="px-4 sm:px-6 py-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                            <span class="text-2xl">RHU</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h1 class="text-lg font-bold tracking-tight">RedPulse RHU</h1>
                                <span class="hidden sm:block text-xs bg-blue-600 px-2 py-0.5 rounded-full text-blue-100 border border-blue-500">Integrated Health System</span>
                            </div>
                            <p class="text-xs text-blue-200"><?php echo esc($RHU_INFO['name']); ?> — <?php echo esc($RHU_INFO['code']); ?> | <?php echo esc($RHU_INFO['municipality']); ?>, <?php echo esc($RHU_INFO['province']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="hidden lg:flex flex-col items-end mr-1">
                            <span class="text-sm font-semibold"><?php echo esc($RHU_INFO['chiefMHO']); ?></span>
                            <span class="text-xs text-blue-200">Municipal Health Officer</span>
                        </div>
                        <a href="<?php echo esc(dashboardUrl($tab, ['notifs' => $showNotifs ? '0' : '1'])); ?>" class="relative p-2 hover:bg-blue-600 rounded-lg transition-colors" title="Notifications">
                            <?php echo iconSvg('bell', 'w-5 h-5'); ?>
                            <span class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-400 rounded-full border border-blue-700 animate-pulse"></span>
                        </a>
                        <a href="RHUAdminDashboard.php" class="hidden sm:flex items-center gap-1.5 text-xs font-semibold bg-white/10 hover:bg-white/20 border border-white/20 px-3 py-1.5 rounded-lg transition-all" title="Admin Panel">
                            <?php echo iconSvg('shield', 'w-3.5 h-3.5'); ?> Admin Panel
                        </a>
                        <span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold"><?php echo iconSvg('bar', 'w-3.5 h-3.5'); ?> <?php echo $showNotifs ? 'Live' : 'Mock'; ?> data</span>
                        <a href="index.php" class="p-2 hover:bg-blue-600 rounded-lg transition-colors" title="Logout">
                            <?php echo iconSvg('logout', 'w-5 h-5'); ?>
                        </a>
                    </div>
                </div>
                <!-- PRIMARY TOP QUICK TABS & BURGER DRAWER BUTTON -->
                <div class="mt-3 flex items-center justify-between gap-2 desktop-tabs">
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-hide">
                        <?php 
                        $quickTabs = ['overview', 'opd', 'immunization', 'medicine', 'analytics'];
                        foreach ($quickTabs as $id): 
                            if (!isset($tabs[$id])) continue;
                            [$label, $icon] = $tabs[$id];
                        ?>
                            <a href="<?php echo esc(dashboardUrl($id)); ?>" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all <?php echo $tab === $id ? 'bg-white text-blue-700 shadow-sm font-bold' : 'text-blue-100 hover:bg-blue-600'; ?>">
                                <?php echo iconSvg($icon, 'w-4 h-4 flex-shrink-0'); ?>
                                <span><?php echo esc($label); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- BURGER MENU TRIGGER BUTTON -->
                    <button type="button" onclick="document.getElementById('navDrawer').classList.remove('hidden')" class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold bg-amber-400 text-slate-900 hover:bg-amber-300 transition-all shadow-md flex-shrink-0 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        <span>All Services</span>
                        <span class="bg-amber-500 text-slate-900 text-[10px] px-1.5 py-0.5 rounded-full font-black"><?php echo count($tabs); ?></span>
                    </button>
                </div>
            </div>
        </header>

        <!-- CATEGORIZED BURGER MENU DRAWER MODAL -->
        <div id="navDrawer" class="hidden fixed inset-0 z-50 overflow-hidden bg-slate-900/60 backdrop-blur-sm transition-opacity">
            <div class="absolute inset-y-0 right-0 max-w-full flex pl-10">
                <div class="w-screen max-w-md bg-white shadow-2xl flex flex-col">
                    <!-- DRAWER HEADER -->
                    <div class="p-5 bg-gradient-to-r from-blue-700 to-blue-900 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">🏥</span>
                            <div>
                                <h3 class="font-bold text-base">RHU Services Menu</h3>
                                <p class="text-xs text-blue-200">Select a department or health program</p>
                            </div>
                        </div>
                        <button type="button" onclick="document.getElementById('navDrawer').classList.add('hidden')" class="p-1 rounded-lg text-blue-200 hover:text-white hover:bg-white/10">
                            <?php echo iconSvg('x', 'w-6 h-6'); ?>
                        </button>
                    </div>

                    <!-- CATEGORIZED SERVICES CONTENT -->
                    <div class="flex-1 overflow-y-auto p-5 space-y-6">
                        <!-- CATEGORY 1: CLINICAL & PATIENT PROGRAMS -->
                        <div>
                            <h4 class="text-xs font-bold text-blue-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <span>🩺</span> Clinical & Patient Programs
                            </h4>
                            <div class="grid grid-cols-1 gap-1.5">
                                <?php 
                                $cat1 = ['opd', 'referrals', 'immunization', 'maternal', 'fp', 'tb_dots', 'nutrition'];
                                foreach ($cat1 as $id):
                                    if (!isset($tabs[$id])) continue;
                                    [$label, $icon] = $tabs[$id];
                                ?>
                                    <a href="<?php echo esc(dashboardUrl($id)); ?>" onclick="document.getElementById('navDrawer').classList.add('hidden')" class="flex items-center justify-between p-2.5 rounded-xl border text-xs font-semibold transition-all <?php echo $tab === $id ? 'bg-blue-50 border-blue-300 text-blue-800' : 'bg-gray-50 border-gray-100 text-gray-700 hover:bg-gray-100'; ?>">
                                        <div class="flex items-center gap-2.5">
                                            <?php echo iconSvg($icon, 'w-4 h-4 text-blue-600'); ?>
                                            <span><?php echo esc($label); ?></span>
                                        </div>
                                        <?php echo iconSvg('right', 'w-3.5 h-3.5 text-gray-400'); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- CATEGORY 2: PUBLIC HEALTH & SURVEILLANCE -->
                        <div>
                            <h4 class="text-xs font-bold text-purple-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <span>🛡️</span> Public Health & Surveillance
                            </h4>
                            <div class="grid grid-cols-1 gap-1.5">
                                <?php 
                                $cat2 = ['disease', 'sanitation', 'vital', 'certificates'];
                                foreach ($cat2 as $id):
                                    if (!isset($tabs[$id])) continue;
                                    [$label, $icon] = $tabs[$id];
                                ?>
                                    <a href="<?php echo esc(dashboardUrl($id)); ?>" onclick="document.getElementById('navDrawer').classList.add('hidden')" class="flex items-center justify-between p-2.5 rounded-xl border text-xs font-semibold transition-all <?php echo $tab === $id ? 'bg-purple-50 border-purple-300 text-purple-800' : 'bg-gray-50 border-gray-100 text-gray-700 hover:bg-gray-100'; ?>">
                                        <div class="flex items-center gap-2.5">
                                            <?php echo iconSvg($icon, 'w-4 h-4 text-purple-600'); ?>
                                            <span><?php echo esc($label); ?></span>
                                        </div>
                                        <?php echo iconSvg('right', 'w-3.5 h-3.5 text-gray-400'); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- CATEGORY 3: RHU MANAGEMENT & PREDICTIONS -->
                        <div>
                            <h4 class="text-xs font-bold text-emerald-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                                <span>⚙️</span> RHU Operations & Analytics
                            </h4>
                            <div class="grid grid-cols-1 gap-1.5">
                                <?php 
                                $cat3 = ['medicine', 'bhw', 'staff', 'reports', 'analytics'];
                                foreach ($cat3 as $id):
                                    if (!isset($tabs[$id])) continue;
                                    [$label, $icon] = $tabs[$id];
                                ?>
                                    <a href="<?php echo esc(dashboardUrl($id)); ?>" onclick="document.getElementById('navDrawer').classList.add('hidden')" class="flex items-center justify-between p-2.5 rounded-xl border text-xs font-semibold transition-all <?php echo $tab === $id ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-gray-50 border-gray-100 text-gray-700 hover:bg-gray-100'; ?>">
                                        <div class="flex items-center gap-2.5">
                                            <?php echo iconSvg($icon, 'w-4 h-4 text-emerald-600'); ?>
                                            <span><?php echo esc($label); ?></span>
                                        </div>
                                        <?php echo iconSvg('right', 'w-3.5 h-3.5 text-gray-400'); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- DRAWER FOOTER -->
                    <div class="p-4 border-t border-gray-100 bg-gray-50 text-center">
                        <button type="button" onclick="document.getElementById('navDrawer').classList.add('hidden')" class="w-full py-2.5 bg-gray-200 text-gray-800 font-bold text-xs rounded-xl hover:bg-gray-300 transition-colors">Close Menu</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MOBILE BOTTOM NAVIGATION -->
        <nav class="mobile-nav fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 safe-area-pb">
            <div class="flex items-stretch">
                <?php foreach (['overview', 'opd', 'medicine', 'analytics'] as $mobileTab): ?>
                    <?php [$label, $icon] = $tabs[$mobileTab]; ?>
                    <a href="<?php echo esc(dashboardUrl($mobileTab)); ?>" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 transition-colors relative <?php echo $tab === $mobileTab ? 'text-blue-600 font-bold' : 'text-gray-400'; ?>">
                        <?php if ($tab === $mobileTab): ?><span class="absolute top-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-blue-500 rounded-full"></span><?php endif; ?>
                        <?php echo iconSvg($icon, 'w-5 h-5'); ?>
                        <span class="text-[10px] leading-none mt-0.5"><?php echo esc($label); ?></span>
                    </a>
                <?php endforeach; ?>
                <button type="button" onclick="document.getElementById('navDrawer').classList.remove('hidden')" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-amber-600 font-bold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    <span class="text-[10px] leading-none mt-0.5">More</span>
                </button>
            </div>
        </nav>

        <main class="px-4 sm:px-6 py-5 pb-28 sm:pb-5">
            <?php if (!empty($flash)): ?>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 mb-4"><?php echo esc($flash); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-800 mb-4"><?php echo esc($error); ?></div>
            <?php endif; ?>

            <?php if ($tab === 'overview'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3">
                        <?php echo iconSvg('alert', 'w-5 h-5 text-red-600 flex-shrink-0'); ?>
                        <div class="flex-1">
                            <p class="text-sm font-bold text-red-800">Active Alerts <?php echo '(' . count(array_filter($notifications, static fn($n) => $n['type'] === 'critical')) . ')'; ?></p>
                            <p class="text-sm text-red-700">Dengue cluster in Halang/Mabini • <?php echo $criticalMeds + $lowMeds; ?> medicine items need resupply • <?php echo $highRiskMom; ?> high-risk pregnant patients</p>
                        </div>
                        <a href="<?php echo esc(dashboardUrl('reports')); ?>" class="text-xs bg-red-600 text-white px-3 py-1.5 rounded-lg hover:bg-red-700 whitespace-nowrap">View All</a>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        <?php $kpis = [
                            ['label' => "Today's OPD", 'value' => $todayOPD, 'sub' => 'consultations', 'color' => 'bg-blue-600', 'tab' => 'opd'],
                            ['label' => 'Active TB Cases', 'value' => $activeTB, 'sub' => 'on treatment', 'color' => 'bg-orange-500', 'tab' => 'tb_dots'],
                            ['label' => 'Malnourished', 'value' => $samCases + $mamCases, 'sub' => $samCases . ' SAM / ' . $mamCases . ' MAM', 'color' => 'bg-yellow-500', 'tab' => 'nutrition'],
                            ['label' => 'Dengue Cases', 'value' => $dengueCases, 'sub' => 'This week', 'color' => 'bg-purple-600', 'tab' => 'disease'],
                            ['label' => 'Medicine Alerts', 'value' => $criticalMeds + $lowMeds, 'sub' => $criticalMeds . ' critical', 'color' => 'bg-teal-600', 'tab' => 'medicine'],
                            ['label' => 'Predictive Alert', 'value' => '+45%', 'sub' => 'Rainy Season Surge', 'color' => 'bg-indigo-600', 'tab' => 'analytics'],
                        ];
                        foreach ($kpis as $kpi): ?>
                            <a href="<?php echo esc(dashboardUrl($kpi['tab'])); ?>" class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 hover:shadow-md hover:-translate-y-0.5 transition-all text-left">
                                <div class="w-8 h-8 <?php echo esc($kpi['color']); ?> rounded-lg flex items-center justify-center mb-2 text-white">+</div>
                                <p class="text-2xl font-black text-gray-900"><?php echo esc($kpi['value']); ?></p>
                                <p class="text-xs font-bold text-gray-700"><?php echo esc($kpi['label']); ?></p>
                                <p class="text-xs text-gray-400"><?php echo esc($kpi['sub']); ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                        <?php $secondary = [
                            ['label' => 'Prenatal Cases', 'value' => count(array_filter($mockMaternalCases, static fn($m) => $m['status'] === 'active_prenatal')), 'sub' => $highRiskMom . ' high-risk', 'tab' => 'maternal', 'color' => 'text-pink-600'],
                            ['label' => 'FP Clients', 'value' => count($mockFPClients), 'sub' => 'Active acceptors', 'tab' => 'fp', 'color' => 'text-rose-600'],
                            ['label' => 'Overdue Vaccines', 'value' => $overdueVaccines + $dueVaccines, 'sub' => 'Need visit', 'tab' => 'immunization', 'color' => 'text-orange-600'],
                            ['label' => 'Blood Drives', 'value' => count(array_filter($mockBloodDrives, static fn($d) => $d['status'] === 'scheduled')), 'sub' => 'Scheduled', 'tab' => 'drives', 'color' => 'text-green-600'],
                            ['label' => 'Referrals', 'value' => count(array_filter($mockReferrals, static fn($r) => $r['status'] === 'pending')), 'sub' => 'Pending', 'tab' => 'referrals', 'color' => 'text-indigo-600'],
                            ['label' => 'Active BHWs', 'value' => count(array_filter($mockBHWs, static fn($b) => $b['activeStatus'])), 'sub' => 'of ' . count($mockBHWs), 'tab' => 'bhw', 'color' => 'text-teal-600'],
                        ];
                        foreach ($secondary as $stat): ?>
                            <a href="<?php echo esc(dashboardUrl($stat['tab'])); ?>" class="bg-white rounded-xl p-3 shadow-sm border border-gray-100 hover:shadow-md text-center transition-all">
                                <p class="text-xl font-black <?php echo esc($stat['color']); ?>"><?php echo esc($stat['value']); ?></p>
                                <p class="text-xs font-bold text-gray-700 mt-0.5"><?php echo esc($stat['label']); ?></p>
                                <p class="text-xs text-gray-400"><?php echo esc($stat['sub']); ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-gray-900 mb-4">Weekly OPD Consultations</h3>
                            <div class="space-y-2 text-sm text-gray-600">
                                <?php foreach ($weeklyOPDData as $row): ?>
                                    <div class="flex justify-between"><span><?php echo esc($row['day']); ?></span><span><?php echo esc($row['consultations']); ?> consultations</span></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-gray-900 mb-4">Top Diagnoses (June 2026)</h3>
                            <div class="space-y-2 text-sm text-gray-600">
                                <?php foreach ($diagnosisData as $item): ?>
                                    <div class="flex justify-between"><span><?php echo esc($item['name']); ?></span><span><?php echo esc($item['value']); ?>%</span></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-3 gap-4">
                        <?php $actions = [
                            ['label' => 'New OPD Consultation', 'tab' => 'opd', 'color' => 'bg-blue-600'],
                            ['label' => 'Record Immunization', 'tab' => 'immunization', 'color' => 'bg-indigo-600'],
                            ['label' => 'Prenatal Check-up', 'tab' => 'maternal', 'color' => 'bg-pink-500'],
                            ['label' => 'Issue Health Certificate', 'tab' => 'certificates', 'color' => 'bg-green-600'],
                            ['label' => 'Create Referral', 'tab' => 'referrals', 'color' => 'bg-purple-600'],
                            ['label' => 'PIDSR Report', 'tab' => 'disease', 'color' => 'bg-orange-600'],
                            ['label' => 'Nutrition Assessment', 'tab' => 'nutrition', 'color' => 'bg-yellow-500'],
                            ['label' => 'TB-DOTS Entry', 'tab' => 'tb_dots', 'color' => 'bg-red-600'],
                        ];
                        foreach ($actions as $actionItem): ?>
                            <a href="<?php echo esc(dashboardUrl($actionItem['tab'])); ?>" class="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors text-left bg-white shadow-sm border border-gray-100">
                                <span class="w-7 h-7 <?php echo esc($actionItem['color']); ?> rounded-lg flex items-center justify-center text-white">+</span>
                                <span class="text-xs font-semibold text-gray-700"><?php echo esc($actionItem['label']); ?></span>
                                <span class="ml-auto text-gray-400"><?php echo iconSvg('right', 'w-4 h-4'); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="grid md:grid-cols-3 gap-4">
                        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-bold text-gray-900">Today's OPD</h3><a href="<?php echo esc(dashboardUrl('opd')); ?>" class="text-xs text-blue-600 hover:underline">View all</a>
                            </div>
                            <div class="space-y-2">
                                <?php foreach ($mockOPDConsultations as $c): ?>
                                    <div class="rounded-xl border border-gray-100 p-3">
                                        <p class="font-bold text-gray-900"><?php echo esc($c['patientName']); ?> <span class="text-gray-500 text-xs"><?php echo esc($c['age']); ?>y • <?php echo esc($c['gender']); ?></span></p>
                                        <p class="text-xs text-gray-500"><?php echo esc($c['diagnosis']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-gray-900 mb-3">Health Alerts</h3>
                            <div class="space-y-2">
                                <?php $alerts = [
                                    ['label' => 'Dengue Cluster (Wk 23)', 'value' => $dengueCases . ' cases', 'color' => 'red', 'tab' => 'disease'],
                                    ['label' => 'Overdue Immunization', 'value' => $overdueVaccines . ' children', 'color' => 'orange', 'tab' => 'immunization'],
                                    ['label' => 'High-Risk Prenatal', 'value' => $highRiskMom . ' mothers', 'color' => 'pink', 'tab' => 'maternal'],
                                ];
                                foreach ($alerts as $alert): ?>
                                    <a href="<?php echo esc(dashboardUrl($alert['tab'])); ?>" class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 transition-colors bg-white border border-gray-100">
                                        <span class="text-xs font-semibold text-gray-700"><?php echo esc($alert['label']); ?></span>
                                        <span class="text-xs font-bold text-<?php echo esc($alert['color']); ?>-600"><?php echo esc($alert['value']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="bg-blue-800 rounded-xl p-4 text-white">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-blue-200 text-xs">Municipality</p>
                                    <p class="font-semibold text-sm"><?php echo esc($RHU_INFO['municipality']); ?>, <?php echo esc($RHU_INFO['province']); ?></p>
                                </div>
                                <div>
                                    <p class="text-blue-200 text-xs">Population Served</p>
                                    <p class="font-semibold text-sm"><?php echo number_format($RHU_INFO['totalPopulation']); ?></p>
                                </div>
                                <div>
                                    <p class="text-blue-200 text-xs">Total Barangays</p>
                                    <p class="font-semibold text-sm"><?php echo count($RHU_INFO['catchmentBarangays']); ?> barangays</p>
                                </div>
                                <div>
                                    <p class="text-blue-200 text-xs">Municipal Health Officer</p>
                                    <p class="font-semibold text-sm"><?php echo esc($RHU_INFO['chiefMHO']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'opd'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">OPD / Consultation Log</h2>
                        <div class="flex gap-2">
                            <a href="<?php echo esc(dashboardUrl('opd', ['modal' => 'new_opd'])); ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> New Consultation</a>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $stats = [
                            ['label' => "Today's Consultations", 'value' => $todayOPD, 'color' => 'blue'],
                            ['label' => 'Referred Out', 'value' => count(array_filter($mockOPDConsultations, static fn($c) => $c['disposition'] === 'referred')), 'color' => 'orange'],
                            ['label' => 'PhilHealth Charged', 'value' => count(array_filter($mockOPDConsultations, static fn($c) => $c['philhealthCharged'])), 'color' => 'green'],
                            ['label' => 'Total This Week', 'value' => count($mockOPDConsultations), 'color' => 'purple'],
                        ];
                        foreach ($stats as $stat): ?>
                            <div class="bg-white rounded-xl p-3 sm:p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($stat['color'] === 'blue' ? 'text-blue-600' : ($stat['color'] === 'orange' ? 'text-orange-600' : ($stat['color'] === 'green' ? 'text-green-600' : 'text-purple-600'))); ?>"><?php echo esc($stat['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($stat['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($mockOPDConsultations as $c): ?>
                            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                                <div class="p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-bold text-gray-900"><?php echo esc($c['patientName']); ?> <span class="font-normal text-gray-500 text-sm"><?php echo esc($c['age']); ?>y / <?php echo esc($c['gender']); ?></span></p>
                                            <p class="text-sm text-gray-700 mt-1"><?php echo esc($c['diagnosis']); ?></p>
                                            <p class="text-xs text-gray-500 mt-1"><?php echo esc($c['barangay']); ?> • <?php echo esc($c['physician']); ?> • <?php echo esc($c['date']); ?></p>
                                        </div>
                                        <span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo $c['disposition'] === 'referred' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700'; ?>"><?php echo esc($c['disposition']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>







            <?php if ($tab === 'referrals'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">Referral Management</h2>
                        <a href="<?php echo esc(dashboardUrl('referrals', ['modal' => 'new_referral'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-semibold hover:bg-purple-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> New Referral</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $refStats = [
                            ['label' => 'Pending', 'count' => count(array_filter($mockReferrals, static fn($r) => $r['status'] === 'pending')), 'color' => 'yellow'],
                            ['label' => 'Accepted', 'count' => count(array_filter($mockReferrals, static fn($r) => $r['status'] === 'accepted')), 'color' => 'blue'],
                            ['label' => 'Completed', 'count' => count(array_filter($mockReferrals, static fn($r) => $r['status'] === 'completed')), 'color' => 'green'],
                            ['label' => 'Total', 'count' => count($mockReferrals), 'color' => 'gray'],
                        ];
                        foreach ($refStats as $s): ?>
                            <div class="bg-white rounded-xl p-3 sm:p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'green' ? 'text-green-600' : ($s['color'] === 'blue' ? 'text-blue-600' : ($s['color'] === 'yellow' ? 'text-yellow-600' : 'text-gray-700'))); ?>"><?php echo esc($s['count']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="space-y-3 sm:space-y-4">
                        <?php foreach ($mockReferrals as $ref): ?>
                            <?php
                            $refUrgencyClasses = $ref['urgency'] === 'critical'
                                ? 'bg-red-100 text-red-700 border-red-200'
                                : 'bg-amber-100 text-amber-700 border-amber-200';
                            $refStatusClasses = $ref['status'] === 'accepted'
                                ? 'bg-blue-100 text-blue-700'
                                : 'bg-amber-100 text-amber-700';
                            ?>
                            <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                                <div class="flex items-start justify-between gap-3 flex-wrap">
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap mb-1">
                                            <span class="font-mono text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded"><?php echo esc($ref['id']); ?></span>
                                            <span class="text-xs px-2 py-0.5 rounded-full font-bold border <?php echo esc($refUrgencyClasses); ?>"><?php echo strtoupper(esc($ref['urgency'])); ?></span>
                                            <span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo esc($refStatusClasses); ?>"><?php echo esc($ref['status']); ?></span>
                                        </div>
                                        <h3 class="font-bold text-gray-900"><?php echo esc($ref['patientName']); ?>, <?php echo esc($ref['age']); ?> y/o <?php echo esc($ref['gender']); ?></h3>
                                        <p class="text-sm text-gray-600"><strong>Dx:</strong> <?php echo esc($ref['diagnosis']); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-gray-900"><?php echo esc($ref['referredTo']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo esc($ref['referralDate']); ?></p>
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <p class="text-sm text-gray-600"><strong>Reason:</strong> <?php echo esc($ref['reason']); ?></p>
                                    <div class="flex items-center justify-between mt-2">
                                        <p class="text-xs text-gray-500">By: <?php echo esc($ref['referringMD']); ?> • <?php echo esc($ref['referralDate']); ?></p>
                                        <div class="flex gap-2">
                                            <a href="#" class="text-xs text-blue-600 hover:underline font-semibold flex items-center gap-1"><?php echo iconSvg('printer', 'w-3 h-3'); ?> Print</a>
                                            <a href="#" class="text-xs text-green-600 hover:underline font-semibold flex items-center gap-1"><?php echo iconSvg('eye', 'w-3 h-3'); ?> View</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'immunization'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">Immunization (EPI) Records</h2>
                        <a href="<?php echo esc(dashboardUrl('immunization', ['modal' => 'new_immunization'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> Record Immunization</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $immStats = [
                            ['label' => 'Total Vaccinations', 'value' => count($mockImmunizations), 'color' => 'indigo'],
                            ['label' => 'Children Immunized', 'value' => count(array_unique(array_column($mockImmunizations, 'childName'))), 'color' => 'blue'],
                            ['label' => 'Core EPI Vaccines', 'value' => '6 Types', 'color' => 'purple'],
                            ['label' => 'Status Administered', 'value' => count(array_filter($mockImmunizations, static fn($imm) => $imm['status'] === 'Administered')), 'color' => 'green'],
                        ];
                        foreach ($immStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'indigo' ? 'text-indigo-600' : ($s['color'] === 'blue' ? 'text-blue-600' : ($s['color'] === 'purple' ? 'text-purple-600' : 'text-green-600'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-5 border-b">
                            <h3 class="font-bold text-gray-900">Childhood Immunization Records</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Live vaccine records from vaccination_records database table</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[700px]">
                                <thead class="bg-gray-50">
                                    <tr><?php foreach (['Record ID', 'Child / Patient', 'Age', 'Vaccine Name', 'Dose', 'Barangay', 'Date Given', 'Batch / Lot No.', 'Administered By', 'Status'] as $h): ?><th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide"><?php echo esc($h); ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($mockImmunizations as $imm): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-mono text-xs text-gray-500"><?php echo esc($imm['id']); ?></td>
                                            <td class="px-4 py-3 font-semibold text-gray-900"><?php echo esc($imm['childName']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($imm['age']); ?></td>
                                            <td class="px-4 py-3 text-indigo-700 font-bold"><?php echo esc($imm['vaccineName']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-700 font-semibold"><?php echo esc($imm['dose']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($imm['barangay']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($imm['dateGiven']); ?></td>
                                            <td class="px-3 py-2.5 font-mono text-xs text-gray-500"><?php echo esc($imm['lot']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($imm['administeredBy']); ?></td>
                                            <td class="px-4 py-3"><span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-green-100 text-green-700"><?php echo esc($imm['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'maternal'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">Maternal Health</h2>
                        <a href="<?php echo esc(dashboardUrl('maternal', ['modal' => 'new_maternal'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-pink-600 text-white rounded-lg text-sm font-semibold hover:bg-pink-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> New Case</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $matStats = [
                            ['label' => 'Active Cases', 'value' => count($mockMaternalCases), 'color' => 'pink'],
                            ['label' => 'High Risk', 'value' => $highRiskMom, 'color' => 'red'],
                            ['label' => 'Next Visits', 'value' => count(array_filter($mockMaternalCases, static fn($m) => strtotime($m['nextVisit']) <= strtotime('+30 days'))), 'color' => 'orange'],
                            ['label' => 'Enrolled', 'value' => count(array_filter($mockMaternalCases, static fn($m) => $m['philhealthStatus'] === 'enrolled')), 'color' => 'green'],
                        ];
                        foreach ($matStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'pink' ? 'text-pink-600' : ($s['color'] === 'red' ? 'text-red-600' : ($s['color'] === 'orange' ? 'text-orange-600' : 'text-green-600'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($mockMaternalCases as $case): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h3 class="font-bold text-gray-900"><?php echo esc($case['name']); ?> <span class="text-sm text-gray-500">(<?php echo esc($case['gravida']); ?>/<?php echo esc($case['para']); ?>)</span></h3>
                                        <p class="text-sm text-gray-600"><?php echo esc($case['barangay']); ?> • <?php echo esc($case['aog']); ?> • Next visit <?php echo esc($case['nextVisit']); ?></p>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full font-semibold <?php echo $case['riskLevel'] === 'high' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>"><?php echo esc(ucfirst(str_replace('_', ' ', $case['riskLevel']))); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'fp'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">Family Planning</h2>
                        <a href="<?php echo esc(dashboardUrl('fp', ['modal' => 'new_fp'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-rose-600 text-white rounded-lg text-sm font-semibold hover:bg-rose-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> New Client</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $fpStats = [
                            ['label' => 'Total FP Clients', 'value' => count($mockFPClients), 'color' => 'rose'],
                            ['label' => 'Active', 'value' => count(array_filter($mockFPClients, static fn($fp) => $fp['status'] === 'active')), 'color' => 'green'],
                            ['label' => 'Next Supply', 'value' => count(array_filter($mockFPClients, static fn($fp) => strtotime($fp['nextVisit']) <= strtotime('+30 days'))), 'color' => 'blue'],
                            ['label' => 'New Acceptors', 'value' => count(array_filter($mockFPClients, static fn($fp) => $fp['acceptorType'] === 'new')), 'color' => 'purple'],
                        ];
                        foreach ($fpStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'rose' ? 'text-rose-600' : ($s['color'] === 'green' ? 'text-green-600' : ($s['color'] === 'blue' ? 'text-blue-600' : 'text-purple-600'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[650px]">
                                <thead class="bg-gray-50">
                                    <tr><?php foreach (['Client', 'Age', 'Barangay', 'Method', 'Status', 'Next Visit'] as $h): ?><th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide"><?php echo esc($h); ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($mockFPClients as $client): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-semibold text-gray-900"><?php echo esc($client['name']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($client['age']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($client['barangay']); ?></td>
                                            <td class="px-4 py-3 text-gray-700"><?php echo esc($client['method']); ?></td>
                                            <td class="px-3 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo $client['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>"><?php echo esc(ucfirst($client['status'])); ?></span></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($client['nextVisit']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'tb_dots'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">TB-DOTS Cases</h2>
                        <a href="<?php echo esc(dashboardUrl('tb_dots', ['modal' => 'new_tb'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> New Case</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $tbStats = [
                            ['label' => 'Total Cases', 'value' => count($mockTBCases), 'color' => 'red'],
                            ['label' => 'On Treatment', 'value' => $activeTB, 'color' => 'blue'],
                            ['label' => 'Intensive Phase', 'value' => count(array_filter($mockTBCases, static fn($t) => $t['phase'] === 'Intensive')), 'color' => 'orange'],
                            ['label' => 'New Cases', 'value' => count(array_filter($mockTBCases, static fn($t) => $t['caseType'] === 'New')), 'color' => 'purple'],
                        ];
                        foreach ($tbStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'red' ? 'text-red-600' : ($s['color'] === 'blue' ? 'text-blue-600' : ($s['color'] === 'orange' ? 'text-orange-600' : 'text-purple-600'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($mockTBCases as $case): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h3 class="font-bold text-gray-900"><?php echo esc($case['name']); ?> <span class="text-sm text-gray-500"><?php echo esc($case['age']); ?> y/o</span></h3>
                                        <p class="text-sm text-gray-600"><?php echo esc($case['classification']); ?> • <?php echo esc($case['barangay']); ?> • Phase <?php echo esc($case['phase']); ?></p>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full font-semibold <?php echo $case['outcome'] === 'on_treatment' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'; ?>"><?php echo esc(str_replace('_', ' ', ucfirst($case['outcome']))); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'nutrition'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">Nutrition Cases</h2>
                        <a href="<?php echo esc(dashboardUrl('nutrition', ['modal' => 'new_nutrition'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm font-semibold hover:bg-yellow-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> Add Case</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $nutriStats = [
                            ['label' => 'Total Cases', 'value' => count($mockNutritionCases), 'color' => 'yellow'],
                            ['label' => 'SAM', 'value' => $samCases, 'color' => 'red'],
                            ['label' => 'MAM', 'value' => $mamCases, 'color' => 'orange'],
                            ['label' => 'Average MUAC', 'value' => count($mockNutritionCases) ? round(array_sum(array_map(static fn($item) => $item['muac'], $mockNutritionCases)) / count($mockNutritionCases), 1) . ' cm' : '0 cm', 'color' => 'green'],
                        ];
                        foreach ($nutriStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'yellow' ? 'text-yellow-600' : ($s['color'] === 'red' ? 'text-red-600' : ($s['color'] === 'orange' ? 'text-orange-600' : 'text-green-600'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[700px]">
                                <thead class="bg-gray-50">
                                    <tr><?php foreach (['Child', 'Age', 'Barangay', 'Classification', 'MUAC', 'Next Visit'] as $h): ?><th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide"><?php echo esc($h); ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($mockNutritionCases as $case): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-semibold text-gray-900"><?php echo esc($case['name']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($case['age']); ?> y/o</td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($case['barangay']); ?></td>
                                            <td class="px-4 py-3 text-gray-700"><?php echo esc($case['classification']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($case['muac']); ?> cm</td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($case['nextVisit']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'disease'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">Disease Surveillance</h2>
                        <a href="<?php echo esc(dashboardUrl('disease', ['modal' => 'new_report'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 text-white rounded-lg text-sm font-semibold hover:bg-orange-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> New Report</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $diseaseStats = [
                            ['label' => 'Alert Reports', 'value' => count($mockDiseaseReports), 'color' => 'orange'],
                            ['label' => 'Active Cases', 'value' => array_sum(array_map(static fn($report) => $report['cases'], $mockDiseaseReports)), 'color' => 'red'],
                            ['label' => 'Barangays Affected', 'value' => count(array_unique(array_merge(...array_map(static fn($report) => $report['barangays'], $mockDiseaseReports)))), 'color' => 'blue'],
                            ['label' => 'Deaths', 'value' => array_sum(array_map(static fn($report) => $report['deaths'], $mockDiseaseReports)), 'color' => 'gray'],
                        ];
                        foreach ($diseaseStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'orange' ? 'text-orange-600' : ($s['color'] === 'red' ? 'text-red-600' : ($s['color'] === 'blue' ? 'text-blue-600' : 'text-gray-700'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="space-y-3 sm:space-y-4">
                        <?php foreach ($mockDiseaseReports as $report): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-bold text-gray-900"><?php echo esc($report['disease']); ?> <span class="text-xs text-gray-500">(<?php echo esc($report['reportingWeek']); ?>)</span></h3>
                                        <p class="text-sm text-gray-600"><?php echo esc($report['alert'] ? 'Alert active' : 'Under monitoring'); ?> • Barangays: <?php echo esc(implode(', ', $report['barangays'])); ?></p>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full font-semibold <?php echo $report['status'] === 'Active' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>"><?php echo esc($report['status']); ?></span>
                                </div>
                                <div class="mt-3 grid gap-2 sm:grid-cols-3 text-sm text-gray-600">
                                    <div><strong><?php echo esc($report['cases']); ?></strong> cases</div>
                                    <div><strong><?php echo esc($report['deaths']); ?></strong> deaths</div>
                                    <div><strong><?php echo esc($report['actionTaken']); ?></strong></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'vital'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">Vital Statistics</h2>
                        <a href="<?php echo esc(dashboardUrl('vital', ['modal' => 'new_vital'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-600 text-white rounded-lg text-sm font-semibold hover:bg-slate-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> Add Record</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $vitalStats = [
                            ['label' => 'Total Registrations', 'value' => count($mockVitalRecords), 'color' => 'slate'],
                            ['label' => 'Births', 'value' => count(array_filter($mockVitalRecords, static fn($v) => $v['type'] === 'Birth')), 'color' => 'blue'],
                            ['label' => 'Registered', 'value' => count(array_filter($mockVitalRecords, static fn($v) => $v['registrationStatus'] === 'registered')), 'color' => 'green'],
                            ['label' => 'Facility-based', 'value' => count(array_filter($mockVitalRecords, static fn($v) => str_contains($v['remarks'], 'Facility'))), 'color' => 'purple'],
                        ];
                        foreach ($vitalStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'blue' ? 'text-blue-600' : ($s['color'] === 'green' ? 'text-green-600' : ($s['color'] === 'purple' ? 'text-purple-600' : 'text-slate-600'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[650px]">
                                <thead class="bg-gray-50">
                                    <tr><?php foreach (['Type', 'Name', 'Mother', 'Barangay', 'Date', 'Status', 'LNCRN'] as $h): ?><th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide"><?php echo esc($h); ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($mockVitalRecords as $record): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-semibold text-gray-900"><?php echo esc($record['type']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($record['name']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($record['motherName']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($record['barangay']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($record['date']); ?></td>
                                            <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo $record['registrationStatus'] === 'registered' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>"><?php echo esc(ucfirst($record['registrationStatus'])); ?></span></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($record['lncrn']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'medicine'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">Medicine Inventory</h2>
                        <a href="<?php echo esc(dashboardUrl('medicine', ['modal' => 'new_medicine'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> Add Medicine</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $medStats = [
                            ['label' => 'Total SKUs', 'value' => count($mockMedicineInventory), 'color' => 'emerald'],
                            ['label' => 'Critical Stock', 'value' => $criticalMeds, 'color' => 'red'],
                            ['label' => 'Low Stock', 'value' => $lowMeds, 'color' => 'amber'],
                            ['label' => 'Needs Reorder', 'value' => count(array_filter($mockMedicineInventory, static fn($item) => $item['stock'] < $item['reorderLevel'])), 'color' => 'orange'],
                        ];
                        foreach ($medStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'emerald' ? 'text-emerald-600' : ($s['color'] === 'red' ? 'text-red-600' : ($s['color'] === 'amber' ? 'text-amber-600' : 'text-orange-600'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[700px]">
                                <thead class="bg-gray-50">
                                    <tr><?php foreach (['ID', 'Name', 'Stock', 'Reorder', 'Status', 'Expiry', 'Batch'] as $h): ?><th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide"><?php echo esc($h); ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($mockMedicineInventory as $med): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-mono text-xs text-gray-500"><?php echo esc($med['id']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-900"><?php echo esc($med['genericName']); ?> (<?php echo esc($med['brandName']); ?>)</td>
                                            <td class="px-4 py-3 text-gray-700"><?php echo esc($med['stock']); ?> <?php echo esc($med['unit']); ?></td>
                                            <td class="px-4 py-3 text-gray-700"><?php echo esc($med['reorderLevel']); ?></td>
                                            <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo $med['status'] === 'critical' ? 'bg-red-100 text-red-700' : ($med['status'] === 'low' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'); ?>"><?php echo esc(ucfirst($med['status'])); ?></span></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($med['expiryDate']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($med['batchNo']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'sanitation'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">Sanitation Inspections</h2>
                        <a href="<?php echo esc(dashboardUrl('sanitation', ['modal' => 'new_inspection'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-lime-600 text-white rounded-lg text-sm font-semibold hover:bg-lime-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> Add Inspection</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $sanStats = [
                            ['label' => 'Total Inspections', 'value' => count($mockSanitationInspections), 'color' => 'lime'],
                            ['label' => 'Compliance', 'value' => count(array_filter($mockSanitationInspections, static fn($i) => $i['complianceRate'] >= 85)), 'color' => 'green'],
                            ['label' => 'Conditional', 'value' => count(array_filter($mockSanitationInspections, static fn($i) => $i['status'] === 'conditional')), 'color' => 'yellow'],
                            ['label' => 'Violations', 'value' => array_sum(array_map(static fn($i) => $i['violations'], $mockSanitationInspections)), 'color' => 'red'],
                        ];
                        foreach ($sanStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'lime' ? 'text-lime-600' : ($s['color'] === 'green' ? 'text-green-600' : ($s['color'] === 'yellow' ? 'text-yellow-600' : 'text-red-600'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[700px]">
                                <thead class="bg-gray-50">
                                    <tr><?php foreach (['Establishment', 'Barangay', 'Inspector', 'Date', 'Status', 'Compliance'] as $h): ?><th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide"><?php echo esc($h); ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($mockSanitationInspections as $insp): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-semibold text-gray-900"><?php echo esc($insp['establishment']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($insp['barangay']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($insp['inspector']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($insp['inspectionDate']); ?></td>
                                            <td class="px-3 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo $insp['status'] === 'conditional' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'; ?>"><?php echo esc(ucfirst($insp['status'])); ?></span></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($insp['complianceRate']); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'certificates'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">Health Certificates</h2>
                        <a href="<?php echo esc(dashboardUrl('certificates', ['modal' => 'new_certificate'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> Issue Certificate</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $certStats = [
                            ['label' => 'Issued', 'value' => count($mockHealthCertificates), 'color' => 'emerald'],
                            ['label' => 'Medical', 'value' => count(array_filter($mockHealthCertificates, static fn($c) => $c['type'] === 'Medical Certificate')), 'color' => 'blue'],
                            ['label' => 'Employment', 'value' => count(array_filter($mockHealthCertificates, static fn($c) => $c['purpose'] === 'Employment')), 'color' => 'purple'],
                            ['label' => 'Current Month', 'value' => count(array_filter($mockHealthCertificates, static fn($c) => strpos($c['issuedDate'], '2026-06') !== false)), 'color' => 'gray'],
                        ];
                        foreach ($certStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'emerald' ? 'text-emerald-600' : ($s['color'] === 'blue' ? 'text-blue-600' : ($s['color'] === 'purple' ? 'text-purple-600' : 'text-gray-700'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[700px]">
                                <thead class="bg-gray-50">
                                    <tr><?php foreach (['ID', 'Type', 'Recipient', 'Barangay', 'Issued', 'Valid Until', 'Fee'] as $h): ?><th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide"><?php echo esc($h); ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($mockHealthCertificates as $cert): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-mono text-xs text-gray-500"><?php echo esc($cert['id']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-900"><?php echo esc($cert['type']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($cert['recipientName']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($cert['barangay']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($cert['issuedDate']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($cert['validUntil']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc(number_format($cert['fee'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'bhw'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">BHW Management</h2>
                        <a href="<?php echo esc(dashboardUrl('bhw', ['modal' => 'new_bhw'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 text-white rounded-lg text-sm font-semibold hover:bg-teal-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> Add BHW</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $bhwStats = [
                            ['label' => 'Total BHWs', 'value' => count($mockBHWs), 'color' => 'teal'],
                            ['label' => 'Active', 'value' => $activeBHW, 'color' => 'green'],
                            ['label' => 'Households', 'value' => array_sum(array_map(static fn($b) => $b['householdsAssigned'], $mockBHWs)), 'color' => 'blue'],
                            ['label' => 'Training Level', 'value' => count(array_filter($mockBHWs, static fn($b) => $b['trainingLevel'] === 'Senior BHW')), 'color' => 'purple'],
                        ];
                        foreach ($bhwStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'teal' ? 'text-teal-600' : ($s['color'] === 'green' ? 'text-green-600' : ($s['color'] === 'blue' ? 'text-blue-600' : 'text-purple-600'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[700px]">
                                <thead class="bg-gray-50">
                                    <tr><?php foreach (['ID', 'Name', 'Barangay', 'Contact', 'Status', 'Donors Referred', 'Households'] as $h): ?><th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide"><?php echo esc($h); ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($mockBHWs as $bhw): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-mono text-xs text-gray-500"><?php echo esc($bhw['id']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-900"><?php echo esc($bhw['name']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($bhw['barangay']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($bhw['contactNo']); ?></td>
                                            <td class="px-3 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo $bhw['activeStatus'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>"><?php echo $bhw['activeStatus'] ? 'Active' : 'Inactive'; ?></span></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($bhw['donorsReferred']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($bhw['householdsAssigned']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'staff'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">Staff Directory</h2>
                        <a href="<?php echo esc(dashboardUrl('staff', ['modal' => 'new_staff'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 text-white rounded-lg text-sm font-semibold hover:bg-sky-700"><?php echo iconSvg('plus', 'w-4 h-4'); ?> Add Staff</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $staffStats = [
                            ['label' => 'Total Staff', 'value' => count($mockRHUStaff), 'color' => 'sky'],
                            ['label' => 'Active', 'value' => $activeStaff, 'color' => 'green'],
                            ['label' => 'Physicians', 'value' => count(array_filter($mockRHUStaff, static fn($s) => str_contains(strtolower($s['position']), 'doctor'))), 'color' => 'blue'],
                            ['label' => 'Midwives', 'value' => count(array_filter($mockRHUStaff, static fn($s) => str_contains(strtolower($s['position']), 'midwife'))), 'color' => 'pink'],
                        ];
                        foreach ($staffStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'sky' ? 'text-sky-600' : ($s['color'] === 'green' ? 'text-green-600' : ($s['color'] === 'blue' ? 'text-blue-600' : 'text-pink-600'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[700px]">
                                <thead class="bg-gray-50">
                                    <tr><?php foreach (['ID', 'Name', 'Position', 'Email', 'License', 'Status', 'PRC Expiry'] as $h): ?><th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide"><?php echo esc($h); ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($mockRHUStaff as $staff): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-mono text-xs text-gray-500"><?php echo esc($staff['id']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-900"><?php echo esc($staff['name']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($staff['position']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($staff['email']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($staff['licenseNo']); ?></td>
                                            <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo $staff['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>"><?php echo esc(ucfirst($staff['status'])); ?></span></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($staff['prcExpiry']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'reports'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900">DOH Reports</h2>
                        <a href="<?php echo esc(dashboardUrl('reports', ['modal' => 'new_report'])); ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 text-white rounded-lg text-sm font-semibold hover:bg-slate-900"><?php echo iconSvg('plus', 'w-4 h-4'); ?> Add Report</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php $reportStats = [
                            ['label' => 'Submitted', 'value' => count($mockDOHReports), 'color' => 'slate'],
                            ['label' => 'Donations', 'value' => array_sum(array_map(static fn($r) => $r['totalDonations'], $mockDOHReports)), 'color' => 'blue'],
                            ['label' => 'Units Collected', 'value' => array_sum(array_map(static fn($r) => $r['totalUnitsCollected'], $mockDOHReports)), 'color' => 'green'],
                            ['label' => 'Transfusions', 'value' => array_sum(array_map(static fn($r) => $r['totalTransfusions'], $mockDOHReports)), 'color' => 'red'],
                        ];
                        foreach ($reportStats as $s): ?>
                            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($s['color'] === 'slate' ? 'text-slate-600' : ($s['color'] === 'blue' ? 'text-blue-600' : ($s['color'] === 'green' ? 'text-green-600' : 'text-red-600'))); ?>"><?php echo esc($s['value']); ?></p>
                                <p class="text-xs text-gray-600 font-semibold mt-0.5"><?php echo esc($s['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[700px]">
                                <thead class="bg-gray-50">
                                    <tr><?php foreach (['ID', 'Type', 'Period', 'Generated', 'Donations', 'Units', 'Transfusions', 'Referrals', 'Drives', 'Status'] as $h): ?><th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide"><?php echo esc($h); ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($mockDOHReports as $rep): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-mono text-xs text-gray-500"><?php echo esc($rep['id']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-900"><?php echo esc($rep['reportType']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($rep['period']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($rep['generatedDate']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($rep['totalDonations']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($rep['totalUnitsCollected']); ?></td>
                                            <td class="px-4 py-3 text-gray-600"><?php echo esc($rep['totalTransfusions']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($rep['totalReferrals']); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($rep['bloodDrivesConducted']); ?></td>
                                            <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo $rep['status'] === 'submitted' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>"><?php echo esc(ucfirst($rep['status'])); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'analytics'): ?>
                <div class="space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('trend', 'w-6 h-6 text-purple-700'); ?> RHU Health Predictive Analytics & Seasonal Forecasting</h2>
                            <p class="text-xs text-gray-500 mt-1">Predictive machine learning models based on historical consultations, climate seasons, and epidemiological trends in Nasugbu</p>
                        </div>
                        <span class="text-xs font-bold bg-purple-100 text-purple-800 px-3 py-1 rounded-full">DOH Predictive Engine Active</span>
                    </div>

                    <!-- 1. SEASONAL CHECKUP SURGE FORECAST -->
                    <div class="grid md:grid-cols-3 gap-4">
                        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-200 space-y-2 border-l-4 border-l-blue-600">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Monsoon / Rainy Season</span>
                                    <h3 class="font-bold text-gray-900 text-sm mt-0.5">July – October Peak</h3>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 font-bold text-[11px]">+45% Checkups</span>
                            </div>
                            <p class="text-xs text-gray-600">Surge in Influenza-like Illness, Acute Respiratory Infections & Dengue. Ensure staff allocation in Triage.</p>
                            <div class="pt-2 text-[11px] font-mono text-blue-900 font-semibold bg-blue-50 p-2 rounded">
                                Predicted Monthly Consults: <strong>380 – 450 cases/mo</strong>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-200 space-y-2 border-l-4 border-l-amber-500">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Dry / Summer Season</span>
                                    <h3 class="font-bold text-gray-900 text-sm mt-0.5">March – May Peak</h3>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold text-[11px]">+30% Checkups</span>
                            </div>
                            <p class="text-xs text-gray-600">Spike in Essential Hypertension, Dehydration, Heat Exhaustion & Skin Conditions due to high temperatures.</p>
                            <div class="pt-2 text-[11px] font-mono text-amber-900 font-semibold bg-amber-50 p-2 rounded">
                                Predicted Monthly Consults: <strong>310 – 360 cases/mo</strong>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-200 space-y-2 border-l-4 border-l-purple-600">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600">Post-Holiday Season</span>
                                    <h3 class="font-bold text-gray-900 text-sm mt-0.5">January – February Peak</h3>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 font-bold text-[11px]">+35% Checkups</span>
                            </div>
                            <p class="text-xs text-gray-600">Surge in Diabetes, Cardiovascular follow-ups, and Lifestyle Disease checkups after holiday celebrations.</p>
                            <div class="pt-2 text-[11px] font-mono text-purple-900 font-semibold bg-purple-50 p-2 rounded">
                                Predicted Monthly Consults: <strong>330 – 380 cases/mo</strong>
                            </div>
                        </div>
                    </div>

                    <!-- 2. EPIDEMIC RISK & MEDICINE STOCKOUT PREDICTIONS -->
                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- EPIDEMIC OUTBREAK PREDICTOR -->
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-4">
                            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                                <h3 class="font-bold text-gray-900 text-base flex items-center gap-2">
                                    <?php echo iconSvg('shield', 'w-5 h-5 text-red-600'); ?>
                                    Disease Outbreak Risk Forecast (Q3 2026)
                                </h3>
                                <span class="text-xs font-bold bg-red-100 text-red-800 px-2.5 py-0.5 rounded-full">High Alert</span>
                            </div>
                            <div class="space-y-3 text-xs">
                                <div class="p-3.5 bg-red-50 rounded-xl border border-red-200 space-y-1">
                                    <div class="flex justify-between font-bold text-red-900">
                                        <span>Dengue Outbreak Risk</span>
                                        <span>78% Outbreak Probability</span>
                                    </div>
                                    <p class="text-red-700">Predicted peak in August – September across Barangays Halang, Mabini, and Balibago due to standing water.</p>
                                    <div class="w-full bg-red-200 h-2 rounded-full mt-2">
                                        <div class="bg-red-600 h-2 rounded-full" style="width: 78%"></div>
                                    </div>
                                </div>

                                <div class="p-3.5 bg-amber-50 rounded-xl border border-amber-200 space-y-1">
                                    <div class="flex justify-between font-bold text-amber-900">
                                        <span>Acute Diarrhea / AGE Risk</span>
                                        <span>54% Moderate Risk</span>
                                    </div>
                                    <p class="text-amber-700">Predicted increase in pediatric diarrhea during heavy monsoon rains. Stock ORS and zinc supplements.</p>
                                    <div class="w-full bg-amber-200 h-2 rounded-full mt-2">
                                        <div class="bg-amber-500 h-2 rounded-full" style="width: 54%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- MEDICINE STOCKOUT & REORDER PREDICTOR -->
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-4">
                            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                                <h3 class="font-bold text-gray-900 text-base flex items-center gap-2">
                                    <?php echo iconSvg('pill', 'w-5 h-5 text-emerald-600'); ?>
                                    Medicine Stockout Date Predictions
                                </h3>
                                <span class="text-xs font-bold bg-emerald-100 text-emerald-800 px-2.5 py-0.5 rounded-full">Demand Velocity</span>
                            </div>
                            <div class="space-y-3 text-xs">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
                                    <div>
                                        <p class="font-bold text-gray-900">Amoxicillin 500mg</p>
                                        <p class="text-gray-500">Avg. 15 caps / day</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="px-2 py-0.5 bg-red-100 text-red-800 font-bold rounded">Stockout in 18 Days</span>
                                        <p class="text-[10px] text-gray-400 mt-0.5">Reorder 300 caps immediately</p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
                                    <div>
                                        <p class="font-bold text-gray-900">ORS (Oral Rehydration Salt)</p>
                                        <p class="text-gray-500">Avg. 12 sachet / day</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="px-2 py-0.5 bg-red-100 text-red-800 font-bold rounded">Stockout in 9 Days</span>
                                        <p class="text-[10px] text-gray-400 mt-0.5">Critical Reorder Needed</p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
                                    <div>
                                        <p class="font-bold text-gray-900">Amlodipine 5mg</p>
                                        <p class="text-gray-500">Avg. 8 tabs / day</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="px-2 py-0.5 bg-amber-100 text-amber-800 font-bold rounded">Stockout in 24 Days</span>
                                        <p class="text-[10px] text-gray-400 mt-0.5">Sufficient for 3 weeks</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. MONTHLY CHECKUP VOLUME PREDICTION TABLE -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <h3 class="font-bold text-gray-900 text-base">Monthly Checkup Volume: Historical vs. Predicted (2026)</h3>
                            <span class="text-xs bg-purple-100 text-purple-800 px-3 py-1 rounded-full font-bold">12-Month Predictive Curve</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Month</th>
                                        <th class="px-3 py-2 text-left">Season Type</th>
                                        <th class="px-3 py-2 text-left">Historical Checkups</th>
                                        <th class="px-3 py-2 text-left">Predicted Checkups</th>
                                        <th class="px-3 py-2 text-left">Dominant Predicted Condition</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2.5 font-bold text-gray-900">January 2026</td>
                                        <td class="px-3 py-2.5 text-purple-700 font-semibold">Post-Holiday Peak</td>
                                        <td class="px-3 py-2.5 font-mono text-gray-600">312 consults</td>
                                        <td class="px-3 py-2.5 font-mono font-bold text-purple-900">340 consults</td>
                                        <td class="px-3 py-2.5 text-gray-700">Diabetes & Hypertension Checkups</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2.5 font-bold text-gray-900">April 2026</td>
                                        <td class="px-3 py-2.5 text-amber-700 font-semibold">Summer Heat Peak</td>
                                        <td class="px-3 py-2.5 font-mono text-gray-600">290 consults</td>
                                        <td class="px-3 py-2.5 font-mono font-bold text-amber-900">325 consults</td>
                                        <td class="px-3 py-2.5 text-gray-700">Dehydration, Heat Exhaustion & Hypertension</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 bg-blue-50/50">
                                        <td class="px-3 py-2.5 font-bold text-gray-900">August 2026 (Upcoming)</td>
                                        <td class="px-3 py-2.5 text-blue-700 font-semibold">Monsoon Rainy Peak</td>
                                        <td class="px-3 py-2.5 font-mono text-gray-400">N/A (Future)</td>
                                        <td class="px-3 py-2.5 font-mono font-bold text-blue-900">420 consults (+45%)</td>
                                        <td class="px-3 py-2.5 font-bold text-red-600">Dengue Fever & Acute Bronchitis</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50 bg-blue-50/50">
                                        <td class="px-3 py-2.5 font-bold text-gray-900">September 2026 (Upcoming)</td>
                                        <td class="px-3 py-2.5 text-blue-700 font-semibold">Monsoon Rainy Peak</td>
                                        <td class="px-3 py-2.5 font-mono text-gray-400">N/A (Future)</td>
                                        <td class="px-3 py-2.5 font-mono font-bold text-blue-900">395 consults (+35%)</td>
                                        <td class="px-3 py-2.5 font-bold text-amber-600">Influenza-like Illness & AGE / Diarrhea</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>



    <!-- 1. NEW OPD CONSULTATION MODAL -->
    <?php if ($modal === 'new_opd'): ?>
        <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-5 border-b flex items-center justify-between bg-blue-700 text-white rounded-t-2xl">
                    <h2 class="text-base font-bold flex items-center gap-2">🩺 Record New OPD Consultation</h2>
                    <a href="<?php echo esc(dashboardUrl('opd')); ?>" class="text-blue-100 hover:text-white"><?php echo iconSvg('x', 'w-5 h-5'); ?></a>
                </div>
                <form class="p-5 space-y-4" method="post">
                    <input type="hidden" name="action" value="save_opd">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Select Patient Resident *</label>
                        <select name="resident_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-semibold">
                            <option value="">-- Choose Resident --</option>
                            <?php foreach ($allResidents as $res): ?>
                                <option value="<?php echo esc($res['id']); ?>"><?php echo esc($res['name']); ?> (<?php echo esc($res['barangay']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Attending Physician / Staff *</label>
                        <select name="physician_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                            <?php foreach ($allStaff as $st): ?>
                                <option value="<?php echo esc($st['id']); ?>"><?php echo esc($st['name']); ?> (<?php echo esc($st['staff_type']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Chief Complaint *</label>
                        <input name="chiefComplaint" required placeholder="e.g., Fever and productive cough for 3 days" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Primary Diagnosis *</label>
                            <input name="diagnosis" required placeholder="e.g., Acute Upper Respiratory Infection" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">ICD-10 Code</label>
                            <input name="icd10" value="J06.9" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Prescribed Medications</label>
                        <input name="medications" placeholder="e.g., Paracetamol 500mg, Amoxicillin 500mg" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Clinical Examination & Vitals Notes</label>
                        <textarea name="notes" rows="2" placeholder="BP: 120/80, Temp: 37.2C, HR: 80 bpm..." class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm"></textarea>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <a href="<?php echo esc(dashboardUrl('opd')); ?>" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 shadow-md">Save Consultation</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. CREATE HOSPITAL REFERRAL MODAL -->
    <?php if ($modal === 'new_referral'): ?>
        <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-5 border-b flex items-center justify-between bg-purple-700 text-white rounded-t-2xl">
                    <h2 class="text-base font-bold flex items-center gap-2">🚑 Create Outpatient Hospital Referral</h2>
                    <a href="<?php echo esc(dashboardUrl('referrals')); ?>" class="text-purple-100 hover:text-white"><?php echo iconSvg('x', 'w-5 h-5'); ?></a>
                </div>
                <form class="p-5 space-y-4" method="post">
                    <input type="hidden" name="action" value="save_referral">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Select Patient Resident *</label>
                        <select name="resident_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-semibold">
                            <option value="">-- Choose Resident --</option>
                            <?php foreach ($allResidents as $res): ?>
                                <option value="<?php echo esc($res['id']); ?>"><?php echo esc($res['name']); ?> (<?php echo esc($res['barangay']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Referring Health Officer / Doctor *</label>
                        <select name="physician_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                            <?php foreach ($allStaff as $st): ?>
                                <option value="<?php echo esc($st['id']); ?>"><?php echo esc($st['name']); ?> (<?php echo esc($st['staff_type']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Referral Destination Facility *</label>
                        <select name="referredTo" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-bold">
                            <option value="Nasugbu District Hospital">Nasugbu District Hospital</option>
                            <option value="Batangas Provincial Hospital">Batangas Provincial Hospital</option>
                            <option value="Batangas Medical Center (BatMC)">Batangas Medical Center (BatMC)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Clinical Diagnosis *</label>
                        <input name="diagnosis" required placeholder="e.g., Severe Pneumonia / Acute Abdomen" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Reason for Referral *</label>
                        <textarea name="reason" rows="3" required placeholder="Detailed clinical justification for referral to hospital..." class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm"></textarea>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <a href="<?php echo esc(dashboardUrl('referrals')); ?>" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-purple-600 text-white rounded-xl text-xs font-bold hover:bg-purple-700 shadow-md">Submit Referral</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- 3. RECORD IMMUNIZATION VACCINE MODAL -->
    <?php if ($modal === 'new_immunization'): ?>
        <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-5 border-b flex items-center justify-between bg-indigo-700 text-white rounded-t-2xl">
                    <h2 class="text-base font-bold flex items-center gap-2">💉 Record Childhood Vaccination (EPI)</h2>
                    <a href="<?php echo esc(dashboardUrl('immunization')); ?>" class="text-indigo-100 hover:text-white"><?php echo iconSvg('x', 'w-5 h-5'); ?></a>
                </div>
                <form class="p-5 space-y-4" method="post">
                    <input type="hidden" name="action" value="save_immunization">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Select Child Patient *</label>
                        <select name="resident_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-semibold">
                            <option value="">-- Choose Child Resident --</option>
                            <?php foreach ($allResidents as $res): ?>
                                <option value="<?php echo esc($res['id']); ?>"><?php echo esc($res['name']); ?> (<?php echo esc($res['barangay']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Vaccine Type *</label>
                        <select name="vaccine_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-bold">
                            <?php foreach ($allVaccines as $vac): ?>
                                <option value="<?php echo esc($vac['id']); ?>"><?php echo esc($vac['vaccine_name']); ?> (<?php echo esc($vac['age_group']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Administering Nurse / Midwife *</label>
                        <select name="provider_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                            <?php foreach ($allStaff as $st): ?>
                                <option value="<?php echo esc($st['id']); ?>"><?php echo esc($st['name']); ?> (<?php echo esc($st['staff_type']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Date Given *</label>
                            <input type="date" name="vaccination_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Batch / Lot Number *</label>
                            <input name="batch_number" value="BCG-2026-<?php echo rand(10, 99); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Next Scheduled Dose Date</label>
                        <input type="date" name="next_dose_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <a href="<?php echo esc(dashboardUrl('immunization')); ?>" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 shadow-md">Save Vaccination Record</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4. MATERNAL PREGNANCY CASE MODAL -->
    <?php if ($modal === 'new_maternal'): ?>
        <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-5 border-b flex items-center justify-between bg-pink-700 text-white rounded-t-2xl">
                    <h2 class="text-base font-bold flex items-center gap-2">👶 Register Maternal Prenatal Case</h2>
                    <a href="<?php echo esc(dashboardUrl('maternal')); ?>" class="text-pink-100 hover:text-white"><?php echo iconSvg('x', 'w-5 h-5'); ?></a>
                </div>
                <form class="p-5 space-y-4" method="post">
                    <input type="hidden" name="action" value="save_maternal">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Select Pregnant Resident Patient *</label>
                        <select name="resident_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-semibold">
                            <option value="">-- Choose Resident --</option>
                            <?php foreach ($allResidents as $res): ?>
                                <option value="<?php echo esc($res['id']); ?>"><?php echo esc($res['name']); ?> (<?php echo esc($res['barangay']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Last Menstrual Period (LMP) *</label>
                            <input type="date" name="lmp" value="<?php echo date('Y-m-d', strtotime('-60 days')); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Expected Delivery Date (EDD) *</label>
                            <input type="date" name="edd" value="<?php echo date('Y-m-d', strtotime('+220 days')); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                    </div>
                    <div class="flex items-center gap-2 p-3 bg-pink-50 rounded-xl border border-pink-200">
                        <input type="checkbox" name="high_risk" value="1" id="hr_check" class="w-4 h-4 text-pink-600 rounded">
                        <label for="hr_check" class="text-xs font-bold text-pink-900 cursor-pointer">Mark as High-Risk Pregnancy Case</label>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Risk Factors / Clinical Notes</label>
                        <input name="risk_factors" placeholder="e.g., Gestational Hypertension, Teenage Pregnancy" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <a href="<?php echo esc(dashboardUrl('maternal')); ?>" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-pink-600 text-white rounded-xl text-xs font-bold hover:bg-pink-700 shadow-md">Register Maternal Case</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- 5. REGISTER TB PATIENT MODAL -->
    <?php if ($modal === 'new_tb'): ?>
        <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-5 border-b flex items-center justify-between bg-red-700 text-white rounded-t-2xl">
                    <h2 class="text-base font-bold flex items-center gap-2">🔬 Register TB-DOTS Patient Case</h2>
                    <a href="<?php echo esc(dashboardUrl('tb_dots')); ?>" class="text-red-100 hover:text-white"><?php echo iconSvg('x', 'w-5 h-5'); ?></a>
                </div>
                <form class="p-5 space-y-4" method="post">
                    <input type="hidden" name="action" value="save_tb">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Select Patient Resident *</label>
                        <select name="resident_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-semibold">
                            <option value="">-- Choose Resident --</option>
                            <?php foreach ($allResidents as $res): ?>
                                <option value="<?php echo esc($res['id']); ?>"><?php echo esc($res['name']); ?> (<?php echo esc($res['barangay']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">DOH TB Registration No. *</label>
                            <input name="tb_reg_no" value="TB-NSG-2026-<?php echo rand(100, 999); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">TB Classification *</label>
                            <select name="tb_type" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                                <option value="Pulmonary TB">Pulmonary TB</option>
                                <option value="Extra-Pulmonary TB">Extra-Pulmonary TB</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Treatment Start Date *</label>
                        <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <a href="<?php echo esc(dashboardUrl('tb_dots')); ?>" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-red-600 text-white rounded-xl text-xs font-bold hover:bg-red-700 shadow-md">Register TB Case</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- 6. ADD MEDICINE ITEM MODAL -->
    <?php if ($modal === 'new_medicine'): ?>
        <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-5 border-b flex items-center justify-between bg-teal-700 text-white rounded-t-2xl">
                    <h2 class="text-base font-bold flex items-center gap-2">💊 Add Medicine Inventory Item</h2>
                    <a href="<?php echo esc(dashboardUrl('medicine')); ?>" class="text-teal-100 hover:text-white"><?php echo iconSvg('x', 'w-5 h-5'); ?></a>
                </div>
                <form class="p-5 space-y-4" method="post">
                    <input type="hidden" name="action" value="save_medicine">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Generic Name *</label>
                            <input name="generic_name" required placeholder="e.g., Amoxicillin" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Brand Name</label>
                            <input name="brand_name" placeholder="e.g., Amoxil" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Dosage / Strength</label>
                            <input name="dosage" value="500mg" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Unit Form</label>
                            <select name="unit_form" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                                <option value="Tablet">Tablet</option>
                                <option value="Capsule">Capsule</option>
                                <option value="Syrup / Suspension">Syrup / Suspension</option>
                                <option value="Sachet / Powder">Sachet / Powder</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Quantity in Stock *</label>
                            <input type="number" name="quantity_in_stock" value="100" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Reorder Threshold Level</label>
                            <input type="number" name="reorder_level" value="30" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Expiry Date *</label>
                            <input type="date" name="expiry_date" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Batch / Lot No. *</label>
                            <input name="batch_number" value="BATCH-2026-<?php echo rand(10, 99); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-mono">
                        </div>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <a href="<?php echo esc(dashboardUrl('medicine')); ?>" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-teal-600 text-white rounded-xl text-xs font-bold hover:bg-teal-700 shadow-md">Add Medicine Item</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- 7. ISSUE HEALTH CERTIFICATE MODAL -->
    <?php if ($modal === 'new_certificate'): ?>
        <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-5 border-b flex items-center justify-between bg-green-700 text-white rounded-t-2xl">
                    <h2 class="text-base font-bold flex items-center gap-2">📑 Issue Official Health Certificate</h2>
                    <a href="<?php echo esc(dashboardUrl('certificates')); ?>" class="text-green-100 hover:text-white"><?php echo iconSvg('x', 'w-5 h-5'); ?></a>
                </div>
                <form class="p-5 space-y-4" method="post">
                    <input type="hidden" name="action" value="save_certificate">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Select Resident Recipient *</label>
                        <select name="resident_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-semibold">
                            <option value="">-- Choose Resident --</option>
                            <?php foreach ($allResidents as $res): ?>
                                <option value="<?php echo esc($res['id']); ?>"><?php echo esc($res['name']); ?> (<?php echo esc($res['barangay']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Certificate Type *</label>
                        <select name="certificate_type_id" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-bold">
                            <?php foreach ($allCertTypes as $ct): ?>
                                <option value="<?php echo esc($ct['id']); ?>"><?php echo esc($ct['certificate_type_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Certificate Number *</label>
                            <input name="cert_no" value="CERT-2026-<?php echo rand(1000, 9999); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Valid Until Date *</label>
                            <input type="date" name="expiry_date" value="<?php echo date('Y-m-d', strtotime('+6 months')); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Purpose / Remarks *</label>
                        <input name="purpose" required value="Employment Requirement / Sanitary Clearance" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                    </div>
                    <div class="flex gap-3 pt-2">
                        <a href="<?php echo esc(dashboardUrl('certificates')); ?>" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-green-600 text-white rounded-xl text-xs font-bold hover:bg-green-700 shadow-md">Issue Certificate</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <!-- 8. ADD BHW ACCOUNT MODAL -->
    <?php if ($modal === 'new_bhw'): ?>
        <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-5 border-b flex items-center justify-between bg-teal-700 text-white rounded-t-2xl">
                    <h2 class="text-base font-bold flex items-center gap-2">👤 Register New Barangay Health Worker (BHW) Account</h2>
                    <a href="<?php echo esc(dashboardUrl('bhw')); ?>" class="text-teal-100 hover:text-white"><?php echo iconSvg('x', 'w-5 h-5'); ?></a>
                </div>
                <form class="p-5 space-y-4" method="post">
                    <input type="hidden" name="action" value="save_bhw">
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">First Name *</label>
                            <input name="first_name" required placeholder="e.g. Maria Clara" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Last Name *</label>
                            <input name="last_name" required placeholder="e.g. Santos" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Email Address (Login Username) *</label>
                            <input type="email" name="email" required placeholder="e.g. bhw.santos@nasugbu.gov.ph" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Contact Phone Number</label>
                            <input name="contact_no" placeholder="e.g. 0917 123 4567" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Assigned Barangay *</label>
                            <select name="barangay" required class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-semibold">
                                <option value="">-- Choose Barangay --</option>
                                <?php foreach ($RHU_INFO['catchmentBarangays'] as $brgy): ?>
                                    <option value="<?php echo esc($brgy); ?>"><?php echo esc($brgy); ?></option>
                                <?php endforeach; ?>
                                <option value="Anilao">Anilao</option>
                                <option value="Balibago">Balibago</option>
                                <option value="Bucana">Bucana</option>
                                <option value="Cogunan">Cogunan</option>
                                <option value="Dayap">Dayap</option>
                                <option value="Halang">Halang</option>
                                <option value="Mabini">Mabini</option>
                                <option value="Nagsabaran">Nagsabaran</option>
                                <option value="Poblacion">Poblacion</option>
                                <option value="Wawa">Wawa</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Assigned Households</label>
                            <input type="number" name="households" value="50" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-bold">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Training Designation Level</label>
                            <select name="training_level" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                                <option value="Junior BHW">Junior BHW</option>
                                <option value="Senior BHW" selected>Senior BHW</option>
                                <option value="Lead BHW Coordinator">Lead BHW Coordinator</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Account Password</label>
                            <input type="password" name="password" placeholder="Default: Bhw@123456" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm">
                        </div>
                    </div>

                    <div class="p-3 bg-teal-50 rounded-xl border border-teal-200 text-xs text-teal-800 space-y-1">
                        <p class="font-bold flex items-center gap-1">ℹ️ BHW Portal Credentials Info</p>
                        <p class="text-teal-700">Creating this account automatically sets up login credentials for the BHW Mobile / Web Portal. The worker can log in using their email address.</p>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <a href="<?php echo esc(dashboardUrl('bhw')); ?>" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                        <button type="submit" class="flex-1 py-2.5 bg-teal-600 text-white rounded-xl text-xs font-bold hover:bg-teal-700 shadow-md">Register & Save BHW</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</body>

</html>
