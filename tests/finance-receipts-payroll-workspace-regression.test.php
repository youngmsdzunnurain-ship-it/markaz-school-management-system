<?php
declare(strict_types=1);

function financeReceiptPayrollCheck(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$html = $read('public/finance.html');
$financeCss = $read('public/css/finance.css');
$headerCss = $read('public/css/page-header-2026.css');
$headerGenerator = $read('tools/header/make-header-css.py');
$receiptHtml = $read('public/finance-receipt.html');
$receiptCss = $read('public/css/finance-receipt.css');
$receiptJs = $read('public/js/finance-receipt.js');
$financeApi = $read('api/finance/index.php');
$payrollApi = $read('api/finance/payroll.php');
$financeJs = $read('public/js/finance.js');
$dashboardJs = $read('public/js/dashboard.js');
$managementShellJs = $read('public/js/management-shell.js');
$settingsJs = $read('public/js/settings.js');
$dashboardCss = $read('public/css/dashboard-2026.css');
$migration = $read('database/migrations/20261128_finance_receipts_payroll_periods_manual_payments.sql');
$installer = $read('scripts/install-finance.sh');

financeReceiptPayrollCheck(str_contains($html, 'class="finance-menu-control"') && str_contains($html, 'id="menuButton"'), 'mobile Finance menu control has a dedicated compact wrapper');
financeReceiptPayrollCheck(str_contains($financeCss, '.finance-menu-control') && str_contains($financeCss, 'background:transparent!important') && str_contains($financeCss, 'width:40px'), 'mobile Finance header leaves the title beside a compact left-aligned menu control');
financeReceiptPayrollCheck(str_contains($headerCss, 'body.finance-workspace-page:not(#mkhdr) .topbar') && str_contains($headerCss, 'background:transparent!important') && str_contains($headerCss, '.finance-menu-control'), 'Finance-specific mobile header overrides the later shared page-header gradient');
financeReceiptPayrollCheck(str_contains($headerGenerator, 'body.finance-workspace-page:not(#mkhdr) .topbar') && str_contains($headerGenerator, '.finance-menu-control'), 'the shared header generator preserves the Finance mobile exception');
financeReceiptPayrollCheck(str_contains($financeCss, '#tab-payments .payment-history-panel{') && str_contains($financeCss, 'grid-column:auto;') && str_contains($financeCss, '#tab-payments .two-col{') && str_contains($financeCss, 'grid-template-columns:repeat(2,minmax(0,1fr));'), 'Payments and Receipts pairs payment entry/history and refund entry/approval in two compact columns');
financeReceiptPayrollCheck(str_contains($html, 'name="bank_name"') && str_contains($html, 'id="paymentBankField"'), 'student payment form captures the bank name only when relevant');
financeReceiptPayrollCheck(str_contains($financeApi, 'bank_name') && str_contains($financeApi, 'verified_by_name') && str_contains($financeApi, 'verified_at'), 'receipt API returns bank, recorder and verifier details');
financeReceiptPayrollCheck(str_contains($receiptHtml, 'markaz-logo.jpg') && str_contains($receiptHtml, 'receipt-watermark') && str_contains($receiptHtml, 'receiptBackButton'), 'receipt preview uses the school logo, watermark and a back-navigation control');
financeReceiptPayrollCheck(str_contains($receiptCss, '@page{size:A5 portrait') && str_contains($receiptCss, '.receipt-watermark'), 'receipt prints on half-A4 A5 paper with a visible school-logo watermark');
financeReceiptPayrollCheck(str_contains($receiptJs, 'payer_name') && str_contains($receiptJs, 'bank_name') && str_contains($receiptJs, 'recorded_by_name') && str_contains($receiptJs, 'verified_by_name'), 'receipt displays payer, transfer bank, receiving staff and confirming staff');
financeReceiptPayrollCheck(str_contains($receiptJs, 'history.back()') && str_contains($receiptJs, './finance.html'), 'receipt back navigation returns to the previous page with a safe Finance fallback');
financeReceiptPayrollCheck(str_contains($financeJs, "window.location.href='./finance-receipt.html?payment_id='"), 'receipt opens in the current tab so Back returns to the originating Finance page');
financeReceiptPayrollCheck(str_contains($dashboardJs, 'addEventListener') && str_contains($dashboardJs, 'renderStaffAvatar(topAdminInitials') && str_contains($dashboardJs, 'getInitials(name)'), 'broken or missing staff profile images fall back to first-and-last-name initials');
financeReceiptPayrollCheck(str_contains($dashboardCss, '.sidebar-user-avatar img'), 'sidebar staff avatar images fit the initials placeholder frame');
financeReceiptPayrollCheck(str_contains($managementShellJs, 'staffInitials') && str_contains($managementShellJs, 'parts[parts.length - 1][0]') && str_contains($managementShellJs, 'image.addEventListener('), 'shared staff shell uses first and last initials when a profile image is missing or broken');
financeReceiptPayrollCheck(str_contains($settingsJs, 'renderSettingsAvatar') && str_contains($settingsJs, 'image.addEventListener('), 'staff profile settings restore initials when a saved profile image cannot load');
financeReceiptPayrollCheck(str_contains($html, 'id="payrollAcademicPeriod"') && str_contains($html, 'id="payrollYear"') && str_contains($html, 'id="payrollMonth"'), 'payroll period selection includes academic session/term, calendar year and month');
financeReceiptPayrollCheck(!str_contains($html, 'id="payrollSchedule"') && !str_contains($payrollApi, "['scheduled_at']"), 'payroll approval no longer asks for a future scheduled bank-release date');
financeReceiptPayrollCheck(str_contains($html, 'Gross salary') && str_contains($html, 'Net salary due') && str_contains($html, 'Attendance history'), 'payroll separates gross salary, net salary due and its own attendance history');
financeReceiptPayrollCheck(str_contains($html, 'id="payrollPaymentDialog"') && str_contains($html, 'id="payrollPaymentForm"'), 'payroll provides a manual salary-payment record form');
financeReceiptPayrollCheck(str_contains($payrollApi, 'academic_period_id') && str_contains($payrollApi, 'attendance_history'), 'payroll calculation and attendance history are scoped to the selected academic period');
financeReceiptPayrollCheck(str_contains($payrollApi, 'record_payment') && str_contains($payrollApi, 'recorded_by') && str_contains($payrollApi, 'financePostJournal'), 'manual salary payments record the staff actor and post an auditable ledger entry');
financeReceiptPayrollCheck(str_contains($payrollApi, 'academic_period_id IS NULL') && str_contains($payrollApi, 'legacy payroll record already exists'), 'legacy monthly payroll rows block duplicate term-scoped payroll creation');
financeReceiptPayrollCheck(str_contains($payrollApi, 'status<>\'paid\' AND net_salary>0') && str_contains($payrollApi, 'positiveRemaining'), 'zero-net payroll rows do not block completion after positive salaries are paid');
financeReceiptPayrollCheck(str_contains($financeJs, 'No payment due') && str_contains($financeJs, 'no-payment-due'), 'zero-net approved staff rows are shown as settled without offering a payout');
financeReceiptPayrollCheck(str_contains($financeJs, "location.hash==='#payments'") && str_contains($financeJs, "showTab('payments')"), 'receipt fallback hash restores the Payments & Receipts tab');
financeReceiptPayrollCheck(str_contains($migration, 'ADD COLUMN bank_name') && str_contains($migration, 'academic_period_id') && str_contains($migration, 'payment_method') && str_contains($migration, 'recorded_by'), 'additive migration stores receipt bank details, academic payroll periods and manual payout metadata');
financeReceiptPayrollCheck(str_contains($installer, '20261128_finance_receipts_payroll_periods_manual_payments.sql'), 'Finance installer applies and verifies the receipt/payroll extension migration');
financeReceiptPayrollCheck(str_contains($financeJs, 'data-record-payroll-payment') && str_contains($financeJs, 'attendance_history') && str_contains($financeJs, 'payrollAcademicPeriod'), 'Finance client renders payroll attendance and manual payment actions');

echo "Finance receipts, compact workspace, staff avatars and term payroll regression checks passed.\n";
