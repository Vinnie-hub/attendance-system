<?php
// includes/auth.php  – session & authentication helpers

require_once __DIR__ . '/db.php';

// Harden session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', 0); // Set to 1 if you enable HTTPS
ini_set('session.use_only_cookies', 1);
if (!isset($_SESSION)) session_start();

// ─── Core helpers ───────────────────────────────────────────

function auth_login(string $email, string $password): array|false {
    $stmt = db_query(
        'SELECT id, full_name, email, password, role, is_active FROM users WHERE email = ? LIMIT 1',
        [strtolower(trim($email))]
    );
    $user = $stmt->fetch();

    if (!$user || !$user['is_active']) return false;
    if (!password_verify($password, $user['password'])) return false;

    // Regenerate session to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['logged_in'] = true;

    return $user;
}

function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function is_logged_in(): bool {
    return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
}

function is_admin(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function current_user(): array {
    if (!is_logged_in()) return [];
    $stmt = db_query(
        'SELECT id, full_name, email, role, department, position, avatar FROM users WHERE id = ? LIMIT 1',
        [current_user_id()]
    );
    return $stmt->fetch() ?: [];
}

// ─── Guards ─────────────────────────────────────────────────

function require_login(string $redirect = ''): void {
    if (!$redirect) $redirect = BASE_URL . '/index.php';
    if (!is_logged_in()) {
        header("Location: $redirect");
        exit;
    }
}

function require_admin(string $redirect = ''): void {
    if (!$redirect) $redirect = BASE_URL . '/index.php';
    require_login($redirect);
    if (!is_admin()) {
        header("Location: {$redirect}?error=unauthorized");
        exit;
    }
}
