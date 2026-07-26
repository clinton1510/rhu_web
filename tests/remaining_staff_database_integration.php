<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/app/components/db.php';

$tables = ['diagnostics','laboratory_referrals','laboratory_supplies','sanitation_inspections','health_certificates','bhw_donor_referrals','blood_drives','blood_need_reports'];
foreach ($tables as $table) {
    $statement = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
    $statement->execute([$table]);
    if (!(int)$statement->fetchColumn()) exit("FAIL missing {$table}\n");
}

$residentId = (int)$pdo->query('SELECT id FROM residents ORDER BY id LIMIT 1')->fetchColumn();
$consultationId = (int)$pdo->query('SELECT id FROM consultations ORDER BY id LIMIT 1')->fetchColumn();
$pdo->beginTransaction();
try {
    if ($consultationId) $pdo->prepare("INSERT INTO diagnostics (consultation_id,test_name,test_date,test_status) VALUES (?,'Integration Test',CURDATE(),'Pending')")->execute([$consultationId]);
    $pdo->prepare("INSERT INTO laboratory_referrals (resident_id,test_requested,destination_facility,referral_date) VALUES (?,'Integration Test','Test Facility',CURDATE())")->execute([$residentId]);
    $pdo->exec("INSERT INTO laboratory_supplies (item_name,quantity,unit,reorder_level) VALUES ('Integration Test',1,'kit',1)");
    $pdo->exec("INSERT INTO sanitation_inspections (establishment,barangay,inspection_date,status) VALUES ('Integration Test','Test Barangay',CURDATE(),'Compliant')");
    $pdo->exec("INSERT INTO bhw_donor_referrals (full_name,age,contact_number) VALUES ('Integration Test',25,'09000000000')");
    $pdo->exec("INSERT INTO blood_need_reports (patient_name,blood_type,urgency,description) VALUES ('Integration Test','O+','urgent','Integration')");
    $pdo->rollBack();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
echo "PASS remaining staff database reads and writes\n";
