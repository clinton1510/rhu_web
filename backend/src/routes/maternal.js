import express from 'express';
import pool from '../config/database.js';

const router = express.Router();

// Get all pregnancies
router.get('/pregnancies', async (req, res) => {
  try {
    const [pregnancies] = await pool.query(
      `SELECT p.*, r.first_name, r.last_name, r.contact_number
       FROM pregnancies p
       JOIN residents r ON p.resident_id = r.id
       WHERE p.pregnancy_status = 'Active'
       ORDER BY p.expected_delivery_date ASC`
    );
    res.json(pregnancies);
  } catch (error) {
    console.error('Error fetching pregnancies:', error);
    res.status(500).json({ error: 'Failed to fetch pregnancies' });
  }
});

// Get pregnancy by resident ID
router.get('/pregnancies/resident/:resident_id', async (req, res) => {
  try {
    const [pregnancy] = await pool.query(
      'SELECT * FROM pregnancies WHERE resident_id = ? AND pregnancy_status = "Active"',
      [req.params.resident_id]
    );
    
    if (pregnancy.length === 0) {
      return res.status(404).json({ error: 'No active pregnancy found' });
    }
    
    res.json(pregnancy[0]);
  } catch (error) {
    console.error('Error fetching pregnancy:', error);
    res.status(500).json({ error: 'Failed to fetch pregnancy' });
  }
});

// Create pregnancy
router.post('/pregnancies', async (req, res) => {
  try {
    const {
      resident_id,
      last_menstrual_period,
      expected_delivery_date,
      high_risk,
      risk_factors
    } = req.body;

    const [result] = await pool.query(
      `INSERT INTO pregnancies
       (resident_id, last_menstrual_period, expected_delivery_date, pregnancy_status, high_risk, risk_factors)
       VALUES (?, ?, ?, 'Active', ?, ?)`,
      [resident_id, last_menstrual_period, expected_delivery_date, high_risk || false, risk_factors]
    );

    res.status(201).json({
      id: result.insertId,
      message: 'Pregnancy record created successfully'
    });
  } catch (error) {
    console.error('Error creating pregnancy:', error);
    res.status(500).json({ error: 'Failed to create pregnancy' });
  }
});

// Record prenatal visit
router.post('/pregnancies/:pregnancy_id/visits', async (req, res) => {
  try {
    const {
      visit_date,
      healthcare_provider_id,
      blood_pressure,
      weight,
      fundal_height,
      fetal_heart_rate,
      risk_assessment,
      notes
    } = req.body;

    const [result] = await pool.query(
      `INSERT INTO prenatal_visits
       (pregnancy_id, visit_date, healthcare_provider_id, blood_pressure, weight, fundal_height, fetal_heart_rate, risk_assessment, notes)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [req.params.pregnancy_id, visit_date, healthcare_provider_id, blood_pressure, weight, fundal_height, fetal_heart_rate, risk_assessment, notes]
    );

    res.status(201).json({
      id: result.insertId,
      message: 'Prenatal visit recorded successfully'
    });
  } catch (error) {
    console.error('Error recording prenatal visit:', error);
    res.status(500).json({ error: 'Failed to record prenatal visit' });
  }
});

// Record delivery
router.post('/pregnancies/:pregnancy_id/delivery', async (req, res) => {
  try {
    const {
      delivery_date,
      delivery_time,
      delivery_type,
      birth_attendant_id,
      live_births,
      complications,
      mother_status,
      delivery_location
    } = req.body;

    const [result] = await pool.query(
      `INSERT INTO deliveries
       (pregnancy_id, delivery_date, delivery_time, delivery_type, birth_attendant_id, live_births, complications, mother_status, delivery_location)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [req.params.pregnancy_id, delivery_date, delivery_time, delivery_type, birth_attendant_id, live_births, complications, mother_status, delivery_location]
    );

    // Update pregnancy status
    await pool.query(
      'UPDATE pregnancies SET pregnancy_status = "Delivered" WHERE id = ?',
      [req.params.pregnancy_id]
    );

    res.status(201).json({
      id: result.insertId,
      message: 'Delivery recorded successfully'
    });
  } catch (error) {
    console.error('Error recording delivery:', error);
    res.status(500).json({ error: 'Failed to record delivery' });
  }
});

// Get prenatal visits for a pregnancy
router.get('/pregnancies/:pregnancy_id/visits', async (req, res) => {
  try {
    const [visits] = await pool.query(
      'SELECT * FROM prenatal_visits WHERE pregnancy_id = ? ORDER BY visit_date DESC',
      [req.params.pregnancy_id]
    );
    res.json(visits);
  } catch (error) {
    console.error('Error fetching prenatal visits:', error);
    res.status(500).json({ error: 'Failed to fetch prenatal visits' });
  }
});

export default router;
