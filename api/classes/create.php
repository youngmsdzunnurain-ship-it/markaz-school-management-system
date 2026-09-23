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

$name = trim($_POST['name'] ?? '');
$levelGroup = trim($_POST['level_group'] ?? '');
$sortOrder = (int)($_POST['sort_order'] ?? 0);
$isActive = isset($_POST['is_active'])
    ? (int)$_POST['is_active']
    : 1;

if ($name === '') {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Class name is required.'
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
     WHERE name = ?
     LIMIT 1'
);

$stmt->execute([$name]);

if ($stmt->fetch()) {
    http_response_code(409);

    echo json_encode([
        'success' => false,
        'message' => 'A class with this name already exists.'
    ]);

    exit;
}

$stmt = $db->prepare(
    'INSERT INTO classes
        (name, level_group, sort_order, is_active)
     VALUES
        (?, ?, ?, ?)'
);

$stmt->execute([
    $name,
    $levelGroup !== '' ? $levelGroup : null,
    $sortOrder,
    $isActive
]);

echo json_encode([
    'success' => true,
    'message' => 'Class created successfully.',
    'id' => (int)$db->lastInsertId()
]);
