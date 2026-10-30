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
$dashboardCss = file_get_contents(__DIR__ . '/../public/css/dashboard.css');
resultTypeCheck(
    str_contains($classApi, "class_type'] ?? ''")
        && str_contains($classApi, "r.class_type = ?")
        && str_contains($classApi, "r.class_id = ?"),
    'class result retrieval requires an explicit type and scopes saved scores to that type and class'
);
resultTypeCheck(
    str_contains($saveApi, "class_type'] ?? ''")
        && str_contains($saveApi, 'AND {$studentClassColumn} = ?'),
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
resultTypeCheck(
    str_contains($dashboardCss, 'grid-template-columns:minmax(0,35fr) minmax(0,30fr) minmax(0,35fr)')
        && str_contains($dashboardCss, 'Examination chart order: Conventional | Whole school | Arabic'),
    'dashboard examination cards use the requested 35/30/35 left-centre-right proportions'
);
echo "Result class-type validation tests passed (5 checks).\n";
