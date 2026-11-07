<?php
declare(strict_types=1);

function settingsFirstRenderAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = __DIR__ . '/..';
$html = file_get_contents($root . '/public/settings.html');
$navigationCss = file_get_contents($root . '/public/css/settings-panel-navigation.css');
$navigationJs = file_get_contents($root . '/public/js/settings-panel-navigation.js');

$headEnd = strpos($html, '</head>');
$bodyStart = strpos($html, '<body');
$guardPosition = strpos($html, 'settings-page-pending');

settingsFirstRenderAssert(
    $guardPosition !== false && $headEnd !== false && $guardPosition < $headEnd && $guardPosition < $bodyStart,
    'the initial-render guard must be activated in the document head before settings content can paint'
);
settingsFirstRenderAssert(
    preg_match('/<section class="settings-card">\s*<div class="settings-card-heading">\s*<div>\s*<span class="settings-label">ADMINISTRATOR<\/span>\s*<h2>Profile Information<\/h2>/s', $html) === 1,
    'Admin Profile Information must remain the first settings card'
);
settingsFirstRenderAssert(
    str_contains($navigationCss, 'html.settings-page-pending .settings-page > :not(.settings-header):not(.settings-card:first-of-type):not(.markaz-global-footer)'),
    'the initial-render guard must hide non-profile settings cards before navigation initializes'
);
settingsFirstRenderAssert(
    str_contains($navigationJs, 'activate("profile")') &&
    str_contains($navigationJs, 'classList.remove("settings-page-pending")'),
    'navigation must select General & Profile before releasing the initial-render guard'
);

echo "Admin settings first-render regression tests passed (4 checks).\n";
