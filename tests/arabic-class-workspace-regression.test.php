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
$classWorkspaceJs = file_get_contents(__DIR__ . '/../public/js/class.js');
$classResultsApi = file_get_contents(__DIR__ . '/../api/results/class.php');
$resultSaveApi = file_get_contents(__DIR__ . '/../api/results/save.php');
$completionApi = file_get_contents(__DIR__ . '/../api/results/class-completion.php');
$studentListApi = file_get_contents(__DIR__ . '/../api/students/list.php');
$attendanceReportsApi = file_get_contents(__DIR__ . '/../api/attendance/reports.php');
$studentResultApi = file_get_contents(__DIR__ . '/../api/results/student.php');
$arabicNamesHelper = file_get_contents(__DIR__ . '/../app/helpers/arabic-class-names.php');
$settingsNavigationJs = file_get_contents(__DIR__ . '/../public/js/settings-panel-navigation.js');
$settingsCss = file_get_contents(__DIR__ . '/../public/css/settings.css');
$settingsJs = file_get_contents(__DIR__ . '/../public/js/settings.js');

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
        && str_contains($classListJs, 'data-class-type=')
        && str_contains($classListJs, 'api/arabic-classes/update.php')
        && str_contains($classListJs, 'api/arabic-classes/toggle-status.php')
        && str_contains($classListJs, 'data.total_students'),
    'Arabic class rows expose type-aware edit and activation actions without crossing into conventional class records'
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
        && !str_contains($classPageHtml, "            </div>\n\n            </div>\n\n        </form>"),
    'class save button is addressable and the modal form has balanced structure'
);

arabicClassWorkspaceCheck(
    str_contains($arabicNamesHelper, "'roudoh sani' => 'Roudoh Thani'")
        && str_contains($arabicNamesHelper, "'faslu ula' => 'Faslu Awal'")
        && str_contains($arabicNamesHelper, "'faslu salis' => 'Faslu Thalith'")
        && str_contains($classListApi, 'markazCanonicalArabicClassName')
        && str_contains($classResultsApi, 'markazCanonicalArabicClassName')
        && str_contains($studentResultApi, 'markazCanonicalArabicClassName')
        && str_contains($studentResultApi, 'COALESCE(NULLIF(st.arabic_class_id, 0), st.arabia_class_id)')
        && str_contains($studentListApi, 'markazCanonicalArabicClassName')
        && str_contains($studentListApi, 'COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id)')
        && str_contains($attendanceReportsApi, 'markazCanonicalArabicClassName'),
    'canonical Arabic spellings are used by class lists, class results, and printable student results with legacy assignments'
);
arabicClassWorkspaceCheck(
    strpos($settingsNavigationJs, 'await fetch("./api/auth/me.php"') < strpos($settingsNavigationJs, 'const allCategories = [')
        && strpos($settingsNavigationJs, 'const allCategories = [') < strrpos($settingsNavigationJs, 'classList.remove("settings-page-pending")')
        && str_contains($settingsNavigationJs, 'role === "management"')
        && str_contains(file_get_contents(__DIR__ . '/../public/settings.html'), 'settings-page-pending')
        && str_contains($settingsCss, 'html.settings-page-pending body .settings-page{visibility:hidden!important}'),
    'Settings keeps role-specific navigation hidden until the authenticated role is resolved'
);
arabicClassWorkspaceCheck(
    str_contains($settingsJs, 'id="saveSupportContactButton"')
        && str_contains($settingsCss, '.markaz-support-settings #saveSupportContactButton')
        && str_contains($settingsCss, '#markazSupportStatus'),
    'emergency support contact has a dedicated styled save action and visible status feedback'
);

echo "Arabic class workspace regression tests passed (8 checks).\n";
