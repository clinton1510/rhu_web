-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 25, 2026 at 01:46 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rhu`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `timestamp`) VALUES
(1, 7, 'RHU staff login', 'staff', 1, NULL, NULL, '::1', '2026-07-24 09:40:31'),
(2, 8, 'RHU staff login', 'staff', 2, NULL, NULL, '::1', '2026-07-24 10:57:14'),
(3, 10, 'RHU Admin Login (2FA MFA Completed)', 'users', 10, NULL, NULL, '::1', '2026-07-24 15:13:42'),
(4, 10, 'RHU Admin Login (2FA MFA Completed)', 'users', 10, NULL, NULL, '::1', '2026-07-24 15:16:03'),
(5, 12, 'Initial RHU Admin Account Registered (Code: MD-12345-6789)', 'users', 12, NULL, NULL, '::1', '2026-07-24 15:42:33'),
(6, 12, 'RHU Admin Login (Strict 2FA Verified)', 'users', 12, NULL, NULL, '::1', '2026-07-24 16:06:20'),
(7, 12, 'Created new Healthcare Staff Account for 23-77766@g.batstate-u.edu.ph (MEDTECH)', 'staff', 0, NULL, NULL, '::1', '2026-07-24 17:10:43'),
(8, 12, 'RHU Admin Login (Strict 2FA Verified)', 'users', 12, NULL, NULL, '::1', '2026-07-25 02:30:04'),
(9, 13, 'RHU staff login', 'staff', 4, NULL, NULL, '::1', '2026-07-25 02:53:36'),
(10, 12, 'RHU Admin Login (Strict 2FA Verified)', 'users', 12, NULL, NULL, '::1', '2026-07-25 02:54:35'),
(11, NULL, 'Created new Healthcare Staff Account for vince@gmail.com (NURSE)', 'staff', 0, NULL, NULL, '::1', '2026-07-25 03:04:56'),
(12, 15, 'RHU staff login', 'staff', 5, NULL, NULL, '::1', '2026-07-25 03:05:20'),
(13, 12, 'RHU Admin Login (Strict 2FA Verified)', 'users', 12, NULL, NULL, '::1', '2026-07-25 10:37:53'),
(14, 12, 'RHU Admin Login (Strict 2FA Verified)', 'users', 12, NULL, NULL, '::1', '2026-07-25 10:44:32'),
(15, 12, 'Replied to Resident Message #2', 'messages', 2, NULL, NULL, '::1', '2026-07-25 10:45:32'),
(16, NULL, 'Replied to Resident Message #2', 'messages', 2, NULL, NULL, '::1', '2026-07-25 10:47:01'),
(17, 16, 'Initial RHU Admin Account Registered (Code: MD-12345-6789)', 'users', 16, NULL, NULL, '::1', '2026-07-25 11:07:45'),
(18, 16, 'RHU Admin Login (Strict 2FA Verified)', 'users', 16, NULL, NULL, '::1', '2026-07-25 11:08:25'),
(19, 16, 'Created Health Event: Women Event', 'portal_events', 1, NULL, NULL, '::1', '2026-07-25 11:17:17');

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `municipality` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `population` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`id`, `name`, `municipality`, `province`, `latitude`, `longitude`, `population`, `created_at`) VALUES
(1, 'Aga', 'Nasugbu', 'Batangas', 13.89050000, 120.98420000, 2100, '2026-07-15 10:36:42'),
(2, 'Balaytiguebalok-Balok', 'Nasugbu', 'Batangas', 13.88670000, 120.99150000, 1850, '2026-07-15 10:36:42'),
(3, 'Banilad', 'Nasugbu', 'Batangas', 13.86540000, 120.97230000, 2300, '2026-07-15 10:36:42'),
(4, 'Barangay 1 (Pob.)', 'Nasugbu', 'Batangas', 13.85420000, 120.96340000, 3500, '2026-07-15 10:36:42'),
(5, 'Barangay 2 (Pob.)', 'Nasugbu', 'Batangas', 13.85580000, 120.96120000, 3200, '2026-07-15 10:36:42'),
(6, 'Barangay 3 (Pob.)', 'Nasugbu', 'Batangas', 13.85740000, 120.95900000, 2900, '2026-07-15 10:36:42'),
(7, 'Barangay 4 (Pob.)', 'Nasugbu', 'Batangas', 13.85900000, 120.95680000, 3100, '2026-07-15 10:36:42'),
(8, 'Barangay 5 (Pob.)', 'Nasugbu', 'Batangas', 13.86060000, 120.95460000, 2800, '2026-07-15 10:36:42'),
(9, 'Barangay 6 (Pob.)', 'Nasugbu', 'Batangas', 13.86220000, 120.95240000, 3000, '2026-07-15 10:36:42'),
(10, 'Barangay 7 (Pob.)', 'Nasugbu', 'Batangas', 13.86380000, 120.95020000, 2700, '2026-07-15 10:36:42'),
(11, 'Barangay 8 (Pob.)', 'Nasugbu', 'Batangas', 13.86540000, 120.94800000, 2600, '2026-07-15 10:36:42'),
(12, 'Barangay 9 (Pob.)', 'Nasugbu', 'Batangas', 13.86700000, 120.94580000, 2500, '2026-07-15 10:36:42'),
(13, 'Barangay 10 (Pob.)', 'Nasugbu', 'Batangas', 13.86860000, 120.94360000, 2400, '2026-07-15 10:36:42'),
(14, 'Barangay 11 (Pob.)', 'Nasugbu', 'Batangas', 13.87020000, 120.94140000, 2300, '2026-07-15 10:36:42'),
(15, 'Barangay 12 (Pob.)', 'Nasugbu', 'Batangas', 13.87180000, 120.93920000, 2200, '2026-07-15 10:36:42'),
(16, 'Bilaran', 'Nasugbu', 'Batangas', 13.85340000, 120.98890000, 1950, '2026-07-15 10:36:42'),
(17, 'Bucana', 'Nasugbu', 'Batangas', 13.87230000, 120.95670000, 2050, '2026-07-15 10:36:42'),
(18, 'Bulihan', 'Nasugbu', 'Batangas', 13.84120000, 120.95010000, 1880, '2026-07-15 10:36:42'),
(19, 'Bunducan', 'Nasugbu', 'Batangas', 13.82980000, 120.94560000, 2200, '2026-07-15 10:36:42'),
(20, 'Butucan', 'Nasugbu', 'Batangas', 13.81870000, 120.94120000, 1750, '2026-07-15 10:36:42'),
(21, 'Calayo', 'Nasugbu', 'Batangas', 13.80760000, 120.93680000, 2100, '2026-07-15 10:36:42'),
(22, 'Catandaan', 'Nasugbu', 'Batangas', 13.79650000, 120.93240000, 1900, '2026-07-15 10:36:42'),
(23, 'Cagunan', 'Nasugbu', 'Batangas', 13.78540000, 120.92800000, 2000, '2026-07-15 10:36:42'),
(24, 'Dayap', 'Nasugbu', 'Batangas', 13.77430000, 120.92360000, 1650, '2026-07-15 10:36:42'),
(25, 'Kaylaway', 'Nasugbu', 'Batangas', 13.89450000, 120.97560000, 1800, '2026-07-15 10:36:42'),
(26, 'Kayrillaw', 'Nasugbu', 'Batangas', 13.90350000, 120.98230000, 1700, '2026-07-15 10:36:42'),
(27, 'Latag', 'Nasugbu', 'Batangas', 13.86320000, 120.99450000, 2250, '2026-07-15 10:36:42'),
(28, 'Looc', 'Nasugbu', 'Batangas', 13.87230000, 120.98670000, 1900, '2026-07-15 10:36:42'),
(29, 'Lumbangan', 'Nasugbu', 'Batangas', 13.88140000, 120.97890000, 2100, '2026-07-15 10:36:42'),
(30, 'Malapad na Bato', 'Nasugbu', 'Batangas', 13.89050000, 120.97110000, 1850, '2026-07-15 10:36:42'),
(31, 'Mataas na Pulo', 'Nasugbu', 'Batangas', 13.89960000, 120.96330000, 1600, '2026-07-15 10:36:42'),
(32, 'Maugat', 'Nasugbu', 'Batangas', 13.90870000, 120.95550000, 1750, '2026-07-15 10:36:42'),
(33, 'Munting Indang', 'Nasugbu', 'Batangas', 13.85410000, 120.97780000, 2000, '2026-07-15 10:36:42'),
(34, 'Natipuan', 'Nasugbu', 'Batangas', 13.84320000, 120.98340000, 1850, '2026-07-15 10:36:42'),
(35, 'Pantalan', 'Nasugbu', 'Batangas', 13.83230000, 120.98900000, 1700, '2026-07-15 10:36:42'),
(36, 'Papaya', 'Nasugbu', 'Batangas', 13.82140000, 120.99460000, 1900, '2026-07-15 10:36:42'),
(37, 'Putat', 'Nasugbu', 'Batangas', 13.81050000, 121.00020000, 1800, '2026-07-15 10:36:42'),
(38, 'Reparo', 'Nasugbu', 'Batangas', 13.79960000, 121.00580000, 2100, '2026-07-15 10:36:42'),
(39, 'Talangan', 'Nasugbu', 'Batangas', 13.78870000, 121.01140000, 1950, '2026-07-15 10:36:42'),
(40, 'Tumalim', 'Nasugbu', 'Batangas', 13.77780000, 121.01700000, 1850, '2026-07-15 10:36:42'),
(41, 'Utod', 'Nasugbu', 'Batangas', 13.76690000, 121.02260000, 1700, '2026-07-15 10:36:42'),
(42, 'Wawa', 'Nasugbu', 'Batangas', 13.75600000, 121.02820000, 1600, '2026-07-15 10:36:42');

-- --------------------------------------------------------

--
-- Table structure for table `bhw`
--

CREATE TABLE `bhw` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` int(11) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `coverage_population` int(11) DEFAULT NULL,
  `coverage_area` decimal(10,2) DEFAULT NULL,
  `assigned_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bhw`
--

INSERT INTO `bhw` (`id`, `staff_id`, `barangay`, `coverage_population`, `coverage_area`, `assigned_date`) VALUES
(1, 1, 'BHW-NAS-1960-0976', 93500, 0.00, '2026-07-24'),
(2, 0, 'Wawa', 93500, 0.00, '2026-07-24'),
(5, 0, 'Bucana', 93500, 0.00, '2026-07-24');

-- --------------------------------------------------------

--
-- Table structure for table `certificate_types`
--

CREATE TABLE `certificate_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `certificate_type_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `fee` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificate_types`
--

INSERT INTO `certificate_types` (`id`, `certificate_type_name`, `description`, `requirements`, `fee`) VALUES
(1, 'Medical Certificate', 'General health certificate for work/school', 'Physical examination', 50.00),
(2, 'Vaccination Certificate', 'Proof of vaccination', 'Complete immunization records', 0.00),
(3, 'Pregnancy Certificate', 'Certificate for pregnant women', 'Prenatal visit records', 50.00),
(4, 'Barangay Health Certificate', 'Local health certification', 'Health screening', 30.00),
(5, 'Fitness Certificate', 'Physical fitness certification', 'Medical evaluation', 100.00),
(6, 'Travel Health Certificate', 'Certificate for travel', 'Health check and vaccination status', 75.00),
(7, 'Mental Health Certificate', 'Mental health evaluation', 'Psychological assessment', 150.00);

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` int(11) NOT NULL,
  `physician_id` int(11) NOT NULL,
  `consultation_date` date NOT NULL,
  `consultation_time` time DEFAULT NULL,
  `chief_complaint` text NOT NULL,
  `patient_history` text DEFAULT NULL,
  `physical_examination` text DEFAULT NULL,
  `diagnosis` varchar(255) DEFAULT NULL,
  `icd_code` varchar(50) DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `medications_prescribed` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `referral_needed` tinyint(1) DEFAULT 0,
  `referral_to` varchar(255) DEFAULT NULL,
  `consultation_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`id`, `resident_id`, `physician_id`, `consultation_date`, `consultation_time`, `chief_complaint`, `patient_history`, `physical_examination`, `diagnosis`, `icd_code`, `treatment_plan`, `medications_prescribed`, `follow_up_date`, `referral_needed`, `referral_to`, `consultation_notes`, `created_at`) VALUES
(1, 1, 1, '2026-07-25', NULL, 'Persistent cough and low grade fever for 3 days', NULL, NULL, 'Acute Bronchitis', 'J20', 'Hydration and rest', 'Amoxicillin 500mg, Paracetamol 500mg', NULL, 0, NULL, NULL, '2026-07-24 17:04:49'),
(2, 2, 1, '2026-07-25', NULL, 'Dizziness and elevated blood pressure reading (150/90)', NULL, NULL, 'Essential Hypertension', 'I10', 'Dietary modification & BP Monitoring', 'Amlodipine 5mg OD', NULL, 0, NULL, NULL, '2026-07-24 17:04:49'),
(3, 1, 1, '2026-07-25', '02:29:08', 'Routine Primary Checkup', NULL, NULL, 'Normal Health Checkup', 'Z00.0', NULL, 'Multivitamins 1 tab daily', NULL, 0, NULL, 'Vitals stable. BP 110/70, Temp 36.5C.', '2026-07-24 18:29:08'),
(4, 1, 1, '2026-07-25', '02:29:20', 'Routine Outpatient Consult', NULL, NULL, 'Normal Primary Health Evaluation', 'Z00.0', NULL, 'Multivitamins 1 tab daily', NULL, 0, NULL, 'Vitals stable. BP 120/80.', '2026-07-24 18:29:20'),
(5, 7, 1, '2026-07-27', '11:00:40', 'maternity checkup', NULL, NULL, 'Pending OPD Triage', NULL, NULL, NULL, NULL, 0, NULL, 'Online Appointment Request from Resident Portal', '2026-07-25 03:00:40'),
(6, 1, 1, '2026-07-25', '11:08:17', 'Persistent Fever and Chills', NULL, NULL, 'Dengue Suspect Case', 'A90', NULL, 'Paracetamol 500mg, Hydration ORS', NULL, 0, NULL, 'NURSING TRIAGE VITALS: BP: 130/85, Temp: 38.5°C, Wt: 68 kg, RR: 20/min, HR: 88 bpm.', '2026-07-25 03:08:17'),
(7, 7, 5, '2026-07-25', '11:10:16', 'Cough', NULL, NULL, 'Acute Upper Respiratory Tract Infection', 'J06.9', NULL, 'Solmux', NULL, 0, NULL, 'NURSING TRIAGE VITALS: BP: 118/78, Temp: 36.8°C, Wt: 67 kg, RR: 18/min, HR: 75 bpm.', '2026-07-25 03:10:16');

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pregnancy_id` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `delivery_time` time DEFAULT NULL,
  `delivery_type` varchar(50) DEFAULT NULL,
  `birth_attendant_id` int(11) DEFAULT NULL,
  `live_births` int(11) DEFAULT 1,
  `stillbirths` int(11) DEFAULT 0,
  `complications` text DEFAULT NULL,
  `mother_status` varchar(50) DEFAULT NULL,
  `delivery_location` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `diagnostics`
--

CREATE TABLE `diagnostics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `test_type` varchar(100) DEFAULT NULL,
  `test_name` varchar(100) NOT NULL,
  `test_date` date DEFAULT NULL,
  `results` text DEFAULT NULL,
  `test_status` varchar(50) DEFAULT NULL,
  `ordered_by_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disease_cases`
--

CREATE TABLE `disease_cases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` int(11) NOT NULL,
  `disease_id` int(11) NOT NULL,
  `case_date` date NOT NULL,
  `onset_date` date DEFAULT NULL,
  `reported_by_id` int(11) DEFAULT NULL,
  `case_classification` varchar(50) DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `specimen_collection_date` date DEFAULT NULL,
  `specimen_type` varchar(100) DEFAULT NULL,
  `laboratory_result` varchar(50) DEFAULT NULL,
  `outcome` varchar(50) DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `case_status` varchar(50) DEFAULT NULL,
  `reported_to_doh` tinyint(1) DEFAULT 0,
  `doh_report_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disease_cases`
--

INSERT INTO `disease_cases` (`id`, `resident_id`, `disease_id`, `case_date`, `onset_date`, `reported_by_id`, `case_classification`, `symptoms`, `specimen_collection_date`, `specimen_type`, `laboratory_result`, `outcome`, `treatment`, `case_status`, `reported_to_doh`, `doh_report_date`, `created_at`) VALUES
(1, 1, 1, '2026-07-25', NULL, NULL, 'Confirmed', NULL, NULL, NULL, NULL, 'Recovered', NULL, NULL, 0, NULL, '2026-07-24 17:05:03'),
(2, 2, 2, '2026-07-25', NULL, NULL, 'Probable', NULL, NULL, NULL, NULL, 'Recovered', NULL, NULL, 0, NULL, '2026-07-24 17:05:03'),
(3, 1, 1, '2026-07-25', NULL, NULL, 'Confirmed', NULL, NULL, NULL, NULL, 'Recovered', NULL, NULL, 0, NULL, '2026-07-24 17:05:21'),
(4, 2, 2, '2026-07-25', NULL, NULL, 'Probable', NULL, NULL, NULL, NULL, 'Recovered', NULL, NULL, 0, NULL, '2026-07-24 17:05:21');

-- --------------------------------------------------------

--
-- Table structure for table `disease_types`
--

CREATE TABLE `disease_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `disease_name` varchar(100) NOT NULL,
  `icd_code` varchar(50) DEFAULT NULL,
  `is_reportable` tinyint(1) DEFAULT 1,
  `incubation_period_days` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disease_types`
--

INSERT INTO `disease_types` (`id`, `disease_name`, `icd_code`, `is_reportable`, `incubation_period_days`) VALUES
(1, 'Dengue Fever', 'A90', 1, NULL),
(2, 'Acute Gastroenteritis', 'A15', 1, NULL),
(3, 'Leptospirosis', 'A27', 1, NULL),
(4, 'Measles', 'B05', 1, NULL),
(5, 'COVID-19', 'U07.1', 1, NULL),
(6, 'Pneumonia', 'J18', 1, NULL),
(7, 'Diarrhea', 'A09', 1, NULL),
(8, 'Hypertension', 'I10', 0, NULL),
(9, 'Diabetes Mellitus Type 2', 'E11', 0, NULL),
(10, 'Asthma', 'J45', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `fhsis_reports`
--

CREATE TABLE `fhsis_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `report_month` int(11) NOT NULL,
  `report_year` int(11) NOT NULL,
  `submitted_date` date DEFAULT NULL,
  `submitted_by_id` int(11) DEFAULT NULL,
  `report_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`report_data`)),
  `status` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fhsis_reports`
--

INSERT INTO `fhsis_reports` (`id`, `report_month`, `report_year`, `submitted_date`, `submitted_by_id`, `report_data`, `status`, `notes`, `created_at`) VALUES
(1, 7, 2026, '2026-07-25', NULL, NULL, 'Submitted', NULL, '2026-07-24 17:05:21');

-- --------------------------------------------------------

--
-- Table structure for table `health_certificates`
--

CREATE TABLE `health_certificates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` int(11) NOT NULL,
  `certificate_type_id` int(11) NOT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `issued_by_id` int(11) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `validity_status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_certificates`
--

INSERT INTO `health_certificates` (`id`, `resident_id`, `certificate_type_id`, `certificate_number`, `issue_date`, `expiry_date`, `issued_by_id`, `purpose`, `validity_status`, `created_at`) VALUES
(1, 1, 1, 'CERT-2026-0001', '2026-07-25', '2027-01-25', NULL, 'Employment Requirement', NULL, '2026-07-24 17:05:21'),
(2, 7, 4, 'REQ-7-7511', '2026-07-25', '2027-01-25', NULL, 'Portal Request: Health Certificate (₱100)', 'Pending', '2026-07-25 02:57:43'),
(3, 7, 4, 'CERT-2026-5497', '2026-07-25', '2027-01-25', NULL, 'Employment Requirement / Sanitary Clearance', 'Valid', '2026-07-25 02:58:02'),
(4, 7, 1, 'REQ-7-8282', '2026-07-25', '2027-01-25', NULL, 'Portal Request: Medical Certificate (₱50)', 'Pending', '2026-07-25 11:31:45');

-- --------------------------------------------------------

--
-- Table structure for table `immunization_schedules`
--

CREATE TABLE `immunization_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vaccine_name` varchar(100) NOT NULL,
  `age_group` varchar(50) DEFAULT NULL,
  `doh_recommended_schedule` text DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `immunization_schedules`
--

INSERT INTO `immunization_schedules` (`id`, `vaccine_name`, `age_group`, `doh_recommended_schedule`, `notes`) VALUES
(1, 'BCG Vaccine', 'Newborn', 'At birth', NULL),
(2, 'Hepatitis B', 'Infants', '2, 4, 6 months', NULL),
(3, 'Pentavalent (DPT-HepB-Hib)', 'Infants', '2, 4, 6, 12-18 months', NULL),
(4, 'Pneumococcal', 'Infants', '2, 4, 12-15 months', NULL),
(5, 'Measles/MMR', 'Toddlers', '12-15 months, 4-6 years', NULL),
(6, 'DPT Booster', 'School age', 'Grade 1', NULL),
(7, 'Tetanus', 'Adults', 'Every 10 years', NULL),
(8, 'Influenza', 'Annually', 'Yearly vaccination', NULL),
(9, 'Hepatitis B', 'Newborn and adults', 'At birth, then 1-2 months', NULL),
(10, 'Japanese Encephalitis', 'Age 1 year', '1 year old', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `medicine_inventory`
--

CREATE TABLE `medicine_inventory` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `generic_name` varchar(100) NOT NULL,
  `brand_name` varchar(100) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `unit_form` varchar(50) DEFAULT NULL,
  `quantity_in_stock` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine_inventory`
--

INSERT INTO `medicine_inventory` (`id`, `generic_name`, `brand_name`, `dosage`, `unit_form`, `quantity_in_stock`, `reorder_level`, `supplier`, `unit_cost`, `expiry_date`, `batch_number`, `last_updated`) VALUES
(1, 'Amoxicillin', 'Amoxil', '500mg', 'Capsule', 150, 50, NULL, NULL, '2027-07-25', 'AMX-2026-01', '2026-07-24 17:05:03'),
(2, 'Paracetamol', 'Biogesic', '500mg', 'Tablet', 300, 100, NULL, NULL, '2028-07-25', 'PAR-2026-05', '2026-07-24 17:05:03'),
(3, 'Amlodipine', 'Norvasc', '5mg', 'Tablet', 80, 30, NULL, NULL, '2028-01-25', 'AML-2026-09', '2026-07-24 17:05:03');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `admin_reply` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `replied_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `resident_id`, `subject`, `message`, `admin_reply`, `status`, `created_at`, `replied_at`) VALUES
(1, 1, 'Appointment Request', 'Hello RHU Staff, requesting an OPD appointment for hypertension checkup this Friday.', NULL, 'Pending', '2026-07-25 02:48:55', NULL),
(2, 7, 'Feedback / Complaint', 'system failured', 'Sorry for the inconvenient.', 'Replied', '2026-07-25 02:58:49', '2026-07-25 10:47:01');

-- --------------------------------------------------------

--
-- Table structure for table `midwife`
--

CREATE TABLE `midwife` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` int(11) NOT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `cases_assisted` int(11) DEFAULT 0,
  `assigned_facility` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ntp_tb_reports`
--

CREATE TABLE `ntp_tb_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `report_month` int(11) NOT NULL,
  `report_year` int(11) NOT NULL,
  `submitted_date` date DEFAULT NULL,
  `submitted_by_id` int(11) DEFAULT NULL,
  `new_tb_cases` int(11) DEFAULT NULL,
  `completed_treatment` int(11) DEFAULT NULL,
  `lost_to_follow_up` int(11) DEFAULT NULL,
  `tb_deaths` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nurse`
--

CREATE TABLE `nurse` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` int(11) NOT NULL,
  `duty_station` varchar(100) DEFAULT NULL,
  `shift` varchar(20) DEFAULT NULL,
  `assigned_facility` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `physician`
--

CREATE TABLE `physician` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` int(11) NOT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `consultation_days` varchar(255) DEFAULT NULL,
  `assigned_facility` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pidsr_reports`
--

CREATE TABLE `pidsr_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `report_week` int(11) NOT NULL,
  `report_year` int(11) NOT NULL,
  `submitted_date` date DEFAULT NULL,
  `submitted_by_id` int(11) DEFAULT NULL,
  `disease_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`disease_data`)),
  `status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `portal_announcements`
--

CREATE TABLE `portal_announcements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'Health Notice',
  `content` text NOT NULL,
  `badge_text` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `posted_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `portal_events`
--

CREATE TABLE `portal_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_date` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `venue` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `image_url` text DEFAULT NULL,
  `badge_color` varchar(50) DEFAULT 'bg-emerald-500',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `portal_events`
--

INSERT INTO `portal_events` (`id`, `event_date`, `title`, `venue`, `description`, `image_url`, `badge_color`, `is_active`, `created_at`) VALUES
(1, 'July 20, 2026', 'Women Event', 'Bucana Nasugbu Batangas', 'for all wmen', 'uploads/events/event_1784978237_497.jpg', 'bg-pink-500', 1, '2026-07-25 11:17:17');

-- --------------------------------------------------------

--
-- Table structure for table `portal_settings`
--

CREATE TABLE `portal_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pregnancies`
--

CREATE TABLE `pregnancies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` int(11) NOT NULL,
  `last_menstrual_period` date DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `pregnancy_status` varchar(50) DEFAULT NULL,
  `high_risk` tinyint(1) DEFAULT 0,
  `risk_factors` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pregnancies`
--

INSERT INTO `pregnancies` (`id`, `resident_id`, `last_menstrual_period`, `expected_delivery_date`, `pregnancy_status`, `high_risk`, `risk_factors`, `created_at`, `updated_at`) VALUES
(1, 2, '2026-01-25', '2026-10-25', 'Active', 1, 'Gestational Hypertension', '2026-07-24 17:04:49', '2026-07-24 17:04:49'),
(2, 3, '2026-03-25', '2026-12-25', 'Active', 0, 'Normal Routine', '2026-07-24 17:04:49', '2026-07-24 17:04:49'),
(3, 1, '2025-11-01', '2026-08-08', 'Active', 1, 'G2P1 - Preeclampsia Monitoring & Previous Cesarean Delivery', '2026-07-25 03:15:47', '2026-07-25 03:15:47');

-- --------------------------------------------------------

--
-- Table structure for table `prenatal_visits`
--

CREATE TABLE `prenatal_visits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pregnancy_id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `visit_type` varchar(50) DEFAULT NULL,
  `healthcare_provider_id` int(11) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `fundal_height` decimal(5,2) DEFAULT NULL,
  `fetal_heart_rate` int(11) DEFAULT NULL,
  `urine_test_results` text DEFAULT NULL,
  `blood_test_results` text DEFAULT NULL,
  `ultrasound_findings` text DEFAULT NULL,
  `risk_assessment` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `next_visit_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `civil_status` varchar(20) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `purok_sitio` varchar(100) DEFAULT NULL,
  `philhealth_id` varchar(50) DEFAULT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `blood_type` varchar(10) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `first_name`, `last_name`, `middle_name`, `date_of_birth`, `gender`, `civil_status`, `contact_number`, `email`, `address`, `barangay`, `purok_sitio`, `philhealth_id`, `national_id`, `blood_type`, `allergies`, `medical_conditions`, `emergency_contact_name`, `emergency_contact_number`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Test', 'Resident', '', '2000-01-01', 'Not specif', NULL, '09171234567', 'resident_test_01@example.com', 'Not provided', 'Poblacion', NULL, 'PH-TEST-001', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-23 13:35:26', '2026-07-23 13:35:26'),
(2, 'Chedric', 'Bascoguin', NULL, '2005-01-20', NULL, NULL, '09095417674', 'chedricbascoguin27@gmail.com', 'Lipatan Lucsuhin Calatagan Batangas', 'Bucana', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-23 13:54:39', '2026-07-23 13:54:39'),
(3, 'Ched', 'Bascoguin', NULL, '2005-01-20', NULL, NULL, '09095417674', 'chedricbascoguin@gmail.com', 'Lipatan Lucsuhin Calatagan Batangas', 'Bucana', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-23 13:55:36', '2026-07-23 13:55:36'),
(4, 'Chedric', 'Bascoguin', NULL, '2005-02-02', NULL, NULL, '09095417674', 'chedricbascoguin2@gmail.com', 'Lipatan Lucsuhin Calatagan Batangas', 'Bucana', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-23 14:15:33', '2026-07-23 14:15:33'),
(5, 'Che', 'Bascoguin', NULL, '2005-02-02', NULL, NULL, '09095417674', 'chedricbascoguin211@gmail.com', 'Lipatan Lucsuhin Calatagan Batangas', 'Bucana', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-23 14:21:33', '2026-07-23 14:21:33'),
(6, 'Maria', 'Ghana', NULL, '2004-02-09', NULL, NULL, '09095417674', 'maria@email.com', 'Lipatan Lucsuhin Calatagan Batangas', 'Wawa', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-23 15:05:07', '2026-07-23 15:05:07'),
(7, 'Clinton', 'Masongsong', NULL, '2005-01-15', NULL, NULL, '09095417674', 'masongsong@gmail.com', 'Lipatan Lucsuhin Calatagan Batangas', 'Bucana', NULL, 'PH-0987654321', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-25 02:57:24', '2026-07-25 02:57:24');

-- --------------------------------------------------------

--
-- Table structure for table `resident_health_profiles`
--

CREATE TABLE `resident_health_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` int(11) NOT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `last_checkup_date` date DEFAULT NULL,
  `smoking_status` varchar(50) DEFAULT NULL,
  `alcohol_consumption` varchar(50) DEFAULT NULL,
  `exercise_frequency` varchar(50) DEFAULT NULL,
  `diet_type` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'RESIDENT', 'Community resident user', '2026-07-15 10:29:17'),
(2, 'BHW', 'Barangay Health Worker', '2026-07-15 10:29:17'),
(3, 'MIDWIFE', 'Midwife', '2026-07-15 10:29:17'),
(4, 'NURSE', 'Registered Nurse', '2026-07-15 10:29:17'),
(5, 'MEDTECH', 'Medical Technician', '2026-07-15 10:29:17'),
(6, 'PHYSICIAN', 'Medical Doctor/Physician', '2026-07-15 10:29:17'),
(7, 'SANITARY_INSPECTOR', 'Sanitary Inspector', '2026-07-15 10:29:17'),
(8, 'ADMIN_STAFF', 'RHU Administrative Staff', '2026-07-15 10:29:17'),
(9, 'RHU_ADMIN', 'RHU Administrator', '2026-07-15 10:29:17'),
(10, 'SUPER_ADMIN', 'System Super Administrator', '2026-07-15 10:29:17');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `staff_type` varchar(50) NOT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_hired` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `staff_type`, `license_number`, `license_expiry`, `specialization`, `phone_number`, `address`, `date_hired`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 7, 'NURSE', 'MD-12345-6789', '1990-03-05', 'Nurse', '09095417674', 'Lipatan Lucsuhin Calatagan Batangas', '2026-07-24', 1, '2026-07-24 09:39:31', '2026-07-24 09:39:31'),
(4, 13, 'MEDTECH', 'MD-2025-7657', NULL, 'radiologic technology', '09987655674', '', '2026-07-25', 1, '2026-07-24 17:10:43', '2026-07-24 17:10:43'),
(5, 15, 'NURSE', 'MD-2023-1011', NULL, 'Nurse', '09764567765', '', '2026-07-25', 1, '2026-07-25 03:04:56', '2026-07-25 03:04:56'),
(6, 16, 'RHU_ADMIN', 'MD-12345-6789', NULL, 'Municipal Health Officer', '09387933806', NULL, '2026-07-25', 1, '2026-07-25 11:07:45', '2026-07-25 11:07:45');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transactions`
--

CREATE TABLE `stock_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `transaction_type` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `recorded_by_id` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_adherence_tracking`
--

CREATE TABLE `tb_adherence_tracking` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tb_patient_id` int(11) NOT NULL,
  `tracking_date` date NOT NULL,
  `observed_dose` tinyint(1) DEFAULT NULL,
  `dose_count` int(11) DEFAULT NULL,
  `missed_doses` int(11) DEFAULT 0,
  `side_effects` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_patients`
--

CREATE TABLE `tb_patients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` int(11) NOT NULL,
  `tb_registration_number` varchar(100) DEFAULT NULL,
  `tb_type` varchar(50) DEFAULT NULL,
  `treatment_status` varchar(50) DEFAULT NULL,
  `treatment_start_date` date DEFAULT NULL,
  `treatment_end_date` date DEFAULT NULL,
  `dots_provider_id` int(11) DEFAULT NULL,
  `diagnosis_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_patients`
--

INSERT INTO `tb_patients` (`id`, `resident_id`, `tb_registration_number`, `tb_type`, `treatment_status`, `treatment_start_date`, `treatment_end_date`, `dots_provider_id`, `diagnosis_date`, `created_at`) VALUES
(1, 1, 'TB-NSG-2026-001', 'Pulmonary TB', 'Active', '2026-05-25', NULL, NULL, NULL, '2026-07-24 17:04:49');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_mfa_enabled` tinyint(1) DEFAULT 0,
  `mfa_secret` varchar(255) DEFAULT NULL,
  `last_login` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `role_id`, `is_active`, `is_mfa_enabled`, `mfa_secret`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'resident_test_01', 'resident_test_01@example.com', '$2a$10$sQq8TMgZpfJNDTAvo3jhK.NU0iNFflGMRCWFcMzpaqgRe1dxS5t8e', 'Test', 'Resident', 1, 1, 0, NULL, '2026-07-23 13:35:43', '2026-07-23 13:35:26', '2026-07-23 13:35:26'),
(3, 'chedricbascoguin_gmail.com', 'chedricbascoguin@gmail.com', '$2y$12$h2pHSMFPCSChlHbRdwi/ou54bNjDfMlMxgxgS.Icl3sZXPeo4fggO', 'Ched', 'Bascoguin', 1, 0, 0, NULL, '2026-07-23 13:55:36', '2026-07-23 13:55:36', '2026-07-23 13:55:36'),
(4, 'chedricbascoguin2_gmail.com', 'chedricbascoguin2@gmail.com', '$2y$12$CL2s/8mN7POKmJU8v9kH8OZIVziHii1h/HeE0Ar9jPXxi0VdlRzK.', 'Chedric', 'Bascoguin', 1, 0, 0, NULL, '2026-07-23 14:15:33', '2026-07-23 14:15:33', '2026-07-23 14:15:33'),
(5, 'chedricbascoguin211_gmail.com', 'chedricbascoguin211@gmail.com', '$2y$12$WdfSaaKrF/98OJWpp7dp1uTMaQRNlctUJEefR5H//3qYFkhTwJYPO', 'Che', 'Bascoguin', 1, 0, 0, NULL, '2026-07-23 14:21:33', '2026-07-23 14:21:33', '2026-07-23 14:21:33'),
(6, 'maria_email.com', 'maria@email.com', '$2y$12$mko70APjP1PVPW3PJ4Inv.a1JZ8KZKnUFAZJkpcNkQaO9LFsAaI56', 'Maria', 'Ghana', 1, 0, 0, NULL, '2026-07-23 15:05:07', '2026-07-23 15:05:07', '2026-07-23 15:05:07'),
(7, 'mariafr_email.com', 'mariaFR@email.com', '$2y$12$HsnqpkxMxDoXNhlEgqUPJuuWnbp9m5DvCbhG/ZUb.3y8WvlngnEbK', 'Chedric', 'Bascoguin', 4, 1, 0, NULL, '2026-07-24 09:39:31', '2026-07-24 09:39:31', '2026-07-24 09:39:31'),
(8, 'danica_gmail.com', 'danica@gmail.com', '$2y$12$xQF.rB0tFHkeLgwek1QxeuChQyXmcGnHfx0UPVcQ59BoRiJTevBjG', 'Danica', 'Yuan', 3, 1, 0, NULL, '2026-07-24 10:49:47', '2026-07-24 10:49:47', '2026-07-24 10:49:47'),
(13, '2377766879', '23-77766@g.batstate-u.edu.ph', '$2y$12$DO8JNDMWqBQy1qbyRkGw8OuL27Xe92d3MmcHK38BSMSyVlabYV5ha', 'Pauline', 'Baldoz', 5, 1, 0, NULL, '2026-07-24 17:10:43', '2026-07-24 17:10:43', '2026-07-24 17:10:43'),
(14, 'masongsong_gmail.com', 'masongsong@gmail.com', '$2y$12$5vADJ4KCqoNzyg7w0kqDqOZw6RnTzfP/6KlM.HH2.ImrcjyaUAtle', 'Clinton', 'Masongsong', 1, 0, 0, NULL, '2026-07-25 02:57:24', '2026-07-25 02:57:24', '2026-07-25 02:57:24'),
(15, 'vince810', 'vince@gmail.com', '$2y$12$qKGqATGcbn5XlXfYoG1hz.TV6bPTSs9S5xNaFBwDBIQC1QV61n0AW', 'Vince', 'Mendoza', 4, 1, 0, NULL, '2026-07-25 03:04:56', '2026-07-25 03:04:56', '2026-07-25 03:04:56'),
(16, 'chedricbascoguin27', 'chedricbascoguin27@gmail.com', '$2y$12$MYccIDnPikgvkKWYedLG5OlP/GfOjmcdtvm2Zh/zHbqKGJq/cgc7y', 'Chedric', 'Bascoguin', 8, 1, 0, NULL, '2026-07-25 11:08:25', '2026-07-25 11:07:45', '2026-07-25 11:07:45');

-- --------------------------------------------------------

--
-- Table structure for table `vaccination_records`
--

CREATE TABLE `vaccination_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `resident_id` int(11) NOT NULL,
  `vaccine_id` int(11) NOT NULL,
  `vaccination_date` date NOT NULL,
  `healthcare_provider_id` int(11) DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `site_of_injection` varchar(100) DEFAULT NULL,
  `adverse_reactions` text DEFAULT NULL,
  `next_dose_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vaccination_records`
--

INSERT INTO `vaccination_records` (`id`, `resident_id`, `vaccine_id`, `vaccination_date`, `healthcare_provider_id`, `batch_number`, `site_of_injection`, `adverse_reactions`, `next_dose_date`, `created_at`) VALUES
(1, 1, 1, '2026-01-25', 1, 'BCG-2025-09', NULL, NULL, '2026-08-25', '2026-07-24 17:04:49'),
(2, 2, 3, '2026-05-25', 1, 'PENTA-9921', NULL, NULL, '2026-08-08', '2026-07-24 17:04:49'),
(3, 1, 1, '2026-01-25', 1, 'BCG-2025-09', NULL, NULL, '2026-08-25', '2026-07-24 17:05:03'),
(4, 2, 3, '2026-05-25', 1, 'PENTA-9921', NULL, NULL, '2026-08-08', '2026-07-24 17:05:03'),
(5, 1, 1, '2026-01-25', 1, 'BCG-2025-09', NULL, NULL, '2026-08-25', '2026-07-24 17:05:21'),
(6, 2, 3, '2026-05-25', 1, 'PENTA-9921', NULL, NULL, '2026-08-08', '2026-07-24 17:05:21');

-- --------------------------------------------------------

--
-- Table structure for table `vital_statistics_births`
--

CREATE TABLE `vital_statistics_births` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `birth_certificate_number` varchar(100) DEFAULT NULL,
  `child_name` varchar(100) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `time_of_birth` time DEFAULT NULL,
  `place_of_birth` varchar(255) DEFAULT NULL,
  `mother_id` int(11) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `birth_weight_kg` decimal(5,2) DEFAULT NULL,
  `birth_length_cm` decimal(5,2) DEFAULT NULL,
  `delivery_attendant_id` int(11) DEFAULT NULL,
  `registered_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vital_statistics_births`
--

INSERT INTO `vital_statistics_births` (`id`, `birth_certificate_number`, `child_name`, `date_of_birth`, `time_of_birth`, `place_of_birth`, `mother_id`, `father_name`, `gender`, `birth_weight_kg`, `birth_length_cm`, `delivery_attendant_id`, `registered_date`, `created_at`) VALUES
(1, NULL, 'Baby Girl Santos', '2026-07-25', NULL, 'Nasugbu Lying-in Clinic', 2, NULL, NULL, 3.10, NULL, NULL, NULL, '2026-07-24 17:05:03'),
(2, NULL, 'Baby Girl Santos', '2026-07-25', NULL, 'Nasugbu Lying-in Clinic', 2, NULL, NULL, 3.10, NULL, NULL, NULL, '2026-07-24 17:05:21');

-- --------------------------------------------------------

--
-- Table structure for table `vital_statistics_deaths`
--

CREATE TABLE `vital_statistics_deaths` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `death_certificate_number` varchar(100) DEFAULT NULL,
  `deceased_name` varchar(100) DEFAULT NULL,
  `date_of_death` date NOT NULL,
  `place_of_death` varchar(255) DEFAULT NULL,
  `cause_of_death` varchar(255) DEFAULT NULL,
  `icd_code` varchar(50) DEFAULT NULL,
  `age_at_death` int(11) DEFAULT NULL,
  `reported_by_id` int(11) DEFAULT NULL,
  `registered_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_barangay_municipality` (`municipality`),
  ADD KEY `idx_barangay_province` (`province`),
  ADD KEY `idx_barangay_name` (`name`);

--
-- Indexes for table `bhw`
--
ALTER TABLE `bhw`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barangay` (`barangay`);

--
-- Indexes for table `certificate_types`
--
ALTER TABLE `certificate_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_type_name` (`certificate_type_name`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resident_id` (`resident_id`),
  ADD KEY `idx_consultation_date` (`consultation_date`);

--
-- Indexes for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `diagnostics`
--
ALTER TABLE `diagnostics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `disease_cases`
--
ALTER TABLE `disease_cases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_disease_id` (`disease_id`),
  ADD KEY `idx_case_date` (`case_date`);

--
-- Indexes for table `disease_types`
--
ALTER TABLE `disease_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `disease_name` (`disease_name`);

--
-- Indexes for table `fhsis_reports`
--
ALTER TABLE `fhsis_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_month` (`report_month`,`report_year`);

--
-- Indexes for table `health_certificates`
--
ALTER TABLE `health_certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_number` (`certificate_number`),
  ADD KEY `idx_issue_date` (`issue_date`),
  ADD KEY `idx_validity_status` (`validity_status`);

--
-- Indexes for table `immunization_schedules`
--
ALTER TABLE `immunization_schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medicine_inventory`
--
ALTER TABLE `medicine_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_generic_name` (`generic_name`),
  ADD KEY `idx_expiry_date` (`expiry_date`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `midwife`
--
ALTER TABLE `midwife`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ntp_tb_reports`
--
ALTER TABLE `ntp_tb_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_month` (`report_month`,`report_year`);

--
-- Indexes for table `nurse`
--
ALTER TABLE `nurse`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `physician`
--
ALTER TABLE `physician`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pidsr_reports`
--
ALTER TABLE `pidsr_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_week` (`report_week`,`report_year`);

--
-- Indexes for table `portal_announcements`
--
ALTER TABLE `portal_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_announcements_active` (`is_active`);

--
-- Indexes for table `portal_events`
--
ALTER TABLE `portal_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_events_active` (`is_active`);

--
-- Indexes for table `portal_settings`
--
ALTER TABLE `portal_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `pregnancies`
--
ALTER TABLE `pregnancies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resident_id` (`resident_id`),
  ADD KEY `idx_status` (`pregnancy_status`);

--
-- Indexes for table `prenatal_visits`
--
ALTER TABLE `prenatal_visits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_barangay` (`barangay`),
  ADD KEY `idx_name` (`last_name`,`first_name`),
  ADD KEY `idx_philhealth` (`philhealth_id`),
  ADD KEY `idx_residents_barangay` (`barangay`);

--
-- Indexes for table `resident_health_profiles`
--
ALTER TABLE `resident_health_profiles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_type` (`staff_type`),
  ADD KEY `idx_staff_user_id` (`user_id`);

--
-- Indexes for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction_date` (`transaction_date`);

--
-- Indexes for table `tb_adherence_tracking`
--
ALTER TABLE `tb_adherence_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tracking_date` (`tracking_date`);

--
-- Indexes for table `tb_patients`
--
ALTER TABLE `tb_patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tb_registration_number` (`tb_registration_number`),
  ADD KEY `idx_tb_registration` (`tb_registration_number`),
  ADD KEY `idx_status` (`treatment_status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_users_role_id` (`role_id`);

--
-- Indexes for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resident_id` (`resident_id`),
  ADD KEY `idx_vaccination_date` (`vaccination_date`);

--
-- Indexes for table `vital_statistics_births`
--
ALTER TABLE `vital_statistics_births`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `birth_certificate_number` (`birth_certificate_number`),
  ADD KEY `idx_date_of_birth` (`date_of_birth`);

--
-- Indexes for table `vital_statistics_deaths`
--
ALTER TABLE `vital_statistics_deaths`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `death_certificate_number` (`death_certificate_number`),
  ADD KEY `idx_date_of_death` (`date_of_death`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `bhw`
--
ALTER TABLE `bhw`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `certificate_types`
--
ALTER TABLE `certificate_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `diagnostics`
--
ALTER TABLE `diagnostics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disease_cases`
--
ALTER TABLE `disease_cases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `disease_types`
--
ALTER TABLE `disease_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `fhsis_reports`
--
ALTER TABLE `fhsis_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `health_certificates`
--
ALTER TABLE `health_certificates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `immunization_schedules`
--
ALTER TABLE `immunization_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `medicine_inventory`
--
ALTER TABLE `medicine_inventory`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `midwife`
--
ALTER TABLE `midwife`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ntp_tb_reports`
--
ALTER TABLE `ntp_tb_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nurse`
--
ALTER TABLE `nurse`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `physician`
--
ALTER TABLE `physician`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pidsr_reports`
--
ALTER TABLE `pidsr_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `portal_announcements`
--
ALTER TABLE `portal_announcements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `portal_events`
--
ALTER TABLE `portal_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pregnancies`
--
ALTER TABLE `pregnancies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `prenatal_visits`
--
ALTER TABLE `prenatal_visits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `resident_health_profiles`
--
ALTER TABLE `resident_health_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tb_adherence_tracking`
--
ALTER TABLE `tb_adherence_tracking`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tb_patients`
--
ALTER TABLE `tb_patients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `vital_statistics_births`
--
ALTER TABLE `vital_statistics_births`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vital_statistics_deaths`
--
ALTER TABLE `vital_statistics_deaths`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
