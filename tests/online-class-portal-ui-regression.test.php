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
$workspaceJs = (string) file_get_contents($root . '/public/js/online-class.js');
$dashboard = (string) file_get_contents($root . '/public/dashboard.html');
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
    substr_count($login, 'id="onlineSection"') === 1 &&
    substr_count($login, 'id="onlineTerm"') === 1 &&
    substr_count($login, 'id="onlineSession"') === 1 &&
    substr_count($login, 'name="academic_session"') === 1 &&
    !str_contains($login, 'online-portal-switch') &&
    !str_contains($portal, 'online-portal-home') &&
    str_contains($login, 'js/login-shared.js') && str_contains($portal, 'markaz-shared-login-footer') &&
    str_contains($portal, 'id="onlineSupportLink"') && !str_contains($login, 'online-support-footer') &&
    !str_contains($portal, 'online-support-footer') && !str_contains($login, '+234 807') &&
    !str_contains($portal, '+234 807') && !str_contains($studentLogin, 'online-portal-switch'),
    'Student login must contain exactly five fields, remove cross-portal links and provide support footers'
);
onlinePortalUiAssert(
    str_contains($loginJs, 'function populateAcademicSessions()') &&
    str_contains($loginJs, 'Array.from({ length: 10 }') &&
    str_contains($loginJs, 'academic_session:') && str_contains($loginJs, "onlineSession').value") &&
    str_contains($loginJs, 'function isAcademicSession(value)') &&
    str_contains($loginJs, 'api/online-class/portal-options.php') &&
    str_contains($loginJs, 'level_id') &&
    str_contains($loginJs, 'section'),
    'Student login must automatically supply the current academic session and load registered sections'
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
    strpos($workspace, 'online-welcome-card') < strpos($workspace, 'id="onlineLevelStats"') &&
    substr_count($workspace, 'data-tab-link=') === 6 &&
    !str_contains($workspace, 'class="online-tabs"') &&
    str_contains($workspace, 'id="sidebarOverlay"') &&
    str_contains($workspace, 'id="menuButton"') &&
    str_contains($workspace, 'id="resultSession"') &&
    preg_match('/<select[^>]*id="resultSession"/', $workspace) === 1,
    'Staff workspace must use the shared watermark, responsive shell and session dropdown'
);
onlinePortalUiAssert(
    str_contains($workspaceJs, 'function loadWorkspaceGreeting()') &&
    str_contains($workspaceJs, './api/auth/me.php') &&
    str_contains($workspaceJs, 'function populateResultSessions()') &&
    str_contains($workspaceJs, 'const first=2026;') &&
    str_contains($workspaceJs, 'populateResultSessions();') &&
    str_contains($workspaceJs, "event.key==='Escape'") &&
    str_contains($workspaceJs, "sidebarOverlay") &&
    str_contains($workspaceJs, 'function closeSidebar()'),
    'Staff sessions must begin in 2026 and sidebar must support overlay and Escape dismissal'
);
onlinePortalUiAssert(
    str_contains($onlineCss, '.online-workspace-page .topbar{position:sticky') &&
    str_contains($onlineCss, '.online-workspace-page .app{grid-template-columns:260px minmax(0,1fr)') &&
    !str_contains($onlineCss, 'margin-left:280px') &&
    str_contains($onlineCss, '.online-results-report::before') &&
    str_contains($login, 'css/login-shared.css') &&
    str_contains($portal, 'css/app-watermark.css'),
    'Online Class pages and reports must show the MARKAZ watermark with responsive sidebar states'
);
onlinePortalUiAssert(
    strpos($dashboard, '<div class="welcome-card">') < strpos($dashboard, '<section class="stats-grid"') &&
    strpos($dashboard, '<section class="stats-grid"') < strpos($dashboard, 'id="teacherAssignmentsSection"'),
    'Dashboard greeting must precede student, class and subject totals'
);

foreach (['index.php','portal-options.php','student-login.php','student-results.php','student-footer.php','footer.php','logout.php'] as $file) {
    onlinePortalUiAssert(
        is_file($root . '/public/api/online-class/' . $file),
        'Public Online Class endpoint must forward ' . $file
    );
}

echo "Online Class student portal and responsive shell regression checks passed.\n";
