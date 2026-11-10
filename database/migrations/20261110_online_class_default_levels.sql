-- Ensure the four original Online Class levels exist even when the base
-- Online Class schema migration was applied before its seed data was restored.
-- The unique level name and INSERT IGNORE make this safe to apply repeatedly.
INSERT IGNORE INTO online_class_levels (name, sort_order) VALUES
('Level 1', 1),
('Level 2', 2),
('Level 3', 3),
('Level 4', 4);
