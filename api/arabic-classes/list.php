<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/class-hierarchy.php';

header('Content-Type: application/json; charset=utf-8');

requireAdminOrManagementPermission('students');

$stmt = db()->query(
    'SELECT
        id,
        name,
        sort_order,
        is_active,
        created_at,
        updated_at
     FROM arabic_classes
     ORDER BY sort_order ASC, name ASC'
);

$arabicClasses = markazSortAndDeduplicateClasses(array_map(
    static fn (array $class): array => $class + ['class_type' => 'arabic'],
    $stmt->fetchAll()
));

echo json_encode([
    'success' => true,
    'arabic_classes' => $arabicClasses
]);
