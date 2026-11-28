-- Store gender for academic teacher login accounts.
-- Apply once after the existing admins table migrations.
ALTER TABLE admins
    ADD COLUMN gender ENUM('male', 'female') NULL AFTER name;
