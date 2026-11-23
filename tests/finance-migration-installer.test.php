<?php
declare(strict_types=1);

function financeInstallerCheck(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$installer = file_get_contents($root . '/scripts/install-finance.sh');
$guide = file_get_contents($root . '/docs/finance-installation.md');
$foundation = file_get_contents($root . '/database/migrations/20261121_markaz_finance_payroll.sql');
$categories = file_get_contents($root . '/database/migrations/20261122_markaz_finance_fee_categories.sql');
$terms = file_get_contents($root . '/database/migrations/20261123_markaz_academic_term_fees.sql');
$independentStaff = file_get_contents($root . '/database/migrations/20261124_markaz_independent_finance_staff.sql');
$attendanceModes = file_get_contents($root . '/database/migrations/20261125_teacher_attendance_modes.sql');
$refunds = file_get_contents($root . '/database/migrations/20261126_markaz_fee_refunds.sql');

financeInstallerCheck(str_contains($foundation, 'CREATE TABLE finance_fee_payments'), 'foundation migration creates the payment table required by term fees');
financeInstallerCheck(str_contains($terms, 'REFERENCES finance_fee_payments(id)'), 'term-fee payment allocation references the foundation payment table');
financeInstallerCheck(strpos($installer, '20261121_markaz_finance_payroll.sql') < strpos($installer, '20261122_markaz_finance_fee_categories.sql')
    && strpos($installer, '20261122_markaz_finance_fee_categories.sql') < strpos($installer, '20261123_markaz_academic_term_fees.sql'), 'installer applies migrations in dependency order');
financeInstallerCheck(str_contains($installer, 'mariadb-dump') && str_contains($installer, '[[ -s "$backup" ]]'), 'installer verifies a database backup before running migrations');
financeInstallerCheck(str_contains($installer, 'DB_HOST="${DB_HOST:-127.0.0.1}"') && str_contains($installer, 'DB_USER="${DB_USER:-root}"'), 'installer uses MARKAZ TCP database host and root defaults instead of the Termux shell account');
financeInstallerCheck(str_contains($installer, '--protocol=TCP') && str_contains($installer, 'mariadb-dump'), 'installer applies the same explicit TCP connection settings to database operations');
financeInstallerCheck(str_contains($installer, 'INSTALL FINANCE ON TEST DATABASE'), 'installer requires explicit confirmation before database changes');
financeInstallerCheck(str_contains($installer, 'DB_NAME must be set explicitly') && str_contains($installer, 'DB_NAME="${DB_NAME:-}"'), 'installer refuses to guess a database target');
financeInstallerCheck(str_contains($installer, 'ALLOW_PRODUCTION_INSTALL') && str_contains($installer, 'markaz_result_system'), 'installer blocks the live database unless production is explicitly enabled');
financeInstallerCheck(str_contains($installer, 'INSTALL FINANCE ON PRODUCTION DATABASE') && str_contains($installer, 'INSTALL FINANCE ON TEST DATABASE'), 'installer uses a distinct confirmation phrase for production and test databases');
financeInstallerCheck(str_contains($installer, 'Partial finance foundation detected')
    && str_contains($installer, 'Partial academic term-fee schema detected'), 'installer refuses to continue with partially applied schemas');
financeInstallerCheck(str_contains($installer, 'for table in admins students classes'), 'installer validates existing school-table prerequisites');
financeInstallerCheck(str_contains($installer, 'MARKAZ Finance is already installed'), 'installer is safe to rerun after a complete installation');
financeInstallerCheck(!str_contains($installer, '\\${'), 'shell parameter expansions are emitted as valid Bash syntax');
financeInstallerCheck(str_contains($guide, 'The third migration references finance_fee_payments'), 'installation guide explains the missing dependency');
financeInstallerCheck(str_contains($categories, 'ADD COLUMN fee_code'), 'fee-category migration remains part of the ordered installation');
financeInstallerCheck(str_contains($independentStaff, 'CREATE TABLE finance_staff') && str_contains($independentStaff, 'finance_staff_id'), 'independent staff migration creates a separate payroll identity');
financeInstallerCheck(str_contains($installer, '20261124_markaz_independent_finance_staff.sql') && str_contains($installer, 'independent_staff_columns') && str_contains($installer, 'independent_staff_constraints'), 'installer applies and verifies the independent staff schema after Finance foundations');
financeInstallerCheck(str_contains($attendanceModes, "attendance_mode ENUM('time_based','attendance_only')") && str_contains($attendanceModes, 'MODIFY COLUMN expected_arrival TIME NULL'), 'teacher attendance migration preserves existing time-based staff and permits attendance-only profiles');
financeInstallerCheck(str_contains($refunds, 'CREATE TABLE finance_fee_refunds') && str_contains($refunds, 'CREATE TABLE finance_fee_refund_allocations'), 'refund migration creates maker-checker requests and invoice-allocation reversals');
financeInstallerCheck(str_contains($installer, '20261125_teacher_attendance_modes.sql') && str_contains($installer, '20261126_markaz_fee_refunds.sql') && str_contains($installer, 'refund_tables'), 'installer applies and verifies teacher attendance policies and refund tables after their dependencies');
echo "MARKAZ Finance migration installer regression checks passed.\n";
