<?php
require_once __DIR__ . '/db.php';

if (!function_exists('e')) {
    function e(mixed $value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('resolveImageUrl')) {
    function resolveImageUrl(?string $url): string {
        if (!$url) return '';
        if (preg_match('/^https?:\/\//i', $url) || str_starts_with($url, 'data:')) return $url;

        $clean = ltrim($url, '/');

        if (file_exists(__DIR__ . '/' . $clean)) {
            return $clean;
        }
        if (file_exists(__DIR__ . '/../../' . $clean)) {
            return '../../' . $clean;
        }
        if (file_exists(__DIR__ . '/../../../' . $clean)) {
            return '../../../' . $clean;
        }
        if (file_exists(__DIR__ . '/../' . $clean)) {
            return '../' . $clean;
        }

        return '../../' . $clean;
    }
}

if (!function_exists('portalSaveSettings')) {
    function portalSaveSettings(?PDO $pdo, array $settings): void {
        if (!$pdo) throw new RuntimeException('Database connection is unavailable.');
        ensurePortalTables($pdo);
        $statement = $pdo->prepare(
            'INSERT INTO portal_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($settings as $key => $value) {
            $statement->execute(['setting_key' => $key, 'setting_value' => trim((string)$value)]);
        }
    }

    function portalCsrfToken(): string {
        if (empty($_SESSION['portal_csrf_token'])) {
            $_SESSION['portal_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['portal_csrf_token'];
    }

    function portalVerifyCsrf(): bool {
        $submitted = (string)($_POST['csrf_token'] ?? '');
        $stored = (string)($_SESSION['portal_csrf_token'] ?? '');
        return $submitted !== '' && $stored !== '' && hash_equals($stored, $submitted);
    }

    function portalRequireAdmin(): void {
        if (empty($_SESSION['rhu_admin_authenticated']) || empty($_SESSION['user']['user_id'])) {
            header('Location: RHUAdminLogin.php');
            exit;
        }
    }

    function portalNotify(?PDO $pdo, string $message, ?int $userId = null, ?string $role = null, ?string $link = null): void {
        if (!$pdo) return;
        try {
            $statement = $pdo->prepare(
                'INSERT INTO portal_notifications (user_id, audience_role, message, link_url)
                 VALUES (:user_id, :audience_role, :message, :link_url)'
            );
            $statement->execute([
                'user_id' => $userId,
                'audience_role' => $role,
                'message' => $message,
                'link_url' => $link,
            ]);
        } catch (PDOException $e) {
            error_log('portalNotify: ' . $e->getMessage());
        }
    }

    function portalNotifyResident(?PDO $pdo, int $residentId, string $message, ?string $link = null): void {
        if (!$pdo) return;
        try {
            $userId = null;
            try {
                $statement1 = $pdo->prepare('SELECT user_id FROM residents WHERE id = :resident_id LIMIT 1');
                $statement1->execute(['resident_id' => $residentId]);
                $rUid = $statement1->fetchColumn();
                if ($rUid) $userId = (int)$rUid;
            } catch (Throwable $t) {}

            if (!$userId) {
                $statement2 = $pdo->prepare('SELECT u.id FROM residents r JOIN users u ON u.email = r.email WHERE r.id = :resident_id LIMIT 1');
                $statement2->execute(['resident_id' => $residentId]);
                $uId = $statement2->fetchColumn();
                if ($uId) $userId = (int)$uId;
            }

            portalNotify($pdo, $message, $userId ?: null, $userId ? null : 'RESIDENT', $link);
        } catch (PDOException $e) {
            error_log('portalNotifyResident: ' . $e->getMessage());
        }
    }

    function portalEnsureCertificateTypes(?PDO $pdo, array $typeNames): array {
        if (!$pdo || !$typeNames) return [];
        $insert = $pdo->prepare('INSERT IGNORE INTO certificate_types (certificate_type_name, description, requirements, fee) VALUES (:name, :description, :requirements, 0)');
        $cleanNames = [];
        foreach ($typeNames as $name) {
            $name = trim((string)$name);
            if ($name === '') continue;
            $cleanNames[] = $name;
            $insert->execute(['name' => $name, 'description' => 'Issued by an authorized RHU healthcare professional.', 'requirements' => 'Verified resident record']);
        }
        if (!$cleanNames) return [];
        $placeholders = implode(',', array_fill(0, count($cleanNames), '?'));
        $select = $pdo->prepare("SELECT id, certificate_type_name FROM certificate_types WHERE certificate_type_name IN ({$placeholders}) ORDER BY certificate_type_name");
        $select->execute($cleanNames);
        return $select->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    function portalIssueResidentCertificate(?PDO $pdo, array $input, int $staffId, string $issuerRole): array {
        if (!$pdo) throw new RuntimeException('Database connection is unavailable.');
        $residentId = (int)($input['resident_id'] ?? 0);
        $certificateTypeId = (int)($input['certificate_type_id'] ?? 0);
        $purpose = trim((string)($input['purpose'] ?? ''));
        $issueDate = trim((string)($input['issue_date'] ?? date('Y-m-d')));
        $expiryDate = trim((string)($input['expiry_date'] ?? ''));
        if ($residentId <= 0 || $certificateTypeId <= 0 || $purpose === '') {
            throw new InvalidArgumentException('Resident, certificate type, and purpose are required.');
        }
        $residentStmt = $pdo->prepare('SELECT id FROM residents WHERE id = :id AND is_active = 1 LIMIT 1');
        $residentStmt->execute(['id' => $residentId]);
        if (!$residentStmt->fetchColumn()) throw new RuntimeException('The selected active resident was not found.');
        $typeStmt = $pdo->prepare('SELECT certificate_type_name FROM certificate_types WHERE id = :id LIMIT 1');
        $typeStmt->execute(['id' => $certificateTypeId]);
        $typeName = $typeStmt->fetchColumn();
        if (!$typeName) throw new RuntimeException('The selected certificate type was not found.');
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $issuerRole), 0, 3)) ?: 'RHU';
        $certificateNumber = $prefix . '-' . date('Ymd-His') . '-' . str_pad((string)$residentId, 4, '0', STR_PAD_LEFT) . '-' . random_int(10, 99);
        $stmt = $pdo->prepare("INSERT INTO health_certificates
            (resident_id, certificate_type_id, certificate_number, issue_date, expiry_date, issued_by_id, purpose, validity_status, created_at)
            VALUES (:resident, :type, :number, :issue_date, :expiry_date, :issuer, :purpose, 'Valid', NOW())");
        $stmt->execute([
            'resident' => $residentId, 'type' => $certificateTypeId, 'number' => $certificateNumber,
            'issue_date' => $issueDate, 'expiry_date' => $expiryDate !== '' ? $expiryDate : null,
            'issuer' => $staffId > 0 ? $staffId : null, 'purpose' => $purpose
        ]);
        $certificateId = (int)$pdo->lastInsertId();
        portalNotifyResident($pdo, $residentId, "{$typeName} {$certificateNumber} was issued by {$issuerRole} and is ready to view or print.", 'ResidentDashboard.php?tab=certificates');
        portalAudit($pdo, (int)($_SESSION['rhu_staff_login']['user_id'] ?? $_SESSION['rhu_staff_login']['id'] ?? $_SESSION['user']['user_id'] ?? $_SESSION['user']['id'] ?? 0), "Issued {$typeName}", 'health_certificates', $certificateId);
        return ['id' => $certificateId, 'number' => $certificateNumber, 'type' => $typeName];
    }

    function portalCertificateWorkflowLog(PDO $pdo, int $certificateId, string $action, string $notes = '', ?int $staffId = null): void {
        try {
            $stmt = $pdo->prepare("INSERT INTO certificate_workflow_logs (certificate_id, actor_user_id, actor_staff_id, action, notes) VALUES (:certificate, :user, :staff, :action, :notes)");
            $stmt->execute([
                'certificate' => $certificateId,
                'user' => (int)($_SESSION['user']['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['rhu_staff_login']['user_id'] ?? 0) ?: null,
                'staff' => $staffId ?: (int)($_SESSION['rhu_staff_login']['staff_id'] ?? 0) ?: null,
                'action' => $action,
                'notes' => $notes,
            ]);
        } catch (Throwable $ignored) {}
    }

    function portalCertificateUrl(array $params): string {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = $_SERVER['SCRIPT_NAME'] ?? '/RHU/rhu_web/src/app/components/RHUAdminDashboard.php';
        return $scheme . '://' . $host . $path . '?' . http_build_query($params);
    }

    function portalPublicAssetUrl(string $path): string {
        $path = trim($path);
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
            return $path;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/RHU/rhu_web/src/app/components/RHUAdminDashboard.php';
        $base = preg_replace('#/src/app/components/[^/]+$#', '/', $script) ?: '/';
        return $scheme . '://' . $host . rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    function portalSelectCertificateDoctor(PDO $pdo, int $certificateTypeId, string $purpose, string $processingDate, int $preferredStaffId = 0): ?array {
        $purposeLower = strtolower($purpose);
        $stmt = $pdo->prepare("
            SELECT s.id AS staff_id, s.staff_type, s.specialization, s.work_days, s.shift_start, s.shift_end, s.is_on_duty,
                   u.id AS user_id, u.email, CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS name
            FROM certificate_doctor_assignments cda
            JOIN staff s ON s.id = cda.staff_id
            JOIN users u ON u.id = s.user_id
            WHERE cda.certificate_type_id = :type
              AND cda.is_active = 1
              AND s.is_active = 1
              AND (:preferred_zero = 0 OR s.id = :preferred_staff)
            ORDER BY CASE WHEN cda.purpose_keyword <> '' THEN 0 ELSE 1 END, cda.id ASC
        ");
        $stmt->execute(['type' => $certificateTypeId, 'preferred_zero' => $preferredStaffId, 'preferred_staff' => $preferredStaffId]);
        $candidates = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $keyword = strtolower(trim((string)($row['purpose_keyword'] ?? '')));
            if ($keyword !== '' && !str_contains($purposeLower, $keyword)) continue;
            $candidates[] = $row;
        }
        if (!$candidates) return null;
        $dayName = date('l', strtotime($processingDate ?: date('Y-m-d')));
        foreach ($candidates as $candidate) {
            $days = (string)($candidate['work_days'] ?? '');
            $onDuty = (int)($candidate['is_on_duty'] ?? 1) === 1;
            if ($onDuty && ($days === '' || stripos($days, $dayName) !== false)) return $candidate;
        }
        return $candidates[0];
    }

    function portalGenerateCertificateHtml(PDO $pdo, int $certificateId, bool $absoluteImages = false): string {
        $stmt = $pdo->prepare("
            SELECT hc.*, ct.certificate_type_name,
                   CONCAT(r.first_name, ' ', r.last_name) AS resident_name, r.address, r.barangay, r.date_of_birth, r.gender, r.email,
                   CONCAT(doc_u.first_name, ' ', doc_u.last_name) AS doctor_name, doc_s.staff_type AS doctor_position, doc_s.e_signature_path AS doctor_signature,
                   CONCAT(admin_u.first_name, ' ', admin_u.last_name) AS admin_name, admin_u.e_signature_path AS admin_signature
            FROM health_certificates hc
            JOIN certificate_types ct ON ct.id = hc.certificate_type_id
            JOIN residents r ON r.id = hc.resident_id
            LEFT JOIN staff doc_s ON doc_s.id = hc.assigned_doctor_id
            LEFT JOIN users doc_u ON doc_u.id = doc_s.user_id
            LEFT JOIN users admin_u ON admin_u.id = hc.admin_approver_user_id
            WHERE hc.id = :id
        ");
        $stmt->execute(['id' => $certificateId]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cert) throw new RuntimeException('Certificate record not found.');
        $certificateNo = $cert['certificate_number'] ?: ('RHU-' . str_pad((string)$certificateId, 8, '0', STR_PAD_LEFT));
        $issueDate = $cert['issue_date'] ? date('F j, Y', strtotime($cert['issue_date'])) : date('F j, Y');
        $expiryDate = $cert['expiry_date'] ? date('F j, Y', strtotime($cert['expiry_date'])) : 'No fixed expiration';
        $adminName = trim((string)($cert['admin_name'] ?? '')) ?: 'RHU Administrator';
        $doctorName = trim((string)($cert['doctor_name'] ?? '')) ?: 'Authorized RHU Physician';
        $doctorPosition = trim((string)($cert['doctor_position'] ?? '')) ?: 'Authorized Staff';
        $doctorSignatureUrl = $absoluteImages ? portalPublicAssetUrl((string)$cert['doctor_signature']) : portalImgUrl((string)$cert['doctor_signature']);
        $adminSignatureUrl = $absoluteImages ? portalPublicAssetUrl((string)$cert['admin_signature']) : portalImgUrl((string)$cert['admin_signature']);
        $sealUrl = $absoluteImages ? portalPublicAssetUrl('src/app/components/nasugbu_seal.png') : 'nasugbu_seal.png';
        $signatureStyle = 'display:block;width:170px;height:58px;margin:0 auto -4px;object-fit:contain;object-position:center bottom;mix-blend-mode:multiply';
        $doctorSignature = !empty($cert['doctor_signature_approved_at']) && !empty($cert['doctor_signature'])
            ? '<img class="certificate-signature-image" style="' . $signatureStyle . '" src="' . htmlspecialchars($doctorSignatureUrl, ENT_QUOTES, 'UTF-8') . '" alt="Approved doctor e-signature">'
            : '<div class="signature-line"></div>';
        $adminSignature = !empty($cert['admin_signature_approved_at']) && !empty($cert['admin_signature'])
            ? '<img class="certificate-signature-image" style="' . $signatureStyle . '" src="' . htmlspecialchars($adminSignatureUrl, ENT_QUOTES, 'UTF-8') . '" alt="Approved administrator e-signature">'
            : '<div class="signature-line"></div>';
        $residentAddress = trim(($cert['address'] ?? '') . ', ' . ($cert['barangay'] ?? ''), ' ,');
        $certificateType = strtoupper((string)$cert['certificate_type_name']);
        $verificationRef = 'Ref: ' . substr(hash('sha256', $certificateNo . '|' . $certificateId), 0, 18);
        return '<section class="official-certificate-template">'
            . '<img class="cert-watermark" src="' . htmlspecialchars($sealUrl, ENT_QUOTES, 'UTF-8') . '" alt="">'
            . '<div class="cert-header"><img class="cert-seal" src="' . htmlspecialchars($sealUrl, ENT_QUOTES, 'UTF-8') . '" alt="Municipality of Nasugbu official seal"><div class="cert-header-copy">'
            . '<p class="cert-republic">Republic of the Philippines</p><p>CALABARZON Region</p><p>Province of Batangas</p>'
            . '<h1>Municipality of Nasugbu</h1><h2>Nasugbu Rural Health Unit I</h2></div></div>'
            . '<div class="cert-rule"></div>'
            . '<h3>' . htmlspecialchars($certificateType, ENT_QUOTES, 'UTF-8') . '</h3>'
            . '<p class="cert-no">Certificate No. ' . htmlspecialchars($certificateNo, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<div class="cert-body"><p class="cert-greeting"><strong>TO WHOM IT MAY CONCERN:</strong></p>'
            . '<p>This is to certify that <strong>' . htmlspecialchars($cert['resident_name'], ENT_QUOTES, 'UTF-8') . '</strong>, a resident of '
            . htmlspecialchars($residentAddress ?: 'Nasugbu, Batangas', ENT_QUOTES, 'UTF-8') . ', has been duly examined and/or verified by this office and is hereby issued this '
            . htmlspecialchars(strtolower((string)$cert['certificate_type_name']), ENT_QUOTES, 'UTF-8') . '.</p>'
            . '<p>This is to certify further that the above-named person has satisfied the applicable requirements of the Nasugbu Rural Health Unit I for <strong>'
            . htmlspecialchars($cert['purpose'] ?: 'official health certification', ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>Issued this <strong>' . htmlspecialchars(strtoupper($issueDate), ENT_QUOTES, 'UTF-8') . '</strong> at the Municipality of Nasugbu, Province of Batangas, Philippines, upon request of the interested party for whatever lawful purpose this certificate may serve.</p>'
            . '<div class="cert-dates"><span>Issue date: <strong>' . htmlspecialchars($issueDate, ENT_QUOTES, 'UTF-8') . '</strong></span><span>Valid until: <strong>' . htmlspecialchars($expiryDate, ENT_QUOTES, 'UTF-8') . '</strong></span></div></div>'
            . '<div class="cert-signatures"><div>' . $doctorSignature . '<strong>' . htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8') . '</strong><small>' . htmlspecialchars($doctorPosition, ENT_QUOTES, 'UTF-8') . '</small></div>'
            . '<div>' . $adminSignature . '<strong>' . htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') . '</strong><small>Authorized RHU Officer</small></div></div>'
            . '<div class="cert-footer"><span>Verification ' . htmlspecialchars($verificationRef, ENT_QUOTES, 'UTF-8') . '</span><span>Status: ' . htmlspecialchars($cert['workflow_status'] ?: $cert['validity_status'], ENT_QUOTES, 'UTF-8') . '</span></div>'
            . '</section>';
    }

    function portalRecordCertificateEmail(PDO $pdo, int $certificateId, string $email, string $type, string $subject, array $result): void {
        try {
            $stmt = $pdo->prepare("INSERT INTO certificate_email_logs (certificate_id, recipient_email, email_type, subject, delivery_status, delivery_method, error_message) VALUES (:certificate, :email, :type, :subject, :status, :method, :error)");
            $stmt->execute([
                'certificate' => $certificateId,
                'email' => $email,
                'type' => $type,
                'subject' => $subject,
                'status' => !empty($result['success']) ? 'Sent' : 'Failed',
                'method' => $result['method'] ?? null,
                'error' => $result['error'] ?? null,
            ]);
        } catch (Throwable $ignored) {}
    }

    function portalCreateCertificateApproval(PDO $pdo, int $certificateId, string $approverType, string $email, ?int $userId, ?int $staffId): string {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO certificate_signature_approvals (certificate_id, approver_type, user_id, staff_id, approver_email, token_hash, expires_at) VALUES (:certificate, :type, :user, :staff, :email, :hash, DATE_ADD(NOW(), INTERVAL 3 DAY))");
        $stmt->execute([
            'certificate' => $certificateId,
            'type' => $approverType,
            'user' => $userId,
            'staff' => $staffId,
            'email' => $email,
            'hash' => hash('sha256', $token),
        ]);
        return $token;
    }

    function portalSendCertificateApprovalRequests(PDO $pdo, int $certificateId, array $admin, array $doctor): array {
        $results = [];
        foreach ([['Administrator', $admin], ['Doctor', $doctor]] as [$type, $person]) {
            $email = trim((string)($person['email'] ?? ''));
            $token = portalCreateCertificateApproval($pdo, $certificateId, $type, $email, $person['user_id'] ?? null, $person['staff_id'] ?? null);
            if (!empty($person['signature_path'])) {
                $stmt = $pdo->prepare("UPDATE certificate_signature_approvals SET status = 'Approved', responded_at = NOW(), ip_address = :ip, user_agent = :ua WHERE token_hash = :hash");
                $stmt->execute([
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    'hash' => hash('sha256', $token),
                ]);
                portalCertificateWorkflowLog($pdo, $certificateId, "{$type} Approved", "{$type} signature was uploaded directly during certificate creation.");
                $results[] = ['type' => $type, 'email' => $email, 'success' => true, 'method' => 'direct_upload'];
                continue;
            }
            if ($email === '') {
                $results[] = ['type' => $type, 'email' => '', 'success' => false, 'method' => 'validation', 'error' => 'Missing approver email'];
                continue;
            }
            $approveUrl = portalCertificateUrl(['certificate_signature_approval' => $token, 'decision' => 'approve']);
            $rejectUrl = portalCertificateUrl(['certificate_signature_approval' => $token, 'decision' => 'reject']);
            $subject = "Certificate e-signature approval required";
            $html = '<p>A certificate requires your e-signature.</p>'
                . '<p>Click <strong>Approve</strong>, upload a clear picture of your signature, and the system will automatically place it on the certificate.</p>'
                . '<p><a style="display:inline-block;background:#047857;color:#fff;padding:10px 14px;border-radius:8px;text-decoration:none" href="' . htmlspecialchars($approveUrl, ENT_QUOTES, 'UTF-8') . '">Approve</a> '
                . '<a style="display:inline-block;background:#be123c;color:#fff;padding:10px 14px;border-radius:8px;text-decoration:none" href="' . htmlspecialchars($rejectUrl, ENT_QUOTES, 'UTF-8') . '">Reject</a></p>'
                . '<p>This secure link expires in 3 days.</p>';
            $result = function_exists('sendRHUEmail') ? sendRHUEmail($email, $subject, $html) : ['success' => false, 'method' => 'none', 'error' => 'Mailer unavailable'];
            portalRecordCertificateEmail($pdo, $certificateId, $email, strtolower($type) . '_approval_request', $subject, $result);
            $results[] = ['type' => $type, 'email' => $email, 'success' => !empty($result['success']), 'method' => $result['method'] ?? 'unknown', 'error' => $result['error'] ?? ''];
        }
        portalCertificateWorkflowLog($pdo, $certificateId, 'Approval requests sent', 'Administrator and assigned doctor/staff signature approvals were requested.');
        return $results;
    }

    function portalRefreshCertificateWorkflowStatus(PDO $pdo, int $certificateId): string {
        $rowsStmt = $pdo->prepare("SELECT approver_type, status FROM certificate_signature_approvals WHERE certificate_id = :id ORDER BY id");
        $rowsStmt->execute(['id' => $certificateId]);
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $adminApproved = false;
        $doctorApproved = false;
        foreach ($rows as $row) {
            if ($row['status'] === 'Rejected') {
                $pdo->prepare("UPDATE health_certificates SET workflow_status = 'Rejected', validity_status = 'Rejected' WHERE id = :id")->execute(['id' => $certificateId]);
                return 'Rejected';
            }
            if ($row['approver_type'] === 'Administrator' && $row['status'] === 'Approved') $adminApproved = true;
            if ($row['approver_type'] === 'Doctor' && $row['status'] === 'Approved') $doctorApproved = true;
        }
        $status = 'Pending Approval';
        if ($doctorApproved && !$adminApproved) $status = 'Pending Administrator Approval';
        if ($adminApproved && !$doctorApproved) $status = 'Pending Doctor Approval';
        if ($adminApproved && $doctorApproved) $status = 'Signed';
        $stmt = $pdo->prepare("UPDATE health_certificates SET workflow_status = :status, validity_status = :validity, admin_signature_approved_at = IF(:admin_ok = 1, COALESCE(admin_signature_approved_at, NOW()), admin_signature_approved_at), doctor_signature_approved_at = IF(:doctor_ok = 1, COALESCE(doctor_signature_approved_at, NOW()), doctor_signature_approved_at) WHERE id = :id");
        $stmt->execute(['status' => $status, 'validity' => $status === 'Signed' ? 'Approved & Issued' : $status, 'admin_ok' => $adminApproved ? 1 : 0, 'doctor_ok' => $doctorApproved ? 1 : 0, 'id' => $certificateId]);
        return $status;
    }

    function portalAutoSendSignedCertificate(PDO $pdo, int $certificateId): bool {
        $stmt = $pdo->prepare("
            SELECT hc.certificate_number, hc.sent_at, hc.resident_id, r.email, CONCAT(r.first_name, ' ', r.last_name) AS resident_name
            FROM health_certificates hc
            JOIN residents r ON r.id = hc.resident_id
            WHERE hc.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $certificateId]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cert || !empty($cert['sent_at']) || empty($cert['email'])) return false;

        $signedHtml = portalGenerateCertificateHtml($pdo, $certificateId);
        $emailCertificateHtml = portalGenerateCertificateHtml($pdo, $certificateId, true);
        $downloadUrl = portalCertificateUrl(['certificate_pdf' => $certificateId]);
        $subject = 'Your RHU certificate is ready';
        $html = '<p>Your signed RHU certificate is approved and ready. The signed certificate is shown below.</p>'
            . '<div style="margin:16px 0;padding:14px;border:1px solid #d1d5db;background:#ffffff;max-width:900px">'
            . $emailCertificateHtml
            . '</div>'
            . '<p>You can also open it from your Resident Portal certificates tab.</p>'
            . '<p><a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '">Download certificate</a></p>';
        $result = function_exists('sendRHUEmail')
            ? sendRHUEmail((string)$cert['email'], $subject, $html)
            : ['success' => false, 'method' => 'none', 'error' => 'Mailer unavailable'];
        portalRecordCertificateEmail($pdo, $certificateId, (string)$cert['email'], 'resident_signed_certificate_auto', $subject, $result);

        $pdo->prepare("UPDATE health_certificates SET generated_html = :html, workflow_status = 'Sent', validity_status = 'Approved & Issued', final_approved_at = COALESCE(final_approved_at, NOW()), sent_at = NOW() WHERE id = :id")
            ->execute(['html' => $signedHtml, 'id' => $certificateId]);
        portalNotifyResident($pdo, (int)$cert['resident_id'], 'Your signed certificate is ready to download.', 'ResidentDashboard.php?tab=certificates');

        if (!empty($result['success'])) {
            portalCertificateWorkflowLog($pdo, $certificateId, 'Certificate auto-sent', 'All required signatures were completed, so the signed certificate was automatically emailed and released.');
            return true;
        }

        portalCertificateWorkflowLog($pdo, $certificateId, 'Certificate released; email failed', 'The signed certificate was released to the resident portal, but email delivery failed: ' . (string)($result['error'] ?? 'Email delivery failed.'));
        return false;
    }

    function portalRenderCertificateSignatureApprovalPage(array $approval, string $token, string $decision, string $error = ''): never {
        $title = $decision === 'reject' ? 'Reject certificate signature request' : 'Approve certificate e-signature';
        $actionText = $decision === 'reject' ? 'Reject Request' : 'Approve & Apply Signature';
        $buttonClass = $decision === 'reject' ? '#be123c' : '#047857';
        $safeToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        $safeDecision = htmlspecialchars($decision, ENT_QUOTES, 'UTF-8');
        $safeType = htmlspecialchars((string)($approval['approver_type'] ?? 'Approver'), ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars((string)($approval['approver_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $safeError = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
            . '<style>body{margin:0;background:#eef2f7;font-family:Arial,sans-serif;color:#0f172a}.wrap{min-height:100vh;display:grid;place-items:center;padding:24px}.panel{width:min(560px,100%);background:#fff;border:1px solid #dbe3ef;border-radius:18px;box-shadow:0 20px 60px rgba(15,23,42,.12);padding:28px}.eyebrow{font-size:11px;font-weight:800;color:#047857;text-transform:uppercase;letter-spacing:.08em}h1{margin:8px 0 8px;font-size:24px}p{font-size:14px;line-height:1.55;color:#475569}.meta{border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;padding:12px;margin:16px 0}.field{margin-top:16px}.field label{display:block;font-size:12px;font-weight:800;color:#334155;margin-bottom:6px;text-transform:uppercase}.field input{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:12px;padding:12px;background:#fff}.error{border:1px solid #fecdd3;background:#fff1f2;color:#9f1239;border-radius:12px;padding:11px;font-size:13px;font-weight:700}.actions{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}.btn{border:0;border-radius:12px;padding:12px 16px;color:#fff;font-weight:900;cursor:pointer}.back{display:inline-flex;align-items:center;border:1px solid #cbd5e1;border-radius:12px;padding:11px 14px;text-decoration:none;color:#475569;font-size:13px;font-weight:800}</style></head><body><main class="wrap"><section class="panel">'
            . '<div class="eyebrow">ResiHUnity RHU Certificate Workflow</div><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<p>This secure page confirms whether RHU may use your signature on the certificate. If approving, upload a clear picture of your signature and it will be placed automatically on the certificate.</p>'
            . '<div class="meta"><p><strong>Approver:</strong> ' . $safeType . '<br><strong>Email:</strong> ' . $safeEmail . '<br><strong>Request ID:</strong> #' . (int)($approval['certificate_id'] ?? 0) . '</p></div>'
            . ($safeError !== '' ? '<div class="error">' . $safeError . '</div>' : '')
            . '<form method="post" enctype="multipart/form-data">'
            . '<input type="hidden" name="certificate_signature_approval" value="' . $safeToken . '"><input type="hidden" name="decision" value="' . $safeDecision . '">';
        if ($decision === 'approve') {
            echo '<div class="field"><label for="signature_image">Signature Picture</label><input id="signature_image" name="signature_image" type="file" accept="image/png,image/jpeg,image/webp" required></div>'
                . '<p>Tip: use a clean white background. PNG/JPG/WEBP, up to 2MB.</p>';
        }
        echo '<div class="actions"><button class="btn" style="background:' . $buttonClass . '" type="submit">' . htmlspecialchars($actionText, ENT_QUOTES, 'UTF-8') . '</button>'
            . '<a class="back" href="?certificate_signature_approval=' . $safeToken . '&decision=' . ($decision === 'reject' ? 'approve' : 'reject') . '">' . ($decision === 'reject' ? 'Switch to Approve' : 'Reject Instead') . '</a></div>'
            . '</form></section></main></body></html>';
        exit;
    }

    function portalHandleCertificateSignatureApproval(?PDO $pdo): void {
        if (!$pdo || (empty($_GET['certificate_signature_approval']) && empty($_POST['certificate_signature_approval']))) return;
        ensurePortalTables($pdo);
        $token = (string)($_GET['certificate_signature_approval'] ?? $_POST['certificate_signature_approval'] ?? '');
        $decision = strtolower((string)($_GET['decision'] ?? $_POST['decision'] ?? ''));
        if (!in_array($decision, ['approve', 'reject'], true) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            http_response_code(400);
            exit('Invalid approval link.');
        }
        $stmt = $pdo->prepare("SELECT * FROM certificate_signature_approvals WHERE token_hash = :hash LIMIT 1");
        $stmt->execute(['hash' => hash('sha256', $token)]);
        $approval = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$approval || strtotime($approval['expires_at']) < time()) {
            http_response_code(403);
            exit('This approval link is invalid or expired.');
        }
        if ($approval['status'] !== 'Pending') {
            exit('This approval request has already been processed.');
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            portalRenderCertificateSignatureApprovalPage($approval, $token, $decision);
        }
        $signaturePath = '';
        if ($decision === 'approve') {
            try {
                $signaturePath = portalCertificateSignatureUploadPath($_FILES['signature_image'] ?? []);
                portalSaveApprovalSignature($pdo, $approval, $signaturePath);
            } catch (Throwable $e) {
                portalRenderCertificateSignatureApprovalPage($approval, $token, $decision, $e->getMessage());
            }
        }
        $status = $decision === 'approve' ? 'Approved' : 'Rejected';
        $upd = $pdo->prepare("UPDATE certificate_signature_approvals SET status = :status, responded_at = NOW(), ip_address = :ip, user_agent = :ua WHERE id = :id");
        $upd->execute(['status' => $status, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null, 'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), 'id' => (int)$approval['id']]);
        $logNote = $signaturePath !== '' ? 'Signature image uploaded and approval link processed.' : 'Signature usage approval link processed.';
        portalCertificateWorkflowLog($pdo, (int)$approval['certificate_id'], "{$approval['approver_type']} {$status}", $logNote, (int)($approval['staff_id'] ?? 0) ?: null);
        $newStatus = portalRefreshCertificateWorkflowStatus($pdo, (int)$approval['certificate_id']);
        $updatedHtml = portalGenerateCertificateHtml($pdo, (int)$approval['certificate_id']);
        $pdo->prepare("UPDATE health_certificates SET generated_html = :html WHERE id = :id")
            ->execute(['html' => $updatedHtml, 'id' => (int)$approval['certificate_id']]);
        if (($approval['approver_type'] ?? '') === 'Doctor' && $signaturePath !== '') {
            try {
                $noticeStmt = $pdo->prepare("
                    SELECT hc.admin_approver_user_id, hc.certificate_number,
                           CONCAT(COALESCE(doc_u.first_name,''), ' ', COALESCE(doc_u.last_name,'')) AS doctor_name,
                           CONCAT(COALESCE(r.first_name,''), ' ', COALESCE(r.last_name,'')) AS resident_name
                    FROM health_certificates hc
                    LEFT JOIN staff doc_s ON doc_s.id = hc.assigned_doctor_id
                    LEFT JOIN users doc_u ON doc_u.id = doc_s.user_id
                    LEFT JOIN residents r ON r.id = hc.resident_id
                    WHERE hc.id = :id
                    LIMIT 1
                ");
                $noticeStmt->execute(['id' => (int)$approval['certificate_id']]);
                $notice = $noticeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $doctorNoticeName = trim((string)($notice['doctor_name'] ?? '')) ?: 'Doctor/staff';
                $residentNoticeName = trim((string)($notice['resident_name'] ?? '')) ?: 'the resident';
                $certificateNoticeNo = trim((string)($notice['certificate_number'] ?? '')) ?: ('Certificate #' . (int)$approval['certificate_id']);
                portalNotify(
                    $pdo,
                    "{$doctorNoticeName} uploaded an e-signature for {$certificateNoticeNo} ({$residentNoticeName}). Current status: {$newStatus}.",
                    !empty($notice['admin_approver_user_id']) ? (int)$notice['admin_approver_user_id'] : null,
                    empty($notice['admin_approver_user_id']) ? 'RHU_ADMIN' : null,
                    'RHUAdminDashboard.php?tab=overview'
                );
            } catch (Throwable $ignored) {}
        }
        if ($newStatus === 'Signed') {
            portalAutoSendSignedCertificate($pdo, (int)$approval['certificate_id']);
            $newStatus = 'Sent';
        } elseif (($approval['approver_type'] ?? '') === 'Doctor' && $signaturePath !== '') {
            try {
                $adminStmt = $pdo->prepare("SELECT u.email FROM health_certificates hc JOIN users u ON u.id = hc.admin_approver_user_id WHERE hc.id = :id LIMIT 1");
                $adminStmt->execute(['id' => (int)$approval['certificate_id']]);
                $adminEmail = (string)($adminStmt->fetchColumn() ?: '');
                if ($adminEmail !== '' && function_exists('sendRHUEmail')) {
                    $previewHtml = portalGenerateCertificateHtml($pdo, (int)$approval['certificate_id'], true);
                    $result = sendRHUEmail($adminEmail, 'Doctor/staff signature uploaded', '<p>The assigned doctor/staff uploaded their e-signature. Current certificate status: <strong>' . htmlspecialchars($newStatus, ENT_QUOTES, 'UTF-8') . '</strong>.</p>' . $previewHtml);
                    portalRecordCertificateEmail($pdo, (int)$approval['certificate_id'], $adminEmail, 'doctor_signature_uploaded_notice', 'Doctor/staff signature uploaded', $result);
                }
            } catch (Throwable $ignored) {}
        }
        header('Content-Type: text/html; charset=UTF-8');
        exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="margin:0;background:#eef2f7;font-family:Arial,sans-serif;color:#0f172a;display:grid;min-height:100vh;place-items:center;padding:24px"><section style="max-width:520px;background:#fff;border:1px solid #dbe3ef;border-radius:18px;padding:28px;box-shadow:0 20px 60px rgba(15,23,42,.12)"><h1 style="margin:0 0 8px;font-size:24px">Certificate signature request ' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</h1><p style="font-size:14px;line-height:1.55;color:#475569">Current certificate status: <strong>' . htmlspecialchars($newStatus, ENT_QUOTES, 'UTF-8') . '</strong>.</p><p style="font-size:13px;color:#64748b">You may close this page.</p></section></body>');
    }

    function portalRenderCertificateIssuancePanel(?PDO $pdo, array $residents, array $certificateTypes, int $staffId, string $accent = 'teal'): string {
        $allowedAccents = ['teal','emerald','violet','pink','blue'];
        if (!in_array($accent, $allowedAccents, true)) $accent = 'teal';
        $recent = [];
        if ($pdo && $staffId > 0) {
            try {
                $recentStmt = $pdo->prepare("SELECT hc.certificate_number, hc.issue_date, hc.validity_status,
                    ct.certificate_type_name, CONCAT(r.first_name, ' ', r.last_name) AS resident_name
                    FROM health_certificates hc JOIN certificate_types ct ON ct.id = hc.certificate_type_id
                    JOIN residents r ON r.id = hc.resident_id WHERE hc.issued_by_id = :staff_id
                    ORDER BY hc.id DESC LIMIT 15");
                $recentStmt->execute(['staff_id' => $staffId]);
                $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $ignored) {}
        }
        ob_start(); ?>
        <section class="space-y-5">
          <div class="rounded-2xl border border-<?= $accent ?>-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-extrabold text-gray-900">Issue Resident Certificate</h2>
            <p class="mt-1 text-xs text-gray-500">The certificate becomes immediately available in the Resident portal with the official printable design.</p>
            <form method="post" class="mt-5 grid gap-3 text-xs sm:grid-cols-2">
              <input type="hidden" name="action" value="issue_certificate">
              <label class="space-y-1"><span class="font-bold text-gray-700">Resident *</span><select required name="resident_id" class="w-full rounded-xl border border-gray-300 bg-white p-3"><option value="">Select resident</option><?php foreach ($residents as $resident): ?><option value="<?= (int)$resident['id'] ?>"><?= e($resident['name']) ?><?= !empty($resident['barangay']) ? ' — ' . e($resident['barangay']) : '' ?></option><?php endforeach; ?></select></label>
              <label class="space-y-1"><span class="font-bold text-gray-700">Certificate type *</span><select required name="certificate_type_id" class="w-full rounded-xl border border-gray-300 bg-white p-3"><option value="">Select certificate</option><?php foreach ($certificateTypes as $type): ?><option value="<?= (int)$type['id'] ?>"><?= e($type['certificate_type_name']) ?></option><?php endforeach; ?></select></label>
              <label class="space-y-1"><span class="font-bold text-gray-700">Issue date *</span><input required type="date" name="issue_date" value="<?= date('Y-m-d') ?>" class="w-full rounded-xl border border-gray-300 p-3"></label>
              <label class="space-y-1"><span class="font-bold text-gray-700">Valid until</span><input type="date" name="expiry_date" value="<?= date('Y-m-d', strtotime('+6 months')) ?>" min="<?= date('Y-m-d') ?>" class="w-full rounded-xl border border-gray-300 p-3"></label>
              <label class="space-y-1 sm:col-span-2"><span class="font-bold text-gray-700">Purpose / certification statement *</span><textarea required name="purpose" rows="3" maxlength="1000" class="w-full rounded-xl border border-gray-300 p-3" placeholder="State the verified purpose and relevant certification details"></textarea></label>
              <button class="sm:col-span-2 rounded-xl bg-<?= $accent ?>-700 p-3 font-extrabold text-white hover:bg-<?= $accent ?>-800">Issue Certificate &amp; Notify Resident</button>
            </form>
          </div>
          <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="font-extrabold text-gray-900">Recently Issued by You</h3>
            <div class="mt-3 space-y-2"><?php foreach ($recent as $certificate): ?><div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-gray-50 p-3 text-xs"><div><p class="font-bold text-gray-900"><?= e($certificate['certificate_type_name']) ?> — <?= e($certificate['resident_name']) ?></p><p class="text-gray-500"><?= e($certificate['certificate_number']) ?> · <?= e($certificate['issue_date']) ?></p></div><span class="rounded-full bg-emerald-100 px-2 py-1 font-bold text-emerald-800"><?= e($certificate['validity_status']) ?></span></div><?php endforeach; ?><?php if (!$recent): ?><p class="py-5 text-center text-xs text-gray-400">No certificates issued by this account yet.</p><?php endif; ?></div>
          </div>
        </section>
        <?php return (string)ob_get_clean();
    }

    function portalHandleNotificationApi(?PDO $pdo): void {
        if (!$pdo || empty($_GET['api'])) return;

        $api = $_GET['api'];
        if (!in_array($api, ['get_notifications', 'mark_read', 'delete_notifications'], true)) return;

        header('Content-Type: application/json');

        $uid = (int)($_SESSION['user']['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['resident_login']['id'] ?? $_SESSION['bhw_user']['id'] ?? 0);
        $role = strtoupper((string)($_SESSION['user']['role'] ?? $_SESSION['rhu_staff_login']['staff_type'] ?? ''));
        if (empty($role) && !empty($_SESSION['resident_login'])) $role = 'RESIDENT';
        if (empty($role) && !empty($_SESSION['bhw_user'])) $role = 'BHW';
        if (empty($role) && !empty($_SESSION['rhu_admin_authenticated'])) $role = 'RHU_ADMIN';

        if ($api === 'get_notifications') {
            $stmt = $pdo->prepare("
                SELECT id, message, link_url, is_read, created_at
                FROM portal_notifications
                WHERE (user_id = :uid AND user_id > 0)
                   OR audience_role = :role
                   OR audience_role = 'ALL'
                   OR (user_id IS NULL AND audience_role IS NULL)
                ORDER BY id DESC LIMIT 30
            ");
            $stmt->execute(['uid' => $uid, 'role' => $role]);
            $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $unread = 0;
            foreach ($notifs as $n) {
                if (empty($n['is_read'])) $unread++;
            }

            echo json_encode(['success' => true, 'notifications' => $notifs, 'unreadCount' => $unread]);
            exit;
        }

        if ($api === 'mark_read') {
            $notifId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($notifId === 0) {
                $stmt = $pdo->prepare("UPDATE portal_notifications SET is_read = 1 WHERE (user_id = :uid AND user_id > 0) OR audience_role = :role OR audience_role = 'ALL' OR (user_id IS NULL AND audience_role IS NULL)");
                $stmt->execute(['uid' => $uid, 'role' => $role]);
            } else {
                $stmt = $pdo->prepare("UPDATE portal_notifications SET is_read = 1 WHERE id = :id");
                $stmt->execute(['id' => $notifId]);
            }
            echo json_encode(['success' => true]);
            exit;
        }

        if ($api === 'delete_notifications') {
            $rawInput = file_get_contents('php://input');
            $inputData = json_decode($rawInput, true);

            $ids = [];
            if (!empty($_POST['ids'])) {
                $ids = is_array($_POST['ids']) ? $_POST['ids'] : explode(',', $_POST['ids']);
            } elseif (!empty($inputData['ids']) && is_array($inputData['ids'])) {
                $ids = $inputData['ids'];
            } elseif (!empty($_GET['id'])) {
                $ids = [(int)$_GET['id']];
            }

            $cleanIds = array_map('intval', array_filter($ids));

            if (!empty($cleanIds)) {
                $inQuery = implode(',', $cleanIds);
                $stmt = $pdo->prepare("DELETE FROM portal_notifications WHERE id IN ($inQuery)");
                $stmt->execute();
            }
            echo json_encode(['success' => true]);
            exit;
        }
    }

    function portalRenderNotificationButton(): string {
        return '
        <button id="notification-bell-btn" onclick="toggleNotificationPanel(event)" type="button" class="relative p-2 rounded-full text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors" title="Notifications">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span id="notif-badge-count" class="hidden absolute top-1 right-1 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-extrabold text-white bg-rose-500 rounded-full border-2 border-white shadow-sm">0</span>
        </button>';
    }

    function portalRenderNotificationPanel(): string {
        return '
  <!-- Floating Notification Popover Panel -->
  <div id="global-notification-panel" class="hidden fixed top-16 right-4 sm:right-8 z-[99999] w-80 sm:w-96 rounded-2xl bg-white shadow-2xl border border-slate-200/90 overflow-hidden font-sans text-slate-800 animate-in fade-in zoom-in-95 duration-150">
    <div class="flex items-center justify-between px-4 py-3 bg-slate-900 text-white border-b border-slate-800">
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <h4 class="font-bold text-sm">Notifications</h4>
        <span id="notif-panel-count-badge" class="px-2 py-0.5 text-[10px] font-extrabold bg-teal-500/30 text-teal-300 rounded-full border border-teal-500/40">0 New</span>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" onclick="markAllNotificationsRead()" class="text-[11px] font-semibold text-slate-300 hover:text-teal-300 transition-colors">Mark all read</button>
        <button type="button" onclick="toggleNotificationPanel(event)" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">✕</button>
      </div>
    </div>

    <!-- Master Toolbar -->
    <div class="flex items-center justify-between px-4 py-2 bg-slate-50 border-b border-slate-100 text-xs text-slate-600">
      <label class="flex items-center gap-2 cursor-pointer select-none font-medium">
        <input type="checkbox" id="notif-select-all" onchange="toggleSelectAllNotifications(this)" class="rounded text-teal-600 focus:ring-teal-500">
        <span>Select All</span>
      </label>
      <button id="notif-delete-selected-btn" type="button" onclick="deleteSelectedNotifications()" class="hidden items-center gap-1 text-rose-600 font-bold hover:text-rose-700 transition-colors">
        🗑 Delete Selected (<span id="notif-selected-count">0</span>)
      </button>
    </div>

    <!-- Notifications List -->
    <div id="notif-panel-list" class="max-h-80 overflow-y-auto divide-y divide-slate-100 text-xs">
      <div class="p-6 text-center text-slate-400 font-medium">Loading notifications...</div>
    </div>
  </div>

  <!-- Notification Detail Modal -->
  <div id="notification-detail-modal" class="hidden fixed inset-0 z-[999999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden font-sans animate-in fade-in zoom-in-95 duration-200">
      <div class="flex items-center justify-between px-5 py-4 bg-slate-900 text-white">
        <div class="flex items-center gap-2">
          <span class="p-1.5 bg-teal-500/20 text-teal-400 rounded-lg">🔔</span>
          <h3 class="font-bold text-sm">Notification Details</h3>
        </div>
        <button type="button" onclick="closeNotifDetailModal()" class="text-slate-400 hover:text-white transition-colors">✕</button>
      </div>
      <div class="p-5 space-y-4 text-xs">
        <p id="notif-detail-time" class="text-[11px] font-semibold text-teal-600">--</p>
        <div id="notif-detail-text" class="text-slate-700 text-sm leading-relaxed whitespace-pre-wrap">--</div>
        <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
          <button id="notif-detail-delete-btn" type="button" class="px-3 py-2 bg-rose-50 text-rose-600 hover:bg-rose-100 font-bold rounded-xl transition-colors">
            🗑 Delete
          </button>
          <button type="button" onclick="closeNotifDetailModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function toggleNotificationPanel(event) {
      if (event) {
        event.stopPropagation();
        if (typeof event.preventDefault === "function") event.preventDefault();
      }
      const panel = document.getElementById("global-notification-panel") || document.querySelector("[data-notification-panel]");
      if (panel) {
        panel.classList.toggle("hidden");
      }
    }

    document.addEventListener("click", (event) => {
      const panel = document.getElementById("global-notification-panel") || document.querySelector("[data-notification-panel]");
      const bell = document.getElementById("notification-bell-btn") || document.querySelector("[data-notifications]");
      if (panel && !panel.classList.contains("hidden")) {
        if (bell && (panel.contains(event.target) || bell.contains(event.target))) return;
        if (!panel.contains(event.target)) {
          panel.classList.add("hidden");
        }
      }
    });

    let currentNotifications = [];
    let notifPollTimer = null;

    async function fetchNotifications() {
      try {
        const res = await fetch("?api=get_notifications");
        if (!res.ok) return;
        const data = await res.json();
        if (data.success) {
          currentNotifications = data.notifications || [];
          updateNotificationUI(data.unreadCount || 0);
        }
      } catch (err) {
        console.error("Error fetching notifications:", err);
      }
    }

    function updateNotificationUI(unreadCount) {
      const badge = document.getElementById("notif-badge-count");
      const panelBadge = document.getElementById("notif-panel-count-badge");
      if (badge) {
        if (unreadCount > 0) {
          badge.textContent = unreadCount > 99 ? "99+" : unreadCount;
          badge.classList.remove("hidden");
        } else {
          badge.classList.add("hidden");
        }
      }
      if (panelBadge) {
        panelBadge.textContent = `${unreadCount} New`;
      }
      renderNotificationList();
    }

    function renderNotificationList() {
      const listContainer = document.getElementById("notif-panel-list");
      if (!listContainer) return;

      if (!currentNotifications || currentNotifications.length === 0) {
        listContainer.innerHTML = \'<div class="p-6 text-center text-slate-400 font-medium">No notifications yet.</div>\';
        return;
      }

      let html = "";
      currentNotifications.forEach(n => {
        const isRead = n.is_read == 1;
        const bgClass = isRead ? "bg-white" : "bg-teal-50/60 font-semibold";
        const createdDate = new Date(n.created_at).toLocaleString();

        html += `
          <div class="p-3 ${bgClass} hover:bg-slate-50 transition-colors flex items-start gap-2.5 group relative">
            <input type="checkbox" class="notif-item-checkbox mt-1 rounded text-teal-600 focus:ring-teal-500" value="${n.id}" onchange="updateSelectedNotifCount()">
            <div class="flex-1 cursor-pointer" onclick="openNotifDetail(${n.id})">
              <p class="text-slate-800 leading-snug line-clamp-2">${escapeHtml(n.message)}</p>
              <span class="text-[10px] text-slate-400 mt-1 block">${createdDate}</span>
            </div>
            <button type="button" onclick="deleteSingleNotification(${n.id}, event)" class="opacity-0 group-hover:opacity-100 p-1 text-slate-400 hover:text-rose-600 transition-opacity" title="Delete notification">
              🗑
            </button>
          </div>
        `;
      });

      listContainer.innerHTML = html;
      updateSelectedNotifCount();
    }

    function escapeHtml(text) {
      if (!text) return "";
      return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/\'/g, "&#039;");
    }

    function toggleSelectAllNotifications(masterCb) {
      const checkboxes = document.querySelectorAll(".notif-item-checkbox");
      checkboxes.forEach(cb => cb.checked = masterCb.checked);
      updateSelectedNotifCount();
    }

    function updateSelectedNotifCount() {
      const checkboxes = document.querySelectorAll(".notif-item-checkbox:checked");
      const countSpan = document.getElementById("notif-selected-count");
      const delBtn = document.getElementById("notif-delete-selected-btn");
      if (countSpan && delBtn) {
        const count = checkboxes.length;
        countSpan.textContent = count;
        if (count > 0) {
          delBtn.classList.remove("hidden");
          delBtn.classList.add("flex");
        } else {
          delBtn.classList.add("hidden");
          delBtn.classList.remove("flex");
        }
      }
    }

    async function markAllNotificationsRead() {
      try {
        await fetch("?api=mark_read", { method: "POST" });
        fetchNotifications();
      } catch (err) {}
    }

    function openNotifDetail(notifId) {
      const notif = currentNotifications.find(n => n.id == notifId);
      if (!notif) return;

      const modal = document.getElementById("notification-detail-modal");
      const timeEl = document.getElementById("notif-detail-time");
      const textEl = document.getElementById("notif-detail-text");
      const delBtn = document.getElementById("notif-detail-delete-btn");

      if (timeEl) timeEl.textContent = new Date(notif.created_at).toLocaleString();
      if (textEl) textEl.textContent = notif.message;
      if (delBtn) {
        delBtn.onclick = async () => {
          await deleteSingleNotification(notifId);
          closeNotifDetailModal();
        };
      }

      if (modal) modal.classList.remove("hidden");
      fetch(`?api=mark_read&id=${notifId}`, { method: "POST" }).then(() => fetchNotifications());
    }

    function closeNotifDetailModal() {
      const modal = document.getElementById("notification-detail-modal");
      if (modal) modal.classList.add("hidden");
    }

    async function deleteSingleNotification(id, event) {
      if (event) event.stopPropagation();
      try {
        await fetch("?api=delete_notifications", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ ids: [id] })
        });
        fetchNotifications();
      } catch (err) {}
    }

    async function deleteSelectedNotifications() {
      const checkboxes = document.querySelectorAll(".notif-item-checkbox:checked");
      const ids = Array.from(checkboxes).map(cb => parseInt(cb.value)).filter(id => id > 0);
      if (ids.length === 0) return;

      try {
        await fetch("?api=delete_notifications", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ ids: ids })
        });
        const masterCb = document.getElementById("notif-select-all");
        if (masterCb) masterCb.checked = false;
        fetchNotifications();
      } catch (err) {}
    }

    document.addEventListener("DOMContentLoaded", () => {
      fetchNotifications();
      notifPollTimer = setInterval(fetchNotifications, 5000);
    });
  </script>';
    }
}

if (!function_exists('portalSettings')) {
    function portalSettings(?PDO $pdo): array {
        if (!$pdo) return [];
        try {
            ensurePortalTables($pdo);
            return $pdo->query('SELECT setting_key, setting_value FROM portal_settings')
                ->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (PDOException $e) {
            error_log('portalSettings: ' . $e->getMessage());
            return [];
        }
    }

    function portalSetting(array $settings, string $key, string $fallback = ''): string {
        return trim((string)($settings[$key] ?? '')) ?: $fallback;
    }

    function portalAudit(?PDO $pdo, ?int $userId, string $action, ?string $entityType = null, ?int $entityId = null): void {
        if (!$pdo) return;
        try {
            $statement = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address) VALUES (:user_id, :action, :entity_type, :entity_id, :ip_address)');
            $statement->execute([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (PDOException $e) {
            error_log('portalAudit: ' . $e->getMessage());
        }
    }

    function portalImgUrl(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, 'data:')) {
            return $url;
        }
        $cleanPath = ltrim($url, '/');
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (str_contains($script, '/src/app/components/')) {
            return '../../../' . $cleanPath;
        }
        return $cleanPath;
    }

    function portalCertificateSignatureUploadPath(array $file): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Signature upload failed. Please choose the image again.');
        }
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new RuntimeException('Signature image must be 2MB or smaller.');
        }
        $tmpPath = (string)($file['tmp_name'] ?? '');
        $imageInfo = $tmpPath !== '' ? @getimagesize($tmpPath) : false;
        if (!$imageInfo || !in_array((int)$imageInfo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('Upload a valid PNG, JPG, JPEG, or WEBP signature image.');
        }
        $extensions = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
        $uploadDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'signatures';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Unable to create the signature upload folder.');
        }
        $cleanedPath = portalCleanSignatureImage($tmpPath, (int)$imageInfo[2], $uploadDir);
        if ($cleanedPath !== '') {
            return 'uploads/signatures/' . basename($cleanedPath);
        }
        $filename = 'signature_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extensions[(int)$imageInfo[2]];
        $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpPath, $target)) {
            throw new RuntimeException('Unable to save the uploaded signature.');
        }
        return 'uploads/signatures/' . $filename;
    }

    function portalCleanSignatureImage(string $tmpPath, int $imageType, string $uploadDir): string {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) return '';
        $source = match ($imageType) {
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmpPath) : false,
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmpPath) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false,
            default => false,
        };
        if (!$source) return '';

        $width = imagesx($source);
        $height = imagesy($source);
        $maxWidth = 900;
        $targetWidth = min($width, $maxWidth);
        $targetHeight = (int)round($height * ($targetWidth / max(1, $width)));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $cornerSamples = [
            imagecolorat($canvas, 0, 0),
            imagecolorat($canvas, max(0, $targetWidth - 1), 0),
            imagecolorat($canvas, 0, max(0, $targetHeight - 1)),
            imagecolorat($canvas, max(0, $targetWidth - 1), max(0, $targetHeight - 1)),
        ];
        $background = ['r' => 0, 'g' => 0, 'b' => 0];
        foreach ($cornerSamples as $sample) {
            $background['r'] += ($sample >> 16) & 0xFF;
            $background['g'] += ($sample >> 8) & 0xFF;
            $background['b'] += $sample & 0xFF;
        }
        $background = array_map(static fn($value) => (int)round($value / 4), $background);
        $backgroundLum = (int)round(($background['r'] * 0.299) + ($background['g'] * 0.587) + ($background['b'] * 0.114));

        $minX = $targetWidth;
        $minY = $targetHeight;
        $maxX = 0;
        $maxY = 0;
        for ($y = 0; $y < $targetHeight; $y++) {
            for ($x = 0; $x < $targetWidth; $x++) {
                $rgba = imagecolorat($canvas, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $brightness = max($r, $g, $b);
                $lum = (int)round(($r * 0.299) + ($g * 0.587) + ($b * 0.114));
                $darkness = max(0, $backgroundLum - $lum);
                $contrast = max($r, $g, $b) - min($r, $g, $b);
                $backgroundDistance = abs($r - $background['r']) + abs($g - $background['g']) + abs($b - $background['b']);
                $isInk = $darkness > 28 && ($backgroundDistance > 42 || $contrast > 18);
                if (!$isInk) {
                    imagesetpixel($canvas, $x, $y, $transparent);
                    continue;
                }
                $inkStrength = max(80, min(255, ($darkness * 4) + ($contrast * 2)));
                $alpha = max(0, min(52, 72 - (int)round($inkStrength / 4)));
                $inkLevel = max(0, 58 - (int)round($inkStrength / 6));
                $ink = imagecolorallocatealpha($canvas, $inkLevel, $inkLevel, $inkLevel, $alpha);
                imagesetpixel($canvas, $x, $y, $ink);
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($minX <= $maxX && $minY <= $maxY) {
            $padding = 14;
            $cropX = max(0, $minX - $padding);
            $cropY = max(0, $minY - $padding);
            $cropW = min($targetWidth - $cropX, ($maxX - $minX + 1) + ($padding * 2));
            $cropH = min($targetHeight - $cropY, ($maxY - $minY + 1) + ($padding * 2));
            $cropped = imagecreatetruecolor($cropW, $cropH);
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
            imagefilledrectangle($cropped, 0, 0, $cropW, $cropH, $transparent);
            imagecopy($cropped, $canvas, 0, 0, $cropX, $cropY, $cropW, $cropH);
            imagedestroy($canvas);
            $canvas = $cropped;
        }

        $filename = 'signature_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.png';
        $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        $saved = imagepng($canvas, $target, 6);
        imagedestroy($source);
        imagedestroy($canvas);
        return $saved ? $target : '';
    }

    function portalSaveApprovalSignature(PDO $pdo, array $approval, string $signaturePath): void {
        if ($signaturePath === '') return;
        if (($approval['approver_type'] ?? '') === 'Doctor' && !empty($approval['staff_id'])) {
            $stmt = $pdo->prepare("UPDATE staff SET e_signature_path = :path WHERE id = :id");
            $stmt->execute(['path' => $signaturePath, 'id' => (int)$approval['staff_id']]);
            return;
        }
        if (!empty($approval['user_id'])) {
            $stmt = $pdo->prepare("UPDATE users SET e_signature_path = :path WHERE id = :id");
            $stmt->execute(['path' => $signaturePath, 'id' => (int)$approval['user_id']]);
        }
    }

    function ensurePortalTables(?PDO $pdo): void {
        if (!$pdo) return;
        static $ensured = false;
        if ($ensured) return;
        $ensured = true;
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS portal_settings (
                    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
                    setting_value TEXT NOT NULL,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS portal_announcements (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    category VARCHAR(50) NOT NULL DEFAULT 'Health Notice',
                    content TEXT NOT NULL,
                    badge_text VARCHAR(50) NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    posted_by VARCHAR(100) NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_announcements_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS portal_events (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    event_date VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    venue VARCHAR(255) NOT NULL,
                    description TEXT NOT NULL,
                    image_url TEXT NULL,
                    badge_color VARCHAR(50) DEFAULT 'bg-emerald-500',
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_events_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS barangays (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL UNIQUE,
                    municipality VARCHAR(100) NOT NULL DEFAULT 'Nasugbu',
                    province VARCHAR(100) NOT NULL DEFAULT 'Batangas',
                    population INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_barangay_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            try {
                $cols = $pdo->query("SHOW COLUMNS FROM portal_events LIKE 'image_url'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE portal_events ADD COLUMN image_url TEXT NULL AFTER description");
                }
            } catch (Exception $e) {}

            try {
                $colsAnnImg = $pdo->query("SHOW COLUMNS FROM portal_announcements LIKE 'image_url'")->fetchAll();
                if (empty($colsAnnImg)) {
                    $pdo->exec("ALTER TABLE portal_announcements ADD COLUMN image_url TEXT NULL AFTER content");
                }
                $colsAnnPop = $pdo->query("SHOW COLUMNS FROM portal_announcements LIKE 'is_popup'")->fetchAll();
                if (empty($colsAnnPop)) {
                    $pdo->exec("ALTER TABLE portal_announcements ADD COLUMN is_popup TINYINT(1) NOT NULL DEFAULT 0 AFTER image_url");
                }

                $countAnn = (int)$pdo->query("SELECT COUNT(*) FROM portal_announcements")->fetchColumn();
                if ($countAnn === 0) {
                    $seedTitle = "Dengue & Clean Community Awareness Drive";
                    $seedCategory = "Health Awareness";
                    $seedContent = "Join RHU Nasugbu in keeping our barangays safe from dengue fever. Remember the 4-S strategy: Search and destroy mosquito breeding sites, Self-protection measures, Seek early consultation, and Say yes to fogging when needed.";
                    $seedImg = "https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800&q=80";
                    $insSeed = $pdo->prepare("INSERT INTO portal_announcements (title, category, content, badge_text, image_url, is_popup, is_active, posted_by) VALUES (:t, :c, :cnt, 'Health Awareness', :img, 1, 1, 'MHO Admin')");
                    $insSeed->execute(['t' => $seedTitle, 'c' => $seedCategory, 'cnt' => $seedContent, 'img' => $seedImg]);
                }
            } catch (Exception $e) {}

            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS certificate_doctor_assignments (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        certificate_type_id INT NOT NULL,
                        purpose_keyword VARCHAR(150) NOT NULL DEFAULT '',
                        staff_id INT NOT NULL,
                        is_active TINYINT(1) NOT NULL DEFAULT 1,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_certificate_assignment (certificate_type_id, purpose_keyword, staff_id),
                        INDEX idx_certificate_assignment_lookup (certificate_type_id, purpose_keyword, is_active)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS certificate_signature_approvals (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        certificate_id INT NOT NULL,
                        approver_type VARCHAR(30) NOT NULL,
                        user_id INT NULL,
                        staff_id INT NULL,
                        approver_email VARCHAR(255) NOT NULL,
                        token_hash CHAR(64) NOT NULL UNIQUE,
                        status VARCHAR(30) NOT NULL DEFAULT 'Pending',
                        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        responded_at DATETIME NULL,
                        expires_at DATETIME NOT NULL,
                        ip_address VARCHAR(64) NULL,
                        user_agent VARCHAR(255) NULL,
                        INDEX idx_certificate_signature_certificate (certificate_id),
                        INDEX idx_certificate_signature_status (status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS certificate_email_logs (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        certificate_id INT NOT NULL,
                        recipient_email VARCHAR(255) NOT NULL,
                        email_type VARCHAR(60) NOT NULL,
                        subject VARCHAR(255) NOT NULL,
                        delivery_status VARCHAR(30) NOT NULL,
                        delivery_method VARCHAR(60) NULL,
                        error_message TEXT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_certificate_email_certificate (certificate_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS certificate_workflow_logs (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                        certificate_id INT NOT NULL,
                        actor_user_id INT NULL,
                        actor_staff_id INT NULL,
                        action VARCHAR(100) NOT NULL,
                        notes TEXT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_certificate_workflow_certificate (certificate_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");

                $certificateColumns = [
                    'workflow_status' => "ALTER TABLE health_certificates ADD COLUMN workflow_status VARCHAR(50) NOT NULL DEFAULT 'Draft' AFTER validity_status",
                    'assigned_doctor_id' => "ALTER TABLE health_certificates ADD COLUMN assigned_doctor_id INT NULL AFTER issued_by_id",
                    'admin_approver_user_id' => "ALTER TABLE health_certificates ADD COLUMN admin_approver_user_id INT NULL AFTER assigned_doctor_id",
                    'admin_signature_approved_at' => "ALTER TABLE health_certificates ADD COLUMN admin_signature_approved_at DATETIME NULL AFTER workflow_status",
                    'doctor_signature_approved_at' => "ALTER TABLE health_certificates ADD COLUMN doctor_signature_approved_at DATETIME NULL AFTER admin_signature_approved_at",
                    'final_approved_at' => "ALTER TABLE health_certificates ADD COLUMN final_approved_at DATETIME NULL AFTER doctor_signature_approved_at",
                    'sent_at' => "ALTER TABLE health_certificates ADD COLUMN sent_at DATETIME NULL AFTER final_approved_at",
                    'generated_html' => "ALTER TABLE health_certificates ADD COLUMN generated_html LONGTEXT NULL AFTER purpose",
                ];
                foreach ($certificateColumns as $column => $sql) {
                    $exists = $pdo->query("SHOW COLUMNS FROM health_certificates LIKE " . $pdo->quote($column))->fetchAll();
                    if (empty($exists)) $pdo->exec($sql);
                }

                foreach (['staff', 'users'] as $signatureTable) {
                    $exists = $pdo->query("SHOW COLUMNS FROM {$signatureTable} LIKE 'e_signature_path'")->fetchAll();
                    if (empty($exists)) $pdo->exec("ALTER TABLE {$signatureTable} ADD COLUMN e_signature_path TEXT NULL");
                }
            } catch (Throwable $certificateWorkflowError) {
                error_log('ensure certificate workflow: ' . $certificateWorkflowError->getMessage());
            }

        } catch (Exception $e) {
            error_log('ensurePortalTables: ' . $e->getMessage());
        }
    }

    function getPortalBarangays(?PDO $pdo): array {
        if (!$pdo) return [];
        try {
            ensurePortalTables($pdo);
            $count = (int)$pdo->query("SELECT COUNT(*) FROM barangays")->fetchColumn();
            if ($count === 0) {
                $defaultBgys = [
                    'Aga','Balaytigue','Banilad','Barangay 1 (Pob.)','Barangay 2 (Pob.)','Barangay 3 (Pob.)',
                    'Barangay 4 (Pob.)','Barangay 5 (Pob.)','Barangay 6 (Pob.)','Barangay 7 (Pob.)','Barangay 8 (Pob.)',
                    'Barangay 9 (Pob.)','Barangay 10 (Pob.)','Barangay 11 (Pob.)','Barangay 12 (Pob.)','Bilaran',
                    'Bucana','Bulihan','Bunducan','Butucan','Calayo','Catandaan','Cogunan','Dayap','Kaylaway','Kayrilaw',
                    'Latag','Looc','Lumbangan','Malapad na Bato','Mataas na Pulo','Maugat','Munting Indang','Natipuan',
                    'Pantalan','Papaya','Putat','Reparo','Talangan','Tumalim','Utod','Wawa'
                ];
                $stmt = $pdo->prepare("INSERT IGNORE INTO barangays (name, municipality, province) VALUES (:name, 'Nasugbu', 'Batangas')");
                foreach ($defaultBgys as $bgyName) {
                    $stmt->execute(['name' => $bgyName]);
                }
            }

            $stmt = $pdo->query("SELECT name FROM barangays ORDER BY name ASC");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            return $rows ?: [];
        } catch (PDOException $e) {
            error_log('getPortalBarangays: ' . $e->getMessage());
            return [];
        }
    }

    function getPortalAnnouncements(?PDO $pdo): array {
        if (!$pdo) return [];
        try {
            ensurePortalTables($pdo);
            $stmt = $pdo->query("SELECT * FROM portal_announcements WHERE is_active = 1 ORDER BY id DESC LIMIT 10");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            error_log('getPortalAnnouncements: ' . $e->getMessage());
            return [];
        }
    }

    function getPortalEvents(?PDO $pdo): array {
        if (!$pdo) return [];
        try {
            ensurePortalTables($pdo);
            $stmt = $pdo->query("SELECT * FROM portal_events WHERE is_active = 1 ORDER BY id DESC LIMIT 20");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            error_log('getPortalEvents: ' . $e->getMessage());
            return [];
        }
    }

    function getPortalEventGallery(?PDO $pdo): array {
        if ($pdo) {
            try {
                $settings = portalSettings($pdo);
                if (!empty($settings['rhu_event_gallery'])) {
                    $gallery = json_decode($settings['rhu_event_gallery'], true);
                    if (is_array($gallery) && count($gallery) > 0) return $gallery;
                }
            } catch (Exception $e) {}
        }
        return getPortalEventGalleryDefaults();
    }

    function getPortalEventGalleryDefaults(): array {
        return [
            [
                'title' => 'Municipal Health & Blood Donation Drive',
                'category' => 'Blood Drive Mission',
                'image_url' => 'https://images.unsplash.com/photo-1615461066841-6116e61058f4?auto=format&fit=crop&w=800&q=80',
                'date' => 'Jun 2026'
            ],
            [
                'title' => 'Women\'s Free Cancer Screening Campaign',
                'category' => 'Maternal Wellness',
                'image_url' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800&q=80',
                'date' => 'Jun 2026'
            ],
            [
                'title' => 'Senior Citizens Free ECG & Medical Clinic',
                'category' => 'Elderly Care',
                'image_url' => 'https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?auto=format&fit=crop&w=800&q=80',
                'date' => 'Jun 2026'
            ],
            [
                'title' => 'Barangay Child Immunization (EPI) Day',
                'category' => 'Child Health',
                'image_url' => 'https://images.unsplash.com/photo-1631815588090-d4bfec5b1cdb?auto=format&fit=crop&w=800&q=80',
                'date' => 'Jul 2026'
            ]
        ];
    }
}

if (!function_exists('getStaffSchedulesFilePath')) {
    function getStaffSchedulesFilePath(): string {
        $paths = [
            __DIR__ . '/../../data/staff_schedules.json',
            __DIR__ . '/../data/staff_schedules.json',
            dirname(__DIR__, 2) . '/data/staff_schedules.json',
            sys_get_temp_dir() . '/staff_schedules.json'
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        $primary = __DIR__ . '/../../data/staff_schedules.json';
        $dir = dirname($primary);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $primary;
    }
}

if (!function_exists('loadStaffSchedulesFromJson')) {
    function loadStaffSchedulesFromJson(): array {
        $file = getStaffSchedulesFilePath();
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }
        return [];
    }
}

if (!function_exists('saveStaffScheduleToJson')) {
    function saveStaffScheduleToJson(int $staffId, array $scheduleData): bool {
        if ($staffId <= 0) return false;
        
        $schedules = loadStaffSchedulesFromJson();
        $key = (string)$staffId;
        $existing = $schedules[$key] ?? [];
        
        $merged = array_merge($existing, $scheduleData, [
            'staff_id' => $staffId,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        $schedules[$key] = $merged;
        
        $file = getStaffSchedulesFilePath();
        $jsonStr = json_encode($schedules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $res = @file_put_contents($file, $jsonStr) !== false;
        
        $secondary = __DIR__ . '/../data/staff_schedules.json';
        if (is_dir(dirname($secondary)) && $secondary !== $file) {
            @file_put_contents($secondary, $jsonStr);
        }
        return $res;
    }
}

if (!function_exists('syncStaffFromDatabaseToJson')) {
    function syncStaffFromDatabaseToJson(?PDO $pdo): array {
        $existingSchedules = loadStaffSchedulesFromJson();
        if (!$pdo) return $existingSchedules;

        try {
            try {
                $pdo->exec("ALTER TABLE staff ADD COLUMN work_days VARCHAR(100) DEFAULT 'Monday, Tuesday, Wednesday, Thursday, Friday'");
                $pdo->exec("ALTER TABLE staff ADD COLUMN shift_start TIME DEFAULT '08:00:00'");
                $pdo->exec("ALTER TABLE staff ADD COLUMN shift_end TIME DEFAULT '17:00:00'");
                $pdo->exec("ALTER TABLE staff ADD COLUMN is_on_duty TINYINT(1) DEFAULT 1");
            } catch (Throwable $tCols) {}

            $stmt = $pdo->query("
                SELECT s.id AS staff_id, s.staff_type, s.specialization,
                       COALESCE(s.work_days, 'Monday, Tuesday, Wednesday, Thursday, Friday') AS db_work_days,
                       COALESCE(s.shift_start, '08:00:00') AS db_shift_start,
                       COALESCE(s.shift_end, '17:00:00') AS db_shift_end,
                       COALESCE(s.is_on_duty, 1) AS db_is_on_duty,
                       COALESCE(u.first_name, '') AS first_name,
                       COALESCE(u.last_name, '') AS last_name,
                       u.email, s.phone_number
                FROM staff s
                LEFT JOIN users u ON s.user_id = u.id
                ORDER BY s.id ASC
            ");

            $dbStaff = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($dbStaff)) {
                $newSchedules = [];
                foreach ($dbStaff as $row) {
                    $sid = (int)$row['staff_id'];
                    $key = (string)$sid;
                    $existing = $existingSchedules[$key] ?? [];

                    $fName = trim($row['first_name']);
                    $lName = trim($row['last_name']);
                    $fullName = trim($fName . ' ' . $lName);
                    if ($fullName === '') {
                        $fullName = 'RHU Staff #' . $sid;
                        $fName = 'RHU';
                        $lName = 'Staff #' . $sid;
                    }

                    $sType = !empty($row['staff_type']) ? $row['staff_type'] : 'Rural Health Staff';
                    $spec = !empty($row['specialization']) ? $row['specialization'] : 'General Healthcare';

                    $newSchedules[$key] = [
                        'staff_id' => $sid,
                        'first_name' => $fName,
                        'last_name' => $lName,
                        'name' => $fullName,
                        'staff_type' => $sType,
                        'position' => $sType,
                        'specialization' => $spec,
                        'email' => $row['email'] ?? '',
                        'phone_number' => $row['phone_number'] ?? '',
                        'work_days' => $existing['work_days'] ?? $row['db_work_days'],
                        'shift_start' => $existing['shift_start'] ?? $row['db_shift_start'],
                        'shift_end' => $existing['shift_end'] ?? $row['db_shift_end'],
                        'is_on_duty' => isset($existing['is_on_duty']) ? (int)$existing['is_on_duty'] : (int)$row['db_is_on_duty'],
                        'updated_at' => $existing['updated_at'] ?? date('Y-m-d H:i:s')
                    ];
                }

                $file = getStaffSchedulesFilePath();
                $jsonStr = json_encode($newSchedules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                @file_put_contents($file, $jsonStr);
                $secondary = __DIR__ . '/../data/staff_schedules.json';
                if (is_dir(dirname($secondary)) && $secondary !== $file) {
                    @file_put_contents($secondary, $jsonStr);
                }
                return $newSchedules;
            }
        } catch (Throwable $e) {
            error_log('syncStaffFromDatabaseToJson error: ' . $e->getMessage());
        }

        return $existingSchedules;
    }
}

if (!function_exists('mergeJsonScheduleIntoStaffList')) {
    function mergeJsonScheduleIntoStaffList(array $staffList, ?PDO $pdo = null): array {
        if ($pdo) {
            $jsonSchedules = syncStaffFromDatabaseToJson($pdo);
        } else {
            $jsonSchedules = loadStaffSchedulesFromJson();
        }
        
        if (empty($staffList) && !empty($jsonSchedules)) {
            $formatted = [];
            foreach ($jsonSchedules as $sched) {
                $sStart = !empty($sched['shift_start']) ? date('g:i A', strtotime($sched['shift_start'])) : '8:00 AM';
                $sEnd = !empty($sched['shift_end']) ? date('g:i A', strtotime($sched['shift_end'])) : '5:00 PM';
                $formatted[] = array_merge($sched, [
                    'id' => 'ST-' . ($sched['staff_id'] ?? 1),
                    'staff_id' => (int)($sched['staff_id'] ?? 1),
                    'first_name' => $sched['first_name'] ?? 'RHU',
                    'last_name' => $sched['last_name'] ?? 'Staff',
                    'name' => $sched['name'] ?? trim(($sched['first_name'] ?? '') . ' ' . ($sched['last_name'] ?? '')),
                    'position' => $sched['position'] ?? $sched['staff_type'] ?? 'Rural Health Staff',
                    'staff_type' => $sched['staff_type'] ?? $sched['position'] ?? 'Rural Health Staff',
                    'specialization' => $sched['specialization'] ?? 'General Medicine',
                    'workDays' => $sched['work_days'] ?? 'Monday, Tuesday, Wednesday, Thursday, Friday',
                    'work_days' => $sched['work_days'] ?? 'Monday, Tuesday, Wednesday, Thursday, Friday',
                    'shiftHours' => "{$sStart} - {$sEnd}",
                    'rawShiftStart' => $sched['shift_start'] ?? '08:00:00',
                    'rawShiftEnd' => $sched['shift_end'] ?? '17:00:00',
                    'isOnDuty' => !empty($sched['is_on_duty']),
                    'is_on_duty' => !empty($sched['is_on_duty']) ? 1 : 0
                ]);
            }
            return $formatted;
        }

        if (empty($jsonSchedules)) return $staffList;
        
        foreach ($staffList as &$staff) {
            $sid = (int)($staff['staff_id'] ?? $staff['id'] ?? 0);
            $key = (string)$sid;
            if ($sid > 0 && isset($jsonSchedules[$key])) {
                $sched = $jsonSchedules[$key];
                if (isset($sched['work_days'])) {
                    $staff['work_days'] = $sched['work_days'];
                    $staff['workDays'] = $sched['work_days'];
                }
                if (isset($sched['shift_start'])) {
                    $staff['shift_start'] = $sched['shift_start'];
                    $staff['rawShiftStart'] = $sched['shift_start'];
                }
                if (isset($sched['shift_end'])) {
                    $staff['shift_end'] = $sched['shift_end'];
                    $staff['rawShiftEnd'] = $sched['shift_end'];
                }
                if (isset($sched['is_on_duty'])) {
                    $staff['is_on_duty'] = (int)$sched['is_on_duty'];
                    $staff['isOnDuty'] = (bool)$sched['is_on_duty'];
                }
                $sStart = !empty($staff['shift_start']) ? date('g:i A', strtotime($staff['shift_start'])) : '8:00 AM';
                $sEnd = !empty($staff['shift_end']) ? date('g:i A', strtotime($staff['shift_end'])) : '5:00 PM';
                $staff['shiftHours'] = "{$sStart} - {$sEnd}";
            }
        }
        unset($staff);
        return $staffList;
    }
}


