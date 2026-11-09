<?php
declare(strict_types=1);

/** Remove duplicate rows by category and database ID, preserving saved class order. */
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
        $aType = (string) ($a['class_type'] ?? 'conventional');
        $bType = (string) ($b['class_type'] ?? 'conventional');

        return (($typeOrder[$aType] ?? 9) <=> ($typeOrder[$bType] ?? 9))
            ?: ((int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0))
            ?: strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
            ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
    });

    return $classes;
}
