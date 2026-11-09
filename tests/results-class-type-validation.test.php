<?php
declare(strict_types=1);
function resultTypeCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
$classApi = file_get_contents(__DIR__ . '/../api/results/class.php');
$saveApi = file_get_contents(__DIR__ . '/../api/results/save.php');
$completionApi = file_get_contents(__DIR__ . '/../api/results/class-completion.php');
$classJs = file_get_contents(__DIR__ . '/../public/js/class.js');
$dashboardHtml = file_get_contents(__DIR__ . '/../public/dashboard.html');
$dashboardRedesignCss = file_get_contents(__DIR__ . '/../public/css/dashboard-redesign.css');
resultTypeCheck(
    str_contains($classApi, "class_type'] ?? ''")
        && str_contains($classApi, "r.class_type = ?")
        && str_contains($classApi, "r.class_id = ?"),
    'class result retrieval requires an explicit type and scopes saved scores to that type and class'
);
resultTypeCheck(
    str_contains($saveApi, "class_type'] ?? ''")
        && str_contains($saveApi, 'AND {$studentClassExpression} = ?')
        && str_contains($saveApi, 'COALESCE(NULLIF(arabic_class_id, 0), arabia_class_id)'),
    'result saving requires an explicit class type and validates the student against that system'
);
resultTypeCheck(
    str_contains($completionApi, "class_type'] ?? ''")
        && str_contains($completionApi, 'class_type = ?')
        && str_contains($completionApi, 'class_id = ?')
        && str_contains($completionApi, 'arabic_class_subjects')
        && str_contains($completionApi, 'arabic_class_id'),
    'completion calculations isolate rosters, subjects and saved scores by education system and class'
);
resultTypeCheck(
    str_contains($classJs, 'class_type=${encodeURIComponent(classType)}')
        && str_contains($classJs, 'class_type: classType'),
    'class workspace sends its selected type to completion and result-save endpoints'
);
$examOverviewStart = strpos($dashboardHtml, '<section class="dashboard-exam-section"');
$attendanceSectionStart = strpos($dashboardHtml, '<section class="dashboard-attendance-panel"', $examOverviewStart === false ? 0 : $examOverviewStart);
$examOverviewHtml = ($examOverviewStart !== false && $attendanceSectionStart !== false)
    ? substr($dashboardHtml, $examOverviewStart, $attendanceSectionStart - $examOverviewStart)
    : '';
resultTypeCheck(
    $examOverviewHtml !== ''
        && substr_count($examOverviewHtml, 'id="dashboardCompletionPie"') === 1
        && str_contains($examOverviewHtml, 'Overall Examination Completion')
        && str_contains($examOverviewHtml, 'class="result-progress-grid"')
        && !str_contains($examOverviewHtml, 'dashboardConventionalPie')
        && !str_contains($examOverviewHtml, 'dashboardArabicPie')
        && str_contains($dashboardRedesignCss, '.dashboard-exam-overview')
        && str_contains($dashboardRedesignCss, 'grid-template-columns: minmax(0, 560px) minmax(0, 1fr);'),
    'dashboard examination overview keeps one whole-school completion chart with the overall progress summary'
);
echo "Result class-type validation tests passed (5 checks).\n";
