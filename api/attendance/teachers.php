<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../../app/helpers/finance.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$pdo = db();
attendanceRequireTeacherAttendanceAccess();
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $date = attendanceValidateDate((string)($_GET['date'] ?? date('Y-m-d')));
    $stmt = $pdo->prepare(
        "SELECT s.id AS finance_staff_id,s.staff_number,s.full_name,s.job_title,
                p.expected_arrival,p.attendance_mode,p.lateness_rate_per_minute,
                a.id AS attendance_id,a.actual_arrival,a.status,a.late_minutes,
                a.lateness_deduction,a.notes
         FROM finance_staff s
         INNER JOIN staff_salary_profiles p ON p.finance_staff_id=s.id
            AND p.effective_from<=? AND (p.effective_until IS NULL OR p.effective_until>=?)
         LEFT JOIN staff_attendance a ON a.finance_staff_id=s.id AND a.attendance_date=?
         WHERE s.is_active=1
         ORDER BY s.full_name,s.id"
    );
    $stmt->execute([$date,$date,$date]);
    echo json_encode(['success'=>true,'date'=>$date,'staff'=>$stmt->fetchAll(PDO::FETCH_ASSOC)],JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    attendanceJsonError(405, 'Method not allowed.');
}

attendanceRequireSafePost();
$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input) || ($input['action'] ?? '') !== 'save_register' || !is_array($input['records'] ?? null)) {
    attendanceJsonError(400, 'Send a valid teacher attendance register.');
}
$date = attendanceValidateDate((string)($input['attendance_date'] ?? date('Y-m-d')));
$records = $input['records'];
if (count($records) > 300) attendanceJsonError(422, 'The attendance register is too large.');

$profileQuery = $pdo->prepare(
    'SELECT expected_arrival,attendance_mode,lateness_rate_per_minute
     FROM staff_salary_profiles
     WHERE finance_staff_id=? AND effective_from<=?
       AND (effective_until IS NULL OR effective_until>=?)
     ORDER BY effective_from DESC,id DESC LIMIT 1'
);
$staffQuery = $pdo->prepare('SELECT id FROM finance_staff WHERE id=? AND is_active=1');
$save = $pdo->prepare(
    'INSERT INTO staff_attendance
        (finance_staff_id,attendance_date,expected_arrival,attendance_mode,actual_arrival,status,late_minutes,lateness_deduction,notes,recorded_by,approved_by,approved_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
     ON DUPLICATE KEY UPDATE expected_arrival=VALUES(expected_arrival),attendance_mode=VALUES(attendance_mode),
        actual_arrival=VALUES(actual_arrival),status=VALUES(status),
        late_minutes=VALUES(late_minutes),lateness_deduction=VALUES(lateness_deduction),
        notes=VALUES(notes),recorded_by=VALUES(recorded_by),approved_by=VALUES(approved_by),approved_at=NOW()'
);

$actor = (int)($_SESSION['admin_id'] ?? 0);
$saved = 0;
$seenStaffIds = [];
$pdo->beginTransaction();
try {
    foreach ($records as $record) {
        if (!is_array($record)) throw new InvalidArgumentException('An attendance row is invalid.');
        $staffId = filter_var($record['finance_staff_id'] ?? null,FILTER_VALIDATE_INT);
        $status = (string)($record['status'] ?? '');
        $actual = trim((string)($record['actual_arrival'] ?? ''));
        $notes = trim((string)($record['notes'] ?? ''));
        if (!$staffId || $staffId < 1 || isset($seenStaffIds[$staffId]) || !in_array($status,['present','absent','approved_leave','excused'],true) || mb_strlen($notes)>500 || (in_array($status,['approved_leave','excused'],true) && $notes==='')) {
            throw new InvalidArgumentException('A staff attendance row is invalid. Add a reason for approved leave or an excused absence.');
        }
        $seenStaffIds[$staffId] = true;
        $staffQuery->execute([$staffId]);
        if (!$staffQuery->fetchColumn()) throw new InvalidArgumentException('An inactive or unknown Finance staff member was selected.');
        $profileQuery->execute([$staffId,$date,$date]);
        $profile = $profileQuery->fetch(PDO::FETCH_ASSOC);
        if (!$profile) throw new InvalidArgumentException('Every selected staff member must have an active salary and arrival-time profile for this date.');
        $mode = (string)($profile['attendance_mode'] ?? 'time_based');
        if (!in_array($mode, ['time_based','attendance_only'], true)) {
            throw new InvalidArgumentException('The staff attendance policy is invalid. Update the salary profile first.');
        }
        $actualValue = null;
        $lateMinutes = 0;
        $deduction = '0.00';
        if ($status === 'present' && $mode === 'time_based') {
            if (!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/',$actual)) {
                throw new InvalidArgumentException('Enter a valid arrival time for every present time-based staff member.');
            }
            $actualValue = strlen($actual) === 5 ? $actual . ':00' : $actual;
            $lateMinutes = financeCalculateLateMinutes((string)$profile['expected_arrival'],$actualValue);
            $rate = financeToCents((string)$profile['lateness_rate_per_minute']);
            $deduction = financeFromCents($lateMinutes * $rate);
        }
        // Excused/approved leave and attendance-only staff never incur lateness deductions.
        $expectedArrival = $mode === 'time_based' ? $profile['expected_arrival'] : null;
        $save->execute([$staffId,$date,$expectedArrival,$mode,$actualValue,$status,$lateMinutes,$deduction,$notes?:null,$actor,$actor]);
        $saved++;
    }
    $audit = $pdo->prepare(
        'INSERT INTO finance_audit_logs(actor_id,action,entity_type,entity_id,details_json,ip_address)
         VALUES(?,?,?,?,?,?)'
    );
    $audit->execute([$actor,'teacher_attendance_register_saved','teacher_attendance',null,
        json_encode(['date'=>$date,'records_saved'=>$saved],JSON_UNESCAPED_UNICODE),
        substr((string)($_SERVER['REMOTE_ADDR']??''),0,45) ?: null]);
    $pdo->commit();
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    attendanceJsonError(422,$error->getMessage());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('MARKAZ teacher attendance: '.$error->getMessage());
    attendanceJsonError(500,'Teacher attendance could not be saved. Check the server log.');
}

echo json_encode(['success'=>true,'saved'=>$saved,'message'=>'Teacher attendance saved and approved for payroll calculation.'],JSON_UNESCAPED_UNICODE);
