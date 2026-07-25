<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/portal.php';
require_once __DIR__ . '/mailer.php';
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
        $subject = 'RedPulse RHU - System SMTP Integration Test';
        $html = "<h3>RedPulse RHU System Test</h3><p>This is a test 2FA email sent from RHU System Settings.</p><div class='code-box'>{$testCode}</div>";
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
            $certNo = 'CERT-' . date('Y') . '-' . str_pad((string)$certId, 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("UPDATE health_certificates SET validity_status = 'Approved & Issued', certificate_number = :cert_no, issue_date = CURDATE(), rejection_reason = NULL WHERE id = :id");
            $stmt->execute(['cert_no' => $certNo, 'id' => $certId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Approved Certificate Request #{$certId} (Cert No: {$certNo})", 'health_certificates', $certId);
            $flashSuccess = "Certificate request #{$certId} approved and issued successfully!";
        }
    }

    // Action: Reject Certificate Request
    if ($action === 'reject_certificate') {
        $certId = (int)($_POST['request_id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? 'Requirements incomplete');
        if ($certId > 0) {
            $stmt = $pdo->prepare("UPDATE health_certificates SET validity_status = 'Rejected', rejection_reason = :reason WHERE id = :id");
            $stmt->execute(['reason' => $reason, 'id' => $certId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Rejected Certificate Request #{$certId}", 'health_certificates', $certId);
            $flashSuccess = "Certificate request #{$certId} rejected.";
        }
    }

    // Action: Reply to Resident Message
    if ($action === 'reply_message') {
        $msgId = (int)($_POST['message_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        if ($msgId > 0 && $reply !== '') {
            $stmt = $pdo->prepare("UPDATE messages SET admin_reply = :reply, status = 'Replied', replied_at = NOW() WHERE id = :id");
            $stmt->execute(['reply' => $reply, 'id' => $msgId]);
            portalAudit($pdo, $_SESSION['user']['user_id'] ?? null, "Replied to Resident Message #{$msgId}", 'messages', $msgId);
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
        $stmt = $pdo->prepare('UPDATE consultations SET diagnosis = :diagnosis, treatment_plan = :treatment_plan, follow_up_date = :follow_up_date WHERE id = :id');
        $stmt->execute([
            'diagnosis' => trim($_POST['diagnosis'] ?? ''),
            'treatment_plan' => trim($_POST['treatment_plan'] ?? ''),
            'follow_up_date' => ($_POST['follow_up_date'] ?? '') ?: null,
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

    if ($action === 'create_event') {
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
                SELECT c.id, c.resident_id, r.first_name, r.last_name, r.barangay, c.chief_complaint, c.diagnosis, c.physician_id, c.consultation_date
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
    <title>RHU Admin Dashboard - RedPulse RHU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gray-50 text-gray-900">
    <div class="min-h-screen flex flex-col">
        <!-- HEADER -->
        <header class="bg-gradient-to-r from-slate-800 to-purple-900 text-white shadow-xl sticky top-0 z-40">
            <div class="px-4 sm:px-6 py-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
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
                        <a href="RHUAdminDashboard.php?logout=1" class="p-2 rounded-lg hover:bg-white/10" aria-label="Sign out">
                            <?php echo iconSvg('logout', 'w-4 h-4'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- DESKTOP TABS -->
            <div class="hidden sm:flex px-4 gap-1 overflow-x-auto pb-0.5">
                <?php foreach ($tabLabelMap as $key => $label): ?>
                    <?php $active = $tab === $key;
                    $icon = $tabIconMap[$key]; ?>
                    <a href="<?php echo esc(tabUrl($key)); ?>" class="flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-t-lg transition-all whitespace-nowrap flex-shrink-0 <?php echo $active ? 'bg-white text-purple-800' : 'text-purple-200 hover:bg-white/10'; ?>">
                        <?php echo iconSvg($icon, 'w-3.5 h-3.5'); ?>
                        <?php echo esc($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </header>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 max-w-7xl mx-auto w-full px-3 sm:px-4 py-4 sm:py-6 space-y-4 sm:space-y-5 pb-28 sm:pb-6">
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
                                                        <span class="text-[11px] text-gray-400 font-mono"><?php echo esc($cert['certificate_number'] ?? 'Processed'); ?></span>
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
                                            <td class="px-3 py-2.5"><form method="post" class="flex min-w-[420px] gap-1"><input type="hidden" name="action" value="update_consultation"><input type="hidden" name="consultation_id" value="<?php echo (int)$csl['id']; ?>"><input name="diagnosis" value="<?php echo esc($csl['diagnosis'] ?? ''); ?>" placeholder="Diagnosis" class="w-28 rounded border p-1"><input name="treatment_plan" placeholder="Treatment plan" class="w-32 rounded border p-1"><input type="date" name="follow_up_date" class="rounded border p-1"><button class="rounded bg-blue-600 px-2 text-white">Save</button></form></td>
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
                            <input type="hidden" name="action" value="create_event">
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

            <!-- RE-DESIGNED SECURITY TAB -->
            <?php if ($tab === 'security'): ?>
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
<script>
document.querySelectorAll('form[method="post"], form[method="POST"]').forEach((form) => {
    if (form.querySelector('input[name="csrf_token"]')) return;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'csrf_token';
    input.value = <?php echo json_encode(portalCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    form.prepend(input);
});
</script>
</body>

</html>
