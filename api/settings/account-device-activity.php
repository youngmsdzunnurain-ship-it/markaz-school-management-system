<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/student-portal-access.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$pdo = db();

$studentScope = studentPortalResultScope(false);
$identityType = 'staff';
$identityId = 0;
$identityKey = '';
$displayName = '';
$role = '';

if ($studentScope !== null) {
    $identityType = 'student';
    $identityId = (int) ($studentScope['student_id'] ?? 0);
    if ($identityId < 1) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Student session expired.']);
        exit;
    }
    $identityKey = 'student:' . $identityId;
    $studentStmt = $pdo->prepare(
        'SELECT CONCAT_WS(" ", first_name, NULLIF(middle_name, ""), last_name) FROM students WHERE id = ? LIMIT 1'
    );
    $studentStmt->execute([$identityId]);
    $displayName = trim((string) $studentStmt->fetchColumn());
    $role = 'student';
} else {
    requireLogin();
    $identityId = (int) ($_SESSION['admin_id'] ?? 0);
    $identityKey = 'staff:' . $identityId;
    $displayName = (string) ($_SESSION['admin_name'] ?? 'Staff account');
    $role = (string) ($_SESSION['admin_role'] ?? 'staff');
}

if ($displayName === '' || $identityId < 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'An active account is required.']);
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS device_activity_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    client_session_id CHAR(36) NOT NULL,
    identity_type ENUM('staff','student') NOT NULL,
    identity_key VARCHAR(190) NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    student_id BIGINT UNSIGNED NULL,
    display_name VARCHAR(190) NOT NULL,
    role VARCHAR(40) NOT NULL,
    device_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    duration_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_device_activity_client_identity (client_session_id, identity_key),
    KEY idx_device_activity_identity (identity_type, identity_key, device_hash),
    KEY idx_device_activity_last_seen (last_activity_at),
    CONSTRAINT fk_device_activity_account FOREIGN KEY (account_id) REFERENCES admins(id) ON DELETE CASCADE,
    CONSTRAINT fk_device_activity_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB");
$deviceToken = (string) ($_COOKIE['MARKAZ_DEVICE_TOKEN'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/i', $deviceToken)) {
    $deviceToken = bin2hex(random_bytes(32));
    setcookie('MARKAZ_DEVICE_TOKEN', $deviceToken, [
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
$deviceHash = hash('sha256', $deviceToken);
$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireSeniorAdmin();
    $stmt = $pdo->query(
        "SELECT identity_type, display_name, role, user_agent,
                MAX(last_activity_at) AS last_activity_at,
                MIN(started_at) AS first_seen_at,
                SUM(duration_seconds) AS total_active_seconds,
                MAX(CASE WHEN ended_at IS NULL AND last_activity_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND) THEN 1 ELSE 0 END) AS is_active,
                COUNT(*) AS session_count
         FROM device_activity_sessions
         GROUP BY identity_type, identity_key, device_hash, display_name, role, user_agent
         ORDER BY is_active DESC, last_activity_at DESC"
    );
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $item['total_active_seconds'] = (int) $item['total_active_seconds'];
        $item['session_count'] = (int) $item['session_count'];
        $item['is_active'] = (bool) $item['is_active'];
    }
    unset($item);
    echo json_encode(['success' => true, 'activity' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid activity request.']);
    exit;
}

$action = (string) ($input['action'] ?? '');
if ($action === 'clear_history') {
    requireSeniorAdmin();
    $pdo->exec('DELETE FROM device_activity_sessions');
    echo json_encode(['success' => true, 'message' => 'Device activity history cleared.']);
    exit;
}

$clientSessionId = strtolower(trim((string) ($input['session_id'] ?? '')));
if (!in_array($action, ['heartbeat', 'pause'], true)
    || !preg_match('/^[a-f0-9-]{36}$/', $clientSessionId)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid activity session is required.']);
    exit;
}

$accountId = $identityType === 'staff' ? $identityId : null;
$studentId = $identityType === 'student' ? $identityId : null;
$endedAt = $action === 'pause' ? 'NOW()' : 'NULL';
$sql = "INSERT INTO device_activity_sessions
    (client_session_id, identity_type, identity_key, account_id, student_id,
     display_name, role, device_hash, user_agent, started_at, last_activity_at, ended_at, duration_seconds)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), {$endedAt}, 0)
    ON DUPLICATE KEY UPDATE
        duration_seconds = duration_seconds + IF(ended_at IS NULL, LEAST(45, GREATEST(0, TIMESTAMPDIFF(SECOND, last_activity_at, NOW()))), 0),
        display_name = VALUES(display_name),
        role = VALUES(role),
        user_agent = VALUES(user_agent),
        last_activity_at = NOW(),
        ended_at = {$endedAt}";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    $clientSessionId,
    $identityType,
    $identityKey,
    $accountId,
    $studentId,
    $displayName,
    $role,
    $deviceHash,
    $userAgent
]);

echo json_encode(['success' => true, 'message' => 'Activity recorded.']);
