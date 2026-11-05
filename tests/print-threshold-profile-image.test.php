<?php
declare(strict_types=1);

function printProfileCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$settingsPage = file_get_contents(__DIR__ . '/../public/settings.html');
$thresholdApi = file_get_contents(__DIR__ . '/../api/settings/result-completion.php');
$thresholdUi = file_get_contents(__DIR__ . '/../public/js/result-print-threshold-settings.js');
$thresholdNavigation = file_get_contents(__DIR__ . '/../public/js/settings-panel-navigation.js');
$classCompletion = file_get_contents(__DIR__ . '/../api/results/class-completion.php');
$studentResultApi = file_get_contents(__DIR__ . '/../api/results/student.php');
$studentCreate = file_get_contents(__DIR__ . '/../api/students/create.php');
$studentUpdate = file_get_contents(__DIR__ . '/../api/students/update.php');
$studentManagementJs = file_get_contents(__DIR__ . '/../public/js/students.js');
$studentListApi = file_get_contents(__DIR__ . '/../api/students/list.php');
$studentsPage = file_get_contents(__DIR__ . '/../public/students.html');

printProfileCheck(
    str_contains($settingsPage, 'resultPrintThresholdForm') &&
    str_contains($settingsPage, 'Print Eligibility Threshold'),
    'settings must expose a separate result-completion threshold control'
);
printProfileCheck(
    str_contains($thresholdNavigation, 'id: "result-print-threshold"') &&
    str_contains($thresholdNavigation, 'page.querySelector(".result-print-threshold-card")'),
    'print eligibility threshold must be reachable as its own Settings navigation category'
);
printProfileCheck(
    preg_match('/<select id="resultPrintThreshold"[^>]*>(.*?)<\/select>/s', $settingsPage, $thresholdSelect) === 1 &&
    preg_match_all('/<option value="([0-9]+)">/', $thresholdSelect[1], $thresholdOptionMatches) === 11 &&
    $thresholdOptionMatches[1] === ['50', '55', '60', '65', '70', '75', '80', '85', '90', '95', '100'],
    'print threshold must offer every 5-percent choice from 50 through 100 percent'
);
printProfileCheck(
    !str_contains($settingsPage, 'resultPrintScale') &&
    !str_contains($thresholdNavigation, 'result-print-scale') &&
    !str_contains(file_get_contents(__DIR__ . '/../public/css/settings.css'), 'result-print-scale-card') &&
    !file_exists(__DIR__ . '/../public/js/result-print-scale-settings.js') &&
    !file_exists(__DIR__ . '/../api/settings/result-print-scale.php') &&
    !file_exists(__DIR__ . '/../public/api/settings/result-print-scale.php'),
    'the obsolete print-scale setting must be removed from Settings, navigation, styling, frontend and backend'
);
$scaleCleanupMigration = file_get_contents(__DIR__ . '/../database/migrations/20261105_remove_result_print_scale.sql');
printProfileCheck(
    str_contains($scaleCleanupMigration, "DELETE FROM settings WHERE setting_key = 'result_print_scale'"),
    'the obsolete persisted print-scale setting must be removed without touching other settings'
);
printProfileCheck(
    str_contains($thresholdApi, 'result_print_completion_threshold') &&
    str_contains($thresholdApi, 'requireSeniorAdmin()'),
    'threshold setting must persist under the exact key read by print eligibility and be Senior Admin-only'
);
printProfileCheck(
    str_contains($thresholdUi, 'result-completion.php') &&
    str_contains($thresholdUi, 'JSON.stringify({ threshold: Number(threshold) })'),
    'threshold UI must send and confirm the selected threshold'
);
printProfileCheck(
    str_contains($classCompletion, 'round(($completed / $required) * 100, 2)') &&
    str_contains($studentResultApi, 'round(($completedCount / $requiredCount) * 100, 2)'),
    'class eligibility and individual print authorization must use identical percentage precision at threshold boundaries'
);
printProfileCheck(
    str_contains($classCompletion, "execute(['result_print_completion_threshold'])") &&
    str_contains($studentResultApi, "execute(['result_print_completion_threshold'])"),
    'class list and individual print authorization must read the same threshold setting'
);
printProfileCheck(
    str_contains($studentCreate, 'chmod($uploadDirectory, 0755)') &&
    str_contains($studentCreate, 'chmod($destination, 0644)') &&
    str_contains($studentUpdate, 'chmod($uploadDirectory, 0755)') &&
    str_contains($studentUpdate, 'chmod($destination, 0644)'),
    'new student photo uploads must be readable by the web server'
);
printProfileCheck(
    str_contains($studentListApi, 's.profile_image') &&
    str_contains($studentResultApi, 'st.profile_image') &&
    str_contains($studentResultApi, "'profile_image' =>"),
    'student management and result APIs must return the saved profile image path'
);
printProfileCheck(
    str_contains($studentManagementJs, 'src="./${escapeHtml(String(student.profile_image)') &&
    str_contains($studentManagementJs, 'class="view-student-photo"') &&
    !str_contains($studentManagementJs, 'style="background-image:url("./${student.profile_image}")"') &&
    str_contains($studentsPage, 'capture="environment"') &&
    !str_contains($studentsPage, 'studentCameraModal') &&
    !str_contains($studentManagementJs, 'MarkazCameraAI'),
    'student profiles must render valid photos and use the native device camera without the old live camera'
);

echo "Print threshold and student profile image regression tests passed.\n";
