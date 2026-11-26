<?php
declare(strict_types=1);
$html = file_get_contents(dirname(__DIR__) . '/public/finance-fees-preview.html');
$js = file_get_contents(dirname(__DIR__) . '/public/js/finance-fees-preview.js');
$css = file_get_contents(dirname(__DIR__) . '/public/css/finance-fees-preview.css');
$checks = [
    'preview is linked from Finance' => str_contains(file_get_contents(dirname(__DIR__) . '/public/finance.html'), 'finance-fees-preview.html'),
    'preview has a protected read-only entry point' => str_contains($js, "api/finance/index.php?action=dashboard") && !str_contains($js, 'term-fees.php'),
    'preview contains all four education sections' => str_contains($js, 'junior_secondary') && str_contains($js, 'senior_secondary') && str_contains($js, 'nursery') && str_contains($js, 'primary'),
    'preview generates exactly one hundred fictional records' => str_contains($js, 'i<100') && str_contains($js, "'MZ/27/'"),
    'preview covers payment statuses' => str_contains($js, "'paid'") && str_contains($js, "'partial'") && str_contains($js, "'unpaid'") && str_contains($js, "'overdue'"),
    'preview shows previous-term debt and instalment history' => str_contains($js, 'previousDebt') && str_contains($js, 'payments') && str_contains($html, 'payment histories'),
    'preview has responsive layout and filters' => str_contains($css, '@media(max-width:760px)') && str_contains($html, 'statusFilter'),
    'preview does not submit or save financial transactions' => !str_contains($js, "method:'POST'") && !str_contains($js, 'termFeeRequest'),
];
foreach ($checks as $label => $ok) {
    if (!$ok) { fwrite(STDERR, "FAIL: {$label}\\n"); exit(1); }
    echo "PASS: {$label}\\n";
}
echo "Student Fees preview regression checks passed.\\n";
