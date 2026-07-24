import pool from './src/config/database.js';

async function main() {
  try {
    const [rows] = await pool.query('SELECT id, email, first_name, last_name FROM residents ORDER BY id LIMIT 2');
    console.log('DB rows:', JSON.stringify(rows, null, 2));
    process.exit(0);
  } catch (error) {
    console.error('ERROR:', error);
    process.exit(1);
  }
}

main();
