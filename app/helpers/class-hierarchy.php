<?php
declare(strict_types=1);

function markazNormaliseClassName(string $name): string
{
    $name = strtolower(trim($name));
    $name = str_replace(["’", "'"], "", $name);
    $name = preg_replace('/[._-]+/', ' ', $name) ?? $name;
    return preg_replace('/\\s+/', ' ', $name) ?? $name;
}

function markazClassHierarchyRank(array $class): int
{
    static $orders = [
        'conventional' => [
            'kg', 'nursery 1', 'nursery 2', 'primary 1', 'primary 2',
            'basic 1', 'basic 2', 'basic 3', 'basic 4', 'basic 5',
            'jss 1', 'jss 2', 'jss 3', 'ss 1', 'ss 2', 'ss 3',
        ],
        'arabic' => [
            'roudoh awwal', 'roudoh sani', 'faslu ula', 'faslu sani',
            'faslu salis', 'faslu rabi', 'faslu khamis', 'faslu sadis',
        ],
    ];
    $type = (string) ($class['class_type'] ?? 'conventional');
    $order = $orders[$type] ?? $orders['conventional'];
    $position = array_search(markazNormaliseClassName((string) ($class['name'] ?? '')), $order, true);
    return $position === false ? 1000 + (int) ($class['sort_order'] ?? 0) : (int) $position;
}

/** Remove duplicate rows by category and database ID, not by display name. */
function markazSortAndDeduplicateClasses(array $classes): array
{
    $unique = [];
    foreach ($classes as $class) {
        $type = (string) ($class['class_type'] ?? 'conventional');
        $id = (string) ($class['id'] ?? '');
        $key = $type . ':' . $id;
        if ($id !== '' && isset($unique[$key])) {
            continue;
        }
        if ($id !== '') {
            $unique[$key] = $class;
        } else {
            $unique[] = $class;
        }
    }
    $classes = array_values($unique);
    usort($classes, static function (array $a, array $b): int {
        $typeOrder = ['conventional' => 0, 'arabic' => 1];
        return (($typeOrder[$a['class_type'] ?? 'conventional'] ?? 9) <=> ($typeOrder[$b['class_type'] ?? 'conventional'] ?? 9))
            ?: (markazClassHierarchyRank($a) <=> markazClassHierarchyRank($b))
            ?: strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
            ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
    });
    return $classes;
}
