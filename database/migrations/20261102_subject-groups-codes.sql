-- Add a separate academic grouping; keep category as the existing school-level field.
-- Existing subject IDs, class_subjects mappings and results are not changed.
ALTER TABLE subjects
    ADD COLUMN IF NOT EXISTS subject_group VARCHAR(60) NULL AFTER category;

-- Give every legacy subject with a missing code a stable, unique code based on its ID.
UPDATE subjects
SET code = CONCAT('SUB', LPAD(id, 4, '0'))
WHERE code IS NULL OR TRIM(code) = '';

-- Backfill broad academic groups only where no group has already been assigned.
UPDATE subjects
SET subject_group = CASE
    WHEN LOWER(REPLACE(REPLACE(REPLACE(TRIM(name), ' ', ''), '-', ''), '_', '')) IN
        ('quran', 'english', 'englishlanguage', 'mathematics', 'math', 'maths')
        THEN 'Core'
    WHEN LOWER(REPLACE(REPLACE(REPLACE(TRIM(name), ' ', ''), '-', ''), '_', '')) IN
        ('biology', 'chemistry', 'physics', 'basicscience', 'computer',
         'computerstudies', 'ict', 'agric', 'agriculture', 'agriculturalscience',
         'civiceducation', 'civic', 'economics', 'foodandnutrition',
         'foodnutrition', 'animalhusbandry', 'socialstudies', 'geography')
        THEN 'Science'
    WHEN LOWER(REPLACE(REPLACE(REPLACE(TRIM(name), ' ', ''), '-', ''), '_', '')) IN
        ('tawheed', 'fiqh', 'hadith', 'hadeeth', 'islamicstudies',
         'islamicreligiousstudies', 'sirah', 'seerah')
        THEN 'Islamic Studies'
    WHEN LOWER(REPLACE(REPLACE(REPLACE(TRIM(name), ' ', ''), '-', ''), '_', '')) IN
        ('arabic', 'french', 'hausa', 'yoruba', 'igbo')
        THEN 'Languages'
    WHEN LOWER(REPLACE(REPLACE(REPLACE(TRIM(name), ' ', ''), '-', ''), '_', '')) IN
        ('history', 'government', 'literature', 'literatureinenglish', 'socialscience')
        THEN 'Humanities'
    WHEN LOWER(REPLACE(REPLACE(REPLACE(TRIM(name), ' ', ''), '-', ''), '_', '')) IN
        ('businessstudies', 'commerce', 'accounting', 'bookkeeping',
         'homeeconomics', 'technicaldrawing', 'creativearts', 'finearts')
        THEN 'Vocational'
    ELSE 'Other'
END
WHERE subject_group IS NULL OR TRIM(subject_group) = '';
