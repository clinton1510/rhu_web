-- Complete database backing for RHU Admin Dashboard workflows.
-- Idempotent for the existing MariaDB `rhu` database.

CREATE TABLE IF NOT EXISTS portal_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS portal_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    audience_role VARCHAR(50) NULL,
    message TEXT NOT NULL,
    link_url VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notification_user (user_id),
    INDEX idx_notification_role (audience_role),
    CONSTRAINT fk_admin_notification_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS portal_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    venue VARCHAR(255) NOT NULL,
    event_date VARCHAR(50) NOT NULL,
    scheduled_date DATE NULL,
    start_time TIME NULL,
    capacity INT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Scheduled',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_portal_event_date (event_date),
    INDEX idx_portal_event_status (status),
    CONSTRAINT fk_portal_event_creator
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Older portal installations already have portal_events with a display-date string.
ALTER TABLE portal_events
    ADD COLUMN IF NOT EXISTS scheduled_date DATE NULL AFTER event_date,
    ADD COLUMN IF NOT EXISTS start_time TIME NULL AFTER scheduled_date,
    ADD COLUMN IF NOT EXISTS capacity INT NULL AFTER start_time,
    ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'Scheduled' AFTER capacity,
    ADD COLUMN IF NOT EXISTS created_by BIGINT UNSIGNED NULL AFTER status;

UPDATE portal_events
SET scheduled_date = COALESCE(
    scheduled_date,
    STR_TO_DATE(event_date, '%M %e, %Y'),
    STR_TO_DATE(event_date, '%Y-%m-%d')
)
WHERE scheduled_date IS NULL;

CREATE TABLE IF NOT EXISTS event_registrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    resident_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    confirmed_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_event_resident (event_id, resident_id),
    INDEX idx_event_registration_status (status),
    CONSTRAINT fk_event_registration_event
        FOREIGN KEY (event_id) REFERENCES portal_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_registration_resident
        FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_registration_confirmer
        FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE health_certificates
    ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER validity_status;

INSERT INTO portal_settings (setting_key, setting_value) VALUES
    ('rhu_name', 'Nasugbu Rural Health Unit I'),
    ('rhu_mho_name', 'Municipal Health Officer'),
    ('rhu_municipality', 'Nasugbu'),
    ('rhu_province', 'Batangas'),
    ('rhu_contact_number', '(043) 416-1234'),
    ('rhu_email', ''),
    ('smtp_host', ''),
    ('smtp_port', '587'),
    ('smtp_encryption', 'tls'),
    ('smtp_user', ''),
    ('two_factor_enabled', '1')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO portal_events (title, description, venue, event_date, scheduled_date, start_time, capacity, status)
SELECT 'Community Blood Donation Drive',
       'Free community blood donation activity for eligible residents.',
       'Nasugbu RHU Main',
       DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 30 DAY), '%M %e, %Y'),
       DATE_ADD(CURDATE(), INTERVAL 30 DAY),
       '08:00:00',
       100,
       'Scheduled'
WHERE NOT EXISTS (SELECT 1 FROM portal_events);
