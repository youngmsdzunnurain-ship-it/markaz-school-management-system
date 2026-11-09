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
$classesApi = file_get_contents(__DIR__ . '/../api/attendance/classes.php');
$periodProxy = file_get_contents(__DIR__ . '/../public/api/attendance/periods.php');
$historyProxy = file_get_contents(__DIR__ . '/../public/api/attendance/history.php');
$page = file_get_contents(__DIR__ . '/../public/attendance.html');
$script = file_get_contents(__DIR__ . '/../public/js/attendance.js');
$style = file_get_contents(__DIR__ . '/../public/css/attendance.css');

attendanceHistoryCheck(str_contains($migration, 'academic_period_id BIGINT UNSIGNED NULL') && str_contains($migration, 'ON DELETE SET NULL'), 'old attendance rows must remain intact and unassigned rather than guessed into a term');
attendanceHistoryCheck(str_contains($periodApi, 'is_current') && str_contains($periodApi, 'is_finalized'), 'period selector must expose current and finalised periods');
attendanceHistoryCheck(str_contains($registerApi, 'academic_period_id') && str_contains($registerApi, 'is_finalized'), 'daily attendance saves must be linked to an open academic period');
attendanceHistoryCheck(str_contains($historyApi, 'academic_period_id = ?') && str_contains($historyApi, 'attended_count') && str_contains($historyApi, 'attendance_rate') && str_contains($historyApi, 'present_count') && str_contains($historyApi, 'absent_count') && str_contains($historyApi, 'late_count') && str_contains($historyApi, 'excused_count'), 'history must calculate per-student status totals for the selected term');
attendanceHistoryCheck(str_contains($historyApi, "'week', 'month', 'term'") && str_contains($historyApi, 'monday this week') && str_contains($historyApi, 'first day of this month') && str_contains($historyApi, "format('l')"), 'history must support weekly, monthly and term views with weekday names');
attendanceHistoryCheck(str_contains($periodProxy, "api/attendance/periods.php") && str_contains($historyProxy, "api/attendance/history.php"), 'public attendance page API routes must resolve period and history endpoints');
attendanceHistoryCheck(str_contains($classesApi, "\$classTypeFilter") && str_contains($classesApi, "\$class['class_type'] === \$classTypeFilter") && str_contains($classesApi, "'available_class_types'") && str_contains($script, 'availableClassTypes') && str_contains($script, 'item.class_type === type') && str_contains($script, 'attendanceClassTypeField'), 'conventional and Arabic class lists remain isolated while teachers see only their assigned categories');
attendanceHistoryCheck(str_contains($page, 'attendancePeriod') && str_contains($page, 'historyView') && str_contains($page, 'attendanceHistoryOutput'), 'page must expose term selection and historical register controls');
attendanceHistoryCheck(str_contains($script, 'academic_period_id: Number(state.periodId)') && str_contains($script, 'loadAttendanceHistory') && str_contains($script, 'attended_count'), 'browser must submit selected term and render student totals');
attendanceHistoryCheck(str_contains($style, 'attendance-history-table') && str_contains($style, 'position: sticky'), 'historical register must keep student identity visible while scrolling');
attendanceHistoryCheck(str_contains($page, 'attendance-overview-layout') && substr_count($page, 'attendance-summary-grid') === 1 && strpos($page, 'attendance-summary-grid') > strpos($page, 'attendance-chart-panel'), 'attendance chart and five summary cards must share one overview panel near the top');
attendanceHistoryCheck(str_contains($style, 'attendance-overview-layout') && str_contains($style, '@media (max-width: 720px)') && !str_contains($style, '!important'), 'attendance overview must use responsive consolidated CSS without override patches');
attendanceHistoryCheck(str_contains($classesApi, "'conventional' AS class_type") && str_contains($classesApi, "'arabic' AS class_type") && str_contains($classesApi, "\$class['class_type'] === \$classTypeFilter"), 'attendance class list keeps conventional and Arabic records separated by explicit class type');
attendanceHistoryCheck(str_contains($page, 'attendance.css?v=20261108-overview1') && str_contains($page, 'attendance.js?v=20261108-overview1'), 'attendance assets must use fresh versions so mobile browsers do not reuse stale layout or class-list code');

echo "Attendance academic-period and historical register regression checks passed.\n";
