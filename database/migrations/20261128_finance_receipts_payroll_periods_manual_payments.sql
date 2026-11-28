-- Receipt attribution, academic-period payroll and manual salary-payment records.
-- Existing fee payments and payroll periods remain intact; legacy payroll rows keep a NULL academic period.

ALTER TABLE finance_fee_payments
    ADD COLUMN bank_name VARCHAR(100) NULL AFTER payer_name;

ALTER TABLE finance_payroll_periods
    DROP INDEX uq_payroll_period,
    ADD COLUMN academic_period_id BIGINT UNSIGNED NULL AFTER id,
    ADD UNIQUE KEY uq_payroll_academic_period_month (academic_period_id, period_year, period_month),
    ADD CONSTRAINT fk_payroll_academic_period
        FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id)
        ON DELETE RESTRICT;

ALTER TABLE finance_payroll_payments
    ADD COLUMN recorded_by BIGINT UNSIGNED NULL AFTER confirmed_at,
    ADD COLUMN payment_method ENUM('cash','bank_transfer','pos','other') NULL AFTER recorded_by,
    ADD COLUMN payment_reference VARCHAR(190) NULL AFTER payment_method,
    ADD COLUMN notes VARCHAR(500) NULL AFTER payment_reference,
    ADD KEY idx_payroll_payment_recorder (recorded_by),
    ADD CONSTRAINT fk_payroll_payment_recorder
        FOREIGN KEY (recorded_by) REFERENCES admins(id)
        ON DELETE SET NULL;
