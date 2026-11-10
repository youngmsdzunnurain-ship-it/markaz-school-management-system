<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
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
        'SELECT s.id, s.admission_number, s.first_name, s.middle_name,
                s.last_name, s.class_id, "conventional" AS class_type,
                c.name AS class_name
         FROM students s
         INNER JOIN classes c ON c.id = s.class_id
         WHERE s.is_active = 1
           AND EXISTS (
               SELECT 1 FROM teacher_assignments ta
               WHERE ta.account_id = ? AND ta.class_type = "conventional"
                 AND ta.class_id = s.class_id AND ta.subject_id IS NULL
                 AND ta.is_active = 1
           )
         UNION ALL
         SELECT s.id, s.admission_number, s.first_name, s.middle_name,
                s.last_name, COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id) AS class_id, "arabic" AS class_type,
                ac.name AS class_name
         FROM students s
         INNER JOIN arabic_classes ac ON ac.id = COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id)
         WHERE s.is_active = 1
           AND EXISTS (
               SELECT 1 FROM teacher_assignments ta
               WHERE ta.account_id = ? AND ta.class_type = "arabic"
                 AND ta.class_id = COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id) AND ta.subject_id IS NULL
                 AND ta.is_active = 1
           )
         ORDER BY last_name ASC, first_name ASC'
    );
    $stmt->execute([$accountId, $accountId]);
} else {
    requireAdminOrManagementPermission('students');
    $stmt = db()->query(
        'SELECT s.id, s.admission_number, s.first_name, s.middle_name,
                s.last_name, s.parent_guardian_name, s.parent_guardian_phone,
                s.profile_image, s.gender, s.student_type, s.date_of_birth,
                s.class_id, s.arabic_class_id, s.admission_date, s.is_active,
                c.name AS class_name, ac.name AS arabic_class_name
         FROM students s
         INNER JOIN classes c ON c.id = s.class_id
         LEFT JOIN arabic_classes ac ON ac.id = COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id)
         ORDER BY s.last_name ASC, s.first_name ASC'
    );
}

echo json_encode([
    'success' => true,
    'students' => array_map(static function (array $student): array {
        if (($student['class_type'] ?? '') === 'arabic') {
            $student['class_name'] = markazCanonicalArabicClassName((string) ($student['class_name'] ?? ''));
        }
        if (!empty($student['arabic_class_name'])) {
            $student['arabic_class_name'] = markazCanonicalArabicClassName((string) $student['arabic_class_name']);
        }
        return $student;
    }, $stmt->fetchAll())
]);
