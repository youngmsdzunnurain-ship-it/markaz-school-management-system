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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo = db();
        $stmt = $pdo->query(
            'SELECT ap.id, ap.academic_session_id, ap.term_id,
                    s.name AS session_name, t.name AS term_name,
                    ap.is_current, ap.is_finalized, ap.finalized_at,
                    ap.results_released, ap.resumption_date,
                    COUNT(DISTINCT r.id) AS saved_result_rows,
                    COUNT(DISTINCT br.id) AS behavioural_reports
             FROM academic_periods ap
             INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
             INNER JOIN terms t ON t.id = ap.term_id
             LEFT JOIN results r ON r.academic_period_id = ap.id
             LEFT JOIN behavioural_reports br ON br.academic_period_id = ap.id
             GROUP BY ap.id, ap.academic_session_id, ap.term_id,
                      s.name, t.name, ap.is_current, ap.is_finalized,
                      ap.finalized_at, ap.results_released, ap.resumption_date
             ORDER BY s.name DESC, t.sort_order ASC'
        );
        $periods = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'session_name' => $row['session_name'],
                'term_name' => $row['term_name'],
                'is_current' => (int) $row['is_current'] === 1,
                'is_finalized' => (int) $row['is_finalized'] === 1,
                'finalized_at' => $row['finalized_at'],
                'results_released' => (int) $row['results_released'] === 1,
                'resumption_date' => $row['resumption_date'],
                'saved_result_rows' => (int) $row['saved_result_rows'],
                'behavioural_reports' => (int) $row['behavioural_reports']
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        finalizePeriodResponse(200, ['success' => true, 'periods' => $periods]);
    } catch (Throwable $error) {
        error_log('Academic archive list failed: ' . $error->getMessage());
        finalizePeriodResponse(500, ['success' => false, 'message' => 'Unable to load academic archives.']);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    finalizePeriodResponse(405, ['success' => false, 'message' => 'GET or POST request required.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || ($input['confirm'] ?? false) !== true) {
    finalizePeriodResponse(422, [
        'success' => false,
        'message' => 'Explicit confirmation is required before finalizing a term.'
    ]);
}

$password = (string) ($input['password'] ?? '');
if ($password === '') {
    finalizePeriodResponse(422, [
        'success' => false,
        'message' => 'Enter your administrator password to finalize this term.'
    ]);
}

try {
    $pdo = db();
    $adminStmt = $pdo->prepare(
        'SELECT password_hash FROM admins
         WHERE id = ? AND role = "admin" AND is_active = 1 AND is_frozen = 0
         LIMIT 1'
    );
    $adminStmt->execute([(int) $_SESSION['admin_id']]);
    $passwordHash = $adminStmt->fetchColumn();

    if (!is_string($passwordHash) || !password_verify($password, $passwordHash)) {
        finalizePeriodResponse(403, [
            'success' => false,
            'message' => 'The administrator password is incorrect. The term has not been finalized.'
        ]);
    }
} catch (Throwable $error) {
    error_log('Academic finalization password verification failed: ' . $error->getMessage());
    finalizePeriodResponse(500, [
        'success' => false,
        'message' => 'Unable to verify administrator password. The term has not been finalized.'
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
