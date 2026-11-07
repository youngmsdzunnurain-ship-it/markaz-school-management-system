<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/config/app.php';
require_once __DIR__ . '/../../../app/middleware/auth.php';
require_once __DIR__ . '/../../../app/helpers/result-progress.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

requireAdmin();

try {
    $pdo = db();

    $students = (int) $pdo->query(
        'SELECT COUNT(*) FROM students WHERE is_active = 1'
    )->fetchColumn();

    $classes = (int) $pdo->query(
        'SELECT COUNT(*) FROM classes WHERE is_active = 1'
    )->fetchColumn();

    $subjects = (int) $pdo->query(
        'SELECT COUNT(*) FROM subjects WHERE is_active = 1'
    )->fetchColumn();

    $periodStmt = $pdo->query(
        'SELECT ap.id
         FROM academic_periods ap
         INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
         WHERE s.is_current = 1 AND ap.is_current = 1
         ORDER BY ap.id DESC
         LIMIT 1'
    );
    $periodId = $periodStmt->fetchColumn();

    $progress = [
        'complete_students' => 0,
        'incomplete_students' => 0,
        'no_result_students' => 0,
        'unassigned_students' => 0,
        'completed_results' => 0,
        'expected_results' => 0,
        'completion_percentage' => 0.0,
    ];
    $results = 0;

    if ($periodId !== false) {
        $periodId = (int) $periodId;

        // Build one row per active student and required subject in each
        // class context. Arabic and conventional IDs are deliberately scoped
        // independently, because a student may belong to both class systems.
        $progressSql = "
            SELECT
                'conventional' AS class_type,
                st.class_id AS class_id,
                st.id AS student_id,
                s.id AS subject_id,
                r.ca1, r.ca2, r.exam
            FROM students st
            INNER JOIN classes c
                ON c.id = st.class_id AND c.is_active = 1
            LEFT JOIN class_subjects cs
                ON cs.class_id = st.class_id AND cs.is_required = 1
            LEFT JOIN subjects s
                ON s.id = cs.subject_id AND s.is_active = 1
            LEFT JOIN results r
                ON r.student_id = st.id
               AND r.class_type = 'conventional'
               AND r.class_id = st.class_id
               AND r.subject_id = s.id
               AND r.academic_period_id = ?
            WHERE st.is_active = 1

            UNION ALL

            SELECT
                'arabic' AS class_type,
                st.arabic_class_id AS class_id,
                st.id AS student_id,
                s.id AS subject_id,
                r.ca1, r.ca2, r.exam
            FROM students st
            INNER JOIN arabic_classes ac
                ON ac.id = st.arabic_class_id AND ac.is_active = 1
            LEFT JOIN arabic_class_subjects acs
                ON acs.arabic_class_id = st.arabic_class_id AND acs.is_required = 1
            LEFT JOIN subjects s
                ON s.id = acs.subject_id AND s.is_active = 1
            LEFT JOIN results r
                ON r.student_id = st.id
               AND r.class_type = 'arabic'
               AND r.class_id = st.arabic_class_id
               AND r.subject_id = s.id
               AND r.academic_period_id = ?
            WHERE st.is_active = 1 AND st.arabic_class_id IS NOT NULL
        ";

        $progressStmt = $pdo->prepare($progressSql);
        $progressStmt->execute([$periodId, $periodId]);
        $progress = array_merge($progress, summarizeResultProgress(
            $progressStmt->fetchAll(PDO::FETCH_ASSOC)
        ));

        $resultStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM results r
             INNER JOIN students st ON st.id = r.student_id AND st.is_active = 1
             WHERE r.academic_period_id = ?'
        );
        $resultStmt->execute([$periodId]);
        $results = (int) $resultStmt->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'students' => $students,
            'classes' => $classes,
            'subjects' => $subjects,
            'results' => $results,
            'complete_students' => $progress['complete_students'],
            'incomplete_students' => $progress['incomplete_students'],
            'no_result_students' => $progress['no_result_students'],
            'unassigned_students' => $progress['unassigned_students'],
            'completed_results' => $progress['completed_results'],
            'expected_results' => $progress['expected_results'],
            'completion_percentage' => $progress['completion_percentage'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('Dashboard statistics error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load dashboard statistics.',
    ]);
}
