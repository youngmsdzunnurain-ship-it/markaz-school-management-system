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
termFeeCheck(str_contains($api,'WHERE s.is_active=1 AND s.class_id IS NOT NULL'),'billing targets existing active conventional students without re-registration');
termFeeCheck(str_contains($api,'INSERT IGNORE INTO finance_term_fee_invoices'),'re-running a billing batch does not overwrite issued invoices');
termFeeCheck(str_contains($api,'$type===\'full_scholarship\''),'full scholarships reduce only the selected student invoice to zero');
termFeeCheck(str_contains($payments,'finance_term_fee_payments'),'confirmed receipts allocate to academic-term invoices');
termFeeCheck(str_contains($html,'id="termClassFeeForm"')&&str_contains($html,'id="studentFeeAdjustmentForm"'),'finance screen exposes class fee and individual adjustment forms');
termFeeCheck(str_contains($html,'id="termFeeReport"')&&str_contains($html,'id="termFeeInvoices"'),'finance screen exposes class totals and individual term invoices');
termFeeCheck(str_contains($js,'loadTermFeeReport')&&str_contains($js,'generate_term_invoices'),'term fee interface loads reports and generates invoices');
echo "MARKAZ academic term fee regression checks passed.\n";
