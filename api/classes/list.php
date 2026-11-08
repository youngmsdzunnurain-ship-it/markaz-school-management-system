<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (currentAdminRole() === 'teacher') {
    $accountId = (int) ($_SESSION['admin_id'] ?? 0);
    $permissionStmt = db()->prepare(
        'SELECT is_allowed FROM account_permissions
         WHERE account_id = ? AND permission_key = ? LIMIT 1'
    );
    $permissionStmt->execute([$accountId, 'print_results']);
    $individualPermission = $permissionStmt->fetchColumn();

    if (
        !systemAccessEnabled('teacher_print_results')
        || $individualPermission === false
        || (int) $individualPermission !== 1
    ) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not permitted to print results.']);
        exit;
    }

    $stmt = db()->prepare(
        'SELECT c.id, c.name, c.is_active,
                (SELECT COUNT(*) FROM students s
                 WHERE s.class_id = c.id AND s.is_active = 1) AS student_count
         FROM classes c
         INNER JOIN teacher_assignments ta
           ON ta.class_id = c.id
          AND ta.account_id = ?
          AND ta.class_type = "conventional"
          AND ta.subject_id IS NULL
          AND ta.is_active = 1
         WHERE c.is_active = 1
         ORDER BY c.sort_order ASC, c.name ASC'
    );
    $stmt->execute([$accountId]);
} else {
    requireAdmin();
    $stmt = db()->query(
        'SELECT c.id, c.name, c.level_group, c.sort_order, c.is_active,
                c.created_at, c.updated_at,
                (SELECT COUNT(*) FROM students s
                 WHERE s.class_id = c.id AND s.is_active = 1) AS student_count
         FROM classes c
         ORDER BY c.sort_order ASC, c.name ASC'
    );
}

$classes = $stmt->fetchAll();
echo json_encode([
    'success' => true,
    'classes' => $classes
]);
