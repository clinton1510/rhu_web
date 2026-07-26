-- Database-backed content and workflow storage for the PHP portal.
-- Safe to run more than once on the existing `rhu` MariaDB database.

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
    badge_color VARCHAR(50) DEFAULT 'bg-emerald-500',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_events_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS portal_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    audience_role VARCHAR(50) NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notification_user (user_id),
    INDEX idx_notification_role (audience_role),
    CONSTRAINT fk_portal_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    resident_id BIGINT UNSIGNED NULL,
    name VARCHAR(200) NOT NULL,
    contact_number VARCHAR(30) NULL,
    email VARCHAR(255) NULL,
    subject VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_resident (resident_id),
    INDEX idx_contact_status (status),
    CONSTRAINT fk_contact_message_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blood_drives (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    venue VARCHAR(255) NOT NULL,
    barangay VARCHAR(100) NULL,
    scheduled_date DATE NOT NULL,
    target_donors INT NULL,
    actual_donors INT NULL,
    blood_types_needed VARCHAR(100) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Scheduled',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blood_drive_date (scheduled_date),
    INDEX idx_blood_drive_barangay (barangay)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS donor_referrals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bhw_id BIGINT UNSIGNED NULL,
    donor_id BIGINT UNSIGNED NULL,
    full_name VARCHAR(255) NOT NULL,
    blood_type VARCHAR(10) NULL,
    age INT NOT NULL,
    gender VARCHAR(20) NULL,
    weight_kg DECIMAL(5,2) NULL,
    contact_number VARCHAR(30) NOT NULL,
    address TEXT NULL,
    occupation VARCHAR(100) NULL,
    philhealth_id VARCHAR(50) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_referral_bhw (bhw_id),
    INDEX idx_referral_donor (donor_id),
    CONSTRAINT fk_donor_referral_bhw FOREIGN KEY (bhw_id) REFERENCES bhw(id) ON DELETE SET NULL,
    CONSTRAINT fk_donor_referral_donor FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO portal_settings (setting_key, setting_value) VALUES
    ('rhu_name', 'Nasugbu Rural Health Unit I'),
    ('rhu_address', 'Poblacion, Nasugbu, Batangas'),
    ('rhu_contact_number', '(043) 416-1234'),
    ('rhu_operating_hours', 'Mon–Fri: 8:00 AM – 5:00 PM'),
    ('rhu_municipality', 'Nasugbu'),
    ('rhu_province', 'Batangas'),
    ('rhu_municipal_officials', '[{"name":"Hon. Antonio Jose A. Barcelon","position":"Municipal Mayor","office":"Office of the Municipal Mayor"},{"name":"Hon. Larry D. Albanio","position":"Municipal Vice Mayor","office":"Office of the Municipal Vice Mayor"},{"name":"Hon. Arlene R. Chuidian","position":"Municipal Councilor","office":"Committee Chair on Health & Sanitation"},{"name":"Hon. Marcos H. Guevarra","position":"Municipal Councilor","office":"Committee on Women & Family Services"},{"name":"Hon. Anne Linette A. Vivas","position":"Municipal Councilor","office":"Committee on Finance & Budget"},{"name":"Hon. Dionisio B. Botobara","position":"Municipal Councilor","office":"Committee on Social Welfare"}]')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO portal_announcements (title, category, content, badge_text, is_active, posted_by)
SELECT 'Dengue Awareness & Prevention Alert', 'Emergency Alert', 'Active monitoring in Brgy. Halang & Mabini. Clean surroundings & eliminate stagnant water.', 'Dengue Alert', 1, 'Admin'
WHERE NOT EXISTS (SELECT 1 FROM portal_announcements LIMIT 1);
