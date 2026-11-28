<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/finance.php';
financeRequireAccess();
$pdo=db();
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    $year=(int)($_GET['year']??date('Y'));$month=(int)($_GET['month']??date('n'));
    $periods=$pdo->query(
        "SELECT ap.id,ap.starts_on,ap.ends_on,ap.is_current,s.is_current session_is_current,s.name session_name,t.name term_name
         FROM academic_periods ap
         INNER JOIN academic_sessions s ON s.id=ap.academic_session_id
         INNER JOIN terms t ON t.id=ap.term_id
         ORDER BY s.id DESC,t.sort_order ASC,ap.id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    $selectedPeriodId=(int)($_GET['period_id']??0);
    if($selectedPeriodId<1){
        foreach($periods as $candidate){if((int)$candidate['is_current']===1&&(int)$candidate['session_is_current']===1){$selectedPeriodId=(int)$candidate['id'];break;}}
        if($selectedPeriodId<1&&$periods)$selectedPeriodId=(int)$periods[0]['id'];
    }
    $selectedPeriod=null;
    foreach($periods as $candidate){if((int)$candidate['id']===$selectedPeriodId){$selectedPeriod=$candidate;break;}}
    if($selectedPeriodId>0&&!$selectedPeriod)financeJson(['success'=>false,'message'=>'The selected academic session and term could not be found.'],404);
    $period=null;$items=[];$attendanceHistory=[];
    if($selectedPeriod&&$year>=2020&&$year<=2100&&$month>=1&&$month<=12){
        $st=$pdo->prepare(
            "SELECT p.*,ap.starts_on,ap.ends_on,s.name session_name,t.name term_name
             FROM finance_payroll_periods p
             INNER JOIN academic_periods ap ON ap.id=p.academic_period_id
             INNER JOIN academic_sessions s ON s.id=ap.academic_session_id
             INNER JOIN terms t ON t.id=ap.term_id
             WHERE p.academic_period_id=? AND p.period_year=? AND p.period_month=? LIMIT 1"
        );
        $st->execute([$selectedPeriodId,$year,$month]);$period=$st->fetch(PDO::FETCH_ASSOC)?:null;
        if($period){
            $q=$pdo->prepare(
                "SELECT i.*,s.full_name teacher_name,s.staff_number,s.job_title,
                        pp.payment_method,pp.payment_reference,pp.confirmed_at paid_at,
                        paid_by.name paid_by_name
                 FROM finance_payroll_items i
                 INNER JOIN finance_staff s ON s.id=i.finance_staff_id
                 LEFT JOIN finance_payroll_payments pp ON pp.payroll_item_id=i.id AND pp.status='successful'
                 LEFT JOIN admins paid_by ON paid_by.id=pp.recorded_by
                 WHERE i.payroll_period_id=? ORDER BY s.full_name"
            );
            $q->execute([(int)$period['id']]);$items=$q->fetchAll(PDO::FETCH_ASSOC);
        }
        $monthStart=sprintf('%04d-%02d-01',$year,$month);$monthEnd=date('Y-m-t',strtotime($monthStart));
        $rangeStart=max($monthStart,(string)$selectedPeriod['starts_on']);
        $rangeEnd=min($monthEnd,(string)$selectedPeriod['ends_on']);
        if($rangeStart<=$rangeEnd){
            $attendanceQuery=$pdo->prepare(
                "SELECT a.attendance_date,s.full_name teacher_name,s.staff_number,s.job_title,
                        a.status,a.expected_arrival,a.actual_arrival,a.late_minutes,a.lateness_deduction
                 FROM staff_attendance a
                 INNER JOIN finance_staff s ON s.id=a.finance_staff_id
                 WHERE a.attendance_date BETWEEN ? AND ? AND a.approved_at IS NOT NULL
                 ORDER BY a.attendance_date DESC,s.full_name"
            );
            $attendanceQuery->execute([$rangeStart,$rangeEnd]);
            $attendanceHistory=$attendanceQuery->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    $history=$pdo->query(
        "SELECT CONCAT(acs.name,' · ',t.name) session_term,
                DATE_FORMAT(STR_TO_DATE(CONCAT(p.period_year,'-',LPAD(p.period_month,2,'0'),'-01'),'%Y-%m-%d'),'%M %Y') month_label,
                s.full_name teacher_name,s.staff_number,s.job_title,
                i.base_salary,i.late_minutes,i.lateness_deductions,i.other_deductions,i.net_salary,i.status,
                pp.confirmed_at paid_at,pp.payment_method,paid_by.name paid_by_name
         FROM finance_payroll_items i
         INNER JOIN finance_payroll_periods p ON p.id=i.payroll_period_id
         INNER JOIN finance_staff s ON s.id=i.finance_staff_id
         LEFT JOIN academic_periods ap ON ap.id=p.academic_period_id
         LEFT JOIN academic_sessions acs ON acs.id=ap.academic_session_id
         LEFT JOIN terms t ON t.id=ap.term_id
         LEFT JOIN finance_payroll_payments pp ON pp.payroll_item_id=i.id AND pp.status='successful'
         LEFT JOIN admins paid_by ON paid_by.id=pp.recorded_by
         ORDER BY p.period_year DESC,p.period_month DESC,s.full_name LIMIT 500"
    )->fetchAll(PDO::FETCH_ASSOC);
    financeJson(['success'=>true,'periods'=>$periods,'selected_period_id'=>$selectedPeriodId,'period'=>$period,'items'=>$items,'attendance_history'=>$attendanceHistory,'history'=>$history]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')financeJson(['success'=>false,'message'=>'Method not allowed.'],405);
financeRequireSafePost();$input=financeInput();$action=(string)($input['action']??'');
try{
if($action==='save_salary_profile'){
    $staffId=(int)($input['finance_staff_id']??0);$salary=financeMoney($input['base_salary']??'',true);
    $mode=(string)($input['attendance_mode']??'time_based');
    $arrival=trim((string)($input['expected_arrival']??''));$deductLateness=($input['deduct_lateness']??'0')==='1'||($input['deduct_lateness']??false)===true;$rate=financeMoney($input['lateness_rate_per_minute']??'13',true);$date=(string)($input['effective_from']??'');
    $validArrival=$mode==='attendance_only' ? $arrival==='' : preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$arrival);
    if($staffId<1||!in_array($mode,['time_based','attendance_only'],true)||!$validArrival||!financeValidDate($date))financeJson(['success'=>false,'message'=>'Choose a staff member, attendance policy, valid schedule and effective date.'],422);
    if($mode==='attendance_only'){$arrival=null;$rate='0.00';$deductLateness=false;}
    $q=$pdo->prepare('SELECT id FROM finance_staff WHERE id=? AND is_active=1');$q->execute([$staffId]);if(!$q->fetchColumn())financeJson(['success'=>false,'message'=>'Select an active Finance staff member.'],422);
    $futureProfile=$pdo->prepare('SELECT id FROM staff_salary_profiles WHERE finance_staff_id=? AND effective_from>? AND (effective_until IS NULL OR effective_until>=?) LIMIT 1');
    $futureProfile->execute([$staffId,$date,$date]);
    if($futureProfile->fetchColumn())financeJson(['success'=>false,'message'=>'A later salary profile is already scheduled. Update that profile or choose a later effective date.'],409);
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE staff_salary_profiles SET is_active=0,effective_until=DATE_SUB(?,INTERVAL 1 DAY) WHERE finance_staff_id=? AND is_active=1 AND effective_from<?')->execute([$date,$staffId,$date]);
    $sameDate=$pdo->prepare('SELECT id FROM staff_salary_profiles WHERE finance_staff_id=? AND effective_from=? ORDER BY id DESC LIMIT 1');
    $sameDate->execute([$staffId,$date]);$existingProfile=(int)$sameDate->fetchColumn();
    if($existingProfile>0){
        $q=$pdo->prepare('UPDATE staff_salary_profiles SET base_salary=?,expected_arrival=?,attendance_mode=?,lateness_rate_per_minute=?,deduct_lateness=?,effective_until=NULL,is_active=1,created_by=? WHERE id=?');
        $q->execute([$salary,$arrival,$mode,$rate,(int)$deductLateness,(int)$_SESSION['admin_id'],$existingProfile]);$id=$existingProfile;
    }else{
        $q=$pdo->prepare('INSERT INTO staff_salary_profiles(finance_staff_id,base_salary,expected_arrival,attendance_mode,lateness_rate_per_minute,deduct_lateness,effective_from,created_by) VALUES(?,?,?,?,?,?,?,?)');
        $q->execute([$staffId,$salary,$arrival,$mode,$rate,(int)$deductLateness,$date,(int)$_SESSION['admin_id']]);$id=(int)$pdo->lastInsertId();
    }
    financeAudit($pdo,'salary_profile_saved','salary_profile',$id,['finance_staff_id'=>$staffId,'attendance_mode'=>$mode]);$pdo->commit();
    financeJson(['success'=>true,'message'=>'Salary profile saved.']);
}
if($action==='calculate'){
    $year=(int)($input['year']??0);$month=(int)($input['month']??0);$periodId=(int)($input['period_id']??0);
    if($year<2020||$year>2100||$month<1||$month>12||$periodId<1)financeJson(['success'=>false,'message'=>'Choose a valid academic session, term, payroll year and month.'],422);
    $academicQuery=$pdo->prepare('SELECT id,starts_on,ends_on FROM academic_periods WHERE id=? LIMIT 1');$academicQuery->execute([$periodId]);$academicPeriod=$academicQuery->fetch(PDO::FETCH_ASSOC);
    if(!$academicPeriod||!financeValidDate((string)$academicPeriod['starts_on'])||!financeValidDate((string)$academicPeriod['ends_on']))financeJson(['success'=>false,'message'=>'The selected academic period must have valid start and end dates in School Settings.'],422);
    $first=sprintf('%04d-%02d-01',$year,$month);$last=date('Y-m-t',strtotime($first));
    $attendanceStart=max($first,(string)$academicPeriod['starts_on']);$attendanceEnd=min($last,(string)$academicPeriod['ends_on']);
    if($attendanceStart>$attendanceEnd)financeJson(['success'=>false,'message'=>'The selected month does not fall within the dates of this academic term.'],422);
    $pdo->beginTransaction();
    $legacyQuery=$pdo->prepare('SELECT id,status FROM finance_payroll_periods WHERE academic_period_id IS NULL AND period_year=? AND period_month=? ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $legacyQuery->execute([$year,$month]);$legacyPeriod=$legacyQuery->fetch(PDO::FETCH_ASSOC);
    if($legacyPeriod)financeJson(['success'=>false,'message'=>'A legacy payroll record already exists for this calendar month without an academic term. Have Finance review and assign that record before creating a new term-based payroll.'],409);
    $q=$pdo->prepare('SELECT * FROM finance_payroll_periods WHERE academic_period_id=? AND period_year=? AND period_month=? FOR UPDATE');$q->execute([$periodId,$year,$month]);$period=$q->fetch(PDO::FETCH_ASSOC);
    if($period&&in_array($period['status'],['approved','processing','completed'],true))financeJson(['success'=>false,'message'=>'Approved or paid payroll cannot be recalculated.'],409);
    if(!$period){$q=$pdo->prepare('INSERT INTO finance_payroll_periods(academic_period_id,period_year,period_month,status,created_by) VALUES(?,?,?,"draft",?)');$q->execute([$periodId,$year,$month,(int)$_SESSION['admin_id']]);$periodIdRow=(int)$pdo->lastInsertId();}
    else{$periodIdRow=(int)$period['id'];}
    $pdo->prepare('DELETE d FROM finance_payroll_deductions d INNER JOIN finance_payroll_items i ON i.id=d.payroll_item_id WHERE i.payroll_period_id=?')->execute([$periodIdRow]);
    $pdo->prepare('DELETE FROM finance_payroll_items WHERE payroll_period_id=?')->execute([$periodIdRow]);
    $peopleQuery=$pdo->prepare(
        "SELECT DISTINCT s.id finance_staff_id,s.full_name name
         FROM finance_staff s INNER JOIN staff_salary_profiles p ON p.finance_staff_id=s.id
         WHERE s.is_active=1 AND p.effective_from<=? AND (p.effective_until IS NULL OR p.effective_until>=?)
         ORDER BY s.full_name"
    );
    $peopleQuery->execute([$last,$first]);$people=$peopleQuery->fetchAll(PDO::FETCH_ASSOC);
    $profileQuery=$pdo->prepare(
        'SELECT base_salary,effective_from,effective_until FROM staff_salary_profiles
         WHERE finance_staff_id=? AND effective_from<=? AND (effective_until IS NULL OR effective_until>=?)
         ORDER BY effective_from,id'
    );
    $attendance=$pdo->prepare('SELECT id,attendance_mode,status,late_minutes,lateness_deduction FROM staff_attendance WHERE finance_staff_id=? AND attendance_date BETWEEN ? AND ? AND approved_at IS NOT NULL ORDER BY attendance_date');
    $insert=$pdo->prepare('INSERT INTO finance_payroll_items(payroll_period_id,finance_staff_id,base_salary,lateness_deductions,late_minutes,other_deductions,allowances,net_salary) VALUES(?,?,?,?,?,?,0,?)');
    $deduct=$pdo->prepare('INSERT INTO finance_payroll_deductions(payroll_item_id,staff_attendance_id,description,amount) VALUES(?,?,?,?)');
    $daysInMonth=(int)date('t',strtotime($first));$gross=0;$totalDeduct=0;$netTotal=0;
    foreach($people as $person){
        $staffId=(int)$person['finance_staff_id'];$profileQuery->execute([$staffId,$last,$first]);$profileRows=$profileQuery->fetchAll(PDO::FETCH_ASSOC);
        $baseCents=0;$coveredUntil=null;
        foreach($profileRows as $profile){
            $segmentStart=max($first,(string)$profile['effective_from']);
            $segmentEnd=min($last,(string)($profile['effective_until']??$last));
            if($coveredUntil!==null&&$segmentStart<=$coveredUntil)throw new DomainException('Overlapping salary profile dates detected for Finance staff ID '.$staffId.'.');
            $segmentDays=max(0,(int)((strtotime($segmentEnd)-strtotime($segmentStart))/86400)+1);
            if($segmentDays>0){$baseCents+=financeToCents(financeProrateMonthlySalary((string)$profile['base_salary'],$segmentDays,$daysInMonth));$coveredUntil=$segmentEnd;}
        }
        if($baseCents<=0)continue;
        $attendance->execute([$staffId,$attendanceStart,$attendanceEnd]);$records=$attendance->fetchAll(PDO::FETCH_ASSOC);$lateMinutes=0;$lateDeductionCents=0;$absenceDeductionCents=0;
        foreach($records as $record){
            if(($record['attendance_mode']??'time_based')==='time_based'&&($record['status']??'')==='present'){
                $lateMinutes+=(int)$record['late_minutes'];
                $lateDeductionCents+=financeToCents((string)$record['lateness_deduction']);
            }
            if(($record['status']??'')==='absent')$absenceDeductionCents+=30000;
        }
        $lateApplied=min($baseCents,$lateDeductionCents);
        $otherApplied=min(max(0,$baseCents-$lateApplied),$absenceDeductionCents);
        $totalApplied=$lateApplied+$otherApplied;
        $netCents=$baseCents-$totalApplied;
        $insert->execute([$periodIdRow,$staffId,financeFromCents($baseCents),financeFromCents($lateApplied),$lateMinutes,financeFromCents($otherApplied),financeFromCents($netCents)]);
        $itemId=(int)$pdo->lastInsertId();
        foreach(financeAllocatePayrollDeductions($records,$lateApplied,$otherApplied) as $detail){$deduct->execute([$itemId,$detail['staff_attendance_id'],$detail['description'],$detail['amount']]);}
        $gross+=$baseCents;$totalDeduct+=$totalApplied;$netTotal+=$netCents;
    }
    $q=$pdo->prepare('UPDATE finance_payroll_periods SET status="calculated",total_gross=?,total_deductions=?,total_net=?,approved_by=NULL,approved_at=NULL,scheduled_at=NULL WHERE id=?');
    $q->execute([financeFromCents($gross),financeFromCents($totalDeduct),financeFromCents($netTotal),$periodIdRow]);
    financeAudit($pdo,'payroll_calculated','payroll_period',$periodIdRow,['academic_period_id'=>$academicPeriod['id'],'year'=>$year,'month'=>$month,'staff_count'=>count($people)]);
    $pdo->commit();
    financeJson(['success'=>true,'message'=>'Payroll calculated. Review gross salary, approved lateness deductions and net salary due before approval.']);
}
if($action==='approve'){
    $year=(int)($input['year']??0);$month=(int)($input['month']??0);$periodId=(int)($input['period_id']??0);
    if($periodId<1||$year<2020||$year>2100||$month<1||$month>12)financeJson(['success'=>false,'message'=>'Choose the academic period and payroll month to approve.'],422);
    $pdo->beginTransaction();
    $legacyQuery=$pdo->prepare('SELECT id FROM finance_payroll_periods WHERE academic_period_id IS NULL AND period_year=? AND period_month=? ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $legacyQuery->execute([$year,$month]);
    if($legacyQuery->fetchColumn())financeJson(['success'=>false,'message'=>'A legacy payroll record exists for this calendar month without an academic term. Have Finance review and assign it before approving another payroll for the month.'],409);
    $q=$pdo->prepare('SELECT * FROM finance_payroll_periods WHERE academic_period_id=? AND period_year=? AND period_month=? FOR UPDATE');$q->execute([$periodId,$year,$month]);$period=$q->fetch(PDO::FETCH_ASSOC);
    if(!$period||$period['status']!=='calculated')financeJson(['success'=>false,'message'=>'Calculate and review this academic-period payroll before approval.'],409);
    $pdo->prepare('UPDATE finance_payroll_periods SET status="approved",scheduled_at=NULL,approved_by=?,approved_at=NOW() WHERE id=?')->execute([(int)$_SESSION['admin_id'],(int)$period['id']]);
    $pdo->prepare('UPDATE finance_payroll_items SET status="approved" WHERE payroll_period_id=?')->execute([(int)$period['id']]);
    $itemCount=$pdo->prepare('SELECT COUNT(*) FROM finance_payroll_items WHERE payroll_period_id=?');
    $itemCount->execute([(int)$period['id']]);
    $positiveRemaining=$pdo->prepare('SELECT COUNT(*) FROM finance_payroll_items WHERE payroll_period_id=? AND net_salary>0');
    $positiveRemaining->execute([(int)$period['id']]);
    if((int)$itemCount->fetchColumn()>0&&(int)$positiveRemaining->fetchColumn()===0)$pdo->prepare('UPDATE finance_payroll_periods SET status="completed",processed_at=NOW() WHERE id=?')->execute([(int)$period['id']]);
    financeAudit($pdo,'payroll_approved','payroll_period',(int)$period['id'],['academic_period_id'=>$periodId,'year'=>$year,'month'=>$month]);
    $pdo->commit();
    financeJson(['success'=>true,'message'=>'Payroll approved. Record each positive net salary after it is paid; zero-net rows are settled without a payout.']);
}
if($action==='record_payment'){
    $itemId=(int)($input['payroll_item_id']??0);$method=(string)($input['payment_method']??'');
    $raw=(string)($input['paid_at']??'');$reference=trim((string)($input['payment_reference']??''));$notes=trim((string)($input['notes']??''));
    $paidAt=DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i',$raw);
    if($itemId<1||!in_array($method,['cash','bank_transfer','pos','other'],true)||!$paidAt||$paidAt->format('Y-m-d\\TH:i')!==$raw||$paidAt>new DateTimeImmutable()||mb_strlen($reference)>190||mb_strlen($notes)>500){
        financeJson(['success'=>false,'message'=>'Enter a valid salary payment method, date and optional reference.'],422);
    }
    $pdo->beginTransaction();
    $q=$pdo->prepare(
        "SELECT i.*,p.status period_status,p.period_year,p.period_month,s.full_name teacher_name,s.staff_number
         FROM finance_payroll_items i
         INNER JOIN finance_payroll_periods p ON p.id=i.payroll_period_id
         INNER JOIN finance_staff s ON s.id=i.finance_staff_id
         WHERE i.id=? FOR UPDATE"
    );
    $q->execute([$itemId]);$item=$q->fetch(PDO::FETCH_ASSOC);
    if(!$item)financeJson(['success'=>false,'message'=>'Payroll item not found.'],404);
    if($item['status']==='paid')financeJson(['success'=>false,'message'=>'This salary has already been recorded as paid.'],409);
    if($item['status']!=='approved'||$item['period_status']!=='approved')financeJson(['success'=>false,'message'=>'Approve this payroll before recording its payment.'],409);
    $amount=financeMoney((string)$item['net_salary'],true);
    if(financeToCents($amount)<1)financeJson(['success'=>false,'message'=>'A zero-value salary does not require a payment record.'],422);
    $paidAtValue=$paidAt->format('Y-m-d H:i:s');$actor=(int)$_SESSION['admin_id'];
    $idempotencyKey='manual-payroll-item-'.$itemId;
    $insert=$pdo->prepare(
        "INSERT INTO finance_payroll_payments
            (payroll_item_id,provider,idempotency_key,amount,status,initiated_at,confirmed_at,recorded_by,payment_method,payment_reference,notes)
         VALUES (?, 'manual', ?, ?, 'successful', ?, ?, ?, ?, ?, ?)"
    );
    $insert->execute([$itemId,$idempotencyKey,$amount,$paidAtValue,$paidAtValue,$actor,$method,$reference?:null,$notes?:null]);
    $paymentId=(int)$pdo->lastInsertId();
    $cashCode=$method==='cash'?'1000':'1010';
    $journal=financePostJournal($pdo,'payroll_payment',$paymentId,'Salary payment: '.$item['teacher_name'],substr($paidAtValue,0,10),[
        ['account_id'=>financeAccountId($pdo,'5100'),'debit'=>$amount,'credit'=>'0.00','description'=>'Teacher / staff salary paid'],
        ['account_id'=>financeAccountId($pdo,$cashCode),'debit'=>'0.00','credit'=>$amount,'description'=>'Salary paid by '.str_replace('_',' ',$method)]
    ]);
    $pdo->prepare('UPDATE finance_payroll_items SET status="paid" WHERE id=?')->execute([$itemId]);
    $remaining=$pdo->prepare("SELECT COUNT(*) FROM finance_payroll_items WHERE payroll_period_id=? AND status<>'paid' AND net_salary>0");
    $remaining->execute([(int)$item['payroll_period_id']]);
    if((int)$remaining->fetchColumn()===0){
        $pdo->prepare('UPDATE finance_payroll_periods SET status="completed",processed_at=NOW() WHERE id=?')->execute([(int)$item['payroll_period_id']]);
    }
    financeAudit($pdo,'payroll_salary_paid','payroll_item',$itemId,['payment_id'=>$paymentId,'amount'=>$amount,'method'=>$method,'paid_at'=>$paidAtValue,'journal_id'=>$journal]);
    $pdo->commit();
    financeJson(['success'=>true,'message'=>'Salary payment recorded with the payment date, method and staff member who recorded it.']);
}
financeJson(['success'=>false,'message'=>'Unknown payroll action.'],404);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof DomainException)financeJson(['success'=>false,'message'=>$e->getMessage()],422);error_log('MARKAZ payroll API: '.$e->getMessage());financeJson(['success'=>false,'message'=>'Payroll operation failed. Check the server log.'],500);}
