<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

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
    ],
]);
