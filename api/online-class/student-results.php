<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/online-class.php';
$studentId=onlineClassStudentSession();
$studentStmt=db()->prepare('SELECT s.id,s.admission_number,s.first_name,s.middle_name,s.last_name,l.name AS level_name,l.tutor_name,l.memorization_portion,l.first_term_resumption,l.second_term_resumption,l.third_term_resumption FROM online_class_students s JOIN online_class_levels l ON l.id=s.level_id WHERE s.id=? AND s.is_active=1 LIMIT 1');
$studentStmt->execute([$studentId]);$student=$studentStmt->fetch(PDO::FETCH_ASSOC);
if(!$student) onlineClassJsonError(401,'This Online Class account is no longer active.');
$session=trim((string)($_GET['academic_session']??''));$term=(string)($_GET['term']??'First Term');
if(!preg_match('/^\d{4}\/\d{4}$/',$session)||!in_array($term,['First Term','Second Term','Third Term'],true)) onlineClassJsonError(422,'Choose a valid academic session and term.');
$release=db()->prepare('SELECT is_released FROM online_class_result_releases WHERE level_id=(SELECT level_id FROM online_class_students WHERE id=?) AND academic_session=? AND term=? LIMIT 1');
$release->execute([$studentId,$session,$term]);
if((int)$release->fetchColumn()!==1) onlineClassJson(['success'=>true,'released'=>false,'student'=>$student,'results'=>[]]);
$stmt=db()->prepare('SELECT sub.name AS subject_name,sub.sort_order,r.attendance_score,r.test_score,r.exam_score,r.teacher_remark,r.director_remark FROM online_class_subjects sub LEFT JOIN online_class_results r ON r.subject_id=sub.id AND r.student_id=? AND r.academic_session=? AND r.term=? WHERE sub.level_id=(SELECT level_id FROM online_class_students WHERE id=?) AND sub.is_active=1 ORDER BY sub.sort_order,sub.name');
$stmt->execute([$studentId,$session,$term,$studentId]);$results=[];
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
 $scores=[$row['attendance_score'],$row['test_score'],$row['exam_score']];
 $complete=!in_array(null,$scores,true);
 $total=$complete?array_sum(array_map('floatval',$scores)):null;
 $grade=$complete?onlineClassGrade((float)$total):null;
 $results[]=$row+['total'=>$total,'percentage'=>$total,'grade'=>$grade['grade']??null,'grade_remark'=>$grade['remark']??null];
}
$pdo=db();
$pdo->exec("CREATE TABLE IF NOT EXISTS markaz_app_settings (setting_key VARCHAR(100) NOT NULL PRIMARY KEY, setting_value TEXT NULL) ENGINE=InnoDB");
$signatureRows=$pdo->query("SELECT setting_key,setting_value FROM markaz_app_settings WHERE setting_key IN ('result_footer_class_teacher_image','result_footer_director_image','result_footer_signature_date')")->fetchAll(PDO::FETCH_KEY_PAIR);
$signatures=['teacher'=>$signatureRows['result_footer_class_teacher_image']??'','director'=>$signatureRows['result_footer_director_image']??'','date'=>$signatureRows['result_footer_signature_date']??''];
onlineClassJson(['success'=>true,'released'=>true,'student'=>$student,'academic_session'=>$session,'term'=>$term,'results'=>$results,'signatures'=>$signatures]);
