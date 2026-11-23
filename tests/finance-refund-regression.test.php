<?php
declare(strict_types=1);
function refundCheck(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}echo "PASS: {$message}\n";}
$root=dirname(__DIR__);$migration=file_get_contents($root.'/database/migrations/20261126_markaz_fee_refunds.sql');$api=file_get_contents($root.'/api/finance/index.php');$termApi=file_get_contents($root.'/api/finance/term-fees.php');$html=file_get_contents($root.'/public/finance.html');$js=file_get_contents($root.'/public/js/finance.js');$installer=file_get_contents($root.'/scripts/install-finance.sh');
refundCheck(str_contains($migration,'CREATE TABLE finance_fee_refunds')&&str_contains($migration,'CREATE TABLE finance_fee_refund_allocations'),'refund requests and reversible invoice allocations have dedicated audit tables');
refundCheck(str_contains($api,"if($action==='request_refund')")&&str_contains($api,"if($action==='review_refund')"),'Finance exposes separate refund request and review actions');
refundCheck(str_contains($api,'status IN (\'pending\',\'approved\')'),'pending refund requests reserve the remaining refundable payment amount');
refundCheck(str_contains($api,'recorded_by')&&str_contains($api,'cannot approve or reject it'),'refund maker cannot review their own request');
refundCheck(str_contains($api,'finance_fee_refund_allocations')&&str_contains($api,'financePostJournal'),'approved refunds restore invoice balances and post a balanced ledger journal');
refundCheck(str_contains($api,'overdue_1_30')&&str_contains($api,'r.refunded'),'student statement and arrears reports calculate net paid balances after refunds');
refundCheck(str_contains($termApi,'r.refunded'),'term class reports and invoices include approved refunds');
refundCheck(str_contains($html,'id="refundForm"')&&str_contains($html,'id="refundQueue"'),'Finance UI provides refund request and maker-checker approval workspaces');
refundCheck(str_contains($js,"request('refunds'")&&str_contains($js,"action='request_refund'")&&str_contains($js,'data-review-refund'),'refund requests and approvals are wired to live Finance API actions');
refundCheck(str_contains($installer,'20261126_markaz_fee_refunds.sql'),'guarded Finance installer applies and verifies refund tables');
echo "MARKAZ fee refund regression checks passed.\n";
