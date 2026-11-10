<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/online-class.php';
if (!empty($_SESSION['admin_id'])) {
    onlineClassStaffAccess();
} else {
    onlineClassStudentSession();
}
$keys=['class_teacher_signature_image','class_teacher_signature','director_signature_image','director_signature','result_signature_date'];
$marks=implode(',',array_fill(0,count($keys),'?'));
$stmt=db()->prepare("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ($marks)");
$stmt->execute($keys);
$settings=array_fill_keys($keys,'');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$settings[$row['setting_key']]=$row['setting_value']??'';
onlineClassJson(['success'=>true,'settings'=>$settings]);
