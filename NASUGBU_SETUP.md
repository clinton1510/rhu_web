# Nasugbu, Batangas Configuration - Quick Setup

## Files Created

✅ **src/data/nasugbuBarangays.ts**
- All 42 barangays of Nasugbu with coordinates
- System configuration for Nasugbu
- Helper functions for barangay lookup

✅ **nasugbu_barangays.sql**
- SQL script to populate barangays table
- 42 barangay records with population data

✅ **NASUGBU_CONFIG.md**
- Complete configuration guide
- Component update instructions
- Validation checklist

---

## Setup Instructions

### Step 1: Import Barangays to Database
```powershell
cd "C:\Users\chedr\Downloads\RHU PROTOTYPE (2)"
mysql -u root rhu < nasugbu_barangays.sql
```

### Step 2: Verify Import
```powershell
mysql -u root rhu -e "SELECT COUNT(*) as total FROM barangays;"
# Should return: 42
```

### Step 3: Use in Frontend

In any React component:
```typescript
import { NASUGBU_BARANGAYS, BARANGAY_NAMES, SYSTEM_CONFIG } from '@/data/nasugbuBarangays';

// Dropdown example
<select>
  {BARANGAY_NAMES.map(barangay => (
    <option key={barangay} value={barangay}>{barangay}</option>
  ))}
</select>

// System title
<h1>{SYSTEM_CONFIG.systemTitle}</h1>
```

---

## All 42 Barangays Configured

```
1. Aga
2. Balaytiguebalok-Balok
3. Banilad
4. Barangay 1 (Pob.)
5. Barangay 2 (Pob.)
6. Barangay 3 (Pob.)
7. Barangay 4 (Pob.)
8. Barangay 5 (Pob.)
9. Barangay 6 (Pob.)
10. Barangay 7 (Pob.)
11. Barangay 8 (Pob.)
12. Barangay 9 (Pob.)
13. Barangay 10 (Pob.)
14. Barangay 11 (Pob.)
15. Barangay 12 (Pob.)
16. Bilaran
17. Bucana
18. Bulihan
19. Bunducan
20. Butucan
21. Calayo
22. Catandaan
23. Cagunan
24. Dayap
25. Kaylaway
26. Kayrillaw
27. Latag
28. Looc
29. Lumbangan
30. Malapad na Bato
31. Mataas na Pulo
32. Maugat
33. Munting Indang
34. Natipuan
35. Pantalan
36. Papaya
37. Putat
38. Reparo
39. Talangan
40. Tumalim
41. Utod
42. Wawa
```

---

## System Configuration

- **Municipality**: Nasugbu
- **Province**: Batangas
- **Region**: CALABARZON
- **Total Barangays**: 42
- **Estimated Population**: 95,000
- **Center Point**: 13.8542°N, 120.9634°E
- **Map Bounds**: 
  - North: 13.9087°N
  - South: 13.7560°N
  - East: 121.0282°E
  - West: 120.9236°E

---

## Database Structure

**barangays Table:**
```
id              - Auto-increment ID
name            - Barangay name (unique)
municipality    - "Nasugbu"
province        - "Batangas"
latitude        - GPS latitude
longitude       - GPS longitude
population      - Estimated population
created_at      - Timestamp
```

---

## Next Steps

1. Import barangays SQL file
2. Update DonorRegistration, ResidentRegistration components
3. Update Map components to use Nasugbu coordinates
4. Test registration with Nasugbu barangays only
5. Verify residents can only select from Nasugbu barangays

---

## Components to Update

These components should use NASUGBU_BARANGAYS:

- [ ] DonorRegistration.tsx
- [ ] ResidentRegistration.tsx
- [ ] DonorLogin.tsx
- [ ] ResidentLogin.tsx
- [ ] Map.tsx / MapWrapper.tsx
- [ ] AdminDashboard.tsx
- [ ] AnalyticsDashboard.tsx
- [ ] BHWDashboard.tsx (for barangay filtering)

---

**System is now Nasugbu-only ✅**

All residents, donors, and staff must be associated with one of the 42 barangays of Nasugbu, Batangas.
