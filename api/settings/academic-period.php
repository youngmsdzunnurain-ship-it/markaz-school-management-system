<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

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
         ORDER BY ap.id DESC
         LIMIT 1'
    );

    $period = $stmt->fetch();

    return $period ?: null;
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
        'academic_period' => [
            'id' => (int) $period['id'],
            'session_name' => $period['session_name'],
            'term_id' => (int) $period['term_id'],
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

$termId = filter_var(
    $_POST['term_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$resumptionDate = trim((string) ($_POST['resumption_date'] ?? ''));

if ($termId === false || $termId === null) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please select a valid current term.'
    ]);
    exit;
}

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

$termStmt = $pdo->prepare(
    'SELECT id, name
     FROM terms
     WHERE id = ?
     LIMIT 1'
);

$termStmt->execute([$termId]);
$term = $termStmt->fetch();

if (!$term) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'The selected academic term does not exist.'
    ]);
    exit;
}

$period = getCurrentAcademicPeriod($pdo);

if (!$period) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'No current academic period is configured.'
    ]);
    exit;
}

$duplicateStmt = $pdo->prepare(
    'SELECT id
     FROM academic_periods
     WHERE academic_session_id = ?
       AND term_id = ?
       AND id <> ?
     LIMIT 1'
);

$duplicateStmt->execute([
    (int) $period['academic_session_id'],
    (int) $termId,
    (int) $period['id']
]);

$duplicatePeriod = $duplicateStmt->fetch();

if ($duplicatePeriod) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'That term already has an academic period for the current session.'
    ]);
    exit;
}

$stmt = $pdo->prepare(
    'UPDATE academic_periods
     SET term_id = ?,
         resumption_date = ?,
         updated_at = NOW()
     WHERE id = ?'
);

$stmt->execute([
    (int) $termId,
    $resumptionDate,
    (int) $period['id']
]);

echo json_encode([
    'success' => true,
    'message' => 'Academic calendar saved successfully.',
    'academic_period' => [
        'id' => (int) $period['id'],
        'term_id' => (int) $termId,
        'term_name' => $term['name'],
        'resumption_date' => $resumptionDate
    ]
]);
