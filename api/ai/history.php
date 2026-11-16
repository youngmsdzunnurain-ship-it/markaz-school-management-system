<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/markaz-ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
requireLogin();
$accountId = (int) $_SESSION['admin_id'];
$role = currentAdminRole();
try { markazAiResolveScope(db(), $accountId, $role); } catch (Throwable $error) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
    exit;
}
$conversationId = filter_input(INPUT_GET, 'conversation_id', FILTER_VALIDATE_INT);
if (!$conversationId || $conversationId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid conversation is required.']);
    exit;
}
$owner = db()->prepare('SELECT id FROM markaz_ai_conversations WHERE id = ? AND account_id = ? AND role_snapshot = ? LIMIT 1');
$owner->execute([$conversationId, $accountId, $role]);
if (!$owner->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'This conversation is not available to your account.']);
    exit;
}
$stmt = db()->prepare(
    'SELECT id, role, content, created_at FROM markaz_ai_messages
     WHERE conversation_id = ? AND account_id = ? ORDER BY id ASC LIMIT 100'
);
$stmt->execute([$conversationId, $accountId]);
echo json_encode(['success' => true, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
