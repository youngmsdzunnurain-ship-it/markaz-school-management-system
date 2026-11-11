<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/online-class.php';
onlineClassStudentSession();
$stmt=db()->prepare("SELECT CASE setting_key
 WHEN 'result_footer_class_teacher_signature' THEN 'class_teacher_signature'
 WHEN 'result_footer_director_signature' THEN 'director_signature'
 WHEN 'result_footer_class_teacher_image' THEN 'class_teacher_signature_image'
 WHEN 'result_footer_director_image' THEN 'director_signature_image'
 WHEN 'result_footer_signature_date' THEN 'result_signature_date'
 END AS setting_key,setting_value
 FROM markaz_app_settings
 WHERE setting_key IN (?,?,?,?,?)");
$stmt->execute(['result_footer_class_teacher_signature','result_footer_director_signature','result_footer_class_teacher_image','result_footer_director_image','result_footer_signature_date']);
$settings=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$settings[$row['setting_key']]=$row['setting_value']??'';
onlineClassJson(['success'=>true,'settings'=>$settings]);
