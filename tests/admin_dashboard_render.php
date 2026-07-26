<?php
declare(strict_types=1);

$tab = $argv[1] ?? 'overview';
$sessionId = 'adminrender' . preg_replace('/[^a-z0-9]/i', '', $tab);
session_save_path(__DIR__);
session_id($sessionId);
session_start();
$_SESSION['rhu_admin_authenticated'] = true;
$_SESSION['user'] = [
    'user_id' => 1,
    'first_name' => 'Integration',
    'last_name' => 'Admin',
    'email' => 'integration@example.test',
];
$_GET = ['tab' => $tab];
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
require dirname(__DIR__) . '/src/app/components/RHUAdminDashboard.php';
$html = ob_get_clean();

$passed = str_contains($html, '<!DOCTYPE html>') && str_contains($html, 'RHU Admin Dashboard');
echo ($passed ? 'PASS' : 'FAIL'), ": rendered {$tab} tab (", strlen($html), " bytes)\n";

session_write_close();
$sessionFile = __DIR__ . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;
if (is_file($sessionFile)) unlink($sessionFile);
exit($passed ? 0 : 1);
