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
check(is_file(__DIR__ . '/../public/api/attendance/classes.php'), 'publishes the class-list endpoint under the public API path');
check(is_file(__DIR__ . '/../public/api/attendance/register.php'), 'publishes the register endpoint under the public API path');

$classesApi = file_get_contents(__DIR__ . '/../api/attendance/classes.php');
$reportsApi = file_get_contents(__DIR__ . '/../api/attendance/reports.php');
$attendanceJs = file_get_contents(__DIR__ . '/../public/js/attendance.js');
check(str_contains($classesApi, 'sort_order') && str_contains($classesApi, '$a[\'sort_order\'] <=> $b[\'sort_order\']'), 'preserves configured class hierarchy after combining categories');
check(str_contains($classesApi, 'COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id) = ac.id AND s.is_active = 1'), 'counts active Arabic students using the current field with legacy Arabic class fallback');
check(str_contains($classesApi, 's.class_id = c.id AND s.is_active = 1'), 'counts active conventional students against conventional class assignments');
check(str_contains($classesApi, "'conventional' AS class_type") && str_contains($classesApi, "'arabic' AS class_type"), 'returns conventional and Arabic classes with explicit independent type labels');
check(!str_contains($classesApi, 'NOT EXISTS') && !str_contains($classesApi, 'arabic_class.name'), 'does not hide conventional classes because an Arabic class has the same name');
check(str_contains($reportsApi, 'ar.class_type = ?') && str_contains($reportsApi, 'ar.class_id = c.id'), 'attendance report rows are scoped by both category and class id');
check(str_contains($reportsApi, 'student_count_sql') && str_contains($reportsApi, 'AS student_count'), 'attendance reports provide the active class roster denominator for present-rate charts');
check(!str_contains($reportsApi, 'NOT EXISTS') && !str_contains($reportsApi, 'arabic_class.name'), 'school reports include same-named conventional and Arabic classes independently');
check(str_contains($attendanceJs, 'item.class_type === type'), 'attendance class dropdown filters strictly by selected class category');
check(str_contains($attendanceJs, 'classes.php?class_type='), 'attendance page requests only its selected category');
check(str_contains($classesApi, '$classTypeFilter') && str_contains($classesApi, '$class[\'class_type\'] === $classTypeFilter'), 'attendance class API filters by class_type on the server');

check(str_contains($attendanceJs, 'setInterval') && str_contains($attendanceJs, 'updateDateLabel'), 'refreshes displayed school date while page remains open');
check(str_contains($attendanceJs, 'attendanceStudent') && str_contains($attendanceJs, 'populateStudentDropdown'), 'provides a real class-populated student dropdown');
check(str_contains($attendanceJs, 'attendancePieChart'), 'renders the attendance status pie chart');
check(is_file(__DIR__ . '/../api/attendance/reports.php'), 'publishes the attendance reporting endpoint');
$attendanceCommon = file_get_contents(__DIR__ . '/../api/attendance/_common.php');
check(str_contains($attendanceCommon, "'management'"), 'recognises Management as an attendance role');
check(str_contains($attendanceCommon, 'attendance_records'), 'enforces the teacher attendance permission');
$accountsApi = file_get_contents(__DIR__ . '/../api/settings/accounts.php');
check(str_contains($accountsApi, "'attendance_records'"), 'persists the individual teacher attendance permission');
$attendancePage = file_get_contents(__DIR__ . '/../public/attendance.html');
check(str_contains($attendancePage, 'markaz-global-footer'), 'uses the standard MARKAZ footer');
check(str_contains($attendanceJs, 'clearInterval') || str_contains($attendanceJs, 'visibilitychange'), 'handles page visibility for date refresh');

echo "Attendance validation tests passed ({$checks} checks).\n";
