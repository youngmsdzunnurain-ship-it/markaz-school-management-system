<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

$stmt = db()->query(
    'SELECT
        c.id,
        c.name,
        c.level_group,
        c.sort_order,
        c.is_active,
        c.created_at,
        c.updated_at,

        (
            SELECT COUNT(*)
            FROM students s
            WHERE s.class_id = c.id
              AND s.is_active = 1
        ) AS student_count

    FROM classes c
    ORDER BY c.sort_order ASC, c.name ASC'
);

$classes = $stmt->fetchAll();
echo json_encode([
    'success' => true,
    'classes' => $classes
]);
