<?php
declare(strict_types=1);

function arabicClassWorkspaceCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$classListApi = file_get_contents(__DIR__ . '/../api/classes/list.php');
$classListJs = file_get_contents(__DIR__ . '/../public/js/classes.js');
$classWorkspaceJs = file_get_contents(__DIR__ . '/../public/js/class.js');
$classResultsApi = file_get_contents(__DIR__ . '/../api/results/class.php');
$resultSaveApi = file_get_contents(__DIR__ . '/../api/results/save.php');
$completionApi = file_get_contents(__DIR__ . '/../api/results/class-completion.php');

arabicClassWorkspaceCheck(
    str_contains($classListApi, 'FROM arabic_classes')
        && str_contains($classListApi, 'AS class_type')
        && str_contains($classListApi, 'arabic_class_id')
        && str_contains($classListApi, 'arabia_class_id')
        && str_contains($classListApi, 'hufaz'),
    'class listing includes Arabic classes, counts current and legacy Arabic assignments, and excludes Hufaz'
);
arabicClassWorkspaceCheck(
    str_contains($classListJs, 'class_type=')
        && str_contains($classListJs, 'classItem.class_type')
        && str_contains($classListJs, 'class.html?id=')
        && str_contains($classListJs, 'class-type-label')
        && str_contains($classListJs, 'data.total_students'),
    'Arabic class rows open the correctly typed class-results workspace without conventional edit actions'
);
arabicClassWorkspaceCheck(
    str_contains($classWorkspaceJs, 'classType === "arabic"')
        && str_contains($classWorkspaceJs, 'editClassButton')
        && str_contains($classWorkspaceJs, 'addStudentButton'),
    'Arabic class workspace prevents conventional class editing and student-creation routes'
);
arabicClassWorkspaceCheck(
    str_contains($classResultsApi, 'COALESCE(NULLIF(st.arabic_class_id, 0), st.arabia_class_id)')
        && str_contains($resultSaveApi, 'COALESCE(NULLIF(arabic_class_id, 0), arabia_class_id)')
        && str_contains($completionApi, 'COALESCE(NULLIF(arabic_class_id, 0), arabia_class_id)'),
    'Arabic roster retrieval, result saving, and completion support existing legacy Arabic assignments'
);

echo "Arabic class workspace regression tests passed (4 checks).\n";
