-- Add optional gender details to staff accounts.
-- Existing records remain NULL; the account-management UI requires a selection
-- when creating or updating a teacher account.
ALTER TABLE admins
    ADD COLUMN gender ENUM('male', 'female') NULL AFTER role;
