import express from 'express';
import pool from '../config/database.js';

const router = express.Router();

// Get all donors
router.get('/', async (req, res) => {
  try {
    const [donors] = await pool.query(
      `SELECT d.*, r.first_name, r.last_name, r.blood_type, r.contact_number
       FROM donors d
       JOIN residents r ON d.resident_id = r.id
       LIMIT 100`
    );
    res.json(donors);
  } catch (error) {
    console.error('Error fetching donors:', error);
    res.status(500).json({ error: 'Failed to fetch donors' });
  }
});

// Get donors by blood type
router.get('/blood-type/:blood_type', async (req, res) => {
  try {
    const [donors] = await pool.query(
      `SELECT d.*, r.first_name, r.last_name, r.contact_number
       FROM donors d
       JOIN residents r ON d.resident_id = r.id
       WHERE d.blood_type = ?`,
      [req.params.blood_type]
    );
    res.json(donors);
  } catch (error) {
    console.error('Error fetching donors:', error);
    res.status(500).json({ error: 'Failed to fetch donors' });
  }
});

// Get available donors (eligible for donation)
router.get('/available', async (req, res) => {
  try {
    const [donors] = await pool.query(
      `SELECT d.*, r.first_name, r.last_name, r.contact_number, r.latitude, r.longitude
       FROM donors d
       JOIN residents r ON d.resident_id = r.id
       WHERE d.is_eligible = TRUE
       LIMIT 100`
    );
    res.json(donors);
  } catch (error) {
    console.error('Error fetching available donors:', error);
    res.status(500).json({ error: 'Failed to fetch available donors' });
  }
});

// Create new donor
router.post('/', async (req, res) => {
  try {
    const { resident_id, blood_type, rh_factor, donor_classification } = req.body;

    const [result] = await pool.query(
      `INSERT INTO donors (resident_id, blood_type, rh_factor, donor_classification) 
       VALUES (?, ?, ?, ?)`,
      [resident_id, blood_type, rh_factor, donor_classification]
    );

    res.status(201).json({
      id: result.insertId,
      message: 'Donor registered successfully'
    });
  } catch (error) {
    console.error('Error creating donor:', error);
    res.status(500).json({ error: 'Failed to create donor' });
  }
});

export default router;
