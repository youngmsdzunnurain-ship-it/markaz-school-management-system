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

financeInstallerCheck(str_contains($foundation, 'CREATE TABLE finance_fee_payments'), 'foundation migration creates the payment table required by term fees');
financeInstallerCheck(str_contains($terms, 'REFERENCES finance_fee_payments(id)'), 'term-fee payment allocation references the foundation payment table');
financeInstallerCheck(strpos($installer, '20261121_markaz_finance_payroll.sql') < strpos($installer, '20261122_markaz_finance_fee_categories.sql')
    && strpos($installer, '20261122_markaz_finance_fee_categories.sql') < strpos($installer, '20261123_markaz_academic_term_fees.sql'), 'installer applies migrations in dependency order');
financeInstallerCheck(str_contains($installer, 'mariadb-dump') && str_contains($installer, '[[ -s "$backup" ]]'), 'installer verifies a database backup before running migrations');
financeInstallerCheck(str_contains($installer, 'INSTALL FINANCE'), 'installer requires explicit confirmation before database changes');
financeInstallerCheck(str_contains($installer, 'Partial finance foundation detected')
    && str_contains($installer, 'Partial academic term-fee schema detected'), 'installer refuses to continue with partially applied schemas');
financeInstallerCheck(str_contains($installer, 'for table in admins students classes'), 'installer validates existing school-table prerequisites');
financeInstallerCheck(str_contains($installer, 'MARKAZ Finance is already installed'), 'installer is safe to rerun after a complete installation');
financeInstallerCheck(!str_contains($installer, '\\${'), 'shell parameter expansions are emitted as valid Bash syntax');
financeInstallerCheck(str_contains($guide, 'The third migration references finance_fee_payments'), 'installation guide explains the missing dependency');
financeInstallerCheck(str_contains($categories, 'ADD COLUMN fee_code'), 'fee-category migration remains part of the ordered installation');
echo "MARKAZ Finance migration installer regression checks passed.\n";
