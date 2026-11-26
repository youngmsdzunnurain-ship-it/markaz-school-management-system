<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/config/app.php';
require_once __DIR__ . '/../../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
requireAdminOrExamOfficer();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

function academicPeriodResponse(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getCurrentAcademicPeriod(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        'SELECT ap.id, ap.academic_session_id, ap.term_id,
                s.name AS session_name, t.name AS term_name,
                ap.examination_date, ap.vacation_date, ap.resumption_date, ap.starts_on, ap.ends_on,
                ap.results_released, ap.is_finalized, ap.finalized_at
         FROM academic_periods ap
         INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
         INNER JOIN terms t ON t.id = ap.term_id
         WHERE s.is_current = 1 AND ap.is_current = 1
         ORDER BY ap.id DESC LIMIT 1'
    );
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getAcademicSessions(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, name, is_current FROM academic_sessions ORDER BY id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
}

if ($method === 'GET') {
    $period = getCurrentAcademicPeriod($pdo);
    if (!$period) {
        academicPeriodResponse(404, ['success' => false, 'message' => 'No current academic period is configured.']);
    }
    academicPeriodResponse(200, [
        'success' => true,
        'sessions' => getAcademicSessions($pdo),
        'academic_period' => [
            'id' => (int) $period['id'],
            'academic_session_id' => (int) $period['academic_session_id'],
            'term_id' => (int) $period['term_id'],
            'session_name' => $period['session_name'],
            'term_name' => $period['term_name'],
            'resumption_date' => $period['resumption_date'],
            'starts_on' => $period['starts_on'],
            'ends_on' => $period['ends_on'],
            'is_finalized' => (int) $period['is_finalized'],
            'finalized_at' => $period['finalized_at'],
            'results_released' => (int) $period['results_released']
        ]
    ]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    academicPeriodResponse(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$sessionId = filter_var($_POST['academic_session_id'] ?? null, FILTER_VALIDATE_INT);
$termId = filter_var($_POST['term_id'] ?? null, FILTER_VALIDATE_INT);
$resumptionDate = trim((string) ($_POST['resumption_date'] ?? ''));
$startsOn = trim((string) ($_POST['starts_on'] ?? ''));
$endsOn = trim((string) ($_POST['ends_on'] ?? ''));

if (!$sessionId || $sessionId < 1 || !$termId || !in_array($termId, [1, 2, 3], true)) {
    academicPeriodResponse(422, ['success' => false, 'message' => 'Select a valid academic session and term.']);
}
$date = DateTime::createFromFormat('Y-m-d', $resumptionDate);
$termStart = DateTime::createFromFormat('!Y-m-d', $startsOn);
$termEnd = DateTime::createFromFormat('!Y-m-d', $endsOn);
if (!$date || $date->format('Y-m-d') !== $resumptionDate
    || !$termStart || $termStart->format('Y-m-d') !== $startsOn
    || !$termEnd || $termEnd->format('Y-m-d') !== $endsOn
    || $endsOn < $startsOn) {
    academicPeriodResponse(422, ['success' => false, 'message' => 'Enter valid term start, term end and next-term resumption dates. The term end cannot be before its start.']);
}

try {
    $pdo->beginTransaction();

    // Serialize progression requests against the session rows before changing the active period.
    $pdo->query('SELECT id FROM academic_sessions ORDER BY id FOR UPDATE')->fetchAll();

    $sessionCheck = $pdo->prepare('SELECT id FROM academic_sessions WHERE id = ? LIMIT 1');
    $sessionCheck->execute([$sessionId]);
    if (!$sessionCheck->fetchColumn()) {
        $pdo->rollBack();
        academicPeriodResponse(422, ['success' => false, 'message' => 'Selected academic session does not exist.']);
    }

    $termCheck = $pdo->prepare('SELECT id FROM terms WHERE id = ? LIMIT 1');
    $termCheck->execute([$termId]);
    if (!$termCheck->fetchColumn()) {
        $pdo->rollBack();
        academicPeriodResponse(422, ['success' => false, 'message' => 'Selected term does not exist.']);
    }

    $currentStmt = $pdo->query(
        'SELECT ap.id, ap.academic_session_id, ap.term_id, ap.is_finalized,
                s.name AS session_name, t.name AS term_name
         FROM academic_periods ap
         INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
         INNER JOIN terms t ON t.id = ap.term_id
         WHERE s.is_current = 1 AND ap.is_current = 1
         ORDER BY ap.id DESC LIMIT 1 FOR UPDATE'
    );
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

    $samePeriod = $current
        && (int) $current['academic_session_id'] === $sessionId
        && (int) $current['term_id'] === $termId;

    if ($samePeriod && $current && (int) $current['is_finalized'] === 1) {
        $pdo->rollBack();
        academicPeriodResponse(409, [
            'success' => false,
            'code' => 'PERIOD_ALREADY_FINALIZED',
            'message' => 'This academic period is finalized and read-only. Select the next term to continue.'
        ]);
    }

    if ($current && !$samePeriod && (int) $current['is_finalized'] !== 1) {
        $pdo->rollBack();
        academicPeriodResponse(409, [
            'success' => false,
            'code' => 'PERIOD_NOT_FINALIZED',
            'message' => 'Finalize and lock the current term before switching to another term or academic session.',
            'current_period' => [
                'session_name' => $current['session_name'],
                'term_name' => $current['term_name']
            ]
        ]);
    }

    $targetStmt = $pdo->prepare(
        'SELECT id, is_finalized FROM academic_periods
         WHERE academic_session_id = ? AND term_id = ? LIMIT 1 FOR UPDATE'
    );
    $targetStmt->execute([$sessionId, $termId]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);

    if ($target && (int) $target['is_finalized'] === 1 && !$samePeriod) {
        $pdo->rollBack();
        academicPeriodResponse(409, [
            'success' => false,
            'code' => 'PERIOD_ALREADY_FINALIZED',
            'message' => 'This term has already been finalized and archived. It cannot be reactivated for new entries.'
        ]);
    }

    if ($target) {
        $periodId = (int) $target['id'];
        $update = $pdo->prepare(
            'UPDATE academic_periods SET starts_on = ?, ends_on = ?, resumption_date = ?, updated_at = NOW() WHERE id = ? AND is_finalized = 0'
        );
        $update->execute([$startsOn, $endsOn, $resumptionDate, $periodId]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO academic_periods (academic_session_id, term_id, starts_on, ends_on, resumption_date, is_current)
             VALUES (?, ?, ?, ?, ?, 0)'
        );
        $insert->execute([$sessionId, $termId, $startsOn, $endsOn, $resumptionDate]);
        $periodId = (int) $pdo->lastInsertId();
    }

    $pdo->exec('UPDATE academic_periods SET is_current = 0 WHERE is_current = 1');
    $pdo->exec('UPDATE academic_sessions SET is_current = 0 WHERE is_current = 1');

    $activateSession = $pdo->prepare('UPDATE academic_sessions SET is_current = 1 WHERE id = ?');
    $activateSession->execute([$sessionId]);
    $activatePeriod = $pdo->prepare('UPDATE academic_periods SET is_current = 1 WHERE id = ? AND is_finalized = 0');
    $activatePeriod->execute([$periodId]);

    if ($activatePeriod->rowCount() !== 1) {
        throw new RuntimeException('The selected academic period could not be activated.');
    }

    $pdo->commit();
    academicPeriodResponse(200, [
        'success' => true,
        'message' => $samePeriod ? 'Academic calendar updated successfully.' : 'New academic period activated. Previous finalized results remain archived.',
        'academic_period' => [
            'id' => $periodId,
            'academic_session_id' => $sessionId,
            'term_id' => $termId,
            'resumption_date' => $resumptionDate,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_finalized' => false
        ]
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Academic period progression failed: ' . $error->getMessage());
    academicPeriodResponse(500, ['success' => false, 'message' => 'Unable to save the academic calendar. No changes were committed.']);
}
