<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

requireSeniorAdmin();

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

$allowedSettings = [
    'student_result_access',
    'teacher_access',
    'exam_officer_access',
    'management_access',
    'exam_officer_print_results',
    'teacher_print_results',
    'student_print_results',
    'management_print_results',
    'admin_print_results',
    'login_lockout_enabled'
];

$defaultSettings = [
    'student_result_access' => true,
    'teacher_access' => true,
    'exam_officer_access' => true,
    'management_access' => true,
    'exam_officer_print_results' => false,
    'teacher_print_results' => false,
    'student_print_results' => false,
    'management_print_results' => false,
    'admin_print_results' => false,
    'login_lockout_enabled' => true
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $placeholders = implode(',', array_fill(0, count($allowedSettings), '?'));

    $stmt = $pdo->prepare(
        "SELECT setting_key, setting_value
         FROM settings
         WHERE setting_key IN ($placeholders)"
    );
    $stmt->execute($allowedSettings);

    $settings = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['setting_key']] = in_array(
            strtolower(trim((string) $row['setting_value'])),
            ['1', 'true', 'yes', 'on'],
            true
        );
    }

    foreach ($allowedSettings as $key) {
        if (!array_key_exists($key, $settings)) {
            $settings[$key] = $defaultSettings[$key];
        }
    }

    echo json_encode([
        'success' => true,
        'settings' => $settings
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

if (!is_array($input)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

$updates = [];

foreach ($allowedSettings as $key) {
    if (array_key_exists($key, $input)) {
        $value = filter_var(
            $input[$key],
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($value === null) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => "Invalid value for {$key}."
            ]);
            exit;
        }

        $updates[$key] = $value ? '1' : '0';
    }
}

if (!$updates) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'No valid settings supplied.'
    ]);
    exit;
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value, setting_type)
         VALUES (?, ?, 'boolean')
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value)"
    );

    foreach ($updates as $key => $value) {
        $stmt->execute([$key, $value]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Access settings updated successfully.'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to save access settings.'
    ]);
}
