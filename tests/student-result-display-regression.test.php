<?php
declare(strict_types=1);

function studentDisplayCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$classApi = file_get_contents(__DIR__ . '/../api/results/class.php');
$classJs = file_get_contents(__DIR__ . '/../public/js/class.js');
$classHtml = file_get_contents(__DIR__ . '/../public/class.html');
$studentPage = file_get_contents(__DIR__ . '/../public/student-result.html');
$printPage = file_get_contents(__DIR__ . '/../public/student-result-print.html');
$printJs = file_get_contents(__DIR__ . '/../public/js/print-results.js');
$resultJs = file_get_contents(__DIR__ . '/../public/js/student-result.js');

studentDisplayCheck(
    str_contains($classApi, 'ac.name AS arabic_class_name')
        && str_contains($classApi, 'LEFT JOIN arabic_classes ac')
        && str_contains($classApi, "'arabic_class_id' =>")
        && str_contains($classApi, "'arabic_class_name' =>"),
    'class roster API returns each student Arabic class ID and display name'
);

studentDisplayCheck(
    str_contains($classJs, 'student.arabic_class_name || "—"')
        && str_contains($classApi, "'first_name' =>")
        && str_contains($classApi, "'last_name' =>"),
    'class roster renders the student identity and Arabic class fields supplied by its API'
);

studentDisplayCheck(
    str_contains($classHtml, 'id="resultSubjectSelect"')
        && str_contains($classJs, 'populateSubjectSelect();')
        && str_contains($classJs, 'resultSubjects = data.subjects || [];'),
    'class result subject selector is populated from the selected class subject list'
);

studentDisplayCheck(
    str_contains($classHtml, 'class.js?v=20261120-roster-stable1')
        && str_contains($classJs, 'display_version=20261106-targeted-student-display-fix'),
    'class workspace and student result navigation bypass stale cached markup/scripts'
);

studentDisplayCheck(
    str_contains($printJs, 'display_version: "20261106-targeted-student-display-fix"'),
    'print-result navigation bypasses stale cached printable page markup'
);

studentDisplayCheck(
    !str_contains($studentPage, 'id="printStudentResultButton"')
        && !str_contains($studentPage, 'id="downloadStudentPdfButton"')
        && str_contains($studentPage, 'id="editScoresButton"'),
    'normal student result management page exposes Edit only, not print or PDF actions'
);

studentDisplayCheck(
    str_contains($printPage, 'id="printStudentResultButton"')
        && !str_contains($printPage, 'id="downloadStudentPdfButton"')
        && !str_contains($printPage, 'Download PDF')
        && !str_contains($printPage, 'id="editScoresButton"'),
    'dedicated printable result page owns the browser Print action only and has no Edit action'
);

studentDisplayCheck(
    str_contains($printJs, './student-result-print.html?')
        && str_contains($resultJs, 'renderStudentResult(data);')
        && str_contains($resultJs, 'document.body.dataset.resultReady = "true";'),
    'print selection opens the dedicated printable page and waits for student result rendering'
);


studentDisplayCheck(
    str_contains($resultJs, 'function showStudentPhotoInitials(')
        && str_contains($resultJs, 'image.addEventListener("error"')
        && str_contains($resultJs, 'image.alt = "";'),
    'missing or broken student photos fall back to initials without rendering the full name as image alt text'
);

studentDisplayCheck(
    str_contains($resultJs, 'async function waitForStudentPhotoImage(')
        && str_contains($resultJs, 'await waitForStudentPhotoImage();'),
    'student photo loading or fallback completes before the browser opens print'
);

echo "Targeted student/result display regression tests passed.\\n";
