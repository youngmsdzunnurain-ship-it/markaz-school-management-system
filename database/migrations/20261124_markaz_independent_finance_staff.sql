-- Separate payroll identity from academic login accounts.
-- Only historical payroll references are copied into the Finance register; no
-- academic teacher accounts are bulk-enrolled and no login accounts are created.
CREATE TABLE finance_staff (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_number VARCHAR(40) NOT NULL UNIQUE,
    full_name VARCHAR(160) NOT NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    job_title VARCHAR(100) NOT NULL DEFAULT 'Teacher',
    employment_date DATE NULL,
    notes VARCHAR(500) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_finance_staff_active_name (is_active, full_name),
    CONSTRAINT fk_finance_staff_creator FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Preserve any pre-existing Finance history without enrolling every teacher.
-- The legacy name/contact details are copied as a standalone Finance record;
-- no admins.id foreign key or account relationship is retained.
INSERT INTO finance_staff (staff_number, full_name, email, notes, is_active)
SELECT CONCAT('LEGACY-', legacy.account_id), COALESCE(NULLIF(a.name,''), CONCAT('Legacy staff ',legacy.account_id)),
       a.email, 'Migrated from a pre-existing Finance payroll reference; verify this independent staff record.',
       1
FROM (
    SELECT account_id FROM staff_salary_profiles
    UNION
    SELECT account_id FROM staff_attendance
    UNION
    SELECT account_id FROM finance_payroll_items
) legacy
LEFT JOIN admins a ON a.id = legacy.account_id
WHERE legacy.account_id IS NOT NULL;

ALTER TABLE staff_salary_profiles
    DROP FOREIGN KEY fk_salary_profile_account,
    DROP INDEX idx_salary_profile_account,
    CHANGE COLUMN account_id finance_staff_id BIGINT UNSIGNED NOT NULL,
    ADD KEY idx_salary_profile_staff (finance_staff_id, is_active, effective_from);

UPDATE staff_salary_profiles p
INNER JOIN finance_staff s ON s.staff_number = CONCAT('LEGACY-', p.finance_staff_id)
SET p.finance_staff_id = s.id;

ALTER TABLE staff_salary_profiles
    ADD CONSTRAINT fk_salary_profile_finance_staff
        FOREIGN KEY (finance_staff_id) REFERENCES finance_staff(id) ON DELETE RESTRICT;

ALTER TABLE staff_attendance
    DROP FOREIGN KEY fk_staff_attendance_account,
    DROP INDEX idx_staff_attendance_date,
    DROP INDEX uq_staff_attendance_day,
    CHANGE COLUMN account_id finance_staff_id BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_staff_attendance_day (finance_staff_id, attendance_date),
    ADD KEY idx_staff_attendance_date (attendance_date, status);

UPDATE staff_attendance a
INNER JOIN finance_staff s ON s.staff_number = CONCAT('LEGACY-', a.finance_staff_id)
SET a.finance_staff_id = s.id;

ALTER TABLE staff_attendance
    ADD CONSTRAINT fk_staff_attendance_finance_staff
        FOREIGN KEY (finance_staff_id) REFERENCES finance_staff(id) ON DELETE RESTRICT;

ALTER TABLE finance_payroll_items
    DROP FOREIGN KEY fk_payroll_item_account,
    DROP INDEX uq_payroll_item_staff,
    CHANGE COLUMN account_id finance_staff_id BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_payroll_item_staff (payroll_period_id, finance_staff_id);

UPDATE finance_payroll_items i
INNER JOIN finance_staff s ON s.staff_number = CONCAT('LEGACY-', i.finance_staff_id)
SET i.finance_staff_id = s.id;

ALTER TABLE finance_payroll_items
    ADD CONSTRAINT fk_payroll_item_finance_staff
        FOREIGN KEY (finance_staff_id) REFERENCES finance_staff(id) ON DELETE RESTRICT;
