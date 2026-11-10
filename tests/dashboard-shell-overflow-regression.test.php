<?php
declare(strict_types=1);

function shellUiAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$css = (string) file_get_contents(__DIR__ . '/../public/css/dashboard-2026.css');
$html = (string) file_get_contents(__DIR__ . '/../public/dashboard.html');

shellUiAssert(
    preg_match('/\.sidebar\{[^}]*height:calc\(100dvh\s*\/\s*var\(--page-zoom,1\)\)[^}]*min-height:0/s', $css) === 1,
    'Desktop sidebar must occupy the viewport without being clipped by a scaled fixed height'
);
shellUiAssert(
    preg_match('/\.sidebar-nav\{[^}]*flex:1[^}]*min-height:0[^}]*overflow-y:auto/s', $css) === 1,
    'Sidebar navigation must own a scrollable flex region between the header and account footer'
);
shellUiAssert(
    preg_match('/\.topbar\{[^}]*min-width:0[^}]*min-height:78px/s', $css) === 1 &&
    preg_match('/\.topbar\s*>\s*div\{[^}]*min-width:0/s', $css) === 1,
    'Top bar and its title region must shrink safely rather than clip at narrow desktop and mobile widths'
);
shellUiAssert(
    str_contains($html, 'id="sidebar"') && str_contains($html, 'id="sidebarOverlay"') &&
    str_contains($html, 'id="menuButton"'),
    'Sidebar drawer controls must remain present'
);

echo "Dashboard shell overflow regression checks passed.\n";
