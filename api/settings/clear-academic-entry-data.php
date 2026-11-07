<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
requireSeniorAdmin();

function academicResetResponse(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    academicResetResponse(405, ['success' => false, 'message' => 'POST request required.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || ($input['confirm'] ?? false) !== true) {
    academicResetResponse(422, ['success' => false, 'message' => 'Explicit confirmation is required.']);
}

$password = (string) ($input['password'] ?? '');
$confirmation = strtoupper(preg_replace('/\\s+/', ' ', trim((string) ($input['confirmation'] ?? ''))));

if ($password === '' || $confirmation !== 'CLEAR ACADEMIC ENTRY DATA') {
    academicResetResponse(422, [
        'success' => false,
        'message' => 'Enter your administrator password and type CLEAR ACADEMIC ENTRY DATA to confirm.'
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
        academicResetResponse(403, [
            'success' => false,
            'message' => 'The administrator password is incorrect. No academic data was cleared.'
        ]);
    }

    $pdo->beginTransaction();

    // Serialize this all-period reset with score and behaviour writes, which lock their period row.
    $pdo->query('SELECT id FROM academic_periods ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);

    $deleted = [];
    foreach (['results', 'behavioural_reports', 'student_assessments'] as $table) {
        $deleted[$table] = $pdo->exec("DELETE FROM {$table}");
    }

    $pdo->commit();

    academicResetResponse(200, [
        'success' => true,
        'message' => 'Academic entry data cleared. Student details and school setup were preserved.',
        'deleted' => [
            'subject_result_entries' => (int) $deleted['results'],
            'behavioural_reports' => (int) $deleted['behavioural_reports'],
            'legacy_student_assessments' => (int) $deleted['student_assessments']
        ]
    ]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Academic entry reset failed: ' . $error->getMessage());
    academicResetResponse(500, [
        'success' => false,
        'message' => 'Unable to clear academic entry data. The database transaction was rolled back.'
    ]);
}
