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
$shellCss = file_get_contents(__DIR__ . '/../public/css/management-shell.css');
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
    (str_contains($css, '.dashboard-attendance-category') || str_contains($redesignCss, '.dashboard-attendance-category')) &&
    (str_contains($css, 'linear-gradient(135deg') || str_contains($redesignCss, 'linear-gradient(135deg')) &&
    (str_contains($redesignCss, 'var(--gold-') || str_contains($redesignCss, '#bd953a')),
    'Dashboard surfaces must use the current shared green-and-gold design tokens'
);
$examOverviewStart = strpos($html, '<section class="dashboard-exam-section"');
$attendanceSectionStart = strpos($html, '<section class="dashboard-attendance-panel"', $examOverviewStart === false ? 0 : $examOverviewStart);
$examOverviewHtml = ($examOverviewStart !== false && $attendanceSectionStart !== false)
    ? substr($html, $examOverviewStart, $attendanceSectionStart - $examOverviewStart)
    : '';
dashboardAssert(
    $examOverviewHtml !== '' &&
    substr_count($examOverviewHtml, 'id="dashboardCompletionPie"') === 1 &&
    str_contains($examOverviewHtml, 'Overall Examination Completion') &&
    !str_contains($examOverviewHtml, 'dashboardConventionalPie') &&
    !str_contains($examOverviewHtml, 'dashboardArabicPie') &&
    !str_contains($examOverviewHtml, 'dashboard-chart-card'),
    'Only the overall examination completion overview must remain on the dashboard'
);
dashboardAssert(
    str_contains($redesignCss, '.dashboard-exam-overview') &&
    str_contains($redesignCss, 'grid-template-columns: minmax(0, 560px) minmax(0, 1fr);') &&
    str_contains($redesignCss, '.dashboard-completion-pie'),
    'The overall completion chart must use the current responsive examination overview layout'
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
    str_contains($html, 'href="./finance.html" class="nav-link" data-role-access="management_school_fees"') &&
    str_contains($html, 'href="./attendance.html" class="nav-link" data-role-access="attendance"') &&
    str_contains($js, 'requiredRole === "attendance"') &&
    str_contains($js, 'requiredRole === "management_school_fees"') &&
    str_contains($js, 'permissions.can_manage_attendance === true') &&
    str_contains($js, 'permissions.management_permissions?.attendance === true') &&
    str_contains($js, 'permissions.management_permissions?.school_fees === true'),
    'Attendance and School Fees navigation must follow administrator, assigned teacher and Management permissions'
);
dashboardAssert(
    preg_match('/<button type="button" data-role-access="attendance"><i class="fa-solid fa-calendar-check"[^>]*><\/i><span>Attendance<\/span><\/button>/', $html) === 1 &&
    preg_match('/<button type="button" data-role-access="management_school_fees"><i class="fa-solid fa-wallet"[^>]*><\/i><span>School Fees<\/span><\/button>/', $html) === 1,
    'Attendance and School Fees quick links must use their matching permission rules'
);
dashboardAssert(
    str_contains($js, 'const attendanceAllowed = role === "admin"') &&
    str_contains($js, '(role === "management" && window.markazManagementPermissions?.attendance === true)') &&
    str_contains($js, 'if (!attendanceAllowed)') &&
    str_contains($js, 'panel.hidden = true;'),
    'Dashboard attendance overview must remain limited to administrators and Management accounts with attendance permission'
);
dashboardAssert(
    str_contains($shellCss, 'background:#dff3e7!important;color:#145c38!important') &&
    str_contains($shellCss, 'border-color:#d6b65f!important'),
    'profile avatar colors must remain consistent when navigating between dashboard and management pages'
);
dashboardAssert(
    !str_contains($js, 'refreshButton') &&
    !str_contains($js, 'Refresh dashboard') &&
    !str_contains($css, 'dashboard-refresh-button') &&
    !str_contains($redesignCss, 'dashboard-refresh-button'),
    'dashboard refresh control and its obsolete styles must be fully removed'
);
dashboardAssert(
    str_contains($redesignCss, 'backdrop-filter: saturate(170%) blur(20px)')
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
    str_contains($stats, "'unassigned_students' => \$schoolSummary['unassigned_students']") &&
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
    str_contains($js, 'String(item.class_type || "").trim().toLowerCase() === type') &&
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
