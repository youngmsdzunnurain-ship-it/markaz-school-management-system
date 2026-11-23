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
                    COALESCE(SUM(CASE WHEN status='confirmed' AND DATE(paid_at)=CURDATE() THEN amount ELSE 0 END),0)
                    -(SELECT COALESCE(SUM(amount),0) FROM finance_fee_refunds WHERE status='approved' AND DATE(reviewed_at)=CURDATE()) AS fees_today,
                    (SELECT COALESCE(SUM(amount),0) FROM finance_expenses WHERE status='paid' AND expense_date=CURDATE()) AS expenses_today,
                    (SELECT COALESCE(SUM(amount),0) FROM finance_other_income WHERE status='confirmed' AND received_date=CURDATE()) AS other_income_today,
                    ((SELECT COUNT(*) FROM finance_fee_invoices WHERE status IN ('unpaid','partial'))+(SELECT COUNT(*) FROM finance_term_fee_invoices WHERE status IN ('unpaid','partial'))) AS open_invoices,
                    ((SELECT COALESCE(SUM(i.amount_due-i.discount_amount-COALESCE(a.paid,0)+COALESCE(r.refunded,0)),0)
                      FROM finance_fee_invoices i
                      LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM finance_fee_allocations GROUP BY invoice_id) a ON a.invoice_id=i.id
                      LEFT JOIN (SELECT a2.invoice_id,SUM(x.amount) refunded FROM finance_fee_refund_allocations x JOIN finance_fee_refunds rf ON rf.id=x.refund_id JOIN finance_fee_allocations a2 ON x.allocation_type='monthly' AND a2.id=x.source_allocation_id WHERE rf.status='approved' GROUP BY a2.invoice_id) r ON r.invoice_id=i.id
                      WHERE i.status IN ('unpaid','partial'))
                     +(SELECT COALESCE(SUM(i.amount_due-COALESCE(p.paid,0)+COALESCE(r.refunded,0)),0)
                      FROM finance_term_fee_invoices i
                      LEFT JOIN (SELECT term_invoice_id,SUM(amount) paid FROM finance_term_fee_payments GROUP BY term_invoice_id) p ON p.term_invoice_id=i.id
                      LEFT JOIN (SELECT a2.term_invoice_id,SUM(x.amount) refunded FROM finance_fee_refund_allocations x JOIN finance_fee_refunds rf ON rf.id=x.refund_id JOIN finance_term_fee_payments a2 ON x.allocation_type='term' AND a2.id=x.source_allocation_id WHERE rf.status='approved' GROUP BY a2.term_invoice_id) r ON r.term_invoice_id=i.id
                      WHERE i.status IN ('unpaid','partial'))) AS outstanding
                 FROM finance_fee_payments"
            )->fetch(PDO::FETCH_ASSOC);
            $month = $pdo->query(
                "SELECT COALESCE(SUM(CASE WHEN status='confirmed' THEN amount ELSE 0 END),0)
                 -(SELECT COALESCE(SUM(amount),0) FROM finance_fee_refunds WHERE status='approved' AND YEAR(reviewed_at)=YEAR(CURDATE()) AND MONTH(reviewed_at)=MONTH(CURDATE()))
                 FROM finance_fee_payments WHERE YEAR(paid_at)=YEAR(CURDATE()) AND MONTH(paid_at)=MONTH(CURDATE())"
            )->fetchColumn();
            $recent = $pdo->query(
                "SELECT p.id,p.receipt_number,p.amount,p.method,p.status,p.paid_at,
                        s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name
                 FROM finance_fee_payments p INNER JOIN students s ON s.id=p.student_id
                 ORDER BY p.created_at DESC LIMIT 8"
            )->fetchAll(PDO::FETCH_ASSOC);

            // Six-month trend from confirmed receipts and paid expenses only.
            $trendStart = date('Y-m-01', strtotime('-5 months'));
            $trend = [];
            for ($offset = 5; $offset >= 0; $offset--) {
                $key = date('Y-m', strtotime("-{$offset} months"));
                $trend[$key] = ['month' => $key, 'fees' => 0.0, 'other_income' => 0.0, 'expenses' => 0.0];
            }
            $trendQueries = [
                'fees' => ["SELECT DATE_FORMAT(paid_at,'%Y-%m') period,SUM(amount) total FROM finance_fee_payments WHERE status='confirmed' AND paid_at>=? GROUP BY period", $trendStart],
                'other_income' => ["SELECT DATE_FORMAT(received_date,'%Y-%m') period,SUM(amount) total FROM finance_other_income WHERE status='confirmed' AND received_date>=? GROUP BY period", $trendStart],
                'expenses' => ["SELECT DATE_FORMAT(expense_date,'%Y-%m') period,SUM(amount) total FROM finance_expenses WHERE status='paid' AND expense_date>=? GROUP BY period", $trendStart],
            ];
            foreach ($trendQueries as $key => [$sql, $start]) {
                $statement = $pdo->prepare($sql);
                $statement->execute([$start]);
                foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (isset($trend[$row['period']])) $trend[$row['period']][$key] = (float)$row['total'];
                }
            }
            $refundTrend=$pdo->prepare("SELECT DATE_FORMAT(reviewed_at,'%Y-%m') period,SUM(amount) total FROM finance_fee_refunds WHERE status='approved' AND reviewed_at>=? GROUP BY period");
            $refundTrend->execute([$trendStart]);
            foreach($refundTrend->fetchAll(PDO::FETCH_ASSOC) as $row){if(isset($trend[$row['period']]))$trend[$row['period']]['fees']-=(float)$row['total'];}
            $incomeMix = [
                // Use the exact same six calendar-month window as the bar chart.
                'fees' => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM finance_fee_payments WHERE status='confirmed' AND paid_at>='{$trendStart}'")->fetchColumn()-(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM finance_fee_refunds WHERE status='approved' AND reviewed_at>='{$trendStart}'")->fetchColumn(),
                'other_income' => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM finance_other_income WHERE status='confirmed' AND received_date>='{$trendStart}'")->fetchColumn(),
            ];
            financeJson(['success'=>true,'summary'=>$today,'fees_this_month'=>(string)$month,'recent_payments'=>$recent,'monthly_trend'=>array_values($trend),'income_mix'=>$incomeMix]);
        }
        if ($action === 'payments') {
            $rows=$pdo->query(
                "SELECT p.id,p.receipt_number,p.amount,p.method,p.reference,p.status,p.paid_at,p.payer_name,
                        COALESCE((SELECT SUM(r.amount) FROM finance_fee_refunds r WHERE r.payment_id=p.id AND r.status='approved'),0) refunded_amount,
                        COALESCE((SELECT SUM(r.amount) FROM finance_fee_refunds r WHERE r.payment_id=p.id AND r.status IN ('pending','approved')),0) reserved_refund_amount,
                        s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name
                 FROM finance_fee_payments p INNER JOIN students s ON s.id=p.student_id
                 ORDER BY p.created_at DESC LIMIT 300"
            )->fetchAll(PDO::FETCH_ASSOC);
            financeJson(['success'=>true,'payments'=>$rows]);
        }
        if ($action === 'refunds') {
            $rows=$pdo->query("SELECT r.*,p.receipt_number,p.amount payment_amount,p.method,p.student_id,s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name,a.name recorded_by_name,v.name reviewed_by_name FROM finance_fee_refunds r JOIN finance_fee_payments p ON p.id=r.payment_id JOIN students s ON s.id=p.student_id JOIN admins a ON a.id=r.recorded_by LEFT JOIN admins v ON v.id=r.reviewed_by ORDER BY FIELD(r.status,'pending','approved','rejected'),r.created_at DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
            financeJson(['success'=>true,'refunds'=>$rows]);
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
            $refunds=$pdo->prepare("SELECT refund_number,amount,reason,status,created_at,reviewed_at FROM finance_fee_refunds WHERE payment_id=? ORDER BY created_at");
            $refunds->execute([$id]);
            financeJson(['success'=>true,'payment'=>$payment,'allocations'=>$alloc->fetchAll(PDO::FETCH_ASSOC),'refunds'=>$refunds->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($action === 'audit') {
            $from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??date('Y-m-d'));
            if(!financeValidDate($from)||!financeValidDate($to)||$from>$to)financeJson(['success'=>false,'message'=>'Choose a valid audit date range.'],422);
            $stmt=$pdo->prepare("SELECT l.id,l.action,l.entity_type,l.entity_id,l.details_json,l.ip_address,l.created_at,a.name actor_name FROM finance_audit_logs l LEFT JOIN admins a ON a.id=l.actor_id WHERE DATE(l.created_at) BETWEEN ? AND ? ORDER BY l.id DESC LIMIT 500");
            $stmt->execute([$from,$to]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
            financeJson(['success'=>true,'from'=>$from,'to'=>$to,'events'=>$rows,'count'=>count($rows)]);
        }
        if ($action === 'reports') {
            $from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??date('Y-m-d'));
            if(!financeValidDate($from)||!financeValidDate($to)||$from>$to)financeJson(['success'=>false,'message'=>'Choose a valid report date range.'],422);
            $fees=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_fee_payments WHERE status='confirmed' AND DATE(paid_at) BETWEEN ? AND ?");
            $fees->execute([$from,$to]);$refunds=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_fee_refunds WHERE status='approved' AND DATE(reviewed_at) BETWEEN ? AND ?");$refunds->execute([$from,$to]);$refundTotal=(string)$refunds->fetchColumn();$expenses=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_expenses WHERE status='paid' AND expense_date BETWEEN ? AND ?");
            $expenses->execute([$from,$to]);
            $otherIncome=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_other_income WHERE status='confirmed' AND received_date BETWEEN ? AND ?");
            $otherIncome->execute([$from,$to]);
            $categories=$pdo->prepare("SELECT category,COUNT(*) transaction_count,SUM(amount) total FROM finance_expenses WHERE status='paid' AND expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
            $categories->execute([$from,$to]);
            $pending=$pdo->prepare("SELECT COUNT(*),COALESCE(SUM(amount),0) FROM finance_fee_payments WHERE status='pending' AND DATE(paid_at) BETWEEN ? AND ?");
            $pending->execute([$from,$to]);$pendingRow=$pending->fetch(PDO::FETCH_NUM);
            $feeTotal=(string)$fees->fetchColumn();$feesNet=financeFromCents(financeToCents($feeTotal)-financeToCents($refundTotal));$expenseTotal=(string)$expenses->fetchColumn();$otherIncomeTotal=(string)$otherIncome->fetchColumn();
            financeJson(['success'=>true,'from'=>$from,'to'=>$to,'fees_collected'=>$feeTotal,'refunds_approved'=>$refundTotal,'fees_net'=>$feesNet,'other_income'=>$otherIncomeTotal,'expenses_paid'=>$expenseTotal,'net_movement'=>financeFromCents(financeToCents($feesNet)+financeToCents($otherIncomeTotal)-financeToCents($expenseTotal)),'pending_payment_count'=>(int)$pendingRow[0],'pending_payment_amount'=>(string)$pendingRow[1],'expense_categories'=>$categories->fetchAll(PDO::FETCH_ASSOC)]);
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
        if ($action === 'student_statement') {
            $admission = trim((string)($_GET['admission_number'] ?? ''));
            if ($admission === '' || mb_strlen($admission) > 80) financeJson(['success'=>false,'message'=>'Enter a valid admission number.'],422);
            $studentQuery=$pdo->prepare("SELECT id,admission_number,CONCAT_WS(' ',first_name,middle_name,last_name) student_name,class_id,COALESCE(NULLIF(arabic_class_id,0),arabia_class_id) arabic_class_id FROM students WHERE admission_number=? LIMIT 1");
            $studentQuery->execute([$admission]);$student=$studentQuery->fetch(PDO::FETCH_ASSOC);
            if(!$student)financeJson(['success'=>false,'message'=>'No student was found with that admission number.'],404);
            $monthly=$pdo->prepare("SELECT i.invoice_number,'Monthly' invoice_type,CONCAT(LPAD(i.billing_month,2,'0'),'/',i.billing_year) period_label,i.issued_on,i.due_on,i.amount_due base_amount,i.discount_amount,(COALESCE(a.paid,0)-COALESCE(r.refunded,0)) paid_amount,(i.amount_due-i.discount_amount-COALESCE(a.paid,0)+COALESCE(r.refunded,0)) balance,i.status FROM finance_fee_invoices i LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM finance_fee_allocations GROUP BY invoice_id) a ON a.invoice_id=i.id LEFT JOIN (SELECT a2.invoice_id,SUM(x.amount) refunded FROM finance_fee_refund_allocations x JOIN finance_fee_refunds r ON r.id=x.refund_id JOIN finance_fee_allocations a2 ON x.allocation_type='monthly' AND a2.id=x.source_allocation_id WHERE r.status='approved' GROUP BY a2.invoice_id) r ON r.invoice_id=i.id WHERE i.student_id=? ORDER BY i.due_on,i.id");
            $monthly->execute([(int)$student['id']]);$monthlyRows=$monthly->fetchAll(PDO::FETCH_ASSOC);
            $term=$pdo->prepare("SELECT i.invoice_number,'Term' invoice_type,CONCAT(ac.session_name,' · ',i.term) period_label,i.created_at issued_on,i.due_on,i.base_amount,i.discount_amount,(COALESCE(p.paid,0)-COALESCE(r.refunded,0)) paid_amount,(i.amount_due-COALESCE(p.paid,0)+COALESCE(r.refunded,0)) balance,i.status FROM finance_term_fee_invoices i JOIN finance_academic_sessions ac ON ac.id=i.session_id LEFT JOIN (SELECT term_invoice_id,SUM(amount) paid FROM finance_term_fee_payments GROUP BY term_invoice_id) p ON p.term_invoice_id=i.id LEFT JOIN (SELECT a2.term_invoice_id,SUM(x.amount) refunded FROM finance_fee_refund_allocations x JOIN finance_fee_refunds r ON r.id=x.refund_id JOIN finance_term_fee_payments a2 ON x.allocation_type='term' AND a2.id=x.source_allocation_id WHERE r.status='approved' GROUP BY a2.term_invoice_id) r ON r.term_invoice_id=i.id WHERE i.student_id=? ORDER BY i.due_on,i.id");
            $term->execute([(int)$student['id']]);$termRows=$term->fetchAll(PDO::FETCH_ASSOC);
            $payments=$pdo->prepare("SELECT p.receipt_number,p.amount,p.method,p.reference,p.status,p.paid_at,p.payer_name,COALESCE((SELECT SUM(r.amount) FROM finance_fee_refunds r WHERE r.payment_id=p.id AND r.status='approved'),0) refunded_amount FROM finance_fee_payments p WHERE p.student_id=? ORDER BY paid_at,id");
            $payments->execute([(int)$student['id']]);$paymentRows=$payments->fetchAll(PDO::FETCH_ASSOC);
            $invoices=array_merge($monthlyRows,$termRows);
            $totals=['billed'=>0.0,'discounts'=>0.0,'paid_allocated'=>0.0,'outstanding'=>0.0];
            foreach($invoices as $invoice){$totals['billed']+=(float)$invoice['base_amount'];$totals['discounts']+=(float)$invoice['discount_amount'];$totals['paid_allocated']+=(float)$invoice['paid_amount'];$totals['outstanding']+=(float)$invoice['balance'];}
            financeJson(['success'=>true,'student'=>$student,'invoices'=>$invoices,'payments'=>$paymentRows,'totals'=>$totals]);
        }
        if ($action === 'arrears') {
            $rows=$pdo->query(
                "SELECT x.student_id,x.admission_number,x.student_name,x.class_name,COUNT(*) open_invoices,
                        SUM(x.balance) outstanding,MAX(x.days_overdue) max_days_overdue,
                        SUM(CASE WHEN x.days_overdue BETWEEN 1 AND 30 THEN x.balance ELSE 0 END) overdue_1_30,
                        SUM(CASE WHEN x.days_overdue BETWEEN 31 AND 60 THEN x.balance ELSE 0 END) overdue_31_60,
                        SUM(CASE WHEN x.days_overdue BETWEEN 61 AND 90 THEN x.balance ELSE 0 END) overdue_61_90,
                        SUM(CASE WHEN x.days_overdue>90 THEN x.balance ELSE 0 END) overdue_over_90
                 FROM (
                    SELECT i.student_id,s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name,
                           COALESCE(c.name,ac.name,'Unassigned') class_name,
                           (i.amount_due-i.discount_amount-COALESCE(a.paid,0)+COALESCE(r.refunded,0)) balance,
                           GREATEST(DATEDIFF(CURDATE(),i.due_on),0) days_overdue
                    FROM finance_fee_invoices i JOIN students s ON s.id=i.student_id
                    LEFT JOIN classes c ON c.id=s.class_id
                    LEFT JOIN arabic_classes ac ON ac.id=COALESCE(NULLIF(s.arabic_class_id,0),s.arabia_class_id)
                    LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM finance_fee_allocations GROUP BY invoice_id) a ON a.invoice_id=i.id
                    LEFT JOIN (SELECT a2.invoice_id,SUM(x.amount) refunded FROM finance_fee_refund_allocations x JOIN finance_fee_refunds rf ON rf.id=x.refund_id JOIN finance_fee_allocations a2 ON x.allocation_type='monthly' AND a2.id=x.source_allocation_id WHERE rf.status='approved' GROUP BY a2.invoice_id) r ON r.invoice_id=i.id
                    WHERE i.status IN ('unpaid','partial') AND i.status<>'void'
                    UNION ALL
                    SELECT i.student_id,s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name,
                           COALESCE(c.name,'Unassigned') class_name,
                           (i.amount_due-COALESCE(p.paid,0)+COALESCE(r.refunded,0)) balance,
                           GREATEST(DATEDIFF(CURDATE(),i.due_on),0) days_overdue
                    FROM finance_term_fee_invoices i JOIN students s ON s.id=i.student_id
                    LEFT JOIN classes c ON c.id=i.class_id
                    LEFT JOIN (SELECT term_invoice_id,SUM(amount) paid FROM finance_term_fee_payments GROUP BY term_invoice_id) p ON p.term_invoice_id=i.id
                    LEFT JOIN (SELECT a2.term_invoice_id,SUM(x.amount) refunded FROM finance_fee_refund_allocations x JOIN finance_fee_refunds rf ON rf.id=x.refund_id JOIN finance_term_fee_payments a2 ON x.allocation_type='term' AND a2.id=x.source_allocation_id WHERE rf.status='approved' GROUP BY a2.term_invoice_id) r ON r.term_invoice_id=i.id
                    WHERE i.status IN ('unpaid','partial')
                 ) x WHERE x.balance>0
                 GROUP BY x.student_id,x.admission_number,x.student_name,x.class_name
                 ORDER BY outstanding DESC,student_name"
            )->fetchAll(PDO::FETCH_ASSOC);
            financeJson(['success'=>true,'students'=>$rows,'student_count'=>count($rows),'total_outstanding'=>array_sum(array_map(static fn($row)=>(float)$row['outstanding'],$rows))]);
        }
        if ($action === 'invoices') {
            $studentId=(int)($_GET['student_id']??0);
            $sql="SELECT i.*,s.admission_number,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) student_name,
                         COALESCE(a.paid,0) paid_amount,
                         (i.amount_due-i.discount_amount-COALESCE(a.paid,0)) balance
                  FROM finance_fee_invoices i INNER JOIN students s ON s.id=i.student_id
                  LEFT JOIN (SELECT invoice_id,SUM(amount) paid FROM finance_fee_allocations GROUP BY invoice_id) a ON a.invoice_id=i.id LEFT JOIN (SELECT a2.invoice_id,SUM(x.amount) refunded FROM finance_fee_refund_allocations x JOIN finance_fee_refunds r ON r.id=x.refund_id JOIN finance_fee_allocations a2 ON x.allocation_type='monthly' AND a2.id=x.source_allocation_id WHERE r.status='approved' GROUP BY a2.invoice_id) r ON r.invoice_id=i.id";
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
                "SELECT s.id,s.staff_number,s.full_name AS name,s.email,s.phone,s.job_title,
                        s.employment_date,s.is_active,p.base_salary,p.expected_arrival,p.attendance_mode,
                        p.lateness_rate_per_minute,p.effective_from
                 FROM finance_staff s
                 LEFT JOIN staff_salary_profiles p ON p.finance_staff_id=s.id
                    AND p.effective_from<=CURDATE()
                    AND (p.effective_until IS NULL OR p.effective_until>=CURDATE())
                 ORDER BY s.is_active DESC,s.full_name,s.id"
            )->fetchAll(PDO::FETCH_ASSOC);
            financeJson(['success'=>true,'staff'=>$rows]);
        }
        financeJson(['success'=>false,'message'=>'Unknown finance action.'],404);
    }

    if ($method !== 'POST') financeJson(['success'=>false,'message'=>'Method not allowed.'],405);
    financeRequireSafePost();
    $input=financeInput();
    $action=(string)($input['action']??'');

    if ($action === 'save_staff') {
        $name = trim((string)($input['full_name'] ?? ''));
        $number = strtoupper(trim((string)($input['staff_number'] ?? '')));
        $jobTitle = trim((string)($input['job_title'] ?? 'Teacher'));
        $phone = trim((string)($input['phone'] ?? '')) ?: null;
        $email = strtolower(trim((string)($input['email'] ?? ''))) ?: null;
        $employmentDate = trim((string)($input['employment_date'] ?? '')) ?: null;
        $notes = trim((string)($input['notes'] ?? '')) ?: null;
        if ($name === '' || mb_strlen($name) > 160 || $jobTitle === '' || mb_strlen($jobTitle) > 100
            || ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL))
            || ($employmentDate !== null && !financeValidDate($employmentDate)) || ($notes !== null && mb_strlen($notes) > 500)) {
            financeJson(['success'=>false,'message'=>'Enter a valid staff name, job title, contact and employment date.'],422);
        }
        if ($number === '') $number = 'MZ-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{0,39}$/', $number)) {
            financeJson(['success'=>false,'message'=>'Staff number may contain only letters, numbers, hyphens and underscores.'],422);
        }
        $stmt = $pdo->prepare('INSERT INTO finance_staff(staff_number,full_name,phone,email,job_title,employment_date,notes,created_by) VALUES(?,?,?,?,?,?,?,?)');
        try {
            $stmt->execute([$number,$name,$phone,$email,$jobTitle,$employmentDate,$notes,(int)$_SESSION['admin_id']]);
        } catch (PDOException $error) {
            if ((string)$error->getCode() === '23000') financeJson(['success'=>false,'message'=>'That Finance staff number is already in use.'],409);
            throw $error;
        }
        $id=(int)$pdo->lastInsertId();
        financeAudit($pdo,'finance_staff_created','finance_staff',$id,['staff_number'=>$number]);
        financeJson(['success'=>true,'id'=>$id,'message'=>'Finance staff member added. No academic login account was created.'],201);
    }
    if ($action === 'deactivate_staff') {
        $id=(int)($input['finance_staff_id']??0);
        if($id<1) financeJson(['success'=>false,'message'=>'Choose a valid Finance staff member.'],422);
        $stmt=$pdo->prepare('SELECT id,is_active FROM finance_staff WHERE id=? FOR UPDATE');
        $pdo->beginTransaction();
        try {
            $stmt->execute([$id]);$person=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$person) { $pdo->rollBack(); financeJson(['success'=>false,'message'=>'Finance staff member not found.'],404); }
            if((int)$person['is_active']===0) { $pdo->commit(); financeJson(['success'=>true,'message'=>'This Finance staff member is already inactive.']); }
            $pdo->prepare('UPDATE finance_staff SET is_active=0 WHERE id=?')->execute([$id]);
            $pdo->prepare('UPDATE staff_salary_profiles SET is_active=0,effective_until=CASE WHEN effective_until IS NULL OR effective_until>CURDATE() THEN CURDATE() ELSE effective_until END WHERE finance_staff_id=? AND is_active=1')->execute([$id]);
            financeAudit($pdo,'finance_staff_deactivated','finance_staff',$id,[]);
            $pdo->commit();
        } catch(Throwable $error) {
            if($pdo->inTransaction())$pdo->rollBack();
            throw $error;
        }
        financeJson(['success'=>true,'message'=>'Finance staff member deactivated. Historical attendance and payroll records were retained.']);
    }

    if($action==='create_fee_plan'){
        $name=trim((string)($input['name']??''));
        $feeCode=strtolower(trim((string)($input['fee_code']??'general')));
        $scope=(string)($input['scope']??'');
        $amount=financeMoney($input['amount']??'');
        $frequency=(string)($input['frequency']??'monthly');
        $dueDay=filter_var($input['due_day']??null,FILTER_VALIDATE_INT);
        $from=(string)($input['effective_from']??date('Y-m-01'));
        $until=trim((string)($input['effective_until']??'')) ?: null;
        $classType=$input['class_type']??null;
        $classId=(int)($input['class_id']??0);
        $studentId=(int)($input['student_id']??0);
        if($name===''||!preg_match('/^[a-z0-9][a-z0-9_-]{0,59}$/',$feeCode)||!in_array($scope,['school','class','student'],true)||$frequency!=='monthly'||!$dueDay||$dueDay<1||$dueDay>31||!financeValidDate($from)||($until!==null&&(!financeValidDate($until)||$until<$from))){
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
            'INSERT INTO finance_fee_plans (name,fee_code,description,scope,class_type,class_id,student_id,amount,frequency,due_day,effective_from,effective_until,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$name,$feeCode,trim((string)($input['description']??''))?:null,$scope,$scope==='class'?$classType:null,$scope==='class'?$classId:null,$scope==='student'?$studentId:null,$amount,$frequency,$dueDay,$from,$until,(int)$_SESSION['admin_id']]);
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
                "INSERT IGNORE INTO finance_fee_invoices (invoice_number,student_id,fee_plan_id,fee_code,billing_year,billing_month,issued_on,due_on,amount_due,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            foreach($students as $student){
                // Exactly one plan per fee category: student override > class > school.
                $applicablePlans=financeSelectApplicableFeePlans($plans,$student,$period);
                foreach($applicablePlans as $plan){
                    $dueDay=min((int)$plan['due_day'],(int)date('t',strtotime($period)));
                    $due=sprintf('%04d-%02d-%02d',$year,$month,$dueDay);
                    $insert->execute([financeNextNumber('INV'),(int)$student['id'],(int)$plan['id'],(string)($plan['fee_code']??'general'),$year,$month,$period,$due,$plan['amount'],(int)$_SESSION['admin_id']]);
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

    if($action==='request_refund'){
        $paymentId=(int)($input['payment_id']??0);$amount=financeMoney($input['amount']??'');$reason=trim((string)($input['reason']??''));
        if($paymentId<1||$reason===''||mb_strlen($reason)>500)financeJson(['success'=>false,'message'=>'Choose a payment and provide a clear refund reason.'],422);
        $pdo->beginTransaction();
        try{
            $st=$pdo->prepare("SELECT id,amount,status FROM finance_fee_payments WHERE id=? FOR UPDATE");$st->execute([$paymentId]);$payment=$st->fetch(PDO::FETCH_ASSOC);
            if(!$payment||$payment['status']!=='confirmed')throw new DomainException('Only confirmed fee payments can be refunded.');
            $reserved=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_fee_refunds WHERE payment_id=? AND status IN ('pending','approved')");$reserved->execute([$paymentId]);
            if(financeToCents((string)$reserved->fetchColumn())+financeToCents($amount)>financeToCents((string)$payment['amount']))throw new DomainException('The requested refund exceeds the payment amount remaining after existing refund requests.');
            $actor=(int)$_SESSION['admin_id'];$stmt=$pdo->prepare("INSERT INTO finance_fee_refunds(refund_number,payment_id,amount,reason,status,recorded_by) VALUES(?,?,?,?,'pending',?)");
            $stmt->execute([financeNextNumber('MZRFND'),$paymentId,$amount,$reason,$actor]);$refundId=(int)$pdo->lastInsertId();
            financeAudit($pdo,'fee_refund_requested','fee_refund',$refundId,['payment_id'=>$paymentId,'amount'=>$amount]);
            $pdo->commit();
        }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();if($error instanceof DomainException)financeJson(['success'=>false,'message'=>$error->getMessage()],422);throw $error;}
        financeJson(['success'=>true,'refund_id'=>$refundId,'message'=>'Refund request submitted for independent approval.'],201);
    }
    if($action==='review_refund'){
        $refundId=(int)($input['refund_id']??0);$decision=(string)($input['decision']??'');
        if($refundId<1||!in_array($decision,['approve','reject'],true))financeJson(['success'=>false,'message'=>'Choose a valid refund decision.'],422);
        $pdo->beginTransaction();
        try{
            $st=$pdo->prepare("SELECT r.*,p.amount payment_amount,p.method,p.paid_at FROM finance_fee_refunds r JOIN finance_fee_payments p ON p.id=r.payment_id WHERE r.id=? FOR UPDATE");$st->execute([$refundId]);$refund=$st->fetch(PDO::FETCH_ASSOC);
            $actor=(int)$_SESSION['admin_id'];
            if(!$refund||$refund['status']!=='pending')throw new DomainException('Only pending refund requests can be reviewed.');
            if((int)$refund['recorded_by']===$actor)throw new DomainException('The staff member who requested a refund cannot approve or reject it.');
            if($decision==='reject'){
                $pdo->prepare("UPDATE finance_fee_refunds SET status='rejected',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$actor,$refundId]);
                financeAudit($pdo,'fee_refund_rejected','fee_refund',$refundId,[]);
                $pdo->commit();financeJson(['success'=>true,'message'=>'Refund request rejected and retained in the audit history.']);
            }
            $approved=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_fee_refunds WHERE payment_id=? AND status='approved'");$approved->execute([(int)$refund['payment_id']]);
            if(financeToCents((string)$approved->fetchColumn())+financeToCents((string)$refund['amount'])>financeToCents((string)$refund['payment_amount']))throw new DomainException('Approved refunds cannot exceed the original confirmed payment.');
            $remaining=financeToCents((string)$refund['amount']);
            $monthly=$pdo->prepare("SELECT a.id,a.invoice_id,a.amount,i.due_on,COALESCE((SELECT SUM(x.amount) FROM finance_fee_refund_allocations x JOIN finance_fee_refunds r ON r.id=x.refund_id WHERE r.status='approved' AND x.allocation_type='monthly' AND x.source_allocation_id=a.id),0) refunded FROM finance_fee_allocations a JOIN finance_fee_invoices i ON i.id=a.invoice_id WHERE a.payment_id=? ORDER BY i.due_on DESC,a.id DESC FOR UPDATE");
            $monthly->execute([(int)$refund['payment_id']]);
            $term=$pdo->prepare("SELECT a.id,a.term_invoice_id invoice_id,a.amount,i.due_on,COALESCE((SELECT SUM(x.amount) FROM finance_fee_refund_allocations x JOIN finance_fee_refunds r ON r.id=x.refund_id WHERE r.status='approved' AND x.allocation_type='term' AND x.source_allocation_id=a.id),0) refunded FROM finance_term_fee_payments a JOIN finance_term_fee_invoices i ON i.id=a.term_invoice_id WHERE a.payment_id=? ORDER BY i.due_on DESC,a.id DESC FOR UPDATE");
            $term->execute([(int)$refund['payment_id']]);
            $insertAllocation=$pdo->prepare("INSERT INTO finance_fee_refund_allocations(refund_id,allocation_type,source_allocation_id,amount) VALUES(?,?,?,?)");
            $monthlyRows=$monthly->fetchAll(PDO::FETCH_ASSOC);
            foreach($monthlyRows as $allocation){if($remaining<=0)break;$available=financeToCents((string)$allocation['amount'])-financeToCents((string)$allocation['refunded']);$part=min($remaining,max(0,$available));if($part<=0)continue;$insertAllocation->execute([$refundId,'monthly',(int)$allocation['id'],financeFromCents($part)]);$remaining-=$part;}
            $termRows=$term->fetchAll(PDO::FETCH_ASSOC);
            foreach($termRows as $allocation){if($remaining<=0)break;$available=financeToCents((string)$allocation['amount'])-financeToCents((string)$allocation['refunded']);$part=min($remaining,max(0,$available));if($part<=0)continue;$insertAllocation->execute([$refundId,'term',(int)$allocation['id'],financeFromCents($part)]);$remaining-=$part;}
            if($remaining>0)throw new DomainException('The refund amount is greater than the remaining allocated payment balance.');
            $pdo->prepare("UPDATE finance_fee_refunds SET status='approved',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$actor,$refundId]);
            $refreshMonthly=$pdo->prepare("UPDATE finance_fee_invoices i SET status=CASE WHEN (i.amount_due-i.discount_amount-(COALESCE((SELECT SUM(a.amount) FROM finance_fee_allocations a WHERE a.invoice_id=i.id),0)-COALESCE((SELECT SUM(x.amount) FROM finance_fee_refund_allocations x JOIN finance_fee_refunds r ON r.id=x.refund_id WHERE r.status='approved' AND x.allocation_type='monthly' AND x.source_allocation_id IN (SELECT a2.id FROM finance_fee_allocations a2 WHERE a2.invoice_id=i.id)),0)))<=0 THEN 'paid' WHEN (COALESCE((SELECT SUM(a.amount) FROM finance_fee_allocations a WHERE a.invoice_id=i.id),0)-COALESCE((SELECT SUM(x.amount) FROM finance_fee_refund_allocations x JOIN finance_fee_refunds r ON r.id=x.refund_id WHERE r.status='approved' AND x.allocation_type='monthly' AND x.source_allocation_id IN (SELECT a2.id FROM finance_fee_allocations a2 WHERE a2.invoice_id=i.id)),0))>0 THEN 'partial' ELSE 'unpaid' END WHERE i.id=?");
            $refreshTerm=$pdo->prepare("UPDATE finance_term_fee_invoices i SET status=CASE WHEN (i.amount_due-(COALESCE((SELECT SUM(a.amount) FROM finance_term_fee_payments a WHERE a.term_invoice_id=i.id),0)-COALESCE((SELECT SUM(x.amount) FROM finance_fee_refund_allocations x JOIN finance_fee_refunds r ON r.id=x.refund_id WHERE r.status='approved' AND x.allocation_type='term' AND x.source_allocation_id IN (SELECT a2.id FROM finance_term_fee_payments a2 WHERE a2.term_invoice_id=i.id)),0)))<=0 THEN 'paid' WHEN (COALESCE((SELECT SUM(a.amount) FROM finance_term_fee_payments a WHERE a.term_invoice_id=i.id),0)-COALESCE((SELECT SUM(x.amount) FROM finance_fee_refund_allocations x JOIN finance_fee_refunds r ON r.id=x.refund_id WHERE r.status='approved' AND x.allocation_type='term' AND x.source_allocation_id IN (SELECT a2.id FROM finance_term_fee_payments a2 WHERE a2.term_invoice_id=i.id)),0))>0 THEN 'partial' ELSE 'unpaid' END WHERE i.id=?");
            foreach($monthlyRows as $allocation)$refreshMonthly->execute([(int)$allocation['invoice_id']]);
            foreach($termRows as $allocation)$refreshTerm->execute([(int)$allocation['invoice_id']]);
            $account=$refund['method']==='cash'?'1000':'1010';
            $journal=financePostJournal($pdo,'adjustment',$refundId,'Student fee refund',date('Y-m-d'),[
                ['account_id'=>financeAccountId($pdo,'4000'),'debit'=>$refund['amount'],'credit'=>'0.00','description'=>'Approved student fee refund'],
                ['account_id'=>financeAccountId($pdo,$account),'debit'=>'0.00','credit'=>$refund['amount'],'description'=>'Refund paid from school funds']
            ]);
            $pdo->prepare("UPDATE finance_fee_refunds SET journal_id=? WHERE id=?")->execute([$journal,$refundId]);
            financeAudit($pdo,'fee_refund_approved','fee_refund',$refundId,['payment_id'=>(int)$refund['payment_id'],'amount'=>$refund['amount'],'journal_id'=>$journal]);
            $pdo->commit();
        }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();if($error instanceof DomainException)financeJson(['success'=>false,'message'=>$error->getMessage()],422);throw $error;}
        financeJson(['success'=>true,'message'=>'Refund approved, invoice balances restored and the refund posted to the ledger.']);
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
    // Continue allocating to academic-term invoices after monthly invoices.
    $termStmt=$pdo->prepare(
        "SELECT i.id,(i.amount_due-COALESCE(a.paid,0)) balance
         FROM finance_term_fee_invoices i
         LEFT JOIN (SELECT term_invoice_id,SUM(amount) paid FROM finance_term_fee_payments GROUP BY term_invoice_id) a ON a.term_invoice_id=i.id
         WHERE i.student_id=? AND i.status IN ('unpaid','partial') AND (i.amount_due-COALESCE(a.paid,0))>0
         ORDER BY i.due_on,i.id FOR UPDATE"
    );
    $termStmt->execute([$studentId]);
    $termInsert=$pdo->prepare('INSERT INTO finance_term_fee_payments (term_invoice_id,payment_id,amount) VALUES (?,?,?)');
    $termUpdate=$pdo->prepare(
        "UPDATE finance_term_fee_invoices i SET status=CASE
         WHEN (i.amount_due-(SELECT COALESCE(SUM(p.amount),0) FROM finance_term_fee_payments p WHERE p.term_invoice_id=i.id))<=0 THEN 'paid'
         ELSE 'partial' END WHERE id=?"
    );
    foreach($termStmt->fetchAll(PDO::FETCH_ASSOC) as $invoice){
        if($remainingCents<=0)break;
        $balanceCents=financeToCents((string)$invoice['balance']);
        $partCents=min($remainingCents,$balanceCents);
        $termInsert->execute([(int)$invoice['id'],$paymentId,financeFromCents($partCents)]);
        $termUpdate->execute([(int)$invoice['id']]);
        $remainingCents-=$partCents;
    }
    if($remainingCents>0){ throw new DomainException('The payment exceeds the student’s outstanding monthly and term invoices.'); }
}
