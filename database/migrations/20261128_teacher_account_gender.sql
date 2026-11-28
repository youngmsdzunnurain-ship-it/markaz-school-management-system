-- Add optional gender metadata to staff accounts.
-- The account-management API requires a value for teacher accounts only.
ALTER TABLE admins
    ADD COLUMN gender ENUM('male', 'female') NULL AFTER name;
