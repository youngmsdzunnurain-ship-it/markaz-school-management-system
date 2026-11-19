-- Private staff-to-staff messaging for MARKAZ. Apply once after backing up the database.
CREATE TABLE staff_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_type ENUM('direct','group') NOT NULL DEFAULT 'direct',
    title VARCHAR(120) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_staff_conversations_updated (updated_at),
    CONSTRAINT fk_staff_conversations_creator FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE staff_conversation_participants (
    conversation_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (conversation_id, account_id),
    KEY idx_staff_participants_account (account_id, conversation_id),
    CONSTRAINT fk_staff_participants_conversation FOREIGN KEY (conversation_id) REFERENCES staff_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_staff_participants_account FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE staff_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    message_type ENUM('text') NOT NULL DEFAULT 'text',
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_staff_messages_conversation (conversation_id, id),
    KEY idx_staff_messages_sender (sender_id, created_at),
    CONSTRAINT fk_staff_messages_conversation FOREIGN KEY (conversation_id) REFERENCES staff_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_staff_messages_sender FOREIGN KEY (sender_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;
