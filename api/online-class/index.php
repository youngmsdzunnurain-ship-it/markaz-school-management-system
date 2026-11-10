<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/online-class.php';
onlineClassStaffAccess();
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string)($_GET['action'] ?? '');
$terms = ['First Term','Second Term','Third Term'];
$levelStmt = $pdo->query('SELECT * FROM online_class_levels ORDER BY sort_order, id');
$levels = $levelStmt->fetchAll(PDO::FETCH_ASSOC);
$levelIds = array_map(static fn($row)=>(int)$row['id'], $levels);
$validLevel = static function($value) use ($levelIds): int {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if (!$id || !in_array((int)$id, $levelIds, true)) onlineClassJsonError(422,'Choose a valid Online Class level.');
    return (int)$id;
};
if ($method === 'GET') {
    if ($action === 'footer_settings') {
        $stmt=$pdo->prepare('SELECT setting_key,setting_value FROM settings WHERE setting_key IN (?,?,?,?,?)');
        $stmt->execute(['class_teacher_signature','director_signature','class_teacher_signature_image','director_signature_image','result_signature_date']);
        $settings=[]; foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $settings[$row['setting_key']]=$row['setting_value']??'';
        onlineClassJson(['success'=>true,'settings'=>$settings]);
    }
    if ($action === 'bootstrap') {
        $subjects = $pdo->query('SELECT s.*, l.name AS level_name FROM online_class_subjects s JOIN online_class_levels l ON l.id=s.level_id ORDER BY l.sort_order,s.sort_order,s.name')->fetchAll(PDO::FETCH_ASSOC);
        $counts = $pdo->query('SELECT level_id, COUNT(*) AS total FROM online_class_students WHERE is_active=1 GROUP BY level_id')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($levels as &$level) {
            $level['id']=(int)$level['id']; $level['student_count']=(int)($counts[$level['id']] ?? 0);
        } unset($level);
        $signatureStmt=$pdo->query(\"SELECT setting_key,setting_value FROM markaz_app_settings WHERE setting_key IN ('result_footer_class_teacher_image','result_footer_director_image','result_footer_signature_date')\");\n        $signatureRows=$signatureStmt->fetchAll(PDO::FETCH_KEY_PAIR);\n        onlineClassJson(['success'=>true,'levels'=>$levels,'subjects'=>$subjects,'terms'=>$terms,'signatures'=>['teacher'=>$signatureRows['result_footer_class_teacher_image']??'','director'=>$signatureRows['result_footer_director_image']??'','date'=>$signatureRows['result_footer_signature_date']??'']]);
    }
    if ($action === 'students') {
        $levelId = isset($_GET['level_id']) && $_GET['level_id']!=='' ? $validLevel($_GET['level_id']) : null;
        $sql='SELECT s.id,s.admission_number,s.first_name,s.middle_name,s.last_name,s.gender,s.level_id,l.name AS level_name,s.email,s.phone,s.is_active,s.created_at FROM online_class_students s JOIN online_class_levels l ON l.id=s.level_id';
        if ($levelId) { $stmt=$pdo->prepare($sql.' WHERE s.level_id=? ORDER BY s.last_name,s.first_name'); $stmt->execute([$levelId]); }
        else { $stmt=$pdo->query($sql.' ORDER BY l.sort_order,s.last_name,s.first_name'); }
        onlineClassJson(['success'=>true,'students'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if ($action === 'attendance') {
        $date=(string)($_GET['date'] ?? date('Y-m-d'));
        $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$dt || $dt->format('Y-m-d')!==$date) onlineClassJsonError(422,'Choose a valid attendance date.');
        $levelId=isset($_GET['level_id'])&&$_GET['level_id']!==''?$validLevel($_GET['level_id']):null;
        $sql='SELECT s.id,s.admission_number,s.first_name,s.last_name,l.name AS level_name,a.status,a.remarks FROM online_class_students s JOIN online_class_levels l ON l.id=s.level_id LEFT JOIN online_class_attendance a ON a.student_id=s.id AND a.attendance_date=? WHERE s.is_active=1';
        if($levelId){$stmt=$pdo->prepare($sql.' AND s.level_id=? ORDER BY l.sort_order,s.last_name,s.first_name');$stmt->execute([$date,$levelId]);}
        else{$stmt=$pdo->prepare($sql.' ORDER BY l.sort_order,s.last_name,s.first_name');$stmt->execute([$date]);}
        onlineClassJson(['success'=>true,'date'=>$date,'students'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if ($action === 'results') {
        $levelId=$validLevel($_GET['level_id'] ?? 0);
        $session=trim((string)($_GET['academic_session'] ?? ''));
        $term=(string)($_GET['term'] ?? 'First Term');
        if(!preg_match('/^\d{4}\/\d{4}$/',$session)||!in_array($term,$terms,true)) onlineClassJsonError(422,'Choose a valid academic session and term.');
        $stmt=$pdo->prepare('SELECT s.id,s.id AS student_id,s.admission_number,s.first_name,s.middle_name,s.last_name,sub.id AS subject_id,sub.name AS subject_name,r.attendance_score,r.test_score,r.exam_score,r.teacher_remark,r.director_remark FROM online_class_students s JOIN online_class_subjects sub ON sub.level_id=s.level_id AND sub.is_active=1 LEFT JOIN online_class_results r ON r.student_id=s.id AND r.subject_id=sub.id AND r.academic_session=? AND r.term=? WHERE s.level_id=? AND s.is_active=1 ORDER BY s.last_name,s.first_name,sub.sort_order,sub.name');
        $stmt->execute([$session,$term,$levelId]);
        $release=$pdo->prepare('SELECT is_released FROM online_class_result_releases WHERE level_id=? AND academic_session=? AND term=?');
        $release->execute([$levelId,$session,$term]);
        onlineClassJson(['success'=>true,'results'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'is_released'=>(bool)$release->fetchColumn(),'level_id'=>$levelId,'academic_session'=>$session,'term'=>$term]);
    }
    onlineClassJsonError(404,'Unknown Online Class request.');
}
if ($method !== 'POST') onlineClassJsonError(405,'Use GET or POST.');
$data=onlineClassInput();
$staffId=(int)($_SESSION['admin_id'] ?? 0);
if($action==='save_level'){
    $id=$validLevel($data['id']??0);
    $tutor=trim((string)($data['tutor_name']??''));
    $task1=trim((string)($data['task_one_name']??'Task One'));
    $task2=trim((string)($data['task_two_name']??'Task Two'));
    $portion=trim((string)($data['memorization_portion']??''));
    foreach([$tutor,$task1,$task2,$portion] as $v) if(strlen($v)>255) onlineClassJsonError(422,'A setting is too long.');
    $dates=[];
    foreach(['first_term_resumption','second_term_resumption','third_term_resumption'] as $key){
        $v=trim((string)($data[$key]??''));
        if($v!==''){ $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v); if(!$d||$d->format('Y-m-d')!==$v) onlineClassJsonError(422,'Enter valid resumption dates.'); }
        $dates[]=$v===''?null:$v;
    }
    $stmt=$pdo->prepare('UPDATE online_class_levels SET tutor_name=?,task_one_name=?,task_two_name=?,memorization_portion=?,first_term_resumption=?,second_term_resumption=?,third_term_resumption=? WHERE id=?');
    $stmt->execute([$tutor?:null,$task1?:'Task One',$task2?:'Task Two',$portion?:null,...$dates,$id]);
    onlineClassJson(['success'=>true,'message'=>'Level settings saved.']);
}
if($action==='save_subject'){
    $id=filter_var($data['id']??null,FILTER_VALIDATE_INT);
    $levelId=$validLevel($data['level_id']??0);
    $name=trim((string)($data['name']??''));
    if($name===''||mb_strlen($name)>150) onlineClassJsonError(422,'Enter a subject name (maximum 150 characters).');
    $sort=max(0,(int)($data['sort_order']??0));
    if($id){$stmt=$pdo->prepare('UPDATE online_class_subjects SET level_id=?,name=?,sort_order=? WHERE id=?');$stmt->execute([$levelId,$name,$sort,$id]);}
    else{$stmt=$pdo->prepare('INSERT INTO online_class_subjects(level_id,name,sort_order) VALUES(?,?,?)');$stmt->execute([$levelId,$name,$sort]);}
    onlineClassJson(['success'=>true,'message'=>'Subject saved.']);
}
if($action==='save_student'){
    $id=filter_var($data['id']??null,FILTER_VALIDATE_INT);
    $levelId=$validLevel($data['level_id']??0);
    $first=trim((string)($data['first_name']??''));$middle=trim((string)($data['middle_name']??''));$last=trim((string)($data['last_name']??''));
    $gender=(string)($data['gender']??'');
    if($first===''||$last===''||mb_strlen($first)>100||mb_strlen($last)>100||($gender!==''&&!in_array($gender,['Male','Female'],true))) onlineClassJsonError(422,'Enter a valid student name and gender.');
    $email=trim((string)($data['email']??''));$phone=trim((string)($data['phone']??''));
    if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)) onlineClassJsonError(422,'Enter a valid email address.');
    if($id){
        $stmt=$pdo->prepare('UPDATE online_class_students SET first_name=?,middle_name=?,last_name=?,gender=?,level_id=?,email=?,phone=?,is_active=? WHERE id=?');
        $stmt->execute([$first,$middle?:null,$last,$gender?:null,$levelId,$email?:null,$phone?:null,!empty($data['is_active'])?1:0,$id]);
        onlineClassJson(['success'=>true,'message'=>'Online student updated.']);
    }
    $password=bin2hex(random_bytes(5));
    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare("INSERT INTO online_class_students(admission_number,first_name,middle_name,last_name,gender,level_id,email,phone,password_hash) VALUES(?,?,?,?,?,?,?,?,?)");
        $stmt->execute(['PENDING-'.bin2hex(random_bytes(8)),$first,$middle?:null,$last,$gender?:null,$levelId,$email?:null,$phone?:null,password_hash($password,PASSWORD_DEFAULT)]);
        $newId=(int)$pdo->lastInsertId();
        $admission='MOC-'.date('Y').'-'.str_pad((string)$newId,6,'0',STR_PAD_LEFT);
        $pdo->prepare('UPDATE online_class_students SET admission_number=? WHERE id=?')->execute([$admission,$newId]);
        $pdo->commit();
        onlineClassJson(['success'=>true,'message'=>'Online student created. Give the student the admission number and temporary password below.','student'=>['id'=>$newId,'admission_number'=>$admission],'temporary_password'=>$password],201);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
if($action==='save_attendance'){
    $date=(string)($data['date']??'');$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$dt||$dt->format('Y-m-d')!==$date) onlineClassJsonError(422,'Choose a valid attendance date.');
    $records=$data['records']??null;if(!is_array($records)||!$records) onlineClassJsonError(422,'No attendance records were submitted.');
    $ids=array_map('intval',array_keys($records));$placeholders=implode(',',array_fill(0,count($ids),'?'));
    $check=$pdo->prepare("SELECT id FROM online_class_students WHERE is_active=1 AND id IN ($placeholders)");$check->execute($ids);
    if(count($check->fetchAll(PDO::FETCH_COLUMN))!==count(array_unique($ids))) onlineClassJsonError(422,'An attendance student is invalid.');
    $save=$pdo->prepare('INSERT INTO online_class_attendance(student_id,attendance_date,status,remarks,marked_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),remarks=VALUES(remarks),marked_by=VALUES(marked_by)');
    $pdo->beginTransaction();try{foreach($records as $studentId=>$record){$status=(string)($record['status']??'');if(!in_array($status,['present','absent','late','excused'],true)) onlineClassJsonError(422,'Choose a valid attendance status for every student.');$save->execute([(int)$studentId,$date,$status,trim((string)($record['remarks']??''))?:null,$staffId]);}$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    onlineClassJson(['success'=>true,'message'=>'Online Class attendance saved.']);
}
if($action==='save_result'){
    $studentId=filter_var($data['student_id']??null,FILTER_VALIDATE_INT);$subjectId=filter_var($data['subject_id']??null,FILTER_VALIDATE_INT);
    $session=trim((string)($data['academic_session']??''));$term=(string)($data['term']??'');
    if(!$studentId||!$subjectId||!preg_match('/^\d{4}\/\d{4}$/',$session)||!in_array($term,$terms,true)) onlineClassJsonError(422,'Choose a valid student, subject, session and term.');
    $scope=$pdo->prepare('SELECT s.id FROM online_class_students s JOIN online_class_subjects sub ON sub.level_id=s.level_id WHERE s.id=? AND sub.id=? AND s.is_active=1 AND sub.is_active=1');
    $scope->execute([$studentId,$subjectId]);if(!$scope->fetchColumn()) onlineClassJsonError(422,'That subject does not belong to this student’s level.');
    $scores=onlineClassValidateScores($data);
    $teacherRemark=trim((string)($data['teacher_remark']??''));
    $directorRemark=trim((string)($data['director_remark']??''));
    if(!in_array(null,$scores,true)){
        $total=(float)$scores['attendance_score']+(float)$scores['test_score']+(float)$scores['exam_score'];
        $teacherRemark=$teacherRemark?:onlineClassTutorRemark($total);
        $directorRemark=$directorRemark?:onlineClassDirectorRemark($total);
    }
    $stmt=$pdo->prepare('INSERT INTO online_class_results(student_id,subject_id,academic_session,term,attendance_score,test_score,exam_score,teacher_remark,director_remark,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE attendance_score=VALUES(attendance_score),test_score=VALUES(test_score),exam_score=VALUES(exam_score),teacher_remark=VALUES(teacher_remark),director_remark=VALUES(director_remark),updated_by=VALUES(updated_by)');
    $stmt->execute([$studentId,$subjectId,$session,$term,$scores['attendance_score'],$scores['test_score'],$scores['exam_score'],$teacherRemark?:null,$directorRemark?:null,$staffId,$staffId]);
    onlineClassJson(['success'=>true,'message'=>'Online Class result saved.']);
}
if($action==='release_results'){
    $levelId=$validLevel($data['level_id']??0);$session=trim((string)($data['academic_session']??''));$term=(string)($data['term']??'');
    if(!preg_match('/^\d{4}\/\d{4}$/',$session)||!in_array($term,$terms,true)) onlineClassJsonError(422,'Choose a valid session and term.');
    $released=!empty($data['is_released'])?1:0;
    $stmt=$pdo->prepare('INSERT INTO online_class_result_releases(level_id,academic_session,term,is_released,released_by,released_at) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE is_released=VALUES(is_released),released_by=VALUES(released_by),released_at=VALUES(released_at)');
    $stmt->execute([$levelId,$session,$term,$released?:0,$released?$staffId:null,$released?date('Y-m-d H:i:s'):null]);
    onlineClassJson(['success'=>true,'message'=>$released?'Online Class results released.':'Online Class results hidden from students.']);
}
onlineClassJsonError(404,'Unknown Online Class action.');
