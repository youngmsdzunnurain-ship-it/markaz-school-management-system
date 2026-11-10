<?php
declare(strict_types=1);
function onlineCheck(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}}
$root=dirname(__DIR__);$schema=file_get_contents($root.'/database/migrations/20261110_markaz_online_class.sql');$helper=file_get_contents($root.'/app/helpers/online-class.php');$accounts=file_get_contents($root.'/public/js/account-management.js');$apiAccounts=file_get_contents($root.'/api/settings/accounts.php');$dashboard=file_get_contents($root.'/public/dashboard.html');$login=file_get_contents($root.'/public/online-class-login.html');$page=file_get_contents($root.'/public/online-class.html');$api=file_get_contents($root.'/api/online-class/index.php');
onlineCheck(str_contains($schema,'online_class_students')&&str_contains($schema,'online_class_results'),'Online Class uses isolated student and result tables');
onlineCheck(str_contains($schema,'attendance_score DECIMAL(5,2)')&&str_contains($schema,'test_score DECIMAL(5,2)')&&str_contains($schema,'exam_score DECIMAL(5,2)'),'Assessment columns support 20/20/60 marks');
onlineCheck(str_contains($schema,"('Level 1', 1), ('Level 2', 2), ('Level 3', 3), ('Level 4', 4)"),'Four levels are seeded');
onlineCheck(str_contains($helper,"'remark'=>'Awesome'")&&str_contains($helper,"'remark'=>'Phenomenal'")&&str_contains($helper,"'remark'=>'Promising'")&&str_contains($helper,"'remark'=>'Impressive'")&&str_contains($helper,"'remark'=>'Poor'"),'Online Class grade remarks are implemented');
onlineCheck(str_contains($accounts,'online_class_access')&&str_contains($apiAccounts,'online_class_access'),'Staff access can be assigned per account');
onlineCheck(str_contains($dashboard,'online-class.html')&&str_contains($dashboard,'Online Class'),'Dashboard includes Online Class');
onlineCheck(str_contains($login,'Online Class Student Portal')&&str_contains($page,'online-class.js'),'Separate student login and staff workspace are present');
onlineCheck(str_contains($api,'attendance_score')&&str_contains($api,'release_results'),'Attendance, result entry and release endpoints exist');
echo "MARKAZ Online Class regression checks passed (8 checks).\n";
