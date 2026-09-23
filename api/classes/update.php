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
$name = trim($_POST['name'] ?? '');
$levelGroup = trim($_POST['level_group'] ?? '');
$sortOrder = (int)($_POST['sort_order'] ?? 0);
$isActive = isset($_POST['is_active'])
    ? (int)$_POST['is_active']
    : 1;

if ($id <= 0 || $name === '') {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Class ID and class name are required.'
    ]);

    exit;
}

if ($isActive !== 0 && $isActive !== 1) {
    $isActive = 1;
}

$db = db();

$stmt = $db->prepare(
    'SELECT id
     FROM classes
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute([$id]);

if (!$stmt->fetch()) {
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Class not found.'
    ]);

    exit;
}

$stmt = $db->prepare(
    'SELECT id
     FROM classes
     WHERE name = ?
       AND id <> ?
     LIMIT 1'
);

$stmt->execute([$name, $id]);

if ($stmt->fetch()) {
    http_response_code(409);

    echo json_encode([
        'success' => false,
        'message' => 'Another class already uses this name.'
    ]);

    exit;
}

$stmt = $db->prepare(
    'UPDATE classes
     SET
        name = ?,
        level_group = ?,
        sort_order = ?,
        is_active = ?
     WHERE id = ?'
);

$stmt->execute([
    $name,
    $levelGroup !== '' ? $levelGroup : null,
    $sortOrder,
    $isActive,
    $id
]);

echo json_encode([
    'success' => true,
    'message' => 'Class updated successfully.'
]);
