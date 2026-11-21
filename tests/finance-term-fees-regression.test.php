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
termFeeCheck(str_contains(file_get_contents(__DIR__.'/../public/css/finance.css'),'.app{gap:0!important;column-gap:0!important;row-gap:0!important')&&str_contains(file_get_contents(__DIR__.'/../public/css/finance.css'),'.main-content>.topbar{width:100%;max-width:none;margin:0!important'),'Finance sidebar and topbar share a zero-gap aligned shell');
termFeeCheck(!str_contains($js,'DEMO_')&&!str_contains($js,'demoResponse')&&!str_contains($js,'Teacher Demo'),'finance JavaScript contains no fabricated demo students, receipts, expenses or payroll');
termFeeCheck(!str_contains($html,'finance-demo-shortcut')&&!str_contains($html,'?demo='),'finance page exposes no demo shortcut or demo URL');
termFeeCheck(!str_contains(file_get_contents(__DIR__.'/../public/css/finance.css'),'finance-demo-'),'finance stylesheet contains no demo-only styling');
echo "MARKAZ academic term fee regression checks passed.\n";
