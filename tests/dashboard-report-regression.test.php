<?php
declare(strict_types=1);

function dashboardReportCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$attendanceReport = file_get_contents(__DIR__ . '/../api/attendance/reports.php');
$dashboardScript = file_get_contents(__DIR__ . '/../public/js/dashboard.js');
$dashboardHtml = file_get_contents(__DIR__ . '/../public/dashboard.html');
$dashboardCss = file_get_contents(__DIR__ . '/../public/css/dashboard.css');
$dashboardRedesignCss = file_get_contents(__DIR__ . '/../public/css/dashboard-redesign.css');

dashboardReportCheck(
    str_contains($attendanceReport, "'table' => 'classes'")
        && str_contains($attendanceReport, "'table' => 'arabic_classes'")
        && str_contains($attendanceReport, "LEFT JOIN attendance_records")
        && str_contains($attendanceReport, '$hasClassId = $classId !== false && $classId !== null'),
    'school-wide attendance query includes active classes with zero saved marks and accepts empty filters'
);dashboardReportCheck(
    str_contains($dashboardScript, 'dashboardConventionalAttendance')
        && str_contains($dashboardScript, 'dashboardArabicAttendance')
        && str_contains($dashboardScript, 'dashboard-chart-empty-state') && str_contains($dashboardScript, 'dashboard-chart-error'),
    'attendance error handling replaces permanent loading placeholders'
);
dashboardReportCheck(
    str_contains($dashboardHtml, 'dashboardCompletionPie')
        && !str_contains($dashboardHtml, 'dashboardConventionalPie')
        && !str_contains($dashboardHtml, 'dashboardArabicPie')
        && substr_count($dashboardHtml, 'class="dashboard-chart-card') === 1
        && !str_contains($dashboardScript, 'dashboardRenderClassPie'),
    'the dashboard displays only overall examination completion, without conventional or Arabic class charts'
);
dashboardReportCheck(
    str_contains($dashboardRedesignCss, 'grid-template-columns: minmax(0, 680px) !important;')
        && str_contains($dashboardRedesignCss, '.dashboard-completion-pie'),
    'the remaining overall completion chart is centered and keeps its completion visualization'
);
dashboardReportCheck(
    str_contains($dashboardRedesignCss, '.page-content .welcome-card::before,')
        && str_contains($dashboardRedesignCss, '.page-content .welcome-card::after,')
        && str_contains($dashboardRedesignCss, 'background: linear-gradient(120deg, #123d2a 0%, #17643a 100%) !important;'),
    'the dashboard greeting banner has no decorative curves or radial flourishes'
);

echo "Dashboard report regression tests passed (5 checks).\n";
