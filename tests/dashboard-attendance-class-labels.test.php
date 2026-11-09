<?php
declare(strict_types=1);

function attendanceClassLabelCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$dashboardScript = file_get_contents(__DIR__ . '/../public/js/dashboard.js');

$filterStart = strpos($dashboardScript, 'const arabicOnlyLabels = new Set([');
$filterEnd = strpos($dashboardScript, ']);', $filterStart === false ? 0 : $filterStart);
$filterBlock = ($filterStart !== false && $filterEnd !== false)
    ? substr($dashboardScript, $filterStart, $filterEnd - $filterStart)
    : '';

attendanceClassLabelCheck(
    $filterBlock !== ''
        && str_contains($filterBlock, '"roudoh1"')
        && str_contains($filterBlock, '"roudoh2"')
        && str_contains($filterBlock, '"faslu1"')
        && str_contains($filterBlock, '"faslu6"')
        && str_contains($filterBlock, '"hufaz"')
        && !str_contains($filterBlock, '"ss1"')
        && !str_contains($filterBlock, '"ss2"')
        && !str_contains($filterBlock, '"ss3"'),
    'Conventional chart excludes only the nine confirmed Arabic-only class labels and retains SS1-SS3'
);

$arabicAliases = [
    '"roudoh sani", "Roudoh Thani"',
    '"faslu ula", "Faslu Awal"',
    '"faslu sani", "Faslu Thani"',
    '"faslu salis", "Faslu Thalith"',
    '"faslu rabia", "Faslu Rabia"',
    '"faslu khamis", "Faslu Khamis"',
    '"faslu sadis", "Faslu Sadis"'
];
foreach ($arabicAliases as $alias) {
    attendanceClassLabelCheck(
        str_contains($dashboardScript, $alias),
        "Arabic attendance chart maps database label {$alias}"
    );
}

$arabicOrder = strpos($dashboardScript, 'const arabicOrder = ["Roudoh Ula", "Roudoh Thani", "Faslu Awal", "Faslu Thani", "Faslu Thalith", "Faslu Rabia", "Faslu Khamis", "Faslu Sadis"];');
attendanceClassLabelCheck(
    $arabicOrder !== false,
    'Arabic attendance chart follows the exact eight-class requested hierarchy'
);

echo "Dashboard attendance class-label regression tests passed.\n";
