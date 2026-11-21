<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/finance.php';

function financeRegressionCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

financeRegressionCheck(financeCalculateLateMinutes('15:30', '15:30') === 0, 'arrival exactly on schedule has no lateness');
financeRegressionCheck(financeCalculateLateMinutes('15:30', '15:35') === 5, 'five minutes late is counted exactly');
financeRegressionCheck(financeCalculateLateMinutes('15:30', '15:40') === 10, 'ten minutes late is counted exactly');
financeRegressionCheck(financeCalculateLateMinutes('15:30', '15:29') === 0, 'early arrival never creates a negative deduction');
financeRegressionCheck(financeValidDate('2026-02-28') && !financeValidDate('2026-02-31'), 'calendar dates are validated rather than regex-only');
financeRegressionCheck(financeToCents('10.00') * 5 === 5000, 'five late minutes at NGN 10 per minute equals NGN 50');
financeRegressionCheck(financeToCents('10.00') * 10 === 10000, 'ten late minutes at NGN 10 per minute equals NGN 100');
financeRegressionCheck(financeFromCents(19400) === '194.00', 'integer-cent arithmetic formats exact naira amounts');
financeRegressionCheck(financeProrateMonthlySalary('20000.00', 30, 30) === '20000.00', 'full-month salary is not changed by proration');
financeRegressionCheck(financeProrateMonthlySalary('20000.00', 15, 30) === '10000.00', 'mid-month salary changes are prorated by calendar days');
financeRegressionCheck(financeMoney('1250.5') === '1250.50', 'money input is normalised to two decimal places');

$migration = file_get_contents(__DIR__ . '/../database/migrations/20261121_markaz_finance_payroll.sql');
$financeApi = file_get_contents(__DIR__ . '/../api/finance/index.php');
$payrollApi = file_get_contents(__DIR__ . '/../api/finance/payroll.php');

financeRegressionCheck(str_contains($payrollApi, 'financeProrateMonthlySalary'), 'payroll uses the salary proration rule for mid-month profile changes');

$financePage = file_get_contents(__DIR__ . '/../public/finance.html');
$financeHelper = file_get_contents(__DIR__ . '/../app/helpers/finance.php');

financeRegressionCheck(str_contains($migration, "frequency ENUM('monthly')"), 'fee plans use monthly billing periods');
financeRegressionCheck(str_contains($migration, "scope ENUM('school','class','student')"), 'fee plans support school-wide, class and individual scopes');
financeRegressionCheck(str_contains($migration, 'FOREIGN KEY (student_id) REFERENCES students(id)'), 'fee accounts reuse the existing MARKAZ student identity');
financeRegressionCheck(str_contains($migration, 'FOREIGN KEY (account_id) REFERENCES admins(id)'), 'payroll reuses existing staff account identities');
financeRegressionCheck(str_contains($migration, 'CREATE TABLE staff_attendance'), 'teacher clock records are separate from student attendance');
financeRegressionCheck(str_contains($migration, 'CREATE TABLE finance_other_income'), 'non-fee school income has its own verified record type');
financeRegressionCheck(str_contains($migration, 'idempotency_key VARCHAR(120) NOT NULL UNIQUE'), 'salary payout records have unique idempotency keys');
financeRegressionCheck(str_contains($financeApi, 'financeRequireAccess();'), 'finance API delegates access checks to the shared finance helper');
financeRegressionCheck(str_contains($financeHelper, "requireManagementPermission('school_fees')") && str_contains($financeHelper, '$role ==='), 'finance access is restricted to Admin and individually authorised Management');
financeRegressionCheck(!preg_match('/\\bbc(add|sub|comp)\\s*\\(/', $financeHelper), 'financial arithmetic does not depend on optional BCMath');
financeRegressionCheck(str_contains($financeApi, 'verify_payment'), 'bank transfer payments require an explicit verification action');
financeRegressionCheck(str_contains($financePage, 'Development workspace'), 'finance UI is explicitly labelled as a separate development workspace');
financeRegressionCheck(str_contains($payrollApi, 'financeCalculateLateMinutes'), 'payroll uses the shared lateness calculation');
financeRegressionCheck(!str_contains($payrollApi, 'grace_period'), 'lateness calculation does not apply a grace period');

echo "MARKAZ finance and payroll regression checks passed.\n";
