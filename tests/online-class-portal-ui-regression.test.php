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
$js = $workspaceJs;
$portalJs = (string) file_get_contents($root . '/public/js/online-class-portal.js');
$workspaceApi = (string) file_get_contents($root . '/api/online-class/index.php');
$dashboard = (string) file_get_contents($root . '/public/dashboard.html');
$dashboardCss = (string) file_get_contents($root . '/public/css/dashboard-2026.css');
$onlineCss = (string) file_get_contents($root . '/public/css/online-class.css');
$footerSettingsApi = (string) file_get_contents($root . '/api/settings/result-footer.php');
$studentResultsApi = (string) file_get_contents($root . '/api/online-class/student-results.php');
$studentFooterApi = (string) file_get_contents($root . '/api/online-class/student-footer.php');

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
    substr_count($portal, '+234 807') === 2 && !str_contains($studentLogin, 'online-portal-switch') &&
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
    str_contains($workspaceApi, 'FROM markaz_app_settings') &&
    str_contains($workspaceApi, 'result_footer_class_teacher_image') &&
    str_contains($workspaceApi, 'result_footer_director_image') &&
    str_contains($studentResultsApi, 'FROM markaz_app_settings') &&
    str_contains($studentResultsApi, 'result_footer_class_teacher_image') &&
    str_contains($studentResultsApi, 'result_footer_director_image') &&
    str_contains($studentFooterApi, 'FROM markaz_app_settings') &&
    str_contains($portalJs, 'function signatureImageSource(value)') &&
    str_contains($portalJs, "const path=raw.replace(") &&
    str_contains($workspaceJs, 'function signatureImageSource(value)') &&
    str_contains($workspaceJs, 'data-signature-fallback') &&
    str_contains($portalJs, 'function renderSavedSignature'),
    'Both Online Class APIs must read the same saved signature records as the main result page and render their image paths safely'
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
    preg_match('/@page\\s*\\{[^}]*size:\\s*letter landscape/s', $onlineCss) === 1 &&
    str_contains($onlineCss, '#onlineReport') &&
    str_contains($workspaceJs, 'Attendance</th><th>Test</th><th>Final Examination</th>'),
    'Staff Online Class reports must preserve their existing design and print in landscape orientation'
);

onlinePortalUiAssert(
    preg_match('/body\\.online-workspace-page\\s+\\*\\s*\\{\\s*visibility:\\s*hidden\\s*!important/', $onlineCss) === 1 &&
    preg_match('/body\\.online-results-page\\s+\\*\\s*\\{\\s*visibility:\\s*hidden\\s*!important/', $onlineCss) === 1 &&
    str_contains($onlineCss, 'width: 273.4mm !important') &&
    str_contains($onlineCss, 'height: 209.9mm !important') &&
    str_contains($onlineCss, 'flex-direction: column !important') &&
    str_contains($onlineCss, 'background: url("../assets/images/markaz-logo.jpg") center/contain no-repeat !important') &&
    str_contains($onlineCss, 'border-top: .7mm solid #b99a48 !important') &&
    !str_contains($onlineCss, 'font-size: 7pt !important'),
    'Printable reports must fill the landscape page, use one centered logo watermark and finish with a gold footer rule'
);
onlinePortalUiAssert(
    str_contains($workspace, 'Enabling student login does not release results') &&
    str_contains($workspaceJs, 'function formatOnlineReportDate(value)') &&
    str_contains($workspaceJs, 'formatOnlineReportDate(level?.[dateKey])') &&
    str_contains($workspaceJs, 'Results are RELEASED to students.') &&
    str_contains($portalJs ?? '', 'function formatOnlineReportDate(value)'),
    'Online Class release controls and resumption dates must be explicit and consistently formatted'
);

onlinePortalUiAssert(
    str_contains($js ?? '', 'مركز تعليم وتحفيظ القرآن الكريم') &&
    str_contains($workspaceJs, 'مركز تعليم وتحفيظ القرآن الكريم') &&
    str_contains($workspaceJs, 'class="school-name-ar"') &&
    str_contains($portal, 'مركز تعليم وتحفيظ القرآن الكريم') &&
    str_contains($portal, 'school-name-ar') &&
    str_contains($onlineCss, 'size: letter landscape') &&
    !str_contains($onlineCss, 'width: 287mm !important'),
    'Staff and student reports must use the Arabic-first school heading and Letter landscape page geometry'
);
onlinePortalUiAssert(
    str_contains($workspaceJs, 'غواغوالادا، أبوجا') &&
    !str_contains($workspaceJs, 'جواغوالادا') &&
    !str_contains($workspaceJs, 'تخطيط التعويضات / كوتونكو القديمة') &&
    str_contains($portal, 'غواغوالادا، أبوجا') &&
    !str_contains($portal, 'جواغوالادا') &&
    !str_contains($portal, 'تخطيط التعويضات / كوتونكو القديمة') &&
    str_contains($workspaceJs, '+234 807 319 1275') &&
    str_contains($workspaceJs, '+234 807 095 5833') &&
    !str_contains($workspaceJs, '+234 704 865 7955') &&
    str_contains($portal, '+234 807 319 1275') &&
    str_contains($portal, '+234 807 095 5833') &&
    !str_contains($portal, '+234 704 865 7955') &&
    str_contains($portal, 'markazilquran@gmail.com') &&
    str_contains($portal, 'class="school-contact"'),
    'Both printable Online Class reports must show only the two confirmed school phone numbers and the published email'
);


onlinePortalUiAssert(
    str_contains($workspaceApi, "'class_teacher_signature_image'") &&
    str_contains($workspaceApi, "'director_signature_image'") &&
    str_contains($portalJs, 'function signatureImageSource(value)') &&
    str_contains($portalJs, "const path=raw.replace(") &&
    str_contains($workspaceJs, 'function signatureImageSource(value)') &&
    str_contains($workspaceJs, 'data-signature-fallback') &&
    str_contains($portalJs, 'function renderSavedSignature'),
    'Both Online Class reports must resolve the existing saved signature image paths like the main student-result page and fall back safely only if an image is unavailable'
);
onlinePortalUiAssert(
    str_contains($workspace, 'family=Amiri') &&
    str_contains($portal, 'family=Amiri') &&
    str_contains($onlineCss, 'font-family:"Amiri"') &&
    str_contains($onlineCss, 'font-family:"Cormorant Garamond"') &&
    str_contains($onlineCss, 'grid-template-columns:248px minmax(0,1fr)') &&
    str_contains($onlineCss, 'height:38px!important') &&
    str_contains($onlineCss, 'padding:12px!important'),
    'Online Class heading typography must be calligraphic and its sidebar navigation must use a tighter, narrower layout'
);

onlinePortalUiAssert(
    str_contains($footerSettingsApi, "'class_teacher_signature_image'") &&
    str_contains($footerSettingsApi, "'director_signature_image'"),
    'Shared result-footer settings must return the saved signature image paths to authorized result viewers'
);
onlinePortalUiAssert(
    str_contains($portal, 'css/student-result-portal-print.css') &&
    str_contains($portal, 'id="portalReport"') &&
    str_contains($portal, 'class="report-footer"') &&
    str_contains($portal, 'id="classTeacherSignatureDisplay"') &&
    str_contains($portal, 'id="directorSignatureDisplay"') &&
    str_contains($portal, 'class="report-footer"'),
    'Online Class student portal must use the established main student-result print structure and signature slots'
);
$portalPrintCss = (string) file_get_contents($root . '/public/css/student-result-portal-print.css');
onlinePortalUiAssert(
    str_contains($portalJs, "image.className='result-signature-image'") &&
    str_contains($portalJs, 'function renderSavedSignature') &&
    str_contains($onlineCss, 'width:145%') &&
    str_contains($portalPrintCss, 'width: 145% !important;') &&
    str_contains($portalPrintCss, 'top: 50% !important;') &&
    str_contains($portalPrintCss, 'left: 50% !important;') &&
    str_contains($portalPrintCss, 'transform: translate(-50%, -50%) !important;'),
    'Student portal must render saved signature images and center the enlarged print watermark'
);

echo "Online Class student portal and responsive shell regression checks passed.\n";


onlinePortalUiAssert(
 str_contains($workspaceJs, 'function waitForReportImages(root)') &&
 str_contains($workspaceJs, 'await waitForReportImages($("onlineReport"))') &&
 str_contains($portalJs, 'function waitForReportImages(root)') &&
 str_contains($portalJs, "await waitForReportImages($('portalReport'))") &&
 str_contains($workspaceJs, 'CENTRE FOR LEARNING AND MEMORIZATION OF THE GLORIOUS QUR’AN') &&
 str_contains($portal, 'الفصل الدراسي عبر الإنترنت') &&
 str_contains($workspaceJs, 'Academic Report • Official School Record') &&
  str_contains($workspaceJs, 'جواغواغوالادا، أبوجا') &&
  !str_contains($workspaceJs, 'تخطيط التعويضات / كوتونكو القديمة') &&
 str_contains($workspaceJs, 'online-report-signature-date') &&
 str_contains($onlineCss, 'width:172mm!important') &&
 str_contains($onlineCss, 'border:.45mm double rgba(185,154,72,.82)') &&
 str_contains($onlineCss, 'body.online-workspace-page #onlineReport .online-report-signature-date span') &&
 str_contains($onlineCss, 'width: 205mm !important') &&
  str_contains($onlineCss, 'flex: 0 0 auto !important') &&
  str_contains($onlineCss, 'padding: 1.5mm 3mm 1mm !important') &&
 str_contains($onlineCss, 'padding: 5mm 7mm 4mm !important') &&
 str_contains($onlineCss, 'official-results-label'),
 'Staff Online Class report must retain the one-page layout with restored official footer, ruled signature dates, balanced top spacing and larger watermark'
);



onlinePortalUiAssert(
    str_contains($onlineCss, '/* Keep the dashboard-generated staff report on one landscape sheet. */') &&
    str_contains($onlineCss, 'position: absolute !important;') &&
    str_contains($onlineCss, 'width: 273.4mm !important;') &&
    str_contains($onlineCss, 'height: 209.9mm !important;') &&
    str_contains($onlineCss, 'padding: 2.5mm 7mm 4mm !important;') &&
    str_contains($onlineCss, 'page-break-inside: avoid !important;'),
    'Staff Online Class report must print once on one landscape page with its content shifted slightly upward'
);
