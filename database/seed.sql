USE markaz_result_system;

INSERT INTO terms (name, sort_order)
VALUES
    ('First Term', 1),
    ('Second Term', 2),
    ('Third Term', 3);

INSERT INTO academic_sessions (name, is_current)
VALUES
    ('2026/2027', TRUE);

INSERT INTO settings (setting_key, setting_value, setting_type)
VALUES
    ('school_name', 'MARKAZ - Centre for Learning and Memorization of the Glorious Qur’an', 'text'),
    ('school_email', 'markazilquran@gmail.com', 'text'),
    ('grading_total', '100', 'number'),
    ('ca1_max', '20', 'number'),
    ('ca2_max', '20', 'number'),
    ('exam_max', '60', 'number');
