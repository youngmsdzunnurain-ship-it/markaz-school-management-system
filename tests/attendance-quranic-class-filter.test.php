<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../api/attendance/classes.php');
if ($source === false) {
    throw new RuntimeException('Unable to read attendance classes API.');
}

$filter = "(c.level_group IS NULL OR c.level_group <> 'Quranic')";
if (substr_count($source, $filter) !== 2) {
    throw new RuntimeException('Both conventional attendance queries must exclude Quranic classes.');
}

if (!str_contains($source, "ta.class_type = 'conventional'")) {
    throw new RuntimeException('Teacher conventional class scoping must remain intact.');
}

if (!str_contains($source, "FROM arabic_classes ac")) {
    throw new RuntimeException('Arabic attendance class query must remain present.');
}

$arabicQueryStart = strpos($source, "FROM arabic_classes ac");
if ($arabicQueryStart === false || str_contains(substr($source, $arabicQueryStart), $filter)) {
    throw new RuntimeException('Quranic filtering must not be applied to Arabic attendance classes.');
}

echo "Attendance Quranic conventional-class filter tests passed.\n";
