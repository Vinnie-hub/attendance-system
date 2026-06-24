<?php
// includes/attendance.php  – core attendance logic

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// ─── Status helpers ──────────────────────────────────────────

/**
 * Determine status from check-in time string (H:i or H:i:s).
 */
function calc_status(string $checkInTime): string {
    $in     = strtotime($checkInTime);
    $cutoff = strtotime(WORK_START . ':00') + (GRACE_MINUTES * 60);
    return ($in <= $cutoff) ? 'on_time' : 'late';
}

/**
 * Compute work hours between two DATETIME strings.
 */
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

/**
 * Shared GPS gate: returns null when allowed, or an error array when the
 * supplied coordinates are outside the office fence.
 * Accepts optional GPS accuracy to apply a buffer matching the frontend logic.
 */
function gps_gate(?float $lat, ?float $lng, string $action, ?float $accuracy = null): ?array {
    if ($lat === null || $lng === null) return null; // legacy callers without GPS

    $office = get_office_location();

    // Reject obviously bad fixes (likely IP geolocation, not real GPS).
    if ($accuracy !== null && $accuracy > MAX_ACCEPTABLE_ACCURACY_M) {
        return [
            'ok'  => false,
            'msg' => "Your GPS fix is too imprecise (±" . round($accuracy) . " m). "
                   . "Move outside or near a window, wait a few seconds, and try again.",
        ];
    }

    // Effective radius = office radius + accuracy buffer (cap the buffer so
    // a noisy fix can't grant unlimited slack).
    $buffer  = $accuracy !== null ? min($accuracy, MAX_ACCURACY_BUFFER_M) : 0.0;
    $dist    = haversine($lat, $lng, $office['latitude'], $office['longitude']);
    $allowed = (float)$office['radius_m'] + $buffer;

    if ($dist <= $allowed) return null;

    return [
        'ok'  => false,
        'msg' => "You are not within the {$office['name']} office radius "
               . "(you are ~" . number_format($dist) . " m away; allowed "
               . number_format($allowed) . " m). Please move closer to {$action}.",
    ];
}

function do_check_in(int $userId, ?float $lat = null, ?float $lng = null, ?float $accuracy = null): array {
    if (get_today_record($userId)) {
        return ['ok' => false, 'msg' => 'You have already checked in today.'];
    }
    if ($err = gps_gate($lat, $lng, 'check in', $accuracy)) return $err;

    $now    = date('Y-m-d H:i:s');
    $status = calc_status(date('H:i:s'));

    db_query(
        'INSERT INTO attendance (user_id, attendance_date, check_in_time, check_in_lat, check_in_lng, status)
         VALUES (?, CURDATE(), ?, ?, ?, ?)',
        [$userId, $now, $lat, $lng, $status]
    );

    return ['ok' => true, 'status' => $status, 'time' => $now];
}

function do_check_out(int $userId, ?float $lat = null, ?float $lng = null, ?float $accuracy = null): array {
    $record = get_today_record($userId);
    if (!$record) {
        return ['ok' => false, 'msg' => 'You have not checked in today.'];
    }
    if ($record['check_out_time']) {
        return ['ok' => false, 'msg' => 'You have already checked out today.'];
    }
    if ($err = gps_gate($lat, $lng, 'check out', $accuracy)) return $err;

    $now   = date('Y-m-d H:i:s');
    $hours = calc_work_hours($record['check_in_time'], $now);

    $status = $record['status'];
    if ($hours !== null) {
        if ($hours < 4)        $status = 'half_day';
        elseif ($hours >= 8 && $status !== 'late')   $status = 'full_day';
    }

    db_query(
        'UPDATE attendance
            SET check_out_time = ?, check_out_lat = ?, check_out_lng = ?,
                work_hours = ?, status = ?
          WHERE id = ?',
        [$now, $lat, $lng, $hours, $status, $record['id']]
    );

    return ['ok' => true, 'hours' => $hours, 'time' => $now];
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

/**
 * Active office fence (DB row, or config fallback). Cached per request so
 * is_within_office / get_distance_from_office / get_office_name share one query.
 */
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

/**
 * Check whether the given coordinates are within the office fence.
 * Accepts optional GPS accuracy to add a buffer matching frontend logic.
 */
function is_within_office(float $lat, float $lng, ?float $accuracy = null): bool {
    $o = get_office_location();
    $radius = $o['radius_m'];
    // Apply same accuracy buffer as the frontend: radius + min(accuracy, 100m)
    if ($accuracy !== null && $accuracy >= 0) {
        $radius += min($accuracy, MAX_ACCURACY_BUFFER_M);
    }
    return haversine($lat, $lng, $o['latitude'], $o['longitude']) <= $radius;
}

/**
 * Get the user's current status (checked in or out)
 */
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