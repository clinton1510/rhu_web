<?php
require_once __DIR__ . '/db.php';

if (!function_exists('portalSettings')) {
    function portalSettings(?PDO $pdo): array {
        if (!$pdo) return [];
        try {
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

    function portalSaveSettings(?PDO $pdo, array $settings): void {
        if (!$pdo) throw new RuntimeException('Database connection is unavailable.');
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
            $statement = $pdo->prepare('SELECT u.id FROM residents r JOIN users u ON u.email = r.email WHERE r.id = :resident_id LIMIT 1');
            $statement->execute(['resident_id' => $residentId]);
            $userId = $statement->fetchColumn();
            portalNotify($pdo, $message, $userId ? (int)$userId : null, $userId ? null : 'RESIDENT', $link);
        } catch (PDOException $e) {
            error_log('portalNotifyResident: ' . $e->getMessage());
        }
    }
}
