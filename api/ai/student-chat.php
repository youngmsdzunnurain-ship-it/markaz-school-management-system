<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/helpers/markaz-ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
$resolved = markazAiResolveStudentPortalContext(db());
if (!$resolved) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Open your published result first to use the Student Learning Assistant.']);
    exit;
}
$studentId = (int) $resolved['student_id'];
$sessionHash = (string) $resolved['session_hash'];

function markazAiStudentRespond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    markazAiStudentRespond(['success' => false, 'message' => 'Method not allowed.'], 405);
}
markazAiRequireSameOrigin();
$input = json_decode(file_get_contents('php://input'), true);
$message = trim((string) ($input['message'] ?? ''));
if ($message === '' || mb_strlen($message) > 2000) {
    markazAiStudentRespond(['success' => false, 'message' => 'Enter a message of up to 2,000 characters.'], 422);
}
$rate = db()->prepare(
    "SELECT COUNT(*) FROM markaz_ai_student_audit_events
     WHERE student_id = ? AND session_key_hash = ? AND event_type = 'message_started'
       AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$rate->execute([$studentId, $sessionHash]);
if ((int) $rate->fetchColumn() >= 15) {
    markazAiStudentRespond(['success' => false, 'message' => 'You have reached the hourly assistant limit. Please try again later.'], 429);
}
$id = filter_var($input['conversation_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
if ($id > 0) {
    $owner = db()->prepare(
        'SELECT id FROM markaz_ai_student_conversations
         WHERE id = ? AND student_id = ? AND session_key_hash = ? LIMIT 1'
    );
    $owner->execute([$id, $studentId, $sessionHash]);
    if (!$owner->fetchColumn()) markazAiStudentRespond(['success' => false, 'message' => 'This conversation is not available.'], 404);
} else {
    $create = db()->prepare(
        'INSERT INTO markaz_ai_student_conversations (student_id, session_key_hash, title) VALUES (?, ?, ?)'
    );
    $create->execute([$studentId, $sessionHash, mb_substr($message, 0, 70)]);
    $id = (int) db()->lastInsertId();
}
markazAiStudentRecordEvent(db(), $studentId, $sessionHash, 'message_started');
$answer = markazAiBuildFreeAnswer($message, ['role' => 'student'], $resolved['context'], true);
db()->beginTransaction();
try {
    $save = db()->prepare(
        'INSERT INTO markaz_ai_student_messages (conversation_id, student_id, session_key_hash, role, content)
         VALUES (?, ?, ?, ?, ?)'
    );
    $save->execute([$id, $studentId, $sessionHash, 'user', $message]);
    $save->execute([$id, $studentId, $sessionHash, 'assistant', $answer]);
    db()->prepare(
        'UPDATE markaz_ai_student_conversations SET updated_at = NOW()
         WHERE id = ? AND student_id = ? AND session_key_hash = ?'
    )->execute([$id, $studentId, $sessionHash]);
    markazAiStudentRecordEvent(db(), $studentId, $sessionHash, 'message_completed', ['conversation_id' => $id]);
    db()->commit();
} catch (Throwable $error) {
    if (db()->inTransaction()) db()->rollBack();
    error_log('Student MARKAZ AI persistence failed: ' . $error->getMessage());
    markazAiStudentRespond(['success' => false, 'message' => 'Your answer could not be saved. Please try again.'], 500);
}
markazAiStudentRespond(['success' => true, 'conversation_id' => $id, 'answer' => $answer]);
