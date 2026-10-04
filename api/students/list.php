<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

$stmt = db()->query(
    'SELECT
        s.id,
        s.admission_number,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.parent_guardian_name,
        s.parent_guardian_phone,
        s.profile_image,
        s.gender,
        s.student_type,
        s.date_of_birth,
        s.class_id,
        s.arabic_class_id,
        s.admission_date,
        s.is_active,
        c.name AS class_name,
        ac.name AS arabic_class_name
     FROM students s
     INNER JOIN classes c ON c.id = s.class_id
     LEFT JOIN arabic_classes ac ON ac.id = s.arabic_class_id
     ORDER BY s.last_name ASC, s.first_name ASC'
);

echo json_encode([
    'success' => true,
    'students' => $stmt->fetchAll()
]);
