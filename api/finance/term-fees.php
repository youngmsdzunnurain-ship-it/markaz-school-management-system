<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/finance.php';
financeRequireAccess();
$pdo=db();
try {
 $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
 if($method==='GET'){
  $action=(string)($_GET['action']??'setup');
  if($action==='setup'){
   $sessions=$pdo->query("SELECT id,session_name,starts_on,ends_on,is_current FROM finance_academic_sessions ORDER BY starts_on DESC")->fetchAll(PDO::FETCH_ASSOC);
   $classes=$pdo->query("SELECT id,name FROM classes WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
   financeJson(['success'=>true,'sessions'=>$sessions,'classes'=>$classes]);
  }
  if($action==='report'){
   $session=(int)($_GET['session_id']??0);$term=(string)($_GET['term']??'');
   if($session<1||!in_array($term,['first','second','third'],true))financeJson(['success'=>false,'message'=>'Choose a valid session and term.'],422);
   $st=$pdo->prepare("SELECT c.id class_id,c.name class_name,COUNT(i.id) student_count,COALESCE(SUM(i.amount_due),0) billed,COALESCE(SUM(i.discount_amount),0) discounts,COALESCE(SUM(p.paid),0) paid,COALESCE(SUM(i.amount_due-p.paid),0) outstanding FROM finance_term_fee_invoices i JOIN classes c ON c.id=i.class_id LEFT JOIN (SELECT term_invoice_id,SUM(amount) paid FROM finance_term_fee_payments GROUP BY term_invoice_id) p ON p.term_invoice_id=i.id WHERE i.session_id=? AND i.term=? AND i.status<>'void' GROUP BY c.id,c.name ORDER BY c.name");
   $st->execute([$session,$term]);$rows=$st->fetchAll(PDO::FETCH_ASSOC);
   financeJson(['success'=>true,'classes'=>$rows,'totals'=>['students'=>array_sum(array_map(fn($r)=>(int)$r['student_count'],$rows)),'billed'=>array_sum(array_map(fn($r)=>(float)$r['billed'],$rows)),'paid'=>array_sum(array_map(fn($r)=>(float)$r['paid'],$rows)),'outstanding'=>array_sum(array_map(fn($r)=>(float)$r['outstanding'],$rows))]]);
  }
  if($action==='invoices'){
   $session=(int)($_GET['session_id']??0);$term=(string)($_GET['term']??'');
   $st=$pdo->prepare("SELECT i.*,s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name,c.name class_name,COALESCE(p.paid,0) paid, i.amount_due-COALESCE(p.paid,0) balance FROM finance_term_fee_invoices i JOIN students s ON s.id=i.student_id JOIN classes c ON c.id=i.class_id LEFT JOIN (SELECT term_invoice_id,SUM(amount) paid FROM finance_term_fee_payments GROUP BY term_invoice_id) p ON p.term_invoice_id=i.id WHERE i.session_id=? AND i.term=? ORDER BY c.name,s.last_name,s.first_name");
   $st->execute([$session,$term]);financeJson(['success'=>true,'invoices'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
  }
  financeJson(['success'=>false,'message'=>'Unknown term-fee action.'],404);
 }
 if($method!=='POST')financeJson(['success'=>false,'message'=>'Method not allowed.'],405);
 financeRequireSafePost();$in=financeInput();$action=(string)($in['action']??'');
 if($action==='save_session'){
  $name=trim((string)($in['session_name']??''));$start=(string)($in['starts_on']??'');$end=(string)($in['ends_on']??'');
  if(!preg_match('/^20\d{2}\/20\d{2}$/',$name)||!financeValidDate($start)||!financeValidDate($end)||$end<=$start)financeJson(['success'=>false,'message'=>'Enter a session like 2026/2027 and a valid date range.'],422);
  $pdo->beginTransaction();$pdo->prepare('UPDATE finance_academic_sessions SET is_current=0')->execute();
  $st=$pdo->prepare('INSERT INTO finance_academic_sessions(session_name,starts_on,ends_on,is_current,created_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE starts_on=VALUES(starts_on),ends_on=VALUES(ends_on),is_current=VALUES(is_current)');
  $st->execute([$name,$start,$end,1,(int)$_SESSION['admin_id']]);$pdo->commit();
  financeAudit($pdo,'academic_session_saved','academic_session',(int)$pdo->lastInsertId(),['session'=>$name]);
  financeJson(['success'=>true,'message'=>'Academic session saved and set as current.']);
 }
 if($action==='save_class_fee'){
  $session=(int)($in['session_id']??0);$class=(int)($in['class_id']??0);$term=(string)($in['term']??'');$amount=financeMoney($in['amount']??'');$due=(string)($in['due_on']??'');
  if($session<1||$class<1||!in_array($term,['first','second','third'],true)||!financeValidDate($due))financeJson(['success'=>false,'message'=>'Complete the session, term, class, amount and due date.'],422);
  $st=$pdo->prepare("INSERT INTO finance_term_fee_schedules(session_id,term,class_id,amount,due_on,created_by) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount),due_on=VALUES(due_on),is_active=1");
  $st->execute([$session,$term,$class,$amount,$due,(int)$_SESSION['admin_id']]);financeAudit($pdo,'term_class_fee_saved','term_fee_schedule',null,['session_id'=>$session,'term'=>$term,'class_id'=>$class,'amount'=>$amount]);
  financeJson(['success'=>true,'message'=>'Class fee saved for this session and term. Other classes and students are unchanged.']);
 }
 if($action==='save_student_adjustment'){
  $student=(int)($in['student_id']??0);$session=(int)($in['session_id']??0);$term=(string)($in['term']??'');$type=(string)($in['adjustment_type']??'');$value=financeMoney($in['value']??'0',true);$reason=trim((string)($in['reason']??''));
  $allowed=['fixed_fee','discount_amount','discount_percent','full_scholarship'];
  if($student<1||$session<1||!in_array($term,['first','second','third'],true)||!in_array($type,$allowed,true)||$reason==='')financeJson(['success'=>false,'message'=>'Complete the student adjustment and explain the reason.'],422);
  if($type==='discount_percent'&&(float)$value>100)financeJson(['success'=>false,'message'=>'Percentage discount cannot exceed 100%.'],422);
  $st=$pdo->prepare('SELECT id FROM students WHERE id=? AND is_active=1');$st->execute([$student]);if(!$st->fetchColumn())financeJson(['success'=>false,'message'=>'Active student not found.'],404);
  $pdo->beginTransaction();$st=$pdo->prepare('INSERT INTO finance_student_fee_adjustments(student_id,session_id,term,adjustment_type,value,reason,approved_by,created_by,effective_from) VALUES(?,?,?,?,?,?,?,?,CURDATE())');$st->execute([$student,$session,$term,$type,$value,$reason,(int)$_SESSION['admin_id'],(int)$_SESSION['admin_id']]);$pdo->commit();
  financeAudit($pdo,'student_fee_adjustment_created','student_fee_adjustment',(int)$pdo->lastInsertId(),['student_id'=>$student,'type'=>$type,'value'=>$value]);
  financeJson(['success'=>true,'message'=>'Individual fee adjustment saved. It affects only this student.']);
 }
 if($action==='generate_term_invoices'){
  $session=(int)($in['session_id']??0);$term=(string)($in['term']??'');
  if($session<1||!in_array($term,['first','second','third'],true))financeJson(['success'=>false,'message'=>'Choose a valid session and term.'],422);
  $pdo->beginTransaction();$created=0;
  try{
   $st=$pdo->prepare('SELECT id,session_name,starts_on FROM finance_academic_sessions WHERE id=?');$st->execute([$session]);$academic=$st->fetch(PDO::FETCH_ASSOC);if(!$academic)financeJson(['success'=>false,'message'=>'Academic session not found.'],404);
   $fees=$pdo->prepare('SELECT * FROM finance_term_fee_schedules WHERE session_id=? AND term=? AND is_active=1');$fees->execute([$session,$term]);$feeMap=[];foreach($fees->fetchAll(PDO::FETCH_ASSOC) as $f)$feeMap[(int)$f['class_id']]=$f;
   $students=$pdo->query('SELECT id,class_id FROM students WHERE is_active=1 AND class_id IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
   $adj=$pdo->prepare('SELECT * FROM finance_student_fee_adjustments WHERE student_id=? AND session_id=? AND term=? ORDER BY id DESC LIMIT 1');
   $insert=$pdo->prepare('INSERT IGNORE INTO finance_term_fee_invoices(invoice_number,student_id,session_id,term,class_id,base_amount,discount_amount,amount_due,due_on,generated_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
   foreach($students as $student){$fee=$feeMap[(int)$student['class_id']]??null;if(!$fee)continue;$base=(float)$fee['amount'];$discount=0.0;$adj->execute([(int)$student['id'],$session,$term]);$a=$adj->fetch(PDO::FETCH_ASSOC);
    if($a){$type=$a['adjustment_type'];$value=(float)$a['value'];if($type==='fixed_fee')$base=$value;elseif($type==='discount_amount')$discount=min($base,$value);elseif($type==='discount_percent')$discount=round($base*$value/100,2);elseif($type==='full_scholarship')$discount=$base;}
    $due=max(0,round($base-$discount,2));$insert->execute([financeNextNumber('TERM'),(int)$student['id'],$session,$term,(int)$student['class_id'],number_format($base,2,'.',''),number_format($discount,2,'.',''),number_format($due,2,'.',''),$fee['due_on'],(int)$_SESSION['admin_id']]);$created+=$insert->rowCount();
   }
   financeAudit($pdo,'term_fee_invoices_generated','term_fee_invoice_batch',null,['session_id'=>$session,'term'=>$term,'created'=>$created]);$pdo->commit();
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  financeJson(['success'=>true,'created'=>$created,'message'=>"Generated {$created} term invoices. Previously generated invoices were not changed."]);
 }
 financeJson(['success'=>false,'message'=>'Unknown term-fee action.'],404);
} catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('MARKAZ term fees: '.$e->getMessage());financeJson(['success'=>false,'message'=>'Term-fee operation failed. Check server logs.'],500);}
