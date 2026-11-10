<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/class-hierarchy.php';
require_once __DIR__ . '/../../app/helpers/arabic-class-names.php';

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
        'SELECT c.id, c.name, c.sort_order, "conventional" AS class_type, c.is_active,
                (SELECT COUNT(*) FROM students s
                 WHERE s.class_id = c.id AND s.is_active = 1) AS student_count
         FROM classes c
         INNER JOIN teacher_assignments ta
           ON ta.class_id = c.id AND ta.account_id = ?
          AND ta.class_type = "conventional" AND ta.subject_id IS NULL
          AND ta.is_active = 1
         WHERE c.is_active = 1
           AND LOWER(TRIM(c.name)) <> "hufaz"
           AND LOWER(TRIM(c.name)) NOT REGEXP "^(roudoh[[:space:]]*[12]|faslu[[:space:]]*[1-6])$"
         UNION ALL
         SELECT ac.id, ac.name, ac.sort_order, "arabic" AS class_type, ac.is_active,
                (SELECT COUNT(*) FROM students s
                 WHERE COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id) = ac.id
                   AND s.is_active = 1) AS student_count
         FROM arabic_classes ac
         INNER JOIN teacher_assignments ta
           ON ta.class_id = ac.id AND ta.account_id = ?
          AND ta.class_type = "arabic" AND ta.subject_id IS NULL
          AND ta.is_active = 1
         WHERE ac.is_active = 1
         ORDER BY class_type, sort_order, name'
    );
    $stmt->execute([$accountId, $accountId]);
} else {
    requireAdminOrManagementPermission('students');
    $stmt = db()->query(
        'SELECT c.id, c.name, c.level_group, c.sort_order,
                "conventional" AS class_type, c.is_active,
                c.created_at, c.updated_at,
                (SELECT COUNT(*) FROM students s
                 WHERE s.class_id = c.id AND s.is_active = 1) AS student_count
         FROM classes c
         WHERE LOWER(TRIM(c.name)) <> "hufaz"
           AND LOWER(TRIM(c.name)) NOT REGEXP "^(roudoh[[:space:]]*[12]|faslu[[:space:]]*[1-6])$"

         UNION ALL

         SELECT ac.id, ac.name, NULL AS level_group, ac.sort_order,
                "arabic" AS class_type, ac.is_active,
                ac.created_at, ac.updated_at,
                (SELECT COUNT(*) FROM students s
                 WHERE COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id) = ac.id
                   AND s.is_active = 1) AS student_count
         FROM arabic_classes ac

         ORDER BY sort_order ASC, name ASC'
    );
}

$classes = markazSortAndDeduplicateClasses($stmt->fetchAll());
foreach ($classes as &$class) {
    if (($class['class_type'] ?? '') === 'arabic') {
        $class['name'] = markazCanonicalArabicClassName((string) ($class['name'] ?? ''));
    }
}
unset($class);
if (currentAdminRole() === 'teacher') {
    $totalStudentsStmt = db()->prepare(
        'SELECT COUNT(DISTINCT s.id)
         FROM students s
         WHERE s.is_active = 1
           AND (
               EXISTS (
                   SELECT 1 FROM teacher_assignments ta
                   INNER JOIN classes assigned_class
                     ON assigned_class.id = ta.class_id
                    AND LOWER(TRIM(assigned_class.name)) <> "hufaz"
                     AND LOWER(TRIM(assigned_class.name)) NOT REGEXP "^(roudoh[[:space:]]*[12]|faslu[[:space:]]*[1-6])$"
                   WHERE ta.account_id = ? AND ta.class_type = "conventional"
                     AND ta.class_id = s.class_id AND ta.subject_id IS NULL
                     AND ta.is_active = 1
               )
               OR EXISTS (
                   SELECT 1 FROM teacher_assignments ta
                   WHERE ta.account_id = ? AND ta.class_type = "arabic"
                     AND ta.class_id = COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id)
                     AND ta.subject_id IS NULL AND ta.is_active = 1
               )
           )'
    );
    $accountId = (int) ($_SESSION['admin_id'] ?? 0);
    $totalStudentsStmt->execute([$accountId, $accountId]);
    $totalStudents = (int) $totalStudentsStmt->fetchColumn();
} else {
    $totalStudents = (int) db()->query(
        'SELECT COUNT(DISTINCT s.id)
         FROM students s
         WHERE s.is_active = 1
           AND (
               EXISTS (
                   SELECT 1 FROM classes assigned_class
                   WHERE assigned_class.id = s.class_id
                     AND LOWER(TRIM(assigned_class.name)) <> "hufaz"
               )
               OR EXISTS (
                   SELECT 1 FROM arabic_classes assigned_arabic_class
                   WHERE assigned_arabic_class.id =
                     COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id)
               )
           )'
    )->fetchColumn();
}
echo json_encode([
    'success' => true,
    'classes' => $classes,
    'total_students' => $totalStudents
]);
