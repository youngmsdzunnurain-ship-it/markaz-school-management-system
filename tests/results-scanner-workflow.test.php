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

resultsScannerCheck(
    str_contains($classHtml, 'id="resultsScanner"') &&
    str_contains($classHtml, 'capture="environment"') &&
    str_contains($classHtml, 'accept="image/'),
    'Results workspace exposes camera capture and image upload controls'
);
resultsScannerCheck(
    str_contains($classJs, 'ResultsScanner.mount') &&
    str_contains($classJs, 'selectedSubjectId'),
    'Scanner is mounted with the active class, subject, roster and result-save workflow'
);
resultsScannerCheck(
    str_contains($scannerJs, 'Tesseract.createWorker') &&
    str_contains($scannerJs, 'worker.recognize') &&
    str_contains($scannerJs, 'credentials: "same-origin"'),
    'OCR runs locally in the browser and saves only through the authenticated same-origin results API'
);
resultsScannerCheck(
    str_contains($scannerJs, 'Review every detected mark') &&
    str_contains($scannerJs, 'Save reviewed results') &&
    str_contains($scannerJs, 'data-student-id'),
    'Recognised marks are editable and require explicit staff review before saving'
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
