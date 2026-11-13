<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../app/helpers/class-hierarchy.php';
require_once __DIR__ . '/../../app/helpers/arabic-class-names.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$pdo = db();
$role = attendanceRequireStaff();
$from = attendanceValidateDate((string) ($_GET['from'] ?? date('Y-m-d')));
$to = attendanceValidateDate((string) ($_GET['to'] ?? date('Y-m-d')));
if ($from > $to) {
    attendanceJsonError(422, 'The report start date must be on or before its end date.');
}
if ((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 366) {
    attendanceJsonError(422, 'Choose a reporting period of 12 months or less.');
}

$classType = (string) ($_GET['class_type'] ?? '');
$classId = filter_var($_GET['class_id'] ?? null, FILTER_VALIDATE_INT);
$hasClassId = $classId !== false && $classId !== null;

if ($classType !== '' || $hasClassId) {
    if (!in_array($classType, ['conventional', 'arabic'], true) || !$classId || $classId < 1) {
        attendanceJsonError(422, 'Choose both a valid class category and class.');
    }
    attendanceRequireClassAccess($pdo, $classType, (int) $classId);
} elseif ($role === 'teacher') {
    attendanceJsonError(403, 'Teachers must select one of their assigned classes to view a report.');
}

$classDefinitions = [
    [
        'type' => 'conventional',
        'table' => 'classes',
        'student_count_sql' => '(SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.is_active = 1)',
    ],
    [
        'type' => 'arabic',
        'table' => 'arabic_classes',
        'student_count_sql' => '(SELECT COUNT(*) FROM students s WHERE COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id) = c.id AND s.is_active = 1)',
    ],
];
$classes = [];

foreach ($classDefinitions as $definition) {
    $type = $definition['type'];
    $table = $definition['table'];
    $studentCountSql = $definition['student_count_sql'];
    $sql = "SELECT
                ? AS class_type,
                {$studentCountSql} AS student_count,
                c.id AS class_id,
                c.name AS class_name,
                COUNT(ar.id) AS marked_records,
                COALESCE(SUM(ar.status = 'present'), 0) AS present_count,
                COALESCE(SUM(ar.status = 'absent'), 0) AS absent_count,
                COALESCE(SUM(ar.status = 'late'), 0) AS late_count,
                COALESCE(SUM(ar.status = 'excused'), 0) AS excused_count,
                COUNT(DISTINCT ar.student_id) AS students_recorded,
                COUNT(DISTINCT ar.attendance_date) AS days_with_records
            FROM {$table} c
            LEFT JOIN attendance_records ar
              ON ar.class_type = ?
             AND ar.class_id = c.id
             AND ar.attendance_date BETWEEN ? AND ?
            WHERE c.is_active = 1";

    $classParams = [$type, $type, $from, $to];

    if ($classType !== '') {
        if ($classType !== $type) {
            continue;
        }
        $sql .= " AND c.id = ?";
        $classParams[] = (int) $classId;
    }

    $sql .= " GROUP BY c.id, c.name ORDER BY c.sort_order, c.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($classParams);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        foreach (['class_id', 'student_count', 'marked_records', 'present_count', 'absent_count', 'late_count', 'excused_count', 'students_recorded', 'days_with_records'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        $classes[] = $row;
    }
}

$classes = markazSortAndDeduplicateClasses(array_map(
    static fn (array $row): array => $row + ['id' => $row['class_id'], 'name' => $row['class_name']],
    $classes
));
foreach ($classes as &$class) {
    if (($class['class_type'] ?? '') === 'arabic') {
        $class['class_name'] = markazCanonicalArabicClassName((string) ($class['class_name'] ?? ''));
        $class['name'] = $class['class_name'];
    }
}
unset($class);

$summary = [
    'marked_records' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'excused' => 0,
    'students_recorded' => 0,
];
foreach ($classes as $row) {
    $summary['marked_records'] += $row['marked_records'];
    $summary['present'] += $row['present_count'];
    $summary['absent'] += $row['absent_count'];
    $summary['late'] += $row['late_count'];
    $summary['excused'] += $row['excused_count'];
    $summary['students_recorded'] += $row['students_recorded'];
}

echo json_encode([
    'success' => true,
    'from' => $from,
    'to' => $to,
    'scope' => ($classType !== '' ? 'class' : 'school'),
    'summary' => $summary,
    'classes' => $classes,
], JSON_UNESCAPED_UNICODE);
