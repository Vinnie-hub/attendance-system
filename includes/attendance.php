<?php
// includes/attendance.php  – core attendance logic

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// ─── Status helpers ──────────────────────────────────────────

function calc_status(string $checkInTime): string {
    $in     = strtotime($checkInTime);
    $cutoff = strtotime(WORK_START . ':00') + (GRACE_MINUTES * 60);
    return ($in <= $cutoff) ? 'on_time' : 'late';
}


function calc_work_hours(?string $checkIn, ?string $checkOut): ?float {
    if (!$checkIn || !$checkOut) return null;
    $diff = strtotime($checkOut) - strtotime($checkIn);
    return round($diff / 3600, 2);
}

// ─── Attendance CRUD ─────────────────────────────────────────

function get_today_record(int $userId): array|false {
    $stmt = db_query(
        'SELECT * FROM attendance WHERE user_id = ? AND attendance_date = CURDATE() LIMIT 1',
        [$userId]
    );
    return $stmt->fetch();
}


function gps_gate(?float $lat, ?float $lng, string $action, ?float $accuracy = null, ?string $method = 'gps'): ?array {
    // If GPS is optional and method is not GPS, skip GPS-specific validation
    if (GPS_OPTIONAL && $method !== 'gps' && $method !== 'qr') {
        // For WiFi/manual methods, just check geofence (no accuracy requirement)
        if ($lat === null || $lng === null) return null;
        
        $office = get_office_location();
        $dist = haversine($lat, $lng, $office['latitude'], $office['longitude']);
        
        if ($dist <= $office['radius_m']) {
            return null;
        }
        
        return [
            'ok' => false,
            'msg' => "You are not within the {$office['name']} office radius "
                   . "(you are ~" . number_format($dist) . " m away; allowed "
                   . number_format($office['radius_m']) . " m). Please move closer to {$action}.",
            'distance_m' => $dist,
            'method' => $method,
        ];
    }
    
    // Original strict GPS validation (when GPS_REQUIRED or method is GPS/QR)
    if ($lat === null || $lng === null) return null;

    $office = get_office_location();

  
    $buffer  = $accuracy !== null ? min($accuracy, MAX_ACCURACY_BUFFER_M) : 0.0;
    $dist    = haversine($lat, $lng, $office['latitude'], $office['longitude']);
    $allowed = (float)$office['radius_m'] + $buffer;

    if ($dist <= $allowed) return null;

    // Only hard-block on poor accuracy when the user is *also* outside.
    if ($accuracy !== null && $accuracy > MAX_ACCEPTABLE_ACCURACY_M) {
        return [
            'ok'  => false,
            'msg' => "Your GPS signal is weak (±" . round($accuracy) . " m) and you appear to be "
                   . "~" . number_format($dist) . " m away from the office. "
                   . "Move outside or near a window, wait a few seconds, and try again.",
            'accuracy_m' => $accuracy,
            'distance_m' => $dist,
            'method'     => $method,
        ];
    }

    return [
        'ok'  => false,
        'msg' => "You are not within the {$office['name']} office radius "
               . "(you are ~" . number_format($dist) . " m away; allowed "
               . number_format($allowed) . " m). Please move closer to {$action}.",
        'distance_m' => $dist,
        'method' => $method,
    ];
}

function do_check_in(int $userId, ?float $lat = null, ?float $lng = null, ?float $accuracy = null, ?string $method = 'gps'): array {
    if (get_today_record($userId)) {
        return ['ok' => false, 'msg' => 'You have already checked in today.'];
    }
    if ($err = gps_gate($lat, $lng, 'check in', $accuracy, $method)) return $err;

    $now    = date('Y-m-d H:i:s');
    $status = calc_status(date('H:i:s'));
    
    // Create audit record (gracefully handle missing table)
    $auditId = null;
    if ($lat !== null && $lng !== null) {
        $office = get_office_location();
        $distance = haversine($lat, $lng, $office['latitude'], $office['longitude']);
        $isWithinGeofence = $distance <= ($office['radius_m'] + (min($accuracy ?? 0, MAX_ACCURACY_BUFFER_M)));
        
        try {
            db_query(
                'INSERT INTO geolocation_audit (user_id, action, geolocation_method, latitude, longitude, accuracy_m, distance_m, is_within_geofence)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$userId, 'check_in', $method, $lat, $lng, $accuracy, $distance, $isWithinGeofence ? 1 : 0]
            );
            $auditId = db_query('SELECT LAST_INSERT_ID() as id')->fetch()['id'];
        } catch (Throwable $e) {
            // Table may not exist — skip audit logging; check-in still works
            $auditId = null;
        }
    }

    db_query(
        'INSERT INTO attendance (user_id, attendance_date, check_in_time, check_in_lat, check_in_lng, status, geolocation_method, gps_accuracy_m, audit_id)
         VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)',
        [$userId, $now, $lat, $lng, $status, $method, $accuracy, $auditId]
    );

    return ['ok' => true, 'status' => $status, 'time' => $now, 'method' => $method];
}

function do_check_out(int $userId, ?float $lat = null, ?float $lng = null, ?float $accuracy = null, ?string $method = 'gps'): array {
    $record = get_today_record($userId);
    if (!$record) {
        return ['ok' => false, 'msg' => 'You have not checked in today.'];
    }
    if ($record['check_out_time']) {
        return ['ok' => false, 'msg' => 'You have already checked out today.'];
    }
    if ($err = gps_gate($lat, $lng, 'check out', $accuracy, $method)) return $err;

    $now   = date('Y-m-d H:i:s');
    $hours = calc_work_hours($record['check_in_time'], $now);

    $status = $record['status'];
    if ($hours !== null) {
        if ($hours < 4)        $status = 'half_day';
        elseif ($hours >= 8 && $status !== 'late')   $status = 'full_day';
    }
    
    // Create audit record for check-out (gracefully handle missing table)
    $auditId = null;
    if ($lat !== null && $lng !== null) {
        $office = get_office_location();
        $distance = haversine($lat, $lng, $office['latitude'], $office['longitude']);
        $isWithinGeofence = $distance <= ($office['radius_m'] + (min($accuracy ?? 0, MAX_ACCURACY_BUFFER_M)));
        
        try {
            db_query(
                'INSERT INTO geolocation_audit (user_id, action, geolocation_method, latitude, longitude, accuracy_m, distance_m, is_within_geofence)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$userId, 'check_out', $method, $lat, $lng, $accuracy, $distance, $isWithinGeofence ? 1 : 0]
            );
            $auditId = db_query('SELECT LAST_INSERT_ID() as id')->fetch()['id'];
        } catch (Throwable $e) {
            // Table may not exist — skip audit logging; check-out still works
            $auditId = null;
        }
    }

    db_query(
        'UPDATE attendance
            SET check_out_time = ?, check_out_lat = ?, check_out_lng = ?,
                work_hours = ?, status = ?, geolocation_method = ?, gps_accuracy_m = ?, audit_id = ?
          WHERE id = ?',
        [$now, $lat, $lng, $hours, $status, $method, $accuracy, $auditId, $record['id']]
    );

    return ['ok' => true, 'hours' => $hours, 'time' => $now, 'method' => $method];
}

// ─── Reporting helpers ────────────────────────────────────────

function get_attendance_for_user(int $userId, ?string $from = null, ?string $to = null): array {
    $from = $from ?: date('Y-m-01');
    $to   = $to   ?: date('Y-m-t');
    $stmt = db_query(
        'SELECT a.*, u.full_name, u.department
           FROM attendance a
           JOIN users u ON u.id = a.user_id
          WHERE a.user_id = ?
            AND a.attendance_date BETWEEN ? AND ?
          ORDER BY a.attendance_date DESC',
        [$userId, $from, $to]
    );
    return $stmt->fetchAll();
}

function get_all_attendance(?string $from = null, ?string $to = null, ?string $search = null): array {
    $from   = $from   ?: date('Y-m-01');
    $to     = $to     ?: date('Y-m-t');
    $params = [$from, $to];
    $where  = 'WHERE a.attendance_date BETWEEN ? AND ?';

    if ($search) {
        $where   .= ' AND (u.full_name LIKE ? OR DATE_FORMAT(a.attendance_date, "%d/%m/%Y") LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $stmt = db_query(
        "SELECT a.*, u.full_name, u.email, u.department, u.position
           FROM attendance a
           JOIN users u ON u.id = a.user_id
           $where
          ORDER BY a.attendance_date DESC, u.full_name ASC",
        $params
    );
    return $stmt->fetchAll();
}

function get_summary_stats(int $userId, string $month): array {
    // month format: Y-m
    $from = $month . '-01';
    $to   = date('Y-m-t', strtotime($from));

    $stmt = db_query(
        'SELECT
            SUM(CASE WHEN status != "absent" THEN 1 ELSE 0 END) AS total_days,
            SUM(CASE WHEN status = "on_time" THEN 1 ELSE 0 END) AS on_time,
            SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) AS late,
            SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) AS absent,
            SUM(CASE WHEN status = "half_day" THEN 1 ELSE 0 END) AS half_day,
            SUM(CASE WHEN status = "full_day" THEN 1 ELSE 0 END) AS full_day,
            ROUND(AVG(work_hours), 2) AS avg_hours,
            ROUND(SUM(work_hours), 2) AS total_hours
           FROM attendance
          WHERE user_id = ? AND attendance_date BETWEEN ? AND ?',
        [$userId, $from, $to]
    );
    $result = $stmt->fetch();
    return $result ?: [
        'total_days' => 0,
        'on_time' => 0,
        'late' => 0,
        'absent' => 0,
        'half_day' => 0,
        'full_day' => 0,
        'avg_hours' => 0,
        'total_hours' => 0
    ];
}

// ─── GPS distance check ───────────────────────────────────────

/**
 * Haversine formula — returns distance in metres.
 */
function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R    = 6371000;
    $phi1 = deg2rad($lat1);
    $phi2 = deg2rad($lat2);
    $dphi = deg2rad($lat2 - $lat1);
    $dlam = deg2rad($lng2 - $lng1);
    $a    = sin($dphi/2)**2 + cos($phi1)*cos($phi2)*sin($dlam/2)**2;
    return round($R * 2 * asin(sqrt($a)), 2);
}


function get_office_location(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $row = false;
    try {
        $stmt = db_query('SELECT name, latitude, longitude, radius_m FROM office_location WHERE is_active = 1 LIMIT 1');
        $row  = $stmt->fetch();
    } catch (Throwable $e) {
        $row = false;
    }

    if ($row) {
        return $cache = [
            'name'      => $row['name'],
            'latitude'  => (float)$row['latitude'],
            'longitude' => (float)$row['longitude'],
            'radius_m'  => (float)$row['radius_m'],
        ];
    }

    return $cache = [
        'name'      => defined('OFFICE_NAME')  ? OFFICE_NAME          : 'Office',
        'latitude'  => defined('OFFICE_LAT')   ? OFFICE_LAT           : 0.0,
        'longitude' => defined('OFFICE_LNG')   ? OFFICE_LNG           : 0.0,
        'radius_m'  => defined('GPS_RADIUS_M') ? (float)GPS_RADIUS_M  : 700.0,
    ];
}

function get_office_name(): string {
    return get_office_location()['name'];
}

function get_distance_from_office(float $lat, float $lng): float {
    $o = get_office_location();
    return haversine($lat, $lng, $o['latitude'], $o['longitude']);
}


function is_within_office(float $lat, float $lng, ?float $accuracy = null): bool {
    $o = get_office_location();
    $radius = $o['radius_m'];
    // Apply same accuracy buffer as the frontend: radius + min(accuracy, 100m)
    if ($accuracy !== null && $accuracy >= 0) {
        $radius += min($accuracy, MAX_ACCURACY_BUFFER_M);
    }
    return haversine($lat, $lng, $o['latitude'], $o['longitude']) <= $radius;
}


function get_current_status(int $userId): array {
    $record = get_today_record($userId);
    if (!$record) {
        return ['status' => 'not_checked_in', 'message' => 'Not checked in today'];
    }
    if ($record['check_out_time']) {
        return ['status' => 'checked_out', 'message' => 'Checked out', 'hours' => $record['work_hours']];
    }
    return ['status' => 'checked_in', 'message' => 'Checked in', 'time' => $record['check_in_time']];
}

/**
 * Generate a time-limited admin approval token for a user.
 *
 * @param int $userId          User attempting check-in/out
 * @param int $adminId         Admin approving the check-in
 * @param string $action       'check_in' or 'check_out'
 * @return array               {token: string, expires_at: string}
 */
function generate_admin_approval_token(int $userId, int $adminId, string $action = 'check_in'): array {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + (ADMIN_APPROVAL_TOKEN_EXPIRY_MINUTES * 60));
    
    db_query(
        'INSERT INTO admin_approval_tokens (user_id, admin_id, token, action, expires_at)
         VALUES (?, ?, ?, ?, ?)',
        [$userId, $adminId, $token, $action, $expiresAt]
    );
    
    return [
        'ok' => true,
        'token' => $token,
        'expires_at' => $expiresAt,
        'expires_in_minutes' => ADMIN_APPROVAL_TOKEN_EXPIRY_MINUTES
    ];
}

/**
 * Validate and consume an admin approval token.
 *
 * @param string $token       The approval token
 * @param int $userId         The user claiming to use this token
 * @param string $action      'check_in' or 'check_out'
 * @return array              {ok: bool, msg: string, admin_id: ?int}
 */
function validate_admin_approval_token(string $token, int $userId, string $action = 'check_in'): array {
    try {
        $stmt = db_query(
            'SELECT id, admin_id FROM admin_approval_tokens
             WHERE token = ? AND user_id = ? AND action = ? AND is_used = 0 AND expires_at > NOW()
             LIMIT 1',
            [$token, $userId, $action]
        );
        
        $tokenRecord = $stmt->fetch();
        if (!$tokenRecord) {
            return ['ok' => false, 'msg' => 'Invalid or expired approval token.', 'admin_id' => null];
        }
        
        // Mark token as used
        db_query(
            'UPDATE admin_approval_tokens SET is_used = 1, used_at = NOW() WHERE id = ?',
            [$tokenRecord['id']]
        );
        
        return ['ok' => true, 'msg' => 'Approval token validated.', 'admin_id' => (int)$tokenRecord['admin_id']];
    } catch (Throwable $e) {
        return ['ok' => false, 'msg' => 'Error validating approval token: ' . $e->getMessage(), 'admin_id' => null];
    }
}

/**
 * Check if user has a pending (unused, non-expired) approval token.
 *
 * @param int $userId  The user
 * @param string $action  'check_in' or 'check_out'
 * @return array       {has_pending: bool, expires_in_seconds: ?int}
 */
function check_pending_approval_token(int $userId, string $action = 'check_in'): array {
    try {
        $stmt = db_query(
            'SELECT UNIX_TIMESTAMP(expires_at) - UNIX_TIMESTAMP(NOW()) as expires_in
             FROM admin_approval_tokens
             WHERE user_id = ? AND action = ? AND is_used = 0 AND expires_at > NOW()
             ORDER BY created_at DESC
             LIMIT 1',
            [$userId, $action]
        );
        
        $result = $stmt->fetch();
        if ($result) {
            return ['has_pending' => true, 'expires_in_seconds' => max(0, (int)$result['expires_in'])];
        }
        
        return ['has_pending' => false, 'expires_in_seconds' => null];
    } catch (Throwable $e) {
        return ['has_pending' => false, 'expires_in_seconds' => null];
    }
}