<?php
// api/qr_scan.php – handle QR code check-in/out scans
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';
require_login(BASE_URL . '/index.php');

$token  = $_GET['token']  ?? '';
$action = $_GET['action'] ?? '';

if (!in_array($action, ['check_in', 'check_out']) || !$token) {
    die('<h3>Invalid QR code.</h3>');
}

// Verify token matches today's session-based token
$uid       = current_user_id();
$expected  = hash('sha256', 'qr_' . date('Y-m-d') . '_' . $uid . '_' . session_id());

if (!hash_equals($expected, $token)) {
    die('<h3>QR code expired or invalid.</h3>');
}

// QR code attendance still validates GPS if enabled (accuracy is N/A for QR scans)
$result = ($action === 'check_in') ? do_check_in($uid, null, null, null) : do_check_out($uid, null, null, null);

header('Location: ' . BASE_URL . '/employee/checkin.php?qr=' . ($result['ok'] ? 'ok' : 'err') . '&msg=' . urlencode($result['msg'] ?? ''));
exit;
