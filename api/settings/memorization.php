<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireSeniorAdmin();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query(
        'SELECT
            ac.id,
            ac.name,
            ac.sort_order,
            ac.is_active,
            m.memorization_portion
         FROM arabic_classes ac
         LEFT JOIN arabic_class_memorization_defaults m
            ON m.arabic_class_id = ac.id
         ORDER BY ac.sort_order ASC, ac.name ASC'
    );

    echo json_encode([
        'success' => true,
        'arabic_classes' => $stmt->fetchAll()
    ]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$arabicClassId = filter_input(
    INPUT_POST,
    'arabic_class_id',
    FILTER_VALIDATE_INT
);

$portion = trim((string) ($_POST['memorization_portion'] ?? ''));

if (!$arabicClassId) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Arabic class is required.'
    ]);
    exit;
}

if ($portion === '') {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Portion of memorization is required.'
    ]);
    exit;
}

$classStmt = $pdo->prepare(
    'SELECT id
     FROM arabic_classes
     WHERE id = ?
     LIMIT 1'
);

$classStmt->execute([$arabicClassId]);

if (!$classStmt->fetch()) {
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Arabic class not found.'
    ]);
    exit;
}

$stmt = $pdo->prepare(
    'INSERT INTO arabic_class_memorization_defaults
        (arabic_class_id, memorization_portion)
     VALUES
        (?, ?)
     ON DUPLICATE KEY UPDATE
        memorization_portion = VALUES(memorization_portion)'
);

$stmt->execute([
    $arabicClassId,
    $portion
]);

echo json_encode([
    'success' => true,
    'message' => 'Memorization portion saved successfully.'
]);
