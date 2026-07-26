<?php
declare(strict_types=1);

session_save_path(__DIR__);
session_id('residentdashboardrender');
session_start();

require dirname(__DIR__) . '/src/app/components/db.php';
if (empty($pdo)) {
    fwrite(STDERR, "FAIL: database connection unavailable\n");
    exit(1);
}

$resident = $pdo->query(
    'SELECT id, email, first_name, last_name FROM residents ORDER BY id LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    fwrite(STDERR, "FAIL: no resident fixture is available\n");
    exit(1);
}

$_SESSION['user'] = [
    'id' => 1,
    'resident_id' => (int)$resident['id'],
    'email' => (string)($resident['email'] ?? ''),
    'first_name' => (string)$resident['first_name'],
    'last_name' => (string)$resident['last_name'],
];
$tab = $argv[1] ?? 'home';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['tab' => $tab];

ob_start();
require dirname(__DIR__) . '/src/app/components/ResidentDashboard.php';
$html = ob_get_clean();

$passed = str_contains($html, '<!doctype html>')
    && str_contains($html, 'Resident Dashboard')
    && str_contains($html, (string)$resident['first_name']);

echo ($passed ? 'PASS' : 'FAIL'), ": rendered resident {$tab} tab (", strlen($html), " bytes)\n";

session_write_close();
$sessionFile = __DIR__ . DIRECTORY_SEPARATOR . 'sess_residentdashboardrender';
if (is_file($sessionFile)) {
    unlink($sessionFile);
}

exit($passed ? 0 : 1);
