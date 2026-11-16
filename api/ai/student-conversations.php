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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->prepare(
        'SELECT id, title, created_at, updated_at FROM markaz_ai_student_conversations
         WHERE student_id = ? AND session_key_hash = ? ORDER BY updated_at DESC LIMIT 20'
    );
    $stmt->execute([$studentId, $sessionHash]);
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
$title = trim((string) ($input['title'] ?? 'My learning conversation'));
$title = mb_substr($title !== '' ? $title : 'My learning conversation', 0, 160);
$stmt = db()->prepare(
    'INSERT INTO markaz_ai_student_conversations (student_id, session_key_hash, title) VALUES (?, ?, ?)'
);
$stmt->execute([$studentId, $sessionHash, $title]);
$id = (int) db()->lastInsertId();
markazAiStudentRecordEvent(db(), $studentId, $sessionHash, 'conversation_created', ['conversation_id' => $id]);
echo json_encode(['success' => true, 'conversation' => ['id' => $id, 'title' => $title]]);
