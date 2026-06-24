<?php
// api/attendance.php  – JSON API for check-in / check-out
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'msg' => 'Not authenticated.']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_POST['action'] ?? '');

/**
 * Read a coordinate from JSON body or POST.
 * Returns null when missing, JSON-null, empty string, or non-numeric –
 * so an invalid value never silently becomes 0.0 (a valid lat/lng in the ocean).
 */
function read_coord(array $input, string $key): ?float {
    $raw = null;
    if (array_key_exists($key, $input)) {
        $raw = $input[$key];
    } elseif (array_key_exists($key, $_POST)) {
        $raw = $_POST[$key];
    }
    if ($raw === null || $raw === '' || !is_numeric($raw)) {
        return null;
    }
    return (float)$raw;
}

$lat = read_coord($input, 'lat');
$lng = read_coord($input, 'lng');
$uid = current_user_id();

$isCheckIn = ($action === 'check_in');
$label     = $isCheckIn ? 'check-in' : 'check-out';

// GPS check (when enabled, coordinates are mandatory)
if (GPS_REQUIRED) {
    if ($lat === null || $lng === null) {
        echo json_encode([
            'ok'  => false,
            'msg' => "GPS coordinates are required for {$label}. Please enable location access and try again.",
        ]);
        exit;
    }
    // Range sanity check
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid GPS coordinates received.']);
        exit;
    }
    if (!is_within_office($lat, $lng)) {
        $dist = get_distance_from_office($lat, $lng);
        echo json_encode([
            'ok'  => false,
            'msg' => "You are not within the office location (you are ~" . number_format($dist) . " m away). {$label} not allowed.",
        ]);
        exit;
    }
}

$result = match($action) {
    'check_in'  => do_check_in($uid, $lat, $lng),
    'check_out' => do_check_out($uid, $lat, $lng),
    default     => ['ok' => false, 'msg' => 'Invalid action.']
};

echo json_encode($result);
