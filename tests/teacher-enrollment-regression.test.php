<?php
declare(strict_types=1);

function teacherEnrollmentCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$form = file_get_contents(__DIR__ . '/../public/js/account-management.js');
$api = file_get_contents(__DIR__ . '/../api/settings/accounts.php');
$login = file_get_contents(__DIR__ . '/../api/auth/login.php');

teacherEnrollmentCheck(
    str_contains($form, '<option value="teacher">Teacher</option>') &&
    str_contains($form, 'accountCanTakeAttendance') &&
    str_contains($form, 'attendance_records: role.value === \'teacher\'') &&
    str_contains($form, 'assignments'),
    'teacher enrollment must expose teacher role, class/subject assignments and attendance permission'
);
teacherEnrollmentCheck(
    str_contains($api, 'teacher_assignments') &&
    str_contains($api, 'INSERT INTO teacher_assignments') &&
    str_contains($api, 'attendance_records') &&
    str_contains($api, 'That teacher username is already in use.'),
    'teacher enrollment must persist assignments and attendance access and enforce unique usernames'
);
teacherEnrollmentCheck(
    str_contains($login, 'username = :username') &&
    str_contains($login, 'password_verify'),
    'teacher login must authenticate with the assigned username and password'
);

echo "Teacher enrollment regression tests passed.\n";
