-- Prediction source records for development/testing.
-- Import this AFTER database_schema.sql.
-- The script is idempotent: its tagged records are inserted only once.
-- It requires at least one existing resident and one existing staff record.

START TRANSACTION;

SET @prediction_resident_id := (SELECT id FROM residents ORDER BY id LIMIT 1);
SET @prediction_staff_id := (SELECT id FROM staff ORDER BY id LIMIT 1);

-- -------------------------------------------------------------------------
-- 1. CONSULTATION HISTORY
-- Gives the OPD predictor six months of gradually increasing visit history.
-- -------------------------------------------------------------------------

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, treatment_plan, referral_needed,
     consultation_notes)
SELECT @prediction_resident_id, @prediction_staff_id,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH), INTERVAL 3 DAY),
       '08:30:00', 'Routine checkup', 'Essential hypertension',
       'Continue monitoring', 0, '[PRED-SEED-OPD-01]'
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM consultations WHERE consultation_notes = '[PRED-SEED-OPD-01]');

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, treatment_plan, referral_needed,
     consultation_notes)
SELECT @prediction_resident_id, @prediction_staff_id,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 4 MONTH), INTERVAL 4 DAY),
       '09:00:00', 'Cough and colds', 'Acute respiratory infection',
       'Supportive care', 0, '[PRED-SEED-OPD-02]'
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM consultations WHERE consultation_notes = '[PRED-SEED-OPD-02]');

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, treatment_plan, referral_needed,
     consultation_notes)
SELECT @prediction_resident_id, @prediction_staff_id,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 4 MONTH), INTERVAL 18 DAY),
       '10:15:00', 'Blood pressure follow-up', 'Essential hypertension',
       'Continue medication', 0, '[PRED-SEED-OPD-03]'
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM consultations WHERE consultation_notes = '[PRED-SEED-OPD-03]');

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, treatment_plan, referral_needed,
     consultation_notes)
SELECT @prediction_resident_id, @prediction_staff_id,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 3 MONTH), INTERVAL 5 DAY),
       '08:45:00', 'Fever', 'Influenza-like illness',
       'Hydration and monitoring', 0, '[PRED-SEED-OPD-04]'
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM consultations WHERE consultation_notes = '[PRED-SEED-OPD-04]');

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, treatment_plan, referral_needed,
     consultation_notes)
SELECT @prediction_resident_id, @prediction_staff_id,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 3 MONTH), INTERVAL 19 DAY),
       '13:10:00', 'Skin irritation', 'Contact dermatitis',
       'Topical treatment', 0, '[PRED-SEED-OPD-05]'
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM consultations WHERE consultation_notes = '[PRED-SEED-OPD-05]');

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, treatment_plan, referral_needed,
     consultation_notes)
SELECT @prediction_resident_id, @prediction_staff_id,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 2 MONTH), INTERVAL 3 DAY),
       '08:20:00', 'Headache and dizziness', 'Essential hypertension',
       'Blood pressure monitoring', 0, '[PRED-SEED-OPD-06]'
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM consultations WHERE consultation_notes = '[PRED-SEED-OPD-06]');

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, treatment_plan, referral_needed,
     consultation_notes)
SELECT @prediction_resident_id, @prediction_staff_id,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 2 MONTH), INTERVAL 12 DAY),
       '10:40:00', 'Loose bowel movement', 'Acute gastroenteritis',
       'ORS and zinc', 0, '[PRED-SEED-OPD-07]'
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM consultations WHERE consultation_notes = '[PRED-SEED-OPD-07]');

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, treatment_plan, referral_needed,
     consultation_notes)
SELECT @prediction_resident_id, @prediction_staff_id,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 2 MONTH), INTERVAL 22 DAY),
       '14:00:00', 'Persistent cough', 'Acute respiratory infection',
       'Medication and follow-up', 0, '[PRED-SEED-OPD-08]'
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM consultations WHERE consultation_notes = '[PRED-SEED-OPD-08]');

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, treatment_plan, referral_needed,
     consultation_notes)
SELECT @prediction_resident_id, @prediction_staff_id,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH), INTERVAL 2 DAY),
       '08:10:00', 'High fever and body pain', 'Dengue suspected',
       'CBC and close monitoring', 1, '[PRED-SEED-OPD-09]'
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM consultations WHERE consultation_notes = '[PRED-SEED-OPD-09]');

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, treatment_plan, referral_needed,
     consultation_notes)
SELECT @prediction_resident_id, @prediction_staff_id,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH), INTERVAL 9 DAY),
       '09:30:00', 'Cough and fever', 'Acute respiratory infection',
       'Medication and monitoring', 0, '[PRED-SEED-OPD-10]'
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM consultations WHERE consultation_notes = '[PRED-SEED-OPD-10]');

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, treatment_plan, referral_needed,
     consultation_notes)
SELECT @prediction_resident_id, @prediction_staff_id,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH), INTERVAL 16 DAY),
       '11:20:00', 'Diarrhea and dehydration', 'Acute gastroenteritis',
       'ORS and zinc', 0, '[PRED-SEED-OPD-11]'
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM consultations WHERE consultation_notes = '[PRED-SEED-OPD-11]');

INSERT INTO consultations
    (resident_id, physician_id, consultation_date, consultation_time,
     chief_complaint, diagnosis, treatment_plan, referral_needed,
     consultation_notes)
SELECT @prediction_resident_id, @prediction_staff_id,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH), INTERVAL 23 DAY),
       '14:15:00', 'Blood pressure follow-up', 'Essential hypertension',
       'Continue medication', 0, '[PRED-SEED-OPD-12]'
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM consultations WHERE consultation_notes = '[PRED-SEED-OPD-12]');

-- -------------------------------------------------------------------------
-- 2. DISEASE SURVEILLANCE HISTORY
-- Creates a measurable recent increase compared with the preceding 30 days.
-- -------------------------------------------------------------------------

INSERT INTO disease_types (disease_name, icd_code, is_reportable, incubation_period_days)
VALUES
    ('Dengue Fever', 'A90', 1, 7),
    ('Acute Gastroenteritis', 'A09', 1, 3)
ON DUPLICATE KEY UPDATE
    icd_code = VALUES(icd_code),
    is_reportable = VALUES(is_reportable),
    incubation_period_days = VALUES(incubation_period_days);

SET @dengue_id := (SELECT id FROM disease_types WHERE disease_name = 'Dengue Fever' LIMIT 1);
SET @age_id := (SELECT id FROM disease_types WHERE disease_name = 'Acute Gastroenteritis' LIMIT 1);

INSERT INTO disease_cases
    (resident_id, disease_id, case_date, onset_date, reported_by_id,
     case_classification, symptoms, laboratory_result, outcome, treatment,
     case_status, reported_to_doh)
SELECT @prediction_resident_id, @dengue_id, DATE_SUB(CURDATE(), INTERVAL 50 DAY),
       DATE_SUB(CURDATE(), INTERVAL 52 DAY), @prediction_staff_id,
       'Suspected', '[PRED-SEED-DIS-01] Fever and body pain', 'Pending',
       'Recovered', 'Supportive care', 'Closed', 1
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND @dengue_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM disease_cases WHERE symptoms LIKE '[PRED-SEED-DIS-01]%');

INSERT INTO disease_cases
    (resident_id, disease_id, case_date, onset_date, reported_by_id,
     case_classification, symptoms, laboratory_result, outcome, treatment,
     case_status, reported_to_doh)
SELECT @prediction_resident_id, @dengue_id, DATE_SUB(CURDATE(), INTERVAL 42 DAY),
       DATE_SUB(CURDATE(), INTERVAL 44 DAY), @prediction_staff_id,
       'Probable', '[PRED-SEED-DIS-02] Fever and rash', 'Pending',
       'Recovered', 'Supportive care', 'Closed', 1
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND @dengue_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM disease_cases WHERE symptoms LIKE '[PRED-SEED-DIS-02]%');

INSERT INTO disease_cases
    (resident_id, disease_id, case_date, onset_date, reported_by_id,
     case_classification, symptoms, laboratory_result, outcome, treatment,
     case_status, reported_to_doh)
SELECT @prediction_resident_id, @dengue_id, DATE_SUB(CURDATE(), INTERVAL 24 DAY),
       DATE_SUB(CURDATE(), INTERVAL 26 DAY), @prediction_staff_id,
       'Confirmed', '[PRED-SEED-DIS-03] Fever and thrombocytopenia', 'Positive',
       'Recovered', 'Hydration and monitoring', 'Closed', 1
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND @dengue_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM disease_cases WHERE symptoms LIKE '[PRED-SEED-DIS-03]%');

INSERT INTO disease_cases
    (resident_id, disease_id, case_date, onset_date, reported_by_id,
     case_classification, symptoms, laboratory_result, outcome, treatment,
     case_status, reported_to_doh)
SELECT @prediction_resident_id, @dengue_id, DATE_SUB(CURDATE(), INTERVAL 15 DAY),
       DATE_SUB(CURDATE(), INTERVAL 17 DAY), @prediction_staff_id,
       'Confirmed', '[PRED-SEED-DIS-04] Fever and body pain', 'Positive',
       'Recovered', 'Hydration and monitoring', 'Closed', 1
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND @dengue_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM disease_cases WHERE symptoms LIKE '[PRED-SEED-DIS-04]%');

INSERT INTO disease_cases
    (resident_id, disease_id, case_date, onset_date, reported_by_id,
     case_classification, symptoms, laboratory_result, outcome, treatment,
     case_status, reported_to_doh)
SELECT @prediction_resident_id, @dengue_id, DATE_SUB(CURDATE(), INTERVAL 7 DAY),
       DATE_SUB(CURDATE(), INTERVAL 9 DAY), @prediction_staff_id,
       'Probable', '[PRED-SEED-DIS-05] Persistent fever', 'Pending',
       NULL, 'Hydration and monitoring', 'Active', 1
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND @dengue_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM disease_cases WHERE symptoms LIKE '[PRED-SEED-DIS-05]%');

INSERT INTO disease_cases
    (resident_id, disease_id, case_date, onset_date, reported_by_id,
     case_classification, symptoms, laboratory_result, outcome, treatment,
     case_status, reported_to_doh)
SELECT @prediction_resident_id, @age_id, DATE_SUB(CURDATE(), INTERVAL 38 DAY),
       DATE_SUB(CURDATE(), INTERVAL 39 DAY), @prediction_staff_id,
       'Confirmed', '[PRED-SEED-DIS-06] Diarrhea and dehydration', 'Positive',
       'Recovered', 'ORS and zinc', 'Closed', 1
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND @age_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM disease_cases WHERE symptoms LIKE '[PRED-SEED-DIS-06]%');

INSERT INTO disease_cases
    (resident_id, disease_id, case_date, onset_date, reported_by_id,
     case_classification, symptoms, laboratory_result, outcome, treatment,
     case_status, reported_to_doh)
SELECT @prediction_resident_id, @age_id, DATE_SUB(CURDATE(), INTERVAL 12 DAY),
       DATE_SUB(CURDATE(), INTERVAL 13 DAY), @prediction_staff_id,
       'Confirmed', '[PRED-SEED-DIS-07] Diarrhea and dehydration', 'Positive',
       'Recovered', 'ORS and zinc', 'Closed', 1
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND @age_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM disease_cases WHERE symptoms LIKE '[PRED-SEED-DIS-07]%');

INSERT INTO disease_cases
    (resident_id, disease_id, case_date, onset_date, reported_by_id,
     case_classification, symptoms, laboratory_result, outcome, treatment,
     case_status, reported_to_doh)
SELECT @prediction_resident_id, @age_id, DATE_SUB(CURDATE(), INTERVAL 4 DAY),
       DATE_SUB(CURDATE(), INTERVAL 5 DAY), @prediction_staff_id,
       'Probable', '[PRED-SEED-DIS-08] Loose bowel movement', 'Pending',
       NULL, 'ORS and zinc', 'Active', 1
WHERE @prediction_resident_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND @age_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM disease_cases WHERE symptoms LIKE '[PRED-SEED-DIS-08]%');

-- -------------------------------------------------------------------------
-- 3. MEDICINE INVENTORY AND 30-DAY ISSUE HISTORY
-- Supplies stock and usage velocity for stockout-date predictions.
-- -------------------------------------------------------------------------

INSERT INTO medicine_inventory
    (generic_name, brand_name, dosage, unit_form, quantity_in_stock,
     reorder_level, supplier, unit_cost, expiry_date, batch_number)
SELECT 'Oral Rehydration Salts', 'RHU Generic', '20.5 g', 'Sachet', 12,
       60, 'DOH Supply', 8.00, DATE_ADD(CURDATE(), INTERVAL 18 MONTH),
       'PRED-SEED-ORS'
WHERE NOT EXISTS (SELECT 1 FROM medicine_inventory WHERE batch_number = 'PRED-SEED-ORS');

INSERT INTO medicine_inventory
    (generic_name, brand_name, dosage, unit_form, quantity_in_stock,
     reorder_level, supplier, unit_cost, expiry_date, batch_number)
SELECT 'Amoxicillin', 'RHU Generic', '500 mg', 'Capsule', 25,
       100, 'DOH Supply', 3.50, DATE_ADD(CURDATE(), INTERVAL 20 MONTH),
       'PRED-SEED-AMOX'
WHERE NOT EXISTS (SELECT 1 FROM medicine_inventory WHERE batch_number = 'PRED-SEED-AMOX');

INSERT INTO medicine_inventory
    (generic_name, brand_name, dosage, unit_form, quantity_in_stock,
     reorder_level, supplier, unit_cost, expiry_date, batch_number)
SELECT 'Amlodipine', 'RHU Generic', '5 mg', 'Tablet', 180,
       120, 'DOH Supply', 2.00, DATE_ADD(CURDATE(), INTERVAL 24 MONTH),
       'PRED-SEED-AMLO'
WHERE NOT EXISTS (SELECT 1 FROM medicine_inventory WHERE batch_number = 'PRED-SEED-AMLO');

-- Keep the tagged records inside the dashboard's critical/low thresholds even
-- when this idempotent seed is imported after an earlier version.
UPDATE medicine_inventory
SET quantity_in_stock = 12
WHERE batch_number = 'PRED-SEED-ORS';

UPDATE medicine_inventory
SET quantity_in_stock = 25
WHERE batch_number = 'PRED-SEED-AMOX';

SET @ors_id := (SELECT id FROM medicine_inventory WHERE batch_number = 'PRED-SEED-ORS' LIMIT 1);
SET @amox_id := (SELECT id FROM medicine_inventory WHERE batch_number = 'PRED-SEED-AMOX' LIMIT 1);
SET @amlo_id := (SELECT id FROM medicine_inventory WHERE batch_number = 'PRED-SEED-AMLO' LIMIT 1);

INSERT INTO stock_transactions
    (medicine_id, transaction_type, quantity, transaction_date,
     recorded_by_id, reason, notes)
SELECT @ors_id, 'OUT', 60, DATE_SUB(CURDATE(), INTERVAL 24 DAY),
       @prediction_staff_id, 'Dispensed to residents', '[PRED-SEED-STOCK-01]'
WHERE @ors_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM stock_transactions WHERE notes = '[PRED-SEED-STOCK-01]');

INSERT INTO stock_transactions
    (medicine_id, transaction_type, quantity, transaction_date,
     recorded_by_id, reason, notes)
SELECT @ors_id, 'OUT', 54, DATE_SUB(CURDATE(), INTERVAL 9 DAY),
       @prediction_staff_id, 'Dispensed to residents', '[PRED-SEED-STOCK-02]'
WHERE @ors_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM stock_transactions WHERE notes = '[PRED-SEED-STOCK-02]');

INSERT INTO stock_transactions
    (medicine_id, transaction_type, quantity, transaction_date,
     recorded_by_id, reason, notes)
SELECT @amox_id, 'OUT', 72, DATE_SUB(CURDATE(), INTERVAL 21 DAY),
       @prediction_staff_id, 'Dispensed to residents', '[PRED-SEED-STOCK-03]'
WHERE @amox_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM stock_transactions WHERE notes = '[PRED-SEED-STOCK-03]');

INSERT INTO stock_transactions
    (medicine_id, transaction_type, quantity, transaction_date,
     recorded_by_id, reason, notes)
SELECT @amox_id, 'OUT', 48, DATE_SUB(CURDATE(), INTERVAL 6 DAY),
       @prediction_staff_id, 'Dispensed to residents', '[PRED-SEED-STOCK-04]'
WHERE @amox_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM stock_transactions WHERE notes = '[PRED-SEED-STOCK-04]');

INSERT INTO stock_transactions
    (medicine_id, transaction_type, quantity, transaction_date,
     recorded_by_id, reason, notes)
SELECT @amlo_id, 'OUT', 45, DATE_SUB(CURDATE(), INTERVAL 20 DAY),
       @prediction_staff_id, 'Dispensed to residents', '[PRED-SEED-STOCK-05]'
WHERE @amlo_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM stock_transactions WHERE notes = '[PRED-SEED-STOCK-05]');

INSERT INTO stock_transactions
    (medicine_id, transaction_type, quantity, transaction_date,
     recorded_by_id, reason, notes)
SELECT @amlo_id, 'OUT', 35, DATE_SUB(CURDATE(), INTERVAL 5 DAY),
       @prediction_staff_id, 'Dispensed to residents', '[PRED-SEED-STOCK-06]'
WHERE @amlo_id IS NOT NULL AND @prediction_staff_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM stock_transactions WHERE notes = '[PRED-SEED-STOCK-06]');

COMMIT;

-- Verification result sets shown by phpMyAdmin/MySQL after import.
SELECT COUNT(*) AS seeded_consultations
FROM consultations
WHERE consultation_notes LIKE '[PRED-SEED-OPD-%';

SELECT COUNT(*) AS seeded_disease_cases
FROM disease_cases
WHERE symptoms LIKE '[PRED-SEED-DIS-%';

SELECT m.generic_name, m.quantity_in_stock,
       COALESCE(SUM(CASE WHEN st.transaction_type = 'OUT'
                         AND st.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    THEN st.quantity ELSE 0 END), 0) AS usage_last_30_days
FROM medicine_inventory m
LEFT JOIN stock_transactions st ON st.medicine_id = m.id
WHERE m.batch_number LIKE 'PRED-SEED-%'
GROUP BY m.id, m.generic_name, m.quantity_in_stock;

-- Optional cleanup (remove the leading "-- " only when you want to delete
-- all prediction seed records):
-- DELETE FROM stock_transactions WHERE notes LIKE '[PRED-SEED-STOCK-%';
-- DELETE FROM medicine_inventory WHERE batch_number LIKE 'PRED-SEED-%';
-- DELETE FROM disease_cases WHERE symptoms LIKE '[PRED-SEED-DIS-%';
-- DELETE FROM consultations WHERE consultation_notes LIKE '[PRED-SEED-OPD-%';
