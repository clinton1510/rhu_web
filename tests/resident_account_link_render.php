<?php
declare(strict_types=1);

session_save_path(__DIR__);
session_id('residentaccountlinkrender');
session_start();
$_SESSION['user'] = [
    'id' => 18,
    'email' => '23-75584@g.batstate-u.edu.ph',
    'first_name' => 'Clinton John',
    'last_name' => 'Masongsong',
    'role_id' => 8,
];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['tab' => 'family'];

ob_start();
require dirname(__DIR__) . '/src/app/components/ResidentDashboard.php';
$html = ob_get_clean();

$linkedId = (int)($_SESSION['user']['resident_id'] ?? 0);
$passed = $linkedId === 7
    && str_contains($html, 'Clinton Masongsong')
    && str_contains($html, 'Add Dependent');

echo ($passed ? 'PASS' : 'FAIL'), ": account linked to resident #{$linkedId} and family UI rendered\n";

session_write_close();
$sessionFile = __DIR__ . DIRECTORY_SEPARATOR . 'sess_residentaccountlinkrender';
if (is_file($sessionFile)) unlink($sessionFile);
exit($passed ? 0 : 1);
