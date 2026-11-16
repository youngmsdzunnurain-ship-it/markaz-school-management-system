<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/helpers/markaz-ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
$resolved = markazAiResolveStudentPortalContext(db());
if (!$resolved) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Open your published result first to use voice input.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
markazAiRequireSameOrigin();
if (empty($_FILES['voice_note']) || !is_uploaded_file($_FILES['voice_note']['tmp_name'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Record or choose a voice note first.']);
    exit;
}
$file = $_FILES['voice_note'];
if ((int) $file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Voice input must be no larger than 5 MB.']);
    exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$allowed = [
    'audio/webm' => 'webm', 'video/webm' => 'webm', 'audio/ogg' => 'ogg', 'application/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'video/mp4' => 'm4a',
    'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav'
];
if (!isset($allowed[$mime])) {
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => 'Use a supported browser audio recording (WebM, OGG, MP4, MP3 or WAV).']);
    exit;
}
$providerMime = $mime === 'video/webm' ? 'audio/webm' : ($mime === 'video/mp4' ? 'audio/mp4' : ($mime === 'application/ogg' ? 'audio/ogg' : $mime));
$voiceRate = db()->prepare(
    "SELECT COUNT(*) FROM markaz_ai_student_audit_events
     WHERE student_id = ? AND session_key_hash = ? AND event_type = 'voice_transcription_started'
       AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$voiceRate->execute([$resolved['student_id'], $resolved['session_hash']]);
if ((int) $voiceRate->fetchColumn() >= 15) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'You have reached the hourly voice-input limit.']);
    exit;
}
$key = trim((string) ($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: ''));
$model = trim((string) ($_ENV['MARKAZ_AI_TRANSCRIPTION_MODEL'] ?? getenv('MARKAZ_AI_TRANSCRIPTION_MODEL') ?: ''));
if ($key === '' || $model === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Voice input is not connected yet. Please contact the school.']);
    exit;
}
markazAiStudentRecordEvent(db(), $resolved['student_id'], $resolved['session_hash'], 'voice_transcription_started', ['mime_type' => $mime, 'file_size' => (int) $file['size']]);
$boundary = '----MARKAZAI' . bin2hex(random_bytes(16));
$bytes = file_get_contents($file['tmp_name']);
$filename = 'markaz-student-voice.' . $allowed[$mime];
$body = "--{$boundary}\r\n"
    . "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n"
    . "Content-Type: {$providerMime}\r\n\r\n"
    . $bytes . "\r\n"
    . "--{$boundary}\r\n"
    . "Content-Disposition: form-data; name=\"model\"\r\n\r\n"
    . $model . "\r\n"
    . "--{$boundary}--\r\n";
$baseUrl = rtrim((string) ($_ENV['MARKAZ_AI_BASE_URL'] ?? getenv('MARKAZ_AI_BASE_URL') ?: 'https://api.openai.com/v1'), '/');
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: multipart/form-data; boundary={$boundary}\r\nAuthorization: Bearer {$key}\r\n",
        'content' => $body,
        'timeout' => 45,
        'ignore_errors' => true
    ],
    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
]);
$responseBody = @file_get_contents($baseUrl . '/audio/transcriptions', false, $context);
$statusLine = $http_response_header[0] ?? '';
preg_match('/\\s(\\d{3})\\s/', $statusLine, $match);
$status = (int) ($match[1] ?? 0);
if ($responseBody === false || $status < 200 || $status >= 300) {
    markazAiStudentRecordEvent(db(), $resolved['student_id'], $resolved['session_hash'], 'voice_transcription_failed', ['http_status' => $status]);
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Voice transcription is temporarily unavailable.']);
    exit;
}
$data = json_decode($responseBody, true);
$transcript = trim((string) ($data['text'] ?? ''));
if ($transcript === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No speech was detected. Try again.']);
    exit;
}
markazAiStudentRecordEvent(db(), $resolved['student_id'], $resolved['session_hash'], 'voice_transcription_completed', ['mime_type' => $mime, 'file_size' => (int) $file['size']]);
echo json_encode(['success' => true, 'transcript' => $transcript], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
