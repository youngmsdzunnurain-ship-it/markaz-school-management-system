<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = trim((string) ($input['token'] ?? ''));
$password = (string) ($input['password'] ?? '');
$confirmation = (string) ($input['password_confirmation'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'This password reset link is invalid or has expired.']);
    exit;
}
if (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Use at least 12 characters with uppercase, lowercase, a number and a symbol.']);
    exit;
}
if (!hash_equals($password, $confirmation)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'The password confirmation does not match.']);
    exit;
}

$pdo = null;
try {
    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT id, admin_id FROM admin_password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE");
    $stmt->execute([hash('sha256', $token)]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reset) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This password reset link is invalid or has expired.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ? AND role = 'admin' AND is_active = 1")->execute([$hash, (int) $reset['admin_id']]);
    if ($pdo->rowCount() !== 1) {
        throw new RuntimeException('Administrator account could not be updated.');
    }
    $pdo->prepare("UPDATE admin_password_resets SET used_at = NOW() WHERE admin_id = ? AND used_at IS NULL")->execute([(int) $reset['admin_id']]);
    $pdo->prepare("DELETE FROM login_lockouts WHERE role = 'admin' AND identifier = (SELECT LOWER(email) FROM admins WHERE id = ?)")->execute([(int) $reset['admin_id']]);
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Your administrator password has been reset. You can now sign in.']);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Admin password reset completion failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to reset the password. Please request a new reset link.']);
}
