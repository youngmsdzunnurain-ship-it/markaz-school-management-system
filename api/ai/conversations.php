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
try {
    markazAiResolveScope(db(), $accountId, $role);
} catch (Throwable $error) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->prepare(
        'SELECT c.id, c.title, c.created_at, c.updated_at,
                (SELECT COUNT(*) FROM markaz_ai_messages m
                 WHERE m.conversation_id = c.id AND m.account_id = c.account_id) AS message_count
         FROM markaz_ai_conversations c
         WHERE c.account_id = ? AND c.role_snapshot = ?
         ORDER BY c.updated_at DESC LIMIT 30'
    );
    $stmt->execute([$accountId, $role]);
    echo json_encode(['success' => true, 'conversations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

markazAiRequireSameOrigin();
$input = json_decode(file_get_contents('php://input'), true);
$title = trim((string) ($input['title'] ?? 'New conversation'));
$title = mb_substr($title !== '' ? $title : 'New conversation', 0, 160);
$stmt = db()->prepare('INSERT INTO markaz_ai_conversations (account_id, role_snapshot, title) VALUES (?, ?, ?)');
$stmt->execute([$accountId, $role, $title]);
$id = (int) db()->lastInsertId();
markazAiRecordEvent(db(), $accountId, $role, 'conversation_created', ['conversation_id' => $id]);
echo json_encode(['success' => true, 'conversation' => ['id' => $id, 'title' => $title]]);
