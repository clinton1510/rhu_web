import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import pool from './src/config/database.js';

// Import routes
import authRoutes from './src/routes/auth.js';
import residentsRoutes from './src/routes/residents.js';
import donorsRoutes from './src/routes/donors.js';
import staffRoutes from './src/routes/staff.js';
import consultationsRoutes from './src/routes/consultations.js';
import bloodRequestsRoutes from './src/routes/blood-requests.js';
import maternalRoutes from './src/routes/maternal.js';
import vaccinationRoutes from './src/routes/vaccination.js';
import diseaseRoutes from './src/routes/disease.js';
import analyticsRoutes from './src/routes/analytics.js';

dotenv.config();

const app = express();
const PORT = process.env.PORT || 5000;

// Middleware
const allowedOrigins = (process.env.CORS_ORIGIN || 'http://localhost:5173').split(',').map(origin => origin.trim());

// During local development, allow requests from any local origin so the
// frontend served at http://localhost/CAPSTONERHU can call the API.
// This is intentionally permissive for dev only.
app.use(cors({
  origin: true,
  credentials: true,
}));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Request logging middleware
app.use((req, res, next) => {
  console.log(`${new Date().toISOString()} - ${req.method} ${req.path}`);
  next();
});

// API Routes
app.use('/api/auth', authRoutes);
app.use('/api/residents', residentsRoutes);
app.use('/api/donors', donorsRoutes);
app.use('/api/staff', staffRoutes);
app.use('/api/consultations', consultationsRoutes);
app.use('/api/blood-requests', bloodRequestsRoutes);
app.use('/api/maternal', maternalRoutes);
app.use('/api/vaccination', vaccinationRoutes);
app.use('/api/disease', diseaseRoutes);
app.use('/api/analytics', analyticsRoutes);

// Health check endpoint
app.get('/api/health', (req, res) => {
  res.json({ 
    status: 'ok', 
    message: 'RHU Backend API is running',
    timestamp: new Date().toISOString()
  });
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error('Error:', err);
  res.status(500).json({ 
    error: 'Internal Server Error',
    message: err.message
  });
});

// 404 handler
app.use((req, res) => {
  res.status(404).json({ 
    error: 'Not Found',
    path: req.path
  });
});

// Start server
app.listen(PORT, () => {
  console.log(`🚀 RHU Backend API running on http://localhost:${PORT}`);
  console.log(`📊 Database: ${process.env.DB_NAME} on ${process.env.DB_HOST}:${process.env.DB_PORT}`);
});

export default app;
