<?php
declare(strict_types=1);

$studentJs = file_get_contents(__DIR__ . '/../public/js/student-result.js');
$classJs = file_get_contents(__DIR__ . '/../public/js/class.js');
$printJs = file_get_contents(__DIR__ . '/../public/js/print-results.js');
$completionApi = file_get_contents(__DIR__ . '/../api/results/class-completion.php');
$classApi = file_get_contents(__DIR__ . '/../api/results/class.php');
$printPage = file_get_contents(__DIR__ . '/../public/student-result-print.html');

function checkResultPrintFlow(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}

checkResultPrintFlow(str_contains($classJs, 'await loadResults();') && !str_contains($classJs, 'await loadStudents();'), 'subject loading is independent of student-list loading');
checkResultPrintFlow(str_contains($classApi, 'AND ap.is_current = 1'), 'class result workspace uses the explicitly selected academic period');
checkResultPrintFlow(str_contains($studentJs, 'const data = await fetchStudentResultPayload(false)') && str_contains($studentJs, 'studentData = data;') && str_contains($studentJs, 'await fetchStudentResultPayload(true, permissionAction)') && strpos($studentJs, 'renderStudentResult(data);', strpos($studentJs, 'async function loadStudentResult()')) < strpos($studentJs, 'await fetchStudentResultPayload(true, permissionAction)'), 'preview data loads before print eligibility is checked');
checkResultPrintFlow(str_contains($studentJs, 'resultDocumentActions.hidden = !isPrintMode || editing || !studentData') && !str_contains(file_get_contents(__DIR__ . '/../public/student-result.html'), 'id="downloadStudentPdfButton"'), 'entry page never shows print or PDF actions');
checkResultPrintFlow(str_contains($studentJs, 'if (!isPrintMode || !studentData || resultPrintBlocked) return;'), 'blocked individual result cannot print');
checkResultPrintFlow(str_contains($printJs, 'classList.contains("result-print-blocked")') && str_contains($printJs, 'At least one selected result is not eligible to print'), 'print-all rejects blocked result frames');
checkResultPrintFlow(str_contains($completionApi, '$subjectSql .= " AND EXISTS (') && str_contains($completionApi, 'FROM {$mappingTable} cs') && str_contains(file_get_contents(__DIR__ . '/../api/results/student.php'), '$completionPercent < $printThreshold'), 'class eligibility counts the same required class subjects');
checkResultPrintFlow(!str_contains($printPage, 'id="editScoresButton"') && !str_contains($printPage, 'class="student-edit-actions"'), 'print page contains no edit controls');

echo "Targeted result-print flow regression tests passed.\n";
