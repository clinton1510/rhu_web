-- RHU Prototype Database Schema
-- Database: rhu
-- Created: 2026-07-15

-- ============================================
-- 1. USER & AUTHENTICATION TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS permissions (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id INT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    role_id INT NOT NULL REFERENCES roles(id),
    is_active BOOLEAN DEFAULT TRUE,
    is_mfa_enabled BOOLEAN DEFAULT FALSE,
    mfa_secret VARCHAR(255),
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_timestamp (timestamp)
);

-- ============================================
-- 2. STAFF & PERSONNEL TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS staff (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    staff_type VARCHAR(50) NOT NULL, -- BHW, Midwife, Nurse, Physician, MedTech, Sanitary Inspector
    license_number VARCHAR(100),
    license_expiry DATE,
    specialization VARCHAR(100),
    phone_number VARCHAR(20),
    address TEXT,
    date_hired DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_staff_type (staff_type)
);

CREATE TABLE IF NOT EXISTS bhw (
    id SERIAL PRIMARY KEY,
    staff_id INT NOT NULL REFERENCES staff(id) ON DELETE CASCADE,
    barangay VARCHAR(100) NOT NULL,
    coverage_population INT,
    coverage_area DECIMAL(10, 2),
    assigned_date DATE,
    UNIQUE(barangay)
);

CREATE TABLE IF NOT EXISTS midwife (
    id SERIAL PRIMARY KEY,
    staff_id INT NOT NULL REFERENCES staff(id) ON DELETE CASCADE,
    specialty VARCHAR(100),
    cases_assisted INT DEFAULT 0,
    assigned_facility VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS nurse (
    id SERIAL PRIMARY KEY,
    staff_id INT NOT NULL REFERENCES staff(id) ON DELETE CASCADE,
    duty_station VARCHAR(100),
    shift VARCHAR(20), -- Morning, Afternoon, Night
    assigned_facility VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS physician (
    id SERIAL PRIMARY KEY,
    staff_id INT NOT NULL REFERENCES staff(id) ON DELETE CASCADE,
    specialty VARCHAR(100),
    consultation_days VARCHAR(255),
    assigned_facility VARCHAR(255)
);

-- ============================================
-- 3. RESIDENT/PATIENT TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS residents (
    id SERIAL PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    date_of_birth DATE NOT NULL,
    gender VARCHAR(10),
    civil_status VARCHAR(20),
    contact_number VARCHAR(20),
    email VARCHAR(255),
    address TEXT NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    purok_sitio VARCHAR(100),
    philhealth_id VARCHAR(50),
    national_id VARCHAR(50),
    blood_type VARCHAR(10),
    allergies TEXT,
    medical_conditions TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_number VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_barangay (barangay),
    INDEX idx_name (last_name, first_name),
    INDEX idx_philhealth (philhealth_id)
);

CREATE TABLE IF NOT EXISTS resident_health_profiles (
    id SERIAL PRIMARY KEY,
    resident_id INT NOT NULL REFERENCES residents(id) ON DELETE CASCADE,
    height DECIMAL(5, 2),
    weight DECIMAL(5, 2),
    blood_pressure VARCHAR(20),
    heart_rate INT,
    temperature DECIMAL(4, 1),
    last_checkup_date DATE,
    smoking_status VARCHAR(50),
    alcohol_consumption VARCHAR(50),
    exercise_frequency VARCHAR(50),
    diet_type VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 4. BLOOD DONATION SYSTEM TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS donors (
    id SERIAL PRIMARY KEY,
    resident_id INT NOT NULL REFERENCES residents(id),
    blood_type VARCHAR(10) NOT NULL,
    rh_factor VARCHAR(5),
    total_donations INT DEFAULT 0,
    last_donation_date DATE,
    donor_classification VARCHAR(50), -- Reliable, Moderate, New
    is_eligible BOOLEAN DEFAULT TRUE,
    donation_response_probability DECIMAL(3, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blood_type (blood_type),
    INDEX idx_classification (donor_classification),
    INDEX idx_eligible (is_eligible)
);

CREATE TABLE IF NOT EXISTS blood_banks (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    address TEXT,
    phone_number VARCHAR(20),
    email VARCHAR(255),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    manager_name VARCHAR(100),
    capacity INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blood_inventory (
    id SERIAL PRIMARY KEY,
    blood_bank_id INT NOT NULL REFERENCES blood_banks(id) ON DELETE CASCADE,
    blood_type VARCHAR(10) NOT NULL,
    rh_factor VARCHAR(5),
    quantity INT NOT NULL DEFAULT 0,
    expiry_date DATE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blood_bank_id (blood_bank_id),
    INDEX idx_blood_type (blood_type)
);

CREATE TABLE IF NOT EXISTS blood_donations (
    id SERIAL PRIMARY KEY,
    donor_id INT NOT NULL REFERENCES donors(id),
    blood_bank_id INT NOT NULL REFERENCES blood_banks(id),
    donation_date DATE NOT NULL,
    donation_time TIME,
    quantity_ml INT DEFAULT 450,
    blood_type VARCHAR(10),
    rh_factor VARCHAR(5),
    collection_status VARCHAR(50), -- Collected, Rejected, Incomplete
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_donation_date (donation_date),
    INDEX idx_donor_id (donor_id)
);

CREATE TABLE IF NOT EXISTS blood_requests (
    id SERIAL PRIMARY KEY,
    requesting_facility VARCHAR(255) NOT NULL,
    blood_type VARCHAR(10) NOT NULL,
    rh_factor VARCHAR(5),
    quantity_needed INT NOT NULL,
    urgency_level VARCHAR(50), -- Critical, High, Normal, Low
    requested_date DATE NOT NULL,
    requested_time TIME,
    patient_name VARCHAR(100),
    patient_condition TEXT,
    request_status VARCHAR(50), -- Pending, Matched, Fulfilled, Cancelled
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blood_type (blood_type),
    INDEX idx_urgency (urgency_level),
    INDEX idx_status (request_status)
);

CREATE TABLE IF NOT EXISTS blood_matches (
    id SERIAL PRIMARY KEY,
    blood_request_id INT NOT NULL REFERENCES blood_requests(id) ON DELETE CASCADE,
    donor_id INT NOT NULL REFERENCES donors(id),
    distance_km DECIMAL(10, 2),
    response_probability DECIMAL(3, 2),
    match_score DECIMAL(5, 2),
    matched_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    matched_by INT REFERENCES users(id),
    contact_status VARCHAR(50), -- Not Contacted, Contacted, Agreed, Declined
    contacted_date TIMESTAMP,
    INDEX idx_blood_request_id (blood_request_id),
    INDEX idx_donor_id (donor_id)
);

-- ============================================
-- 5. MATERNAL HEALTH TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS pregnancies (
    id SERIAL PRIMARY KEY,
    resident_id INT NOT NULL REFERENCES residents(id) ON DELETE CASCADE,
    last_menstrual_period DATE,
    expected_delivery_date DATE,
    pregnancy_status VARCHAR(50), -- Active, Delivered, Miscarriage, Terminated
    high_risk BOOLEAN DEFAULT FALSE,
    risk_factors TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_resident_id (resident_id),
    INDEX idx_status (pregnancy_status)
);

CREATE TABLE IF NOT EXISTS prenatal_visits (
    id SERIAL PRIMARY KEY,
    pregnancy_id INT NOT NULL REFERENCES pregnancies(id) ON DELETE CASCADE,
    visit_date DATE NOT NULL,
    visit_type VARCHAR(50), -- Initial, Follow-up
    healthcare_provider_id INT REFERENCES staff(id),
    blood_pressure VARCHAR(20),
    weight DECIMAL(5, 2),
    fundal_height DECIMAL(5, 2),
    fetal_heart_rate INT,
    urine_test_results TEXT,
    blood_test_results TEXT,
    ultrasound_findings TEXT,
    risk_assessment VARCHAR(50),
    notes TEXT,
    next_visit_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS deliveries (
    id SERIAL PRIMARY KEY,
    pregnancy_id INT NOT NULL REFERENCES pregnancies(id) ON DELETE CASCADE,
    delivery_date DATE NOT NULL,
    delivery_time TIME,
    delivery_type VARCHAR(50), -- Vaginal, Cesarean, Assisted
    birth_attendant_id INT REFERENCES staff(id),
    live_births INT DEFAULT 1,
    stillbirths INT DEFAULT 0,
    complications TEXT,
    mother_status VARCHAR(50),
    delivery_location VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 6. IMMUNIZATION TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS immunization_schedules (
    id SERIAL PRIMARY KEY,
    vaccine_name VARCHAR(100) NOT NULL,
    age_group VARCHAR(50),
    doh_recommended_schedule TEXT,
    notes TEXT
);

CREATE TABLE IF NOT EXISTS vaccination_records (
    id SERIAL PRIMARY KEY,
    resident_id INT NOT NULL REFERENCES residents(id) ON DELETE CASCADE,
    vaccine_id INT NOT NULL REFERENCES immunization_schedules(id),
    vaccination_date DATE NOT NULL,
    healthcare_provider_id INT REFERENCES staff(id),
    batch_number VARCHAR(100),
    site_of_injection VARCHAR(100),
    adverse_reactions TEXT,
    next_dose_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_resident_id (resident_id),
    INDEX idx_vaccination_date (vaccination_date)
);

-- ============================================
-- 7. DISEASE SURVEILLANCE (PIDSR) TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS disease_types (
    id SERIAL PRIMARY KEY,
    disease_name VARCHAR(100) NOT NULL UNIQUE,
    icd_code VARCHAR(50),
    is_reportable BOOLEAN DEFAULT TRUE,
    incubation_period_days INT
);

CREATE TABLE IF NOT EXISTS disease_cases (
    id SERIAL PRIMARY KEY,
    resident_id INT NOT NULL REFERENCES residents(id),
    disease_id INT NOT NULL REFERENCES disease_types(id),
    case_date DATE NOT NULL,
    onset_date DATE,
    reported_by_id INT REFERENCES staff(id),
    case_classification VARCHAR(50), -- Confirmed, Probable, Suspected
    symptoms TEXT,
    specimen_collection_date DATE,
    specimen_type VARCHAR(100),
    laboratory_result VARCHAR(50),
    outcome VARCHAR(50), -- Recovered, Died, Lost to follow-up
    treatment TEXT,
    case_status VARCHAR(50),
    reported_to_doh BOOLEAN DEFAULT FALSE,
    doh_report_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_disease_id (disease_id),
    INDEX idx_case_date (case_date)
);

-- ============================================
-- 8. TB-DOTS TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS tb_patients (
    id SERIAL PRIMARY KEY,
    resident_id INT NOT NULL REFERENCES residents(id) ON DELETE CASCADE,
    tb_registration_number VARCHAR(100) UNIQUE,
    tb_type VARCHAR(50), -- Pulmonary, Extra-pulmonary
    treatment_status VARCHAR(50), -- Active, Completed, Lost to follow-up, Died
    treatment_start_date DATE,
    treatment_end_date DATE,
    dots_provider_id INT REFERENCES staff(id),
    diagnosis_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tb_registration (tb_registration_number),
    INDEX idx_status (treatment_status)
);

CREATE TABLE IF NOT EXISTS tb_adherence_tracking (
    id SERIAL PRIMARY KEY,
    tb_patient_id INT NOT NULL REFERENCES tb_patients(id) ON DELETE CASCADE,
    tracking_date DATE NOT NULL,
    observed_dose BOOLEAN,
    dose_count INT,
    missed_doses INT DEFAULT 0,
    side_effects TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tracking_date (tracking_date)
);

-- ============================================
-- 9. CONSULTATION & DIAGNOSTIC TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS consultations (
    id SERIAL PRIMARY KEY,
    resident_id INT NOT NULL REFERENCES residents(id) ON DELETE CASCADE,
    physician_id INT NOT NULL REFERENCES staff(id),
    consultation_date DATE NOT NULL,
    consultation_time TIME,
    chief_complaint TEXT NOT NULL,
    patient_history TEXT,
    physical_examination TEXT,
    diagnosis VARCHAR(255),
    icd_code VARCHAR(50),
    treatment_plan TEXT,
    medications_prescribed TEXT,
    follow_up_date DATE,
    referral_needed BOOLEAN DEFAULT FALSE,
    referral_to VARCHAR(255),
    consultation_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_resident_id (resident_id),
    INDEX idx_consultation_date (consultation_date)
);

CREATE TABLE IF NOT EXISTS diagnostics (
    id SERIAL PRIMARY KEY,
    consultation_id INT NOT NULL REFERENCES consultations(id) ON DELETE CASCADE,
    test_type VARCHAR(100), -- Laboratory, Imaging, ECG, etc.
    test_name VARCHAR(100) NOT NULL,
    test_date DATE,
    results TEXT,
    test_status VARCHAR(50), -- Pending, Completed, Abnormal
    ordered_by_id INT REFERENCES staff(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 10. INVENTORY MANAGEMENT TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS medicine_inventory (
    id SERIAL PRIMARY KEY,
    generic_name VARCHAR(100) NOT NULL,
    brand_name VARCHAR(100),
    dosage VARCHAR(50),
    unit_form VARCHAR(50), -- Tablet, Capsule, Injection, etc.
    quantity_in_stock INT NOT NULL DEFAULT 0,
    reorder_level INT,
    supplier VARCHAR(255),
    unit_cost DECIMAL(10, 2),
    expiry_date DATE,
    batch_number VARCHAR(100),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_generic_name (generic_name),
    INDEX idx_expiry_date (expiry_date)
);

CREATE TABLE IF NOT EXISTS stock_transactions (
    id SERIAL PRIMARY KEY,
    medicine_id INT NOT NULL REFERENCES medicine_inventory(id),
    transaction_type VARCHAR(50), -- In, Out, Adjustment
    quantity INT NOT NULL,
    transaction_date DATE NOT NULL,
    recorded_by_id INT REFERENCES staff(id),
    reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction_date (transaction_date)
);

-- ============================================
-- 11. VITAL STATISTICS TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS vital_statistics_births (
    id SERIAL PRIMARY KEY,
    birth_certificate_number VARCHAR(100) UNIQUE,
    child_name VARCHAR(100),
    date_of_birth DATE NOT NULL,
    time_of_birth TIME,
    place_of_birth VARCHAR(255),
    mother_id INT NOT NULL REFERENCES residents(id),
    father_name VARCHAR(100),
    gender VARCHAR(10),
    birth_weight_kg DECIMAL(5, 2),
    birth_length_cm DECIMAL(5, 2),
    delivery_attendant_id INT REFERENCES staff(id),
    registered_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date_of_birth (date_of_birth)
);

CREATE TABLE IF NOT EXISTS vital_statistics_deaths (
    id SERIAL PRIMARY KEY,
    death_certificate_number VARCHAR(100) UNIQUE,
    deceased_name VARCHAR(100),
    date_of_death DATE NOT NULL,
    place_of_death VARCHAR(255),
    cause_of_death VARCHAR(255),
    icd_code VARCHAR(50),
    age_at_death INT,
    reported_by_id INT REFERENCES staff(id),
    registered_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date_of_death (date_of_death)
);

-- ============================================
-- 12. HEALTH CERTIFICATES TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS certificate_types (
    id SERIAL PRIMARY KEY,
    certificate_type_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    requirements TEXT,
    fee DECIMAL(10, 2)
);

CREATE TABLE IF NOT EXISTS health_certificates (
    id SERIAL PRIMARY KEY,
    resident_id INT NOT NULL REFERENCES residents(id) ON DELETE CASCADE,
    certificate_type_id INT NOT NULL REFERENCES certificate_types(id),
    certificate_number VARCHAR(100) UNIQUE,
    issue_date DATE NOT NULL,
    expiry_date DATE,
    issued_by_id INT REFERENCES staff(id),
    purpose TEXT,
    validity_status VARCHAR(50), -- Valid, Expired, Revoked
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_issue_date (issue_date),
    INDEX idx_validity_status (validity_status)
);

-- ============================================
-- 13. DOH REPORTING TABLES
-- ============================================

CREATE TABLE IF NOT EXISTS fhsis_reports (
    id SERIAL PRIMARY KEY,
    report_month INT NOT NULL,
    report_year INT NOT NULL,
    submitted_date DATE,
    submitted_by_id INT REFERENCES staff(id),
    report_data JSON,
    status VARCHAR(50), -- Draft, Submitted, Approved, Rejected
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(report_month, report_year)
);

CREATE TABLE IF NOT EXISTS pidsr_reports (
    id SERIAL PRIMARY KEY,
    report_week INT NOT NULL,
    report_year INT NOT NULL,
    submitted_date DATE,
    submitted_by_id INT REFERENCES staff(id),
    disease_data JSON,
    status VARCHAR(50), -- Draft, Submitted, Approved
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(report_week, report_year)
);

CREATE TABLE IF NOT EXISTS ntp_tb_reports (
    id SERIAL PRIMARY KEY,
    report_month INT NOT NULL,
    report_year INT NOT NULL,
    submitted_date DATE,
    submitted_by_id INT REFERENCES staff(id),
    new_tb_cases INT,
    completed_treatment INT,
    lost_to_follow_up INT,
    tb_deaths INT,
    status VARCHAR(50), -- Draft, Submitted
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(report_month, report_year)
);

-- ============================================
-- INSERT DEFAULT ROLES
-- ============================================

INSERT INTO roles (name, description) VALUES
('RESIDENT', 'Community resident user'),
('BHW', 'Barangay Health Worker'),
('MIDWIFE', 'Midwife'),
('NURSE', 'Registered Nurse'),
('MEDTECH', 'Medical Technician'),
('PHYSICIAN', 'Medical Doctor/Physician'),
('SANITARY_INSPECTOR', 'Sanitary Inspector'),
('ADMIN_STAFF', 'RHU Administrative Staff'),
('RHU_ADMIN', 'RHU Administrator'),
('SUPER_ADMIN', 'System Super Administrator')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ============================================
-- INSERT DEFAULT CERTIFICATE TYPES
-- ============================================

INSERT INTO certificate_types (certificate_type_name, description, requirements, fee) VALUES
('Medical Certificate', 'General health certificate for work/school', 'Physical examination', 50.00),
('Vaccination Certificate', 'Proof of vaccination', 'Complete immunization records', 0.00),
('Pregnancy Certificate', 'Certificate for pregnant women', 'Prenatal visit records', 50.00),
('Barangay Health Certificate', 'Local health certification', 'Health screening', 30.00),
('Fitness Certificate', 'Physical fitness certification', 'Medical evaluation', 100.00),
('Travel Health Certificate', 'Certificate for travel', 'Health check and vaccination status', 75.00),
('Mental Health Certificate', 'Mental health evaluation', 'Psychological assessment', 150.00)
ON DUPLICATE KEY UPDATE certificate_type_name=VALUES(certificate_type_name);

-- ============================================
-- INSERT DEFAULT DISEASES
-- ============================================

INSERT INTO disease_types (disease_name, icd_code, is_reportable) VALUES
('Dengue Fever', 'A90', TRUE),
('Tuberculosis', 'A15', TRUE),
('Leptospirosis', 'A27', TRUE),
('Measles', 'B05', TRUE),
('COVID-19', 'U07.1', TRUE),
('Pneumonia', 'J18', TRUE),
('Diarrhea', 'A09', TRUE),
('Hypertension', 'I10', FALSE),
('Diabetes Mellitus Type 2', 'E11', FALSE),
('Asthma', 'J45', FALSE)
ON DUPLICATE KEY UPDATE disease_name=VALUES(disease_name);

-- ============================================
-- INSERT DEFAULT IMMUNIZATION SCHEDULES
-- ============================================

INSERT INTO immunization_schedules (vaccine_name, age_group, doh_recommended_schedule) VALUES
('BCG', 'Newborn', 'At birth'),
('Pentavalent', 'Infants', '2, 4, 6 months'),
('Polio', 'Infants', '2, 4, 6, 12-18 months'),
('Pneumococcal', 'Infants', '2, 4, 12-15 months'),
('Measles/MMR', 'Toddlers', '12-15 months, 4-6 years'),
('DPT Booster', 'School age', 'Grade 1'),
('Tetanus', 'Adults', 'Every 10 years'),
('Influenza', 'Annually', 'Yearly vaccination'),
('Hepatitis B', 'Newborn and adults', 'At birth, then 1-2 months'),
('Japanese Encephalitis', 'Age 1 year', '1 year old')
ON DUPLICATE KEY UPDATE vaccine_name=VALUES(vaccine_name);

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

CREATE INDEX idx_users_role_id ON users(role_id);
CREATE INDEX idx_staff_user_id ON staff(user_id);
CREATE INDEX idx_residents_barangay ON residents(barangay);
CREATE INDEX idx_donors_resident_id ON donors(resident_id);
CREATE INDEX idx_blood_bank_id ON blood_inventory(blood_bank_id);
CREATE INDEX idx_consultations_resident_id ON consultations(resident_id);
CREATE INDEX idx_vaccination_resident_id ON vaccination_records(resident_id);
CREATE INDEX idx_disease_cases_resident_id ON disease_cases(resident_id);
CREATE INDEX idx_tb_patients_resident_id ON tb_patients(resident_id);
CREATE INDEX idx_pregnancies_resident_id ON pregnancies(resident_id);

COMMIT;