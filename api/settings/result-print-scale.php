<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

set_exception_handler(static function (Throwable $error): void {
    error_log('Result print scale settings error: ' . $error->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save or load print scale. Check the server error log.'
    ]);
});
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSeniorAdmin();
} else {
    requireResultStaff();
}

$pdo = db();
$key = 'result_print_scale';
$allowed = range(50, 100, 5);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    $scale = $value !== false && in_array((int) $value, $allowed, true) ? (int) $value : 100;
    echo json_encode(['success' => true, 'scale' => $scale, 'allowed' => $allowed]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$scale = filter_var($input['scale'] ?? null, FILTER_VALIDATE_INT);
if ($scale === false || !in_array($scale, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a print scale from 10% to 100%.']);
    exit;
}

$stmt = $pdo->prepare(
    "INSERT INTO settings (setting_key, setting_value, setting_type)
     VALUES (?, ?, 'number')
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)"
);
$stmt->execute([$key, (string) $scale]);
echo json_encode(['success' => true, 'scale' => $scale, 'message' => 'Print scale saved.']);
