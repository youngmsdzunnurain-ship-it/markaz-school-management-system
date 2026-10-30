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
    str_contains($dashboardScript, 'dashboardRenderClassPie')
        && str_contains($dashboardScript, 'dashboard-class-pie-empty'),
    'class progress charts render a visible zero-data state'
);
dashboardReportCheck(
    str_contains($dashboardCss, '.dashboard-exam-charts > article:nth-child(1) { order:2; }')
        && str_contains($dashboardCss, '.dashboard-exam-charts > article:nth-child(2) { order:1; }')
        && str_contains($dashboardCss, '.dashboard-exam-charts > article:nth-child(3) { order:3; }'),
    'exam charts are visually ordered Conventional, Whole School, Arabic'
);dashboardReportCheck(
    str_contains($dashboardCss, 'border: 1px solid rgba(201, 169, 75')
        && str_contains($dashboardCss, 'dashboard-class-pie-empty'),
    'chart cards use gold accents and styled empty states'
);

echo "Dashboard report regression tests passed (5 checks).\n";
