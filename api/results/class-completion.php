<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/config/app.php';

requireResultStaff();
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    $classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);

    if (!$classId || $classId < 1) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'A valid conventional class ID is required.'
        ]);
        exit;
    }

    $classStmt = $pdo->prepare(
        'SELECT id, name FROM classes WHERE id = ? LIMIT 1'
    );
    $classStmt->execute([$classId]);
    $class = $classStmt->fetch();

    if (!$class) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Class not found.'
        ]);
        exit;
    }

    $periodStmt = $pdo->query(
        'SELECT ap.id
         FROM academic_periods ap
         INNER JOIN academic_sessions s
            ON s.id = ap.academic_session_id
         WHERE s.is_current = 1
         ORDER BY ap.id DESC
         LIMIT 1'
    );
    $periodId = $periodStmt->fetchColumn();

    if ($periodId === false) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No current academic period is configured.'
        ]);
        exit;
    }

    $settingStmt = $pdo->prepare(
        'SELECT setting_value FROM settings
         WHERE setting_key = ? LIMIT 1'
    );
    $settingStmt->execute(['result_print_completion_threshold']);
    $savedThreshold = $settingStmt->fetchColumn();
    $threshold = $savedThreshold !== false && is_numeric($savedThreshold)
        ? max(0, min(100, (float) $savedThreshold))
        : 95.0;

    $studentStmt = $pdo->prepare(
        'SELECT id, admission_number, first_name, middle_name, last_name
         FROM students
         WHERE class_id = ?
         ORDER BY last_name ASC, first_name ASC, id ASC'
    );
    $studentStmt->execute([$classId]);
    $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

    $subjectStmt = $pdo->prepare(
        'SELECT s.id, s.name, cs.is_required
         FROM class_subjects cs
         INNER JOIN subjects s ON s.id = cs.subject_id
         WHERE cs.class_id = ? AND s.is_active = 1
         ORDER BY cs.sort_order, s.name'
    );
    $subjectStmt->execute([$classId]);
    $subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

    $requiredSubjects = array_values(array_filter(
        $subjects,
        static fn(array $subject): bool =>
            in_array($subject['is_required'], [true, 1, '1'], true)
    ));

    $behaviourFields = [
        'attendance' => 'Punctuality',
        'neatness' => 'Neatness',
        'contentment' => 'Contentment',
        'obedience' => 'Obedience',
        'responsiveness' => 'Responsiveness',
        'calmness' => 'Calmness',
        'tolerance' => 'Tolerance',
        'attentiveness' => 'Attentiveness',
        'truthfulness' => 'Truthfulness'
    ];

    $scoreFields = [
        'ca1' => 'CA1',
        'ca2' => 'CA2',
        'exam' => 'Exam'
    ];

    $resultMap = [];
    $behaviourMap = [];

    if ($students) {
        $studentIds = array_map(
            static fn(array $student): int => (int) $student['id'],
            $students
        );
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

        $resultStmt = $pdo->prepare(
            "SELECT student_id, subject_id, ca1, ca2, exam
             FROM results
             WHERE academic_period_id = ?
               AND student_id IN ($placeholders)"
        );
        $resultStmt->execute(array_merge([(int) $periodId], $studentIds));

        foreach ($resultStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $resultMap[(int) $row['student_id']][(int) $row['subject_id']] = $row;
        }

        $behaviourStmt = $pdo->prepare(
            "SELECT student_id, attendance, neatness, contentment, obedience,
                    responsiveness, calmness, tolerance, attentiveness, truthfulness
             FROM behavioural_reports
             WHERE academic_period_id = ?
               AND student_id IN ($placeholders)"
        );
        $behaviourStmt->execute(array_merge([(int) $periodId], $studentIds));

        foreach ($behaviourStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $behaviourMap[(int) $row['student_id']] = $row;
        }
    }

    $completionRows = [];

    foreach ($students as $student) {
        $sid = (int) $student['id'];
        $completed = 0;
        $required = (count($requiredSubjects) * 3) + count($behaviourFields);
        $missing = [];

        foreach ($requiredSubjects as $subject) {
            $subjectId = (int) $subject['id'];
            $subjectName = (string) $subject['name'];
            $saved = $resultMap[$sid][$subjectId] ?? null;

            foreach ($scoreFields as $field => $label) {
                $value = $saved[$field] ?? null;

                if ($value !== null && $value !== '') {
                    $completed++;
                } else {
                    $missing[] = $subjectName . ' — ' . $label;
                }
            }
        }

        $behaviour = $behaviourMap[$sid] ?? [];

        foreach ($behaviourFields as $field => $label) {
            $value = $behaviour[$field] ?? null;

            if ($value !== null && $value !== '') {
                $completed++;
            } else {
                $missing[] = $label;
            }
        }

        $percentage = $required > 0
            ? (int) round(($completed / $required) * 100)
            : 0;

        $completionRows[] = [
            'student_id' => $sid,
            'first_name' => (string) $student['first_name'],
            'middle_name' => (string) ($student['middle_name'] ?? ''),
            'last_name' => (string) $student['last_name'],
            'percentage' => $percentage,
            'completed_fields' => $completed,
            'required_fields' => $required,
            'threshold' => $threshold,
            'eligible' => $percentage >= $threshold,
            'missing_fields' => $missing
        ];
    }

    echo json_encode([
        'success' => true,
        'class_id' => (int) $class['id'],
        'class_name' => $class['name'],
        'academic_period_id' => (int) $periodId,
        'threshold' => $threshold,
        'students' => $completionRows
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('Class result completion error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to calculate class result completion.'
    ]);
}
