<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/config/app.php';
require_once __DIR__ . '/../../../app/middleware/auth.php';
require_once __DIR__ . '/../../../app/helpers/result-progress.php';
require_once __DIR__ . '/../../../app/helpers/class-hierarchy.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$role = currentAdminRole();
if ($role === 'teacher') {
    try {
        $pdo = db();
        $accountId = (int) ($_SESSION['admin_id'] ?? 0);
        $assignmentStmt = $pdo->prepare(
            'SELECT ta.class_type, ta.class_id, ta.subject_id,
                    CASE WHEN ta.class_type = "arabic" THEN ac.name ELSE c.name END AS class_name,
                    CASE WHEN ta.class_type = "arabic" THEN ac.sort_order ELSE c.sort_order END AS sort_order
             FROM teacher_assignments ta
             LEFT JOIN classes c ON ta.class_type = "conventional" AND c.id = ta.class_id
             LEFT JOIN arabic_classes ac ON ta.class_type = "arabic" AND ac.id = ta.class_id
             WHERE ta.account_id = ? AND ta.is_active = 1
             ORDER BY ta.class_type, class_name, ta.subject_id'
        );
        $assignmentStmt->execute([$accountId]);
        $rows = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        $assignedStudentIds = [];
        $assignedSubjectIds = [];
        foreach ($rows as $row) {
            $key = $row['class_type'] . ':' . (int) $row['class_id'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'class_id' => (int) $row['class_id'],
                    'class_type' => $row['class_type'],
                    'class_name' => $row['class_name'] ?: 'Assigned class',
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'full_class' => false,
                    'subjects' => [],
                    'student_count' => 0,
                ];
            }
            if ($row['subject_id'] === null) {
                $grouped[$key]['full_class'] = true;
                $grouped[$key]['subjects'] = [];
            } elseif (!$grouped[$key]['full_class']) {
                $subjectId = (int) $row['subject_id'];
                $grouped[$key]['subjects'][$subjectId] = true;
            }
        }

        foreach ($grouped as &$assignment) {
            $classColumn = $assignment['class_type'] === 'arabic' ? 'arabic_class_id' : 'class_id';
            $studentCountStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM students WHERE {$classColumn} = ? AND is_active = 1"
            );
            $studentCountStmt->execute([$assignment['class_id']]);
            $assignment['student_count'] = (int) $studentCountStmt->fetchColumn();

            $subjectIds = $assignment['full_class']
                ? []
                : array_keys($assignment['subjects']);
            if ($assignment['full_class']) {
                $mappingTable = $assignment['class_type'] === 'arabic' ? 'arabic_class_subjects' : 'class_subjects';
                $mappingColumn = $assignment['class_type'] === 'arabic' ? 'arabic_class_id' : 'class_id';
                $subjectStmt = $pdo->prepare(
                    "SELECT subject_id FROM {$mappingTable} WHERE {$mappingColumn} = ?"
                );
                $subjectStmt->execute([$assignment['class_id']]);
                $subjectIds = array_map('intval', $subjectStmt->fetchAll(PDO::FETCH_COLUMN));
            }
            $subjectNames = [];
            if ($subjectIds) {
                $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
                $nameStmt = $pdo->prepare(
                    "SELECT id, name FROM subjects WHERE is_active = 1 AND id IN ({$placeholders}) ORDER BY name"
                );
                $nameStmt->execute($subjectIds);
                foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $subject) {
                    $subjectNames[] = $subject['name'];
                    $assignedSubjectIds[(int) $subject['id']] = true;
                }
            }
            $assignment['subjects'] = $subjectNames;
            unset($assignment['subjects_map']);
        }
        unset($assignment);

        foreach ($grouped as $assignment) {
            $classColumn = $assignment['class_type'] === 'arabic' ? 'arabic_class_id' : 'class_id';
            $studentStmt = $pdo->prepare(
                "SELECT id FROM students WHERE {$classColumn} = ? AND is_active = 1"
            );
            $studentStmt->execute([$assignment['class_id']]);
            foreach ($studentStmt->fetchAll(PDO::FETCH_COLUMN) as $studentId) {
                $assignedStudentIds[(int) $studentId] = true;
            }
        }

        $orderedAssignments = markazSortAndDeduplicateClasses(array_map(
            static function (array $assignment): array {
                $assignment['id'] = $assignment['class_id'];
                $assignment['name'] = $assignment['class_name'];
                return $assignment;
            },
            array_values($grouped)
        ));
        $orderedAssignments = array_map(static function (array $assignment): array {
            unset($assignment['id'], $assignment['name']);
            return $assignment;
        }, $orderedAssignments);

        echo json_encode([
            'success' => true,
            'role' => 'teacher',
            'stats' => [
                'assigned_students' => count($assignedStudentIds),
                'assigned_classes' => count($grouped),
                'assigned_subjects' => count($assignedSubjectIds),
            ],
            'assignments' => $orderedAssignments,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    } catch (Throwable $error) {
        error_log('Teacher dashboard data error: ' . $error->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to load your teaching assignments.']);
        exit;
    }
}

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
        $progressRows = $progressStmt->fetchAll(PDO::FETCH_ASSOC);

        // Behavioural reports are period-scoped and shared by the student's
        // result card, regardless of whether the student has conventional,
        // Arabic, or both class contexts.
        $behaviourStmt = $pdo->prepare(
            'SELECT br.student_id, br.attendance, br.neatness, br.contentment,
                    br.obedience, br.responsiveness, br.calmness, br.tolerance,
                    br.attentiveness, br.truthfulness
             FROM behavioural_reports br
             INNER JOIN students st ON st.id = br.student_id AND st.is_active = 1
             WHERE br.academic_period_id = ?'
        );
        $behaviourStmt->execute([$periodId]);
        $behaviourRows = $behaviourStmt->fetchAll(PDO::FETCH_ASSOC);

        $progress = array_merge($progress, summarizeResultProgress(
            $progressRows,
            $behaviourRows
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
