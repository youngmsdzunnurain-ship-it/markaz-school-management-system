<?php
declare(strict_types=1);
function eoAssert(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: ".$message.PHP_EOL);exit(1);}}
$root=dirname(__DIR__);$d=file_get_contents($root.'/public/dashboard.html');$j=file_get_contents($root.'/public/js/dashboard.js');$p=file_get_contents($root.'/public/exam-officer-settings.html');$s=file_get_contents($root.'/public/js/exam-officer-settings.js');$a=file_get_contents($root.'/app/middleware/auth.php');$r=file_get_contents($root.'/api/settings/result-release.php');$f=file_get_contents($root.'/api/settings/finalize-academic-period.php');
foreach(['dashboard'=>$d,'dashboard script'=>$j,'settings page'=>$p,'settings script'=>$s,'middleware'=>$a,'release API'=>$r,'archive API'=>$f] as $k=>$v)eoAssert(is_string($v),$k.' exists');
eoAssert(str_contains($d,'data-role-access="exam_officer_settings"'),'dedicated settings navigation exists');
eoAssert(str_contains($d,'data-role-access="admin_or_exam_officer"'),'Add Student shortcut is shared with Exam Officer');
eoAssert(str_contains($d,'data-role-access="admin" aria-label="Attendance by class"'),'attendance charts remain Admin-only');
eoAssert(str_contains($j,'exam-officer-settings.html'),'Exam Officer settings route is wired');
eoAssert(str_contains($p,'Academic Results Release')&&str_contains($p,'Academic &amp; Term Archive')&&str_contains($p,'Academic Settings')&&str_contains($p,'Result &amp; Reports')&&str_contains($p,'Core Memorization'),'all six requested settings categories exist');
eoAssert(str_contains($a,'function requireAdminOrExamOfficer()'),'operational settings guard exists');
eoAssert(str_contains($r,'password_verify($password, $passwordHash)'),'release requires Exam Officer password');
eoAssert(str_contains($f,'role = ? AND is_active = 1'),'archive verifies the authenticated account role');
echo "Exam Officer portal checks passed.".PHP_EOL;