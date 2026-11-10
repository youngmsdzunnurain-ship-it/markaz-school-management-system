<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/student-portal-access.php';

$studentPortalScope = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? studentPortalResultScope(false)
    : null;
if ($studentPortalScope === null) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
        && in_array(currentAdminRole(), ['teacher', 'management'], true)) {
        requireOnlineClassAccess();
    } else {
        requireAdminOrExamOfficer();
    }
}

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

$allowedSettings = [
    'class_teacher_signature',
    'director_signature',
    'result_signature_date'
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
        $settings[$row['setting_key']] = $row['setting_value'] ?? '';
    }

    foreach ($allowedSettings as $key) {
        if (!array_key_exists($key, $settings)) {
            $settings[$key] = '';
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

$teacherSignature = trim((string)($input['class_teacher_signature'] ?? ''));
$directorSignature = trim((string)($input['director_signature'] ?? ''));
$signatureDate = trim((string)($input['result_signature_date'] ?? ''));

if ($signatureDate !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $signatureDate);

    if (!$date || $date->format('Y-m-d') !== $signatureDate) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid signature date.'
        ]);
        exit;
    }
}

$updates = [
    'class_teacher_signature' => $teacherSignature,
    'director_signature' => $directorSignature,
    'result_signature_date' => $signatureDate
];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value, setting_type)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
             setting_value = VALUES(setting_value),
             setting_type = VALUES(setting_type)"
    );

    foreach ($updates as $key => $value) {
        $type = $key === 'result_signature_date' ? 'date' : 'text';
        $stmt->execute([$key, $value, $type]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Result footer settings saved successfully.'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save result footer settings.'
    ]);
}
