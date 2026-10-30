<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/_common.php';

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
$params = [$from, $to];
$where = 'ar.attendance_date BETWEEN ? AND ?';

if ($classType !== '' || $classId !== null) {
    if (!in_array($classType, ['conventional', 'arabic'], true) || !$classId || $classId < 1) {
        attendanceJsonError(422, 'Choose both a valid class category and class.');
    }
    attendanceRequireClassAccess($pdo, $classType, (int) $classId);
    $where .= ' AND ar.class_type = ? AND ar.class_id = ?';
    $params[] = $classType;
    $params[] = (int) $classId;
} elseif ($role === 'teacher') {
    attendanceJsonError(403, 'Teachers must select one of their assigned classes to view a report.');
}

$sql = "SELECT ar.class_type, ar.class_id,
        CASE WHEN ar.class_type = 'arabic' THEN ac.name ELSE c.name END AS class_name,
        COUNT(*) AS marked_records,
        SUM(ar.status = 'present') AS present_count,
        SUM(ar.status = 'absent') AS absent_count,
        SUM(ar.status = 'late') AS late_count,
        SUM(ar.status = 'excused') AS excused_count,
        COUNT(DISTINCT ar.student_id) AS students_recorded,
        COUNT(DISTINCT ar.attendance_date) AS days_with_records
    FROM attendance_records ar
    LEFT JOIN classes c ON ar.class_type = 'conventional' AND c.id = ar.class_id
    LEFT JOIN arabic_classes ac ON ar.class_type = 'arabic' AND ac.id = ar.class_id
    WHERE {$where}
    GROUP BY ar.class_type, ar.class_id, class_name
    ORDER BY ar.class_type, class_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$summary = [
    'marked_records' => 0, 'present' => 0, 'absent' => 0,
    'late' => 0, 'excused' => 0, 'students_recorded' => 0,
];
foreach ($classes as &$row) {
    foreach (['marked_records', 'present_count', 'absent_count', 'late_count', 'excused_count', 'students_recorded', 'days_with_records'] as $key) {
        $row[$key] = (int) $row[$key];
    }
    $summary['marked_records'] += $row['marked_records'];
    $summary['present'] += $row['present_count'];
    $summary['absent'] += $row['absent_count'];
    $summary['late'] += $row['late_count'];
    $summary['excused'] += $row['excused_count'];
    $summary['students_recorded'] += $row['students_recorded'];
}
unset($row);

echo json_encode([
    'success' => true,
    'from' => $from,
    'to' => $to,
    'scope' => ($classType !== '' ? 'class' : 'school'),
    'summary' => $summary,
    'classes' => $classes,
], JSON_UNESCAPED_UNICODE);
