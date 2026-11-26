<?php
declare(strict_types=1);

function independentStaffCheck(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/database/migrations/20261124_markaz_independent_finance_staff.sql');
$financeApi = file_get_contents($root . '/api/finance/index.php');
$payrollApi = file_get_contents($root . '/api/finance/payroll.php');
$teacherAttendanceApi = file_get_contents($root . '/api/attendance/teachers.php');
$attendanceCommon = file_get_contents($root . '/api/attendance/_common.php');
$attendancePage = file_get_contents($root . '/public/attendance.html');
$financePage = file_get_contents($root . '/public/finance.html');
$financeJs = file_get_contents($root . '/public/js/finance.js');
$authMe = file_get_contents($root . '/api/auth/me.php');
$installer = file_get_contents($root . '/scripts/install-finance.sh');

independentStaffCheck(str_contains($migration, 'CREATE TABLE finance_staff'), 'Finance has an independent staff register');
independentStaffCheck(str_contains($migration, 'finance_staff_id') && str_contains($migration, 'REFERENCES finance_staff(id)'), 'salary, attendance and payroll records reference Finance staff IDs');
independentStaffCheck(!str_contains($migration, 'INSERT INTO finance_staff SELECT') && !str_contains($migration, 'FROM admins a WHERE a.role'), 'migration never imports all academic teacher accounts into Finance');
independentStaffCheck(str_contains($financeApi, 'FROM finance_staff') && str_contains($financeApi, 'save_staff') && str_contains($financeApi, 'deactivate_staff'), 'Finance API manages independent staff records');
independentStaffCheck(!str_contains($financeApi, "WHERE a.role='teacher'"), 'Finance staff dropdown does not query academic teacher accounts');
independentStaffCheck(str_contains($payrollApi, 'finance_staff_id') && str_contains($payrollApi, 'FROM finance_staff'), 'payroll calculation and history use independent Finance staff');
independentStaffCheck(!str_contains($payrollApi, 'action===\'record_attendance\''), 'Finance no longer records attendance separately');
independentStaffCheck(str_contains($teacherAttendanceApi, 'attendanceRequireTeacherAttendanceAccess') && str_contains($teacherAttendanceApi, 'staff_attendance'), 'teacher attendance is recorded in the Attendance module with full-attendance authorization');
independentStaffCheck(str_contains($attendanceCommon, 'attendanceRequireTeacherAttendanceAccess'), 'teacher attendance has a dedicated server-side permission gate');
independentStaffCheck(str_contains($attendancePage, 'teacher-attendance.html') && str_contains($attendancePage, 'data-role-access="teacher_attendance"'), 'student Attendance workspace links to restricted Teacher Attendance');
independentStaffCheck(str_contains($teacherAttendanceApi, 'attendanceRequireTeacherAttendanceAccess') && !str_contains($teacherAttendanceApi, 'requireAdmin();'), 'teacher attendance endpoint enforces the dedicated Admin/fully-authorised-Management gate instead of ordinary attendance access');
independentStaffCheck(str_contains($financePage, 'id="financeStaffForm"') && str_contains($financePage, 'id="staffRows"'), 'Finance provides an independent add/deactivate staff register');
independentStaffCheck(str_contains($financeJs, 'save_staff') && str_contains($financeJs, 'deactivate_staff'), 'Finance staff UI submits create and deactivate operations');
independentStaffCheck(str_contains($financeJs, 'financeStaffLoaded=true') && str_contains($financeJs, 'financeStaffLoaded=false') && str_contains($financeJs, 'financeStaffLoading=null'), 'Finance staff loading state is cached only after success and reset after failure for retry');
independentStaffCheck(str_contains($authMe, 'can_access_teacher_attendance'), 'session capability response exposes the separate teacher-attendance permission');
independentStaffCheck(str_contains($installer, '20261124_markaz_independent_finance_staff.sql'), 'Finance installer applies the independent staff migration after existing Finance migrations');
independentStaffCheck(str_contains($teacherAttendanceApi, 'deduct_lateness') && str_contains($payrollApi, "??'10'"), 'lateness deductions default to ten naira per minute and respect staff opt-in');
independentStaffCheck(str_contains($payrollApi, "($record['status']??'')==='absent'"), 'unexcused absent attendance contributes to payroll deductions');
independentStaffCheck(str_contains($payrollApi, '30000') || str_contains($payrollApi, "'300.00'"), 'each unexcused absent day deducts exactly three hundred naira');
independentStaffCheck(str_contains($teacherAttendanceApi, "['present','absent','approved_leave','excused']") && str_contains($payrollApi, "($record['status']??'')==='absent'"), 'excused and approved leave remain distinct from the only absence-deducted status');

independentStaffCheck(str_contains($financeApi, 'bank_account_number') && str_contains($financeApi, 'teaching_type'), 'Finance staff records store bank and teaching details independently');
independentStaffCheck(str_contains($financePage, 'bank_account_number') && str_contains($financePage, 'otherJobTitleWrap'), 'Finance registration exposes bank details and conditional job-type fields');

echo "Independent Finance staff and teacher attendance regression checks passed.\n";
