<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireSeniorAdmin();

$studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
$portion = trim((string) ($_POST['memorization_portion'] ?? ''));

if (!$studentId) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Student ID is required.'
    ]);
    exit;
}

if (mb_strlen($portion) > 500) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Portion of memorization is too long.'
    ]);
    exit;
}

$pdo = db();

$studentStmt = $pdo->prepare(
    'SELECT id
     FROM students
     WHERE id = ?
     LIMIT 1'
);

$studentStmt->execute([$studentId]);

if (!$studentStmt->fetch()) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Student not found.'
    ]);
    exit;
}

$stmt = $pdo->prepare(
    'UPDATE students
     SET memorization_portion_override = ?
     WHERE id = ?'
);

$stmt->execute([
    $portion !== '' ? $portion : null,
    $studentId
]);

echo json_encode([
    'success' => true,
    'message' => 'Memorization portion saved successfully.'
]);
