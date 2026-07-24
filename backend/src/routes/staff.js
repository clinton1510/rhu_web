import express from 'express';
import pool from '../config/database.js';

const router = express.Router();

// Get all staff
router.get('/', async (req, res) => {
  try {
    const [staff] = await pool.query(
      `SELECT s.*, u.username, u.email, r.name as role_name
       FROM staff s
       JOIN users u ON s.user_id = u.id
       JOIN roles r ON u.role_id = r.id
       LIMIT 100`
    );
    res.json(staff);
  } catch (error) {
    console.error('Error fetching staff:', error);
    res.status(500).json({ error: 'Failed to fetch staff' });
  }
});

// Get staff by type
router.get('/type/:staff_type', async (req, res) => {
  try {
    const [staff] = await pool.query(
      `SELECT s.*, u.username, u.email
       FROM staff s
       JOIN users u ON s.user_id = u.id
       WHERE s.staff_type = ?`,
      [req.params.staff_type]
    );
    res.json(staff);
  } catch (error) {
    console.error('Error fetching staff:', error);
    res.status(500).json({ error: 'Failed to fetch staff' });
  }
});

// Get BHWs by barangay
router.get('/bhw/barangay/:barangay', async (req, res) => {
  try {
    const [bhws] = await pool.query(
      `SELECT s.*, b.barangay, b.coverage_population, u.email, u.username
       FROM staff s
       JOIN bhw b ON s.id = b.staff_id
       JOIN users u ON s.user_id = u.id
       WHERE b.barangay = ?`,
      [req.params.barangay]
    );
    res.json(bhws);
  } catch (error) {
    console.error('Error fetching BHWs:', error);
    res.status(500).json({ error: 'Failed to fetch BHWs' });
  }
});

export default router;
