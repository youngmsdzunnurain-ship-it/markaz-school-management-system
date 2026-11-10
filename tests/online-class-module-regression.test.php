<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$checks = [
    'isolated online-class tables and four default levels' => is_file($root . '/database/migrations/20261110_markaz_online_class.sql')
        && str_contains(file_get_contents($root . '/database/migrations/20261110_markaz_online_class.sql'), "('Level 4', 4)"),
    'online-class score contract is 20 + 20 + 60' => is_file($root . '/api/online-class/_common.php')
        && str_contains(file_get_contents($root . '/api/online-class/_common.php'), "'attendance' => 20")
        && str_contains(file_get_contents($root . '/api/online-class/_common.php'), "'test' => 20")
        && str_contains(file_get_contents($root . '/api/online-class/_common.php'), "'exam' => 60"),
    'online student login is isolated from conventional student accounts' => is_file($root . '/api/online-class/portal-login.php')
        && str_contains(file_get_contents($root . '/api/online-class/portal-login.php'), 'online_class_students')
        && str_contains(file_get_contents($root . '/api/online-class/portal-login.php'), 'online_student_id'),
    'online-class staff permission is enforced server-side' => is_file($root . '/app/middleware/auth.php')
        && str_contains(file_get_contents($root . '/app/middleware/auth.php'), 'function requireOnlineClassAccess'),
    'landscape result sheet and direct print flow exist' => is_file($root . '/public/online-class-results.html')
        && str_contains(file_get_contents($root . '/public/online-class-results.html'), 'A4 landscape'),
];
$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? "PASS " : "FAIL ") . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}
if ($failed) exit(1);
echo "Online Class regression checks passed (" . count($checks) . ")." . PHP_EOL;
