<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/finance.php';

financeRequireAccess();
$pdo = db();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'GET') {
        $action = (string) ($_GET['action'] ?? 'dashboard');
        if ($action === 'dashboard') {
            $today = $pdo->query(
                "SELECT
                    COALESCE(SUM(CASE WHEN status='confirmed' AND DATE(paid_at)=CURDATE() THEN amount ELSE 0 END),0) AS fees_today,
                    (SELECT COALESCE(SUM(amount),0) FROM finance_expenses WHERE status='paid' AND expense_date=CURDATE()) AS expenses_today,
                    (SELECT COALESCE(SUM(amount),0) FROM finance_other_income WHERE status='confirmed' AND received_date=CURDATE()) AS other_income_today,
                    (SELECT COUNT(*) FROM finance_fee_invoices WHERE status IN ('unpaid','partial')) AS open_invoices,
                    (SELECT COALESCE(SUM(i.amount_due-i.discount_amount-COALESCE(a.paid,0)),0)
                     FROM finance_fee_invoices i
                     LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM finance_fee_allocations GROUP BY invoice_id) a ON a.invoice_id=i.id
                     WHERE i.status IN ('unpaid','partial')) AS outstanding
                 FROM finance_fee_payments"
            )->fetch(PDO::FETCH_ASSOC);
            $month = $pdo->query(
                "SELECT COALESCE(SUM(CASE WHEN status='confirmed' THEN amount ELSE 0 END),0)
                 FROM finance_fee_payments WHERE YEAR(paid_at)=YEAR(CURDATE()) AND MONTH(paid_at)=MONTH(CURDATE())"
            )->fetchColumn();
            $recent = $pdo->query(
                "SELECT p.id,p.receipt_number,p.amount,p.method,p.status,p.paid_at,
                        s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name
                 FROM finance_fee_payments p INNER JOIN students s ON s.id=p.student_id
                 ORDER BY p.created_at DESC LIMIT 8"
            )->fetchAll(PDO::FETCH_ASSOC);
            financeJson(['success'=>true,'summary'=>$today,'fees_this_month'=>(string)$month,'recent_payments'=>$recent]);
        }
        if ($action === 'payments') {
            $rows=$pdo->query(
                "SELECT p.id,p.receipt_number,p.amount,p.method,p.reference,p.status,p.paid_at,p.payer_name,
                        s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name
                 FROM finance_fee_payments p INNER JOIN students s ON s.id=p.student_id
                 ORDER BY p.created_at DESC LIMIT 300"
            )->fetchAll(PDO::FETCH_ASSOC);
            financeJson(['success'=>true,'payments'=>$rows]);
        }
        if ($action === 'receipt') {
            $id=(int)($_GET['payment_id']??0);
            $stmt=$pdo->prepare(
                "SELECT p.*,s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name,
                        c.name class_name,ac.name arabic_class_name,a.name recorded_by_name
                 FROM finance_fee_payments p INNER JOIN students s ON s.id=p.student_id
                 LEFT JOIN classes c ON c.id=s.class_id
                 LEFT JOIN arabic_classes ac ON ac.id=COALESCE(NULLIF(s.arabic_class_id,0),s.arabia_class_id)
                 LEFT JOIN admins a ON a.id=p.recorded_by WHERE p.id=? LIMIT 1"
            );
            $stmt->execute([$id]);$payment=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$payment)financeJson(['success'=>false,'message'=>'Receipt record not found.'],404);
            $alloc=$pdo->prepare('SELECT i.invoice_number,i.billing_year,i.billing_month,fa.amount FROM finance_fee_allocations fa INNER JOIN finance_fee_invoices i ON i.id=fa.invoice_id WHERE fa.payment_id=? ORDER BY i.billing_year,i.billing_month');
            $alloc->execute([$id]);
            financeJson(['success'=>true,'payment'=>$payment,'allocations'=>$alloc->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($action === 'reports') {
            $from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??date('Y-m-d'));
            if(!financeValidDate($from)||!financeValidDate($to)||$from>$to)financeJson(['success'=>false,'message'=>'Choose a valid report date range.'],422);
            $fees=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_fee_payments WHERE status='confirmed' AND DATE(paid_at) BETWEEN ? AND ?");
            $fees->execute([$from,$to]);$expenses=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_expenses WHERE status='paid' AND expense_date BETWEEN ? AND ?");
            $expenses->execute([$from,$to]);
            $otherIncome=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_other_income WHERE status='confirmed' AND received_date BETWEEN ? AND ?");
            $otherIncome->execute([$from,$to]);
            $categories=$pdo->prepare("SELECT category,COUNT(*) transaction_count,SUM(amount) total FROM finance_expenses WHERE status='paid' AND expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
            $categories->execute([$from,$to]);
            $pending=$pdo->prepare("SELECT COUNT(*),COALESCE(SUM(amount),0) FROM finance_fee_payments WHERE status='pending' AND DATE(paid_at) BETWEEN ? AND ?");
            $pending->execute([$from,$to]);$pendingRow=$pending->fetch(PDO::FETCH_NUM);
            $feeTotal=(string)$fees->fetchColumn();$expenseTotal=(string)$expenses->fetchColumn();$otherIncomeTotal=(string)$otherIncome->fetchColumn();
            financeJson(['success'=>true,'from'=>$from,'to'=>$to,'fees_collected'=>$feeTotal,'other_income'=>$otherIncomeTotal,'expenses_paid'=>$expenseTotal,'net_movement'=>financeFromCents(financeToCents($feeTotal)+financeToCents($otherIncomeTotal)-financeToCents($expenseTotal)),'pending_payment_count'=>(int)$pendingRow[0],'pending_payment_amount'=>(string)$pendingRow[1],'expense_categories'=>$categories->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($action === 'classes') {
            $classes=$pdo->query(
                "SELECT id,name,'conventional' class_type FROM classes WHERE is_active=1
                 UNION ALL
                 SELECT id,name,'arabic' class_type FROM arabic_classes WHERE is_active=1
                 ORDER BY class_type,name"
            )->fetchAll(PDO::FETCH_ASSOC);
            financeJson(['success'=>true,'classes'=>$classes]);
        }
        if ($action === 'students') {
            $q = trim((string)($_GET['q'] ?? ''));
            $stmt = $pdo->prepare(
                "SELECT s.id,s.admission_number,s.first_name,s.middle_name,s.last_name,s.class_id,
                        COALESCE(NULLIF(s.arabic_class_id,0),s.arabia_class_id) arabic_class_id,
                        c.name class_name,ac.name arabic_class_name
                 FROM students s
                 LEFT JOIN classes c ON c.id=s.class_id
                 LEFT JOIN arabic_classes ac ON ac.id=COALESCE(NULLIF(s.arabic_class_id,0),s.arabia_class_id)
                 WHERE s.is_active=1 AND (?='' OR s.admission_number LIKE ? OR s.first_name LIKE ? OR s.middle_name LIKE ? OR s.last_name LIKE ?)
                 ORDER BY s.last_name,s.first_name LIMIT 100"
            );
            $like='%'.$q.'%'; $stmt->execute([$q,$like,$like,$like,$like]);
            financeJson(['success'=>true,'students'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($action === 'plans') {
            $stmt=$pdo->query(
                "SELECT p.*,c.name class_name,ac.name arabic_class_name,
                        s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name
                 FROM finance_fee_plans p
                 LEFT JOIN classes c ON p.class_type='conventional' AND c.id=p.class_id
                 LEFT JOIN arabic_classes ac ON p.class_type='arabic' AND ac.id=p.class_id
                 LEFT JOIN students s ON s.id=p.student_id
                 ORDER BY p.is_active DESC,p.created_at DESC"
            );
            financeJson(['success'=>true,'plans'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($action === 'invoices') {
            $studentId=(int)($_GET['student_id']??0);
            $sql="SELECT i.*,s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name,
                         COALESCE(a.paid,0) paid_amount,
                         (i.amount_due-i.discount_amount-COALESCE(a.paid,0)) balance
                  FROM finance_fee_invoices i INNER JOIN students s ON s.id=i.student_id
                  LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM finance_fee_allocations GROUP BY invoice_id) a ON a.invoice_id=i.id";
            if($studentId>0){$st=$pdo->prepare($sql.' WHERE i.student_id=? ORDER BY i.billing_year DESC,i.billing_month DESC');$st->execute([$studentId]);}
            else{$st=$pdo->query($sql.' ORDER BY i.due_on DESC LIMIT 300');}
            financeJson(['success'=>true,'invoices'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($action === 'income') {
            $rows=$pdo->query(
                "SELECT i.*,a.name recorded_by_name,v.name verified_by_name
                 FROM finance_other_income i LEFT JOIN admins a ON a.id=i.recorded_by
                 LEFT JOIN admins v ON v.id=i.verified_by ORDER BY i.received_date DESC,i.id DESC LIMIT 300"
            )->fetchAll(PDO::FETCH_ASSOC);
            financeJson(['success'=>true,'income'=>$rows]);
        }
        if ($action === 'expenses') {
            $rows=$pdo->query(
                "SELECT e.*,a.name recorded_by_name,ap.name approved_by_name
                 FROM finance_expenses e LEFT JOIN admins a ON a.id=e.recorded_by
                 LEFT JOIN admins ap ON ap.id=e.approved_by ORDER BY e.expense_date DESC,e.id DESC LIMIT 300"
            )->fetchAll(PDO::FETCH_ASSOC);
            financeJson(['success'=>true,'expenses'=>$rows]);
        }
        if ($action === 'staff') {
            $rows=$pdo->query(
                "SELECT a.id,a.name,a.email,p.base_salary,p.expected_arrival,p.lateness_rate_per_minute,p.effective_from,p.is_active
                 FROM admins a LEFT JOIN staff_salary_profiles p ON p.account_id=a.id AND p.effective_from<=CURDATE() AND (p.effective_until IS NULL OR p.effective_until>=CURDATE())
                 WHERE a.role='teacher' AND a.is_active=1 ORDER BY a.name"
            )->fetchAll(PDO::FETCH_ASSOC);
            financeJson(['success'=>true,'staff'=>$rows]);
        }
        financeJson(['success'=>false,'message'=>'Unknown finance action.'],404);
    }

    if ($method !== 'POST') financeJson(['success'=>false,'message'=>'Method not allowed.'],405);
    financeRequireSafePost();
    $input=financeInput();
    $action=(string)($input['action']??'');

    if($action==='create_fee_plan'){
        $name=trim((string)($input['name']??''));
        $scope=(string)($input['scope']??'');
        $amount=financeMoney($input['amount']??'');
        $frequency=(string)($input['frequency']??'monthly');
        $dueDay=filter_var($input['due_day']??null,FILTER_VALIDATE_INT);
        $from=(string)($input['effective_from']??date('Y-m-01'));
        $until=trim((string)($input['effective_until']??'')) ?: null;
        $classType=$input['class_type']??null;
        $classId=(int)($input['class_id']??0);
        $studentId=(int)($input['student_id']??0);
        if($name===''||!in_array($scope,['school','class','student'],true)||$frequency!=='monthly'||!$dueDay||$dueDay<1||$dueDay>31||!financeValidDate($from)||($until!==null&&(!financeValidDate($until)||$until<$from))){
            financeJson(['success'=>false,'message'=>'Complete the fee plan fields with valid values.'],422);
        }
        if(($scope==='class'&&!in_array($classType,['conventional','arabic'],true))||($scope==='class'&&$classId<1)||($scope==='student'&&$studentId<1)){
            financeJson(['success'=>false,'message'=>'Choose a valid class or student for this fee plan.'],422);
        }
        if($scope==='class'){
            $table=$classType==='arabic'?'arabic_classes':'classes';
            $st=$pdo->prepare("SELECT id FROM {$table} WHERE id=? AND is_active=1");$st->execute([$classId]);
            if(!$st->fetchColumn()) financeJson(['success'=>false,'message'=>'Selected class is not active.'],422);
        }
        if($scope==='student'){
            $st=$pdo->prepare('SELECT id FROM students WHERE id=? AND is_active=1');$st->execute([$studentId]);
            if(!$st->fetchColumn()) financeJson(['success'=>false,'message'=>'Selected student was not found.'],422);
        }
        $stmt=$pdo->prepare(
            'INSERT INTO finance_fee_plans (name,description,scope,class_type,class_id,student_id,amount,frequency,due_day,effective_from,effective_until,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$name,trim((string)($input['description']??''))?:null,$scope,$scope==='class'?$classType:null,$scope==='class'?$classId:null,$scope==='student'?$studentId:null,$amount,$frequency,$dueDay,$from,$until,(int)$_SESSION['admin_id']]);
        $id=(int)$pdo->lastInsertId();financeAudit($pdo,'fee_plan_created','fee_plan',$id,['scope'=>$scope,'amount'=>$amount]);
        financeJson(['success'=>true,'id'=>$id,'message'=>'Monthly fee plan created.'],201);
    }

    if($action==='generate_monthly_invoices'){
        $year=filter_var($input['year']??date('Y'),FILTER_VALIDATE_INT);
        $month=filter_var($input['month']??date('n'),FILTER_VALIDATE_INT);
        if(!$year||$year<2020||$year>2100||!$month||$month<1||$month>12) financeJson(['success'=>false,'message'=>'Choose a valid billing month.'],422);
        $period=sprintf('%04d-%02d-01',$year,$month);
        $plans=$pdo->query("SELECT * FROM finance_fee_plans WHERE is_active=1 AND effective_from<=LAST_DAY('{$period}') AND (effective_until IS NULL OR effective_until>='{$period}')")->fetchAll(PDO::FETCH_ASSOC);
        $created=0;$pdo->beginTransaction();
        try{
            $students=$pdo->query("SELECT id,class_id,COALESCE(NULLIF(arabic_class_id,0),arabia_class_id) arabic_class_id FROM students WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
            $insert=$pdo->prepare(
                "INSERT IGNORE INTO finance_fee_invoices (invoice_number,student_id,fee_plan_id,billing_year,billing_month,issued_on,due_on,amount_due,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            foreach($students as $student){
                foreach($plans as $plan){
                    $applies=$plan['scope']==='school'
                        ||($plan['scope']==='student'&&(int)$plan['student_id']===(int)$student['id'])
                        ||($plan['scope']==='class'&&$plan['class_type']==='conventional'&&(int)$plan['class_id']===(int)$student['class_id'])
                        ||($plan['scope']==='class'&&$plan['class_type']==='arabic'&&(int)$plan['class_id']===(int)$student['arabic_class_id']);
                    if(!$applies)continue;
                    $dueDay=min((int)$plan['due_day'],(int)date('t',strtotime($period)));
                    $due=sprintf('%04d-%02d-%02d',$year,$month,$dueDay);
                    $insert->execute([financeNextNumber('INV'),(int)$student['id'],(int)$plan['id'],$year,$month,$period,$due,$plan['amount'],(int)$_SESSION['admin_id']]);
                    $created+=$insert->rowCount();
                }
            }
            financeAudit($pdo,'monthly_invoices_generated','invoice_batch',null,['year'=>$year,'month'=>$month,'created'=>$created]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        financeJson(['success'=>true,'created'=>$created,'message'=>"Created {$created} monthly invoices. Existing invoices were left unchanged."]);
    }

    if($action==='record_payment'){
        $studentId=(int)($input['student_id']??0);$amount=financeMoney($input['amount']??'');
        $method=(string)($input['method']??'bank_transfer');
        $allowed=['cash','bank_transfer','pos','online','other'];
        if($studentId<1||!in_array($method,$allowed,true))financeJson(['success'=>false,'message'=>'Choose a student, amount and valid payment method.'],422);
        $st=$pdo->prepare('SELECT id FROM students WHERE id=?');$st->execute([$studentId]);if(!$st->fetchColumn())financeJson(['success'=>false,'message'=>'Student not found.'],404);
        $reference=trim((string)($input['reference']??''))?:null;
        $paidAtRaw=(string)($input['paid_at']??'');$paidAt=DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i',$paidAtRaw);
        if(!$paidAt||$paidAt->format('Y-m-d\\TH:i')!==$paidAtRaw||$paidAt>new DateTimeImmutable())financeJson(['success'=>false,'message'=>'Choose a valid payment date and time that is not in the future.'],422);
        $paidAtValue=$paidAt->format('Y-m-d H:i:s');
        $status=$method==='cash'?'confirmed':'pending';
        $pdo->beginTransaction();
        try{
            $stmt=$pdo->prepare(
                'INSERT INTO finance_fee_payments (receipt_number,student_id,amount,method,reference,payer_name,notes,status,paid_at,recorded_by,verified_by,verified_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $actor=(int)$_SESSION['admin_id'];
            $stmt->execute([financeNextNumber('MZRCPT'),$studentId,$amount,$method,$reference,trim((string)($input['payer_name']??''))?:null,trim((string)($input['notes']??''))?:null,$status,$paidAtValue,$actor,$status==='confirmed'?$actor:null,$status==='confirmed'?date('Y-m-d H:i:s'):null]);
            $paymentId=(int)$pdo->lastInsertId();
            if($status==='confirmed'){
                financeAllocatePayment($pdo,$paymentId,$studentId,$amount);
                $journal=financePostJournal($pdo,'fee_payment',$paymentId,'Student fee payment',substr($paidAtValue,0,10),[
                    ['account_id'=>financeAccountId($pdo,$method==='cash'?'1000':'1010'),'debit'=>$amount,'credit'=>'0.00','description'=>'Funds received'],
                    ['account_id'=>financeAccountId($pdo,'4000'),'debit'=>'0.00','credit'=>$amount,'description'=>'Student fee income']
                ]);
            }
            financeAudit($pdo,'fee_payment_recorded','fee_payment',$paymentId,['amount'=>$amount,'method'=>$method,'status'=>$status]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        financeJson(['success'=>true,'receipt_id'=>$paymentId,'status'=>$status,'message'=>$status==='confirmed'?'Cash payment recorded and allocated.':'Payment recorded as pending verification.'],201);
    }

    if($action==='verify_payment'){
        $id=(int)($input['payment_id']??0);$decision=(string)($input['decision']??'');
        if($id<1||!in_array($decision,['confirm','reject'],true))financeJson(['success'=>false,'message'=>'Invalid payment verification request.'],422);
        $pdo->beginTransaction();
        try{
            $st=$pdo->prepare('SELECT * FROM finance_fee_payments WHERE id=? FOR UPDATE');$st->execute([$id]);$payment=$st->fetch(PDO::FETCH_ASSOC);
            if(!$payment||$payment['status']!=='pending')financeJson(['success'=>false,'message'=>'Only pending payments can be verified.'],409);
            $new=$decision==='confirm'?'confirmed':'rejected';$actor=(int)$_SESSION['admin_id'];
            $up=$pdo->prepare('UPDATE finance_fee_payments SET status=?,verified_by=?,verified_at=NOW() WHERE id=?');$up->execute([$new,$actor,$id]);
            if($new==='confirmed'){
                financeAllocatePayment($pdo,$id,(int)$payment['student_id'],(string)$payment['amount']);
                financePostJournal($pdo,'fee_payment',$id,'Verified student fee payment',substr((string)$payment['paid_at'],0,10),[
                    ['account_id'=>financeAccountId($pdo,'1010'),'debit'=>$payment['amount'],'credit'=>'0.00'],
                    ['account_id'=>financeAccountId($pdo,'4000'),'debit'=>'0.00','credit'=>$payment['amount']]
                ]);
            }
            financeAudit($pdo,'fee_payment_'.$new,'fee_payment',$id,[]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        financeJson(['success'=>true,'message'=>'Payment '.$new.'.']);
    }

    if($action==='record_income'){
        $category=trim((string)($input['category']??''));$description=trim((string)($input['description']??''));$amount=financeMoney($input['amount']??'');
        $date=(string)($input['received_date']??date('Y-m-d'));$method=(string)($input['payment_method']??'bank_transfer');
        if($category===''||$description===''||!financeValidDate($date)||!in_array($method,['cash','bank_transfer','pos','other'],true))financeJson(['success'=>false,'message'=>'Complete the income fields correctly.'],422);
        $status=$method==='cash'?'confirmed':'pending';$pdo->beginTransaction();
        try{
            $stmt=$pdo->prepare('INSERT INTO finance_other_income (income_number,category,description,payer_name,amount,payment_method,reference,received_date,status,recorded_by,verified_by,verified_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $actor=(int)$_SESSION['admin_id'];$stmt->execute([financeNextNumber('INC'),$category,$description,trim((string)($input['payer_name']??''))?:null,$amount,$method,trim((string)($input['reference']??''))?:null,$date,$status,$actor,$status==='confirmed'?$actor:null,$status==='confirmed'?date('Y-m-d H:i:s'):null]);
            $id=(int)$pdo->lastInsertId();
            if($status==='confirmed'){
                $journal=financePostJournal($pdo,'adjustment',$id,'Other school income: '.$category,$date,[
                    ['account_id'=>financeAccountId($pdo,$method==='cash'?'1000':'1010'),'debit'=>$amount,'credit'=>'0.00'],
                    ['account_id'=>financeAccountId($pdo,'4100'),'debit'=>'0.00','credit'=>$amount]
                ]);
                $pdo->prepare('UPDATE finance_other_income SET journal_id=? WHERE id=?')->execute([$journal,$id]);
            }
            financeAudit($pdo,'other_income_recorded','other_income',$id,['amount'=>$amount,'category'=>$category,'status'=>$status]);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        financeJson(['success'=>true,'id'=>$id,'status'=>$status,'message'=>$status==='confirmed'?'Cash income recorded and posted.':'Income recorded pending verification.'],201);
    }

    if($action==='verify_income'){
        $id=(int)($input['income_id']??0);$decision=(string)($input['decision']??'');
        if($id<1||!in_array($decision,['confirm','reject'],true))financeJson(['success'=>false,'message'=>'Invalid income verification request.'],422);
        $pdo->beginTransaction();
        try{
            $st=$pdo->prepare('SELECT * FROM finance_other_income WHERE id=? FOR UPDATE');$st->execute([$id]);$income=$st->fetch(PDO::FETCH_ASSOC);
            if(!$income||$income['status']!=='pending')financeJson(['success'=>false,'message'=>'Only pending income records can be verified.'],409);
            $status=$decision==='confirm'?'confirmed':'rejected';$actor=(int)$_SESSION['admin_id'];
            $pdo->prepare('UPDATE finance_other_income SET status=?,verified_by=?,verified_at=NOW() WHERE id=?')->execute([$status,$actor,$id]);
            if($status==='confirmed'){
                $journal=financePostJournal($pdo,'adjustment',$id,'Verified other school income: '.$income['category'],$income['received_date'],[
                    ['account_id'=>financeAccountId($pdo,$income['payment_method']==='cash'?'1000':'1010'),'debit'=>$income['amount'],'credit'=>'0.00'],
                    ['account_id'=>financeAccountId($pdo,'4100'),'debit'=>'0.00','credit'=>$income['amount']]
                ]);
                $pdo->prepare('UPDATE finance_other_income SET journal_id=? WHERE id=?')->execute([$journal,$id]);
            }
            financeAudit($pdo,'other_income_'.$status,'other_income',$id,[]);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        financeJson(['success'=>true,'message'=>'Income '.$status.'.']);
    }

    if($action==='create_expense'){
        $category=trim((string)($input['category']??''));$description=trim((string)($input['description']??''));$amount=financeMoney($input['amount']??'');
        $date=(string)($input['expense_date']??date('Y-m-d'));$method=(string)($input['payment_method']??'bank_transfer');
        if($category===''||$description===''||!financeValidDate($date)||!in_array($method,['cash','bank_transfer','pos','other'],true))financeJson(['success'=>false,'message'=>'Complete the expense fields correctly.'],422);
        $st=$pdo->prepare('INSERT INTO finance_expenses (expense_number,category,description,vendor_name,amount,payment_method,reference,expense_date,recorded_by) VALUES (?,?,?,?,?,?,?,?,?)');
        $st->execute([financeNextNumber('EXP'),$category,$description,trim((string)($input['vendor_name']??''))?:null,$amount,$method,trim((string)($input['reference']??''))?:null,$date,(int)$_SESSION['admin_id']]);
        $id=(int)$pdo->lastInsertId();financeAudit($pdo,'expense_submitted','expense',$id,['amount'=>$amount,'category'=>$category]);
        financeJson(['success'=>true,'id'=>$id,'message'=>'Expense submitted for approval.'],201);
    }

    if($action==='approve_expense'){
        $id=(int)($input['expense_id']??0);if($id<1)financeJson(['success'=>false,'message'=>'Invalid expense.'],422);
        $pdo->beginTransaction();
        try{
            $st=$pdo->prepare('SELECT * FROM finance_expenses WHERE id=? FOR UPDATE');$st->execute([$id]);$expense=$st->fetch(PDO::FETCH_ASSOC);
            if(!$expense||$expense['status']!=='pending')financeJson(['success'=>false,'message'=>'Only pending expenses can be approved.'],409);
            $expenseAccount=financeAccountId($pdo,'5000');$cashAccount=financeAccountId($pdo,$expense['payment_method']==='cash'?'1000':'1010');
            $journal=financePostJournal($pdo,'expense',$id,'Expense: '.$expense['category'],$expense['expense_date'],[
                ['account_id'=>$expenseAccount,'debit'=>$expense['amount'],'credit'=>'0.00','description'=>$expense['description']],
                ['account_id'=>$cashAccount,'debit'=>'0.00','credit'=>$expense['amount'],'description'=>'Expense payment']
            ]);
            $up=$pdo->prepare('UPDATE finance_expenses SET status="paid",approved_by=?,approved_at=NOW(),journal_id=? WHERE id=?');$up->execute([(int)$_SESSION['admin_id'],$journal,$id]);
            financeAudit($pdo,'expense_approved','expense',$id,['journal_id'=>$journal]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        financeJson(['success'=>true,'message'=>'Expense approved and posted to the finance ledger.']);
    }

    financeJson(['success'=>false,'message'=>'Unknown finance action.'],404);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('MARKAZ finance API: '.$error->getMessage());
    financeJson(['success'=>false,'message'=>'The finance operation could not be completed. Check the audit and server logs.'],500);
}

function financeAllocatePayment(PDO $pdo,int $paymentId,int $studentId,string $amount):void
{
    $remainingCents=financeToCents($amount);
    $stmt=$pdo->prepare(
        "SELECT i.id,(i.amount_due-i.discount_amount-COALESCE(a.paid,0)) balance
         FROM finance_fee_invoices i
         LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM finance_fee_allocations GROUP BY invoice_id) a ON a.invoice_id=i.id
         WHERE i.student_id=? AND i.status IN ('unpaid','partial') AND (i.amount_due-i.discount_amount-COALESCE(a.paid,0))>0
         ORDER BY i.due_on,i.id FOR UPDATE"
    );
    $stmt->execute([$studentId]);$invoices=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $insert=$pdo->prepare('INSERT INTO finance_fee_allocations (payment_id,invoice_id,amount) VALUES (?,?,?)');
    $update=$pdo->prepare(
        "UPDATE finance_fee_invoices i SET status=CASE
          WHEN (i.amount_due-i.discount_amount-(SELECT COALESCE(SUM(a.amount),0) FROM finance_fee_allocations a WHERE a.invoice_id=i.id))<=0 THEN 'paid'
          ELSE 'partial' END WHERE id=?"
    );
    foreach($invoices as $invoice){
        if($remainingCents<=0)break;
        $balanceCents=financeToCents((string)$invoice['balance']);
        $partCents=min($remainingCents,$balanceCents);
        $part=financeFromCents($partCents);
        $insert->execute([$paymentId,(int)$invoice['id'],$part]);$update->execute([(int)$invoice['id']]);
        $remainingCents-=$partCents;
    }
    if($remainingCents>0){ throw new DomainException('The payment exceeds the student’s outstanding invoices. Generate the monthly invoice or correct the amount before confirming.'); }
}
