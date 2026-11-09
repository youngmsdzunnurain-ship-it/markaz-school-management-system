<?php
declare(strict_types=1);

function markazNormaliseClassName(string $name): string
{
    $name = strtolower(trim($name));
    $name = str_replace(["’", "'"], "", $name);
    $name = preg_replace('/[._-]+/', ' ', $name) ?? $name;
    return preg_replace('/\\s+/', ' ', $name) ?? $name;
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
    // The Classes management page lets administrators set sort_order explicitly.
    // Keep that saved order authoritative everywhere; never impose a second,
    // hard-coded academic hierarchy on top of it.
    usort($classes, static function (array $a, array $b): int {
        $typeOrder = ['conventional' => 0, 'arabic' => 1];
        return (($typeOrder[$a['class_type'] ?? 'conventional'] ?? 9) <=> ($typeOrder[$b['class_type'] ?? 'conventional'] ?? 9))
            ?: ((int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0))
            ?: strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
            ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
    });
    return $classes;
}
