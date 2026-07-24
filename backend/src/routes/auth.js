import express from 'express';
import bcrypt from 'bcryptjs';
import pool from '../config/database.js';

const router = express.Router();

function normalizeIdentifier(value) {
  return String(value || '').trim().toLowerCase();
}

function sanitizeUserPayload(user) {
  return {
    id: user.id,
    username: user.username,
    email: user.email,
    first_name: user.first_name,
    last_name: user.last_name,
    role_id: user.role_id,
    role: user.role_name,
    is_active: user.is_active,
    last_login: user.last_login,
  };
}

// Login endpoint
router.post('/login', async (req, res) => {
  try {
    const { email, username, password } = req.body;
    const loginIdentifier = normalizeIdentifier(email || username);

    if (!loginIdentifier || !password) {
      return res.status(400).json({ error: 'Email or username and password are required' });
    }

    const [users] = await pool.query(
      `SELECT u.*, r.name AS role_name
       FROM users u
       LEFT JOIN roles r ON r.id = u.role_id
       WHERE u.email = ? OR u.username = ?
       LIMIT 1`,
      [loginIdentifier, loginIdentifier]
    );

    if (users.length === 0) {
      return res.status(401).json({ error: 'Invalid credentials' });
    }

    const user = users[0];
    const passwordMatches = await bcrypt.compare(password, user.password_hash).catch(() => false);
    const isPlainTextMatch = !passwordMatches && user.password_hash === password;

    if (!passwordMatches && !isPlainTextMatch) {
      return res.status(401).json({ error: 'Invalid credentials' });
    }

    if (user.password_hash !== password && !user.password_hash.startsWith('$2')) {
      await pool.query(
        'UPDATE users SET password_hash = ? WHERE id = ?',
        [await bcrypt.hash(password, 10), user.id]
      );
    }

    const [residentRows] = await pool.query(
      'SELECT * FROM residents WHERE email = ? LIMIT 1',
      [user.email]
    );

    await pool.query(
      'UPDATE users SET last_login = NOW() WHERE id = ?',
      [user.id]
    );

    res.json({
      ...sanitizeUserPayload(user),
      resident: residentRows[0] || null,
      message: 'Login successful'
    });
  } catch (error) {
    console.error('Login error:', error);
    res.status(500).json({ error: 'Login failed', message: error.message });
  }
});

// Register endpoint (basic)
router.post('/register', async (req, res) => {
  try {
    const {
      username,
      email,
      password,
      first_name,
      last_name,
      contact_number,
      barangay,
      date_of_birth,
      philhealth_id,
    } = req.body;

    const normalizedEmail = normalizeIdentifier(email);
    const normalizedUsername = normalizeIdentifier(username || normalizedEmail) || `${normalizedEmail.split('@')[0]}_${Date.now()}`;

    if (!normalizedEmail || !password || !first_name || !last_name) {
      return res.status(400).json({ error: 'Email, password, first name, and last name are required' });
    }

    const [existingUsers] = await pool.query(
      'SELECT id FROM users WHERE username = ? OR email = ?',
      [normalizedUsername, normalizedEmail]
    );

    if (existingUsers.length > 0) {
      return res.status(400).json({ error: 'User already exists' });
    }

    const [roles] = await pool.query(
      'SELECT id FROM roles WHERE name = ?',
      ['RESIDENT']
    );

    if (roles.length === 0) {
      return res.status(500).json({ error: 'Default resident role not found' });
    }

    const passwordHash = await bcrypt.hash(password, 10);

    const [result] = await pool.query(
      `INSERT INTO users (username, email, password_hash, first_name, last_name, role_id)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [normalizedUsername, normalizedEmail, passwordHash, first_name, last_name, roles[0].id]
    );

    const [residentResult] = await pool.query(
      `INSERT INTO residents
       (first_name, last_name, middle_name, date_of_birth, gender, contact_number, email, address, barangay, philhealth_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        first_name,
        last_name,
        '',
        date_of_birth || null,
        'Not specified',
        contact_number || null,
        normalizedEmail,
        'Not provided',
        barangay || 'Not provided',
        philhealth_id || null,
      ]
    );

    res.status(201).json({
      id: result.insertId,
      residentId: residentResult.insertId,
      message: 'Registration successful'
    });
  } catch (error) {
    console.error('Registration error:', error);
    res.status(500).json({ error: 'Registration failed', message: error.message });
  }
});

export default router;
