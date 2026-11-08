<?php
declare(strict_types=1);

function portalAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$html = file_get_contents($root . '/public/student-login.html');
$script = file_get_contents($root . '/public/js/student-results-portal.js');
$css = file_get_contents($root . '/public/css/student-results-portal.css');
$resultCss = file_get_contents($root . '/public/css/student-result.css');
$portalApi = file_get_contents($root . '/api/results/student-portal.php');
$resultApi = file_get_contents($root . '/api/results/student.php');
$behaviourApi = file_get_contents($root . '/api/results/behavioural.php');
$printPage = file_get_contents($root . '/public/student-result-print.html');
$resultScript = file_get_contents($root . '/public/js/student-result.js');
$legacyRoleScript = file_get_contents($root . '/public/js/login-roles.js');
$sessionMiddleware = file_get_contents($root . '/app/middleware/session.php');
$portalAccessHelper = file_get_contents($root . '/app/helpers/student-portal-access.php');

foreach ([
    'student portal page' => $html,
    'dedicated student portal script' => $script,
    'dedicated student portal stylesheet' => $css,
    'shared result stylesheet' => $resultCss,
    'public lookup endpoint' => $portalApi,
    'official result API' => $resultApi,
    'behavioural report API' => $behaviourApi,
    'official printable result page' => $printPage,
    'official result page script' => $resultScript,
    'staff login script' => $legacyRoleScript,
    'session middleware' => $sessionMiddleware,
    'student portal access helper' => $portalAccessHelper,
] as $label => $content) {
    portalAssert(is_string($content), "{$label} exists");
}

portalAssert(str_contains($html, 'data-role="student-lookup"'), 'student lookup remains isolated from staff login handlers');
portalAssert(str_contains($html, 'css/student-results-portal.css'), 'portal-specific styles remain isolated');
portalAssert(str_contains($html, 'js/student-results-portal.js'), 'dedicated portal workflow remains loaded');
portalAssert(!str_contains($legacyRoleScript, 'data-role="student-lookup"'), 'staff login script does not bind student lookup');
portalAssert(str_contains($script, 'Check Results'), 'successful lookup presents the requested Check Results action');
portalAssert(str_contains($script, "document.body.classList.add('student-success-mode')"), 'successful lookup switches to a focused congratulations experience');
portalAssert(str_contains($script, "document.body.classList.remove('student-success-mode')"), 'reset restores the normal student lookup screen');
portalAssert(str_contains($css, '.student-success-mode .role-brand'), 'successful lookup hides school branding outside the congratulations message');
portalAssert(str_contains($css, '.student-success-mode .role-heading'), 'successful lookup hides the original login heading');
portalAssert(str_contains($css, '.student-success-mode .role-footer'), 'successful lookup hides the login footer');
portalAssert(str_contains($css, 'backdrop-filter: blur('), 'congratulations experience uses a softened backdrop');
portalAssert(str_contains($css, '@keyframes portal-celebration'), 'congratulations experience includes a restrained entrance animation');
portalAssert(!str_contains($script, 'View My Result'), 'welcome action does not use the old View My Result label');
portalAssert(str_contains($script, 'student-result-print.html'), 'Check Results opens the existing official printable page');
portalAssert(str_contains($script, "display_version: '20261106-targeted-student-display-fix'"), 'student report uses the same preview version as the staff Print Results listing');
portalAssert(str_contains($printPage, 'id="printStudentResultButton"'), 'official preview exposes the existing Print action');
portalAssert(str_contains($printPage, 'id="downloadStudentPdfButton"'), 'official preview exposes the existing Download PDF action');
portalAssert(str_contains($script, "student_portal: '1'"), 'official report is opened in scoped student-portal mode');
portalAssert(!str_contains($script, 'portal-report-sheet'), 'student portal does not render a second report card');
portalAssert(!str_contains($css, '.portal-report-sheet'), 'portal stylesheet does not duplicate official report styling');
portalAssert(str_contains($printPage, 'id="studentReportCard"'), 'official report-card template remains the destination');
portalAssert(str_contains($resultScript, 'student_portal'), 'official report script recognises student-portal mode');
portalAssert(!str_contains($resultScript, 'document.documentElement.classList.add("student-portal-view")'), 'student mode does not alter the official staff report preview presentation');
portalAssert(!str_contains($resultScript, 'student-portal-view'), 'student mode does not add a separate report-preview layout');
portalAssert(str_contains($resultScript, 'window.location.replace("./student-login.html");'), 'student report back navigation returns to the student portal');
portalAssert(str_contains($resultScript, 'portalBackButton.textContent = "← Student Portal"'), 'student report back button is clearly labelled for portal navigation');
portalAssert(strpos($resultScript, 'if (isStudentPortalView) {') < strpos($resultScript, 'if (isPrintMode) {'), 'student portal back navigation is handled before staff print navigation');
portalAssert(str_contains($resultScript, 'window.print()'), 'student can use the official report print dialog');
portalAssert(str_contains($portalApi, 'startStudentPortalSession'), 'successful public lookup creates a scoped session');
portalAssert(str_contains($portalApi, 'student_portal_result_access'), 'lookup scope is tied to the verified student and period');
portalAssert(str_contains($sessionMiddleware, 'MARKAZ_STUDENT_SESSION'), 'public student access uses a session cookie separate from staff authentication');
portalAssert(str_contains($portalAccessHelper, 'empty($_COOKIE[\'MARKAZ_STUDENT_SESSION\'])'), 'ordinary staff requests do not start a public student session');
portalAssert(str_contains($portalAccessHelper, 'session_write_close()'), 'invalid student scopes release the session before staff authentication continues');
portalAssert(str_contains($portalApi, 'results_released'), 'public lookup still checks school result-release status');
portalAssert(str_contains($portalApi, "AND r.class_type = 'conventional'"), 'public lookup preserves conventional result isolation');
portalAssert(str_contains($resultApi, 'studentPortalResultScope()'), 'official result API accepts only a matching scoped student session');
portalAssert(str_contains($behaviourApi, 'studentPortalResultScope()'), 'official behavioural report supports the same scoped read-only session');
portalAssert(str_contains($resultApi, 'requireResultStaff()'), 'staff result API continues to enforce staff access when no student scope exists');
portalAssert(str_contains($behaviourApi, 'requireResultStaff()'), 'behaviour API continues to enforce staff access when no student scope exists');

portalAssert(str_contains($resultScript, 'document.body.classList.add("student-portal-result-view")'), 'student portal result view receives an isolated layout scope');
portalAssert(str_contains($resultScript, 'document.body.classList.remove("student-portal-result-view")') === false, 'student portal layout scope is not removed during normal result rendering');
portalAssert(str_contains($printPage, 'class="management-panel behavioural-management-panel"'), 'behavioural report remains a distinct section below academic results in the shared report markup');
portalAssert(str_contains($resultCss, 'body.student-portal-result-view .report-card .result-management-grid'), 'student portal has an isolated stacked result layout');
portalAssert(str_contains($resultCss, 'body.student-portal-result-view .report-card .behaviour-grid'), 'student portal behavioural ratings use a horizontal grid');

portalAssert(str_contains($resultCss, 'body.student-portal-result-view .student-result-page.management-view .document-actions'), 'student portal actions use a dedicated responsive action group');
portalAssert(str_contains($resultCss, 'body.student-portal-result-view .student-information .student-class-row'), 'student portal class information has a compact equal-height row');
portalAssert(str_contains($resultCss, 'grid-template-columns: 333px 64px 58px 96px') && str_contains($resultCss, 'grid-template-columns: 109px 109px 102px 214px'), 'desktop student portal profile uses the requested fixed information-column widths');
portalAssert(str_contains($resultCss, 'min-height: 36px !important;') && str_contains($resultCss, 'height: 36px !important;') && str_contains($resultCss, 'min-height: 44px !important;') && str_contains($resultCss, 'height: 44px !important;'), 'desktop student portal profile uses the requested 36px and 44px row heights');
portalAssert(str_contains($resultCss, 'gap: 9px !important;'), 'desktop student portal profile rows use the requested 9px field gap');
portalAssert(str_contains($resultCss, 'body.student-portal-result-view .student-information .student-photo {') && str_contains($resultCss, 'display: none !important;'), 'student portal hides the profile photo on mobile screens');
portalAssert(str_contains($resultCss, 'body.student-portal-result-view .student-information .student-profile-grid') && str_contains($resultCss, 'grid-template-columns: minmax(0, 1fr) !important;'), 'student portal profile details use the full mobile width without a photo column');
portalAssert(str_contains($resultCss, 'grid-template-areas:') && str_contains($resultCss, '"name name"') && str_contains($resultCss, '"gender age"') && str_contains($resultCss, '"student-type conventional-class"') && str_contains($resultCss, '"class-size arabic-class"') && str_contains($resultCss, '"memorization memorization"'), 'student portal mobile profile follows the five requested information rows');
portalAssert(str_contains($resultCss, 'display: contents !important;'), 'student portal mobile rows share one responsive information grid');
portalAssert(str_contains($resultCss, 'body.student-portal-result-view .student-information .memorization-value-row') && str_contains($resultCss, 'display: block !important;'), 'memorization value is displayed below its label without a separate inline box');
portalAssert(str_contains($resultCss, 'min-height: 50px !important;') && str_contains($resultCss, 'height: auto !important;'), 'student portal second-row fields use a compact natural height');
portalAssert(str_contains($resultCss, '@media screen and (min-width: 761px)') && str_contains($resultCss, 'justify-content: flex-end !important;'), 'student portal desktop action buttons are aligned together at the right');
portalAssert(str_contains($resultCss, '.student-top-row > .detail-item,') && str_contains($resultCss, '.student-class-row > .detail-item {'), 'mobile field sizing applies to the actual nested row items');
portalAssert(str_contains($resultCss, '@media screen and (max-width: 560px)'), 'student portal mobile profile changes apply to screen and not printed output');
portalAssert(str_contains($printPage, 'student-result.css?v=20261108-student-portal-final-profile-polish'), 'student portal mobile layout refreshes the result stylesheet cache');

echo "Student portal official-report integration checks passed." . PHP_EOL;
