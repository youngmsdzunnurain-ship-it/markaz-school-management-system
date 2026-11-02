<?php
declare(strict_types=1);

/**
 * Static contract checks for the MARKAZ language and navigation layer.
 * Run from the repository root: php tests/markaz-ui-contract.test.php
 */
$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$pages = [
    'public/dashboard.html',
    'public/attendance.html',
    'public/classes.html',
    'public/subjects/index.html',
    'public/students.html',
    'public/school-fees.html',
    'public/settings.html',
    'public/login.html',
    'public/print-results.html',
    'public/student-result.html',
    'public/class.html',
    'public/class-subjects/index.html',
];
foreach ($pages as $page) {
    $html = file_get_contents($root . '/' . $page);
    $check($html !== false, "Missing page: {$page}");
    if ($html === false) continue;
    $prefix = in_array($page, ['public/subjects/index.html', 'public/class-subjects/index.html'], true) ? '../' : './';
    $check(str_contains($html, $prefix . 'js/markaz-i18n.js'), "{$page}: missing language script");
    $check(str_contains($html, $prefix . 'css/markaz-preferences.css'), "{$page}: missing preferences stylesheet");
}
$dashboard = file_get_contents($root . '/public/dashboard.html') ?: '';
$check(str_contains($dashboard, 'data-role-check-pending="true"'), 'Dashboard role gate was removed');
$check(!str_contains($dashboard, 'app-watermark.css'), 'Dashboard still loads the legacy watermark');
$check(str_contains($dashboard, 'js/markaz-theme.js'), 'Dashboard theme controller missing');
$check(!str_contains(file_get_contents($root . '/public/print-results.html') ?: '', 'markaz-theme.js'), 'Theme controller must remain dashboard-only');

$preferences = file_get_contents($root . '/public/css/markaz-preferences.css') ?: '';
$check(str_contains($preferences, 'top:68px!important'), 'Management page headings are not sticky below the shared bar');
$check(str_contains($preferences, 'background:rgba(255,255,255,.94)!important'), 'Legacy green page headers have not been neutralized');
$check(str_contains($preferences, 'content:none!important'), 'Legacy green header decorations have not been removed');
$check(str_contains($preferences, 'position:sticky!important;top:0!important'), 'Dashboard topbar is not sticky');
$check(str_contains($preferences, 'prefers-reduced-motion') || str_contains($preferences, 'prefers-reduced-transparency'), 'Accessibility media preferences are missing');
$check(str_contains($preferences, '@media print'), 'Print-specific navigation suppression is missing');

$shell = file_get_contents($root . '/public/js/management-shell.js') ?: '';
$check(str_contains($shell, 'markaz-shell-sidebar'), 'Shared navigation drawer missing');
$managementCss = file_get_contents($root . '/public/css/management-shell.css') ?: '';
$check(str_contains($managementCss, 'position:fixed'), 'Shared top navigation is not fixed across management pages');
$check(str_contains($shell, '<span>Dashboard</span>'), 'Navigation labels are not individually localizable');

$i18n = file_get_contents($root . '/public/js/markaz-i18n.js') ?: '';
$check(str_contains($i18n, 'markazI18n'), 'Translation API missing');
$check(str_contains($i18n, 'originalAttributes'), 'Accessible attribute translation is missing');
$check(str_contains($i18n, 'tbody,[contenteditable'), 'Database-backed table bodies are not protected from automatic translation');

if ($failures) {
    fwrite(STDERR, "MARKAZ UI contract: FAILED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "MARKAZ UI contract: PASS (" . count($pages) . " pages checked)\n";
