<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/ss-curriculum.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

try {
    $classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);

    if (!$classId || $classId < 1) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'A valid class ID is required.'
        ]);

        exit;
    }

    $pdo = db();
    ensureSeniorSecondaryCurriculum($pdo, (int) $classId);

    $stmt = $pdo->prepare(
        'SELECT
            cs.id,
            cs.class_id,
            cs.subject_id,
            cs.is_required,
            cs.sort_order,
            s.name,
            s.code,
            s.category,
            s.max_ca1,
            s.max_ca2,
            s.max_exam,
            s.is_active
        FROM class_subjects cs
        INNER JOIN subjects s
            ON s.id = cs.subject_id
        WHERE cs.class_id = :class_id
        ORDER BY cs.sort_order ASC, s.name ASC'
    );

    $stmt->execute([
        'class_id' => $classId
    ]);

    $subjects = $stmt->fetchAll();

    foreach ($subjects as &$subject) {
        $subject['id'] = (int) $subject['id'];
        $subject['class_id'] = (int) $subject['class_id'];
        $subject['subject_id'] = (int) $subject['subject_id'];
        $subject['is_required'] = (bool) $subject['is_required'];
        $subject['sort_order'] = (int) $subject['sort_order'];
        $subject['max_ca1'] = (float) $subject['max_ca1'];
        $subject['max_ca2'] = (float) $subject['max_ca2'];
        $subject['max_exam'] = (float) $subject['max_exam'];
        $subject['is_active'] = (bool) $subject['is_active'];
    }

    unset($subject);

    echo json_encode([
        'success' => true,
        'class_id' => $classId,
        'subjects' => $subjects
    ]);

} catch (Throwable $error) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load class subjects.'
    ]);
}
