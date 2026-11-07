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
$api = file_get_contents($root . '/api/results/student-portal.php');
$legacyRoleScript = file_get_contents($root . '/public/js/login-roles.js');

portalAssert(is_string($html), 'student portal page exists');
portalAssert(is_string($script), 'dedicated student portal script exists');
portalAssert(is_string($css), 'dedicated student portal stylesheet exists');
portalAssert(is_string($api), 'existing public results endpoint exists');
portalAssert(is_string($legacyRoleScript), 'existing staff login script exists');

portalAssert(str_contains($html, 'data-role="student-lookup"'), 'student lookup is isolated from staff login handlers');
portalAssert(str_contains($html, 'css/student-results-portal.css'), 'portal-specific styles load after shared login styles');
portalAssert(str_contains($html, 'js/student-results-portal.js'), 'dedicated portal workflow is loaded');
portalAssert(!str_contains($legacyRoleScript, 'data-role="student-lookup"'), 'staff login script does not bind the student lookup form');
portalAssert(!str_contains($html, 'id="studentResultsPanel" class="student-results-panel"'), 'old inline result panel is not retained');
portalAssert(str_contains($script, "api/results/student-portal.php"), 'the existing public endpoint remains the data source');
portalAssert(str_contains($script, 'View My Result'), 'successful lookup presents a separate welcome action');
portalAssert(str_contains($script, 'window.print()'), 'student can print or save the result through the browser print dialog');
portalAssert(str_contains($script, 'textContent'), 'student-supplied and API text is rendered as text, not injected HTML');
portalAssert(str_contains($css, '@media print'), 'the result has a dedicated print layout');
portalAssert(str_contains($css, '.role-login-page::before'), 'decorative portal watermark is excluded from print');
portalAssert(str_contains($api, "results_released"), 'public lookup still checks school result-release status');
portalAssert(str_contains($api, "'class_type' => 'conventional'"), 'existing conventional result isolation remains intact');

echo "Student portal welcome-and-print flow regression checks passed." . PHP_EOL;
