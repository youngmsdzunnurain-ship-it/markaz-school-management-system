<?php
declare(strict_types=1);

function expectSourceContains(string $source, string $needle, string $label): void
{
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$financeHtml = file_get_contents($root . '/public/finance.html');
$financeJs = file_get_contents($root . '/public/js/finance.js');
$financeCss = file_get_contents($root . '/public/css/finance.css');
$accountJs = file_get_contents($root . '/public/js/account-management.js');
$accountApi = file_get_contents($root . '/api/settings/accounts.php');
$migration = file_get_contents($root . '/database/migrations/20261128_teacher_account_gender.sql');
$seeder = file_get_contents($root . '/scripts/create-teacher-preview-accounts.php');

expectSourceContains($financeHtml, 'recent-payments-table', 'Recent payments table has a scoped class');
expectSourceContains($financeHtml, 'recent-payments-wrap', 'Recent payments wrapper has a scoped class');
expectSourceContains($financeJs, 'payment-student-cell', 'Student name and admission ID have separate visual hierarchy');
expectSourceContains($financeJs, 'payment-amount-cell', 'Payment amount is visually separated');
expectSourceContains($financeCss, 'table-layout:fixed', 'Recent payments table uses stable column sizing');
expectSourceContains($financeCss, 'overflow-wrap:anywhere', 'Long names and IDs can wrap without crushing columns');
expectSourceContains($financeCss, '.recent-payments-table th:nth-child(2){width:25%;}', 'Student column receives enough width');

expectSourceContains($accountJs, 'id="accountGender"', 'Teacher form includes a gender selector');
expectSourceContains($accountJs, "'Username: ' + (account.username || 'Not set')", 'Teacher usernames are visible in the account register');
expectSourceContains($accountJs, 'genderInput.required = isTeacher', 'Gender is required for teacher accounts');
expectSourceContains($accountJs, "gender: role.value === 'teacher' ? genderInput.value : ''", 'Gender is sent when saving teacher accounts');
expectSourceContains($accountJs, "account.gender ? account.gender.charAt(0).toUpperCase()", 'Existing teacher gender is displayed in the account list');
expectSourceContains($accountApi, "Choose the teacher\\'s gender.", 'API validates teacher gender');
expectSourceContains($accountApi, 'gender = ?', 'API persists teacher gender');
expectSourceContains($migration, "ENUM('male', 'female')", 'Database migration stores supported gender values');

if (!preg_match_all("/\\['[^']+', '(?:male|female)'\\]/", $seeder, $matches) || count($matches[0]) !== 20) {
    fwrite(STDERR, "FAIL: seeder must contain exactly 20 fictional teacher records\n");
    exit(1);
}
expectSourceContains($seeder, "in_array('--confirm', \$argv, true)", 'Seeder requires explicit confirmation');
expectSourceContains($seeder, 'password_hash($temporaryPassword, PASSWORD_DEFAULT)', 'Seeder hashes generated temporary passwords');
expectSourceContains($seeder, 'random_bytes(8)', 'Seeder generates unpredictable temporary passwords');

echo "Teacher account gender and recent payments regression checks passed.\n";
