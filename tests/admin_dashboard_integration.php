<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/app/components/db.php';
require dirname(__DIR__) . '/src/app/components/portal.php';
require dirname(__DIR__) . '/src/app/components/admin_extended.php';

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "FAIL: database connection unavailable\n");
    exit(1);
}

$checks = [];
$check = static function (bool $condition, string $label) use (&$checks): void {
    $checks[] = [$condition, $label];
};

foreach ([
    'users', 'staff', 'residents', 'pregnancies', 'vaccination_records',
    'medicine_inventory', 'vital_statistics_births', 'vital_statistics_deaths',
    'health_certificates', 'portal_events', 'event_registrations',
    'portal_settings', 'portal_notifications', 'permissions', 'role_permissions',
    'fhsis_reports', 'pidsr_reports', 'ntp_tb_reports',
] as $table) {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $statement->execute([$table]);
    $check((int)$statement->fetchColumn() === 1, "table {$table} exists");
}

$check((int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE name='manage_system'")->fetchColumn() === 1, 'default admin permissions installed');
$check(function_exists('adminExtendedAction'), 'extended workflow handler loaded');
$check(function_exists('adminDownloadSqlBackup'), 'SQL backup generator loaded');
$check(function_exists('adminDownloadCertificatePdf'), 'certificate PDF generator loaded');
$check(function_exists('portalVerifyCsrf'), 'CSRF verification loaded');
$check(function_exists('portalRequireAdmin'), 'admin authorization guard loaded');

$pdo->beginTransaction();
try {
    $_POST = ['permission_name' => 'integration_test_permission', 'description' => 'Rolled back test'];
    $message = adminExtendedAction($pdo, 'save_permission', 1);
    $check($message === 'Permission saved.', 'extended action executes');
    $check((int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE name='integration_test_permission'")->fetchColumn() === 1, 'extended action writes database');

    $residentId = (int)$pdo->query('SELECT id FROM residents ORDER BY id LIMIT 1')->fetchColumn();
    $vaccineId = (int)$pdo->query('SELECT id FROM immunization_schedules ORDER BY id LIMIT 1')->fetchColumn();
    $diseaseId = (int)$pdo->query('SELECT id FROM disease_types ORDER BY id LIMIT 1')->fetchColumn();
    $staffId = (int)$pdo->query('SELECT id FROM staff ORDER BY id LIMIT 1')->fetchColumn();

    $_POST = ['resident_id'=>$residentId,'vaccine_id'=>$vaccineId,'vaccination_date'=>date('Y-m-d'),'provider_id'=>$staffId,'batch_number'=>'TEST'];
    $check(adminExtendedAction($pdo, 'save_vaccination', 1) === 'Vaccination record saved.', 'vaccination CRUD executes');

    $_POST = ['resident_id'=>$residentId,'lmp'=>date('Y-m-d'),'edc'=>date('Y-m-d', strtotime('+9 months')),'risk_factors'=>'Integration test'];
    $check(adminExtendedAction($pdo, 'create_pregnancy', 1) === 'Pregnancy case created.', 'maternal CRUD executes');

    $_POST = ['generic_name'=>'Integration Medicine','brand_name'=>'Test','quantity'=>1,'reorder_level'=>1,'unit_cost'=>1];
    $check(adminExtendedAction($pdo, 'save_medicine', 1) === 'Medicine item saved.', 'medicine CRUD executes');

    $_POST = ['resident_id'=>$residentId,'disease_id'=>$diseaseId,'case_date'=>date('Y-m-d'),'classification'=>'Suspected','outcome'=>'Active'];
    $check(adminExtendedAction($pdo, 'create_disease_case', 1) === 'Disease surveillance case created.', 'disease CRUD executes');

    $_POST = ['report_type'=>'fhsis','month'=>(int)date('n'),'year'=>(int)date('Y'),'notes'=>'Integration test'];
    $check(adminExtendedAction($pdo, 'generate_doh_report', 1) === 'FHSIS report generated.', 'DOH report generation executes');
} finally {
    $pdo->rollBack();
    $_POST = [];
}
$check((int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE name='integration_test_permission'")->fetchColumn() === 0, 'integration fixture rolled back');

$failed = array_filter($checks, static fn(array $result): bool => !$result[0]);
foreach ($checks as [$passed, $label]) {
    echo ($passed ? 'PASS' : 'FAIL'), ': ', $label, PHP_EOL;
}
echo count($checks) . ' checks, ' . count($failed) . " failures\n";
exit($failed ? 1 : 0);
