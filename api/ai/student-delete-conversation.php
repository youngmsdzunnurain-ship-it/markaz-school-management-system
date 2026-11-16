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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
markazAiRequireSameOrigin();
$input = json_decode(file_get_contents('php://input'), true);
$id = filter_var($input['conversation_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid conversation is required.']);
    exit;
}
$stmt = db()->prepare(
    'DELETE FROM markaz_ai_student_conversations
     WHERE id = ? AND student_id = ? AND session_key_hash = ?'
);
$stmt->execute([$id, $studentId, $sessionHash]);
if ($stmt->rowCount() !== 1) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'This conversation is not available in your Student Portal session.']);
    exit;
}
echo json_encode(['success' => true]);
