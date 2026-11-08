<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/config/app.php';
requireResultStaff();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$classType = (string) ($_GET['class_type'] ?? '');
if (!in_array($classType, ['conventional', 'arabic'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid class type is required.']);
    exit;
}
$classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
if (!$classId || $classId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid class ID is required.']);
    exit;
}
$classTable = $classType === 'arabic' ? 'arabic_classes' : 'classes';
$studentClassColumn = $classType === 'arabic' ? 'arabic_class_id' : 'class_id';
$mappingTable = $classType === 'arabic' ? 'arabic_class_subjects' : 'class_subjects';
$mappingClassColumn = $classType === 'arabic' ? 'arabic_class_id' : 'class_id';

try {
    $pdo = db();
    $classStmt = $pdo->prepare("SELECT id, name FROM {$classTable} WHERE id = ? AND is_active = 1 LIMIT 1");
    $classStmt->execute([$classId]);
    $class = $classStmt->fetch(PDO::FETCH_ASSOC);
    if (!$class) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'The selected class was not found or is inactive.']);
        exit;
    }

    if (currentAdminRole() === 'teacher') {
        $assignmentStmt = $pdo->prepare(
            'SELECT 1 FROM teacher_assignments
             WHERE account_id = ? AND class_type = ? AND class_id = ?
               AND is_active = 1 LIMIT 1'
        );
        $assignmentStmt->execute([(int) ($_SESSION['admin_id'] ?? 0), $classType, $classId]);
        if (!$assignmentStmt->fetchColumn()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are not assigned to this class.']);
            exit;
        }
    }

    $periodStmt = $pdo->query(
        'SELECT ap.id FROM academic_periods ap
         INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
         WHERE s.is_current = 1 AND ap.is_current = 1
         ORDER BY ap.id DESC LIMIT 1'
    );
    $periodId = $periodStmt->fetchColumn();
    if ($periodId === false) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No current academic period is configured.']);
        exit;
    }

    $settingStmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $settingStmt->execute(['result_print_completion_threshold']);
    $savedThreshold = $settingStmt->fetchColumn();
    $threshold = $savedThreshold !== false && is_numeric($savedThreshold)
        ? max(0, min(100, (float) $savedThreshold)) : 95.0;

    $studentStmt = $pdo->prepare(
        "SELECT id, admission_number, first_name, middle_name, last_name
         FROM students WHERE {$studentClassColumn} = ? AND is_active = 1
         ORDER BY last_name ASC, first_name ASC, id ASC"
    );
    $studentStmt->execute([$classId]);
    $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

    $subjectSql = "SELECT s.id, s.name, cs.is_required
        FROM {$mappingTable} cs
        INNER JOIN subjects s ON s.id = cs.subject_id
        WHERE cs.{$mappingClassColumn} = ? AND s.is_active = 1";
    // Completion and print eligibility must use every required subject
    // assigned to the class, matching api/results/student.php.
    $subjectParams = [$classId];
    $subjectSql .= ' ORDER BY cs.sort_order, s.name';
    $subjectStmt = $pdo->prepare($subjectSql);
    $subjectStmt->execute($subjectParams);
    $subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);
    $requiredSubjects = array_values(array_filter(
        $subjects,
        static fn(array $subject): bool => in_array($subject['is_required'], [true, 1, '1'], true)
    ));

    $behaviourFields = [
        'attendance' => 'Punctuality', 'neatness' => 'Neatness',
        'contentment' => 'Contentment', 'obedience' => 'Obedience',
        'responsiveness' => 'Responsiveness', 'calmness' => 'Calmness',
        'tolerance' => 'Tolerance', 'attentiveness' => 'Attentiveness',
        'truthfulness' => 'Truthfulness'
    ];
    $scoreFields = ['ca1' => 'CA1', 'ca2' => 'CA2', 'exam' => 'Exam'];
    $resultMap = [];
    $behaviourMap = [];

    if ($students) {
        $studentIds = array_map(static fn(array $student): int => (int) $student['id'], $students);
        $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
        $subjectIds = array_map(static fn(array $subject): int => (int) $subject['id'], $subjects);

        if ($subjectIds) {
            $subjectPlaceholders = implode(',', array_fill(0, count($subjectIds), '?'));
            $resultStmt = $pdo->prepare(
                "SELECT student_id, subject_id, ca1, ca2, exam FROM results
                 WHERE academic_period_id = ? AND class_type = ? AND class_id = ?
                   AND student_id IN ({$studentPlaceholders})
                   AND subject_id IN ({$subjectPlaceholders})"
            );
            $resultStmt->execute(array_merge(
                [(int) $periodId, $classType, (int) $classId], $studentIds, $subjectIds
            ));
            foreach ($resultStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $resultMap[(int) $row['student_id']][(int) $row['subject_id']] = $row;
            }
        }

        $behaviourStmt = $pdo->prepare(
            "SELECT student_id, attendance, neatness, contentment, obedience,
                    responsiveness, calmness, tolerance, attentiveness, truthfulness
             FROM behavioural_reports
             WHERE academic_period_id = ? AND student_id IN ({$studentPlaceholders})"
        );
        $behaviourStmt->execute(array_merge([(int) $periodId], $studentIds));
        foreach ($behaviourStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $behaviourMap[(int) $row['student_id']] = $row;
        }
    }

    $completionRows = [];
    foreach ($students as $student) {
        $studentId = (int) $student['id'];
        $completed = 0;
        $required = (count($requiredSubjects) * count($scoreFields)) + count($behaviourFields);
        $missing = [];
        foreach ($requiredSubjects as $subject) {
            $saved = $resultMap[$studentId][(int) $subject['id']] ?? null;
            foreach ($scoreFields as $field => $label) {
                $value = $saved[$field] ?? null;
                if ($value !== null && $value !== '') $completed++;
                else $missing[] = (string) $subject['name'] . ' — ' . $label;
            }
        }
        $behaviour = $behaviourMap[$studentId] ?? [];
        foreach ($behaviourFields as $field => $label) {
            $value = $behaviour[$field] ?? null;
            if ($value !== null && $value !== '') $completed++;
            else $missing[] = $label;
        }
        $percentage = $required > 0 ? round(($completed / $required) * 100, 2) : 0.0;
        $completionRows[] = [
            'student_id' => $studentId,
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
        'success' => true, 'class_type' => $classType,
        'class_id' => (int) $class['id'], 'class_name' => $class['name'],
        'academic_period_id' => (int) $periodId, 'threshold' => $threshold,
        'students' => $completionRows
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('Class result completion error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to calculate class result completion.']);
}
