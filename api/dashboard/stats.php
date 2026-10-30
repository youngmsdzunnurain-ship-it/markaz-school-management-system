<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (currentAdminRole() === 'teacher') {
    require __DIR__ . '/teacher-stats.php';
    exit;
}

requireAdmin();

$pdo = db();

/*
 * BASIC DASHBOARD COUNTS
 */
$students = (int) $pdo->query(
    "SELECT COUNT(*) FROM students WHERE is_active = 1"
)->fetchColumn();

$classes = (int) $pdo->query(
    "SELECT COUNT(*) FROM classes WHERE is_active = 1"
)->fetchColumn();

$subjects = (int) $pdo->query(
    "SELECT COUNT(*) FROM subjects WHERE is_active = 1"
)->fetchColumn();

/*
 * CURRENT ACADEMIC PERIOD
 * Same rule used by the Results pages.
 */
$periodStmt = $pdo->query(
    "SELECT
        ap.id
     FROM academic_periods ap
     INNER JOIN academic_sessions s
        ON s.id = ap.academic_session_id
     INNER JOIN terms t
        ON t.id = ap.term_id
     WHERE s.is_current = 1
     ORDER BY ap.id DESC
     LIMIT 1"
);

$period = $periodStmt->fetch();

$results = 0;
$expectedResults = 0;
$completeStudents = 0;
$incompleteStudents = 0;
$noResultStudents = 0;
$completionPercentage = 0;

if ($period) {
    $periodId = (int) $period['id'];

    /*
     * Total result rows recorded for the current academic period.
     */
    $resultStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM results r
         INNER JOIN students st
            ON st.id = r.student_id
         WHERE st.is_active = 1
           AND r.academic_period_id = ?"
    );

    $resultStmt->execute([$periodId]);
    $results = (int) $resultStmt->fetchColumn();

    /*
     * Build student result-completion information.
     *
     * A student's expected results are based on the ACTIVE subjects
     * assigned to that student's conventional class.
     *
     * Only REQUIRED subjects are used for completion status.
     */
    $progressStmt = $pdo->prepare(
        "SELECT
            st.id AS student_id,
            COUNT(DISTINCT CASE
                WHEN cs.is_required = 1
                 AND s.is_active = 1
                THEN cs.subject_id
            END) AS required_subjects,
            COUNT(DISTINCT CASE
                WHEN cs.is_required = 1
                 AND s.is_active = 1
                 AND r.id IS NOT NULL
                THEN cs.subject_id
            END) AS completed_subjects
         FROM students st
         LEFT JOIN class_subjects cs
            ON cs.class_id = st.class_id
         LEFT JOIN subjects s
            ON s.id = cs.subject_id
         LEFT JOIN results r
            ON r.student_id = st.id
           AND r.subject_id = cs.subject_id
           AND r.academic_period_id = ?
         WHERE st.is_active = 1
         GROUP BY st.id"
    );

    $progressStmt->execute([$periodId]);
    $progressRows = $progressStmt->fetchAll();

    foreach ($progressRows as $row) {
        $required = (int) $row['required_subjects'];
        $completed = (int) $row['completed_subjects'];

        /*
         * If a class has no required subjects configured,
         * do not incorrectly mark the student as complete.
         */
        if ($required === 0 || $completed === 0) {
            $noResultStudents++;
        } elseif ($completed >= $required) {
            $completeStudents++;
        } else {
            $incompleteStudents++;
        }

        $expectedResults += $required;
    }

    if ($expectedResults > 0) {
        $completionPercentage = round(
            ($results / $expectedResults) * 100,
            1
        );

        if ($completionPercentage > 100) {
            $completionPercentage = 100;
        }
    }
}


$classProgress = [];
$classRows = $pdo->query(
    "SELECT id, name, sort_order FROM classes WHERE is_active = 1 ORDER BY sort_order, name"
)->fetchAll(PDO::FETCH_ASSOC);

$assessmentFields = ['attendance', 'punctuality', 'neatness', 'conduct', 'cooperation', 'attentiveness'];
$assessmentTableExists = (bool) $pdo->query("SHOW TABLES LIKE 'student_assessments'")->fetchColumn();

foreach ($classRows as $classRow) {
    $classId = (int) $classRow['id'];
    $studentStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ? AND is_active = 1");
    $studentStmt->execute([$classId]);
    $studentCount = (int) $studentStmt->fetchColumn();

    $requiredStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM class_subjects cs
         INNER JOIN subjects s ON s.id = cs.subject_id AND s.is_active = 1
         WHERE cs.class_id = ? AND cs.is_required = 1"
    );
    $requiredStmt->execute([$classId]);
    $requiredSubjects = (int) $requiredStmt->fetchColumn();

    $expected = $studentCount * (($requiredSubjects * 3) + count($assessmentFields));
    $entered = 0;

    if ($studentCount > 0 && $requiredSubjects > 0) {
        $scoreStmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(r.ca1 IS NOT NULL), 0) AS ca1_count,
                COALESCE(SUM(r.ca2 IS NOT NULL), 0) AS ca2_count,
                COALESCE(SUM(r.exam IS NOT NULL), 0) AS exam_count
             FROM students st
             INNER JOIN class_subjects cs ON cs.class_id = st.class_id AND cs.is_required = 1
             INNER JOIN subjects s ON s.id = cs.subject_id AND s.is_active = 1
             LEFT JOIN results r ON r.student_id = st.id
                 AND r.subject_id = cs.subject_id AND r.academic_period_id = ?
                 AND r.class_type = 'conventional' AND r.class_id = ?
             WHERE st.class_id = ? AND st.is_active = 1"
        );
        $scoreStmt->execute([$periodId, $classId, $classId]);
        $counts = $scoreStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (['ca1_count', 'ca2_count', 'exam_count'] as $field) {
            $entered += (int) ($counts[$field] ?? 0);
        }
    }

    if ($assessmentTableExists && $studentCount > 0) {
        $assessmentStmt = $pdo->prepare(
            "SELECT " . implode(', ', array_map(
                fn($field) => "COALESCE(SUM(sa.`$field` IS NOT NULL AND sa.`$field` <> ''), 0) AS `$field`",
                $assessmentFields
            )) . "
             FROM student_assessments sa
             INNER JOIN students st ON st.id = sa.student_id
             WHERE st.class_id = ? AND st.is_active = 1 AND sa.academic_period_id = ?"
        );
        $assessmentStmt->execute([$classId, $periodId]);
        $assessmentCounts = $assessmentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach ($assessmentFields as $field) {
            $entered += (int) ($assessmentCounts[$field] ?? 0);
        }
    }

    $classProgress[] = [
        'class_id' => $classId,
        'class_name' => $classRow['name'],
        'student_count' => $studentCount,
        'entered_entries' => $entered,
        'expected_entries' => $expected,
        'completion_percentage' => $expected > 0 ? min(100, round($entered / $expected * 100, 1)) : 0
    ];
}

echo json_encode([
    'success' => true,
    'stats' => [
        'students' => $students,
        'classes' => $classes,
        'subjects' => $subjects,
        'results' => $results,

        'complete_students' => $completeStudents,
        'incomplete_students' => $incompleteStudents,
        'no_result_students' => $noResultStudents,

        'expected_results' => $expectedResults,
        'completion_percentage' => $completionPercentage,
      'class_progress' => $classProgress,
    ],
]);
