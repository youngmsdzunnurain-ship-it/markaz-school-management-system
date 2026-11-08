<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/config/app.php';
require_once __DIR__ . '/../../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

requireAdminOrExamOfficer();

function releaseResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query(
            "SELECT ap.id AS period_id, ap.results_released,
                    s.name AS session_name, t.name AS term_name
             FROM academic_periods ap
             INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
             INNER JOIN terms t ON t.id = ap.term_id
             ORDER BY s.name DESC, t.sort_order ASC"
        );

        releaseResponse(200, [
            'success' => true,
            'periods' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        releaseResponse(405, [
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        releaseResponse(400, ['success' => false, 'message' => 'Invalid request.']);
    }

    if (currentAdminRole() === 'exam_officer') {
        $password = (string) ($input['password'] ?? '');
        $accountStmt = $pdo->prepare(
            'SELECT password_hash FROM admins
             WHERE id = ? AND role = "exam_officer"
               AND is_active = 1 AND is_frozen = 0
             LIMIT 1'
        );
        $accountStmt->execute([(int) $_SESSION['admin_id']]);
        $passwordHash = $accountStmt->fetchColumn();

        if ($password === '' || !is_string($passwordHash) || !password_verify($password, $passwordHash)) {
            releaseResponse(403, [
                'success' => false,
                'message' => 'Your Exam Officer password is incorrect. Result release was not changed.'
            ]);
        }
    }

    $periodId = filter_var($input['period_id'] ?? null, FILTER_VALIDATE_INT);
    $released = filter_var(
        $input['released'] ?? null,
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE
    );

    if (!$periodId || $periodId < 1 || $released === null) {
        releaseResponse(422, [
            'success' => false,
            'message' => 'Choose a valid academic period and release status.'
        ]);
    }

    $stmt = $pdo->prepare(
        'UPDATE academic_periods SET results_released = ? WHERE id = ?'
    );
    $stmt->execute([$released ? 1 : 0, $periodId]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id FROM academic_periods WHERE id = ?');
        $check->execute([$periodId]);
        if (!$check->fetchColumn()) {
            releaseResponse(404, [
                'success' => false,
                'message' => 'Academic period not found.'
            ]);
        }
    }

    releaseResponse(200, [
        'success' => true,
        'message' => $released
            ? 'Results released for the selected academic period.'
            : 'Results hidden for the selected academic period.'
    ]);
} catch (Throwable $e) {
    error_log('Result release settings error: ' . $e->getMessage());
    releaseResponse(500, [
        'success' => false,
        'message' => 'Unable to update result release settings.'
    ]);
}
