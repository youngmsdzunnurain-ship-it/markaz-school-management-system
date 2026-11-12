<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
};
$staffApi = $read('api/auth/login.php');
$adminJs = $read('public/js/login.js');
$helper = $read('app/helpers/login-lockout.php');
$studentApi = $read('api/results/student-portal.php');
$studentJs = $read('public/js/student-results-portal.js');
$onlineApi = $read('api/online-class/student-login.php');
$onlineJs = $read('public/js/online-class-login.js');
$assert(str_contains($staffApi, "'admin', 'exam_officer', 'teacher', 'management'"), 'Admin, Exam Officer, Teacher and Management all use the protected staff login endpoint');
$assert(str_contains($staffApi, 'DATE_ADD(NOW(), INTERVAL 7 DAY)') && str_contains($staffApi, 'if ($attempts >= 3)'), 'staff accounts including Admin lock after three failed password attempts for seven days');
$assert(str_contains($adminJs, 'error.loginData') && str_contains($adminJs, 'remaining_seconds') && str_contains($adminJs, 'adminLockoutTimer'), 'Admin login displays the server-authoritative seven-day lockout countdown');
$assert(str_contains($studentApi, 'markazRecordLoginFailure(') && str_contains($studentApi, "'student_result'") && str_contains($studentApi, ', 3)'), 'conventional Student Results lookup applies a three-day lockout');
$assert(str_contains($onlineApi, 'markazRecordLoginFailure(') && str_contains($onlineApi, "'online_class_student'") && str_contains($onlineApi, ', 3)'), 'Online Class student sign-in applies a separate three-day lockout');
$assert(str_contains($helper, 'failed_attempts + 1 >= 3') && str_contains($helper, 'DATE_ADD(NOW(), INTERVAL {$lockDays} DAY)'), 'shared helper locks at the third failed attempt and keeps the server authoritative');
$assert(str_contains($studentJs, 'showLockoutCountdown') && str_contains($onlineJs, 'showLockoutCountdown'), 'both student portals display the active lockout countdown');
$assert(str_contains($staffApi, 'DATE_ADD(NOW(), INTERVAL 7 DAY)') && !str_contains($onlineApi, 'INTERVAL 7 DAY'), 'student lockout duration does not alter the existing staff seven-day policy');
echo "Login lockout coverage checks passed (8 checks).\n";
