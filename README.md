# AttendTrack – Employee Attendance Management System
## Complete Setup Guide

---

## Project Structure

```
attendance-system/
├── index.php                  ← Login page
├── database.sql               ← Full DB schema + seed data
├── README.md
│
├── includes/
│   ├── config.php             ← App config & constants
│   ├── db.php                 ← PDO singleton + helper
│   ├── auth.php               ← Session auth & guards
│   ├── attendance.php         ← Business logic
│   ├── header.php             ← Shared HTML header / sidebar
│   └── footer.php             ← Shared footer
│
├── employee/
│   ├── dashboard.php          ← Employee home with stats
│   ├── checkin.php            ← Check-in / Check-out + QR
│   └── history.php            ← Personal attendance history
│
├── admin/
│   ├── dashboard.php          ← Admin overview & charts
│   ├── employees.php          ← Add / Edit / Remove staff
│   ├── attendance.php         ← All records with search
│   └── reports.php            ← Monthly reports + charts
│
├── api/
│   ├── attendance.php         ← JSON API (check-in/out)
│   ├── export.php             ← PDF & CSV/Excel export
│   ├── qr_scan.php            ← QR code handler
│   └── logout.php             ← Session logout
│
└── assets/
    ├── css/app.css            ← Custom stylesheet
    └── js/app.js              ← Charts, GPS, QR helpers
```

---

## Local Setup with XAMPP

### 1. Install XAMPP
Download from https://www.apachefriends.org and install.

### 2. Copy files
```
C:\xampp\htdocs\attendance-system\    (Windows)
/Applications/XAMPP/htdocs/attendance-system/   (macOS)
```

### 3. Create database
1. Start Apache + MySQL in XAMPP Control Panel
2. Go to http://localhost/phpmyadmin
3. Click **New** → Name: `attendance_db` → Create
4. Click **Import** → Choose `database.sql` → Go

### 4. Configure connection
Edit `includes/config.php` if needed:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // blank for XAMPP default
define('DB_NAME', 'attendance_db');
define('BASE_URL', '/attendance-system');
```

### 5. Launch
Open http://localhost/attendance-system

**Admin login:**
- Email: `admin@company.com`
- Password: `Admin@1234`

**Employee login:**
- Email: `alice@company.com`
- Password: `Pass@1234`

---

## Shared Hosting Deployment

### 1. Upload files
Upload all files to `public_html/attendance-system/` via FTP (FileZilla) or cPanel File Manager.

### 2. Create database via cPanel
- Go to **MySQL Databases** in cPanel
- Create database, user, and assign all privileges
- Import `database.sql` via **phpMyAdmin**

### 3. Update config
```php
define('DB_HOST', 'localhost');   // usually localhost
define('DB_USER', 'cpanelusername_dbuser');
define('DB_PASS', 'your_password');
define('DB_NAME', 'cpanelusername_attendance_db');
define('BASE_URL', '/attendance-system');  // or '' if in root
```

### 4. PHP 8 requirement
Ensure your hosting uses PHP 8.0+. In cPanel → **PHP Selector** or `.htaccess`:
```
AddType application/x-httpd-php8 .php
```

---

## Features Summary

| Feature | Status |
|---|---|
| Secure login with bcrypt password hashing | ✅ |
| Role-based access (Admin / Employee) | ✅ |
| Check-in & Check-out with timestamp | ✅ |
| Duplicate check-in prevention | ✅ |
| On Time / Late status (configurable grace period) | ✅ |
| GPS location verification (toggle on/off) | ✅ |
| QR Code attendance option | ✅ |
| Employee dashboard with monthly stats | ✅ |
| Admin dashboard with live charts | ✅ |
| Employee management (Add/Edit/Remove) | ✅ |
| Attendance history with date filter | ✅ |
| Search by name or date | ✅ |
| Monthly attendance reports | ✅ |
| Export to PDF (print-ready) | ✅ |
| Export to Excel (CSV) | ✅ |
| Responsive Bootstrap 5 design | ✅ |
| Real-time clock display | ✅ |

---

## Enabling GPS Verification

In `includes/config.php`:
```php
define('GPS_REQUIRED', true);
define('GPS_RADIUS_M', 200);   // metres from office
```

Update office coordinates in the database:
```sql
UPDATE office_location
SET latitude = -1.2921, longitude = 36.8219, radius_m = 200
WHERE id = 1;
```

---

## Security Notes

- Passwords are hashed with bcrypt (cost 12)
- Session IDs are regenerated on login to prevent fixation
- All DB queries use prepared statements (no SQL injection)
- User input is escaped with `htmlspecialchars()` on output
- Admin routes are protected by `require_admin()` guards
- QR tokens are HMAC-like hashed and expire daily

---

## Tech Stack

- **Backend:** PHP 8.0+
- **Database:** MySQL 5.7+ / MariaDB 10.3+
- **Frontend:** Bootstrap 5.3, Chart.js 4, Bootstrap Icons
- **Fonts:** Inter + Space Grotesk (Google Fonts)
- **QR:** qrcode.js (CDN)
- **Maps:** GPS via native browser Geolocation API
