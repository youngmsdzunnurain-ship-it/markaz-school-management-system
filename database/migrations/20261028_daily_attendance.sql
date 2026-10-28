-- Daily class attendance for MARKAZ.
-- Apply once to an existing database. This is separate from the termly
-- behavioural_reports.attendance assessment used on result statements.

CREATE TABLE attendance_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_date DATE NOT NULL,
    class_type ENUM('conventional','arabic') NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    status ENUM('present','absent','late','excused') NOT NULL,
    remarks VARCHAR(500) NULL,
    marked_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_attendance_date_class_student
        (attendance_date, class_type, class_id, student_id),
    KEY idx_attendance_class_date
        (class_type, class_id, attendance_date),
    KEY idx_attendance_student_date
        (student_id, attendance_date),
    KEY idx_attendance_marker (marked_by),

    CONSTRAINT fk_attendance_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_attendance_marker
        FOREIGN KEY (marked_by) REFERENCES admins(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;
