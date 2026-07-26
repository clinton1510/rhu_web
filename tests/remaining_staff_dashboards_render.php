<?php
declare(strict_types=1);

ini_set('session.save_path', sys_get_temp_dir());
$_SERVER['REQUEST_METHOD'] = 'GET';
[$script, $dashboard, $tab] = array_pad($argv, 3, '');
$_GET['tab'] = $tab ?: 'overview';

$allowed = ['NurseDashboard.php', 'MidwifeDashboard.php', 'MedTechDashboard.php', 'SanitaryDashboard.php', 'BHWDashboard.php'];
if (!in_array($dashboard, $allowed, true)) exit(2);
if (session_status() === PHP_SESSION_NONE) session_start();
$staffTypes = [
    'NurseDashboard.php' => 'NURSE',
    'MidwifeDashboard.php' => 'MIDWIFE',
    'MedTechDashboard.php' => 'MEDTECH',
    'SanitaryDashboard.php' => 'SANITARY_INSPECTOR',
];
if ($dashboard === 'BHWDashboard.php') {
    $_SESSION['bhw_user'] = ['id' => 1, 'staff_id' => 1, 'bhw_id' => 1];
} else {
    $_SESSION['rhu_staff_login'] = ['id' => 18, 'staff_id' => 8, 'staff_type' => $staffTypes[$dashboard]];
}

ob_start();
require dirname(__DIR__) . '/src/app/components/' . $dashboard;
$html = (string)ob_get_clean();
if (!str_contains(strtolower($html), '<!doctype html>')) {
    fwrite(STDERR, "Render failed: {$dashboard} {$tab}\n");
    exit(1);
}
echo "PASS {$dashboard} {$tab}\n";
