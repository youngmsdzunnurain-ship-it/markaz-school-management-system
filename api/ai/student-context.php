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
echo json_encode([
    'success' => true,
    'role' => 'student',
    'student_name' => $resolved['context']['student_name'],
    'student_context' => $resolved['context']
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
