<?php
// api/check_approval_status.php – Check if admin approval token is available
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance.php';

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'msg' => 'Not authenticated.']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? 'check_in';
$uid = current_user_id();

if (!in_array($action, ['check_in', 'check_out'], true)) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid action.']);
    exit;
}

$status = check_pending_approval_token($uid, $action);
echo json_encode(array_merge(['ok' => true], $status));
