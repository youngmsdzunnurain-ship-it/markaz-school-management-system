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
$accountId = (int) $_SESSION['admin_id'];
$role = currentAdminRole();
try {
    $scope = markazAiResolveScope(db(), $accountId, $role);
} catch (Throwable $error) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
    exit;
}
if (!in_array($role, ['admin', 'exam_officer', 'teacher'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Result-sheet scanning is not enabled for this workspace.']);
    exit;
}
if (empty($_FILES['result_sheet']) || !is_uploaded_file($_FILES['result_sheet']['tmp_name'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a result-sheet image to scan.']);
    exit;
}
$file = $_FILES['result_sheet'];
if ((int) $file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'The image must be no larger than 5 MB.']);
    exit;
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($allowed[$mime])) {
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => 'Use a JPEG, PNG or WebP image.']);
    exit;
}
$classId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$classType = (string) ($_POST['class_type'] ?? '');
$subjectId = filter_var($_POST['subject_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
if ($role === 'teacher') {
    if ($classId < 1 || $subjectId < 1 || !in_array($classType, ['conventional', 'arabic'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Select your assigned class and subject before scanning.']);
        exit;
    }
    requireTeacherAssignment($classId, $subjectId, $classType);
}
$scanRate = db()->prepare(
    "SELECT COUNT(*) FROM markaz_ai_audit_events
     WHERE account_id = ? AND event_type = 'result_scan_started'
       AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$scanRate->execute([$accountId]);
if ((int) $scanRate->fetchColumn() >= 10) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'You have reached the hourly result-scan limit. Please try again later.']);
    exit;
}
$key = trim((string) ($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: ''));
$model = trim((string) ($_ENV['MARKAZ_AI_MODEL'] ?? getenv('MARKAZ_AI_MODEL') ?: ''));
if ($key === '' || $model === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'MARKAZ AI has not been configured by the Admin yet.']);
    exit;
}
markazAiRecordEvent(db(), $accountId, $role, 'result_scan_started', ['mime_type' => $mime, 'file_size' => (int) $file['size']]);
$bytes = file_get_contents($file['tmp_name']);
$dataUrl = 'data:' . $mime . ';base64,' . base64_encode($bytes);
$prompt = "Read this result sheet carefully. " . ($role === 'teacher' ? "Only extract the selected assigned class ID {$classId}, class type {$classType}, and subject ID {$subjectId}; do not transcribe any other class or subject. " : "") . "Return a draft transcription in a clear table with student name/admission number if legible, subject, CA1, CA2, exam and total where visible. Mark uncertain or unreadable cells as UNCLEAR; never guess a score. Identify possible class/session/term only when visibly printed. Do not claim this data is saved to MARKAZ. This is a review-only draft; a staff member must verify every value and enter it through the normal authorised MARKAZ result workflow.";
$body = json_encode([
    'model' => $model,
    'instructions' => markazAiBuildInstructions($scope, 'result-sheet-scanner', markazAiLoadWorkspaceContext(db(), $accountId, $scope)),
    'input' => [[
        'role' => 'user',
        'content' => [
            ['type' => 'input_text', 'text' => $prompt],
            ['type' => 'input_image', 'image_url' => $dataUrl, 'detail' => 'high']
        ]
    ]],
    'store' => false,
    'max_output_tokens' => 1800
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r
Authorization: Bearer {$key}\r
",
        'content' => $body,
        'timeout' => 50,
        'ignore_errors' => true
    ],
    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
]);
$result = @file_get_contents(rtrim((string) ($_ENV['MARKAZ_AI_BASE_URL'] ?? getenv('MARKAZ_AI_BASE_URL') ?: 'https://api.openai.com/v1'), '/') . '/responses', false, $context);
$statusLine = $http_response_header[0] ?? '';
preg_match('/\\s(\\d{3})\\s/', $statusLine, $match);
$status = (int) ($match[1] ?? 0);
if ($result === false || $status < 200 || $status >= 300) {
    markazAiRecordEvent(db(), $accountId, $role, 'result_scan_failed', ['http_status' => $status]);
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'The image could not be scanned right now. Please try again.']);
    exit;
}
$decoded = json_decode($result, true);
$text = trim((string) ($decoded['output_text'] ?? ''));
if ($text === '' && is_array($decoded['output'] ?? null)) {
    foreach ($decoded['output'] as $item) foreach (($item['content'] ?? []) as $part) {
        if (($part['type'] ?? '') === 'output_text') $text .= (string) ($part['text'] ?? '');
    }
    $text = trim($text);
}
if ($text === '') {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'No readable result data was detected. Try a clearer, well-lit image.']);
    exit;
}
markazAiRecordEvent(db(), $accountId, $role, 'result_scan_completed', ['mime_type' => $mime, 'file_size' => (int) $file['size']]);
echo json_encode([
    'success' => true,
    'draft' => $text,
    'saved_to_results' => false,
    'message' => 'Scan complete. Review every value before entering it through the normal MARKAZ result page.'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
