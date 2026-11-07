-- Link daily attendance entries to the academic session and term in which they were recorded.
-- Existing rows remain NULL: their term cannot be inferred safely because historical
-- attendance records have dates but no authoritative academic-period date boundaries.
ALTER TABLE attendance_records
    ADD COLUMN academic_period_id BIGINT UNSIGNED NULL AFTER attendance_date,
    ADD KEY idx_attendance_period_class_student (academic_period_id, class_type, class_id, student_id),
    ADD CONSTRAINT fk_attendance_academic_period
        FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id)
        ON DELETE SET NULL;
