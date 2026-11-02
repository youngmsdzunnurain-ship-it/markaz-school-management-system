<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
function subjectLayoutCheck(bool $ok, string $message): void {
    global $failures;
    if (!$ok) $failures[] = $message;
}

$sharedHeading = file_get_contents($root . '/public/css/standard-page-heading.css');
$dashboardCss = file_get_contents($root . '/public/css/dashboard.css');
$shellCss = file_get_contents($root . '/public/css/management-shell.css');
$subjectCss = file_get_contents($root . '/public/subjects/subjects.css');
$subjectJs = file_get_contents($root . '/public/subjects/subjects.js');
$subjectHtml = file_get_contents($root . '/public/subjects/index.html');
$attendanceCss = file_get_contents($root . '/public/css/attendance.css');
$resultCss = file_get_contents($root . '/public/css/student-result.css');
$createApi = file_get_contents($root . '/api/subjects/create.php');
$updateApi = file_get_contents($root . '/api/subjects/update.php');
$listApi = file_get_contents($root . '/api/subjects/list.php');
$groupHelper = file_get_contents($root . '/app/helpers/subject-categories.php');
$migration = file_get_contents($root . '/database/migrations/20261102_subject-groups-codes.sql');

subjectLayoutCheck(
    !str_contains($sharedHeading, 'position: sticky !important;')
        && !str_contains($sharedHeading, 'position: sticky;'),
    'Legacy management title bars must not remain sticky beneath the shared avatar header.'
);
subjectLayoutCheck(
    !str_contains($dashboardCss, 'position:sticky !important;\n    top:0 !important;\n    z-index:1100 !important;')
        && !str_contains($dashboardCss, 'position: sticky;\n    position: -webkit-sticky;\n    top: 0;\n    z-index: 1100;'),
    'Dashboard stylesheet must not re-pin the old page title bars.'
);

subjectLayoutCheck(
    str_contains($shellCss, 'rgba(9,43,29,.76)')
        && str_contains($shellCss, 'backdrop-filter:blur')
        && str_contains($shellCss, 'animation:'),
    'The shared management bar must use translucent glass and a subtle entrance animation.'
);
subjectLayoutCheck(
    str_contains($subjectCss, 'backdrop-filter: blur')
        && str_contains($subjectCss, '@keyframes subjectsHeaderEnter')
        && str_contains($subjectCss, 'grid-template-columns: max-content minmax(0, 1fr) max-content'),
    'Subject title bar must align correctly and use translucent glass styling.'
);
subjectLayoutCheck(
    str_contains($attendanceCss, 'grid-template-columns:repeat(5,minmax(0,1fr))!important;'),
    'All five attendance summary cards must remain in one row at narrow widths.'
);
subjectLayoutCheck(
    str_contains($resultCss, 'grid-template-columns:repeat(2,minmax(0,1fr))!important;')
        && str_contains($resultCss, '.markaz-confirm-actions .markaz-confirm-leave{grid-column:1/-1!important}'),
    'Unsaved changes dialog actions must have a clear two-column layout and full-width leave action.'
);
subjectLayoutCheck(
    str_contains($subjectHtml, 'id="subjectGroup"')
        && str_contains($subjectHtml, 'Subject Group')
        && str_contains($subjectHtml, 'School Level'),
    'Subject editor must distinguish academic group from the school level.'
);
subjectLayoutCheck(
    str_contains($subjectJs, 'subjectGroup')
        && str_contains($subjectJs, 'subject_group')
        && str_contains($subjectJs, 'subject.subject_group')
        && str_contains($subjectJs, 'suggestSubjectGroupForName'),
    'Subject editor must load, render and save the separate subject group.'
);
subjectLayoutCheck(
    str_contains($createApi, 'subject_group')
        && str_contains($updateApi, 'subject_group')
        && str_contains($listApi, 'subject_group'),
    'Subject APIs must store and return the separate group without repurposing school level.'
);
subjectLayoutCheck(
    str_contains($createApi, 'Choose a valid school level.')
        && str_contains($updateApi, 'Choose a valid school level.'),
    'Existing school-level validation must remain intact alongside the new subject groups.'
);

subjectLayoutCheck(
    str_contains($groupHelper, 'suggestSubjectGroup')
        && str_contains($groupHelper, "'Core'")
        && str_contains($groupHelper, "'Science'")
        && str_contains($groupHelper, 'civiceducation')
        && str_contains($groupHelper, 'basicscience'),
    'Subject group helper must classify shared subjects and the requested science subjects.'
);
subjectLayoutCheck(
    str_contains($migration, 'ADD COLUMN IF NOT EXISTS subject_group')
        && str_contains($migration, 'UPDATE subjects')
        && str_contains($migration, 'LPAD(id, 4')
        && str_contains($migration, 'subject_group'),
    'Migration must add groups and backfill every missing unique subject code.'
);

require_once $root . '/app/helpers/subject-categories.php';
$codes = [];
$first = generateUniqueSubjectCode('Food and Nutrition', $codes);
$codes[] = $first;
$second = generateUniqueSubjectCode('Food and Nutrition', $codes);
subjectLayoutCheck($first !== '' && $second !== '' && $first !== $second, 'Generated subject codes must be non-empty and unique.');
subjectLayoutCheck(suggestSubjectGroup('Quran') === 'Core', 'Quran must be classified as Core.');
subjectLayoutCheck(suggestSubjectGroup('Mathematics') === 'Core', 'Mathematics must be classified as Core.');
subjectLayoutCheck(suggestSubjectGroup('Basic Science') === 'Science', 'Basic Science must be classified as Science.');
subjectLayoutCheck(suggestSubjectGroup('Civic Education') === 'Science', 'Civic Education must follow the requested Science grouping.');
subjectLayoutCheck(suggestSubjectGroup('Economics') === 'Science', 'Economics must follow the requested Science grouping.');
subjectLayoutCheck(suggestSubjectGroup('Agric') === 'Science', 'Agric must follow the requested Science grouping.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, array_map(static fn($m) => 'FAIL: ' . $m, $failures)) . PHP_EOL);
    exit(1);
}
echo "Sticky header, subject groups/codes, dialog and attendance layout checks passed." . PHP_EOL;
