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
$html = (string) file_get_contents($root . '/public/online-class-login.html');
$js = (string) file_get_contents($root . '/public/js/online-class-login.js');
$levels = (string) file_get_contents($root . '/database/migrations/20261110_markaz_online_class.sql');
$levelSeed = (string) file_get_contents($root . '/database/migrations/20261110_online_class_default_levels.sql');
$portal = (string) file_get_contents($root . '/public/online-class-portal.html');
$workspace = (string) file_get_contents($root . '/public/online-class.html');
$workspaceJs = (string) file_get_contents($root . '/public/js/online-class.js');
$dashboard = (string) file_get_contents($root . '/public/dashboard.html');
$dashboardJs = (string) file_get_contents($root . '/public/js/dashboard.js');
$onlineCss = (string) file_get_contents($root . '/public/css/online-class.css');
$watermarkCss = (string) file_get_contents($root . '/public/css/app-watermark.css');

onlinePortalUiAssert(
    str_contains($html, 'css/login-roles.css') &&
    str_contains($html, 'css/login-shared.css') &&
    str_contains($html, 'class="role-login-page"') &&
    str_contains($html, 'class="role-login-card student-login-card online-login-card"'),
    'Online Class login must reuse the established MARKAZ student login visual system'
);
onlinePortalUiAssert(
    substr_count($html, 'id="onlineAdmission"') === 1 &&
    substr_count($html, 'id="onlineLevel"') === 1 &&
    substr_count($html, 'id="onlineSection"') === 1 &&
    substr_count($html, 'id="onlineSession"') === 1 &&
    substr_count($html, 'id="onlineTerm"') === 1,
    'Student login must render one admission, level, section, academic-session and term field without duplicate section controls'
);
onlinePortalUiAssert(
    str_contains($js, 'api/online-class/portal-options.php') &&
    str_contains($js, 'function populateSessions()') &&
    str_contains($js, 'const firstYear = 2026;') &&
    str_contains($js, 'const lastYear = Math.max(currentYear + 10, 2036);') &&
    str_contains($js, 'onlineSession') &&
    str_contains($js, 'level_id') &&
    str_contains($js, 'section'),
    'Student portal must load registered sections and offer academic sessions beginning in 2026'
);
onlinePortalUiAssert(
    str_contains($levels, "('Level 1', 1), ('Level 2', 2), ('Level 3', 3), ('Level 4', 4)") &&
    str_contains($levelSeed, "('Level 1', 1)") && str_contains($levelSeed, "('Level 4', 4)") &&
    str_contains($levelSeed, 'INSERT IGNORE INTO online_class_levels'),
    'Four initial levels must remain safely seeded while additional levels stay supported'
);
onlinePortalUiAssert(
    str_contains($portal, 'id="portalReport"') &&
    str_contains($portal, 'id="portalResultsRows"') &&
    str_contains($portal, 'id="portalLogout"') &&
    str_contains($portal, 'css/app-watermark.css'),
    'Student results, report rendering, logout and shared MARKAZ watermark must remain available'
);
onlinePortalUiAssert(
    str_contains($html, 'css/online-class.css') &&
    str_contains($onlineCss, '.online-login-page::before') &&
    str_contains($onlineCss, '.online-results-page::before'),
    'Online Class login and student result pages must show the subtle MARKAZ watermark'
);

onlinePortalUiAssert(
    str_contains($workspace, 'css/app-watermark.css') &&
    str_contains($workspace, 'id="sidebarOverlay"') &&
    str_contains($workspace, 'id="menuButton"') &&
    str_contains($workspaceJs, 'function syncSidebarForViewport') &&
    str_contains($workspaceJs, 'sidebarOverlay') &&
    str_contains($workspaceJs, "event.key==='Escape'") &&
    str_contains($onlineCss, '.online-workspace-page .app > .main-content > header.topbar{position:sticky') &&
    str_contains($onlineCss, '.online-workspace-page .sidebar.open'),
    'Workspace sidebar, overlay, Escape handling and sticky topbar must follow the responsive MARKAZ shell'
);
onlinePortalUiAssert(
    !str_contains($workspace, 'class="logout-button"'),
    'Online Class workspace sidebar must not show a redundant logout listing'
);
onlinePortalUiAssert(
    str_contains($onlineCss, '.online-workspace-page::before') &&
    str_contains($onlineCss, '.online-panel{') &&
    str_contains($onlineCss, 'rgba(251,252,246,.92)') &&
    str_contains($onlineCss, '.online-report::before') &&
    str_contains($onlineCss, '.online-results-report::before'),
    'Workspace cards and reports must retain readable surfaces over the MARKAZ watermark'
);

onlinePortalUiAssert(
    preg_match('/<select[^>]*id="resultSession"/', $workspace) === 1 &&
    str_contains($workspaceJs, 'function populateResultSessions()') &&
    str_contains($workspaceJs, 'const firstYear = 2026;'),
    'Staff result filters must use an academic-session dropdown beginning in 2026'
);
onlinePortalUiAssert(
    strpos($dashboard, '<section class="stats-grid"') > strpos($dashboard, '<div class="welcome-card">') &&
    strpos($dashboard, '<section class="stats-grid"') < strpos($dashboard, 'id="teacherAssignmentsSection"') &&
    !str_contains($dashboardJs, 'pageContent.insertBefore(welcomeCard'),
    'Admin greeting must remain at the top with student, class and subject totals immediately beneath it'
);
onlinePortalUiAssert(
    str_contains($workspaceJs, 'const rows = state.results.filter(item => String(item.student_id) === String(studentId))') &&
    str_contains($workspaceJs, 'Overall Average'),
    'Staff report printing must include every subject for the selected learner'
);

$forwarders = ['index.php','portal-options.php','student-login.php','student-results.php','student-footer.php','footer.php','logout.php'];
foreach ($forwarders as $file) {
    onlinePortalUiAssert(
        is_file($root . '/public/api/online-class/' . $file),
        'Public Online Class endpoint must forward ' . $file . ' to the shared API implementation'
    );
}

echo "Online Class student portal and responsive shell regression checks passed.\n";
