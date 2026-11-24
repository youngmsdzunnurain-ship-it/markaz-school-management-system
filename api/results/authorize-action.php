<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/student-portal-access.php';

$studentPortalScope = studentPortalResultScope();
if ($studentPortalScope === null) {
    requireLogin();
}

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$permissions = [
    'print_results' => 'print_results',
];

if (!isset($permissions[$action])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid result action.'
    ]);
    exit;
}

if ($studentPortalScope !== null) {
    $studentSetting = 'student_print_results';
    if (!systemAccessEnabled($studentSetting)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $action === 'print_results'
                ? 'Student printing is currently disabled by the Admin.'
                : 'Student PDF downloads are currently disabled by the Admin.'
        ]);
        exit;
    }
} else {
    requireRolePermission($permissions[$action]);
}

echo json_encode([
    'success' => true,
    'message' => 'Permission verified.'
]);
