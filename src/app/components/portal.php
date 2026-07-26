<?php
require_once __DIR__ . '/db.php';

if (!function_exists('e')) {
    function e(mixed $value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
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
