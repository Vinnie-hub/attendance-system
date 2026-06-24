<?php
// includes/config.php – central configuration

// ── Environment auto-detection ────────────────────────────────
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$isLocal = in_array($serverName, ['localhost', '127.0.0.1', '::1']);

if ($isLocal) {
    // Local XAMPP development
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_NAME', 'attendance_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // Live server (freepage.cc / InfinityFree)
    define('DB_HOST', 'sql303.infinityfree.com');
    define('DB_PORT', '3306');
    define('DB_NAME', 'if0_42243455_attendance_db');
    define('DB_USER', 'if0_42243455');
    define('DB_PASS', 'Vin025mas');
}
define('DB_CHARSET', 'utf8mb4');

// Work schedule (mirrors work_schedule table default row)
define('WORK_START', '08:00');
define('WORK_END', '17:00');
define('GRACE_MINUTES', 15);

// GPS – default office location (Siriba Campus, Maseno)
define('GPS_REQUIRED', true);
define('GPS_RADIUS_M', 700);
define('OFFICE_LAT', -0.003496);
define('OFFICE_LNG', 34.610503);
define('OFFICE_NAME', 'ICT Department Service Desk (Siriba Branch)');

// GPS accuracy thresholds (must stay in sync with employee/checkin.php JS)
define('MAX_ACCURACY_BUFFER_M', 100);       // maximum added to office radius based on GPS accuracy
define('MAX_ACCEPTABLE_ACCURACY_M', 200);   // fixes worse than this are rejected outright

// App meta
define('APP_NAME', 'AttendTrack');
define('APP_VERSION', '1.0.0');

// App base URL — always points to the project root
// Local: /attendance-system   Live: (empty string for root domain)
if ($isLocal) {
    define('BASE_URL', '/attendance-system');
} else {
    define('BASE_URL', '');
}

define('TIMEZONE', 'Africa/Nairobi');

date_default_timezone_set(TIMEZONE);
