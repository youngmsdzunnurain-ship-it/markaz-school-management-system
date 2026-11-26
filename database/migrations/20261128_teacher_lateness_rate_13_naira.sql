-- Change the default lateness rate to the school's approved ₦13 per minute.
-- Existing default-rate profiles move from ₦10 to ₦13; other configured rates remain unchanged.
ALTER TABLE staff_salary_profiles
    MODIFY COLUMN lateness_rate_per_minute DECIMAL(10,2) NOT NULL DEFAULT 13.00;

UPDATE staff_salary_profiles
SET lateness_rate_per_minute = 13.00
WHERE lateness_rate_per_minute = 10.00;
