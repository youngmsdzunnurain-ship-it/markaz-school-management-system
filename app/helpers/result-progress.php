<?php
declare(strict_types=1);

/**
 * Summarize saved assessment entries across the school's conventional and
 * Arabic class contexts. A student is counted once, even when enrolled in both.
 *
 * Completion status follows the same rules as class-completion.php:
 * every required subject must have CA1, CA2 and Exam, and all nine
 * behavioural assessment fields must be present. A numeric zero is present.
 */
function summarizeResultProgress(array $rows, array $behaviourRows = []): array
{
    $students = [];
    $expectedEntries = 0;
    $completedEntries = 0;
    $behaviourFields = [
        'attendance', 'neatness', 'contentment', 'obedience', 'responsiveness',
        'calmness', 'tolerance', 'attentiveness', 'truthfulness',
    ];

    foreach ($rows as $row) {
        $type = (string) ($row['class_type'] ?? 'conventional');
        $classId = (int) ($row['class_id'] ?? 0);
        $studentId = (int) ($row['student_id'] ?? 0);
        if (!in_array($type, ['conventional', 'arabic'], true) || $classId < 1 || $studentId < 1) {
            continue;
        }

        $key = (string) $studentId;
        if (!isset($students[$key])) {
            $students[$key] = [
                'required' => 0,
                'started' => 0,
                'completed' => 0,
                'behaviour_started' => false,
                'behaviour_complete' => false,
            ];
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

    foreach ($behaviourRows as $row) {
        $studentId = (int) ($row['student_id'] ?? 0);
        if ($studentId < 1) {
            continue;
        }

        $key = (string) $studentId;
        if (!isset($students[$key])) {
            // Behaviour alone must not create a student outside the active
            // conventional/Arabic class population supplied by the main query.
            continue;
        }

        $hasAny = false;
        $hasAll = true;
        foreach ($behaviourFields as $field) {
            $value = $row[$field] ?? null;
            $present = $value !== null && $value !== '';
            $hasAny = $hasAny || $present;
            $hasAll = $hasAll && $present;
        }
        $students[$key]['behaviour_started'] = $hasAny;
        $students[$key]['behaviour_complete'] = $hasAll;
    }

    $complete = 0;
    $inProgress = 0;
    $notStarted = 0;
    $unassigned = 0;

    foreach ($students as $student) {
        if ($student['required'] === 0) {
            $unassigned++;
            continue;
        }

        $subjectsComplete = $student['completed'] === $student['required'];
        if ($subjectsComplete && $student['behaviour_complete']) {
            $complete++;
        } elseif ($student['started'] > 0 || $student['behaviour_started']) {
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
