<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

requireLogin();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$permissions = [
    'print_results' => 'print_results',
    'export_results' => 'export_results',
];

if (!isset($permissions[$action])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid result action.'
    ]);
    exit;
}

requireRolePermission($permissions[$action]);

echo json_encode([
    'success' => true,
    'message' => 'Permission verified.'
]);
