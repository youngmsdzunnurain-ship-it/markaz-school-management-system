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

$id = filter_input(INPUT_GET, 'conversation_id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid conversation is required.']);
    exit;
}
$owner = db()->prepare(
    'SELECT id FROM markaz_ai_student_conversations
     WHERE id = ? AND student_id = ? AND session_key_hash = ? LIMIT 1'
);
$owner->execute([$id, $studentId, $sessionHash]);
if (!$owner->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'This conversation is not available in your Student Portal session.']);
    exit;
}
$stmt = db()->prepare(
    'SELECT id, role, content, created_at FROM markaz_ai_student_messages
     WHERE conversation_id = ? AND student_id = ? AND session_key_hash = ? ORDER BY id ASC LIMIT 80'
);
$stmt->execute([$id, $studentId, $sessionHash]);
echo json_encode(['success' => true, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
