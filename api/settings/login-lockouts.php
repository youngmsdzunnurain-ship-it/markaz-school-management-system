<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireSeniorAdmin();

$pdo = db();

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
