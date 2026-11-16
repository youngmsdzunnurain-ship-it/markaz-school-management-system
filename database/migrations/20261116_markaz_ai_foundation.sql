-- MARKAZ AI foundation: private conversations and auditable school activity.
-- Apply once after backing up the database. No existing school data is altered.
CREATE TABLE IF NOT EXISTS markaz_ai_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    role_snapshot VARCHAR(30) NOT NULL,
    title VARCHAR(160) NOT NULL DEFAULT 'New conversation',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_markaz_ai_conversation_owner_updated (account_id, updated_at),
    CONSTRAINT fk_markaz_ai_conversation_account
        FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS markaz_ai_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    content MEDIUMTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_markaz_ai_messages_owner_conversation (account_id, conversation_id, id),
    CONSTRAINT fk_markaz_ai_message_conversation
        FOREIGN KEY (conversation_id) REFERENCES markaz_ai_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_markaz_ai_message_account
        FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS markaz_ai_audit_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NULL,
    account_name_snapshot VARCHAR(160) NOT NULL,
    role_snapshot VARCHAR(30) NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_markaz_ai_audit_created (created_at),
    KEY idx_markaz_ai_audit_account_created (account_id, created_at),
    CONSTRAINT fk_markaz_ai_audit_account
        FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS markaz_activity_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NULL,
    account_name_snapshot VARCHAR(160) NOT NULL,
    role_snapshot VARCHAR(30) NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_markaz_activity_created (created_at),
    KEY idx_markaz_activity_account_created (account_id, created_at),
    KEY idx_markaz_activity_type_created (event_type, created_at),
    CONSTRAINT fk_markaz_activity_account
        FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Student Portal conversations are isolated by the verified portal session,
-- not merely by student ID/admission number.
CREATE TABLE IF NOT EXISTS markaz_ai_student_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    session_key_hash CHAR(64) NOT NULL,
    title VARCHAR(160) NOT NULL DEFAULT 'My learning conversation',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_markaz_ai_student_owner_updated (student_id, session_key_hash, updated_at),
    CONSTRAINT fk_markaz_ai_student_conversation_student
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS markaz_ai_student_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    session_key_hash CHAR(64) NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    content MEDIUMTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_markaz_ai_student_messages_owner (student_id, session_key_hash, conversation_id, id),
    CONSTRAINT fk_markaz_ai_student_message_conversation
        FOREIGN KEY (conversation_id) REFERENCES markaz_ai_student_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_markaz_ai_student_message_student
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS markaz_ai_student_audit_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NULL,
    student_name_snapshot VARCHAR(160) NOT NULL,
    session_key_hash CHAR(64) NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_markaz_ai_student_audit_created (created_at),
    KEY idx_markaz_ai_student_audit_owner (student_id, session_key_hash, created_at),
    CONSTRAINT fk_markaz_ai_student_audit_student
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
) ENGINE=InnoDB;
