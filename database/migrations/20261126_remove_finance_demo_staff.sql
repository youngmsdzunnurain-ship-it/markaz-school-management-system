-- Deactivate fictional demo staff and remove only unfinalized payroll previews.
UPDATE finance_staff
SET is_active = 0
WHERE email REGEXP '^demo\\.teacher[0-9]+@example\\.test$';

DELETE d
FROM finance_payroll_deductions d
INNER JOIN finance_payroll_items i ON i.id = d.payroll_item_id
INNER JOIN finance_staff s ON s.id = i.finance_staff_id
INNER JOIN finance_payroll_periods p ON p.id = i.payroll_period_id
WHERE s.email REGEXP '^demo\\.teacher[0-9]+@example\\.test$'
  AND p.status IN ('draft', 'calculated');

DELETE i
FROM finance_payroll_items i
INNER JOIN finance_staff s ON s.id = i.finance_staff_id
INNER JOIN finance_payroll_periods p ON p.id = i.payroll_period_id
WHERE s.email REGEXP '^demo\\.teacher[0-9]+@example\\.test$'
  AND p.status IN ('draft', 'calculated');

-- Reconcile period totals after removing demo-only draft/calculated items.
UPDATE finance_payroll_periods p
LEFT JOIN (
    SELECT payroll_period_id,
           COALESCE(SUM(base_salary), 0) total_gross,
           COALESCE(SUM(lateness_deductions + other_deductions), 0) total_deductions,
           COALESCE(SUM(net_salary), 0) total_net,
           COUNT(*) item_count
    FROM finance_payroll_items
    GROUP BY payroll_period_id
) totals ON totals.payroll_period_id = p.id
SET p.total_gross = COALESCE(totals.total_gross, 0),
    p.total_deductions = COALESCE(totals.total_deductions, 0),
    p.total_net = COALESCE(totals.total_net, 0),
    p.status = CASE
        WHEN COALESCE(totals.item_count, 0) = 0 AND p.status = 'calculated' THEN 'draft'
        ELSE p.status
    END
WHERE p.status IN ('draft', 'calculated');
