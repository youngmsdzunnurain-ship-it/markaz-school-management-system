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
$printJs = file_get_contents(__DIR__ . '/../public/js/print-results.js');
$resultPage = file_get_contents(__DIR__ . '/../public/js/student-result.js');
$classPage = file_get_contents(__DIR__ . '/../public/js/class.js');
$completionApi = file_get_contents(__DIR__ . '/../api/results/class-completion.php');
$studentApi = file_get_contents(__DIR__ . '/../api/results/student.php');

printCheck(
    str_contains($classPage, 'const classLabel = /^ss\\s*1$/i.test(displayedClassName)') &&
    str_contains($classPage, 'classTitle.textContent = classLabel;'),
    'the existing SS1 class result page must display SS1 Science without creating another class'
);
printCheck(
    str_contains($printJs, 'return /^ss\\s*1$/i.test(String(name).trim()) ? "SS1 Science" : name;') &&
    str_contains($printJs, 'title.textContent = displayClassName(item);') &&
    str_contains($resultPage, '/^ss\\s*1$/i.test(displayedClassName) ? "SS1 Science"'),
    'SS1 must be displayed as SS1 Science in class selection and individual results without creating a new class record'
);
printCheck(
    str_contains($printJs, 'getClassCompletion(id, "conventional")') &&
    str_contains($printJs, 'class_type: "conventional"'),
    'print eligibility request must send the required Conventional class type'
);
printCheck(
    str_contains($printJs, 'class_type: "conventional"') &&
    str_contains($printJs, 'new URLSearchParams'),
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
    str_contains($printPage, 'id="printAllEligibleButton"') &&
    str_contains($printJs, 'printAllEligibleStudents(id, eligibleIds)') &&
    str_contains($printJs, 'class_type: "conventional"') &&
    str_contains($printJs, 'print: "1"'),
    'Print All must include only server-verified eligible students in the selected class'
);
printCheck(
    str_contains($resultPage, 'document.body.dataset.resultReady = "true"'),
    'batch printing must wait until each student result and behavioural report have rendered'
);
printCheck(
    str_contains($studentApi, "requireRolePermission('print_results')"),
    'print result data must remain protected by server-side print permission'
);

echo "Print eligibility and print-action regression tests passed.\\n";
