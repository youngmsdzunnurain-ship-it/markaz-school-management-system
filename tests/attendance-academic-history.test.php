<?php
declare(strict_types=1);

function attendanceHistoryCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$migration = file_get_contents(__DIR__ . '/../database/migrations/20261107_attendance_academic_period.sql');
$periodApi = file_get_contents(__DIR__ . '/../api/attendance/periods.php');
$historyApi = file_get_contents(__DIR__ . '/../api/attendance/history.php');
$registerApi = file_get_contents(__DIR__ . '/../api/attendance/register.php');
$page = file_get_contents(__DIR__ . '/../public/attendance.html');
$script = file_get_contents(__DIR__ . '/../public/js/attendance.js');
$style = file_get_contents(__DIR__ . '/../public/css/attendance.css');

attendanceHistoryCheck(str_contains($migration, 'academic_period_id BIGINT UNSIGNED NULL') && str_contains($migration, 'ON DELETE SET NULL'), 'old attendance rows must remain intact and unassigned rather than guessed into a term');
attendanceHistoryCheck(str_contains($periodApi, 'is_current') && str_contains($periodApi, 'is_finalized'), 'period selector must expose current and finalised periods');
attendanceHistoryCheck(str_contains($registerApi, 'academic_period_id') && str_contains($registerApi, 'is_finalized'), 'daily attendance saves must be linked to an open academic period');
attendanceHistoryCheck(str_contains($historyApi, 'academic_period_id = ?') && str_contains($historyApi, 'attended_count') && str_contains($historyApi, 'attendance_rate') && str_contains($historyApi, 'present_count') && str_contains($historyApi, 'absent_count') && str_contains($historyApi, 'late_count') && str_contains($historyApi, 'excused_count'), 'history must calculate per-student status totals for the selected term');
attendanceHistoryCheck(str_contains($historyApi, "'week', 'month', 'term'") && str_contains($historyApi, 'monday this week') && str_contains($historyApi, 'first day of this month') && str_contains($historyApi, "format('l')"), 'history must support weekly, monthly and term views with weekday names');
attendanceHistoryCheck(str_contains($page, 'attendancePeriod') && str_contains($page, 'historyView') && str_contains($page, 'attendanceHistoryOutput'), 'page must expose term selection and historical register controls');
attendanceHistoryCheck(str_contains($script, 'academic_period_id: Number(state.periodId)') && str_contains($script, 'loadAttendanceHistory') && str_contains($script, 'attended_count'), 'browser must submit selected term and render student totals');
attendanceHistoryCheck(str_contains($style, 'attendance-history-table') && str_contains($style, 'position: sticky'), 'historical register must keep student identity visible while scrolling');

echo "Attendance academic-period and historical register regression checks passed.\n";
