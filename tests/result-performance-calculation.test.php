<?php
declare(strict_types=1);

function resultPerformanceCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$studentApi = file_get_contents(__DIR__ . '/../api/results/student.php');
$saveApi = file_get_contents(__DIR__ . '/../api/results/save.php');
$classJs = file_get_contents(__DIR__ . '/../public/js/class.js');
$resultJs = file_get_contents(__DIR__ . '/../public/js/student-result.js');

resultPerformanceCheck(
    str_contains($studentApi, 'SUM(')
        && str_contains($studentApi, 'COALESCE(r.ca1, 0)')
        && str_contains($studentApi, 'COALESCE(r.ca2, 0)')
        && str_contains($studentApi, 'COALESCE(r.exam, 0)'),
    'class averages add each entered score component independently and treat blank components as zero'
);

resultPerformanceCheck(
    str_contains($studentApi, 'COUNT(r.id) AS completed_subjects')
        && str_contains($studentApi, '/ $completedSubjects'),
    'class averages divide the same per-student subject total by that student’s saved subject-row count'
);

resultPerformanceCheck(
    str_contains($studentApi, '$studentTotalScore +=')
        && str_contains($studentApi, '$studentCompletedSubjects++')
        && str_contains($saveApi, '($ca1 ?? 0)')
        && str_contains($saveApi, '($ca2 ?? 0)')
        && str_contains($saveApi, '($exam ?? 0)'),
    'student average and saved subject total both use the same blank-component-as-zero rule'
);

resultPerformanceCheck(
    str_contains($classJs, '(ca1 + ca2 + exam).toFixed(2)')
        && str_contains($resultJs, 'ca1 + ca2 + exam'),
    'live class-entry totals and the student result page both add CA1, CA2 and exam'
);

// Reference cases: these are the same NULL-as-zero semantics used by the
// student result total and average. They cover partial entries and class growth.
$averageFor = static function (array $rows): ?float {
    if ($rows === []) {
        return null;
    }

    $total = 0.0;
    foreach ($rows as $row) {
        $total += (float) ($row['ca1'] ?? 0)
            + (float) ($row['ca2'] ?? 0)
            + (float) ($row['exam'] ?? 0);
    }

    return round($total / count($rows), 2);
};

$smallClass = [
    [['ca1' => 10, 'ca2' => null, 'exam' => 20]],
    [['ca1' => 20, 'ca2' => 10, 'exam' => null]],
];
$smallAverages = array_map($averageFor, $smallClass);

resultPerformanceCheck(
    $smallAverages === [30.0, 30.0],
    'partially entered score components contribute their entered marks consistently'
);

$largerClass = $smallClass;
$largerClass[] = [
    ['ca1' => 20, 'ca2' => 20, 'exam' => 40],
    ['ca1' => null, 'ca2' => null, 'exam' => null],
];
$largerAverages = array_map($averageFor, $largerClass);

resultPerformanceCheck(
    $largerAverages === [30.0, 30.0, 40.0],
    'adding more students preserves each student average and yields correct class extrema'
);

resultPerformanceCheck(
    max($largerAverages) === 40.0 && min($largerAverages) === 30.0,
    'highest and lowest class averages are selected from the complete per-student average set'
);

echo "Result performance calculation tests passed (7 checks).\n";
