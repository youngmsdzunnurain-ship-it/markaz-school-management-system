<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/markaz-ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
requireLogin();

function markazAiRespond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    markazAiRespond(['success' => false, 'message' => 'Method not allowed.'], 405);
}

markazAiRequireSameOrigin();
$input = json_decode(file_get_contents('php://input'), true);
$message = trim((string) ($input['message'] ?? ''));
if ($message === '' || mb_strlen($message) > 4000) {
    markazAiRespond(['success' => false, 'message' => 'Enter a message of up to 4,000 characters.'], 422);
}

$accountId = (int) $_SESSION['admin_id'];
$role = currentAdminRole();
$pdo = db();

try {
    $scope = markazAiResolveScope($pdo, $accountId, $role);
} catch (Throwable $error) {
    markazAiRespond(['success' => false, 'message' => $error->getMessage()], 403);
}

$rate = $pdo->prepare(
    "SELECT COUNT(*) FROM markaz_ai_audit_events
     WHERE account_id = ? AND event_type = 'message_started' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$rate->execute([$accountId]);
if ((int) $rate->fetchColumn() >= 30) {
    markazAiRespond(['success' => false, 'message' => 'You have reached the hourly AI message limit. Please try again later.'], 429);
}

$conversationId = filter_var($input['conversation_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
if ($conversationId > 0) {
    $owner = $pdo->prepare(
        'SELECT id FROM markaz_ai_conversations
         WHERE id = ? AND account_id = ? AND role_snapshot = ? LIMIT 1'
    );
    $owner->execute([$conversationId, $accountId, $role]);
    if (!$owner->fetchColumn()) {
        markazAiRespond(['success' => false, 'message' => 'This conversation is not available to your account.'], 404);
    }
} else {
    $create = $pdo->prepare(
        'INSERT INTO markaz_ai_conversations (account_id, role_snapshot, title) VALUES (?, ?, ?)'
    );
    $create->execute([$accountId, $role, mb_substr($message, 0, 72)]);
    $conversationId = (int) $pdo->lastInsertId();
}

markazAiRecordEvent($pdo, $accountId, $role, 'message_started', ['conversation_id' => $conversationId]);
$workspaceContext = markazAiLoadWorkspaceContext($pdo, $accountId, $scope);
$answer = markazAiBuildFreeAnswer($message, $scope, $workspaceContext);

$pdo->beginTransaction();
try {
    $save = $pdo->prepare(
        'INSERT INTO markaz_ai_messages (conversation_id, account_id, role, content)
         VALUES (?, ?, ?, ?)'
    );
    $save->execute([$conversationId, $accountId, 'user', $message]);
    $save->execute([$conversationId, $accountId, 'assistant', $answer]);
    $pdo->prepare('UPDATE markaz_ai_conversations SET updated_at = NOW() WHERE id = ? AND account_id = ?')
        ->execute([$conversationId, $accountId]);
    markazAiRecordEvent($pdo, $accountId, $role, 'message_completed', ['conversation_id' => $conversationId]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('MARKAZ AI message persistence failed: ' . $error->getMessage());
    markazAiRespond(['success' => false, 'message' => 'Your answer could not be saved securely. Please try again.'], 500);
}

markazAiRespond([
    'success' => true,
    'conversation_id' => $conversationId,
    'answer' => $answer
]);
