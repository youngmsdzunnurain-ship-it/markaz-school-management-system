<?php
declare(strict_types=1);

/**
 * Summarise one row per active student from a class/subject/result aggregate.
 * A result entry is complete only when CA1, CA2 and Exam are all present.
 */
function dashboardSummariseStudentProgress(array $rows): array
{
    $summary = [
        'students' => count($rows),
        'complete_students' => 0,
        'incomplete_students' => 0,
        'no_result_students' => 0,
        'unassigned_students' => 0,
        'expected_entries' => 0,
        'completed_entries' => 0,
        'started_entries' => 0,
        'completion_percentage' => 0.0,
    ];

    foreach ($rows as $row) {
        $expected = max(0, (int) ($row['expected_subjects'] ?? 0));
        if ($expected === 0) {
            $summary['unassigned_students']++;
            continue;
        }
        $completed = max(0, min($expected, (int) ($row['completed_subjects'] ?? 0)));
        $started = max($completed, min($expected, (int) ($row['started_subjects'] ?? 0)));

        $summary['expected_entries'] += $expected;
        $summary['completed_entries'] += $completed;
        $summary['started_entries'] += $started;

        if ($expected > 0 && $completed === $expected) {
            $summary['complete_students']++;
        } elseif ($started > 0) {
            $summary['incomplete_students']++;
        } else {
            $summary['no_result_students']++;
        }
    }

    if ($summary['expected_entries'] > 0) {
        $summary['completion_percentage'] = round(
            $summary['completed_entries'] / $summary['expected_entries'] * 100,
            2
        );
    }

    return $summary;
}
