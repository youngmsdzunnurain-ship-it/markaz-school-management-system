<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$pdo = db();
attendanceRequireStaff();

$classType = (string) ($_GET['class_type'] ?? '');
$classId = filter_var($_GET['class_id'] ?? null, FILTER_VALIDATE_INT);
$periodId = filter_var($_GET['academic_period_id'] ?? null, FILTER_VALIDATE_INT);
$view = (string) ($_GET['view'] ?? 'term');
$anchor = attendanceValidateDate((string) ($_GET['date'] ?? date('Y-m-d')));

if (!in_array($view, ['week', 'month', 'term'], true)) {
    attendanceJsonError(422, 'Choose a valid attendance view.');
}
if ($classId === false || $classId === null || $classId < 1 || $periodId === false || $periodId === null || $periodId < 1) {
    attendanceJsonError(422, 'Choose a class and academic term to view its register.');
}
$class = attendanceRequireClassAccess($pdo, $classType, (int) $classId);
$periodStmt = $pdo->prepare(
    'SELECT ap.id, t.name AS term_name, s.name AS session_name
     FROM academic_periods ap
     INNER JOIN terms t ON t.id = ap.term_id
     INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
     WHERE ap.id = ? LIMIT 1'
);
$periodStmt->execute([(int) $periodId]);
$period = $periodStmt->fetch(PDO::FETCH_ASSOC);
if (!$period) {
    attendanceJsonError(404, 'The selected academic period was not found.');
}

if ($view === 'week') {
    $date = new DateTimeImmutable($anchor);
    $from = $date->modify('monday this week')->format('Y-m-d');
    $to = $date->modify('friday this week')->format('Y-m-d');
} elseif ($view === 'month') {
    $date = new DateTimeImmutable($anchor);
    $from = $date->modify('first day of this month')->format('Y-m-d');
    $to = $date->modify('last day of this month')->format('Y-m-d');
} else {
    $rangeStmt = $pdo->prepare(
        'SELECT MIN(attendance_date) AS first_date, MAX(attendance_date) AS last_date
         FROM attendance_records
         WHERE academic_period_id = ? AND class_type = ? AND class_id = ?'
    );
    $rangeStmt->execute([(int) $periodId, $classType, (int) $classId]);
    $range = $rangeStmt->fetch(PDO::FETCH_ASSOC);
    $from = $range['first_date'] ?: $anchor;
    $to = $range['last_date'] ?: $anchor;
}
if ($from > $to || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 366) {
    attendanceJsonError(422, 'The selected register range is invalid or exceeds 12 months.');
}
attendanceValidateDate($from, true);
attendanceValidateDate($to, true);

$studentClassExpression = $classType === 'arabic'
    ? 'COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id)'
    : 's.class_id';
$studentsStmt = $pdo->prepare(
    "SELECT s.id, s.admission_number, s.first_name, s.middle_name, s.last_name
     FROM students s
     WHERE {$studentClassExpression} = ?
     ORDER BY s.last_name, s.first_name, s.middle_name, s.admission_number"
);
$studentsStmt->execute([(int) $classId]);
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

$recordsStmt = $pdo->prepare(
    'SELECT student_id, attendance_date, status
     FROM attendance_records
     WHERE academic_period_id = ? AND class_type = ? AND class_id = ?
       AND attendance_date BETWEEN ? AND ?
     ORDER BY attendance_date, student_id'
);
$recordsStmt->execute([(int) $periodId, $classType, (int) $classId, $from, $to]);
$records = [];
foreach ($recordsStmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
    $records[(int) $record['student_id']][$record['attendance_date']] = $record['status'];
}

$dates = [];
$cursor = new DateTimeImmutable($from);
$last = new DateTimeImmutable($to);
while ($cursor <= $last) {
    // School register columns are weekdays only; weekends are not counted as school days.
    if ((int) $cursor->format('N') <= 5) {
        $dates[] = [
            'date' => $cursor->format('Y-m-d'),
            'label' => $cursor->format('D'),
            'weekday' => $cursor->format('l'),
            'display_date' => $cursor->format('j M Y'),
        ];
    }
    $cursor = $cursor->modify('+1 day');
}

$totals = ['students' => count($students), 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'attended' => 0, 'marked_days' => 0];
foreach ($students as &$student) {
    $id = (int) $student['id'];
    $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
    foreach ($records[$id] ?? [] as $status) {
        if (isset($counts[$status])) $counts[$status]++;
    }
    $student['id'] = $id;
    $student['attendance'] = [];
    foreach ($dates as $day) {
        $student['attendance'][$day['date']] = $records[$id][$day['date']] ?? null;
    }
    $student['present_count'] = $counts['present'];
    $student['absent_count'] = $counts['absent'];
    $student['late_count'] = $counts['late'];
    $student['excused_count'] = $counts['excused'];
    $student['attended_count'] = $counts['present'] + $counts['late'];
    $student['marked_days'] = array_sum($counts);
    $eligibleDays = $counts['present'] + $counts['absent'] + $counts['late'];
    $student['attendance_rate'] = $eligibleDays > 0
        ? round(($student['attended_count'] / $eligibleDays) * 100, 1)
        : null;
    foreach (['present', 'absent', 'late', 'excused', 'attended', 'marked_days'] as $key) {
        $totals[$key] += $student[$key === 'attended' ? 'attended_count' : ($key === 'marked_days' ? 'marked_days' : $key . '_count')];
    }
}
unset($student);
$eligibleTotal = $totals['present'] + $totals['absent'] + $totals['late'];

echo json_encode([
    'success' => true,
    'class' => ['id' => (int) $class['id'], 'name' => $class['name'], 'class_type' => $classType],
    'period' => ['id' => (int) $period['id'], 'term_name' => $period['term_name'], 'session_name' => $period['session_name']],
    'view' => $view, 'from' => $from, 'to' => $to, 'dates' => $dates,
    'students' => $students,
    'summary' => $totals + ['attendance_rate' => $eligibleTotal > 0 ? round((($totals['present'] + $totals['late']) / $eligibleTotal) * 100, 1) : null],
    'legacy_unassigned_note' => 'Attendance records saved before academic-period linking are preserved but are not included in term totals until assigned to a term.',
], JSON_UNESCAPED_UNICODE);
