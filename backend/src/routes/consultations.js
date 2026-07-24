import express from 'express';
import pool from '../config/database.js';

const router = express.Router();

// Get all consultations
router.get('/', async (req, res) => {
  try {
    const [consultations] = await pool.query(
      `SELECT c.*, r.first_name, r.last_name, u.username as physician_name
       FROM consultations c
       JOIN residents r ON c.resident_id = r.id
       JOIN staff s ON c.physician_id = s.id
       JOIN users u ON s.user_id = u.id
       ORDER BY c.consultation_date DESC
       LIMIT 100`
    );
    res.json(consultations);
  } catch (error) {
    console.error('Error fetching consultations:', error);
    res.status(500).json({ error: 'Failed to fetch consultations' });
  }
});

// Get consultations for a resident
router.get('/resident/:resident_id', async (req, res) => {
  try {
    const [consultations] = await pool.query(
      `SELECT c.*, u.username as physician_name
       FROM consultations c
       JOIN staff s ON c.physician_id = s.id
       JOIN users u ON s.user_id = u.id
       WHERE c.resident_id = ?
       ORDER BY c.consultation_date DESC`,
      [req.params.resident_id]
    );
    res.json(consultations);
  } catch (error) {
    console.error('Error fetching consultations:', error);
    res.status(500).json({ error: 'Failed to fetch consultations' });
  }
});

// Create new consultation
router.post('/', async (req, res) => {
  try {
    const {
      resident_id, physician_id, consultation_date, chief_complaint,
      patient_history, physical_examination, diagnosis, treatment_plan
    } = req.body;

    const [result] = await pool.query(
      `INSERT INTO consultations 
       (resident_id, physician_id, consultation_date, chief_complaint, patient_history, physical_examination, diagnosis, treatment_plan) 
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [resident_id, physician_id, consultation_date, chief_complaint, patient_history, physical_examination, diagnosis, treatment_plan]
    );

    res.status(201).json({
      id: result.insertId,
      message: 'Consultation recorded successfully'
    });
  } catch (error) {
    console.error('Error creating consultation:', error);
    res.status(500).json({ error: 'Failed to create consultation' });
  }
});

export default router;
