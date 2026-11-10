<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/config/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
startSecureSession();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
 http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed.']); exit;
}
$input=json_decode((string)file_get_contents('php://input'),true);
$admission=strtoupper(trim((string)($input['admission_number']??'')));
$password=(string)($input['password']??'');
if($admission===''||$password===''){http_response_code(422);echo json_encode(['success'=>false,'message'=>'Enter your Online Class admission number and password.']);exit;}
$stmt=db()->prepare('SELECT id,admission_number,first_name,middle_name,last_name,level_id,password_hash,is_active FROM online_class_students WHERE admission_number=? LIMIT 1');
$stmt->execute([$admission]);$student=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$student||!(int)$student['is_active']||!password_verify($password,(string)$student['password_hash'])){
 http_response_code(401);echo json_encode(['success'=>false,'message'=>'Invalid admission number or password.']);exit;
}
session_regenerate_id(true);
$_SESSION['online_student_id']=(int)$student['id'];
$_SESSION['online_student_name']=trim($student['first_name'].' '.($student['middle_name']??'').' '.$student['last_name']);
echo json_encode(['success'=>true,'message'=>'Welcome to MARKAZ Online Class.']);
