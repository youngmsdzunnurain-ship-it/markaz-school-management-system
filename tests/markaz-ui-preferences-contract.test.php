<?php
declare(strict_types=1);

/**
 * Static contract checks for the MARKAZ language and persistent navigation layer.
 * Run: php tests/markaz-ui-preferences-contract.test.php
 */
$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        throw new RuntimeException("Missing required file: {$path}");
    }
    return (string) file_get_contents($full);
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pages = [
    'public/dashboard.html',
    'public/attendance.html',
    'public/classes.html',
    'public/subjects/index.html',
    'public/students.html',
    'public/school-fees.html',
    'public/settings.html',
    'public/exam-officer-login.html',
    'public/teacher-login.html',
    'public/print-results.html',
    'public/student-result.html',
    'public/class.html',
    'public/class-subjects/index.html',
];
foreach ($pages as $page) {
    $html = $read($page);
    $relative = in_array($page, ['public/subjects/index.html', 'public/class-subjects/index.html'], true) ? '../' : './';
    $assert(str_contains($html, $relative . 'js/markaz-i18n.js'), "{$page}: language script missing");
    $assert(str_contains($html, $relative . 'css/markaz-preferences.css'), "{$page}: preference stylesheet missing");
}
$adminLogin = $read('public/login.html');
$studentLogin = $read('public/student-login.html');
$examOfficerLogin = $read('public/exam-officer-login.html');
$teacherLogin = $read('public/teacher-login.html');
$attendanceHtml = $read('public/attendance.html');
$attendanceJs = $read('public/js/attendance.js');
$attendanceApi = $read('api/attendance/classes.php');
$themeJs = $read('public/js/markaz-theme.js');
$profileMenuJs = $read('public/js/dashboard-profile-menu.js');
$assert(!str_contains($adminLogin, 'markaz-i18n.js'), 'Administrator login must not show language controls');
$assert(!str_contains($studentLogin, 'markaz-i18n.js') && !str_contains($studentLogin, 'markaz-theme.js'), 'Student login must not show language or theme controls');
$assert(str_contains($examOfficerLogin, 'staff-login-preferences') && str_contains($teacherLogin, 'staff-login-preferences'), 'Staff login preference controls are missing');
$assert(str_contains($dashboard, 'dashboardProfileMenu') && str_contains($profileMenuJs, 'mountControls'), 'Dashboard avatar preference menu is missing');
$assert(str_contains($shell, 'data-markaz-preferences') && str_contains($shell, 'markaz-theme.js'), 'Staff profile menu must mount shared preferences');
$assert(str_contains($i18n, 'mountControls:controls') && !str_contains($i18n, 'createElement("select")'), 'Language must be a direct toggle, not a dropdown');
$assert(str_contains($themeJs, 'data-markaz-theme-toggle') && str_contains($themeJs, 'markaz-dashboard-theme'), 'Fast persistent theme toggle is missing');
$assert(str_contains($attendanceHtml, 'id="schoolTotalCount"'), 'Attendance school-wide student total card is missing');
$assert(str_contains($attendanceJs, 'school_student_total') || str_contains($attendanceJs, 'schoolStudentTotal'), 'Attendance school-wide total is not wired');
$assert(str_contains($attendanceApi, 'school_student_total'), 'Attendance bootstrap API must return school-wide active student total');
$assert(!str_contains($attendanceApi, 'NOT EXISTS ('), 'Conventional classes must not be hidden by matching Arabic class names');
$assert(str_contains($attendanceJs, 'String(item.class_type).toLowerCase()'), 'Attendance class list must filter using normalized class type');
$dashboard = $read('public/dashboard.html');
$assert(str_contains($dashboard, 'data-role-check-pending="true"'), 'Dashboard role-check gate was removed');
$assert(str_contains($dashboard, './js/markaz-theme.js'), 'Dashboard-only theme script missing');
$assert(!str_contains($dashboard, 'app-watermark.css'), 'Legacy dashboard watermark must stay disabled');

$shell = $read('public/js/management-shell.js');
$shellCss = $read('public/css/management-shell.css');
$preferences = $read('public/css/markaz-preferences.css');
$i18n = $read('public/js/markaz-i18n.js');
$dashboardJs = $read('public/js/dashboard.js');
$assert(str_contains($shell, 'position') || str_contains($shell, 'markaz-management-shell'), 'Management shell is missing');
$assert(str_contains($preferences, '.markaz-management-shell{position:fixed'), 'Persistent shell header must be fixed');
$assert(str_contains($preferences, '.attendance-header'), 'Attendance header sticky override missing');
$assert(str_contains($preferences, 'top:68px!important'), 'Page heading must sit below the fixed navigation');
$assert(str_contains($preferences, 'background:rgba(255,255,255,.94)!important'), 'Old green page hero headers must be neutralized');
$assert(str_contains($preferences, 'position:sticky!important;top:0!important'), 'Dashboard topbar must remain sticky');
$assert(str_contains($preferences, 'content:none!important'), 'Old green header decorations must be removed');
$assert(str_contains($i18n, 'document.documentElement.dir=lang==="ar"?"rtl":"ltr"'), 'Arabic RTL direction is missing');
$assert(str_contains($i18n, 'data-no-translate'), 'Database values must be protected from translation');
$assert(str_contains($dashboardJs, 'renderDashboardAttendanceComboChart'), 'Attendance combo chart renderer missing');
$assert(str_contains($dashboardJs, 'data-no-translate'), 'Database-derived chart labels must be protected');
$assert(str_contains($dashboard, 'dashboardConventionalAttendanceChart'), 'Conventional attendance chart container missing');
$assert(str_contains($dashboard, 'dashboardArabicAttendanceChart'), 'Arabic attendance chart container missing');

echo "MARKAZ UI preference contract checks passed.\n";
