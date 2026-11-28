<?php
declare(strict_types=1);
function financeSimpleCheck(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}echo "PASS: {$message}\n";}
$root=dirname(__DIR__);$js=file_get_contents($root.'/public/js/finance.js');$html=file_get_contents($root.'/public/finance.html');$api=file_get_contents($root.'/api/finance/index.php');$css=file_get_contents($root.'/public/css/finance.css');
$expenseStart=strpos($html,'id="tab-expenses"');$payrollStart=strpos($html,'id="tab-payroll"',$expenseStart===false?0:$expenseStart);$entryHtml=$expenseStart===false?'':substr($html,$expenseStart,$payrollStart===false?null:$payrollStart-$expenseStart);
financeSimpleCheck(substr_count($js,"const form=e.currentTarget")===3 && substr_count($js,"form.reset()")>=3,'async Finance handlers retain form references across awaited requests');
financeSimpleCheck(substr_count($js,"form.dataset.submitting==='1'")===3,'payment, income and expense submissions block duplicate taps');
financeSimpleCheck(str_contains($api,'if($e instanceof DomainException)financeJson([\'success\'=>false,\'message\'=>$e->getMessage()],422)'),'payment allocation validation returns a clear client error');
financeSimpleCheck(str_contains($entryHtml,'What was purchased or repaired?')&&!str_contains($entryHtml,'name="vendor_name"'),'expense entry asks what was purchased without requiring vendor identity');
financeSimpleCheck(str_contains($entryHtml,'Report / extra details')&&substr_count($entryHtml,'(optional)')>=2,'income and expense reports are optional');
financeSimpleCheck(!str_contains($entryHtml,'name="payer_name"')&&!str_contains($entryHtml,'name="reference"'),'simplified income and expense forms omit payer and reference fields');
financeSimpleCheck(substr_count($api,'if($category===\'\'||!financeValidDate($date)')>=2,'API accepts optional income and expense descriptions while validating core fields');
financeSimpleCheck(str_contains($css,'.finance-simple-form')&&str_contains($css,'.finance-entry-grid'),'income and expense forms use compact responsive styling');
echo "MARKAZ Finance entry regression checks passed.\n";

$financeTabStart=strpos($html,'id="tab-expenses"');$financeTabEnd=strpos($html,'id="tab-payroll"',$financeTabStart===false?0:$financeTabStart);$financeTab=$financeTabStart===false?'':substr($html,$financeTabStart,$financeTabEnd===false?null:$financeTabEnd-$financeTabStart);
financeSimpleCheck(strpos($financeTab,'id="incomeForm"')<strpos($financeTab,'id="expenseForm"'),'money received column appears before money spent column');
financeSimpleCheck(str_contains($financeTab,'id="incomeQueueHeading">Awaiting verification')&&str_contains($financeTab,'id="expenseQueueHeading">Awaiting approval'),'review queues use clear, action-specific labels');
financeSimpleCheck(str_contains($financeTab,'class="two-col finance-register-columns"')&&str_contains($financeTab,'Other income register')&&str_contains($financeTab,'Expense register'),'income and expense registers are paired beneath the entry columns');
financeSimpleCheck(substr_count($financeTab,'id="incomeForm"')===1&&substr_count($financeTab,'id="expenseForm"')===1&&substr_count($financeTab,'id="incomeQueue"')===1&&substr_count($financeTab,'id="expenseQueue"')===1,'existing form and review queue hooks remain unique');
financeSimpleCheck(str_contains($css,'.finance-money-columns')&&str_contains($css,'@media(max-width:760px)'),'paired finance layout includes responsive single-column mobile styling');

financeSimpleCheck(str_contains($css,'@media(min-width:761px)')&&str_contains($css,'.finance-money-columns,.finance-register-columns{grid-template-columns:repeat(2,minmax(0,1fr))}'),'income and expense workspaces stay side by side above the mobile breakpoint');
financeSimpleCheck(str_contains($css,'body.finance-workspace-page .topbar{background:transparent')&&str_contains($css,'body.finance-workspace-page .topbar .menu-button{background:'),'mobile Finance header keeps its title while limiting the green panel to the menu button');
