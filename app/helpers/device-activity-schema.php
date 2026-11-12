<?php
declare(strict_types=1);

/**
 * Ensure the persistent device-usage table exists without issuing DDL on
 * every activity heartbeat. The migration remains the canonical schema.
 */
function ensureDeviceActivitySchema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $exists = $pdo->query(
        "SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'device_activity_sessions'
         LIMIT 1"
    )->fetchColumn();

    if ($exists) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS device_activity_sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        client_session_id CHAR(36) NOT NULL,
        identity_type ENUM('staff','student','online_class_student') NOT NULL,
        identity_key VARCHAR(190) NOT NULL,
        account_id BIGINT UNSIGNED NULL,
        student_id BIGINT UNSIGNED NULL,
        online_student_id BIGINT UNSIGNED NULL,
        display_name VARCHAR(190) NOT NULL,
        role VARCHAR(40) NOT NULL,
        device_hash CHAR(64) NOT NULL,
        user_agent VARCHAR(500) NOT NULL DEFAULT '',
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME NULL,
        duration_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY uq_device_activity_client_identity (client_session_id, identity_key),
        KEY idx_device_activity_identity (identity_type, identity_key, device_hash),
        KEY idx_device_activity_last_seen (last_activity_at),
        KEY idx_device_activity_online_student (online_student_id),
        CONSTRAINT fk_device_activity_account FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE SET NULL,
        CONSTRAINT fk_device_activity_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
        CONSTRAINT fk_device_activity_online_student FOREIGN KEY (online_student_id) REFERENCES online_class_students(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");
}
