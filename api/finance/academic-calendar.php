<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/finance.php';
financeRequireAccess();

try {
    $pdo = db();
    $stmt = $pdo->query(
        "SELECT ap.id AS period_id, ap.starts_on, ap.ends_on,
                ap.examination_date, ap.vacation_date, ap.resumption_date,
                ap.is_current, ap.is_finalized,
                s.name AS session_name, t.name AS term_name
         FROM academic_periods ap
         INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
         INNER JOIN terms t ON t.id = ap.term_id
         ORDER BY s.id DESC, t.sort_order ASC, ap.id ASC"
    );

    $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $events = [];
    $eventFields = [
        'starts_on' => ['type' => 'term_start', 'label' => 'Term begins'],
        'ends_on' => ['type' => 'term_end', 'label' => 'Term ends'],
        'examination_date' => ['type' => 'examination', 'label' => 'Examination'],
        'vacation_date' => ['type' => 'vacation', 'label' => 'Vacation begins'],
        'resumption_date' => ['type' => 'resumption', 'label' => 'School resumes'],
    ];

    foreach ($periods as $period) {
        foreach ($eventFields as $field => $definition) {
            $date = $period[$field] ?? null;
            if (!$date) {
                continue;
            }
            $events[] = [
                'date' => (string) $date,
                'type' => $definition['type'],
                'label' => $definition['label'],
                'session' => (string) $period['session_name'],
                'term' => (string) $period['term_name'],
                'is_current' => (int) $period['is_current'] === 1,
                'is_finalized' => (int) $period['is_finalized'] === 1,
            ];
        }
    }

    financeJson([
        'success' => true,
        'events' => $events,
        'current_period' => array_values(array_filter(
            $periods,
            static fn(array $period): bool => (int) $period['is_current'] === 1
        ))[0] ?? null,
    ]);
} catch (Throwable $error) {
    error_log('MARKAZ academic calendar: ' . $error->getMessage());
    financeJson([
        'success' => false,
        'code' => 'ACADEMIC_CALENDAR_UNAVAILABLE',
        'message' => 'The academic calendar could not be loaded. Confirm the Student Fees calendar database update has been applied.',
    ], 503);
}
