CREATE TABLE IF NOT EXISTS family_planning_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    contraceptive_method VARCHAR(100) NOT NULL,
    acceptor_type VARCHAR(50) NOT NULL DEFAULT 'New Acceptor',
    last_supply_date DATE NOT NULL,
    next_visit_date DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Active',
    clinical_notes TEXT NULL,
    healthcare_provider_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fp_resident (resident_id),
    INDEX idx_fp_next_visit (next_visit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS maternal_referrals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    pregnancy_id BIGINT UNSIGNED NULL,
    diagnosis VARCHAR(255) NOT NULL,
    referred_to VARCHAR(255) NOT NULL,
    referral_reason TEXT NOT NULL,
    urgency VARCHAR(30) NOT NULL DEFAULT 'Routine',
    referral_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    referred_by_id INT NULL,
    referral_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_referral_resident (resident_id),
    INDEX idx_referral_status (referral_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

