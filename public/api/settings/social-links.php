<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/config/app.php';
require_once __DIR__ . '/../../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

$platforms = ['facebook', 'instagram', 'x', 'youtube', 'tiktok'];

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
            'SELECT setting_value FROM markaz_app_settings
             WHERE setting_key = ? LIMIT 1'
        );

        $links = [];
        foreach ($platforms as $platform) {
            $stmt->execute(['website_social_' . $platform]);
            $value = $stmt->fetchColumn();
            $links[$platform] = $value !== false ? (string) $value : '';
        }

        echo json_encode(['success' => true, 'links' => $links]);
        exit;
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
        exit;
    }

    requireSeniorAdmin();

    $stmt = $pdo->prepare(
        'INSERT INTO markaz_app_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    $saved = [];

    foreach ($platforms as $platform) {
        $url = trim((string) ($_POST[$platform] ?? ''));

        if ($url !== '') {
            if (strlen($url) > 255 ||
                filter_var($url, FILTER_VALIDATE_URL) === false ||
                strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Enter a valid HTTPS URL for ' . ucfirst($platform) .
                        ', or leave that field blank.'
                ]);
                exit;
            }
        }

        $stmt->execute(['website_social_' . $platform, $url]);
        $saved[$platform] = $url;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Website social links saved.',
        'links' => $saved
    ]);
} catch (Throwable $e) {
    error_log('MARKAZ social links: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load or save website social links.'
    ]);
}
