<?php
declare(strict_types=1);

function sharedSettingsAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$navigation = file_get_contents($root . '/public/js/settings-panel-navigation.js');
$settingsHtml = file_get_contents($root . '/public/settings.html');
$settingsJs = file_get_contents($root . '/public/js/settings.js');

sharedSettingsAssert(is_string($navigation) && is_string($settingsHtml) && is_string($settingsJs), 'shared settings source files exist');
sharedSettingsAssert(
    str_contains($navigation, 'Qur’an Memorization') &&
    str_contains($navigation, 'Academic Records & Term Archive') &&
    str_contains($navigation, 'Results & Reports'),
    'shared Admin category labels are the canonical labels for both portals'
);
sharedSettingsAssert(
    !str_contains($navigation, 'Core Memorization') &&
    !str_contains($navigation, 'Academic & Term Archive') &&
    !str_contains($navigation, 'Result & Reports') &&
    !str_contains($navigation, 'categoryLabels'),
    'Exam Officer no longer renames settings that use the same underlying feature'
);
sharedSettingsAssert(
    str_contains($settingsHtml, 'Portion of Memorization') === false &&
    str_contains($settingsJs, 'Portion of Memorization'),
    'Portion of Memorization is defined once in the shared settings implementation used by both portals'
);
sharedSettingsAssert(
    str_contains($navigation, 'const examOfficerCategoryIds') &&
    str_contains($navigation, 'categories = isExamOfficer'),
    'Exam Officer retains its restricted category access without changing shared category names'
);

echo "Shared Admin and Exam Officer settings label tests passed." . PHP_EOL;
