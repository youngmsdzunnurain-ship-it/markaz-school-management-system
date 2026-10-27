<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/config/app.php';
require_once __DIR__ . '/../../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

requireSeniorAdmin();

try {
    $pdo = db();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS markaz_app_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL
        )
    ");

    $keys = [
        'class_teacher_signature' => 'result_footer_class_teacher_signature',
        'director_signature' => 'result_footer_director_signature',
        'result_signature_date' => 'result_footer_signature_date'
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT setting_key, setting_value
             FROM markaz_app_settings
             WHERE setting_key IN (?, ?, ?)'
        );

        $stmt->execute(array_values($keys));
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        echo json_encode([
            'success' => true,
            'settings' => [
                'class_teacher_signature' =>
                    $rows[$keys['class_teacher_signature']] ?? '',
                'director_signature' =>
                    $rows[$keys['director_signature']] ?? '',
                'result_signature_date' =>
                    $rows[$keys['result_signature_date']] ?? ''
            ]
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

    $input = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON request.'
        ]);
        exit;
    }

    $classTeacher = trim(
        (string) ($input['class_teacher_signature'] ?? '')
    );
    $director = trim(
        (string) ($input['director_signature'] ?? '')
    );
    $date = trim(
        (string) ($input['result_signature_date'] ?? '')
    );

    if ($date !== '') {
        $parsedDate = DateTime::createFromFormat('!Y-m-d', $date);

        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Please enter a valid result date.'
            ]);
            exit;
        }
    }

    $save = $pdo->prepare(
        'INSERT INTO markaz_app_settings
            (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value)'
    );

    $pdo->beginTransaction();

    try {
        $save->execute([
            $keys['class_teacher_signature'],
            $classTeacher
        ]);

        $save->execute([
            $keys['director_signature'],
            $director
        ]);

        $save->execute([
            $keys['result_signature_date'],
            $date
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Result footer settings saved successfully.',
        'settings' => [
            'class_teacher_signature' => $classTeacher,
            'director_signature' => $director,
            'result_signature_date' => $date
        ]
    ]);
} catch (Throwable $error) {
    error_log('Result footer settings API: ' . $error->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to process result footer settings.'
    ]);
}
