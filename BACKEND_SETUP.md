# RHU Backend Setup Guide

## Prerequisites
- **XAMPP** installed and running (MySQL module enabled)
- **Node.js** (v16 or higher)
- **npm** or **pnpm** package manager

---

## Step 1: Start XAMPP and MySQL

1. **Open XAMPP Control Panel**
   - Go to `C:\xampp\xampp-control.exe` (Windows)
   - Click **Start** next to **MySQL**
   - You should see a green checkmark

2. **Verify MySQL is running**
   ```powershell
   mysql -u root
   ```
   If successful, you'll see the `mysql>` prompt.

3. **Create the `rhu` database** (if not already created)
   ```sql
   CREATE DATABASE rhu;
   EXIT;
   ```

---

## Step 2: Import Database Schema

1. **Navigate to the project root directory**
   ```powershell
   cd "C:\Users\chedr\Downloads\RHU PROTOTYPE (2)"
   ```

2. **Import the database schema**
   ```powershell
   mysql -u root rhu < database_schema.sql
   ```

3. **Verify the import** (optional)
   ```powershell
   mysql -u root rhu -e "SHOW TABLES;"
   ```
   You should see 25+ tables listed.

---

## Step 3: Install Backend Dependencies

1. **Navigate to backend folder**
   ```powershell
   cd backend
   ```

2. **Install Node.js dependencies**
   ```powershell
   npm install
   ```
   Or with pnpm:
   ```powershell
   pnpm install
   ```

3. **Verify installation**
   ```powershell
   npm list
   ```

---

## Step 4: Configure Environment Variables

The `.env` file has already been created in the root directory with these defaults:

```env
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=
DB_NAME=rhu
PORT=5000
NODE_ENV=development
CORS_ORIGIN=http://localhost:5173
```

**If your MySQL has a password**, update `.env`:
```env
DB_PASSWORD=your_mysql_password
```

---

## Step 5: Start the Backend Server

1. **From the backend directory**
   ```powershell
   npm run dev
   ```

2. **You should see output like:**
   ```
   🚀 RHU Backend API running on http://localhost:5000
   📊 Database: rhu on localhost:3306
   ✅ Connected to MySQL database successfully
   ```

3. **Test the connection** (open new terminal)
   ```powershell
   curl http://localhost:5000/api/health
   ```
   Expected response:
   ```json
   {
     "status": "ok",
     "message": "RHU Backend API is running",
     "timestamp": "2026-07-15T..."
   }
   ```

---

## Step 6: Start the Frontend (in another terminal)

1. **Navigate to project root**
   ```powershell
   cd "C:\Users\chedr\Downloads\RHU PROTOTYPE (2)"
   ```

2. **Install frontend dependencies** (if not done)
   ```powershell
   npm install
   ```

3. **Start the dev server**
   ```powershell
   npm run dev
   ```

4. **Open in browser**
   ```
   http://localhost:5173
   ```

---

## Available API Endpoints

### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/register` - User registration

### Residents
- `GET /api/residents` - Get all residents
- `GET /api/residents/:id` - Get resident by ID
- `POST /api/residents` - Create new resident
- `PUT /api/residents/:id` - Update resident
- `DELETE /api/residents/:id` - Delete resident

### Donors
- `GET /api/donors` - Get all donors
- `GET /api/donors/blood-type/:blood_type` - Get donors by blood type
- `GET /api/donors/available` - Get available donors
- `POST /api/donors` - Register new donor

### Staff
- `GET /api/staff` - Get all staff
- `GET /api/staff/type/:staff_type` - Get staff by type
- `GET /api/staff/bhw/barangay/:barangay` - Get BHWs by barangay

### Consultations
- `GET /api/consultations` - Get all consultations
- `GET /api/consultations/resident/:resident_id` - Get resident consultations
- `POST /api/consultations` - Create consultation

---

## Test with API Examples

### Create a Resident
```powershell
$body = @{
    first_name = "Juan"
    last_name = "Dela Cruz"
    date_of_birth = "1990-01-15"
    gender = "M"
    email = "juan@example.com"
    contact_number = "09171234567"
    address = "123 Main St"
    barangay = "San Isidro"
    blood_type = "O+"
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost:5000/api/residents" `
  -Method POST `
  -Body $body `
  -ContentType "application/json"
```

### Get All Residents
```powershell
Invoke-WebRequest -Uri "http://localhost:5000/api/residents" `
  -Method GET | ConvertFrom-Json
```

---

## Troubleshooting

### ❌ "Cannot connect to MySQL"
- **Check**: Is XAMPP MySQL running? (Green checkmark in control panel)
- **Fix**: Start MySQL from XAMPP control panel
- **Alternative**: `net start MySQL80` (Windows PowerShell as Admin)

### ❌ "Database rhu not found"
- **Check**: Did you import the schema?
- **Fix**: 
  ```powershell
  mysql -u root rhu < database_schema.sql
  ```

### ❌ "Port 5000 already in use"
- **Check**: Another process is using port 5000
- **Fix**: Change PORT in `.env` or kill the process
  ```powershell
  Get-NetTCPConnection -LocalPort 5000 | Stop-Process -Force
  ```

### ❌ "Module not found" errors
- **Fix**: Reinstall dependencies
  ```powershell
  cd backend
  rm -r node_modules
  npm install
  ```

### ❌ CORS errors in frontend
- **Check**: Backend server is running on port 5000
- **Check**: CORS_ORIGIN in `.env` matches frontend URL
- **Default**: `http://localhost:5173`

---

## Project Structure

```
RHU PROTOTYPE (2)/
├── backend/
│   ├── src/
│   │   ├── config/
│   │   │   └── database.js          # MySQL connection pool
│   │   ├── controllers/
│   │   ├── middleware/
│   │   ├── routes/
│   │   │   ├── auth.js              # Authentication endpoints
│   │   │   ├── residents.js         # Resident management
│   │   │   ├── donors.js            # Blood donor endpoints
│   │   │   ├── staff.js             # Staff endpoints
│   │   │   └── consultations.js     # Consultation endpoints
│   │   └── middleware/
│   ├── package.json
│   └── server.js                    # Express server entry point
├── src/
│   ├── components/                  # React components
│   ├── App.tsx                      # Main React app
│   └── main.tsx
├── .env                             # Database configuration
├── database_schema.sql              # MySQL schema
├── package.json                     # Frontend dependencies
└── vite.config.ts                   # Vite config
```

---

## Next Steps

1. ✅ **Backend is set up** - Running on `http://localhost:5000`
2. **Update React components** to call the backend API instead of mock data
3. **Implement proper authentication** with JWT tokens
4. **Add more features**:
   - Blood donation matching algorithm
   - Disease surveillance reporting
   - TB-DOTS tracking
   - Maternal health monitoring

---

## Important Notes

⚠️ **Security Reminders**:
- Never commit `.env` file with real credentials to Git
- Implement proper password hashing in production (bcryptjs)
- Use JWT tokens for API authentication
- Add input validation and sanitization
- Implement rate limiting for public endpoints

---

## Support

If you encounter issues:
1. Check that MySQL is running in XAMPP
2. Verify `.env` database credentials
3. Check terminal output for error messages
4. Review API endpoint documentation above
