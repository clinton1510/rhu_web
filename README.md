# ResiHUnity Rural Health Unit System

ResiHUnity is a PHP and MySQL rural-health management system with separate portals for residents, RHU staff, Barangay Health Workers (BHW), and RHU administrators.

## Requirements

- XAMPP with Apache, PHP 8+, and MySQL/MariaDB
- Node.js and npm only when rebuilding the Vite front end
- Project directory: `C:\xampp\htdocs\RURAL-HEALTH-UNIT`

## Initial setup

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open phpMyAdmin at `http://localhost/phpmyadmin`.
3. Create a database named `rhu` if it does not already exist.
4. For a fresh installation, import `rhu (2).sql`.
5. Apply the SQL files in `database_migrations` in numeric order. The currently available migrations are:

   - `001_php_portal_content.sql`
   - `003_clinical_database_seed.sql`
   - `004_admin_dashboard_connectivity.sql`
   - `005_admin_workflows.sql`
   - `006_resident_dependents.sql`
   - `007_rhu_staff_real_data.sql`
   - `008_remaining_staff_data.sql`

6. Confirm the database configuration in `.env`. The usual XAMPP defaults are:

   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=rhu
   DB_USER=root
   DB_PASSWORD=
   ```

7. Open the system:

   ```text
   http://localhost/RURAL-HEALTH-UNIT/
   ```

The included `.htaccess` routes friendly URLs to the PHP components. Apache `mod_rewrite` must be enabled.

## Optional front-end development

The PHP dashboards work through XAMPP. Use these commands only when changing and rebuilding the Vite front end:

```bash
npm install
npm run dev
```

Create a production build with:

```bash
npm run build
```

## Portal addresses

| Portal | Address |
|---|---|
| Portal selection | `http://localhost/RURAL-HEALTH-UNIT/login` |
| Resident login | `http://localhost/RURAL-HEALTH-UNIT/resident/login` |
| RHU staff login | `http://localhost/RURAL-HEALTH-UNIT/rhu/login` |
| BHW login | `http://localhost/RURAL-HEALTH-UNIT/bhw/login` |
| RHU administrator login | `http://localhost/RURAL-HEALTH-UNIT/rhu/admin/login` |

## Current staff and administrator credentials

These accounts are stored in the MySQL `users` table and linked to the `staff` table through `staff.user_id`.

| Login portal | Select this role | Email | Password |
|---|---|---|---|
| RHU Staff | Public Health Nurse | `nurse01@rhu.local` | `Nurse01@RHU26!` |
| RHU Staff | Public Health Nurse | `nurse05@rhu.local` | `Nurse05@RHU26!` |
| RHU Staff | Medical Technologist | `medtech04@rhu.local` | `Medtech04@RHU26!` |
| RHU Staff | Municipal Health Officer | `admin06@rhu.local` | `Admin06@RHU26!` |
| RHU Staff | Municipal Health Officer | `admin07@rhu.local` | `Admin07@RHU26!` |
| RHU Staff | Administrative Staff | `23-75584@g.batstate-u.edu.ph` | `helloworld15` |
| RHU Administrator | Administrator | `admin06@rhu.local` | `Admin06@RHU26!` |
| RHU Administrator | Administrator | `admin07@rhu.local` | `Admin07@RHU26!` |

The `.local` addresses are database login identifiers and are not deliverable public email addresses.

There are currently no dedicated Midwife, Sanitary Inspector, Physician, or BHW login accounts. Create those accounts from the administrator's **Staff Accounts** section when real personnel names and email addresses are available.

> Security: Change all documented passwords before deploying the system outside a local development environment. Do not publish production credentials in this README.

## How to use the system

### Resident

1. Open the Resident Login page.
2. Register an account or sign in with an existing resident email and password.
3. Use the drawer to access the profile, health records, immunization history, certificates, appointments, dependents, and messages.
4. Add dependents from the Dependents section. Saved dependents are stored in MySQL.
5. Use Messages to contact the RHU.
6. Select **Log out** and confirm to securely end the session.

Residents cannot view system audit logs or staff/administrator pages.

### Public Health Nurse

1. Open RHU Staff Login.
2. Select **Public Health Nurse**.
3. Enter one of the nurse credentials listed above.
4. Use the drawer to review database-backed OPD consultations, patients, immunizations, nutrition cases, TB-DOTS cases, and BHW information.
5. Use the available forms to record consultations and other authorized nursing data.

### Midwife

1. Create and link a Midwife account from the administrator portal.
2. Open RHU Staff Login and select **Rural Health Midwife**.
3. Use the Midwife dashboard for maternal cases, prenatal care, immunization records, and prenatal consultations.
4. New pregnancy records are saved to the `pregnancies` table.

### Medical Technologist

1. Open RHU Staff Login.
2. Select **Medical Technologist**.
3. Sign in with `medtech04@rhu.local`.
4. Use the drawer to access Rapid Tests, Specimen Referrals, Test Supplies, and Reports.
5. Select **Record RDT**, **New Referral**, or **Add Supply** to save data to MySQL.
6. Report graphs are calculated from recorded diagnostic and referral data.

### Sanitary Inspector

1. Create and link a Sanitary Inspector account from the administrator portal.
2. Open RHU Staff Login and select **Sanitary Inspector**.
3. Use the dashboard to manage sanitation inspections, health certificates, and environmental disease surveillance.
4. Select **New Inspection** or **Issue Certificate** to store a new database record.

### Barangay Health Worker

1. Create and link a BHW account and BHW assignment from the administrator or RHU Staff portal.
2. Open BHW Login and enter the assigned email, password, barangay, and certification information.
3. Use the drawer to review donor referrals, blood drives, and blood-need reports.
4. New donor referrals and blood-need reports are saved permanently to MySQL.

### RHU Staff / Physician / Municipal Health Officer

1. Open RHU Staff Login.
2. Select the role that matches the database account.
3. Use the **Menu / All Services** drawer to access OPD, referrals, immunization, maternal health, family planning, TB-DOTS, nutrition, disease surveillance, vital statistics, medicine, sanitation, certificates, BHW management, reports, analytics, staff records, and audit logs.
4. Dashboard totals, tables, reports, and graphs are calculated from MySQL records.
5. FHSIS reports can be generated from monthly database totals.

### RHU Administrator

1. Open RHU Administrator Login.
2. Sign in using an account with the `RHU_ADMIN` or `SUPER_ADMIN` role.
3. If two-factor authentication is enabled, enter the exact code delivered by the configured mailer.
4. Use the administrator drawer to manage:

   - Users and staff accounts
   - Residents
   - OPD consultations
   - Maternal and vaccination records
   - Disease cases and medicine inventory
   - Vital statistics and health certificates
   - Landing-page announcements and events
   - DOH reports
   - Roles and permissions
   - Portal settings, backups, and security
   - Audit logs

5. Audit logs are visible only to authenticated staff and administrators. Residents are denied access.

## Navigation, drawers, and logout

- Every staff dashboard has a role-specific navigation drawer.
- **Menu** and **Log out** are positioned together in the header.
- Opening a drawer blurs the page behind it.
- Selecting **Log out** opens a confirmation dialog.
- Confirming logout destroys the server session and returns the user to the correct login portal.
- Logged-out users cannot reopen protected dashboards with the browser Back button or a direct URL.

## Database-backed modules

The system reads and writes real MySQL data for:

- Residents and dependents
- Staff and user accounts
- Consultations and referrals
- Vaccinations and immunization schedules
- Pregnancy and prenatal care
- Family planning
- TB-DOTS and adherence
- Nutrition profiles
- Disease surveillance
- Birth and death statistics
- Medicine stock transactions
- Health certificates
- Sanitation inspections
- Laboratory diagnostics, referrals, and supplies
- BHW donor referrals, blood drives, and blood-need reports
- FHSIS, PIDSR, and NTP-TB reports
- Announcements, events, notifications, messages, settings, and audit logs

Some connected modules may display an empty state until staff enter actual records.

## Verification

Run the PHP integration checks from the project directory:

```powershell
& 'C:\xampp\php\php.exe' tests\admin_dashboard_integration.php
& 'C:\xampp\php\php.exe' tests\rhu_staff_database_integration.php
& 'C:\xampp\php\php.exe' tests\remaining_staff_database_integration.php
& 'C:\xampp\php\php.exe' tests\rhu_staff_login_integration.php
& 'C:\xampp\php\php.exe' tests\resident_dependents_integration.php
```

These tests validate required tables, database workflows, staff linkage, credentials, and representative create/read operations. Integration fixtures are rolled back after testing.

## Important production notes

- Replace placeholder names and `.local` emails with real personnel information.
- Change all initial passwords.
- Configure SMTP before expecting real email or 2FA delivery.
- Enable HTTPS in production.
- Keep `.env`, database backups, and credential-bearing documentation outside public access.
- Back up the `rhu` database before applying migrations or making structural changes.
