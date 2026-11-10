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

onlinePortalUiAssert(
    str_contains($html, 'css/login-roles.css') &&
    str_contains($html, 'css/login-shared.css') &&
    str_contains($html, 'class="role-login-page"') &&
    str_contains($html, 'class="role-login-card student-login-card"'),
    'Online Class login must reuse the established MARKAZ student login visual system'
);
onlinePortalUiAssert(
    preg_match('/<select[^>]*id="onlineSession"/', $html) === 1 &&
    preg_match('/<select[^>]*id="onlineSection"/', $html) === 1 &&
    preg_match('/<select[^>]*id="onlineLevel"/', $html) === 1 &&
    preg_match('/<select[^>]*id="onlineTerm"/', $html) === 1,
    'Level, section, academic session and term must all be dropdowns'
);
onlinePortalUiAssert(
    str_contains($js, 'online-class/portal-options.php') &&
    str_contains($js, 'onlineSession') &&
    str_contains($js, '2020') &&
    str_contains($js, 'getFullYear') &&
    str_contains($js, 'level_id') &&
    str_contains($js, 'section'),
    'Portal dropdowns must populate from active levels/registered sections and offer sessions from 2020 through future years'
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
    str_contains($portal, 'id="portalLogout"'),
    'Student results, report rendering and logout hooks must remain intact'
);

echo "Online Class student portal UI regression checks passed.\n";
