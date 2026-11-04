<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/dashboard/_progress.php';

function dashboardEligibilityCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$summary = dashboardSummariseStudentProgress([
    ['expected_subjects' => 0, 'completed_subjects' => 0, 'started_subjects' => 0],
    ['expected_subjects' => 3, 'completed_subjects' => 0, 'started_subjects' => 0],
    ['expected_subjects' => 3, 'completed_subjects' => 1, 'started_subjects' => 1],
    ['expected_subjects' => 2, 'completed_subjects' => 2, 'started_subjects' => 2],
]);

dashboardEligibilityCheck($summary['students'] === 4, 'preserves the full active class roster count');
dashboardEligibilityCheck($summary['no_result_students'] === 1, 'counts eligible students with no entered scores as Not Started');
dashboardEligibilityCheck($summary['incomplete_students'] === 1, 'counts partially entered students as In Progress');
dashboardEligibilityCheck($summary['complete_students'] === 1, 'counts students with every required subject score complete');
dashboardEligibilityCheck($summary['expected_entries'] === 8, 'counts all required student-subject entries');
dashboardEligibilityCheck($summary['completed_entries'] === 3, 'counts only subject entries with CA1, CA2 and Exam');
dashboardEligibilityCheck($summary['completion_percentage'] === 37.5, 'calculates fully-scored entry percentage against required entries');

echo "Dashboard progress eligibility tests passed (7 checks).\n";
