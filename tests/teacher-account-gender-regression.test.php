<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$api = file_get_contents($root . '/api/settings/accounts.php');
$ui = file_get_contents($root . '/public/js/account-management.js');
$schema = file_get_contents($root . '/database/schema.sql');
$migration = file_get_contents($root . '/database/migrations/20261128_teacher_account_gender.sql');
$seed = file_get_contents($root . '/scripts/seed-demo-teachers.php');

$checks = [
    'account API returns and persists teacher gender' => str_contains($api, 'a.gender') && str_contains($api, '$gender') && str_contains($api, 'gender = ?'),
    'teacher account form exposes gender' => str_contains($ui, 'accountGender') && str_contains($ui, 'gender:'),
    'fresh schema includes nullable gender' => preg_match('/gender\s+ENUM\s*\(/i', $schema) === 1,
    'existing database migration adds gender' => preg_match('/ADD COLUMN\s+gender\s+ENUM\s*\(/i', $migration) === 1,
    'demo seeder is restricted to non-production databases' => str_contains($seed, 'markaz_result_system_finance_test') && str_contains($seed, 'confirm-demo'),
    'demo seeder creates twenty teachers' => preg_match('/count\(\$teachers\)\s*!==\s*20/', $seed) === 1,
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? "PASS" : "FAIL") . " - {$label}\n";
    if (!$passed) $failed[] = $label;
}
if ($failed) {
    fwrite(STDERR, count($failed) . " teacher gender/demo seed regression check(s) failed.\n");
    exit(1);
}
echo "Teacher gender and demo seed regression checks passed.\n";
