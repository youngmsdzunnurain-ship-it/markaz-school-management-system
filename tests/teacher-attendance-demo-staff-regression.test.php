<?php
declare(strict_types=1);

function demoStaffCheck(bool $ok, string $message): void
{
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$attendancePage = file_get_contents($root . '/public/teacher-attendance.html');
$attendanceJs = file_get_contents($root . '/public/js/teacher-attendance.js');
$financePage = file_get_contents($root . '/public/finance.html');
$financeJs = file_get_contents($root . '/public/js/finance.js');

demoStaffCheck(!str_contains($attendancePage, 'teacherFinanceLink'), 'Teacher Attendance has no Finance/Payroll navigation link');
demoStaffCheck(!str_contains($attendancePage, 'Finance &amp; Payroll'), 'Teacher Attendance header does not display Finance & Payroll navigation');
demoStaffCheck(!str_contains($attendanceJs, 'teacherFinanceLink'), 'Teacher Attendance JavaScript does not restore the Finance/Payroll link');
demoStaffCheck(str_contains($attendanceJs, 'data.admin?.can_access_teacher_attendance'), 'Teacher Attendance reads the permission object returned by the auth API');
demoStaffCheck(!str_contains($attendanceJs, 'if(admin.can_access_teacher_attendance'), 'Teacher Attendance does not reference an undefined admin variable');
demoStaffCheck(str_contains($financePage, 'loadDemoTeacherStaff'), 'Finance payroll workspace exposes the demo staff loader');
demoStaffCheck(str_contains($financeJs, 'DEMO-MARKAZ-STAFF'), 'Demo records are explicitly identifiable and safe to distinguish');
demoStaffCheck(substr_count($financeJs, "job_title:'Teacher'") >= 10, 'Ten demo profiles are assigned the Teacher role');
demoStaffCheck(str_contains($financeJs, 'Arabic Language') && str_contains($financeJs, 'Conventional Class Teacher'), 'Demo staff include Arabic and conventional teaching assignments');
demoStaffCheck(str_contains($financeJs, 'loadDemoTeacherStaff') && str.contains ? false : true, '');
echo "Teacher attendance cleanup and demo staff regression checks passed.\n";
