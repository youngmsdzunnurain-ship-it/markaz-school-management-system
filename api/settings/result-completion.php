<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/config/app.php';

requireSeniorAdmin();
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    $key = 'result_print_completion_threshold';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'threshold' => $value !== false && is_numeric($value)
                ? max(0, min(100, (float) $value))
                : 95
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)
        || !array_key_exists('threshold', $input)
        || !is_numeric($input['threshold'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Enter a valid completion threshold from 0 to 100.'
        ]);
        exit;
    }

    $threshold = (float) $input['threshold'];

    if (!is_finite($threshold) || $threshold < 0 || $threshold > 100) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'The threshold must be between 0 and 100.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value, setting_type)
         VALUES (?, ?, 'number')
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            setting_type = 'number'"
    );
    $stmt->execute([$key, (string) $threshold]);

    echo json_encode([
        'success' => true,
        'threshold' => $threshold,
        'message' => 'Print eligibility threshold saved.'
    ]);
} catch (Throwable $e) {
    error_log('Result completion settings error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to process the completion threshold setting.'
    ]);
}
