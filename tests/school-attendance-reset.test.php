<?php
declare(strict_types=1);

function attendanceResetCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$api = file_get_contents(__DIR__ . '/../api/settings/clear-attendance-data.php');
$route = file_get_contents(__DIR__ . '/../public/api/settings/clear-attendance-data.php');
$page = file_get_contents(__DIR__ . '/../public/settings.html');
$js = file_get_contents(__DIR__ . '/../public/js/settings.js');

attendanceResetCheck(
    str_contains($api, 'requireSeniorAdmin()') &&
    str_contains($api, 'password_verify') &&
    str_contains($api, 'CLEAR ALL SCHOOL ATTENDANCE'),
    'clearing school attendance must require Senior Admin access, password verification and typed confirmation'
);
attendanceResetCheck(
    str_contains($api, 'DELETE FROM attendance_records') &&
    str_contains($api, 'beginTransaction') &&
    str_contains($api, 'commit()') &&
    str_contains($api, 'rollBack()'),
    'all conventional and Arabic daily attendance must be cleared atomically'
);
attendanceResetCheck(
    !preg_match('/DELETE\\s+FROM\\s+(online_class_attendance|online_class_students|students|admins|classes|arabic_classes|academic_periods|results|behavioural_reports)\\b/i', $api),
    'the school attendance reset must preserve Online Class data, results, people, classes and academic setup'
);
attendanceResetCheck(
    str_contains($route, 'api/settings/clear-attendance-data.php'),
    'the public Settings route must point to the protected attendance reset API'
);
attendanceResetCheck(
    str_contains($page, 'clearSchoolAttendanceButton') &&
    str_contains($page, 'clearSchoolAttendanceDialog') &&
    str_contains($page, 'CLEAR ALL SCHOOL ATTENDANCE'),
    'Settings must expose a separately confirmed clear-all-school-attendance action'
);
attendanceResetCheck(
    str_contains($js, 'clear-attendance-data.php') &&
    str_contains($js, 'clearSchoolAttendanceButton') &&
    str_contains($js, 'CLEAR ALL SCHOOL ATTENDANCE'),
    'the Settings UI must call the protected attendance reset endpoint'
);
attendanceResetCheck(
    str_contains($page, 'daily attendance') &&
    str_contains($page, 'Conventional and Arabic'),
    'the warning must clearly identify the daily Conventional and Arabic attendance records being removed'
);

echo "School attendance reset regression checks passed (7 checks).\n";
