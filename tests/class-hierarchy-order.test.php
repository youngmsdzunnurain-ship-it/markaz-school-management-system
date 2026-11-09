<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/class-hierarchy.php';

$classes = [
    ['id' => 1, 'name' => 'SS 1', 'class_type' => 'conventional', 'sort_order' => 1],
    ['id' => 2, 'name' => 'Basic 2', 'class_type' => 'conventional', 'sort_order' => 2],
    ['id' => 3, 'name' => 'Nursery 1', 'class_type' => 'conventional', 'sort_order' => 30],
    ['id' => 4, 'name' => 'Primary 1', 'class_type' => 'conventional', 'sort_order' => 40],
    ['id' => 5, 'name' => 'Faslu Salis', 'class_type' => 'arabic', 'sort_order' => 1],
    ['id' => 6, 'name' => 'Roudoh Awwal', 'class_type' => 'arabic', 'sort_order' => 3],
    ['id' => 7, 'name' => 'Faslu Ula', 'class_type' => 'arabic', 'sort_order' => 2],
    ['id' => 3, 'name' => 'Nursery 1 duplicate', 'class_type' => 'conventional', 'sort_order' => 30],
];

$sorted = markazSortAndDeduplicateClasses($classes);
$actual = array_map(
    static fn (array $class): string => $class['class_type'] . ':' . $class['name'],
    $sorted
);
$expected = [
    'conventional:Nursery 1',
    'conventional:Primary 1',
    'conventional:Basic 2',
    'conventional:SS 1',
    'arabic:Roudoh Awwal',
    'arabic:Faslu Ula',
    'arabic:Faslu Salis',
];

if ($actual !== $expected) {
    fwrite(STDERR, 'FAIL: class hierarchy/category isolation/deduplication: ' . json_encode($actual) . PHP_EOL);
    exit(1);
}

echo "Class hierarchy ordering regression tests passed.\n";
