import express from 'express';
import pool from '../config/database.js';

const router = express.Router();

// Get disease types
router.get('/types', async (req, res) => {
  try {
    const [diseases] = await pool.query(
      'SELECT * FROM disease_types WHERE is_reportable = TRUE ORDER BY disease_name'
    );
    res.json(diseases);
  } catch (error) {
    console.error('Error fetching disease types:', error);
    res.status(500).json({ error: 'Failed to fetch disease types' });
  }
});

// Get disease cases
router.get('/cases', async (req, res) => {
  try {
    const { startDate, endDate } = req.query;
    let query = `SELECT d.*, r.first_name, r.last_name, r.barangay, dt.disease_name
                 FROM disease_cases d
                 JOIN residents r ON d.resident_id = r.id
                 JOIN disease_types dt ON d.disease_id = dt.id`;
    let params = [];

    if (startDate && endDate) {
      query += ` WHERE d.case_date BETWEEN ? AND ?`;
      params = [startDate, endDate];
    }

    query += ` ORDER BY d.case_date DESC`;

    const [cases] = await pool.query(query, params);
    res.json(cases);
  } catch (error) {
    console.error('Error fetching disease cases:', error);
    res.status(500).json({ error: 'Failed to fetch disease cases' });
  }
});

// Report disease case
router.post('/cases', async (req, res) => {
  try {
    const {
      resident_id,
      disease_id,
      case_date,
      onset_date,
      reported_by_id,
      case_classification,
      symptoms,
      specimen_collection_date,
      specimen_type,
      laboratory_result,
      treatment
    } = req.body;

    const [result] = await pool.query(
      `INSERT INTO disease_cases
       (resident_id, disease_id, case_date, onset_date, reported_by_id, case_classification, symptoms, specimen_collection_date, specimen_type, laboratory_result, treatment, case_status, reported_to_doh)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', FALSE)`,
      [resident_id, disease_id, case_date, onset_date, reported_by_id, case_classification, symptoms, specimen_collection_date, specimen_type, laboratory_result, treatment]
    );

    res.status(201).json({
      id: result.insertId,
      message: 'Disease case reported successfully'
    });
  } catch (error) {
    console.error('Error reporting disease case:', error);
    res.status(500).json({ error: 'Failed to report disease case' });
  }
});

// Get disease cases by date range for PIDSR reporting
router.get('/pidsr/report', async (req, res) => {
  try {
    const { week, year } = req.query;
    
    // Calculate dates for the week
    const startDate = new Date(year, 0, 1 + (week - 1) * 7);
    const endDate = new Date(startDate.getTime() + 6 * 24 * 60 * 60 * 1000);

    const [cases] = await pool.query(
      `SELECT dt.disease_name, COUNT(*) as count, 
              SUM(CASE WHEN d.case_classification = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
              SUM(CASE WHEN d.case_classification = 'Probable' THEN 1 ELSE 0 END) as probable,
              SUM(CASE WHEN d.case_classification = 'Suspected' THEN 1 ELSE 0 END) as suspected
       FROM disease_cases d
       JOIN disease_types dt ON d.disease_id = dt.id
       WHERE d.case_date BETWEEN ? AND ? AND dt.is_reportable = TRUE
       GROUP BY d.disease_id, dt.disease_name
       ORDER BY count DESC`,
      [startDate, endDate]
    );

    res.json({ week, year, data: cases });
  } catch (error) {
    console.error('Error fetching PIDSR report:', error);
    res.status(500).json({ error: 'Failed to fetch PIDSR report' });
  }
});

// Get monthly disease statistics
router.get('/monthly-stats', async (req, res) => {
  try {
    const { month, year } = req.query;

    const [stats] = await pool.query(
      `SELECT dt.disease_name, COUNT(*) as total_cases, 
              SUM(CASE WHEN d.case_classification = 'Confirmed' THEN 1 ELSE 0 END) as confirmed_cases
       FROM disease_cases d
       JOIN disease_types dt ON d.disease_id = dt.id
       WHERE MONTH(d.case_date) = ? AND YEAR(d.case_date) = ? AND dt.is_reportable = TRUE
       GROUP BY d.disease_id
       ORDER BY total_cases DESC`,
      [month, year]
    );

    res.json(stats);
  } catch (error) {
    console.error('Error fetching monthly disease statistics:', error);
    res.status(500).json({ error: 'Failed to fetch monthly disease statistics' });
  }
});

export default router;
