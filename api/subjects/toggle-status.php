<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

try {
    $input = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($input)) {
        throw new RuntimeException('Invalid request data.');
    }

    $id = isset($input['id']) ? (int) $input['id'] : 0;

    if ($id <= 0) {
        throw new RuntimeException('Invalid subject.');
    }

    $stmt = db()->prepare(
        'SELECT id, is_active
         FROM subjects
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$id]);

    $subject = $stmt->fetch();

    if (!$subject) {
        throw new RuntimeException('Subject not found.');
    }

    $newStatus = ((int) $subject['is_active'] === 1) ? 0 : 1;

    $update = db()->prepare(
        'UPDATE subjects
         SET
            is_active = ?,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
    );

    $update->execute([
        $newStatus,
        $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => $newStatus
            ? 'Subject activated successfully.'
            : 'Subject deactivated successfully.',
        'is_active' => (bool) $newStatus
    ]);

} catch (Throwable $error) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $error->getMessage()
    ]);
}
