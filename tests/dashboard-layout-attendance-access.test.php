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
$redesignCss = file_get_contents(__DIR__ . '/../public/css/dashboard-redesign.css');
$html = file_get_contents(__DIR__ . '/../public/dashboard.html');
$js = file_get_contents(__DIR__ . '/../public/js/dashboard.js');
$stats = file_get_contents(__DIR__ . '/../api/dashboard/stats.php');
$attendance = file_get_contents(__DIR__ . '/../api/attendance/reports.php');
$attendanceRegister = file_get_contents(__DIR__ . '/../api/attendance/register.php');
$attendanceClasses = file_get_contents(__DIR__ . '/../api/attendance/classes.php');

dashboardAssert(
    substr_count($html, 'class="stat-card ') === 3 &&
    !str_contains($css, '.stat-card::after') &&
    !str_contains($css, '.stat-card::before') &&
    !str_contains($redesignCss, '.stat-card::after') &&
    !str_contains($redesignCss, '.stat-card::before'),
    'Dashboard summary cards must be three clean cards with no gold-green decorative pseudo-elements'
);
dashboardAssert(
    str_contains($css, '.dashboard-attendance-category') &&
    str_contains($css, 'linear-gradient(135deg') &&
    str_contains($css, 'rgba(177, 139, 50'),
    'Dashboard surfaces must use a consistent green-and-gold visual system'
);
dashboardAssert(
    str_contains($html, 'dashboard-exam-charts') &&
    str_contains($html, 'dashboardCompletionPie') &&
    !str_contains($html, 'dashboardConventionalPie') &&
    !str_contains($html, 'dashboardArabicPie') &&
    substr_count($html, 'class="dashboard-chart-card') === 1,
    'Only the overall examination completion card must remain on the dashboard'
);
dashboardAssert(
    str_contains($redesignCss, 'grid-template-columns: minmax(0, 680px) !important;') &&
    str_contains($redesignCss, 'justify-content: center;'),
    'The single overall completion card must be centered without category chart columns'
);
dashboardAssert(
    str_contains($html, 'dashboard-attendance-layout') &&
    strpos($html, 'dashboardConventionalAttendanceDonut') < strpos($html, 'dashboardAttendanceDonut') &&
    strpos($html, 'dashboardAttendanceDonut') < strpos($html, 'dashboardArabicAttendanceDonut'),
    'Attendance cards must be ordered Conventional, Whole School, Arabic'
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
    preg_match('/<button type="button" data-role-access="admin"><i class="fa-solid fa-calendar-check"[^>]*><\/i><span>Attendance<\/span><\/button>/', $html) === 1 &&
    preg_match('/<button type="button" data-role-access="admin"><i class="fa-solid fa-wallet"[^>]*><\/i><span>School Fees<\/span><\/button>/', $html) === 1,
    'Quick links for Attendance and School Fees must be restricted to Senior Admin'
);
dashboardAssert(
    str_contains($js, 'if ((window.markazCurrentRole || "") !== "admin")') &&
    str_contains($js, 'panel.hidden = true;'),
    'Dashboard attendance overview must not load for Exam Officer or Teacher'
);
dashboardAssert(
    str_contains($redesignCss, 'backdrop-filter: saturate(165%) blur(18px)')
        && str_contains($redesignCss, 'main-content > footer')
        && str_contains($js, 'link.blur()')
        && !str_contains($js, 'item.classList.add("active")'),
    'dashboard uses a translucent sticky header, bottom-aligned footer and does not preserve a clicked navigation active state'
);
dashboardAssert(
    str_contains($stats, 'SELECT COUNT(*) FROM students WHERE is_active = 1') &&
    str_contains($stats, 'SELECT COUNT(*) FROM classes WHERE is_active = 1') &&
    str_contains($stats, 'SELECT COUNT(*) FROM arabic_classes WHERE is_active = 1') &&
    str_contains($stats, 'SELECT COUNT(*) FROM subjects WHERE is_active = 1') &&
    str_contains($stats, 'r.academic_period_id = ?') &&
    str_contains($stats, '(r.ca1 IS NOT NULL OR r.ca2 IS NOT NULL OR r.exam IS NOT NULL)') &&
    !str_contains($html, 'Score Records') &&
    !str_contains($stats, "'results' =>"),
    'Dashboard headline totals must show only active students, classes and subjects; score-record count is intentionally removed'
);
dashboardAssert(
    str_contains($stats, 'st.arabic_class_id') &&
    str_contains($stats, 'st.arabia_class_id') &&
    str_contains($stats, '$schoolProgressByStudent[$studentId][\'expected_subjects\'] +=') &&
    str_contains($stats, '$schoolProgressByStudent[$studentId][\'completed_subjects\'] +='),
    'Arabic progress must account for both current and legacy Arabic student class columns'
);
dashboardAssert(
    str_contains($js, 'item.class_type === type') &&
    str_contains($attendance, 'ar.class_type = ?') &&
    str_contains($attendance, 'ar.class_id = c.id') &&
    !str_contains($attendance, 'NOT EXISTS') &&
    !str_contains($attendance, 'arabic_class.name') &&
    str_contains($attendanceClasses, "'conventional' AS class_type") &&
    str_contains($attendanceClasses, "'arabic' AS class_type") &&
    !str_contains($attendanceClasses, 'NOT EXISTS') &&
    !str_contains($attendanceClasses, 'arabic_class.name') &&
    str_contains($attendanceRegister, 'ar.class_type = ?') &&
    str_contains($attendanceRegister, 'ar.class_id = ?') &&
    str_contains($attendanceRegister, 's.class_id') &&
    str_contains($attendanceRegister, 's.arabia_class_id') &&
    str_contains($attendanceClasses, 'COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id)'),
    'Attendance class lists, reports and registers must isolate categories by class_type and class_id without name-based exclusions'
);

echo "Dashboard layout, progress separation and role-access tests passed.\n";
