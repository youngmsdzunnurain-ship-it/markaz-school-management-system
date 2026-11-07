<?php
declare(strict_types=1);

/**
 * Summarize saved assessment entries using the same class-context completion
 * rule as api/results/class-completion.php. A student is counted once overall:
 * reaching 100% in any assigned conventional or Arabic class context marks
 * that student complete, even if another context is still in progress.
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

        $studentKey = (string) $studentId;
        if (!isset($students[$studentKey])) {
            $students[$studentKey] = [
                'contexts' => [],
                'behaviour_started' => false,
                'behaviour_complete' => false,
            ];
        }

        $contextKey = $type . ':' . $classId;
        if (!isset($students[$studentKey]['contexts'][$contextKey])) {
            $students[$studentKey]['contexts'][$contextKey] = [
                'required' => 0,
                'started' => 0,
                'completed' => 0,
            ];
        }

        if (empty($row['subject_id'])) {
            continue;
        }

        $students[$studentKey]['contexts'][$contextKey]['required']++;
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
            $students[$studentKey]['contexts'][$contextKey]['started']++;
        }
        if ($hasAll) {
            $students[$studentKey]['contexts'][$contextKey]['completed']++;
            $completedEntries++;
        }
    }

    foreach ($behaviourRows as $row) {
        $studentId = (int) ($row['student_id'] ?? 0);
        if ($studentId < 1) {
            continue;
        }

        $studentKey = (string) $studentId;
        if (!isset($students[$studentKey])) {
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
        $students[$studentKey]['behaviour_started'] = $hasAny;
        $students[$studentKey]['behaviour_complete'] = $hasAll;
    }

    $complete = 0;
    $inProgress = 0;
    $notStarted = 0;
    $unassigned = 0;

    foreach ($students as $student) {
        $hasAssignedSubjects = false;
        $hasCompletedContext = false;
        $hasAnyProgress = $student['behaviour_started'];

        foreach ($student['contexts'] as $context) {
            if ($context['required'] === 0) {
                continue;
            }

            $hasAssignedSubjects = true;
            $contextComplete = $context['completed'] === $context['required']
                && $student['behaviour_complete'];
            if ($contextComplete) {
                $hasCompletedContext = true;
            }
            if ($context['started'] > 0) {
                $hasAnyProgress = true;
            }
        }

        if (!$hasAssignedSubjects) {
            $unassigned++;
        } elseif ($hasCompletedContext) {
            // Match the class completion monitor: a 100% class context
            // completes the student, and cross-context duplicates count once.
            $complete++;
        } elseif ($hasAnyProgress) {
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
