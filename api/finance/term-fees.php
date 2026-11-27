<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/finance.php';
financeRequireAccess();
$pdo = db();

function markazFeeSection(string $className): ?string
{
    $name = strtolower(trim(str_replace(['-', '_'], ' ', $className)));
    $name = preg_replace('/\s+/', ' ', $name) ?? $name;
    if (str_contains($name, 'kindergarten') || str_contains($name, 'pre nursery') || preg_match('/\bnursery\b/', $name)) return 'nursery';
    if (preg_match('/\bbasic\s*(?:[1-5]|one|two|three|four|five)\b/', $name) || preg_match('/\bprimary\b/', $name)) return 'primary';
    if (preg_match('/\bjss\s*[1-3]\b/', $name) || str_contains($name, 'junior secondary')) return 'junior_secondary';
    if (preg_match('/\b(?:ss|sss)\s*[1-3]\b/', $name) || str_contains($name, 'senior secondary')) return 'senior_secondary';
    return null;
}

function markazTermSlug(string $termName): string
{
    $name = strtolower(trim($termName));
    foreach (['first','second','third'] as $term) {
        if (str_contains($name, $term)) return $term;
    }
    throw new RuntimeException('The current MARKAZ academic term is not recognised.');
}

function markazCurrentAcademicPeriod(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT ap.id period_id,ap.academic_session_id,ap.term_id,ap.starts_on,ap.ends_on,
                ap.is_finalized,ap.is_current,s.name session_name,t.name term_name
         FROM academic_periods ap
         INNER JOIN academic_sessions s ON s.id=ap.academic_session_id
         INNER JOIN terms t ON t.id=ap.term_id
         WHERE s.is_current=1 AND ap.is_current=1
         ORDER BY ap.id DESC LIMIT 1"
    );
    $period = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$period) financeJson(['success'=>false,'message'=>'Configure the current academic session and term in Settings first.'],409);
    $period['term'] = markazTermSlug((string)$period['term_name']);
    if (empty($period['starts_on']) || empty($period['ends_on'])) {
        financeJson(['success'=>false,'code'=>'TERM_DATES_REQUIRED','message'=>'Set the current term start and end dates in Settings before configuring or generating student fees.'],409);
    }
    if ((string)$period['ends_on'] < (string)$period['starts_on']) {
        financeJson(['success'=>false,'message'=>'The current academic term has an invalid date range. Correct it in Settings.'],409);
    }
    // Keep the legacy Finance session reference for existing adjustment and
    // invoice relationships; the academic_period_id remains the source of truth.
    $mirror = $pdo->prepare(
        'INSERT INTO finance_academic_sessions(session_name,starts_on,ends_on,is_current,created_by)
         VALUES(?,?,?,?,?)
         ON DUPLICATE KEY UPDATE is_current=VALUES(is_current)'
    );
    $mirror->execute([
        (string)$period['session_name'],(string)$period['starts_on'],(string)$period['ends_on'],1,
        (int)($_SESSION['admin_id']??0)
    ]);
    $find = $pdo->prepare('SELECT id FROM finance_academic_sessions WHERE session_name=? LIMIT 1');
    $find->execute([(string)$period['session_name']]);
    $period['finance_session_id'] = (int)$find->fetchColumn();
    return $period;
}

function markazFeeDiscountCents(int $baseCents, string $type, string $value): array
{
    // A later reduction to the published schedule must not make an older
    // negotiated final fee exceed the new normal rate.
    if ($type === 'fixed_fee') {
        $value = financeFromCents(min($baseCents, financeToCents($value)));
    }
    return financeCalculateFeeAmountsCents($baseCents, $type, $value);
}

function markazSyncStudentTermFees(PDO $pdo, array $period, int $financeSessionId, string $term, int $actor): array
{
    $periodId=(int)$period['period_id'];
    $ratesStmt=$pdo->prepare('SELECT * FROM finance_section_fee_schedules WHERE academic_period_id=? AND is_active=1');
    $ratesStmt->execute([$periodId]);
    $rateMap=[];
    foreach($ratesStmt->fetchAll(PDO::FETCH_ASSOC) as $rate)$rateMap[$rate['section'].'|'.$rate['student_type'].'|'.$rate['intake_type']]=$rate;
    if(count($rateMap)!==16)throw new DomainException('All 16 fee rates must be saved before student accounts can be synchronised.');

    $overrideQuery=$pdo->prepare('SELECT student_id,intake_type FROM finance_student_fee_intake_overrides WHERE academic_period_id=?');
    $overrideQuery->execute([$periodId]);
    $intakeOverrides=[];
    foreach($overrideQuery->fetchAll(PDO::FETCH_ASSOC) as $override)$intakeOverrides[(int)$override['student_id']]=(string)$override['intake_type'];

    $students=$pdo->query("SELECT s.id,s.class_id,s.student_type,s.admission_date,c.name class_name
                           FROM students s LEFT JOIN classes c ON c.id=s.class_id
                           WHERE s.is_active=1 ORDER BY s.id")->fetchAll(PDO::FETCH_ASSOC);
    $adjustmentStmt=$pdo->prepare("SELECT adjustment_type,value FROM finance_student_fee_adjustments
        WHERE student_id=? AND session_id=? AND term=? AND status='approved'
          AND effective_from<=CURDATE() AND (effective_until IS NULL OR effective_until>=CURDATE())
        ORDER BY approved_at DESC,id DESC LIMIT 1");
    $findInvoice=$pdo->prepare("SELECT id FROM finance_term_fee_invoices
        WHERE student_id=? AND (academic_period_id=? OR (academic_period_id IS NULL AND session_id=? AND term=?))
          AND status<>'void' LIMIT 1 FOR UPDATE");
    $paidStmt=$pdo->prepare("SELECT COALESCE(SUM(p.amount),0)-
        COALESCE((SELECT SUM(x.amount) FROM finance_fee_refund_allocations x
          JOIN finance_fee_refunds rf ON rf.id=x.refund_id
          WHERE x.allocation_type='term' AND x.source_allocation_id=p.id AND rf.status='approved'),0)
        FROM finance_term_fee_payments p WHERE p.term_invoice_id=?");
    $insert=$pdo->prepare("INSERT INTO finance_term_fee_invoices
        (invoice_number,student_id,session_id,academic_period_id,term,class_id,section,student_type,intake_type,fee_schedule_id,base_amount,discount_amount,amount_due,due_on,generated_by)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $update=$pdo->prepare("UPDATE finance_term_fee_invoices SET class_id=?,section=?,student_type=?,intake_type=?,fee_schedule_id=?,
        base_amount=?,discount_amount=?,amount_due=?,due_on=?,status=? WHERE id=?");

    $created=0;$updated=0;$skipped=0;
    foreach($students as $student){
        $section=markazFeeSection((string)($student['class_name']??''));
        if($section===null || empty($student['class_id'])){$skipped++;continue;}
        $type=in_array((string)$student['student_type'],['Day Student','Boarding'],true)?(string)$student['student_type']:'Day Student';
        $admission=(string)($student['admission_date']??'');
        $intake=$intakeOverrides[(int)$student['id']]??(($admission!=='' && $admission>=(string)$period['starts_on'] && $admission<=(string)$period['ends_on'])?'new_intake':'returning');
        $rate=$rateMap[$section.'|'.$type.'|'.$intake]??null;
        if(!$rate){$skipped++;continue;}
        $base=financeToCents((string)$rate['amount']);$discount=0;$due=$base;
        $adjustmentStmt->execute([(int)$student['id'],$financeSessionId,$term]);
        $adjustment=$adjustmentStmt->fetch(PDO::FETCH_ASSOC);
        if($adjustment)[$base,$discount,$due]=markazFeeDiscountCents($base,(string)$adjustment['adjustment_type'],(string)$adjustment['value']);
        $findInvoice->execute([(int)$student['id'],$periodId,$financeSessionId,$term]);
        $invoiceId=(int)($findInvoice->fetchColumn()?:0);
        if($invoiceId>0){
            $paidStmt->execute([$invoiceId]);$paid=max(0,financeToCents((string)$paidStmt->fetchColumn()));
            $status=($due===0||$paid>=$due)?'paid':($paid>0?'partial':'unpaid');
            $update->execute([(int)$student['class_id'],$section,$type,$intake,(int)$rate['id'],financeFromCents($base),
                financeFromCents($discount),financeFromCents($due),(string)$rate['due_on'],$status,$invoiceId]);
            $updated+=$update->rowCount()>0?1:0;
        }else{
            $insert->execute([financeNextNumber('TERM'),(int)$student['id'],$financeSessionId,$periodId,$term,(int)$student['class_id'],
                $section,$type,$intake,(int)$rate['id'],financeFromCents($base),financeFromCents($discount),financeFromCents($due),
                (string)$rate['due_on'],$actor]);
            $created+=$insert->rowCount();
        }
    }
    return ['created'=>$created,'updated'=>$updated,'skipped'=>$skipped];
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $action = (string)($_GET['action'] ?? 'setup');
        $period = markazCurrentAcademicPeriod($pdo);
        $periodId = (int)$period['period_id'];
        $financeSessionId = (int)$period['finance_session_id'];
        $term = (string)$period['term'];

        if ($action === 'setup') {
            $classes = $pdo->query("SELECT id,name FROM classes WHERE is_active=1 ORDER BY sort_order,name")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($classes as &$class) $class['section'] = markazFeeSection((string)$class['name']);
            unset($class);
            $rates = $pdo->prepare('SELECT section,student_type,intake_type,amount,due_on FROM finance_section_fee_schedules WHERE academic_period_id=? AND is_active=1');
            $rates->execute([$periodId]);
            $pending = $pdo->prepare(
                "SELECT x.id,x.student_id,x.session_id,x.term,x.adjustment_type,x.value,x.reason,x.created_by,x.created_at,
                        s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name
                 FROM finance_student_fee_adjustments x JOIN students s ON s.id=x.student_id
                 WHERE x.session_id=? AND x.term=? AND x.status='pending' ORDER BY x.created_at"
            );
            $pending->execute([$financeSessionId,$term]);
            financeJson(['success'=>true,'period'=>$period,'classes'=>$classes,'rates'=>$rates->fetchAll(PDO::FETCH_ASSOC),
                'pending_adjustments'=>$pending->fetchAll(PDO::FETCH_ASSOC),'current_admin_id'=>(int)($_SESSION['admin_id']??0),'current_admin_role'=>currentAdminRole()]);
        }

        if ($action === 'register') {
            $search = trim((string)($_GET['q']??''));
            $sectionFilter = (string)($_GET['section']??'');
            $classFilter = (int)($_GET['class_id']??0);
            $typeFilter = (string)($_GET['student_type']??'');
            $scholarshipFilter = (string)($_GET['scholarship']??'');
            $paymentFilter = (string)($_GET['payment_status']??'');
            $intakeFilter = (string)($_GET['intake_type']??'');
            $where = ['s.is_active=1'];
            $params = [$periodId,$financeSessionId,$term,(string)$period['starts_on'],(string)$period['starts_on'],$financeSessionId,$term];
            if ($search !== '') {
                $where[] = "(s.admission_number LIKE ? OR CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) LIKE ?)";
                $params[] = '%'.$search.'%'; $params[] = '%'.$search.'%';
            }
            if ($classFilter > 0) { $where[]='s.class_id=?'; $params[]=$classFilter; }
            if (in_array($typeFilter,['Day Student','Boarding'],true)) { $where[]="COALESCE(NULLIF(s.student_type,''),'Day Student')=?"; $params[]=$typeFilter; }
            $sql = "SELECT s.id student_id,s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name,
                           s.class_id,COALESCE(c.name,'Class not assigned') class_name,s.student_type,s.admission_date,io.intake_type configured_intake_type,
                           COALESCE(cur.current_invoice_count,0) current_invoice_count,COALESCE(cur.has_payment_history,0) has_payment_history,cur.current_intake_type,COALESCE(cur.current_normal_fee,0) current_normal_fee,COALESCE(cur.current_discount,0) current_discount,COALESCE(cur.current_fee,0) current_fee,COALESCE(cur.current_paid,0) current_paid,
                           COALESCE(cur.current_balance,0) current_balance,COALESCE(prev.previous_outstanding,0) previous_outstanding,COALESCE(prev.previous_paid,0) previous_paid,COALESCE(prev.previous_invoice_count,0) previous_invoice_count,prev.previous_debt_details,
                           COALESCE(cur.current_fee,0)+COALESCE(prev.previous_outstanding,0) total_due,
                           COALESCE(cur.current_paid,0)+COALESCE(prev.previous_paid,0) total_paid,
                           COALESCE(cur.current_balance,0)+COALESCE(prev.previous_outstanding,0) total_balance,
                           COALESCE(adj.has_scholarship,0) has_scholarship,adj.adjustment_type
                    FROM students s LEFT JOIN classes c ON c.id=s.class_id
                    LEFT JOIN finance_student_fee_intake_overrides io ON io.student_id=s.id AND io.academic_period_id=".$periodId."
                    LEFT JOIN (
                      SELECT i.student_id,COUNT(i.id) current_invoice_count,MAX(CASE WHEN COALESCE(p.paid,0)>0 THEN 1 ELSE 0 END) has_payment_history,MAX(i.intake_type) current_intake_type,SUM(i.base_amount) current_normal_fee,SUM(i.discount_amount) current_discount,SUM(i.amount_due) current_fee,
                             SUM(COALESCE(p.paid,0)-COALESCE(r.refunded,0)) current_paid,
                             SUM(i.amount_due-COALESCE(p.paid,0)+COALESCE(r.refunded,0)) current_balance
                      FROM finance_term_fee_invoices i
                      LEFT JOIN (SELECT term_invoice_id,SUM(amount) paid FROM finance_term_fee_payments GROUP BY term_invoice_id) p ON p.term_invoice_id=i.id
                      LEFT JOIN (SELECT a.term_invoice_id,SUM(x.amount) refunded FROM finance_fee_refund_allocations x JOIN finance_fee_refunds rf ON rf.id=x.refund_id JOIN finance_term_fee_payments a ON x.allocation_type='term' AND a.id=x.source_allocation_id WHERE rf.status='approved' GROUP BY a.term_invoice_id) r ON r.term_invoice_id=i.id
                      WHERE (i.academic_period_id=? OR (i.academic_period_id IS NULL AND i.session_id=? AND i.term=?)) AND i.status<>'void'
                      GROUP BY i.student_id
                    ) cur ON cur.student_id=s.id
                    LEFT JOIN (
                      SELECT i.student_id,COUNT(i.id) previous_invoice_count,
                             SUM(GREATEST(i.amount_due-COALESCE(p.paid,0)+COALESCE(r.refunded,0),0)) previous_outstanding,
                             SUM(GREATEST(COALESCE(p.paid,0)-COALESCE(r.refunded,0),0)) previous_paid,
                             GROUP_CONCAT(
                               CASE WHEN GREATEST(i.amount_due-COALESCE(p.paid,0)+COALESCE(r.refunded,0),0)>0
                                 THEN CONCAT(fs.session_name,' · ',UPPER(i.term),' TERM: ₦',FORMAT(GREATEST(i.amount_due-COALESCE(p.paid,0)+COALESCE(r.refunded,0),0),2))
                                 ELSE NULL END
                               ORDER BY fs.starts_on,i.id SEPARATOR ' | '
                             ) previous_debt_details
                      FROM finance_term_fee_invoices i
                      INNER JOIN finance_academic_sessions fs ON fs.id=i.session_id
                      LEFT JOIN (SELECT term_invoice_id,SUM(amount) paid FROM finance_term_fee_payments GROUP BY term_invoice_id) p ON p.term_invoice_id=i.id
                      LEFT JOIN (SELECT a.term_invoice_id,SUM(x.amount) refunded FROM finance_fee_refund_allocations x JOIN finance_fee_refunds rf ON rf.id=x.refund_id JOIN finance_term_fee_payments a ON x.allocation_type='term' AND a.id=x.source_allocation_id WHERE rf.status='approved' GROUP BY a.term_invoice_id) r ON r.term_invoice_id=i.id
                      WHERE i.status<>'void'
                        AND (
                          (i.academic_period_id IS NULL AND i.due_on<?)
                          OR (i.academic_period_id IS NOT NULL AND EXISTS (
                              SELECT 1 FROM academic_periods old_period
                              WHERE old_period.id=i.academic_period_id
                                AND old_period.starts_on IS NOT NULL
                                AND old_period.starts_on<?
                          ))
                      )
                      GROUP BY i.student_id
                    ) prev ON prev.student_id=s.id
                    LEFT JOIN (
                      SELECT a.student_id,1 has_scholarship,a.adjustment_type
                      FROM finance_student_fee_adjustments a
                      INNER JOIN (
                        SELECT student_id,MAX(id) adjustment_id
                        FROM finance_student_fee_adjustments
                        WHERE session_id=? AND term=? AND status='approved'
                        GROUP BY student_id
                      ) latest ON latest.adjustment_id=a.id
                    ) adj ON adj.student_id=s.id
                    WHERE ".implode(' AND ',$where)." ORDER BY c.sort_order,c.name,s.last_name,s.first_name";
            $stmt=$pdo->prepare($sql);$stmt->execute(array_merge($params,[]));
            $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach($rows as &$row) {
                $row['section']=markazFeeSection((string)$row['class_name']);
                $admission=(string)($row['admission_date']??'');
                $row['intake_type']=in_array((string)($row['current_intake_type']??''),['new_intake','returning'],true)?(string)$row['current_intake_type']:(in_array((string)($row['configured_intake_type']??''),['new_intake','returning'],true)?(string)$row['configured_intake_type']:(($admission!=='' && $admission>=(string)$period['starts_on'] && $admission<=(string)$period['ends_on'])?'new_intake':'returning'));
                $row['current_normal_fee']=financeFromCents(financeToCents((string)$row['current_normal_fee']));
                $row['current_discount']=financeFromCents(financeToCents((string)$row['current_discount']));
                $row['current_fee']=financeFromCents(financeToCents((string)$row['current_fee']));
                $row['current_paid']=financeFromCents(financeToCents((string)$row['current_paid']));
                $row['current_balance']=financeFromCents(max(0,financeToCents((string)$row['current_balance'])));
                $row['previous_outstanding']=financeFromCents(max(0,financeToCents((string)$row['previous_outstanding'])));
                $row['total_due']=financeFromCents(financeToCents((string)$row['total_due']));
                $row['total_paid']=financeFromCents(financeToCents((string)$row['total_paid']));
                $row['total_balance']=financeFromCents(max(0,financeToCents((string)$row['total_balance'])));
            }
            unset($row);
            if ($sectionFilter !== '') $rows=array_values(array_filter($rows,fn($r)=>($r['section']??'')===$sectionFilter));
            if ($scholarshipFilter==='yes') $rows=array_values(array_filter($rows,fn($r)=>(int)$r['has_scholarship']===1));
            if ($scholarshipFilter==='no') $rows=array_values(array_filter($rows,fn($r)=>(int)$r['has_scholarship']===0));
            if (in_array($scholarshipFilter,['full_scholarship','discount_percent','discount_amount','fixed_fee'],true)) $rows=array_values(array_filter($rows,fn($r)=>(int)$r['has_scholarship']===1 && (string)$r['adjustment_type']===$scholarshipFilter));
            if (in_array($intakeFilter,['new_intake','returning'],true)) $rows=array_values(array_filter($rows,fn($r)=>$r['intake_type']===$intakeFilter));
            if ($paymentFilter==='paid') $rows=array_values(array_filter($rows,fn($r)=>financeToCents((string)$r['total_balance'])===0 && ((int)$r['current_invoice_count']>0 || (int)$r['previous_invoice_count']>0 || ((int)$r['has_scholarship']===1 && (string)$r['adjustment_type']==='full_scholarship'))));
            if ($paymentFilter==='unpaid') $rows=array_values(array_filter($rows,fn($r)=>financeToCents((string)$r['total_paid'])===0 && financeToCents((string)$r['total_due'])>0));
            if ($paymentFilter==='partial') $rows=array_values(array_filter($rows,fn($r)=>financeToCents((string)$r['total_paid'])>0 && financeToCents((string)$r['total_balance'])>0));
            $totals=['students'=>count($rows),'current_fee'=>'0.00','current_paid'=>'0.00','previous_outstanding'=>'0.00','total_due'=>'0.00','total_paid'=>'0.00','total_balance'=>'0.00'];
            foreach($rows as $row) foreach(['current_fee','current_paid','previous_outstanding','total_due','total_paid','total_balance'] as $field) $totals[$field]=financeFromCents(financeToCents($totals[$field])+financeToCents((string)$row[$field]));
            financeJson(['success'=>true,'period'=>$period,'students'=>$rows,'totals'=>$totals]);
        }

        if ($action === 'invoices') {
            $stmt=$pdo->prepare(
                "SELECT i.*,s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name,
                        c.name class_name,(COALESCE(p.paid,0)-COALESCE(r.refunded,0)) paid,
                        i.amount_due-COALESCE(p.paid,0)+COALESCE(r.refunded,0) balance
                 FROM finance_term_fee_invoices i JOIN students s ON s.id=i.student_id JOIN classes c ON c.id=i.class_id
                 LEFT JOIN (SELECT term_invoice_id,SUM(amount) paid FROM finance_term_fee_payments GROUP BY term_invoice_id) p ON p.term_invoice_id=i.id
                 LEFT JOIN (SELECT a.term_invoice_id,SUM(x.amount) refunded FROM finance_fee_refund_allocations x JOIN finance_fee_refunds rf ON rf.id=x.refund_id JOIN finance_term_fee_payments a ON x.allocation_type='term' AND a.id=x.source_allocation_id WHERE rf.status='approved' GROUP BY a.term_invoice_id) r ON r.term_invoice_id=i.id
                 WHERE (i.academic_period_id=? OR (i.academic_period_id IS NULL AND i.session_id=? AND i.term=?)) AND i.status<>'void'
                 ORDER BY c.sort_order,c.name,s.last_name,s.first_name"
            );
            $stmt->execute([$periodId,$financeSessionId,$term]);
            financeJson(['success'=>true,'invoices'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'period'=>$period]);
        }
        financeJson(['success'=>false,'message'=>'Unknown Student Fees action.'],404);
    }

    if (($_SERVER['REQUEST_METHOD']??'GET') !== 'POST') financeJson(['success'=>false,'message'=>'Method not allowed.'],405);
    financeRequireSafePost();
    $input=financeInput();
    $action=(string)($input['action']??'');
    $period=markazCurrentAcademicPeriod($pdo);
    $periodId=(int)$period['period_id'];
    $financeSessionId=(int)$period['finance_session_id'];
    $term=(string)$period['term'];
    if ((int)$period['is_finalized']===1) financeJson(['success'=>false,'message'=>'The current academic term is finalized. Student fee assessments are read-only.'],409);

    if ($action==='save_rates') {
        $rows=$input['rates']??null;
        if(!is_array($rows)||count($rows)!==16)financeJson(['success'=>false,'message'=>'Provide all 16 section, accommodation and intake fee rates.'],422);
        $allowedSections=['nursery','primary','junior_secondary','senior_secondary'];
        $allowedTypes=['Day Student','Boarding'];
        $allowedIntakes=['returning','new_intake'];
        $seen=[];
        $dueOn=(string)($input['due_on']??'');
        if(!financeValidDate($dueOn))financeJson(['success'=>false,'message'=>'Choose a valid fee due date.'],422);
        $pdo->beginTransaction();
        try{
            $save=$pdo->prepare(
                'INSERT INTO finance_section_fee_schedules(academic_period_id,section,student_type,intake_type,amount,due_on,created_by)
                 VALUES(?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE amount=VALUES(amount),due_on=VALUES(due_on),is_active=1'
            );
            foreach($rows as $row){
                if(!is_array($row))throw new DomainException('Invalid fee rate row.');
                $section=(string)($row['section']??'');$type=(string)($row['student_type']??'');$intake=(string)($row['intake_type']??'');
                $key=$section.'|'.$type.'|'.$intake;
                if(!in_array($section,$allowedSections,true)||!in_array($type,$allowedTypes,true)||!in_array($intake,$allowedIntakes,true)||isset($seen[$key]))throw new DomainException('Fee configuration contains an invalid or duplicate category.');
                $seen[$key]=true;$amount=financeMoney($row['amount']??'',true);
                $save->execute([$periodId,$section,$type,$intake,$amount,$dueOn,(int)$_SESSION['admin_id']]);
            }
            $sync=markazSyncStudentTermFees($pdo,$period,$financeSessionId,$term,(int)$_SESSION['admin_id']);
            financeAudit($pdo,'section_student_fee_rates_saved','section_fee_schedule',null,['academic_period_id'=>$periodId,'rate_count'=>count($rows),'student_invoices_created'=>$sync['created'],'student_invoices_updated'=>$sync['updated'],'students_skipped'=>$sync['skipped']]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof DomainException)financeJson(['success'=>false,'message'=>$e->getMessage()],422);throw $e;}
        financeJson(['success'=>true,'created'=>$sync['created'],'updated'=>$sync['updated'],'skipped'=>$sync['skipped'],'message'=>"Fee rates saved. {$sync['created']} student accounts created and {$sync['updated']} existing assessments synchronised. {$sync['skipped']} students skipped because they need a supported conventional class or matching rate. Recorded payments were preserved; balances were recalculated."]);
    }

    if ($action==='save_student_intake_override') {
        $student=(int)($input['student_id']??0);
        $intake=(string)($input['intake_type']??'');
        $actor=(int)($_SESSION['admin_id']??0);
        if($student<1||!in_array($intake,['new_intake','returning'],true)||$actor<1)financeJson(['success'=>false,'message'=>'Choose a student and a valid New Intake or Returning category.'],422);
        $st=$pdo->prepare('SELECT s.id,c.name class_name FROM students s LEFT JOIN classes c ON c.id=s.class_id WHERE s.id=? AND s.is_active=1 LIMIT 1');
        $st->execute([$student]);$studentRow=$st->fetch(PDO::FETCH_ASSOC);
        if(!$studentRow)financeJson(['success'=>false,'message'=>'Active student not found.'],404);
        if(markazFeeSection((string)($studentRow['class_name']??''))===null)financeJson(['success'=>false,'message'=>'Assign this student to a supported conventional class before configuring a fee intake category.'],422);
        $issued=$pdo->prepare("SELECT COUNT(*) FROM finance_term_fee_invoices WHERE student_id=? AND (academic_period_id=? OR (academic_period_id IS NULL AND session_id=? AND term=?)) AND status<>'void'");
        $issued->execute([$student,$periodId,$financeSessionId,$term]);
        if((int)$issued->fetchColumn()>0)financeJson(['success'=>false,'message'=>'A fee assessment already exists for this student in the current term. Its intake category is locked to preserve the issued assessment and payment history.'],409);
        $pdo->beginTransaction();
        try{
            $save=$pdo->prepare('INSERT INTO finance_student_fee_intake_overrides(academic_period_id,student_id,intake_type,created_by,updated_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE intake_type=VALUES(intake_type),updated_by=VALUES(updated_by)');
            $save->execute([$periodId,$student,$intake,$actor,$actor]);
            financeAudit($pdo,'student_fee_intake_classification_saved','student_fee_intake_override',null,['student_id'=>$student,'academic_period_id'=>$periodId,'intake_type'=>$intake]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        financeJson(['success'=>true,'message'=>'Student intake category saved for this academic term. It will be used when the term fee assessment is generated.']);
    }

    if (in_array($action,['save_student_adjustment','reset_student_adjustment'],true)) {
        $isReset=$action==='reset_student_adjustment';
        $student=(int)($input['student_id']??0);$type=$isReset?'fixed_fee':(string)($input['adjustment_type']??'');$value=financeMoney($input['value']??'0',true);$reason=$isReset?'Reset scholarship to the standard scheduled fee.':trim((string)($input['reason']??''));
        $allowed=['fixed_fee','discount_amount','discount_percent','full_scholarship'];
        if($student<1||!in_array($type,$allowed,true)||$reason==='')financeJson(['success'=>false,'message'=>'Complete the student adjustment and explain the reason.'],422);
        if($type==='discount_percent'&&financeToCents($value)>10000)financeJson(['success'=>false,'message'=>'Percentage discount cannot exceed 100%.'],422);
        $issued=$pdo->prepare("SELECT i.id,i.base_amount,(SELECT COUNT(*) FROM finance_term_fee_payments p WHERE p.term_invoice_id=i.id) payment_count
                                FROM finance_term_fee_invoices i
                                WHERE i.student_id=? AND (i.academic_period_id=? OR (i.academic_period_id IS NULL AND i.session_id=? AND i.term=?))
                                LIMIT 1");
        $issued->execute([$student,$periodId,$financeSessionId,$term]);$issuedInvoice=$issued->fetch(PDO::FETCH_ASSOC);
        $pending=$pdo->prepare("SELECT id FROM finance_student_fee_adjustments WHERE student_id=? AND session_id=? AND term=? AND status='pending' LIMIT 1");
        $pending->execute([$student,$financeSessionId,$term]);
        if($pending->fetchColumn())financeJson(['success'=>false,'message'=>'This student already has a pending fee adjustment awaiting approval.'],409);
        $st=$pdo->prepare('SELECT s.id,s.student_type,s.admission_date,c.name class_name FROM students s JOIN classes c ON c.id=s.class_id WHERE s.id=? AND s.is_active=1 LIMIT 1');
        $st->execute([$student]);$studentRow=$st->fetch(PDO::FETCH_ASSOC);
        if(!$studentRow)financeJson(['success'=>false,'message'=>'Active student not found.'],404);
        $section=markazFeeSection((string)$studentRow['class_name']);
        if($section===null)financeJson(['success'=>false,'message'=>'This student class is not part of the four configured MARKAZ fee sections.'],422);
        $studentType=in_array((string)$studentRow['student_type'],['Day Student','Boarding'],true)?(string)$studentRow['student_type']:'Day Student';
        $admission=(string)($studentRow['admission_date']??'');
        $overrideStmt=$pdo->prepare('SELECT intake_type FROM finance_student_fee_intake_overrides WHERE academic_period_id=? AND student_id=? LIMIT 1');
        $overrideStmt->execute([$periodId,$student]);$configuredIntake=$overrideStmt->fetchColumn();
        $intake=in_array((string)$configuredIntake,['new_intake','returning'],true)?(string)$configuredIntake:(($admission!=='' && $admission>=(string)$period['starts_on'] && $admission<=(string)$period['ends_on'])?'new_intake':'returning');
        $rateStmt=$pdo->prepare('SELECT amount FROM finance_section_fee_schedules WHERE academic_period_id=? AND section=? AND student_type=? AND intake_type=? AND is_active=1 LIMIT 1');
        $rateStmt->execute([$periodId,$section,$studentType,$intake]);$normalFee=$rateStmt->fetchColumn();
        if($normalFee===false)financeJson(['success'=>false,'message'=>'Save this term’s section fee rates before configuring an individual student adjustment.'],409);
        $feeBasis=$issuedInvoice?(string)$issuedInvoice['base_amount']:(string)$normalFee;
        if($isReset)$value=financeMoney($normalFee,true);
        if($type==='fixed_fee'&&financeToCents($value)>financeToCents($feeBasis))financeJson(['success'=>false,'message'=>'The negotiated final fee cannot exceed the normal fee configured for this student.'],422);
        $actor=(int)($_SESSION['admin_id']??0);if($actor<1)financeJson(['success'=>false,'message'=>'Your staff session has expired. Sign in again.'],401);
        $role=currentAdminRole();$adminImmediate=$role==='admin';
        $pdo->beginTransaction();
        $st=$pdo->prepare("INSERT INTO finance_student_fee_adjustments(student_id,session_id,term,adjustment_type,value,reason,status,approved_by,approved_at,created_by,effective_from) VALUES(?,?,?,?,?,?,?,?,?,?,CURDATE())");
        $st->execute([$student,$financeSessionId,$term,$type,$value,$reason,$adminImmediate?'approved':'pending',$adminImmediate?$actor:null,$adminImmediate?date('Y-m-d H:i:s'):null,$actor]);$id=(int)$pdo->lastInsertId();
        if($adminImmediate && $issuedInvoice){
            [$baseCents,$discountCents,$dueCents]=markazFeeDiscountCents(financeToCents((string)$issuedInvoice['base_amount']),$type,$value);
            $paidStmt=$pdo->prepare("SELECT COALESCE(SUM(p.amount),0)-
                COALESCE((SELECT SUM(x.amount) FROM finance_fee_refund_allocations x JOIN finance_fee_refunds rf ON rf.id=x.refund_id
                    WHERE x.allocation_type='term' AND x.source_allocation_id=p.id AND rf.status='approved'),0)
                FROM finance_term_fee_payments p WHERE p.term_invoice_id=?");$paidStmt->execute([(int)$issuedInvoice['id']]);$paid=financeToCents((string)$paidStmt->fetchColumn());
            $status=($dueCents===0||$paid>=$dueCents)?'paid':($paid>0?'partial':'unpaid');
            $amend=$pdo->prepare('UPDATE finance_term_fee_invoices SET discount_amount=?,amount_due=?,status=? WHERE id=?');
            $amend->execute([financeFromCents($discountCents),financeFromCents($dueCents),$status,(int)$issuedInvoice['id']]);
        }
        financeAudit($pdo,$adminImmediate?'student_fee_adjustment_admin_applied':'student_fee_adjustment_submitted','student_fee_adjustment',$id,['student_id'=>$student,'type'=>$type,'value'=>$value,'academic_period_id'=>$periodId,'reset_to_standard_fee'=>$isReset]);
        $pdo->commit();
        financeJson(['success'=>true,'message'=>$adminImmediate?($isReset?'Scholarship reset. The standard scheduled fee is now applied immediately.':'Admin change saved and applied immediately.'):'Management change submitted. Admin approval is required before the student fee changes.']);
    }

    if ($action==='approve_student_adjustment') {
        $id=(int)($input['adjustment_id']??0);$actor=(int)($_SESSION['admin_id']??0);
        if(currentAdminRole()!=='admin')financeJson(['success'=>false,'message'=>'Only Admin can approve management fee adjustments.'],403);
        if($id<1||$actor<1)financeJson(['success'=>false,'message'=>'A valid adjustment and active staff session are required.'],422);
        $pdo->beginTransaction();
        $st=$pdo->prepare('SELECT id,created_by,status,session_id,term FROM finance_student_fee_adjustments WHERE id=? FOR UPDATE');$st->execute([$id]);$adjustment=$st->fetch(PDO::FETCH_ASSOC);
        if(!$adjustment||$adjustment['status']!=='pending'||(int)$adjustment['session_id']!==$financeSessionId||(string)$adjustment['term']!==$term){$pdo->rollBack();financeJson(['success'=>false,'message'=>'This pending adjustment does not belong to the current academic term.'],409);}
        if((int)$adjustment['created_by']===$actor){$pdo->rollBack();financeJson(['success'=>false,'message'=>'A different authorised staff member must approve this adjustment.'],403);}
        $invoiceStmt=$pdo->prepare('SELECT id,base_amount FROM finance_term_fee_invoices WHERE student_id=(SELECT student_id FROM finance_student_fee_adjustments WHERE id=?) AND (academic_period_id=? OR (academic_period_id IS NULL AND session_id=? AND term=?)) LIMIT 1 FOR UPDATE');
        $invoiceStmt->execute([$id,$periodId,$financeSessionId,$term]);$issuedInvoice=$invoiceStmt->fetch(PDO::FETCH_ASSOC);
        $up=$pdo->prepare("UPDATE finance_student_fee_adjustments SET status='approved',approved_by=?,approved_at=NOW() WHERE id=? AND status='pending'");$up->execute([$actor,$id]);
        if($up->rowCount()!==1){$pdo->rollBack();financeJson(['success'=>false,'message'=>'Adjustment approval could not be completed. Refresh and try again.'],409);}
        if($issuedInvoice){
            $details=$pdo->prepare('SELECT adjustment_type,value FROM finance_student_fee_adjustments WHERE id=?');$details->execute([$id]);$approved=$details->fetch(PDO::FETCH_ASSOC);
            [$baseCents,$discountCents,$dueCents]=markazFeeDiscountCents(financeToCents((string)$issuedInvoice['base_amount']),(string)$approved['adjustment_type'],(string)$approved['value']);
            $paidStmt=$pdo->prepare("SELECT COALESCE(SUM(p.amount),0)-
                COALESCE((SELECT SUM(x.amount) FROM finance_fee_refund_allocations x JOIN finance_fee_refunds rf ON rf.id=x.refund_id
                    WHERE x.allocation_type='term' AND x.source_allocation_id=p.id AND rf.status='approved'),0)
                FROM finance_term_fee_payments p WHERE p.term_invoice_id=?");$paidStmt->execute([(int)$issuedInvoice['id']]);$paid=financeToCents((string)$paidStmt->fetchColumn());
            $status=($dueCents===0||$paid>=$dueCents)?'paid':($paid>0?'partial':'unpaid');
            $amend=$pdo->prepare('UPDATE finance_term_fee_invoices SET discount_amount=?,amount_due=?,status=? WHERE id=?');
            $amend->execute([financeFromCents($discountCents),financeFromCents($dueCents),$status,(int)$issuedInvoice['id']]);
            financeAudit($pdo,'student_fee_invoice_adjusted','term_fee_invoice',(int)$issuedInvoice['id'],['adjustment_id'=>$id,'base_amount'=>financeFromCents($baseCents),'discount_amount'=>financeFromCents($discountCents),'amount_due'=>financeFromCents($dueCents)]);
        }
        financeAudit($pdo,'student_fee_adjustment_approved','student_fee_adjustment',$id,['approved_by'=>$actor,'invoice_id'=>$issuedInvoice?(int)$issuedInvoice['id']:null]);
        $pdo->commit();financeJson(['success'=>true,'message'=>'Student fee adjustment approved for this term.']);
    }

    if ($action==='generate_term_invoices') {
        $students=$pdo->query("SELECT s.id,s.class_id,s.student_type,s.admission_date,c.name class_name
                               FROM students s LEFT JOIN classes c ON c.id=s.class_id
                               WHERE s.is_active=1 ORDER BY s.id")->fetchAll(PDO::FETCH_ASSOC);
        $rateStmt=$pdo->prepare('SELECT * FROM finance_section_fee_schedules WHERE academic_period_id=? AND is_active=1');
        $rateStmt->execute([$periodId]);$rateMap=[];
        foreach($rateStmt->fetchAll(PDO::FETCH_ASSOC) as $rate)$rateMap[$rate['section'].'|'.$rate['student_type'].'|'.$rate['intake_type']]=$rate;
        $overrideQuery=$pdo->prepare('SELECT student_id,intake_type FROM finance_student_fee_intake_overrides WHERE academic_period_id=?');
        $overrideQuery->execute([$periodId]);$intakeOverrides=[];
        foreach($overrideQuery->fetchAll(PDO::FETCH_ASSOC) as $override)$intakeOverrides[(int)$override['student_id']]=(string)$override['intake_type'];
        if(count($rateMap)!==16)financeJson(['success'=>false,'message'=>'Configure all 16 fee rates for this term before generating student assessments.'],409);
        $pdo->beginTransaction();$created=0;$skipped=0;
        try{
            $adj=$pdo->prepare("SELECT adjustment_type,value FROM finance_student_fee_adjustments WHERE student_id=? AND session_id=? AND term=? AND status='approved' AND effective_from<=CURDATE() AND (effective_until IS NULL OR effective_until>=CURDATE()) ORDER BY approved_at DESC,id DESC LIMIT 1");
            $insert=$pdo->prepare(
                'INSERT IGNORE INTO finance_term_fee_invoices(invoice_number,student_id,session_id,academic_period_id,term,class_id,section,student_type,intake_type,fee_schedule_id,base_amount,discount_amount,amount_due,due_on,generated_by)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            foreach($students as $student){
                $section=markazFeeSection((string)($student['class_name']??''));if($section===null){$skipped++;continue;}
                $type=(string)$student['student_type'];if(!in_array($type,['Day Student','Boarding'],true))$type='Day Student';
                $admission=(string)($student['admission_date']??'');
                $intake=$intakeOverrides[(int)$student['id']]??(($admission!=='' && $admission>=(string)$period['starts_on'] && $admission<=(string)$period['ends_on'])?'new_intake':'returning');
                $rate=$rateMap[$section.'|'.$type.'|'.$intake]??null;if(!$rate)continue;
                $base=financeToCents((string)$rate['amount']);$discount=0;$due=$base;
                $adj->execute([(int)$student['id'],$financeSessionId,$term]);$adjustment=$adj->fetch(PDO::FETCH_ASSOC);
                if($adjustment){[$base,$discount,$due]=markazFeeDiscountCents($base,(string)$adjustment['adjustment_type'],(string)$adjustment['value']);}
                $insert->execute([
                    financeNextNumber('TERM'),(int)$student['id'],$financeSessionId,$periodId,$term,(int)$student['class_id'],
                    $section,$type,$intake,(int)$rate['id'],financeFromCents($base),financeFromCents($discount),
                    financeFromCents($due),(string)$rate['due_on'],(int)$_SESSION['admin_id']
                ]);
                $created+=$insert->rowCount();
            }
            financeAudit($pdo,'term_fee_invoices_generated','term_fee_invoice_batch',null,['academic_period_id'=>$periodId,'created'=>$created]);
            $pdo->commit();
        }catch(DomainException $e){if($pdo->inTransaction())$pdo->rollBack();financeJson(['success'=>false,'message'=>$e->getMessage()],422);}
        catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        financeJson(['success'=>true,'created'=>$created,'skipped_without_supported_class'=>$skipped,'message'=>"Generated {$created} student fee assessments. ".($skipped>0?"{$skipped} active students were skipped because they need a supported conventional class assigned. ":'')."Existing assessments and previous-term balances were left unchanged."]);
    }
    financeJson(['success'=>false,'message'=>'Unknown Student Fees action.'],404);
} catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('MARKAZ student fees: '.$e->getMessage());
    financeJson(['success'=>false,'message'=>'Student Fees operation failed. Check the server log.'],500);
}
