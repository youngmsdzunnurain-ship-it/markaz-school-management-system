<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

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

echo json_encode([
    'success' => true,
    'admin' => [
        'id' => (int) $admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => $admin['role'],
        'profile_image' => $admin['profile_image']
    ]
]);
