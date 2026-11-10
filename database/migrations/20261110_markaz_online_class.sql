-- MARKAZ Online Class: isolated academic workspace.
-- Safe to apply once; CREATE IF NOT EXISTS and INSERT IGNORE preserve existing data.

CREATE TABLE IF NOT EXISTS online_class_levels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(40) NOT NULL UNIQUE,
    sort_order TINYINT UNSIGNED NOT NULL,
    tutor_name VARCHAR(150) NULL,
    task_one_name VARCHAR(100) NOT NULL DEFAULT 'Task One',
    task_two_name VARCHAR(100) NOT NULL DEFAULT 'Task Two',
    memorization_portion VARCHAR(255) NULL,
    first_term_resumption DATE NULL,
    second_term_resumption DATE NULL,
    third_term_resumption DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO online_class_levels (name, sort_order) VALUES
('Level 1', 1), ('Level 2', 2), ('Level 3', 3), ('Level 4', 4);

CREATE TABLE IF NOT EXISTS online_class_subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_online_subject_level_name (level_id, name),
    KEY idx_online_subject_level (level_id, is_active),
    CONSTRAINT fk_online_subject_level FOREIGN KEY (level_id)
        REFERENCES online_class_levels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS online_class_admission_sequences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO settings (setting_key, setting_value, setting_type)
VALUES ('online_class_student_access', '1', 'boolean');

CREATE TABLE IF NOT EXISTS online_class_students (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admission_number VARCHAR(40) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('Male','Female') NULL,
    level_id BIGINT UNSIGNED NOT NULL,
    section VARCHAR(80) NOT NULL DEFAULT 'A',
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    password_hash VARCHAR(255) NOT NULL,
    profile_image VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_online_students_level (level_id, is_active),
    CONSTRAINT fk_online_student_level FOREIGN KEY (level_id)
        REFERENCES online_class_levels(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS online_class_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present','absent','late','excused') NOT NULL,
    remarks VARCHAR(500) NULL,
    marked_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_online_attendance_student_date (student_id, attendance_date),
    KEY idx_online_attendance_date (attendance_date, status),
    CONSTRAINT fk_online_attendance_student FOREIGN KEY (student_id)
        REFERENCES online_class_students(id) ON DELETE CASCADE,
    CONSTRAINT fk_online_attendance_staff FOREIGN KEY (marked_by)
        REFERENCES admins(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS online_class_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    academic_session VARCHAR(30) NOT NULL,
    term ENUM('First Term','Second Term','Third Term') NOT NULL,
    attendance_score DECIMAL(5,2) NULL,
    test_score DECIMAL(5,2) NULL,
    exam_score DECIMAL(5,2) NULL,
    teacher_remark VARCHAR(255) NULL,
    director_remark VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_online_result (student_id, subject_id, academic_session, term),
    KEY idx_online_result_term (academic_session, term),
    CONSTRAINT fk_online_result_student FOREIGN KEY (student_id)
        REFERENCES online_class_students(id) ON DELETE CASCADE,
    CONSTRAINT fk_online_result_subject FOREIGN KEY (subject_id)
        REFERENCES online_class_subjects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_online_result_creator FOREIGN KEY (created_by)
        REFERENCES admins(id) ON DELETE RESTRICT,
    CONSTRAINT fk_online_result_updater FOREIGN KEY (updated_by)
        REFERENCES admins(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS online_class_result_releases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_id BIGINT UNSIGNED NOT NULL,
    academic_session VARCHAR(30) NOT NULL,
    term ENUM('First Term','Second Term','Third Term') NOT NULL,
    is_released TINYINT(1) NOT NULL DEFAULT 0,
    released_by BIGINT UNSIGNED NULL,
    released_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_online_result_release (level_id, academic_session, term),
    CONSTRAINT fk_online_release_level FOREIGN KEY (level_id)
        REFERENCES online_class_levels(id) ON DELETE CASCADE,
    CONSTRAINT fk_online_release_staff FOREIGN KEY (released_by)
        REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;
