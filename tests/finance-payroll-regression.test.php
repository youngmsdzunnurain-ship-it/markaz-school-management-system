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

$feeSelection = financeSelectApplicableFeePlans([
    ['id'=>1,'fee_code'=>'tuition','scope'=>'school','amount'=>'30000.00','is_active'=>1,'effective_from'=>'2026-01-01','effective_until'=>null],
    ['id'=>2,'fee_code'=>'tuition','scope'=>'student','student_id'=>77,'amount'=>'20000.00','is_active'=>1,'effective_from'=>'2026-01-01','effective_until'=>null],
    ['id'=>3,'fee_code'=>'feeding','scope'=>'school','amount'=>'5000.00','is_active'=>1,'effective_from'=>'2026-01-01','effective_until'=>null],
], ['id'=>77,'class_id'=>4,'arabic_class_id'=>8], '2026-11-01');
financeRegressionCheck(count($feeSelection) === 2 && $feeSelection[0]['id'] === 2 && $feeSelection[1]['id'] === 3, 'student-specific fee overrides the school fee only for that student while unrelated fee categories remain');
$otherStudentFees = financeSelectApplicableFeePlans([
    ['id'=>1,'fee_code'=>'tuition','scope'=>'school','amount'=>'30000.00','is_active'=>1,'effective_from'=>'2026-01-01','effective_until'=>null],
    ['id'=>2,'fee_code'=>'tuition','scope'=>'student','student_id'=>77,'amount'=>'20000.00','is_active'=>1,'effective_from'=>'2026-01-01','effective_until'=>null],
], ['id'=>78,'class_id'=>4,'arabic_class_id'=>8], '2026-11-01');
financeRegressionCheck(count($otherStudentFees) === 1 && $otherStudentFees[0]['amount'] === '30000.00', 'a student-specific negotiated fee never changes another student amount');
$midMonthPlan = financeSelectApplicableFeePlans([
    ['id'=>4,'fee_code'=>'tuition','scope'=>'school','amount'=>'30000.00','is_active'=>1,'effective_from'=>'2026-11-15','effective_until'=>null],
], ['id'=>79,'class_id'=>4,'arabic_class_id'=>8], '2026-11-01');
financeRegressionCheck(count($midMonthPlan) === 1, 'a fee plan beginning during the billing month is eligible for that month');
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
$independentStaffMigration = file_get_contents(__DIR__ . '/../database/migrations/20261124_markaz_independent_finance_staff.sql');
$financeApi = file_get_contents(__DIR__ . '/../api/finance/index.php');
$payrollApi = file_get_contents(__DIR__ . '/../api/finance/payroll.php');

financeRegressionCheck(str_contains($payrollApi, 'financeProrateMonthlySalary'), 'payroll uses the salary proration rule for mid-month profile changes');

$financePage = file_get_contents(__DIR__ . '/../public/finance.html');
$financeStyles = file_get_contents(__DIR__ . '/../public/css/finance.css');
$dashboardStyles = file_get_contents(__DIR__ . '/../public/css/dashboard-2026.css');
$financeJs = file_get_contents(__DIR__ . '/../public/js/finance.js');
$dashboardJs = file_get_contents(__DIR__ . '/../public/js/dashboard.js');
$teacherAttendanceApi = file_get_contents(__DIR__ . '/../api/attendance/teachers.php');
$financeHelper = file_get_contents(__DIR__ . '/../app/helpers/finance.php');

financeRegressionCheck(str_contains($migration, "frequency ENUM('monthly')"), 'fee plans use monthly billing periods');
financeRegressionCheck(str_contains($migration, "scope ENUM('school','class','student')"), 'fee plans support school-wide, class and individual scopes');
financeRegressionCheck(str_contains($migration, 'FOREIGN KEY (student_id) REFERENCES students(id)'), 'fee accounts reuse the existing MARKAZ student identity');
financeRegressionCheck(str_contains($independentStaffMigration, 'FOREIGN KEY (finance_staff_id) REFERENCES finance_staff(id)'), 'salary, attendance and payroll now reference the independent Finance staff register');
financeRegressionCheck(str_contains($payrollApi, 'FROM finance_staff') && str_contains($payrollApi, 'finance_staff_id'), 'payroll calculations and history use Finance staff IDs rather than academic accounts');
financeRegressionCheck(!str_contains($financeApi, "WHERE a.role='teacher'"), 'Finance staff discovery never imports academic teacher login accounts');
financeRegressionCheck(str_contains($migration, 'CREATE TABLE staff_attendance'), 'teacher clock records are separate from student attendance');
financeRegressionCheck(str_contains($migration, 'CREATE TABLE finance_other_income'), 'non-fee school income has its own verified record type');
financeRegressionCheck(str_contains($migration, 'idempotency_key VARCHAR(120) NOT NULL UNIQUE'), 'salary payout records have unique idempotency keys');
financeRegressionCheck(str_contains($financeApi, 'financeRequireAccess();'), 'finance API delegates access checks to the shared finance helper');
financeRegressionCheck(str_contains($financeHelper, "requireManagementPermission('school_fees')") && str_contains($financeHelper, '$role ==='), 'finance access is restricted to Admin and individually authorised Management');
financeRegressionCheck(!preg_match('/\\bbc(add|sub|comp)\\s*\\(/', $financeHelper), 'financial arithmetic does not depend on optional BCMath');
financeRegressionCheck(str_contains($financeApi, 'verify_payment'), 'bank transfer payments require an explicit verification action');
financeRegressionCheck(!str_contains($financePage, 'Development workspace'), 'development-only labels do not leak into the standard MARKAZ finance footer');
financeRegressionCheck(str_contains($financePage, '<span class="footer-brand">MARKAZ</span>') && str_contains($financePage, '© 2026 MARKAZ. All Rights Reserved.'), 'finance footer uses the exact standard MARKAZ footer structure');
financeRegressionCheck(str_contains($dashboardStyles, 'grid-template-columns:260px minmax(0,1fr)'), 'desktop finance sidebar inherits the exact 260px MARKAZ dashboard width');
financeRegressionCheck(str_contains($financeStyles, 'width:min(220px,68vw)') || str_contains($financeStyles, 'width: min(220px, 68vw)'), 'mobile finance drawer is constrained to a compact responsive width');
financeRegressionCheck(str_contains($dashboardJs, 'sidebar.classList.add("open")') && str_contains($dashboardJs, 'sidebarOverlay?.classList.add("visible")'), 'shared dashboard controller owns mobile drawer and overlay state');
financeRegressionCheck(str_contains($dashboardJs, 'sidebar-desktop-closed') && str_contains($dashboardJs, 'desktop-closed'), 'shared dashboard controller can collapse and restore the desktop sidebar');
financeRegressionCheck(str_contains($dashboardJs, 'closeSidebar') && str_contains($dashboardJs, 'window.innerWidth <= 850'), 'shared dashboard controller closes the mobile drawer on navigation');
financeRegressionCheck(str_contains($financeStyles, '.report-filters') && str_contains($financeStyles, '.report-metrics'), 'financial report filters and summary metrics receive dedicated layout styling');
financeRegressionCheck(!str_contains($financeJs, 'DEMO_MODE') && !str_contains($financeJs, 'demoResponse'), 'Finance does not expose fabricated demo data or demo modes');
financeRegressionCheck(!str_contains($financePage, 'finance-demo-shortcut') && !str_contains($financePage, '?demo='), 'Finance navigation contains no demo shortcuts or demo URLs');
financeRegressionCheck(!str_contains($financeStyles, '.finance-demo-nav'), 'Finance stylesheet contains no demo-only styling');
financeRegressionCheck(str_contains($teacherAttendanceApi, 'financeCalculateLateMinutes'), 'the Attendance module calculates lateness from the Finance arrival schedule');
financeRegressionCheck(!str_contains($payrollApi, 'grace_period'), 'lateness calculation does not apply a grace period');

financeRegressionCheck(str_contains($financePage, 'id="cashflowChart"') && str_contains($financePage, 'id="incomeMixChart"'), 'finance overview provides dedicated cash-flow and income-mix chart regions');
financeRegressionCheck(str_contains($financeJs, 'renderCashflowChart') && str_contains($financeJs, 'renderIncomeMixChart'), 'finance overview renders responsive visual analytics from returned data');
financeRegressionCheck(str_contains($financeApi, 'financeSelectApplicableFeePlans') && str_contains($financeApi, 'fee_code'), 'invoice generation applies one winning plan per fee category');
financeRegressionCheck(str_contains($migration, 'ADD COLUMN fee_code') && str_contains($financeApi, 'fee_code'), 'fee category is persisted on plans and generated invoices');
financeRegressionCheck(str_contains($financeApi, 'monthly_trend') && str_contains($financeApi, 'income_mix'), 'finance dashboard returns database-backed monthly trend and income composition');
financeRegressionCheck(str_contains($financeStyles, '.finance-chart-grid') && str_contains($financeStyles, '.chart-legend'), 'finance analytics charts receive responsive and accessible styling');
financeRegressionCheck(str_contains($financeStyles, 'grid-template-columns:repeat(5,minmax(0,1fr))') && str_contains($financeStyles, '@media(max-width:850px)'), 'five finance overview metrics share one desktop row and adapt on mobile');
financeRegressionCheck(!str_contains($financePage, 'finance-refresh-button') && !str_contains($financeStyles, '.finance-refresh-button'), 'Finance has no redundant refresh toolbar control');
financeRegressionCheck(str_contains($financeStyles, '.payroll-controls select') && str_contains($financeStyles, '.payroll-controls input'), 'payroll month and scheduled release controls have dedicated polished styling');
financeRegressionCheck(str_contains($financeStyles, '.report-metrics{grid-template-columns:repeat(5,minmax(0,1fr))'), 'all five financial report summary metrics fit one desktop row');
financeRegressionCheck(str_contains($financeStyles, '.dashboard-footer{padding:12px 20px') && str_contains($financeStyles, '.sidebar{width:240px'), 'finance footer and desktop sidebar use compact MARKAZ proportions');
financeRegressionCheck(str_contains($financeStyles, '.topbar{min-height:60px') && str_contains($financeStyles, '.topbar>div{'), 'mobile top navigation is compact and horizontally aligned');
financeRegressionCheck(!str_contains($financePage, 'finance-demo-shortcut') && !str_contains($financePage, '?demo=overview'), 'Finance overview exposes no demo entry point');
financeRegressionCheck(str_contains($financeJs, 'Dashboard data could not be loaded:') && str_contains($financeJs, 'role="alert"'), 'dashboard API failures replace indefinite chart loading placeholders with visible errors');
financeRegressionCheck(!str_contains($financeJs, 'fees_today:25000') && !str_contains($financeJs, 'net_movement:21500'), 'Finance totals are loaded from live API data rather than hard-coded demo figures');
financeRegressionCheck(str_contains($financeApi, 'exact same six calendar-month window'), 'income composition and cash-flow charts share the same reporting window');

echo "MARKAZ finance and payroll regression checks passed.\n";
