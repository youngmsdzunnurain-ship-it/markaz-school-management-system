<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/online-class.php';
$studentId=onlineClassStudentSession();
$studentStmt=db()->prepare('SELECT s.id,s.admission_number,s.first_name,s.middle_name,s.last_name,s.section,l.name AS level_name,l.tutor_name,l.task_one_name,l.task_two_name,l.memorization_portion,l.first_term_resumption,l.second_term_resumption,l.third_term_resumption FROM online_class_students s JOIN online_class_levels l ON l.id=s.level_id WHERE s.id=? AND s.is_active=1 LIMIT 1');
$studentStmt->execute([$studentId]);$student=$studentStmt->fetch(PDO::FETCH_ASSOC);
if(!$student) onlineClassJsonError(401,'This Online Class account is no longer active.');
$session=trim((string)($_SESSION['online_student_academic_session']??''));$term=(string)($_SESSION['online_student_term']??'');
if(!preg_match('/^\d{4}\/\d{4}$/',$session)||!in_array($term,['First Term','Second Term','Third Term'],true)) onlineClassJsonError(422,'Choose a valid academic session and term.');
$levelId=(int)$student['level_id'];
$release=db()->prepare('SELECT is_released FROM online_class_result_releases WHERE level_id=? AND academic_session=? AND term=? LIMIT 1');
$release->execute([$levelId,$session,$term]);
$releaseValue=$release->fetchColumn();
$hasActiveRelease=$releaseValue!==false;
$isReleased=(int)$releaseValue===1;
$rowsToRender=null;
$archivedStudent=null;$archivedLevel=null;$archivedSignatures=null;
if(!$hasActiveRelease){
 $archive=db()->prepare("SELECT payload,payload_sha256,is_released FROM online_class_archives WHERE archive_type='results' AND destination='online' AND level_id=? AND academic_session=? AND term=? AND is_released=1 ORDER BY created_at DESC,id DESC LIMIT 1");
 $archive->execute([$levelId,$session,$term]);
 $saved=$archive->fetch(PDO::FETCH_ASSOC);
 if($saved && (int)$saved['is_released']===1 && hash_equals((string)$saved['payload_sha256'],hash('sha256',(string)$saved['payload']))){
  $decoded=json_decode((string)@gzdecode((string)$saved['payload']),true);
  if(is_array($decoded) && ($decoded['format']??'')==='markaz-online-class-archive-v1' && ($decoded['type']??'')==='results'){
   foreach(($decoded['students']??[]) as $snapshot){if((int)($snapshot['id']??0)===$studentId){$archivedStudent=$snapshot;break;}}
   if(is_array($archivedStudent)){
    $isReleased=true;
    $archivedLevel=is_array($decoded['level']??null)?$decoded['level']:null;
    $archivedSignatures=is_array($decoded['signatures']??null)?$decoded['signatures']:null;
    $rowsToRender=array_values(array_filter($decoded['results']??[],static fn($row)=>(int)($row['student_id']??0)===$studentId));
   }
  }
 }
}
if(!$isReleased) onlineClassJson(['success'=>true,'released'=>false,'student'=>$student,'academic_session'=>$session,'term'=>$term,'results'=>[]]);
if(is_array($archivedStudent))$student=array_merge($student,$archivedStudent);
if(is_array($archivedLevel)){$student['level_name']=$archivedLevel['name']??$student['level_name'];foreach(['tutor_name','task_one_name','task_two_name','memorization_portion','first_term_resumption','second_term_resumption','third_term_resumption'] as $field){$student[$field]=$archivedLevel[$field]??null;}}
if($rowsToRender===null){
 $stmt=db()->prepare('SELECT sub.name AS subject_name,sub.sort_order,r.attendance_score,r.test_score,r.exam_score,r.teacher_remark,r.director_remark FROM online_class_subjects sub LEFT JOIN online_class_results r ON r.subject_id=sub.id AND r.student_id=? AND r.academic_session=? AND r.term=? WHERE sub.level_id=? AND sub.is_active=1 ORDER BY sub.sort_order,sub.name');
 $stmt->execute([$studentId,$session,$term,$levelId]);
 $rowsToRender=$stmt->fetchAll(PDO::FETCH_ASSOC);
}else{
 $rowsToRender=array_map(static fn($row)=>['subject_name'=>$row['subject_name']??'','sort_order'=>0,'attendance_score'=>$row['attendance_score']??null,'test_score'=>$row['test_score']??null,'exam_score'=>$row['exam_score']??null,'teacher_remark'=>$row['teacher_remark']??null,'director_remark'=>$row['director_remark']??null],$rowsToRender);
}
$results=[];foreach($rowsToRender as $row){
 $scores=[$row['attendance_score'],$row['test_score'],$row['exam_score']];
 $complete=!in_array(null,$scores,true);
 $total=$complete?array_sum(array_map('floatval',$scores)):null;
 $grade=$complete?onlineClassGrade((float)$total):null;
 $teacherRemark = trim((string)($row['teacher_remark'] ?? ''));
 $directorRemark = trim((string)($row['director_remark'] ?? ''));
 if ($complete && $teacherRemark === '') {
  $teacherRemark = $total >= 80 ? 'Mashallah! Your dedication and excellent effort are truly commendable. May Allah increase you in beneficial knowledge.' : ($total >= 70 ? 'Well done. Your steady effort is showing beautiful progress. Keep revising and moving forward.' : ($total >= 50 ? 'You are making promising progress. Continue with regular revision and confidence; every step matters.' : ($total >= 30 ? 'You have made a beginning. With patient practice and consistent revision, you can make stronger progress.' : 'Please do not be discouraged. Learning is a journey; take it one step at a time and keep trying.')));
 }
 if ($complete && $directorRemark === '') {
  $directorRemark = $total >= 80 ? 'Excellent achievement. May Allah preserve your knowledge and grant you continued success.' : ($total >= 70 ? 'A very good effort. We encourage you to maintain this consistency and continue striving.' : ($total >= 50 ? 'Your progress is encouraging. More regular revision will help you reach the next level.' : ($total >= 30 ? 'We appreciate your effort. With support, patience and consistency, further improvement is within reach.' : 'Every learner deserves encouragement. Let us continue supporting your learning journey with patience and care.')));
 }
 $results[]=array_merge($row,['teacher_remark'=>$teacherRemark,'director_remark'=>$directorRemark,'total'=>$total,'percentage'=>$total,'grade'=>$grade['grade']??null,'grade_remark'=>$grade['remark']??null]);
}
$signatureRows=db()->query("SELECT CASE setting_key
 WHEN 'result_footer_class_teacher_signature' THEN 'class_teacher_signature'
 WHEN 'result_footer_director_signature' THEN 'director_signature'
 WHEN 'result_footer_class_teacher_image' THEN 'class_teacher_signature_image'
 WHEN 'result_footer_director_image' THEN 'director_signature_image'
 WHEN 'result_footer_signature_date' THEN 'result_signature_date'
 END AS setting_key,setting_value
 FROM markaz_app_settings
 WHERE setting_key IN ('result_footer_class_teacher_signature','result_footer_director_signature','result_footer_class_teacher_image','result_footer_director_image','result_footer_signature_date')")->fetchAll(PDO::FETCH_KEY_PAIR);
$signatures=['teacher_image'=>$signatureRows['class_teacher_signature_image']??'','teacher_text'=>$signatureRows['class_teacher_signature']??'','director_image'=>$signatureRows['director_signature_image']??'','director_text'=>$signatureRows['director_signature']??'','date'=>$signatureRows['result_signature_date']??''];
if(is_array($archivedSignatures))$signatures=$archivedSignatures;
onlineClassJson(['success'=>true,'released'=>true,'student'=>$student,'academic_session'=>$session,'term'=>$term,'results'=>$results,'signatures'=>$signatures]);
