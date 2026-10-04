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

$email = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required.'
    ]);
    exit;
}

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT id, name, email, role, password_hash, is_active
     FROM admins
     WHERE email = :email
     LIMIT 1'
);

$stmt->execute([
    'email' => $email
]);

$admin = $stmt->fetch();

if (
    !$admin ||
    !$admin['is_active'] ||
    !password_verify($password, $admin['password_hash'])
) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid email or password.'
    ]);
    exit;
}

/*
 * Access-control switches:
 * Admin is never blocked.
 * Exam Officer can be globally disabled by Admin.
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