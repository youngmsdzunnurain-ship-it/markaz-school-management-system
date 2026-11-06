-- Explicitly identify the one academic period currently used by result pages.
-- Existing installations retain all historical periods and release states.
ALTER TABLE academic_periods
    ADD COLUMN is_current TINYINT(1) NOT NULL DEFAULT 0 AFTER term_id,
    ADD KEY idx_academic_period_current (is_current, academic_session_id);

UPDATE academic_periods
SET is_current = 0;

-- Preserve the most recently created period in the currently selected session
-- as the initial current term. Future changes are explicit in the settings API.
UPDATE academic_periods ap
INNER JOIN (
    SELECT MAX(ap2.id) AS id
    FROM academic_periods ap2
    INNER JOIN academic_sessions s
        ON s.id = ap2.academic_session_id
    WHERE s.is_current = 1
) active_period
    ON active_period.id = ap.id
SET ap.is_current = 1;
