<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/dashboard/_progress.php';

function dashboardCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$rows = [
    ['expected_subjects' => 2, 'completed_subjects' => 2, 'started_subjects' => 2],
    ['expected_subjects' => 2, 'completed_subjects' => 1, 'started_subjects' => 2],
    ['expected_subjects' => 2, 'completed_subjects' => 0, 'started_subjects' => 0],
    ['expected_subjects' => 0, 'completed_subjects' => 0, 'started_subjects' => 0],
];
$summary = dashboardSummariseStudentProgress($rows);

dashboardCheck($summary['students'] === 4, 'counts every active student in class roster');
dashboardCheck($summary['complete_students'] === 1, 'only students with every required subject fully scored are complete');
dashboardCheck($summary['incomplete_students'] === 1, 'partially entered students are in progress');
dashboardCheck($summary['no_result_students'] === 2, 'students with no completed or started scores are not marked complete');
dashboardCheck($summary['expected_entries'] === 6, 'expected entries use required subject assignments only');
dashboardCheck($summary['completed_entries'] === 3, 'completed entries require CA1, CA2 and Exam');
dashboardCheck($summary['started_entries'] === 4, 'partially entered score rows count as started, not complete');
dashboardCheck($summary['completion_percentage'] === 50.0, 'completion percentage is completed subject entries divided by expected entries');

echo "Dashboard progress tests passed (8 checks).\n";
