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
$thresholdApi = file_get_contents(__DIR__ . '/../api/settings/result-print-threshold.php');
$thresholdUi = file_get_contents(__DIR__ . '/../public/js/result-print-threshold-settings.js');
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
    str_contains($thresholdApi, 'result_print_completion_threshold') &&
    str_contains($thresholdApi, 'requireSeniorAdmin()'),
    'threshold setting must persist under the exact key read by print eligibility and be Senior Admin-only'
);
printProfileCheck(
    str_contains($thresholdUi, 'result-print-threshold.php') &&
    str_contains($thresholdUi, 'JSON.stringify({ threshold: Number(threshold) })'),
    'threshold UI must send and confirm the selected threshold'
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
