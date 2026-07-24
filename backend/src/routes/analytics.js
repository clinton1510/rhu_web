import express from 'express';
import pool from '../config/database.js';

const router = express.Router();

// Get dashboard statistics
router.get('/dashboard', async (req, res) => {
  try {
    // Total residents
    const [totalResidents] = await pool.query('SELECT COUNT(*) as count FROM residents WHERE is_active = TRUE');

    // Active donors
    const [totalDonors] = await pool.query('SELECT COUNT(*) as count FROM donors WHERE is_eligible = TRUE');

    // Registered donors
    const [registeredDonors] = await pool.query('SELECT COUNT(*) as count FROM donors');

    // Active pregnancies
    const [activePregnancies] = await pool.query('SELECT COUNT(*) as count FROM pregnancies WHERE pregnancy_status = "Active"');

    // TB patients
    const [tbPatients] = await pool.query('SELECT COUNT(*) as count FROM tb_patients WHERE treatment_status = "Active"');

    // Recent disease cases (last 7 days)
    const [recentCases] = await pool.query(
      'SELECT COUNT(*) as count FROM disease_cases WHERE case_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'
    );

    // Blood inventory levels
    const [bloodLevels] = await pool.query(
      `SELECT blood_type, rh_factor, SUM(quantity) as total_quantity
       FROM blood_inventory
       GROUP BY blood_type, rh_factor`
    );

    // Staff by type
    const [staffByType] = await pool.query(
      `SELECT staff_type, COUNT(*) as count
       FROM staff
       WHERE is_active = TRUE
       GROUP BY staff_type`
    );

    res.json({
      totalResidents: totalResidents[0]?.count || 0,
      totalDonors: totalDonors[0]?.count || 0,
      registeredDonors: registeredDonors[0]?.count || 0,
      activePregnancies: activePregnancies[0]?.count || 0,
      tbPatients: tbPatients[0]?.count || 0,
      recentCases: recentCases[0]?.count || 0,
      bloodInventory: bloodLevels,
      staff: staffByType
    });
  } catch (error) {
    console.error('Error fetching dashboard statistics:', error);
    res.status(500).json({ error: 'Failed to fetch dashboard statistics' });
  }
});

// Get barangay statistics
router.get('/barangay/:barangay', async (req, res) => {
  try {
    const barangay = req.params.barangay;

    // Residents in barangay
    const [residents] = await pool.query(
      'SELECT COUNT(*) as count FROM residents WHERE barangay = ? AND is_active = TRUE',
      [barangay]
    );

    // Donors in barangay
    const [donors] = await pool.query(
      `SELECT d.blood_type, COUNT(*) as count
       FROM donors d
       JOIN residents r ON d.resident_id = r.id
       WHERE r.barangay = ? AND d.is_eligible = TRUE
       GROUP BY d.blood_type`,
      [barangay]
    );

    // Active pregnancies
    const [pregnancies] = await pool.query(
      `SELECT COUNT(*) as count
       FROM pregnancies p
       JOIN residents r ON p.resident_id = r.id
       WHERE r.barangay = ? AND p.pregnancy_status = 'Active'`,
      [barangay]
    );

    // Recent disease cases
    const [cases] = await pool.query(
      `SELECT COUNT(*) as count
       FROM disease_cases dc
       JOIN residents r ON dc.resident_id = r.id
       WHERE r.barangay = ? AND dc.case_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)`,
      [barangay]
    );

    res.json({
      barangay,
      residents: residents[0]?.count || 0,
      donors: donors || [],
      activePregnancies: pregnancies[0]?.count || 0,
      recentDiseases: cases[0]?.count || 0
    });
  } catch (error) {
    console.error('Error fetching barangay statistics:', error);
    res.status(500).json({ error: 'Failed to fetch barangay statistics' });
  }
});

// Get health indicators
router.get('/indicators', async (req, res) => {
  try {
    // Immunization coverage
    const [immunizationCoverage] = await pool.query(
      `SELECT COUNT(DISTINCT resident_id) as vaccinated_residents
       FROM vaccination_records
       WHERE vaccination_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)`
    );

    // Maternal health - safe deliveries
    const [safeDeliveries] = await pool.query(
      `SELECT COUNT(*) as count
       FROM deliveries
       WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) AND complications IS NULL`
    );

    // TB treatment success rate
    const [tbSuccess] = await pool.query(
      `SELECT COUNT(*) as completed
       FROM tb_patients
       WHERE treatment_status = 'Completed'`
    );

    res.json({
      immunizationCoverage: immunizationCoverage[0]?.vaccinated_residents || 0,
      safeDeliveries: safeDeliveries[0]?.count || 0,
      tbCompletedTreatment: tbSuccess[0]?.completed || 0
    });
  } catch (error) {
    console.error('Error fetching health indicators:', error);
    res.status(500).json({ error: 'Failed to fetch health indicators' });
  }
});

// Get FHSIS report data
router.get('/fhsis', async (req, res) => {
  try {
    const { month, year } = req.query;

    const [data] = await pool.query(
      `SELECT 
        COUNT(DISTINCT c.resident_id) as total_consultations,
        COUNT(DISTINCT p.resident_id) as total_pregnancies_monitored,
        COUNT(DISTINCT v.resident_id) as total_vaccinated,
        COUNT(DISTINCT dc.resident_id) as disease_cases_reported,
        COUNT(DISTINCT t.resident_id) as tb_patients
       FROM residents r
       LEFT JOIN consultations c ON r.id = c.resident_id AND MONTH(c.consultation_date) = ? AND YEAR(c.consultation_date) = ?
       LEFT JOIN pregnancies p ON r.id = p.resident_id
       LEFT JOIN vaccination_records v ON r.id = v.resident_id AND MONTH(v.vaccination_date) = ? AND YEAR(v.vaccination_date) = ?
       LEFT JOIN disease_cases dc ON r.id = dc.resident_id AND MONTH(dc.case_date) = ? AND YEAR(dc.case_date) = ?
       LEFT JOIN tb_patients t ON r.id = t.resident_id
       WHERE r.barangay IN (SELECT name FROM barangays WHERE municipality = 'Nasugbu')`,
      [month, year, month, year, month, year]
    );

    res.json(data[0]);
  } catch (error) {
    console.error('Error fetching FHSIS report:', error);
    res.status(500).json({ error: 'Failed to fetch FHSIS report' });
  }
});

export default router;
