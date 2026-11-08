<?php
declare(strict_types=1);
function eoAssert(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: ".$message.PHP_EOL);exit(1);}}
$root=dirname(__DIR__);
$d=file_get_contents($root.'/public/dashboard.html');
$j=file_get_contents($root.'/public/js/dashboard.js');
$settings=file_get_contents($root.'/public/settings.html');
$navigation=file_get_contents($root.'/public/js/settings-panel-navigation.js');
$settingsScript=file_get_contents($root.'/public/js/settings.js');
$a=file_get_contents($root.'/app/middleware/auth.php');
$r=file_get_contents($root.'/api/settings/result-release.php');
$f=file_get_contents($root.'/api/settings/finalize-academic-period.php');
foreach(['dashboard'=>$d,'dashboard script'=>$j,'shared settings page'=>$settings,'settings navigation'=>$navigation,'settings script'=>$settingsScript,'middleware'=>$a,'release API'=>$r,'archive API'=>$f] as $k=>$v)eoAssert(is_string($v),$k.' exists');
eoAssert(str_contains($d,'href="./settings.html"')&&str_contains($d,'data-role-access="admin_or_exam_officer"'),'Admin and Exam Officer use the same Settings route');
eoAssert(!str_contains($d,'exam-officer-settings.html'),'dashboard does not link to a separate Exam Officer settings page');
eoAssert(str_contains($d,'data-role-access="admin_or_exam_officer"'),'Add Student shortcut is shared with Exam Officer');
eoAssert(str_contains($d,'data-role-access="admin" aria-label="Attendance by class"'),'attendance charts remain Admin-only');
eoAssert(str_contains($settings,'data-role-access="admin_or_exam_officer"'),'shared settings page admits Admin and Exam Officer roles');
eoAssert(str_contains($navigation,'result-release')&&str_contains($navigation,'academic-archive')&&str_contains($navigation,'memorization'),'shared navigation contains the requested settings categories');
eoAssert(str_contains($navigation,'exam_officer'),'shared settings navigation has an Exam Officer category view');
eoAssert(str_contains($navigation,'Core Memorization')&&str_contains($navigation,'Academic & Term Archive'),'Exam Officer sees the requested shared category names');
eoAssert(!str_contains($settingsScript,'exam-officer-settings.html'),'settings logic does not route to a separate page');
eoAssert(str_contains($settingsScript,"data.admin.role !== \"admin\"")===false,'profile loader no longer rejects Exam Officer accounts');
eoAssert(str_contains($settingsScript,'payload.password = password'),'Exam Officer result release submits password confirmation');
eoAssert(str_contains($a,'function requireAdminOrExamOfficer()'),'operational settings guard exists');
eoAssert(str_contains($r,'password_verify($password, $passwordHash)'),'release requires Exam Officer password');
eoAssert(str_contains($f,'role = ? AND is_active = 1'),'archive verifies the authenticated account role');
echo "Exam Officer shared-settings checks passed.".PHP_EOL;
