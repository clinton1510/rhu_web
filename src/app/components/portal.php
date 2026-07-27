<?php
require_once __DIR__ . '/db.php';

if (!function_exists('e')) {
    function e(mixed $value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
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
            $statement = $pdo->prepare('SELECT u.id FROM residents r JOIN users u ON u.email = r.email WHERE r.id = :resident_id LIMIT 1');
            $statement->execute(['resident_id' => $residentId]);
            $userId = $statement->fetchColumn();
            portalNotify($pdo, $message, $userId ? (int)$userId : null, $userId ? null : 'RESIDENT', $link);
        } catch (PDOException $e) {
            error_log('portalNotifyResident: ' . $e->getMessage());
        }
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


