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
$key = trim((string) ($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: ''));
$model = trim((string) ($_ENV['MARKAZ_AI_MODEL'] ?? getenv('MARKAZ_AI_MODEL') ?: ''));
if ($key === '' || $model === '') {
    markazAiStudentRespond(['success' => false, 'message' => 'The Student Learning Assistant is not connected yet. Please contact the school.'], 503);
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
$history = db()->prepare(
    "SELECT role, content FROM markaz_ai_student_messages
     WHERE conversation_id = ? AND student_id = ? AND session_key_hash = ?
     ORDER BY id DESC LIMIT 8"
);
$history->execute([$id, $studentId, $sessionHash]);
$items = [];
foreach (array_reverse($history->fetchAll(PDO::FETCH_ASSOC)) as $item) {
    $items[] = ['role' => $item['role'], 'content' => $item['content']];
}
$items[] = ['role' => 'user', 'content' => $message];
markazAiStudentRecordEvent(db(), $studentId, $sessionHash, 'message_started');
$body = json_encode([
    'model' => $model,
    'instructions' => markazAiStudentBuildInstructions($resolved['context']),
    'input' => $items,
    'store' => false,
    'max_output_tokens' => 800
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$baseUrl = rtrim((string) ($_ENV['MARKAZ_AI_BASE_URL'] ?? getenv('MARKAZ_AI_BASE_URL') ?: 'https://api.openai.com/v1'), '/');
$providerContext = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$key}\r\n",
        'content' => $body,
        'timeout' => 35,
        'ignore_errors' => true
    ],
    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
]);
$responseBody = @file_get_contents($baseUrl . '/responses', false, $providerContext);
$statusLine = $http_response_header[0] ?? '';
preg_match('/\\s(\\d{3})\\s/', $statusLine, $match);
$status = (int) ($match[1] ?? 0);
if ($responseBody === false || $status < 200 || $status >= 300) {
    markazAiStudentRecordEvent(db(), $studentId, $sessionHash, 'provider_error', ['http_status' => $status]);
    markazAiStudentRespond(['success' => false, 'message' => 'The learning assistant is temporarily unavailable. Please try again.'], 502);
}
$provider = json_decode($responseBody, true);
$answer = trim((string) ($provider['output_text'] ?? ''));
if ($answer === '' && is_array($provider['output'] ?? null)) {
    foreach ($provider['output'] as $output) foreach (($output['content'] ?? []) as $part) {
        if (($part['type'] ?? '') === 'output_text') $answer .= (string) ($part['text'] ?? '');
    }
    $answer = trim($answer);
}
if ($answer === '') markazAiStudentRespond(['success' => false, 'message' => 'The assistant returned an empty answer. Please try again.'], 502);
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
