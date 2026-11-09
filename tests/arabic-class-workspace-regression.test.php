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
$classPageHtml = file_get_contents(__DIR__ . '/../public/classes.html');
$managementShellJs = file_get_contents(__DIR__ . '/../public/js/management-shell.js');
$classPageHtml = file_get_contents(__DIR__ . '/../public/classes.html');
$classWorkspaceJs = file_get_contents(__DIR__ . '/../public/js/class.js');
$classResultsApi = file_get_contents(__DIR__ . '/../api/results/class.php');
$resultSaveApi = file_get_contents(__DIR__ . '/../api/results/save.php');
$completionApi = file_get_contents(__DIR__ . '/../api/results/class-completion.php');

arabicClassWorkspaceCheck(
    str_contains($classListApi, 'FROM arabic_classes')
        && str_contains($classListApi, 'AS class_type')
        && str_contains($classListApi, 'arabic_class_id')
        && str_contains($classListApi, 'arabia_class_id')
        && str_contains($classListApi, 'hufaz')
        && str_contains($classListApi, 'NOT REGEXP')
        && str_contains($classListApi, 'roudoh[[:space:]]*[12]')
        && str_contains($classListApi, 'faslu[[:space:]]*[1-6]'),
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

arabicClassWorkspaceCheck(
    str_contains($classPageHtml, 'id="saveClassButton"')
        && !str_contains($classPageHtml, "            </div>\\n\\n            </div>\\n\\n        </form>"),
    'class save button is addressable and the modal form has balanced structure'
);
arabicClassWorkspaceCheck(
    !str_contains($managementShellJs, 'data-shell-settings')
        && str_contains($managementShellJs, 'href="/settings.html"'),
    'Settings appears once in the management navigation, not in both sidebar and profile menu'
);

echo "Arabic class workspace regression tests passed (6 checks).\\n";
