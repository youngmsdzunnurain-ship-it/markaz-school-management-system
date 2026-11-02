<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

try {
    $classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
    if (!$classId || $classId < 1) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'A valid class ID is required.']);
        exit;
    }

    $pdo = db();
    $classStmt = $pdo->prepare('SELECT name FROM classes WHERE id = ? LIMIT 1');
    $classStmt->execute([$classId]);
    $className = (string) $classStmt->fetchColumn();
    $normalizedClass = strtoupper((string) preg_replace('/[\\s_-]+/', '', trim($className)));
    $isSeniorSecondary = in_array($normalizedClass, ['SS1', 'SS2', 'SS3', 'SSS1', 'SSS2', 'SSS3'], true);

    $sql = 'SELECT
            id,
            name,
            code,
            category,
            max_ca1,
            max_ca2,
            max_exam,
            is_active
        FROM subjects
        WHERE is_active = 1';
    if ($isSeniorSecondary) {
        $sql .= " AND LOWER(TRIM(name)) <> 'animal husbandry'";
    }
    $sql .= ' ORDER BY name ASC, id ASC';
    $stmt = $pdo->query($sql);

    $subjects = $stmt->fetchAll();

    foreach ($subjects as &$subject) {
        $subject['id'] = (int) $subject['id'];
        $subject['max_ca1'] = (float) $subject['max_ca1'];
        $subject['max_ca2'] = (float) $subject['max_ca2'];
        $subject['max_exam'] = (float) $subject['max_exam'];
        $subject['is_active'] = (bool) $subject['is_active'];
    }

    unset($subject);

    echo json_encode([
        'success' => true,
        'subjects' => $subjects
    ]);

} catch (Throwable $error) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load available subjects.'
    ]);
}
