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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
markazAiRequireSameOrigin();
http_response_code(503);
echo json_encode([
    'success' => false,
    'code' => 'VOICE_INPUT_UNAVAILABLE_NO_COST_MODE',
    'message' => 'Voice transcription is disabled in the no-cost Student Learning Assistant. Please type your question instead.'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
