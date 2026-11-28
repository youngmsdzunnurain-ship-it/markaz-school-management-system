-- Add optional gender information to staff login accounts.
-- Safe to re-run: the column is added only when it does not already exist.

SET @has_admin_gender = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'admins'
      AND COLUMN_NAME = 'gender'
);

SET @add_admin_gender_sql = IF(
    @has_admin_gender = 0,
    'ALTER TABLE admins ADD COLUMN gender ENUM(''female'', ''male'') NULL DEFAULT NULL AFTER name',
    'SELECT 1'
);

PREPARE add_admin_gender_stmt FROM @add_admin_gender_sql;
EXECUTE add_admin_gender_stmt;
DEALLOCATE PREPARE add_admin_gender_stmt;
