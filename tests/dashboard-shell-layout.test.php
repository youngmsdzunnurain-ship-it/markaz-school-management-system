<?php
declare(strict_types=1);

function dashboardShellAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$html = file_get_contents(__DIR__ . '/../public/dashboard.html');
$css = file_get_contents(__DIR__ . '/../public/css/dashboard-2026.css');
$js = file_get_contents(__DIR__ . '/../public/js/dashboard.js');

dashboardShellAssert(
    str_contains($html, 'id="dashboardAdminPhoto"') &&
    str_contains($html, 'id="dashboardAdminName"') &&
    str_contains($html, 'id="dashboardAdminEmail"') &&
    str_contains($js, 'dashboardAdminName.textContent = name') &&
    str_contains($js, 'dashboardAdminEmail.textContent = email'),
    'Dashboard administrator photo, name and email must remain present and populated from the authenticated account'
);

dashboardShellAssert(
    preg_match('/\.dashboard-bottom-layout\{grid-template-columns:minmax\(0,\s*45fr\)\s+minmax\(0,\s*55fr\)\}/', $css) === 1,
    'Dashboard quick navigation and administrator panel must use the requested 45% / 55% split'
);

dashboardShellAssert(
    preg_match('/\.nav-link\{[^}]*color:#fff/s', $css) === 1 &&
    preg_match('/\.nav-section-label\{[^}]*color:#fff/s', $css) === 1,
    'Dashboard sidebar navigation text must use pure white'
);

dashboardShellAssert(
    preg_match('/\.nav-link i\{[^}]*color:#a9cdb6/s', $css) === 1 &&
    preg_match('/\.nav-link\.active i\{[^}]*color:#e6d9a4/s', $css) === 1,
    'Dashboard sidebar icons must retain their original normal and active colours'
);

dashboardShellAssert(
    str_contains($css, '.main-content{min-width:0;min-height:calc(100vh / var(--page-zoom,1));min-height:calc(100dvh / var(--page-zoom,1));display:flex;flex-direction:column}') &&
    str_contains($css, '.page-content{') &&
    str_contains($css, 'flex:1 0 auto') &&
    str_contains($css, '.dashboard-footer{') &&
    str_contains($css, 'margin-top:auto'),
    'Dashboard footer must remain at the bottom of the viewport on short pages and after content on long pages'
);

dashboardShellAssert(
    str_contains($css, '.dashboard-footer') &&
    str_contains($css, 'border-top:1px solid #e1e8e3') &&
    str_contains($css, 'background:rgba(255,255,255,.88)'),
    'Dashboard footer must use the shared translucent white background and subtle divider'
);

dashboardShellAssert(
    preg_match('/\.dashboard-footer\\{[^}]*text-align:center/s', $css) === 1 &&
    preg_match('/\.dashboard-footer::before\\{[^}]*background:linear-gradient/s', $css) === 1 &&
    preg_match('/\.dashboard-footer \.footer-brand\\{[^}]*display:block/s', $css) === 1 &&
    preg_match('/\.dashboard-footer \.footer-school\\{[^}]*display:block/s', $css) === 1 &&
    preg_match('/\.dashboard-footer \.footer-copyright\\{[^}]*display:block/s', $css) === 1,
    'Dashboard footer must match the shared centered MARKAZ footer with its green-gold accent and stacked text'
);

echo "Dashboard sidebar text-only colour and shell layout regression tests passed.\n";
