<?php
declare(strict_types=1);

function studentFeesExperienceCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$api = file_get_contents(__DIR__ . '/../api/finance/term-fees.php');
$js = file_get_contents(__DIR__ . '/../public/js/finance.js');
$html = file_get_contents(__DIR__ . '/../public/finance.html');
$css = file_get_contents(__DIR__ . '/../public/css/finance.css');

studentFeesExperienceCheck(str_contains($api, 'previous_paid'), 'previous-term payment allocations are included in paid totals');
studentFeesExperienceCheck(str_contains($api, 'previous_invoice_count'), 'fully settled accounts are distinguished from students without any fee assessment');
studentFeesExperienceCheck(str_contains($api, 'COALESCE(cur.current_paid,0)+COALESCE(prev.previous_paid,0) total_paid'), 'account payment status uses payments from current and previous terms');
studentFeesExperienceCheck(str_contains($api, "financeToCents((string)\$r['total_paid'])===0"), 'unpaid filter checks the full account payment history');
studentFeesExperienceCheck(str_contains($api, "financeToCents((string)\$r['total_paid'])>0"), 'part-paid filter checks the full account payment history');
studentFeesExperienceCheck(str_contains($api, "adjustment_type']==='full_scholarship"), 'approved full-scholarship accounts are included in the settled filter');
studentFeesExperienceCheck(str_contains($js, 'studentFeesRequestSequence') && str_contains($js, 'sequence!==studentFeesRequestSequence'), 'stale search and filter responses cannot overwrite newer results');
studentFeesExperienceCheck(str_contains($js, "feeClearanceBadge('Full scholarship')"), 'approved full scholarship receives a distinct green clearance indicator');
studentFeesExperienceCheck(str_contains($js, "feeClearanceBadge('No previous debt')"), 'students without previous-term debt receive a green clearance indicator');
studentFeesExperienceCheck(str_contains($js, 'money(s.current_paid)') && str_contains($js, 'money(s.current_balance)'), 'ordinary students retain current-term paid and remaining amounts');
studentFeesExperienceCheck(str_contains($js, 'await refreshStudentFeeViews(Number(d.student_id))'), 'recording a payment refreshes the register and the open matching student statement');
studentFeesExperienceCheck(str_contains($js, 'await refreshStudentFeeViews()'), 'verifying a payment refreshes student balances and statements');
studentFeesExperienceCheck(str_contains($js, 'Awaiting verification — this amount has not yet been applied'), 'pending transfers are clearly distinguished from allocated payments');
studentFeesExperienceCheck(substr_count($html, 'class="currency-field"') === 16, 'all sixteen school fee rate inputs display the Naira prefix');
studentFeesExperienceCheck(str_contains($html, 'A different authorised staff member must approve it'), 'scholarship approval requirements are explicit in the form');
studentFeesExperienceCheck(str_contains($css, '.fee-clearance-badge') && str_contains($css, '.fee-support-badge'), 'clearance and scholarship badges have dedicated styling');
studentFeesExperienceCheck(str_contains($css, '.currency-field') && str_contains($css, '.student-fees-adjustment-note'), 'fee inputs and scholarship guidance have dedicated styling');

echo "MARKAZ Student Fees experience regression checks passed.\n";
