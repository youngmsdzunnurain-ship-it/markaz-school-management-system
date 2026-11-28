<?php
declare(strict_types=1);
function financeSimpleCheck(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}echo "PASS: {$message}\n";}
$root=dirname(__DIR__);$js=file_get_contents($root.'/public/js/finance.js');$html=file_get_contents($root.'/public/finance.html');$api=file_get_contents($root.'/api/finance/index.php');$css=file_get_contents($root.'/public/css/finance.css');
financeSimpleCheck(substr_count($js,"const form=e.currentTarget")===3 && substr_count($js,"form.reset()")>=3,'async Finance handlers retain form references across awaited requests');
financeSimpleCheck(substr_count($js,"form.dataset.submitting==='1'")===3,'payment, income and expense submissions block duplicate taps');
financeSimpleCheck(str_contains($api,"if($e instanceof DomainException)financeJson(['success'=>false,'message'=>$e->getMessage()],422)"),'payment allocation validation returns a clear client error');
financeSimpleCheck(str_contains($html,'What was purchased or repaired?')&&!str_contains($html,'name="vendor_name"'),'expense entry asks what was purchased without requiring vendor identity');
financeSimpleCheck(str_contains($html,'Report / extra details')&&substr_count($html,'(optional)')>=2,'income and expense reports are optional');
financeSimpleCheck(!str_contains($html,'name="payer_name"')&&!str_contains($html,'name="reference"'),'simplified income and expense forms omit payer and reference fields');
financeSimpleCheck(str_contains($api,"if($category===''||!financeValidDate($date)"),'API accepts optional income and expense descriptions while validating core fields');
financeSimpleCheck(str_contains($css,'.finance-simple-form')&&str_contains($css,'.finance-entry-grid'),'income and expense forms use compact responsive styling');
echo "MARKAZ Finance entry regression checks passed.\n";
