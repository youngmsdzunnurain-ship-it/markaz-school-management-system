<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/markaz-ai.php';

function markazAiFreeCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$adminScope = ['role' => 'admin', 'capabilities' => ['help', 'school_overview', 'attendance_analysis'], 'assignments' => [], 'permissions' => []];
$adminContext = [
    'current_academic_period' => ['session' => '2026/2027', 'term' => 'First Term', 'finalized' => false, 'results_released' => false],
    'workspace_summary' => ['active_students' => 120, 'active_conventional_classes' => 8, 'active_arabic_classes' => 4, 'result_rows_in_current_period' => 90],
    'assigned_workspaces' => []
];
$periodAnswer = markazAiBuildFreeAnswer('What is the current term?', $adminScope, $adminContext);
markazAiFreeCheck(str_contains($periodAnswer, '2026/2027') && str_contains($periodAnswer, 'First Term'), 'current period answer uses verified academic period context');

$overviewAnswer = markazAiBuildFreeAnswer('How many active students are there?', $adminScope, $adminContext);
markazAiFreeCheck(str_contains($overviewAnswer, '120'), 'Admin overview answers use live school summary');

$teacherScope = ['role' => 'teacher', 'capabilities' => ['help', 'assigned_class_result_guidance'], 'assignments' => [['class_type' => 'conventional', 'class_id' => 2]], 'permissions' => []];
$teacherContext = [
    'current_academic_period' => ['session' => '2026/2027', 'term' => 'First Term'],
    'workspace_summary' => [],
    'assigned_workspaces' => [[
        'class_type' => 'conventional', 'class_name' => 'JSS 2', 'subjects' => ['English', 'Mathematics'],
        'active_student_count' => 30, 'result_rows_current_period' => 45
    ]]
];
$assignmentAnswer = markazAiBuildFreeAnswer('Show my assigned classes', $teacherScope, $teacherContext);
markazAiFreeCheck(str_contains($assignmentAnswer, 'JSS 2') && str_contains($assignmentAnswer, 'Mathematics'), 'teacher answer lists only server-provided assigned workspaces');

$teacherCountAnswer = markazAiBuildFreeAnswer('How many students are in my class?', $teacherScope, $teacherContext);
markazAiFreeCheck(str_contains($teacherCountAnswer, 'JSS 2') && str_contains($teacherCountAnswer, '30'), 'teacher can see student counts only for assigned classes');

$managementScope = ['role' => 'management', 'capabilities' => ['help'], 'assignments' => [], 'permissions' => []];
$managementAnswer = markazAiBuildFreeAnswer('How many active students?', $managementScope, $adminContext);
markazAiFreeCheck(str_contains($managementAnswer, 'not available') || str_contains($managementAnswer, 'not permitted'), 'Management cannot receive Admin-only school overview');

$studentContext = [
    'student_name' => 'Test Student', 'class_name' => 'JSS 1',
    'academic_session' => '2026/2027', 'term' => 'First Term',
    'published_results' => [
        ['subject' => 'English', 'ca1' => 15, 'ca2' => 16, 'exam' => 50, 'total' => 81],
        ['subject' => 'Mathematics', 'ca1' => 10, 'ca2' => 12, 'exam' => 30, 'total' => 52]
    ]
];
$studentAnswer = markazAiBuildFreeAnswer('Explain my result', ['role' => 'student', 'capabilities' => [], 'assignments' => [], 'permissions' => []], $studentContext, true);
markazAiFreeCheck(str_contains($studentAnswer, 'English') && str_contains($studentAnswer, '81') && str_contains($studentAnswer, 'Mathematics') && str_contains($studentAnswer, '52'), 'student result explanation uses only the published personal result context');

$adminAttendance = markazAiBuildFreeAnswer('How many students were present today?', $adminScope, [
    'current_academic_period' => $adminContext['current_academic_period'],
    'workspace_summary' => ['today_attendance' => ['marked' => 105, 'present_count' => 90, 'absent_count' => 10, 'late_count' => 5]],
    'assigned_workspaces' => []
]);
markazAiFreeCheck(str_contains($adminAttendance, 'present 90') && str_contains($adminAttendance, 'absent 10'), 'Admin attendance answers use the recorded daily attendance summary');

$unknownAnswer = markazAiBuildFreeAnswer('Write a poem about the moon', $adminScope, $adminContext);
markazAiFreeCheck(str_contains($unknownAnswer, 'cannot answer that reliably'), 'unsupported questions receive an honest limitation instead of invented content');

echo "MARKAZ AI no-cost assistant tests passed.\n";
