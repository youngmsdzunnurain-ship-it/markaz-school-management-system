<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$role = attendanceRequireStaff();
$pdo = db();
$classes = [];

if ($role === 'teacher') {
    $accountId = (int) ($_SESSION['admin_id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT DISTINCT 'conventional' AS class_type, c.id, c.name,
                c.sort_order,
                (SELECT COUNT(*) FROM students s
                 WHERE s.class_id = c.id AND s.is_active = 1) AS student_count
         FROM teacher_assignments ta
         INNER JOIN classes c ON c.id = ta.class_id
         WHERE ta.account_id = ? AND ta.class_type = 'conventional'
           AND ta.is_active = 1 AND c.is_active = 1
          "
    );
    $stmt->execute([$accountId]);
    $classes = array_merge($classes, $stmt->fetchAll(PDO::FETCH_ASSOC));

    $stmt = $pdo->prepare(
        "SELECT DISTINCT 'arabic' AS class_type, ac.id, ac.name,
                ac.sort_order,
                (SELECT COUNT(*) FROM students s
                 WHERE COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id) = ac.id AND s.is_active = 1) AS student_count
         FROM teacher_assignments ta
         INNER JOIN arabic_classes ac ON ac.id = ta.class_id
         WHERE ta.account_id = ? AND ta.class_type = 'arabic'
           AND ta.is_active = 1 AND ac.is_active = 1"
    );
    $stmt->execute([$accountId]);
    $classes = array_merge($classes, $stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    $stmt = $pdo->query(
        "SELECT 'conventional' AS class_type, c.id, c.name, c.sort_order,
                COUNT(s.id) AS student_count
         FROM classes c
         LEFT JOIN students s ON s.class_id = c.id AND s.is_active = 1
         WHERE c.is_active = 1
          
         GROUP BY c.id, c.name, c.sort_order"
    );
    $classes = array_merge($classes, $stmt->fetchAll(PDO::FETCH_ASSOC));

    $stmt = $pdo->query(
        "SELECT 'arabic' AS class_type, ac.id, ac.name, ac.sort_order,
                COUNT(s.id) AS student_count
         FROM arabic_classes ac
         LEFT JOIN students s ON COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id) = ac.id AND s.is_active = 1
         WHERE ac.is_active = 1
         GROUP BY ac.id, ac.name, ac.sort_order"
    );
    $classes = array_merge($classes, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// Return only the requested education category. Filtering is enforced here,
// not merely by the browser, so categories cannot leak into each other's list.
$classTypeFilter = (string) ($_GET['class_type'] ?? '');
if ($classTypeFilter !== '' && !in_array($classTypeFilter, ['conventional', 'arabic'], true)) {
    attendanceJsonError(422, 'Choose a valid attendance class category.');
}
if ($classTypeFilter !== '') {
    $classes = array_values(array_filter(
        $classes,
        static fn (array $class): bool => $class['class_type'] === $classTypeFilter
    ));
}

foreach ($classes as &$class) {
    $class['id'] = (int) $class['id'];
    $class['sort_order'] = (int) $class['sort_order'];
    $class['student_count'] = (int) $class['student_count'];
}
unset($class);

usort($classes, static function (array $a, array $b): int {
    $typeOrder = ['conventional' => 0, 'arabic' => 1];
    return ($typeOrder[$a['class_type']] <=> $typeOrder[$b['class_type']])
        ?: (($a['sort_order'] <=> $b['sort_order'])
        ?: strcasecmp((string) $a['name'], (string) $b['name']));
});

echo json_encode([
    'success' => true,
    'role' => $role,
    'classes' => $classes,
], JSON_UNESCAPED_UNICODE);
