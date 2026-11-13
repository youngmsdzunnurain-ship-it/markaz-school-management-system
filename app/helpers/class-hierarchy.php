<?php
declare(strict_types=1);

function markazNormaliseClassName(string $name): string
{
    $name = strtolower(trim($name));
    $name = str_replace(["’", "'", ".", "_", "-"], "", $name);
    $name = preg_replace('/\\s+/', ' ', $name) ?? $name;
    return trim($name);
}

function markazClassHierarchyRank(array $class): int
{
    static $orders = [
        'conventional' => [
            'kindergarten', 'kg',
            'preschool 1', 'preschool1', 'pre nursery 1', 'prinosgy 1', 'prenursery 1',
            'preschool 2', 'preschool2', 'pre nursery 2', 'prinosgy 2', 'prenursery 2',
            'nursery 1', 'nursery1', 'nursery 2', 'nursery2',
            'primary 1', 'primary1', 'primary 2', 'primary2', 'primary 3', 'primary3',
            'primary 4', 'primary4', 'primary 5', 'primary5', 'primary 6', 'primary6',
            'basic 1', 'basic1', 'basic 2', 'basic2', 'basic 3', 'basic3',
            'basic 4', 'basic4', 'basic 5', 'basic5',
            'jss 1', 'jss1', 'js 1', 'js1', 'junior secondary 1',
            'jss 2', 'jss2', 'js 2', 'js2', 'junior secondary 2',
            'jss 3', 'jss3', 'js 3', 'js3', 'junior secondary 3',
            'ss 1', 'ss1', 'sss 1', 'sss1', 'senior secondary 1',
            'ss 2', 'ss2', 'sss 2', 'sss2', 'senior secondary 2',
            'ss 3', 'ss3', 'sss 3', 'sss3', 'senior secondary 3',
        ],
        'arabic' => [
            'farabia', 'farabiyyah', 'farabiyya',
            'roudoh ula', 'roudah ula', 'raudah ula', 'raudho ula', 'roudoh awwal', 'roudah awwal',
            'roudoh sani', 'roudah sani', 'raudah sani',
            'faslu awal', 'faslu awwal', 'faslu ula',
            'faslu sani', 'faslu thalith', 'faslu thali', 'faslu salis',
            'faslu robi', 'faslu rabi', 'faslu rabia',
            'faslu sali', 'faslu khamis', 'faslu sadis',
        ],
    ];
    $type = (string) ($class['class_type'] ?? 'conventional');
    $order = $orders[$type] ?? $orders['conventional'];
    $name = markazNormaliseClassName((string) ($class['name'] ?? ''));
    $position = array_search($name, $order, true);
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
