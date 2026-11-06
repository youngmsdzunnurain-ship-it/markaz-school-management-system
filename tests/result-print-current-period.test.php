<?php
declare(strict_types=1);
function printCurrentPeriodCheck(bool $condition, string $message): void { if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
$completionApi = file_get_contents(__DIR__ . '/../api/results/class-completion.php');
$studentApi = file_get_contents(__DIR__ . '/../api/results/student.php');
$studentJs = file_get_contents(__DIR__ . '/../public/js/student-result.js');
$printPage = file_get_contents(__DIR__ . '/../public/student-result-print.html');
$screenCss = file_get_contents(__DIR__ . '/../public/css/student-result.css');
$printCss = file_get_contents(__DIR__ . '/../public/css/student-result-print.css');
printCurrentPeriodCheck(preg_match('/WHERE\s+s\.is_current\s*=\s*1\s+AND\s+ap\.is_current\s*=\s*1/is', $completionApi) === 1, 'print eligibility must use the explicitly selected academic period');
printCurrentPeriodCheck(str_contains($studentApi, 'AND ap.is_current = 1'), 'individual results must use the explicitly selected academic period');
printCurrentPeriodCheck(str_contains($studentJs, 'let data = await fetchStudentResultPayload(false)') && str_contains($studentJs, 'data = await fetchStudentResultPayload(true)') && str_contains($studentJs, 'resultPrintBlocked'), 'incomplete print requests must load a read-only preview');
printCurrentPeriodCheck(str_contains($printPage, 'id="resultPrintBlockedNotice"'), 'print page must contain the incomplete-result notice');
printCurrentPeriodCheck(str_contains($screenCss, '.result-print-blocked-notice') && str_contains($printCss, 'body.result-print-blocked #studentReportCard') && str_contains($printCss, 'body.result-print-blocked .result-print-blocked-notice'), 'blocked previews must not print the report card');
echo "Print-result current-period and incomplete-preview regression tests passed.\n";
