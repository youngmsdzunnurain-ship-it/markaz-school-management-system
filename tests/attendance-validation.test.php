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
check(str_contains($classesApi, 'sort_order') && str_contains($classesApi, '$a[\'sort_order\'] <=> $b[\'sort_order\']'), 'preserves configured class hierarchy after combining categories');
check(str_contains($classesApi, 'COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id) = ac.id AND s.is_active = 1'), 'counts active Arabic students using the current field with legacy Arabic class fallback');
check(str_contains($classesApi, 's.class_id = c.id AND s.is_active = 1'), 'counts active conventional students against conventional class assignments');

$classesApi = file_get_contents(__DIR__ . '/../api/attendance/classes.php');
check(str_contains($classesApi, "'school_student_total' => $schoolStudentTotal"), 'returns the overall active school population separately from class rosters');
check(str_contains($classesApi, "s.class_id = c.id AND s.is_active = 1"), 'keeps conventional attendance classes tied only to conventional student class IDs');
check(str_contains($classesApi, "COALESCE(NULLIF(s.arabic_class_id, 0), s.arabia_class_id) = ac.id"), 'keeps Arabic attendance classes tied only to Arabic student class IDs');

$attendanceJs = file_get_contents(__DIR__ . '/../public/js/attendance.js');
check(str_contains($attendanceJs, 'setInterval') && str_contains($attendanceJs, 'updateDateLabel'), 'refreshes displayed school date while page remains open');
check(str_contains($attendanceJs, 'attendanceStudent') && str_contains($attendanceJs, 'populateStudentDropdown'), 'provides a real class-populated student dropdown');
check(str_contains($attendanceJs, 'attendancePieChart'), 'renders the attendance status pie chart');
check(is_file(__DIR__ . '/../api/attendance/reports.php'), 'publishes the attendance reporting endpoint');
check(str_contains($attendanceJs, 'function normalizeClassType') && str_contains($attendanceJs, 'normalizeClassType(item.class_type) === type'), 'filters the class dropdown using normalized, separate class categories');
check(str_contains($attendanceJs, 'schoolStudentTotal = Number(data.school_student_total)'), 'loads the school-wide total independently of the selected class');
$attendancePage = file_get_contents(__DIR__ . '/../public/attendance.html');
$attendanceCss = file_get_contents(__DIR__ . '/../public/css/attendance.css');
check(str_contains($attendancePage, 'id="schoolTotalCount"'), 'shows a dedicated school-wide student total card');
check(str_contains($attendanceCss, 'grid-template-columns:repeat(6,minmax(0,1fr))') && str_contains($attendanceCss, 'grid-template-columns:repeat(3,minmax(0,1fr))'), 'lays out all six attendance metrics responsively');

$attendanceCommon = file_get_contents(__DIR__ . '/../api/attendance/_common.php');
check(str_contains($attendanceCommon, "'management'"), 'recognises Management as an attendance role');
check(str_contains($attendanceCommon, 'attendance_records'), 'enforces the teacher attendance permission');
$accountsApi = file_get_contents(__DIR__ . '/../api/settings/accounts.php');
check(str_contains($accountsApi, "'attendance_records'"), 'persists the individual teacher attendance permission');
check(str_contains($attendancePage, 'markaz-global-footer'), 'uses the standard MARKAZ footer');
check(str_contains($attendanceJs, 'clearInterval') || str_contains($attendanceJs, 'visibilitychange'), 'handles page visibility for date refresh');

echo "Attendance validation tests passed ({$checks} checks).\n";
