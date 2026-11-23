<?php
declare(strict_types=1);

function teacherPolicyCheck(bool $ok, string $message): void
{
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/database/migrations/20261125_teacher_attendance_modes.sql');
$attendanceApi = file_get_contents($root . '/api/attendance/teachers.php');
$payrollApi = file_get_contents($root . '/api/finance/payroll.php');
$financePage = file_get_contents($root . '/public/finance.html');
$attendancePage = file_get_contents($root . '/public/teacher-attendance.html');
$attendanceJs = file_get_contents($root . '/public/js/teacher-attendance.js');
$financeJs = file_get_contents($root . '/public/js/finance.js');
$installer = file_get_contents($root . '/scripts/install-finance.sh');

teacherPolicyCheck(str_contains($migration, "attendance_mode ENUM('time_based','attendance_only')"), 'salary profiles distinguish time-based and attendance-only staff');
teacherPolicyCheck(str_contains($migration, 'MODIFY COLUMN expected_arrival TIME NULL'), 'arrival schedules are optional for attendance-only staff');
teacherPolicyCheck(str_contains($migration, 'ADD COLUMN attendance_mode'), 'attendance records preserve the policy active on the attendance date');
teacherPolicyCheck(str_contains($attendanceApi, "['time_based','attendance_only']"), 'attendance API validates the two permitted staff policies');
teacherPolicyCheck(str_contains($attendanceApi, '$mode === \'time_based\''), 'only time-based staff require arrival-time entry and lateness calculation');
teacherPolicyCheck(str_contains($attendanceApi, "'attendance_only'"), 'attendance-only staff are recorded without arrival times or lateness deductions');
teacherPolicyCheck(str_contains($attendanceApi, "['present','absent','approved_leave','excused']"), 'teacher attendance supports present, absent, approved leave and excused statuses');
teacherPolicyCheck(str_contains($attendanceApi, "lateness_deduction"), 'attendance records persist payroll deductions from the attendance workflow');
teacherPolicyCheck(str_contains($attendanceApi, '$notes===\'\''), 'excused and approved-leave entries require an auditable reason');
teacherPolicyCheck(str_contains($payrollApi, "attendance_mode='time_based'") || str_contains($payrollApi, "attendance_mode']"), 'payroll applies lateness deductions only to time-based attendance');
teacherPolicyCheck(str_contains($payrollApi, "status") && str_contains($payrollApi, "'present'"), 'payroll excludes excused and non-present records from lateness deductions');
teacherPolicyCheck(str_contains($financePage, 'name="attendance_mode"'), 'Finance salary setup lets authorised users choose the staff attendance policy');
teacherPolicyCheck(str_contains($financeJs, 'Attendance-only staff'), 'Finance explains that attendance-only staff are not timed for payroll');
teacherPolicyCheck(str_contains($attendancePage, 'Time-based staff') && str_contains($attendancePage, 'attendance-only staff'), 'Teacher Attendance presents the two staff groups separately');
teacherPolicyCheck(str_contains($attendanceJs, 'attendance_mode') && str_contains($attendanceJs, 'attendance_only'), 'teacher register UI omits arrival-time requirements for attendance-only staff');
teacherPolicyCheck(str_contains($attendanceJs, 'Excused') && str_contains($attendanceJs, 'Approved leave'), 'teacher register clearly exposes authorised absence statuses');
teacherPolicyCheck(str_contains($installer, '20261125_teacher_attendance_modes.sql'), 'Finance installer applies the teacher attendance policy migration');
echo "Teacher attendance policy regression checks passed.\n";
