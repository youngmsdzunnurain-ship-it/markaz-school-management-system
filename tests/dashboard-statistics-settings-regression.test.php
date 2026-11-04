<?php
declare(strict_types=1);

function dashboardRegressionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = __DIR__ . '/..';
$html = file_get_contents($root . '/public/dashboard.html');
$js = file_get_contents($root . '/public/js/dashboard.js');
$stats = file_get_contents($root . '/api/dashboard/stats.php');
$redesign = file_get_contents($root . '/public/css/dashboard-redesign.css');
$settingsHtml = file_get_contents($root . '/public/settings.html');
$settingsJs = file_get_contents($root . '/public/js/settings.js');
$settingsCss = file_get_contents($root . '/public/css/settings.css');
$classHtml = file_get_contents($root . '/public/class.html');
$classJs = file_get_contents($root . '/public/js/class.js');

dashboardRegressionAssert(
    substr_count($html, 'class="stat-card ') === 3,
    'dashboard must keep exactly the three requested summary cards'
);
dashboardRegressionAssert(
    str_contains($js, 'if (statCards.length < 3) return;'),
    'dashboard statistics must load when the three-card layout is used'
);
dashboardRegressionAssert(
    !str_contains($js, '"Entry Completion"') &&
    str_contains($js, 'statCards[2].textContent = data.stats.subjects;'),
    'teacher and senior-admin headline cards must use only Students, Classes and Subjects'
);
dashboardRegressionAssert(
    str_contains($stats, 'SELECT COUNT(*) FROM students WHERE is_active = 1') &&
    str_contains($stats, 'SELECT COUNT(*) FROM classes WHERE is_active = 1') &&
    str_contains($stats, 'SELECT COUNT(*) FROM arabic_classes WHERE is_active = 1') &&
    str_contains($stats, 'SELECT COUNT(*) FROM subjects WHERE is_active = 1') &&
    str_contains($stats, 'dashboardSummariseStudentProgress'),
    'headline totals and result progress must be sourced from active database records and score rows'
);
dashboardRegressionAssert(
    !str_contains($settingsJs, 'MARKAZ RESULT COMPLETION THRESHOLD') &&
    !str_contains($settingsJs, 'resultCompletionThresholdCard') &&
    str_contains($settingsHtml, 'id="resultPrintThresholdForm"') &&
    str_contains($settingsHtml, 'js/result-print-threshold-settings.js'),
    'only the single persisted Print Eligibility Threshold setting must remain'
);
dashboardRegressionAssert(
    str_contains($settingsCss, '.result-print-threshold-card') &&
    str_contains($settingsCss, '#resultPrintThreshold') &&
    str_contains($settingsCss, '#saveResultPrintThresholdButton'),
    'the single print-threshold setting must have its own clear responsive styling'
);
dashboardRegressionAssert(
    str_contains($redesign, 'grid-template-columns: minmax(0, 2fr) minmax(0, 3fr)') &&
    str_contains($redesign, 'grid-template-columns: repeat(3, minmax(0, 1fr))'),
    'dashboard management and administrator panels must use a 40/60 split with three quick-access columns'
);
dashboardRegressionAssert(
    !str_contains($classHtml, 'printClassResultsButton') &&
    !str_contains($classHtml, 'Print Class Results') &&
    !str_contains($classJs, 'printClassResultsButton'),
    'the unwanted Print Class Results control and handler must be removed from the class page'
);

echo "Dashboard statistics, settings, layout and class-print regression tests passed (8 checks).\n";
