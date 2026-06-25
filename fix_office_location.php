<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

echo '<h2>🔧 Database Repair Tool</h2>';
echo '<pre>';

try {
    // ─── Step 1: Create geolocation_audit table if missing ───
    echo "\n--- Step 1: Checking geolocation_audit table ---\n";
    try {
        db_query('SELECT 1 FROM geolocation_audit LIMIT 1');
        echo "✅ geolocation_audit table exists.\n";
    } catch (Throwable $e) {
        echo "⚠ Table missing. Creating it...\n";
        db_query("
            CREATE TABLE IF NOT EXISTS geolocation_audit (
              id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id               INT UNSIGNED NOT NULL,
              action                ENUM('check_in','check_out') NOT NULL,
              geolocation_method    ENUM('gps','wifi','manual','qr') NOT NULL,
              latitude              DECIMAL(10,7) NOT NULL,
              longitude             DECIMAL(10,7) NOT NULL,
              accuracy_m            INT UNSIGNED DEFAULT NULL,
              distance_m            INT UNSIGNED DEFAULT NULL,
              is_within_geofence    TINYINT(1) NOT NULL DEFAULT 0,
              approved_by_admin_id  INT UNSIGNED DEFAULT NULL,
              override_reason       VARCHAR(255) DEFAULT NULL,
              created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              FOREIGN KEY (approved_by_admin_id) REFERENCES users(id) ON DELETE SET NULL,
              KEY idx_user_date (user_id, created_at),
              KEY idx_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✅ geolocation_audit table created successfully!\n";
    }

    // ─── Step 2: Create admin_approval_tokens table if missing ───
    echo "\n--- Step 2: Checking admin_approval_tokens table ---\n";
    try {
        db_query('SELECT 1 FROM admin_approval_tokens LIMIT 1');
        echo "✅ admin_approval_tokens table exists.\n";
    } catch (Throwable $e) {
        echo "⚠ Table missing. Creating it...\n";
        db_query("
            CREATE TABLE IF NOT EXISTS admin_approval_tokens (
              id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id         INT UNSIGNED NOT NULL,
              admin_id        INT UNSIGNED NOT NULL,
              token           VARCHAR(64)  NOT NULL UNIQUE,
              action          ENUM('check_in','check_out') NOT NULL,
              is_used         TINYINT(1) NOT NULL DEFAULT 0,
              used_at         DATETIME DEFAULT NULL,
              expires_at      DATETIME NOT NULL,
              created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
              KEY idx_token (token),
              KEY idx_user_expires (user_id, expires_at),
              KEY idx_used (is_used, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✅ admin_approval_tokens table created successfully!\n";
    }

    // ─── Step 3: Fix office_location coordinates ───
    echo "\n--- Step 3: Fixing office_location coordinates ---\n";
    
    // Check current values
    try {
        $stmt = db_query('SELECT id, name, latitude, longitude, radius_m FROM office_location WHERE is_active = 1 LIMIT 1');
        $current = $stmt->fetch();
    } catch (Throwable $e) {
        $current = false;
    }

    $newLat = -0.002704;
    $newLng = 34.608207;
    $newRadius = 700;
    $newName = 'ICT Department Service Desk (Siriba Branch)';

    if ($current) {
        echo "Current record (ID {$current['id']}):\n";
        echo "  Latitude:  {$current['latitude']}\n";
        echo "  Longitude: {$current['longitude']}\n";
        echo "  Radius:    {$current['radius_m']}m\n\n";

        db_query(
            'UPDATE office_location SET name = ?, latitude = ?, longitude = ?, radius_m = ? WHERE id = ?',
            [$newName, $newLat, $newLng, $newRadius, $current['id']]
        );
        echo "✅ Updated record ID {$current['id']}:\n";
    } else {
        echo "No active office_location record found. Creating...\n";
        db_query(
            'INSERT INTO office_location (name, latitude, longitude, radius_m, is_active) VALUES (?, ?, ?, ?, 1)',
            [$newName, $newLat, $newLng, $newRadius]
        );
        echo "✅ Inserted new record:\n";
    }

    echo "  Name:      {$newName}\n";
    echo "  Latitude:  {$newLat}\n";
    echo "  Longitude: {$newLng}\n";
    echo "  Radius:    {$newRadius}m\n\n";

    // ─── Step 4: Verify ───
    echo "--- Step 4: Verification ---\n";
    $verify = get_office_location();
    echo "Office location verified:\n";
    echo "  Name:      {$verify['name']}\n";
    echo "  Latitude:  {$verify['latitude']}\n";
    echo "  Longitude: {$verify['longitude']}\n";
    echo "  Radius:    {$verify['radius_m']}m\n";

    echo "\n" . str_repeat('=', 50) . "\n";
    echo "✅ ALL FIXES APPLIED SUCCESSFULLY!\n";
    echo "   - geolocation_audit table: ✅\n";
    echo "   - admin_approval_tokens table: ✅\n";
    echo "   - Office location updated to your exact coordinates ✅\n";
    echo "   - Radius set to 700m ✅\n";

} catch (Throwable $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
}

echo '</pre>';
echo '<p style="color:red;font-weight:bold;font-size:1.2em">⚠ DELETE THIS FILE AFTER USE — IT ALLOWS UNRESTRICTED DATABASE ACCESS</p>';