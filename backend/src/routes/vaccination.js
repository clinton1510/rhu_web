import express from 'express';
import pool from '../config/database.js';

const router = express.Router();

// Get immunization schedules
router.get('/schedules', async (req, res) => {
  try {
    const [schedules] = await pool.query(
      'SELECT * FROM immunization_schedules ORDER BY age_group'
    );
    res.json(schedules);
  } catch (error) {
    console.error('Error fetching immunization schedules:', error);
    res.status(500).json({ error: 'Failed to fetch immunization schedules' });
  }
});

// Get vaccination records for a resident
router.get('/records/resident/:resident_id', async (req, res) => {
  try {
    const [records] = await pool.query(
      `SELECT v.*, i.vaccine_name, u.username as provider_name
       FROM vaccination_records v
       JOIN immunization_schedules i ON v.vaccine_id = i.id
       LEFT JOIN users u ON v.healthcare_provider_id = u.id
       WHERE v.resident_id = ?
       ORDER BY v.vaccination_date DESC`,
      [req.params.resident_id]
    );
    res.json(records);
  } catch (error) {
    console.error('Error fetching vaccination records:', error);
    res.status(500).json({ error: 'Failed to fetch vaccination records' });
  }
});

// Record vaccination
router.post('/records', async (req, res) => {
  try {
    const {
      resident_id,
      vaccine_id,
      vaccination_date,
      healthcare_provider_id,
      batch_number,
      site_of_injection,
      adverse_reactions,
      next_dose_date
    } = req.body;

    const [result] = await pool.query(
      `INSERT INTO vaccination_records
       (resident_id, vaccine_id, vaccination_date, healthcare_provider_id, batch_number, site_of_injection, adverse_reactions, next_dose_date)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [resident_id, vaccine_id, vaccination_date, healthcare_provider_id, batch_number, site_of_injection, adverse_reactions, next_dose_date]
    );

    res.status(201).json({
      id: result.insertId,
      message: 'Vaccination recorded successfully'
    });
  } catch (error) {
    console.error('Error recording vaccination:', error);
    res.status(500).json({ error: 'Failed to record vaccination' });
  }
});

// Get vaccination coverage statistics
router.get('/coverage/statistics', async (req, res) => {
  try {
    const [stats] = await pool.query(
      `SELECT 
        i.vaccine_name,
        COUNT(v.id) as total_administered,
        COUNT(DISTINCT v.resident_id) as unique_residents,
        DATE_FORMAT(MAX(v.vaccination_date), '%Y-%m-%d') as latest_date
       FROM immunization_schedules i
       LEFT JOIN vaccination_records v ON i.id = v.vaccine_id
       GROUP BY i.id
       ORDER BY total_administered DESC`
    );
    res.json(stats);
  } catch (error) {
    console.error('Error fetching coverage statistics:', error);
    res.status(500).json({ error: 'Failed to fetch coverage statistics' });
  }
});

export default router;
