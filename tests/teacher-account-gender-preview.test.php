<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;

function verify(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if ($content === false) {
        throw new RuntimeException("Unable to read {$path}");
    }
    return $content;
};

$migration = $read('database/migrations/20261128_teacher_account_gender.sql');
$api = $read('api/settings/accounts.php');
$accountUi = $read('public/js/account-management.js');
$financeHtml = $read('public/finance.html');
$financeJs = $read('public/js/finance.js');
$financeCss = $read('public/css/finance.css');
$seed = $read('scripts/dev/seed-teacher-preview.php');

verify(str_contains($migration, "COLUMN_NAME = 'gender'"), 'Gender migration must be safe to re-run.');
verify(str_contains($api, 'a.gender'), 'Account API must return gender.');
verify(str_contains($api, 'gender = ?'), 'Account API must persist gender on updates.');
verify(str_contains($api, 'password_hash, is_active, student_id, gender'), 'Account API must persist gender on account creation.');
verify(str_contains($accountUi, 'accountGenderField'), 'Teacher account form must include a gender field.');
verify(str_contains($accountUi, 'genderInput.value'), 'Teacher account edit/save flow must preserve gender.');
verify(str_contains($accountUi, 'account.gender'), 'Account list must display the saved gender.');
verify(str_contains($financeHtml, 'recent-payments-table') && str_contains($financeHtml, '<colgroup>'), 'Recent payments table must define its own column layout.');
verify(str_contains($financeJs, 'recent-payment-student-name') && str_contains($financeJs, 'recent-payment-amount'), 'Recent payment cells must have clear semantic structure.');
verify(str_contains($financeCss, 'table-layout: fixed') && str_contains($financeCss, 'recent-payments-table'), 'Recent payments must use fixed, scoped table sizing.');
verify(str_contains($seed, "MARKAZ_ALLOW_TEACHER_PREVIEW_SEED") && str_contains($seed, "markaz_result_system"), 'Teacher seed script must be explicitly guarded for the local database.');
verify(substr_count($seed, "'name' =>") === 20, 'Teacher seed must contain exactly 20 distinct sample records.');

echo "PASS: {$checks} teacher-account, seed-safety and recent-payment layout checks.\n";
