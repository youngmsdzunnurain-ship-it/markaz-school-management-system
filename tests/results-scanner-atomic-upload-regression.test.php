<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$scanner = file_get_contents($root . '/public/js/results-scanner.js');
$endpoint = file_get_contents($root . '/api/results/batch-save.php');
$wrapper = file_get_contents($root . '/public/api/results/batch-save.php');
$templateStart = strpos($scanner, 'const TEMPLATE = `');
$cameraPosition = strpos($scanner, '<section class="mkls-camera"', $templateStart === false ? 0 : $templateStart);
$controlsPosition = strpos($scanner, '<div class="mkls-controls"', $templateStart === false ? 0 : $templateStart);

$checks = [
    'scanner offers one icon-only photo upload control' => str_contains($scanner, 'data-mkls-upload-photo') &&
        str_contains($scanner, 'aria-label="Upload photo"') && !str_contains($scanner, 'data-mkls-upload-folder'),
    'photo picker input remains usable in Android browser file choosers' => str_contains($scanner, 'class="mkls-photo-input"') && !str_contains($scanner, 'data-mkls-photo-input accept="image/*" hidden'),
    'photo picker releases camera and OCR resources before opening gallery' => str_contains($scanner, 'function openPhotoPicker()') && strpos($scanner, 'stopStream();', strpos($scanner, 'function openPhotoPicker()')) < strpos($scanner, 'input.click();', strpos($scanner, 'function openPhotoPicker()')) && str_contains($scanner, 'worker.terminate()'),
    'cancelled photo picker returns to a working camera preview' => str_contains($scanner, 'addEventListener("cancel"') && str_contains($scanner, 'startCamera();'),
    'scanner has an accessible torch on/off control' => str_contains($scanner, 'data-mkls-torch') &&
        str_contains($scanner, 'function toggleTorch()') && str_contains($scanner, 'torch: next'),
    'scanner shows the selected photo while processing' => str_contains($scanner, 'data-mkls-upload-preview') &&
        str_contains($scanner, 'URL.createObjectURL(file)'),
    'photo and torch controls render below camera preview' => $cameraPosition !== false &&
        $controlsPosition !== false && $controlsPosition > $cameraPosition,
    'printed subject headings tolerate bounded OCR errors and split words' => str_contains($scanner, 'target.length >= 9 ? 2 : target.length >= 5 ? 1 : 0') &&
        str_contains($scanner, 'Math.min(words.length, i + 7)'),
    'photo scan can recover subject columns from repeated CA1 CA2 Exam header triplets' => str_contains($scanner, 'function inferSubjectSpansFromColumnLabels(') && str_contains($scanner, 'inferSubjectSpansFromColumnLabels(headerWords, subjects)'),
    'photo scan maps score columns to class subjects only when every ordered header triplet is present' => str_contains($scanner, 'labels.length !== subjects.length * FIELDS.length') && str_contains($scanner, '["ca1", "ca2", "exam"]'),
    'photo review retains extracted score cells when subject headings are recovered by column position' => str_contains($scanner, 'const detectedSpans = findSubjectSpans(headerWords, subjects);') && str_contains($scanner, 'detectedSpans.length ? detectedSpans : inferSubjectSpansFromColumnLabels(headerWords, subjects)'),
    'single A4 photo is processed at higher resolution for dense subject grids' => str_contains($scanner, 'const OCR_MAX_WIDTH = 2800;') && str_contains($scanner, 'const OCR_MAX_HEIGHT = 4000;'),
    'numeric OCR uses character boxes to separate adjacent printed score cells' => str_contains($scanner, 'function digitItemsOf(result)') && str_contains($scanner, 'result.data.symbols') && str_contains($scanner, 'near.map(number => number.text).join("")'),
    'score extraction keeps each digit group inside its own detected score column' => str_contains($scanner, 'Math.abs(number.cx - cols[field]) <= reach') && str_contains($scanner, 'if (!NUMBER.test(raw)) ok = false;'),
    'subject subset is accepted when its own headings are recognised' => str_contains($scanner, 'const detectedSpans = findSubjectSpans(headerWords, subjects);') && str_contains($scanner, 'detectedSpans.length ? detectedSpans : inferSubjectSpansFromColumnLabels(headerWords, subjects)'),
    'photo review limits rows to subjects actually detected on a partial-subject worksheet' => str_contains($scanner, 'const reviewSubjects = subjects.length ? subjects : (state.meta.subjects || [])'),
    'photo scan reports individual captured cells against the selected worksheet subject count' => str_contains($scanner, 'const totalScoreCells = classStudents().length * reviewSubjects.length * FIELDS.length') && str_contains($scanner, '${capturedScoreCells} of ${totalScoreCells} score cells read'),
    'photo scan retries sparse-text OCR when headings are missed' => str_contains($scanner, 'tessedit_pageseg_mode: "11"') &&
        str_contains($scanner, 'Recovering printed subject and score columns'),
    'photo scan retries sparse OCR when either headings or score cells are missing' => str_contains($scanner, 'function parsedScoreCount(parsed)') && str_contains($scanner, 'parsedScoreCount(sparseParsed) > parsedScoreCount(parsed)'),
    'photo scan reads score digits again in sparse page mode when normal OCR misses them' => str_contains($scanner, 'tessedit_char_whitelist: "0123456789.", tessedit_pageseg_mode: "11"'),
    'photo scan opens review instead of rejecting an unrecognised heading' => str_contains($scanner, 'All subjects assigned to this class are listed below') &&
        !str_contains($scanner, 'Try a sharper, straight-on photo'),
    'review save uses the subjects actually shown in the review table' => str_contains($scanner, 'state.reviewSubjects = [...subjects]') &&
        str_contains($scanner, 'state.reviewSubjects.length ? state.reviewSubjects : confirmedSubjects()'),
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
