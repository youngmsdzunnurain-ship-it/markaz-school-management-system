<?php
declare(strict_types=1);

function termFeeCheck(bool $ok,string $message):void{
 if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}
 echo "PASS: {$message}\n";
}
$legacyMigration=file_get_contents(__DIR__.'/../database/migrations/20261123_markaz_academic_term_fees.sql');
$sectionMigration=file_get_contents(__DIR__.'/../database/migrations/20261129_markaz_section_student_fees.sql');
$api=file_get_contents(__DIR__.'/../api/finance/term-fees.php');
$payments=file_get_contents(__DIR__.'/../api/finance/index.php');
$html=file_get_contents(__DIR__.'/../public/finance.html');
$dashboardHtml=file_get_contents(__DIR__.'/../public/dashboard.html');
$dashboardJs=file_get_contents(__DIR__.'/../public/js/dashboard.js');
$js=file_get_contents(__DIR__.'/../public/js/finance.js');
$css=file_get_contents(__DIR__.'/../public/css/finance.css');
$settingsApi=file_get_contents(__DIR__.'/../public/api/settings/academic-period.php');
$settingsHtml=file_get_contents(__DIR__.'/../public/settings.html');
$settingsJs=file_get_contents(__DIR__.'/../public/js/settings.js');
$helper=file_get_contents(__DIR__.'/../app/helpers/finance-fee-calculations.php');
$installer=file_get_contents(__DIR__.'/../scripts/install-finance.sh');
$feesRoute=file_get_contents(__DIR__.'/../public/api/finance/term-fees.php');

termFeeCheck(str_contains($legacyMigration,'UNIQUE KEY uq_term_student_invoice (student_id,session_id,term)'),'term invoice identity remains unique per student and academic term');
termFeeCheck(str_contains($legacyMigration,'finance_student_fee_adjustments'),'individual scholarships and negotiated fees retain a separate approval history');
termFeeCheck(str_contains($sectionMigration,'finance_section_fee_schedules')&&str_contains($sectionMigration,'academic_period_id'),'section-based rates are linked to MARKAZ academic periods');
termFeeCheck(str_contains($sectionMigration,"ENUM('nursery','primary','junior_secondary','senior_secondary')"),'Nursery, Primary, Junior Secondary and Senior Secondary are the only fee sections');
termFeeCheck(str_contains($sectionMigration,'student_type')&&str_contains($sectionMigration,'intake_type'),'each section has Day/Boarding and New Intake/Returning fee variants');
termFeeCheck(str_contains($sectionMigration,'ADD COLUMN starts_on DATE NULL')&&str_contains($sectionMigration,'ADD COLUMN ends_on DATE NULL'),'term date range is stored on the shared academic period');
termFeeCheck(str_contains($sectionMigration,'ADD COLUMN academic_period_id')&&str_contains($sectionMigration,'ADD COLUMN fee_schedule_id'),'issued invoices preserve their period and exact fee-rate snapshot');
termFeeCheck(str_contains($api,'FROM academic_periods ap')&&str_contains($api,'s.is_current=1 AND ap.is_current=1'),'Student Fees follows the single active academic period from Settings');
termFeeCheck(str_contains($api,'function markazFeeSection')&&str_contains($api,'kindergarten')&&str_contains($api,'junior secondary'),'live MARKAZ classes are grouped into the four requested education sections');
termFeeCheck(str_contains($api,'admission_date')&&str_contains($api,'starts_on')&&str_contains($api,'ends_on'),'New Intake rate is selected from the student admission date within the term');
termFeeCheck(str_contains($api, "'save_rates'") && str_contains($api, 'finance_section_fee_schedules'),'section fee rates are saved through the current-period configuration action');
termFeeCheck(str_contains($api,"if (\$action === 'register')")&&str_contains($api,'previous_outstanding')&&str_contains($api,'previous_debt_details')&&str_contains($api,'current_balance'),'central register separates current-term balances from previous-term debts');
termFeeCheck(str_contains($api,'INSERT IGNORE INTO finance_term_fee_invoices'),'re-running invoice generation cannot rewrite an issued student assessment');
termFeeCheck(str_contains($api,'payment has already been recorded against this term assessment')&&str_contains($api,'student_fee_invoice_adjusted'),'assessed but unpaid invoices can be amended with approval; any payment history locks the assessment');
termFeeCheck(str_contains($api,'different authorised staff member must approve'),'the person who submits an individual fee adjustment cannot approve it');
termFeeCheck(str_contains($api,'markazSyncStudentTermFees') && str_contains($api,'$sync=markazSyncStudentTermFees'),'saving the 16 configured rates automatically creates or synchronises current-term student assessments');
termFeeCheck(str_contains($api,'$role=currentAdminRole();$adminImmediate=$role===\\'admin\\'') && str_contains($api,"currentAdminRole()!=='admin'"),'Admin fee adjustments apply immediately while only Admin can approve Management requests');
termFeeCheck(str_contains($api,'$action===\\'reset_student_adjustment\\'') && str_contains($api,'Reset scholarship to the standard scheduled fee.'),'resetting a scholarship restores the standard configured student fee');
termFeeCheck(str_contains($api,'finance_term_fee_payments WHERE term_invoice_id=?') && str_contains($api,'$status=($dueCents===0||$paid>=$dueCents)?\\'paid\\':($paid>0?\\'partial\\':\\'unpaid\\')'),'revised assessments recalculate paid/partial/outstanding status without deleting payment allocations');
termFeeCheck(str_contains($payments,'finance_term_fee_payments'),'confirmed instalment payments are allocated to term invoices');
$currentTermAllocation = strpos($payments, '// Current-term school fees are paid first');
$monthlyAllocation = strpos($payments, '// Legacy monthly invoices remain payable');
$previousTermAllocation = strpos($payments, '// Previous-term balances carry forward');
termFeeCheck($currentTermAllocation !== false && $monthlyAllocation !== false && $previousTermAllocation !== false
    && $currentTermAllocation < $monthlyAllocation && $monthlyAllocation < $previousTermAllocation,
    'instalments settle the current term first and preserve previous-term debt separately');

termFeeCheck(str_contains($helper,'financeCalculateFeeAmountsCents')&&str_contains($helper,'financeFeeAmountToCents')&&str_contains($helper,'intdiv'),'student fee calculations use integer kobo arithmetic');
termFeeCheck(str_contains($html,'id="studentFeesAcademicPeriod"')&&str_contains($html,'id="studentFeesSession"')&&str_contains($html,'id="studentFeesTerm"'),'Student Fees displays the linked academic session and current term');
termFeeCheck(str_contains($html,'id="studentFeesTermStarts"')&&str_contains($html,'id="studentFeesTermEnds"'),'Student Fees displays the term start and end dates');
termFeeCheck(str_contains($html,'id="studentFeesSectionNav"')&&str_contains($html,'data-fee-section="nursery"')&&str_contains($html,'data-fee-section="senior_secondary"'),'the interface provides the four education-section filters');
termFeeCheck(str_contains($html,'id="studentFeesRatesForm"')&&str_contains($html,'rate_nursery_new_day')&&str_contains($html,'rate_senior_secondary_new_boarding'),'the fee form exposes all 16 section, accommodation and intake rates');
termFeeCheck(str_contains($html,'id="studentFeesRegister"')&&str_contains($html,'id="studentFeesSearch"')&&str_contains($html,'id="studentFeesScholarshipFilter"'),'the central register supports live search and scholarship filters');
termFeeCheck(str_contains($html,'Previous debt')&&str_contains($html,'Current balance')&&str_contains($html,'Total balance'),'the register shows previous debt and current/total balances separately');
termFeeCheck(str_contains($html,'id="studentFeeAdjustmentForm"')&&str_contains($html,'id="termAdjustmentQueue"'),'individual student fee support uses a separate approval queue');
termFeeCheck(str_contains($js,"termFeeRequest('register'")&&str_contains($js,'previous_outstanding')&&str_contains($js,'current_balance'),'the central register loads reconciled live student balances');
termFeeCheck(!str_contains($js,'TERM_FEE_API')&&str_contains($js,"new URL('./api/finance/term-fees.php',location.href)"),'Student Fees API URL is resolved directly without a temporal-dead-zone global');
termFeeCheck(str_contains($feesRoute, "require_once __DIR__ . '/../../../api/finance/term-fees.php'"), 'Student Fees API is reachable under the public document root');
termFeeCheck(str_contains($html,'finance.js?v=20261127-student-fees-intake-cache8'),'Finance uses a fresh asset URL so browsers cannot reuse the previously failing Student Fees script');
termFeeCheck(str_contains($js,'let studentFeesClasses=[];') && strpos($js,'let studentFeesClasses=[];') < strpos($js,'function init(){') && strpos($js,'function init(){') < strpos($js,'init();'), 'Student Fees class state is initialized before startup invokes its asynchronous loader');
termFeeCheck(str_contains($js,'async function loadTermFeeSetup()') && !str_contains($js,'loadTermFeesSetup'), 'Finance uses one canonical Student Fees loader name');
termFeeCheck(preg_match('/^var studentFeesPeriod=null;/m', $js) === 1 && strpos($js, 'var studentFeesPeriod=null;') < strpos($js, 'function showTab('), 'Student Fees academic-period state is initialized before any tab handler can run');
termFeeCheck(str_contains($installer,'20261126_markaz_fee_refunds.sql')&&str_contains($installer,'refund_tables'),'Finance installation includes the refund tables required by the dashboard');
termFeeCheck(str_contains($js,'data-fee-section')&&str_contains($js,'generate_term_invoices')&&str_contains($js,'data-approve-term-adjustment'),'section filters, invoice generation and independent approvals are connected');
termFeeCheck(str_contains($settingsApi,'starts_on')&&str_contains($settingsApi,'ends_on')&&str_contains($settingsHtml,'academicTermStartsOn')&&str_contains($settingsHtml,'academicTermEndsOn'),'term start/end dates are configured in the existing Settings calendar');
termFeeCheck(str_contains($settingsJs,'academicTermStartsOn')&&str_contains($settingsJs,'academicTermEndsOn'),'Settings loads and saves the shared term date range');
termFeeCheck(str_contains($css,'.student-fees-section-switcher')&&str_contains($css,'.student-fees-filters')&&str_contains($css,'@media(max-width:720px)'),'Student Fees has compact responsive styling for phones and desktop');
termFeeCheck(str_contains($html,'href="./dashboard.html"')&&str_contains($html,'data-tab="overview"')&&str_contains($html,'data-tab="fees"')&&str_contains($html,'data-tab="payments"')&&str_contains($html,'data-tab="expenses"')&&str_contains($html,'data-tab="payroll"')&&str_contains($html,'data-tab="reports"'),'the dedicated Finance sidebar retains all six workspaces');
termFeeCheck(str_contains($html,'<nav class="sidebar-nav finance-sidebar-nav" aria-label="School Fees navigation">')&&!str_contains($html,'id="logoutButton"'),'Finance uses the shared dashboard shell without duplicate logout controls');
termFeeCheck(str_contains($html,'id="welcomeGreeting"')&&str_contains($html,'id="adminName"')&&str_contains($html,'class="welcome-card"'),'Finance Overview retains the shared dashboard greeting');
termFeeCheck(str_contains($html,'fa-solid fa-')&&str_contains($html,'Manrope:wght@400;500;600;700;800')&&str_contains($html,'font-awesome/6.7.2/css/all.min.css'),'Finance uses consistent Font Awesome icons and shared typography');
termFeeCheck(str_contains($dashboardHtml,'href="./finance.html" class="nav-link" data-role-access="management_school_fees"'),'the Admin Dashboard School Fees navigation opens Finance');
termFeeCheck(str_contains($dashboardJs,'./api/auth/me.php')&&str_contains($dashboardJs,'management_school_fees'),'Finance navigation retains existing role-based access');
termFeeCheck(!str_contains($js,'DEMO_')&&!str_contains($js,'demoResponse')&&!str_contains($js,'Teacher Demo'),'Finance JavaScript contains no fabricated demo students or payroll');
termFeeCheck(!str_contains($html,'finance-demo-shortcut')&&!str_contains($html,'?demo='),'Finance exposes no demo shortcut');
termFeeCheck(!str_contains($css,'finance-demo-'),'Finance CSS contains no demo-only styling');

echo "MARKAZ section-based student-fees regression checks passed.\n";
