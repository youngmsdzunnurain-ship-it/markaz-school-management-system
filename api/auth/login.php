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

$isUsernameLogin = isset($input['username']) && !isset($input['email']);
$loginIdentifier = trim((string) ($isUsernameLogin ? $input['username'] : ($input['email'] ?? '')));
$password = (string) ($input['password'] ?? '');

if ($loginIdentifier === '' || $password === '') {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => ($isUsernameLogin ? 'Username' : 'Email') . ' and password are required.'
    ]);
    exit;
}

$pdo = db();

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
        "SELECT id, name, email, username, role, password_hash, is_active, is_frozen
         FROM admins WHERE email = :email AND role <> 'teacher' LIMIT 1"
    );
    $accountStmt->execute(['email' => $loginIdentifier]);
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
        'message' => 'This account has been frozen by the Senior Admin. Contact the school administrator.'
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