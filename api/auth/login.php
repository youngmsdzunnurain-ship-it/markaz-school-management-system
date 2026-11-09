<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/session.php';

header('Content-Type: application/json; charset=utf-8');

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$requestedRole = strtolower(trim((string) ($input['expected_role'] ?? '')));
$allowedLoginRoles = ['admin', 'exam_officer', 'teacher', 'management'];
$isUsernameLogin = $requestedRole === 'teacher'
    && isset($input['username'])
    && !isset($input['email']);
$loginIdentifier = trim((string) ($isUsernameLogin ? $input['username'] : ($input['email'] ?? '')));
$password = (string) ($input['password'] ?? '');

if (!in_array($requestedRole, $allowedLoginRoles, true)
    || ($requestedRole === 'teacher' && !$isUsernameLogin)
    || ($requestedRole !== 'teacher' && $isUsernameLogin)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'This login page is not configured for the requested account role.'
    ]);
    exit;
}

if ($loginIdentifier === '' || $password === '') {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => ($isUsernameLogin ? 'Username' : 'Email') . ' and password are required.'
    ]);
    exit;
}

$pdo = db();
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


// Ensure failed-login tracking exists on installations that predate this table.
try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS login_lockouts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            role VARCHAR(40) NOT NULL,
            identifier VARCHAR(190) NOT NULL,
            failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_login_lockout_role_identifier (role, identifier),
            KEY idx_login_lockout_expiry (locked_until)
        ) ENGINE=InnoDB"
    );
} catch (Throwable $error) {
    error_log('Login lockout initialization failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Account protection is temporarily unavailable. Please contact the administrator.']);
    exit;
}

$setting = $pdo->prepare(
    "SELECT setting_value FROM settings WHERE setting_key = 'login_lockout_enabled' LIMIT 1"
);
$setting->execute();
$lockoutSetting = $setting->fetchColumn();
$lockoutEnabled = $lockoutSetting === false
    ? true
    : in_array(strtolower((string) $lockoutSetting), ['1', 'true', 'yes'], true);

if ($isUsernameLogin) {
    $accountStmt = $pdo->prepare(
        "SELECT id, name, email, username, role, password_hash, is_active, is_frozen
         FROM admins WHERE username = :username AND role = 'teacher' LIMIT 1"
    );
    $accountStmt->execute(['username' => $loginIdentifier]);
} else {
    $accountStmt = $pdo->prepare(
        'SELECT id, name, email, username, role, password_hash, is_active, is_frozen
         FROM admins WHERE email = :email AND role = :role LIMIT 1'
    );
    $accountStmt->execute([
        'email' => $loginIdentifier,
        'role' => $requestedRole
    ]);
}
$admin = $accountStmt->fetch(PDO::FETCH_ASSOC);

$roleKey = $admin ? (string) $admin['role'] : ($isUsernameLogin ? 'teacher' : 'staff');
$identifier = strtolower($loginIdentifier);

if ($lockoutEnabled) {
    $resetExpiredStmt = $pdo->prepare(
        'UPDATE login_lockouts
         SET failed_attempts = 0, locked_until = NULL
         WHERE role = ? AND identifier = ?
           AND locked_until IS NOT NULL AND locked_until <= NOW()'
    );
    $resetExpiredStmt->execute([$roleKey, $identifier]);

    $lockStmt = $pdo->prepare(
        'SELECT failed_attempts, locked_until,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS remaining_seconds
         FROM login_lockouts WHERE role = ? AND identifier = ? LIMIT 1'
    );
    $lockStmt->execute([$roleKey, $identifier]);
    $lock = $lockStmt->fetch(PDO::FETCH_ASSOC);

    if ($lock && !empty($lock['locked_until']) && (int) $lock['remaining_seconds'] > 0) {
        http_response_code(423);
        echo json_encode([
            'success' => false,
            'locked' => true,
            'locked_until' => $lock['locked_until'],
            'remaining_seconds' => (int) $lock['remaining_seconds'],
            'message' => 'This account is temporarily locked. Please try again when the countdown ends.'
        ]);
        exit;
    }
}

if (!$admin || !$admin['is_active'] || !password_verify($password, $admin['password_hash'])) {
    if ($lockoutEnabled) {
        $pdo->prepare(
            'INSERT INTO login_lockouts (role, identifier, failed_attempts, locked_until)
             VALUES (?, ?, 1, NULL)
             ON DUPLICATE KEY UPDATE
                failed_attempts = failed_attempts + 1,
                locked_until = NULL'
        )->execute([$roleKey, $identifier]);

        $countStmt = $pdo->prepare(
            'SELECT failed_attempts FROM login_lockouts WHERE role = ? AND identifier = ?'
        );
        $countStmt->execute([$roleKey, $identifier]);
        $attempts = (int) $countStmt->fetchColumn();

        if ($attempts >= 3) {
            $pdo->prepare(
                'UPDATE login_lockouts SET locked_until = DATE_ADD(NOW(), INTERVAL 7 DAY)
                 WHERE role = ? AND identifier = ?'
            )->execute([$roleKey, $identifier]);

            $expiryStmt = $pdo->prepare(
                'SELECT locked_until, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS remaining_seconds
                 FROM login_lockouts WHERE role = ? AND identifier = ?'
            );
            $expiryStmt->execute([$roleKey, $identifier]);
            $expiry = $expiryStmt->fetch(PDO::FETCH_ASSOC);

            http_response_code(423);
            echo json_encode([
                'success' => false,
                'locked' => true,
                'locked_until' => $expiry['locked_until'],
                'remaining_seconds' => (int) $expiry['remaining_seconds'],
                'message' => 'Too many failed attempts. This account is locked for 7 days.'
            ]);
            exit;
        }

        $remaining = max(0, 3 - $attempts);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'attempts_remaining' => $remaining,
            'message' => 'Invalid username/email or password. ' . $remaining . ' attempt(s) remaining before a 7-day lockout.'
        ]);
        exit;
    }

    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid username/email or password.']);
    exit;
}

if (!empty($admin['is_frozen'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'account_frozen' => true,
        'message' => 'This account has been blocked.'
    ]);
    exit;
}

$deviceToken = (string) ($_COOKIE['MARKAZ_DEVICE_TOKEN'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/i', $deviceToken)) {
    $deviceToken = bin2hex(random_bytes(32));
}
$deviceTokenHash = hash('sha256', $deviceToken);
$deviceCheckStmt = $pdo->prepare(
    'SELECT is_blocked FROM login_devices
     WHERE account_id = ? AND device_token_hash = ? LIMIT 1'
);
$deviceCheckStmt->execute([(int) $admin['id'], $deviceTokenHash]);
if ((int) $deviceCheckStmt->fetchColumn() === 1) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'device_blocked' => true,
        'message' => 'This account has been blocked.'
    ]);
    exit;
}

if ($lockoutEnabled) {
    $pdo->prepare(
        'DELETE FROM login_lockouts WHERE role = ? AND identifier = ?'
    )->execute([$roleKey, $identifier]);
}

/*
 * Access-control switches:
 * Login lockout applies to Admin and other supported staff accounts.
 * Exam Officer access can be globally disabled by Admin.
 */
if (
    $admin['role'] === 'exam_officer'
) {
    $accessStmt = $pdo->prepare(
        'SELECT setting_value
         FROM settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    $accessStmt->execute(['exam_officer_access']);

    $accessValue = $accessStmt->fetchColumn();

    $examOfficerAccessEnabled =
        $accessValue === '1' ||
        strtolower((string) $accessValue) === 'true';

    if (!$examOfficerAccessEnabled) {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Exam Officer access is currently closed by the Admin.'
        ]);
        exit;
    }
}

$portalSettings = [
    'exam_officer' => 'exam_officer_access',
    'teacher' => 'teacher_access',
    'student' => 'student_result_access',
    'management' => 'management_access',
];

$loginRole = (string) $admin['role'];
if ($loginRole !== 'admin' && isset($portalSettings[$loginRole])) {
    $portalStmt = $pdo->prepare(
        'SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1'
    );
    $portalStmt->execute([$portalSettings[$loginRole]]);
    $portalValue = $portalStmt->fetchColumn();

    if (!in_array(strtolower(trim((string) $portalValue)), ['1', 'true', 'yes', 'on'], true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'portal_closed' => true,
            'message' => 'Your portal access is currently closed by the Admin.'
        ]);
        exit;
    }
}

$deviceStmt = $pdo->prepare(
    'INSERT INTO login_devices (account_id, device_token_hash, user_agent, last_seen_at)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE user_agent = VALUES(user_agent), last_seen_at = NOW()'
);
$deviceStmt->execute([
    (int) $admin['id'],
    $deviceTokenHash,
    substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)
]);
$_SESSION['device_token_hash'] = $deviceTokenHash;
setcookie('MARKAZ_DEVICE_TOKEN', $deviceToken, [
    'expires' => time() + 31536000,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_regenerate_id(true);

$_SESSION['admin_id'] = (int) $admin['id'];
$_SESSION['admin_name'] = $admin['name'];
$_SESSION['admin_email'] = $admin['email'];
$_SESSION['admin_role'] = $admin['role'];

$stmt = $pdo->prepare(
    'UPDATE admins
     SET last_login_at = NOW()
     WHERE id = :id'
);

$stmt->execute([
    'id' => $admin['id']
]);

echo json_encode([
    'success' => true,
    'message' => 'Login successful.',
    'admin' => [
        'id' => (int) $admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => $admin['role']
    ]
]);