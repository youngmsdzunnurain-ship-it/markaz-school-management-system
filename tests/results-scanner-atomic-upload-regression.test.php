<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$scanner = file_get_contents($root . '/public/js/results-scanner.js');
$endpoint = file_get_contents($root . '/api/results/batch-save.php');
$wrapper = file_get_contents($root . '/public/api/results/batch-save.php');

$checks = [
    'scanner offers one icon-only photo upload control' => str_contains($scanner, 'data-mkls-upload-photo') &&
        str_contains($scanner, 'aria-label="Upload photo"') && !str_contains($scanner, 'data-mkls-upload-folder'),
    'scanner has an accessible torch on/off control' => str_contains($scanner, 'data-mkls-torch') &&
        str_contains($scanner, 'function toggleTorch()') && str_contains($scanner, 'torch: next'),
    'scanner shows the selected photo while processing' => str_contains($scanner, 'data-mkls-upload-preview') &&
        str_contains($scanner, 'URL.createObjectURL(file)'),
    'scanner shows class student and subject counts before progress' => str_contains($scanner, 'data-mkls-class-name') &&
        str_contains($scanner, 'data-mkls-student-count') && str_contains($scanner, 'data-mkls-subject-count'),
    'scanner reports staged progress through completion' => str_contains($scanner, 'setUploadProgress(100, "Scan complete")'),
    'scanner requires review before saving completed scans' => str_contains($scanner, 'Review the marks, then save.') &&
        str_contains($scanner, 'buildReview(subjects)'),
    'uploaded images are processed locally' => str_contains($scanner, 'createImageBitmap(file)'),
    'scanner uses one batch request' => str_contains($scanner, './api/results/batch-save.php'),
    'scanner blocks partial saves when any row conflicts' => str_contains($scanner, 'Resolve every conflicting or incomplete result row before saving. No results were saved.'),
    'scanner no longer loops over individual save requests' => !str_contains($scanner, 'fetch("./api/results/save.php"'),
    'invalid automatic scan is sent to review' => str_contains($scanner, 'need correction before anything can be saved'),
    'batch endpoint validates class type' => str_contains($endpoint, "['conventional', 'arabic']"),
    'batch endpoint opens a transaction' => str_contains($endpoint, '$pdo->beginTransaction()'),
    'batch endpoint rolls back failed batches' => str_contains($endpoint, '$pdo->rollBack()'),
    'batch endpoint locks academic period' => str_contains($endpoint, 'FOR UPDATE'),
    'public API wrapper exists' => str_contains($wrapper, 'api/results/batch-save.php'),
    'scanner has ambiguity-safe fuzzy roster matching' => str_contains($scanner, 'function fuzzyStudentMatch(') &&
        str_contains($scanner, 'second.distance - best.distance < 2'),
    'scanner applies fuzzy matching only after exact roster matching' => str_contains($scanner, 'rosterMatch(line, students) || fuzzyStudentMatch(line, students)'),
    'scanner does not match short OCR fragments fuzzily' => str_contains($scanner, 'candidate.length < 6'),
    'fuzzy matching strips admission-number digits before comparing names' => str_contains($scanner, 'replace(/\\d+/g, "")')
];

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL;
    if (!$passed) $failed++;
}
if ($failed) {
    fwrite(STDERR, $failed . ' scanner regression check(s) failed.' . PHP_EOL);
    exit(1);
}
echo 'MARKAZ scanner atomic-upload regression checks passed.' . PHP_EOL;
