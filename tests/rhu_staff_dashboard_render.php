<?php
declare(strict_types=1);

$tab = $argv[1] ?? 'overview';
$_GET['tab'] = $tab;
$_SERVER['REQUEST_METHOD'] = 'GET';
ini_set('session.save_path', sys_get_temp_dir());
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['rhu_staff_login'] = ['id' => 18, 'staff_id' => 8, 'role' => 'Administrative Staff'];

ob_start();
require dirname(__DIR__) . '/src/app/components/RHUDashboard.php';
$html = (string)ob_get_clean();

if (!str_contains($html, '<!DOCTYPE html>') || !str_contains($html, 'RedPulse RHU')) {
    fwrite(STDERR, "RHU Staff dashboard did not render correctly for tab: {$tab}\n");
    exit(1);
}

if (isset($pdo) && $pdo instanceof PDO) {
    $expectedResidents = (int)$pdo->query('SELECT COUNT(*) FROM residents')->fetchColumn();
    $expectedConsultations = (int)$pdo->query('SELECT COUNT(*) FROM consultations')->fetchColumn();
    $expectedReports = (int)$pdo->query('SELECT COUNT(*) FROM fhsis_reports')->fetchColumn();
    if (count($allResidents) !== $expectedResidents
        || count($mockOPDConsultations) !== $expectedConsultations
        || count($mockDOHReports) !== $expectedReports) {
        fwrite(STDERR, "RHU Staff hydration count mismatch for tab: {$tab}\n");
        exit(1);
    }
}

echo "PASS {$tab}\n";
