<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/markaz-ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
markazAiRequireSameOrigin();
try {
    markazAiResolveScope(db(), (int) $_SESSION['admin_id'], currentAdminRole());
} catch (Throwable $error) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
    exit;
}
http_response_code(503);
echo json_encode([
    'success' => false,
    'code' => 'VOICE_INPUT_UNAVAILABLE_NO_COST_MODE',
    'message' => 'Voice transcription is disabled in the no-cost MARKAZ assistant. Please type your message instead.'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
