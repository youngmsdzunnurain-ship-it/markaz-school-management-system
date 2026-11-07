<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function studentPortalError(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    studentPortalError(405, 'Please submit the student result form.');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    studentPortalError(400, 'Please complete all four fields correctly.');
}

$admissionNumber = trim((string) ($input['admission_number'] ?? ''));
$classId = filter_var($input['class_id'] ?? null, FILTER_VALIDATE_INT);
$termId = filter_var($input['term_id'] ?? null, FILTER_VALIDATE_INT);
$sessionId = filter_var($input['session_id'] ?? null, FILTER_VALIDATE_INT);

if (
    $admissionNumber === '' ||
    strlen($admissionNumber) > 80 ||
    !$classId || $classId < 1 ||
    !$termId || !in_array($termId, [1, 2, 3], true) ||
    !$sessionId || $sessionId < 1 || $sessionId > 10
) {
    studentPortalError(422, 'Complete all four fields using the available options.');
}

try {
    require_once __DIR__ . '/../../app/middleware/session.php';
    startSecureSession();
    session_regenerate_id(true);

    $pdo = db();

    $accessStmt = $pdo->prepare(
        "SELECT setting_value FROM settings
         WHERE setting_key = 'student_result_access' LIMIT 1"
    );
    $accessStmt->execute();
    $studentAccess = $accessStmt->fetchColumn();

    if (!in_array(strtolower(trim((string) $studentAccess)), ['1', 'true', 'yes', 'on'], true)) {
        studentPortalError(403, 'The Student Portal is currently closed by the Admin.');
    }

    $classStmt = $pdo->prepare(
        "SELECT id, name
         FROM classes
         WHERE id = ?
           AND COALESCE(level_group, '') <> 'Quranic'
         LIMIT 1"
    );
    $classStmt->execute([$classId]);
    $class = $classStmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        studentPortalError(422, 'Select a valid active conventional class.');
    }

    $studentStmt = $pdo->prepare(
        "SELECT id, admission_number, first_name, middle_name, last_name,
                class_id, is_active
         FROM students
         WHERE admission_number = ?
         LIMIT 1"
    );
    $studentStmt->execute([$admissionNumber]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        studentPortalError(404, 'Admission number not found. Check your details or contact the school.');
    }

    // A student may have moved to another class or graduated since this period.
    // Historical access is authorized against the student's saved result rows
    // for the selected class and period, not their current class assignment.

    $periodStmt = $pdo->prepare(
        "SELECT ap.id, s.name AS session_name, t.name AS term_name,
                ap.examination_date, ap.vacation_date, ap.resumption_date,
                ap.results_released
         FROM academic_periods ap
         INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
         INNER JOIN terms t ON t.id = ap.term_id
         WHERE ap.academic_session_id = ?
           AND ap.term_id = ?
         LIMIT 1"
    );
    $periodStmt->execute([$sessionId, $termId]);
    $period = $periodStmt->fetch(PDO::FETCH_ASSOC);

    if (!$period) {
        studentPortalError(404, 'Results for this term and academic session are not available yet. The school must configure this academic period first.');
    }

    if ((int) $period['results_released'] !== 1) {
        studentPortalError(403, 'Results for this term and academic session have not been released by the school.');
    }

    $resultsStmt = $pdo->prepare(
        "SELECT sub.id AS subject_id, sub.name AS subject_name,
                sub.code, sub.max_ca1, sub.max_ca2, sub.max_exam,
                r.ca1, r.ca2, r.exam, r.teacher_remark, r.result_remark
         FROM results r
         INNER JOIN subjects sub ON sub.id = r.subject_id
         WHERE r.student_id = ?
           AND r.academic_period_id = ?
           AND r.class_type = 'conventional'
           AND r.class_id = ?
         ORDER BY sub.name"
    );
    $resultsStmt->execute([
        (int) $student['id'],
        (int) $period['id'],
        (int) $class['id']
    ]);
    $rows = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);

    $published = array_filter(
        $rows,
        static fn(array $row): bool => $row['ca1'] !== null
            || $row['ca2'] !== null
            || $row['exam'] !== null
    );

    if (!$published) {
        studentPortalError(404, 'No results have been published for this student in the selected term and session.');
    }

    if (!$published) {
        studentPortalError(404, 'No results have been published for this student in the selected term and session.');
    }

    $_SESSION['student_portal_result_access'] = [
        'student_id' => (int) $student['id'],
        'class_id' => (int) $class['id'],
        'class_type' => 'conventional',
        'academic_period_id' => (int) $period['id'],
        'expires_at' => time() + 1200,
    ];

    $subjects = [];
    $totalScore = 0.0;
    $scoredSubjects = 0;

    foreach ($rows as $row) {
        $hasScore = $row['ca1'] !== null
            || $row['ca2'] !== null
            || $row['exam'] !== null;

        $ca1 = $row['ca1'] === null ? null : (float) $row['ca1'];
        $ca2 = $row['ca2'] === null ? null : (float) $row['ca2'];
        $exam = $row['exam'] === null ? null : (float) $row['exam'];
        $total = $hasScore
            ? round(($ca1 ?? 0) + ($ca2 ?? 0) + ($exam ?? 0), 2)
            : null;

        if ($hasScore) {
            $totalScore += $total;
            $scoredSubjects++;
        }

        $subjects[] = [
            'name' => $row['subject_name'],
            'code' => $row['code'],
            'ca1' => $ca1,
            'ca2' => $ca2,
            'exam' => $exam,
            'total' => $total,
            'teacher_remark' => $row['teacher_remark'],
            'result_remark' => $row['result_remark']
        ];
    }

    echo json_encode([
        'success' => true,
        'student' => [
            'name' => trim(
                $student['first_name'] . ' ' .
                ($student['middle_name'] ?? '') . ' ' .
                $student['last_name']
            ),
            'admission_number' => $student['admission_number'],
            'class_name' => $class['name']
        ],
        'academic_period' => [
            'session_name' => $period['session_name'],
            'term_name' => $period['term_name'],
            'examination_date' => $period['examination_date']
        ],
        'subjects' => $subjects,
        'performance' => [
            'total_score' => round($totalScore, 2),
            'subjects_scored' => $scoredSubjects,
            'average' => $scoredSubjects > 0
                ? round($totalScore / $scoredSubjects, 2)
                : null
        ]
    ], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    error_log('Student portal result lookup failed: ' . $e->getMessage());
    studentPortalError(500, 'Unable to check results right now. Please try again later.');
}
