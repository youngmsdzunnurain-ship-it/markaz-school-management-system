<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireSeniorAdmin();

$pdo = db();
$actorId = (int) ($_SESSION['admin_id'] ?? 0);

function freezeResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        "SELECT id, name, email, username, role, is_active, is_frozen,
                frozen_at, freeze_reason
         FROM admins
         WHERE role <> 'admin'
         ORDER BY is_frozen DESC, name ASC"
    );

    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($accounts as &$account) {
        $account['is_active'] = (bool) $account['is_active'];
        $account['is_frozen'] = (bool) $account['is_frozen'];
    }
    unset($account);

    freezeResponse(['success' => true, 'accounts' => $accounts]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    freezeResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);

if (!is_array($input)) {
    freezeResponse(['success' => false, 'message' => 'Send a valid JSON request.'], 400);
}

$action = $input['action'] ?? '';
$targetId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
$reason = trim((string) ($input['reason'] ?? ''));

if (
    !in_array($action, ['freeze', 'unfreeze'], true)
    || !$targetId || $targetId < 1
) {
    freezeResponse(['success' => false, 'message' => 'Invalid account freeze request.'], 400);
}

if ($action === 'freeze' && $reason === '') {
    freezeResponse(['success' => false, 'message' => 'Enter a reason for freezing this account.'], 400);
}

if (mb_strlen($reason) > 500) {
    freezeResponse(['success' => false, 'message' => 'The reason must not exceed 500 characters.'], 400);
}

if ($targetId === $actorId) {
    freezeResponse(['success' => false, 'message' => 'You cannot freeze or unfreeze your own account here.'], 403);
}

try {
    $pdo->beginTransaction();

    $targetStmt = $pdo->prepare(
        'SELECT id, name, role, is_frozen
         FROM admins
         WHERE id = ?
         LIMIT 1
         FOR UPDATE'
    );
    $targetStmt->execute([$targetId]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        $pdo->rollBack();
        freezeResponse(['success' => false, 'message' => 'Account not found.'], 404);
    }

    if ($target['role'] === 'admin') {
        $pdo->rollBack();
        freezeResponse(['success' => false, 'message' => 'Admin accounts cannot be frozen through this control.'], 403);
    }

    $actorStmt = $pdo->prepare(
        "SELECT name FROM admins WHERE id = ? AND role = 'admin' LIMIT 1"
    );
    $actorStmt->execute([$actorId]);
    $actorName = $actorStmt->fetchColumn();

    if ($actorName === false) {
        $pdo->rollBack();
        freezeResponse(['success' => false, 'message' => 'Senior Admin verification failed.'], 403);
    }

    $currentlyFrozen = (bool) $target['is_frozen'];

    if ($action === 'freeze') {
        if ($currentlyFrozen) {
            $pdo->rollBack();
            freezeResponse(['success' => false, 'message' => 'This account is already frozen.'], 409);
        }

        $update = $pdo->prepare(
            'UPDATE admins
             SET is_frozen = 1, frozen_at = NOW(), frozen_by = ?, freeze_reason = ?
             WHERE id = ? AND role <> ?'
        );
        $update->execute([$actorId, $reason, $targetId, 'admin']);
    } else {
        if (!$currentlyFrozen) {
            $pdo->rollBack();
            freezeResponse(['success' => false, 'message' => 'This account is not frozen.'], 409);
        }

        $update = $pdo->prepare(
            'UPDATE admins
             SET is_frozen = 0, frozen_at = NULL, frozen_by = NULL, freeze_reason = NULL
             WHERE id = ? AND role <> ?'
        );
        $update->execute([$targetId, 'admin']);
    }

    $audit = $pdo->prepare(
        'INSERT INTO account_freeze_audit
         (target_account_id, target_name, target_role, action, reason, performed_by, performed_by_name)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $audit->execute([
        $targetId,
        $target['name'],
        $target['role'],
        $action,
        $reason !== '' ? $reason : null,
        $actorId,
        (string) $actorName
    ]);

    $pdo->commit();

    freezeResponse([
        'success' => true,
        'message' => $action === 'freeze'
            ? 'Account frozen successfully. The freeze remains until a Senior Admin unfreezes it.'
            : 'Account unfrozen successfully. Its existing active/inactive status has not been changed.'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Account freeze API error: ' . $e->getMessage());
    freezeResponse(['success' => false, 'message' => 'Unable to update the account freeze status.'], 500);
}
