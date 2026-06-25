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

// Google Geolocation API (for precise location via WiFi/cell-tower fingerprinting)
define('GOOGLE_API_KEY', '');              // Set your Google API key (with Geolocation API enabled)
define('GOOGLE_GEO_API_ENABLED', true);    // Set to false to disable Google Geolocation API

// GPS – default office location (ICT Department Service Desk, Siriba Campus)
define('GPS_OPTIONAL', true);                           // Allow WiFi/manual fallback when GPS fails
define('WIFI_FALLBACK_ENABLED', true);                  // Use IP/WiFi geolocation as fallback
define('ADMIN_APPROVAL_REQUIRED_FOR_FALLBACK', false);  // Require admin token for WiFi/manual check-ins (set to true for strict enforcement)
define('ADMIN_APPROVAL_TOKEN_EXPIRY_MINUTES', 5);      // Token validity period

define('GPS_RADIUS_M', 700);
define('OFFICE_LAT', -0.002704);
define('OFFICE_LNG', 34.608207);
define('OFFICE_NAME', 'ICT Department Service Desk (Siriba Branch)');

// GPS accuracy thresholds (must stay in sync with employee/checkin.php JS)
define('MAX_ACCURACY_BUFFER_M', 100);       // maximum added to office radius based on GPS accuracy
define('MAX_ACCEPTABLE_ACCURACY_M', 500);   // fixes worse than this are rejected outright (increased for desktop/WiFi environments)
define('GPS_TIMEOUT_PHASE1_MS', 5000);      // High accuracy GPS attempt timeout (reduced for faster feedback)
define('GPS_TIMEOUT_PHASE2_MS', 4000);      // WiFi/IP geolocation timeout (reduced for faster feedback)
define('GPS_RETRY_ATTEMPTS', 2);            // Number of GPS retry attempts before fallback (reduced for faster feedback)

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