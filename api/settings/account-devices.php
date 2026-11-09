<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
requireSeniorAdmin();

$pdo = db();
$actorId = (int) ($_SESSION['admin_id'] ?? 0);

$pdo->exec("CREATE TABLE IF NOT EXISTS login_devices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    device_token_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    blocked_at DATETIME NULL,
    blocked_by BIGINT UNSIGNED NULL,
    block_reason VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_login_device_account_token (account_id, device_token_hash),
    KEY idx_login_device_account_status (account_id, is_blocked),
    CONSTRAINT fk_login_devices_account FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE CASCADE,
    CONSTRAINT fk_login_devices_blocked_by FOREIGN KEY (blocked_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS login_device_blocks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    device_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    action ENUM('block','unblock') NOT NULL,
    reason VARCHAR(500) NULL,
    performed_by BIGINT UNSIGNED NOT NULL,
    performed_by_name VARCHAR(190) NOT NULL,
    performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_device_block_history (device_id, performed_at),
    CONSTRAINT fk_login_device_blocks_device FOREIGN KEY (device_id) REFERENCES login_devices(id) ON DELETE CASCADE,
    CONSTRAINT fk_login_device_blocks_account FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE CASCADE,
    CONSTRAINT fk_login_device_blocks_actor FOREIGN KEY (performed_by) REFERENCES admins(id) ON DELETE RESTRICT
) ENGINE=InnoDB");

function deviceAdminJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        "SELECT d.id, d.account_id, a.name AS account_name, a.role,
                d.user_agent, d.last_seen_at, d.created_at, d.is_blocked,
                d.blocked_at, d.block_reason
         FROM login_devices d
         INNER JOIN admins a ON a.id = d.account_id
         WHERE a.role <> 'admin'
         ORDER BY d.is_blocked DESC, d.last_seen_at DESC"
    );
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($devices as &$device) {
        $device['id'] = (int) $device['id'];
        $device['account_id'] = (int) $device['account_id'];
        $device['is_blocked'] = (bool) $device['is_blocked'];
    }
    unset($device);
    deviceAdminJson(['success' => true, 'devices' => $devices]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    deviceAdminJson(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    deviceAdminJson(['success' => false, 'message' => 'Invalid device request.'], 400);
}
$deviceId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
$action = (string) ($input['action'] ?? '');
$reason = trim((string) ($input['reason'] ?? ''));
if (!$deviceId || $deviceId < 1 || !in_array($action, ['block', 'unblock'], true)) {
    deviceAdminJson(['success' => false, 'message' => 'Choose a valid device action.'], 400);
}
if ($action === 'block' && $reason === '') {
    deviceAdminJson(['success' => false, 'message' => 'Enter a reason for blocking this device.'], 400);
}
if (mb_strlen($reason) > 500) {
    deviceAdminJson(['success' => false, 'message' => 'The reason must not exceed 500 characters.'], 400);
}

try {
    $pdo->beginTransaction();
    $deviceStmt = $pdo->prepare(
        "SELECT d.id, d.account_id, d.is_blocked, d.user_agent, a.name, a.role
         FROM login_devices d
         INNER JOIN admins a ON a.id = d.account_id
         WHERE d.id = ? AND a.role <> 'admin'
         LIMIT 1 FOR UPDATE"
    );
    $deviceStmt->execute([$deviceId]);
    $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);
    if (!$device) {
        $pdo->rollBack();
        deviceAdminJson(['success' => false, 'message' => 'Device not found.'], 404);
    }
    if ($action === 'block' && (bool) $device['is_blocked']) {
        $pdo->rollBack();
        deviceAdminJson(['success' => false, 'message' => 'This device is already blocked.'], 409);
    }
    if ($action === 'unblock' && !(bool) $device['is_blocked']) {
        $pdo->rollBack();
        deviceAdminJson(['success' => false, 'message' => 'This device is not blocked.'], 409);
    }

    if ($action === 'block') {
        $update = $pdo->prepare(
            'UPDATE login_devices SET is_blocked = 1, blocked_at = NOW(), blocked_by = ?, block_reason = ? WHERE id = ?'
        );
        $update->execute([$actorId, $reason, $deviceId]);
    } else {
        $update = $pdo->prepare(
            'UPDATE login_devices SET is_blocked = 0, blocked_at = NULL, blocked_by = NULL, block_reason = NULL WHERE id = ?'
        );
        $update->execute([$deviceId]);
    }

    $actorStmt = $pdo->prepare("SELECT name FROM admins WHERE id = ? AND role = 'admin' LIMIT 1");
    $actorStmt->execute([$actorId]);
    $actorName = $actorStmt->fetchColumn();
    if ($actorName === false) {
        $pdo->rollBack();
        deviceAdminJson(['success' => false, 'message' => 'Senior Admin verification failed.'], 403);
    }
    $audit = $pdo->prepare(
        'INSERT INTO login_device_blocks (device_id, account_id, action, reason, performed_by, performed_by_name)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $audit->execute([$deviceId, (int) $device['account_id'], $action, $reason !== '' ? $reason : null, $actorId, (string) $actorName]);
    $pdo->commit();

    deviceAdminJson([
        'success' => true,
        'message' => $action === 'block'
            ? 'Device blocked. This account remains usable from its other devices.'
            : 'Device unblocked. The account can sign in from this device again.'
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Account device control failed: ' . $error->getMessage());
    deviceAdminJson(['success' => false, 'message' => 'Unable to update this device.'], 500);
}
