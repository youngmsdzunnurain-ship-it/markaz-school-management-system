-- MARKAZ: Arabic class subjects and class-specific results.
-- Existing results remain conventional and are backfilled from students.class_id.

CREATE TABLE arabic_class_subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    arabic_class_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    is_required BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_arabic_class_subject (arabic_class_id, subject_id),
    KEY idx_arabic_class_subject_subject (subject_id),
    CONSTRAINT fk_arabic_class_subject_class
        FOREIGN KEY (arabic_class_id)
        REFERENCES arabic_classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_arabic_class_subject_subject
        FOREIGN KEY (subject_id)
        REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Add the seven approved Arabic subjects to each active Arabic class.
INSERT INTO arabic_class_subjects
    (arabic_class_id, subject_id, is_required, sort_order)
SELECT ac.id, s.id, TRUE, s.id - 6
FROM arabic_classes ac
CROSS JOIN subjects s
WHERE ac.is_active = 1
  AND s.id BETWEEN 6 AND 12;

-- Preserve existing rows as conventional results.
ALTER TABLE results
    ADD COLUMN class_type ENUM('conventional', 'arabic')
        NOT NULL DEFAULT 'conventional' AFTER student_id,
    ADD COLUMN class_id BIGINT UNSIGNED NULL AFTER class_type;

UPDATE results r
INNER JOIN students st ON st.id = r.student_id
SET r.class_id = st.class_id
WHERE r.class_type = 'conventional';

-- Do not allow a result without an explicit class context.
ALTER TABLE results
    MODIFY COLUMN class_id BIGINT UNSIGNED NOT NULL;

-- Allow the same student and subject in both class systems.
ALTER TABLE results
    DROP INDEX uq_student_subject_period,
    ADD UNIQUE KEY uq_result_class_subject_period
        (student_id, class_type, class_id, subject_id, academic_period_id),
    ADD KEY idx_results_class_period
        (class_type, class_id, academic_period_id);
