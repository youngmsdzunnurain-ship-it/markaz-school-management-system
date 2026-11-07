<?php
declare(strict_types=1);

/**
 * Summarize saved assessment entries for the active student/class contexts.
 * Each row represents one required subject entry. A score of zero is present;
 * NULL/empty means that component has not yet been entered.
 */
function summarizeResultProgress(array $rows): array
{
    $students = [];
    $expectedEntries = 0;
    $completedEntries = 0;

    foreach ($rows as $row) {
        $type = (string) ($row['class_type'] ?? 'conventional');
        $classId = (int) ($row['class_id'] ?? 0);
        $studentId = (int) ($row['student_id'] ?? 0);
        if (!in_array($type, ['conventional', 'arabic'], true) || $classId < 1 || $studentId < 1) {
            continue;
        }

        // A student enrolled in both systems is still one person for the student cards.
        // Keep each required subject entry in the denominator, but aggregate status by student ID.
        $key = (string) $studentId;
        if (!isset($students[$key])) {
            $students[$key] = ['required' => 0, 'started' => 0, 'completed' => 0];
        }

        if (empty($row['subject_id'])) {
            continue;
        }

        $students[$key]['required']++;
        $expectedEntries++;

        $hasAny = false;
        $hasAll = true;
        foreach (['ca1', 'ca2', 'exam'] as $field) {
            $value = $row[$field] ?? null;
            $present = $value !== null && $value !== '';
            $hasAny = $hasAny || $present;
            $hasAll = $hasAll && $present;
        }

        if ($hasAny) {
            $students[$key]['started']++;
        }
        if ($hasAll) {
            $students[$key]['completed']++;
            $completedEntries++;
        }
    }

    $complete = 0;
    $inProgress = 0;
    $notStarted = 0;
    $unassigned = 0;

    foreach ($students as $student) {
        if ($student['required'] === 0) {
            $unassigned++;
        } elseif ($student['completed'] === $student['required']) {
            $complete++;
        } elseif ($student['started'] > 0) {
            $inProgress++;
        } else {
            $notStarted++;
        }
    }

    return [
        'complete_students' => $complete,
        'incomplete_students' => $inProgress,
        'no_result_students' => $notStarted,
        'unassigned_students' => $unassigned,
        'completed_results' => $completedEntries,
        'expected_results' => $expectedEntries,
        'completion_percentage' => $expectedEntries > 0
            ? round(($completedEntries / $expectedEntries) * 100, 6)
            : 0.0,
    ];
}
