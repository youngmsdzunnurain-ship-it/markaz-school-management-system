-- MARKAZ role accounts, per-account permissions and teacher assignments.
-- Run once against an existing MARKAZ database.

ALTER TABLE admins
    MODIFY COLUMN role
        ENUM('admin','exam_officer','teacher','student','management')
        NOT NULL DEFAULT 'exam_officer',
    ADD COLUMN student_id BIGINT UNSIGNED NULL UNIQUE,
    ADD CONSTRAINT fk_admins_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE SET NULL;

CREATE TABLE account_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    is_allowed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_account_permission (account_id, permission_key),
    CONSTRAINT fk_account_permissions_account
        FOREIGN KEY (account_id) REFERENCES admins(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE teacher_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    class_type ENUM('conventional','arabic') NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_teacher_assignment_lookup
        (account_id, class_type, class_id, subject_id),
    KEY idx_teacher_class (class_type, class_id),
    CONSTRAINT fk_teacher_assignments_account
        FOREIGN KEY (account_id) REFERENCES admins(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_teacher_assignments_subject
        FOREIGN KEY (subject_id) REFERENCES subjects(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;
