CREATE TABLE IF NOT EXISTS sanitation_notices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inspection_id BIGINT UNSIGNED NOT NULL,
    notice_number VARCHAR(60) NOT NULL UNIQUE,
    issued_by_staff_id INT NULL,
    issued_date DATE NOT NULL,
    violations INT NOT NULL DEFAULT 0,
    findings TEXT NULL,
    notice_status VARCHAR(30) NOT NULL DEFAULT 'Issued',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notice_inspection (inspection_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

