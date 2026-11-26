<?php
declare(strict_types=1);
function financeDemoRemovalCheck(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "PASS: {$message}\n";
}
$root = dirname(__DIR__);
$attendancePage = file_get_contents($root . '/public/teacher-attendance.html');
$attendanceJs = file_get_contents($root . '/public/js/teacher-attendance.js');
$financePage = file_get_contents($root . '/public/finance.html');
$financeJs = file_get_contents($root . '/public/js/finance.js');
$payrollApi = file_get_contents($root . '/api/finance/payroll.php');
financeDemoRemovalCheck(str_contains($attendancePage, 'href="./dashboard.html" class="attendance-back"'), 'Teacher Attendance back navigation returns directly to Dashboard');
financeDemoRemovalCheck(str_contains($attendancePage, 'v=20261126-attendance-nav1'), 'Teacher Attendance retains cache-busted JavaScript');
financeDemoRemovalCheck(!str_contains($attendancePage, 'teacherFinanceLink'), 'Teacher Attendance has no Finance/Payroll navigation link');
financeDemoRemovalCheck(str_contains($attendanceJs, 'data.admin?.can_access_teacher_attendance'), 'Teacher Attendance reads the permission object returned by the auth API');
financeDemoRemovalCheck(!str_contains($financePage, 'loadDemoTeacherStaff') && !str_contains($financePage, 'Preview with 10 demo teachers'), 'Finance no longer exposes demo staff controls');
financeDemoRemovalCheck(!str_contains($financeJs, 'DEMO —') && !str_contains($financeJs, 'example.test'), 'Finance no longer contains fictional demo staff profiles');
financeDemoRemovalCheck(!str_contains($financeJs, 'ensureDemoPayrollVisible') && !str_contains($financeJs, 'loadDemoTeacherStaff'), 'Finance no longer seeds demo staff or auto-calculates demo payroll');
financeDemoRemovalCheck(str_contains($financeJs, 'payrollHistoryRows') && str_contains($financeJs, 'd.history'), 'Payroll history continues to display saved payroll records');
financeDemoRemovalCheck(str_contains($payrollApi, 's.is_active=1'), 'New payroll calculations include active staff only');
financeDemoRemovalCheck(str_contains($financePage, 'id="payrollHistoryRows"'), 'Finance retains the real payroll history table');
echo "Finance demo removal regression checks passed.\n";
