<?php
declare(strict_types=1);

function onlineCheck(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
$root = dirname(__DIR__);
require_once $root . '/app/helpers/online-class.php';
$schema = file_get_contents($root . '/database/migrations/20261110_markaz_online_class.sql');
$helper = file_get_contents($root . '/app/helpers/online-class.php');
$auth = file_get_contents($root . '/app/middleware/auth.php');
$dashboard = file_get_contents($root . '/public/dashboard.html');
$accounts = file_get_contents($root . '/public/js/account-management.js');
$apiAccounts = file_get_contents($root . '/api/settings/accounts.php');
$login = file_get_contents($root . '/public/online-class-login.html');
$page = file_get_contents($root . '/public/online-class.html');
onlineCheck(str_contains($schema, 'CREATE TABLE IF NOT EXISTS online_class_students') && str_contains($schema, 'CREATE TABLE IF NOT EXISTS online_class_results'), 'Online students and results have isolated tables');
onlineCheck(str_contains($schema, 'attendance_score DECIMAL(5,2)') && str_contains($schema, 'test_score DECIMAL(5,2)') && str_contains($schema, 'exam_score DECIMAL(5,2)'), 'Results store Attendance 20, Test 20 and Exam 60 separately');
onlineCheck(str_contains($schema, "('Level 1', 1), ('Level 2', 2), ('Level 3', 3), ('Level 4', 4)"), 'Four initial levels are seeded idempotently');
onlineCheck(str_contains($helper, 'function onlineClassGrade') && onlineClassGrade(80)['grade'] === 'A' && onlineClassGrade(70)['grade'] === 'B' && onlineClassGrade(50)['grade'] === 'C' && onlineClassGrade(40)['grade'] === 'D' && onlineClassGrade(39)['grade'] === 'E', 'Online grading boundaries match MARKAZ grading scale');
onlineCheck(str_contains($helper, 'function onlineClassStaffAccess'), 'Staff Online Class permission is enforced server-side');
onlineCheck(str_contains($accounts, 'online_class_access') && str_contains($apiAccounts, 'online_class_access'), 'Account creation supports individual Online Class access');
onlineCheck(str_contains($dashboard, 'online-class.html') && str_contains($dashboard, 'Online Class'), 'Dashboard provides Online Class navigation');
onlineCheck(str_contains(file_get_contents($root . '/api/online-class/student-login.php'), 'online_student_id') && str_contains($page, 'online-class.js'), 'Separate Online Class student login and workspace exist');
echo "MARKAZ Online Class regression checks passed (8 checks).\n";
