<?php
declare(strict_types=1);

function ssSubjectsCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$migration = file_get_contents(__DIR__ . '/../database/migrations/20261102_ss_subjects.sql');
$page = file_get_contents(__DIR__ . '/../public/subjects/index.html');
$studentApi = file_get_contents(__DIR__ . '/../api/results/student.php');
$css = file_get_contents(__DIR__ . '/../public/subjects/subjects.css');
$curriculumHelper = file_get_contents(__DIR__ . '/../app/helpers/ss-curriculum.php');
$classResultsApi = file_get_contents(__DIR__ . '/../api/results/class.php');
$classSubjectsListApi = file_get_contents(__DIR__ . '/../api/class-subjects/list.php');
$availableSubjectsApi = file_get_contents(__DIR__ . '/../api/class-subjects/available.php');

$expected = [
    'Quran', 'English', 'Mathematics', 'Biology', 'Chemistry',
    'Physics', 'Civic Education', 'Agric', 'Computer',
    'Food and Nutrition', 'Economics'
];

foreach ($expected as $subject) {
    ssSubjectsCheck(
        str_contains($migration, "SELECT '{$subject}'")
            || str_contains($migration, "AS name") && str_contains($migration, "'{$subject}'"),
        "SS curriculum includes {$subject}"
    );
}

ssSubjectsCheck(
    str_contains($migration, 'DELETE cs')
        && str_contains($migration, 'class_subjects')
        && str_contains($migration, "'SSS1', 'SSS2', 'SSS3'"),
    'SS class mappings are replaced for SS1, SS2 and SS3 only'
);
ssSubjectsCheck(
    str_contains($migration, "WHEN 'quran' THEN 1")
        && str_contains($migration, "WHEN 'economics' THEN 11"),
    'Quran is first and Economics is eleventh in SS result order'
);
ssSubjectsCheck(
    str_contains($studentApi, 'current_subject.subject_id = r.subject_id')
        && str_contains($studentApi, 'SELECT cs.subject_id')
        && str_contains($studentApi, 'WHERE student_id = ?'),
    'student and class averages include only subjects currently assigned to the class'
);
ssSubjectsCheck(
    str_contains($curriculumHelper, "'Quran'")
        && str_contains($curriculumHelper, "'Economics'")
        && str_contains($curriculumHelper, 'DELETE FROM class_subjects')
        && str_contains($classResultsApi, 'ensureSeniorSecondaryCurriculum')
        && str_contains($studentApi, 'ensureSeniorSecondaryCurriculum')
        && str_contains($classSubjectsListApi, 'ensureSeniorSecondaryCurriculum'),
    'SS curriculum is synchronized by class results, student results and subject management APIs'
);
ssSubjectsCheck(
    str_contains($availableSubjectsApi, "'animal husbandry'")
        && str_contains($classResultsApi, 'mapped_subject.subject_id')
        && str_contains($studentApi, 'active_subject.is_active = 1'),
    'Animal Husbandry is unavailable in SS choices and removed/inactive subjects do not affect current results'
);
ssSubjectsCheck(
    str_contains($css, 'grid-template-columns: minmax(0, 1fr) minmax(220px, 280px)')
        && str_contains($css, 'table-layout: fixed')
        && str_contains($css, 'flex-flow: row nowrap'),
    'Subject Management filters and table actions align consistently'
);
ssSubjectsCheck(
    str_contains($page, 'subjects.css?v=20261102-aligned2')
        && str_contains($css, '.subjects-page > .page-content')
        && str_contains($css, 'width: 100%;')
        && str_contains($css, 'max-width: none;'),
    'Subject Management page uses a cache-busted full-width layout'
);

echo "SS subjects and Subject Management layout checks passed.\n";
