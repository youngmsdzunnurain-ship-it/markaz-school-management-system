<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

attendanceRequireStaff();
$pdo = db();
$stmt = $pdo->query(
    "SELECT ap.id AS academic_period_id, ap.is_current, ap.is_finalized,
            s.id AS academic_session_id, s.name AS session_name,
            t.id AS term_id, t.name AS term_name, t.sort_order AS term_order
     FROM academic_periods ap
     INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
     INNER JOIN terms t ON t.id = ap.term_id
     ORDER BY ap.is_current DESC, s.id DESC, t.sort_order, ap.id DESC"
);
$periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($periods as &$period) {
    foreach (['academic_period_id', 'academic_session_id', 'term_id', 'term_order'] as $key) {
        $period[$key] = (int) $period[$key];
    }
    $period['is_current'] = (bool) $period['is_current'];
    $period['is_finalized'] = (bool) $period['is_finalized'];
    $period['label'] = $period['session_name'] . ' · ' . $period['term_name'];
}
unset($period);

echo json_encode([
    'success' => true,
    'current_period_id' => (int) (array_values(array_filter($periods, static fn(array $p): bool => $p['is_current']))[0]['academic_period_id'] ?? 0),
    'periods' => $periods,
], JSON_UNESCAPED_UNICODE);
