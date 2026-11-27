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
$calendarJs = file_get_contents(__DIR__ . '/../public/js/student-fees-calendar.js');
$calendarCss = file_get_contents(__DIR__ . '/../public/css/student-fees-calendar.css');
$studentsApi = file_get_contents(__DIR__ . '/../api/finance/index.php');
$intakeMigration = file_get_contents(__DIR__ . '/../database/migrations/20261130_markaz_student_fee_intake_overrides.sql');

studentFeesExperienceCheck(str_contains($api, 'previous_paid'), 'previous-term payment allocations are included in paid totals');
studentFeesExperienceCheck(str_contains($api, 'previous_invoice_count'), 'fully settled accounts are distinguished from students without any fee assessment');
studentFeesExperienceCheck(str_contains($api, 'COALESCE(cur.current_paid,0)+COALESCE(prev.previous_paid,0) total_paid'), 'account payment status uses payments from current and previous terms');
studentFeesExperienceCheck(str_contains($api, "financeToCents((string)\$r['total_paid'])===0"), 'unpaid filter checks the full account payment history');
studentFeesExperienceCheck(str_contains($api, "financeToCents((string)\$r['total_paid'])>0"), 'part-paid filter checks the full account payment history');
studentFeesExperienceCheck(str_contains($api, "adjustment_type']==='full_scholarship"), 'approved full-scholarship accounts are included in the settled filter');
studentFeesExperienceCheck(str_contains($js, 'studentFeesRequestSequence') && str_contains($js, 'sequence!==studentFeesRequestSequence'), 'stale search and filter responses cannot overwrite newer results');
studentFeesExperienceCheck(str_contains($js, "const currentFee=fullScholarship") && str_contains($js, "?'—'"), 'approved full scholarship accounts show double dashes for current-term fee amounts');
studentFeesExperienceCheck(str_contains($js, "feeClearanceBadge('No previous debt')"), 'students without previous-term debt receive a green clearance indicator');
studentFeesExperienceCheck(str_contains($js, 'money(s.current_paid)') && str_contains($js, 'money(s.current_balance)'), 'ordinary students retain current-term paid and remaining amounts');
studentFeesExperienceCheck(str_contains($js, 'await refreshStudentFeeViews(Number(d.student_id))'), 'recording a payment refreshes the register and the open matching student statement');
studentFeesExperienceCheck(str_contains($js, 'await refreshStudentFeeViews()'), 'verifying a payment refreshes student balances and statements');
studentFeesExperienceCheck(str_contains($js, 'Awaiting verification — this amount has not yet been applied'), 'pending transfers are clearly distinguished from allocated payments');
studentFeesExperienceCheck(substr_count($html, 'class="currency-field"') === 16, 'all sixteen school fee rate inputs display the Naira prefix');
studentFeesExperienceCheck(str_contains($html, 'A different authorised staff member must approve it'), 'scholarship approval requirements are explicit in the form');
studentFeesExperienceCheck(str_contains($css, '.fee-clearance-badge') && str_contains($css, '.fee-support-badge'), 'clearance and scholarship badges have dedicated styling');
studentFeesExperienceCheck(str_contains($css, '.currency-field') && str_contains($css, '.student-fees-adjustment-note'), 'fee inputs and scholarship guidance have dedicated styling');
studentFeesExperienceCheck(str_contains($html, 'value="full_scholarship"') && str_contains($html, 'value="discount_percent"') && str_contains($html, 'value="discount_amount"') && str_contains($html, 'value="fixed_fee"'), 'scholarship filter exposes each approved adjustment type');
studentFeesExperienceCheck(str_contains($api, "in_array(\$scholarshipFilter,['full_scholarship','discount_percent','discount_amount','fixed_fee'],true)") && str_contains($api, "(string)\$r['adjustment_type']===\$scholarshipFilter"), 'each scholarship filter option is applied to the matching account adjustment');
studentFeesExperienceCheck(str_contains($js, "const currentPaid=fullScholarship?'—':money(s.current_paid)") && str_contains($js, "const currentBalance=fullScholarship?'—':money(s.current_balance)"), 'full-scholarship register rows use clear double-dash amounts for current-term figures');
studentFeesExperienceCheck(str_contains($studentsApi, "CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) LIKE ?"), 'student lookup supports searching by the complete student name');
studentFeesExperienceCheck(str_contains($studentsApi, 'configured_intake_type') && str_contains($js, 'student.configured_intake_type'), 'student lookup preserves an already saved intake override when the student is selected again');
studentFeesExperienceCheck(str_contains($html, 'id="studentFeeIntakeType"') && str_contains($js, "save_student_intake_override"), 'staff can explicitly configure an individual student intake category');
studentFeesExperienceCheck(str_contains($api, 'finance_student_fee_intake_overrides') && str_contains($api, 'save_student_intake_override'), 'intake overrides are saved and used by the current academic period');
studentFeesExperienceCheck(str_contains($calendarJs, 'studentCalendarContent') && str_contains($calendarJs, 'aria-expanded') && str_contains($calendarJs, 'studentCalendarMonthSummary'), 'academic calendar has an accessible collapsible month header');
studentFeesExperienceCheck(str_contains($calendarCss, '.student-fees-calendar.is-collapsed') && str_contains($calendarCss, '.student-calendar-toggle'), 'collapsed calendar uses a compact header without reserving expanded calendar space');
studentFeesExperienceCheck(str_contains($intakeMigration, 'CREATE TABLE finance_student_fee_intake_overrides') && str_contains($intakeMigration, 'UNIQUE KEY'), 'per-student intake overrides are stored once per academic period');

echo "MARKAZ Student Fees experience regression checks passed.\n";
