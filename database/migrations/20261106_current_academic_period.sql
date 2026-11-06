-- Explicitly identify the one academic period currently used by result pages.
-- Existing installations retain all historical periods and release states.
ALTER TABLE academic_periods
    ADD COLUMN is_current TINYINT(1) NOT NULL DEFAULT 0 AFTER term_id,
    ADD KEY idx_academic_period_current (is_current, academic_session_id);

UPDATE academic_periods
SET is_current = 0;

-- Recover the period most recently saved in the currently selected session.
-- The previous settings API updated updated_at on the selected term but did not
-- mark it current, so this preserves the administrator's latest successful choice.
UPDATE academic_periods ap
INNER JOIN (
    SELECT ap2.id
    FROM academic_periods ap2
    INNER JOIN academic_sessions s
        ON s.id = ap2.academic_session_id
    WHERE s.is_current = 1
    ORDER BY ap2.updated_at DESC, ap2.id DESC
    LIMIT 1
) active_period
    ON active_period.id = ap.id
SET ap.is_current = 1;
