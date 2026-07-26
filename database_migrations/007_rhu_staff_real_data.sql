CREATE TABLE IF NOT EXISTS family_planning_clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id BIGINT UNSIGNED NOT NULL,
    method VARCHAR(100) NOT NULL,
    acceptor_type VARCHAR(50) NOT NULL,
    last_supply_date DATE NULL,
    next_visit_date DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Active',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fp_resident (resident_id),
    INDEX idx_fp_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sanitation_inspections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment VARCHAR(180) NOT NULL,
    barangay VARCHAR(120) NOT NULL,
    inspector_staff_id BIGINT UNSIGNED NULL,
    inspection_date DATE NOT NULL,
    next_inspection_date DATE NULL,
    status VARCHAR(40) NOT NULL,
    compliance_rate DECIMAL(5,2) NULL,
    violations INT UNSIGNED NOT NULL DEFAULT 0,
    findings TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sanitation_barangay (barangay),
    INDEX idx_sanitation_date (inspection_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
