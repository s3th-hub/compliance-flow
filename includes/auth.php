<?php
// includes/auth.php — Auth helpers

require_once __DIR__ . '/../config/db.php';

function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in(): bool {
    session_start_safe();
    return !empty($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: ' . BASE_URL . '/index.php?error=unauthorized');
        exit;
    }
}

function current_user(): array {
    return [
        'id'   => (int) ($_SESSION['user_id'] ?? 0),
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
    ];
}

function login_user(string $email, string $password): bool {
    $db   = db();
    $stmt = $db->prepare('SELECT id, full_name, role, password_hash, is_active FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_start_safe();
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];
    return true;
}

function logout_user(): void {
    session_start_safe();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Sanitize string input */
function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

/** Auto-calculate status based on expiry date */
function calculate_status(string $expiry_date): string {
    $today  = new DateTime('today');
    $expiry = new DateTime($expiry_date);
    $diff   = (int) $today->diff($expiry)->format('%r%a'); // negative = past

    if ($diff < 0) return 'expired';
    if ($diff <= 30) return 'pending_renewal';
    return 'active';
}

/** Days until expiry (negative if past) */
function days_until_expiry(string $expiry_date): int {
    $today  = new DateTime('today');
    $expiry = new DateTime($expiry_date);
    return (int) $today->diff($expiry)->format('%r%a');
}
