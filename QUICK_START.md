# RHU Backend Connection Setup - Quick Reference

## What Was Set Up

✅ **Database Schema** (`database_schema.sql`)
- 50+ tables for all 14 health domains
- Default roles, diseases, vaccines, certificates
- Foreign keys and indexes for performance

✅ **Backend API** (`backend/` folder)
- Express.js server
- MySQL connection pool
- 5 main route modules (auth, residents, donors, staff, consultations)
- CORS and error handling middleware

✅ **Configuration Files**
- `.env` - Database connection settings
- `backend/package.json` - Node.js dependencies
- `BACKEND_SETUP.md` - Complete setup instructions

---

## Quick Start (3 Steps)

### 1. Start XAMPP MySQL
```powershell
# Open XAMPP Control Panel → Click Start (MySQL)
```

### 2. Import Database Schema
```powershell
cd "C:\Users\chedr\Downloads\RHU PROTOTYPE (2)"
mysql -u root rhu < database_schema.sql
```

### 3. Run Backend Server
```powershell
cd backend
npm install
npm run dev
```

---

## Then Start Frontend (New Terminal)
```powershell
cd "C:\Users\chedr\Downloads\RHU PROTOTYPE (2)"
npm run dev
```

---

## API Endpoints Ready to Use

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/health` | Health check |
| POST | `/api/auth/login` | User login |
| POST | `/api/auth/register` | User registration |
| GET | `/api/residents` | List residents |
| POST | `/api/residents` | Create resident |
| GET | `/api/donors` | List donors |
| GET | `/api/staff` | List staff |
| GET | `/api/consultations` | List consultations |

**Base URL**: `http://localhost:5000`

---

## File Structure

```
backend/
├── server.js                 ← Main entry point
├── package.json             ← Dependencies
├── src/
│   ├── config/database.js   ← MySQL connection
│   └── routes/
│       ├── auth.js
│       ├── residents.js
│       ├── donors.js
│       ├── staff.js
│       └── consultations.js
├── controllers/             ← (Ready for business logic)
└── middleware/              ← (Ready for auth middleware)
```

---

## Environment Variables (.env)

```env
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=              ← Add password if MySQL has one
DB_NAME=rhu
PORT=5000
CORS_ORIGIN=http://localhost:5173
```

---

## Test Connection

After backend starts, test it:
```powershell
curl http://localhost:5000/api/health

# Expected response:
# {"status":"ok","message":"RHU Backend API is running",...}
```

---

## Next Steps to Complete

1. **Frontend Integration**
   - Replace mock data calls with API calls
   - Update components to fetch from `/api/residents`, `/api/donors`, etc.
   - Add loading states and error handling

2. **Authentication**
   - Implement JWT token-based auth
   - Add login endpoint integration in frontend
   - Store tokens in localStorage/cookies

3. **Blood Donation Matching**
   - Implement matching algorithm in backend
   - Add `/api/blood-requests` and `/api/blood-matches` endpoints
   - Calculate distance and response probability

4. **Additional Features**
   - DOH reporting endpoints
   - Disease surveillance tracking
   - TB-DOTS adherence monitoring
   - Maternal health tracking

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| MySQL not starting | Open XAMPP Control Panel → Click Start |
| "Database not found" | Run: `mysql -u root rhu < database_schema.sql` |
| "Cannot find module" | Run: `npm install` in backend folder |
| Port 5000 in use | Change PORT in `.env` or kill process |
| CORS errors | Check backend is running on port 5000 |

---

## Default Database Credentials

```
Host: localhost
Port: 3306
Username: root
Password: (empty by default)
Database: rhu
```

If your XAMPP MySQL has a password, update `.env` file.

---

**For detailed setup instructions, see**: [BACKEND_SETUP.md](BACKEND_SETUP.md)
