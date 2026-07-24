import express from 'express';
import pool from '../config/database.js';

const router = express.Router();

// Get all residents
router.get('/', async (req, res) => {
  try {
    const [residents] = await pool.query(
      'SELECT * FROM residents LIMIT 100'
    );
    res.json(residents);
  } catch (error) {
    console.error('Error fetching residents:', error);
    res.status(500).json({ error: 'Failed to fetch residents' });
  }
});

// Get resident by email
router.get('/email/:email', async (req, res) => {
  try {
    const [resident] = await pool.query(
      'SELECT * FROM residents WHERE email = ? LIMIT 1',
      [req.params.email]
    );
    if (resident.length === 0) {
      return res.status(404).json({ error: 'Resident not found' });
    }
    res.json(resident[0]);
  } catch (error) {
    console.error('Error fetching resident by email:', error);
    res.status(500).json({ error: 'Failed to fetch resident by email' });
  }
});

// Get resident by ID
router.get('/:id', async (req, res) => {
  try {
    const [resident] = await pool.query(
      'SELECT * FROM residents WHERE id = ?',
      [req.params.id]
    );
    if (resident.length === 0) {
      return res.status(404).json({ error: 'Resident not found' });
    }
    res.json(resident[0]);
  } catch (error) {
    console.error('Error fetching resident:', error);
    res.status(500).json({ error: 'Failed to fetch resident' });
  }
});

// Search residents by query
router.get('/search', async (req, res) => {
  try {
    const q = (req.query.q || '').toString().trim();
    if (!q) {
      return res.json([]);
    }

    const wildcard = `%${q}%`;
    const [residents] = await pool.query(
      `SELECT * FROM residents WHERE
         first_name LIKE ? OR
         last_name LIKE ? OR
         email LIKE ? OR
         barangay LIKE ?
       LIMIT 50`,
      [wildcard, wildcard, wildcard, wildcard]
    );

    res.json(residents);
  } catch (error) {
    console.error('Error searching residents:', error);
    res.status(500).json({ error: 'Failed to search residents' });
  }
});

// Create new resident
router.post('/', async (req, res) => {
  try {
    const {
      first_name, last_name, middle_name, date_of_birth, gender,
      contact_number, email, address, barangay, blood_type
    } = req.body;

    const [result] = await pool.query(
      `INSERT INTO residents 
       (first_name, last_name, middle_name, date_of_birth, gender, contact_number, email, address, barangay, blood_type) 
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [first_name, last_name, middle_name, date_of_birth, gender, contact_number, email, address, barangay, blood_type]
    );

    res.status(201).json({
      id: result.insertId,
      message: 'Resident created successfully'
    });
  } catch (error) {
    console.error('Error creating resident:', error);
    res.status(500).json({ error: 'Failed to create resident' });
  }
});

// Update resident
router.put('/:id', async (req, res) => {
  try {
    const { first_name, last_name, email, contact_number, blood_type } = req.body;
    
    await pool.query(
      'UPDATE residents SET first_name = ?, last_name = ?, email = ?, contact_number = ?, blood_type = ? WHERE id = ?',
      [first_name, last_name, email, contact_number, blood_type, req.params.id]
    );

    res.json({ message: 'Resident updated successfully' });
  } catch (error) {
    console.error('Error updating resident:', error);
    res.status(500).json({ error: 'Failed to update resident' });
  }
});

// Delete resident
router.delete('/:id', async (req, res) => {
  try {
    await pool.query('DELETE FROM residents WHERE id = ?', [req.params.id]);
    res.json({ message: 'Resident deleted successfully' });
  } catch (error) {
    console.error('Error deleting resident:', error);
    res.status(500).json({ error: 'Failed to delete resident' });
  }
});

export default router;
