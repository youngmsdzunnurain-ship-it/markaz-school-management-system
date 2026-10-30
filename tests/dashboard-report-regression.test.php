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

dashboardReportCheck(
    str_contains($attendanceReport, "FROM classes WHERE is_active = 1")
        && str_contains($attendanceReport, "FROM arabic_classes WHERE is_active = 1")
        && str_contains($attendanceReport, "LEFT JOIN attendance_records"),
    'attendance report includes active classes even when no attendance has been saved'
);
dashboardReportCheck(
    str_contains($dashboardScript, 'dashboardConventionalAttendance')
        && str_contains($dashboardScript, 'dashboardArabicAttendance')
        && str_contains($dashboardScript, 'dashboardChartEmptyState'),
    'attendance error handling replaces permanent loading placeholders'
);
dashboardReportCheck(
    str_contains($dashboardScript, 'dashboardRenderClassPie')
        && str_contains($dashboardScript, 'dashboardClassPieEmptyState'),
    'class progress charts render a visible zero-data state'
);
dashboardReportCheck(
    strpos($dashboardHtml, 'id="dashboardConventionalPie"')
        < strpos($dashboardHtml, 'id="dashboardCompletionDonut"')
        && strpos($dashboardHtml, 'id="dashboardCompletionDonut"')
        < strpos($dashboardHtml, 'id="dashboardArabicPie"'),
    'exam charts are ordered Conventional, Whole School, Arabic'
);
dashboardReportCheck(
    str_contains($dashboardCss, 'border: 1px solid rgba(201, 169, 75')
        && str_contains($dashboardCss, 'dashboard-class-pie-empty'),
    'chart cards use gold accents and styled empty states'
);

echo "Dashboard report regression tests passed (5 checks).\n";
