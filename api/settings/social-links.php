<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireSeniorAdmin();

$pdo = db();
$platforms = ['facebook', 'instagram', 'x', 'youtube', 'tiktok'];
$keys = array_map(static fn(string $platform): string => 'social_link_' . $platform, $platforms);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    $saved = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $saved[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
    }
    $links = [];
    foreach ($platforms as $platform) {
        $links[$platform] = $saved['social_link_' . $platform] ?? '';
    }
    echo json_encode(['success' => true, 'links' => $links]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = $_POST;
if (!$input && str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    $decoded = json_decode(file_get_contents('php://input'), true);
    if (is_array($decoded)) $input = $decoded;
}

$links = [];
foreach ($platforms as $platform) {
    $url = trim((string) ($input[$platform] ?? ''));
    if ($url !== '') {
        $parts = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Enter a valid http:// or https:// URL for ' . ucfirst($platform) . '.']);
            exit;
        }
    }
    $links['social_link_' . $platform] = $url;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "INSERT INTO settings (setting_key, setting_value, setting_type)
         VALUES (?, ?, 'text')
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)"
    );
    foreach ($links as $key => $url) $stmt->execute([$key, $url]);
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Website and social links saved.', 'links' => array_combine($platforms, array_values($links))]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Social links settings error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save website and social links.']);
}
