<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../attendance/_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

requireLogin();
requireOnlineClassAccess();
$pdo = db();

function onlineJson(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function onlineInput(): array
{
    $data = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($data)) onlineJson(['success' => false, 'message' => 'Invalid JSON request.'], 400);
    return $data;
}
function onlineLevel(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM online_class_levels WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$id]);
    $level = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$level) onlineJson(['success' => false, 'message' => 'Choose a valid active Online Class level.'], 404);
    return $level;
}
function onlineName(array $data, string $key, int $max = 150): string
{
    $value = trim((string) ($data[$key] ?? ''));
    if ($value === '' || mb_strlen($value) > $max) onlineJson(['success' => false, 'message' => 'Enter a valid ' . str_replace('_', ' ', $key) . '.'], 422);
    return $value;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $levels = $pdo->query('SELECT * FROM online_class_levels ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    $students = $pdo->query(
        'SELECT s.id, s.admission_number, s.first_name, s.middle_name, s.last_name, s.gender, s.email, s.phone, s.is_active, s.level_id, l.name AS level_name
         FROM online_class_students s JOIN online_class_levels l ON l.id = s.level_id
         ORDER BY l.sort_order, s.last_name, s.first_name'
    )->fetchAll(PDO::FETCH_ASSOC);
    $subjects = $pdo->query(
        'SELECT s.id, s.level_id, s.name, s.sort_order, s.is_active, l.name AS level_name
         FROM online_class_subjects s JOIN online_class_levels l ON l.id = s.level_id
         ORDER BY l.sort_order, s.sort_order, s.name'
    )->fetchAll(PDO::FETCH_ASSOC);
    $term = (string) ($_GET['term'] ?? 'First Term');
    $session = trim((string) ($_GET['session'] ?? date('Y') . '/' . (date('Y') + 1)));
    $levelId = filter_var($_GET['level_id'] ?? null, FILTER_VALIDATE_INT);
    $attendance = [];
    $results = [];
    if ($levelId && $levelId > 0) {
        onlineLevel($pdo, (int) $levelId);
        $date = attendanceValidateDate((string) ($_GET['date'] ?? date('Y-m-d')));
        $stmt = $pdo->prepare(
            'SELECT a.id, a.student_id, a.attendance_date, a.status, a.remarks, s.admission_number, s.first_name, s.last_name
             FROM online_class_attendance a JOIN online_class_students s ON s.id = a.student_id
             WHERE s.level_id = ? AND a.attendance_date = ? ORDER BY s.last_name, s.first_name'
        );
        $stmt->execute([(int) $levelId, $date]);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (in_array($term, ['First Term','Second Term','Third Term'], true) && $session !== '') {
            $stmt = $pdo->prepare(
                'SELECT r.*, s.admission_number, s.first_name, s.middle_name, s.last_name, sub.name AS subject_name
                 FROM online_class_results r
                 JOIN online_class_students s ON s.id = r.student_id
                 JOIN online_class_subjects sub ON sub.id = r.subject_id
                 WHERE s.level_id = ? AND r.academic_session = ? AND r.term = ?
                 ORDER BY s.last_name, s.first_name, sub.sort_order'
            );
            $stmt->execute([(int) $levelId, $session, $term]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    onlineJson(['success' => true, 'levels' => $levels, 'students' => $students, 'subjects' => $subjects, 'attendance' => $attendance, 'results' => $results]);
}

if ($method !== 'POST') onlineJson(['success' => false, 'message' => 'Method not allowed.'], 405);
attendanceRequireSafePost();
$data = onlineInput();
$action = (string) ($data['action'] ?? '');

try {
    if ($action === 'save_level') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $tutor = trim((string) ($data['tutor_name'] ?? ''));
        $taskOne = onlineName($data, 'task_one_name', 100);
        $taskTwo = onlineName($data, 'task_two_name', 100);
        $portion = trim((string) ($data['memorization_portion'] ?? ''));
        $dates = [];
        foreach (['first_term_resumption','second_term_resumption','third_term_resumption'] as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            if ($value !== '' && !DateTime::createFromFormat('!Y-m-d', $value)) onlineJson(['success' => false, 'message' => 'Enter valid resumption dates.'], 422);
            $dates[$key] = $value === '' ? null : $value;
        }
        if ($id) {
            $stmt = $pdo->prepare('UPDATE online_class_levels SET tutor_name=?, task_one_name=?, task_two_name=?, memorization_portion=?, first_term_resumption=?, second_term_resumption=?, third_term_resumption=? WHERE id=?');
            $stmt->execute([$tutor ?: null,$taskOne,$taskTwo,$portion ?: null,$dates['first_term_resumption'],$dates['second_term_resumption'],$dates['third_term_resumption'],$id]);
        } else onlineJson(['success' => false, 'message' => 'Level IDs are system-managed.'], 422);
        onlineJson(['success' => true, 'message' => 'Level settings saved.']);
    }

    if ($action === 'save_subject') {
        $levelId = filter_var($data['level_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$levelId || $levelId < 1) onlineJson(['success'=>false,'message'=>'Choose a level.'],422);
        onlineLevel($pdo,(int)$levelId);
        $name = onlineName($data,'name');
        $id = filter_var($data['id'] ?? null,FILTER_VALIDATE_INT);
        if ($id) {
            $stmt=$pdo->prepare('UPDATE online_class_subjects SET name=? WHERE id=? AND level_id=?');
            $stmt->execute([$name,$id,$levelId]);
        } else {
            $stmt=$pdo->prepare('INSERT INTO online_class_subjects (level_id,name,sort_order) VALUES (?,?,(SELECT COALESCE(MAX(sort_order),0)+1 FROM (SELECT sort_order FROM online_class_subjects WHERE level_id=?) x))');
            $stmt->execute([$levelId,$name,$levelId]);
        }
        onlineJson(['success'=>true,'message'=>'Subject saved.']);
    }

    if ($action === 'save_student') {
        $levelId=filter_var($data['level_id'] ?? null,FILTER_VALIDATE_INT);
        if(!$levelId || $levelId<1) onlineJson(['success'=>false,'message'=>'Choose a level.'],422);
        onlineLevel($pdo,(int)$levelId);
        $first=onlineName($data,'first_name',100); $last=onlineName($data,'last_name',100);
        $middle=trim((string)($data['middle_name']??'')); $gender=$data['gender']??null;
        if(!in_array($gender,['Male','Female',null,''],true)) onlineJson(['success'=>false,'message'=>'Choose a valid gender.'],422);
        $email=trim((string)($data['email']??'')); if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) onlineJson(['success'=>false,'message'=>'Enter a valid email.'],422);
        $id=filter_var($data['id']??null,FILTER_VALIDATE_INT);
        $password=(string)($data['password']??'');
        if(!$id && (strlen($password)<8 || !preg_match('/[A-Z]/',$password) || !preg_match('/[a-z]/',$password) || !preg_match('/[0-9]/',$password) || !preg_match('/[^A-Za-z0-9]/',$password))) onlineJson(['success'=>false,'message'=>'Set a password with at least 8 characters, uppercase, lowercase, number and symbol.'],422);
        if($id) {
            $stmt=$pdo->prepare('UPDATE online_class_students SET first_name=?,middle_name=?,last_name=?,gender=?,level_id=?,email=?,phone=?'.($password!==''?',password_hash=?':'').' WHERE id=?');
            $params=[$first,$middle?:null,$last,$gender?:null,$levelId,$email?:null,trim((string)($data['phone']??''))?:null];
            if($password!=='') $params[]=password_hash($password,PASSWORD_DEFAULT);
            $params[]=$id; $stmt->execute($params);
            onlineJson(['success'=>true,'message'=>'Online student updated.']);
        }
        $admission='MOC-'.date('y').'-'.strtoupper(bin2hex(random_bytes(4)));
        $stmt=$pdo->prepare('INSERT INTO online_class_students (admission_number,first_name,middle_name,last_name,gender,level_id,email,phone,password_hash) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$admission,$first,$middle?:null,$last,$gender?:null,$levelId,$email?:null,trim((string)($data['phone']??''))?:null,password_hash($password,PASSWORD_DEFAULT)]);
        onlineJson(['success'=>true,'message'=>'Online student created.','admission_number'=>$admission]);
    }

    if ($action === 'save_attendance') {
        $date=attendanceValidateDate((string)($data['date']??''));
        $levelId=filter_var($data['level_id']??null,FILTER_VALIDATE_INT);
        if(!$levelId || $levelId<1) onlineJson(['success'=>false,'message'=>'Choose a level.'],422);
        onlineLevel($pdo,(int)$levelId);
        $records=$data['records']??null;
        if(!is_array($records)||!$records) onlineJson(['success'=>false,'message'=>'No attendance records were submitted.'],422);
        $rosterStmt=$pdo->prepare('SELECT id FROM online_class_students WHERE level_id=? AND is_active=1 ORDER BY id');
        $rosterStmt->execute([$levelId]); $roster=array_map('intval',$rosterStmt->fetchAll(PDO::FETCH_COLUMN));
        $normalized=[];
        foreach($records as $record){
            $sid=filter_var($record['student_id']??null,FILTER_VALIDATE_INT); $status=(string)($record['status']??'');
            if(!$sid || !in_array($status,['present','absent','late','excused'],true) || isset($normalized[$sid])) onlineJson(['success'=>false,'message'=>'Invalid or duplicate attendance entry.'],422);
            $normalized[$sid]=$status;
        }
        $submitted=array_keys($normalized); sort($submitted); sort($roster);
        if($submitted!==$roster) onlineJson(['success'=>false,'message'=>'The level roster changed. Reload attendance before saving.'],409);
        $pdo->beginTransaction();
        $stmt=$pdo->prepare('INSERT INTO online_class_attendance (student_id,attendance_date,status,remarks,marked_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),remarks=VALUES(remarks),marked_by=VALUES(marked_by)');
        foreach($normalized as $sid=>$status) $stmt->execute([$sid,$date,$status,null,(int)$_SESSION['admin_id']]);
        $pdo->commit();
        onlineJson(['success'=>true,'message'=>'Online attendance saved.']);
    }

    if ($action === 'save_result') {
        $studentId=filter_var($data['student_id']??null,FILTER_VALIDATE_INT);
        $subjectId=filter_var($data['subject_id']??null,FILTER_VALIDATE_INT);
        $session=trim((string)($data['academic_session']??'')); $term=(string)($data['term']??'');
        if(!$studentId||!$subjectId||$session===''||mb_strlen($session)>30||!in_array($term,['First Term','Second Term','Third Term'],true)) onlineJson(['success'=>false,'message'=>'Choose a valid student, subject, session and term.'],422);
        $ownership=$pdo->prepare('SELECT s.level_id, sub.id FROM online_class_students s JOIN online_class_subjects sub ON sub.level_id=s.level_id AND sub.id=? WHERE s.id=? AND s.is_active=1');
        $ownership->execute([$subjectId,$studentId]);
        if(!$ownership->fetch()) onlineJson(['success'=>false,'message'=>'That subject is not assigned to this Online Class student.'],422);
        $scores=[];
        foreach(['attendance_score'=>20,'test_score'=>20,'exam_score'=>60] as $key=>$max){
            $value=$data[$key]??null;
            if($value===''||$value===null){$scores[$key]=null;continue;}
            if(!is_numeric($value)||(float)$value<0||(float)$value>$max) onlineJson(['success'=>false,'message'=>ucfirst(str_replace('_',' ',$key)).' must be between 0 and '.$max.'.'],422);
            $scores[$key]=round((float)$value,2);
        }
        $teacher=trim((string)($data['teacher_remark']??'')); $director=trim((string)($data['director_remark']??''));
        $staff=(int)$_SESSION['admin_id'];
        $stmt=$pdo->prepare('INSERT INTO online_class_results (student_id,subject_id,academic_session,term,attendance_score,test_score,exam_score,teacher_remark,director_remark,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE attendance_score=VALUES(attendance_score),test_score=VALUES(test_score),exam_score=VALUES(exam_score),teacher_remark=VALUES(teacher_remark),director_remark=VALUES(director_remark),updated_by=VALUES(updated_by)');
        $stmt->execute([$studentId,$subjectId,$session,$term,$scores['attendance_score'],$scores['test_score'],$scores['exam_score'],$teacher?:null,$director?:null,$staff,$staff]);
        onlineJson(['success'=>true,'message'=>'Online Class result saved.']);
    }

    if ($action === 'release_results') {
        $levelId=filter_var($data['level_id']??null,FILTER_VALIDATE_INT);
        $session=trim((string)($data['academic_session']??'')); $term=(string)($data['term']??'');
        if(!$levelId||$levelId<1||$session===''||!in_array($term,['First Term','Second Term','Third Term'],true)) onlineJson(['success'=>false,'message'=>'Choose a valid level, session and term.'],422);
        onlineLevel($pdo,(int)$levelId);
        $released=filter_var($data['is_released']??false,FILTER_VALIDATE_BOOLEAN);
        $stmt=$pdo->prepare('INSERT INTO online_class_result_releases (level_id,academic_session,term,is_released,released_by,released_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE is_released=VALUES(is_released),released_by=VALUES(released_by),released_at=VALUES(released_at)');
        $stmt->execute([$levelId,$session,$term,$released?1:0,$released?(int)$_SESSION['admin_id']:null,$released?date('Y-m-d H:i:s'):null]);
        onlineJson(['success'=>true,'message'=>$released?'Online results released.':'Online results are now hidden from students.']);
    }

    onlineJson(['success'=>false,'message'=>'Unknown Online Class action.'],400);
} catch (PDOException $error) {
    if($pdo->inTransaction()) $pdo->rollBack();
    error_log('Online Class workspace error: '.$error->getMessage());
    onlineJson(['success'=>false,'message'=>'The Online Class change could not be saved. Check for duplicate names or records.'],409);
} catch (Throwable $error) {
    if($pdo->inTransaction()) $pdo->rollBack();
    error_log('Online Class workspace error: '.$error->getMessage());
    onlineJson(['success'=>false,'message'=>'The Online Class request could not be completed.'],500);
}
