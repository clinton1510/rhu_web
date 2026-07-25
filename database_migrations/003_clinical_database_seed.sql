-- RHU Clinical Operations Seed SQL Script
-- Populates MySQL 'rhu' database with initial clinical records

USE rhu;

-- 1. Seed Blood Banks & Inventory
INSERT INTO blood_banks (id, name, location, address, phone_number, capacity) 
VALUES (1, 'Nasugbu RHU Main Blood Bank', 'Nasugbu Health Center', 'Poblacion, Nasugbu, Batangas', '(043) 416-1234', 100)
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO blood_inventory (blood_bank_id, blood_type, rh_factor, quantity, expiry_date) VALUES 
(1, 'O+', '+', 24, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
(1, 'A+', '+', 18, DATE_ADD(CURDATE(), INTERVAL 25 DAY)),
(1, 'B+', '+', 12, DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
(1, 'AB+', '+', 8, DATE_ADD(CURDATE(), INTERVAL 20 DAY)),
(1, 'O-', '-', 5, DATE_ADD(CURDATE(), INTERVAL 10 DAY))
ON DUPLICATE KEY UPDATE quantity=VALUES(quantity);

-- 2. Seed Blood Requests
INSERT INTO blood_requests (id, requesting_facility, blood_type, quantity_needed, urgency_level, requested_date, patient_name, patient_condition, request_status) VALUES
(1, 'Nasugbu RHU Clinic', 'O+', 2, 'High', CURDATE(), 'Jose Dela Cruz', 'Severe anemia requiring urgent transfusion', 'Pending'),
(2, 'Nasugbu District Hospital', 'A+', 1, 'Critical', CURDATE(), 'Maria Santos', 'Postpartum Hemorrhage', 'Fulfilled')
ON DUPLICATE KEY UPDATE request_status=VALUES(request_status);

-- 3. Seed Donors
INSERT INTO donors (id, resident_id, blood_type, rh_factor, total_donations, last_donation_date, donor_classification, is_eligible) VALUES
(1, 1, 'O+', '+', 4, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 'Reliable', 1),
(2, 2, 'A+', '+', 2, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), 'Moderate', 1),
(3, 3, 'B+', '+', 5, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'Reliable', 1)
ON DUPLICATE KEY UPDATE total_donations=VALUES(total_donations);

-- 4. Seed Consultations
INSERT INTO consultations (id, resident_id, physician_id, consultation_date, chief_complaint, diagnosis, icd_code, treatment_plan, medications_prescribed) VALUES
(1, 1, 1, CURDATE(), 'Persistent cough and low grade fever for 3 days', 'Acute Bronchitis', 'J20', 'Hydration and rest', 'Amoxicillin 500mg, Paracetamol 500mg'),
(2, 2, 1, CURDATE(), 'Dizziness and elevated blood pressure reading (150/90)', 'Essential Hypertension', 'I10', 'Dietary modification & BP Monitoring', 'Amlodipine 5mg OD')
ON DUPLICATE KEY UPDATE diagnosis=VALUES(diagnosis);

-- 5. Seed Immunization Schedules & Vaccination Records
INSERT INTO immunization_schedules (id, vaccine_name, age_group, doh_recommended_schedule) VALUES
(1, 'BCG Vaccine', 'Infant', 'At birth'),
(2, 'Hepatitis B', 'Infant', 'At birth'),
(3, 'Pentavalent (DPT-HepB-Hib)', 'Infant', '6, 10, 14 weeks')
ON DUPLICATE KEY UPDATE vaccine_name=VALUES(vaccine_name);

INSERT INTO vaccination_records (resident_id, vaccine_id, vaccination_date, healthcare_provider_id, batch_number, next_dose_date) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 6 MONTH), 1, 'BCG-2025-09', DATE_ADD(CURDATE(), INTERVAL 1 MONTH)),
(2, 3, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 1, 'PENTA-9921', DATE_ADD(CURDATE(), INTERVAL 2 WEEK))
ON DUPLICATE KEY UPDATE batch_number=VALUES(batch_number);

-- 6. Seed Pregnancies / Maternal Cases
INSERT INTO pregnancies (id, resident_id, last_menstrual_period, expected_delivery_date, pregnancy_status, high_risk, risk_factors) VALUES
(1, 2, DATE_SUB(CURDATE(), INTERVAL 6 MONTH), DATE_ADD(CURDATE(), INTERVAL 3 MONTH), 'Active', 1, 'Gestational Hypertension'),
(2, 3, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_ADD(CURDATE(), INTERVAL 5 MONTH), 'Active', 0, 'Normal Routine')
ON DUPLICATE KEY UPDATE pregnancy_status=VALUES(pregnancy_status);

-- 7. Seed TB Patients
INSERT INTO tb_patients (id, resident_id, tb_registration_number, tb_type, treatment_status, treatment_start_date) VALUES
(1, 1, 'TB-NSG-2026-001', 'Pulmonary TB', 'Active', DATE_SUB(CURDATE(), INTERVAL 2 MONTH))
ON DUPLICATE KEY UPDATE treatment_status=VALUES(treatment_status);

-- 8. Seed Medicine Inventory
INSERT INTO medicine_inventory (id, generic_name, brand_name, dosage, unit_form, quantity_in_stock, reorder_level, expiry_date, batch_number) VALUES
(1, 'Amoxicillin', 'Amoxil', '500mg', 'Capsule', 150, 50, DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'AMX-2026-01'),
(2, 'Paracetamol', 'Biogesic', '500mg', 'Tablet', 300, 100, DATE_ADD(CURDATE(), INTERVAL 2 YEAR), 'PAR-2026-05'),
(3, 'Amlodipine', 'Norvasc', '5mg', 'Tablet', 80, 30, DATE_ADD(CURDATE(), INTERVAL 18 MONTH), 'AML-2026-09')
ON DUPLICATE KEY UPDATE quantity_in_stock=VALUES(quantity_in_stock);

-- 9. Seed Disease Types & Cases
INSERT INTO disease_types (id, disease_name, icd_code, is_reportable) VALUES
(1, 'Dengue Fever', 'A90', 1),
(2, 'Acute Gastroenteritis', 'A09', 1)
ON DUPLICATE KEY UPDATE disease_name=VALUES(disease_name);

INSERT INTO disease_cases (resident_id, disease_id, case_date, case_classification, outcome) VALUES
(1, 1, CURDATE(), 'Confirmed', 'Recovered'),
(2, 2, CURDATE(), 'Probable', 'Recovered')
ON DUPLICATE KEY UPDATE case_classification=VALUES(case_classification);

-- 10. Seed Vital Statistics Births
INSERT INTO vital_statistics_births (mother_id, child_name, date_of_birth, place_of_birth, birth_weight_kg) VALUES
(2, 'Baby Girl Santos', CURDATE(), 'Nasugbu Lying-in Clinic', 3.1)
ON DUPLICATE KEY UPDATE child_name=VALUES(child_name);

-- 11. Seed Health Certificates
INSERT INTO health_certificates (resident_id, certificate_type_id, certificate_number, issue_date, expiry_date, purpose) VALUES
(1, 1, 'CERT-2026-0001', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 'Employment Requirement')
ON DUPLICATE KEY UPDATE certificate_number=VALUES(certificate_number);

-- 12. Seed FHSIS Reports
INSERT INTO fhsis_reports (report_month, report_year, submitted_date, status) VALUES
(7, 2026, CURDATE(), 'Submitted')
ON DUPLICATE KEY UPDATE status=VALUES(status);
