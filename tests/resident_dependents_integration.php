<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/app/components/db.php';
if (empty($pdo)) {
    fwrite(STDERR, "FAIL: database connection unavailable\n");
    exit(1);
}

$residentId = (int)$pdo->query('SELECT id FROM residents ORDER BY id LIMIT 1')->fetchColumn();
if ($residentId < 1) {
    fwrite(STDERR, "FAIL: no resident fixture is available\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare(
        'INSERT INTO resident_dependents
         (primary_resident_id, first_name, last_name, relationship, date_of_birth, gender, blood_type)
         VALUES (:resident_id, :first_name, :last_name, :relationship, :date_of_birth, :gender, :blood_type)'
    );
    $insert->execute([
        'resident_id' => $residentId,
        'first_name' => 'Integration',
        'last_name' => 'Dependent',
        'relationship' => 'Child',
        'date_of_birth' => '2020-01-15',
        'gender' => 'Female',
        'blood_type' => 'O+',
    ]);
    $dependentId = (int)$pdo->lastInsertId();

    $read = $pdo->prepare(
        'SELECT first_name, relationship FROM resident_dependents
         WHERE id = :id AND primary_resident_id = :resident_id'
    );
    $read->execute(['id' => $dependentId, 'resident_id' => $residentId]);
    $dependent = $read->fetch(PDO::FETCH_ASSOC);

    $delete = $pdo->prepare(
        'DELETE FROM resident_dependents WHERE id = :id AND primary_resident_id = :resident_id'
    );
    $delete->execute(['id' => $dependentId, 'resident_id' => $residentId]);

    $passed = ($dependent['first_name'] ?? '') === 'Integration'
        && ($dependent['relationship'] ?? '') === 'Child'
        && $delete->rowCount() === 1;
    echo ($passed ? 'PASS' : 'FAIL'), ": dependent create, read, and owned removal\n";
    $pdo->rollBack();
    exit($passed ? 0 : 1);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
