-- Explicit lateness opt-in and secure payroll profile details.
ALTER TABLE staff_salary_profiles
    ADD COLUMN deduct_lateness TINYINT(1) NOT NULL DEFAULT 0 AFTER lateness_rate_per_minute;

ALTER TABLE finance_staff
    ADD COLUMN age TINYINT UNSIGNED NULL AFTER phone,
    ADD COLUMN bank_account_number VARCHAR(34) NULL AFTER email,
    ADD COLUMN bank_account_name VARCHAR(160) NULL AFTER bank_account_number,
    ADD COLUMN bank_name VARCHAR(100) NULL AFTER bank_account_name,
    ADD COLUMN teaching_type VARCHAR(160) NULL AFTER job_title;
