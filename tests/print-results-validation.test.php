<?php
declare(strict_types=1);

function printCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$printPage = file_get_contents(__DIR__ . '/../public/print-results.html');
$resultPage = file_get_contents(__DIR__ . '/../public/js/student-result.js');
$completionApi = file_get_contents(__DIR__ . '/../api/results/class-completion.php');
$studentApi = file_get_contents(__DIR__ . '/../api/results/student.php');

printCheck(
    str_contains($printPage, 'class_type=') &&
    str_contains($printPage, 'getClassCompletion(id, "conventional")'),
    'print eligibility request must send the required Conventional class type'
);
printCheck(
    str_contains($printPage, 'class_type: "conventional"') &&
    str_contains($printPage, 'new URLSearchParams'),
    'individual print navigation must preserve the selected class type'
);
printCheck(
    str_contains($completionApi, "class_type'] ?? ''") &&
    str_contains($completionApi, 'A valid class type is required.'),
    'completion endpoint must continue enforcing an explicit class type'
);
printCheck(
    str_contains($resultPage, 'if (isPrintMode) {') &&
    str_contains($resultPage, 'window.print();'),
    'print-mode button must invoke the browser print dialog directly after server-side page authorization'
);
printCheck(
    substr_count($resultPage, 'loadStudentResult().catch(error => {') === 1,
    'student result must be loaded once to avoid competing duplicate initializations'
);
printCheck(
    str_contains($studentApi, "requireRolePermission('print_results')"),
    'print result data must remain protected by server-side print permission'
);

echo "Print eligibility and print-action regression tests passed.\\n";
