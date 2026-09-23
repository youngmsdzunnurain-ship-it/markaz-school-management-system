<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

$stmt = db()->query(
    'SELECT
        id,
        name,
        level_group,
        sort_order,
        is_active,
        created_at,
        updated_at
     FROM classes
     ORDER BY sort_order ASC, name ASC'
);

echo json_encode([
    'success' => true,
    'classes' => $stmt->fetchAll()
]);
