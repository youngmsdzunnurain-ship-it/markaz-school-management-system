<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/student-portal-access.php';

header('Content-Type: application/json; charset=utf-8');

$studentPortalScope = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? studentPortalResultScope()
    : null;
if ($studentPortalScope === null) {
    requireResultStaff();
}

function requireFullClassBehaviourAccess(PDO $pdo, int $studentId, string $classType, int $classId): void
{
    if (!in_array($classType, ['conventional', 'arabic'], true) || $classId < 1) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Valid class context is required.']);
        exit;
    }

    $column = $classType === 'arabic' ? 'arabic_class_id' : 'class_id';
    $student = $pdo->prepare("SELECT id FROM students WHERE id = ? AND {$column} = ? LIMIT 1");
    $student->execute([$studentId, $classId]);

    if (!$student->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Student does not belong to this class.']);
        exit;
    }

    $assignment = $pdo->prepare(
        'SELECT 1 FROM teacher_assignments
         WHERE account_id = ? AND class_type = ? AND class_id = ?
           AND is_active = 1 AND subject_id IS NULL LIMIT 1'
    );
    $assignment->execute([(int) ($_SESSION['admin_id'] ?? 0), $classType, $classId]);

    if (!$assignment->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Full-class assignment required for behavioural assessments.']);
        exit;
    }
}



if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);

    if (!$studentId) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Student ID is required.'
        ]);
        exit;
    }

    $pdo = db();

    if ($studentPortalScope !== null && (int) $studentId !== (int) $studentPortalScope['student_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Student result access has expired or is invalid.']);
        exit;
    }

    if ($studentPortalScope === null && currentAdminRole() === 'teacher') {
        $classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
        requireFullClassBehaviourAccess(
            $pdo, (int) $studentId,
            (string) ($_GET['class_type'] ?? ''),
            (int) ($classId ?: 0)
        );
    }

    if ($studentPortalScope !== null) {
        $periodStmt = $pdo->prepare(
            'SELECT id, is_finalized FROM academic_periods WHERE id = ? LIMIT 1'
        );
        $periodStmt->execute([(int) $studentPortalScope['academic_period_id']]);
    } else {
        $periodStmt = $pdo->query(
            'SELECT ap.id, ap.is_finalized
             FROM academic_periods ap
             INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
             WHERE s.is_current = 1 AND ap.is_current = 1
             ORDER BY ap.id DESC LIMIT 1'
        );
    }

    $period = $periodStmt->fetch();

    if (!$period) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No current academic period is configured.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT
            attendance,
            neatness,
            contentment,
            obedience,
            responsiveness,
            tolerance,
            attentiveness,
            truthfulness,
            calmness
         FROM behavioural_reports
         WHERE student_id = ?
           AND academic_period_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $studentId,
        $period['id']
    ]);

    $report = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'behaviour' => $report ?: null
    ]);
    exit;
}

if (!in_array(currentAdminRole(), ['admin', 'teacher'], true)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Only the Admin can edit behavioural assessments.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'GET or POST request required.'
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

$studentId = filter_var(
    $input['student_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$studentId) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Student ID is required.'
    ]);
    exit;
}

$fields = [
    'attendance',
    'neatness',
    'contentment',
    'obedience',
    'responsiveness',
    'tolerance',
    'attentiveness',
    'truthfulness',
    'calmness'
];

$values = [];

foreach ($fields as $field) {
    $value = $input[$field] ?? null;

    if ($value === '' || $value === null) {
        $values[$field] = null;
        continue;
    }

    if (
        !is_numeric($value) ||
        (int) $value < 1 ||
        (int) $value > 5
    ) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => ucfirst($field) . ' must be between 1 and 5.'
        ]);
        exit;
    }

    $values[$field] = (int) $value;
}

try {
    $pdo = db();

    if (currentAdminRole() === 'teacher') {
        $classId = filter_var($input['class_id'] ?? null, FILTER_VALIDATE_INT);
        requireFullClassBehaviourAccess(
            $pdo, (int) $studentId,
            (string) ($input['class_type'] ?? ''),
            (int) ($classId ?: 0)
        );
    }

    $periodStmt = $pdo->query(
        'SELECT ap.id, ap.is_finalized
         FROM academic_periods ap
         INNER JOIN academic_sessions s
             ON s.id = ap.academic_session_id
         WHERE s.is_current = 1
           AND ap.is_current = 1
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
            'message' => 'Behavioural assessments for this academic period are finalized and read-only.'
        ]);
        exit;
    }

    $studentStmt = $pdo->prepare(
        'SELECT id
         FROM students
         WHERE id = ?
         LIMIT 1'
    );

    $studentStmt->execute([$studentId]);

    if (!$studentStmt->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Student not found.'
        ]);
        exit;
    }

    // Lock the period row so this write cannot race with term finalization.
    $pdo->beginTransaction();
    $lockedPeriodStmt = $pdo->prepare(
        'SELECT is_finalized FROM academic_periods WHERE id = ? FOR UPDATE'
    );
    $lockedPeriodStmt->execute([(int) $period['id']]);
    $lockedPeriod = $lockedPeriodStmt->fetch();

    if (!$lockedPeriod || (int) $lockedPeriod['is_finalized'] === 1) {
        $pdo->rollBack();
        http_response_code(423);
        echo json_encode([
            'success' => false,
            'code' => 'ACADEMIC_PERIOD_FINALIZED',
            'message' => 'Behavioural assessments for this academic period are finalized and read-only.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO behavioural_reports (
            student_id,
            academic_period_id,
            attendance,
            neatness,
            contentment,
            obedience,
            responsiveness,
            tolerance,
            attentiveness,
            truthfulness,
            calmness
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            attendance = VALUES(attendance),
            neatness = VALUES(neatness),
            contentment = VALUES(contentment),
            obedience = VALUES(obedience),
            responsiveness = VALUES(responsiveness),
            tolerance = VALUES(tolerance),
            attentiveness = VALUES(attentiveness),
            truthfulness = VALUES(truthfulness),
            calmness = VALUES(calmness),
            updated_at = CURRENT_TIMESTAMP'
    );

    $stmt->execute([
        $studentId,
        $period['id'],
        $values['attendance'],
        $values['neatness'],
        $values['contentment'],
        $values['obedience'],
        $values['responsiveness'],
        $values['tolerance'],
        $values['attentiveness'],
        $values['truthfulness'],
        $values['calmness']
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Behavioural report saved successfully.'
    ]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to save behavioural report.'
    ]);
}
