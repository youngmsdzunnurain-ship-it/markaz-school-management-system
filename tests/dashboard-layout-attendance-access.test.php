<?php
declare(strict_types=1);

function dashboardAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$css = file_get_contents(__DIR__ . '/../public/css/dashboard.css');
$html = file_get_contents(__DIR__ . '/../public/dashboard.html');
$js = file_get_contents(__DIR__ . '/../public/js/dashboard.js');
$stats = file_get_contents(__DIR__ . '/../api/dashboard/stats.php');
$attendance = file_get_contents(__DIR__ . '/../api/attendance/reports.php');
$attendanceRegister = file_get_contents(__DIR__ . '/../api/attendance/register.php');
$attendanceClasses = file_get_contents(__DIR__ . '/../api/attendance/classes.php');

dashboardAssert(
    str_contains($css, '.dashboard-attendance-category') &&
    str_contains($css, 'linear-gradient(135deg') &&
    str_contains($css, 'rgba(177, 139, 50'),
    'Dashboard surfaces must use a consistent green-and-gold visual system'
);
dashboardAssert(
    str_contains($css, 'grid-template-columns: minmax(260px, 35fr) minmax(230px, 30fr) minmax(260px, 35fr)'),
    'Examination cards must use 35/30/35 columns in Conventional, Whole School, Arabic order'
);
dashboardAssert(
    str_contains($html, 'dashboard-exam-charts') &&
    strpos($html, 'dashboardConventionalPie') < strpos($html, 'dashboardCompletionPie') &&
    strpos($html, 'dashboardCompletionPie') < strpos($html, 'dashboardArabicPie'),
    'Examination cards must be ordered Conventional, Whole School, Arabic'
);
dashboardAssert(
    str_contains($html, 'dashboard-attendance-layout') &&
    strpos($html, 'dashboardArabicAttendanceDonut') < strpos($html, 'dashboardAttendanceDonut') &&
    strpos($html, 'dashboardAttendanceDonut') < strpos($html, 'dashboardConventionalAttendanceDonut'),
    'Attendance cards must be ordered Arabic, Whole School, Conventional'
);
dashboardAssert(
    preg_match('/result-progress-grid[^}]*grid-template-columns:\s*repeat\(4,\s*minmax\(0,\s*1fr\)\)/s', $css) === 1,
    'Four examination status figures must remain on one row at dashboard widths'
);
dashboardAssert(
    str_contains($html, 'href="./school-fees.html" class="nav-link" data-role-access="admin"') &&
    str_contains($html, 'href="./attendance.html" class="nav-link" data-role-access="admin"'),
    'School Fees and Attendance navigation must be restricted to Senior Admin'
);
dashboardAssert(
    str_contains($html, 'data-role-access="admin">Attendance</button>') &&
    str_contains($html, 'data-role-access="admin">School Fees</button>'),
    'Quick links for Attendance and School Fees must be restricted to Senior Admin'
);
dashboardAssert(
    str_contains($js, 'if(role!=="admin"){panel.hidden=true;return;} panel.hidden=false;'),
    'Dashboard attendance overview must not load for Exam Officer or Teacher'
);
dashboardAssert(
    str_contains($stats, 'st.arabic_class_id') &&
    str_contains($stats, 'st.arabia_class_id') &&
    str_contains($stats, '$schoolProgressByStudent[$studentId][\'expected_subjects\'] +=') &&
    str_contains($stats, '$schoolProgressByStudent[$studentId][\'completed_subjects\'] +='),
    'Arabic progress must account for both current and legacy Arabic student class columns'
);
dashboardAssert(
    str_contains($js, 'class_type=conventional') &&
    str_contains($js, 'class_type=arabic') &&
    str_contains($js, 'categoryReports[type]') &&
    str_contains($attendance, "ar.class_type = ?") &&
    str_contains($attendance, 'FROM arabic_classes arabic_class') &&
    str_contains($attendance, 'LOWER(TRIM(arabic_class.name)) = LOWER(TRIM(c.name))') &&
    str_contains($attendanceClasses, 'NOT EXISTS') &&
    str_contains($attendanceClasses, 'LOWER(TRIM(arabic_class.name)) = LOWER(TRIM(c.name))') &&
    substr_count($attendanceClasses, 'FROM arabic_classes arabic_class') === 2 &&
    str_contains($attendance, "ar.class_id = c.id") &&
    str_contains($attendanceRegister, 's.arabia_class_id') &&
    substr_count($attendanceClasses, 's.arabia_class_id') >= 2,
    'Attendance report queries must isolate each category by class_type and class_id'
);

echo "Dashboard layout, progress separation and role-access tests passed.\n";
