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

$behaviourFields = [
    'attendance', 'neatness', 'contentment', 'obedience', 'responsiveness',
    'calmness', 'tolerance', 'attentiveness', 'truthfulness',
];
$completeBehaviour = array_fill_keys($behaviourFields, 4);
$emptyBehaviour = array_fill_keys($behaviourFields, null);

$rows = [
    // Student 1 has all subject scores in the conventional and Arabic contexts.
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 1, 'subject_id' => 10, 'ca1' => 0, 'ca2' => 12, 'exam' => 55],
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 1, 'subject_id' => 11, 'ca1' => 10, 'ca2' => 10, 'exam' => 50],
    ['class_type' => 'arabic', 'class_id' => 7, 'student_id' => 1, 'subject_id' => 20, 'ca1' => 8, 'ca2' => 9, 'exam' => 40],
    // Student 2 has started, but CA2 and Exam are still missing.
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 2, 'subject_id' => 10, 'ca1' => 15, 'ca2' => null, 'exam' => null],
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 2, 'subject_id' => 11, 'ca1' => null, 'ca2' => null, 'exam' => null],
    // Student 3 has required subjects but no scores or behaviour assessments.
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 3, 'subject_id' => 10, 'ca1' => null, 'ca2' => null, 'exam' => null],
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 3, 'subject_id' => 11, 'ca1' => null, 'ca2' => null, 'exam' => null],
    // Student 4 has no required subject assignments.
    ['class_type' => 'conventional', 'class_id' => 1, 'student_id' => 4, 'subject_id' => null, 'ca1' => null, 'ca2' => null, 'exam' => null],
];

$behaviourRows = [
    ['student_id' => 1] + $completeBehaviour,
    ['student_id' => 2, 'attendance' => 4] + array_fill_keys(array_slice($behaviourFields, 1), null),
    ['student_id' => 3] + $emptyBehaviour,
];

$stats = summarizeResultProgress($rows, $behaviourRows);

expectSame(1, $stats['complete_students'], 'one student with all subject scores across both class contexts and all behaviour fields is complete');
expectSame(1, $stats['incomplete_students'], 'student with partial scores/behaviour is in progress');
expectSame(1, $stats['no_result_students'], 'student with no scores or behaviour is not started');
expectSame(1, $stats['unassigned_students'], 'student without required subjects is reported separately');
expectSame(3, $stats['completed_results'], 'subject-entry completion count includes both class categories');
expectSame(7, $stats['expected_results'], 'expected subject entries include both class categories');
expectSame(42.857143, $stats['completion_percentage'], 'overall subject-entry percentage remains based on subject score entries');

echo "Dashboard result-progress regression checks passed." . PHP_EOL;
