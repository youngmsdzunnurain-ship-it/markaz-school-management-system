-- Preserve academic periods as immutable historical records after finalization.
-- Safe for existing installations: all existing periods remain available and unlocked.
ALTER TABLE academic_periods
    ADD COLUMN is_finalized TINYINT(1) NOT NULL DEFAULT 0 AFTER results_released,
    ADD COLUMN finalized_at DATETIME NULL AFTER is_finalized,
    ADD COLUMN finalized_by BIGINT UNSIGNED NULL AFTER finalized_at,
    ADD KEY idx_academic_period_finalized (is_finalized, academic_session_id),
    ADD CONSTRAINT fk_academic_period_finalized_by
        FOREIGN KEY (finalized_by) REFERENCES admins(id)
        ON DELETE SET NULL;
