<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/config/app.php';

requireResultStaff();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'POST request required.'
    ]);

    exit;
}

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data.'
    ]);

    exit;
}

$classType = (string) ($input['class_type'] ?? '');
if (!in_array($classType, ['conventional', 'arabic'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid class type.']);
    exit;
}

$classId = filter_var(
    $input['class_id'] ?? null,
    FILTER_VALIDATE_INT
);

$studentId = filter_var(
    $input['student_id'] ?? null,
    FILTER_VALIDATE_INT
);

$subjectId = filter_var(
    $input['subject_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$classId || !$studentId || !$subjectId) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Class, student and subject are required.'
    ]);

    exit;
}

// Teachers may save results only for their assigned class and subject.
requireTeacherAssignment(
    (int) $classId,
    (int) $subjectId,
    $classType
);

function parseScore(mixed $value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return round((float) $value, 2);
}

$ca1 = parseScore($input['ca1'] ?? null);
$ca2 = parseScore($input['ca2'] ?? null);
$exam = parseScore($input['exam'] ?? null);

$teacherRemark = trim((string) ($input['teacher_remark'] ?? ''));
$resultRemark = trim((string) ($input['result_remark'] ?? ''));

try {
    $pdo = db();

    $periodStmt = $pdo->query(
        'SELECT ap.id, ap.is_finalized
         FROM academic_periods ap
         INNER JOIN academic_sessions s
            ON s.id = ap.academic_session_id
         WHERE s.is_current = 1
         ORDER BY ap.id DESC
         LIMIT 1'
    );

    $period = $periodStmt->fetch();

    if (!$period) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'No current academic period is configured.'
        ]);

        exit;
    }

    if ((int) ($period['is_finalized'] ?? 0) === 1) {
        http_response_code(423);
        echo json_encode([
            'success' => false,
            'code' => 'ACADEMIC_PERIOD_FINALIZED',
            'message' => 'Results for this academic period are finalized and read-only.'
        ]);
        exit;
    }

    $studentClassColumn = $classType === 'arabic'
        ? 'arabic_class_id'
        : 'class_id';

    $studentStmt = $pdo->prepare(
        "SELECT id
         FROM students
         WHERE id = ?
           AND {$studentClassColumn} = ?
           AND is_active = 1
         LIMIT 1"
    );

    $studentStmt->execute([
        $studentId,
        $classId
    ]);

    if (!$studentStmt->fetch()) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Student does not belong to this active class.'
        ]);

        exit;
    }

    $mappingTable = $classType === 'arabic'
        ? 'arabic_class_subjects'
        : 'class_subjects';
    $mappingClassColumn = $classType === 'arabic'
        ? 'arabic_class_id'
        : 'class_id';

    $subjectStmt = $pdo->prepare(
        "SELECT
            s.id,
            s.max_ca1,
            s.max_ca2,
            s.max_exam
         FROM {$mappingTable} cs
         INNER JOIN subjects s
            ON s.id = cs.subject_id
         WHERE cs.{$mappingClassColumn} = ?
           AND cs.subject_id = ?
           AND s.is_active = 1
         LIMIT 1"
    );

    $subjectStmt->execute([
        $classId,
        $subjectId
    ]);

    $subject = $subjectStmt->fetch();

    if (!$subject) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Subject is not assigned to this class.'
        ]);

        exit;
    }

    $maxCa1 = (float) $subject['max_ca1'];
    $maxCa2 = (float) $subject['max_ca2'];
    $maxExam = (float) $subject['max_exam'];

    if ($ca1 !== null && ($ca1 < 0 || $ca1 > $maxCa1)) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => "CA1 must be between 0 and {$maxCa1}."
        ]);

        exit;
    }

    if ($ca2 !== null && ($ca2 < 0 || $ca2 > $maxCa2)) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => "CA2 must be between 0 and {$maxCa2}."
        ]);

        exit;
    }

    if ($exam !== null && ($exam < 0 || $exam > $maxExam)) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => "Exam must be between 0 and {$maxExam}."
        ]);

        exit;
    }

    $checkStmt = $pdo->prepare(
        'SELECT id
         FROM results
         WHERE student_id = ?
           AND class_type = ?
           AND class_id = ?
           AND subject_id = ?
           AND academic_period_id = ?
         LIMIT 1'
    );

    $checkStmt->execute([
        $studentId,
        $classType,
        $classId,
        $subjectId,
        $period['id']
    ]);

    $existing = $checkStmt->fetch();

    if ($existing) {
        $updateStmt = $pdo->prepare(
            'UPDATE results
             SET
                ca1 = ?,
                ca2 = ?,
                exam = ?,
                teacher_remark = ?,
                result_remark = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND class_type = ?
               AND class_id = ?'
        );

        $updateStmt->execute([
            $ca1,
            $ca2,
            $exam,
            $teacherRemark !== '' ? $teacherRemark : null,
            $resultRemark !== '' ? $resultRemark : null,
            $existing['id'],
            $classType,
            $classId
        ]);

        $resultId = (int) $existing['id'];
    } else {
        $insertStmt = $pdo->prepare(
            'INSERT INTO results (
                student_id,
                class_type,
                class_id,
                subject_id,
                academic_period_id,
                ca1,
                ca2,
                exam,
                teacher_remark,
                result_remark
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $insertStmt->execute([
            $studentId,
            $classType,
            $classId,
            $subjectId,
            $period['id'],
            $ca1,
            $ca2,
            $exam,
            $teacherRemark !== '' ? $teacherRemark : null,
            $resultRemark !== '' ? $resultRemark : null
        ]);

        $resultId = (int) $pdo->lastInsertId();
    }

    $total = round(
        ($ca1 ?? 0)
        + ($ca2 ?? 0)
        + ($exam ?? 0),
        2
    );

    echo json_encode([
        'success' => true,
        'message' => 'Result saved successfully.',
        'result' => [
            'id' => $resultId,
            'student_id' => $studentId,
            'subject_id' => $subjectId,
            'academic_period_id' => (int) $period['id'],
            'ca1' => $ca1,
            'ca2' => $ca2,
            'exam' => $exam,
            'total' => $total
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to save result.'
    ]);
}
