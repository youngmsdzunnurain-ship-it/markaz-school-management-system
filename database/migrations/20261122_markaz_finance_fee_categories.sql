-- MARKAZ Finance flexible fee categories and student-specific overrides.
-- Apply after 20261121_markaz_finance_payroll.sql on the Finance database.
-- Existing plans and invoices are retained and assigned the default "general" category.

ALTER TABLE finance_fee_plans
    ADD COLUMN fee_code VARCHAR(60) NOT NULL DEFAULT 'general' AFTER name,
    ADD KEY idx_fee_plan_category (fee_code, is_active, effective_from, effective_until);

ALTER TABLE finance_fee_invoices
    ADD COLUMN fee_code VARCHAR(60) NOT NULL DEFAULT 'general' AFTER fee_plan_id,
    ADD KEY idx_invoice_student_category_period (student_id, fee_code, billing_year, billing_month);
