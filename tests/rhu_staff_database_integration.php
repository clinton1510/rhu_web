<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/app/components/db.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}

$requiredTables = [
    'residents', 'consultations', 'vaccination_records', 'pregnancies',
    'family_planning_clients', 'tb_patients', 'resident_health_profiles', 'disease_cases',
    'vital_statistics_births', 'medicine_inventory', 'sanitation_inspections',
    'health_certificates', 'bhw', 'staff', 'fhsis_reports',
];

foreach ($requiredTables as $table) {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $statement->execute([$table]);
    if ((int)$statement->fetchColumn() !== 1) {
        fwrite(STDERR, "Missing RHU Staff data table: {$table}\n");
        exit(1);
    }
}

$residentCount = (int)$pdo->query('SELECT COUNT(*) FROM residents')->fetchColumn();
$linkedStaffCount = (int)$pdo->query('SELECT COUNT(*) FROM staff s JOIN users u ON u.id = s.user_id')->fetchColumn();

echo 'PASS RHU Staff database tables; residents=' . $residentCount . '; linked_staff=' . $linkedStaffCount . PHP_EOL;
