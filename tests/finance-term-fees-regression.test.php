<?php
declare(strict_types=1);

function termFeeCheck(bool $ok,string $message):void{
 if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}
 echo "PASS: {$message}\n";
}
$migration=file_get_contents(__DIR__.'/../database/migrations/20261123_markaz_academic_term_fees.sql');
$api=file_get_contents(__DIR__.'/../api/finance/term-fees.php');
$payments=file_get_contents(__DIR__.'/../api/finance/index.php');
$html=file_get_contents(__DIR__.'/../public/finance.html');
$dashboardHtml=file_get_contents(__DIR__.'/../public/dashboard.html');
$dashboardJs=file_get_contents(__DIR__.'/../public/js/dashboard.js');
$js=file_get_contents(__DIR__.'/../public/js/finance.js');
termFeeCheck(str_contains($migration,'UNIQUE KEY uq_term_fee_class (session_id,term,class_id)'),'each class has one configurable amount per session and term');
termFeeCheck(str_contains($migration,'UNIQUE KEY uq_term_student_invoice (student_id,session_id,term)'),'term invoice generation is idempotent per student and term');
termFeeCheck(str_contains($migration,'finance_student_fee_adjustments'),'individual scholarships and negotiated fees have separate audited records');
termFeeCheck(str_contains($migration,'ENUM(\'fixed_fee\',\'discount_amount\',\'discount_percent\',\'full_scholarship\')'),'fixed, percentage and full scholarships are supported');
termFeeCheck(str_contains($api,'FROM students WHERE is_active=1 AND class_id IS NOT NULL'),'billing targets existing active conventional students without re-registration');
termFeeCheck(str_contains($api,'INSERT IGNORE INTO finance_term_fee_invoices'),'re-running a billing batch does not overwrite issued invoices');
termFeeCheck(str_contains($api,'$type===\'full_scholarship\''),'full scholarships reduce only the selected student invoice to zero');
termFeeCheck(str_contains($payments,'finance_term_fee_payments'),'confirmed receipts allocate to academic-term invoices');
termFeeCheck(str_contains($html,'id="termClassFeeForm"')&&str_contains($html,'id="studentFeeAdjustmentForm"'),'finance screen exposes class fee and individual adjustment forms');
termFeeCheck(str_contains($html,'id="termFeeReport"')&&str_contains($html,'id="termFeeInvoices"'),'finance screen exposes class totals and individual term invoices');
termFeeCheck(str_contains($js,'loadTermFeeReport')&&str_contains($js,'generate_term_invoices'),'term fee interface loads reports and generates invoices');
termFeeCheck(str_contains($migration,"status ENUM('pending','approved','rejected')")&&str_contains($migration,'approved_by BIGINT UNSIGNED NULL'),'scholarship adjustments require a separate approval state and nullable approver');
termFeeCheck(str_contains($api,'different authorised staff member must approve')&&str_contains($api,"status='approved' AND effective_from<=CURDATE()"),'the creator cannot approve their own adjustment and only approved adjustments affect invoices');
termFeeCheck(str_contains($api,'SELECT id FROM finance_academic_sessions WHERE session_name=? LIMIT 1'),'session audit uses the persisted session ID after insert or update');
termFeeCheck(str_contains($html,'id="termAdjustmentQueue"')&&str_contains($html,'id="reloadTermAdjustments"'),'Finance UI exposes a separate scholarship approval queue');
termFeeCheck(str_contains($js,'renderClassOptions')&&str_contains($js,'Loading classes')&&str_contains($js,'No active classes found'),'class dropdowns expose loading, available options and empty states');
termFeeCheck(str_contains($js,'data-approve-term-adjustment')&&str_contains($js,'approve_student_adjustment'),'the approval queue submits a distinct independent approval action');
termFeeCheck(str_contains($js,'financeStaffLoaded')&&str_contains($js,'Teachers could not load'),'teacher dropdowns handle delayed or failed staff loading');
termFeeCheck(str_contains($html,'href="css/dashboard-2026.css')&&str_contains($html,'href="css/dashboard-polish-2026.css'),'Finance reuses the established MARKAZ dashboard shell and visual system');
termFeeCheck(!str_contains(file_get_contents(__DIR__.'/../public/css/finance.css'),'Finance shell: remove the unintended white seam'),'Finance does not introduce a competing custom sidebar/topbar geometry override');
termFeeCheck(!str_contains($js,'DEMO_')&&!str_contains($js,'demoResponse')&&!str_contains($js,'Teacher Demo'),'finance JavaScript contains no fabricated demo students, receipts, expenses or payroll');
termFeeCheck(!str_contains($html,'finance-demo-shortcut')&&!str_contains($html,'?demo='),'finance page exposes no demo shortcut or demo URL');
termFeeCheck(!str_contains(file_get_contents(__DIR__.'/../public/css/finance.css'),'finance-demo-'),'finance stylesheet contains no demo-only styling');

termFeeCheck(str_contains($dashboardHtml,'href="./finance.html" class="nav-link" data-role-access="management_school_fees"'),'the Admin Dashboard School Fees navigation opens the integrated Finance workspace');
termFeeCheck(str_contains($html,'<nav class="sidebar-nav finance-sidebar-nav" aria-label="School Fees navigation">')&&!str_contains($html,'id="logoutButton"')&&!str_contains($html,'class="sidebar-bottom"'),'School Fees sidebar has no duplicate profile or logout footer');
termFeeCheck(str_contains($html,'id="welcomeGreeting"')&&str_contains($html,'id="adminName"')&&str_contains($html,'class="welcome-card"'),'Finance Overview reuses the shared dashboard greeting and welcome card');
termFeeCheck(str_contains(file_get_contents(__DIR__.'/../public/css/finance.css'),'overflow-y:auto')&&str_contains(file_get_contents(__DIR__.'/../public/css/finance.css'),'min-height:0'),'Finance sidebar navigation can scroll fully to its final item');
termFeeCheck(str_contains(file_get_contents(__DIR__.'/../public/css/finance.css'),'flex:1 0 auto')&&str_contains(file_get_contents(__DIR__.'/../public/css/finance.css'),'margin-top:auto'),'Finance content and footer use a bottom-anchored flex layout');
termFeeCheck(str_contains($html,'fa-solid fa-')&&str_contains($html,'fonts.googleapis.com/css2?family=Manrope'),'Finance uses consistent icons and the shared Manrope typography');
termFeeCheck(str_contains($html,'href="./dashboard.html"')&&str_contains($html,'data-tab="overview"')&&str_contains($html,'data-tab="fees"')&&str_contains($html,'data-tab="payments"')&&str_contains($html,'data-tab="expenses"')&&str_contains($html,'data-tab="payroll"')&&str_contains($html,'data-tab="reports"'),'the dedicated sidebar provides Main Dashboard and all six finance workspaces');
termFeeCheck(!str_contains($html,'class="finance-tabs"')&&str_contains($html,'class="finance-nav-link nav-link nav"'),'finance navigation is in the dedicated sidebar, not a horizontal tab row');
termFeeCheck(str_contains($html,'id="menuButton"')&&str_contains($html,'id="pageTitle"')&&str_contains($html,'class="topbar"'),'Finance retains the shared topbar and mobile menu control');
termFeeCheck(str_contains($html,'body class="finance-workspace-page"')&&str_contains(file_get_contents(__DIR__.'/../public/css/finance.css'),'finance-workspace-page .topbar'),'the Finance shell explicitly removes the sidebar/header seam');
termFeeCheck(str_contains($html,'js/dashboard.js?v=20261120-staff-messaging-role4'),'Finance loads the shared dashboard role, profile, sidebar and logout controller');
termFeeCheck(str_contains($dashboardJs,'./api/auth/me.php')&&str_contains($dashboardJs,'management_school_fees'),'shared Finance navigation is governed by the same staff-role permissions as School Fees');
termFeeCheck(!str_contains($js,'financeSidebar')&&!str_contains($js,'closeFinanceSidebar'),'Finance does not register a competing sidebar controller');
termFeeCheck(str_contains($html,'<footer class="dashboard-footer">')&&str_contains($html,'© 2026 MARKAZ. All Rights Reserved.'),'Finance retains the exact shared dashboard footer');
termFeeCheck(!str_contains(file_get_contents(__DIR__.'/../public/css/finance.css'),'!important'),'Finance CSS does not override the shared dashboard shell geometry');
termFeeCheck(!str_contains(file_get_contents(__DIR__.'/../public/css/finance.css'),'.app{display:block'),'Finance preserves the dashboard grid shell on mobile');
echo "MARKAZ academic term fee regression checks passed.\n";
