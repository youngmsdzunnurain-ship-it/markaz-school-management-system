<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/attendance/_common.php';

$checks = 0;

function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

check(attendanceIsValidDate('2024-02-29'), 'accepts a valid leap day');
check(!attendanceIsValidDate('2025-02-29'), 'rejects an impossible leap day');
check(!attendanceIsValidDate('2026-13-01'), 'rejects an invalid month');
check(!attendanceIsValidDate('2026-04-31'), 'rejects an invalid day');
check(!attendanceIsValidDate('28-10-2026'), 'requires ISO date format');
check(attendanceIsValidStatus('present'), 'accepts present status');
check(attendanceIsValidStatus('absent'), 'accepts absent status');
check(attendanceIsValidStatus('late'), 'accepts late status');
check(attendanceIsValidStatus('excused'), 'accepts excused status');
check(!attendanceIsValidStatus('holiday'), 'rejects unknown attendance status');
check(!attendanceIsValidStatus(''), 'rejects an empty attendance status');
check(attendanceClassTable('conventional') === 'classes', 'maps conventional class type to the expected table');
check(attendanceClassTable('arabic') === 'arabic_classes', 'maps Arabic class type to the expected table');
check(attendanceClassTable('classes; DROP TABLE students') === null, 'rejects untrusted table names');

echo "Attendance validation tests passed ({$checks} checks).\n";
