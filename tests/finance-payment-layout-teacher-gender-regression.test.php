<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'finance_js' => $root . '/public/js/finance.js',
    'finance_css' => $root . '/public/css/finance.css',
    'account_js' => $root . '/public/js/account-management.js',
    'accounts_api' => $root . '/api/settings/accounts.php',
    'gender_migration' => $root . '/database/migrations/20261128_teacher_account_gender.sql',
    'teacher_seed' => $root . '/database/seeds/create_demo_teachers.php',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$financeJs = file_get_contents($files['finance_js']);
$financeCss = file_get_contents($files['finance_css']);
$accountJs = file_get_contents($files['account_js']);
$accountsApi = file_get_contents($files['accounts_api']);
$migration = file_get_contents($files['gender_migration']);
$seed = file_get_contents($files['teacher_seed']);

$checks = [
    'recent payments use a structured full-width record' =>
        str_contains($financeJs, '<article class="payment-record">')
        && str_contains($financeJs, 'payment-record-meta')
        && str_contains($financeJs, 'payment-record-actions'),
    'recent payments list uses one column' =>
        preg_match('/#paymentHistory\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/s', $financeCss) === 1,
    'recent payments have a responsive stacked layout' =>
        str_contains($financeCss, '@media (max-width: 760px)')
        && str_contains($financeCss, '.payment-record-side'),
    'teacher account form captures gender' =>
        str_contains($accountJs, 'id="accountGender"')
        && str_contains($accountJs, "gender: role.value === 'teacher'"),
    'teacher account table displays gender' =>
        str_contains($accountJs, '<th>Gender</th>')
        && str_contains($accountJs, "account.gender.charAt(0).toUpperCase()"),
    'account API reads, validates and persists gender' =>
        str_contains($accountsApi, 'a.gender')
        && str_contains($accountsApi, 'in_array($gender, [\'male\', \'female\'], true)')
        && str_contains($accountsApi, 'gender = ?')
        && str_contains($accountsApi, 'role, gender, password_hash'),
    'gender migration adds a nullable enum for existing staff' =>
        str_contains($migration, "ENUM('male', 'female') NULL"),
    'demo teacher seeder is restricted to CLI and a demo database' =>
        str_contains($seed, "PHP_SAPI !== 'cli'")
        && str_contains($seed, 'str_contains(strtolower($databaseName), \'demo\')'),
    'demo teacher seeder contains exactly twenty sample teachers' =>
        preg_match('/\$teachers\s*=\s*\[(.*?)\n\];/s', $seed, $matches) === 1
        && preg_match_all('/^\s*\[\'/m', $matches[1]) === 20,
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " {$label}\n";
    if (!$passed) {
        $failed[] = $label;
    }
}

if ($failed) {
    fwrite(STDERR, count($failed) . " regression check(s) failed.\n");
    exit(1);
}

echo count($checks) . " regression checks passed.\n";
