<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/helpers/online-class.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../attendance/_common.php';

onlineClassStaffAccess();
$pdo = db();
$terms = ['First Term', 'Second Term', 'Third Term'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');
$validLevel = static function ($value) use ($pdo): int {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if (!$id || !$pdo->query('SELECT 1 FROM online_class_levels WHERE id=' . (int) $id . ' LIMIT 1')->fetchColumn()) {
        onlineClassJsonError(422, 'Choose a valid Online Class level.');
    }
    return (int) $id;
};
$archiveJson = static function (array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};
if ($method === 'GET' && $action === 'list') {
    requireSeniorAdmin();
    $stmt = $pdo->query("SELECT a.id,a.archive_type,a.destination,a.level_id,l.name AS level_name,a.academic_session,a.term,a.record_count,a.coverage_start,a.coverage_end,a.is_released,a.payload_sha256,a.created_at,ad.name AS created_by_name FROM online_class_archives a JOIN online_class_levels l ON l.id=a.level_id JOIN admins ad ON ad.id=a.created_by WHERE a.destination='online' ORDER BY a.created_at DESC,a.id DESC");
    $archiveJson(['success' => true, 'archives' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
if ($method !== 'POST') {
    http_response_code(405);
    $archiveJson(['success' => false, 'message' => 'Use GET or POST.'], 405);
}
attendanceRequireSafePost();
requireSeniorAdmin();
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) $archiveJson(['success' => false, 'message' => 'Invalid archive request.'], 400);
$action = (string) ($input['action'] ?? 'backup');
$levelId = $validLevel($input['level_id'] ?? 0);
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$password = (string) ($input['password'] ?? '');
if ($password === '' || strlen($password) > 4096) $archiveJson(['success' => false, 'message' => 'Enter your administrator password to continue.'], 422);
$verify = $pdo->prepare('SELECT password_hash FROM admins WHERE id=? AND is_active=1 AND is_frozen=0 LIMIT 1');
$verify->execute([$adminId]);
$hash = (string) $verify->fetchColumn();
if ($hash === '' || !password_verify($password, $hash)) $archiveJson(['success' => false, 'message' => 'Administrator password is incorrect.'], 403);
$encryptBackup = static function (string $compressed, string $password): array {
    if (!function_exists('openssl_encrypt')) throw new RuntimeException('Server encryption support is unavailable.');
    $salt = random_bytes(16);
    $nonce = random_bytes(12);
    $key = hash_pbkdf2('sha256', $password, $salt, 210000, 32, true);
    $tag = '';
    $cipher = openssl_encrypt($compressed, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'MARKAZ-ONLINE-CLASS-ARCHIVE-V1');
    if ($cipher === false || strlen($tag) !== 16) throw new RuntimeException('Unable to encrypt the backup file.');
    $file = "MOCBKP1" . $salt . $nonce . $tag . $cipher;
    return ['payload'=>base64_encode($file),'sha256'=>hash('sha256',$file)];
};
if ($action === 'download') {
    $archiveId = filter_var($input['archive_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$archiveId) $archiveJson(['success'=>false,'message'=>'Choose a valid online backup.'],422);
    $stmt=$pdo->prepare("SELECT payload,payload_sha256,archive_type,level_id,academic_session,term FROM online_class_archives WHERE id=? AND destination='online' LIMIT 1");
    $stmt->execute([$archiveId]);
    $saved=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$saved || (int)$saved['level_id'] !== $levelId || !hash_equals((string)$saved['payload_sha256'],hash('sha256',(string)$saved['payload']))) $archiveJson(['success'=>false,'message'=>'The selected backup is unavailable or failed its integrity check.'],404);
    $encrypted=$encryptBackup((string)$saved['payload'],$password);
    $archiveJson(['success'=>true,'message'=>'Encrypted backup file is ready.','filename'=>'markaz-online-'.$saved['archive_type'].'-archive-'.$archiveId.'.mocbackup','mime'=>'application/vnd.markaz.online-class-backup','payload'=>$encrypted['payload'],'sha256'=>$encrypted['sha256']]);
}
if (in_array($action, ['clear_attendance', 'clear_results'], true)) {
    if (($input['confirm_clear'] ?? false) !== true) $archiveJson(['success'=>false,'message'=>'Explicit clear confirmation is required.'],422);
    $typeToClear = $action === 'clear_attendance' ? 'attendance' : 'results';
    $sessionToClear = trim((string) ($input['academic_session'] ?? ''));
    $termToClear = (string) ($input['term'] ?? '');
    if ($typeToClear === 'results' && (!preg_match('/^\\d{4}\\/\\d{4}$/', $sessionToClear) || !in_array($termToClear, $terms, true))) {
        $archiveJson(['success'=>false,'message'=>'Choose the exact academic session and term to clear.'],422);
    }
    $pdo->beginTransaction();
    try {
        if ($typeToClear === 'attendance') {
            $delete = $pdo->prepare('DELETE a FROM online_class_attendance a JOIN online_class_students s ON s.id=a.student_id WHERE s.level_id=?');
            $delete->execute([$levelId]);
        } else {
            $delete = $pdo->prepare('DELETE r FROM online_class_results r JOIN online_class_students s ON s.id=r.student_id WHERE s.level_id=? AND r.academic_session=? AND r.term=?');
            $delete->execute([$levelId,$sessionToClear,$termToClear]);
            $pdo->prepare('DELETE FROM online_class_result_releases WHERE level_id=? AND academic_session=? AND term=? AND is_released=1')->execute([$levelId,$sessionToClear,$termToClear]);
        }
        $affected = $delete->rowCount();
        $pdo->commit();
        $archiveJson(['success'=>true,'message'=>$typeToClear === 'attendance' ? 'Attendance records for the selected level have been cleared. Existing backups remain untouched.' : 'Only the selected level, academic session and term results have been cleared. Existing archives remain available.','deleted_count'=>$affected]);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Online Class clear failed: '.$error->getMessage());
        $archiveJson(['success'=>false,'message'=>'The clear operation failed. No partial changes were kept.'],500);
    }
}
if ($action !== 'backup') $archiveJson(['success'=>false,'message'=>'Unknown archive action.'],422);
$type = (string) ($input['archive_type'] ?? '');
$destination = (string) ($input['destination'] ?? 'online');
if (!in_array($type, ['attendance', 'results'], true) || !in_array($destination, ['online', 'device'], true)) $archiveJson(['success' => false, 'message' => 'Choose a valid backup type and destination.'], 422);
$session = trim((string) ($input['academic_session'] ?? ''));
$term = (string) ($input['term'] ?? '');
if ($type === 'results' && (!preg_match('/^\d{4}\/\d{4}$/', $session) || !in_array($term, $terms, true))) $archiveJson(['success' => false, 'message' => 'Choose a valid academic session and term.'], 422);
if ($type === 'attendance') { $session = null; $term = null; }
$pdo->beginTransaction();
try {
    if ($type === 'attendance') {
        $stmt = $pdo->prepare('SELECT a.id,a.student_id,s.admission_number,s.first_name,s.middle_name,s.last_name,s.section,a.attendance_date,a.status,a.remarks,a.marked_by,a.created_at,a.updated_at FROM online_class_attendance a JOIN online_class_students s ON s.id=a.student_id WHERE s.level_id=? ORDER BY a.attendance_date,s.last_name,s.first_name');
        $stmt->execute([$levelId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $payloadData = ['format'=>'markaz-online-class-archive-v1','type'=>'attendance','level_id'=>$levelId,'created_at'=>date(DATE_ATOM),'records'=>$rows];
        $released = 0;
        $coverageStart = $rows ? min(array_column($rows, 'attendance_date')) : null;
        $coverageEnd = $rows ? max(array_column($rows, 'attendance_date')) : null;
    } else {
        $students = $pdo->prepare('SELECT id,admission_number,first_name,middle_name,last_name,section,level_id FROM online_class_students WHERE level_id=? ORDER BY last_name,first_name');
        $students->execute([$levelId]);
        $results = $pdo->prepare('SELECT r.id,r.student_id,r.subject_id,s.admission_number,s.first_name,s.middle_name,s.last_name,sub.name AS subject_name,r.attendance_score,r.test_score,r.exam_score,r.teacher_remark,r.director_remark,r.created_at,r.updated_at FROM online_class_results r JOIN online_class_students s ON s.id=r.student_id JOIN online_class_subjects sub ON sub.id=r.subject_id WHERE s.level_id=? AND r.academic_session=? AND r.term=? ORDER BY s.last_name,s.first_name,sub.sort_order,sub.name');
        $results->execute([$levelId,$session,$term]);
        $releaseStmt = $pdo->prepare('SELECT is_released FROM online_class_result_releases WHERE level_id=? AND academic_session=? AND term=? LIMIT 1');
        $releaseStmt->execute([$levelId,$session,$term]);
        $released = (int) $releaseStmt->fetchColumn();
        $coverageStart = null;
        $coverageEnd = null;
        $payloadData = ['format'=>'markaz-online-class-archive-v1','type'=>'results','level_id'=>$levelId,'academic_session'=>$session,'term'=>$term,'is_released'=>$released,'created_at'=>date(DATE_ATOM),'students'=>$students->fetchAll(PDO::FETCH_ASSOC),'results'=>$results->fetchAll(PDO::FETCH_ASSOC)];
    }
    $raw = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    $compressed = gzencode($raw, 9);
    if ($compressed === false) throw new RuntimeException('Unable to compress the archive.');
    $sha = hash('sha256', $compressed);
    if ($destination === 'online') {
        $insert = $pdo->prepare('INSERT INTO online_class_archives (archive_type,destination,level_id,academic_session,term,record_count,coverage_start,coverage_end,is_released,payload,payload_sha256,created_by) VALUES (?,\'online\',?,?,?,?,?,?,?,?,?,?)');
        $insert->execute([$type,$levelId,$session,$term,count($type === 'attendance' ? $payloadData['records'] : $payloadData['results']),$coverageStart,$coverageEnd,$released,$compressed,$sha,$adminId]);
        $archiveId = (int) $pdo->lastInsertId();
        $pdo->commit();
        $archiveJson(['success'=>true,'message'=>'Protected online backup saved. It is now listed in Backup Records.','archive_id'=>$archiveId,'record_count'=>count($type === 'attendance' ? $payloadData['records'] : $payloadData['results'])],201);
    }
    $encrypted=$encryptBackup($compressed,$password);
    $pdo->commit();
    $archiveJson(['success'=>true,'message'=>'Encrypted device backup is ready to download. Keep the password safe; the file is not stored online.','filename'=>'markaz-online-'.$type.'-level-'.$levelId.'-'.date('Ymd-His').'.mocbackup','mime'=>'application/vnd.markaz.online-class-backup','sha256'=>$encrypted['sha256'],'payload'=>$encrypted['payload'],'record_count'=>count($type === 'attendance' ? $payloadData['records'] : $payloadData['results'])]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Online Class archive failed: ' . $error->getMessage());
    $archiveJson(['success'=>false,'message'=>'The backup could not be completed. No active records were changed.'],500);
}
