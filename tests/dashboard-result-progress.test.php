<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/result-progress.php';

function expectSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}; expected " . var_export($expected, true)
            . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$rows = [
    // Conventional student 1: all required score fields are present (zero is a valid score).
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 1, 'subject_id' => 10, 'ca1' => 0, 'ca2' => 12, 'exam' => 55],
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 1, 'subject_id' => 11, 'ca1' => 10, 'ca2' => 10, 'exam' => 50],
    // Conventional student 2: started, but CA2 and Exam are still missing.
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 2, 'subject_id' => 10, 'ca1' => 15, 'ca2' => null, 'exam' => null],
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 2, 'subject_id' => 11, 'ca1' => null, 'ca2' => null, 'exam' => null],
    // Conventional student 3: required subjects exist but no scores have been saved.
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 3, 'subject_id' => 10, 'ca1' => null, 'ca2' => null, 'exam' => null],
    // Student 4 has no required subject assignments.
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 4, 'subject_id' => null, 'ca1' => null, 'ca2' => null, 'exam' => null],
    // Same student can have a separate Arabic result context; it must not be merged with conventional.
    ['class_type' => 'arabic', 'class_id' => 7, 'student_id' => 1, 'subject_id' => 20, 'ca1' => 8, 'ca2' => 9, 'exam' => 40],
];

$stats = summarizeResultProgress($rows);

expectSame(2, $stats['complete_students'], 'only the fully scored conventional student is complete');
expectSame(1, $stats['incomplete_students'], 'partially entered student is in progress');
expectSame(1, $stats['no_result_students'], 'student with no scores is not started');
expectSame(1, $stats['unassigned_students'], 'student without required subjects is reported separately');
expectSame(3, $stats['completed_results'], 'only subject entries with all three scores count as fully scored');
expectSame(6, $stats['expected_results'], 'expected subject entries include both class categories');
expectSame(50.0, $stats['completion_percentage'], 'overall percentage uses fully scored subject entries');

echo "Dashboard result-progress regression checks passed." . PHP_EOL;
