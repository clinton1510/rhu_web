<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/app/components/db.php';

$statement = $pdo->prepare(
    'SELECT u.id AS user_id, u.password_hash, s.id AS staff_id
     FROM users u
     INNER JOIN staff s ON s.user_id = u.id
     LEFT JOIN roles r ON r.id = u.role_id
     WHERE u.email = :email AND u.is_active = 1 AND s.is_active = 1
       AND (UPPER(s.staff_type) = UPPER(:staff_type_staff)
            OR UPPER(r.name) = UPPER(:staff_type_role))
     LIMIT 1'
);
$statement->execute([
    'email' => '23-75584@g.batstate-u.edu.ph',
    'staff_type_staff' => 'ADMIN_STAFF',
    'staff_type_role' => 'ADMIN_STAFF',
]);
$account = $statement->fetch(PDO::FETCH_ASSOC);

if (!$account || !password_verify('helloworld15', $account['password_hash'])) {
    fwrite(STDERR, "RHU Staff login verification failed.\n");
    exit(1);
}

echo "PASS RHU Staff credentials and role query\n";
