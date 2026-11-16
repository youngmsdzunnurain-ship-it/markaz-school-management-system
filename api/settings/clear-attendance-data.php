<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
requireSeniorAdmin();

function attendanceResetResponse(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    attendanceResetResponse(405, ['success' => false, 'message' => 'POST request required.']);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input) || ($input['confirm'] ?? false) !== true) {
    attendanceResetResponse(422, ['success' => false, 'message' => 'Explicit confirmation is required.']);
}

$password = (string) ($input['password'] ?? '');
$confirmation = strtoupper(preg_replace('/\\s+/', ' ', trim((string) ($input['confirmation'] ?? ''))));

if ($password === '' || strlen($password) > 4096 || $confirmation !== 'CLEAR ALL SCHOOL ATTENDANCE') {
    attendanceResetResponse(422, [
        'success' => false,
        'message' => 'Enter your administrator password and type CLEAR ALL SCHOOL ATTENDANCE to confirm.'
    ]);
}

try {
    $pdo = db();
    $adminStmt = $pdo->prepare(
        'SELECT password_hash FROM admins
         WHERE id = ? AND role = "admin" AND is_active = 1 AND is_frozen = 0
         LIMIT 1'
    );
    $adminStmt->execute([(int) ($_SESSION['admin_id'] ?? 0)]);
    $passwordHash = $adminStmt->fetchColumn();

    if (!is_string($passwordHash) || !password_verify($password, $passwordHash)) {
        attendanceResetResponse(403, [
            'success' => false,
            'message' => 'The administrator password is incorrect. No attendance records were cleared.'
        ]);
    }

    $pdo->beginTransaction();

    // This table contains only the daily Conventional and Arabic school register.
    // Online Class attendance is stored separately and is deliberately untouched.
    $deleted = $pdo->exec('DELETE FROM attendance_records');

    $pdo->commit();

    attendanceResetResponse(200, [
        'success' => true,
        'message' => 'All Conventional and Arabic daily attendance records have been cleared. Students and school setup were preserved.',
        'deleted_count' => (int) $deleted
    ]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('School attendance reset failed: ' . $error->getMessage());
    attendanceResetResponse(500, [
        'success' => false,
        'message' => 'Unable to clear school attendance. The database transaction was rolled back.'
    ]);
}
