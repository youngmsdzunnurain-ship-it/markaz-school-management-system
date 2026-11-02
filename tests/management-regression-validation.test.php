<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
function checkFix(bool $ok, string $message): void {
    global $failures;
    if (!$ok) $failures[] = $message;
}

$studentJs = file_get_contents($root . '/public/js/student-result.js');
$studentHtml = file_get_contents($root . '/public/student-result.html');
$subjectListApi = file_get_contents($root . '/api/subjects/list.php');
$curriculum = file_get_contents($root . '/app/helpers/ss-curriculum.php');
$shellJs = file_get_contents($root . '/public/js/management-shell.js');
$shellCss = file_get_contents($root . '/public/css/management-shell.css');
$subjectCss = file_get_contents($root . '/public/subjects/subjects.css');

checkFix(
    preg_match('/async\\s+function\\s+loadStudentResult\\s*\\(/', $studentJs) === 1,
    'Student result loader must be async so its await statements do not break the entire script.'
);
checkFix(
    str_contains($studentHtml, 'id="saveAndLeaveResultButton"'),
    'Unsaved changes dialog must offer a working Save and Leave action.'
);
checkFix(
    str_contains($studentJs, 'saveAndLeaveResultButton') &&
    str_contains($studentJs, 'await saveAllStudentChanges()'),
    'Save and Leave must persist changes before navigating.'
);
checkFix(
    str_contains($subjectListApi, 'seenSubjectKeys') &&
    str_contains($subjectListApi, 'animal husbandry'),
    'Subject library must hide duplicate names and Animal Husbandry.'
);
checkFix(
    str_contains($curriculum, 'normalizeSeniorSubjectKey') &&
    str_contains($curriculum, 'existingSubjectIdsByKey'),
    'SS curriculum must reuse existing subject records by normalized names instead of inserting duplicates.'
);
checkFix(
    str_contains($shellJs, 'admin-profile-avatar') &&
    str_contains($shellJs, 'menu-button') &&
    str_contains($shellJs, 'api/auth/me.php'),
    'Shared management shell must use dashboard-style menu and authenticated avatar.'
);
checkFix(
    str_contains($shellCss, 'position: fixed') &&
    str_contains($shellCss, 'markaz-management-shell-active'),
    'Shared management shell must remain sticky and reserve sidebar space.'
);
checkFix(
    str_contains($subjectCss, 'grid-template-columns: max-content minmax(0, 1fr) max-content'),
    'Subject page title bar must align back link, heading and action on one grid.'
);

$managementPages = [
    'attendance.html', 'class.html', 'classes.html', 'print-results.html',
    'school-fees.html', 'settings.html', 'student-result.html', 'students.html',
    'subjects/index.html'
];
foreach ($managementPages as $page) {
    $html = file_get_contents($root . '/public/' . $page);
    checkFix(
        str_contains($html, 'management-shell.js?v=20261102-fix1'),
        'Management shell is missing from ' . $page
    );
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, array_map(fn($m) => 'FAIL: ' . $m, $failures)) . PHP_EOL);
    exit(1);
}
echo "Management shell, subjects and result-editing regression checks passed." . PHP_EOL;
