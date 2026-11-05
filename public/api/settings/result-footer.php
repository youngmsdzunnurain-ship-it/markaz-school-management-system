<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/config/app.php';
require_once __DIR__ . '/../../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$keys = [
    'class_teacher_signature' => 'result_footer_class_teacher_signature',
    'director_signature' => 'result_footer_director_signature',
    'class_teacher_image' => 'result_footer_class_teacher_image',
    'director_image' => 'result_footer_director_image',
    'result_date' => 'result_footer_signature_date'
];

function resultFooterJsonError(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function resultFooterStoreSignature(?string $dataUrl, string $role): ?string
{
    if ($dataUrl === null || $dataUrl === '') {
        return null;
    }

    if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
        resultFooterJsonError(422, 'Signature must be a transparent PNG image.');
    }

    $binary = base64_decode($matches[1], true);
    if ($binary === false || strlen($binary) > 1572864) {
        resultFooterJsonError(422, 'Signature image is invalid or exceeds 1.5MB.');
    }

    $image = @getimagesizefromstring($binary);
    if (
        $image === false ||
        ($image['mime'] ?? '') !== 'image/png' ||
        (int) $image[0] < 2 ||
        (int) $image[1] < 2 ||
        (int) $image[0] > 1800 ||
        (int) $image[1] > 700
    ) {
        resultFooterJsonError(422, 'Signature image dimensions or format are not supported.');
    }

    $directory = dirname(__DIR__, 2) . '/uploads/signatures';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create signature storage directory.');
    }

    $filename = $role . '-' . bin2hex(random_bytes(16)) . '.png';
    $destination = $directory . '/' . $filename;

    if (file_put_contents($destination, $binary, LOCK_EX) === false) {
        throw new RuntimeException('Unable to save signature image.');
    }

    return 'uploads/signatures/' . $filename;
}

function resultFooterDeleteStoredSignature(?string $relativePath): void
{
    if (!$relativePath || !preg_match('~^uploads/signatures/(class_teacher|director)-[a-f0-9]{32}\.png$~', $relativePath)) {
        return;
    }

    $file = dirname(__DIR__, 2) . '/' . $relativePath;
    if (is_file($file)) {
        @unlink($file);
    }
}

try {
    if ($method === 'GET') {
        // Result viewers need read access; only the Senior Admin can change these values.
        requireLogin();
    } elseif ($method === 'POST') {
        requireSeniorAdmin();
    } else {
        resultFooterJsonError(405, 'Method not allowed.');
    }

    $pdo = db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS markaz_app_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL
        )
    ");

    if ($method === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT setting_key, setting_value
             FROM markaz_app_settings
             WHERE setting_key IN (?, ?, ?, ?, ?)'
        );
        $stmt->execute(array_values($keys));
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        echo json_encode([
            'success' => true,
            'settings' => [
                'class_teacher_signature' => $rows[$keys['class_teacher_signature']] ?? '',
                'director_signature' => $rows[$keys['director_signature']] ?? '',
                'class_teacher_signature_image' => $rows[$keys['class_teacher_image']] ?? '',
                'director_signature_image' => $rows[$keys['director_image']] ?? '',
                'result_signature_date' => $rows[$keys['result_date']] ?? ''
            ]
        ]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        resultFooterJsonError(400, 'Invalid JSON request.');
    }

    $date = trim((string) ($input['result_signature_date'] ?? ''));
    if ($date !== '') {
        $parsedDate = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            resultFooterJsonError(422, 'Please enter a valid result date.');
        }
    }

    $currentStmt = $pdo->prepare(
        'SELECT setting_key, setting_value
         FROM markaz_app_settings
         WHERE setting_key IN (?, ?)'
    );
    $currentStmt->execute([$keys['class_teacher_image'], $keys['director_image']]);
    $currentRows = $currentStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $newFiles = [];
    $oldFiles = [
        $currentRows[$keys['class_teacher_image']] ?? null,
        $currentRows[$keys['director_image']] ?? null
    ];

    try {
        $teacherPath = array_key_exists('class_teacher_signature_image', $input)
            ? resultFooterStoreSignature((string) $input['class_teacher_signature_image'], 'class_teacher')
            : ($oldFiles[0] ?? null);
        if ($teacherPath && $teacherPath !== $oldFiles[0]) {
            $newFiles[] = $teacherPath;
        }

        $directorPath = array_key_exists('director_signature_image', $input)
            ? resultFooterStoreSignature((string) $input['director_signature_image'], 'director')
            : ($oldFiles[1] ?? null);
        if ($directorPath && $directorPath !== $oldFiles[1]) {
            $newFiles[] = $directorPath;
        }

        $save = $pdo->prepare(
            'INSERT INTO markaz_app_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        $pdo->beginTransaction();
        $save->execute([$keys['class_teacher_image'], $teacherPath ?? '']);
        $save->execute([$keys['director_image'], $directorPath ?? '']);
        if (array_key_exists('class_teacher_signature_image', $input)) {
            $save->execute([$keys['class_teacher_signature'], '']);
        }
        if (array_key_exists('director_signature_image', $input)) {
            $save->execute([$keys['director_signature'], '']);
        }
        $save->execute([$keys['result_date'], $date]);
        $pdo->commit();

        if ($teacherPath !== $oldFiles[0]) {
            resultFooterDeleteStoredSignature($oldFiles[0]);
        }
        if ($directorPath !== $oldFiles[1]) {
            resultFooterDeleteStoredSignature($oldFiles[1]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Signatures and result date saved successfully.',
            'settings' => [
                'class_teacher_signature_image' => $teacherPath ?? '',
                'director_signature_image' => $directorPath ?? '',
                'result_signature_date' => $date
            ]
        ]);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($newFiles as $newFile) {
            resultFooterDeleteStoredSignature($newFile);
        }
        throw $error;
    }
} catch (Throwable $error) {
    error_log('Result footer settings API: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to process result footer settings.'
    ]);
}
