<?php
declare(strict_types=1);

function onlinePortalUiAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$login = (string) file_get_contents($root . '/public/online-class-login.html');
$loginJs = (string) file_get_contents($root . '/public/js/online-class-login.js');
$portal = (string) file_get_contents($root . '/public/online-class-portal.html');
$workspace = (string) file_get_contents($root . '/public/online-class.html');
$studentLogin = (string) file_get_contents($root . '/public/student-login.html');
$studentAuth = (string) file_get_contents($root . '/api/online-class/student-login.php');
$portalOptions = (string) file_get_contents($root . '/api/online-class/portal-options.php');
$workspaceJs = (string) file_get_contents($root . '/public/js/online-class.js');
$portalJs = (string) file_get_contents($root . '/public/js/online-class-portal.js');
$workspaceApi = (string) file_get_contents($root . '/api/online-class/index.php');
$dashboard = (string) file_get_contents($root . '/public/dashboard.html');
$dashboardCss = (string) file_get_contents($root . '/public/css/dashboard-2026.css');
$onlineCss = (string) file_get_contents($root . '/public/css/online-class.css');

onlinePortalUiAssert(
    str_contains($login, 'css/login-roles.css') &&
    str_contains($login, 'css/login-shared.css') &&
    str_contains($login, 'class="role-login-page online-login-page"') &&
    str_contains($login, 'class="role-login-card student-login-card online-login-card"'),
    'Online Class login must reuse the established MARKAZ login visual system'
);
onlinePortalUiAssert(
    substr_count($login, 'id="onlineAdmission"') === 1 &&
    substr_count($login, 'id="onlineLevel"') === 1 &&
    substr_count($login, 'id="onlineTerm"') === 1 &&
    substr_count($login, 'id="onlineSession"') === 1 &&
    substr_count($login, 'name="academic_session"') === 1 &&
    !str_contains($login, 'online-portal-switch') &&
    !str_contains($portal, 'online-portal-home') &&
    str_contains($login, 'js/login-shared.js') && str_contains($portal, 'markaz-shared-login-footer') &&
    str_contains($portal, 'id="onlineSupportLink"') && !str_contains($login, 'online-support-footer') &&
    !str_contains($portal, 'online-support-footer') && !str_contains($login, '+234 807') &&
    !str_contains($portal, '+234 807') && !str_contains($studentLogin, 'online-portal-switch') &&
    !str_contains($login, 'onlineSection'),
    'Student login must contain the four requested fields, remove cross-portal links and provide support footers'
);
onlinePortalUiAssert(
    str_contains($loginJs, 'function populateAcademicSessions()') &&
    str_contains($loginJs, 'Array.from({ length: 10 }') &&
    str_contains($loginJs, 'academic_session:') && str_contains($loginJs, "onlineSession').value") &&
    str_contains($loginJs, 'api/online-class/portal-options.php') &&
    str_contains($loginJs, 'level_id') &&
    !str_contains($loginJs, 'onlineSection') &&
    !str_contains($loginJs, 'section:') &&
    !str_contains($studentAuth, "\$input['section']") &&
    !str_contains($studentAuth, 's.section=?') &&
    !str_contains($portalOptions, "'sections'"),
    'Student login must use admission number, level, session and term without a section field'
);
onlinePortalUiAssert(
    str_contains($portal, 'id="portalReport"') &&
    str_contains($portal, 'id="portalResultsRows"') &&
    str_contains($portal, 'id="portalLogout"') &&
    str_contains($portal, 'css/app-watermark.css') &&
    str_contains($portal, 'Attendance /20') &&
    str_contains($portal, 'Test /20') &&
    str_contains($portal, 'Final Exam /60') &&
    str_contains($portal, 'Total /100'),
    'Student report must retain the report card, watermark and 20/20/60 scoring layout'
);
onlinePortalUiAssert(
    str_contains($workspace, 'css/app-watermark.css') &&
    str_contains($workspace, 'class="welcome-card online-welcome-card"') &&
    str_contains($workspace, 'class="dashboard-footer"') &&
    strpos($workspace, 'online-welcome-card') < strpos($workspace, 'id="onlineLevelStats"') &&
    substr_count($workspace, 'data-tab-link=') === 6 &&
    !str_contains($workspace, 'class="online-tabs"') &&
    str_contains($workspace, 'id="sidebarOverlay"') &&
    str_contains($workspace, 'id="menuButton"') &&
    str_contains($workspace, 'id="resultSession"') &&
    str_contains($workspace, '<select id="resultStudentName"') &&
    str_contains($workspace, '<select id="resultSubjectName"') &&
    str_contains($workspace, 'id="resultTotal"') &&
    str_contains($workspace, 'id="markAllPresent"') &&
    preg_match('/<select[^>]*id="resultSession"/', $workspace) === 1,
    'Staff workspace must use the shared watermark, responsive shell and session dropdown'
);
onlinePortalUiAssert(
    str_contains($workspaceJs, 'function loadWorkspaceGreeting()') &&
    str_contains($workspaceJs, './api/auth/me.php') &&
    str_contains($workspaceJs, "const d=await request(API+'?action=bootstrap')") &&
    !str_contains($workspaceJs, "Promise.all([request(API+'?action=bootstrap')") &&
    str_contains($workspaceJs, 'function populateResultSessions()') &&
    str_contains($workspaceJs, 'const first=2026;') &&
    str_contains($workspaceJs, 'populateResultSessions();') &&
    str_contains($workspaceJs, "event.key==='Escape'") &&
    str_contains($workspaceJs, "sidebarOverlay") &&
    str_contains($workspaceJs, 'function closeSidebar()') &&
    str_contains($workspaceJs, 'async function populateResultEntryOptions()') &&
    str_contains($workspaceJs, 'function updateResultCalculations()') &&
    str_contains($workspaceJs, 'Mashallah, Barakallahu fi'),
    'Staff sessions must begin in 2026 and sidebar must support overlay and Escape dismissal'
);
onlinePortalUiAssert(
    str_contains($onlineCss, '.online-workspace-page .topbar{position:sticky') &&
    str_contains($onlineCss, '.online-workspace-page .app{grid-template-columns:260px minmax(0,1fr)') &&
    !str_contains($onlineCss, 'margin-left:280px') &&
    str_contains($onlineCss, '.online-results-report::before') &&
    str_contains($login, 'css/login-shared.css') &&
    str_contains($portal, 'css/app-watermark.css') &&
    str_contains($onlineCss, 'grid-template-columns:260px minmax(0,1fr)') &&
    str_contains($onlineCss, 'padding:18px'),
    'Online Class pages and reports must show the MARKAZ watermark with responsive sidebar states'
);
onlinePortalUiAssert(
    strpos($dashboard, '<div class="welcome-card">') < strpos($dashboard, '<section class="stats-grid"') &&
    strpos($dashboard, '<section class="stats-grid"') < strpos($dashboard, 'id="teacherAssignmentsSection"'),
    'Dashboard greeting must precede student, class and subject totals'
);


onlinePortalUiAssert(
    str_contains($workspaceApi, "if (\$action === 'subjects')") &&
    str_contains($workspaceApi, 'WHERE level_id = ? AND is_active = 1') &&
    str_contains($workspaceJs, "request(API+'?action=subjects'+query)") &&
    str_contains($workspaceJs, 'Promise.allSettled') &&
    str_contains($workspaceJs, 'resultOptionsRequest'),
    'Result-entry subjects must load independently from results and be filtered by the selected Online Class level'
);
onlinePortalUiAssert(
    str_contains($dashboardCss, '.topbar>.topbar-actions{flex:0 0 auto') &&
    str_contains($dashboardCss, 'margin-left:auto'),
    'The dynamically inserted admin profile control must remain aligned to the top-right at responsive widths'
);

foreach (['index.php','portal-options.php','student-login.php','student-results.php','student-footer.php','footer.php','logout.php'] as $file) {
    onlinePortalUiAssert(
        is_file($root . '/public/api/online-class/' . $file),
        'Public Online Class endpoint must forward ' . $file
    );
}


onlinePortalUiAssert(
    str_contains($workspaceApi, "if(\$action==='set_student_status')") &&
    str_contains($workspaceApi, 'UPDATE online_class_students SET is_active=? WHERE id=?') &&
    str_contains($workspaceApi, 'Online student account deactivated.') &&
    str_contains($workspaceApi, 'Online student account reactivated.'),
    'Online Class students must have a dedicated, validated deactivate/reactivate endpoint'
);
onlinePortalUiAssert(
    str_contains($workspaceJs, 'data-toggle-student=') &&
    str_contains($workspaceJs, 'async function setStudentStatus') &&
    str_contains($workspaceJs, 'Deactivate') &&
    str_contains($workspaceJs, 'Reactivate'),
    'Student list must expose working deactivate and reactivate actions'
);
onlinePortalUiAssert(
    str_contains($workspaceJs, 'dataset.status=value') &&
    str_contains($onlineCss, '.attendance-status[data-status="present"]') &&
    str_contains($onlineCss, '.attendance-status[data-status="absent"]') &&
    str_contains($onlineCss, '.attendance-status[data-status="late"]') &&
    str_contains($onlineCss, '.attendance-status[data-status="excused"]'),
    'Attendance status controls must use clear, accessible status-specific colours'
);
onlinePortalUiAssert(
    preg_match('/@page\\s*\\{[^}]*size:\\s*A4\\s+landscape/s', $onlineCss) === 1 &&
    str_contains($onlineCss, '#onlineReport') &&
    str_contains($workspaceJs, 'Attendance</th><th>Test</th><th>Final Examination</th>'),
    'Staff Online Class reports must print as A4 landscape with Attendance, Test and Final Examination columns'
);

onlinePortalUiAssert(
    str_contains($onlineCss, 'body.online-workspace-page *{visibility:hidden!important}') &&
    str_contains($onlineCss, '#onlineReport,#onlineReport *{visibility:visible!important}') &&
    str_contains($onlineCss, 'body.online-results-page *{visibility:hidden!important}') &&
    str_contains($onlineCss, '#portalReport,#portalReport *{visibility:visible!important}') &&
    str_contains($onlineCss, 'height:200mm') &&
    str_contains($onlineCss, 'overflow:hidden!important'),
    'Staff and student reports must each print alone on one constrained A4 landscape page'
);
onlinePortalUiAssert(
    str_contains($workspace, 'Enabling student login does not release results') &&
    str_contains($workspaceJs, 'function formatOnlineReportDate(value)') &&
    str_contains($workspaceJs, 'formatOnlineReportDate(resumption)') &&
    str_contains($workspaceJs, 'Results are RELEASED to students.') &&
    str_contains($portalJs ?? '', 'function formatOnlineReportDate(value)'),
    'Online Class release controls and resumption dates must be explicit and consistently formatted'
);
echo "Online Class student portal and responsive shell regression checks passed.\n";
