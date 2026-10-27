<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireSeniorAdmin();

$pdo = db();

try {
    // Self-heal older installations where the lockout table was never created.
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
    error_log('Login lockout table initialization failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to initialize account locking. Check database permissions and server logs.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        "SELECT id, role, identifier, failed_attempts, locked_until,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS remaining_seconds
         FROM login_lockouts
         WHERE locked_until IS NOT NULL AND locked_until > NOW()
         ORDER BY locked_until ASC"
    );

    echo json_encode(['success' => true, 'accounts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id || ($input['action'] ?? '') !== 'unlock') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid unlock request.']);
    exit;
}

$stmt = $pdo->prepare('DELETE FROM login_lockouts WHERE id = ? AND locked_until IS NOT NULL');
$stmt->execute([$id]);

echo json_encode([
    'success' => true,
    'message' => $stmt->rowCount() ? 'Account unlocked successfully.' : 'No active lockout found for that account.'
]);
