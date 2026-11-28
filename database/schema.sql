CREATE DATABASE IF NOT EXISTS markaz_result_system
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE markaz_result_system;

-- =========================================================
-- ADMINISTRATORS
-- =========================================================
CREATE TABLE admins (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    role ENUM('admin','exam_officer','teacher','student','management') NOT NULL DEFAULT 'exam_officer',
    gender ENUM('male','female') NULL,
    student_id BIGINT UNSIGNED NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- ACADEMIC SESSIONS
-- =========================================================
CREATE TABLE academic_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    is_current BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- TERMS
-- =========================================================
CREATE TABLE terms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    sort_order TINYINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- ACADEMIC PERIODS
-- Links a session + term and stores important dates.
-- =========================================================
CREATE TABLE academic_periods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_session_id BIGINT UNSIGNED NOT NULL,
    term_id BIGINT UNSIGNED NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    examination_date DATE NULL,
    vacation_date DATE NULL,
    resumption_date DATE NULL,
    results_released TINYINT(1) NOT NULL DEFAULT 0,
    is_finalized TINYINT(1) NOT NULL DEFAULT 0,
    finalized_at DATETIME NULL,
    finalized_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_academic_period (academic_session_id, term_id),
    KEY idx_academic_period_current (is_current, academic_session_id),
    KEY idx_academic_period_finalized (is_finalized, academic_session_id),

    CONSTRAINT fk_period_session
        FOREIGN KEY (academic_session_id)
        REFERENCES academic_sessions(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_period_term
        FOREIGN KEY (term_id)
        REFERENCES terms(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_academic_period_finalized_by
        FOREIGN KEY (finalized_by)
        REFERENCES admins(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- CLASSES
-- =========================================================
CREATE TABLE classes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    level_group VARCHAR(100) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- SUBJECTS
-- =========================================================
CREATE TABLE subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NULL UNIQUE,
    category VARCHAR(100) NULL,
    max_ca1 DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    max_ca2 DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    max_exam DECIMAL(5,2) NOT NULL DEFAULT 60.00,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- CLASS SUBJECTS
-- Determines which subjects belong to each class.
-- =========================================================
CREATE TABLE class_subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    is_required BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_class_subject (class_id, subject_id),

    CONSTRAINT fk_class_subject_class
        FOREIGN KEY (class_id)
        REFERENCES classes(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_class_subject_subject
        FOREIGN KEY (subject_id)
        REFERENCES subjects(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- STUDENTS
-- =========================================================
CREATE TABLE students (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admission_number VARCHAR(80) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female') NULL,
    date_of_birth DATE NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    admission_date DATE NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_students_class (class_id),
    INDEX idx_students_active (is_active),

    CONSTRAINT fk_student_class
        FOREIGN KEY (class_id)
        REFERENCES classes(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =========================================================
-- MEMORIZATION PORTIONS
-- Defines memorization portion automatically for a class.
-- =========================================================
CREATE TABLE memorization_portions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id BIGINT UNSIGNED NOT NULL,
    academic_period_id BIGINT UNSIGNED NOT NULL,
    portion_from VARCHAR(255) NOT NULL,
    portion_to VARCHAR(255) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_memorization_class_period
        (class_id, academic_period_id),

    CONSTRAINT fk_memorization_class
        FOREIGN KEY (class_id)
        REFERENCES classes(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_memorization_period
        FOREIGN KEY (academic_period_id)
        REFERENCES academic_periods(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- RESULTS
-- One row = one student + one subject + one academic period.
-- Total is calculated automatically by the application.
-- =========================================================
CREATE TABLE results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    academic_period_id BIGINT UNSIGNED NOT NULL,

    ca1 DECIMAL(5,2) NULL DEFAULT NULL,
    ca2 DECIMAL(5,2) NULL DEFAULT NULL,
    exam DECIMAL(5,2) NULL DEFAULT NULL,

    teacher_remark VARCHAR(255) NULL,
    result_remark VARCHAR(255) NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_student_subject_period
        (student_id, subject_id, academic_period_id),

    INDEX idx_results_student (student_id),
    INDEX idx_results_subject (subject_id),
    INDEX idx_results_period (academic_period_id),

    CONSTRAINT fk_result_student
        FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_result_subject
        FOREIGN KEY (subject_id)
        REFERENCES subjects(id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_result_period
        FOREIGN KEY (academic_period_id)
        REFERENCES academic_periods(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- STUDENT BEHAVIOR / CHARACTER ASSESSMENTS
-- Exact fields can be expanded after the official MARKAZ
-- report card is available.
-- =========================================================
CREATE TABLE student_assessments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    academic_period_id BIGINT UNSIGNED NOT NULL,

    attendance VARCHAR(50) NULL,
    punctuality VARCHAR(50) NULL,
    neatness VARCHAR(50) NULL,
    conduct VARCHAR(50) NULL,
    cooperation VARCHAR(50) NULL,
    attentiveness VARCHAR(50) NULL,
    teacher_comment TEXT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_student_assessment_period
        (student_id, academic_period_id),

    CONSTRAINT fk_assessment_student
        FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_assessment_period
        FOREIGN KEY (academic_period_id)
        REFERENCES academic_periods(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- SYSTEM SETTINGS
-- General configurable system values.
-- =========================================================
CREATE TABLE settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type VARCHAR(30) NOT NULL DEFAULT 'text',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- EXEMPTION DATES
-- Dates that can be configured from Settings.
-- =========================================================
CREATE TABLE exemption_dates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_period_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    exemption_date DATE NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_exemption_period
        FOREIGN KEY (academic_period_id)
        REFERENCES academic_periods(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================================
-- ACCOUNT-SPECIFIC PERMISSIONS
-- =========================================================
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

-- =========================================================
-- TEACHER CLASS AND SUBJECT ASSIGNMENTS
-- NULL subject_id means all subjects in that class.
-- class_type distinguishes conventional and Arabic class IDs.
-- =========================================================
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

ALTER TABLE admins
    ADD CONSTRAINT fk_admins_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON DELETE SET NULL;
