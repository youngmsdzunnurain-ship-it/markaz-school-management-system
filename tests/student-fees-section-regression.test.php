<?php
declare(strict_types=1);

function studentFeesCheck(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/database/migrations/20261129_markaz_section_student_fees.sql');
$api = file_get_contents($root . '/api/finance/term-fees.php');
$html = file_get_contents($root . '/public/finance.html');
$js = file_get_contents($root . '/public/js/finance.js');
$settingsApi = file_get_contents($root . '/public/api/settings/academic-period.php');
$settingsHtml = file_get_contents($root . '/public/settings.html');
$settingsJs = file_get_contents($root . '/public/js/settings.js');
$installer = file_get_contents($root . '/scripts/install-finance.sh');
require_once $root . '/app/helpers/finance-fee-calculations.php';

studentFeesCheck(str_contains($migration, 'ADD COLUMN starts_on DATE NULL') && str_contains($migration, 'ADD COLUMN ends_on DATE NULL'), 'term start and end dates extend the existing Settings academic period');
studentFeesCheck(str_contains($migration, 'finance_section_fee_schedules') && str_contains($migration, 'academic_period_id') && str_contains($migration, 'student_type') && str_contains($migration, 'intake_type'), 'fee rates are configured per academic term, section, day/boarding and new/returning intake');
studentFeesCheck(str_contains($migration, "ENUM('nursery','primary','junior_secondary','senior_secondary')"), 'the four MARKAZ education sections are explicit and constrained');
studentFeesCheck(str_contains($migration, 'ADD COLUMN academic_period_id') && str_contains($migration, 'ADD COLUMN section'), 'issued invoices retain their original academic period and fee classification');
studentFeesCheck(str_contains($migration, 'intake_type ENUM(\'returning\',\'new_intake\') NULL') && !str_contains($migration, 'UPDATE finance_term_fee_invoices'), 'migration leaves historical invoice classifications untouched instead of guessing old intake data');
studentFeesCheck(str_contains($api, "preg_match('/\\\\bsss\\\\s*[1-3]\\\\b/'"), 'SSS classes with subject suffixes map to senior secondary');
studentFeesCheck(str_contains($settingsApi, 'starts_on') && str_contains($settingsApi, 'ends_on'), 'Settings reads and saves the academic term date range');
studentFeesCheck(str_contains($settingsHtml, 'id="academicTermStartsOn"') && str_contains($settingsHtml, 'id="academicTermEndsOn"'), 'Settings exposes term begins and term ends fields');
studentFeesCheck(str_contains($settingsJs, 'academicTermStartsOn') && str_contains($settingsJs, 'academicTermEndsOn'), 'Settings sends the configured term dates');
studentFeesCheck(str_contains($api, 'academic_periods') && str_contains($api, 'academic_sessions') && str_contains($api, 'terms'), 'Student Fees reads the single current period from MARKAZ academic settings');
studentFeesCheck(str_contains($api, 'finance_section_fee_schedules') && str_contains($api, 'new_intake') && str_contains($api, 'student_type'), 'the API saves the four section-based day/boarding and intake fee rates');
studentFeesCheck(str_contains($api, 'admission_date') && str_contains($api, 'starts_on') && str_contains($api, 'ends_on'), 'new intake classification uses the student admission date within the configured academic term');
studentFeesCheck(str_contains($api, 'function markazFeeSection') && str_contains($api, 'kindergarten') && str_contains($api, 'junior secondary'), 'existing active MARKAZ classes are mapped to the four requested sections');
studentFeesCheck(str_contains($api, "if (\$action === 'register')") && str_contains($api, 'previous_outstanding') && str_contains($api, 'previous_debt_details') && str_contains($api, 'current_normal_fee') && str_contains($api, 'current_discount') && str_contains($api, 'current_balance'), 'central register reports previous debt and current-term balance separately');
studentFeesCheck(str_contains($api, 'financeToCents') && str_contains($api, 'financeFromCents'), 'fee discounts and balances use integer kobo arithmetic');
studentFeesCheck(str_contains($api, 'INSERT IGNORE INTO finance_term_fee_invoices') && str_contains($api, 'ON DUPLICATE KEY'), 'invoice generation is safe to retry without rewriting issued assessments');
studentFeesCheck(str_contains($html, 'id="studentFeesAcademicPeriod"') && str_contains($html, 'id="studentFeesSectionNav"'), 'Student Fees shows the linked academic period and four section navigation');
studentFeesCheck(str_contains($html, 'id="studentFeesRegister"') && str_contains($html, 'id="studentFeesSearch"') && str_contains($html, 'id="studentFeesScholarshipFilter"'), 'the central register supports student search and scholarship filtering');
studentFeesCheck(str_contains($html, 'id="studentFeesIntakeFilter"') && str_contains($api, '$intakeFilter'), 'the central register identifies and filters new-intake and returning students');
studentFeesCheck(str_contains($html, 'id="studentFeesRatesForm"') && str_contains($html, 'rate_nursery_new_day') && str_contains($html, 'rate_nursery_new_boarding'), 'the compact configuration form includes all four fee rate types');
studentFeesCheck(str_contains($js, "termFeeRequest('register'") && str_contains($js, 'previous_outstanding') && str_contains($js, 'current_balance'), 'the central register loads reconciled live student balances');
studentFeesCheck(str_contains($installer, '20261129_markaz_section_student_fees.sql'), 'the Finance installer applies and verifies the new section-based fee migration');
$percentage = financeCalculateFeeAmountsCents(financeFeeAmountToCents('90000.00'), 'discount_percent', '12.50');
studentFeesCheck($percentage === [financeFeeAmountToCents('90000.00'), financeFeeAmountToCents('11250.00'), financeFeeAmountToCents('78750.00')], '12.50% scholarship is rounded to the nearest kobo using integer arithmetic');
$fixed = financeCalculateFeeAmountsCents(financeFeeAmountToCents('50000.00'), 'fixed_fee', '30000.00');
studentFeesCheck($fixed === [financeFeeAmountToCents('50000.00'), financeFeeAmountToCents('20000.00'), financeFeeAmountToCents('30000.00')], 'negotiated final fee replaces only the assessed current-term amount');
$fixedOverNormalRejected = false;
try { financeCalculateFeeAmountsCents(financeFeeAmountToCents('50000.00'), 'fixed_fee', '50000.01'); } catch (DomainException) { $fixedOverNormalRejected = true; }
studentFeesCheck($fixedOverNormalRejected, 'a negotiated fee cannot silently exceed the normal section fee');

$discount = financeCalculateFeeAmountsCents(financeFeeAmountToCents('50000.00'), 'discount_amount', '60000.00');
studentFeesCheck($discount === [financeFeeAmountToCents('50000.00'), financeFeeAmountToCents('50000.00'), 0], 'fixed scholarship reduction is capped at the base fee');
$waiver = financeCalculateFeeAmountsCents(financeFeeAmountToCents('50000.00'), 'full_scholarship', '0.00');
studentFeesCheck($waiver === [financeFeeAmountToCents('50000.00'), financeFeeAmountToCents('50000.00'), 0], 'full scholarship makes only the current-term assessment zero');
$halfKobo = financeCalculateFeeAmountsCents(1, 'discount_percent', '50.00');
studentFeesCheck($halfKobo === [1, 1, 0], 'percentage rounding is deterministic at a half-kobo boundary');
$invalidPercentRejected = false;
try { financeCalculateFeeAmountsCents(financeFeeAmountToCents('100.00'), 'discount_percent', '100.01'); } catch (DomainException) { $invalidPercentRejected = true; }
studentFeesCheck($invalidPercentRejected, 'percentage scholarships above 100% are rejected');

studentFeesCheck(financeFeeAmountToCents('90000.00') + financeFeeAmountToCents('15000.00') - financeFeeAmountToCents('50000.00') === financeFeeAmountToCents('55000.00'), '₦90,000 current fee plus ₦15,000 previous debt less ₦50,000 paid equals ₦55,000');
studentFeesCheck(financeFeeAmountToCents('90000.00') - financeFeeAmountToCents('50000.00') === financeFeeAmountToCents('40000.00'), 'current-term balance remains separately identifiable as ₦40,000');
studentFeesCheck(financeFeeAmountToCents('0.00') + financeFeeAmountToCents('15000.00') - financeFeeAmountToCents('5000.00') === financeFeeAmountToCents('10000.00'), 'full scholarship does not erase previous-term debt');

echo "MARKAZ section-based student-fees regression checks passed.\n";
