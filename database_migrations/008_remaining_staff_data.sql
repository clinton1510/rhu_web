CREATE TABLE IF NOT EXISTS laboratory_referrals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id BIGINT UNSIGNED NOT NULL,
    test_requested VARCHAR(180) NOT NULL,
    destination_facility VARCHAR(180) NOT NULL,
    referral_date DATE NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Pending',
    result_text TEXT NULL,
    clinical_notes TEXT NULL,
    referred_by_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lab_referral_date (referral_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS laboratory_supplies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(180) NOT NULL,
    category VARCHAR(100) NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    unit VARCHAR(40) NOT NULL DEFAULT 'units',
    reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
    expiry_date DATE NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bhw_donor_referrals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bhw_id BIGINT UNSIGNED NULL,
    full_name VARCHAR(180) NOT NULL,
    blood_type VARCHAR(5) NULL,
    age INT UNSIGNED NOT NULL,
    gender VARCHAR(20) NULL,
    contact_number VARCHAR(30) NOT NULL,
    address TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Pending Verification',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blood_drives (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bhw_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    scheduled_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    venue VARCHAR(180) NOT NULL,
    target_donors INT UNSIGNED NOT NULL DEFAULT 0,
    actual_donors INT UNSIGNED NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Scheduled',
    notes TEXT NULL,
    blood_types_needed VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blood_need_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bhw_id BIGINT UNSIGNED NULL,
    patient_name VARCHAR(180) NOT NULL,
    blood_type VARCHAR(5) NOT NULL,
    urgency VARCHAR(30) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Submitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
