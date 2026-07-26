<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/portal.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/admin_extended.php';
portalRequireAdmin();

if (isset($_GET['logout'])) {
    portalAudit($pdo, (int)($_SESSION['user']['user_id'] ?? 0), 'RHU Admin Logout', 'users', (int)($_SESSION['user']['user_id'] ?? 0));
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: RHUAdminLogin.php');
    exit;
}

if (($_GET['export'] ?? '') === 'summary' && $pdo) {
    portalAudit($pdo, (int)$_SESSION['user']['user_id'], 'Exported dashboard summary report', 'reports', null);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rhu-summary-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['Metric', 'Count']);
    foreach ([
        'Users' => 'users',
        'Active Staff' => 'staff WHERE is_active = 1',
        'Residents' => 'residents',
        'Consultations' => 'consultations',
        'Pregnancies' => 'pregnancies',
        'Vaccinations' => 'vaccination_records',
        'Disease Cases' => 'disease_cases',
        'Medicine Items' => 'medicine_inventory',
    ] as $label => $source) {
        fputcsv($output, [$label, (int)$pdo->query("SELECT COUNT(*) FROM {$source}")->fetchColumn()]);
    }
    fclose($output);
    exit;
}

if (($_GET['backup'] ?? '') === 'sql' && $pdo) {
    portalAudit($pdo, (int)$_SESSION['user']['user_id'], 'Downloaded SQL database backup', 'system', null);
    adminDownloadSqlBackup($pdo);
}

if (isset($_GET['certificate_pdf']) && ctype_digit((string)$_GET['certificate_pdf']) && $pdo) {
    portalAudit($pdo, (int)$_SESSION['user']['user_id'], 'Downloaded certificate PDF', 'health_certificates', (int)$_GET['certificate_pdf']);
    adminDownloadCertificatePdf($pdo, (int)$_GET['certificate_pdf']);
}

/**
 * Escape output for HTML.
 * @param mixed $value
 */
function esc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function iconSvg(string $name, string $class = 'w-5 h-5'): string
{
    $icons = [
        'shield' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'users' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'settings' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6V20a2 2 0 0 1-4 0v-.1a1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1H4a2 2 0 0 1 0-4h.1a1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6V4a2 2 0 0 1 4 0v.1a1.65 1.65 0 0 0 1 .6 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.24.31.4.68.6 1H20a2 2 0 0 1 0 4h-.1a1.65 1.65 0 0 0-.5 1z"/></svg>',
        'bar' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>',
        'logout' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/><path d="M13 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7"/></svg>',
        'bell' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"/><path d="M9 17a3 3 0 0 0 6 0"/></svg>',
        'home' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>',
        'activity' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
        'file' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'database' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>',
        'lock' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'check' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
        'clock' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
        'trend' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
        'right' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 5l7 7-7 7"/></svg>',
        'eye' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>',
        'edit' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>',
        'trash' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
        'plus' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>',
        'download' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        'refresh' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>',
        'server' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="8" rx="2"/><rect x="2" y="13" width="20" height="8" rx="2"/><line x1="6" y1="7" x2="6.01" y2="7"/><line x1="6" y1="17" x2="6.01" y2="17"/></svg>',
        'stethoscope' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v7a6 6 0 0 0 12 0V3"/><path d="M6 10a3 3 0 0 0 6 0"/><path d="M12 20a4 4 0 0 0 8 0v-2"/></svg>',
        'building' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M9 22V12h6v10"/><path d="M9 8h.01"/><path d="M13 8h.01"/><path d="M9 12h.01"/><path d="M13 12h.01"/></svg>',
        'mail' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
        'phone' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.18 2 2 0 0 1 4.09 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.77.6 2.6a2 2 0 0 1-.45 2.11L8 9a16 16 0 0 0 7 7l.57-1.24a2 2 0 0 1 2.11-.45c.83.27 1.7.48 2.6.6A2 2 0 0 1 22 16.92z"/></svg>',
        'key' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.5 12.5L21 2"/><path d="M17 5l2 2"/></svg>',
        'menu' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
        'close' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
        'baby' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h.01M15 12h.01"/><path d="M10 16c.5.35 1.17.5 2 .5s1.5-.15 2-.5"/><path d="M12 3a9 9 0 1 0 9 9c0-2.5-1-4.7-2.7-6.3"/><path d="M12 3c2 0 3 1 3 2.5S14 8 12 8"/></svg>',
        'alert' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 2.9 1.8 17a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>',
        'pill' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m10.5 20.5 10-10a5 5 0 0 0-7-7l-10 10a5 5 0 0 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>',
    ];

    return $icons[$name] ?? '';
}

$tab = $_GET['tab'] ?? 'overview';
$showNotifs = isset($_GET['notifs']) && $_GET['notifs'] === '1';

// ----------------------------------------------------
// 1. DATABASE POST HANDLERS FOR ACTIONS
// ----------------------------------------------------
$flashSuccess = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($pdo)) {
    if (!portalVerifyCsrf()) {
        http_response_code(419);
        exit('Invalid or expired form token. Refresh the page and try again.');
    }
    $action = $_POST['action'] ?? '';

    // Action: Update Facility Profile Info (System Tab)
    if ($action === 'update_rhu_info') {
        portalSaveSettings($pdo, [
            'rhu_name' => $_POST['rhu_name'] ?? '',
            'rhu_mho_name' => $_POST['mho_name'] ?? '',
            'rhu_municipality' => $_POST['municipality'] ?? '',
            'rhu_province' => $_POST['province'] ?? '',
            'rhu_contact_number' => $_POST['contact'] ?? '',
            'rhu_email' => $_POST['email'] ?? '',
        ]);
        portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Updated RHU Facility Settings", 'system', 1);
        $flashSuccess = "RHU Facility information updated successfully!";
    }

    // Action: Change Admin Password (Security Tab)
    if ($action === 'change_admin_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        $adminUserId = (int)($_SESSION['user']['user_id'] ?? 0);

        if (!$currentPass || !$newPass || !$confirmPass) {
            $flashError = "Please complete all password fields.";
        } elseif ($newPass !== $confirmPass) {
            $flashError = "New password and confirmation do not match.";
        } elseif (strlen($newPass) < 8) {
            $flashError = "New password must be at least 8 characters long.";
        } else {
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $adminUserId]);
            $hash = $stmt->fetchColumn();

            if ($hash && password_verify($currentPass, $hash)) {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $upd = $pdo->prepare("UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id");
                $upd->execute(['hash' => $newHash, 'id' => $adminUserId]);
                portalAudit($pdo, $adminUserId, "Admin Password Changed Successfully", 'users', $adminUserId);
                $flashSuccess = "Administrator password updated successfully in database!";
            } else {
                $flashError = "Incorrect current password. Please try again.";
            }
        }
    }

    // Action: Send Test Email (System Tab)
    if ($action === 'send_test_email') {
        $targetEmail = trim($_POST['test_email'] ?? 'chedricbascoguin27@gmail.com');
        $testCode = sprintf('%06d', mt_rand(100000, 999999));
        $subject = 'ResiHUnity RHU - System SMTP Integration Test';
        $html = "<h3>ResiHUnity RHU System Test</h3><p>This is a test 2FA email sent from RHU System Settings.</p><div class='code-box'>{$testCode}</div>";
        $res = sendRHUEmail($targetEmail, $subject, $html);
        if ($res['success']) {
            $flashSuccess = "Test email dispatched successfully to {$targetEmail}! (Method: {$res['method']})";
        } else {
            $flashError = "Failed to dispatch test email to {$targetEmail}. Check SMTP settings.";
        }
    }

    // Action: Approve Certificate Request
    if ($action === 'approve_certificate') {
        $certId = (int)($_POST['request_id'] ?? 0);
        if ($certId > 0) {
            $residentId = (int)$pdo->query("SELECT resident_id FROM health_certificates WHERE id = {$certId}")->fetchColumn();
            $certNo = 'CERT-' . date('Y') . '-' . str_pad((string)$certId, 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("UPDATE health_certificates SET validity_status = 'Approved & Issued', certificate_number = :cert_no, issue_date = CURDATE(), rejection_reason = NULL WHERE id = :id");
            $stmt->execute(['cert_no' => $certNo, 'id' => $certId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Approved Certificate Request #{$certId} (Cert No: {$certNo})", 'health_certificates', $certId);
            portalNotifyResident($pdo, $residentId, "Your certificate {$certNo} is approved and ready.", 'ResidentDashboard.php?tab=certificates');
            $flashSuccess = "Certificate request #{$certId} approved and issued successfully!";
        }
    }

    // Action: Reject Certificate Request
    if ($action === 'reject_certificate') {
        $certId = (int)($_POST['request_id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? 'Requirements incomplete');
        if ($certId > 0) {
            $residentId = (int)$pdo->query("SELECT resident_id FROM health_certificates WHERE id = {$certId}")->fetchColumn();
            $stmt = $pdo->prepare("UPDATE health_certificates SET validity_status = 'Rejected', rejection_reason = :reason WHERE id = :id");
            $stmt->execute(['reason' => $reason, 'id' => $certId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Rejected Certificate Request #{$certId}", 'health_certificates', $certId);
            portalNotifyResident($pdo, $residentId, 'Your certificate request was rejected: ' . $reason, 'ResidentDashboard.php?tab=certificates');
            $flashSuccess = "Certificate request #{$certId} rejected.";
        }
    }

    // Action: Reply to Resident Message
    if ($action === 'reply_message') {
        $msgId = (int)($_POST['message_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        if ($msgId > 0 && $reply !== '') {
            $residentId = (int)$pdo->query("SELECT resident_id FROM messages WHERE id = {$msgId}")->fetchColumn();
            $stmt = $pdo->prepare("UPDATE messages SET admin_reply = :reply, status = 'Replied', replied_at = NOW() WHERE id = :id");
            $stmt->execute(['reply' => $reply, 'id' => $msgId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Replied to Resident Message #{$msgId}", 'messages', $msgId);
            portalNotifyResident($pdo, $residentId, 'RHU staff replied to your message.', 'ResidentDashboard.php?tab=contact');
            $flashSuccess = "Reply sent to resident message #{$msgId}.";
        }
    }

    // Action: Confirm Event Registration
    if ($action === 'confirm_event') {
        $eventId = (int)($_POST['event_id'] ?? 0);
        if ($eventId > 0) {
            $stmt = $pdo->prepare("UPDATE event_registrations SET status = 'Confirmed', confirmed_at = NOW(), confirmed_by = :admin_id WHERE id = :id");
            $stmt->execute(['admin_id' => (int)$_SESSION['user']['user_id'], 'id' => $eventId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Confirmed Event Registration #{$eventId}", 'event_registrations', $eventId);
            $flashSuccess = "Event registration #{$eventId} confirmed.";
        }
    }

    if ($action === 'mark_notifications_read') {
        $stmt = $pdo->prepare("UPDATE portal_notifications SET is_read = 1 WHERE user_id = :user_id OR audience_role IN ('RHU_ADMIN', 'SUPER_ADMIN', 'ADMIN_STAFF')");
        $stmt->execute(['user_id' => (int)$_SESSION['user']['user_id']]);
        $flashSuccess = 'Notifications marked as read.';
    }

    if ($action === 'update_resident_status') {
        $id = (int)($_POST['resident_id'] ?? 0);
        $status = (int)($_POST['new_status'] ?? 0);
        $stmt = $pdo->prepare('UPDATE residents SET is_active = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
        portalAudit($pdo, (int)$_SESSION['user']['user_id'], 'Updated resident status', 'residents', $id);
        $flashSuccess = 'Resident status updated.';
    }

    if ($action === 'update_consultation') {
        $id = (int)($_POST['consultation_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE consultations SET diagnosis = :diagnosis, treatment_plan = :treatment_plan, follow_up_date = :follow_up_date, physician_id = COALESCE(:physician_id, physician_id), consultation_status = :status WHERE id = :id');
        $stmt->execute([
            'diagnosis' => trim($_POST['diagnosis'] ?? ''),
            'treatment_plan' => trim($_POST['treatment_plan'] ?? ''),
            'follow_up_date' => ($_POST['follow_up_date'] ?? '') ?: null,
            'physician_id' => ($_POST['physician_id'] ?? '') ?: null,
            'status' => trim($_POST['consultation_status'] ?? 'Scheduled'),
            'id' => $id,
        ]);
        portalAudit($pdo, (int)$_SESSION['user']['user_id'], 'Updated consultation', 'consultations', $id);
        $flashSuccess = 'Consultation updated.';
    }

    if ($action === 'update_pregnancy') {
        $id = (int)($_POST['pregnancy_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE pregnancies SET pregnancy_status = :status, high_risk = :high_risk WHERE id = :id');
        $stmt->execute(['status' => trim($_POST['status'] ?? 'Active'), 'high_risk' => isset($_POST['high_risk']) ? 1 : 0, 'id' => $id]);
        portalAudit($pdo, (int)$_SESSION['user']['user_id'], 'Updated maternal case', 'pregnancies', $id);
        $flashSuccess = 'Maternal case updated.';
    }

    if ($action === 'update_disease_case') {
        $id = (int)($_POST['case_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE disease_cases SET case_classification = :classification, outcome = :outcome, reported_to_doh = :reported WHERE id = :id');
        $stmt->execute([
            'classification' => trim($_POST['classification'] ?? 'Suspected'),
            'outcome' => trim($_POST['outcome'] ?? ''),
            'reported' => isset($_POST['reported_to_doh']) ? 1 : 0,
            'id' => $id,
        ]);
        portalAudit($pdo, (int)$_SESSION['user']['user_id'], 'Updated disease surveillance case', 'disease_cases', $id);
        $flashSuccess = 'Disease case updated.';
    }

    if ($action === 'adjust_medicine_stock') {
        $id = (int)($_POST['medicine_id'] ?? 0);
        $quantity = (int)($_POST['quantity_change'] ?? 0);
        if ($id > 0 && $quantity !== 0) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('UPDATE medicine_inventory SET quantity_in_stock = GREATEST(0, quantity_in_stock + :quantity), last_updated = NOW() WHERE id = :id');
                $stmt->execute(['quantity' => $quantity, 'id' => $id]);
                $stmt = $pdo->prepare("INSERT INTO stock_transactions (medicine_id, transaction_type, quantity, transaction_date, reason) VALUES (:id, :type, :quantity, CURDATE(), :reason)");
                $stmt->execute(['id' => $id, 'type' => $quantity > 0 ? 'In' : 'Out', 'quantity' => abs($quantity), 'reason' => trim($_POST['reason'] ?? 'Admin adjustment')]);
                $pdo->commit();
                portalAudit($pdo, (int)$_SESSION['user']['user_id'], 'Adjusted medicine stock', 'medicine_inventory', $id);
                $flashSuccess = 'Medicine stock adjusted.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }
    }

    if ($action === 'update_smtp_settings') {
        portalSaveSettings($pdo, [
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '587',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'smtp_user' => $_POST['smtp_user'] ?? '',
            'two_factor_enabled' => isset($_POST['two_factor_enabled']) ? '1' : '0',
        ]);
        portalAudit($pdo, (int)$_SESSION['user']['user_id'], 'Updated mail and 2FA settings', 'portal_settings', null);
        $flashSuccess = 'Mail and 2FA settings saved.';
    }

    if ($action === 'create_scheduled_event') {
        $title = trim($_POST['title'] ?? '');
        $scheduledDate = trim($_POST['scheduled_date'] ?? '');
        if ($title !== '' && $scheduledDate !== '') {
            $stmt = $pdo->prepare(
                "INSERT INTO portal_events (event_date, scheduled_date, start_time, title, venue, description, capacity, status)
                 VALUES (:display_date, :scheduled_date, :start_time, :title, :venue, :description, :capacity, 'Scheduled')"
            );
            $stmt->execute([
                'display_date' => date('F j, Y', strtotime($scheduledDate)),
                'scheduled_date' => $scheduledDate,
                'start_time' => ($_POST['start_time'] ?? '') ?: null,
                'title' => $title,
                'venue' => trim($_POST['venue'] ?? 'Nasugbu RHU'),
                'description' => trim($_POST['description'] ?? ''),
                'capacity' => ($_POST['capacity'] ?? '') ?: null,
            ]);
            portalAudit($pdo, (int)$_SESSION['user']['user_id'], 'Created RHU event', 'portal_events', (int)$pdo->lastInsertId());
            portalNotify($pdo, "New RHU event scheduled: {$title}", null, 'RESIDENT', 'ResidentDashboard.php?tab=events');
            $flashSuccess = 'Event created and published to the Resident Portal.';
        }
    }

    if ($action === 'update_user_role') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $roleId = (int)($_POST['role_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE users SET role_id = :role_id WHERE id = :id');
        $stmt->execute(['role_id' => $roleId, 'id' => $userId]);
        portalAudit($pdo, (int)$_SESSION['user']['user_id'], 'Changed user role', 'users', $userId);
        $flashSuccess = 'User role updated.';
    }

    // Action: Toggle User Status
    if ($action === 'toggle_user_status') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newStatus = (int)($_POST['new_status'] ?? 0);
        if ($targetUserId > 0) {
            $stmt = $pdo->prepare("UPDATE users SET is_active = :status WHERE id = :id");
            $stmt->execute(['status' => $newStatus, 'id' => $targetUserId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Toggled User #{$targetUserId} status to " . ($newStatus ? 'Active' : 'Inactive'), 'users', $targetUserId);
            $flashSuccess = "User status updated successfully.";
        }
    }

    // Action: Delete User
    if ($action === 'delete_user') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId > 0 && $targetUserId !== (int)($_SESSION['user']['user_id'] ?? 0)) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $targetUserId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Deleted User #{$targetUserId}", 'users', $targetUserId);
            $flashSuccess = "User account removed from database.";
        }
    }

    // Action: Toggle Staff Status
    if ($action === 'toggle_staff_status') {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $newStatus = (int)($_POST['new_status'] ?? 0);
        if ($staffId > 0) {
            $stmt = $pdo->prepare("UPDATE staff SET is_active = :status WHERE id = :id");
            $stmt->execute(['status' => $newStatus, 'id' => $staffId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Toggled Staff #{$staffId} status to " . ($newStatus ? 'Active' : 'Inactive'), 'staff', $staffId);
            $flashSuccess = "Staff account status updated.";
        }
    }

    // Action: Create New Healthcare Staff
    if ($action === 'create_staff') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $staffType = trim($_POST['staff_type'] ?? 'ADMIN_STAFF');
        $specialization = trim($_POST['specialization'] ?? '');
        $licenseNumber = trim($_POST['license_number'] ?? '');
        $phone = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
            $flashError = "Please fill in all required fields.";
        } else {
            try {
                $pdo->beginTransaction();
                $check = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $check->execute(['email' => $email]);
                if ($check->fetch()) {
                    throw new Exception("An account with email {$email} already exists.");
                }

                $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE UPPER(name) = UPPER(:name) LIMIT 1");
                $roleStmt->execute(['name' => $staffType]);
                $roleId = $roleStmt->fetchColumn() ?: 2;

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $username = strtolower(preg_replace('/[^a-z0-9]/i', '', explode('@', $email)[0])) . rand(100, 999);

                $insUser = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active, created_at) VALUES (:u, :e, :h, :f, :l, :r, 1, NOW())");
                $insUser->execute(['u' => $username, 'e' => $email, 'h' => $hash, 'f' => $firstName, 'l' => $lastName, 'r' => $roleId]);
                $newUserId = (int)$pdo->lastInsertId();

                $insStaff = $pdo->prepare("INSERT INTO staff (user_id, staff_type, license_number, specialization, phone_number, address, date_hired, is_active) VALUES (:uid, :st, :lic, :spec, :phone, :addr, CURDATE(), 1)");
                $insStaff->execute(['uid' => $newUserId, 'st' => $staffType, 'lic' => $licenseNumber, 'spec' => $specialization, 'phone' => $phone, 'addr' => $address]);

                $pdo->commit();
                portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Created new Healthcare Staff Account for {$email} ({$staffType})", 'staff', (int)$pdo->lastInsertId());
                $flashSuccess = "New staff account created successfully for {$firstName} {$lastName}!";
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $flashError = $ex->getMessage();
            }
        }
    }

    // Action: Publish Announcement for Landing Page
    if ($action === 'create_announcement') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'Health Notice');
        $content = trim($_POST['content'] ?? '');
        $badgeText = trim($_POST['badge_text'] ?? 'Notice');
        $postedBy = trim($_SESSION['user']['username'] ?? 'MHO Admin');

        if ($title !== '' && $content !== '') {
            ensurePortalTables($pdo);
            $stmt = $pdo->prepare("INSERT INTO portal_announcements (title, category, content, badge_text, is_active, posted_by) VALUES (:t, :c, :cnt, :b, 1, :p)");
            $stmt->execute(['t' => $title, 'c' => $category, 'cnt' => $content, 'b' => $badgeText, 'p' => $postedBy]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Published Landing Page Announcement: {$title}", 'portal_announcements', (int)$pdo->lastInsertId());
            $flashSuccess = "Announcement published live to Landing Page!";
        } else {
            $flashError = "Please provide both title and content for the announcement.";
        }
    }

    // Action: Delete Announcement
    if ($action === 'delete_announcement') {
        $annId = (int)($_POST['announcement_id'] ?? 0);
        if ($annId > 0) {
            $stmt = $pdo->prepare("DELETE FROM portal_announcements WHERE id = :id");
            $stmt->execute(['id' => $annId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Deleted Announcement #{$annId}", 'portal_announcements', $annId);
            $flashSuccess = "Announcement removed from Landing Page.";
        }
    }

    // Action: Create Health Event
    if ($action === 'create_event') {
        $eventDate = trim($_POST['event_date'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $badgeColor = trim($_POST['badge_color'] ?? 'bg-emerald-500');
        $imageUrl = trim($_POST['image_url'] ?? '');

        if (!empty($_FILES['event_image']['tmp_name'])) {
            $uploadDir = dirname(__DIR__, 3) . '/uploads/events/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
            $filename = 'event_' . time() . '_' . mt_rand(100, 999) . '.' . strtolower($ext ?: 'jpg');
            $target = $uploadDir . $filename;
            if (@move_uploaded_file($_FILES['event_image']['tmp_name'], $target)) {
                $imageUrl = 'uploads/events/' . $filename;
            }
        }

        if ($title !== '' && $eventDate !== '' && $venue !== '') {
            ensurePortalTables($pdo);
            $stmt = $pdo->prepare("INSERT INTO portal_events (event_date, title, venue, description, image_url, badge_color, is_active) VALUES (:ed, :t, :v, :d, :img, :bc, 1)");
            $stmt->execute(['ed' => $eventDate, 't' => $title, 'v' => $venue, 'd' => $description, 'img' => $imageUrl, 'bc' => $badgeColor]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Created Health Event: {$title}", 'portal_events', (int)$pdo->lastInsertId());
            $flashSuccess = "Health Event with picture published live to Landing Page!";
        } else {
            $flashError = "Please complete event date, title, and venue.";
        }
    }

    // Action: Delete Health Event (and attached uploaded picture)
    if ($action === 'delete_event') {
        $evtId = (int)($_POST['event_id'] ?? 0);
        if ($evtId > 0) {
            $stmtImg = $pdo->prepare("SELECT image_url FROM portal_events WHERE id = :id");
            $stmtImg->execute(['id' => $evtId]);
            $imgFile = $stmtImg->fetchColumn();
            if (!empty($imgFile) && str_starts_with($imgFile, 'uploads/events/')) {
                $localPath = dirname(__DIR__, 3) . '/' . ltrim($imgFile, '/');
                if (file_exists($localPath)) {
                    @unlink($localPath);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM portal_events WHERE id = :id");
            $stmt->execute(['id' => $evtId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Deleted Health Event #{$evtId}", 'portal_events', $evtId);
            $flashSuccess = "Health event and attached picture deleted successfully.";
        }
    }

    // Action: Delete Event Cover Picture Only
    if ($action === 'delete_event_picture') {
        $evtId = (int)($_POST['event_id'] ?? 0);
        if ($evtId > 0) {
            $stmtImg = $pdo->prepare("SELECT image_url FROM portal_events WHERE id = :id");
            $stmtImg->execute(['id' => $evtId]);
            $imgFile = $stmtImg->fetchColumn();
            if (!empty($imgFile) && str_starts_with($imgFile, 'uploads/events/')) {
                $localPath = dirname(__DIR__, 3) . '/' . ltrim($imgFile, '/');
                if (file_exists($localPath)) {
                    @unlink($localPath);
                }
            }

            $stmt = $pdo->prepare("UPDATE portal_events SET image_url = NULL WHERE id = :id");
            $stmt->execute(['id' => $evtId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Removed Picture from Health Event #{$evtId}", 'portal_events', $evtId);
            $flashSuccess = "Event picture removed successfully.";
        }
    }

    // Action: Add Municipal Official to portal_settings
    if ($action === 'add_official') {
        $name = trim($_POST['official_name'] ?? '');
        $position = trim($_POST['official_position'] ?? '');
        $office = trim($_POST['official_office'] ?? '');

        if ($name !== '' && $position !== '') {
            $settings = portalSettings($pdo);
            $currentOfficials = [];
            if (!empty($settings['rhu_municipal_officials'])) {
                $currentOfficials = json_decode($settings['rhu_municipal_officials'], true) ?: [];
            }
            $currentOfficials[] = [
                'name' => $name,
                'position' => $position,
                'office' => $office ?: 'LGU Nasugbu'
            ];
            $jsonStr = json_encode($currentOfficials);
            $stmt = $pdo->prepare("INSERT INTO portal_settings (setting_key, setting_value) VALUES ('rhu_municipal_officials', :val) ON DUPLICATE KEY UPDATE setting_value = :val");
            $stmt->execute(['val' => $jsonStr]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Added Municipal Official: {$name}", 'portal_settings', 1);
            $flashSuccess = "Municipal Official '{$name}' added and updated live on Landing Page!";
        } else {
            $flashError = "Please enter official name and position.";
        }
    }

    // Action: Delete Municipal Official
    if ($action === 'delete_official') {
        $index = (int)($_POST['official_index'] ?? -1);
        $settings = portalSettings($pdo);
        if (!empty($settings['rhu_municipal_officials'])) {
            $currentOfficials = json_decode($settings['rhu_municipal_officials'], true) ?: [];
            if (isset($currentOfficials[$index])) {
                $removed = $currentOfficials[$index]['name'] ?? 'Official';
                array_splice($currentOfficials, $index, 1);
                $jsonStr = json_encode($currentOfficials);
                $stmt = $pdo->prepare("INSERT INTO portal_settings (setting_key, setting_value) VALUES ('rhu_municipal_officials', :val) ON DUPLICATE KEY UPDATE setting_value = :val");
                $stmt->execute(['val' => $jsonStr]);
                portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Removed Municipal Official #{$index}", 'portal_settings', 1);
                $flashSuccess = "Municipal Official '{$removed}' removed from Landing Page.";
            }
        }
    }

    // Action: Add Photo to Landing Page Event Gallery (stored in portal_settings)
    if ($action === 'add_gallery_photo') {
        $photoTitle = trim($_POST['photo_title'] ?? '');
        $photoCategory = trim($_POST['photo_category'] ?? 'Event Photo');
        $photoDate = trim($_POST['photo_date'] ?? date('M Y'));
        $imageUrl = trim($_POST['image_url'] ?? '');

        if (!empty($_FILES['gallery_file']['tmp_name'])) {
            $uploadDir = dirname(__DIR__, 3) . '/uploads/events/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['gallery_file']['name'], PATHINFO_EXTENSION);
            $filename = 'gallery_' . time() . '_' . mt_rand(100, 999) . '.' . strtolower($ext ?: 'jpg');
            $target = $uploadDir . $filename;
            if (@move_uploaded_file($_FILES['gallery_file']['tmp_name'], $target)) {
                $imageUrl = 'uploads/events/' . $filename;
            }
        }

        if ($photoTitle !== '' && $imageUrl !== '') {
            $currentGallery = getPortalEventGallery($pdo);
            array_unshift($currentGallery, [
                'title' => $photoTitle,
                'category' => $photoCategory,
                'image_url' => $imageUrl,
                'date' => $photoDate
            ]);
            $jsonStr = json_encode($currentGallery);
            $stmt = $pdo->prepare("INSERT INTO portal_settings (setting_key, setting_value) VALUES ('rhu_event_gallery', :val) ON DUPLICATE KEY UPDATE setting_value = :val");
            $stmt->execute(['val' => $jsonStr]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Added Event Gallery Photo: {$photoTitle}", 'portal_settings', 1);
            $flashSuccess = "Event photo '{$photoTitle}' published to Landing Page gallery!";
        } else {
            $flashError = "Please provide photo title and image file/URL.";
        }
    }

    // Action: Delete Photo from Landing Page Event Gallery
    if ($action === 'delete_gallery_photo') {
        $index = (int)($_POST['photo_index'] ?? -1);
        $currentGallery = getPortalEventGallery($pdo);
        if (isset($currentGallery[$index])) {
            $removed = $currentGallery[$index]['title'] ?? 'Photo';
            $imgFile = $currentGallery[$index]['image_url'] ?? '';

            if (!empty($imgFile) && str_starts_with($imgFile, 'uploads/events/')) {
                $localPath = dirname(__DIR__, 3) . '/' . ltrim($imgFile, '/');
                if (file_exists($localPath)) {
                    @unlink($localPath);
                }
            }

            array_splice($currentGallery, $index, 1);
            $jsonStr = json_encode($currentGallery);
            $stmt = $pdo->prepare("INSERT INTO portal_settings (setting_key, setting_value) VALUES ('rhu_event_gallery', :val) ON DUPLICATE KEY UPDATE setting_value = :val");
            $stmt->execute(['val' => $jsonStr]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Deleted Gallery Photo #{$index}", 'portal_settings', 1);
            $flashSuccess = "Picture '{$removed}' deleted successfully from gallery and server.";
        }
    }
}


// RHU Profile Settings
$savedPortalSettings = portalSettings($pdo);
$rhuProfile = [
    'name' => portalSetting($savedPortalSettings, 'rhu_name', 'Nasugbu Rural Health Unit I'),
    'mho_name' => portalSetting($savedPortalSettings, 'rhu_mho_name', 'Municipal Health Officer'),
    'municipality' => portalSetting($savedPortalSettings, 'rhu_municipality', 'Nasugbu'),
    'province' => portalSetting($savedPortalSettings, 'rhu_province', 'Batangas'),
    'contact' => portalSetting($savedPortalSettings, 'rhu_contact_number', '(043) 416-1234'),
    'email' => portalSetting($savedPortalSettings, 'rhu_email', ''),
];

// ----------------------------------------------------
// 2. FETCH REAL DATABASE RECORDS
// ----------------------------------------------------
$totalUsersCount = 0;
$totalStaffCount = 0;
$totalBhwCount = 0;
$totalResidentsCount = 0;

$dbUsersList = [];
$dbStaffList = [];
$dbResidentList = [];
$dbAuditLogsList = [];
$dbCertificatesList = [];
$dbMessagesList = [];
$dbEventsList = [];
$dbAnnouncementsList = [];
$dbPortalEventsList = [];
$adminNotifications = [];

// Clinical data
$dbConsultationsList = [];
$dbMaternalCases = [];
$dbVaccinationRecords = [];
$dbDiseaseCases = [];
$dbMedicineInventory = [];
$dbVitalStatistics = [];

$barangayStats = [];
$staffTypeStats = [];
$roleStats = [];
$dbRoles = [];
$consultationStats = [];
$maternalStats = [];
$vaccinationStats = [];
$diseaseStats = [];
$medicineStats = [];
$databaseHealth = ['name' => rhuEnv('DB_NAME', 'rhu'), 'version' => 'Unavailable', 'tables' => 0];

if (!empty($pdo)) {
    try {
        $databaseHealth['version'] = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
        $databaseHealth['tables'] = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        $totalUsersCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalStaffCount = (int)$pdo->query("SELECT COUNT(*) FROM staff WHERE is_active = 1")->fetchColumn();
        $totalBhwCount = (int)$pdo->query("SELECT COUNT(*) FROM staff WHERE staff_type = 'BHW' AND is_active = 1")->fetchColumn();
        $totalResidentsCount = (int)$pdo->query("SELECT COUNT(*) FROM residents")->fetchColumn();

        $uStmt = $pdo->query("
            SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.is_active, u.created_at, u.last_login, r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY u.id DESC LIMIT 100
        ");
        $dbUsersList = $uStmt->fetchAll(PDO::FETCH_ASSOC);

        $sStmt = $pdo->query("
            SELECT s.id, s.user_id, u.first_name, u.last_name, u.email, s.staff_type, s.specialization, s.license_number, s.license_expiry, s.phone_number, s.address, s.is_active, s.date_hired
            FROM staff s
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY s.id DESC LIMIT 100
        ");
        $dbStaffList = $sStmt->fetchAll(PDO::FETCH_ASSOC);

        $rStmt = $pdo->query("
            SELECT r.id, r.first_name, r.last_name, r.middle_name, r.date_of_birth, r.gender, r.contact_number, r.email, r.barangay, r.address, r.philhealth_id, r.blood_type, r.created_at, r.is_active, TIMESTAMPDIFF(YEAR, r.date_of_birth, CURDATE()) AS age
            FROM residents r
            ORDER BY r.id DESC LIMIT 200
        ");
        $dbResidentList = $rStmt->fetchAll(PDO::FETCH_ASSOC);

        $aStmt = $pdo->query("
            SELECT a.id, a.user_id, u.first_name, u.last_name, u.email, r.name AS role_name, a.action, a.entity_type AS table_name, a.entity_id AS record_id, a.timestamp, a.ip_address
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY a.timestamp DESC LIMIT 50
        ");
        $dbAuditLogsList = $aStmt->fetchAll(PDO::FETCH_ASSOC);

        try {
            $cStmt = $pdo->query("
                SELECT c.id, c.resident_id, r.first_name, r.last_name, ct.certificate_type_name AS certificate_type, c.purpose, c.validity_status AS status, c.issue_date, c.certificate_number, c.created_at
                FROM health_certificates c
                JOIN residents r ON c.resident_id = r.id
                JOIN certificate_types ct ON c.certificate_type_id = ct.id
                ORDER BY c.created_at DESC LIMIT 50
            ");
            $dbCertificatesList = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        try {
            $mStmt = $pdo->query("
                SELECT m.id, m.resident_id, r.first_name, r.last_name, r.email, m.subject, m.message, m.admin_reply, m.status, m.created_at
                FROM messages m
                LEFT JOIN residents r ON m.resident_id = r.id
                ORDER BY m.created_at DESC LIMIT 50
            ");
            $dbMessagesList = $mStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        try {
            $eStmt = $pdo->query("
                SELECT er.id, er.resident_id, r.first_name, r.last_name, pe.title AS event_title,
                       pe.event_date, er.status, er.registered_at AS created_at
                FROM event_registrations er
                JOIN portal_events pe ON pe.id = er.event_id
                LEFT JOIN residents r ON er.resident_id = r.id
                ORDER BY er.registered_at DESC LIMIT 50
            ");
            $dbEventsList = $eStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { error_log('Admin event registrations: ' . $e->getMessage()); }

        $barangayStats = $pdo->query("SELECT barangay, COUNT(*) AS count FROM residents GROUP BY barangay ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);
        $staffTypeStats = $pdo->query("SELECT staff_type, COUNT(*) AS count FROM staff GROUP BY staff_type ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);
        $dbRoles = $pdo->query("SELECT id, name FROM roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        // CLINICAL DATA QUERIES
        // Consultations
        try {
            $cslStmt = $pdo->query("
                SELECT c.id, c.resident_id, r.first_name, r.last_name, r.barangay, c.chief_complaint, c.diagnosis, c.physician_id, c.consultation_date, c.consultation_status
                FROM consultations c
                LEFT JOIN residents r ON c.resident_id = r.id
                ORDER BY c.consultation_date DESC LIMIT 100
            ");
            $dbConsultationsList = $cslStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // Maternal Health Cases
        try {
            $mtlStmt = $pdo->query("
                SELECT p.id, p.resident_id, r.first_name, r.last_name, r.barangay, p.last_menstrual_period as lmp, p.expected_delivery_date as edc, p.pregnancy_status as status, p.high_risk, r.blood_type
                FROM pregnancies p
                LEFT JOIN residents r ON p.resident_id = r.id
                ORDER BY p.last_menstrual_period DESC LIMIT 100
            ");
            $dbMaternalCases = $mtlStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // Immunization/Vaccination Records
        try {
            $vccStmt = $pdo->query("
                SELECT v.id, v.resident_id, r.first_name, r.last_name, r.barangay, vac.vaccine_name, v.vaccination_date, 'Completed' as status
                FROM vaccination_records v
                LEFT JOIN residents r ON v.resident_id = r.id
                LEFT JOIN immunization_schedules vac ON v.vaccine_id = vac.id
                ORDER BY v.vaccination_date DESC LIMIT 100
            ");
            $dbVaccinationRecords = $vccStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { error_log('Admin vaccinations: ' . $e->getMessage()); }

        // Disease Surveillance Cases
        try {
            $dcsStmt = $pdo->query("
                SELECT dc.id, dc.disease_id, dt.disease_name, dc.resident_id, r.first_name, r.last_name, r.barangay, dc.case_date, dc.case_classification as status, dc.outcome
                FROM disease_cases dc
                LEFT JOIN disease_types dt ON dc.disease_id = dt.id
                LEFT JOIN residents r ON dc.resident_id = r.id
                ORDER BY dc.case_date DESC LIMIT 100
            ");
            $dbDiseaseCases = $dcsStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { error_log('Admin disease cases: ' . $e->getMessage()); }

        // Medicine Inventory
        try {
            $medStmt = $pdo->query("
                SELECT id, generic_name, brand_name, quantity_in_stock as quantity, reorder_level, unit_cost as unit_price, expiry_date, batch_number,
                       CASE
                           WHEN quantity_in_stock <= reorder_level THEN 'critical'
                           WHEN quantity_in_stock <= (reorder_level * 1.5) THEN 'low'
                           ELSE 'adequate'
                       END as status
                FROM medicine_inventory
                ORDER BY quantity_in_stock ASC LIMIT 100
            ");
            $dbMedicineInventory = $medStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // Vital Statistics (Births & Deaths)
        try {
            $vitalStmt = $pdo->query("
                SELECT id, 'Birth' as type, child_name, place_of_birth as location, date_of_birth as date_recorded
                FROM vital_statistics_births
                UNION ALL
                SELECT id, 'Death' as type, deceased_name, place_of_death, date_of_death
                FROM vital_statistics_deaths
                ORDER BY date_recorded DESC LIMIT 100
            ");
            $dbVitalStatistics = $vitalStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}

        // Statistics Summaries
        try {
            $consultationStats = $pdo->query("SELECT COUNT(*) as total FROM consultations")->fetchAll(PDO::FETCH_ASSOC);
            $maternalStats = $pdo->query("SELECT pregnancy_status as status, COUNT(*) as count FROM pregnancies GROUP BY pregnancy_status")->fetchAll(PDO::FETCH_ASSOC);
            $vaccinationStats = $pdo->query("SELECT COUNT(*) as count FROM vaccination_records")->fetchAll(PDO::FETCH_ASSOC);
            $diseaseStats = $pdo->query("SELECT dt.disease_name, COUNT(*) as count FROM disease_cases dc LEFT JOIN disease_types dt ON dc.disease_id = dt.id GROUP BY dt.disease_name ORDER BY count DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            $medicineStats = $pdo->query("SELECT CASE WHEN quantity_in_stock <= reorder_level THEN 'critical' WHEN quantity_in_stock <= (reorder_level*1.5) THEN 'low' ELSE 'adequate' END as status, COUNT(*) as count FROM medicine_inventory GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}



        $pendingCertCount = (int)($pdo->query("SELECT COUNT(*) FROM health_certificates WHERE validity_status = 'Pending'")->fetchColumn() ?: 0);
        if ($pendingCertCount > 0) {
            $adminNotifications[] = ['id' => 'n1', 'msg' => "{$pendingCertCount} pending certificate request(s) require review", 'type' => 'alert', 'time' => 'Action Needed', 'unread' => true];
        }

        try {
            $pendingMsgCount = (int)($pdo->query("SELECT COUNT(*) FROM messages WHERE status = 'Pending'")->fetchColumn() ?: 0);
            if ($pendingMsgCount > 0) {
                $adminNotifications[] = ['id' => 'n2', 'msg' => "{$pendingMsgCount} unread resident message(s) received", 'type' => 'alert', 'time' => 'Action Needed', 'unread' => true];
            }
        } catch (Exception $e) {}

        try {
            $nStmt = $pdo->prepare(
                "SELECT id, message AS msg, created_at AS time, (is_read = 0) AS unread
                 FROM portal_notifications
                 WHERE user_id = :user_id OR audience_role IN ('RHU_ADMIN', 'SUPER_ADMIN', 'ADMIN_STAFF')
                 ORDER BY created_at DESC LIMIT 20"
            );
            $nStmt->execute(['user_id' => (int)($_SESSION['user']['user_id'] ?? 0)]);
            $adminNotifications = array_merge($nStmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $adminNotifications);
        } catch (Exception $e) { error_log('Admin notifications: ' . $e->getMessage()); }

        $adminNotifications[] = ['id' => 'n4', 'msg' => 'System connected to MySQL rhu database.', 'type' => 'check', 'time' => 'Active', 'unread' => false];

    } catch (PDOException $ex) {
        error_log("RHUAdminDashboard DB Error: " . $ex->getMessage());
    }
}

$tabLabelMap = [
    'overview' => 'Overview',
    'users' => 'User Management',
    'staff' => 'Staff Accounts',
    'residents' => 'Resident Registry',
    'announcements' => 'Landing Page Content',
    'consultations' => 'OPD Consultations',
    'maternal' => 'Maternal Health',
    'vaccination' => 'Immunization',
    'disease' => 'Disease Surveillance',
    'medicine' => 'Medicine Inventory',
    'vital' => 'Vital Statistics',
    'reports' => 'Reports & Analytics',
    'audit' => 'Audit Logs',
    'system' => 'System Settings',
    'security' => 'Security',
];

$tabIconMap = [
    'overview' => 'home',
    'users' => 'users',
    'staff' => 'stethoscope',
    'residents' => 'check',
    'announcements' => 'bell',
    'consultations' => 'activity',
    'maternal' => 'baby',
    'vaccination' => 'shield',
    'disease' => 'alert',
    'medicine' => 'pill',
    'vital' => 'file',
    'reports' => 'bar',
    'audit' => 'database',
    'system' => 'settings',
    'security' => 'lock',
];

$drawerGroups = [
    'Dashboard' => ['overview', 'announcements'],
    'People & Accounts' => ['users', 'staff', 'residents'],
    'Clinical Services' => ['consultations', 'maternal', 'vaccination', 'disease'],
    'Records & Resources' => ['medicine', 'vital'],
    'Governance' => ['reports', 'audit', 'system', 'security'],
];

function tabUrl(string $tab, bool $notifs = false, array $extra = []): string
{
    $params = ['tab' => $tab];
    if ($notifs) {
        $params['notifs'] = '1';
    }
    foreach ($extra as $key => $value) {
        if ($value !== null) {
            $params[$key] = $value;
        }
    }
    return '?' . http_build_query($params);
}

function renderMetricCard(string $label, mixed $value, string $sub, string $icon, string $iconClass, string $textColor): void
{
    echo '<div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">';
    echo '<div class="w-9 h-9 ' . esc($iconClass) . ' rounded-lg flex items-center justify-center mb-3 text-white">' . iconSvg($icon, 'w-4.5 h-4.5') . '</div>';
    echo '<p class="text-2xl font-black text-gray-900">' . esc($value) . '</p>';
    echo '<p class="text-sm font-bold text-gray-700">' . esc($label) . '</p>';
    echo '<p class="text-xs text-gray-400">' . esc($sub) . '</p>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RHU Admin Dashboard - ResiHUnity RHU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html { scroll-behavior: smooth; }
        body { overflow-x: hidden; }

        .dashboard-header {
            transition: box-shadow .28s ease, transform .28s ease;
            will-change: box-shadow;
        }
        .dashboard-header.is-scrolled {
            box-shadow: 0 16px 35px -22px rgba(15, 23, 42, .75);
        }
        .feature-drawer-backdrop {
            opacity: 0;
            pointer-events: none;
            transition: opacity .28s ease;
        }
        .feature-drawer-backdrop.is-open {
            opacity: 1;
            pointer-events: auto;
        }
        .feature-drawer {
            transform: translateX(-102%);
            transition: transform .34s cubic-bezier(.2, .75, .25, 1);
            will-change: transform;
        }
        .feature-drawer.is-open {
            transform: translateX(0);
        }
        .feature-drawer a svg {
            transition: transform .2s ease;
        }
        .feature-drawer a:hover svg {
            transform: scale(1.08);
        }
        body.drawer-open {
            overflow: hidden;
        }

        main > div,
        main > section,
        main .bg-white.rounded-xl,
        main .bg-white.rounded-2xl,
        main details {
            transition: transform .24s ease, box-shadow .24s ease, opacity .24s ease;
        }
        main .bg-white.rounded-xl:hover,
        main .bg-white.rounded-2xl:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px -24px rgba(15, 23, 42, .55);
        }

        [data-scroll-reveal] {
            opacity: 0;
            transform: translateY(18px) scale(.992);
            transition:
                opacity .58s cubic-bezier(.2, .75, .25, 1),
                transform .58s cubic-bezier(.2, .75, .25, 1);
            transition-delay: var(--reveal-delay, 0ms);
        }
        [data-scroll-reveal].is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        table tbody tr {
            transition: transform .18s ease, box-shadow .18s ease;
        }
        table tbody tr:not([hidden]):hover {
            position: relative;
            z-index: 1;
            transform: translateX(3px);
            box-shadow: -4px 0 0 currentColor;
        }

        button, a, input, select, textarea, summary {
            transition:
                transform .18s ease,
                box-shadow .18s ease,
                opacity .18s ease,
                border-color .18s ease;
        }
        button:not(:disabled):active,
        a:active {
            transform: scale(.97);
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(15, 23, 42, .09);
        }
        details[open] > summary {
            margin-bottom: .65rem;
        }
        details > summary::marker {
            transition: transform .2s ease;
        }

        .metric-value-pop {
            animation: metricPop .52s cubic-bezier(.2, .75, .25, 1) both;
        }
        @keyframes metricPop {
            from { opacity: 0; transform: translateY(7px) scale(.94); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .scroll-top-button {
            opacity: 0;
            pointer-events: none;
            transform: translateY(12px) scale(.92);
            transition: opacity .22s ease, transform .22s ease;
        }
        .scroll-top-button.is-visible {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0) scale(1);
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
            [data-scroll-reveal] { opacity: 1; transform: none; }
        }
    </style>
  <link rel="stylesheet" href="dashboard-enhancements.css">
  <script defer src="dashboard-enhancements.js?v=20260726-controls3"></script>
</head>

<body class="min-h-screen bg-gray-50 text-gray-900">
    <div class="min-h-screen flex flex-col">
        <!-- HEADER -->
        <header class="dashboard-header bg-gradient-to-r from-slate-800 to-purple-900 text-white shadow-xl sticky top-0 z-40">
            <div class="px-4 sm:px-6 py-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <button type="button" data-drawer-open class="flex h-9 w-9 items-center justify-center rounded-xl border border-white/20 bg-white/10 hover:bg-white/20" aria-label="Open feature drawer" aria-controls="feature-drawer" aria-expanded="false">
                            <?php echo iconSvg('menu', 'w-5 h-5'); ?>
                        </button>
                        <div class="w-9 h-9 bg-purple-700 rounded-xl flex items-center justify-center">
                            <?php echo iconSvg('shield', 'w-5 h-5 text-purple-200'); ?>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-base font-bold">MHO / Admin Panel</h1>
                                <span class="hidden sm:block text-xs bg-purple-700 px-2 py-0.5 rounded-full text-purple-200 border border-purple-600">Municipal Health Officer</span>
                            </div>
                            <p class="text-xs text-purple-300"><?php echo esc($rhuProfile['name']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <a href="<?php echo esc(tabUrl($tab, !$showNotifs)); ?>" class="relative p-2 rounded-lg hover:bg-white/10 inline-flex">
                                <?php echo iconSvg('bell', 'w-5 h-5'); ?>
                                <?php if (count(array_filter($adminNotifications, static fn($n) => !empty($n['unread']))) > 0): ?>
                                    <span class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></span>
                                <?php endif; ?>
                            </a>
                            <?php if ($showNotifs): ?>
                                <div class="absolute right-0 top-10 w-80 max-w-[90vw] bg-white rounded-xl shadow-2xl border border-gray-100 z-50">
                                    <div class="p-3 border-b border-gray-100 flex items-center justify-between">
                                        <p class="font-bold text-gray-900 text-sm">System Notifications</p>
                                        <form method="post" class="flex items-center gap-2"><input type="hidden" name="action" value="mark_notifications_read"><span class="text-xs text-purple-600 font-semibold"><?php echo count($adminNotifications); ?> alerts</span><button class="text-[10px] font-bold text-gray-500">Mark read</button></form>
                                    </div>
                                    <div class="max-h-72 overflow-y-auto divide-y divide-gray-50">
                                        <?php foreach ($adminNotifications as $notif): ?>
                                            <div class="p-3 <?php echo !empty($notif['unread']) ? 'bg-purple-50/60' : ''; ?>">
                                                <p class="text-xs text-gray-800 font-medium"><?php echo esc($notif['msg']); ?></p>
                                                <p class="text-[10px] text-gray-400 mt-1"><?php echo esc($notif['time']); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="RHUDashboard.php" class="hidden sm:flex items-center gap-1.5 bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all">
                            <?php echo iconSvg('stethoscope', 'w-3.5 h-3.5'); ?> Clinical Dashboard
                        </a>
                        <?php 
                        $currentAdminName = trim(($_SESSION['user']['first_name'] ?? '') . ' ' . ($_SESSION['user']['last_name'] ?? '')) ?: $rhuProfile['mho_name'];
                        $currentAdminCode = $_SESSION['user']['admin_code'] ?? 'ADM-2026-0001';
                        $currentDesignation = $_SESSION['user']['designation'] ?? 'Municipal Health Officer';
                        ?>
                        <div class="flex items-center gap-2 bg-white/10 rounded-lg px-3 py-1.5" title="Admin ID: <?php echo esc($currentAdminCode); ?> | <?php echo esc($currentDesignation); ?>">
                            <div class="w-7 h-7 bg-purple-500 rounded-full flex items-center justify-center font-bold text-xs text-white">
                                <?php echo esc(strtoupper(substr($currentAdminName, 0, 1))); ?>
                            </div>
                            <div class="hidden sm:block text-left">
                                <p class="text-xs font-bold leading-none"><?php echo esc($currentAdminName); ?></p>
                                <p class="text-[10px] text-purple-200 leading-none mt-0.5"><?php echo esc($currentAdminCode); ?></p>
                            </div>
                        </div>
                        <a href="RHUAdminDashboard.php?logout=1" data-staff-logout class="staff-logout-trigger" aria-label="Sign out">
                            <?php echo iconSvg('logout', 'w-4 h-4'); ?><span>Log out</span>
                        </a>
                    </div>
                </div>
            </div>

        </header>

        <div data-drawer-backdrop class="feature-drawer-backdrop fixed inset-0 z-50 bg-slate-950/40" aria-hidden="true"></div>
        <aside id="feature-drawer" data-feature-drawer class="feature-drawer fixed inset-y-0 left-0 z-[60] flex w-[88vw] max-w-sm flex-col border-r border-gray-200 bg-white shadow-2xl" aria-label="RHU Admin features" aria-hidden="true">
            <div class="flex items-center justify-between border-b border-gray-100 p-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-700 text-white"><?php echo iconSvg('shield', 'w-5 h-5'); ?></span>
                    <div><p class="font-bold text-gray-900">Admin Features</p><p class="text-xs text-gray-500"><?php echo esc($tabLabelMap[$tab] ?? 'Dashboard'); ?></p></div>
                </div>
                <button type="button" data-drawer-close class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:shadow-sm" aria-label="Close feature drawer"><?php echo iconSvg('close', 'w-5 h-5'); ?></button>
            </div>
            <nav class="flex-1 overflow-y-auto p-3">
                <?php foreach ($drawerGroups as $groupLabel => $groupTabs): ?>
                    <section class="mb-4">
                        <h2 class="mb-1.5 px-3 text-[10px] font-black uppercase tracking-[0.16em] text-gray-400"><?php echo esc($groupLabel); ?></h2>
                        <div class="space-y-1">
                            <?php foreach ($groupTabs as $key): ?>
                                <?php $active = $tab === $key; ?>
                                <a href="<?php echo esc(tabUrl($key)); ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold <?php echo $active ? 'bg-purple-100 text-purple-900 shadow-sm' : 'text-gray-700 hover:bg-gray-50'; ?>" <?php echo $active ? 'aria-current="page"' : ''; ?>>
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg <?php echo $active ? 'bg-purple-700 text-white' : 'bg-gray-100 text-gray-600'; ?>"><?php echo iconSvg($tabIconMap[$key], 'w-4 h-4'); ?></span>
                                    <span class="flex-1"><?php echo esc($tabLabelMap[$key]); ?></span>
                                    <?php if ($active): ?><span aria-hidden="true"><?php echo iconSvg('right', 'w-3.5 h-3.5'); ?></span><?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </nav>
            <div class="border-t border-gray-100 p-3">
                <a href="RHUDashboard.php" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100"><?php echo iconSvg('stethoscope', 'w-4 h-4'); ?></span>Clinical Dashboard</a>
                <a href="RHUAdminDashboard.php?logout=1" data-staff-logout class="mt-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100"><?php echo iconSvg('logout', 'w-4 h-4'); ?></span>Sign Out</a>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 max-w-7xl mx-auto w-full px-3 sm:px-4 py-4 sm:py-6 space-y-4 sm:space-y-5 pb-6">
            <?php if ($flashSuccess): ?>
                <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-semibold text-green-800 flex items-center justify-between shadow-sm">
                    <span>✓ <?php echo esc($flashSuccess); ?></span>
                    <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800 font-bold">&times;</button>
                </div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800 flex items-center justify-between shadow-sm">
                    <span>⚠ <?php echo esc($flashError); ?></span>
                    <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800 font-bold">&times;</button>
                </div>
            <?php endif; ?>

            <!-- OVERVIEW TAB -->
            <?php if ($tab === 'overview'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <?php renderMetricCard('Total System Users', $totalUsersCount, 'Database Accounts', 'users', 'bg-purple-600', 'text-purple-600'); ?>
                        <?php renderMetricCard('Registered Residents', $totalResidentsCount, 'Barangay Registry', 'check', 'bg-blue-600', 'text-blue-600'); ?>
                        <?php renderMetricCard('Active Healthcare Staff', $totalStaffCount, 'Plantilla & Contractual', 'stethoscope', 'bg-emerald-600', 'text-emerald-600'); ?>
                        <?php renderMetricCard('Barangay Health Workers', $totalBhwCount, 'Assigned BHWs', 'users', 'bg-teal-600', 'text-teal-600'); ?>
                    </div>

                    <!-- CLINICAL SUMMARY CARDS -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <?php renderMetricCard('OPD Consultations', count($dbConsultationsList), 'View All', 'activity', 'bg-blue-600', 'text-blue-600'); ?>
                        <?php renderMetricCard('Maternal Cases', count($dbMaternalCases), 'Active Pregnancies', 'baby', 'bg-pink-600', 'text-pink-600'); ?>
                        <?php renderMetricCard('Immunizations', count($dbVaccinationRecords), 'Vaccination Records', 'shield', 'bg-emerald-600', 'text-emerald-600'); ?>
                        <?php renderMetricCard('Disease Cases', count($dbDiseaseCases), 'Active Surveillance', 'alert', 'bg-red-600', 'text-red-600'); ?>
                        <?php renderMetricCard('Medicine Items', count($dbMedicineInventory), 'Inventory Stock', 'pill', 'bg-orange-600', 'text-orange-600'); ?>
                    </div>

                    <!-- PENDING CERTIFICATE REQUESTS PANEL -->
                    <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-bold text-gray-900 text-base">Resident Medical Certificate Requests</h3>
                                <p class="text-xs text-gray-500">Live requests submitted by residents requiring Admin approval</p>
                            </div>
                            <span class="text-xs font-bold bg-purple-100 text-purple-800 px-3 py-1 rounded-full"><?php echo count($dbCertificatesList); ?> Records</span>
                        </div>
                        <?php if (empty($dbCertificatesList)): ?>
                            <p class="text-xs text-gray-400 italic py-3">No certificate requests found in database.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 text-gray-500 uppercase">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Resident Name</th>
                                            <th class="px-3 py-2 text-left">Certificate Type</th>
                                            <th class="px-3 py-2 text-left">Purpose</th>
                                            <th class="px-3 py-2 text-left">Status</th>
                                            <th class="px-3 py-2 text-left">Date Requested</th>
                                            <th class="px-3 py-2 text-left">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach ($dbCertificatesList as $cert): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-2.5 font-bold text-gray-900"><?php echo esc(($cert['first_name'] ?? '') . ' ' . ($cert['last_name'] ?? '')) ?: 'Resident #' . $cert['resident_id']; ?></td>
                                                <td class="px-3 py-2.5 text-gray-700"><?php echo esc($cert['certificate_type']); ?></td>
                                                <td class="px-3 py-2.5 text-gray-600 max-w-[200px] truncate"><?php echo esc($cert['purpose']); ?></td>
                                                <td class="px-3 py-2.5">
                                                    <?php if ($cert['status'] === 'Approved & Issued'): ?>
                                                        <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-bold">✓ Approved</span>
                                                    <?php elseif ($cert['status'] === 'Rejected'): ?>
                                                        <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-bold">✕ Rejected</span>
                                                    <?php else: ?>
                                                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold">⏳ Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-3 py-2.5 text-gray-500"><?php echo esc(date('M d, Y', strtotime($cert['created_at']))); ?></td>
                                                <td class="px-3 py-2.5">
                                                    <?php if ($cert['status'] === 'Pending'): ?>
                                                        <div class="flex items-center gap-1.5">
                                                            <form method="post" class="inline">
                                                                <input type="hidden" name="action" value="approve_certificate">
                                                                <input type="hidden" name="request_id" value="<?php echo $cert['id']; ?>">
                                                                <button type="submit" class="px-2.5 py-1 bg-green-600 text-white rounded font-semibold text-[11px] hover:bg-green-700">Approve</button>
                                                            </form>
                                                            <form method="post" class="inline">
                                                                <input type="hidden" name="action" value="reject_certificate">
                                                                <input type="hidden" name="request_id" value="<?php echo $cert['id']; ?>">
                                                                <button type="submit" class="px-2.5 py-1 bg-red-600 text-white rounded font-semibold text-[11px] hover:bg-red-700">Reject</button>
                                                            </form>
                                                        </div>
                                                    <?php else: ?>
                                                        <a href="?certificate_pdf=<?php echo (int)$cert['id']; ?>" class="text-[11px] font-bold text-purple-700">Download PDF</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- INCOMING RESIDENT MESSAGES PANEL -->
                    <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-bold text-gray-900 text-base">Incoming Resident Inquiries & Messages</h3>
                                <p class="text-xs text-gray-500">Live messages submitted from the Resident Portal</p>
                            </div>
                            <span class="text-xs font-bold bg-blue-100 text-blue-800 px-3 py-1 rounded-full"><?php echo count($dbMessagesList); ?> Messages</span>
                        </div>
                        <?php if (empty($dbMessagesList)): ?>
                            <p class="text-xs text-gray-400 italic py-3">No resident messages found in database.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($dbMessagesList as $msg): ?>
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 space-y-2">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-gray-900"><?php echo esc(($msg['first_name'] ?? '') . ' ' . ($msg['last_name'] ?? '')) ?: 'Resident #' . $msg['resident_id']; ?> (<?php echo esc($msg['email'] ?? ''); ?>)</span>
                                            <span class="text-gray-400"><?php echo esc(date('M d, Y h:i A', strtotime($msg['created_at']))); ?></span>
                                        </div>
                                        <p class="text-xs font-semibold text-purple-900">Subject: <?php echo esc($msg['subject']); ?></p>
                                        <p class="text-xs text-gray-700 bg-white p-2.5 rounded border border-gray-200"><?php echo esc($msg['message']); ?></p>

                                        <?php if (!empty($msg['admin_reply'])): ?>
                                            <div class="ml-4 p-2.5 bg-purple-50 rounded border-l-4 border-purple-600 text-xs">
                                                <p class="font-bold text-purple-900">Admin Response:</p>
                                                <p class="text-purple-800 mt-0.5"><?php echo esc($msg['admin_reply']); ?></p>
                                            </div>
                                        <?php else: ?>
                                            <form method="post" class="mt-2 flex gap-2">
                                                <input type="hidden" name="action" value="reply_message">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                                <input required name="reply" class="flex-1 text-xs px-3 py-1.5 rounded border border-gray-300 focus:outline-none focus:border-purple-600" placeholder="Type response to resident...">
                                                <button type="submit" class="px-3 py-1.5 bg-purple-700 text-white rounded text-xs font-semibold hover:bg-purple-800">Send Reply</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'overview'): ?>
                    <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-bold text-gray-900 text-base">Event Registrations</h3>
                                <p class="text-xs text-gray-500">Resident registrations for database-backed RHU events</p>
                            </div>
                            <span class="text-xs font-bold bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full"><?php echo count($dbEventsList); ?> Registrations</span>
                        </div>
                        <?php if (!$dbEventsList): ?>
                            <p class="text-xs text-gray-400 italic">No event registrations found.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead><tr class="text-left text-gray-500"><th class="p-2">Resident</th><th class="p-2">Event</th><th class="p-2">Date</th><th class="p-2">Status</th><th class="p-2">Action</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($dbEventsList as $eventRegistration): ?>
                                        <tr class="border-t">
                                            <td class="p-2 font-semibold"><?php echo esc(trim(($eventRegistration['first_name'] ?? '') . ' ' . ($eventRegistration['last_name'] ?? ''))); ?></td>
                                            <td class="p-2"><?php echo esc($eventRegistration['event_title']); ?></td>
                                            <td class="p-2"><?php echo esc($eventRegistration['event_date']); ?></td>
                                            <td class="p-2"><?php echo esc($eventRegistration['status']); ?></td>
                                            <td class="p-2">
                                                <?php if ($eventRegistration['status'] === 'Pending'): ?>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="confirm_event">
                                                    <input type="hidden" name="event_id" value="<?php echo (int)$eventRegistration['id']; ?>">
                                                    <button class="px-2 py-1 rounded bg-indigo-600 text-white font-bold">Confirm</button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <details class="mt-4 rounded border p-3"><summary class="cursor-pointer text-xs font-bold">Edit or cancel a published event</summary><div class="mt-3 space-y-2"><?php
                            $publishedEvents = $pdo->query("SELECT id,title,scheduled_date,start_time,venue,description,capacity,status FROM portal_events ORDER BY scheduled_date DESC")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($publishedEvents as $publishedEvent): ?>
                            <form method="post" class="grid gap-1 border-t pt-2 text-xs sm:grid-cols-6">
                                <input type="hidden" name="action" value="save_event"><input type="hidden" name="event_id" value="<?php echo (int)$publishedEvent['id']; ?>">
                                <input name="title" value="<?php echo esc($publishedEvent['title']); ?>" class="rounded border p-1">
                                <input type="date" name="scheduled_date" value="<?php echo esc($publishedEvent['scheduled_date']); ?>" class="rounded border p-1">
                                <input type="time" name="start_time" value="<?php echo esc($publishedEvent['start_time']); ?>" class="rounded border p-1">
                                <input name="venue" value="<?php echo esc($publishedEvent['venue']); ?>" class="rounded border p-1">
                                <input name="description" value="<?php echo esc($publishedEvent['description']); ?>" class="rounded border p-1">
                                <input type="number" name="capacity" value="<?php echo esc($publishedEvent['capacity']); ?>" class="rounded border p-1">
                                <select name="status" class="rounded border p-1"><option>Scheduled</option><option>Cancelled</option><option>Completed</option></select>
                                <button class="rounded bg-indigo-700 p-1 text-white">Save</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete this event?')" class="mt-1"><input type="hidden" name="action" value="delete_event"><input type="hidden" name="event_id" value="<?php echo (int)$publishedEvent['id']; ?>"><button class="text-xs font-bold text-red-700">Delete <?php echo esc($publishedEvent['title']); ?></button></form>
                            <?php endforeach; ?>
                        </div></details>
                    </div>
            <?php endif; ?>

            <!-- USER MANAGEMENT TAB -->
            <?php if ($tab === 'users'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('users', 'w-5 h-5 text-blue-600'); ?> Database User Accounts</h2>
                        <span class="text-xs bg-blue-100 text-blue-800 font-bold px-3 py-1 rounded-full">Total: <?php echo count($dbUsersList); ?> Accounts</span>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs min-w-[700px]">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left">ID</th>
                                        <th class="px-3 py-2.5 text-left">Name</th>
                                        <th class="px-3 py-2.5 text-left">Email Address</th>
                                        <th class="px-3 py-2.5 text-left">Role</th>
                                        <th class="px-3 py-2.5 text-left">Status</th>
                                        <th class="px-3 py-2.5 text-left">Created At</th>
                                        <th class="px-3 py-2.5 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($dbUsersList as $u): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2.5 font-mono font-bold text-gray-500">#<?php echo $u['id']; ?></td>
                                            <td class="px-3 py-2.5 font-bold text-gray-900"><?php echo esc(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?: $u['username']; ?></td>
                                            <td class="px-3 py-2.5 text-gray-700 font-mono"><?php echo esc($u['email']); ?></td>
                                            <td class="px-3 py-2.5"><form method="post" class="flex gap-1"><input type="hidden" name="action" value="update_user_role"><input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>"><select name="role_id" class="rounded border p-1"><?php foreach ($dbRoles as $role): ?><option value="<?php echo (int)$role['id']; ?>" <?php echo ($u['role_name'] ?? '') === $role['name'] ? 'selected' : ''; ?>><?php echo esc($role['name']); ?></option><?php endforeach; ?></select><button class="rounded bg-purple-700 px-2 text-white">Set</button></form></td>
                                            <td class="px-3 py-2.5">
                                                <?php if ($u['is_active']): ?>
                                                    <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-bold">Active</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-bold">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-2.5 text-gray-500"><?php echo esc(date('M d, Y', strtotime($u['created_at']))); ?></td>
                                            <td class="px-3 py-2.5">
                                                <div class="flex items-center gap-1.5">
                                                    <form method="post" class="inline">
                                                        <input type="hidden" name="action" value="toggle_user_status">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <input type="hidden" name="new_status" value="<?php echo $u['is_active'] ? 0 : 1; ?>">
                                                        <button type="submit" class="px-2 py-0.5 text-[11px] font-semibold rounded <?php echo $u['is_active'] ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-green-100 text-green-800 hover:bg-green-200'; ?>">
                                                            <?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                    </form>
                                                    <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this user from database?');">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="px-2 py-0.5 text-[11px] font-semibold rounded bg-red-100 text-red-700 hover:bg-red-200">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- STAFF ACCOUNTS TAB -->
            <?php if ($tab === 'staff'): ?>
                <?php renderAdminExtendedPanel($pdo, 'staff'); ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('stethoscope', 'w-5 h-5 text-emerald-600'); ?> Healthcare Staff Registry</h2>
                        <a href="<?php echo esc(tabUrl('staff', false, ['action' => 'new'])); ?>" class="px-3 py-1.5 bg-emerald-600 text-white text-xs font-bold rounded-lg hover:bg-emerald-700">+ Create Staff Account</a>
                    </div>

                    <?php if (($_GET['action'] ?? '') === 'new'): ?>
                        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 space-y-4">
                            <h3 class="font-bold text-gray-900 text-base">Register New Healthcare Staff</h3>
                            <form method="post" class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                <input type="hidden" name="action" value="create_staff">
                                <label class="block">First Name * <input required name="first_name" class="mt-1 w-full p-2 border rounded border-gray-300"></label>
                                <label class="block">Last Name * <input required name="last_name" class="mt-1 w-full p-2 border rounded border-gray-300"></label>
                                <label class="block">Email Address * <input required type="email" name="email" class="mt-1 w-full p-2 border rounded border-gray-300"></label>
                                <label class="block">Password * <input required type="password" name="password" class="mt-1 w-full p-2 border rounded border-gray-300"></label>
                                <label class="block">Staff Position * 
                                    <select required name="staff_type" class="mt-1 w-full p-2 border rounded border-gray-300">
                                        <option value="ADMIN_STAFF">RHU Admin Staff</option>
                                        <option value="PHYSICIAN">Rural Health Physician</option>
                                        <option value="NURSE">Public Health Nurse</option>
                                        <option value="MIDWIFE">Midwife</option>
                                        <option value="MEDTECH">Medical Technologist</option>
                                        <option value="SANITARY_INSPECTOR">Sanitary Inspector</option>
                                        <option value="BHW">Barangay Health Worker (BHW)</option>
                                    </select>
                                </label>
                                <label class="block">PRC License / Badge No. <input name="license_number" class="mt-1 w-full p-2 border rounded border-gray-300"></label>
                                <label class="block">Specialization <input name="specialization" class="mt-1 w-full p-2 border rounded border-gray-300" placeholder="Public Health"></label>
                                <label class="block">Contact Phone <input name="phone_number" class="mt-1 w-full p-2 border rounded border-gray-300"></label>
                                <div class="sm:col-span-2 flex items-center justify-end gap-2 pt-2">
                                    <a href="<?php echo esc(tabUrl('staff')); ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded font-bold">Cancel</a>
                                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded font-bold hover:bg-emerald-700">Save Account</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs min-w-[700px]">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left">Staff ID</th>
                                        <th class="px-3 py-2.5 text-left">Full Name</th>
                                        <th class="px-3 py-2.5 text-left">Position</th>
                                        <th class="px-3 py-2.5 text-left">License No.</th>
                                        <th class="px-3 py-2.5 text-left">Contact</th>
                                        <th class="px-3 py-2.5 text-left">Status</th>
                                        <th class="px-3 py-2.5 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($dbStaffList as $st): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2.5 font-mono font-bold text-gray-500">STF-<?php echo $st['id']; ?></td>
                                            <td class="px-3 py-2.5 font-bold text-gray-900"><?php echo esc(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? '')); ?></td>
                                            <td class="px-3 py-2.5 text-purple-900 font-semibold"><?php echo esc($st['staff_type']); ?></td>
                                            <td class="px-3 py-2.5 font-mono text-gray-600"><?php echo esc($st['license_number'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($st['phone_number'] ?? $st['email']); ?></td>
                                            <td class="px-3 py-2.5">
                                                <span class="px-2 py-0.5 rounded-full font-bold <?php echo $st['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                                    <?php echo $st['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="action" value="toggle_staff_status">
                                                    <input type="hidden" name="staff_id" value="<?php echo $st['id']; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo $st['is_active'] ? 0 : 1; ?>">
                                                    <button type="submit" class="px-2.5 py-1 text-[11px] font-bold rounded <?php echo $st['is_active'] ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'; ?>">
                                                        <?php echo $st['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- RESIDENT REGISTRY TAB -->
            <?php if ($tab === 'residents'): ?>
                <?php renderAdminExtendedPanel($pdo, 'residents'); ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('check', 'w-5 h-5 text-teal-600'); ?> Municipal Resident Registry</h2>
                        <span class="text-xs bg-teal-100 text-teal-800 font-bold px-3 py-1 rounded-full">Total: <?php echo count($dbResidentList); ?> Residents</span>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs min-w-[700px]">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left">Resident ID</th>
                                        <th class="px-3 py-2.5 text-left">Full Name</th>
                                        <th class="px-3 py-2.5 text-left">Age / Sex</th>
                                        <th class="px-3 py-2.5 text-left">Barangay & Address</th>
                                        <th class="px-3 py-2.5 text-left">Contact No.</th>
                                        <th class="px-3 py-2.5 text-left">PhilHealth ID</th>
                                        <th class="px-3 py-2.5 text-left">Email Address</th>
                                        <th class="px-3 py-2.5 text-left">Registered Date</th>
                                        <th class="px-3 py-2.5 text-left">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($dbResidentList as $res): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2.5 font-mono font-bold text-gray-500">RES-<?php echo $res['id']; ?></td>
                                            <td class="px-3 py-2.5 font-bold text-gray-900"><?php echo esc(trim(($res['first_name'] ?? '') . ' ' . ($res['middle_name'] ?? '') . ' ' . ($res['last_name'] ?? ''))); ?></td>
                                            <td class="px-3 py-2.5 text-gray-700"><?php echo esc(($res['age'] ?? 'N/A') . ' yrs • ' . ($res['gender'] ?? 'N/A')); ?></td>
                                            <td class="px-3 py-2.5 font-semibold text-purple-900"><?php echo esc($res['barangay'] ?? 'Nasugbu'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600 font-mono"><?php echo esc($res['contact_number'] ?: 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 font-mono text-gray-600"><?php echo esc($res['philhealth_id'] ?: 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600 font-mono"><?php echo esc($res['email'] ?: 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-500"><?php echo esc(date('M d, Y', strtotime($res['created_at']))); ?></td>
                                            <td class="px-3 py-2.5"><form method="post"><input type="hidden" name="action" value="update_resident_status"><input type="hidden" name="resident_id" value="<?php echo (int)$res['id']; ?>"><input type="hidden" name="new_status" value="<?php echo !empty($res['is_active']) ? 0 : 1; ?>"><button class="rounded px-2 py-1 font-bold <?php echo !empty($res['is_active']) ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'; ?>"><?php echo !empty($res['is_active']) ? 'Deactivate' : 'Activate'; ?></button></form></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- OPD CONSULTATIONS TAB -->
            <?php if ($tab === 'consultations'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('activity', 'w-5 h-5 text-blue-600'); ?> OPD Consultations</h2>
                        <span class="text-xs bg-blue-100 text-blue-800 font-bold px-3 py-1 rounded-full">Total: <?php echo count($dbConsultationsList); ?> Consultations</span>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs min-w-[800px]">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left">Consultation ID</th>
                                        <th class="px-3 py-2.5 text-left">Patient Name</th>
                                        <th class="px-3 py-2.5 text-left">Barangay</th>
                                        <th class="px-3 py-2.5 text-left">Chief Complaint</th>
                                        <th class="px-3 py-2.5 text-left">Diagnosis</th>
                                        <th class="px-3 py-2.5 text-left">Date</th>
                                        <th class="px-3 py-2.5 text-left">Clinical Update</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($dbConsultationsList as $csl): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2.5 font-mono font-bold text-gray-500">CSL-<?php echo $csl['id']; ?></td>
                                            <td class="px-3 py-2.5 font-bold text-gray-900"><?php echo esc(($csl['first_name'] ?? '') . ' ' . ($csl['last_name'] ?? '')); ?></td>
                                            <td class="px-3 py-2.5 text-purple-900"><?php echo esc($csl['barangay'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-700"><?php echo esc(substr($csl['chief_complaint'] ?? '', 0, 30)); ?></td>
                                            <td class="px-3 py-2.5 text-gray-700"><?php echo esc($csl['diagnosis'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-500"><?php echo esc(date('M d, Y', strtotime($csl['consultation_date']))); ?></td>
                                            <td class="px-3 py-2.5"><form method="post" class="flex min-w-[620px] gap-1"><input type="hidden" name="action" value="update_consultation"><input type="hidden" name="consultation_id" value="<?php echo (int)$csl['id']; ?>"><input name="diagnosis" value="<?php echo esc($csl['diagnosis'] ?? ''); ?>" placeholder="Diagnosis" class="w-28 rounded border p-1"><input name="treatment_plan" placeholder="Treatment plan" class="w-32 rounded border p-1"><input type="date" name="follow_up_date" class="rounded border p-1"><select name="physician_id" class="rounded border p-1"><?php foreach ($dbStaffList as $provider): ?><option value="<?php echo (int)$provider['id']; ?>" <?php echo (int)$csl['physician_id'] === (int)$provider['id'] ? 'selected' : ''; ?>><?php echo esc($provider['first_name'] . ' ' . $provider['last_name']); ?></option><?php endforeach; ?></select><select name="consultation_status" class="rounded border p-1"><option>Scheduled</option><option>In Progress</option><option>Completed</option><option>Cancelled</option><option>Referred</option></select><button class="rounded bg-blue-600 px-2 text-white">Save</button></form></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($dbConsultationsList)): ?>
                                <div class="p-4 text-center text-gray-500">No consultation records found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- MATERNAL HEALTH TAB -->
            <?php if ($tab === 'maternal'): ?>
                <?php renderAdminExtendedPanel($pdo, 'maternal'); ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('baby', 'w-5 h-5 text-pink-600'); ?> Maternal Health Cases</h2>
                        <span class="text-xs bg-pink-100 text-pink-800 font-bold px-3 py-1 rounded-full">Total: <?php echo count($dbMaternalCases); ?> Cases</span>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs min-w-[850px]">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left">Case ID</th>
                                        <th class="px-3 py-2.5 text-left">Patient Name</th>
                                        <th class="px-3 py-2.5 text-left">Barangay</th>
                                        <th class="px-3 py-2.5 text-left">LMP</th>
                                        <th class="px-3 py-2.5 text-left">EDC</th>
                                        <th class="px-3 py-2.5 text-left">Blood Type</th>
                                        <th class="px-3 py-2.5 text-left">Status</th>
                                        <th class="px-3 py-2.5 text-left">Update</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($dbMaternalCases as $mtl): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2.5 font-mono font-bold text-gray-500">MTL-<?php echo $mtl['id']; ?></td>
                                            <td class="px-3 py-2.5 font-bold text-gray-900"><?php echo esc(($mtl['first_name'] ?? '') . ' ' . ($mtl['last_name'] ?? '')); ?></td>
                                            <td class="px-3 py-2.5 text-purple-900"><?php echo esc($mtl['barangay'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc(date('M d, Y', strtotime($mtl['lmp'] ?? 'now'))); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc(date('M d, Y', strtotime($mtl['edc'] ?? 'now'))); ?></td>
                                            <td class="px-3 py-2.5 font-bold"><?php echo esc($mtl['blood_type'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5"><span class="px-2 py-0.5 rounded-full bg-pink-100 text-pink-800 font-bold"><?php echo esc($mtl['status'] ?? 'Active'); ?></span></td>
                                            <td class="px-3 py-2.5"><form method="post" class="flex gap-1"><input type="hidden" name="action" value="update_pregnancy"><input type="hidden" name="pregnancy_id" value="<?php echo (int)$mtl['id']; ?>"><select name="status" class="rounded border p-1"><option>Active</option><option>Delivered</option><option>Completed</option><option>Referred</option></select><label class="flex items-center gap-1"><input type="checkbox" name="high_risk" <?php echo !empty($mtl['high_risk']) ? 'checked' : ''; ?>> High risk</label><button class="rounded bg-pink-600 px-2 text-white">Save</button></form></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($dbMaternalCases)): ?>
                                <div class="p-4 text-center text-gray-500">No maternal health cases found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- IMMUNIZATION TAB -->
            <?php if ($tab === 'vaccination'): ?>
                <?php renderAdminExtendedPanel($pdo, 'vaccination'); ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('shield', 'w-5 h-5 text-emerald-600'); ?> Immunization Records</h2>
                        <span class="text-xs bg-emerald-100 text-emerald-800 font-bold px-3 py-1 rounded-full">Total: <?php echo count($dbVaccinationRecords); ?> Records</span>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs min-w-[750px]">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left">Record ID</th>
                                        <th class="px-3 py-2.5 text-left">Beneficiary</th>
                                        <th class="px-3 py-2.5 text-left">Barangay</th>
                                        <th class="px-3 py-2.5 text-left">Vaccine Name</th>
                                        <th class="px-3 py-2.5 text-left">Vaccination Date</th>
                                        <th class="px-3 py-2.5 text-left">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($dbVaccinationRecords as $vcc): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2.5 font-mono font-bold text-gray-500">VCC-<?php echo $vcc['id']; ?></td>
                                            <td class="px-3 py-2.5 font-bold text-gray-900"><?php echo esc(($vcc['first_name'] ?? '') . ' ' . ($vcc['last_name'] ?? '')); ?></td>
                                            <td class="px-3 py-2.5 text-purple-900"><?php echo esc($vcc['barangay'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-700"><?php echo esc($vcc['vaccine_name'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc(date('M d, Y', strtotime($vcc['vaccination_date']))); ?></td>
                                            <td class="px-3 py-2.5"><span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold"><?php echo esc($vcc['status'] ?? 'Completed'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($dbVaccinationRecords)): ?>
                                <div class="p-4 text-center text-gray-500">No vaccination records found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- DISEASE SURVEILLANCE TAB -->
            <?php if ($tab === 'disease'): ?>
                <?php renderAdminExtendedPanel($pdo, 'disease'); ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('alert', 'w-5 h-5 text-red-600'); ?> Disease Surveillance</h2>
                        <span class="text-xs bg-red-100 text-red-800 font-bold px-3 py-1 rounded-full">Total: <?php echo count($dbDiseaseCases); ?> Cases</span>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs min-w-[800px]">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left">Case ID</th>
                                        <th class="px-3 py-2.5 text-left">Disease</th>
                                        <th class="px-3 py-2.5 text-left">Patient</th>
                                        <th class="px-3 py-2.5 text-left">Barangay</th>
                                        <th class="px-3 py-2.5 text-left">Case Date</th>
                                        <th class="px-3 py-2.5 text-left">Classification</th>
                                            <th class="px-3 py-2.5 text-left">Outcome</th>
                                            <th class="px-3 py-2.5 text-left">Update</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($dbDiseaseCases as $dcs): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2.5 font-mono font-bold text-gray-500">DCS-<?php echo $dcs['id']; ?></td>
                                            <td class="px-3 py-2.5 font-bold text-red-700"><?php echo esc($dcs['disease_name'] ?? 'Unknown'); ?></td>
                                            <td class="px-3 py-2.5 font-bold text-gray-900"><?php echo esc(($dcs['first_name'] ?? '') . ' ' . ($dcs['last_name'] ?? '')); ?></td>
                                            <td class="px-3 py-2.5 text-purple-900"><?php echo esc($dcs['barangay'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc(date('M d, Y', strtotime($dcs['case_date']))); ?></td>
                                            <td class="px-3 py-2.5"><span class="px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-800 font-bold"><?php echo esc($dcs['status'] ?? 'Pending'); ?></span></td>
                                            <td class="px-3 py-2.5"><span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-800 font-bold"><?php echo esc($dcs['outcome'] ?? 'Pending'); ?></span></td>
                                            <td class="px-3 py-2.5"><form method="post" class="flex min-w-[360px] gap-1"><input type="hidden" name="action" value="update_disease_case"><input type="hidden" name="case_id" value="<?php echo (int)$dcs['id']; ?>"><select name="classification" class="rounded border p-1"><option>Suspected</option><option>Probable</option><option>Confirmed</option></select><select name="outcome" class="rounded border p-1"><option>Active</option><option>Recovered</option><option>Referred</option><option>Died</option></select><label class="flex items-center gap-1"><input type="checkbox" name="reported_to_doh"> DOH</label><button class="rounded bg-red-600 px-2 text-white">Save</button></form></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($dbDiseaseCases)): ?>
                                <div class="p-4 text-center text-gray-500">No disease cases found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- MEDICINE INVENTORY TAB -->
            <?php if ($tab === 'medicine'): ?>
                <?php renderAdminExtendedPanel($pdo, 'medicine'); ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('pill', 'w-5 h-5 text-orange-600'); ?> Medicine Inventory</h2>
                        <span class="text-xs bg-orange-100 text-orange-800 font-bold px-3 py-1 rounded-full">Total Items: <?php echo count($dbMedicineInventory); ?></span>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs min-w-[900px]">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left">Item ID</th>
                                        <th class="px-3 py-2.5 text-left">Generic Name</th>
                                        <th class="px-3 py-2.5 text-left">Brand Name</th>
                                        <th class="px-3 py-2.5 text-left">Quantity</th>
                                        <th class="px-3 py-2.5 text-left">Reorder Level</th>
                                        <th class="px-3 py-2.5 text-left">Unit Cost</th>
                                        <th class="px-3 py-2.5 text-left">Batch No.</th>
                                        <th class="px-3 py-2.5 text-left">Expiry Date</th>
                                        <th class="px-3 py-2.5 text-left">Status</th>
                                        <th class="px-3 py-2.5 text-left">Stock Adjustment</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($dbMedicineInventory as $med): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2.5 font-mono font-bold text-gray-500">MED-<?php echo $med['id']; ?></td>
                                            <td class="px-3 py-2.5 font-bold text-gray-900"><?php echo esc($med['generic_name'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-700"><?php echo esc($med['brand_name'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 font-bold"><?php echo esc($med['quantity'] ?? 0); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc($med['reorder_level'] ?? 10); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600">₱<?php echo esc(number_format($med['unit_price'] ?? 0, 2)); ?></td>
                                            <td class="px-3 py-2.5 font-mono text-gray-600"><?php echo esc($med['batch_number'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc(date('M d, Y', strtotime($med['expiry_date']))); ?></td>
                                            <td class="px-3 py-2.5">
                                                <span class="px-2 py-0.5 rounded-full font-bold <?php
                                                    if ($med['status'] === 'critical') echo 'bg-red-100 text-red-800';
                                                    elseif ($med['status'] === 'low') echo 'bg-yellow-100 text-yellow-800';
                                                    else echo 'bg-green-100 text-green-800';
                                                ?>">
                                                    <?php echo esc(ucfirst($med['status'] ?? 'adequate')); ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-2.5"><form method="post" class="flex min-w-[280px] gap-1"><input type="hidden" name="action" value="adjust_medicine_stock"><input type="hidden" name="medicine_id" value="<?php echo (int)$med['id']; ?>"><input required type="number" name="quantity_change" placeholder="+/- qty" class="w-20 rounded border p-1"><input name="reason" placeholder="Reason" class="w-28 rounded border p-1"><button class="rounded bg-orange-600 px-2 text-white">Apply</button></form></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($dbMedicineInventory)): ?>
                                <div class="p-4 text-center text-gray-500">No medicine inventory records found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- VITAL STATISTICS TAB -->
            <?php if ($tab === 'vital'): ?>
                <?php renderAdminExtendedPanel($pdo, 'vital'); ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('file', 'w-5 h-5 text-purple-600'); ?> Vital Statistics</h2>
                        <span class="text-xs bg-purple-100 text-purple-800 font-bold px-3 py-1 rounded-full">Total: <?php echo count($dbVitalStatistics); ?> Records</span>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs min-w-[750px]">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left">Record ID</th>
                                        <th class="px-3 py-2.5 text-left">Type</th>
                                        <th class="px-3 py-2.5 text-left">Name</th>
                                        <th class="px-3 py-2.5 text-left">Location</th>
                                        <th class="px-3 py-2.5 text-left">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($dbVitalStatistics as $vital): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2.5 font-mono font-bold text-gray-500">VIT-<?php echo $vital['id']; ?></td>
                                            <td class="px-3 py-2.5">
                                                <span class="px-2 py-0.5 rounded-full font-bold <?php echo $vital['type'] === 'Birth' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                    <?php echo esc($vital['type']); ?>
                                                </span>
                                            </td>
                                            <td class="px-3 py-2.5 font-bold text-gray-900"><?php echo esc($vital['child_name'] ?? $vital['deceased_name'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-purple-900"><?php echo esc($vital['location'] ?? 'N/A'); ?></td>
                                            <td class="px-3 py-2.5 text-gray-600"><?php echo esc(date('M d, Y', strtotime($vital['date_recorded']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (empty($dbVitalStatistics)): ?>
                                <div class="p-4 text-center text-gray-500">No vital statistics records found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- AUDIT LOGS TAB -->

            <?php if ($tab === 'audit'): ?>
                <div class="space-y-4 sm:space-y-5">
                    <h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('database', 'w-5 h-5 text-slate-600'); ?> System Audit Trail</h2>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs min-w-[800px]">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left">ID</th>
                                        <th class="px-3 py-2.5 text-left">User</th>
                                        <th class="px-3 py-2.5 text-left">Action</th>
                                        <th class="px-3 py-2.5 text-left">Table / Record</th>
                                        <th class="px-3 py-2.5 text-left">Timestamp</th>
                                        <th class="px-3 py-2.5 text-left">IP Address</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($dbAuditLogsList as $log): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2.5 font-mono text-gray-400">#<?php echo $log['id']; ?></td>
                                            <td class="px-3 py-2.5 font-bold text-gray-900"><?php echo esc(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'User #' . $log['user_id']; ?></td>
                                            <td class="px-3 py-2.5 text-purple-950 font-medium"><?php echo esc($log['action']); ?></td>
                                            <td class="px-3 py-2.5 font-mono text-gray-500"><?php echo esc($log['table_name'] ?? ''); ?> #<?php echo esc($log['record_id'] ?? ''); ?></td>
                                            <td class="px-3 py-2.5 text-gray-500 font-mono"><?php echo esc(date('Y-m-d H:i:s', strtotime($log['timestamp']))); ?></td>
                                            <td class="px-3 py-2.5 font-mono text-gray-400"><?php echo esc($log['ip_address'] ?? '127.0.0.1'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- REPORTS & ANALYTICS TAB -->
            <?php if ($tab === 'reports'): ?>
                <?php renderAdminExtendedPanel($pdo, 'reports'); ?>
                <div class="space-y-4 sm:space-y-5">
                    <div class="flex items-center justify-between"><h2 class="text-base sm:text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('bar', 'w-5 h-5 text-purple-600'); ?> Database Analytics & Reports</h2><a href="?tab=reports&amp;export=summary" class="rounded-lg bg-purple-700 px-4 py-2 text-xs font-bold text-white">Export CSV Summary</a></div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100 space-y-3">
                            <h3 class="font-bold text-gray-900 text-sm">Resident Distribution by Barangay</h3>
                            <?php foreach ($barangayStats as $b): ?>
                                <div class="flex items-center justify-between text-xs py-1 border-b border-gray-50">
                                    <span class="font-semibold text-gray-700"><?php echo esc($b['barangay'] ?: 'Nasugbu Poblacion'); ?></span>
                                    <span class="font-mono font-bold bg-blue-100 text-blue-800 px-2 py-0.5 rounded"><?php echo $b['count']; ?> residents</span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100 space-y-3">
                            <h3 class="font-bold text-gray-900 text-sm">Staff Breakdown by Position</h3>
                            <?php foreach ($staffTypeStats as $st): ?>
                                <div class="flex items-center justify-between text-xs py-1 border-b border-gray-50">
                                    <span class="font-semibold text-gray-700"><?php echo esc($st['staff_type']); ?></span>
                                    <span class="font-mono font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded"><?php echo $st['count']; ?> staff</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- RE-DESIGNED SYSTEM SETTINGS TAB -->
            <?php if ($tab === 'system'): ?>
                <?php renderAdminExtendedPanel($pdo, 'system'); ?>
                <div class="space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('settings', 'w-6 h-6 text-purple-700'); ?> System & Facility Settings</h2>
                            <p class="text-xs text-gray-500 mt-1">Configure health office profile, SMTP gateways, and system maintenance controls</p>
                        </div>
                        <span class="text-xs font-bold bg-purple-100 text-purple-800 px-3 py-1 rounded-full">XAMPP Environment</span>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- 1. RHU FACILITY PROFILE FORM -->
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-4">
                            <div class="flex items-center gap-2 border-b border-gray-100 pb-3">
                                <span class="p-2 bg-purple-100 rounded-lg text-purple-700"><?php echo iconSvg('building', 'w-5 h-5'); ?></span>
                                <h3 class="font-bold text-gray-900 text-base">Facility & RHU Profile</h3>
                            </div>
                            <form method="post" class="space-y-4 text-xs">
                                <input type="hidden" name="action" value="update_rhu_info">
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Facility Name</label>
                                    <input required name="rhu_name" value="<?php echo esc($rhuProfile['name']); ?>" class="w-full p-2.5 rounded-lg border border-gray-300 text-gray-900 focus:border-purple-600 focus:outline-none">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Municipality</label>
                                        <input required name="municipality" value="<?php echo esc($rhuProfile['municipality']); ?>" class="w-full p-2.5 rounded-lg border border-gray-300 text-gray-900">
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Province</label>
                                        <input required name="province" value="<?php echo esc($rhuProfile['province']); ?>" class="w-full p-2.5 rounded-lg border border-gray-300 text-gray-900">
                                    </div>
                                </div>
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Municipal Health Officer (MHO)</label>
                                    <input required name="mho_name" value="<?php echo esc($rhuProfile['mho_name']); ?>" class="w-full p-2.5 rounded-lg border border-gray-300 text-gray-900">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Contact Telephone</label>
                                        <input required name="contact" value="<?php echo esc($rhuProfile['contact']); ?>" class="w-full p-2.5 rounded-lg border border-gray-300 text-gray-900">
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Official Email</label>
                                        <input required type="email" name="email" value="<?php echo esc($rhuProfile['email']); ?>" class="w-full p-2.5 rounded-lg border border-gray-300 text-gray-900">
                                    </div>
                                </div>
                                <button type="submit" class="w-full py-2.5 bg-purple-700 text-white rounded-lg font-bold hover:bg-purple-800 transition-all shadow-md">
                                    Save Facility Settings
                                </button>
                            </form>
                        </div>

                        <!-- 2. SMTP MAIL GATEWAY & TEST SENDER -->
                        <div class="space-y-6">
                            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-4">
                                <div class="flex items-center gap-2 border-b border-gray-100 pb-3">
                                    <span class="p-2 bg-blue-100 rounded-lg text-blue-700"><?php echo iconSvg('mail', 'w-5 h-5'); ?></span>
                                    <h3 class="font-bold text-gray-900 text-base">Email SMTP Gateway Configuration</h3>
                                </div>
                                <div class="space-y-3 text-xs">
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-500 font-medium">SMTP Server Host</span>
                                        <span class="font-mono font-bold text-gray-900"><?php echo esc(portalSetting($savedPortalSettings, 'smtp_host', 'Not configured') . ':' . portalSetting($savedPortalSettings, 'smtp_port', '587') . ' (' . strtoupper(portalSetting($savedPortalSettings, 'smtp_encryption', 'tls')) . ')'); ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-500 font-medium">Active Email Account</span>
                                        <span class="font-mono font-bold text-purple-800"><?php echo esc(portalSetting($savedPortalSettings, 'smtp_user', 'Not configured')); ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-500 font-medium">Authentication Status</span>
                                        <span class="px-2 py-0.5 rounded-full <?php echo portalSetting($savedPortalSettings, 'smtp_user') ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'; ?> font-bold"><?php echo portalSetting($savedPortalSettings, 'smtp_user') ? 'Configured' : 'Environment fallback'; ?></span>
                                    </div>
                                </div>

                                <form method="post" class="grid grid-cols-2 gap-2 pt-2 text-xs">
                                    <input type="hidden" name="action" value="update_smtp_settings">
                                    <input name="smtp_host" value="<?php echo esc(portalSetting($savedPortalSettings, 'smtp_host')); ?>" placeholder="SMTP host" class="rounded border p-2">
                                    <input type="number" name="smtp_port" value="<?php echo esc(portalSetting($savedPortalSettings, 'smtp_port', '587')); ?>" placeholder="Port" class="rounded border p-2">
                                    <input type="email" name="smtp_user" value="<?php echo esc(portalSetting($savedPortalSettings, 'smtp_user')); ?>" placeholder="SMTP account" class="rounded border p-2">
                                    <select name="smtp_encryption" class="rounded border p-2"><option value="tls">TLS</option><option value="ssl">SSL</option></select>
                                    <label class="col-span-2 flex items-center gap-2"><input type="checkbox" name="two_factor_enabled" <?php echo portalSetting($savedPortalSettings, 'two_factor_enabled', '1') === '1' ? 'checked' : ''; ?>> Require admin 2FA</label>
                                    <button class="col-span-2 rounded bg-slate-700 py-2 font-bold text-white">Save Mail &amp; 2FA Settings</button>
                                </form>

                                <form method="post" class="pt-2 space-y-3 text-xs">
                                    <input type="hidden" name="action" value="send_test_email">
                                    <label class="block font-bold text-gray-700">Dispatch Test Notification Email
                                        <div class="mt-1 flex gap-2">
                                            <input required type="email" name="test_email" value="<?php echo esc($rhuProfile['email']); ?>" class="flex-1 p-2 rounded border border-gray-300">
                                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded font-bold hover:bg-blue-700 whitespace-nowrap">Send Test Email</button>
                                        </div>
                                    </label>
                                </form>
                            </div>

                            <!-- 3. DATABASE MAINTENANCE & OPERATIONS -->
                            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-4">
                                <div class="flex items-center gap-2 border-b border-gray-100 pb-3">
                                    <span class="p-2 bg-emerald-100 rounded-lg text-emerald-700"><?php echo iconSvg('database', 'w-5 h-5'); ?></span>
                                    <h3 class="font-bold text-gray-900 text-base">Database Health & Maintenance</h3>
                                </div>
                                <div class="grid grid-cols-2 gap-3 text-xs">
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                        <p class="text-gray-400">Database Name</p>
                                        <p class="font-mono font-bold text-gray-900 mt-1"><?php echo esc($databaseHealth['name']); ?></p>
                                    </div>
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                        <p class="text-gray-400">Connection Driver</p>
                                        <p class="font-mono font-bold text-green-700 mt-1">PDO MySQL</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                        <p class="text-gray-400">PHP Version</p>
                                        <p class="font-mono font-bold text-gray-900 mt-1"><?php echo phpversion(); ?></p>
                                    </div>
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                        <p class="text-gray-400">Total System Users</p>
                                        <p class="font-mono font-bold text-purple-900 mt-1"><?php echo $totalUsersCount; ?> Accounts</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                        <p class="text-gray-400">Database Server</p>
                                        <p class="font-mono font-bold text-gray-900 mt-1"><?php echo esc($databaseHealth['version']); ?></p>
                                    </div>
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                        <p class="text-gray-400">Schema Tables</p>
                                        <p class="font-mono font-bold text-gray-900 mt-1"><?php echo (int)$databaseHealth['tables']; ?> tables</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200">
                        <h3 class="mb-3 font-bold text-gray-900">Publish RHU Event</h3>
                        <form method="post" class="grid gap-2 text-xs sm:grid-cols-3">
                            <input type="hidden" name="action" value="create_scheduled_event">
                            <input required name="title" placeholder="Event title" class="rounded border p-2">
                            <input required type="date" name="scheduled_date" class="rounded border p-2">
                            <input type="time" name="start_time" class="rounded border p-2">
                            <input required name="venue" placeholder="Venue" class="rounded border p-2">
                            <input type="number" min="1" name="capacity" placeholder="Capacity" class="rounded border p-2">
                            <input name="description" placeholder="Description" class="rounded border p-2">
                            <button class="sm:col-span-3 rounded bg-indigo-700 py-2 font-bold text-white">Publish Event to Residents</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ANNOUNCEMENTS & LANDING PAGE CONTENT TAB -->
            <?php if ($tab === 'announcements'): ?>
                <?php 
                $dbAnnouncementsList = getPortalAnnouncements($pdo);
                $dbPortalEventsList = getPortalEvents($pdo);
                ?>
                <div class="space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('bell', 'w-6 h-6 text-purple-600'); ?> Landing Page Announcements & Health Events</h2>
                            <p class="text-xs text-gray-500 mt-1">Publish health alerts, emergency notices, and upcoming health drives directly to the public Landing Page.</p>
                        </div>
                        <span class="text-xs font-bold bg-purple-100 text-purple-800 px-3 py-1 rounded-full">Live Landing Page Sync</span>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- 1. ANNOUNCEMENTS FORM & LIST -->
                        <div class="space-y-6">
                            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-4">
                                <div class="flex items-center gap-2 border-b border-gray-100 pb-3">
                                    <span class="p-2 bg-red-100 rounded-lg text-red-700"><?php echo iconSvg('bell', 'w-5 h-5'); ?></span>
                                    <h3 class="font-bold text-gray-900 text-base">Publish Health Announcement / Alert</h3>
                                </div>
                                <form method="post" class="space-y-3 text-xs">
                                    <input type="hidden" name="action" value="create_announcement">
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Announcement Title *</label>
                                        <input required type="text" name="title" placeholder="e.g. Dengue Alert: Stagnant Water Clean-Up" class="w-full p-2.5 rounded-lg border border-gray-300">
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block font-bold text-gray-700 mb-1">Category</label>
                                            <select name="category" class="w-full p-2.5 rounded-lg border border-gray-300 bg-white">
                                                <option value="Emergency Alert">Emergency Alert</option>
                                                <option value="Health Notice" selected>Health Notice</option>
                                                <option value="Vaccine Schedule">Vaccine Schedule</option>
                                                <option value="General Announcement">General Announcement</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block font-bold text-gray-700 mb-1">Badge Label</label>
                                            <input type="text" name="badge_text" value="Health Alert" class="w-full p-2.5 rounded-lg border border-gray-300">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Announcement Details / Message *</label>
                                        <textarea required name="content" rows="3" placeholder="Write full announcement description..." class="w-full p-2.5 rounded-lg border border-gray-300"></textarea>
                                    </div>
                                    <button type="submit" class="w-full py-2.5 bg-purple-700 text-white rounded-lg font-bold hover:bg-purple-800 transition-all shadow-md">
                                        Publish to Landing Page Live
                                    </button>
                                </form>
                            </div>

                            <!-- ANNOUNCEMENTS LIST TABLE -->
                            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-3">
                                <h4 class="font-bold text-gray-900 text-sm">Active Landing Page Announcements</h4>
                                <?php if (empty($dbAnnouncementsList)): ?>
                                    <p class="text-xs text-gray-400 italic py-2">No announcements stored yet. Standard default banner will display.</p>
                                <?php else: ?>
                                    <div class="space-y-2">
                                        <?php foreach ($dbAnnouncementsList as $ann): ?>
                                            <div class="p-3 rounded-xl border border-gray-100 bg-gray-50 flex items-start justify-between gap-3 text-xs">
                                                <div>
                                                    <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-bold text-[10px]"><?php echo esc($ann['badge_text'] ?: $ann['category']); ?></span>
                                                    <h5 class="font-bold text-gray-900 mt-1"><?php echo esc($ann['title']); ?></h5>
                                                    <p class="text-gray-600 mt-0.5"><?php echo esc($ann['content']); ?></p>
                                                </div>
                                                <form method="post" onsubmit="return confirm('Remove this announcement from Landing Page?')">
                                                    <input type="hidden" name="action" value="delete_announcement">
                                                    <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                                    <button type="submit" class="p-1.5 text-red-600 hover:bg-red-50 rounded" title="Delete Announcement">
                                                        <?php echo iconSvg('trash', 'w-4 h-4'); ?>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 2. HEALTH EVENTS FORM & LIST -->
                        <div class="space-y-6">
                            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-4">
                                <div class="flex items-center gap-2 border-b border-gray-100 pb-3">
                                    <span class="p-2 bg-emerald-100 rounded-lg text-emerald-700"><?php echo iconSvg('activity', 'w-5 h-5'); ?></span>
                                    <h3 class="font-bold text-gray-900 text-base">Add Landing Page Health Event & Picture</h3>
                                </div>
                                <form method="post" enctype="multipart/form-data" class="space-y-3 text-xs">
                                    <input type="hidden" name="action" value="create_event">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block font-bold text-gray-700 mb-1">Event Date *</label>
                                            <input required type="text" name="event_date" placeholder="e.g. Jul 28 or Aug 05" class="w-full p-2.5 rounded-lg border border-gray-300">
                                        </div>
                                        <div>
                                            <label class="block font-bold text-gray-700 mb-1">Badge Color</label>
                                            <select name="badge_color" class="w-full p-2.5 rounded-lg border border-gray-300 bg-white">
                                                <option value="bg-red-500">Red (Emergency / Blood Drive)</option>
                                                <option value="bg-pink-500">Pink (Women & Maternal)</option>
                                                <option value="bg-emerald-500" selected>Green (Wellness & Senior)</option>
                                                <option value="bg-blue-500">Blue (Child & Vaccine)</option>
                                                <option value="bg-teal-500">Teal (Nutrition)</option>
                                                <option value="bg-purple-500">Purple (Family Planning)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Event Title *</label>
                                        <input required type="text" name="title" placeholder="e.g. Free Senior Citizens ECG & Glucose Check" class="w-full p-2.5 rounded-lg border border-gray-300">
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Venue / Location *</label>
                                        <input required type="text" name="venue" placeholder="e.g. Halang Barangay Health Station · 8:00 AM" class="w-full p-2.5 rounded-lg border border-gray-300">
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Event Description</label>
                                        <textarea name="description" rows="2" placeholder="Brief event details or free services offered..." class="w-full p-2.5 rounded-lg border border-gray-300"></textarea>
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Event Cover Picture (File Upload or Image URL)</label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="file" name="event_image" accept="image/*" class="p-1.5 rounded border border-gray-300 text-[11px]">
                                            <input type="text" name="image_url" placeholder="Or paste Image URL..." class="p-2.5 rounded-lg border border-gray-300 text-xs">
                                        </div>
                                    </div>
                                    <button type="submit" class="w-full py-2.5 bg-emerald-700 text-white rounded-lg font-bold hover:bg-emerald-800 transition-all shadow-md">
                                        Add Event & Picture to Landing Page
                                    </button>
                                </form>
                            </div>

                            <!-- EVENTS LIST TABLE -->
                            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-3">
                                <h4 class="font-bold text-gray-900 text-sm">Active Landing Page Health Events</h4>
                                <?php if (empty($dbPortalEventsList)): ?>
                                    <p class="text-xs text-gray-400 italic py-2">No custom events stored yet. Default schedule will display.</p>
                                <?php else: ?>
                                    <div class="space-y-2">
                                        <?php foreach ($dbPortalEventsList as $evt): ?>
                                            <div class="p-3 rounded-xl border border-gray-100 bg-gray-50 flex items-start justify-between gap-3 text-xs">
                                                <div class="flex items-start gap-3">
                                                    <?php if (!empty($evt['image_url'])): ?>
                                                        <div class="h-14 w-14 rounded-lg overflow-hidden shrink-0 bg-slate-200 border border-gray-200">
                                                            <img src="<?php echo esc(portalImgUrl($evt['image_url'])); ?>" alt="<?php echo esc($evt['title']); ?>" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=100&q=80';">
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <span class="px-2 py-0.5 rounded font-mono bg-gray-200 text-gray-800 font-bold text-[10px]"><?php echo esc($evt['event_date']); ?></span>
                                                        <h5 class="font-bold text-gray-900 mt-1"><?php echo esc($evt['title']); ?></h5>
                                                        <p class="text-xs font-semibold text-emerald-700"><?php echo esc($evt['venue']); ?></p>
                                                        <p class="text-gray-500 mt-0.5"><?php echo esc($evt['description']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-1 shrink-0">
                                                    <?php if (!empty($evt['image_url'])): ?>
                                                        <form method="post" onsubmit="return confirm('Delete picture attached to this event?')">
                                                            <input type="hidden" name="action" value="delete_event_picture">
                                                            <input type="hidden" name="event_id" value="<?php echo $evt['id']; ?>">
                                                            <button type="submit" class="px-2 py-1 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded text-[10px] font-bold" title="Delete Cover Picture Only">
                                                                Remove Picture
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="post" onsubmit="return confirm('Remove this health event and its picture?')">
                                                        <input type="hidden" name="action" value="delete_event">
                                                        <input type="hidden" name="event_id" value="<?php echo $evt['id']; ?>">
                                                        <button type="submit" class="p-1.5 text-red-600 hover:bg-red-50 rounded" title="Delete Event">
                                                            <?php echo iconSvg('trash', 'w-4 h-4'); ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 3. MUNICIPAL OFFICIALS MANAGEMENT PANEL -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-6">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="p-2 bg-blue-100 rounded-lg text-blue-700"><?php echo iconSvg('building', 'w-5 h-5'); ?></span>
                                <div>
                                    <h3 class="font-bold text-gray-900 text-base">Municipal Government Officials</h3>
                                    <p class="text-xs text-gray-500">Manage LGU leaders displayed in the Municipal Leadership section of the Landing Page.</p>
                                </div>
                            </div>
                            <span class="text-xs font-bold bg-blue-50 text-blue-700 px-3 py-1 rounded-full">Stored in Portal Settings</span>
                        </div>

                        <?php 
                        $pSettings = portalSettings($pdo);
                        $adminOfficialsList = [];
                        if (!empty($pSettings['rhu_municipal_officials'])) {
                            $adminOfficialsList = json_decode($pSettings['rhu_municipal_officials'], true) ?: [];
                        }
                        ?>

                        <div class="grid md:grid-cols-12 gap-6">
                            <!-- ADD OFFICIAL FORM -->
                            <form method="post" class="md:col-span-5 space-y-3 text-xs bg-gray-50 p-4 rounded-xl border border-gray-200">
                                <input type="hidden" name="action" value="add_official">
                                <h4 class="font-bold text-gray-900 text-sm">Add New Municipal Official</h4>
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Full Name & Honorific *</label>
                                    <input required type="text" name="official_name" placeholder="e.g. Hon. Antonio Jose A. Barcelon" class="w-full p-2.5 rounded-lg border border-gray-300 bg-white">
                                </div>
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Official Position *</label>
                                    <input required type="text" name="official_position" placeholder="e.g. Municipal Mayor" class="w-full p-2.5 rounded-lg border border-gray-300 bg-white">
                                </div>
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Office / Committee Designation</label>
                                    <input type="text" name="official_office" placeholder="e.g. Office of the Municipal Mayor" class="w-full p-2.5 rounded-lg border border-gray-300 bg-white">
                                </div>
                                <button type="submit" class="w-full py-2.5 bg-blue-700 text-white rounded-lg font-bold hover:bg-blue-800 transition-all shadow-md">
                                    Add Municipal Official
                                </button>
                            </form>

                            <!-- OFFICIALS LIST DISPLAY -->
                            <div class="md:col-span-7 space-y-3">
                                <h4 class="font-bold text-gray-900 text-sm">Current Officials Displayed on Landing Page</h4>
                                <?php if (empty($adminOfficialsList)): ?>
                                    <p class="text-xs text-gray-400 italic py-2">No officials specified yet.</p>
                                <?php else: ?>
                                    <div class="grid sm:grid-cols-2 gap-3 max-h-80 overflow-y-auto pr-1">
                                        <?php foreach ($adminOfficialsList as $idx => $off): ?>
                                            <div class="p-3 rounded-xl border border-gray-200 bg-white shadow-xs flex items-center justify-between gap-2 text-xs">
                                                <div>
                                                    <h5 class="font-bold text-gray-900"><?php echo esc($off['name']); ?></h5>
                                                    <p class="text-blue-700 font-semibold text-[11px]"><?php echo esc($off['position']); ?></p>
                                                    <p class="text-gray-400 text-[10px]"><?php echo esc($off['office']); ?></p>
                                                </div>
                                                <form method="post" onsubmit="return confirm('Remove <?php echo esc($off['name']); ?> from Landing Page?')">
                                                    <input type="hidden" name="action" value="delete_official">
                                                    <input type="hidden" name="official_index" value="<?php echo $idx; ?>">
                                                    <button type="submit" class="p-1.5 text-red-600 hover:bg-red-50 rounded shrink-0" title="Remove Official">
                                                        <?php echo iconSvg('trash', 'w-4 h-4'); ?>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                    <!-- 4. EVENT PHOTO GALLERY MANAGEMENT PANEL (Stored in portal_settings, no new DB table) -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-6">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="p-2 bg-purple-100 rounded-lg text-purple-700"><?php echo iconSvg('eye', 'w-5 h-5'); ?></span>
                                <div>
                                    <h3 class="font-bold text-gray-900 text-base">RHU Event Photo Gallery</h3>
                                    <p class="text-xs text-gray-500">Upload or add photo links to the Event & Community Photo Gallery section on the Landing Page.</p>
                                </div>
                            </div>
                            <span class="text-xs font-bold bg-purple-100 text-purple-800 px-3 py-1 rounded-full">Saved in Portal Settings</span>
                        </div>

                        <?php $eventGalleryList = getPortalEventGallery($pdo); ?>

                        <div class="grid md:grid-cols-12 gap-6">
                            <!-- ADD PHOTO FORM -->
                            <form method="post" enctype="multipart/form-data" class="md:col-span-5 space-y-3 text-xs bg-gray-50 p-4 rounded-xl border border-gray-200">
                                <input type="hidden" name="action" value="add_gallery_photo">
                                <h4 class="font-bold text-gray-900 text-sm">Add Event Gallery Photo</h4>
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Photo Title *</label>
                                    <input required type="text" name="photo_title" placeholder="e.g. Free Dental Mission & Medical Checkup" class="w-full p-2.5 rounded-lg border border-gray-300 bg-white">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Category</label>
                                        <input type="text" name="photo_category" value="Community Health Drive" class="w-full p-2.5 rounded-lg border border-gray-300 bg-white">
                                    </div>
                                    <div>
                                        <label class="block font-bold text-gray-700 mb-1">Date</label>
                                        <input type="text" name="photo_date" value="<?php echo date('M Y'); ?>" class="w-full p-2.5 rounded-lg border border-gray-300 bg-white">
                                    </div>
                                </div>
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Image File Upload or Image URL *</label>
                                    <input type="file" name="gallery_file" accept="image/*" class="w-full p-1 rounded border border-gray-300 text-[11px] mb-2 bg-white">
                                    <input type="text" name="image_url" placeholder="Or paste Image URL (e.g. https://...)" class="w-full p-2.5 rounded-lg border border-gray-300 bg-white">
                                </div>
                                <button type="submit" class="w-full py-2.5 bg-purple-700 text-white rounded-lg font-bold hover:bg-purple-800 transition-all shadow-md">
                                    Add Photo to Landing Page Gallery
                                </button>
                            </form>

                            <!-- GALLERY DISPLAY GRID -->
                            <div class="md:col-span-7 space-y-3">
                                <h4 class="font-bold text-gray-900 text-sm">Current Landing Page Gallery Photos</h4>
                                <?php if (empty($eventGalleryList)): ?>
                                    <p class="text-xs text-gray-400 italic py-2">No photos in gallery yet.</p>
                                <?php else: ?>
                                    <div class="grid sm:grid-cols-2 gap-3 max-h-96 overflow-y-auto pr-1">
                                        <?php foreach ($eventGalleryList as $gIdx => $gItem): ?>
                                            <div class="p-3 rounded-xl border border-gray-200 bg-white shadow-xs space-y-2 text-xs relative group">
                                                <div class="h-28 w-full rounded-lg overflow-hidden bg-slate-100 border border-slate-100">
                                                    <img src="<?php echo esc(portalImgUrl($gItem['image_url'])); ?>" alt="<?php echo esc($gItem['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=400&q=80';">
                                                </div>
                                                <div class="flex items-start justify-between gap-2">
                                                    <div>
                                                        <span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 font-bold text-[10px]"><?php echo esc($gItem['category'] ?? 'Event Photo'); ?></span>
                                                        <h5 class="font-bold text-gray-900 mt-1 truncate max-w-[160px]"><?php echo esc($gItem['title']); ?></h5>
                                                        <p class="text-[10px] text-gray-400"><?php echo esc($gItem['date'] ?? ''); ?></p>
                                                    </div>
                                                    <form method="post" onsubmit="return confirm('Remove photo from gallery?')">
                                                        <input type="hidden" name="action" value="delete_gallery_photo">
                                                        <input type="hidden" name="photo_index" value="<?php echo $gIdx; ?>">
                                                        <button type="submit" class="p-1.5 text-red-600 hover:bg-red-50 rounded shrink-0" title="Delete Photo">
                                                            <?php echo iconSvg('trash', 'w-4 h-4'); ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- RE-DESIGNED SECURITY TAB -->
            <?php if ($tab === 'security'): ?>
                <?php renderAdminExtendedPanel($pdo, 'security'); ?>
                <div class="space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2"><?php echo iconSvg('lock', 'w-6 h-6 text-red-600'); ?> Security Command Center</h2>
                            <p class="text-xs text-gray-500 mt-1">Manage Admin Credentials, 2-Factor Authentication, Role-Based Access Control, and Audit Security Logs</p>
                        </div>
                        <span class="text-xs font-bold bg-red-100 text-red-800 px-3 py-1 rounded-full">RA 10173 Compliant</span>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- 1. CHANGE ADMIN PASSWORD FORM -->
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-4">
                            <div class="flex items-center gap-2 border-b border-gray-100 pb-3">
                                <span class="p-2 bg-purple-100 rounded-lg text-purple-700"><?php echo iconSvg('key', 'w-5 h-5'); ?></span>
                                <h3 class="font-bold text-gray-900 text-base">Change Administrator Password</h3>
                            </div>
                            <form method="post" class="space-y-4 text-xs">
                                <input type="hidden" name="action" value="change_admin_password">
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Current Password *</label>
                                    <input required type="password" name="current_password" class="w-full p-2.5 rounded-lg border border-gray-300 text-gray-900 focus:border-purple-600 focus:outline-none" placeholder="Enter current admin password">
                                </div>
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">New Password *</label>
                                    <input required type="password" name="new_password" class="w-full p-2.5 rounded-lg border border-gray-300 text-gray-900 focus:border-purple-600 focus:outline-none" placeholder="Min 8 characters">
                                </div>
                                <div>
                                    <label class="block font-bold text-gray-700 mb-1">Confirm New Password *</label>
                                    <input required type="password" name="confirm_password" class="w-full p-2.5 rounded-lg border border-gray-300 text-gray-900 focus:border-purple-600 focus:outline-none" placeholder="Re-type new password">
                                </div>
                                <button type="submit" class="w-full py-2.5 bg-red-600 text-white rounded-lg font-bold hover:bg-red-700 transition-all shadow-md">
                                    Update Admin Password in Database
                                </button>
                            </form>
                        </div>

                        <!-- 2. 2FA ENFORCEMENT & SECURITY STATUS -->
                        <div class="space-y-6">
                            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-4">
                                <div class="flex items-center gap-2 border-b border-gray-100 pb-3">
                                    <span class="p-2 bg-emerald-100 rounded-lg text-emerald-700"><?php echo iconSvg('shield', 'w-5 h-5'); ?></span>
                                    <h3 class="font-bold text-gray-900 text-base">2-Factor Authentication (2FA) Status</h3>
                                </div>
                                <div class="space-y-3 text-xs">
                                    <div class="p-4 bg-purple-50 rounded-xl border border-purple-200">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-purple-900">2FA Enforcement</span>
                                            <span class="px-2.5 py-1 bg-purple-700 text-white font-bold rounded-full text-[10px]"><?php echo portalSetting($savedPortalSettings, 'two_factor_enabled', '1') === '1' ? 'ACTIVE' : 'DISABLED'; ?></span>
                                        </div>
                                        <p class="text-purple-800 mt-2">All login attempts require a unique 6-digit OTP code sent directly to your email address.</p>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-500 font-medium">Registered 2FA Email</span>
                                        <span class="font-mono font-bold text-gray-900"><?php echo esc($_SESSION['user']['email'] ?? 'Not available'); ?></span>
                                    </div>
                                    <div class="flex justify-between py-2 border-b border-gray-100">
                                        <span class="text-gray-500 font-medium">Code Validity Window</span>
                                        <span class="font-mono font-bold text-gray-900">10 Minutes</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. ROLE-BASED ACCESS CONTROL (RBAC) MATRIX -->
                    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-200 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="p-2 bg-purple-100 rounded-lg text-purple-700"><?php echo iconSvg('users', 'w-5 h-5'); ?></span>
                                <h3 class="font-bold text-gray-900 text-base">Role-Based Module Permissions Matrix</h3>
                            </div>
                            <span class="text-xs bg-gray-100 text-gray-700 px-3 py-1 rounded-full font-semibold">6 System Roles</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-gray-50 text-gray-500 uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Role Name</th>
                                        <th class="px-3 py-2 text-left">Access Level</th>
                                        <th class="px-3 py-2 text-left">Accessible Modules</th>
                                        <th class="px-3 py-2 text-left">Active Users in DB</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-3 font-bold text-purple-900">RHU_ADMIN</td>
                                        <td class="px-3 py-3"><span class="px-2 py-0.5 bg-purple-100 text-purple-800 font-bold rounded-full">Full System Control</span></td>
                                        <td class="px-3 py-3 text-gray-700">All Modules, User Management, Staff Accounts, Audit Logs, Settings</td>
                                        <td class="px-3 py-3 font-mono font-bold text-gray-900">1 Account</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-3 font-bold text-blue-900">PHYSICIAN</td>
                                        <td class="px-3 py-3"><span class="px-2 py-0.5 bg-blue-100 text-blue-800 font-bold rounded-full">Clinical Lead</span></td>
                                        <td class="px-3 py-3 text-gray-700">OPD Consultations, ICD-10 Prescriptions, Patient Medical Records</td>
                                        <td class="px-3 py-3 font-mono font-bold text-gray-900">Plantilla Doctors</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-3 font-bold text-emerald-900">NURSE / MIDWIFE</td>
                                        <td class="px-3 py-3"><span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 font-bold rounded-full">Healthcare Operations</span></td>
                                        <td class="px-3 py-3 text-gray-700">Maternal Health, Immunization (EPI), Triage, Vital Signs</td>
                                        <td class="px-3 py-3 font-mono font-bold text-gray-900">Clinical Staff</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-3 font-bold text-teal-900">BHW</td>
                                        <td class="px-3 py-3"><span class="px-2 py-0.5 bg-teal-100 text-teal-800 font-bold rounded-full">Community Health</span></td>
                                        <td class="px-3 py-3 text-gray-700">Barangay Resident Registry, Health Event Verification</td>
                                        <td class="px-3 py-3 font-mono font-bold text-gray-900"><?php echo $totalBhwCount; ?> BHWs</td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-3 font-bold text-gray-900">RESIDENT</td>
                                        <td class="px-3 py-3"><span class="px-2 py-0.5 bg-gray-100 text-gray-800 font-bold rounded-full">Self-Service Portal</span></td>
                                        <td class="px-3 py-3 text-gray-700">Request Medical Certificates, Send Inquiries, Event Registration</td>
                                        <td class="px-3 py-3 font-mono font-bold text-gray-900"><?php echo $totalResidentsCount; ?> Residents</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
<button type="button" class="scroll-top-button fixed bottom-20 right-4 z-30 flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 shadow-xl sm:bottom-6 sm:right-6" aria-label="Back to top" title="Back to top">
    <span aria-hidden="true">↑</span>
</button>
<script>
document.querySelectorAll('form[method="post"], form[method="POST"]').forEach((form) => {
    if (form.querySelector('input[name="csrf_token"]')) return;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'csrf_token';
    input.value = <?php echo json_encode(portalCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    form.prepend(input);
});

const featureDrawer = document.querySelector('[data-feature-drawer]');
const drawerBackdrop = document.querySelector('[data-drawer-backdrop]');
const drawerOpenButton = document.querySelector('[data-drawer-open]');
const drawerCloseButton = document.querySelector('[data-drawer-close]');
let drawerReturnFocus = null;
const setDrawerOpen = (open) => {
    featureDrawer?.classList.toggle('is-open', open);
    drawerBackdrop?.classList.toggle('is-open', open);
    document.body.classList.toggle('drawer-open', open);
    featureDrawer?.setAttribute('aria-hidden', String(!open));
    drawerBackdrop?.setAttribute('aria-hidden', String(!open));
    drawerOpenButton?.setAttribute('aria-expanded', String(open));
    if (open) {
        drawerReturnFocus = document.activeElement;
        window.setTimeout(() => drawerCloseButton?.focus(), 120);
    } else if (drawerReturnFocus instanceof HTMLElement) {
        drawerReturnFocus.focus();
    }
};
drawerOpenButton?.addEventListener('click', () => setDrawerOpen(true));
drawerCloseButton?.addEventListener('click', () => setDrawerOpen(false));
drawerBackdrop?.addEventListener('click', () => setDrawerOpen(false));
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && featureDrawer?.classList.contains('is-open')) setDrawerOpen(false);
});

document.querySelectorAll('table').forEach((table, tableIndex) => {
    const body = table.tBodies[0];
    if (!body || body.rows.length < 5) return;
    const rows = Array.from(body.rows);
    const pageSize = 15;
    let page = 1;
    let query = '';
    const toolbar = document.createElement('div');
    toolbar.className = 'flex items-center justify-between gap-2 border-b bg-gray-50 p-2 text-xs';
    toolbar.innerHTML = `<input aria-label="Search table" class="w-full max-w-xs rounded border p-2" placeholder="Search these records…"><span class="whitespace-nowrap"></span><div class="flex gap-1"><button type="button" class="rounded border bg-white px-2 py-1">Previous</button><button type="button" class="rounded border bg-white px-2 py-1">Next</button></div>`;
    table.parentElement.insertBefore(toolbar, table);
    const input = toolbar.querySelector('input');
    const status = toolbar.querySelector('span');
    const [previous, next] = toolbar.querySelectorAll('button');
    const render = () => {
        const matching = rows.filter(row => row.textContent.toLowerCase().includes(query));
        const pages = Math.max(1, Math.ceil(matching.length / pageSize));
        page = Math.min(page, pages);
        rows.forEach(row => row.hidden = true);
        matching.slice((page - 1) * pageSize, page * pageSize).forEach(row => row.hidden = false);
        status.textContent = `${matching.length} records · Page ${page}/${pages}`;
        previous.disabled = page <= 1;
        next.disabled = page >= pages;
    };
    input.addEventListener('input', () => { query = input.value.trim().toLowerCase(); page = 1; render(); });
    previous.addEventListener('click', () => { page--; render(); });
    next.addEventListener('click', () => { page++; render(); });
    render();
});

const dashboardHeader = document.querySelector('.dashboard-header');
const scrollTopButton = document.querySelector('.scroll-top-button');
const updateScrollEffects = () => {
    const scrolled = window.scrollY > 18;
    dashboardHeader?.classList.toggle('is-scrolled', scrolled);
    scrollTopButton?.classList.toggle('is-visible', window.scrollY > 420);
};
window.addEventListener('scroll', updateScrollEffects, { passive: true });
updateScrollEffects();
scrollTopButton?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

const revealTargets = new Set([
    ...document.querySelectorAll('main > div > div, main > div > section, main .grid > .bg-white, main details')
]);
revealTargets.forEach((element, index) => {
    element.dataset.scrollReveal = '';
    element.style.setProperty('--reveal-delay', `${Math.min(index % 5, 4) * 55}ms`);
});

if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -36px 0px' });
    revealTargets.forEach(element => revealObserver.observe(element));
} else {
    revealTargets.forEach(element => element.classList.add('is-visible'));
}

document.querySelectorAll('main .text-2xl.font-black').forEach((value, index) => {
    value.classList.add('metric-value-pop');
    value.style.animationDelay = `${Math.min(index, 8) * 45}ms`;
});
</script>
</body>

</html>
