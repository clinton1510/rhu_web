-- RHU Overview KPI sample records for development/testing.
-- Import after database_schema.sql and 010_prediction_source_seed.sql.
-- Requires at least one resident and one staff row.
-- Tagged rows are inserted only once.

START TRANSACTION;

SET @kpi_resident_id := (SELECT id FROM residents ORDER BY id LIMIT 1);
SET @kpi_second_resident_id := (
    SELECT id FROM residents
    WHERE id <> @kpi_resident_id
    ORDER BY id LIMIT 1
);
SET @kpi_female_resident_id := (
    SELECT id FROM residents
    WHERE LOWER(gender) IN ('female', 'f')
    ORDER BY id LIMIT 1
);
SET @kpi_staff_id := (SELECT id FROM staff ORDER BY id LIMIT 1);

-- Add the nutrition fields consumed by RHUDashboard.
ALTER TABLE resident_health_profiles
    ADD COLUMN IF NOT EXISTS nutrition_classification VARCHAR(20) NULL AFTER weight,
    ADD COLUMN IF NOT EXISTS muac DECIMAL(5,2) NULL AFTER nutrition_classification;

-- Add the standalone BHW profile fields consumed by RHUDashboard.
ALTER TABLE bhw
    ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) NULL AFTER staff_id,
    ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) NULL AFTER first_name,
    ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER last_name,
    ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS cert_number VARCHAR(50) NULL AFTER phone_number,
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER cert_number,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER password_hash;

-- 1. Today's OPD and one pending hospital referral.
INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, icd_code, treatment_plan,
     medications_prescribed, referral_needed, referral_to,
     consultation_notes)
SELECT @kpi_resident_id, @kpi_staff_id, CURDATE(), '08:30:00',
       'Fever and persistent cough', 'Acute respiratory infection', 'J06.9',
       'Hydration, medication, and follow-up',
       'Paracetamol 500 mg', 0, NULL, '[KPI-SEED-OPD-TODAY]'
WHERE @kpi_resident_id IS NOT NULL AND @kpi_staff_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM consultations
      WHERE consultation_notes = '[KPI-SEED-OPD-TODAY]'
  );

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, icd_code, treatment_plan,
     referral_needed, referral_to, consultation_notes)
SELECT @kpi_resident_id, @kpi_staff_id, CURDATE(), '10:00:00',
       'Severe abdominal pain', 'Acute abdominal pain', 'R10.0',
       'Refer for further diagnostic evaluation',
       1, 'Batangas Provincial Hospital', '[KPI-SEED-REFERRAL-PENDING]'
WHERE @kpi_resident_id IS NOT NULL AND @kpi_staff_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM consultations
      WHERE consultation_notes = '[KPI-SEED-REFERRAL-PENDING]'
  );

-- 2. Active TB-DOTS case and adherence record.
INSERT INTO tb_patients
    (resident_id, tb_registration_number, tb_type, treatment_status,
     treatment_start_date, dots_provider_id, diagnosis_date)
SELECT @kpi_resident_id, 'KPI-SEED-TB-001', 'Pulmonary', 'On_Treatment',
       DATE_SUB(CURDATE(), INTERVAL 45 DAY), @kpi_staff_id,
       DATE_SUB(CURDATE(), INTERVAL 50 DAY)
WHERE @kpi_resident_id IS NOT NULL AND @kpi_staff_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM tb_patients
      WHERE tb_registration_number = 'KPI-SEED-TB-001'
  );

SET @kpi_tb_id := (
    SELECT id FROM tb_patients
    WHERE tb_registration_number = 'KPI-SEED-TB-001' LIMIT 1
);

INSERT INTO tb_adherence_tracking
    (tb_patient_id, tracking_date, observed_dose, dose_count,
     missed_doses, side_effects, notes)
SELECT @kpi_tb_id, CURDATE(), 1, 30, 1,
       'None reported', '[KPI-SEED-TB-TRACKING]'
WHERE @kpi_tb_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM tb_adherence_tracking
      WHERE notes = '[KPI-SEED-TB-TRACKING]'
  );

-- 3. Nutrition records producing one SAM and one MAM count.
INSERT INTO resident_health_profiles
    (resident_id, height, weight, nutrition_classification, muac,
     blood_pressure, heart_rate, temperature, last_checkup_date,
     diet_type)
SELECT @kpi_resident_id, 105.00, 13.50, 'SAM', 11.20,
       '100/70', 92, 36.7, CURDATE(), 'Nutrition rehabilitation plan'
WHERE @kpi_resident_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM resident_health_profiles
      WHERE resident_id = @kpi_resident_id
        AND nutrition_classification = 'SAM'
  );

INSERT INTO resident_health_profiles
    (resident_id, height, weight, nutrition_classification, muac,
     blood_pressure, heart_rate, temperature, last_checkup_date,
     diet_type)
SELECT @kpi_second_resident_id, 112.00, 16.20, 'MAM', 12.10,
       '100/70', 88, 36.6, CURDATE(), 'Supplementary feeding plan'
WHERE @kpi_second_resident_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM resident_health_profiles
      WHERE resident_id = @kpi_second_resident_id
        AND nutrition_classification = 'MAM'
  );

-- 4. Active high-risk prenatal case and latest prenatal visit.
INSERT INTO pregnancies
    (resident_id, gravida, para, last_menstrual_period,
     expected_delivery_date, pregnancy_status, high_risk, risk_factors)
SELECT @kpi_female_resident_id, 2, 1,
       DATE_SUB(CURDATE(), INTERVAL 120 DAY),
       DATE_ADD(CURDATE(), INTERVAL 160 DAY),
       'Active', 1, '[KPI-SEED-PRENATAL] Gestational hypertension'
WHERE @kpi_female_resident_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM pregnancies
      WHERE risk_factors LIKE '[KPI-SEED-PRENATAL]%'
  );

SET @kpi_pregnancy_id := (
    SELECT id FROM pregnancies
    WHERE risk_factors LIKE '[KPI-SEED-PRENATAL]%'
    ORDER BY id DESC LIMIT 1
);

INSERT INTO prenatal_visits
    (pregnancy_id, visit_date, visit_type, healthcare_provider_id,
     blood_pressure, weight, fundal_height, fetal_heart_rate,
     risk_assessment, notes, next_visit_date)
SELECT @kpi_pregnancy_id, CURDATE(), 'Follow-up', @kpi_staff_id,
       '140/90', 62.50, 22.00, 145,
       'High', '[KPI-SEED-PRENATAL-VISIT] Close BP monitoring',
       DATE_ADD(CURDATE(), INTERVAL 14 DAY)
WHERE @kpi_pregnancy_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM prenatal_visits
      WHERE notes LIKE '[KPI-SEED-PRENATAL-VISIT]%'
  );

-- 5. Active family-planning client.
INSERT INTO family_planning_clients
    (resident_id, method, acceptor_type, last_supply_date,
     next_visit_date, status, notes)
SELECT COALESCE(@kpi_female_resident_id, @kpi_resident_id),
       'Combined Oral Contraceptive Pills', 'New',
       CURDATE(), DATE_ADD(CURDATE(), INTERVAL 28 DAY),
       'Active', '[KPI-SEED-FP-001]'
WHERE COALESCE(@kpi_female_resident_id, @kpi_resident_id) IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM family_planning_clients
      WHERE notes = '[KPI-SEED-FP-001]'
  );

-- 6. One overdue and one upcoming vaccine dose.
INSERT INTO immunization_schedules
    (vaccine_name, age_group, doh_recommended_schedule, notes)
SELECT 'KPI Seed Pentavalent', 'Infant', '6, 10, and 14 weeks',
       'Development seed schedule'
WHERE NOT EXISTS (
    SELECT 1 FROM immunization_schedules
    WHERE vaccine_name = 'KPI Seed Pentavalent'
);

SET @kpi_vaccine_id := (
    SELECT id FROM immunization_schedules
    WHERE vaccine_name = 'KPI Seed Pentavalent' LIMIT 1
);

INSERT INTO vaccination_records
    (resident_id, vaccine_id, vaccination_date, healthcare_provider_id,
     batch_number, site_of_injection, adverse_reactions, next_dose_date)
SELECT @kpi_resident_id, @kpi_vaccine_id,
       DATE_SUB(CURDATE(), INTERVAL 60 DAY), @kpi_staff_id,
       'KPI-SEED-VAC-OVERDUE', 'Left thigh', 'None',
       DATE_SUB(CURDATE(), INTERVAL 10 DAY)
WHERE @kpi_resident_id IS NOT NULL AND @kpi_vaccine_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM vaccination_records
      WHERE batch_number = 'KPI-SEED-VAC-OVERDUE'
  );

INSERT INTO vaccination_records
    (resident_id, vaccine_id, vaccination_date, healthcare_provider_id,
     batch_number, site_of_injection, adverse_reactions, next_dose_date)
SELECT COALESCE(@kpi_second_resident_id, @kpi_resident_id), @kpi_vaccine_id,
       DATE_SUB(CURDATE(), INTERVAL 20 DAY), @kpi_staff_id,
       'KPI-SEED-VAC-DUE', 'Right thigh', 'None',
       DATE_ADD(CURDATE(), INTERVAL 7 DAY)
WHERE COALESCE(@kpi_second_resident_id, @kpi_resident_id) IS NOT NULL
  AND @kpi_vaccine_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM vaccination_records
      WHERE batch_number = 'KPI-SEED-VAC-DUE'
  );

-- 7. Two issued health certificates.
INSERT INTO certificate_types
    (certificate_type_name, description, requirements, fee)
VALUES
    ('Medical Certificate', 'General medical assessment certificate',
     'Resident record and completed consultation', 50.00),
    ('Sanitary Clearance', 'Health clearance for employment or food handling',
     'Resident record and completed assessment', 75.00)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    requirements = VALUES(requirements),
    fee = VALUES(fee);

SET @kpi_medical_cert_type_id := (
    SELECT id FROM certificate_types
    WHERE certificate_type_name = 'Medical Certificate' LIMIT 1
);
SET @kpi_sanitary_cert_type_id := (
    SELECT id FROM certificate_types
    WHERE certificate_type_name = 'Sanitary Clearance' LIMIT 1
);

INSERT INTO health_certificates
    (resident_id, certificate_type_id, certificate_number, issue_date,
     expiry_date, issued_by_id, purpose, validity_status)
SELECT @kpi_resident_id, @kpi_medical_cert_type_id,
       'KPI-SEED-CERT-001', CURDATE(),
       DATE_ADD(CURDATE(), INTERVAL 6 MONTH), @kpi_staff_id,
       'Employment requirement', 'Valid'
WHERE @kpi_resident_id IS NOT NULL AND @kpi_medical_cert_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM health_certificates
      WHERE certificate_number = 'KPI-SEED-CERT-001'
  );

INSERT INTO health_certificates
    (resident_id, certificate_type_id, certificate_number, issue_date,
     expiry_date, issued_by_id, purpose, validity_status)
SELECT COALESCE(@kpi_second_resident_id, @kpi_resident_id),
       @kpi_sanitary_cert_type_id,
       'KPI-SEED-CERT-002', CURDATE(),
       DATE_ADD(CURDATE(), INTERVAL 1 YEAR), @kpi_staff_id,
       'Food-handler clearance', 'Valid'
WHERE COALESCE(@kpi_second_resident_id, @kpi_resident_id) IS NOT NULL
  AND @kpi_sanitary_cert_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM health_certificates
      WHERE certificate_number = 'KPI-SEED-CERT-002'
  );

-- 8. Active Barangay Health Worker.
INSERT INTO bhw
    (staff_id, first_name, last_name, email, phone_number, cert_number,
     password_hash, is_active, barangay, coverage_population,
     coverage_area, assigned_date)
SELECT @kpi_staff_id, 'Maria', 'KPI Seed',
       'kpi.seed.bhw@example.test', '09170000001',
       'KPI-SEED-BHW-001', '$2y$10$developmentSeedNotForLoginOnly0000000000000000000000',
       1, 'KPI Seed Barangay', 75, 2.50, CURDATE()
WHERE @kpi_staff_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM bhw
      WHERE cert_number = 'KPI-SEED-BHW-001'
         OR barangay = 'KPI Seed Barangay'
  );

COMMIT;

-- Verification queries.
SELECT COUNT(*) AS today_opd
FROM consultations WHERE consultation_date = CURDATE();

SELECT COUNT(*) AS active_tb
FROM tb_patients WHERE LOWER(treatment_status) IN ('active', 'on_treatment');

SELECT nutrition_classification, COUNT(*) AS total
FROM resident_health_profiles
WHERE nutrition_classification IN ('SAM', 'MAM')
GROUP BY nutrition_classification;

SELECT COUNT(*) AS active_prenatal
FROM pregnancies WHERE LOWER(pregnancy_status) = 'active';

SELECT COUNT(*) AS active_fp_clients
FROM family_planning_clients WHERE LOWER(status) = 'active';

SELECT COUNT(*) AS vaccines_needing_visit
FROM vaccination_records
WHERE next_dose_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY);

SELECT COUNT(*) AS valid_certificates
FROM health_certificates WHERE validity_status = 'Valid';

SELECT COUNT(*) AS active_bhws
FROM bhw WHERE COALESCE(is_active, 1) = 1;

