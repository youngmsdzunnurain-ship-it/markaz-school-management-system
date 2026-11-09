<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/middleware/session.php';

header('Content-Type: application/json; charset=utf-8');

startSecureSession();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'] ?? '',
        (bool) $params['secure'],
        (bool) $params['httponly']
    );
}

$role = strtolower((string) ($_SESSION['admin_role'] ?? ''));

session_destroy();

echo json_encode([
    'role' => $role,
    'success' => true,
    'message' => 'Logged out successfully.'
]);
