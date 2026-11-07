<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $classType = (string) ($_GET['class_type'] ?? '');
    $classId = filter_var($_GET['class_id'] ?? null, FILTER_VALIDATE_INT);
    $date = attendanceValidateDate(
        (string) ($_GET['date'] ?? date('Y-m-d'))
    );
    $periodId = filter_var($_GET['academic_period_id'] ?? null, FILTER_VALIDATE_INT);
    if ($periodId === false) {
        attendanceJsonError(422, 'Choose a valid academic period.');
    }

    if ($classId === false || $classId === null || $classId < 1) {
        attendanceJsonError(422, 'Choose a class to load its register.');
    }

    $class = attendanceRequireClassAccess($pdo, $classType, (int) $classId);
    $studentClassExpression = $classType === 'arabic'
        ? 'COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id)'
        : 's.class_id';

    $stmt = $pdo->prepare(
        "SELECT s.id, s.admission_number, s.first_name, s.middle_name, s.last_name,
                s.gender, s.student_type,
                ar.status AS attendance_status, ar.remarks
         FROM students s
         LEFT JOIN attendance_records ar
           ON ar.student_id = s.id
          AND ar.attendance_date = ?
          AND ar.class_type = ?
          AND ar.class_id = ?
          AND (? IS NULL OR ar.academic_period_id = ? OR ar.academic_period_id IS NULL)
         WHERE {$studentClassExpression} = ?
           AND s.is_active = 1
         ORDER BY s.last_name, s.first_name, s.middle_name, s.admission_number"
    );
    $stmt->execute([$date, $classType, (int) $classId, $periodId ?: null, $periodId ?: null, (int) $classId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = ['total' => count($students), 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'unmarked' => 0];
    foreach ($students as &$student) {
        $student['id'] = (int) $student['id'];
        $status = (string) ($student['attendance_status'] ?? '');
        if ($status === '') {
            $summary['unmarked']++;
        } elseif (isset($summary[$status])) {
            $summary[$status]++;
        }
    }
    unset($student);

    echo json_encode([
        'success' => true,
        'class' => [
            'id' => (int) $class['id'],
            'name' => $class['name'],
            'class_type' => $classType,
        ],
        'date' => $date,
        'students' => $students,
        'summary' => $summary,
        'is_saved' => $summary['unmarked'] === 0 && $summary['total'] > 0,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    attendanceJsonError(405, 'Use GET to load a register or POST to save it.');
}

attendanceRequireSafePost();
$input = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($input)) {
    attendanceJsonError(400, 'The attendance request is not valid JSON.');
}

$classType = (string) ($input['class_type'] ?? '');
$classId = filter_var($input['class_id'] ?? null, FILTER_VALIDATE_INT);
$date = attendanceValidateDate((string) ($input['date'] ?? ''));

if ($classId === false || $classId === null || $classId < 1) {
    attendanceJsonError(422, 'Choose a valid class.');
}

$class = attendanceRequireClassAccess($pdo, $classType, (int) $classId);
$periodId = filter_var($input['academic_period_id'] ?? null, FILTER_VALIDATE_INT);
if ($periodId === false || $periodId === null || $periodId < 1) {
    attendanceJsonError(422, 'Choose the academic session and term before saving attendance.');
}
$periodStmt = $pdo->prepare('SELECT id, is_finalized FROM academic_periods WHERE id = ? LIMIT 1');
$periodStmt->execute([(int) $periodId]);
$period = $periodStmt->fetch(PDO::FETCH_ASSOC);
if (!$period) {
    attendanceJsonError(422, 'The selected academic period is not available.');
}
if ((int) $period['is_finalized'] === 1) {
    attendanceJsonError(409, 'This academic period has been finalised. Attendance can no longer be changed.');
}
$studentClassExpression = $classType === 'arabic'
    ? 'COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id)'
    : 's.class_id';
$records = $input['records'] ?? null;

if (!is_array($records) || count($records) < 1) {
    attendanceJsonError(422, 'There are no student attendance records to save.');
}

$normalized = [];
foreach ($records as $record) {
    if (!is_array($record)) {
        attendanceJsonError(422, 'One of the attendance records is invalid.');
    }

    $studentId = filter_var($record['student_id'] ?? null, FILTER_VALIDATE_INT);
    $status = (string) ($record['status'] ?? '');
    $remarks = trim((string) ($record['remarks'] ?? ''));

    if ($studentId === false || $studentId === null || $studentId < 1) {
        attendanceJsonError(422, 'A student record has an invalid ID.');
    }
    // Empty status means intentionally clear this student's saved mark.
    if ($status !== '' && !attendanceIsValidStatus($status)) {
        attendanceJsonError(422, 'Choose Present, Absent, Late or Excused, or leave the status empty to clear it.');
    }
    if ($status === '') {
        $remarks = '';
    }
    if ((function_exists('mb_strlen') ? mb_strlen($remarks, 'UTF-8') : strlen($remarks)) > 500) {
        attendanceJsonError(422, 'Student remarks must be 500 characters or fewer.');
    }
    if (isset($normalized[(int) $studentId])) {
        attendanceJsonError(422, 'The request contains a duplicate student.');
    }

    $normalized[(int) $studentId] = [
        'status' => $status,
        'remarks' => $remarks === '' ? null : $remarks,
    ];
}

$rosterStmt = $pdo->prepare(
    "SELECT s.id FROM students s
     WHERE {$studentClassExpression} = ? AND s.is_active = 1
     ORDER BY id"
);
$rosterStmt->execute([(int) $classId]);
$rosterIds = array_map('intval', $rosterStmt->fetchAll(PDO::FETCH_COLUMN));
$submittedIds = array_keys($normalized);
sort($rosterIds);
sort($submittedIds);

if ($rosterIds !== $submittedIds) {
    attendanceJsonError(409, 'The class roster has changed or is incomplete. Reload the register before saving.');
}

$markedBy = (int) ($_SESSION['admin_id'] ?? 0);

try {
    $pdo->beginTransaction();
    $periodLock = $pdo->prepare('SELECT is_finalized FROM academic_periods WHERE id = ? FOR UPDATE');
    $periodLock->execute([(int) $periodId]);
    $lockedPeriod = $periodLock->fetch(PDO::FETCH_ASSOC);
    if (!$lockedPeriod || (int) $lockedPeriod['is_finalized'] === 1) {
        $pdo->rollBack();
        attendanceJsonError(409, 'This academic period has been finalised. Attendance can no longer be changed.');
    }
    $saveStmt = $pdo->prepare(
        'INSERT INTO attendance_records
            (attendance_date, academic_period_id, class_type, class_id, student_id, status, remarks, marked_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            academic_period_id = VALUES(academic_period_id),
            status = VALUES(status),
            remarks = VALUES(remarks),
            marked_by = VALUES(marked_by),
            updated_at = CURRENT_TIMESTAMP'
    );
    $clearStmt = $pdo->prepare(
        'DELETE FROM attendance_records
         WHERE attendance_date = ? AND class_type = ? AND class_id = ? AND student_id = ?'
    );
    $savedCount = 0;
    $clearedCount = 0;

    foreach ($normalized as $studentId => $record) {
        if ($record['status'] === '') {
            $clearStmt->execute([$date, $classType, (int) $classId, $studentId]);
            $clearedCount += $clearStmt->rowCount();
            continue;
        }

        $saveStmt->execute([
            $date,
            (int) $periodId,
            $classType,
            (int) $classId,
            $studentId,
            $record['status'],
            $record['remarks'],
            $markedBy,
        ]);
        $savedCount++;
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('MARKAZ attendance save failed: ' . $error->getMessage());
    attendanceJsonError(500, 'Attendance could not be saved. No changes were kept; please try again.');
}

echo json_encode([
    'success' => true,
    'message' => 'Attendance saved successfully.',
    'class' => $class['name'],
    'date' => $date,
    'saved_count' => $savedCount,
    'cleared_count' => $clearedCount,
], JSON_UNESCAPED_UNICODE);
