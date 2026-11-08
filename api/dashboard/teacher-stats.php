<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

if (currentAdminRole() !== 'teacher') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Teacher access required.'
    ]);
    exit;
}

$pdo = db();
$accountId = (int) ($_SESSION['admin_id'] ?? 0);

$periodStmt = $pdo->query(
    "SELECT ap.id
     FROM academic_periods ap
     INNER JOIN academic_sessions s
       ON s.id = ap.academic_session_id
     INNER JOIN terms t ON t.id = ap.term_id
     WHERE s.is_current = 1
     ORDER BY ap.id DESC
     LIMIT 1"
);
$period = $periodStmt->fetch();
$periodId = $period ? (int) $period['id'] : 0;

$assignmentStmt = $pdo->prepare(
    "SELECT class_type, class_id, subject_id
     FROM teacher_assignments
     WHERE account_id = ? AND is_active = 1
     ORDER BY class_type, class_id, subject_id"
);
$assignmentStmt->execute([$accountId]);
$assignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);

$groups = [];
foreach ($assignments as $assignment) {
    $type = (string) $assignment['class_type'];
    if (!in_array($type, ['conventional', 'arabic'], true)) {
        continue;
    }

    $classId = (int) $assignment['class_id'];
    $key = $type . ':' . $classId;

    if (!isset($groups[$key])) {
        $groups[$key] = [
            'class_type' => $type,
            'class_id' => $classId,
            'subject_ids' => [],
            'full_class' => false
        ];
    }

    if ($assignment['subject_id'] === null) {
        $groups[$key]['full_class'] = true;
    } else {
        $groups[$key]['subject_ids'][] = (int) $assignment['subject_id'];
    }
}

$cards = [];
$allStudentIds = [];
$allSubjectIds = [];
$totalExpected = 0;
$totalCompleted = 0;

foreach ($groups as $group) {
    $type = $group['class_type'];
    $classId = $group['class_id'];
    $isArabic = $type === 'arabic';

    $classTable = $isArabic ? 'arabic_classes' : 'classes';
    $mappingTable = $isArabic ? 'arabic_class_subjects' : 'class_subjects';
    $mappingClassColumn = $isArabic ? 'arabic_class_id' : 'class_id';
    $studentClassColumn = $isArabic ? 'arabic_class_id' : 'class_id';

    $classStmt = $pdo->prepare(
        "SELECT name FROM {$classTable} WHERE id = ? AND is_active = 1 LIMIT 1"
    );
    $classStmt->execute([$classId]);
    $className = $classStmt->fetchColumn();

    if ($className === false) {
        continue;
    }

    $subjectStmt = $pdo->prepare(
        "SELECT DISTINCT s.id, s.name
         FROM {$mappingTable} cs
         INNER JOIN subjects s ON s.id = cs.subject_id
         WHERE cs.{$mappingClassColumn} = ?
           AND s.is_active = 1
         ORDER BY s.name"
    );
    $subjectStmt->execute([$classId]);
    $mappedSubjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

    $mappedIds = array_map(
        static fn(array $row): int => (int) $row['id'],
        $mappedSubjects
    );

    $subjectIds = $group['full_class']
        ? $mappedIds
        : array_values(array_intersect(
            array_unique($group['subject_ids']),
            $mappedIds
        ));

    $subjectNames = [];
    foreach ($mappedSubjects as $subject) {
        if (in_array((int) $subject['id'], $subjectIds, true)) {
            $subjectNames[] = (string) $subject['name'];
            $allSubjectIds[(int) $subject['id']] = true;
        }
    }

    $studentStmt = $pdo->prepare(
        "SELECT id FROM students
         WHERE {$studentClassColumn} = ? AND is_active = 1"
    );
    $studentStmt->execute([$classId]);
    $studentIds = array_map(
        'intval',
        $studentStmt->fetchAll(PDO::FETCH_COLUMN)
    );

    foreach ($studentIds as $studentId) {
        $allStudentIds[$type . ':' . $studentId] = true;
    }

    $studentCount = count($studentIds);
    $expected = $studentCount * count($subjectIds);
    $completed = 0;

    if ($periodId > 0 && $studentCount > 0 && count($subjectIds) > 0) {
        $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
        $subjectPlaceholders = implode(',', array_fill(0, count($subjectIds), '?'));

        $resultStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM results r
             INNER JOIN students st ON st.id = r.student_id
             WHERE r.class_type = ?
               AND r.class_id = ?
               AND r.academic_period_id = ?
               AND r.subject_id IN ({$subjectPlaceholders})
               AND r.student_id IN ({$studentPlaceholders})
               AND st.is_active = 1
               AND st.{$studentClassColumn} = ?
               AND r.ca1 IS NOT NULL
               AND r.ca2 IS NOT NULL
               AND r.exam IS NOT NULL"
        );

        $resultStmt->execute(array_merge(
            [$type, $classId, $periodId],
            $subjectIds,
            $studentIds,
            [$classId]
        ));
        $completed = (int) $resultStmt->fetchColumn();
    }

    $percentage = $expected > 0
        ? round(($completed / $expected) * 100, 1)
        : 0;

    $totalExpected += $expected;
    $totalCompleted += $completed;

    $cards[] = [
        'class_type' => $type,
        'class_id' => $classId,
        'class_name' => (string) $className,
        'student_count' => $studentCount,
        'subjects' => $subjectNames,
        'subject_count' => count($subjectIds),
        'full_class' => $group['full_class'],
        'access_mode' => $group['full_class']
            ? 'Full Class Access'
            : (count($subjectIds) === count($mappedIds) ? 'All Subjects' : 'Selected Subjects'),
        'completed_entries' => $completed,
        'expected_entries' => $expected,
        'completion_percentage' => $percentage
    ];
}

$overallPercentage = $totalExpected > 0
    ? round(($totalCompleted / $totalExpected) * 100, 1)
    : 0;

echo json_encode([
    'success' => true,
    'role' => 'teacher',
    'stats' => [
        'assigned_students' => count($allStudentIds),
        'assigned_classes' => count($cards),
        'assigned_subjects' => count($allSubjectIds),
        'completed_entries' => $totalCompleted,
        'expected_entries' => $totalExpected,
        'completion_percentage' => $overallPercentage
    ],
    'assignments' => $cards
]);
