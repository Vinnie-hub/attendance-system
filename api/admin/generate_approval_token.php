<?php
// api/admin/generate_approval_token.php – Admin endpoint to generate approval tokens for employees
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/attendance.php';

// Admin-only access
if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'msg' => 'Not authenticated.']);
    http_response_code(401);
    exit;
}

if (!is_admin()) {
    echo json_encode(['ok' => false, 'msg' => 'Admin access required.']);
    http_response_code(403);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$userId = $input['user_id'] ?? null;
$action = $input['action'] ?? 'check_in';
$adminId = current_user_id();

// Validate input
if (!$userId || !in_array($action, ['check_in', 'check_out'], true)) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid user_id or action.']);
    http_response_code(400);
    exit;
}

// Generate approval token
$result = generate_admin_approval_token($userId, $adminId, $action);

if ($result['ok']) {
    echo json_encode([
        'ok' => true,
        'msg' => 'Approval token generated successfully.',
        'token' => $result['token'],
        'expires_at' => $result['expires_at'],
        'expires_in_minutes' => $result['expires_in_minutes']
    ]);
} else {
    echo json_encode(['ok' => false, 'msg' => 'Error generating approval token.']);
    http_response_code(500);
}
