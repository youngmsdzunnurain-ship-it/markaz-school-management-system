<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (string) file_get_contents($root . '/' . $path) : '';
$schema=$read('database/migrations/20261110_markaz_online_class.sql');
$helper=$read('app/helpers/online-class.php');
$api=$read('api/online-class/index.php');
$portal=$read('api/online-class/student-results.php');
$login=$read('api/online-class/student-login.php');
$page=$read('public/online-class.html');
$portalPage=$read('public/online-class-portal.html');
$loginPage=$read('public/online-class-login.html');
$js=$read('public/js/online-class.js');
$checks=[
 'isolated online academic tables and four default levels'=>str_contains($schema,'CREATE TABLE IF NOT EXISTS online_class_students')&&str_contains($schema,'CREATE TABLE IF NOT EXISTS online_class_results')&&str_contains($schema,"('Level 4', 4)"),
 'score limits are attendance 20, test 20 and exam 60'=>str_contains($helper,"'attendance_score'=>20")&&str_contains($helper,"'test_score'=>20")&&str_contains($helper,"'exam_score'=>60"),
 'requested grade boundaries are consistent on server and browser'=>str_contains($helper,"$"."score >= 80")&&str_contains($helper,"$"."score >= 70")&&str_contains($helper,"$"."score >= 50")&&str_contains($helper,"$"."score >= 40")&&str_contains($helper,"'remark' => 'Awesome'")&&str_contains($helper,"'remark' => 'Phenomenal'")&&str_contains($helper,"'remark' => 'Promising'")&&str_contains($helper,"'remark' => 'Impressive'")&&str_contains($helper,"'remark' => 'Poor'")&&str_contains($js,"if(n>=50)return{grade:'C',remark:'Promising'}")&&str_contains($js,"if(n>=40)return{grade:'D',remark:'Impressive'}"),
 'each level stores tutor, task labels, memorisation and term dates'=>str_contains($schema,'tutor_name')&&str_contains($schema,'task_one_name')&&str_contains($schema,'task_two_name')&&str_contains($schema,'third_term_resumption'),
 'staff access is individually permission-gated'=>str_contains($helper,'online_class_access')&&str_contains($api,'onlineClassStaffAccess'),
 'student portal uses separate online student credentials and session'=>str_contains($login,'online_class_students')&&str_contains($login,'online_student_id')&&str_contains($portal,'onlineClassStudentSession'),
 'results release is level and term specific'=>str_contains($schema,'online_class_result_releases')&&str_contains($api,'release_results'),
 'workspace and landscape report interface exist'=>str_contains($page,'online-class.js')&&str_contains($page,'Level Settings')&&str_contains($js,'online-report-title')&&str_contains($js,'window.print()')&&str_contains($portalPage,'online-class-portal.js')&&str_contains($loginPage,'online-class-login.js'),
];
foreach($checks as $label=>$passed){echo ($passed?'PASS ':'FAIL ').$label.PHP_EOL;if(!$passed)exit(1);}
echo 'Online Class module regression checks passed ('.count($checks).').'.PHP_EOL;
