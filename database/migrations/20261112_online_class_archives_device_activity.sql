-- Immutable Online Class archives and namespaced Online Class device identities.
CREATE TABLE IF NOT EXISTS online_class_archives (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    archive_type ENUM('attendance','results') NOT NULL,
    destination ENUM('online','device') NOT NULL DEFAULT 'online',
    level_id BIGINT UNSIGNED NOT NULL,
    academic_session VARCHAR(30) NULL,
    term ENUM('First Term','Second Term','Third Term') NULL,
    record_count INT UNSIGNED NOT NULL DEFAULT 0,
    coverage_start DATE NULL,
    coverage_end DATE NULL,
    is_released TINYINT(1) NOT NULL DEFAULT 0,
    payload LONGBLOB NOT NULL,
    payload_sha256 CHAR(64) NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_online_archive_scope (archive_type, level_id, academic_session, term, created_at),
    KEY idx_online_archive_created (created_at),
    CONSTRAINT fk_online_archive_level FOREIGN KEY (level_id) REFERENCES online_class_levels(id) ON DELETE RESTRICT,
    CONSTRAINT fk_online_archive_staff FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

ALTER TABLE device_activity_sessions
    MODIFY identity_type ENUM('staff','student','online_class_student') NOT NULL,
    ADD COLUMN online_student_id BIGINT UNSIGNED NULL AFTER student_id,
    ADD KEY idx_device_activity_online_student (online_student_id),
    ADD CONSTRAINT fk_device_activity_online_student
        FOREIGN KEY (online_student_id) REFERENCES online_class_students(id) ON DELETE SET NULL;
