<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/config/app.php';
require_once __DIR__ . '/../../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireSeniorAdmin();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

function getCurrentAcademicPeriod(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        'SELECT
            ap.id,
            ap.academic_session_id,
            ap.term_id,
            s.name AS session_name,
            t.name AS term_name,
            ap.examination_date,
            ap.vacation_date,
            ap.resumption_date
         FROM academic_periods ap
         INNER JOIN academic_sessions s
            ON s.id = ap.academic_session_id
         INNER JOIN terms t
            ON t.id = ap.term_id
         WHERE s.is_current = 1
           AND ap.is_current = 1
         ORDER BY ap.id DESC
         LIMIT 1'
    );

    $period = $stmt->fetch();

    return $period ?: null;
}

function getAcademicSessions(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, name, is_current
         FROM academic_sessions
         ORDER BY id ASC'
    );

    return $stmt->fetchAll();
}

if ($method === 'GET') {
    $period = getCurrentAcademicPeriod($pdo);

    if (!$period) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No current academic period is configured.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'sessions' => getAcademicSessions($pdo),
        'academic_period' => [
            'id' => (int) $period['id'],
            'academic_session_id' => (int) $period['academic_session_id'],
            'term_id' => (int) $period['term_id'],
            'session_name' => $period['session_name'],
            'term_name' => $period['term_name'],
            'resumption_date' => $period['resumption_date']
        ]
    ]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$academicSessionId = (int) ($_POST['academic_session_id'] ?? 0);
$termId = (int) ($_POST['term_id'] ?? 0);

if ($academicSessionId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Academic Session is required.'
    ]);
    exit;
}

if ($termId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Current Term is required.'
    ]);
    exit;
}

$sessionStmt = $pdo->prepare(
    'SELECT id FROM academic_sessions WHERE id = ? LIMIT 1'
);
$sessionStmt->execute([$academicSessionId]);

if (!$sessionStmt->fetchColumn()) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Selected Academic Session does not exist.'
    ]);
    exit;
}

$termStmt = $pdo->prepare(
    'SELECT id FROM terms WHERE id = ? LIMIT 1'
);
$termStmt->execute([$termId]);

if (!$termStmt->fetchColumn()) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Selected Current Term does not exist.'
    ]);
    exit;
}

$resumptionDate = trim((string) ($_POST['resumption_date'] ?? ''));

if ($resumptionDate === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Next Term Begins date is required.'
    ]);
    exit;
}

$date = DateTime::createFromFormat('Y-m-d', $resumptionDate);

if (!$date || $date->format('Y-m-d') !== $resumptionDate) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid date.'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    $pdo->exec(
        'UPDATE academic_sessions
         SET is_current = 0'
    );

    $currentSessionStmt = $pdo->prepare(
        'UPDATE academic_sessions
         SET is_current = 1
         WHERE id = ?'
    );
    $currentSessionStmt->execute([$academicSessionId]);

    // Exactly one academic period is current across the school.
    $pdo->exec('UPDATE academic_periods SET is_current = 0');

    $periodStmt = $pdo->prepare(
        'SELECT id
         FROM academic_periods
         WHERE academic_session_id = ?
           AND term_id = ?
         LIMIT 1'
    );
    $periodStmt->execute([
        $academicSessionId,
        $termId
    ]);

    $periodId = $periodStmt->fetchColumn();

    if ($periodId) {
        $savePeriodStmt = $pdo->prepare(
            'UPDATE academic_periods
             SET resumption_date = ?,
                 is_current = 1,
                 updated_at = NOW()
             WHERE id = ?'
        );

        $savePeriodStmt->execute([
            $resumptionDate,
            (int) $periodId
        ]);
    } else {
        $savePeriodStmt = $pdo->prepare(
            'INSERT INTO academic_periods
             (academic_session_id, term_id, resumption_date, is_current)
             VALUES (?, ?, ?, 1)'
        );

        $savePeriodStmt->execute([
            $academicSessionId,
            $termId,
            $resumptionDate
        ]);

        $periodId = $pdo->lastInsertId();
    }

    $pdo->commit();

} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No current academic period is configured.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Next Term Begins date saved successfully.',
    'resumption_date' => $resumptionDate
]);
