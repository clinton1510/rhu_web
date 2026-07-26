CREATE TABLE IF NOT EXISTS resident_dependents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    primary_resident_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    relationship VARCHAR(40) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender VARCHAR(20) NULL,
    blood_type VARCHAR(10) NULL,
    medical_notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_resident_dependents_primary (primary_resident_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
