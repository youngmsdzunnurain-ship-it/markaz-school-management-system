<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/helpers/finance.php';
financeRequireAccess();
$pdo=db();
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    $year=(int)($_GET['year']??date('Y'));$month=(int)($_GET['month']??date('n'));
    $st=$pdo->prepare('SELECT * FROM finance_payroll_periods WHERE period_year=? AND period_month=? LIMIT 1');
    $st->execute([$year,$month]);$period=$st->fetch(PDO::FETCH_ASSOC);$items=[];
    if($period){$q=$pdo->prepare('SELECT i.*,s.full_name teacher_name FROM finance_payroll_items i INNER JOIN finance_staff s ON s.id=i.finance_staff_id WHERE i.payroll_period_id=? ORDER BY s.full_name');$q->execute([(int)$period['id']]);$items=$q->fetchAll(PDO::FETCH_ASSOC);}
    financeJson(['success'=>true,'period'=>$period,'items'=>$items]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')financeJson(['success'=>false,'message'=>'Method not allowed.'],405);
financeRequireSafePost();$input=financeInput();$action=(string)($input['action']??'');
try{
if($action==='save_salary_profile'){
    $staffId=(int)($input['finance_staff_id']??0);$salary=financeMoney($input['base_salary']??'',true);
    $arrival=(string)($input['expected_arrival']??'');$rate=financeMoney($input['lateness_rate_per_minute']??'10',true);$date=(string)($input['effective_from']??'');
    if($account<1||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$arrival)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))financeJson(['success'=>false,'message'=>'Complete the salary profile with valid values.'],422);
    $q=$pdo->prepare('SELECT id FROM finance_staff WHERE id=? AND is_active=1');$q->execute([$staffId]);if(!$q->fetchColumn())financeJson(['success'=>false,'message'=>'Select an active Finance staff member.'],422);
    $futureProfile=$pdo->prepare('SELECT id FROM staff_salary_profiles WHERE finance_staff_id=? AND effective_from>? AND (effective_until IS NULL OR effective_until>=?) LIMIT 1');
    $futureProfile->execute([$staffId,$date,$date]);
    if($futureProfile->fetchColumn())financeJson(['success'=>false,'message'=>'A later salary profile is already scheduled. Update that profile or choose a later effective date.'],409);
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE staff_salary_profiles SET is_active=0,effective_until=DATE_SUB(?,INTERVAL 1 DAY) WHERE finance_staff_id=? AND is_active=1 AND effective_from<?')->execute([$date,$staffId,$date]);
    $sameDate=$pdo->prepare('SELECT id FROM staff_salary_profiles WHERE finance_staff_id=? AND effective_from=? ORDER BY id DESC LIMIT 1');
    $sameDate->execute([$staffId,$date]);$existingProfile=(int)$sameDate->fetchColumn();
    if($existingProfile>0){
        $q=$pdo->prepare('UPDATE staff_salary_profiles SET base_salary=?,expected_arrival=?,lateness_rate_per_minute=?,effective_until=NULL,is_active=1,created_by=? WHERE id=?');
        $q->execute([$salary,$arrival,$rate,(int)$_SESSION['admin_id'],$existingProfile]);$id=$existingProfile;
    }else{
        $q=$pdo->prepare('INSERT INTO staff_salary_profiles(finance_staff_id,base_salary,expected_arrival,lateness_rate_per_minute,effective_from,created_by) VALUES(?,?,?,?,?,?)');
        $q->execute([$staffId,$salary,$arrival,$rate,$date,(int)$_SESSION['admin_id']]);$id=(int)$pdo->lastInsertId();
    }
    financeAudit($pdo,'salary_profile_saved','salary_profile',$id,['finance_staff_id'=>$staffId]);$pdo->commit();
    financeJson(['success'=>true,'message'=>'Salary profile saved.']);
}
if($action==='calculate'){
    $year=(int)($input['year']??0);$month=(int)($input['month']??0);
    if($year<2020||$year>2100||$month<1||$month>12)financeJson(['success'=>false,'message'=>'Select a valid payroll month.'],422);
    $first=sprintf('%04d-%02d-01',$year,$month);$last=date('Y-m-t',strtotime($first));$pdo->beginTransaction();
    $q=$pdo->prepare('SELECT * FROM finance_payroll_periods WHERE period_year=? AND period_month=? FOR UPDATE');$q->execute([$year,$month]);$period=$q->fetch(PDO::FETCH_ASSOC);
    if($period&&in_array($period['status'],['approved','processing','completed'],true))financeJson(['success'=>false,'message'=>'Approved or processed payroll cannot be recalculated.'],409);
    if(!$period){$q=$pdo->prepare('INSERT INTO finance_payroll_periods(period_year,period_month,status,created_by) VALUES(?,?,"draft",?)');$q->execute([$year,$month,(int)$_SESSION['admin_id']]);$periodId=(int)$pdo->lastInsertId();}
    else{$periodId=(int)$period['id'];}
    $pdo->prepare('DELETE d FROM finance_payroll_deductions d INNER JOIN finance_payroll_items i ON i.id=d.payroll_item_id WHERE i.payroll_period_id=?')->execute([$periodId]);
    $pdo->prepare('DELETE FROM finance_payroll_items WHERE payroll_period_id=?')->execute([$periodId]);
    $peopleQuery=$pdo->prepare(
        "SELECT DISTINCT s.id finance_staff_id,s.full_name name
         FROM finance_staff s INNER JOIN staff_salary_profiles p ON p.finance_staff_id=s.id
         WHERE s.is_active=1
           AND p.effective_from<=? AND (p.effective_until IS NULL OR p.effective_until>=?)
         ORDER BY a.name"
    );
    $peopleQuery->execute([$last,$first]);$people=$peopleQuery->fetchAll(PDO::FETCH_ASSOC);
    $profileQuery=$pdo->prepare(
        'SELECT base_salary,effective_from,effective_until FROM staff_salary_profiles
         WHERE finance_staff_id=? AND effective_from<=? AND (effective_until IS NULL OR effective_until>=?)
         ORDER BY effective_from,id'
    );
    $attendance=$pdo->prepare('SELECT id,late_minutes,lateness_deduction FROM staff_attendance WHERE finance_staff_id=? AND attendance_date BETWEEN ? AND ? AND approved_at IS NOT NULL ORDER BY attendance_date');
    $insert=$pdo->prepare('INSERT INTO finance_payroll_items(payroll_period_id,finance_staff_id,base_salary,lateness_deductions,late_minutes,other_deductions,allowances,net_salary) VALUES(?,?,?,?,?,0,0,?)');
    $deduct=$pdo->prepare('INSERT INTO finance_payroll_deductions(payroll_item_id,staff_attendance_id,description,amount) VALUES(?,?,?,?)');
    $daysInMonth=(int)date('t',strtotime($first));$gross=0;$totalDeduct=0;$netTotal=0;
    foreach($people as $person){
        $staffId=(int)$person['finance_staff_id'];$profileQuery->execute([$staffId,$last,$first]);$profileRows=$profileQuery->fetchAll(PDO::FETCH_ASSOC);
        $baseCents=0;$coveredUntil=null;
        foreach($profileRows as $profile){
            $segmentStart=max($first,(string)$profile['effective_from']);
            $segmentEnd=min($last,(string)($profile['effective_until']??$last));
            if($coveredUntil!==null&&$segmentStart<=$coveredUntil){
                throw new DomainException('Overlapping salary profile dates detected for Finance staff ID '.$staffId.'.');
            }
            $segmentDays=max(0,(int)((strtotime($segmentEnd)-strtotime($segmentStart))/86400)+1);
            if($segmentDays>0){
                $prorated=financeProrateMonthlySalary((string)$profile['base_salary'],$segmentDays,$daysInMonth);
                $baseCents+=financeToCents($prorated);
                $coveredUntil=$segmentEnd;
            }
        }
        if($baseCents<=0)continue;
        $attendance->execute([$staffId,$first,$last]);$records=$attendance->fetchAll(PDO::FETCH_ASSOC);$lateMinutes=0;$deductionCents=0;
        foreach($records as $record){$lateMinutes+=(int)$record['late_minutes'];$deductionCents+=financeToCents((string)$record['lateness_deduction']);}
        $applied=min($baseCents,$deductionCents);$netCents=$baseCents-$applied;
        $insert->execute([$periodId,$accountId,financeFromCents($baseCents),financeFromCents($applied),$lateMinutes,financeFromCents($netCents)]);$itemId=(int)$pdo->lastInsertId();
        foreach($records as $record){if(financeToCents((string)$record['lateness_deduction'])>0)$deduct->execute([$itemId,(int)$record['id'],(int)$record['late_minutes'].' minutes late',$record['lateness_deduction']]);}
        $gross+=$baseCents;$totalDeduct+=$applied;$netTotal+=$netCents;
    }
    $q=$pdo->prepare('UPDATE finance_payroll_periods SET status="calculated",total_gross=?,total_deductions=?,total_net=?,approved_by=NULL,approved_at=NULL,scheduled_at=NULL WHERE id=?');
    $q->execute([financeFromCents($gross),financeFromCents($totalDeduct),financeFromCents($netTotal),$periodId]);
    financeAudit($pdo,'payroll_calculated','payroll_period',$periodId,['staff_count'=>count($people)]);$pdo->commit();
    financeJson(['success'=>true,'message'=>'Payroll calculated. Review the staff totals before approval.']);
}
if($action==='approve'){
    $year=(int)($input['year']??0);$month=(int)($input['month']??0);$raw=(string)($input['scheduled_at']??'');
    $scheduled=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw);
    if(!$scheduled||$scheduled->format('Y-m-d\TH:i')!==$raw||$scheduled<=new DateTimeImmutable())financeJson(['success'=>false,'message'=>'Choose a valid future salary release date and time.'],422);
    $pdo->beginTransaction();$q=$pdo->prepare('SELECT * FROM finance_payroll_periods WHERE period_year=? AND period_month=? FOR UPDATE');$q->execute([$year,$month]);$period=$q->fetch(PDO::FETCH_ASSOC);
    if(!$period||$period['status']!=='calculated')financeJson(['success'=>false,'message'=>'Calculate and review payroll before approval.'],409);
    $pdo->prepare('UPDATE finance_payroll_periods SET status="approved",scheduled_at=?,approved_by=?,approved_at=NOW() WHERE id=?')->execute([$scheduled->format('Y-m-d H:i:s'),(int)$_SESSION['admin_id'],(int)$period['id']]);
    $pdo->prepare('UPDATE finance_payroll_items SET status="approved" WHERE payroll_period_id=?')->execute([(int)$period['id']]);
    financeAudit($pdo,'payroll_approved','payroll_period',(int)$period['id'],['scheduled_at'=>$scheduled->format('Y-m-d H:i:s')]);$pdo->commit();
    financeJson(['success'=>true,'message'=>'Payroll approved and scheduled. Bank transfers are still disabled until the provider and verified recipient accounts are configured.']);
}
financeJson(['success'=>false,'message'=>'Unknown payroll action.'],404);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('MARKAZ payroll API: '.$e->getMessage());financeJson(['success'=>false,'message'=>'Payroll operation failed. Check the server log.'],500);}
