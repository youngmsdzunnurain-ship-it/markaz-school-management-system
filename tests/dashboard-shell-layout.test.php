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
    preg_match('/\\.admin-profile-display\\{[^}]*grid-template-columns:minmax\\(0,\\s*64px\\)\\s+minmax\\(0,\\s*1fr\\)/s', $css) === 1 &&
    preg_match('/\\.admin-profile-photo\\{[^}]*grid-column:1;[^}]*grid-row:1\\s*\\/\\s*span\\s*2;[^}]*border-radius:50%/s', $css) === 1 &&
    preg_match('/\\.admin-profile-summary\\{[^}]*display:contents/s', $css) === 1 &&
    preg_match('/\\.admin-profile-summary strong\\{[^}]*grid-column:2;[^}]*grid-row:1/s', $css) === 1 &&
    preg_match('/\\.admin-profile-summary span\\{[^}]*grid-column:2;[^}]*grid-row:2/s', $css) === 1 &&
    preg_match('/@container adm \\(max-width:380px\\)\\{[^}]*grid-template-columns:minmax\\(0,\\s*48px\\)\\s+minmax\\(0,\\s*1fr\\)/s', $css) === 1 &&
    str_contains($html, 'id="dashboardAdminPhoto"') &&
    str_contains($html, 'id="dashboardAdminName"') &&
    str_contains($html, 'id="dashboardAdminEmail"') &&
    strpos($html, 'class="admin-details"') > strpos($html, 'class="admin-profile-display"'),
    'Administrator profile must place its circular photo left of vertically stacked name and email, with active status underneath and existing account bindings intact'
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
