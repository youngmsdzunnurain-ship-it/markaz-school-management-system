<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
require_once __DIR__ . '/../../app/helpers/markaz-ai.php';
markazAiRequireSameOrigin();
$input = json_decode(file_get_contents('php://input'), true);
$id = filter_var($input['conversation_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid conversation is required.']);
    exit;
}
$stmt = db()->prepare('DELETE FROM markaz_ai_conversations WHERE id = ? AND account_id = ? AND role_snapshot = ?');
$stmt->execute([$id, (int) $_SESSION['admin_id'], currentAdminRole()]);
if ($stmt->rowCount() !== 1) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'This conversation is not available to your account.']);
    exit;
}
echo json_encode(['success' => true]);
