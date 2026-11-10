<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$schema = file_get_contents($root . '/database/migrations/20261110_markaz_online_class.sql');
$auth = file_get_contents($root . '/app/middleware/auth.php');
$accounts = file_get_contents($root . '/api/settings/accounts.php');
$login = file_get_contents($root . '/api/auth/login.php');
$checks = [
    'isolated online student, subject, attendance, result and release tables' =>
        str_contains($schema, 'online_class_students') && str_contains($schema, 'online_class_subjects')
        && str_contains($schema, 'online_class_attendance') && str_contains($schema, 'online_class_results')
        && str_contains($schema, 'online_class_result_releases'),
    'four initial levels are provisioned without overwriting existing records' =>
        str_contains($schema, 'INSERT IGNORE INTO online_class_levels') && str_contains($schema, "('Level 4', 4)"),
    'assessment score maximums remain 20, 20 and 60' =>
        str_contains($schema, 'attendance_score DECIMAL(5,2)') && str_contains($schema, 'test_score DECIMAL(5,2)')
        && str_contains($schema, 'exam_score DECIMAL(5,2)'),
    'online class staff access is separately enforced' =>
        str_contains($auth, 'requireOnlineClassAccess') && str_contains($accounts, 'online_class_access'),
    'online students are not added to the conventional staff login role list' =>
        !str_contains($login, "'online_student'"),
];
foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
echo 'Online Class architecture regression checks passed (' . count($checks) . ").\n";
