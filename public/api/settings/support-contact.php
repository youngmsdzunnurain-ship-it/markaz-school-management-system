<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/config/app.php';
require_once __DIR__ . '/../../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS markaz_app_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $stmt = $pdo->prepare(
            "SELECT setting_value FROM markaz_app_settings
             WHERE setting_key = 'support_whatsapp_number' LIMIT 1"
        );
        $stmt->execute();
        $number = $stmt->fetchColumn();
        if (!$number) {
            $number = '07048657955';
        }
        echo json_encode(['success' => true, 'whatsapp_number' => $number]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    requireSeniorAdmin();

    $raw = trim((string) ($_POST['whatsapp_number'] ?? ''));
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if (str_starts_with($digits, '0')) {
        $digits = '234' . substr($digits, 1);
    } elseif (str_starts_with($digits, '2340')) {
        $digits = '234' . substr($digits, 4);
    }

    if (strlen($digits) < 10 || strlen($digits) > 15) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Enter a valid WhatsApp number, including its country code if needed.'
        ]);
        exit;
    }

    $display = $digits;
    $stmt = $pdo->prepare(
        "INSERT INTO markaz_app_settings (setting_key, setting_value)
         VALUES ('support_whatsapp_number', ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $stmt->execute([$display]);

    echo json_encode(['success' => true, 'whatsapp_number' => $display,
        'message' => 'Support WhatsApp number saved.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load or save the support number. Check that MariaDB is running.'
    ]);
}
