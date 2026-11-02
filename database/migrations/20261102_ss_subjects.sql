-- Ensure SS1, SS2 and SS3 use the approved eleven-subject curriculum.
-- Re-running this migration is safe: subjects are inserted only when absent,
-- and each SS class receives one canonical mapping per subject.

START TRANSACTION;

INSERT INTO subjects (
    name,
    category,
    max_ca1,
    max_ca2,
    max_exam,
    is_active
)
SELECT
    desired.name,
    'Senior Secondary',
    20.00,
    20.00,
    60.00,
    1
FROM (
    SELECT 'Quran' AS name
    UNION ALL SELECT 'English'
    UNION ALL SELECT 'Mathematics'
    UNION ALL SELECT 'Biology'
    UNION ALL SELECT 'Chemistry'
    UNION ALL SELECT 'Physics'
    UNION ALL SELECT 'Civic Education'
    UNION ALL SELECT 'Agric'
    UNION ALL SELECT 'Computer'
    UNION ALL SELECT 'Food and Nutrition'
    UNION ALL SELECT 'Economics'
) AS desired
WHERE NOT EXISTS (
    SELECT 1
    FROM subjects existing
    WHERE LOWER(TRIM(existing.name)) = LOWER(desired.name)
);

-- Replace only the conventional SS class mappings. Existing result rows are
-- deliberately retained for historical auditability; unassigned subjects
-- simply stop appearing in current SS result entry and student result views.
DELETE cs
FROM class_subjects cs
INNER JOIN classes c ON c.id = cs.class_id
WHERE UPPER(REPLACE(REPLACE(REPLACE(TRIM(c.name), ' ', ''), '-', ''), '_', ''))
    IN ('SSS1', 'SSS2', 'SSS3', 'SS1', 'SS2', 'SS3');

-- Use the lowest-ID matching subject record if historical duplicates exist.
-- The CASE expression establishes the exact requested display/result order.
INSERT INTO class_subjects (
    class_id,
    subject_id,
    is_required,
    sort_order
)
SELECT
    c.id,
    canonical.id,
    1,
    CASE LOWER(TRIM(s.name))
        WHEN 'quran' THEN 1
        WHEN 'english' THEN 2
        WHEN 'mathematics' THEN 3
        WHEN 'biology' THEN 4
        WHEN 'chemistry' THEN 5
        WHEN 'physics' THEN 6
        WHEN 'civic education' THEN 7
        WHEN 'agric' THEN 8
        WHEN 'computer' THEN 9
        WHEN 'food and nutrition' THEN 10
        WHEN 'economics' THEN 11
    END
FROM classes c
INNER JOIN (
    SELECT LOWER(TRIM(name)) AS subject_key, MIN(id) AS id
    FROM subjects
    GROUP BY LOWER(TRIM(name))
) canonical
    ON canonical.subject_key IN (
        'quran',
        'english',
        'mathematics',
        'biology',
        'chemistry',
        'physics',
        'civic education',
        'agric',
        'computer',
        'food and nutrition',
        'economics'
    )
INNER JOIN subjects s ON s.id = canonical.id
WHERE UPPER(REPLACE(REPLACE(REPLACE(TRIM(c.name), ' ', ''), '-', ''), '_', ''))
    IN ('SSS1', 'SSS2', 'SSS3', 'SS1', 'SS2', 'SS3')
ORDER BY c.id, CASE LOWER(TRIM(s.name))
    WHEN 'quran' THEN 1
    WHEN 'english' THEN 2
    WHEN 'mathematics' THEN 3
    WHEN 'biology' THEN 4
    WHEN 'chemistry' THEN 5
    WHEN 'physics' THEN 6
    WHEN 'civic education' THEN 7
    WHEN 'agric' THEN 8
    WHEN 'computer' THEN 9
    WHEN 'food and nutrition' THEN 10
    WHEN 'economics' THEN 11
END;

COMMIT;
