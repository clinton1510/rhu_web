# RHU PROTOTYPE - COMPREHENSIVE DATABASE & DATA STRUCTURE ANALYSIS

**System**: Nasugbu Rural Health Unit Digital Health Portal  
**Type**: Multi-role Health Information Management System  
**Date**: July 2026  
**Status**: Current codebase analysis from mock data

---

## TABLE OF CONTENTS

1. [Database Type & Architecture](#database-type--architecture)
2. [Required Tables & Collections](#required-tables--collections)
3. [User Roles & Access Control](#user-roles--access-control)
4. [Data Relationships & Dependencies](#data-relationships--dependencies)
5. [Third-Party Services](#third-party-services)
6. [Authentication & Security](#authentication--security)
7. [API Endpoints Required](#api-endpoints-required)
8. [Data Flow Diagrams](#data-flow-diagrams)
9. [Implementation Priorities](#implementation-priorities)

---

## DATABASE TYPE & ARCHITECTURE

### Recommended Database: **PostgreSQL** (Primary)

**Why PostgreSQL?**
- Complex relationships between entities (patients, staff, consultations, tests)
- ACID compliance required for medical data integrity
- Transactional consistency for blood donation matching
- PostGIS extension for geospatial queries (donor location tracking)
- JSONB support for flexible structured data (vitals, lab results)
- Row-level security for HIPAA/RA 10173 compliance

**Alternatives Considered:**
- **MySQL/MariaDB**: Acceptable, but less robust for complex relationships
- **Firebase/Firestore**: Good for real-time notifications, poor for relational data integrity
- **Hybrid Approach**: PostgreSQL primary + Firebase for real-time sync

### Backend Framework Needed
- **Node.js + Express** OR **Django** OR **.NET Core**
- REST API architecture
- GraphQL layer (optional, for analytics)

**Not Currently Implemented**: 
- No API endpoints found in codebase (mock data only)
- All data is currently client-side mock data
- **CRITICAL**: Backend must be built

---

## REQUIRED TABLES & COLLECTIONS

### 1. AUTHENTICATION & USER MANAGEMENT

#### `users`
```sql
CREATE TABLE users (
  id UUID PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(20),
  full_name VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP,
  status ENUM('active', 'inactive', 'suspended')
);
```

#### `user_roles`
```sql
CREATE TABLE user_roles (
  id UUID PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES users(id),
  role VARCHAR(50) NOT NULL,  -- Enum values below
  assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_date TIMESTAMP,
  assigned_by UUID REFERENCES users(id),
  UNIQUE(user_id, role)
);
```

#### `login_attempts`
```sql
CREATE TABLE login_attempts (
  id UUID PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  ip_address INET NOT NULL,
  attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  success BOOLEAN,
  reason_for_failure VARCHAR(255)
);
```

#### `mfa_settings`
```sql
CREATE TABLE mfa_settings (
  id UUID PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES users(id),
  mfa_type ENUM('totp', 'sms', 'email'),
  secret VARCHAR(255),
  enabled BOOLEAN DEFAULT false,
  backup_codes TEXT[]
);
```

---

### 2. STAFF & PERSONNEL

#### `staff`
```sql
CREATE TABLE staff (
  id UUID PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES users(id),
  name VARCHAR(255) NOT NULL,
  position VARCHAR(100) NOT NULL,
  specialization VARCHAR(100),
  employment_type ENUM('plantilla', 'contractual', 'cas'),
  license_no VARCHAR(100),
  prc_expiry DATE NOT NULL,
  philhealth_accreditation VARCHAR(50),
  schedule VARCHAR(255),
  contact_no VARCHAR(20),
  email VARCHAR(255),
  status ENUM('active', 'inactive', 'on_leave')
);
```

**Positions Identified:**
- Medical Officer/Municipal Health Officer (MHO)
- Rural Health Physician
- Midwife
- Public Health Nurse
- Sanitary Inspector
- MedTech/Laboratory Technician
- Administrative Staff
- BHW Supervisor

#### `barangay_health_workers` (BHW)
```sql
CREATE TABLE barangay_health_workers (
  id UUID PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  barangay VARCHAR(100) NOT NULL,
  contact_no VARCHAR(20),
  years_of_service INT,
  training_level VARCHAR(50),
  active_status BOOLEAN DEFAULT true,
  households_assigned INT,
  donors_referred INT,
  immunization_coverage DECIMAL(5,2),
  maternal_cases_managed INT,
  last_training DATE,
  supervisor_id UUID REFERENCES staff(id)
);
```

#### `rhu_info`
```sql
CREATE TABLE rhu_info (
  id UUID PRIMARY KEY,
  name VARCHAR(255),
  code VARCHAR(50),
  municipality VARCHAR(100),
  province VARCHAR(100),
  region VARCHAR(100),
  address TEXT,
  contact_number VARCHAR(20),
  email VARCHAR(255),
  catchment_barangays TEXT[],
  total_population INT,
  staff_count INT,
  bhw_count INT,
  operating_hours VARCHAR(255),
  chief_mho_id UUID REFERENCES staff(id)
);
```

---

### 3. RESIDENT/PATIENT MANAGEMENT

#### `residents`
```sql
CREATE TABLE residents (
  id UUID PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  date_of_birth DATE NOT NULL,
  age INT GENERATED ALWAYS AS (EXTRACT(YEAR FROM age(date_of_birth))) STORED,
  gender ENUM('male', 'female', 'other'),
  blood_type VARCHAR(5),  -- A+, A-, B+, B-, AB+, AB-, O+, O-
  barangay VARCHAR(100) NOT NULL,
  philhealth_no VARCHAR(50),
  contact_no VARCHAR(20),
  email VARCHAR(255),
  verified BOOLEAN DEFAULT false,
  verified_date TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### `patient_admissions`
```sql
CREATE TABLE patient_admissions (
  id UUID PRIMARY KEY,
  resident_id UUID NOT NULL REFERENCES residents(id),
  admission_date TIMESTAMP NOT NULL,
  diagnosis TEXT NOT NULL,
  physician_id UUID NOT NULL REFERENCES staff(id),
  ward VARCHAR(100),
  status ENUM('admitted', 'discharged', 'observation'),
  discharge_date TIMESTAMP,
  discharge_summary TEXT
);
```

#### `referrals`
```sql
CREATE TABLE referrals (
  id UUID PRIMARY KEY,
  resident_id UUID NOT NULL REFERENCES residents(id),
  referring_md_id UUID NOT NULL REFERENCES staff(id),
  referred_to VARCHAR(255),  -- Hospital/facility name
  diagnosis TEXT,
  icd10_code VARCHAR(10),
  reason TEXT,
  urgency ENUM('critical', 'urgent', 'moderate', 'routine'),
  status ENUM('pending', 'accepted', 'completed', 'cancelled'),
  referral_date TIMESTAMP,
  feedback TEXT
);
```

---

### 4. BLOOD DONATION SYSTEM (CRITICAL)

#### `donors`
```sql
CREATE TABLE donors (
  id UUID PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  blood_type VARCHAR(5) NOT NULL,
  location_lat DECIMAL(10,8),
  location_lng DECIMAL(11,8),
  address TEXT,
  barangay VARCHAR(100),
  municipality VARCHAR(100),
  availability BOOLEAN DEFAULT true,
  donation_history INT DEFAULT 0,
  last_donation DATE,
  response_rate DECIMAL(5,2),  -- 0-100 percentage
  avg_response_time_minutes INT,
  cluster ENUM('reliable', 'moderate', 'new'),
  contact_no VARCHAR(20) NOT NULL,
  email VARCHAR(255),
  verified BOOLEAN DEFAULT false,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for spatial queries
CREATE INDEX idx_donors_location ON donors USING GIST (
  ll_to_earth(location_lat, location_lng)
);
CREATE INDEX idx_donors_blood_type ON donors(blood_type);
```

#### `blood_banks`
```sql
CREATE TABLE blood_banks (
  id UUID PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  location_lat DECIMAL(10,8),
  location_lng DECIMAL(11,8),
  address TEXT,
  municipality VARCHAR(100),
  contact_no VARCHAR(20),
  email VARCHAR(255),
  operating_hours_weekday VARCHAR(100),
  operating_hours_weekend VARCHAR(100),
  services TEXT[],
  verified BOOLEAN,
  rating DECIMAL(2,1)
);
```

#### `blood_bank_stock`
```sql
CREATE TABLE blood_bank_stock (
  id UUID PRIMARY KEY,
  blood_bank_id UUID NOT NULL REFERENCES blood_banks(id),
  blood_type VARCHAR(5) NOT NULL,
  units INT NOT NULL,
  status ENUM('critical', 'low', 'adequate', 'good'),
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expiry_date DATE,
  batch_no VARCHAR(100)
);
```

**Blood Type Compatibility Matrix (Stored Logic):**
```
O- → Can give to: O-, O+, A-, A+, B-, B+, AB-, AB+
O+ → Can give to: O+, A+, B+, AB+
A- → Can give to: A-, A+, AB-, AB+
A+ → Can give to: A+, AB+
B- → Can give to: B-, B+, AB-, AB+
B+ → Can give to: B+, AB+
AB- → Can give to: AB-, AB+
AB+ → Can give to: AB+
```

#### `blood_requests`
```sql
CREATE TABLE blood_requests (
  id UUID PRIMARY KEY,
  hospital_id VARCHAR(100) NOT NULL,
  hospital_name VARCHAR(255) NOT NULL,
  blood_type VARCHAR(5) NOT NULL,
  quantity INT NOT NULL,
  urgency ENUM('critical', 'urgent', 'moderate', 'scheduled'),
  location_lat DECIMAL(10,8),
  location_lng DECIMAL(11,8),
  address TEXT,
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  needed_by TIMESTAMP NOT NULL,
  status ENUM('pending', 'matching', 'fulfilled', 'expired', 'cancelled'),
  patient_info TEXT
);
```

#### `donor_matches` (ML Algorithm Output)
```sql
CREATE TABLE donor_matches (
  id UUID PRIMARY KEY,
  request_id UUID NOT NULL REFERENCES blood_requests(id),
  donor_id UUID NOT NULL REFERENCES donors(id),
  distance_km DECIMAL(8,2),
  eta_minutes INT,
  route_coordinates JSONB,  -- Array of [lat, lng] pairs
  response_probability DECIMAL(5,2),  -- ML-predicted 0-100
  composite_score DECIMAL(5,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  selected BOOLEAN DEFAULT false
);
```

**Matching Algorithm Inputs:**
- Geographic distance (haversine formula)
- Donor response probability (historical data + urgency level)
- Donor cluster reliability
- Time to reach location (ETA)
- Donor availability status
- Days since last donation (56-day rule)

#### `blood_transfusions`
```sql
CREATE TABLE blood_transfusions (
  id UUID PRIMARY KEY,
  resident_id UUID NOT NULL REFERENCES residents(id),
  blood_type VARCHAR(5) NOT NULL,
  units INT NOT NULL,
  component VARCHAR(50),  -- Packed RBC, FFP, Platelets, etc.
  transfusion_date TIMESTAMP NOT NULL,
  transfused_by UUID NOT NULL REFERENCES staff(id),
  nurse_id UUID REFERENCES staff(id),
  blood_bank_source VARCHAR(100),
  pre_transfusion_hemoglobin DECIMAL(4,1),
  post_transfusion_hemoglobin DECIMAL(4,1),
  reaction_observed VARCHAR(50),
  status ENUM('completed', 'adverse_event', 'cancelled')
);
```

#### `blood_drives`
```sql
CREATE TABLE blood_drives (
  id UUID PRIMARY KEY,
  title VARCHAR(255),
  date DATE NOT NULL,
  venue TEXT,
  organizer VARCHAR(255),
  target_units INT,
  registered_donors INT,
  units_collected INT,
  status ENUM('scheduled', 'ongoing', 'completed', 'cancelled'),
  barangays TEXT[],
  contact_person VARCHAR(100),
  notes TEXT
);
```

---

### 5. MATERNAL HEALTH & FAMILY PLANNING

#### `maternal_cases`
```sql
CREATE TABLE maternal_cases (
  id UUID PRIMARY KEY,
  resident_id UUID REFERENCES residents(id),
  name VARCHAR(255),
  age INT,
  barangay VARCHAR(100),
  gravida INT,  -- Number of pregnancies
  para INT,     -- Number of deliveries
  age_of_gestation VARCHAR(20),  -- e.g., "28 weeks"
  last_menstrual_period DATE,
  expected_delivery_date DATE,
  blood_type VARCHAR(5),
  prenatal_visits INT DEFAULT 0,
  risk_level ENUM('low', 'moderate', 'high'),
  risk_factors JSONB,
  delivery_plan VARCHAR(255),
  status ENUM('active_prenatal', 'postpartum', 'high_risk', 'lost_to_followup'),
  midwife_id UUID REFERENCES staff(id),
  philhealth_status ENUM('enrolled', 'pending', 'not_enrolled'),
  supplements TEXT[],
  lab_results JSONB,  -- HGB, hematocrit, urinalysis, VDRL, HBsAg, HIV
  last_visit TIMESTAMP,
  next_visit TIMESTAMP
);
```

#### `family_planning_clients`
```sql
CREATE TABLE family_planning_clients (
  id UUID PRIMARY KEY,
  resident_id UUID REFERENCES residents(id),
  name VARCHAR(255),
  age INT,
  barangay VARCHAR(100),
  method VARCHAR(100) NOT NULL,  -- Combined Oral Pills, DMPA, IUD, BTL, NSV, etc.
  start_date DATE,
  last_supply DATE,
  next_visit DATE,
  partner_status ENUM('married', 'single', 'widowed', 'separated'),
  children_count INT,
  acceptor_type ENUM('new', 'continuing', 'returning'),
  side_effects TEXT,
  counselor_id UUID REFERENCES staff(id),
  status ENUM('active', 'discontinued', 'overdue', 'transferred')
);
```

---

### 6. CHILD HEALTH & IMMUNIZATION

#### `immunization_records`
```sql
CREATE TABLE immunization_records (
  id UUID PRIMARY KEY,
  child_id UUID NOT NULL REFERENCES residents(id),
  child_name VARCHAR(255),
  mother_id UUID REFERENCES residents(id),
  mother_name VARCHAR(255),
  barangay VARCHAR(100),
  date_of_birth DATE NOT NULL,
  next_visit DATE,
  status ENUM('on_schedule', 'overdue', 'complete', 'incomplete'),
  bhw_id UUID REFERENCES barangay_health_workers(id)
);
```

#### `vaccination_records`
```sql
CREATE TABLE vaccination_records (
  id UUID PRIMARY KEY,
  immunization_id UUID NOT NULL REFERENCES immunization_records(id),
  vaccine_name VARCHAR(100) NOT NULL,  -- BCG, HepB, DPT-HepB-Hib, OPV, PCV, MMR, Varicella, etc.
  date_administered DATE,
  lot_number VARCHAR(100),
  status ENUM('given', 'due', 'missed', 'contraindicated', 'deferred'),
  administered_by VARCHAR(100)
);

-- DOH Vaccine Schedule (Reference):
-- 0 months: BCG, HepB (birth)
-- 2 months: DPT-HepB-Hib 1, OPV 1, PCV 1
-- 4 months: DPT-HepB-Hib 2, OPV 2, PCV 2
-- 6 months: DPT-HepB-Hib 3, OPV 3, PCV 3
-- 12 months: MMR, Varicella
-- 16-24 months: DPT-HepB-Hib Booster, OPV Booster
```

#### `nutrition_cases`
```sql
CREATE TABLE nutrition_cases (
  id UUID PRIMARY KEY,
  child_id UUID NOT NULL REFERENCES residents(id),
  name VARCHAR(255),
  age_months INT,
  gender ENUM('male', 'female'),
  barangay VARCHAR(100),
  mother_name VARCHAR(255),
  weight_kg DECIMAL(5,2),
  height_cm DECIMAL(5,2),
  muac_cm DECIMAL(4,2),  -- Mid-Upper Arm Circumference
  classification ENUM('normal', 'mam', 'sam'),  -- MAM: Moderate, SAM: Severe
  nutrition_status VARCHAR(100),
  interventions TEXT[],
  last_visit DATE,
  next_visit DATE,
  bhw_id UUID REFERENCES barangay_health_workers(id),
  program VARCHAR(100),  -- Operation Timbang Plus, etc.
  philhealth_coverage BOOLEAN
);
```

---

### 7. DISEASE SURVEILLANCE & EPIDEMIOLOGY

#### `disease_reports` (PIDSR)
```sql
CREATE TABLE disease_reports (
  id UUID PRIMARY KEY,
  disease_name VARCHAR(255) NOT NULL,
  icd10_code VARCHAR(10),
  reporting_week VARCHAR(20),  -- 2026-W23
  cases INT,
  deaths INT,
  affected_barangays TEXT[],
  age_groups JSONB,  -- {"0-4": 1, "5-14": 2, "15-49": 1, "50+": 0}
  action_taken TEXT,
  reported_by_id UUID REFERENCES staff(id),
  status ENUM('verified', 'under_investigation', 'unverified'),
  alert_flag BOOLEAN DEFAULT false,
  report_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Critical Diseases Tracked (from mock data):
-- Dengue Fever, Leptospirosis, Acute Bloody Diarrhea, Pneumonia, TB, Animal Bite
```

#### `tb_dots_cases`
```sql
CREATE TABLE tb_dots_cases (
  id UUID PRIMARY KEY,
  resident_id UUID REFERENCES residents(id),
  name VARCHAR(255),
  age INT,
  gender ENUM('male', 'female'),
  barangay VARCHAR(100),
  case_type ENUM('new', 'relapse', 'treatment_after_failure', 'treatment_after_lost_followup'),
  classification VARCHAR(255),  -- Pulmonary TB (Bacteriologically Confirmed), Extra-Pulmonary TB, etc.
  treatment_regimen VARCHAR(100),  -- Category I, II, etc.
  treatment_start_date DATE,
  phase ENUM('intensive', 'continuation'),
  months_completed INT,
  total_months INT,
  next_collection DATE,
  supporter_name VARCHAR(255),
  weight_kg DECIMAL(5,2),
  sputum_results JSONB,  -- Array of {date, result}
  outcome ENUM('on_treatment', 'treatment_completed', 'cured', 'treatment_failed', 'lost_to_followup', 'died'),
  adherence_percentage INT  -- 0-100
);
```

#### `sanitation_inspections`
```sql
CREATE TABLE sanitation_inspections (
  id UUID PRIMARY KEY,
  establishment_name VARCHAR(255),
  establishment_type VARCHAR(100),  -- Food Establishment, School Canteen, Public Market, etc.
  barangay VARCHAR(100),
  inspector_id UUID NOT NULL REFERENCES staff(id),
  inspection_date DATE,
  findings JSONB,
  violations_count INT,
  status ENUM('passed', 'conditional', 'failed'),
  next_inspection DATE,
  compliance_rate DECIMAL(5,2)  -- 0-100 percentage
);
```

---

### 8. CONSULTATIONS & DIAGNOSTICS

#### `opd_consultations`
```sql
CREATE TABLE opd_consultations (
  id UUID PRIMARY KEY,
  resident_id UUID NOT NULL REFERENCES residents(id),
  consultation_date DATE NOT NULL,
  chief_complaint TEXT,
  diagnosis TEXT,
  icd10_code VARCHAR(10),
  physician_id UUID NOT NULL REFERENCES staff(id),
  disposition ENUM('prescribed', 'referred', 'admitted', 'discharged'),
  philhealth_charged BOOLEAN,
  vitals JSONB,  -- {bp, temp, weight, rr, hr}
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### `opd_prescriptions`
```sql
CREATE TABLE opd_prescriptions (
  id UUID PRIMARY KEY,
  consultation_id UUID NOT NULL REFERENCES opd_consultations(id),
  medication_name VARCHAR(255),
  dosage VARCHAR(100),
  frequency VARCHAR(100),
  duration VARCHAR(100),
  qty_dispensed INT
);
```

#### `medical_tests`
```sql
CREATE TABLE medical_tests (
  id UUID PRIMARY KEY,
  resident_id UUID NOT NULL REFERENCES residents(id),
  test_name VARCHAR(255),
  test_date DATE,
  test_result TEXT,
  reference_range VARCHAR(100),
  performed_by UUID REFERENCES staff(id),
  status ENUM('completed', 'pending', 'cancelled')
);
```

---

### 9. INVENTORY & SUPPLIES

#### `medicine_inventory`
```sql
CREATE TABLE medicine_inventory (
  id UUID PRIMARY KEY,
  generic_name VARCHAR(255),
  brand_name VARCHAR(255),
  form VARCHAR(100),  -- Tablet, Capsule, Liquid, Vial, etc.
  current_stock INT,
  unit VARCHAR(50),  -- tablets, capsules, vials, mL, etc.
  reorder_level INT,
  category VARCHAR(100),  -- Antibiotic, Analgesic, Vitamin, Vaccine, etc.
  expiry_date DATE,
  batch_no VARCHAR(100),
  source VARCHAR(100),  -- DOH-CHD, NTP-CHD, etc.
  unit_cost DECIMAL(10,2),
  status ENUM('critical', 'low', 'adequate', 'good', 'overstocked'),
  usage_30_days INT,
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### `rhu_blood_inventory`
```sql
CREATE TABLE rhu_blood_inventory (
  id UUID PRIMARY KEY,
  blood_type VARCHAR(5) NOT NULL,
  units INT,
  status ENUM('critical', 'low', 'adequate', 'good'),
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expiry_date DATE,
  source VARCHAR(100)
);
```

---

### 10. VITAL STATISTICS

#### `vital_records`
```sql
CREATE TABLE vital_records (
  id UUID PRIMARY KEY,
  vital_type ENUM('birth', 'death', 'fetal_death'),
  person_name VARCHAR(255),
  date_of_vital_event DATE,
  barangay VARCHAR(100),
  details JSONB,  -- Mother/father names, birth weight, APGAR, cause of death, etc.
  attendant_id UUID REFERENCES staff(id),
  registration_status ENUM('registered', 'pending', 'not_registered'),
  vital_cert_no VARCHAR(100),  -- Birth/Death Certificate Number
  remarks TEXT
);
```

---

### 11. HEALTH CERTIFICATES

#### `health_certificates`
```sql
CREATE TABLE health_certificates (
  id UUID PRIMARY KEY,
  certificate_type VARCHAR(100),  -- Medical Certificate, Health Certificate, Birth Certificate, etc.
  recipient_name VARCHAR(255),
  age INT,
  barangay VARCHAR(100),
  purpose VARCHAR(255),  -- Employment, Food Handler, Driver's License, Business Permit, etc.
  issued_by_id UUID NOT NULL REFERENCES staff(id),
  issued_date DATE,
  valid_until DATE,
  certificate_no VARCHAR(100) UNIQUE,
  fee DECIMAL(10,2),
  status ENUM('issued', 'cancelled', 'expired'),
  findings TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

### 12. DOH REPORTING & COMPLIANCE

#### `doh_reports`
```sql
CREATE TABLE doh_reports (
  id UUID PRIMARY KEY,
  report_type VARCHAR(100),  -- FHSIS, PIDSR, NTP, EPI, etc.
  period VARCHAR(50),  -- May 2026, Week 23, Q1 2026, etc.
  submitted_by_id UUID NOT NULL REFERENCES staff(id),
  submitted_date TIMESTAMP,
  status ENUM('pending', 'submitted', 'accepted', 'rejected', 'revised'),
  accepted_by VARCHAR(100),  -- CHD IV-A, RESU, etc.
  modules TEXT[],  -- Which modules included: OPD, Maternal, FP, TB, etc.
  remarks TEXT,
  due_date DATE
);
```

#### `audit_logs`
```sql
CREATE TABLE audit_logs (
  id UUID PRIMARY KEY,
  user_id UUID NOT NULL REFERENCES users(id),
  action VARCHAR(255),  -- login, view_record, export_report, create_referral, etc.
  module VARCHAR(100),  -- Authentication, OPD, Blood, Maternal, etc.
  resource_id VARCHAR(255),
  old_values JSONB,
  new_values JSONB,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ip_address INET,
  status ENUM('success', 'failed'),
  reason_if_failed VARCHAR(255)
);
```

---

### 13. GEOGRAPHIC/LOCATION DATA

#### `barangays`
```sql
CREATE TABLE barangays (
  id UUID PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  municipality VARCHAR(100) NOT NULL,
  province VARCHAR(100),
  population INT,
  location_lat DECIMAL(10,8),
  location_lng DECIMAL(11,8)
);

-- Catchment Barangays for Nasugbu RHU I:
-- Halang, Mabini, San Jose, Poblacion, Kumintang Ilaya, 
-- Kumintang Ibaba, Alangilan, Bolbok
```

#### `hospitals`
```sql
CREATE TABLE hospitals (
  id UUID PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  address TEXT,
  municipality VARCHAR(100),
  barangay VARCHAR(100),
  location_lat DECIMAL(10,8),
  location_lng DECIMAL(11,8),
  contact_no VARCHAR(20),
  email VARCHAR(255),
  type ENUM('primary', 'secondary', 'tertiary', 'specialty')
);
```

---

## USER ROLES & ACCESS CONTROL

### Role-Based Access Control (RBAC)

```sql
CREATE TYPE user_role AS ENUM (
  'resident',
  'bhw',
  'midwife',
  'nurse',
  'physician',
  'medtech',
  'sanitary_inspector',
  'admin_staff',
  'rhu_admin',
  'super_admin'
);
```

### Role Permissions Matrix

| Role | View Own Records | View Patients | Create Consultation | Manage Donors | Manage Staff | Create Reports | System Admin |
|------|---|---|---|---|---|---|---|
| **RESIDENT** | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **BHW** | ✓ | ✓ (catchment) | ✗ | ✓ | ✗ | ✗ | ✗ |
| **MIDWIFE** | ✓ | ✓ | ✓ | ✗ | ✗ | ✓ (maternal) | ✗ |
| **NURSE** | ✓ | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ |
| **PHYSICIAN** | ✓ | ✓ | ✓ | ✗ | ✗ | ✓ | ✗ |
| **MEDTECH** | ✓ | ✓ | ✓ (tests) | ✗ | ✗ | ✓ (lab) | ✗ |
| **SANITARY INSPECTOR** | ✓ | ✓ | ✓ (health certs) | ✗ | ✗ | ✓ (sanitation) | ✗ |
| **ADMIN STAFF** | ✓ | ✓ | ✓ (registration) | ✗ | ✗ | ✗ | ✗ |
| **RHU ADMIN** | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ | ✓ |
| **SUPER ADMIN** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Critical Access Controls

1. **Row-Level Security (RLS)**
   - Residents: Can only access own records
   - BHW: Can only access residents in their barangay
   - Staff: Role-based filtering applied

2. **Data Privacy Requirements (RA 10173)**
   - Patient identifiers encrypted
   - Access logging mandatory
   - De-identification for research purposes
   - Right to data access/deletion

---

## DATA RELATIONSHIPS & DEPENDENCIES

### Entity Relationship Diagram (ERD Summary)

```
┌─────────────────────────────────────────────────────────────┐
│                      USERS & AUTH                           │
│  users ──┬──> user_roles                                    │
│          ├──> login_attempts                                │
│          └──> mfa_settings                                  │
└─────────────────────────────────────────────────────────────┘
           │
           ├────────────────┬──────────────────┬───────────────┐
           │                │                  │               │
┌──────────▼────────┐  ┌───▼──────────┐  ┌───▼──────────┐  ┌─▼──────────┐
│  STAFF            │  │ RHU_INFO     │  │ BHW          │  │ AUDIT_LOGS │
│  (Physicians,     │  │              │  │              │  │            │
│   Nurses, etc)    │  └──────────────┘  └──────────────┘  └────────────┘
└───────────────────┘

┌──────────────────────────────────────────────────────────────┐
│                      RESIDENTS/PATIENTS                       │
│  residents ──┬──> patient_admissions                         │
│              ├──> opd_consultations ──> opd_prescriptions   │
│              ├──> referrals                                 │
│              ├──> immunization_records ──> vaccination_records
│              ├──> maternal_cases                            │
│              ├──> family_planning_clients                   │
│              ├──> tb_dots_cases                             │
│              ├──> nutrition_cases                           │
│              ├──> vital_records                             │
│              ├──> health_certificates                       │
│              └──> blood_transfusions                        │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│                    BLOOD DONATION SYSTEM                      │
│  blood_requests ──┬──> donor_matches ──> donors             │
│                   │                                          │
│  blood_banks ──┬──> blood_bank_stock                        │
│                │                                            │
│  blood_drives ──> (collected units linked to donors)        │
│                                                              │
│  blood_transfusions ──> residents (patient receiving)       │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│              DISEASE SURVEILLANCE & EPIDEMIOLOGY              │
│  disease_reports (PIDSR)                                     │
│  tb_dots_cases                                               │
│  sanitation_inspections                                      │
│  medical_tests                                               │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│            DOH REPORTING & INVENTORY MANAGEMENT               │
│  doh_reports                                                 │
│  medicine_inventory                                          │
│  rhu_blood_inventory                                         │
└──────────────────────────────────────────────────────────────┘
```

### Critical Data Dependencies

1. **Blood Donation System** (Most Complex)
   - Donor matching requires:
     - Blood type compatibility matrix
     - Geographic distance (haversine)
     - Historical response data
     - ETA calculation
     - Real-time availability status
   
2. **Immunization Program**
   - Depends on: Resident age → DOH schedule → Vaccine availability
   - Output triggers: SMS reminders to parents/BHWs

3. **Maternal Health**
   - Requires: Careful tracking of pregnancy timeline
   - Output: Referral generation when high-risk indicators detected

4. **TB-DOTS**
   - Mandatory: BHW supporter assignment & adherence monitoring
   - Critical: Sputum test result tracking for treatment outcome

---

## THIRD-PARTY SERVICES

### Currently Used

1. **Google reCAPTCHA v2**
   - Purpose: Bot protection on login forms
   - Implemented in: All login pages
   - Site Key: `6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI` (TEST KEY - MUST BE REPLACED)
   - Backend: Requires secret key for server-side verification

2. **Leaflet.js + React-Leaflet**
   - Purpose: Map visualization for donor locations
   - Features: Geospatial queries, route display

3. **Recharts**
   - Purpose: Data visualization & analytics
   - Uses: Blood demand forecasting charts, PIDSR trends

4. **React Hook Form**
   - Purpose: Form state management
   - All registration & login forms

5. **Shadcn/ui + Tailwind CSS**
   - Purpose: UI component library
   - All page layouts

6. **Sonner**
   - Purpose: Toast notifications
   - Real-time alerts

### Recommended Additions

| Service | Purpose | Why Needed |
|---------|---------|-----------|
| **Twilio** | SMS notifications | Appointment reminders, urgent alerts |
| **SendGrid/AWS SES** | Email service | Referral letters, certificates download links |
| **AWS S3 / Google Cloud Storage** | File storage | Certificate PDFs, health records documents |
| **Auth0 / Firebase Auth** | Advanced auth | SSO, passwordless login, MFA |
| **PostGIS** | Geospatial queries | Distance calculations, heat maps |
| **Sentry** | Error tracking | Production debugging |
| **DataDog / New Relic** | APM monitoring | Performance tracking |

---

## AUTHENTICATION & SECURITY

### Security Architecture

```
┌─────────────────┐
│  LOGIN PAGE     │
│  (reCAPTCHA)    │
└────────┬────────┘
         │
         ▼
┌──────────────────────────┐
│  Server-side reCAPTCHA   │
│  verification            │
└────────┬─────────────────┘
         │
         ▼
┌──────────────────────────┐
│  Hash password check     │
│  against database        │
└────────┬─────────────────┘
         │
         ▼
┌──────────────────────────┐
│  MFA Challenge           │
│  (if admin/staff)        │
├──────────────────────────┤
│  TOTP App / SMS Code     │
└────────┬─────────────────┘
         │
         ▼
┌──────────────────────────┐
│  JWT Token Issued        │
│  (includes role/perms)   │
└──────────────────────────┘
```

### Security Requirements

**Authentication:**
- Password hashing: bcrypt (minimum 12 rounds)
- Session management: JWT tokens with 1-hour expiry
- MFA: TOTP or SMS for admin accounts
- reCAPTCHA v2 on all login forms

**Authorization:**
- Role-Based Access Control (RBAC)
- Row-Level Security (RLS) for patient data
- Field-level encryption for sensitive fields

**Data Protection:**
- Encryption at rest: AES-256
- Encryption in transit: TLS 1.3
- Database backups: Daily encrypted snapshots

**Compliance:**
- RA 10173 (Data Privacy Act) compliance
- Audit logging of all data access
- 90-day retention for audit logs (configurable)
- HIPAA-equivalent controls

**Login Policies:**
- Account lockout: 5 failed attempts → 15 minute lockout
- Session timeout: 30 minutes of inactivity
- Password requirements: 
  - Minimum 8 characters
  - Must include uppercase, lowercase, number, symbol

---

## API ENDPOINTS REQUIRED

### Authentication Endpoints

```
POST /api/auth/login
  Input: { email, password, recaptchaToken }
  Output: { accessToken, user, mfaRequired }

POST /api/auth/mfa/verify
  Input: { mfaCode }
  Output: { accessToken, user }

POST /api/auth/logout
  Output: { success }

POST /api/auth/register
  Input: { email, password, phone, fullName, role }
  Output: { user, emailVerificationSent }
```

### Resident/Patient Endpoints

```
GET /api/residents/:id/dashboard
  Returns: Consultations, upcoming appointments, certificates

GET /api/residents/:id/consultations
  Returns: OPD history with diagnoses, prescriptions

GET /api/residents/:id/immunization-card
  Returns: Vaccination status, schedule, due vaccines

GET /api/residents/:id/certificates
  Returns: List of issued certificates (downloadable PDFs)

POST /api/residents/:id/request-certificate
  Input: { certificateType, purpose }
  Output: { requestId, estimatedReadyDate }

GET /api/residents/:id/referrals
  Returns: Active and completed referrals
```

### Blood Donation Endpoints

```
POST /api/donors/register
  Input: { name, bloodType, location, contactNo, email }
  Output: { donorId, verified }

GET /api/blood-requests/active
  Returns: List of active blood requests

GET /api/blood-requests/:requestId/matches
  Returns: Ranked list of donor matches

POST /api/donors/:donorId/respond
  Input: { requestId, response }  -- 'accept' or 'decline'
  Output: { success, navigation }

GET /api/blood-banks/nearby
  Input: { lat, lng, radiusKm }
  Returns: Nearby blood banks with stock info

GET /api/blood-inventory/rhu
  Returns: Current RHU blood stock by type
```

### Clinical Endpoints

```
POST /api/consultations
  Input: { patientId, chiefComplaint, diagnosis, vitals, medications }
  Output: { consultationId, referralGenerated }

POST /api/referrals
  Input: { patientId, referredTo, urgency, reason }
  Output: { referralId }

POST /api/immunization/record
  Input: { childId, vaccineGiven, date, lotNo }
  Output: { success, nextDueVaccines }

POST /api/maternal/record
  Input: { residentsId, aog, risks, nextVisitDate }
  Output: { caseId, alertsGenerated }
```

### Disease Surveillance Endpoints

```
POST /api/disease-reports/submit
  Input: { disease, cases, deaths, barangays, week }
  Output: { reportId }

POST /api/tb-dots/case
  Input: { patientId, treatmentRegimen, startDate }
  Output: { caseId }

GET /api/disease-surveillance/dashboard
  Returns: Active diseases, clusters, alerts
```

### Reporting Endpoints

```
GET /api/reports/fhsis/monthly/:period
  Returns: Monthly health facility statistics

POST /api/reports/fhsis/submit
  Input: { period, moduleData }
  Output: { success, submissionId }

GET /api/reports/analytics/blood
  Returns: Blood demand trends, matching success rate

GET /api/reports/analytics/maternal
  Returns: Maternal health indicators, referral patterns
```

### Admin Endpoints

```
GET /api/admin/staff
  Returns: All staff with credentials expiry dates

POST /api/admin/staff
  Input: { staffData }
  Output: { staffId }

GET /api/admin/audit-logs
  Returns: All audit logs (filtered by date/user)

POST /api/admin/users/:userId/role
  Input: { role }
  Output: { success }
```

---

## DATA FLOW DIAGRAMS

### Blood Donation Workflow

```
BHW registers Donor
         │
         ▼
┌─────────────────┐
│  Donor Profile  │
│  Created        │
└────────┬────────┘
         │
    Hospital needs blood
         │
         ▼
┌─────────────────────────┐
│  Blood Request          │
│  Created & Broadcast    │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  Donor Matching Algorithm           │
│  - Blood type compatibility         │
│  - Geographic distance (haversine)  │
│  - ETA calculation                  │
│  - Response probability (ML)        │
│  - Composite scoring                │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────┐
│  Ranked matches     │
│  presented to BHW   │
└────────┬────────────┘
         │
    BHW contacts donor
         │
         ▼
┌───────────────────────┐
│  Donor accepts        │
│  or declines          │
└────────┬──────────────┘
         │
         ├─ Accepted ──────────► Blood collected ──► Transfusion
         │
         └─ Declined ──────────► Next donor contacted
```

### Immunization Workflow

```
Child born
     │
     ▼
Resident registered
     │
     ▼
Immunization record created
     │
     ▼
BCG & HepB given (birth)
     │
     ▼
Next vaccine due (2 months)
     │
     ▼
BHW notifies parent
     │
     ├─ Vaccine given ─► Record updated
     │
     └─ Missed ─────────► Overdue alert generated
```

### Maternal Health Workflow

```
Pregnant woman reports
          │
          ▼
Prenatal case created
          │
          ▼
Risk assessment
    /    |    \
   /     |     \
Low   Moderate  High
Risk   Risk     Risk
  │      │       │
  ▼      ▼       ▼
Monitor Monthly Monthly Monitor
every 4 wk monitoring + alerts + Specialist
                      Referral
          │
          ▼
    Delivery Plan
    /     |     \
RHU   Hospital  Higher
                Center
```

### TB-DOTS Workflow

```
Patient suspects TB
          │
          ▼
Sputum test → GeneXpert
          │
    ┌─────┴─────┐
    │           │
Positive     Negative
    │
    ▼
DOTS therapy started
    │
    ├─ BHW assigned
    ├─ Adherence monitoring
    │
    ▼
Monthly sputum tests
    │
    ├─ Negative (month 2) ─► Continue therapy
    │
    └─ Positive (month 4) ─► Drug resistance workup
                                      │
                                      ▼
                                  MDR-TB Center
```

---

## IMPLEMENTATION PRIORITIES

### Phase 1: Critical Infrastructure (MVP - 4-6 weeks)

**Must Have:**
1. ✅ Residents table & login
2. ✅ Staff management
3. ✅ Blood donation system (donors, requests, matching)
4. ✅ OPD consultations (basic)
5. ✅ Authentication & RBAC
6. ✅ reCAPTCHA integration (backend verification)
7. ✅ Audit logging

**Not in MVP:**
- Advanced analytics
- ML-based demand forecasting
- Mobile app
- SMS notifications

### Phase 2: Core Health Programs (6-10 weeks)

1. Immunization tracking
2. Maternal health case management
3. TB-DOTS tracking
4. Disease surveillance (PIDSR)
5. Vital statistics
6. Health certificate generation

### Phase 3: Advanced Features (10-16 weeks)

1. Demand forecasting (Prophet/ARIMA)
2. Donor behavior clustering (K-Means)
3. SMS/Email notifications
4. Advanced analytics dashboard
5. DOH report automation
6. Mobile app (React Native/Flutter)

### Phase 4: Optimization & Compliance (Ongoing)

1. Performance optimization
2. Security audits
3. RA 10173 compliance verification
4. User acceptance testing (UAT)
5. Staff training
6. Go-live support

---

## SCHEMA DEPENDENCIES CHECKLIST

- [ ] PostgreSQL 14+ instance provisioned
- [ ] PostGIS extension installed (for geospatial queries)
- [ ] All tables created with proper indexes
- [ ] Foreign key constraints established
- [ ] Row-level security policies defined
- [ ] Audit triggers created
- [ ] Backup strategy implemented
- [ ] Connection pooling configured (PgBouncer)
- [ ] Regular expression functions for validation
- [ ] Full-text search indexes (for patient search)

---

## REFERENCES

### DOH Standards Referenced
- RA 10173 (Data Privacy Act)
- DOH Administrative Order on FHSIS
- National Immunization Schedule
- DOTS Strategy (WHO/DOH)
- PIDSR Guidelines

### Key Data Points from Codebase
- **RHU Location**: Nasugbu, Batangas
- **Catchment Barangays**: 8 (Halang, Mabini, San Jose, Poblacion, Kumintang Ilaya, Kumintang Ibaba, Alangilan, Bolbok)
- **Estimated Population**: 48,230
- **Blood Types Tracked**: All 8 types (A+, A-, B+, B-, AB+, AB-, O+, O-)
- **Disease Types Monitored**: 6+ (Dengue, TB, Leptospirosis, etc.)
- **Certificate Types**: 7+ (Medical, Health, Birth, Death, Business, Food Handler, etc.)

---

**Document Version**: 1.0  
**Last Updated**: July 15, 2026  
**Status**: Analysis Complete - Ready for Development Planning
