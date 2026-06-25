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

/**
 * Read a string parameter from JSON or POST
 */
function read_string(array $input, string $key, ?string $default = null): ?string {
    $raw = $input[$key] ?? $_POST[$key] ?? $default;
    return $raw ? (string)$raw : $default;
}

$lat = read_coord($input, 'lat');
$lng = read_coord($input, 'lng');
$accuracy = read_coord($input, 'accuracy');
$method = read_string($input, 'method', 'gps');
$adminToken = read_string($input, 'admin_override_token');
$uid = current_user_id();

$isCheckIn = ($action === 'check_in');
$label     = $isCheckIn ? 'check-in' : 'check-out';

// Validate geolocation method
if (!in_array($method, ['gps', 'wifi', 'manual', 'qr'], true)) {
    echo json_encode([
        'ok'  => false,
        'msg' => 'Invalid geolocation method.',
    ]);
    exit;
}

// Handle admin override for WiFi/manual methods
if ($method === 'wifi' || $method === 'manual') {
    if (ADMIN_APPROVAL_REQUIRED_FOR_FALLBACK) {
        if (!$adminToken) {
            echo json_encode([
                'ok'  => false,
                'msg' => 'Admin approval required for ' . $method . ' check-in. Request an approval code from your administrator.',
                'requires_admin_approval' => true,
                'method' => $method,
            ]);
            exit;
        }
        
        // Validate admin token
        $tokenValidation = validate_admin_approval_token($adminToken, $uid, $action);
        if (!$tokenValidation['ok']) {
            echo json_encode([
                'ok'  => false,
                'msg' => $tokenValidation['msg'],
                'requires_admin_approval' => true,
                'method' => $method,
            ]);
            exit;
        }
    }
}

// GPS check (when GPS_REQUIRED and method is gps/qr)
if (($method === 'gps' || $method === 'qr') && ($lat === null || $lng === null)) {
    echo json_encode([
        'ok'  => false,
        'msg' => "GPS coordinates are required for {$label}. Please enable location access and try again.",
        'method' => $method,
    ]);
    exit;
}

// WiFi/manual methods also require coordinates
if (($method === 'wifi' || $method === 'manual') && ($lat === null || $lng === null)) {
    echo json_encode([
        'ok'  => false,
        'msg' => "Location coordinates are required for {$label}.",
        'method' => $method,
    ]);
    exit;
}

// Range sanity check (if coordinates provided)
if ($lat !== null && $lng !== null) {
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid GPS coordinates received.']);
        exit;
    }
}

$result = match($action) {
    'check_in'  => do_check_in($uid, $lat, $lng, $accuracy, $method),
    'check_out' => do_check_out($uid, $lat, $lng, $accuracy, $method),
    default     => ['ok' => false, 'msg' => 'Invalid action.']
};

echo json_encode($result);
