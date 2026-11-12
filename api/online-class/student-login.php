<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/session.php';
require_once __DIR__ . '/../../app/helpers/login-lockout.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
startSecureSession();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
 http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit;
}
$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input)){http_response_code(400);echo json_encode(['success'=>false,'message'=>'Invalid login request.']);exit;}
$admission=strtoupper(trim((string)($input['admission_number']??'')));
$levelId=filter_var($input['level_id']??null,FILTER_VALIDATE_INT);
$session=trim((string)($input['academic_session']??''));
$term=(string)($input['term']??'');

if($admission===''||!$levelId||!preg_match('/^(202[6-9]|203[0-5])\/(202[7-9]|203[0-6])$/',$session)||!in_array($term,['First Term','Second Term','Third Term'],true) ){
 http_response_code(422);echo json_encode(['success'=>false,'message'=>'Enter your admission number, level, academic session and term.']);exit;
}
$pdo = db();
markazEnsureLoginLockoutSchema($pdo);
$lockoutEnabled = markazLoginLockoutEnabled($pdo);
$lockoutIdentifier = strtolower($admission);
if ($lockoutEnabled) {
 $activeLock = markazGetActiveLoginLockout($pdo, 'online_class_student', $lockoutIdentifier);
 if ($activeLock !== null) {
  http_response_code(423);
  echo json_encode(['success'=>false,'locked'=>true,'locked_until'=>$activeLock['locked_until'],'remaining_seconds'=>$activeLock['remaining_seconds'],'attempts_remaining'=>0,'message'=>'Too many unsuccessful sign-in attempts. This Online Class account is locked for 3 days.']);
  exit;
 }
}
$enabled=$pdo->query("SELECT setting_value FROM settings WHERE setting_key='online_class_student_access' LIMIT 1")->fetchColumn();
if(!in_array(strtolower(trim((string)$enabled)),['1','true','yes','on'],true)){http_response_code(403);echo json_encode(['success'=>false,'message'=>'Online Class student login is currently closed.']);exit;}
$stmt=$pdo->prepare('SELECT s.id,s.admission_number,s.first_name,s.middle_name,s.last_name,s.level_id,s.section,l.name AS level_name,s.password_hash,s.is_active FROM online_class_students s JOIN online_class_levels l ON l.id=s.level_id WHERE s.admission_number=? AND s.level_id=? AND l.is_active=1 LIMIT 1');
$stmt->execute([$admission,$levelId]);$student=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$student||!(int)$student['is_active']){
 if ($lockoutEnabled) {
  $failure = markazRecordLoginFailure($pdo, 'online_class_student', $lockoutIdentifier, 3);
  if ($failure['locked']) {
   http_response_code(423);
   echo json_encode(['success'=>false,'locked'=>true,'locked_until'=>$failure['locked_until'],'remaining_seconds'=>$failure['remaining_seconds'],'attempts_remaining'=>0,'message'=>'Too many unsuccessful sign-in attempts. This Online Class account is locked for 3 days.']);
   exit;
  }
  http_response_code(401);
  echo json_encode(['success'=>false,'attempts_remaining'=>$failure['attempts_remaining'],'message'=>'The admission number or level does not match our records. '.$failure['attempts_remaining'].' attempt(s) remaining before a 3-day lockout.']);
  exit;
 }
 http_response_code(401);echo json_encode(['success'=>false,'message'=>'The admission number or level does not match our records.']);exit;
}
if ($lockoutEnabled) markazClearLoginFailures($pdo, 'online_class_student', $lockoutIdentifier);
session_regenerate_id(true);
unset($_SESSION['admin_id'], $_SESSION['admin_role'], $_SESSION['admin_name'], $_SESSION['admin_email']);
$_SESSION=[];
$_SESSION['online_student_id']=(int)$student['id'];
$_SESSION['online_student_name']=trim($student['first_name'].' '.($student['middle_name']??'').' '.$student['last_name']);
$_SESSION['online_student_academic_session']=$session;
$_SESSION['online_student_term']=$term;
echo json_encode(['success'=>true,'message'=>'Welcome to MARKAZ Online Class.','student'=>['name'=>$_SESSION['online_student_name'],'admission_number'=>$student['admission_number'],'level'=>$student['level_name'],'section'=>$student['section'],'academic_session'=>$session,'term'=>$term]]);
