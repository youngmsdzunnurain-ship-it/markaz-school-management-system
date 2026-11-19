-- Staff chat avatars, online presence, delivery acknowledgements and read receipts.
-- Apply once after backing up the MARKAZ database.
ALTER TABLE staff_conversation_participants
    ADD COLUMN last_delivered_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_read_message_id;

CREATE TABLE staff_presence (
    account_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    last_seen_at DATETIME NOT NULL,
    CONSTRAINT fk_staff_presence_account FOREIGN KEY (account_id)
        REFERENCES admins(id) ON DELETE CASCADE,
    KEY idx_staff_presence_last_seen (last_seen_at)
) ENGINE=InnoDB;
