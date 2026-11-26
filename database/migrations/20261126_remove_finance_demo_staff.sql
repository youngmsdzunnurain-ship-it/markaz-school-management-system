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
