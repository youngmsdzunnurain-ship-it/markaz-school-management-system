<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid student ID.'
    ]);
    exit;
}

$db = db();

$stmt = $db->prepare(
    'SELECT is_active
     FROM students
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute([$id]);

$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Student not found.'
    ]);
    exit;
}

$newStatus = (int)$student['is_active'] === 1 ? 0 : 1;

$stmt = $db->prepare(
    'UPDATE students
     SET is_active = ?
     WHERE id = ?'
);

$stmt->execute([$newStatus, $id]);

echo json_encode([
    'success' => true,
    'message' => $newStatus === 1
        ? 'Student activated successfully.'
        : 'Student deactivated successfully.',
    'is_active' => $newStatus
]);
