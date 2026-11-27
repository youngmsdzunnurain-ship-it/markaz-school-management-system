-- Section-based MARKAZ student fees.
-- Adds term date ranges to the existing academic calendar and fee rates by
-- education section, accommodation and intake type. Existing invoices and
-- payment allocations are retained unchanged.

ALTER TABLE academic_periods
    ADD COLUMN starts_on DATE NULL AFTER term_id,
    ADD COLUMN ends_on DATE NULL AFTER starts_on,
    ADD CONSTRAINT chk_academic_period_date_range
        CHECK (starts_on IS NULL OR ends_on IS NULL OR ends_on >= starts_on);

CREATE TABLE finance_section_fee_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_period_id BIGINT UNSIGNED NOT NULL,
    section ENUM('nursery','primary','junior_secondary','senior_secondary') NOT NULL,
    student_type ENUM('Day Student','Boarding') NOT NULL,
    intake_type ENUM('returning','new_intake') NOT NULL DEFAULT 'returning',
    amount DECIMAL(14,2) NOT NULL,
    due_on DATE NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_section_fee_rate (academic_period_id,section,student_type,intake_type),
    KEY idx_section_fee_period (academic_period_id,is_active,section),
    CONSTRAINT fk_section_fee_period FOREIGN KEY (academic_period_id)
        REFERENCES academic_periods(id) ON DELETE RESTRICT,
    CONSTRAINT fk_section_fee_creator FOREIGN KEY (created_by)
        REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT chk_section_fee_amount CHECK (amount >= 0)
) ENGINE=InnoDB;

ALTER TABLE finance_term_fee_invoices
    ADD COLUMN academic_period_id BIGINT UNSIGNED NULL AFTER session_id,
    ADD COLUMN section ENUM('nursery','primary','junior_secondary','senior_secondary') NULL AFTER class_id,
    ADD COLUMN student_type ENUM('Day Student','Boarding') NULL AFTER section,
    ADD COLUMN intake_type ENUM('returning','new_intake') NULL AFTER student_type,
    ADD COLUMN fee_schedule_id BIGINT UNSIGNED NULL AFTER intake_type,
    ADD KEY idx_term_invoice_period_section (academic_period_id,section,student_type,intake_type,status),
    ADD CONSTRAINT fk_term_invoice_academic_period FOREIGN KEY (academic_period_id)
        REFERENCES academic_periods(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_term_invoice_fee_schedule FOREIGN KEY (fee_schedule_id)
        REFERENCES finance_section_fee_schedules(id) ON DELETE RESTRICT;

-- Historical invoice classification is intentionally left NULL.
-- Current student class/type/admission data may have changed since an invoice
-- was issued. Never infer historical fee classifications from mutable records.
-- New invoices receive all classification snapshots in term-fees.php.
