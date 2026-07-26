<?php
declare(strict_types=1);

ini_set('session.save_path', sys_get_temp_dir());
session_start();
$_SESSION['rhu_staff_login'] = ['id' => 18, 'staff_id' => 8, 'role' => 'Administrative Staff'];
$_SESSION['rhu_admin_authenticated'] = true;
$_SESSION['user'] = ['user_id' => 18];
$_GET['tab'] = 'audit';
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
require dirname(__DIR__) . '/src/app/components/RHUDashboard.php';
$html = (string)ob_get_clean();

if (!empty($_SESSION['rhu_admin_authenticated']) || isset($_SESSION['user'])) {
    fwrite(STDERR, "FAIL stale administrator authorization remained active\n");
    exit(1);
}
if (str_contains($html, 'title="Admin Panel"')) {
    fwrite(STDERR, "FAIL staff dashboard exposed the administrator panel\n");
    exit(1);
}
if (!str_contains($html, 'Staff and Administrator Audit Log')) {
    fwrite(STDERR, "FAIL staff audit page did not remain in the staff dashboard\n");
    exit(1);
}

echo "PASS staff/admin sessions are isolated\n";
