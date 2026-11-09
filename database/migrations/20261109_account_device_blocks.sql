-- Track recognised browser/device installations separately from account-wide freezes.
CREATE TABLE IF NOT EXISTS login_devices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    device_token_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    blocked_at DATETIME NULL,
    blocked_by BIGINT UNSIGNED NULL,
    block_reason VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_login_device_account_token (account_id, device_token_hash),
    KEY idx_login_device_account_status (account_id, is_blocked),
    CONSTRAINT fk_login_devices_account FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE CASCADE,
    CONSTRAINT fk_login_devices_blocked_by FOREIGN KEY (blocked_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_device_blocks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    device_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    action ENUM('block','unblock') NOT NULL,
    reason VARCHAR(500) NULL,
    performed_by BIGINT UNSIGNED NOT NULL,
    performed_by_name VARCHAR(190) NOT NULL,
    performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_device_block_history (device_id, performed_at),
    CONSTRAINT fk_login_device_blocks_device FOREIGN KEY (device_id) REFERENCES login_devices(id) ON DELETE CASCADE,
    CONSTRAINT fk_login_device_blocks_account FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE CASCADE,
    CONSTRAINT fk_login_device_blocks_actor FOREIGN KEY (performed_by) REFERENCES admins(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
