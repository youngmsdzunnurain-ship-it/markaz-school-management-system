-- Add student sections to an already-installed Online Class module.
-- MariaDB supports IF NOT EXISTS; safe to run more than once.
ALTER TABLE online_class_students ADD COLUMN IF NOT EXISTS section VARCHAR(80) NOT NULL DEFAULT 'A' AFTER level_id;
