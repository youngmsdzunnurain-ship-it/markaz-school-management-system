<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/class-hierarchy.php';

$classes = [
    ['id' => 11, 'name' => 'Basic 1', 'class_type' => 'conventional', 'sort_order' => 30],
    ['id' => 12, 'name' => 'Nursery 1', 'class_type' => 'conventional', 'sort_order' => 10],
    ['id' => 21, 'name' => 'Faslu Ula', 'class_type' => 'arabic', 'sort_order' => 20],
    ['id' => 22, 'name' => 'Roudoh Awwal', 'class_type' => 'arabic', 'sort_order' => 5],
    ['id' => 11, 'name' => 'Basic 1 duplicate', 'class_type' => 'conventional', 'sort_order' => 30],
];

$sorted = markazSortAndDeduplicateClasses($classes);
$actual = array_map(
    static fn (array $class): string => $class['class_type'] . ':' . $class['id'],
    $sorted
);
$expected = [
    'conventional:12',
    'conventional:11',
    'arabic:22',
    'arabic:21',
];

if ($actual !== $expected) {
    fwrite(STDERR, 'FAIL: saved class order/category isolation/deduplication: ' . json_encode($actual) . PHP_EOL);
    exit(1);
}

echo "Class hierarchy ordering regression tests passed.\n";
