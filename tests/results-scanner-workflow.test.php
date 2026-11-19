<?php
declare(strict_types=1);

function resultsScannerCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$classHtml = file_get_contents(__DIR__ . '/../public/class.html');
$classJs = file_get_contents(__DIR__ . '/../public/js/class.js');
$dashboardHtml = file_get_contents(__DIR__ . '/../public/dashboard.html');
$managementShell = file_get_contents(__DIR__ . '/../public/js/management-shell.js');
$scannerJs = file_get_contents(__DIR__ . '/../public/js/results-scanner.js');
$staffChatHtml = file_get_contents(__DIR__ . '/../public/staff-chat.html');
$staffChatJs = file_get_contents(__DIR__ . '/../public/js/staff-chat.js');
$resultSaveApi = file_get_contents(__DIR__ . '/../api/results/save.php');

resultsScannerCheck(
    str_contains($classHtml, 'id="resultsScanner"') &&
    str_contains($classHtml, 'capture="environment"') &&
    str_contains($classHtml, 'accept="image/'),
    'Results workspace exposes camera capture and image upload controls'
);
resultsScannerCheck(
    str_contains($classJs, 'MarkazResultsScanner.configure') &&
    str_contains($classJs, 'selectedSubjectId') &&
    str_contains($classJs, 'results: classResults'),
    'Scanner is connected to the selected class, subject and roster'
);
resultsScannerCheck(
    str_contains($scannerJs, 'Tesseract.createWorker') &&
    str_contains($scannerJs, 'worker.recognize') &&
    str_contains($scannerJs, 'ara+eng') &&
    str_contains($scannerJs, 'admissionMatches.length === 1') &&
    str_contains($scannerJs, 'partialNameMatches.length === 1') &&
    str_contains($scannerJs, '\\p{L}') &&
    str_contains($scannerJs, 'credentials: "same-origin"'),
    'OCR is local, supports Arabic and English, and saves through the authenticated results endpoint'
);
resultsScannerCheck(
    str_contains($scannerJs, 'Review every detected mark') &&
    str_contains($scannerJs, 'Blank marks are never saved') &&
    str_contains($scannerJs, 'Save reviewed results') &&
    str_contains($scannerJs, 'dataset.studentId') &&
    str_contains($resultSaveApi, 'requireResultStaff()') &&
    str_contains($resultSaveApi, 'requireTeacherAssignment('),
    'Recognised marks require review and the existing server-side result permissions'
);
resultsScannerCheck(
    str_contains($dashboardHtml, "Let's Chat") &&
    str_contains($staffChatHtml, "Let's Chat") &&
    str_contains($staffChatJs, "Let's chat with a staff member."),
    'Staff chat navigation and conversation actions use the clearer wording'
);
resultsScannerCheck(
    !str_contains($dashboardHtml, 'js/markaz-ai.js') &&
    !str_contains($managementShell, 'markaz-ai.js'),
    'General-purpose AI assistant is no longer loaded globally or from the dashboard'
);
resultsScannerCheck(
    !str_contains($classHtml, 'markaz-ai.js') &&
    str_contains($classHtml, 'results-scanner.js'),
    'Class results page loads the dedicated scanner without loading the AI assistant'
);

echo "MARKAZ results scanner workflow checks passed.\n";
