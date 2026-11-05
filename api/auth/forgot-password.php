<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

function forgotResponse(): void
{
    echo json_encode([
        'success' => true,
        'message' => 'If an administrator account matches that email address, a password reset link will be sent shortly.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim((string) ($input['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid email address.']);
    exit;
}

try {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_password_resets (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        admin_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_admin_password_reset_token (token_hash),
        KEY idx_admin_password_reset_admin (admin_id, created_at),
        KEY idx_admin_password_reset_expiry (expires_at),
        CONSTRAINT fk_admin_password_reset_admin FOREIGN KEY (admin_id)
            REFERENCES admins(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $stmt = $pdo->prepare("SELECT id, name, email FROM admins WHERE email = ? AND role = 'admin' AND is_active = 1 AND is_frozen = 0 LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        forgotResponse();
    }

    $rate = $pdo->prepare("SELECT COUNT(*) FROM admin_password_resets WHERE admin_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $rate->execute([(int) $admin['id']]);
    if ((int) $rate->fetchColumn() >= 5) {
        forgotResponse();
    }$token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $insert = $pdo->prepare("INSERT INTO admin_password_resets (admin_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
    $insert->execute([(int) $admin['id'], $tokenHash]);

    $baseUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
    if ($baseUrl === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i', $host)) {
            throw new RuntimeException('APP_URL must be configured for password reset email.');
        }
        $baseUrl = ($https ? 'https://' : 'http://') . $host;
    }

    $resetUrl = $baseUrl . '/reset-password.html?token=' . rawurlencode($token);
    $from = (string) ($_ENV['MAIL_FROM'] ?? 'no-reply@' . (parse_url($baseUrl, PHP_URL_HOST) ?: 'localhost'));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('MAIL_FROM must be a valid email address.');
    }
    $subject = 'MARKAZ administrator password reset';
    $safeName = htmlspecialchars((string) $admin['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $body = "<!doctype html><html><body style=\"font-family:Arial,sans-serif;color:#183326;line-height:1.6\">"
        . "<h2>MARKAZ password reset</h2><p>Hello {$safeName},</p>"
        . "<p>A password reset was requested for your administrator account.</p>"
        . "<p><a href=\"{$safeUrl}\" style=\"display:inline-block;padding:12px 20px;background:#176b45;color:#fff;text-decoration:none;border-radius:8px\">Reset Password</a></p>"
        . "<p>This link expires in 30 minutes and can only be used once. If you did not request this, you can ignore this email.</p></body></html>";
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: MARKAZ <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . PHP_VERSION
    ];

    if (!mail((string) $admin['email'], $subject, $body, implode("\r\n", $headers))) {
        $pdo->prepare("DELETE FROM admin_password_resets WHERE token_hash = ?")->execute([$tokenHash]);
        throw new RuntimeException('The server mail service could not send the password reset email.');
    }

    forgotResponse();
} catch (Throwable $error) {
    error_log('Admin password reset request failed: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Password reset email is temporarily unavailable. Please contact MARKAZ support.']);
}
