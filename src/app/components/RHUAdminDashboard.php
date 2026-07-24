<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function iconSvg(string $name, string $class = 'w-5 h-5'): string
{
    $icons = [
        'shield' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'users' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'settings' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6V20a2 2 0 0 1-4 0v-.1a1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1H4a2 2 0 0 1 0-4h.1a1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6V4a2 2 0 0 1 4 0v.1a1.65 1.65 0 0 0 1 .6 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.24.31.4.68.6 1H20a2 2 0 0 1 0 4h-.1a1.65 1.65 0 0 0-.5 1z"/></svg>',
        'bar' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>',
        'logout' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/><path d="M13 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7"/></svg>',
        'bell' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"/><path d="M9 17a3 3 0 0 0 6 0"/></svg>',
        'home' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>',
        'activity' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
        'file' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'database' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>',
        'lock' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'check' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
        'clock' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
        'trend' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
        'right' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 5l7 7-7 7"/></svg>',
        'eye' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>',
        'edit' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>',
        'trash' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
        'plus' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>',
        'download' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        'refresh' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>',
        'server' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><line x1="6" y1="7" x2="6.01" y2="7"/><line x1="6" y1="17" x2="6.01" y2="17"/></svg>',
        'heart' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 22l7.8-8.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>',
        'stethoscope' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v7a6 6 0 0 0 12 0V3"/><path d="M6 10a3 3 0 0 0 6 0"/><path d="M12 20a4 4 0 0 0 8 0v-2"/></svg>',
        'building' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M9 22V12h6v10"/><path d="M9 8h.01"/><path d="M13 8h.01"/><path d="M9 12h.01"/><path d="M13 12h.01"/></svg>',
        'key' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.5 12.5L21 2"/><path d="M17 5l2 2"/></svg>',
        'globe' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20"/><path d="M12 2a15.3 15.3 0 0 0 0 20"/></svg>',
        'mail' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
        'phone' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.18 2 2 0 0 1 4.09 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.77.6 2.6a2 2 0 0 1-.45 2.11L8 9a16 16 0 0 0 7 7l.57-1.24a2 2 0 0 1 2.11-.45c.83.27 1.7.48 2.6.6A2 2 0 0 1 22 16.92z"/></svg>',
        'calendar' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
    ];

    return $icons[$name] ?? '';
}

$tab = $_GET['tab'] ?? 'overview';
$showNotifs = isset($_GET['notifs']) && $_GET['notifs'] === '1';

$RHU_INFO = [
    'name' => 'Nasugbu Rural Health Unit I',
    'code' => 'RHU-NSG-001',
    'municipality' => 'Nasugbu',
    'province' => 'Batangas',
    'region' => 'Region IV-A (CALABARZON)',
    'address' => 'Poblacion, Nasugbu, Batangas',
    'contactNumber' => '(043) 416-1234',
    'email' => 'rhu1.nasugbu@doh.gov.ph',
];

// fallback mock staff data
$mockRHUStaff = [
    ['id' => 'ST001', 'name' => 'Dr. Maria C. Santos', 'position' => 'Municipal Health Officer', 'specialization' => 'Public Health', 'employmentType' => 'Plantilla', 'licenseNo' => 'MD-2005-12345', 'prcExpiry' => '2026-06-30', 'philhealthAccreditation' => 'Active', 'schedule' => 'Mon�Fri: 8AM�5PM', 'contactNo' => '09178001001', 'email' => 'mcsantos.mho@nasugbu.gov.ph', 'status' => 'active'],
];

$mockBHWs = [
    ['id' => 'BHW001', 'name' => 'Natividad Puno', 'barangay' => 'Halang', 'contactNo' => '09171110001', 'yearsOfService' => 8, 'trainingLevel' => 'Senior BHW', 'activeStatus' => true, 'householdsAssigned' => 62, 'donorsReferred' => 28, 'immunizationCoverage' => 94, 'maternalCases' => 12, 'lastTraining' => '2025-11-15', 'supervisor' => 'Midwife Rosario Peralta', 'responsibilities' => ['Home visits', 'Nutrition monitoring', 'Immunization reminders', 'TB-DOTS support']],
];

// fallback admin notifications
$adminNotifs = [
    ['id' => 1, 'msg' => 'Failed login attempt detected from external IP (203.177.55.12)', 'type' => 'alert', 'time' => '2h ago', 'unread' => true],
];

$systemMetrics = [
    ['month' => 'Jan', 'residents' => 12, 'staff' => 6, 'bhw' => 47, 'consultations' => 288],
];

$moduleUsage = [
    ['module' => 'OPD', 'sessions' => 480],
    ['module' => 'Maternal', 'sessions' => 210],
    ['module' => 'Immunization', 'sessions' => 185],
    ['module' => 'TB-DOTS', 'sessions' => 95],
    ['module' => 'Nutrition', 'sessions' => 120],
    ['module' => 'FP', 'sessions' => 88],
    ['module' => 'Disease', 'sessions' => 145],
    ['module' => 'Certs', 'sessions' => 67],
];

$mockAuditLogs = [
    ['id' => 'AL001', 'user' => 'Dr. Maria C. Santos', 'role' => 'MHO', 'action' => 'Viewed patient record: Ricardo Dimayuga', 'module' => 'Patient Records', 'timestamp' => '2026-06-10 14:32:15', 'ip' => '192.168.1.45', 'status' => 'success'],
];

$mockResidents = [
    ['id' => 'RES-001', 'name' => 'Maria Clara Santos', 'barangay' => 'Halang', 'philhealthNo' => 'PH-123456789', 'registeredDate' => '2026-01-15', 'lastLogin' => '2026-06-10', 'status' => 'active'],
];

// Attempt to load from DB
@include_once __DIR__ . '/db.php';

$staffCreateError = '';
$staffCreateData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'password' => '',
    'staff_type' => 'ADMIN_STAFF',
    'specialization' => '',
    'license_number' => '',
    'license_expiry' => '',
    'phone_number' => '',
    'address' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_staff') {
    $staffCreateData = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'staff_type' => trim($_POST['staff_type'] ?? 'ADMIN_STAFF'),
        'specialization' => trim($_POST['specialization'] ?? ''),
        'license_number' => trim($_POST['license_number'] ?? ''),
        'license_expiry' => trim($_POST['license_expiry'] ?? ''),
        'phone_number' => trim($_POST['phone_number'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
    ];

    if ($staffCreateData['first_name'] === '' || $staffCreateData['last_name'] === '' || $staffCreateData['email'] === '' || $staffCreateData['password'] === '' || $staffCreateData['staff_type'] === '') {
        $staffCreateError = 'First name, last name, email, password, and staff type are required.';
    } elseif (!filter_var($staffCreateData['email'], FILTER_VALIDATE_EMAIL)) {
        $staffCreateError = 'Please enter a valid email address.';
    } elseif (strlen($staffCreateData['password']) < 8) {
        $staffCreateError = 'Password must be at least 8 characters long.';
    } elseif (!isset($pdo) || !$pdo) {
        $staffCreateError = 'The database is unavailable. Please try again later.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $staffCreateData['email']]);
            if ($stmt->fetch()) {
                throw new Exception('An account with that email already exists.');
            }

            $roleMap = [
                'ADMIN_STAFF' => 'RHU Admin',
                'MIDWIFE' => 'MIDWIFE',
                'NURSE' => 'NURSE',
                'MEDTECH' => 'MEDTECH',
                'SANITARY_INSPECTOR' => 'SANITARY_INSPECTOR',
                'BHW' => 'BHW',
                'PHYSICIAN' => 'PHYSICIAN',
            ];
            $roleName = $roleMap[$staffCreateData['staff_type']] ?? strtoupper(str_replace(' ', '_', $staffCreateData['staff_type']));
            $roleId = resolveRoleId($pdo, $roleName);

            $username = preg_replace('/[^a-z0-9._-]/i', '_', strtolower($staffCreateData['email']));
            $passwordHash = password_hash($staffCreateData['password'], PASSWORD_DEFAULT);
            $insertUser = $pdo->prepare('INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active, created_at) VALUES (:username, :email, :password_hash, :first_name, :last_name, :role_id, 1, NOW())');
            $insertUser->execute([
                'username' => $username,
                'email' => $staffCreateData['email'],
                'password_hash' => $passwordHash,
                'first_name' => $staffCreateData['first_name'],
                'last_name' => $staffCreateData['last_name'],
                'role_id' => $roleId,
            ]);
            $userId = (int) $pdo->lastInsertId();

            $insertStaff = $pdo->prepare('INSERT INTO staff (user_id, staff_type, license_number, license_expiry, specialization, phone_number, address, date_hired, is_active, created_at) VALUES (:user_id, :staff_type, :license_number, :license_expiry, :specialization, :phone_number, :address, NOW(), 1, NOW())');
            $insertStaff->execute([
                'user_id' => $userId,
                'staff_type' => $staffCreateData['staff_type'],
                'license_number' => $staffCreateData['license_number'] ?: null,
                'license_expiry' => $staffCreateData['license_expiry'] ?: null,
                'specialization' => $staffCreateData['specialization'] ?: null,
                'phone_number' => $staffCreateData['phone_number'] ?: null,
                'address' => $staffCreateData['address'] ?: null,
            ]);

            $pdo->commit();
            $_SESSION['rhu_admin_flash'] = 'New staff account created successfully.';
            header('Location: ' . tabUrl('staff'));
            exit;
        } catch (Exception $e) {
            if (isset($pdo) && $pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RHUAdminDashboard create_staff: ' . $e->getMessage());
            $staffCreateError = $e->getMessage();
        }
    }
}

$staffCreateSuccess = $_SESSION['rhu_admin_flash'] ?? '';
unset($_SESSION['rhu_admin_flash']);

if (isset($pdo) && $pdo) {
    try {
        // staff
        $stmt = $pdo->query('SELECT s.id, u.first_name, u.last_name, s.staff_type, s.specialization, s.phone_number, s.license_number, s.license_expiry, s.is_active FROM staff s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.id LIMIT 50');
        $staff = [];
        while ($r = $stmt->fetch()) {
            $staff[] = [
                'id' => $r['id'],
                'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: 'Staff ' . $r['id'],
                'position' => $r['staff_type'] ?? '',
                'specialization' => $r['specialization'] ?? '',
                'licenseNo' => $r['license_number'] ?? '',
                'prcExpiry' => $r['license_expiry'] ? date('Y-m-d', strtotime($r['license_expiry'])) : 'N/A',
                'philhealthAccreditation' => 'Active',
                'status' => $r['is_active'] ? 'active' : 'inactive',
            ];
        }
        if (!empty($staff)) $mockRHUStaff = $staff;

        // bhw
        $stmt = $pdo->query('SELECT b.id, s.id AS staff_id, u.first_name, u.last_name, b.barangay, b.assigned_date FROM bhw b LEFT JOIN staff s ON b.staff_id = s.id LEFT JOIN users u ON s.user_id = u.id LIMIT 50');
        $bhws = [];
        while ($r = $stmt->fetch()) {
            $bhws[] = [
                'id' => $r['id'],
                'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: 'BHW ' . $r['id'],
                'barangay' => $r['barangay'] ?? '',
                'contactNo' => '',
                'yearsOfService' => null,
                'activeStatus' => true,
            ];
        }
        if (!empty($bhws)) $mockBHWs = $bhws;

        // audit logs
        $stmt = $pdo->query('SELECT id, user_id, action, timestamp, ip_address, new_values, old_values FROM audit_logs ORDER BY timestamp DESC LIMIT 20');
        $logs = [];
        while ($r = $stmt->fetch()) {
            $logs[] = [
                'id' => $r['id'],
                'user' => $r['user_id'],
                'role' => '',
                'action' => $r['action'],
                'module' => '',
                'timestamp' => $r['timestamp'],
                'ip' => $r['ip_address'],
                'status' => 'success',
            ];
        }
        if (!empty($logs)) $mockAuditLogs = $logs;

        // residents count (used in some metrics)
        $resCount = (int) $pdo->query('SELECT COUNT(*) FROM residents')->fetchColumn();
        $systemMetrics = [['month' => 'Jun', 'residents' => $resCount, 'staff' => count($mockRHUStaff), 'bhw' => count($mockBHWs), 'consultations' => 0]];

    } catch (PDOException $e) {
        error_log('RHUAdminDashboard DB: ' . $e->getMessage());
    }
}

$tabLabelMap = [
    'overview' => 'Overview',
    'users' => 'User Management',
    'staff' => 'Staff Accounts',
    'residents' => 'Resident Registry',
    'reports' => 'Reports & Analytics',
    'audit' => 'Audit Logs',
    'system' => 'System Settings',
    'security' => 'Security',
];

$tabIconMap = [
    'overview' => 'home',
    'users' => 'users',
    'staff' => 'stethoscope',
    'residents' => 'check',
    'reports' => 'bar',
    'audit' => 'database',
    'system' => 'settings',
    'security' => 'lock',
];

function tabUrl(string $tab, bool $notifs = false, array $extra = []): string
{
    $params = ['tab' => $tab];
    if ($notifs) {
        $params['notifs'] = '1';
    }
    foreach ($extra as $key => $value) {
        if ($value !== null) {
            $params[$key] = $value;
        }
    }
    return '?' . http_build_query($params);
}

function resolveRoleId(PDO $pdo, string $roleName): int
{
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE UPPER(name) = UPPER(:name) LIMIT 1');
    $stmt->execute(['name' => $roleName]);
    $roleId = $stmt->fetchColumn();
    if ($roleId) {
        return (int) $roleId;
    }

    $insert = $pdo->prepare('INSERT INTO roles (name, description) VALUES (:name, :description)');
    $insert->execute(['name' => $roleName, 'description' => 'Auto-created role for ' . $roleName]);
    return (int) $pdo->lastInsertId();
}

function iconNameForType(string $type): string
{
    return $type === 'alert' ? 'alert' : ($type === 'warning' ? 'alert' : 'check');
}

function renderMetricCard(string $label, $value, string $sub, string $icon, string $iconClass, string $textColor): void
{
    echo '<div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">';
    echo '<div class="w-9 h-9 ' . esc($iconClass) . ' rounded-lg flex items-center justify-center mb-3 text-white">' . iconSvg($icon, 'w-4.5 h-4.5') . '</div>';
    echo '<p class="text-2xl font-black text-gray-900">' . esc($value) . '</p>';
    echo '<p class="text-sm font-bold text-gray-700">' . esc($label) . '</p>';
    echo '<p class="text-xs text-gray-400">' . esc($sub) . '</p>';
    echo '</div>';
}

function progressBar(int $value, string $color = 'bg-purple-600'): string
{
    return '<div class="w-full bg-gray-200 rounded-full h-2"><div class="' . esc($color) . ' h-2 rounded-full" style="width: ' . esc($value) . '%"></div></div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RHUAdminDashboard - RedPulse RHU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <div class="min-h-screen flex flex-col">
        <header class="bg-gradient-to-r from-slate-800 to-purple-900 text-white shadow-xl sticky top-0 z-40">
            <div class="px-4 sm:px-6 py-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-purple-700 rounded-xl flex items-center justify-center">
                            <?php echo iconSvg('shield', 'w-5 h-5 text-purple-200'); ?>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-base font-bold">MHO / Admin Panel</h1>
                                <span class="hidden sm:block text-xs bg-purple-700 px-2 py-0.5 rounded-full text-purple-200 border border-purple-600">Municipal Health Officer</span>
                            </div>
                            <p class="text-xs text-purple-300"><?php echo esc($RHU_INFO['name'] ?? 'Nasugbu RHU'); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <a href="<?php echo esc(tabUrl($tab, !$showNotifs)); ?>" class="relative p-2 rounded-lg hover:bg-white/10 inline-flex">
                                <?php echo iconSvg('bell', 'w-5 h-5'); ?>
                                <?php if (count(array_filter($adminNotifs, static fn ($notif) => !empty($notif['unread']))) > 0): ?>
                                    <span class="absolute top-1 right-1 w-2 h-2 bg-red-400 rounded-full"></span>
                                <?php endif; ?>
                            </a>
                            <?php if ($showNotifs): ?>
                                <div class="absolute right-0 top-10 w-72 max-w-[90vw] bg-white rounded-xl shadow-2xl border border-gray-100 z-50">
                                    <div class="p-3 border-b border-gray-100"><p class="font-bold text-gray-900 text-sm">Admin Notifications</p></div>
                                    <?php foreach ($adminNotifs as $notif): ?>
                                        <div class="p-3 border-b border-gray-50 <?php echo !empty($notif['unread']) ? 'bg-purple-50/60' : ''; ?>">
                                            <div class="flex items-start gap-2">
                                                <span class="flex-shrink-0 mt-0.5 text-<?php echo esc($notif['type'] === 'alert' ? 'red' : ($notif['type'] === 'warning' ? 'yellow' : 'green')); ?>-500"><?php echo iconSvg(iconNameForType($notif['type']), 'w-4 h-4'); ?></span>
                                                <p class="text-xs text-gray-800"><?php echo esc($notif['msg']); ?></p>
                                            </div>
                                            <p class="text-xs text-gray-400 mt-1 ml-6"><?php echo esc($notif['time']); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="RHUDashboard.php" class="hidden sm:flex items-center gap-1.5 bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all">
                            <?php echo iconSvg('stethoscope', 'w-3.5 h-3.5'); ?> Clinical Dashboard
                        </a>
                        <div class="flex items-center gap-2 bg-white/10 rounded-lg px-3 py-1.5">
                            <div class="w-7 h-7 bg-purple-500 rounded-full flex items-center justify-center">
                                <?php echo iconSvg('shield', 'w-3.5 h-3.5 text-white'); ?>
                            </div>
                            <span class="text-sm font-semibold hidden sm:block">MHO</span>
                        </div>
                        <a href="LandingPage.php" class="p-2 rounded-lg hover:bg-white/10" aria-label="Exit">
                            <?php echo iconSvg('logout', 'w-4 h-4'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <div class="hidden sm:flex px-4 gap-1 overflow-x-auto pb-0.5">
                <?php foreach ($tabLabelMap as $key => $label): ?>
                    <?php $active = $tab === $key; $icon = $tabIconMap[$key]; ?>
                    <a href="<?php echo esc(tabUrl($key)); ?>" class="flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-t-lg transition-all whitespace-nowrap flex-shrink-0 <?php echo $active ? 'bg-white text-purple-800' : 'text-purple-200 hover:bg-white/10'; ?>">
                        <?php echo iconSvg($icon, 'w-3.5 h-3.5'); ?>
                        <?php echo esc($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </header>

        <nav class="sm:hidden fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-200 safe-area-pb">
            <div class="flex items-stretch overflow-x-auto">
                <?php foreach (['overview' => 'Overview', 'users' => 'Users', 'staff' => 'Staff', 'residents' => 'Residents', 'reports' => 'Reports', 'audit' => 'Audit', 'system' => 'Settings', 'security' => 'Security'] as $key => $label): ?>
                    <?php $active = $tab === $key; ?>
                    <a href="<?php echo esc(tabUrl($key)); ?>" class="flex-1 min-w-[72px] flex flex-col items-center justify-center gap-0.5 py-2 transition-colors relative <?php echo $active ? 'text-purple-600' : 'text-gray-400'; ?>">
                        <?php if ($active): ?><span class="absolute top-0 left-1/2 -translate-x-1/2 w-6 h-0.5 bg-purple-500 rounded-full"></span><?php endif; ?>
                        <?php echo iconSvg($tabIconMap[$key], 'w-5 h-5'); ?>
                        <span class="text-[10px] font-semibold leading-none mt-0.5"><?php echo esc($label); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>

        <main class="flex-1 max-w-7xl mx-auto w-full px-3 sm:px-4 py-4 sm:py-6 space-y-4 sm:space-y-5 pb-28 sm:pb-6">
            <?php if ($tab === 'overview'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3">
                        <span class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5"><?php echo iconSvg('alert', 'w-5 h-5'); ?></span>
                        <div>
                            <p class="font-bold text-red-800 text-sm">Security Alert � Suspicious Login Attempt</p>
                            <p class="text-sm text-red-700">Failed login from external IP 203.177.55.12 (3 attempts) at 08:44 today. Account temporarily flagged.</p>
                        </div>
                        <button class="text-xs bg-red-600 text-white px-3 py-1.5 rounded-lg whitespace-nowrap">Review</button>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <?php renderMetricCard('Registered Residents', 104, '+15 this month', 'users', 'bg-blue-600', 'text-blue-600'); ?>
                        <?php renderMetricCard('Active Staff', count(array_filter($mockRHUStaff, static fn ($staff) => $staff['status'] === 'active')), 'of ' . count($mockRHUStaff) . ' total', 'stethoscope', 'bg-emerald-600', 'text-emerald-600'); ?>
                        <?php renderMetricCard('Active BHWs', count(array_filter($mockBHWs, static fn ($bhw) => !empty($bhw['activeStatus']))), 'of ' . count($mockBHWs) . ' total', 'users', 'bg-teal-600', 'text-teal-600'); ?>
                        <?php renderMetricCard('System Uptime', '99.8%', 'Last 30 days', 'server', 'bg-purple-600', 'text-purple-600'); ?>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-gray-900 mb-4">System Growth (Jan�Jun 2026)</h3>
                            <div class="space-y-3">
                                <?php foreach ($systemMetrics as $row): ?>
                                    <div class="flex items-center gap-3">
                                        <span class="w-10 text-xs font-semibold text-gray-600"><?php echo esc($row['month']); ?></span>
                                        <div class="flex-1 grid grid-cols-2 gap-2 text-xs">
                                            <div>
                                                <div class="flex justify-between mb-1"><span class="text-gray-500">Residents</span><span class="font-semibold text-blue-600"><?php echo esc($row['residents']); ?></span></div>
                                                <?php echo progressBar(min(100, $row['residents']), 'bg-blue-500'); ?>
                                            </div>
                                            <div>
                                                <div class="flex justify-between mb-1"><span class="text-gray-500">Consultations</span><span class="font-semibold text-green-600"><?php echo esc($row['consultations']); ?></span></div>
                                                <?php echo progressBar(min(100, (int) round($row['consultations'] / 4)), 'bg-green-500'); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-gray-900 mb-4">Module Usage (Total Sessions)</h3>
                            <div class="space-y-3">
                                <?php foreach ($moduleUsage as $module): ?>
                                    <?php $width = min(100, (int) round($module['sessions'] / 5)); ?>
                                    <div>
                                        <div class="flex justify-between mb-1 text-xs"><span class="text-gray-500"><?php echo esc($module['module']); ?></span><span class="font-semibold text-purple-700"><?php echo esc($module['sessions']); ?></span></div>
                                        <?php echo progressBar($width, 'bg-purple-500'); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-bold text-gray-900">Clinical Programs Dashboard</h3>
                                <p class="text-xs text-gray-500 mt-0.5">As MHO, you have full access to all health programs</p>
                            </div>
                            <a href="RHUDashboard.php" class="flex items-center gap-1.5 text-sm font-bold text-white bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors">Open Full Dashboard <?php echo iconSvg('right', 'w-4 h-4'); ?></a>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5">
                            <?php
                            $clinicalPrograms = [
                                ['label' => 'OPD / Consultations', 'color' => 'bg-blue-600', 'desc' => 'Patient records, ICD-10, prescriptions'],
                                ['label' => 'Patient Records', 'color' => 'bg-purple-600', 'desc' => 'Admitted & discharged patients'],
                                ['label' => 'Blood Inventory', 'color' => 'bg-red-600', 'desc' => 'Stock levels, expiry, resupply'],
                                ['label' => 'Donor Registry', 'color' => 'bg-rose-600', 'desc' => 'Donor profiles & availability'],
                                ['label' => 'Blood Drives', 'color' => 'bg-orange-600', 'desc' => 'Schedule & manage drives'],
                                ['label' => 'Immunization (EPI)', 'color' => 'bg-indigo-600', 'desc' => 'Vaccine schedules & coverage'],
                                ['label' => 'Maternal Health', 'color' => 'bg-pink-600', 'desc' => 'Prenatal, FP, postnatal'],
                                ['label' => 'TB-DOTS', 'color' => 'bg-amber-600', 'desc' => 'Case management & adherence'],
                                ['label' => 'Nutrition (OPT+)', 'color' => 'bg-yellow-600', 'desc' => 'SAM/MAM classification'],
                                ['label' => 'Disease Surveillance', 'color' => 'bg-red-700', 'desc' => 'PIDSR notifiable diseases'],
                                ['label' => 'Medicine Inventory', 'color' => 'bg-teal-600', 'desc' => 'Drug stock & expiry'],
                                ['label' => 'DOH Reports', 'color' => 'bg-gray-600', 'desc' => 'FHSIS, PIDSR, NTP submissions'],
                            ];
                            ?>
                            <?php foreach ($clinicalPrograms as $program): ?>
                                <a href="RHUDashboard.php" class="flex items-start gap-2.5 p-3 bg-gray-50 hover:bg-gray-100 rounded-xl border border-gray-100 transition-colors group">
                                    <div class="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-0.5 <?php echo esc($program['color']); ?>"></div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-gray-800 leading-tight"><?php echo esc($program['label']); ?></p>
                                        <p class="text-xs text-gray-400 mt-0.5 leading-tight hidden sm:block"><?php echo esc($program['desc']); ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-gray-900 mb-3">Quick Actions</h3>
                            <div class="space-y-2">
                                <?php
                                $quickActions = [
                                    ['label' => 'Add New Staff Account', 'icon' => 'plus', 'color' => 'bg-blue-600', 'tab' => 'staff', 'action' => 'new'],
                                    ['label' => 'View Audit Logs', 'icon' => 'database', 'color' => 'bg-slate-600', 'tab' => 'audit'],
                                    ['label' => 'Export System Report', 'icon' => 'download', 'color' => 'bg-green-600', 'tab' => 'reports'],
                                    ['label' => 'Manage Permissions', 'icon' => 'lock', 'color' => 'bg-purple-600', 'tab' => 'security'],
                                    ['label' => 'System Settings', 'icon' => 'settings', 'color' => 'bg-gray-600', 'tab' => 'system'],
                                    ['label' => 'Resident Registry', 'icon' => 'check', 'color' => 'bg-teal-600', 'tab' => 'residents'],
                                ];
                                ?>
                                <?php foreach ($quickActions as $action): ?>
                                    <a href="<?php echo esc(tabUrl($action['tab'], false, isset($action['action']) ? ['action' => $action['action']] : [])); ?>" class="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 transition-colors text-left">
                                        <div class="w-8 h-8 <?php echo esc($action['color']); ?> rounded-lg flex items-center justify-center flex-shrink-0 text-white"><?php echo iconSvg($action['icon'], 'w-4 h-4'); ?></div>
                                        <span class="text-sm font-semibold text-gray-700"><?php echo esc($action['label']); ?></span>
                                        <span class="w-4 h-4 text-gray-400 ml-auto"><?php echo iconSvg('right', 'w-4 h-4'); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-gray-900 mb-3">Recent Audit Log</h3>
                            <div class="space-y-2">
                                <?php foreach (array_slice($mockAuditLogs, 0, 5) as $log): ?>
                                    <div class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-gray-50">
                                        <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0 <?php echo $log['status'] === 'success' ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-semibold text-gray-800 truncate"><?php echo esc($log['user']); ?> <span class="font-normal text-gray-500">� <?php echo esc($log['action']); ?></span></p>
                                            <p class="text-xs text-gray-400"><?php echo esc($log['timestamp']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <a href="<?php echo esc(tabUrl('audit')); ?>" class="text-xs text-purple-600 hover:underline w-full text-center pt-1 block">View all logs ?</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'users'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('users', 'w-5 h-5 text-blue-600'); ?> User Management</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <?php
                        $userStats = [
                            ['label' => 'Total Users', 'value' => 104 + count($mockRHUStaff) + count($mockBHWs), 'color' => 'text-blue-600'],
                            ['label' => 'Residents', 'value' => 104, 'color' => 'text-emerald-600'],
                            ['label' => 'Staff', 'value' => count($mockRHUStaff), 'color' => 'text-purple-600'],
                            ['label' => 'BHWs', 'value' => count($mockBHWs), 'color' => 'text-teal-600'],
                        ];
                        ?>
                        <?php foreach ($userStats as $stat): ?>
                            <div class="bg-white rounded-xl p-3 sm:p-4 shadow-sm border border-gray-100 text-center">
                                <p class="text-2xl font-black <?php echo esc($stat['color']); ?>"><?php echo esc($stat['value']); ?></p>
                                <p class="text-xs font-semibold text-gray-500 mt-0.5"><?php echo esc($stat['label']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-bold text-gray-900">User Roles & Access Levels</h3>
                            <button class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">
                                <?php echo iconSvg('plus', 'w-4 h-4'); ?> Add User
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[600px]">
                                <thead class="bg-gray-50"><tr>
                                    <?php foreach (['Role', 'Access Level', 'Modules', 'Active Users', 'Actions'] as $heading): ?>
                                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap"><?php echo esc($heading); ?></th>
                                    <?php endforeach; ?>
                                </tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php
                                    $roles = [
                                        ['role' => 'RHU Admin', 'level' => 'Full Access', 'modules' => 'All modules + system settings', 'count' => 1, 'color' => 'bg-purple-100 text-purple-700'],
                                        ['role' => 'Municipal Health Officer', 'level' => 'Clinical + Reports', 'modules' => 'OPD, Maternal, FP, TB, Disease, Reports', 'count' => 1, 'color' => 'bg-blue-100 text-blue-700'],
                                        ['role' => 'Rural Health Physician', 'level' => 'Clinical', 'modules' => 'OPD, Patients, Certificates, Referrals', 'count' => 1, 'color' => 'bg-blue-100 text-blue-700'],
                                        ['role' => 'Midwife', 'level' => 'Maternal/OB', 'modules' => 'Maternal, Immunization, Vital Statistics', 'count' => 1, 'color' => 'bg-pink-100 text-pink-700'],
                                        ['role' => 'Public Health Nurse', 'level' => 'Clinical + Programs', 'modules' => 'OPD, Immunization, Nutrition, Disease', 'count' => 2, 'color' => 'bg-green-100 text-green-700'],
                                        ['role' => 'Sanitary Inspector', 'level' => 'Environmental', 'modules' => 'Sanitation, Certificates', 'count' => 1, 'color' => 'bg-teal-100 text-teal-700'],
                                        ['role' => 'BHW', 'level' => 'Community', 'modules' => 'Immunization, Nutrition (view), BHW Reports', 'count' => count($mockBHWs), 'color' => 'bg-emerald-100 text-emerald-700'],
                                        ['role' => 'Resident', 'level' => 'Self-service', 'modules' => 'Own records, certificates, events (read)', 'count' => 104, 'color' => 'bg-gray-100 text-gray-700'],
                                    ];
                                    ?>
                                    <?php foreach ($roles as $role): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-semibold text-gray-900"><?php echo esc($role['role']); ?></td>
                                            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full font-bold <?php echo esc($role['color']); ?>"><?php echo esc($role['level']); ?></span></td>
                                            <td class="px-4 py-3 text-xs text-gray-600 max-w-[200px] truncate"><?php echo esc($role['modules']); ?></td>
                                            <td class="px-4 py-3 font-bold text-gray-900"><?php echo esc($role['count']); ?></td>
                                            <td class="px-4 py-3"><div class="flex gap-1"><button class="p-1 hover:bg-blue-50 rounded"><?php echo iconSvg('eye', 'w-4 h-4 text-blue-600'); ?></button><button class="p-1 hover:bg-green-50 rounded"><?php echo iconSvg('edit', 'w-4 h-4 text-green-600'); ?></button></div></td>
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
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('stethoscope', 'w-5 h-5 text-blue-600'); ?> Staff Accounts</h2>
                        <a href="<?php echo esc(tabUrl('staff', false, ['action' => 'new'])); ?>" class="flex items-center gap-1.5 px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-lg text-xs sm:text-sm font-semibold hover:bg-blue-700">
                            <?php echo iconSvg('plus', 'w-4 h-4'); ?> New Account
                        </a>
                    </div>
                    <?php if (!empty($staffCreateSuccess)): ?>
                        <div class="rounded-2xl border border-green-200 bg-green-50 p-4 text-green-800">
                            <?php echo esc($staffCreateSuccess); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($staffCreateError): ?>
                        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-800">
                            <?php echo esc($staffCreateError); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (($_GET['action'] ?? '') === 'new'): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Create New Staff Account</h3>
                                <form method="post" class="space-y-4">
                                    <input type="hidden" name="action" value="create_staff">
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <label class="block text-sm">First name *
                                            <input required name="first_name" value="<?php echo esc($staffCreateData['first_name']); ?>" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Juan">
                                        </label>
                                        <label class="block text-sm">Last name *
                                            <input required name="last_name" value="<?php echo esc($staffCreateData['last_name']); ?>" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Dela Cruz">
                                        </label>
                                    </div>
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <label class="block text-sm">Email *
                                            <input required type="email" name="email" value="<?php echo esc($staffCreateData['email']); ?>" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="user@nasugbu.gov.ph">
                                        </label>
                                        <label class="block text-sm">Password *
                                            <input required type="password" name="password" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Choose a strong password">
                                        </label>
                                    </div>
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <label class="block text-sm">Staff Type *
                                            <select required name="staff_type" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2">
                                                <?php foreach (['ADMIN_STAFF' => 'Administrative Staff', 'MIDWIFE' => 'Midwife', 'NURSE' => 'Public Health Nurse', 'MEDTECH' => 'Medical Technologist', 'SANITARY_INSPECTOR' => 'Sanitary Inspector', 'BHW' => 'Barangay Health Worker', 'PHYSICIAN' => 'Rural Health Physician'] as $value => $label): ?>
                                                    <option value="<?php echo esc($value); ?>" <?php echo $staffCreateData['staff_type'] === $value ? 'selected' : ''; ?>><?php echo esc($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label class="block text-sm">Specialization
                                            <input name="specialization" value="<?php echo esc($staffCreateData['specialization']); ?>" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="Family Medicine">
                                        </label>
                                    </div>
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <label class="block text-sm">License Number
                                            <input name="license_number" value="<?php echo esc($staffCreateData['license_number']); ?>" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="MD-2025-XXXX">
                                        </label>
                                        <label class="block text-sm">License Expiry
                                            <input type="date" name="license_expiry" value="<?php echo esc($staffCreateData['license_expiry']); ?>" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2">
                                        </label>
                                    </div>
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <label class="block text-sm">Phone Number
                                            <input name="phone_number" value="<?php echo esc($staffCreateData['phone_number']); ?>" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="0917xxxxxxx">
                                        </label>
                                        <label class="block text-sm">Address
                                            <input name="address" value="<?php echo esc($staffCreateData['address']); ?>" class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="RHU Compound, Poblacion">
                                        </label>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <a href="<?php echo esc(tabUrl('staff')); ?>" class="text-sm text-gray-600 hover:underline">Cancel</a>
                                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">Create Staff Account</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                        <div class="overflow-x-auto bg-white rounded-xl shadow-sm border border-gray-100">
                        <table class="w-full text-sm min-w-[700px]">
                            <thead class="bg-gray-50"><tr>
                                <?php foreach (['ID', 'Name', 'Position', 'License No.', 'PRC Expiry', 'PhilHealth Accr.', 'Status', 'Actions'] as $heading): ?>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap"><?php echo esc($heading); ?></th>
                                <?php endforeach; ?>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($mockRHUStaff as $staff): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-mono text-xs text-gray-500"><?php echo esc($staff['id']); ?></td>
                                        <td class="px-4 py-3"><p class="font-semibold text-gray-900 whitespace-nowrap"><?php echo esc($staff['name']); ?></p><p class="text-xs text-gray-400"></p></td>
                                        <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap"><?php echo esc($staff['position']); ?></td>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-600"><?php echo esc($staff['licenseNo']); ?></td>
                                        <td class="px-4 py-3"><span class="text-xs font-semibold <?php echo $staff['prcExpiry'] === '2026-06-30' ? 'text-red-600' : 'text-gray-600'; ?>"><?php echo esc($staff['prcExpiry']); ?></span><?php if ($staff['prcExpiry'] === '2026-06-30'): ?><span class="ml-1 text-xs text-red-500">? Expiring</span><?php endif; ?></td>
                                        <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo $staff['philhealthAccreditation'] === 'Active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>"><?php echo esc($staff['philhealthAccreditation']); ?></span></td>
                                        <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo $staff['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>"><?php echo esc($staff['status']); ?></span></td>
                                        <td class="px-4 py-3"><div class="flex gap-1"><button class="p-1 hover:bg-blue-50 rounded" title="View"><?php echo iconSvg('eye', 'w-4 h-4 text-blue-600'); ?></button><button class="p-1 hover:bg-green-50 rounded" title="Edit"><?php echo iconSvg('edit', 'w-4 h-4 text-green-600'); ?></button><button class="p-1 hover:bg-purple-50 rounded" title="Reset password"><?php echo iconSvg('key', 'w-4 h-4 text-purple-600'); ?></button></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'residents'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('check', 'w-5 h-5 text-teal-600'); ?> Resident Registry</h2>
                        <div class="flex gap-2"><button class="flex items-center gap-1.5 px-2.5 sm:px-3 py-2 border border-gray-300 rounded-lg text-xs sm:text-sm text-gray-600 hover:bg-gray-50"><?php echo iconSvg('download', 'w-4 h-4'); ?> Export</button></div>
                    </div>
                    <div class="overflow-x-auto bg-white rounded-xl shadow-sm border border-gray-100">
                        <table class="w-full text-sm min-w-[700px]">
                            <thead class="bg-gray-50"><tr>
                                <?php foreach (['ID', 'Name', 'Barangay', 'PhilHealth No.', 'Registered', 'Last Login', 'Status', 'Actions'] as $heading): ?>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap"><?php echo esc($heading); ?></th>
                                <?php endforeach; ?>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($mockResidents as $resident): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-mono text-xs text-gray-500"><?php echo esc($resident['id']); ?></td>
                                        <td class="px-3 py-2.5 font-semibold text-gray-900 whitespace-nowrap"><?php echo esc($resident['name']); ?></td>
                                        <td class="px-3 py-2.5 text-gray-600"><?php echo esc($resident['barangay']); ?></td>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-600"><?php echo $resident['philhealthNo'] ? esc($resident['philhealthNo']) : '<span class="text-gray-300">�</span>'; ?></td>
                                        <td class="px-4 py-3 text-xs text-gray-500"><?php echo esc($resident['registeredDate']); ?></td>
                                        <td class="px-4 py-3 text-xs text-gray-500"><?php echo esc($resident['lastLogin']); ?></td>
                                        <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo $resident['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>"><?php echo esc($resident['status']); ?></span></td>
                                        <td class="px-4 py-3"><div class="flex gap-1"><button class="p-1 hover:bg-blue-50 rounded"><?php echo iconSvg('eye', 'w-4 h-4 text-blue-600'); ?></button><button class="p-1 hover:bg-red-50 rounded"><?php echo iconSvg('trash', 'w-4 h-4 text-red-500'); ?></button></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'audit'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('database', 'w-5 h-5 text-slate-600'); ?> Audit Logs</h2>
                        <div class="flex gap-2"><button class="flex items-center gap-1.5 px-2.5 sm:px-3 py-2 border border-gray-300 rounded-lg text-xs sm:text-sm text-gray-600 hover:bg-gray-50"><?php echo iconSvg('download', 'w-4 h-4'); ?> Export CSV</button><button class="flex items-center gap-1.5 px-2.5 sm:px-3 py-2 border border-gray-300 rounded-lg text-xs sm:text-sm text-gray-600 hover:bg-gray-50"><?php echo iconSvg('refresh', 'w-4 h-4'); ?> Refresh</button></div>
                    </div>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-start gap-3">
                        <span class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5"><?php echo iconSvg('alert', 'w-5 h-5'); ?></span>
                        <p class="text-sm text-yellow-700">Audit logs are retained for <strong>5 years</strong> per DOH IT Security Policy and Data Privacy Act (RA 10173) requirements. All logs are read-only and tamper-evident.</p>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[900px]">
                                <thead class="bg-gray-50"><tr>
                                    <?php foreach (['Log ID', 'User', 'Role', 'Action', 'Module', 'Timestamp', 'IP Address', 'Status'] as $heading): ?>
                                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap"><?php echo esc($heading); ?></th>
                                    <?php endforeach; ?>
                                </tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($mockAuditLogs as $log): ?>
                                        <tr class="hover:bg-gray-50 <?php echo $log['status'] === 'failed' ? 'bg-red-50/50' : ''; ?>">
                                            <td class="px-4 py-3 font-mono text-xs text-gray-500"><?php echo esc($log['id']); ?></td>
                                            <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap text-xs"><?php echo esc($log['user']); ?></td>
                                            <td class="px-4 py-3"><span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full"><?php echo esc($log['role']); ?></span></td>
                                            <td class="px-4 py-3 text-xs text-gray-700 max-w-[220px] truncate"><?php echo esc($log['action']); ?></td>
                                            <td class="px-4 py-3 text-xs text-gray-600"><?php echo esc($log['module']); ?></td>
                                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap"><?php echo esc($log['timestamp']); ?></td>
                                            <td class="px-4 py-3 font-mono text-xs text-gray-500"><?php echo esc($log['ip']); ?></td>
                                            <td class="px-4 py-3"><span class="text-xs px-2 py-0.5 rounded-full font-bold <?php echo $log['status'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>"><?php echo esc($log['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'system'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('settings', 'w-5 h-5 text-gray-600'); ?> System Settings</h2>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100 space-y-3 sm:space-y-4">
                            <h3 class="font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('building', 'w-4 h-4 text-blue-600'); ?> RHU Information</h3>
                            <?php
                            $infoRows = [
                                ['label' => 'RHU Name', 'value' => $RHU_INFO['name'] ?? 'Nasugbu RHU I'],
                                ['label' => 'RHU Code', 'value' => $RHU_INFO['code'] ?? 'RHU-NSG-001'],
                                ['label' => 'Municipality', 'value' => ($RHU_INFO['municipality'] ?? 'Nasugbu') . ', ' . ($RHU_INFO['province'] ?? 'Batangas')],
                                ['label' => 'Region', 'value' => $RHU_INFO['region'] ?? 'Region IV-A'],
                                ['label' => 'Email', 'value' => $RHU_INFO['email'] ?? 'rhu1@nasugbu.gov.ph'],
                                ['label' => 'Contact', 'value' => $RHU_INFO['contactNumber'] ?? '(043) 416-1234'],
                            ];
                            ?>
                            <?php foreach ($infoRows as $item): ?>
                                <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                                    <span class="text-sm text-gray-500 font-semibold"><?php echo esc($item['label']); ?></span>
                                    <span class="text-sm text-gray-900 font-medium text-right"><?php echo esc($item['value']); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <a href="#" class="flex items-center gap-2 text-sm text-blue-600 hover:underline font-semibold"><?php echo iconSvg('edit', 'w-4 h-4'); ?> Edit RHU Info</a>
                        </div>

                        <div class="space-y-3 sm:space-y-4">
                            <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                                <h3 class="font-bold text-gray-900 mb-3 flex items-center gap-2"><?php echo iconSvg('globe', 'w-4 h-4 text-green-600'); ?> Integrations</h3>
                                <div class="space-y-3">
                                    <?php foreach ([['name' => 'DOH FHSIS Online', 'status' => 'connected', 'color' => 'green'], ['name' => 'PhilHealth eClaims', 'status' => 'connected', 'color' => 'green'], ['name' => 'PIDSR Online (RESU)', 'status' => 'connected', 'color' => 'green'], ['name' => 'NTP-ITIS (TB Program)', 'status' => 'connected', 'color' => 'green'], ['name' => 'LCRN (Civil Registry)', 'status' => 'pending', 'color' => 'yellow']] as $integration): ?>
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="text-sm text-gray-700"><?php echo esc($integration['name']); ?></span>
                                            <span class="text-xs px-2 py-0.5 rounded-full font-bold <?php echo $integration['color'] === 'green' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>"><?php echo esc($integration['status']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                                <h3 class="font-bold text-gray-900 mb-3 flex items-center gap-2"><?php echo iconSvg('server', 'w-4 h-4 text-purple-600'); ?> System Status</h3>
                                <?php foreach ([['label' => 'Database', 'value' => 'Healthy', 'ok' => true], ['label' => 'Storage Used', 'value' => '2.4 GB / 50 GB', 'ok' => true], ['label' => 'Last Backup', 'value' => '2026-06-10 01:00 AM', 'ok' => true], ['label' => 'SSL Certificate', 'value' => 'Valid until Jan 2027', 'ok' => true]] as $status): ?>
                                    <div class="flex items-center justify-between py-1.5 border-b border-gray-50 last:border-0">
                                        <span class="text-sm text-gray-500"><?php echo esc($status['label']); ?></span>
                                        <div class="flex items-center gap-1.5"><div class="w-1.5 h-1.5 rounded-full <?php echo $status['ok'] ? 'bg-green-500' : 'bg-red-500'; ?>"></div><span class="text-xs font-semibold text-gray-700"><?php echo esc($status['value']); ?></span></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'security'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('lock', 'w-5 h-5 text-red-600'); ?> Security & Access Control</h2>
                    <div class="grid md:grid-cols-3 gap-4">
                        <?php foreach ([['label' => 'Failed Logins (24h)', 'value' => 3, 'sub' => '1 external IP flagged', 'color' => 'text-red-600', 'bg' => 'bg-red-50 border-red-200'], ['label' => 'Active Sessions', 'value' => 4, 'sub' => 'Staff online now', 'color' => 'text-blue-600', 'bg' => 'bg-blue-50 border-blue-200'], ['label' => 'Locked Accounts', 'value' => 0, 'sub' => 'All clear', 'color' => 'text-green-600', 'bg' => 'bg-green-50 border-green-200']] as $metric): ?>
                            <div class="rounded-xl border p-4 <?php echo esc($metric['bg']); ?>">
                                <p class="text-3xl font-black <?php echo esc($metric['color']); ?>"><?php echo esc($metric['value']); ?></p>
                                <p class="font-bold text-gray-800 text-sm mt-1"><?php echo esc($metric['label']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo esc($metric['sub']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                        <h3 class="font-bold text-gray-900 mb-4">Security Policies</h3>
                        <div class="space-y-3">
                            <?php foreach ([['policy' => 'Password Complexity', 'status' => 'Enforced', 'detail' => 'Min 8 chars, upper/lower/number/symbol required'], ['policy' => 'MFA for Admin', 'status' => 'Enabled', 'detail' => 'TOTP or SMS required for all admin logins'], ['policy' => 'Session Timeout', 'status' => 'Active', 'detail' => 'Auto-logout after 30 minutes of inactivity'], ['policy' => 'Login Attempt Limit', 'status' => 'Enforced', 'detail' => 'Account locked after 5 failed attempts (15 min)'], ['policy' => 'reCAPTCHA', 'status' => 'Active', 'detail' => 'Google reCAPTCHA on all login forms'], ['policy' => 'Data Encryption', 'status' => 'Active', 'detail' => 'AES-256 at rest, TLS 1.3 in transit'], ['policy' => 'Audit Logging', 'status' => 'Enabled', 'detail' => 'All data access and changes are logged'], ['policy' => 'IP Allowlist (Admin)', 'status' => 'Pending', 'detail' => 'Restrict admin access to RHU LAN IPs only']] as $policy): ?>
                                <div class="flex items-start justify-between gap-3 py-2.5 border-b border-gray-50 last:border-0">
                                    <div>
                                        <p class="text-sm font-bold text-gray-900"><?php echo esc($policy['policy']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo esc($policy['detail']); ?></p>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full font-bold flex-shrink-0 <?php echo $policy['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'; ?>"><?php echo esc($policy['status']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'reports'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('bar', 'w-5 h-5 text-purple-600'); ?> System Reports & Analytics</h2>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-gray-900 mb-4">System Usage Trend</h3>
                            <div class="space-y-2">
                                <?php foreach ($systemMetrics as $row): ?>
                                    <div class="flex items-center gap-3 text-xs">
                                        <span class="w-8 font-semibold text-gray-600"><?php echo esc($row['month']); ?></span>
                                        <div class="flex-1 grid grid-cols-2 gap-2">
                                            <div>
                                                <div class="flex justify-between mb-1"><span class="text-gray-500">Residents</span><span class="font-semibold text-purple-700"><?php echo esc($row['residents']); ?></span></div>
                                                <?php echo progressBar(min(100, (int) round($row['residents'] * 0.9)), 'bg-purple-500'); ?>
                                            </div>
                                            <div>
                                                <div class="flex justify-between mb-1"><span class="text-gray-500">Consultations</span><span class="font-semibold text-green-600"><?php echo esc($row['consultations']); ?></span></div>
                                                <?php echo progressBar(min(100, (int) round($row['consultations'] / 4)), 'bg-green-500'); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                            <h3 class="font-bold text-gray-900 mb-4">Module Activity</h3>
                            <div class="space-y-2">
                                <?php foreach ($moduleUsage as $module): ?>
                                    <div>
                                        <div class="flex justify-between mb-1 text-xs"><span class="text-gray-500"><?php echo esc($module['module']); ?></span><span class="font-semibold text-purple-700"><?php echo esc($module['sessions']); ?></span></div>
                                        <?php echo progressBar(min(100, (int) round($module['sessions'] / 5)), 'bg-purple-500'); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                        <h3 class="font-bold text-gray-900 mb-4">Available Reports</h3>
                        <div class="grid md:grid-cols-2 gap-3">
                            <?php foreach ([['title' => 'User Activity Report', 'desc' => 'Logins, sessions, module access per user', 'period' => 'Monthly'], ['title' => 'System Usage Summary', 'desc' => 'Transaction counts per module', 'period' => 'Monthly/Quarterly'], ['title' => 'Security Audit Report', 'desc' => 'Failed logins, access violations, flagged IPs', 'period' => 'Weekly'], ['title' => 'Staff Performance Report', 'desc' => 'Consultations, certificates, referrals per staff', 'period' => 'Monthly'], ['title' => 'Resident Enrollment Report', 'desc' => 'New registrations, active users, barangay breakdown', 'period' => 'Monthly'], ['title' => 'DOH Compliance Summary', 'desc' => 'FHSIS, PIDSR, NTP submission status', 'period' => 'Quarterly']] as $report): ?>
                                <div class="flex items-start justify-between gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100">
                                    <div>
                                        <p class="font-bold text-gray-900 text-sm"><?php echo esc($report['title']); ?></p>
                                        <p class="text-xs text-gray-500 mt-0.5"><?php echo esc($report['desc']); ?></p>
                                        <span class="text-xs text-purple-600 font-semibold mt-1 inline-block"><?php echo esc($report['period']); ?></span>
                                    </div>
                                    <button class="flex items-center gap-1 text-xs text-blue-600 hover:underline font-semibold flex-shrink-0"><?php echo iconSvg('download', 'w-3.5 h-3.5'); ?> Export</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
