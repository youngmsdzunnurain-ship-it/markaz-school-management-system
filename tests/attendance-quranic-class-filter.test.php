<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../api/attendance/classes.php');
if ($source === false) {
    throw new RuntimeException('Unable to read attendance classes API.');
}

$conventionalQueries = preg_match_all(
    "/FROM teacher_assignments ta\\s+INNER JOIN classes c.*?WHERE ta\\.account_id = \\?.*?AND c\\.is_active = 1.*?(?=\\);)/s",
    $source
);
if ($conventionalQueries !== 1) {
    throw new RuntimeException('Expected to find the teacher conventional-class query.');
}

if (!str_contains($source, "c.level_group IS NULL OR c.level_group <> 'Quranic'")) {
    throw new RuntimeException('Conventional attendance classes must exclude Quranic level_group entries.');
}

if (!str_contains($source, "WHERE c.is_active = 1\n         AND (c.level_group IS NULL OR c.level_group <> 'Quranic')")) {
    throw new RuntimeException('Staff conventional attendance query must exclude Quranic level_group entries.');
}

if (!str_contains($source, 'FROM arabic_classes ac')) {
    throw new RuntimeException('Arabic attendance class query must remain present.');
}

echo "Attendance Quranic conventional-class filter tests passed.\n";
