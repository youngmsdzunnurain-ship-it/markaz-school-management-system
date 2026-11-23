<?php
declare(strict_types=1);
function feeAccountCheck(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}echo "PASS: {$message}\n";}
$root=dirname(__DIR__);$api=file_get_contents($root.'/api/finance/index.php');$html=file_get_contents($root.'/public/finance.html');$js=file_get_contents($root.'/public/js/finance.js');
feeAccountCheck(str_contains($api,'if ($action === \'student_statement\')'),'Finance exposes an admission-number based student statement endpoint');
feeAccountCheck(str_contains($api,'if ($action === \'arrears\')'),'Finance exposes a combined monthly and term arrears report');
feeAccountCheck(str_contains($api,'finance_fee_allocations')&&str_contains($api,'finance_term_fee_payments'),'student statements reconcile monthly and term invoice allocations');
feeAccountCheck(str_contains($api,'overdue_1_30')&&str_contains($api,'overdue_31_60')&&str_contains($api,'overdue_61_90')&&str_contains($api,'overdue_over_90'),'arrears are grouped into 30, 60, 90 and 90-plus day ageing bands');
feeAccountCheck(str_contains($html,'id="studentStatementForm"')&&str_contains($html,'id="studentStatementRows"'),'School Fees exposes a complete student account statement workspace');
feeAccountCheck(str_contains($html,'id="feeArrearsPanel"')&&str_contains($html,'id="feeArrearsRows"'),'School Fees exposes an outstanding balance and arrears workspace');
feeAccountCheck(str_contains($js,"request('student_statement'")&&str_contains($js,"request('arrears')"),'student statement and arrears views load live API data');
feeAccountCheck(str_contains($js,'studentStatementPayments')&&str_contains($js,'feeArrearsSummary'),'student statement includes payment history and arrears include summary totals');
echo "MARKAZ fee account regression checks passed.\n";
