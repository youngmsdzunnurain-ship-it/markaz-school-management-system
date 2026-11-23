-- Separate time-based attendance from attendance-only staff.
-- Existing profiles/records default to time-based to preserve prior payroll behaviour.
ALTER TABLE staff_salary_profiles
    MODIFY COLUMN expected_arrival TIME NULL,
    ADD COLUMN attendance_mode ENUM('time_based','attendance_only') NOT NULL DEFAULT 'time_based' AFTER expected_arrival;

ALTER TABLE staff_attendance
    MODIFY COLUMN expected_arrival TIME NULL,
    ADD COLUMN attendance_mode ENUM('time_based','attendance_only') NOT NULL DEFAULT 'time_based' AFTER expected_arrival;

-- Existing records remain time-based. Attendance-only profiles must not carry
-- an arrival schedule or per-minute lateness rate into future calculations.
UPDATE staff_salary_profiles
SET expected_arrival = NULL, lateness_rate_per_minute = 0
WHERE attendance_mode = 'attendance_only';
