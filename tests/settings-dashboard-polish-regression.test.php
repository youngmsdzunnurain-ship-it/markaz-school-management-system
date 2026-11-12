<?php
declare(strict_types=1);
function settingsDashboardCheck(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
$nav = file_get_contents(__DIR__ . '/../public/js/settings-panel-navigation.js');
$css = file_get_contents(__DIR__ . '/../public/css/settings.css');
$dash = file_get_contents(__DIR__ . '/../public/css/dashboard-2026.css');
$navHtml = file_get_contents(__DIR__ . '/../public/settings.html');
$dashHtml = file_get_contents(__DIR__ . '/../public/dashboard.html');
settingsDashboardCheck(str_contains($nav, 'Result Footer Signatures & Date')
    && str_contains($nav, 'Website Social Media Links')
    && str_contains($nav, 'Emergency Contact & System Support')
    && str_contains($nav, 'Qur’an Memorization'), 'Settings categories are clearly named');
settingsDashboardCheck(str_contains($nav, '"profile", "result-release", "academic-archive", "academic", "results", "memorization"')
    && str_contains($nav, 'activate("profile")'), 'Exam Officer retains result footer/signature settings');
settingsDashboardCheck(str_contains($css, '.result-footer-settings-card #saveResultFooterButton')
    && str_contains($css, '.signature-editor-actions button:hover'), 'Result footer and signature actions are styled');
settingsDashboardCheck(str_contains($dash, 'object-position:center center')
    && str_contains($dash, 'grid-template-columns:repeat(2,minmax(0,1fr))')
    && str_contains($dash, '@media (max-width:850px)'), 'Dashboard avatar and attendance chart layout adapt');
settingsDashboardCheck(str_contains($navHtml, 'Result Footer Signatures &amp; Report Date')
    && str_contains($dashHtml, '20261112-staff-motion1'), 'Updated assets are loaded by the pages');
echo "Settings and dashboard polish regression tests passed (5 checks).\n";
