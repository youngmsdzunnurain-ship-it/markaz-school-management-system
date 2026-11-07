<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
requireSeniorAdmin();

function finalizePeriodResponse(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    finalizePeriodResponse(405, ['success' => false, 'message' => 'POST request required.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || ($input['confirm'] ?? false) !== true) {
    finalizePeriodResponse(422, [
        'success' => false,
        'message' => 'Explicit confirmation is required before finalizing a term.'
    ]);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $periodStmt = $pdo->query(
        'SELECT ap.id, ap.is_finalized, ap.results_released,
                s.name AS session_name, t.name AS term_name
         FROM academic_periods ap
         INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
         INNER JOIN terms t ON t.id = ap.term_id
         WHERE s.is_current = 1 AND ap.is_current = 1
         ORDER BY ap.id DESC
         LIMIT 1
         FOR UPDATE'
    );
    $period = $periodStmt->fetch(PDO::FETCH_ASSOC);

    if (!$period) {
        $pdo->rollBack();
        finalizePeriodResponse(404, ['success' => false, 'message' => 'No current academic period is configured.']);
    }

    if ((int) $period['is_finalized'] === 1) {
        $pdo->commit();
        finalizePeriodResponse(200, [
            'success' => true,
            'already_finalized' => true,
            'message' => 'This academic period has already been finalized.',
            'academic_period' => [
                'id' => (int) $period['id'],
                'session_name' => $period['session_name'],
                'term_name' => $period['term_name']
            ]
        ]);
    }

    $update = $pdo->prepare(
        'UPDATE academic_periods
         SET is_finalized = 1, finalized_at = NOW(), finalized_by = ?,
             updated_at = NOW()
         WHERE id = ? AND is_finalized = 0'
    );
    $update->execute([(int) ($_SESSION['admin_id'] ?? 0), (int) $period['id']]);

    if ($update->rowCount() !== 1) {
        $pdo->rollBack();
        finalizePeriodResponse(409, ['success' => false, 'message' => 'The academic period changed while it was being finalized. Refresh and try again.']);
    }

    $pdo->commit();

    finalizePeriodResponse(200, [
        'success' => true,
        'message' => 'Term finalized and locked successfully. Results and behavioural assessments are now read-only.',
        'academic_period' => [
            'id' => (int) $period['id'],
            'session_name' => $period['session_name'],
            'term_name' => $period['term_name'],
            'results_released' => (int) $period['results_released'] === 1
        ]
    ]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Academic period finalization failed: ' . $error->getMessage());
    finalizePeriodResponse(500, ['success' => false, 'message' => 'Unable to finalize this academic period. No changes were committed.']);
}
