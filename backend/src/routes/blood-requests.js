import express from 'express';
import pool from '../config/database.js';

const router = express.Router();

// Get all blood requests
router.get('/', async (req, res) => {
  try {
    const [requests] = await pool.query(
      `SELECT br.*, r.first_name, r.last_name
       FROM blood_requests br
       LEFT JOIN residents r ON br.patient_name = CONCAT(r.first_name, ' ', r.last_name)
       ORDER BY br.requested_date DESC, br.requested_time DESC`
    );
    res.json(requests);
  } catch (error) {
    console.error('Error fetching blood requests:', error);
    res.status(500).json({ error: 'Failed to fetch blood requests' });
  }
});

// Get blood request by ID
router.get('/:id', async (req, res) => {
  try {
    const [request] = await pool.query(
      'SELECT * FROM blood_requests WHERE id = ?',
      [req.params.id]
    );
    if (request.length === 0) {
      return res.status(404).json({ error: 'Blood request not found' });
    }
    res.json(request[0]);
  } catch (error) {
    console.error('Error fetching blood request:', error);
    res.status(500).json({ error: 'Failed to fetch blood request' });
  }
});

// Create blood request
router.post('/', async (req, res) => {
  try {
    const {
      requesting_facility,
      blood_type,
      rh_factor,
      quantity_needed,
      urgency_level,
      patient_name,
      patient_condition
    } = req.body;

    const [result] = await pool.query(
      `INSERT INTO blood_requests
       (requesting_facility, blood_type, rh_factor, quantity_needed, urgency_level, requested_date, patient_name, patient_condition, request_status)
       VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, 'Pending')`,
      [requesting_facility, blood_type, rh_factor, quantity_needed, urgency_level, patient_name, patient_condition]
    );

    res.status(201).json({
      id: result.insertId,
      message: 'Blood request created successfully'
    });
  } catch (error) {
    console.error('Error creating blood request:', error);
    res.status(500).json({ error: 'Failed to create blood request' });
  }
});

// Update blood request status
router.put('/:id', async (req, res) => {
  try {
    const { request_status } = req.body;
    
    await pool.query(
      'UPDATE blood_requests SET request_status = ? WHERE id = ?',
      [request_status, req.params.id]
    );

    res.json({ message: 'Blood request updated successfully' });
  } catch (error) {
    console.error('Error updating blood request:', error);
    res.status(500).json({ error: 'Failed to update blood request' });
  }
});

// Find matching donors for a blood request
router.get('/:id/matches', async (req, res) => {
  try {
    const [request] = await pool.query(
      'SELECT * FROM blood_requests WHERE id = ?',
      [req.params.id]
    );

    if (request.length === 0) {
      return res.status(404).json({ error: 'Blood request not found' });
    }

    // Get eligible donors with matching blood type
    const [matches] = await pool.query(
      `SELECT d.*, r.first_name, r.last_name, r.latitude, r.longitude, r.contact_number
       FROM donors d
       JOIN residents r ON d.resident_id = r.id
       WHERE d.blood_type = ? AND d.is_eligible = TRUE AND d.last_donation_date IS NOT NULL
       ORDER BY d.donation_response_probability DESC
       LIMIT 20`,
      [request[0].blood_type]
    );

    res.json(matches);
  } catch (error) {
    console.error('Error finding matches:', error);
    res.status(500).json({ error: 'Failed to find matches' });
  }
});

export default router;
