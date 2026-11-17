<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
http_response_code(403);
echo json_encode([
    'success' => false,
    'code' => 'STUDENT_AI_DISABLED',
    'message' => 'The MARKAZ AI assistant is available to authorised staff accounts only.'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
