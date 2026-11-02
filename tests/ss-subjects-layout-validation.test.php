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
$css = file_get_contents(__DIR__ . '/../public/subjects/subjects.css');

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
    str_contains($page, 'subjects.css?v=20261102-fullwidth1')
        && str_contains($css, '.subjects-page > .page-content')
        && str_contains($css, 'width: 100%;')
        && str_contains($css, 'max-width: none;'),
    'Subject Management page uses a cache-busted full-width layout'
);

echo "SS subjects and Subject Management layout checks passed.\n";
