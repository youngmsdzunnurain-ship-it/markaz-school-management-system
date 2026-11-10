<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/online-class.php';
onlineClassStudentSession();
$stmt=db()->prepare('SELECT setting_key,setting_value FROM settings WHERE setting_key IN (?,?,?,?,?)');
$stmt->execute(['class_teacher_signature','director_signature','class_teacher_signature_image','director_signature_image','result_signature_date']);
$settings=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$settings[$row['setting_key']]=$row['setting_value']??'';
onlineClassJson(['success'=>true,'settings'=>$settings]);
