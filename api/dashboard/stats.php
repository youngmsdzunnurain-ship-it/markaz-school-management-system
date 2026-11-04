<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/_progress.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$dashboardRole = currentAdminRole();

if ($dashboardRole === 'teacher') {
    require __DIR__ . '/teacher-stats.php';
    exit;
}

if ($dashboardRole !== 'management') {
    requireAdmin();
}

$pdo = db();

$students = (int) $pdo->query(
    "SELECT COUNT(*) FROM students WHERE is_active = 1"
)->fetchColumn();

$conventionalClasses = (int) $pdo->query(
    "SELECT COUNT(*) FROM classes WHERE is_active = 1"
)->fetchColumn();
$arabicClasses = (int) $pdo->query(
    "SELECT COUNT(*) FROM arabic_classes WHERE is_active = 1"
)->fetchColumn();
$classes = $conventionalClasses + $arabicClasses;

$subjects = (int) $pdo->query(
    "SELECT COUNT(*) FROM subjects WHERE is_active = 1"
)->fetchColumn();

$periodStmt = $pdo->query(
    "SELECT ap.id
     FROM academic_periods ap
     INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
     INNER JOIN terms t ON t.id = ap.term_id
     WHERE s.is_current = 1
     ORDER BY ap.id DESC
     LIMIT 1"
);
$period = $periodStmt->fetch(PDO::FETCH_ASSOC);
$periodId = $period ? (int) $period['id'] : 0;

$classProgress = [];
$classProgressByType = [
    'conventional' => [],
    'arabic' => [],
];
$schoolProgressByStudent = [];
$activeStudentIds = $pdo->query(
    "SELECT id FROM students WHERE is_active = 1"
)->fetchAll(PDO::FETCH_COLUMN);
foreach ($activeStudentIds as $activeStudentId) {
    $schoolProgressByStudent[(int) $activeStudentId] = [
        'expected_subjects' => 0,
        'completed_subjects' => 0,
        'started_subjects' => 0,
    ];
}

$classDefinitions = [
    [
        'type' => 'conventional',
        'class_table' => 'classes',
        'mapping_table' => 'class_subjects',
        'mapping_class_column' => 'class_id',
        'student_class_expression' => 'st.class_id',
    ],
    [
        'type' => 'arabic',
        'class_table' => 'arabic_classes',
        'mapping_table' => 'arabic_class_subjects',
        'mapping_class_column' => 'arabic_class_id',
        'student_class_expression' => 'COALESCE(NULLIF(st.arabic_class_id, 0), st.arabia_class_id)',
    ],
];

foreach ($classDefinitions as $definition) {
    $type = $definition['type'];
    $classTable = $definition['class_table'];
    $mappingTable = $definition['mapping_table'];
    $mappingClassColumn = $definition['mapping_class_column'];
    $studentClassExpression = $definition['student_class_expression'];

    $classRows = $pdo->query(
        "SELECT id, name, sort_order
         FROM {$classTable}
         WHERE is_active = 1
         ORDER BY sort_order, name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $studentProgressStmt = $pdo->prepare(
        "SELECT
            st.id AS student_id,
            COUNT(DISTINCT CASE
                WHEN cs.is_required = 1 AND s.is_active = 1
                THEN cs.subject_id
            END) AS expected_subjects,
            COUNT(DISTINCT CASE
                WHEN cs.is_required = 1 AND s.is_active = 1
                 AND r.ca1 IS NOT NULL AND r.ca2 IS NOT NULL AND r.exam IS NOT NULL
                THEN cs.subject_id
            END) AS completed_subjects,
            COUNT(DISTINCT CASE
                WHEN cs.is_required = 1 AND s.is_active = 1
                 AND (r.ca1 IS NOT NULL OR r.ca2 IS NOT NULL OR r.exam IS NOT NULL)
                THEN cs.subject_id
            END) AS started_subjects
         FROM students st
         LEFT JOIN {$mappingTable} cs
            ON cs.{$mappingClassColumn} = ?
           AND cs.is_required = 1
         LEFT JOIN subjects s
            ON s.id = cs.subject_id AND s.is_active = 1
         LEFT JOIN results r
            ON r.student_id = st.id
           AND r.subject_id = cs.subject_id
           AND r.academic_period_id = ?
           AND r.class_type = ?
           AND r.class_id = ?
         WHERE st.is_active = 1
           AND {$studentClassExpression} = ?
         GROUP BY st.id"
    );

    foreach ($classRows as $classRow) {
        $classId = (int) $classRow['id'];
        $studentProgressStmt->execute([
            $classId,
            $periodId,
            $type,
            $classId,
            $classId,
        ]);
        $studentRows = $studentProgressStmt->fetchAll(PDO::FETCH_ASSOC);
        $summary = dashboardSummariseStudentProgress($studentRows);
        foreach ($studentRows as $studentRow) {
            $studentId = (int) $studentRow['student_id'];
            if (!isset($schoolProgressByStudent[$studentId])) {
                $schoolProgressByStudent[$studentId] = [
                    'expected_subjects' => 0,
                    'completed_subjects' => 0,
                    'started_subjects' => 0,
                ];
            }
            $schoolProgressByStudent[$studentId]['expected_subjects'] += (int) $studentRow['expected_subjects'];
            $schoolProgressByStudent[$studentId]['completed_subjects'] += (int) $studentRow['completed_subjects'];
            $schoolProgressByStudent[$studentId]['started_subjects'] += (int) $studentRow['started_subjects'];
        }

        $classEntry = [
            'class_type' => $type,
            'class_id' => $classId,
            'class_name' => (string) $classRow['name'],
            'sort_order' => (int) $classRow['sort_order'],
            'student_count' => $summary['students'],
            'completed_students' => $summary['complete_students'],
            'incomplete_students' => $summary['incomplete_students'],
            'no_result_students' => $summary['no_result_students'],
            'unassigned_students' => $summary['unassigned_students'],
            'entered_entries' => $summary['completed_entries'],
            'started_entries' => $summary['started_entries'],
            'expected_entries' => $summary['expected_entries'],
            'completion_percentage' => $summary['completion_percentage'],
        ];

        $classProgress[] = $classEntry;
        $classProgressByType[$type][] = $classEntry;
    }
}

$schoolSummary = dashboardSummariseStudentProgress(array_values($schoolProgressByStudent));

echo json_encode([
    'success' => true,
    'role' => $dashboardRole,
    'stats' => [
        'students' => $students,
        'classes' => $classes,
        'conventional_classes' => $conventionalClasses,
        'arabic_classes' => $arabicClasses,
        'subjects' => $subjects,
        'complete_students' => $schoolSummary['complete_students'],
        'incomplete_students' => $schoolSummary['incomplete_students'],
        'no_result_students' => $schoolSummary['no_result_students'],
        'unassigned_students' => $schoolSummary['unassigned_students'],
        'expected_results' => $schoolSummary['expected_entries'],
        'completed_results' => $schoolSummary['completed_entries'],
        'started_results' => $schoolSummary['started_entries'],
        'completion_percentage' => $schoolSummary['completion_percentage'],
        'class_progress' => $classProgress,
        'class_progress_by_type' => $classProgressByType,
    ],
], JSON_UNESCAPED_UNICODE);
