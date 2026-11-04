<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSeniorAdmin();
} else {
    requireResultStaff();
}

$pdo = db();
$key = 'result_print_completion_threshold';
$allowed = [50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $saved = $stmt->fetchColumn();
    $threshold = $saved !== false && in_array((int) $saved, $allowed, true)
        ? (int) $saved : 95;
    echo json_encode(['success' => true, 'threshold' => $threshold, 'allowed' => $allowed]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$threshold = filter_var($input['threshold'] ?? null, FILTER_VALIDATE_INT);
if ($threshold === false || !in_array($threshold, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a completion threshold from 50% to 100% in 5% steps.']);
    exit;
}

$stmt = $pdo->prepare(
    "INSERT INTO settings (setting_key, setting_value, setting_type)
     VALUES (?, ?, 'number')
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)"
);
$stmt->execute([$key, (string) $threshold]);

echo json_encode([
    'success' => true,
    'threshold' => $threshold,
    'message' => 'Print eligibility threshold saved. Eligible results will use this threshold.'
]);
