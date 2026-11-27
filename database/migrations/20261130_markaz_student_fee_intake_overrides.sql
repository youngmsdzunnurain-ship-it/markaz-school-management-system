-- Explicit per-student New Intake / Returning classification for one academic period.
-- Overrides affect only fee assessments generated after the selection is saved.
-- Existing invoices and payment allocations are never rewritten.

CREATE TABLE finance_student_fee_intake_overrides (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_period_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    intake_type ENUM('returning','new_intake') NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_fee_intake_period (academic_period_id,student_id),
    KEY idx_student_fee_intake_student (student_id,academic_period_id),
    CONSTRAINT fk_student_fee_intake_period FOREIGN KEY (academic_period_id)
        REFERENCES academic_periods(id) ON DELETE RESTRICT,
    CONSTRAINT fk_student_fee_intake_student FOREIGN KEY (student_id)
        REFERENCES students(id) ON DELETE RESTRICT,
    CONSTRAINT fk_student_fee_intake_created_by FOREIGN KEY (created_by)
        REFERENCES admins(id) ON DELETE SET NULL,
    CONSTRAINT fk_student_fee_intake_updated_by FOREIGN KEY (updated_by)
        REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;
