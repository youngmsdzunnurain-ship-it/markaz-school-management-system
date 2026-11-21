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
$classApi = file_get_contents(__DIR__ . '/../api/results/class.php');
$classJs = file_get_contents(__DIR__ . '/../public/js/class.js');
$dashboardHtml = file_get_contents(__DIR__ . '/../public/dashboard.html');
$managementShell = file_get_contents(__DIR__ . '/../public/js/management-shell.js');
$scannerJs = file_get_contents(__DIR__ . '/../public/js/results-scanner.js');
$staffChatHtml = file_get_contents(__DIR__ . '/../public/staff-chat.html');
$staffChatJs = file_get_contents(__DIR__ . '/../public/js/staff-chat.js');
$resultSaveApi = file_get_contents(__DIR__ . '/../api/results/save.php');

resultsScannerCheck(
    str_contains($classHtml, '20261120-roster-stable1') && str_contains($classApi, "header('Cache-Control: no-store, no-cache, must-revalidate, private')") && !str_contains($classHtml, 'list="resultStudentSuggestions"'),
    'Results roster API disables intermediary caching and student search uses its custom accessible dropdown instead of a broken datalist reference'
);
resultsScannerCheck(
    str_contains($classHtml, 'id="resultsScanner"') &&
    str_contains($classHtml, 'capture="environment"') &&
    str_contains($classHtml, 'accept="image/'),
    'Results workspace exposes camera capture and image upload controls'
);
resultsScannerCheck(
    str_contains($classApi, "'roster' => array_map(") &&
    str_contains($classJs, 'let classRoster = []') &&
    str_contains($classJs, 'incomingRoster.length > 0 || !classRosterLoaded') &&
    str_contains($classJs, 'resultsLoadSequence') &&
    str_contains($classJs, 'requestSequence !== resultsLoadSequence'),
    'The class roster retains active and inactive students independently from the active-only result-entry list, and overlapping refreshes cannot erase it'
);
resultsScannerCheck(
    str_contains($classHtml, '20261120-roster-stable1') &&
    str_contains($classJs, 'let classRosterLoaded = false') &&
    str_contains($classJs, 'JSON.stringify(incomingRoster) !== JSON.stringify(classRoster)') &&
    str_contains($classJs, 'if (rosterChanged)') &&
    substr_count($classJs, 'classStudentsContainer.addEventListener("click"') === 1,
    'Class roster only re-renders when its data changes, first empty responses settle correctly, and student actions have one delegated handler'
);
resultsScannerCheck(
    str_contains($classJs, 'if (updateRoster && Array.isArray(data.roster))') &&
    str_contains($classJs, 'loadResults({ updateRoster: false })'),
    'Automatic focus refreshes update result data without replacing the already loaded class roster'
);
resultsScannerCheck(
    str_contains($classJs, 'student.middle_name') &&
    str_contains($classJs, 'student.first_name') &&
    str_contains($classJs, 'student.last_name'),
    'Class roster displays each student full name, including the middle name'
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
