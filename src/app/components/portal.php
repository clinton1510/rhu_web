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
}
