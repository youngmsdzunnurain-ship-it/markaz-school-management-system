<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

startSecureSession();

if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_role'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please sign in to continue.'
    ]);
    exit;
}

$stmt = db()->prepare(
    'SELECT id, name, email, role, profile_image
     FROM admins
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute([$_SESSION['admin_id']]);

$admin = $stmt->fetch();

if (!$admin) {
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Administrator account not found.'
    ]);

    exit;
}

$role = (string) $admin['role'];

$resolvePermission = static function (string $permissionKey) use ($role): bool {
    if (!in_array($permissionKey, ['print_results', 'export_results'], true)) {
        return false;
    }

    return systemAccessEnabled($role . '_' . $permissionKey);
};

echo json_encode([
    'success' => true,
    'admin' => [
        'id' => (int) $admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => $role,
        'profile_image' => $admin['profile_image'],
        'can_print_results' => $resolvePermission('print_results'),
        'can_export_results' => $resolvePermission('export_results')
    ]
]);
